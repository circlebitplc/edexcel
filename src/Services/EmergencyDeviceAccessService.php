<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use PDOException;
use Throwable;

/**
 * One temporary device for one live class. The student's permanent limit stays at 2.
 * Approval is a row in emergency_device_requests. The popup only displays that row.
 */
final class EmergencyDeviceAccessService
{
    public const REQUEST_TTL_SECONDS = 120;
    public const ACCESS_CAP_SECONDS = 10800;

    public function __construct(private PDO $pdo)
    {
        self::ensureSchema($this->pdo);
    }

    public static function ensureSchema(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            return;
        }
        static $done = [];
        $key = spl_object_id($pdo);
        if (isset($done[$key])) {
            return;
        }
        $done[$key] = true;
        $mysql = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
        if ($mysql) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS emergency_device_requests (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    student_name VARCHAR(160) NOT NULL DEFAULT '',
                    device_key CHAR(64) NOT NULL,
                    device_label VARCHAR(120) NOT NULL DEFAULT '',
                    timetable_id INT NOT NULL,
                    meeting_id INT NULL,
                    lesson_teacher_id INT NOT NULL DEFAULT 0,
                    substitute_teacher_id INT NOT NULL DEFAULT 0,
                    class_label VARCHAR(160) NOT NULL DEFAULT '',
                    teacher_name VARCHAR(160) NOT NULL DEFAULT '',
                    lesson_date DATE NULL,
                    end_time VARCHAR(16) NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'pending',
                    reason VARCHAR(255) NULL,
                    requested_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    decided_at DATETIME NULL,
                    decided_by_user_id INT NULL,
                    decided_by_role VARCHAR(32) NULL,
                    decided_by_name VARCHAR(160) NULL,
                    access_until DATETIME NULL,
                    ip_address VARCHAR(45) NULL,
                    pending_key CHAR(64) NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_emergency_device_pending (pending_key),
                    KEY idx_emergency_device_user (user_id, timetable_id, status),
                    KEY idx_emergency_device_teacher (lesson_teacher_id, status, expires_at),
                    KEY idx_emergency_device_status (status, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return;
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS emergency_device_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                student_name VARCHAR(160) NOT NULL DEFAULT '',
                device_key CHAR(64) NOT NULL,
                device_label VARCHAR(120) NOT NULL DEFAULT '',
                timetable_id INT NOT NULL,
                meeting_id INT NULL,
                lesson_teacher_id INT NOT NULL DEFAULT 0,
                substitute_teacher_id INT NOT NULL DEFAULT 0,
                class_label VARCHAR(160) NOT NULL DEFAULT '',
                teacher_name VARCHAR(160) NOT NULL DEFAULT '',
                lesson_date TEXT NULL,
                end_time VARCHAR(16) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                reason VARCHAR(255) NULL,
                requested_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                decided_at DATETIME NULL,
                decided_by_user_id INT NULL,
                decided_by_role VARCHAR(32) NULL,
                decided_by_name VARCHAR(160) NULL,
                access_until DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                pending_key CHAR(64) NULL UNIQUE,
                created_at DATETIME NOT NULL
            )
        ");
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildContext(int $timetableId, int $studentUserId): ?array
    {
        if ($timetableId < 1 || $studentUserId < 1 || !class_exists(OnlineMeetingService::class)) {
            return null;
        }
        try {
            $meetings = new OnlineMeetingService($this->pdo);
            $lesson = $meetings->lesson($timetableId);
            if (!$lesson) {
                return null;
            }
            $meeting = $meetings->findByLesson($timetableId);
            $status = strtolower((string)($meeting['status'] ?? ''));
            if ($status !== 'live') {
                return null;
            }
            $enrolled = function_exists('classroom_student_enrolled')
                && classroom_student_enrolled(
                    $this->pdo,
                    $studentUserId,
                    (int)($lesson['class_id'] ?? 0),
                    (int)($lesson['teacher_id'] ?? 0)
                );
            if (!$enrolled) {
                return null;
            }
            $classLabel = trim((string)($lesson['subject_name'] ?? '') . ' ' . (string)($lesson['class_name'] ?? ''));
            return [
                'timetable_id' => $timetableId,
                'meeting_id' => (int)($meeting['id'] ?? 0),
                'meeting_status' => $status,
                'enrolled' => true,
                'teacher_id' => (int)($lesson['teacher_id'] ?? 0),
                'substitute_teacher_id' => (int)($lesson['substitute_teacher_id'] ?? 0),
                'teacher_name' => trim((string)($lesson['teacher_name'] ?? '')),
                'class_label' => $classLabel !== '' ? $classLabel : 'Live class',
                'lesson_date' => (string)($lesson['date'] ?? ''),
                'end_time' => substr((string)($lesson['end_time'] ?? ''), 0, 8),
                'student_name' => $this->studentName($studentUserId),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $context
     * @return array{status:string,message:string,request_id:int,duplicate:bool,expires_at:string}
     */
    public function requestAccess(int $userId, array $context): array
    {
        $this->expireStale();
        $timetableId = (int)($context['timetable_id'] ?? 0);
        $unavailable = [
            'status' => 'unavailable',
            'message' => '',
            'request_id' => 0,
            'duplicate' => false,
            'expires_at' => '',
        ];
        if ($userId < 1 || $timetableId < 1) {
            return $unavailable;
        }
        if (strtolower((string)($context['meeting_status'] ?? '')) !== 'live' || empty($context['enrolled'])) {
            return $unavailable;
        }
        $devices = new StudentDeviceService($this->pdo);
        if ($devices->currentDeviceBlocked($userId) || $devices->currentDeviceVerified($userId)) {
            return $unavailable;
        }
        if ($devices->verifiedCount($userId) < StudentDeviceService::MAX_DEVICES) {
            return $unavailable;
        }
        $deviceKey = $devices->currentDeviceKey();
        if (!preg_match('/^[a-f0-9]{64}$/', $deviceKey)) {
            return $unavailable;
        }
        $open = $this->allows($userId, $timetableId, $deviceKey);
        if ($open) {
            $latest = $this->latestForDevice($userId, $timetableId, $deviceKey);
            return [
                'status' => 'approved',
                'message' => 'This device is approved for this class.',
                'request_id' => (int)($latest['id'] ?? 0),
                'duplicate' => false,
                'expires_at' => (string)($latest['expires_at'] ?? ''),
            ];
        }
        $pending = $this->findPending($userId, $timetableId, $deviceKey);
        if ($pending) {
            return [
                'status' => 'pending',
                'message' => 'Your request is already waiting for approval.',
                'request_id' => (int)$pending['id'],
                'duplicate' => true,
                'expires_at' => (string)$pending['expires_at'],
            ];
        }
        $recent = $this->pdo->prepare('SELECT COUNT(*) FROM emergency_device_requests WHERE user_id = ? AND timetable_id = ? AND created_at >= ?');
        $recent->execute([$userId, $timetableId, date('Y-m-d H:i:s', time() - 600)]);
        if ((int)$recent->fetchColumn() >= 4) {
            return [
                'status' => 'unavailable',
                'message' => 'Please wait a few minutes before asking again.',
                'request_id' => 0,
                'duplicate' => false,
                'expires_at' => '',
            ];
        }
        $now = $this->now();
        $expires = date('Y-m-d H:i:s', time() + self::REQUEST_TTL_SECONDS);
        $studentName = trim((string)($context['student_name'] ?? ''));
        if ($studentName === '') {
            $studentName = $this->studentName($userId);
        }
        $label = StudentDeviceService::publicLabel((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $pendingKey = hash('sha256', $userId . '|' . $timetableId . '|' . $deviceKey);
        try {
            $this->pdo->beginTransaction();
            $pending = $this->findPending($userId, $timetableId, $deviceKey);
            if ($pending) {
                $this->pdo->commit();
                return [
                    'status' => 'pending',
                    'message' => 'Your request is already waiting for approval.',
                    'request_id' => (int)$pending['id'],
                    'duplicate' => true,
                    'expires_at' => (string)$pending['expires_at'],
                ];
            }
            $stmt = $this->pdo->prepare("
                INSERT INTO emergency_device_requests (
                    user_id, student_name, device_key, device_label, timetable_id, meeting_id,
                    lesson_teacher_id, substitute_teacher_id, class_label, teacher_name,
                    lesson_date, end_time, status, requested_at, expires_at, ip_address,
                    pending_key, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $studentName,
                $deviceKey,
                $label,
                $timetableId,
                (int)($context['meeting_id'] ?? 0) ?: null,
                (int)($context['teacher_id'] ?? 0),
                (int)($context['substitute_teacher_id'] ?? 0),
                trim((string)($context['class_label'] ?? 'Live class')),
                trim((string)($context['teacher_name'] ?? '')),
                trim((string)($context['lesson_date'] ?? '')) ?: null,
                substr(trim((string)($context['end_time'] ?? '')), 0, 8) ?: null,
                $now,
                $expires,
                $this->clientIp(),
                $pendingKey,
                $now,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $pending = $this->findPending($userId, $timetableId, $deviceKey);
            if ($pending) {
                return [
                    'status' => 'pending',
                    'message' => 'Your request is already waiting for approval.',
                    'request_id' => (int)$pending['id'],
                    'duplicate' => true,
                    'expires_at' => (string)$pending['expires_at'],
                ];
            }
            return $unavailable;
        }
        $row = $this->find($id);
        if ($row) {
            $this->notify($row);
            $this->security('EMERGENCY_DEVICE_REQUESTED', $row, 'pending');
        }
        return [
            'status' => 'pending',
            'message' => 'Your teacher/admin has been notified and can approve temporary access to this device.',
            'request_id' => $id,
            'duplicate' => false,
            'expires_at' => $expires,
        ];
    }

    /**
     * @param array{user_id?:int,role?:string,is_admin?:bool,is_teacher?:bool,teacher_id?:int,name?:string} $actor
     * @return array{ok:bool,error:string,status:string}
     */
    public function decide(int $requestId, string $decision, array $actor): array
    {
        $this->expireStale();
        $decision = $decision === 'approved' || $decision === 'denied' ? $decision : '';
        if ($requestId < 1 || $decision === '') {
            return ['ok' => false, 'error' => 'Invalid request.', 'status' => ''];
        }
        $row = $this->find($requestId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Request not found.', 'status' => ''];
        }
        if (!$this->actorMayDecide($row, $actor)) {
            return ['ok' => false, 'error' => 'You cannot decide this request.', 'status' => (string)$row['status']];
        }
        if ($decision === 'approved' && $this->classHasEnded($row)) {
            $closed = $this->closePending($requestId, [
                'status' => 'expired',
                'reason' => 'Class has ended',
                'decided_at' => $this->now(),
                'decided_by_user_id' => (int)($actor['user_id'] ?? 0) ?: null,
                'decided_by_role' => (string)($actor['role'] ?? ''),
                'decided_by_name' => (string)($actor['name'] ?? ''),
                'access_until' => null,
            ]);
            if (!$closed) {
                $fresh = $this->find($requestId);
                return [
                    'ok' => false,
                    'error' => 'This request was already decided.',
                    'status' => (string)($fresh['status'] ?? ''),
                ];
            }
            return ['ok' => false, 'error' => 'This class has ended.', 'status' => 'expired'];
        }
        $now = $this->now();
        $accessUntil = null;
        if ($decision === 'approved') {
            $accessUntil = date('Y-m-d H:i:s', $this->accessUntilTs($row));
        }
        $won = $this->closePending($requestId, [
            'status' => $decision,
            'reason' => null,
            'decided_at' => $now,
            'decided_by_user_id' => (int)($actor['user_id'] ?? 0) ?: null,
            'decided_by_role' => (string)($actor['role'] ?? ''),
            'decided_by_name' => (string)($actor['name'] ?? ''),
            'access_until' => $accessUntil,
        ], true);
        if (!$won) {
            $fresh = $this->find($requestId);
            return [
                'ok' => false,
                'error' => 'This request was already decided.',
                'status' => (string)($fresh['status'] ?? ''),
            ];
        }
        $fresh = $this->find($requestId) ?: $row;
        $this->security($decision === 'approved' ? 'EMERGENCY_DEVICE_APPROVED' : 'EMERGENCY_DEVICE_DENIED', $fresh, $decision);
        return ['ok' => true, 'error' => '', 'status' => $decision];
    }

    public function allows(int $userId, int $timetableId, string $deviceKey): bool
    {
        if ($userId < 1 || $timetableId < 1 || !preg_match('/^[a-f0-9]{64}$/', $deviceKey)) {
            return false;
        }
        $this->expireStale();
        try {
            $devices = new StudentDeviceService($this->pdo);
            if ($devices->currentDeviceBlocked($userId)) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            SELECT * FROM emergency_device_requests
            WHERE user_id = ? AND timetable_id = ? AND device_key = ? AND status = 'approved' AND access_until > ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $timetableId, $deviceKey, $this->now()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        return !$this->classHasEnded($row);
    }

    public function expireStale(): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare("
            SELECT * FROM emergency_device_requests
            WHERE status = 'pending' AND expires_at <= ?
        ");
        $stmt->execute([$now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return;
        }
        $this->pdo->prepare("
            UPDATE emergency_device_requests
            SET status = 'expired', pending_key = NULL, decided_at = ?, reason = COALESCE(NULLIF(reason, ''), 'Request expired')
            WHERE status = 'pending' AND expires_at <= ?
        ")->execute([$now, $now]);
        foreach ($rows as $row) {
            $this->security('EMERGENCY_DEVICE_EXPIRED', $row, 'expired');
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestForCurrentDevice(int $userId, int $timetableId): ?array
    {
        $this->expireStale();
        $devices = new StudentDeviceService($this->pdo);
        return $this->latestForDevice($userId, $timetableId, $devices->currentDeviceKey());
    }

    /**
     * @param array{user_id?:int,role?:string,is_admin?:bool,is_teacher?:bool,teacher_id?:int} $actor
     * @return list<array<string,mixed>>
     */
    public function pendingForActor(array $actor): array
    {
        $this->expireStale();
        $now = $this->now();
        if (($actor['role'] ?? '') === 'admin' && !empty($actor['is_admin'])) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM emergency_device_requests
                WHERE status = 'pending' AND expires_at > ?
                ORDER BY id ASC
            ");
            $stmt->execute([$now]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        if (($actor['role'] ?? '') === 'teacher' && !empty($actor['is_teacher'])) {
            $teacherId = (int)($actor['teacher_id'] ?? 0);
            if ($teacherId < 1) {
                return [];
            }
            $stmt = $this->pdo->prepare("
                SELECT * FROM emergency_device_requests
                WHERE status = 'pending' AND expires_at > ?
                  AND (lesson_teacher_id = ? OR (substitute_teacher_id > 0 AND substitute_teacher_id = ?))
                ORDER BY id ASC
            ");
            $stmt->execute([$now, $teacherId, $teacherId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function history(string $status = '', int $limit = 100): array
    {
        $this->expireStale();
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM emergency_device_requests';
        $params = [];
        if (in_array($status, ['pending', 'approved', 'denied', 'expired'], true)) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function withdrawPending(int $userId): void
    {
        $devices = new StudentDeviceService($this->pdo);
        $key = $devices->currentDeviceKey();
        $this->pdo->prepare("
            UPDATE emergency_device_requests
            SET status = 'expired', pending_key = NULL, decided_at = ?, reason = 'Student deactivated another device'
            WHERE user_id = ? AND device_key = ? AND status = 'pending'
        ")->execute([$this->now(), $userId, $key]);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function publicRequest(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'student_name' => (string)($row['student_name'] ?? ''),
            'class_label' => (string)($row['class_label'] ?? ''),
            'teacher_name' => (string)($row['teacher_name'] ?? ''),
            'device_label' => (string)($row['device_label'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'timetable_id' => (int)($row['timetable_id'] ?? 0),
            'expires_epoch' => strtotime((string)($row['expires_at'] ?? '')) ?: 0,
            'duplicate' => false,
        ];
    }

    public static function maskIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }
        if (str_contains($ip, '.')) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                return implode('.', $parts);
            }
        }
        return $ip;
    }

    public function accessStillOpen(array $row): bool
    {
        if ((string)($row['status'] ?? '') !== 'approved') {
            return false;
        }
        $until = strtotime((string)($row['access_until'] ?? ''));
        if ($until === false || $until <= time()) {
            return false;
        }
        return !$this->classHasEnded($row);
    }

    /**
     * @param array<string,mixed> $row
     * @param array{user_id?:int,role?:string,is_admin?:bool,is_teacher?:bool,teacher_id?:int} $actor
     */
    private function actorMayDecide(array $row, array $actor): bool
    {
        $role = (string)($actor['role'] ?? '');
        $actorId = (int)($actor['user_id'] ?? 0);
        if ($role === 'student' || $actorId < 1 || $actorId === (int)($row['user_id'] ?? 0)) {
            return false;
        }
        if ($role === 'admin' && !empty($actor['is_admin'])) {
            return true;
        }
        if ($role === 'teacher' && !empty($actor['is_teacher'])) {
            return RecordingAccessService::teacherCanManageLesson(
                (int)($actor['teacher_id'] ?? 0),
                (int)($row['lesson_teacher_id'] ?? 0),
                false,
                (int)($row['substitute_teacher_id'] ?? 0)
            );
        }
        return false;
    }

    /**
     * @param array<string,mixed> $fields
     */
    private function closePending(int $requestId, array $fields, bool $requireOpen = false): bool
    {
        $sql = "
            UPDATE emergency_device_requests
            SET status = ?, reason = ?, decided_at = ?, decided_by_user_id = ?, decided_by_role = ?,
                decided_by_name = ?, access_until = ?, pending_key = NULL
            WHERE id = ? AND status = 'pending'
        ";
        $params = [
            $fields['status'],
            $fields['reason'],
            $fields['decided_at'],
            $fields['decided_by_user_id'],
            $fields['decided_by_role'],
            $fields['decided_by_name'],
            $fields['access_until'],
            $requestId,
        ];
        if ($requireOpen) {
            $sql .= ' AND expires_at > ?';
            $params[] = $this->now();
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function classHasEnded(array $row): bool
    {
        $status = $this->meetingStatus((int)($row['timetable_id'] ?? 0));
        if (in_array($status, ['ended', 'cancelled'], true)) {
            return true;
        }
        $date = trim((string)($row['lesson_date'] ?? ''));
        $end = trim((string)($row['end_time'] ?? ''));
        if ($date === '' || $end === '') {
            return false;
        }
        $ts = strtotime($date . ' ' . $end);
        return $ts !== false && $ts <= time();
    }

    /**
     * @param array<string,mixed> $row
     */
    private function accessUntilTs(array $row): int
    {
        $cap = time() + self::ACCESS_CAP_SECONDS;
        $date = trim((string)($row['lesson_date'] ?? ''));
        $end = trim((string)($row['end_time'] ?? ''));
        $ts = ($date !== '' && $end !== '') ? strtotime($date . ' ' . $end) : false;
        if ($ts !== false && $ts > time()) {
            return (int)min($ts, $cap);
        }
        return $cap;
    }

    private function meetingStatus(int $timetableId): string
    {
        if ($timetableId < 1) {
            return '';
        }
        try {
            $stmt = $this->pdo->prepare('SELECT status FROM online_meetings WHERE timetable_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$timetableId]);
            return strtolower((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM emergency_device_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findPending(int $userId, int $timetableId, string $deviceKey): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM emergency_device_requests
            WHERE user_id = ? AND timetable_id = ? AND device_key = ? AND status = 'pending' AND expires_at > ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $timetableId, $deviceKey, $this->now()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function latestForDevice(int $userId, int $timetableId, string $deviceKey): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM emergency_device_requests
            WHERE user_id = ? AND timetable_id = ? AND device_key = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $timetableId, $deviceKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function studentName(int $userId): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $name = trim((string)$stmt->fetchColumn());
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable $e) {
        }
        try {
            $stmt = $this->pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $name = trim((string)$stmt->fetchColumn());
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable $e) {
        }
        return 'Student';
    }

    /**
     * @param array<string,mixed> $row
     */
    private function notify(array $row): void
    {
        $student = trim((string)($row['student_name'] ?? 'A student'));
        $class = trim((string)($row['class_label'] ?? 'a live class'));
        $message = $student . ' is requesting temporary access from a 3rd device for ' . $class . '.';
        try {
            if (function_exists('campus_insert_teacher_notification')) {
                campus_insert_teacher_notification(
                    $this->pdo,
                    (int)($row['lesson_teacher_id'] ?? 0),
                    'Device access request',
                    $message,
                    'classroom/room.php?lesson=' . (int)($row['timetable_id'] ?? 0)
                );
                $substitute = (int)($row['substitute_teacher_id'] ?? 0);
                if ($substitute > 0 && $substitute !== (int)($row['lesson_teacher_id'] ?? 0)) {
                    campus_insert_teacher_notification(
                        $this->pdo,
                        $substitute,
                        'Device access request',
                        $message,
                        'classroom/room.php?lesson=' . (int)($row['timetable_id'] ?? 0)
                    );
                }
            }
            if (function_exists('campus_notify_admins')) {
                campus_notify_admins(
                    $this->pdo,
                    'Student device request',
                    $message,
                    'admin/student_devices.php#emergency-requests'
                );
            }
        } catch (Throwable $e) {
        }
    }

    private function clientIp(): ?string
    {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        return $ip !== '' ? $ip : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function security(string $code, array $row, string $result): void
    {
        try {
            (new SecurityEventService($this->pdo))->record($code, [
                'user_id' => (int)($row['user_id'] ?? 0),
                'timetable_id' => (int)($row['timetable_id'] ?? 0),
                'teacher_id' => (int)($row['lesson_teacher_id'] ?? 0),
                'result' => $result,
                'message' => trim((string)($row['student_name'] ?? 'Student') . ' · ' . (string)($row['class_label'] ?? 'Live class')),
                'reference_id' => (int)($row['id'] ?? 0),
            ]);
        } catch (Throwable $e) {
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
