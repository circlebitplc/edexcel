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
     * Creates an ephemeral, single-use biometric challenge token.
     *
     * @param string $type 'passkey_reg'|'passkey_auth'|'face_enrol'|'face_auth'
     * @param int $userId
     * @param array<string,mixed> $payload
     * @return string Challenge token
     */
    public function createChallenge(string $type, int $userId, array $payload = []): string
    {
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
     * Checks if biometric attempts from the client IP / admin user are locked or throttled.
     *
     * @param string $username
     * @return array{is_locked:bool, delay_seconds:int, attempts_recent:int}
     */
    public function checkThrottling(string $username): array
    {
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $cutoff = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM authentication_audit
            WHERE (ip_address = ? OR user_id = 1)
              AND success = 0
              AND created_at >= ?
        ");
        $stmt->execute([$ip, $cutoff]);
        $failures = (int)$stmt->fetchColumn();

        if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
            return [
                'is_locked' => true,
                'delay_seconds' => self::LOCKOUT_MINUTES * 60,
                'attempts_recent' => $failures,
            ];
        }

        $delay = 0;
        if ($failures >= 5) {
            $delay = 4;
        } elseif ($failures >= 3) {
            $delay = 1;
        }

        return [
            'is_locked' => false,
            'delay_seconds' => $delay,
            'attempts_recent' => $failures,
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
                !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
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
