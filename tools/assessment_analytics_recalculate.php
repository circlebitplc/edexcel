<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentAnalyticsService;
if (!($pdo instanceof PDO)) { exit(1); }
ensure_ops_schema($pdo);
ops_job_start($pdo, 'assessment_analytics_recalculate');
try {
    $ids = $pdo->query("SELECT id FROM assessments WHERE status IN ('published','archived')")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $svc = new AssessmentAnalyticsService($pdo);
    foreach ($ids as $id) {
        $svc->recalculate((int)$id);
    }
    ops_job_finish($pdo, 'assessment_analytics_recalculate', true, 'assessments='.count($ids));
    echo 'assessments='.count($ids)."\n";
} catch (Throwable $e) {
    ops_job_finish($pdo, 'assessment_analytics_recalculate', false, $e->getMessage());
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
