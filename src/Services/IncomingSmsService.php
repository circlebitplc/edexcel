<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;
use RuntimeException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Incoming SMS Service for Edexcel College
 *
 * Handles incoming SMS synchronization from SMS-Gate (Android),
 * device identity and authentication, deduplication, registered user matching,
 * and inbox storage/management.
 */
final class IncomingSmsService
{
    private const ONLINE_THRESHOLD_SECONDS = 900; // 15 minutes

    /**
     * Ensure database schema exists (self-healing for all environments).
     */
    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $done = true;
            return;
        }

        if ($driver === 'sqlite') {
            $sql = "
                CREATE TABLE IF NOT EXISTS sms_gateway_devices (
                    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_name         VARCHAR(150) NOT NULL,
                    device_id           VARCHAR(100) NOT NULL UNIQUE,
                    phone_number        VARCHAR(50)  NULL,
                    api_token_hash      VARCHAR(255) NOT NULL,
                    api_token_prefix    VARCHAR(20)  NOT NULL DEFAULT '',
                    is_enabled          INTEGER      NOT NULL DEFAULT 1,
                    last_seen_at        TEXT         NULL,
                    last_sms_at         TEXT         NULL,
                    last_sync_at        TEXT         NULL,
                    created_at          TEXT         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at          TEXT         NOT NULL DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS incoming_sms (
                    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_id           VARCHAR(100) NOT NULL,
                    device_db_id        INTEGER      NULL,
                    message_id          VARCHAR(150) NULL,
                    sender              VARCHAR(50)  NOT NULL,
                    raw_sender          VARCHAR(100) NULL,
                    recipient           VARCHAR(50)  NULL,
                    message             TEXT         NOT NULL,
                    sim_number          INTEGER      NULL,
                    received_at         TEXT         NOT NULL,
                    received_at_server  TEXT         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    is_read             INTEGER      NOT NULL DEFAULT 0,
                    read_at             TEXT         NULL,
                    read_by             INTEGER      NULL,
                    matched_user_type   VARCHAR(50)  NULL,
                    matched_user_name   VARCHAR(150) NULL,
                    matched_user_id     INTEGER      NULL,
                    fingerprint         CHAR(64)     NOT NULL UNIQUE,
                    created_at          TEXT         NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ";
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS sms_gateway_devices (
                    id                  INT AUTO_INCREMENT PRIMARY KEY,
                    device_name         VARCHAR(150) NOT NULL,
                    device_id           VARCHAR(100) NOT NULL,
                    phone_number        VARCHAR(50)  NULL,
                    api_token_hash      VARCHAR(255) NOT NULL,
                    api_token_prefix    VARCHAR(20)  NOT NULL DEFAULT '',
                    is_enabled          TINYINT(1)   NOT NULL DEFAULT 1,
                    last_seen_at        DATETIME     NULL,
                    last_sms_at         DATETIME     NULL,
                    last_sync_at        DATETIME     NULL,
                    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_sgd_device_id (device_id),
                    INDEX idx_sgd_enabled (is_enabled),
                    INDEX idx_sgd_last_seen (last_seen_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS incoming_sms (
                    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    device_id           VARCHAR(100) NOT NULL,
                    device_db_id        INT          NULL,
                    message_id          VARCHAR(150) NULL,
                    sender              VARCHAR(50)  NOT NULL,
                    raw_sender          VARCHAR(100) NULL,
                    recipient           VARCHAR(50)  NULL,
                    message             TEXT         NOT NULL,
                    sim_number          INT          NULL,
                    received_at         DATETIME     NOT NULL,
                    received_at_server  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    is_read             TINYINT(1)   NOT NULL DEFAULT 0,
                    read_at             DATETIME     NULL,
                    read_by             INT          NULL,
                    matched_user_type   VARCHAR(50)  NULL,
                    matched_user_name   VARCHAR(150) NULL,
                    matched_user_id     INT          NULL,
                    fingerprint         CHAR(64)     NOT NULL,
                    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_incoming_fingerprint (fingerprint),
                    INDEX idx_is_device (device_id),
                    INDEX idx_is_sender (sender),
                    INDEX idx_is_read (is_read, received_at),
                    INDEX idx_is_received (received_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
        }

        try {
            $pdo->exec($sql);
            $done = true;
        } catch (Throwable $e) {
            error_log('IncomingSmsService::ensureSchema error: ' . $e->getMessage());
            $done = true;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Phone Number & User Matching
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Normalize phone number into canonical Sri Lankan format (+947XXXXXXXX)
     * and domestic format (07XXXXXXXX).
     */
    public static function normalizePhone(string $phone): array
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // If local 10-digit starting with 0: e.g. 0771234567
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '94' . substr($digits, 1);
        }
        // If 9-digit starting with 7: e.g. 771234567
        elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '94' . $digits;
        }

        if (preg_match('/^947\d{8}$/', $digits)) {
            return [
                'canonical' => '+' . $digits,
                'e164'      => '+' . $digits,
                'local'     => '0' . substr($digits, 2),
                'digits'    => $digits,
                'valid'     => true,
            ];
        }

        // International or non-Sri Lankan standard
        if ($digits !== '') {
            return [
                'canonical' => '+' . $digits,
                'e164'      => '+' . $digits,
                'local'     => $digits,
                'digits'    => $digits,
                'valid'     => true,
            ];
        }

        return [
            'canonical' => $raw,
            'e164'      => $raw,
            'local'     => $raw,
            'digits'    => '',
            'valid'     => false,
        ];
    }

    /**
     * Match sender's phone number to a registered user (Student, Teacher, Parent).
     * Prevents partial/unsafe matches.
     */
    public static function matchSenderToUser(PDO $pdo, string $phone): array
    {
        $norm = self::normalizePhone($phone);
        if (!$norm['valid']) {
            return [
                'matched'   => false,
                'type'      => 'unknown',
                'name'      => 'Unknown Sender',
                'role'      => 'Unknown',
                'user_id'   => null,
                'phone'     => $norm['canonical'],
            ];
        }

        $patterns = [
            $norm['canonical'],
            $norm['local'],
            $norm['digits'],
            substr($norm['digits'], 2), // 771234567
        ];
        $placeholders = implode(',', array_fill(0, count($patterns), '?'));

        try {
            // 1. Check Student Profiles (whatsapp_number or parent_whatsapp)
            $stmt = $pdo->prepare("
                SELECT sp.user_id, sp.full_name, 'student' AS role_type
                FROM student_profiles sp
                WHERE sp.whatsapp_number IN ($placeholders)
                   OR sp.parent_whatsapp IN ($placeholders)
                LIMIT 1
            ");
            $params = array_merge($patterns, $patterns);
            $stmt->execute($params);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($student && !empty($student['full_name'])) {
                return [
                    'matched'   => true,
                    'type'      => 'student',
                    'name'      => (string)$student['full_name'],
                    'role'      => 'Student',
                    'user_id'   => (int)$student['user_id'],
                    'phone'     => $norm['canonical'],
                ];
            }

            // 2. Check Teachers (phone)
            $stmtTeacher = $pdo->prepare("
                SELECT id, name
                FROM teachers
                WHERE phone IN ($placeholders) AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmtTeacher->execute($patterns);
            $teacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC);
            if ($teacher && !empty($teacher['name'])) {
                return [
                    'matched'   => true,
                    'type'      => 'teacher',
                    'name'      => (string)$teacher['name'],
                    'role'      => 'Teacher',
                    'user_id'   => (int)$teacher['id'],
                    'phone'     => $norm['canonical'],
                ];
            }

            // 3. Check Parent Accounts (phone)
            $stmtParent = $pdo->prepare("
                SELECT id, name
                FROM parent_accounts
                WHERE phone IN ($placeholders)
                LIMIT 1
            ");
            $stmtParent->execute($patterns);
            $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);
            if ($parent && !empty($parent['name'])) {
                return [
                    'matched'   => true,
                    'type'      => 'parent',
                    'name'      => (string)$parent['name'],
                    'role'      => 'Parent',
                    'user_id'   => (int)$parent['id'],
                    'phone'     => $norm['canonical'],
                ];
            }
        } catch (Throwable $e) {
            error_log('matchSenderToUser error: ' . $e->getMessage());
        }

        return [
            'matched'   => false,
            'type'      => 'unknown',
            'name'      => 'Unknown Sender',
            'role'      => 'Unknown',
            'user_id'   => null,
            'phone'     => $norm['canonical'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Device Management
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Get all registered SMS Gateway devices.
     */
    public static function getDevices(PDO $pdo, bool $onlyEnabled = false): array
    {
        self::ensureSchema($pdo);
        $sql = 'SELECT * FROM sms_gateway_devices';
        if ($onlyEnabled) {
            $sql .= ' WHERE is_enabled = 1';
        }
        $sql .= ' ORDER BY id ASC';

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $status = self::getDeviceStatus($row);
                $row['is_online']       = $status['is_online'];
                $row['status_label']    = $status['label'];
                $row['status_badge']    = $status['badge_class'];
                $row['last_seen_human'] = $status['last_seen_human'];
                $row['last_sms_human']  = $status['last_sms_human'];
            }
            unset($row);
            return $rows;
        } catch (Throwable $e) {
            error_log('getDevices error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a device by ID.
     */
    public static function getDeviceById(PDO $pdo, int $id): ?array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('SELECT * FROM sms_gateway_devices WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                return null;
            }
            $status = self::getDeviceStatus($device);
            $device['is_online']       = $status['is_online'];
            $device['status_label']    = $status['label'];
            $device['status_badge']    = $status['badge_class'];
            $device['last_seen_human'] = $status['last_seen_human'];
            $device['last_sms_human']  = $status['last_sms_human'];
            return $device;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get a device by device_id string.
     */
    public static function getDeviceByDeviceId(PDO $pdo, string $deviceId): ?array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('SELECT * FROM sms_gateway_devices WHERE device_id = ? LIMIT 1');
            $stmt->execute([trim($deviceId)]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                return null;
            }
            $status = self::getDeviceStatus($device);
            $device['is_online']       = $status['is_online'];
            $device['status_label']    = $status['label'];
            $device['status_badge']    = $status['badge_class'];
            $device['last_seen_human'] = $status['last_seen_human'];
            return $device;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Calculate device connection status based on last_seen_at.
     */
    public static function getDeviceStatus(array $device): array
    {
        $enabled = (int)($device['is_enabled'] ?? 0) === 1;
        $lastSeen = !empty($device['last_seen_at']) ? strtotime((string)$device['last_seen_at']) : 0;
        $lastSms  = !empty($device['last_sms_at'])  ? strtotime((string)$device['last_sms_at'])  : 0;
        $now = time();

        $isOnline = $enabled && ($lastSeen > 0) && (($now - $lastSeen) <= self::ONLINE_THRESHOLD_SECONDS);

        $lastSeenHuman = $lastSeen > 0 ? self::humanTimeDiff($lastSeen) : 'Never';
        $lastSmsHuman  = $lastSms > 0 ? self::humanTimeDiff($lastSms) : 'Never';

        if (!$enabled) {
            return [
                'is_online'       => false,
                'label'           => 'Disabled',
                'badge_class'     => 'bg-secondary',
                'last_seen_human' => $lastSeenHuman,
                'last_sms_human'  => $lastSmsHuman,
            ];
        }

        if ($isOnline) {
            return [
                'is_online'       => true,
                'label'           => 'Connected',
                'badge_class'     => 'bg-success',
                'last_seen_human' => $lastSeenHuman,
                'last_sms_human'  => $lastSmsHuman,
            ];
        }

        return [
            'is_online'       => false,
            'label'           => 'Offline',
            'badge_class'     => 'bg-warning text-dark',
            'last_seen_human' => $lastSeenHuman,
            'last_sms_human'  => $lastSmsHuman,
        ];
    }

    /**
     * Create a new device. Returns array with raw plaintext token (shown only once).
     */
    public static function createDevice(PDO $pdo, string $name, string $deviceId, ?string $phone = null): array
    {
        self::ensureSchema($pdo);
        $name = trim($name);
        $deviceId = trim($deviceId);
        $phone = $phone !== null ? trim($phone) : null;

        if ($name === '') {
            throw new RuntimeException('Device name is required.');
        }
        if ($deviceId === '') {
            throw new RuntimeException('Device ID is required.');
        }

        // Generate strong secure random token
        $rawToken = 'esms_' . bin2hex(random_bytes(20)); // 45 chars
        $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
        $tokenPrefix = substr($rawToken, 0, 8) . '...' . substr($rawToken, -4);

        $stmt = $pdo->prepare('
            INSERT INTO sms_gateway_devices
                (device_name, device_id, phone_number, api_token_hash, api_token_prefix, is_enabled, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ');
        $stmt->execute([$name, $deviceId, $phone, $tokenHash, $tokenPrefix]);
        $id = (int)$pdo->lastInsertId();

        return [
            'id'           => $id,
            'device_name'  => $name,
            'device_id'    => $deviceId,
            'phone_number' => $phone,
            'token'        => $rawToken, // Plaintext returned only at creation
            'token_prefix' => $tokenPrefix,
        ];
    }

    /**
     * Regenerate device API token. Returns new plaintext token.
     */
    public static function regenerateToken(PDO $pdo, int $id): string
    {
        self::ensureSchema($pdo);
        $device = self::getDeviceById($pdo, $id);
        if (!$device) {
            throw new RuntimeException('Device not found.');
        }

        $rawToken = 'esms_' . bin2hex(random_bytes(20));
        $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
        $tokenPrefix = substr($rawToken, 0, 8) . '...' . substr($rawToken, -4);

        $stmt = $pdo->prepare('
            UPDATE sms_gateway_devices
            SET api_token_hash = ?, api_token_prefix = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$tokenHash, $tokenPrefix, $id]);

        return $rawToken;
    }

    /**
     * Update device metadata (name, phone).
     */
    public static function updateDevice(PDO $pdo, int $id, array $data): bool
    {
        self::ensureSchema($pdo);
        $fields = [];
        $params = [];

        if (isset($data['device_name'])) {
            $name = trim((string)$data['device_name']);
            if ($name === '') {
                throw new RuntimeException('Device name cannot be empty.');
            }
            $fields[] = 'device_name = ?';
            $params[] = $name;
        }

        if (array_key_exists('phone_number', $data)) {
            $fields[] = 'phone_number = ?';
            $params[] = $data['phone_number'] !== null ? trim((string)$data['phone_number']) : null;
        }

        if (isset($data['is_enabled'])) {
            $fields[] = 'is_enabled = ?';
            $params[] = $data['is_enabled'] ? 1 : 0;
        }

        if ($fields === []) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        $sql = 'UPDATE sms_gateway_devices SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Toggle device enabled/disabled.
     */
    public static function toggleDevice(PDO $pdo, int $id, bool $enable): bool
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('UPDATE sms_gateway_devices SET is_enabled = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$enable ? 1 : 0, $id]);
    }

    /**
     * Delete device.
     */
    public static function deleteDevice(PDO $pdo, int $id): bool
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('DELETE FROM sms_gateway_devices WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Device Authentication (Webhook & API)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Authenticate an incoming request.
     * Supports:
     * - X-Device-ID + X-API-Token / X-Device-Token headers
     * - Authorization: Bearer <API-Token>
     * - Authorization: Basic base64(device_id:token)
     * - X-SMS-Webhook-Secret / query secret fallback
     */
    public static function authenticateRequest(
        PDO $pdo,
        array $headers,
        array $payload = [],
        array $queryParams = []
    ): ?array {
        self::ensureSchema($pdo);

        // Normalize header keys to lowercase
        $normHeaders = [];
        foreach ($headers as $k => $v) {
            $normHeaders[strtolower((string)$k)] = is_array($v) ? implode(',', $v) : (string)$v;
        }

        // 1. Identify device ID
        $deviceId = trim($normHeaders['x-device-id'] ?? '');
        if ($deviceId === '') {
            $deviceId = trim((string)($payload['deviceId'] ?? $payload['device_id'] ?? ''));
        }
        if ($deviceId === '') {
            $deviceId = trim((string)($queryParams['device_id'] ?? ''));
        }

        // 2. Identify token/credential
        $token = trim($normHeaders['x-api-token'] ?? $normHeaders['x-device-token'] ?? '');
        if ($token === '') {
            $auth = trim($normHeaders['authorization'] ?? '');
            if (str_starts_with(strtolower($auth), 'bearer ')) {
                $token = trim(substr($auth, 7));
            } elseif (str_starts_with(strtolower($auth), 'basic ')) {
                $decoded = base64_decode(trim(substr($auth, 6)));
                if ($decoded && str_contains($decoded, ':')) {
                    [$basicUser, $basicPass] = explode(':', $decoded, 2);
                    if ($deviceId === '') {
                        $deviceId = trim($basicUser);
                    }
                    $token = trim($basicPass);
                }
            }
        }
        if ($token === '') {
            $token = trim($normHeaders['x-sms-webhook-secret'] ?? '');
        }
        if ($token === '') {
            $token = trim((string)($queryParams['token'] ?? $queryParams['secret'] ?? ''));
        }

        if ($token === '') {
            return null;
        }

        // 3. Verify against devices table
        if ($deviceId !== '') {
            $device = self::getDeviceByDeviceId($pdo, $deviceId);
            if ($device && (int)$device['is_enabled'] === 1) {
                if (password_verify($token, (string)$device['api_token_hash'])) {
                    self::recordDevicePing($pdo, (int)$device['id']);
                    return $device;
                }
            }
        }

        // If deviceId wasn't provided or didn't match directly, iterate through enabled devices
        // and check if token matches any registered device token hash
        try {
            $stmt = $pdo->prepare('SELECT * FROM sms_gateway_devices WHERE is_enabled = 1');
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($devices as $dev) {
                if (password_verify($token, (string)$dev['api_token_hash'])) {
                    self::recordDevicePing($pdo, (int)$dev['id']);
                    return $dev;
                }
            }
        } catch (Throwable) {
            // pass
        }

        // 4. System SMS Webhook Secret Fallback (if set in settings table)
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sms_webhook_secret' LIMIT 1");
            $stmt->execute();
            $systemSecret = trim((string)$stmt->fetchColumn());
            if ($systemSecret !== '' && hash_equals($systemSecret, $token)) {
                // Return primary or first active device, or create virtual entry
                $stmtDev = $pdo->prepare('SELECT * FROM sms_gateway_devices WHERE is_enabled = 1 ORDER BY id ASC LIMIT 1');
                $stmtDev->execute();
                $firstDev = $stmtDev->fetch(PDO::FETCH_ASSOC);
                if ($firstDev) {
                    self::recordDevicePing($pdo, (int)$firstDev['id']);
                    return $firstDev;
                }
                return [
                    'id'          => 0,
                    'device_name' => 'System Webhook Device',
                    'device_id'   => $deviceId !== '' ? $deviceId : 'system_gateway',
                    'is_enabled'  => 1,
                ];
            }
        } catch (Throwable) {
            // pass
        }

        return null;
    }

    /**
     * Record device ping/activity timestamp.
     */
    public static function recordDevicePing(PDO $pdo, int $deviceDbId): void
    {
        if ($deviceDbId <= 0) {
            return;
        }
        try {
            $stmt = $pdo->prepare('UPDATE sms_gateway_devices SET last_seen_at = NOW(), last_sync_at = NOW() WHERE id = ?');
            $stmt->execute([$deviceDbId]);
        } catch (Throwable $e) {
            error_log('recordDevicePing error: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SMS Ingestion & Deduplication
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Process and ingest an incoming SMS payload.
     * Supports both standard sms-gate.app webhook format:
     * {
     *   "deviceId": "...",
     *   "event": "sms:received",
     *   "payload": {
     *     "messageId": "...",
     *     "message": "...",
     *     "sender": "...",
     *     "recipient": "...",
     *     "simNumber": 1,
     *     "receivedAt": "2026-10-01T16:30:00+05:30"
     *   }
     * }
     *
     * And flat format:
     * {
     *   "device_id": "...",
     *   "sender": "...",
     *   "message": "...",
     *   "received_at": "...",
     *   "message_id": "..."
     * }
     */
    public static function processIncomingPayload(PDO $pdo, array $data, array $device): array
    {
        self::ensureSchema($pdo);

        // Determine if nested or flat
        $isNested = isset($data['payload']) && is_array($data['payload']);
        $inner = $isNested ? $data['payload'] : $data;

        $rawMessage   = (string)($inner['message'] ?? $inner['text'] ?? $inner['body'] ?? '');
        $rawSender    = (string)($inner['sender'] ?? $inner['phone'] ?? $inner['from'] ?? $inner['phoneNumber'] ?? '');
        $rawRecipient = (string)($inner['recipient'] ?? $inner['to'] ?? $device['phone_number'] ?? '');
        $messageId    = trim((string)($inner['messageId'] ?? $inner['message_id'] ?? $data['id'] ?? ''));
        $simNumber    = isset($inner['simNumber']) ? (int)$inner['simNumber'] : (isset($inner['sim_number']) ? (int)$inner['sim_number'] : null);
        $rawTime      = (string)($inner['receivedAt'] ?? $inner['received_at'] ?? $inner['timestamp'] ?? '');

        if (trim($rawSender) === '' || trim($rawMessage) === '') {
            return [
                'ok'      => false,
                'status'  => 'invalid_payload',
                'error'   => 'Missing required sender or message field.',
                'code'    => 400,
            ];
        }

        // Parse received_at time safely
        $receivedAt = date('Y-m-d H:i:s');
        if ($rawTime !== '') {
            try {
                $dt = new DateTimeImmutable($rawTime);
                $dt = $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
                $receivedAt = $dt->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $receivedAt = date('Y-m-d H:i:s');
            }
        }

        // Normalize phone number
        $norm = self::normalizePhone($rawSender);
        $canonicalSender = $norm['canonical'];

        // User lookup
        $matched = self::matchSenderToUser($pdo, $rawSender);

        // Deduplication fingerprint
        // device_id + (message_id OR sender + message + received_at)
        $fpSeed = $device['device_id'] . '|' . ($messageId !== '' ? $messageId : ($canonicalSender . '|' . trim($rawMessage) . '|' . $receivedAt));
        $fingerprint = hash('sha256', $fpSeed);

        // Check if duplicate exists
        try {
            $stmtCheck = $pdo->prepare('SELECT id, created_at FROM incoming_sms WHERE fingerprint = ? LIMIT 1');
            $stmtCheck->execute([$fingerprint]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                // Update device ping
                self::recordDevicePing($pdo, (int)$device['id']);
                return [
                    'ok'           => true,
                    'status'       => 'duplicate',
                    'message'      => 'Duplicate SMS ignored.',
                    'id'           => (int)$existing['id'],
                    'duplicate'    => true,
                ];
            }
        } catch (Throwable $e) {
            error_log('Incoming SMS duplicate check error: ' . $e->getMessage());
        }

        // Insert into incoming_sms table
        try {
            $stmt = $pdo->prepare('
                INSERT INTO incoming_sms (
                    device_id, device_db_id, message_id, sender, raw_sender, recipient,
                    message, sim_number, received_at, received_at_server, is_read,
                    matched_user_type, matched_user_name, matched_user_id, fingerprint, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, NOW(), 0,
                    ?, ?, ?, ?, NOW()
                )
            ');
            $stmt->execute([
                $device['device_id'],
                $device['id'] > 0 ? (int)$device['id'] : null,
                $messageId !== '' ? $messageId : null,
                $canonicalSender,
                $rawSender,
                $rawRecipient !== '' ? $rawRecipient : null,
                $rawMessage,
                $simNumber,
                $receivedAt,
                $matched['matched'] ? $matched['type'] : null,
                $matched['matched'] ? $matched['name'] : null,
                $matched['matched'] ? $matched['user_id'] : null,
                $fingerprint,
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Update device's last_sms_at and last_seen_at
            if ($device['id'] > 0) {
                $stmtDev = $pdo->prepare('UPDATE sms_gateway_devices SET last_sms_at = NOW(), last_seen_at = NOW(), last_sync_at = NOW() WHERE id = ?');
                $stmtDev->execute([(int)$device['id']]);
            }

            return [
                'ok'           => true,
                'status'       => 'stored',
                'message'      => 'SMS received and stored successfully.',
                'id'           => $newId,
                'duplicate'    => false,
                'sender'       => $canonicalSender,
                'matched_user' => $matched,
            ];
        } catch (Throwable $e) {
            error_log('Incoming SMS insert error: ' . $e->getMessage());
            return [
                'ok'      => false,
                'status'  => 'db_error',
                'error'   => 'Failed to store incoming SMS.',
                'code'    => 500,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Inbox Queries & Actions
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Query incoming SMS messages with filters and pagination.
     */
    public static function getMessages(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        self::ensureSchema($pdo);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        // Search filter (sender, message, or user name)
        if (!empty($filters['search'])) {
            $term = trim((string)$filters['search']);
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            if ($digits !== '') {
                $where[] = '(sender LIKE ? OR raw_sender LIKE ? OR message LIKE ? OR matched_user_name LIKE ?)';
                $params[] = '%' . $digits . '%';
                $params[] = '%' . $digits . '%';
                $params[] = '%' . $term . '%';
                $params[] = '%' . $term . '%';
            } else {
                $where[] = '(message LIKE ? OR matched_user_name LIKE ?)';
                $params[] = '%' . $term . '%';
                $params[] = '%' . $term . '%';
            }
        }

        // Status filter ('unread' or 'read')
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'unread') {
                $where[] = 'is_read = 0';
            } elseif ($filters['status'] === 'read') {
                $where[] = 'is_read = 1';
            }
        }

        // Device filter
        if (!empty($filters['device_id'])) {
            $where[] = 'device_id = ?';
            $params[] = trim((string)$filters['device_id']);
        }

        // Date From
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_from'])) {
            $where[] = 'DATE(received_at) >= ?';
            $params[] = (string)$filters['date_from'];
        }

        // Date To
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_to'])) {
            $where[] = 'DATE(received_at) <= ?';
            $params[] = (string)$filters['date_to'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM incoming_sms $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $sql = "
                SELECT m.*, d.device_name
                FROM incoming_sms m
                LEFT JOIN sms_gateway_devices d ON m.device_id = d.device_id
                $whereClause
                ORDER BY m.received_at DESC, m.id DESC
                LIMIT $perPage OFFSET $offset
            ";
            $rowStmt = $pdo->prepare($sql);
            $rowStmt->execute($params);
            $rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$row) {
                $row['received_human'] = self::humanTimeDiff(strtotime((string)$row['received_at']));
            }
            unset($row);

            $pages = $total > 0 ? (int)ceil($total / $perPage) : 1;

            return [
                'messages' => $rows,
                'total'    => $total,
                'pages'    => $pages,
                'page'     => $page,
                'per_page' => $perPage,
            ];
        } catch (Throwable $e) {
            error_log('IncomingSmsService::getMessages error: ' . $e->getMessage());
            return [
                'messages' => [],
                'total'    => 0,
                'pages'    => 1,
                'page'     => $page,
                'per_page' => $perPage,
            ];
        }
    }

    /**
     * Get a single message by ID.
     */
    public static function getMessageById(PDO $pdo, int $id): ?array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('
                SELECT m.*, d.device_name
                FROM incoming_sms m
                LEFT JOIN sms_gateway_devices d ON m.device_id = d.device_id
                WHERE m.id = ?
                LIMIT 1
            ');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['received_human'] = self::humanTimeDiff(strtotime((string)$row['received_at']));
                return $row;
            }
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Mark message as read.
     */
    public static function markAsRead(PDO $pdo, int $id, ?int $adminId = null): bool
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('
                UPDATE incoming_sms
                SET is_read = 1, read_at = NOW(), read_by = ?
                WHERE id = ? AND is_read = 0
            ');
            return $stmt->execute([$adminId, $id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Mark message as unread.
     */
    public static function markAsUnread(PDO $pdo, int $id): bool
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('UPDATE incoming_sms SET is_read = 0, read_at = NULL, read_by = NULL WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Mark all messages as read.
     */
    public static function markAllAsRead(PDO $pdo, ?int $adminId = null): int
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('UPDATE incoming_sms SET is_read = 1, read_at = NOW(), read_by = ? WHERE is_read = 0');
            $stmt->execute([$adminId]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Delete an incoming SMS message.
     */
    public static function deleteMessage(PDO $pdo, int $id): bool
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('DELETE FROM incoming_sms WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Get count of unread incoming SMS messages.
     */
    public static function getUnreadCount(PDO $pdo): int
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM incoming_sms WHERE is_read = 0');
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Get summary stats for inbox dashboard cards.
     */
    public static function getStats(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->query("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread,
                    SUM(CASE WHEN DATE(received_at) = CURRENT_DATE() THEN 1 ELSE 0 END) AS today
                FROM incoming_sms
            ");
            $smsStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $devices = self::getDevices($pdo);
            $onlineCount = 0;
            foreach ($devices as $d) {
                if (!empty($d['is_online'])) {
                    $onlineCount++;
                }
            }

            return [
                'total'          => (int)($smsStats['total'] ?? 0),
                'unread'         => (int)($smsStats['unread'] ?? 0),
                'today'          => (int)($smsStats['today'] ?? 0),
                'online_devices' => $onlineCount,
                'total_devices'  => count($devices),
            ];
        } catch (Throwable) {
            return [
                'total'          => 0,
                'unread'         => 0,
                'today'          => 0,
                'online_devices' => 0,
                'total_devices'  => 0,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────────

    private static function humanTimeDiff(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'Never';
        }
        $diff = time() - $timestamp;
        if ($diff < 0) {
            return 'Just now';
        }
        if ($diff < 60) {
            return $diff . 's ago';
        }
        if ($diff < 3600) {
            $mins = (int)floor($diff / 60);
            return $mins . 'm ago';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . 'h ago';
        }
        $days = (int)floor($diff / 86400);
        return $days . 'd ago';
    }
}
