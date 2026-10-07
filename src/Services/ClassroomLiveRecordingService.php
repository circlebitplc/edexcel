<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Live class → existing class_recordings + Bunny paywall.
 * Joining live never marks a lesson paid.
 */
final class ClassroomLiveRecordingService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_classroom_schema')) {
            ensure_classroom_schema($this->pdo);
        }
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    /**
     * Start LiveKit room-composite egress. Auto-record still requires classroom auto_record
     * unless $manual (host Start). Already-running egress is a no-op.
     *
     * @param array<string,mixed> $lesson
     * @param array<string,mixed> $meeting
     * @return array{ok:bool,recording:bool,already:bool,skipped:bool,message:string,egress_id:string}
     */
    public function startForLiveClass(array $lesson, array $meeting, int $userId, bool $manual = false): array
    {
        $settings = function_exists('classroom_settings') ? classroom_settings($this->pdo) : [];
        if (!$manual && empty($settings['auto_record'])) {
            return $this->recordResult(true, false, '', '', false, true);
        }
        $skip = function_exists('livekit_record_skip_message')
            ? livekit_record_skip_message(function_exists('livekit_config') ? livekit_config($this->pdo) : [])
            : 'Recording to Bunny needs MinIO/S3 on the VPS. Classes can still run.';

        if (!function_exists('livekit_ready') || !livekit_ready($this->pdo)) {
            return $this->recordResult(false, false, $skip, '', false, true);
        }
        $egressId = trim((string)($meeting['egress_id'] ?? ''));
        if ($egressId !== '') {
            return $this->recordResult(true, true, 'This class is already being recorded.', $egressId, true, false);
        }
        $room = trim((string)($meeting['livekit_room'] ?? ''));
        $timetableId = (int)($lesson['id'] ?? $meeting['timetable_id'] ?? 0);
        $meetingId = (int)($meeting['id'] ?? 0);
        if ($room === '' || $timetableId < 1 || $meetingId < 1) {
            return $this->recordResult(false, false, 'Recording could not start. The class still runs.', '', false, true);
        }

        $cfg = livekit_config($this->pdo);
        $s3 = function_exists('livekit_s3_for_egress') ? livekit_s3_for_egress($cfg) : null;
        if (function_exists('livekit_can_auto_record') && !livekit_can_auto_record($cfg)) {
            error_log('LiveKit egress skipped: self-hosted recording needs MinIO/S3 (Admin → Live classroom). Class still runs.');
            $this->setRecordingRequested($meetingId, false);
            return $this->recordResult(false, false, $skip, '', false, true);
        }
        $egress = LiveKitEgressService::fromConfig($cfg);
        $path = 'eck/tt' . $timetableId . '-{time}';
        try {
            $info = $egress->startRoomComposite($room, $path, $s3);
        } catch (Throwable $e) {
            error_log('LiveKit egress start: ' . $e->getMessage());
            return $this->recordResult(false, false, $skip, '', false, true);
        }
        $id = LiveKitEgressService::egressIdFrom($info);
        if ($id === '') {
            return $this->recordResult(false, false, $skip, '', false, true);
        }
        $this->pdo->prepare("
            UPDATE online_meetings
            SET egress_id = ?, recording_requested = 1
            WHERE id = ?
        ")->execute([$id, $meetingId]);
        try {
            (new OnlineMeetingService($this->pdo))->event($meetingId, $userId, 'egress_start', ['egress_id' => $id]);
        } catch (Throwable $e) {
        }
        return $this->recordResult(true, true, 'Recording started.', $id, false, false);
    }

    /**
     * Stop LiveKit egress. Class end keeps egress_id for ingest; host Stop clears it so REC matches.
     *
     * @param array<string,mixed> $meeting
     * @return array{ok:bool,recording:bool,message:string}
     */
    public function stopForMeeting(array $meeting, bool $release = false): array
    {
        $egressId = trim((string)($meeting['egress_id'] ?? ''));
        $meetingId = (int)($meeting['id'] ?? 0);
        if ($egressId === '') {
            if ($release && $meetingId > 0) {
                $this->setRecordingRequested($meetingId, false);
            }
            return ['ok' => true, 'recording' => false, 'message' => 'Recording is not running.'];
        }
        if (function_exists('livekit_ready') && livekit_ready($this->pdo)) {
            try {
                LiveKitEgressService::fromConfig(livekit_config($this->pdo))->stop($egressId);
            } catch (Throwable $e) {
                if (!str_contains(strtolower($e->getMessage()), 'not found')) {
                    error_log('LiveKit egress stop: ' . $e->getMessage());
                }
            }
        }
        if ($release && $meetingId > 0) {
            $this->pdo->prepare("
                UPDATE online_meetings
                SET egress_id = NULL, recording_requested = 0
                WHERE id = ?
            ")->execute([$meetingId]);
            try {
                (new OnlineMeetingService($this->pdo))->event($meetingId, null, 'egress_stop', ['egress_id' => $egressId]);
            } catch (Throwable $e) {
            }
        }
        return ['ok' => true, 'recording' => false, 'message' => 'Recording stopped.'];
    }

    /**
     * @return array{ok:bool,recording:bool,already:bool,skipped:bool,message:string,egress_id:string}
     */
    private function recordResult(
        bool $ok,
        bool $recording,
        string $message,
        string $egressId = '',
        bool $already = false,
        bool $skipped = false
    ): array {
        return [
            'ok' => $ok,
            'recording' => $recording,
            'already' => $already,
            'skipped' => $skipped,
            'message' => $message,
            'egress_id' => $egressId,
        ];
    }

    private function setRecordingRequested(int $meetingId, bool $want): void
    {
        if ($meetingId < 1) {
            return;
        }
        try {
            $this->pdo->prepare("UPDATE online_meetings SET recording_requested = ? WHERE id = ?")
                ->execute([$want ? 1 : 0, $meetingId]);
        } catch (Throwable $e) {
        }
    }

    /**
     * @param array<string,mixed> $info
     */
    public function ingestCompletedEgress(array $info): bool
    {
        $cfg = livekit_config($this->pdo);
        $bucket = (string)($cfg['s3_bucket'] ?? '');
        if (!LiveKitEgressService::isComplete($info) && !LiveKitEgressService::hasUsableOutput($info, $bucket)) {
            return false;
        }
        $url = LiveKitEgressService::bunnyFetchUrl($info, $cfg);
        if ($url === '') {
            return false;
        }
        $egressId = LiveKitEgressService::egressIdFrom($info);
        $room = trim((string)($info['roomName'] ?? $info['room_name'] ?? ''));
        $meeting = $this->findMeeting($egressId, $room);
        if (!$meeting) {
            return false;
        }
        $meetings = new OnlineMeetingService($this->pdo);
        $lesson = $meetings->lesson((int)$meeting['timetable_id']);
        if (!$lesson) {
            return false;
        }
        $recordings = new RecordingService($this->pdo);
        $existingId = (int)($meeting['class_recording_id'] ?? 0);
        $recording = null;
        if ($existingId > 0) {
            $recording = $recordings->find($existingId);
            if ($recording && strtolower((string)($recording['status'] ?? '')) === 'ready') {
                return true;
            }
        }
        $teacherId = (int)($lesson['teacher_id'] ?? 0);
        $bunny = BunnyVideoService::tryForTeacher($this->pdo, $teacherId);
        if ($bunny === null) {
            error_log('Live class recording: teacher ' . $teacherId . ' has no Bunny library');
            return false;
        }
        if ($recording) {
            foreach ($recordings->assets((int)$recording['id']) as $asset) {
                $videoId = (string)($asset['bunny_video_id'] ?? '');
                $bs = (int)($asset['bunny_status'] ?? 0);
                if ($videoId === '') {
                    continue;
                }
                if ($bs >= 3 && $bs !== 5 && $bs !== 8) {
                    return true;
                }
                $bunny->fetchFromUrl($videoId, $url);
                $recordings->markUploadFinished((int)$recording['id'], $videoId);
                try {
                    $video = $bunny->getVideo($videoId);
                    $recordings->applyBunnyMetadata($videoId, $video, $bunny);
                } catch (Throwable $e) {
                }
                return true;
            }
        }

        $hostId = (int)($meeting['host_user_id'] ?? 0);
        $recording = $recordings->getOrCreateForLesson($lesson, $hostId);
        $title = (string)($recording['title'] ?? 'Live class');
        $created = $bunny->createVideo($title);
        $videoId = (string)($created['guid'] ?? '');
        if ($videoId === '') {
            throw new RuntimeException('Bunny did not return a video ID.');
        }
        $recordings->addAsset((int)$recording['id'], $videoId, $bunny->libraryId(), $title);
        $this->pdo->prepare('UPDATE online_meetings SET class_recording_id = ? WHERE id = ?')
            ->execute([(int)$recording['id'], (int)$meeting['id']]);

        $bunny->fetchFromUrl($videoId, $url);
        $recordings->markUploadFinished((int)$recording['id'], $videoId);
        try {
            $video = $bunny->getVideo($videoId);
            $recordings->applyBunnyMetadata($videoId, $video, $bunny);
        } catch (Throwable $e) {
        }
        try {
            $meetings->event((int)$meeting['id'], null, 'egress_ingest', [
                'egress_id' => $egressId,
                'recording_id' => (int)$recording['id'],
            ]);
        } catch (Throwable $e) {
        }
        return true;
    }

    public function recoverRecent(): int
    {
        if (!function_exists('livekit_ready') || !livekit_ready($this->pdo)) {
            return 0;
        }
        $stmt = $this->pdo->query("
            SELECT om.*
            FROM online_meetings om
            LEFT JOIN class_recordings cr ON cr.id = om.class_recording_id
            WHERE om.egress_id IS NOT NULL AND om.egress_id <> ''
              AND om.started_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
              AND (
                    om.class_recording_id IS NULL
                    OR cr.status IS NULL
                    OR cr.status IN ('draft','uploading','processing')
                  )
            ORDER BY om.id DESC
            LIMIT 8
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $ok = 0;
        $egress = LiveKitEgressService::fromConfig(livekit_config($this->pdo));
        foreach ($rows as $meeting) {
            $id = trim((string)($meeting['egress_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            try {
                $info = $egress->get($id);
                $info['egressId'] = $info['egressId'] ?? $id;
                $info['roomName'] = $info['roomName'] ?? (string)($meeting['livekit_room'] ?? '');
                if ($this->ingestCompletedEgress($info)) {
                    $ok++;
                }
            } catch (Throwable $e) {
                error_log('Live class recording recover: ' . $e->getMessage());
            }
        }
        return $ok;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findMeeting(string $egressId, string $room): ?array
    {
        if ($egressId !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM online_meetings WHERE egress_id = ? LIMIT 1');
            $stmt->execute([$egressId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        if ($room !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM online_meetings WHERE livekit_room = ? LIMIT 1');
            $stmt->execute([$room]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        return null;
    }
}
