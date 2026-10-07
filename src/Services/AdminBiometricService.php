<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * AdminBiometricService
 *
 * Master orchestration service for the production-grade Admin-Only
 * Biometric Authentication System (Passkeys/WebAuthn & Webcam Face Verification).
 *
 * Enforces:
 * - Admin-only authorization (strictly restricted to the single administrator account)
 * - 1:1 verification constraint (no uncontrolled database-wide face search)
 * - AES-256-GCM authenticated encryption for biometric templates at rest
 * - Ephemeral single-use cryptographic challenges with short TTL
 * - Rate limiting, progressive throttling, and suspicious attempt mitigation
 * - Comprehensive authentication audit logging
 * - Dual-role session preservation (Administrator + Teacher)
 */
final class AdminBiometricService
{
    public const METHOD_PASSKEY = 'passkey';
    public const METHOD_FACE = 'face';
    public const METHOD_PASSWORD = 'password';
    public const METHOD_GOOGLE = 'google';
    public const METHOD_TOTP = 'totp';
    public const METHOD_OTP = 'otp';

    private const CHALLENGE_TTL_SECONDS = 90;
    private const MAX_CONSECUTIVE_FAILURES = 8;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Resolves the single authorized administrator account in the system.
     *
     * @return array<string,mixed>
     * @throws RuntimeException If no active administrator is found
     */
    public function getAdminAccount(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, t.name AS teacher_name, t.phone AS teacher_phone
            FROM users u
            LEFT JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
            WHERE u.role = 'admin'
              AND (u.is_active = 1 OR u.is_active IS NULL)
              AND u.deleted_at IS NULL
            ORDER BY u.id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            throw new RuntimeException('No active administrator account configured.');
        }

        // Ensure administrator performs teaching duties by having a linked teacher record
        if (empty($admin['teacher_id'])) {
            try {
                $tStmt = $this->pdo->prepare("SELECT id, name FROM teachers WHERE email = ? OR name = ? LIMIT 1");
                $tStmt->execute(['admin@edexcel.college', $admin['username']]);
                $teacher = $tStmt->fetch(PDO::FETCH_ASSOC);

                if ($teacher) {
                    $teacherId = (int)$teacher['id'];
                    $teacherName = (string)$teacher['name'];
                } else {
                    $ins = $this->pdo->prepare("INSERT INTO teachers (name, email) VALUES (?, ?)");
                    $displayName = 'Administrator / Head Teacher';
                    $ins->execute([$displayName, 'admin@edexcel.college']);
                    $teacherId = (int)$this->pdo->lastInsertId();
                    $teacherName = $displayName;
                }

                $this->pdo->prepare("UPDATE users SET teacher_id = ? WHERE id = ?")->execute([$teacherId, (int)$admin['id']]);
                $admin['teacher_id'] = $teacherId;
                $admin['teacher_name'] = $teacherName;
            } catch (Throwable $e) {
                error_log('AdminBiometricService: unable to link teacher record: ' . $e->getMessage());
            }
        }

        return $admin;
    }

    /**
     * Ensures the given user ID belongs strictly to the administrator account.
     */
    public function validateIsAdminUser(int $userId): bool
    {
        try {
            $admin = $this->getAdminAccount();
            return (int)$admin['id'] === $userId;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Validates that the given user ID belongs to an active, non-deleted user account of any role.
     */
    public function validateUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM users
                WHERE id = ?
                  AND (is_active = 1 OR is_active IS NULL)
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Retrieves an active user account by ID with linked teacher data if applicable.
     *
     * @param int $userId
     * @return array<string,mixed>
     * @throws RuntimeException If user not found or inactive
     */
    public function getUserAccount(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, t.name AS teacher_name, t.phone AS teacher_phone
            FROM users u
            LEFT JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
            WHERE u.id = ?
              AND (u.is_active = 1 OR u.is_active IS NULL)
              AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('User account not found or inactive.');
        }

        return $user;
    }

    /**
     * Finds an active user account by username, Google email, or associated phone number.
     *
     * @param string $identifier
     * @return array<string,mixed>|null
     */
    public function findUserByIdentifier(string $identifier): ?array
    {
        $clean = trim($identifier);
        if ($clean === '') {
            return null;
        }

        // 1. Direct match on username or google_email
        $stmt = $this->pdo->prepare("
            SELECT u.*, t.name AS teacher_name, t.phone AS teacher_phone
            FROM users u
            LEFT JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
            WHERE (u.username = ? OR u.google_email = ?)
              AND (u.is_active = 1 OR u.is_active IS NULL)
              AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$clean, $clean]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        // 2. Normalized phone lookup if input looks like a phone number
        $cleanPhone = preg_replace('/[^\d+]/', '', $clean) ?? '';
        if (strlen($cleanPhone) >= 9) {
            try {
                $pStmt = $this->pdo->prepare("
                    SELECT u.*, t.name AS teacher_name, t.phone AS teacher_phone
                    FROM users u
                    LEFT JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
                    LEFT JOIN student_profiles sp ON sp.user_id = u.id
                    WHERE (sp.whatsapp_number LIKE ? OR t.phone LIKE ? OR u.username LIKE ?)
                      AND (u.is_active = 1 OR u.is_active IS NULL)
                      AND u.deleted_at IS NULL
                    LIMIT 1
                ");
                $like = '%' . substr($cleanPhone, -9);
                $pStmt->execute([$like, $like, $like]);
                $user = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    return $user;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * Strictly verifies that the given user ID belongs to the authorized administrator account.
     * Throws RuntimeException if not authorized.
     */
    public function ensureAdminUser(int $userId): void
    {
        if (!$this->validateIsAdminUser($userId)) {
            throw new RuntimeException('Unauthorized: Administrator privilege required for biometric authentication.');
        }
    }

    /**
     * Creates an ephemeral, single-use biometric challenge token.
     *
     * @param string $type 'passkey_reg'|'passkey_auth'|'face_enrol'|'face_auth'
     * @param int $userId
     * @param array<string,mixed> $payload
     * @return string Challenge token
     */
    public function createChallenge(string $type, int $userId, array $payload = []): string
    {
        // Opportunistic cleanup: prune expired challenge rows older than 2 hours
        if (random_int(1, 10) === 1) {
            try {
                $pruneCutoff = date('Y-m-d H:i:s', time() - 7200);
                $this->pdo->prepare("
                    DELETE FROM admin_biometric_challenges
                    WHERE expires_at < ?
                ")->execute([$pruneCutoff]);
            } catch (Throwable) {
                // Ignore non-fatal cleanup failure
            }
        }

        $token = bin2hex(random_bytes(32));
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $expiresAt = date('Y-m-d H:i:s', time() + self::CHALLENGE_TTL_SECONDS);

        $stmt = $this->pdo->prepare("
            INSERT INTO admin_biometric_challenges
                (challenge_type, user_id, challenge_token, challenge_payload, ip_address, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $type,
            $userId,
            $token,
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            $ip,
            $expiresAt,
        ]);

        return $token;
    }

    /**
     * Validates and immediately consumes a single-use challenge token.
     *
     * @param string $type Expected challenge type
     * @param int $userId Expected user ID
     * @param string $token Challenge token to consume
     * @return array<string,mixed> Stored challenge payload
     * @throws RuntimeException If invalid, expired, or already used
     */
    public function consumeChallenge(string $type, int $userId, string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Biometric challenge token is missing.');
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM admin_biometric_challenges
            WHERE challenge_token = ?
              AND challenge_type = ?
              AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$token, $type, $userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            throw new RuntimeException('Invalid or unknown biometric challenge.');
        }

        if (!empty($record['used_at'])) {
            throw new RuntimeException('This biometric challenge has already been used.');
        }

        if (strtotime((string)$record['expires_at']) < time()) {
            throw new RuntimeException('Biometric challenge expired. Please try again.');
        }

        // Mark as used immediately to guarantee single-use anti-replay protection
        $this->pdo->prepare("
            UPDATE admin_biometric_challenges
            SET used_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([(int)$record['id']]);

        $payload = json_decode((string)$record['challenge_payload'], true);
        return is_array($payload) ? $payload : [];
    }

    /**
     * Checks if biometric attempts from the client IP / user account are locked or throttled.
     *
     * @param string $username
     * @param int|null $userId
     * @return array{is_locked:bool, delay_seconds:int, attempts_recent:int}
     */
    public function checkThrottling(string $username, ?int $userId = null): array
    {
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $cutoff = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        // 1. Hard lockout strictly enforced against the client IP address
        $stmtIp = $this->pdo->prepare("
            SELECT COUNT(*) FROM authentication_audit
            WHERE ip_address = ?
              AND success = 0
              AND created_at >= ?
        ");
        $stmtIp->execute([$ip, $cutoff]);
        $ipFailures = (int)$stmtIp->fetchColumn();

        if ($ipFailures >= self::MAX_CONSECUTIVE_FAILURES) {
            return [
                'is_locked' => true,
                'delay_seconds' => self::LOCKOUT_MINUTES * 60,
                'attempts_recent' => $ipFailures,
            ];
        }

        // 2. Failures on targeted user account to introduce progressive delays against distributed attacks
        $targetUserId = ($userId !== null && $userId > 0) ? $userId : 1;
        $stmtUser = $this->pdo->prepare("
            SELECT COUNT(*) FROM authentication_audit
            WHERE user_id = ?
              AND success = 0
              AND created_at >= ?
        ");
        $stmtUser->execute([$targetUserId, $cutoff]);
        $userFailures = (int)$stmtUser->fetchColumn();

        $delay = 0;
        $maxFailures = max($ipFailures, $userFailures);
        if ($maxFailures >= 5) {
            $delay = 4;
        } elseif ($maxFailures >= 3) {
            $delay = 1;
        }

        return [
            'is_locked' => false,
            'delay_seconds' => $delay,
            'attempts_recent' => $ipFailures,
        ];
    }

    /**
     * Records an authentication event in the dedicated audit log and the system security log.
     *
     * @param string $method passkey|face|password|google|totp|otp
     * @param bool $success
     * @param int|null $userId
     * @param string|null $failureReason
     * @param array<string,mixed> $metadata NEVER include passwords, private keys, or raw biometrics
     */
    public function recordAudit(
        string $method,
        bool $success,
        ?int $userId = null,
        ?string $failureReason = null,
        array $metadata = []
    ): void {
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $sessRef = session_id() ? hash('sha256', session_id() . 'salt_audit') : null;

        // Defensive sanitation: strip any sensitive or credential keys if accidentally provided
        $sensitiveKeys = ['password', 'secret', 'private_key', 'raw', 'descriptor', 'descriptors', 'embedding', 'token'];
        $cleanMetadata = [];
        foreach ($metadata as $k => $v) {
            if (in_array(strtolower((string)$k), $sensitiveKeys, true)) {
                $cleanMetadata[$k] = '[REDACTED]';
            } else {
                $cleanMetadata[$k] = $v;
            }
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO authentication_audit
                    (user_id, authentication_method, success, failure_reason, ip_address, user_agent, session_reference, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $method,
                $success ? 1 : 0,
                $failureReason ? substr($failureReason, 0, 255) : null,
                $ip,
                $userAgent,
                $sessRef,
                !empty($cleanMetadata) ? json_encode($cleanMetadata, JSON_UNESCAPED_SLASHES) : null,
            ]);

            // Also integrate into existing SecurityEventService
            $sec = new SecurityEventService($this->pdo);
            $eventCode = $success ? 'ADMIN_AUTH_SUCCESS' : 'ADMIN_AUTH_FAILURE';
            $msg = sprintf(
                'Admin %s authentication %s%s',
                strtoupper($method),
                $success ? 'successful' : 'failed',
                $failureReason ? ': ' . $failureReason : ''
            );
            $sec->record(
                $eventCode,
                $msg,
                $success ? 'info' : 'warning',
                $userId,
                'admin',
                'admin',
                'auth'
            );
        } catch (Throwable $e) {
            error_log('Biometric audit recording error: ' . $e->getMessage());
        }
    }

    /**
     * Finishes a successful admin authentication session:
     * - Regenerates session ID
     * - Clears conflicting cross-portal sessions
     * - Loads and preserves BOTH Admin and Teacher permissions
     * - Records success in the audit logs
     *
     * @param array<string,mixed> $adminUser
     * @param string $authMethod passkey|face
     */
    public function establishAdminSession(array $adminUser, string $authMethod): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            if (function_exists('regenerate_session')) {
                regenerate_session();
            } else {
                session_regenerate_id(true);
            }
        }

        if (function_exists('clear_cross_portal_session')) {
            clear_cross_portal_session('staff');
        }

        $userId = (int)$adminUser['id'];
        $teacherId = !empty($adminUser['teacher_id']) ? (int)$adminUser['teacher_id'] : null;

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = (string)$adminUser['username'];
        $_SESSION['auth_method'] = $authMethod;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();

        // Preserve Teacher duties & permissions for the same administrator account
        if ($teacherId !== null) {
            $_SESSION['teacher_id'] = $teacherId;
        }
        $_SESSION['can_teach'] = true;
        $_SESSION['has_teacher_role'] = true;
        $_SESSION['teacher_access'] = true;

        if (function_exists('app_theme_on_login')) {
            app_theme_on_login($this->pdo, $adminUser);
        }

        $this->recordAudit($authMethod, true, $userId, null, [
            'role' => 'admin',
            'has_teacher_permissions' => true,
            'teacher_id' => $teacherId,
        ]);
    }

    /**
     * Establishes a verified authentication session for any user role (admin, teacher, student).
     *
     * @param array<string,mixed> $user
     * @param string $authMethod passkey|face
     * @return string Redirect destination URL
     */
    public function establishSession(array $user, string $authMethod): string
    {
        $role = strtolower(trim((string)($user['role'] ?? 'student')));
        $userId = (int)$user['id'];

        if ($role === 'admin') {
            $this->establishAdminSession($user, $authMethod);
            return (string)BASE_URL . 'dashboard.php';
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            if (function_exists('regenerate_session')) {
                regenerate_session();
            } else {
                session_regenerate_id(true);
            }
        }

        if ($role === 'teacher') {
            if (function_exists('clear_cross_portal_session')) {
                clear_cross_portal_session('staff');
            }

            $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : null;

            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = 'teacher';
            $_SESSION['username'] = (string)$user['username'];
            if ($teacherId !== null) {
                $_SESSION['teacher_id'] = $teacherId;
            }
            $_SESSION['auth_method'] = $authMethod;
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();

            if (function_exists('app_theme_on_login')) {
                app_theme_on_login($this->pdo, $user);
            }

            $this->recordAudit($authMethod, true, $userId, null, [
                'role' => 'teacher',
                'teacher_id' => $teacherId,
            ]);

            return (string)BASE_URL . 'dashboard.php';
        }

        // Student role
        if (function_exists('clear_cross_portal_session')) {
            clear_cross_portal_session('student');
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['student_id'] = $userId;
        $_SESSION['role'] = 'student';
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['auth_method'] = $authMethod;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();

        try {
            if (!function_exists('record_student_portal_login')) {
                $helpers = dirname(__DIR__, 2) . '/student/otp_helpers.php';
                if (is_file($helpers)) {
                    require_once $helpers;
                }
            }
            if (function_exists('record_student_portal_login')) {
                record_student_portal_login($this->pdo, $userId);
            } else {
                $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
            }
        } catch (Throwable) {
        }

        // Student trusted device binding
        try {
            if (!function_exists('student_devices')) {
                $helpers = dirname(__DIR__, 2) . '/student/device_helpers.php';
                if (is_file($helpers)) {
                    require_once $helpers;
                }
            }
            $svc = function_exists('student_devices') ? student_devices($this->pdo) : null;
            if ($svc) {
                $gate = $svc->beginLogin($userId, 'face');
                $gateStatus = (string)($gate['status'] ?? '');
                if ($gateStatus === 'device_limit' || $gateStatus === 'device_blocked') {
                    $_SESSION['student_device_choice'] = $gateStatus;
                    $_SESSION['student_device_blocked_until'] = (string)($gate['blocked_until'] ?? '');
                    return '/student/device_gate.php';
                }
                if ($gateStatus === 'ok' && !empty($gate['device_id'])) {
                    $svc->activateSession($userId, (int)$gate['device_id']);
                    $svc->clearPending();
                    $svc->onSignedIn($userId, (int)$gate['device_id'], 'face', !empty($gate['new_device']));
                }
            }
        } catch (Throwable $e) {
            error_log('Biometric student device bind error: ' . $e->getMessage());
        }

        if (function_exists('app_theme_on_login')) {
            app_theme_on_login($this->pdo, $user);
        }

        $this->recordAudit($authMethod, true, $userId, null, [
            'role' => 'student',
        ]);

        return function_exists('student_post_login_url')
            ? student_post_login_url()
            : (rtrim((string)BASE_URL, '/') . '/student/dashboard.php');
    }

    /**
     * Encrypts sensitive biometric data (e.g., face descriptor vector) using AES-256-GCM.
     *
     * @param string $plaintext
     * @return string Base64 encoded payload: iv:tag:ciphertext
     */
    public function encryptBiometricData(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(12); // Standard 96-bit IV for GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'edexcel_biometric_v1'
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Biometric encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts AES-256-GCM encrypted biometric data.
     *
     * @param string $encryptedData
     * @return string Plaintext
     */
    public function decryptBiometricData(string $encryptedData): string
    {
        $raw = base64_decode($encryptedData, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Malformed biometric ciphertext.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $key = $this->getEncryptionKey();

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'edexcel_biometric_v1'
        );

        if ($plaintext === false) {
            throw new RuntimeException('Biometric decryption failed or authentication tag mismatch.');
        }

        return $plaintext;
    }

    /**
     * Derives a cryptographic 256-bit key dedicated to biometrics.
     */
    private function getEncryptionKey(): string
    {
        $salt = 'edexcel_biometric_vault_kdf_salt';
        $seed = defined('APP_SECRET') ? (string)APP_SECRET : '';
        if ($seed === '') {
            $seed = defined('DB_PASS') ? (string)DB_PASS : 'default_college_vault_secret_2026';
        }
        return hash_hkdf('sha256', $seed, 32, 'edexcel_bio_v1', $salt);
    }
}
