<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AutomationService;
use Edexcel\Services\LeadService;
if (!($pdo instanceof PDO)) { exit(1); }
ensure_ops_schema($pdo);
ops_job_start($pdo, 'admissions_followup_worker');
try {
    $auto = new AutomationService($pdo);
    $leads = [];
    try {
        $leads = $pdo->query("SELECT id FROM admission_leads WHERE status='NEW' AND created_at<=DATE_SUB(NOW(),INTERVAL 1 DAY)")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $leads = [];
    }
    foreach ($leads as $id) {
        try { $auto->handle('enquiry_stale', 'lead-stale-'.$id.'-'.date('Y-m-d'), ['lead_id' => (int)$id]); } catch (Throwable $e) {}
    }
    $apps = [];
    try {
        $apps = $pdo->query("SELECT id FROM admission_applications WHERE status='pending' AND created_at<=DATE_SUB(NOW(),INTERVAL 2 DAY)")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $apps = [];
    }
    foreach ($apps as $id) {
        try { $auto->handle('application_unreviewed', 'app-stale-'.$id.'-'.date('Y-m-d'), ['application_id' => (int)$id]); } catch (Throwable $e) {}
    }
    $due = (new LeadService($pdo))->dueToday();
    ops_job_finish($pdo, 'admissions_followup_worker', true, 'stale_leads='.count($leads).' stale_apps='.count($apps).' due='.count($due));
    echo 'ok'."\n";
} catch (Throwable $e) {
    ops_job_finish($pdo, 'admissions_followup_worker', false, $e->getMessage());
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
