<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class OnlineMeetingService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_classroom_schema') && !$this->pdo->inTransaction()) {
            ensure_classroom_schema($this->pdo);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function ensureForLesson(int $timetableId): ?array
    {
        $lesson = $this->lesson($timetableId);
        if (!$lesson) {
            return null;
        }
        $mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
        if (!in_array($mode, ['online', 'hybrid'], true)) {
            return $this->findByLesson($timetableId);
        }
        $existing = $this->findByLesson($timetableId);
        if ($existing) {
            return $existing;
        }
        $publicId = bin2hex(random_bytes(8));
        $room = 'eck-' . $publicId;
        $waiting = 0;
        try {
            $settings = classroom_settings($this->pdo);
            $waiting = !empty($settings['waiting_room']) ? 1 : 0;
        } catch (Throwable $e) {
            $waiting = 0;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO online_meetings (timetable_id, public_id, livekit_room, status, waiting_room)
            VALUES (?, ?, ?, 'scheduled', ?)
        ");
        $stmt->execute([$timetableId, $publicId, $room, $waiting]);
        return $this->findByLesson($timetableId);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByLesson(int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM online_meetings WHERE timetable_id = ? LIMIT 1");
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM online_meetings WHERE public_id = ? LIMIT 1");
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function lesson(int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT tt.*, s.name AS subject_name, c.name AS class_name,
                   t.name AS teacher_name, r.name AS room_name,
                   st.name AS substitute_name
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            LEFT JOIN rooms r ON r.id = tt.room_id
            LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id
            WHERE tt.id = ? AND tt.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>
     */
    public function start(int $timetableId, int $userId): array
    {
        $meeting = $this->ensureForLesson($timetableId);
        if (!$meeting) {
            throw new RuntimeException('This lesson is not an online class.');
        }
        if (($meeting['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('This online class was cancelled.');
        }
        $wasEnded = strtolower((string)($meeting['status'] ?? '')) === 'ended';
        if ($wasEnded) {
            $stmt = $this->pdo->prepare("
                UPDATE online_meetings
                SET status = 'live', host_user_id = ?, started_at = NOW(), ended_at = NULL, egress_id = NULL
                WHERE id = ?
            ");
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE online_meetings
                SET status = 'live', host_user_id = ?, started_at = COALESCE(started_at, NOW()), ended_at = NULL
                WHERE id = ?
            ");
        }
        $stmt->execute([$userId, (int)$meeting['id']]);
        $this->event((int)$meeting['id'], $userId, 'start', null);
        $fresh = $this->findByLesson($timetableId);
        return $fresh ?: $meeting;
    }

    /**
     * @return array<string,mixed>
     */
    public function end(int $timetableId, int $userId): array
    {
        $meeting = $this->findByLesson($timetableId);
        if (!$meeting) {
            throw new RuntimeException('Online class not found.');
        }
        $this->pdo->prepare("
            UPDATE online_meetings
            SET status = 'ended', ended_at = NOW()
            WHERE id = ?
        ")->execute([(int)$meeting['id']]);
        $this->closeOpenParticipants((int)$meeting['id']);
        $this->event((int)$meeting['id'], $userId, 'end', null);
        $fresh = $this->findByLesson($timetableId) ?: $meeting;
        try {
            (new ClassroomLiveRecordingService($this->pdo))->stopForMeeting($fresh);
        } catch (Throwable $e) {
            error_log('Live class recording stop: ' . $e->getMessage());
        }
        $cfg = livekit_config($this->pdo);
        if (livekit_ready($this->pdo)) {
            try {
                LiveKitRoomService::fromConfig($cfg)->deleteRoom((string)$meeting['livekit_room']);
            } catch (Throwable $e) {
                error_log('LiveKit deleteRoom: ' . $e->getMessage());
            }
        }
        $attendance = new ClassroomAttendanceService($this->pdo);
        $attendance->finalizeMeeting((int)$meeting['id']);
        return $this->findByLesson($timetableId) ?: $meeting;
    }

    public function setLocked(int $meetingId, bool $locked, int $userId): void
    {
        $this->pdo->prepare("UPDATE online_meetings SET locked = ? WHERE id = ?")
            ->execute([$locked ? 1 : 0, $meetingId]);
        $this->event($meetingId, $userId, $locked ? 'lock' : 'unlock', null);
    }

    public function setWaitingRoom(int $meetingId, bool $on, int $userId): void
    {
        $this->pdo->prepare("UPDATE online_meetings SET waiting_room = ? WHERE id = ?")
            ->execute([$on ? 1 : 0, $meetingId]);
        $this->event($meetingId, $userId, $on ? 'waiting_on' : 'waiting_off', null);
    }

    /**
     * @param array<string,mixed>|null $payload
     */
    public function event(int $meetingId, ?int $userId, string $type, ?array $payload): void
    {
        $json = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare("
            INSERT INTO meeting_events (meeting_id, user_id, event_type, payload)
            VALUES (?, ?, ?, ?)
        ")->execute([$meetingId, $userId, $type, $json]);
    }

    /**
     * @return array<string,mixed>
     */
    public function touchParticipant(int $meetingId, int $userId, string $role, string $identity): array
    {
        $existing = $this->pdo->prepare("
            SELECT * FROM meeting_participants WHERE meeting_id = ? AND user_id = ? LIMIT 1
        ");
        $existing->execute([$meetingId, $userId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->pdo->prepare("
                INSERT INTO meeting_participants
                    (meeting_id, user_id, role, livekit_identity, joined_at, last_seen_at, waiting_status, segment_started_at)
                VALUES (?, ?, ?, ?, NOW(), NOW(), 'admitted', NOW())
            ")->execute([$meetingId, $userId, $role, $identity]);
            $this->event($meetingId, $userId, 'join', $this->presencePayload());
        } else {
            $reconnect = (int)$row['reconnect_count'];
            $left = $row['left_at'] ?? null;
            $fromWait = strtolower((string)($row['waiting_status'] ?? '')) === 'waiting';
            if ($left) {
                if (!$fromWait) {
                    $reconnect++;
                }
                $this->pdo->prepare("
                    UPDATE meeting_participants
                    SET left_at = NULL, last_seen_at = NOW(), segment_started_at = NOW(), reconnect_count = ?,
                        livekit_identity = ?, role = ?, waiting_status = 'admitted'"
                    . ($fromWait ? ', joined_at = NOW()' : '') . "
                    WHERE id = ?
                ")->execute([$reconnect, $identity, $role, (int)$row['id']]);
                $this->event($meetingId, $userId, $fromWait ? 'join' : 'rejoin', $this->presencePayload($reconnect));
            } else {
                $this->pdo->prepare("
                    UPDATE meeting_participants
                    SET last_seen_at = NOW(), livekit_identity = ?, waiting_status = 'admitted'
                    WHERE id = ?
                ")->execute([$identity, (int)$row['id']]);
            }
        }
        $existing->execute([$meetingId, $userId]);
        return $existing->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function leaveParticipant(int $meetingId, int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, joined_at, duration_seconds, left_at, segment_started_at
                FROM meeting_participants
                WHERE meeting_id = ? AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$meetingId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $stmt = $this->pdo->prepare("
                SELECT id, joined_at, duration_seconds, left_at
                FROM meeting_participants
                WHERE meeting_id = ? AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$meetingId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$row || !empty($row['left_at'])) {
            return;
        }
        $segment = strtotime((string)($row['segment_started_at'] ?? '')) ?: (strtotime((string)$row['joined_at']) ?: time());
        $extra = max(0, time() - $segment);
        $total = (int)$row['duration_seconds'] + $extra;
        $this->pdo->prepare("
            UPDATE meeting_participants
            SET left_at = NOW(), last_seen_at = NOW(), duration_seconds = ?, hand_raised = 0, hand_raised_at = NULL
            WHERE id = ?
        ")->execute([$total, (int)$row['id']]);
        $this->event($meetingId, $userId, 'leave', ['duration_seconds' => $total]);
    }

    /**
     * @return array<string,mixed>
     */
    private function presencePayload(int $reconnect = 0): array
    {
        $payload = ['device' => StudentDeviceService::publicLabel((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))];
        if ($reconnect > 0) {
            $payload['reconnect'] = $reconnect;
        }
        return $payload;
    }

    public function setHand(int $meetingId, int $userId, bool $raised): void
    {
        if ($raised) {
            $this->pdo->prepare("
                UPDATE meeting_participants
                SET hand_raised = 1, hand_raised_at = COALESCE(hand_raised_at, NOW())
                WHERE meeting_id = ? AND user_id = ?
            ")->execute([$meetingId, $userId]);
        } else {
            $this->pdo->prepare("
                UPDATE meeting_participants
                SET hand_raised = 0, hand_raised_at = NULL
                WHERE meeting_id = ? AND user_id = ?
            ")->execute([$meetingId, $userId]);
        }
        $this->event($meetingId, $userId, $raised ? 'raise_hand' : 'lower_hand', null);
    }

    public function kick(int $meetingId, int $targetUserId, int $actorId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id, joined_at, duration_seconds, left_at
            FROM meeting_participants
            WHERE meeting_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$meetingId, $targetUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $row ? (int)$row['duration_seconds'] : 0;
        if ($row && empty($row['left_at'])) {
            $joined = strtotime((string)$row['joined_at']) ?: time();
            $total += max(0, time() - $joined);
        }
        $this->pdo->prepare("
            UPDATE meeting_participants
            SET kicked = 1, left_at = NOW(), last_seen_at = NOW(), duration_seconds = ?,
                hand_raised = 0, hand_raised_at = NULL
            WHERE meeting_id = ? AND user_id = ?
        ")->execute([$total, $meetingId, $targetUserId]);
        $this->event($meetingId, $actorId, 'kick', ['target_user_id' => $targetUserId]);
        try {
            $roomStmt = $this->pdo->prepare('SELECT livekit_room FROM online_meetings WHERE id = ? LIMIT 1');
            $roomStmt->execute([$meetingId]);
            $room = trim((string)$roomStmt->fetchColumn());
            if ($room !== '' && function_exists('classroom_identity') && function_exists('classroom_remove_livekit_identity')) {
                classroom_remove_livekit_identity($this->pdo, $room, classroom_identity($targetUserId));
            }
        } catch (Throwable $e) {
            error_log('kick livekit remove: ' . $e->getMessage());
        }
    }

    public function isKicked(int $meetingId, int $userId): bool
    {
        $row = $this->participantState($meetingId, $userId);
        return (int)($row['kicked'] ?? 0) === 1;
    }

    /**
     * @return array<string,mixed>
     */
    public function participantState(int $meetingId, int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM meeting_participants WHERE meeting_id = ? AND user_id = ? LIMIT 1
        ");
        $stmt->execute([$meetingId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * Record a student in the waiting room without joining LiveKit.
     * Does not overwrite admitted or denied, and does not demote someone already in class.
     */
    public function markWaiting(int $meetingId, int $userId, string $identity): void
    {
        $row = $this->participantState($meetingId, $userId);
        if ($row === []) {
            $this->pdo->prepare("
                INSERT INTO meeting_participants
                    (meeting_id, user_id, role, livekit_identity, joined_at, left_at, last_seen_at, waiting_status)
                VALUES (?, ?, 'student', ?, NOW(), NOW(), NOW(), 'waiting')
            ")->execute([$meetingId, $userId, $identity]);
            $this->event($meetingId, $userId, 'waiting', null);
            return;
        }
        if ((int)($row['kicked'] ?? 0) === 1) {
            return;
        }
        $st = strtolower((string)($row['waiting_status'] ?? 'none'));
        if ($st === 'admitted' || $st === 'denied') {
            return;
        }
        if ($st === 'none' && empty($row['left_at'])) {
            return;
        }
        if ($st === 'none' && (int)($row['duration_seconds'] ?? 0) > 0) {
            $this->pdo->prepare("
                UPDATE meeting_participants SET waiting_status = 'admitted', last_seen_at = NOW()
                WHERE id = ?
            ")->execute([(int)$row['id']]);
            return;
        }
        $this->pdo->prepare("
            UPDATE meeting_participants
            SET waiting_status = 'waiting', last_seen_at = NOW(), livekit_identity = ?,
                left_at = COALESCE(left_at, NOW())
            WHERE id = ?
        ")->execute([$identity, (int)$row['id']]);
        if ($st !== 'waiting') {
            $this->event($meetingId, $userId, 'waiting', null);
        }
    }

    public function admit(int $meetingId, int $targetUserId, int $actorId): void
    {
        $this->pdo->prepare("
            UPDATE meeting_participants
            SET waiting_status = 'admitted', last_seen_at = NOW()
            WHERE meeting_id = ? AND user_id = ? AND kicked = 0
        ")->execute([$meetingId, $targetUserId]);
        $this->event($meetingId, $actorId, 'admit', ['target_user_id' => $targetUserId]);
    }

    public function denyWaiting(int $meetingId, int $targetUserId, int $actorId): void
    {
        $this->pdo->prepare("
            UPDATE meeting_participants
            SET waiting_status = 'denied', left_at = COALESCE(left_at, NOW()), last_seen_at = NOW(),
                hand_raised = 0, hand_raised_at = NULL
            WHERE meeting_id = ? AND user_id = ? AND kicked = 0
        ")->execute([$meetingId, $targetUserId]);
        $this->event($meetingId, $actorId, 'deny', ['target_user_id' => $targetUserId]);
    }

    /**
     * @return list<array{user_id:int,display_name:string,waiting_status:string,last_seen_at:?string}>
     */
    public function waitingStudents(int $meetingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT mp.user_id, mp.waiting_status, mp.last_seen_at,
                   COALESCE(sp.full_name, u.username) AS display_name
            FROM meeting_participants mp
            JOIN users u ON u.id = mp.user_id
            LEFT JOIN student_profiles sp ON sp.user_id = mp.user_id
            WHERE mp.meeting_id = ?
              AND mp.role = 'student'
              AND mp.kicked = 0
              AND mp.waiting_status IN ('waiting', 'denied')
            ORDER BY mp.waiting_status ASC, mp.last_seen_at ASC, mp.id ASC
        ");
        $stmt->execute([$meetingId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'user_id' => (int)$row['user_id'],
                'display_name' => (string)($row['display_name'] ?? 'Student'),
                'waiting_status' => (string)($row['waiting_status'] ?? 'waiting'),
                'last_seen_at' => $row['last_seen_at'] ?? null,
            ];
        }
        return $out;
    }

    public function setCameraBlocked(int $meetingId, int $userId, bool $blocked, int $actorId): void
    {
        $this->pdo->prepare("
            UPDATE meeting_participants SET camera_blocked = ? WHERE meeting_id = ? AND user_id = ?
        ")->execute([$blocked ? 1 : 0, $meetingId, $userId]);
        $this->event($meetingId, $actorId, $blocked ? 'block_camera' : 'unblock_camera', [
            'target_user_id' => $userId,
        ]);
    }

    public function isCameraBlocked(int $meetingId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT camera_blocked FROM meeting_participants WHERE meeting_id = ? AND user_id = ? LIMIT 1
            ");
            $stmt->execute([$meetingId, $userId]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function setScreenshareAllowed(int $meetingId, int $userId, bool $allowed, int $actorId): void
    {
        $this->pdo->prepare("
            UPDATE meeting_participants SET screenshare_allowed = ? WHERE meeting_id = ? AND user_id = ?
        ")->execute([$allowed ? 1 : 0, $meetingId, $userId]);
        $this->event($meetingId, $actorId, $allowed ? 'allow_share' : 'revoke_share', [
            'target_user_id' => $userId,
        ]);
    }

    public function setMicBlocked(int $meetingId, int $userId, bool $blocked, int $actorId): void
    {
        $this->updateParticipantFlag('mic_blocked', $meetingId, $userId, $blocked);
        try {
            $this->event($meetingId, $actorId, $blocked ? 'mute' : 'allow_mic', [
                'target_user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('mic_blocked event: ' . $e->getMessage());
        }
    }

    private function updateParticipantFlag(string $column, int $meetingId, int $userId, bool $value): void
    {
        $allowed = ['mic_blocked' => true, 'camera_blocked' => true, 'screenshare_allowed' => true];
        if (!isset($allowed[$column])) {
            error_log('Unknown participant flag: ' . $column);
            return;
        }
        $sql = "UPDATE meeting_participants SET `{$column}` = ? WHERE meeting_id = ? AND user_id = ?";
        $run = function () use ($sql, $value, $meetingId, $userId): void {
            $this->pdo->prepare($sql)->execute([$value ? 1 : 0, $meetingId, $userId]);
        };
        try {
            $run();
            return;
        } catch (Throwable $e) {
            $this->addParticipantColumn($column);
            if (function_exists('ensure_classroom_schema')) {
                try {
                    ensure_classroom_schema($this->pdo, true);
                } catch (Throwable $schemaErr) {
                    error_log('participant flag schema: ' . $schemaErr->getMessage());
                }
            }
            try {
                $run();
            } catch (Throwable $e2) {
                error_log('participant flag ' . $column . ': ' . $e2->getMessage());
            }
        }
    }

    private function addParticipantColumn(string $column): void
    {
        $defs = [
            'mic_blocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'camera_blocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'screenshare_allowed' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
        if (!isset($defs[$column])) {
            return;
        }
        if (function_exists('classroom_try_add_column')) {
            classroom_try_add_column($this->pdo, 'meeting_participants', $column, $defs[$column]);
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `meeting_participants` ADD COLUMN `{$column}` {$defs[$column]}");
        } catch (Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (!str_contains($msg, 'duplicate') && !str_contains($msg, 'exists')) {
                error_log('ADD COLUMN meeting_participants.' . $column . ': ' . $e->getMessage());
            }
        }
    }

    public function isMicBlocked(int $meetingId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mic_blocked FROM meeting_participants WHERE meeting_id = ? AND user_id = ? LIMIT 1
            ");
            $stmt->execute([$meetingId, $userId]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isScreenshareAllowed(int $meetingId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT screenshare_allowed FROM meeting_participants WHERE meeting_id = ? AND user_id = ? LIMIT 1
            ");
            $stmt->execute([$meetingId, $userId]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function participantRole(int $meetingId, int $userId): string
    {
        $stmt = $this->pdo->prepare("
            SELECT role FROM meeting_participants WHERE meeting_id = ? AND user_id = ? LIMIT 1
        ");
        $stmt->execute([$meetingId, $userId]);
        return (string)$stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function raisedHands(int $meetingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT mp.user_id, mp.role, COALESCE(sp.full_name, u.username) AS display_name, mp.hand_raised_at
            FROM meeting_participants mp
            JOIN users u ON u.id = mp.user_id
            LEFT JOIN student_profiles sp ON sp.user_id = mp.user_id
            WHERE mp.meeting_id = ? AND mp.hand_raised = 1 AND mp.left_at IS NULL
            ORDER BY mp.hand_raised_at ASC, mp.id ASC
        ");
        $stmt->execute([$meetingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function liveMeetings(): array
    {
        $sql = "
            SELECT om.*, tt.date, tt.start_time, tt.end_time, tt.teacher_id, tt.class_id,
                   s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
                   (SELECT COUNT(*) FROM meeting_participants mp
                    WHERE mp.meeting_id = om.id AND mp.left_at IS NULL
                      AND COALESCE(mp.waiting_status, 'none') IN ('none', 'admitted')) AS in_room
            FROM online_meetings om
            JOIN timetable tt ON tt.id = om.timetable_id AND tt.deleted_at IS NULL
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            WHERE om.status = 'live'
            ORDER BY tt.start_time
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function closeOpenParticipants(int $meetingId): void
    {
        $rows = $this->pdo->prepare("
            SELECT id, user_id, joined_at, duration_seconds
            FROM meeting_participants
            WHERE meeting_id = ? AND left_at IS NULL
        ");
        $rows->execute([$meetingId]);
        $upd = $this->pdo->prepare("
            UPDATE meeting_participants
            SET left_at = NOW(), duration_seconds = ?, hand_raised = 0
            WHERE id = ?
        ");
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $joined = strtotime((string)$row['joined_at']) ?: time();
            $total = (int)$row['duration_seconds'] + max(0, time() - $joined);
            $upd->execute([$total, (int)$row['id']]);
        }
    }
}
