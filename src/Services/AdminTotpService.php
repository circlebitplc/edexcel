<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Admin TOTP (authenticator) 2FA with recovery codes and trusted devices.
 * Uses PHP openssl; no external TOTP library dependency.
 */
final class AdminTotpService
{
    private const ISSUER = 'Edexcel College';

    public function __construct(private PDO $pdo)
    {
    }

    public function isEnabledForUser(int $userId): bool
    {
        $row = $this->secretRow($userId);
        return $row !== null && (int)$row['enabled'] === 1 && !empty($row['confirmed_at']);
    }

    public function isRequiredGlobally(): bool
    {
        if (function_exists('ops_setting')) {
            return ops_setting($this->pdo, 'admin_totp_required', '0') === '1';
        }
        return false;
    }

    /**
     * @return array{secret:string,otpauth_url:string,recovery_codes:list<string>}
     */
    public function beginSetup(int $userId, string $username): array
    {
        $secret = $this->generateSecret(20);
        $recovery = [];
        for ($i = 0; $i < 8; $i++) {
            $recovery[] = strtoupper(bin2hex(random_bytes(4)));
        }
        $hashes = array_map(static fn(string $c): string => password_hash($c, PASSWORD_DEFAULT), $recovery);
        $enc = $this->encryptSecret($secret);
        $this->pdo->prepare("
            INSERT INTO admin_totp_secrets (user_id, secret_encrypted, enabled, confirmed_at, recovery_codes_hash, recovery_codes_remaining)
            VALUES (?, ?, 0, NULL, ?, ?)
            ON DUPLICATE KEY UPDATE
                secret_encrypted = VALUES(secret_encrypted),
                enabled = 0,
                confirmed_at = NULL,
                recovery_codes_hash = VALUES(recovery_codes_hash),
                recovery_codes_remaining = VALUES(recovery_codes_remaining),
                updated_at = NOW()
        ")->execute([$userId, $enc, json_encode($hashes), count($recovery)]);

        $label = rawurlencode(self::ISSUER . ':' . $username);
        $url = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode(self::ISSUER) . '&digits=6&period=30';

        return ['secret' => $secret, 'otpauth_url' => $url, 'recovery_codes' => $recovery];
    }

    public function confirmSetup(int $userId, string $code): bool
    {
        if (!$this->verifyCode($userId, $code, false)) {
            return false;
        }
        $this->pdo->prepare("
            UPDATE admin_totp_secrets SET enabled = 1, confirmed_at = NOW(), updated_at = NOW() WHERE user_id = ?
        ")->execute([$userId]);
        return true;
    }

    public function disable(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM admin_totp_secrets WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('DELETE FROM admin_trusted_devices WHERE user_id = ?')->execute([$userId]);
    }

    public function verifyCode(int $userId, string $code, bool $allowRecovery = true): bool
    {
        $code = trim($code);
        $row = $this->secretRow($userId);
        if (!$row) {
            return false;
        }
        $secret = $this->decryptSecret((string)$row['secret_encrypted']);
        if ($secret !== '' && $this->verifyTotp($secret, $code)) {
            return true;
        }
        if ($allowRecovery && $code !== '') {
            return $this->consumeRecoveryCode($userId, $code, $row);
        }
        return false;
    }

    public function isTrustedDevice(int $userId): bool
    {
        $token = $_COOKIE['eck_admin_trusted'] ?? '';
        if (!is_string($token) || strlen($token) < 32) {
            return false;
        }
        $hash = hash('sha256', $token);
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM admin_trusted_devices
                WHERE user_id = ? AND device_token_hash = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$userId, $hash]);
            $id = $stmt->fetchColumn();
            if ($id) {
                $this->pdo->prepare('UPDATE admin_trusted_devices SET last_used_at = NOW() WHERE id = ?')
                    ->execute([(int)$id]);
                return true;
            }
        } catch (Throwable $e) {
        }
        return false;
    }

    public function trustDevice(int $userId, int $days = 30, string $label = ''): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->pdo->prepare("
            INSERT INTO admin_trusted_devices (user_id, device_token_hash, device_label, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))
        ")->execute([
            $userId,
            $hash,
            mb_substr($label !== '' ? $label : 'Trusted device', 0, 120),
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            max(1, min(90, $days)),
        ]);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('eck_admin_trusted', $token, [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Require recent password re-auth for sensitive actions.
     */
    public function requireReauth(int $userId, string $purpose, int $minutesValid = 10): string
    {
        $token = bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $this->pdo->prepare("
            INSERT INTO admin_reauth_tokens (user_id, purpose, token_hash, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
        ")->execute([$userId, $purpose, $hash, max(5, min(60, $minutesValid))]);
        $_SESSION['admin_reauth_' . $purpose] = $hash;
        $_SESSION['admin_reauth_' . $purpose . '_exp'] = time() + ($minutesValid * 60);
        return $token;
    }

    public function hasValidReauth(int $userId, string $purpose): bool
    {
        $hash = $_SESSION['admin_reauth_' . $purpose] ?? '';
        $exp = (int)($_SESSION['admin_reauth_' . $purpose . '_exp'] ?? 0);
        if (!is_string($hash) || $hash === '' || $exp < time()) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM admin_reauth_tokens
                WHERE user_id = ? AND purpose = ? AND token_hash = ?
                  AND expires_at > NOW() AND used_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$userId, $purpose, $hash]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function markReauthUsed(int $userId, string $purpose): void
    {
        $hash = $_SESSION['admin_reauth_' . $purpose] ?? '';
        if (!is_string($hash) || $hash === '') {
            return;
        }
        try {
            $this->pdo->prepare("
                UPDATE admin_reauth_tokens SET used_at = NOW()
                WHERE user_id = ? AND purpose = ? AND token_hash = ?
            ")->execute([$userId, $purpose, $hash]);
        } catch (Throwable $e) {
        }
        unset($_SESSION['admin_reauth_' . $purpose], $_SESSION['admin_reauth_' . $purpose . '_exp']);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function secretRow(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM admin_totp_secrets WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private function consumeRecoveryCode(int $userId, string $code, array $row): bool
    {
        $hashes = json_decode((string)($row['recovery_codes_hash'] ?? '[]'), true);
        if (!is_array($hashes)) {
            return false;
        }
        $matched = null;
        foreach ($hashes as $i => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                $matched = $i;
                break;
            }
        }
        if ($matched === null) {
            return false;
        }
        unset($hashes[$matched]);
        $hashes = array_values($hashes);
        $this->pdo->prepare("
            UPDATE admin_totp_secrets
            SET recovery_codes_hash = ?, recovery_codes_remaining = ?, updated_at = NOW()
            WHERE user_id = ?
        ")->execute([json_encode($hashes), count($hashes), $userId]);
        return true;
    }

    private function generateSecret(int $length = 20): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    private function verifyTotp(string $secret, string $code, int $window = 1): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $timeSlice = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->hotp($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $bin = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $b32): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper($b32);
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';
        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $val = strpos($map, $b32[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }
        return $result;
    }

    private function encryptSecret(string $secret): string
    {
        $key = $this->appKey();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Unable to encrypt TOTP secret.');
        }
        return base64_encode($iv . $cipher);
    }

    private function decryptSecret(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $this->appKey(), OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? $plain : '';
    }

    private function appKey(): string
    {
        $seed = (string)(getenv('APP_KEY') ?: getenv('BACKUP_ENCRYPTION_KEY') ?: '');
        if ($seed === '') {
            $seed = (defined('DB_NAME') ? DB_NAME : 'edexcel') . '|' . (defined('DB_HOST') ? DB_HOST : 'localhost');
        }
        return hash('sha256', $seed, true);
    }
}
