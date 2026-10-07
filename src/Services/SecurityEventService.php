<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Security history for the admin Security Center and the student's own history.
 * Detailed reasons stay here. Student-facing pages only receive a safe subset.
 */
final class SecurityEventService
{
    public const STUDENT_VISIBLE = [
        'DEVICE_REGISTERED',
        'DEVICE_REPLACED',
        'DEVICE_BLOCKED',
        'DEVICE_UNBLOCKED',
        'SESSION_CREATED',
        'SESSION_REVOKED',
        'EMERGENCY_DEVICE_REQUESTED',
        'EMERGENCY_DEVICE_APPROVED',
        'EMERGENCY_DEVICE_DENIED',
        'EMERGENCY_DEVICE_EXPIRED',
        'LIVE_CLASS_ACCESS_GRANTED',
    ];

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
                CREATE TABLE IF NOT EXISTS security_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    event_code VARCHAR(64) NOT NULL,
                    user_id INT NULL,
                    actor_user_id INT NULL,
                    device_id INT NULL,
                    timetable_id INT NULL,
                    teacher_id INT NULL,
                    result VARCHAR(32) NULL,
                    message VARCHAR(255) NULL,
                    reference_id INT NULL,
                    ip_address VARCHAR(45) NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_security_events_user (user_id, created_at),
                    KEY idx_security_events_code (event_code, created_at),
                    KEY idx_security_events_class (timetable_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return;
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS security_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_code VARCHAR(64) NOT NULL,
                user_id INT NULL,
                actor_user_id INT NULL,
                device_id INT NULL,
                timetable_id INT NULL,
                teacher_id INT NULL,
                result VARCHAR(32) NULL,
                message VARCHAR(255) NULL,
                reference_id INT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL
            )
        ");
    }

    /**
     * LiveKit tokens last at most two hours, and not past the class end plus a short grace period.
     */
    public static function tokenLifetime(int $configured, ?int $classEndsAt): int
    {
        $ttl = $configured > 0 ? $configured : 7200;
        if ($ttl > 7200) {
            $ttl = 7200;
        }
        if ($ttl < 600) {
            $ttl = 600;
        }
        if ($classEndsAt !== null && $classEndsAt > time()) {
            $untilEnd = ($classEndsAt - time()) + 600;
            $ttl = min($ttl, max(600, $untilEnd));
        }
        return $ttl;
    }

    /**
     * Newer callers pass a context array. Older staff/admin pages pass
     * record($code, $message, $severity, $userId, ...). Both are accepted so a
     * failed login is recorded instead of throwing and blanking the page.
     *
     * @param array{user_id?:int,actor_user_id?:int,device_id?:int,timetable_id?:int,teacher_id?:int,result?:string,message?:string,reference_id?:int}|string $context
     */
    public function record(string $code, array|string $context = [], mixed ...$legacy): void
    {
        if (is_string($context)) {
            $context = [
                'message' => $context,
                'result' => isset($legacy[0]) ? (string)$legacy[0] : '',
                'user_id' => isset($legacy[1]) && $legacy[1] !== null ? (int)$legacy[1] : 0,
            ];
        }
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }
        try {
            if ($code === 'SUSPICIOUS_ACTIVITY' && $this->recentExists($code, (int)($context['user_id'] ?? 0), 1800)) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $this->pdo->prepare("
                INSERT INTO security_events (
                    event_code, user_id, actor_user_id, device_id, timetable_id, teacher_id,
                    result, message, reference_id, ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $code,
                $this->nullableInt($context['user_id'] ?? 0),
                $this->nullableInt($context['actor_user_id'] ?? (int)($_SESSION['user_id'] ?? 0)),
                $this->nullableInt($context['device_id'] ?? 0),
                $this->nullableInt($context['timetable_id'] ?? 0),
                $this->nullableInt($context['teacher_id'] ?? 0),
                $this->short($context['result'] ?? ''),
                $this->short($context['message'] ?? '', 255),
                $this->nullableInt($context['reference_id'] ?? 0),
                $this->clientIp(),
                $now,
            ]);
            if (function_exists('log_audit')) {
                log_audit(
                    $this->pdo,
                    $code,
                    'security_events',
                    $this->nullableInt($context['reference_id'] ?? $context['device_id'] ?? 0),
                    null,
                    ['result' => (string)($context['result'] ?? ''), 'user_id' => (int)($context['user_id'] ?? 0)]
                );
            }
            $this->maybeNotify($code, $context);
        } catch (Throwable $e) {
            error_log('Security event: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recent(string $code = '', int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM security_events';
        $params = [];
        if ($code !== '') {
            $sql .= ' WHERE event_code = ?';
            $params[] = $code;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Search security events with filters for admin/security.php & admin/command_center.php
     *
     * @param array{date_from?:string,date_to?:string,user?:string,event_type?:string,ip?:string,severity?:string,limit?:int} $filters
     * @return list<array<string,mixed>>
     */
    public function search(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'e.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'e.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['user'])) {
            $userVal = trim((string)$filters['user']);
            if (ctype_digit($userVal)) {
                $where[] = '(e.user_id = ? OR e.actor_user_id = ?)';
                $params[] = (int)$userVal;
                $params[] = (int)$userVal;
            } else {
                $where[] = 'u.username LIKE ?';
                $params[] = '%' . $userVal . '%';
            }
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'e.event_code = ?';
            $params[] = trim((string)$filters['event_type']);
        }
        if (!empty($filters['ip'])) {
            $where[] = 'e.ip_address LIKE ?';
            $params[] = '%' . trim((string)$filters['ip']) . '%';
        }

        $limit = max(1, min(500, (int)($filters['limit'] ?? 150)));

        $sql = "
            SELECT e.*, e.event_code AS event_type, u.username
            FROM security_events e
            LEFT JOIN users u ON u.id = e.user_id
        ";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.id DESC LIMIT ' . $limit;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $t) {
            error_log('SecurityEventService::search error: ' . $t->getMessage());
            return [];
        }

        foreach ($rows as &$r) {
            $code = (string)($r['event_code'] ?? '');
            $res = strtolower((string)($r['result'] ?? ''));
            // Derive severity
            if ($res === 'fail' || $res === 'error' || $res === 'denied' || str_contains($code, 'DENIED') || str_contains($code, 'FAILED') || str_contains($code, 'BLOCKED')) {
                $r['severity'] = 'error';
            } elseif ($code === 'SUSPICIOUS_ACTIVITY' || str_contains($code, 'WARNING') || $res === 'warning') {
                $r['severity'] = 'warning';
            } elseif ($code === 'EMERGENCY_DEVICE_DENIED' || str_contains($code, 'CRITICAL')) {
                $r['severity'] = 'critical';
            } else {
                $r['severity'] = 'info';
            }
        }
        unset($r);

        if (!empty($filters['severity'])) {
            $sevFilter = strtolower(trim((string)$filters['severity']));
            $rows = array_values(array_filter($rows, static fn(array $row): bool => strtolower((string)($row['severity'] ?? '')) === $sevFilter));
        }

        return $rows;
    }

    /**
     * List distinct event types / codes present in security_events.
     *
     * @return list<string>
     */
    public function eventTypes(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT DISTINCT event_code FROM security_events ORDER BY event_code ASC");
            $types = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (empty($types)) {
                return [
                    'LOGIN_SUCCESS',
                    'LOGIN_FAILED',
                    'DEVICE_REGISTERED',
                    'DEVICE_REPLACED',
                    'DEVICE_BLOCKED',
                    'DEVICE_UNBLOCKED',
                    'SESSION_CREATED',
                    'SESSION_REVOKED',
                    'SUSPICIOUS_ACTIVITY',
                    'TOTP_ENABLED',
                    'TOTP_DISABLED',
                ];
            }
            return $types;
        } catch (Throwable $t) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function forStudent(int $userId, int $limit = 30): array
    {
        if ($userId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $marks = implode(',', array_fill(0, count(self::STUDENT_VISIBLE), '?'));
        $stmt = $this->pdo->prepare("
            SELECT event_code, message, result, created_at
            FROM security_events
            WHERE user_id = ? AND event_code IN ($marks)
            ORDER BY id DESC
            LIMIT $limit
        ");
        $stmt->execute(array_merge([$userId], self::STUDENT_VISIBLE));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{online:int,active_devices:int,pending_requests:int,suspicious:int}
     */
    public function counts(): array
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $online = 0;
        $devices = 0;
        $pending = 0;
        $suspicious = 0;
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM student_active_sessions WHERE updated_at >= ?');
            $stmt->execute([$since]);
            $online = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $devices = (int)$this->pdo->query("
                SELECT COUNT(*) FROM student_devices
                WHERE verified_at IS NOT NULL AND revoked_at IS NULL AND COALESCE(status, 'ACTIVE') = 'ACTIVE'
            ")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $pending = (int)$this->pdo->query("
                SELECT COUNT(*) FROM emergency_device_requests WHERE status = 'pending'
            ")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM security_events
                WHERE event_code = 'SUSPICIOUS_ACTIVITY' AND created_at >= ?
            ");
            $stmt->execute([date('Y-m-d H:i:s', time() - 86400)]);
            $suspicious = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }
        return [
            'online' => $online,
            'active_devices' => $devices,
            'pending_requests' => $pending,
            'suspicious' => $suspicious,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function liveStudents(int $limit = 40): array
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.user_id, s.updated_at AS last_activity, s.device_id,
                       d.label AS device_label, d.platform, d.browser, d.last_login_at, d.ip_address,
                       u.username
                FROM student_active_sessions s
                JOIN users u ON u.id = s.user_id
                LEFT JOIN student_devices d ON d.id = s.device_id
                WHERE s.updated_at >= ?
                ORDER BY s.updated_at DESC
                LIMIT " . max(1, min(100, $limit)) . "
            ");
            $stmt->execute([$since]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        foreach ($rows as &$row) {
            $row['student_name'] = $this->studentName((int)$row['user_id'], (string)($row['username'] ?? ''));
            $class = $this->currentClass((int)$row['user_id']);
            $row['class_label'] = $class['class_label'];
            $row['teacher_name'] = $class['teacher_name'];
        }
        unset($row);
        return $rows;
    }

    /**
     * @return array{active:list<array<string,mixed>>,recent:list<array<string,mixed>>,blocked:list<array<string,mixed>>,expiring:list<array<string,mixed>>}
     */
    public function deviceBoards(): array
    {
        $empty = ['active' => [], 'recent' => [], 'blocked' => [], 'expiring' => []];
        try {
            $active = $this->pdo->query("
                SELECT d.id, d.user_id, d.label, d.platform, d.browser, d.status, d.last_seen_at, d.last_login_at, d.blocked_until, u.username
                FROM student_devices d
                JOIN users u ON u.id = d.user_id
                WHERE d.verified_at IS NOT NULL AND d.revoked_at IS NULL AND COALESCE(d.status, 'ACTIVE') = 'ACTIVE'
                ORDER BY d.last_seen_at DESC
                LIMIT 30
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recent = $this->pdo->query("
                SELECT d.id, d.user_id, d.label, d.status, d.created_at, d.replaced_at, u.username
                FROM student_devices d
                JOIN users u ON u.id = d.user_id
                ORDER BY d.id DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $soon = date('Y-m-d H:i:s', time() + 3 * 86400);
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("
                SELECT d.id, d.user_id, d.label, d.status, d.blocked_until, u.username
                FROM student_devices d
                JOIN users u ON u.id = d.user_id
                WHERE d.blocked_until IS NOT NULL AND d.blocked_until > ? AND d.blocked_until <= ?
                ORDER BY d.blocked_until ASC
                LIMIT 20
            ");
            $stmt->execute([$now, $soon]);
            $expiring = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $blocked = $this->pdo->query("
                SELECT d.id, d.user_id, d.label, d.status, d.blocked_until, u.username
                FROM student_devices d
                JOIN users u ON u.id = d.user_id
                WHERE COALESCE(d.status, '') IN ('BLOCKED', 'REPLACED') AND (d.blocked_until IS NULL OR d.blocked_until > " . $this->pdo->quote($now) . ")
                ORDER BY d.id DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $empty;
        }
        $name = function (array &$rows): void {
            foreach ($rows as &$row) {
                $row['student_name'] = $this->studentName((int)$row['user_id'], (string)($row['username'] ?? ''));
            }
            unset($row);
        };
        $name($active);
        $name($recent);
        $name($blocked);
        $name($expiring);
        return ['active' => $active, 'recent' => $recent, 'blocked' => $blocked, 'expiring' => $expiring];
    }

    public static function studentLabel(string $code): string
    {
        return match ($code) {
            'DEVICE_REGISTERED' => 'New device registered',
            'DEVICE_REPLACED' => 'Device replaced',
            'DEVICE_BLOCKED' => 'Device blocked',
            'DEVICE_UNBLOCKED' => 'Device unblocked',
            'SESSION_CREATED' => 'Signed in',
            'SESSION_REVOKED' => 'Other device sessions revoked',
            'EMERGENCY_DEVICE_REQUESTED' => 'Emergency class access requested',
            'EMERGENCY_DEVICE_APPROVED' => 'Emergency access approved',
            'EMERGENCY_DEVICE_DENIED' => 'Emergency access denied',
            'EMERGENCY_DEVICE_EXPIRED' => 'Emergency request expired',
            'LIVE_CLASS_ACCESS_GRANTED' => 'Joined a live class',
            default => 'Security update',
        };
    }

    public static function maskIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '' || !str_contains($ip, '.')) {
            return $ip;
        }
        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return $ip;
        }
        $parts[3] = '0';
        return implode('.', $parts);
    }

    /**
     * @param array{user_id?:int,message?:string} $context
     */
    private function maybeNotify(string $code, array $context): void
    {
        if (!function_exists('campus_notify_admins')) {
            return;
        }
        $userId = (int)($context['user_id'] ?? 0);
        $message = trim((string)($context['message'] ?? ''));
        if ($code === 'SUSPICIOUS_ACTIVITY') {
            campus_notify_admins($this->pdo, 'Possible simultaneous account usage', $message !== '' ? $message : 'A student was active on more than one device.', 'admin/security_center.php');
            return;
        }
        if ($code === 'LOGIN_FAILED' && $this->countRecent($code, $userId, 900) === 5) {
            campus_notify_admins($this->pdo, 'Repeated failed sign-in attempts', 'A sign-in was refused several times in a short period.', 'admin/security_center.php');
            return;
        }
        if ($code === 'LIVE_CLASS_ACCESS_DENIED' && $userId > 0 && $this->countRecent($code, $userId, 900) === 4) {
            campus_notify_admins($this->pdo, 'Repeated live class access failures', 'A student was refused live class access several times.', 'admin/security_center.php');
        }
    }

    private function countRecent(string $code, int $userId, int $seconds): int
    {
        $since = date('Y-m-d H:i:s', time() - $seconds);
        if ($userId > 0) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM security_events WHERE event_code = ? AND user_id = ? AND created_at >= ?');
            $stmt->execute([$code, $userId, $since]);
            return (int)$stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM security_events WHERE event_code = ? AND created_at >= ?');
        $stmt->execute([$code, $since]);
        return (int)$stmt->fetchColumn();
    }

    private function recentExists(string $code, int $userId, int $seconds): bool
    {
        if ($userId < 1) {
            return false;
        }
        return $this->countRecent($code, $userId, $seconds) > 0;
    }

    /**
     * @return array{class_label:string,teacher_name:string}
     */
    private function currentClass(int $userId): array
    {
        $empty = ['class_label' => '', 'teacher_name' => ''];
        if ($userId < 1) {
            return $empty;
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
                FROM meeting_participants p
                JOIN online_meetings m ON m.id = p.meeting_id
                JOIN timetable tt ON tt.id = m.timetable_id
                JOIN subjects s ON s.id = tt.subject_id
                JOIN student_classes c ON c.id = tt.class_id
                JOIN teachers t ON t.id = tt.teacher_id
                WHERE p.user_id = ? AND p.left_at IS NULL AND m.status = 'live'
                ORDER BY p.last_seen_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $empty;
            }
            return [
                'class_label' => trim((string)$row['subject_name'] . ' ' . (string)$row['class_name']),
                'teacher_name' => (string)$row['teacher_name'],
            ];
        } catch (Throwable $e) {
            return $empty;
        }
    }

    private function studentName(int $userId, string $fallback): string
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
        return $fallback !== '' ? $fallback : 'Student';
    }

    private function nullableInt(mixed $value): ?int
    {
        $n = (int)$value;
        return $n > 0 ? $n : null;
    }

    private function short(mixed $value, int $max = 32): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        return substr($text, 0, $max);
    }

    private function clientIp(): ?string
    {
        $raw = function_exists('eck_client_ip') ? eck_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = substr($raw, 0, 45);
        return $ip !== '' ? $ip : null;
    }
}
