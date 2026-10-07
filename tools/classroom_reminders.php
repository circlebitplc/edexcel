<?php
declare(strict_types=1);

/**
 * ~15-minute class reminders (student + parent WhatsApp, teacher portal/WhatsApp).
 * Crontab: every 5 minutes, Asia/Colombo.
 * Also started from normal portal page loads when crontab is not installed.
 */

date_default_timezone_set('Asia/Colombo');

if (PHP_SAPI !== 'cli') {
    $key = (string)($_GET['key'] ?? '');
    $expected = trim((string)(getenv('CRON_KEY') ?: ''));
    if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "CLI only.\n";
        exit(1);
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No database.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    ensure_campus_schema($pdo);
    ops_job_start($pdo, 'classroom_reminders');
    $result = classroom_run_reminder_job($pdo, $dryRun, true);
    ops_job_finish($pdo, 'classroom_reminders', true, 'window=' . (int)($result['window'] ?? 0) . ' done=' . (int)($result['done'] ?? 0));
    if (($result['window'] ?? 0) === 0 && ($result['done'] ?? 0) === 0) {
        exit(0);
    }
} catch (Throwable $e) {
    if (function_exists('ops_job_finish')) {
        ops_job_finish($pdo, 'classroom_reminders', false, $e->getMessage());
    }
    classroom_reminder_job_log('FATAL: ' . $e->getMessage());
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
