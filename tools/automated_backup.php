<?php
declare(strict_types=1);

/**
 * Automated backup job. Safe daily (or per backup_frequency setting).
 * Timezone: Asia/Colombo.
 */
date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BackupService;
use Edexcel\Services\HealthAlertService;
use Edexcel\Services\SystemHealthService;

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No database.\n");
    exit(1);
}

ensure_ops_schema($pdo);
ops_job_start($pdo, 'automated_backup');

$ok = false;
$message = '';
try {
    $svc = new BackupService($pdo);
    $cfg = $svc->config();
    $freq = (string)($cfg['frequency'] ?? 'daily');
    $shouldRun = true;
    if ($freq === 'weekly' && (int)date('N') !== 7) {
        $shouldRun = false;
        $message = 'Skipped (weekly schedule).';
    } elseif ($freq === 'monthly' && (int)date('j') !== 1) {
        $shouldRun = false;
        $message = 'Skipped (monthly schedule).';
    }

    // Avoid duplicate same-day success unless forced via CLI arg.
    $force = in_array('--force', $argv ?? [], true);
    if ($shouldRun && !$force) {
        $today = $pdo->query("
            SELECT COUNT(*) FROM system_backups
            WHERE status = 'success' AND DATE(finished_at) = CURDATE()
        ")->fetchColumn();
        if ((int)$today > 0 && $freq === 'daily') {
            $shouldRun = false;
            $message = 'Already backed up today.';
        }
    }

    if ($shouldRun) {
        $result = $svc->run(['force' => $force]);
        $ok = !empty($result['ok']);
        $message = (string)($result['message'] ?? ($ok ? 'OK' : 'Failed'));
        if (!$ok) {
            $snap = (new SystemHealthService($pdo))->snapshot();
            (new HealthAlertService($pdo))->evaluateAndAlert($snap);
        }
    } else {
        $ok = true;
    }
} catch (Throwable $e) {
    $ok = false;
    $message = $e->getMessage();
    error_log('automated_backup: ' . $message);
}

ops_job_finish($pdo, 'automated_backup', $ok, $message);
echo ($ok ? "OK: " : "FAIL: ") . $message . "\n";
exit($ok ? 0 : 1);
