<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class LiveKitWebhookHandler
{
    public function __construct(private PDO $pdo)
    {
        date_default_timezone_set('Asia/Colombo');
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function handle(string $rawBody, string $authorizationHeader): array
    {
        $cfg = livekit_config($this->pdo);
        $jwt = self::bearerToken($authorizationHeader);
        if ($jwt === '' || !self::verify($jwt, $rawBody, (string)$cfg['api_key'], (string)$cfg['api_secret'])) {
            return ['ok' => false, 'message' => 'invalid signature'];
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'message' => 'invalid json'];
        }
        $event = strtolower((string)($payload['event'] ?? ''));
        if ($event === 'participant_joined') {
            $this->revokeIfUnauthorized($payload);
            return ['ok' => true, 'message' => 'participant checked'];
        }
        $info = $payload['egressInfo'] ?? $payload['egress_info'] ?? null;
        if (!is_array($info)) {
            $info = $payload;
        }
        if (!str_contains($event, 'egress') && LiveKitEgressService::egressIdFrom($info) === '') {
            return ['ok' => true, 'message' => 'ignored'];
        }
        if (LiveKitEgressService::isFailed($info)) {
            return ['ok' => true, 'message' => 'egress failed'];
        }
        $bucket = (string)($cfg['s3_bucket'] ?? '');
        if (!LiveKitEgressService::isComplete($info) && !LiveKitEgressService::hasUsableOutput($info, $bucket)) {
            return ['ok' => true, 'message' => 'not complete'];
        }
        try {
            $ingested = (new ClassroomLiveRecordingService($this->pdo))->ingestCompletedEgress($info);
            return ['ok' => true, 'message' => $ingested ? 'ingested' : 'skipped'];
        } catch (Throwable $e) {
            error_log('LiveKit webhook ingest: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'ingest failed'];
        }
    }

    /**
     * A LiveKit JWT cannot be expired early. When someone joins or rejoins,
     * drop them if enrollment, payment, kick, or cancellation no longer allows access.
     * Hosts are left in the room. A fee lookup that fails does not disconnect the class.
     *
     * @param array<string,mixed> $payload
     */
    private function revokeIfUnauthorized(array $payload): void
    {
        $roomNode = $payload['room'] ?? [];
        $participant = $payload['participant'] ?? [];
        if (!is_array($roomNode) || !is_array($participant)) {
            return;
        }
        $room = trim((string)($roomNode['name'] ?? ''));
        $identity = trim((string)($participant['identity'] ?? ''));
        if ($room === '' || !function_exists('classroom_user_id_from_identity')) {
            return;
        }
        $userId = classroom_user_id_from_identity($identity);
        if ($userId < 1) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.id AS meeting_id, m.status AS meeting_status, m.timetable_id,
                       tt.class_id, tt.teacher_id, tt.substitute_teacher_id, tt.date,
                       tt.delivery_mode, tt.lesson_status
                FROM online_meetings m
                INNER JOIN timetable tt ON tt.id = m.timetable_id
                WHERE m.livekit_room = ?
                ORDER BY m.id DESC
                LIMIT 1
            ");
            $stmt->execute([$room]);
            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                return;
            }
            $userStmt = $this->pdo->prepare('SELECT role, teacher_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $this->removeJoined($room, $identity);
                return;
            }
            $role = strtolower((string)$user['role']);
            if ($role === 'admin') {
                return;
            }
            if ($role === 'teacher' && RecordingAccessService::teacherCanManageLesson(
                (int)($user['teacher_id'] ?? 0),
                (int)($lesson['teacher_id'] ?? 0),
                false,
                (int)($lesson['substitute_teacher_id'] ?? 0)
            )) {
                return;
            }
            if ($role !== 'student') {
                $this->removeJoined($room, $identity);
                return;
            }
            $meetingStatus = strtolower((string)($lesson['meeting_status'] ?? ''));
            if (in_array($meetingStatus, ['cancelled', 'ended'], true)) {
                $this->removeJoined($room, $identity);
                return;
            }
            if (!function_exists('classroom_student_enrolled')
                || !classroom_student_enrolled($this->pdo, $userId, (int)$lesson['class_id'], (int)($lesson['teacher_id'] ?? 0))
            ) {
                $this->removeJoined($room, $identity);
                return;
            }
            $part = (new OnlineMeetingService($this->pdo))->participantState((int)$lesson['meeting_id'], $userId);
            if ((int)($part['kicked'] ?? 0) === 1) {
                $this->removeJoined($room, $identity);
                return;
            }
            $lesson['id'] = (int)$lesson['timetable_id'];
            $resolved = (new StudentLessonFeeService($this->pdo))->resolve($userId, $lesson);
            $status = strtolower((string)($resolved['status'] ?? 'unpaid'));
            $unlocked = StudentLessonFeeService::isUnlocked($status, (bool)($resolved['covered_by_monthly'] ?? false))
                || (float)($resolved['amount_due'] ?? 0) <= 0;
            if (!$unlocked && !in_array($status, ['paid', 'waived'], true)) {
                $this->removeJoined($room, $identity);
            }
        } catch (Throwable $e) {
            error_log('livekit participant recheck: ' . $e->getMessage());
        }
    }

    private function removeJoined(string $room, string $identity): void
    {
        if (function_exists('classroom_remove_livekit_identity')) {
            classroom_remove_livekit_identity($this->pdo, $room, $identity);
        }
    }

    public static function bearerToken(string $header): string
    {
        $header = trim($header);
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return $header;
    }

    public static function verify(string $jwt, string $body, string $apiKey, string $apiSecret): bool
    {
        if ($apiSecret === '' || !LiveKitTokenService::verify($jwt, $apiSecret)) {
            return false;
        }
        $claims = LiveKitTokenService::decodeUnverified($jwt);
        if (!is_array($claims)) {
            return false;
        }
        $iss = (string)($claims['iss'] ?? '');
        if ($iss !== '' && $apiKey !== '' && $iss !== $apiKey) {
            return false;
        }
        $exp = (int)($claims['exp'] ?? 0);
        if ($exp < 1 || $exp < (time() - 30)) {
            return false;
        }
        $claimed = (string)($claims['sha256'] ?? $claims['sha_256'] ?? '');
        if ($claimed === '') {
            return false;
        }
        $hex = hash('sha256', $body);
        $b64 = base64_encode(hash('sha256', $body, true));
        $b64url = rtrim(strtr($b64, '+/', '-_'), '=');
        return hash_equals($claimed, $hex)
            || hash_equals($claimed, $b64)
            || hash_equals($claimed, $b64url);
    }
}
