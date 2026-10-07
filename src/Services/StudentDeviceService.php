<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class StudentDeviceService
{
    public const MAX_DEVICES = 2;
    public const BLOCK_DAYS = 14;
    public const FREQUENT_REPLACEMENTS = 3;
    public const FREQUENT_WINDOW_DAYS = 90;
    public const COOKIE = 'eck_device';
    public const NOTICE_COOKIE = 'eck_student_notice';
    public const OTP_TTL_SECONDS = 600;
    public const OTP_RESEND_SECONDS = 60;
    public const PRESENCE_TTL_SECONDS = 14400;

    /** @var callable|null */
    private $smsSender;

    public function __construct(private PDO $pdo, ?callable $smsSender = null)
    {
        $this->smsSender = $smsSender;
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
                CREATE TABLE IF NOT EXISTS student_devices (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    device_key CHAR(64) NOT NULL,
                    label VARCHAR(120) NOT NULL DEFAULT '',
                    user_agent VARCHAR(512) NULL,
                    ip_address VARCHAR(45) NULL,
                    session_token CHAR(64) NULL,
                    first_seen_at DATETIME NOT NULL,
                    last_seen_at DATETIME NULL,
                    last_login_at DATETIME NULL,
                    verified_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_student_devices_user_key (user_id, device_key),
                    KEY idx_student_devices_user_status (user_id, revoked_at, verified_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_device_otps (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    device_key CHAR(64) NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    otp_hash VARCHAR(255) NOT NULL,
                    replace_device_id BIGINT UNSIGNED NULL,
                    expires_at DATETIME NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    verified_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_student_device_otps_user (user_id, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_active_sessions (
                    user_id INT NOT NULL,
                    session_token CHAR(64) NOT NULL,
                    device_id BIGINT UNSIGNED NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                device_key CHAR(64) NOT NULL,
                label VARCHAR(120) NOT NULL DEFAULT '',
                user_agent VARCHAR(512) NULL,
                ip_address VARCHAR(45) NULL,
                session_token CHAR(64) NULL,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NULL,
                last_login_at DATETIME NULL,
                verified_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_device_otps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                device_key CHAR(64) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                otp_hash VARCHAR(255) NOT NULL,
                replace_device_id INT NULL,
                expires_at DATETIME NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                verified_at DATETIME NULL,
                created_at DATETIME NOT NULL
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_active_sessions (
                user_id INT NOT NULL PRIMARY KEY,
                session_token CHAR(64) NOT NULL,
                device_id INT NULL,
                updated_at DATETIME NOT NULL
            )
        ");
        try {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_student_devices_user_key ON student_devices (user_id, device_key)');
        } catch (Throwable $e) {
        }
        }

        try {
            $pdo->exec('ALTER TABLE student_device_otps ADD COLUMN purpose VARCHAR(20) NOT NULL DEFAULT \'device\'');
        } catch (Throwable $e) {
        }
        foreach ([
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'",
            'blocked_until' => 'DATETIME NULL',
            'replaced_at' => 'DATETIME NULL',
            'platform' => 'VARCHAR(40) NULL',
            'browser' => 'VARCHAR(40) NULL',
            'updated_at' => 'DATETIME NULL',
        ] as $column => $definition) {
            try {
                $pdo->exec('ALTER TABLE student_devices ADD COLUMN ' . $column . ' ' . $definition);
            } catch (Throwable $e) {
            }
        }
        try {
            $pdo->exec("
                UPDATE student_devices
                SET status = 'REPLACED'
                WHERE revoked_at IS NOT NULL
                  AND (status IS NULL OR status = '' OR status = 'ACTIVE')
            ");
            $pdo->exec("
                UPDATE student_devices
                SET status = 'ACTIVE'
                WHERE verified_at IS NOT NULL
                  AND revoked_at IS NULL
                  AND (status IS NULL OR status = '')
            ");
        } catch (Throwable $e) {
        }
        if ($mysql) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_login_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    device_id BIGINT UNSIGNED NULL,
                    event_name VARCHAR(32) NOT NULL,
                    device_label VARCHAR(120) NOT NULL DEFAULT '',
                    ip_address VARCHAR(45) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_student_login_events_user (user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_login_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INT NOT NULL,
                    device_id INT NULL,
                    event_name VARCHAR(32) NOT NULL,
                    device_label VARCHAR(120) NOT NULL DEFAULT '',
                    ip_address VARCHAR(45) NULL,
                    created_at DATETIME NOT NULL
                )
            ");
        }
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function deviceLabel(string $userAgent): string
    {
        $ua = $userAgent;
        $browser = 'Browser';
        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'EdgA/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari') !== false) {
            $browser = 'Safari';
        }

        $os = 'Device';
        if (stripos($ua, 'iPhone') !== false) {
            $os = 'iPhone';
        } elseif (stripos($ua, 'iPad') !== false) {
            $os = 'iPad';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'Mac';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return $browser . ' on ' . $os;
    }

    /**
     * @return array{browser:string,platform:string}
     */
    public static function clientSignals(?string $userAgent = null): array
    {
        $ua = $userAgent ?? (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $label = self::deviceLabel($ua);
        $parts = explode(' on ', $label, 2);
        return [
            'browser' => $parts[0] !== '' ? $parts[0] : 'Browser',
            'platform' => $parts[1] ?? 'Device',
        ];
    }

    public static function publicLabel(string $labelOrAgent): string
    {
        $label = str_contains($labelOrAgent, ' on ') || !str_contains($labelOrAgent, '/')
            ? (str_contains($labelOrAgent, ' on ') ? $labelOrAgent : self::deviceLabel($labelOrAgent))
            : $labelOrAgent;
        if (!str_contains($label, ' on ') && !str_contains($label, ' / ')) {
            $label = self::deviceLabel($labelOrAgent);
        }
        return str_replace(' on ', ' / ', $label);
    }

    public static function formatLastUsed(?string $at): string
    {
        $at = trim((string)$at);
        if ($at === '') {
            return 'Not yet';
        }
        $ts = strtotime($at);
        if ($ts === false) {
            return $at;
        }
        $time = date('g:i A', $ts);
        $day = date('Y-m-d', $ts);
        if ($day === date('Y-m-d')) {
            return 'Today ' . $time;
        }
        if ($day === date('Y-m-d', strtotime('-1 day'))) {
            return 'Yesterday ' . $time;
        }
        return date('j M Y, g:i A', $ts);
    }

    public static function formatBlockedUntil(string $at): string
    {
        $ts = strtotime($at);
        if ($ts === false) {
            return $at;
        }
        return date('j M Y, g:i A', $ts);
    }

    public static function cookieSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    public function issueCookie(): string
    {
        $existing = trim((string)($_COOKIE[self::COOKIE] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/', $existing)) {
            return $existing;
        }
        $token = bin2hex(random_bytes(32));
        $_COOKIE[self::COOKIE] = $token;
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, $token, [
                'expires' => time() + 400 * 86400,
                'path' => '/',
                'secure' => self::cookieSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $token;
    }

    public function currentToken(): string
    {
        return $this->issueCookie();
    }

    /**
     * @return array{status:string,message:string,show_otp?:bool,device_id?:int,replace_label?:string,channel?:string}
     */
    public function beginLogin(int $userId, string $source): array
    {
        $token = $this->currentToken();
        $blocked = $this->blockedDecision($userId, $token);
        if ($blocked !== null) {
            return $blocked;
        }

        $known = $this->findVerified($userId, $token);
        if ($known) {
            $this->rememberSignals((int)$known['id'], $userId);
            return ['status' => 'ok', 'device_id' => (int)$known['id'], 'message' => '', 'new_device' => false];
        }

        $rebound = $this->rebindIfSameBrowser($userId, $token);
        if ($rebound > 0) {
            return ['status' => 'ok', 'device_id' => $rebound, 'message' => '', 'new_device' => false];
        }

        $this->recordSighting($userId, $token);
        $count = $this->verifiedCount($userId);
        if ($source === 'existing_session') {
            return [
                'status' => 'error',
                'message' => 'This device is no longer registered. Sign in again from a registered device.',
            ];
        }
        if ($count >= self::MAX_DEVICES) {
            return $this->limitDecision($userId);
        }

        $phoneJustProved = in_array($source, ['login_otp', 'registration', 'classroom_sms'], true);
        $autoRegister = $source === 'google'
            || ($count === 0 && $phoneJustProved)
            || ($phoneJustProved && $source === 'registration' && $count < self::MAX_DEVICES);

        if ($autoRegister) {
            $deviceId = $this->registerDevice($userId, $token);
            if ($deviceId < 1) {
                $again = $this->blockedDecision($userId, $token);
                return $again ?? $this->limitDecision($userId);
            }
            return ['status' => 'ok', 'device_id' => $deviceId, 'message' => '', 'new_device' => true];
        }

        if (in_array($source, ['password', 'login_otp', 'classroom_sms'], true)) {
            return $this->startDeviceOtp($userId, false);
        }

        return [
            'status' => 'error',
            'message' => 'This account already uses ' . self::MAX_DEVICES . ' devices. Deactivate one registered device before using another.',
        ];
    }

    /**
     * @return array{status:string,message:string,show_otp?:bool,replace_label?:string,channel?:string}
     */
    public function startDeviceOtp(int $userId, bool $willReplace): array
    {
        $phone = $this->phoneForUser($userId);
        if ($phone === '') {
            return [
                'status' => 'error',
                'message' => 'We could not find a mobile number to send the device code. Contact the college office.',
            ];
        }

        $wait = $this->otpResendWait($userId);
        $replace = $willReplace ? $this->oldestVerified($userId) : null;
        $replaceLabel = $replace ? (string)$replace['label'] : '';
        $replaceId = $replace ? (int)$replace['id'] : null;

        if ($wait > 0) {
            $this->rememberPending($userId, $replaceLabel);
            return [
                'status' => 'otp',
                'show_otp' => true,
                'replace_label' => $replaceLabel,
                'channel' => 'SMS',
                'message' => "A device code was already sent by SMS. Enter it below, or wait {$wait} seconds to resend.",
            ];
        }

        $otp = (string)random_int(100000, 999999);
        $now = $this->now();
        $expires = date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS);
        $token = $this->currentToken();
        $this->pdo->prepare('
            INSERT INTO student_device_otps
                (user_id, device_key, phone, otp_hash, replace_device_id, expires_at, created_at, purpose)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $userId,
            self::hashToken($token),
            $phone,
            password_hash($otp, PASSWORD_DEFAULT),
            $replaceId,
            $expires,
            $now,
            'device',
        ]);

        $sent = $this->deliverOtp($phone, $otp);
        require_once dirname(__DIR__, 2) . '/config/otp_support_log.php';
        otp_support_log_record($this->pdo, [
            'purpose' => 'student_device',
            'phone' => $phone,
            'otp' => $otp,
            'channel' => strtolower((string)($sent['channel'] ?? 'sms')),
            'user_id' => $userId,
            'sent' => !empty($sent['ok']),
            'expires_at' => $expires,
        ]);
        $this->rememberPending($userId, $replaceLabel);
        $channel = (string)($sent['channel'] ?? 'SMS');
        $message = !empty($sent['ok'])
            ? "This device is new. We sent a 6-digit SMS code to register it. You can use up to " . self::MAX_DEVICES . " devices, and only one stays signed in."
            : "Your device code is ready. If SMS is delayed, tap Resend in a minute.";
        if ($replaceLabel !== '') {
            $message .= ' Confirming this code will replace your oldest device: ' . $replaceLabel . '.';
        }

        return [
            'status' => 'otp',
            'show_otp' => true,
            'replace_label' => $replaceLabel,
            'channel' => $channel,
            'message' => $message,
        ];
    }

    /**
     * @return array{status:string,message:string,show_otp?:bool,device_id?:int,user_id?:int}
     */
    public function verifyDeviceOtp(int $userId, string $otp): array
    {
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['status' => 'error', 'show_otp' => true, 'message' => 'Enter the 6-digit SMS code.'];
        }

        $stmt = $this->pdo->prepare('
            SELECT *
            FROM student_device_otps
            WHERE user_id = ?
              AND verified_at IS NULL
              AND expires_at > ?
              AND (purpose = \'device\' OR purpose IS NULL OR purpose = \'\')
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([$userId, $this->now()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['status' => 'error', 'show_otp' => true, 'message' => 'No device code was found. Request a new SMS.'];
        }
        if ((int)$row['attempts'] >= 5) {
            return ['status' => 'error', 'show_otp' => true, 'message' => 'Too many incorrect attempts. Request a new SMS.'];
        }
        if (!password_verify($otp, (string)$row['otp_hash'])) {
            $this->pdo->prepare('UPDATE student_device_otps SET attempts = attempts + 1 WHERE id = ?')
                ->execute([(int)$row['id']]);
            return ['status' => 'error', 'show_otp' => true, 'message' => 'Incorrect code. Check the SMS and try again.'];
        }

        $otpDeviceKey = trim((string)($row['device_key'] ?? ''));
        $currentKey = self::hashToken($this->currentToken());
        if ($otpDeviceKey !== '' && !hash_equals($otpDeviceKey, $currentKey)) {
            return [
                'status' => 'error',
                'show_otp' => true,
                'message' => 'This code is for another browser. Open the SMS on the same phone or computer that requested it.',
            ];
        }

        $this->pdo->prepare('UPDATE student_device_otps SET verified_at = ? WHERE id = ?')
            ->execute([$this->now(), (int)$row['id']]);

        $replaceId = (int)($row['replace_device_id'] ?? 0);
        if ($replaceId > 0) {
            $this->markReplaced($userId, $replaceId);
        } elseif ($this->verifiedCount($userId) >= self::MAX_DEVICES) {
            return $this->limitDecision($userId);
        }

        $deviceId = $this->registerDevice($userId, $this->currentToken());
        if ($deviceId < 1) {
            $blocked = $this->blockedDecision($userId, $this->currentToken());
            return $blocked ?? ['status' => 'error', 'show_otp' => true, 'message' => 'This device cannot be registered right now.'];
        }
        $this->clearPending();
        return [
            'status' => 'ok',
            'device_id' => $deviceId,
            'user_id' => $userId,
            'message' => 'Device registered.',
        ];
    }

    public function resendDeviceOtp(int $userId): array
    {
        return $this->startDeviceOtp($userId, $this->verifiedCount($userId) >= self::MAX_DEVICES);
    }

    public function grantPresence(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['student_presence_until'] = time() + self::PRESENCE_TTL_SECONDS;
        }
    }

    public function presenceValid(): bool
    {
        return (int)($_SESSION['student_presence_until'] ?? 0) > time();
    }

    /**
     * Registered devices already proved ownership via SMS; unlock live class without another OTP.
     */
    public function grantPresenceIfKnownDevice(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        if ($this->presenceValid()) {
            return true;
        }
        $known = $this->findVerified($userId, $this->currentToken());
        if (!$known) {
            return false;
        }
        $this->grantPresence();
        $this->logEvent($userId, (int)$known['id'], 'presence');
        return true;
    }

    public function onSignedIn(int $userId, int $deviceId, string $source, bool $newDevice): void
    {
        if (in_array($source, ['login_otp', 'registration', 'classroom_sms', 'device_otp', 'google'], true)) {
            $this->grantPresence();
        }
        $this->logEvent($userId, $deviceId, $newDevice ? 'new_device' : 'login');
        $this->noteSimultaneous($userId, $deviceId);
        $this->sendSignInAlerts($userId, $deviceId, $newDevice, $source);
    }

    /**
     * @return array{status:string,message:string,show_otp?:bool,channel?:string,device_id?:int}
     */
    public function startPresenceOtp(int $userId): array
    {
        if ($this->presenceValid()) {
            return [
                'status' => 'ok',
                'message' => 'Already confirmed.',
                'device_id' => (int)($_SESSION['student_device_id'] ?? 0),
            ];
        }
        $known = $this->findVerified($userId, $this->currentToken());
        if ($known) {
            $this->grantPresence();
            $this->logEvent($userId, (int)$known['id'], 'presence');
            return [
                'status' => 'ok',
                'message' => 'This device is already registered.',
                'device_id' => (int)$known['id'],
            ];
        }
        $phone = $this->phoneForUser($userId);
        if ($phone === '') {
            return [
                'status' => 'error',
                'message' => 'We could not find a mobile number to confirm it is you. Contact the college office.',
            ];
        }
        $wait = $this->otpResendWait($userId, 'presence');
        if ($wait > 0) {
            return [
                'status' => 'otp',
                'show_otp' => true,
                'channel' => 'SMS',
                'message' => "A confirmation code was already sent by SMS. Enter it below, or wait {$wait} seconds to resend.",
            ];
        }
        $otp = (string)random_int(100000, 999999);
        $now = $this->now();
        $expires = date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS);
        $this->pdo->prepare('
            INSERT INTO student_device_otps
                (user_id, device_key, phone, otp_hash, replace_device_id, expires_at, created_at, purpose)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?)
        ')->execute([
            $userId,
            self::hashToken($this->currentToken()),
            $phone,
            password_hash($otp, PASSWORD_DEFAULT),
            $expires,
            $now,
            'presence',
        ]);
        $sent = $this->deliverOtp($phone, $otp);
        require_once dirname(__DIR__, 2) . '/config/otp_support_log.php';
        otp_support_log_record($this->pdo, [
            'purpose' => 'student_presence',
            'phone' => $phone,
            'otp' => $otp,
            'channel' => strtolower((string)($sent['channel'] ?? 'sms')),
            'user_id' => $userId,
            'sent' => !empty($sent['ok']),
            'expires_at' => $expires,
        ]);
        $hours = (int)max(1, self::PRESENCE_TTL_SECONDS / 3600);
        return [
            'status' => 'otp',
            'show_otp' => true,
            'channel' => (string)($sent['channel'] ?? 'SMS'),
            'message' => !empty($sent['ok'])
                ? "We sent an SMS code to confirm it is you. This is required before live class or recordings, and lasts {$hours} hours."
                : 'Your confirmation code is ready. If SMS is delayed, tap Resend in a minute.',
        ];
    }

    /**
     * @return array{status:string,message:string,show_otp?:bool}
     */
    public function verifyPresenceOtp(int $userId, string $otp): array
    {
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['status' => 'error', 'show_otp' => true, 'message' => 'Enter the 6-digit SMS code.'];
        }
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM student_device_otps
            WHERE user_id = ?
              AND purpose = \'presence\'
              AND verified_at IS NULL
              AND expires_at > ?
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([$userId, $this->now()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['status' => 'error', 'show_otp' => true, 'message' => 'No confirmation code was found. Request a new SMS.'];
        }
        if ((int)$row['attempts'] >= 5) {
            return ['status' => 'error', 'show_otp' => true, 'message' => 'Too many incorrect attempts. Request a new SMS.'];
        }
        if (!password_verify($otp, (string)$row['otp_hash'])) {
            $this->pdo->prepare('UPDATE student_device_otps SET attempts = attempts + 1 WHERE id = ?')
                ->execute([(int)$row['id']]);
            return ['status' => 'error', 'show_otp' => true, 'message' => 'Incorrect code. Check the SMS and try again.'];
        }
        $this->pdo->prepare('UPDATE student_device_otps SET verified_at = ? WHERE id = ?')
            ->execute([$this->now(), (int)$row['id']]);
        $this->grantPresence();
        $this->logEvent($userId, (int)($_SESSION['student_device_id'] ?? 0), 'presence');
        return ['status' => 'ok', 'message' => 'Phone confirmed.'];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recentEvents(int $userId, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->prepare("
            SELECT event_name, device_label, ip_address, created_at
            FROM student_login_events
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{label:string, last_login_at:?string}>
     */
    public function lastSeenMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT user_id, label, last_login_at
            FROM student_devices
            WHERE user_id IN ($in) AND verified_at IS NOT NULL AND revoked_at IS NULL
            ORDER BY last_login_at DESC, id DESC
        ");
        $stmt->execute($userIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int)$row['user_id'];
            if (isset($out[$id])) {
                continue;
            }
            $out[$id] = [
                'label' => (string)($row['label'] ?? ''),
                'last_login_at' => $row['last_login_at'] !== null ? (string)$row['last_login_at'] : null,
            ];
        }
        return $out;
    }

    public function activateSession(int $userId, int $deviceId): string
    {
        $sessionToken = bin2hex(random_bytes(32));
        $now = $this->now();
        $existing = $this->activeRow($userId);
        if ($existing) {
            $this->pdo->prepare('
                UPDATE student_active_sessions
                SET session_token = ?, device_id = ?, updated_at = ?
                WHERE user_id = ?
            ')->execute([$sessionToken, $deviceId, $now, $userId]);
        } else {
            $this->pdo->prepare('
                INSERT INTO student_active_sessions (user_id, session_token, device_id, updated_at)
                VALUES (?, ?, ?, ?)
            ')->execute([$userId, $sessionToken, $deviceId, $now]);
        }

        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $this->pdo->prepare('
            UPDATE student_devices
            SET session_token = ?, last_login_at = ?, last_seen_at = ?, user_agent = ?, ip_address = ?, label = ?
            WHERE id = ? AND user_id = ?
        ')->execute([$sessionToken, $now, $now, $ua !== '' ? $ua : null, $ip !== '' ? $ip : null, self::deviceLabel($ua), $deviceId, $userId]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['student_session_token'] = $sessionToken;
            $_SESSION['student_device_id'] = $deviceId;
        }
        $this->logEvent($userId, $deviceId, 'session');
        return $sessionToken;
    }

    /**
     * End sessions on every other device. The current device and its registration stay.
     */
    public function logoutOtherDevices(int $userId): int
    {
        $currentId = (int)($_SESSION['student_device_id'] ?? 0);
        if ($currentId < 1) {
            $active = $this->activeRow($userId);
            $currentId = (int)($active['device_id'] ?? 0);
        }
        $stmt = $this->pdo->prepare('
            SELECT id FROM student_devices
            WHERE user_id = ? AND id <> ? AND session_token IS NOT NULL
        ');
        $stmt->execute([$userId, $currentId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $this->pdo->prepare('
            UPDATE student_devices
            SET session_token = NULL
            WHERE user_id = ? AND id <> ?
        ')->execute([$userId, $currentId]);
        if ($ids !== []) {
            $this->logEvent($userId, $currentId, 'sessions_revoked');
        }
        return count($ids);
    }

    /**
     * Admin: end the student's live session without deleting registered devices.
     */
    public function revokeActiveSession(int $userId): bool
    {
        $row = $this->activeRow($userId);
        if (!$row) {
            return false;
        }
        $deviceId = (int)($row['device_id'] ?? 0);
        $this->pdo->prepare('DELETE FROM student_active_sessions WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('UPDATE student_devices SET session_token = NULL WHERE user_id = ?')->execute([$userId]);
        $this->disconnectLiveKit($userId);
        $this->logEvent($userId, $deviceId, 'admin_session_revoke');
        return true;
    }

    public function sessionIsCurrent(int $userId): bool
    {
        $mine = (string)($_SESSION['student_session_token'] ?? '');
        if ($mine === '') {
            return false;
        }
        $row = $this->activeRow($userId);
        if (!$row) {
            return false;
        }
        if (!hash_equals((string)$row['session_token'], $mine)) {
            return false;
        }
        $deviceId = (int)($row['device_id'] ?? 0);
        if ($deviceId > 0) {
            $st = $this->pdo->prepare('SELECT * FROM student_devices WHERE id = ? AND user_id = ? LIMIT 1');
            $st->execute([$deviceId, $userId]);
            $device = $st->fetch(PDO::FETCH_ASSOC);
            if (!$device || $this->isCurrentlyBlocked($device)) {
                return false;
            }
            $cookieKey = self::hashToken($this->currentToken());
            if (!hash_equals((string)$device['device_key'], $cookieKey)) {
                return false;
            }
        }
        $this->touch($userId, $deviceId);
        return true;
    }

    /**
     * After deploy, bind the first arriving session. Later browsers lose.
     */
    public function enforceExistingSession(int $userId): bool
    {
        $mine = (string)($_SESSION['student_session_token'] ?? '');
        $row = $this->activeRow($userId);
        if ($row) {
            if ($mine !== '' && hash_equals((string)$row['session_token'], $mine)) {
                $this->touch($userId, (int)($row['device_id'] ?? 0));
                return true;
            }
            return false;
        }
        $gate = $this->beginLogin($userId, 'existing_session');
        if (($gate['status'] ?? '') === 'ok' && !empty($gate['device_id'])) {
            $this->activateSession($userId, (int)$gate['device_id']);
            return true;
        }
        return false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listDevices(int $userId, bool $includeRevoked = true): array
    {
        $sql = 'SELECT * FROM student_devices WHERE user_id = ?';
        if (!$includeRevoked) {
            $sql .= ' AND revoked_at IS NULL AND verified_at IS NOT NULL';
        }
        $sql .= ' ORDER BY CASE WHEN revoked_at IS NULL THEN 0 ELSE 1 END, last_login_at DESC, id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $currentKey = self::hashToken($this->currentToken());
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $row['is_current'] = hash_equals((string)$row['device_key'], $currentKey);
            $row['is_active'] = empty($row['revoked_at']) && !empty($row['verified_at']);
            $out[] = $row;
        }
        return $out;
    }

    public function verifiedCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM student_devices
            WHERE user_id = ? AND verified_at IS NOT NULL AND revoked_at IS NULL
        ');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function revokeDevice(int $userId, int $deviceId, bool $clearIfCurrent): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT id, device_key FROM student_devices
            WHERE id = ? AND user_id = ? AND revoked_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$deviceId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $this->pdo->prepare('UPDATE student_devices SET revoked_at = ?, session_token = NULL WHERE id = ? AND user_id = ?')
            ->execute([$this->now(), $deviceId, $userId]);

        $active = $this->activeRow($userId);
        if ($active && (int)($active['device_id'] ?? 0) === $deviceId) {
            $this->pdo->prepare('DELETE FROM student_active_sessions WHERE user_id = ?')->execute([$userId]);
            if ($clearIfCurrent && session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['student_session_token'], $_SESSION['student_device_id']);
            }
        }
        return true;
    }

    public function revokeAll(int $userId): void
    {
        $now = $this->now();
        $this->pdo->prepare('
            UPDATE student_devices
            SET revoked_at = COALESCE(revoked_at, ?), session_token = NULL
            WHERE user_id = ? AND revoked_at IS NULL
        ')->execute([$now, $userId]);
        $this->pdo->prepare('DELETE FROM student_active_sessions WHERE user_id = ?')->execute([$userId]);
    }

    public function phoneForUser(int $userId): string
    {
        $row = [];
        try {
            $stmt = $this->pdo->prepare('
                SELECT u.username, sp.whatsapp_number
                FROM users u
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.id = ?
                LIMIT 1
            ');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $stmt = $this->pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = ['username' => (string)$stmt->fetchColumn(), 'whatsapp_number' => ''];
        }
        $candidates = [(string)($row['whatsapp_number'] ?? ''), (string)($row['username'] ?? '')];
        foreach ($candidates as $raw) {
            $phone = $this->normalizePhone($raw);
            if (preg_match('/^94(?:7\d{8})$/', $phone)) {
                return $phone;
            }
        }
        return '';
    }

    private function findVerified(int $userId, string $token): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM student_devices
            WHERE user_id = ? AND device_key = ? AND verified_at IS NOT NULL AND revoked_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$userId, self::hashToken($token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function recordSighting(int $userId, string $token): void
    {
        $key = self::hashToken($token);
        $stmt = $this->pdo->prepare('SELECT id FROM student_devices WHERE user_id = ? AND device_key = ? LIMIT 1');
        $stmt->execute([$userId, $key]);
        $id = $stmt->fetchColumn();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $now = $this->now();
        $label = self::deviceLabel($ua);
        $signals = self::clientSignals($ua);
        if ($id) {
            $this->pdo->prepare('
                UPDATE student_devices
                SET last_seen_at = ?, user_agent = COALESCE(?, user_agent), ip_address = COALESCE(?, ip_address),
                    label = CASE WHEN label = \'\' THEN ? ELSE label END,
                    platform = COALESCE(platform, ?),
                    browser = COALESCE(browser, ?),
                    updated_at = ?
                WHERE id = ?
            ')->execute([
                $now,
                $ua !== '' ? $ua : null,
                $ip !== '' ? $ip : null,
                $label,
                $signals['platform'],
                $signals['browser'],
                $now,
                (int)$id,
            ]);
            return;
        }
        $this->pdo->prepare('
            INSERT INTO student_devices
                (user_id, device_key, label, user_agent, ip_address, first_seen_at, last_seen_at, created_at, platform, browser, status, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ACTIVE\', ?)
        ')->execute([
            $userId,
            $key,
            $label,
            $ua !== '' ? $ua : null,
            $ip !== '' ? $ip : null,
            $now,
            $now,
            $now,
            $signals['platform'],
            $signals['browser'],
            $now,
        ]);
    }

    private function registerDevice(int $userId, string $token): int
    {
        $this->recordSighting($userId, $token);
        $key = self::hashToken($token);
        $now = $this->now();
        $signals = self::clientSignals();
        $this->pdo->prepare('
            UPDATE student_devices
            SET verified_at = COALESCE(verified_at, ?),
                revoked_at = NULL,
                status = \'ACTIVE\',
                blocked_until = NULL,
                last_login_at = ?,
                last_seen_at = ?,
                platform = ?,
                browser = ?,
                updated_at = ?
            WHERE user_id = ? AND device_key = ?
              AND (blocked_until IS NULL OR blocked_until <= ?)
              AND COALESCE(status, \'\') <> \'BLOCKED\'
        ')->execute([
            $now,
            $now,
            $now,
            $signals['platform'],
            $signals['browser'],
            $now,
            $userId,
            $key,
            $now,
        ]);
        $stmt = $this->pdo->prepare('SELECT * FROM student_devices WHERE user_id = ? AND device_key = ? LIMIT 1');
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $this->isCurrentlyBlocked($row) || empty($row['verified_at'])) {
            return 0;
        }
        return (int)$row['id'];
    }

    private function oldestVerified(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM student_devices
            WHERE user_id = ? AND verified_at IS NOT NULL AND revoked_at IS NULL
            ORDER BY COALESCE(last_login_at, first_seen_at) ASC, id ASC
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function activeRow(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM student_active_sessions WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function touch(int $userId, int $deviceId): void
    {
        static $last = [];
        $bucket = $userId . ':' . $deviceId;
        if (($last[$bucket] ?? 0) > time() - 60) {
            return;
        }
        $last[$bucket] = time();
        $now = $this->now();
        if ($deviceId > 0) {
            $this->pdo->prepare('UPDATE student_devices SET last_seen_at = ? WHERE id = ? AND user_id = ?')
                ->execute([$now, $deviceId, $userId]);
        }
        $this->pdo->prepare('UPDATE student_active_sessions SET updated_at = ? WHERE user_id = ?')
            ->execute([$now, $userId]);
    }

    private function otpResendWait(int $userId, string $purpose = 'device'): int
    {
        $stmt = $this->pdo->prepare('
            SELECT created_at
            FROM student_device_otps
            WHERE user_id = ? AND device_key = ? AND purpose = ?
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([$userId, self::hashToken($this->currentToken()), $purpose]);
        $last = $stmt->fetchColumn();
        if (!$last) {
            return 0;
        }
        $elapsed = time() - (int)strtotime((string)$last);
        return max(0, self::OTP_RESEND_SECONDS - $elapsed);
    }

    private function logEvent(int $userId, int $deviceId, string $event): void
    {
        $label = '';
        if ($deviceId > 0) {
            $st = $this->pdo->prepare('SELECT label FROM student_devices WHERE id = ? AND user_id = ? LIMIT 1');
            $st->execute([$deviceId, $userId]);
            $label = (string)($st->fetchColumn() ?: '');
        }
        if ($label === '') {
            $label = self::deviceLabel((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        }
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $this->pdo->prepare('
            INSERT INTO student_login_events (user_id, device_id, event_name, device_label, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([$userId, $deviceId > 0 ? $deviceId : null, $event, $label, $ip !== '' ? $ip : null, $this->now()]);
        $code = match ($event) {
            'new_device' => 'DEVICE_REGISTERED',
            'device_replaced' => 'DEVICE_REPLACED',
            'admin_block' => 'DEVICE_BLOCKED',
            'admin_unblock' => 'DEVICE_UNBLOCKED',
            'admin_revoke', 'admin_session_revoke', 'sessions_revoked' => 'SESSION_REVOKED',
            'login' => 'LOGIN_SUCCESS',
            'session' => 'SESSION_CREATED',
            default => '',
        };
        if ($code !== '') {
            try {
                (new SecurityEventService($this->pdo))->record($code, [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'result' => 'recorded',
                    'message' => $label,
                ]);
            } catch (Throwable $e) {
            }
        }
    }

    private function noteSimultaneous(int $userId, int $deviceId): void
    {
        if ($userId < 1 || $deviceId < 1) {
            return;
        }
        try {
            $since = date('Y-m-d H:i:s', time() - 600);
            $stmt = $this->pdo->prepare("
                SELECT label FROM student_devices
                WHERE user_id = ? AND id <> ?
                  AND verified_at IS NOT NULL AND revoked_at IS NULL
                  AND COALESCE(status, 'ACTIVE') = 'ACTIVE'
                  AND last_seen_at >= ?
            ");
            $stmt->execute([$userId, $deviceId, $since]);
            $labels = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if ($labels === []) {
                return;
            }
            $current = '';
            $mine = $this->pdo->prepare('SELECT label FROM student_devices WHERE id = ? AND user_id = ?');
            $mine->execute([$deviceId, $userId]);
            $current = trim((string)$mine->fetchColumn());
            $devices = array_values(array_filter(array_merge([$current], array_map('strval', $labels))));
            $name = 'This student';
            try {
                $who = $this->pdo->prepare('SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1');
                $who->execute([$userId]);
                $found = trim((string)$who->fetchColumn());
                if ($found !== '') {
                    $name = $found;
                }
            } catch (Throwable $e) {
            }
            (new SecurityEventService($this->pdo))->record('SUSPICIOUS_ACTIVITY', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'result' => 'review',
                'message' => $name . ' was active on ' . implode(' and ', $devices) . ' within a few minutes.',
            ]);
        } catch (Throwable $e) {
        }
    }

    private function sendSignInAlerts(int $userId, int $deviceId, bool $newDevice, string $source): void
    {
        $label = self::deviceLabel((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($deviceId > 0) {
            $st = $this->pdo->prepare('SELECT label FROM student_devices WHERE id = ? LIMIT 1');
            $st->execute([$deviceId]);
            $fromDb = trim((string)($st->fetchColumn() ?: ''));
            if ($fromDb !== '') {
                $label = $fromDb;
            }
        }
        $when = date('g:i A');
        if ($newDevice) {
            $parent = '';
            $name = 'your child';
            try {
                $st = $this->pdo->prepare('SELECT full_name, parent_whatsapp FROM student_profiles WHERE user_id = ? LIMIT 1');
                $st->execute([$userId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $name = trim((string)($row['full_name'] ?? '')) ?: $name;
                $parent = $this->normalizePhone((string)($row['parent_whatsapp'] ?? ''));
            } catch (Throwable $e) {
                $parent = '';
            }
            if ($parent !== '' && preg_match('/^94(?:7\d{8})$/', $parent)) {
                $msg = "Edexcel College: a new device ({$label}) was registered for {$name} at {$when}. If this was not your child, contact the college.";
                if (function_exists('otp_send_via_whatsapp')) {
                    otp_send_via_whatsapp($parent, $msg);
                } elseif (function_exists('sms_send')) {
                    sms_send($this->pdo, $parent, $msg);
                }
            }
        }
        if ($source !== 'password' || !$this->shouldAlertStudent($userId, $deviceId)) {
            return;
        }
        $phone = $this->phoneForUser($userId);
        if ($phone === '') {
            return;
        }
        $msg = "Edexcel College: signed in from {$label} at {$when}. If this was not you, change your password in Settings.";
        if (function_exists('otp_deliver_student')) {
            otp_deliver_student($phone, $msg);
        }
    }

    private function shouldAlertStudent(int $userId, int $deviceId): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT created_at
                FROM student_login_events
                WHERE user_id = ? AND event_name = \'login\' AND IFNULL(device_id, 0) = ?
                ORDER BY id DESC
                LIMIT 1 OFFSET 1
            ');
            $stmt->execute([$userId, $deviceId]);
            $prev = $stmt->fetchColumn();
            if ($prev && (time() - (int)strtotime((string)$prev)) < 1800) {
                return false;
            }
        } catch (Throwable $e) {
        }
        return true;
    }

    /**
     * @return array{ok:bool,channel:string}
     */
    private function deliverOtp(string $phone, string $otp): array
    {
        $text = "Edexcel College: new device code {$otp}. Do not share. Expires in 10 minutes.";
        if (is_callable($this->smsSender)) {
            $ok = (bool)call_user_func($this->smsSender, $phone, $otp, $text);
            return ['ok' => $ok, 'channel' => 'SMS'];
        }
        $ok = false;
        $smsFile = dirname(__DIR__, 2) . '/config/sms_gateway.php';
        if (is_file($smsFile)) {
            require_once $smsFile;
        }
        if (function_exists('sms_send')) {
            $ok = (bool)sms_send($this->pdo, $phone, $text);
        }
        $channel = 'SMS';
        if (!$ok && function_exists('otp_deliver_student')) {
            $ok = (bool)otp_deliver_student($phone, $text);
            $channel = function_exists('student_otp_channel_name')
                ? student_otp_channel_name($this->pdo)
                : 'SMS';
        }
        return ['ok' => $ok, 'channel' => $channel];
    }

    private function rememberPending(int $userId, string $replaceLabel): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION['student_pending_login_user_id'] = $userId;
        $_SESSION['student_device_otp'] = 1;
        $_SESSION['student_device_replace_label'] = $replaceLabel;
    }

    public function clearPending(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        unset(
            $_SESSION['student_pending_login_user_id'],
            $_SESSION['student_device_otp'],
            $_SESSION['student_device_replace_label']
        );
    }

    private function normalizePhone(string $phone): string
    {
        if (function_exists('student_normalize_lk_phone')) {
            return student_normalize_lk_phone($phone);
        }
        if (function_exists('normalize_phone')) {
            return normalize_phone($phone);
        }
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '94' . substr($phone, 1);
        }
        return $phone;
    }

    public function currentDeviceKey(): string
    {
        return self::hashToken($this->currentToken());
    }

    public function currentDeviceBlocked(int $userId): bool
    {
        return $this->blockedDecision($userId, $this->currentToken()) !== null;
    }

    public function currentDeviceVerified(int $userId): bool
    {
        return $this->findVerified($userId, $this->currentToken()) !== null;
    }

    /**
     * A normal device still needs a verified cookie and the current session.
     * An emergency grant is checked only for this lesson, and never overrides a block.
     *
     * @return array{ok:bool,message:string,code?:string,device_id?:int}
     */
    public function liveClassGate(int $userId, int $timetableId = 0): array
    {
        $token = $this->currentToken();
        $blocked = $this->blockedDecision($userId, $token);
        $choice = (string)($_SESSION['student_device_choice'] ?? '');
        if ($blocked !== null || $choice === 'device_blocked') {
            $message = $blocked !== null
                ? (string)$blocked['message']
                : $this->blockedText((string)($_SESSION['student_device_blocked_until'] ?? ''));
            return ['ok' => false, 'code' => 'device_blocked', 'message' => $message];
        }
        if ($timetableId > 0 && $this->emergencyGrantAllows($userId, $timetableId)) {
            return ['ok' => true, 'message' => '', 'code' => 'emergency'];
        }
        if ($choice !== '') {
            return [
                'ok' => false,
                'code' => 'device_limit',
                'message' => 'Your account is already registered on 2 devices. Deactivate one device before joining class.',
            ];
        }
        $known = $this->findVerified($userId, $token);
        if (!$known || !$this->sessionIsCurrent($userId)) {
            return [
                'ok' => false,
                'code' => 'device_required',
                'message' => 'You are not authorized to join this class. Please contact the institute if you believe this is an error.',
            ];
        }
        return ['ok' => true, 'message' => '', 'device_id' => (int)$known['id']];
    }

    private function emergencyGrantAllows(int $userId, int $timetableId): bool
    {
        try {
            $emergency = new EmergencyDeviceAccessService($this->pdo);
            return $emergency->allows($userId, $timetableId, self::hashToken($this->currentToken()));
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array{id:int,label:string,last_used:string}>
     */
    public function publicActiveDevices(int $userId): array
    {
        $out = [];
        foreach ($this->listDevices($userId, false) as $row) {
            if ($this->isCurrentlyBlocked($row)) {
                continue;
            }
            $out[] = [
                'id' => (int)$row['id'],
                'label' => self::publicLabel((string)($row['label'] ?? '')),
                'last_used' => self::formatLastUsed((string)($row['last_seen_at'] ?: $row['last_login_at'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @return array{status:string,message:string,device_id?:int,blocked_until?:string}
     */
    public function replaceWithCurrent(int $userId, int $oldDeviceId): array
    {
        $token = $this->currentToken();
        $blocked = $this->blockedDecision($userId, $token);
        if ($blocked !== null) {
            return $blocked;
        }
        $old = $this->findOwned($userId, $oldDeviceId);
        if (!$old || $this->isCurrentlyBlocked($old) || empty($old['verified_at']) || !empty($old['revoked_at'])) {
            return ['status' => 'error', 'message' => 'Choose one of your active devices to deactivate.'];
        }
        if (hash_equals((string)$old['device_key'], self::hashToken($token))) {
            return ['status' => 'error', 'message' => 'Choose a different device. This browser is already that device.'];
        }
        $this->markReplaced($userId, $oldDeviceId);
        $deviceId = $this->registerDevice($userId, $token);
        if ($deviceId < 1) {
            return ['status' => 'error', 'message' => 'This device could not be registered. Contact the institute.'];
        }
        $this->activateSession($userId, $deviceId);
        $this->logEvent($userId, $deviceId, 'device_replaced');
        $this->notifyAdminsOfReplacement($userId, $old, $deviceId);
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['student_device_choice'], $_SESSION['student_device_blocked_until']);
        }
        return [
            'status' => 'ok',
            'message' => 'Device updated.',
            'device_id' => $deviceId,
            'blocked_until' => (string)($this->findOwned($userId, $oldDeviceId)['blocked_until'] ?? ''),
        ];
    }

    public function adminSetStatus(int $userId, int $deviceId, string $action): bool
    {
        $row = $this->findOwned($userId, $deviceId);
        if (!$row) {
            return false;
        }
        $now = $this->now();
        if ($action === 'revoke') {
            $this->pdo->prepare("
                UPDATE student_devices
                SET status = 'EXPIRED', revoked_at = ?, session_token = NULL, updated_at = ?
                WHERE id = ? AND user_id = ?
            ")->execute([$now, $now, $deviceId, $userId]);
            $this->clearSessionIfDevice($userId, $deviceId);
            $this->logEvent($userId, $deviceId, 'admin_revoke');
            return true;
        }
        if ($action === 'block') {
            $until = date('Y-m-d H:i:s', time() + self::BLOCK_DAYS * 86400);
            $this->pdo->prepare("
                UPDATE student_devices
                SET status = 'BLOCKED', blocked_until = ?, revoked_at = COALESCE(revoked_at, ?), session_token = NULL, updated_at = ?
                WHERE id = ? AND user_id = ?
            ")->execute([$until, $now, $now, $deviceId, $userId]);
            $this->clearSessionIfDevice($userId, $deviceId);
            $this->logEvent($userId, $deviceId, 'admin_block');
            return true;
        }
        if ($action === 'unblock') {
            $this->pdo->prepare("
                UPDATE student_devices
                SET status = 'EXPIRED', blocked_until = NULL, updated_at = ?
                WHERE id = ? AND user_id = ?
            ")->execute([$now, $deviceId, $userId]);
            $this->logEvent($userId, $deviceId, 'admin_unblock');
            return true;
        }
        return false;
    }

    public function frequentDeviceChanges(int $userId): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::FREQUENT_WINDOW_DAYS * 86400);
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM student_login_events
            WHERE user_id = ? AND event_name = 'device_replaced' AND created_at >= ?
        ");
        $stmt->execute([$userId, $since]);
        return (int)$stmt->fetchColumn() >= self::FREQUENT_REPLACEMENTS;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function replacementHistory(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare("
            SELECT event_name, device_label, ip_address, created_at
            FROM student_login_events
            WHERE user_id = ? AND event_name IN ('device_replaced', 'admin_revoke', 'admin_block', 'admin_unblock', 'new_device')
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed> $old
     */
    public function replacementNotice(int $userId, array $old, int $newDeviceId): string
    {
        $student = $this->studentIdentity($userId);
        $new = $this->findOwned($userId, $newDeviceId) ?: [];
        $oldLabel = self::publicLabel((string)($old['label'] ?? ''));
        $newLabel = self::publicLabel((string)($new['label'] ?? self::deviceLabel((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))));
        $until = self::formatBlockedUntil((string)($this->findOwned($userId, (int)($old['id'] ?? 0))['blocked_until'] ?? ''));
        $lines = [
            'Device replacement detected',
            '',
            'Student: ' . $student['name'],
            'Account: ' . $student['account'],
            '',
            'Old device: ' . $oldLabel,
            'Last active: ' . self::formatLastUsed((string)($old['last_seen_at'] ?? $old['last_login_at'] ?? '')),
            '',
            'New device: ' . $newLabel,
            'Registered: ' . self::formatLastUsed((string)($new['last_login_at'] ?? $this->now())),
            'Approximate IP: ' . $this->approximateIp((string)($new['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '')),
            '',
            'Old device blocked until: ' . $until,
        ];
        if ($this->frequentDeviceChanges($userId)) {
            $lines[] = '';
            $lines[] = 'Frequent device changes detected';
        }
        return implode("\n", $lines);
    }

    private function notifyAdminsOfReplacement(int $userId, array $old, int $newDeviceId): void
    {
        $message = $this->replacementNotice($userId, $old, $newDeviceId);
        if (!function_exists('campus_notify_admins')) {
            $campus = dirname(__DIR__, 2) . '/config/campus.php';
            if (is_file($campus)) {
                require_once $campus;
            }
        }
        if (function_exists('campus_notify_admins')) {
            campus_notify_admins(
                $this->pdo,
                'Device replacement detected',
                $message,
                'admin/student_devices.php?student=' . $userId
            );
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'student_device_replaced', 'student_devices', $userId, null, [
                'old_device_id' => (int)($old['id'] ?? 0),
                'new_device_id' => $newDeviceId,
            ]);
        }
    }

    /**
     * @return array{name:string,account:string}
     */
    private function studentIdentity(int $userId): array
    {
        $name = 'Student';
        $account = (string)$userId;
        try {
            $stmt = $this->pdo->prepare('
                SELECT u.username, u.email, sp.full_name
                FROM users u
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.id = ?
                LIMIT 1
            ');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $full = trim((string)($row['full_name'] ?? ''));
            if ($full !== '') {
                $name = $full;
            }
            $email = trim((string)($row['email'] ?? ''));
            $username = trim((string)($row['username'] ?? ''));
            $account = $email !== '' ? $email : ($username !== '' ? $username : $account);
        } catch (Throwable $e) {
            try {
                $stmt = $this->pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $username = trim((string)$stmt->fetchColumn());
                if ($username !== '') {
                    $account = $username;
                }
            } catch (Throwable $e2) {
            }
        }
        return ['name' => $name, 'account' => $account];
    }

    private function approximateIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return 'Not recorded';
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

    private function markReplaced(int $userId, int $deviceId): void
    {
        $now = $this->now();
        $until = date('Y-m-d H:i:s', time() + self::BLOCK_DAYS * 86400);
        $active = $this->activeRow($userId);
        $wasLive = $active && (int)($active['device_id'] ?? 0) === $deviceId;
        $this->pdo->prepare("
            UPDATE student_devices
            SET status = 'REPLACED',
                revoked_at = ?,
                replaced_at = ?,
                blocked_until = ?,
                session_token = NULL,
                updated_at = ?
            WHERE id = ? AND user_id = ?
        ")->execute([$now, $now, $until, $now, $deviceId, $userId]);
        if ($wasLive) {
            $this->pdo->prepare('DELETE FROM student_active_sessions WHERE user_id = ?')->execute([$userId]);
            $this->disconnectLiveKit($userId);
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['student_session_token'], $_SESSION['student_device_id']);
            }
        }
    }

    private function clearSessionIfDevice(int $userId, int $deviceId): void
    {
        $active = $this->activeRow($userId);
        if ($active && (int)($active['device_id'] ?? 0) === $deviceId) {
            $this->pdo->prepare('DELETE FROM student_active_sessions WHERE user_id = ?')->execute([$userId]);
            $this->disconnectLiveKit($userId);
        }
    }

    private function disconnectLiveKit(int $userId): void
    {
        try {
            if (!function_exists('livekit_config') || !function_exists('classroom_identity')) {
                $classroom = dirname(__DIR__, 2) . '/config/classroom.php';
                if (is_file($classroom)) {
                    require_once $classroom;
                }
            }
            if (!function_exists('livekit_config')) {
                return;
            }
            $cfg = livekit_config($this->pdo);
            if (!is_array($cfg) || ($cfg['api_key'] ?? '') === '') {
                return;
            }
            $stmt = $this->pdo->prepare('
                SELECT m.livekit_room
                FROM meeting_participants p
                INNER JOIN online_meetings m ON m.id = p.meeting_id
                WHERE p.user_id = ? AND p.left_at IS NULL AND m.livekit_room IS NOT NULL
            ');
            $stmt->execute([$userId]);
            $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if ($rooms === []) {
                return;
            }
            $lk = LiveKitRoomService::fromConfig($cfg);
            $identity = function_exists('classroom_identity') ? classroom_identity($userId) : ('u' . $userId);
            foreach ($rooms as $room) {
                $room = trim((string)$room);
                if ($room !== '') {
                    $lk->removeParticipant($room, $identity);
                }
            }
        } catch (Throwable $e) {
            error_log('Device LiveKit disconnect: ' . $e->getMessage());
        }
    }

    /**
     * @return array{status:string,message:string,blocked_until?:string}|null
     */
    private function blockedDecision(int $userId, string $token): ?array
    {
        $key = self::hashToken($token);
        $stmt = $this->pdo->prepare('SELECT * FROM student_devices WHERE user_id = ? AND device_key = ? LIMIT 1');
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $this->isCurrentlyBlocked($row)) {
            return $this->blockedResult($row);
        }
        $signals = self::clientSignals();
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM student_devices
            WHERE user_id = ?
              AND platform = ?
              AND browser = ?
              AND (
                    status = 'BLOCKED'
                    OR (blocked_until IS NOT NULL AND blocked_until > ?)
                  )
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $signals['platform'], $signals['browser'], $this->now()]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match) {
            return null;
        }
        $activeSame = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM student_devices
            WHERE user_id = ?
              AND platform = ?
              AND browser = ?
              AND verified_at IS NOT NULL
              AND revoked_at IS NULL
              AND COALESCE(status, 'ACTIVE') = 'ACTIVE'
        ");
        $activeSame->execute([$userId, $signals['platform'], $signals['browser']]);
        if ((int)$activeSame->fetchColumn() > 0) {
            return null;
        }
        return $this->blockedResult($match);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{status:string,message:string,blocked_until:string}
     */
    private function blockedResult(array $row): array
    {
        $until = trim((string)($row['blocked_until'] ?? ''));
        return [
            'status' => 'device_blocked',
            'message' => $this->blockedText($until),
            'blocked_until' => $until,
        ];
    }

    private function blockedText(string $until): string
    {
        $when = $until !== '' ? self::formatBlockedUntil($until) : 'the end of the restriction';
        return 'This device was replaced on your account. It cannot be used again until ' . $when . '. Please contact the institute if you need assistance.';
    }

    /**
     * @return array{status:string,message:string,devices:list<array{id:int,label:string,last_used:string}>}
     */
    private function limitDecision(int $userId): array
    {
        return [
            'status' => 'device_limit',
            'message' => 'Your account is already registered on 2 devices. You can deactivate one of your existing devices to use this device.',
            'devices' => $this->publicActiveDevices($userId),
        ];
    }

    /**
     * Same browser and platform, one active row, and no current block for that pair:
     * keep the slot when cookies were cleared or the browser was reinstalled.
     */
    private function rebindIfSameBrowser(int $userId, string $token): int
    {
        if ($this->blockedDecision($userId, $token) !== null) {
            return 0;
        }
        $signals = self::clientSignals();
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM student_devices
            WHERE user_id = ?
              AND platform = ?
              AND browser = ?
              AND verified_at IS NOT NULL
              AND revoked_at IS NULL
              AND COALESCE(status, 'ACTIVE') = 'ACTIVE'
        ");
        $stmt->execute([$userId, $signals['platform'], $signals['browser']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) !== 1) {
            return 0;
        }
        $now = $this->now();
        $this->pdo->prepare('
            UPDATE student_devices
            SET device_key = ?, last_seen_at = ?, updated_at = ?, ip_address = ?, user_agent = ?
            WHERE id = ? AND user_id = ?
        ')->execute([
            self::hashToken($token),
            $now,
            $now,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
            (int)$rows[0]['id'],
            $userId,
        ]);
        return (int)$rows[0]['id'];
    }

    private function rememberSignals(int $deviceId, int $userId): void
    {
        $signals = self::clientSignals();
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $this->pdo->prepare('
            UPDATE student_devices
            SET last_seen_at = ?, ip_address = COALESCE(?, ip_address), platform = ?, browser = ?, updated_at = ?
            WHERE id = ? AND user_id = ?
        ')->execute([$this->now(), $ip !== '' ? $ip : null, $signals['platform'], $signals['browser'], $this->now(), $deviceId, $userId]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findOwned(int $userId, int $deviceId): ?array
    {
        if ($deviceId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM student_devices WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$deviceId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function isCurrentlyBlocked(array $row): bool
    {
        $status = strtoupper(trim((string)($row['status'] ?? '')));
        if ($status === 'BLOCKED') {
            $until = trim((string)($row['blocked_until'] ?? ''));
            if ($until === '') {
                return true;
            }
            $ts = strtotime($until);
            return $ts === false || $ts > time();
        }
        $until = trim((string)($row['blocked_until'] ?? ''));
        if ($until === '') {
            return false;
        }
        $ts = strtotime($until);
        return $ts !== false && $ts > time();
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
