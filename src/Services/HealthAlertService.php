<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Throttled system health alerts via existing ops_admin_alert / WhatsApp path.
 */
final class HealthAlertService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Evaluate snapshot and send throttled alerts for critical issues.
     *
     * @param array<string,mixed> $snapshot
     */
    public function evaluateAndAlert(array $snapshot): int
    {
        $sent = 0;
        $checks = $this->buildChecks($snapshot);
        foreach ($checks as $check) {
            if ($this->maybeAlert($check['key'], $check['severity'], $check['title'], $check['body'], $check['open'])) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return list<array{key:string,severity:string,title:string,body:string,open:bool}>
     */
    private function buildChecks(array $snapshot): array
    {
        $checks = [];

        $db = $snapshot['database'] ?? [];
        $checks[] = [
            'key' => 'database_unavailable',
            'severity' => 'critical',
            'title' => 'Database unavailable',
            'body' => (string)($db['message'] ?? 'Database check failed.'),
            'open' => (($db['status'] ?? '') === 'red'),
        ];

        $cron = $snapshot['cron'] ?? [];
        $checks[] = [
            'key' => 'cron_stale',
            'severity' => 'critical',
            'title' => 'Critical cron job stopped',
            'body' => 'Stale jobs: ' . implode(', ', $cron['stale_names'] ?? []),
            'open' => (($cron['status'] ?? '') === 'red'),
        ];

        $backup = $snapshot['backup'] ?? [];
        $checks[] = [
            'key' => 'backup_failed',
            'severity' => 'critical',
            'title' => 'Backup failed',
            'body' => (string)($backup['label'] ?? 'Backup failure detected.'),
            'open' => (($backup['status'] ?? '') === 'red'),
        ];

        $disk = $snapshot['disk'] ?? [];
        $checks[] = [
            'key' => 'disk_low',
            'severity' => (($disk['status'] ?? '') === 'red') ? 'critical' : 'warning',
            'title' => 'Disk space low',
            'body' => 'Disk usage at ' . (string)($disk['used_percent'] ?? '?') . '%.',
            'open' => in_array(($disk['status'] ?? ''), ['yellow', 'red'], true),
        ];

        $wa = $snapshot['whatsapp'] ?? [];
        $checks[] = [
            'key' => 'whatsapp_auth',
            'severity' => 'critical',
            'title' => 'WhatsApp authentication failed',
            'body' => (string)($wa['message'] ?? 'WhatsApp token invalid.'),
            'open' => (($wa['status'] ?? '') === 'red'),
        ];

        $onepay = $snapshot['onepay'] ?? [];
        $checks[] = [
            'key' => 'onepay_fail',
            'severity' => 'warning',
            'title' => 'OnePay integration issue',
            'body' => (string)($onepay['message'] ?? 'OnePay check failed.'),
            'open' => (($onepay['status'] ?? '') === 'red'),
        ];

        $bunny = $snapshot['bunny'] ?? [];
        $checks[] = [
            'key' => 'bunny_stuck',
            'severity' => 'warning',
            'title' => 'Bunny processing stuck',
            'body' => 'Stuck recordings: ' . (int)($bunny['stuck_processing'] ?? 0),
            'open' => (($bunny['status'] ?? '') === 'red') || ((int)($bunny['stuck_processing'] ?? 0) >= 3),
        ];

        $livekit = $snapshot['livekit'] ?? [];
        $checks[] = [
            'key' => 'livekit_down',
            'severity' => 'warning',
            'title' => 'LiveKit unavailable',
            'body' => (string)($livekit['message'] ?? 'LiveKit not reachable.'),
            'open' => (($livekit['status'] ?? '') === 'red'),
        ];

        $payments = $snapshot['payments'] ?? [];
        $failed = (int)($payments['failed'] ?? 0);
        $checks[] = [
            'key' => 'failed_payments_spike',
            'severity' => 'warning',
            'title' => 'Large number of failed payments',
            'body' => "Failed payments (7d): {$failed}",
            'open' => $failed >= 10,
        ];

        $outbox = $snapshot['outbox'] ?? [];
        $pending = (int)($outbox['pending'] ?? 0);
        $failedOut = (int)($outbox['failed_today'] ?? 0);
        $checks[] = [
            'key' => 'whatsapp_outbox_stuck',
            'severity' => 'warning',
            'title' => 'WhatsApp outbox stuck',
            'body' => "Pending={$pending}, failed_today={$failedOut}",
            'open' => $pending >= 50 || $failedOut >= 20,
        ];

        $mig = $snapshot['migrations'] ?? [];
        $checks[] = [
            'key' => 'schema_migration_fail',
            'severity' => 'critical',
            'title' => 'Schema migration issue',
            'body' => (string)($mig['message'] ?? 'Migration status unhealthy.'),
            'open' => (($mig['status'] ?? '') === 'red'),
        ];

        return $checks;
    }

    private function maybeAlert(string $key, string $severity, string $title, string $body, bool $open): bool
    {
        $cooldown = max(15, (int)$this->setting('health_alert_cooldown_minutes', '60'));
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM system_health_alerts WHERE alert_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$open) {
                if ($row && ($row['last_status'] ?? '') === 'open') {
                    $this->pdo->prepare("
                        UPDATE system_health_alerts
                        SET last_status = 'resolved', last_resolved_at = NOW()
                        WHERE alert_key = ?
                    ")->execute([$key]);
                }
                return false;
            }

            $shouldSend = true;
            if ($row && !empty($row['last_alerted_at'])) {
                $age = time() - strtotime((string)$row['last_alerted_at']);
                if ($age < $cooldown * 60 && ($row['last_status'] ?? '') === 'open') {
                    $shouldSend = false;
                }
            }

            $this->pdo->prepare("
                INSERT INTO system_health_alerts (alert_key, severity, title, body, last_status, last_alerted_at, alert_count)
                VALUES (?, ?, ?, ?, 'open', NOW(), 1)
                ON DUPLICATE KEY UPDATE
                    severity = VALUES(severity),
                    title = VALUES(title),
                    body = VALUES(body),
                    last_status = 'open',
                    last_alerted_at = IF(?, NOW(), last_alerted_at),
                    alert_count = alert_count + IF(?, 1, 0),
                    updated_at = NOW()
            ")->execute([$key, $severity, $title, $body, $shouldSend ? 1 : 0, $shouldSend ? 1 : 0]);

            if (!$shouldSend) {
                return false;
            }

            if (function_exists('ops_admin_alert')) {
                ops_admin_alert($this->pdo, '[' . strtoupper($severity) . '] ' . $title, $body);
            }
            return true;
        } catch (Throwable $e) {
            error_log('HealthAlertService: ' . $e->getMessage());
            return false;
        }
    }

    private function setting(string $key, string $default = ''): string
    {
        if (function_exists('ops_setting')) {
            return ops_setting($this->pdo, $key, $default);
        }
        return $default;
    }
}
