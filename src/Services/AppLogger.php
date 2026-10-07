<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Structured application logger. Never log secrets (passwords, OTPs, API keys, tokens).
 */
final class AppLogger
{
    private static ?string $requestId = null;

    private const REDACT_KEYS = [
        'password', 'password_hash', 'otp', 'otp_hash', 'token', 'access_token',
        'api_key', 'secret', 'authorization', 'gateway_response', 'card', 'cvv',
        'recovery_code', 'totp', 'private_key', 'refresh_token',
    ];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }

    /**
     * @param array<string,mixed> $context
     */
    public function log(string $severity, string $event, string $message = '', array $context = [], ?string $module = null): void
    {
        $severity = strtolower($severity);
        if (!in_array($severity, ['debug', 'info', 'warning', 'error', 'critical'], true)) {
            $severity = 'info';
        }
        $safe = $this->redact($context);
        $line = json_encode([
            'ts' => date('c'),
            'severity' => $severity,
            'event' => $event,
            'module' => $module,
            'request_id' => self::requestId(),
            'user_id' => $safe['user_id'] ?? ($_SESSION['user_id'] ?? null),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'message' => mb_substr($message, 0, 1900),
            'context' => $safe,
        ], JSON_UNESCAPED_SLASHES);

        // Prefer logs outside the web root; OpenLiteSpeed may still serve static files under public_html.
        $candidates = [
            dirname(__DIR__, 3) . '/logs/app',                 // /home/edexcel.college/logs/app
            dirname(__DIR__, 2) . '/storage/logs',             // legacy public_html/storage/logs
        ];
        $dir = $candidates[0];
        foreach ($candidates as $candidate) {
            if (is_dir($candidate) || @mkdir($candidate, 0750, true)) {
                $dir = $candidate;
                break;
            }
        }
        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        $this->rotate($dir);

        if ($this->pdo instanceof PDO && in_array($severity, ['warning', 'error', 'critical'], true)) {
            try {
                $this->pdo->prepare("
                    INSERT INTO application_logs
                        (request_id, severity, event, module, user_id, ip_address, message, context_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    self::requestId(),
                    $severity,
                    mb_substr($event, 0, 120),
                    $module ? mb_substr($module, 0, 64) : null,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : ($safe['user_id'] ?? null),
                    isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null,
                    mb_substr($message, 0, 1900),
                    $safe !== [] ? json_encode($safe) : null,
                ]);
            } catch (Throwable $e) {
                // avoid recursion
            }
        }

        if (in_array($severity, ['error', 'critical'], true)) {
            error_log('[AppLogger] ' . $event . ': ' . $message);
        }
    }

    public function info(string $event, string $message = '', array $context = [], ?string $module = null): void
    {
        $this->log('info', $event, $message, $context, $module);
    }

    public function warning(string $event, string $message = '', array $context = [], ?string $module = null): void
    {
        $this->log('warning', $event, $message, $context, $module);
    }

    public function error(string $event, string $message = '', array $context = [], ?string $module = null): void
    {
        $this->log('error', $event, $message, $context, $module);
    }

    public function critical(string $event, string $message = '', array $context = [], ?string $module = null): void
    {
        $this->log('critical', $event, $message, $context, $module);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recentDb(int $limit = 100, ?string $severity = null): array
    {
        if (!($this->pdo instanceof PDO)) {
            return [];
        }
        try {
            if ($severity) {
                $stmt = $this->pdo->prepare('SELECT * FROM application_logs WHERE severity = ? ORDER BY id DESC LIMIT ?');
                $stmt->bindValue(1, $severity);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare('SELECT * FROM application_logs ORDER BY id DESC LIMIT ?');
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function redact(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $lk = strtolower((string)$k);
            foreach (self::REDACT_KEYS as $bad) {
                if (str_contains($lk, $bad)) {
                    $out[$k] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($v)) {
                $out[$k] = $this->redact($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function rotate(string $dir): void
    {
        $files = glob($dir . '/app-*.log') ?: [];
        if (count($files) <= 30) {
            return;
        }
        sort($files);
        $remove = count($files) - 30;
        for ($i = 0; $i < $remove; $i++) {
            @unlink($files[$i]);
        }
    }
}
