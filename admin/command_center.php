<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AdmissionAnalyticsService;
use Edexcel\Services\BackupService;
use Edexcel\Services\FeeStatementService;
use Edexcel\Services\SecurityEventService;
use Edexcel\Services\SystemHealthService;

require_admin();
ensure_ops_schema($pdo);

$today = date('Y-m-d');
$health = new SystemHealthService($pdo);
$snap = $health->snapshot();
$day = (new FeeStatementService($pdo))->dayEnd($today);
$backupHealth = (new BackupService($pdo))->healthSummary();
$secEvents = (new SecurityEventService($pdo))->search([
    'date_from' => $today,
    'severity' => '',
    'limit' => 20,
]);
$secAlerts = array_values(array_filter($secEvents, static function (array $e): bool {
    return in_array(strtolower((string)($e['severity'] ?? '')), ['warning', 'error', 'critical'], true);
}));

$classesToday = 0;
$studentsActive = 0;
$pendingSlips = (int)($snap['counts']['pending_bank_slips'] ?? 0);
$failedJobs = (int)($snap['counts']['failed_jobs'] ?? 0);
$marksToday = 0;
$homeworkDue = 0;
$riskHigh = 0;
$openTickets = 0;
$cancelledToday = 0;
$successAttention = 0;
$automationFailures = 0;
$readinessLow = 0;
$outstandingMarking = 0;
$admissionsToday = ['new_enquiries'=>0,'new_applications'=>0,'pending_review'=>0,'pending_payments'=>0,'followups_due'=>0,'enrolled_today'=>0];
try { $admissionsToday = (new AdmissionAnalyticsService($pdo))->todayCounts(); } catch (Throwable $e) {}

try {
    $classesToday = (int)$pdo->query("
        SELECT COUNT(*) FROM timetable
        WHERE deleted_at IS NULL AND date = CURDATE()
          AND COALESCE(lesson_status, 'scheduled') <> 'cancelled'
    ")->fetchColumn();
} catch (Throwable $e) {
}
try {
    $successAttention = (int)$pdo->query("SELECT COUNT(*) FROM student_success_snapshots WHERE success_level IN ('AT_RISK','CRITICAL')")->fetchColumn();
} catch (Throwable $e) {}
try {
    $automationFailures = (int)$pdo->query("SELECT COUNT(*) FROM automation_runs WHERE status='failed' AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
} catch (Throwable $e) {}
try {
    $readinessLow = (int)$pdo->query("SELECT COUNT(*) FROM exam_readiness_snapshots WHERE readiness_score < 50")->fetchColumn();
} catch (Throwable $e) {}
try {
    $outstandingMarking = (int)$pdo->query("SELECT COUNT(*) FROM assessment_attempts WHERE status IN ('submitted','marking')")->fetchColumn();
} catch (Throwable $e) {}
try {
    $riskHigh = (int)$pdo->query("SELECT COUNT(*) FROM student_risk_assessments WHERE risk_level IN ('HIGH','CRITICAL')")->fetchColumn();
} catch (Throwable $e) {}
try {
    $openTickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status NOT IN ('resolved','closed')")->fetchColumn();
} catch (Throwable $e) {}
try {
    $cancelledToday = (int)$pdo->query("SELECT COUNT(*) FROM timetable WHERE date=CURDATE() AND lesson_status='cancelled' AND deleted_at IS NULL")->fetchColumn();
} catch (Throwable $e) {}
try {
    $studentsActive = (int)$pdo->query("
        SELECT COUNT(*) FROM users
        WHERE role = 'student' AND deleted_at IS NULL AND is_active = 1
    ")->fetchColumn();
} catch (Throwable $e) {
}
try {
    $marksToday = (int)$pdo->query("
        SELECT COUNT(*) FROM student_progress WHERE recorded_at = CURDATE()
    ")->fetchColumn();
} catch (Throwable $e) {
}
try {
    $homeworkDue = (int)$pdo->query("
        SELECT COUNT(*) FROM student_homework
        WHERE due_date IS NOT NULL AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ")->fetchColumn();
} catch (Throwable $e) {
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-speedometer2"></i> Command center</h1>
            <p class="text-muted mb-0">Today · Financial · Academic · System</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/system_health.php">System health</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/security.php">Security</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/backup.php">Backup</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <h2 class="h5">Operations</h2>
                <div class="row g-2">
                    <div class="col-6 col-lg-3"><div class="border rounded-3 p-3"><div class="small text-muted">High / critical risk</div><div class="fs-3 fw-bold text-danger"><?= $riskHigh ?></div></div></div>
                    <div class="col-6 col-lg-3"><div class="border rounded-3 p-3"><div class="small text-muted">Open support tickets</div><div class="fs-3 fw-bold"><?= $openTickets ?></div></div></div>
                    <div class="col-6 col-lg-3"><div class="border rounded-3 p-3"><div class="small text-muted">Cancelled today</div><div class="fs-3 fw-bold"><?= $cancelledToday ?></div></div></div>
                    <div class="col-6 col-lg-3"><div class="border rounded-3 p-3"><div class="small text-muted">Success attention</div><div class="fs-3 fw-bold text-warning"><?= $successAttention ?></div></div></div>
                    <div class="col-6 col-lg-3 d-flex align-items-center gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>admin/risk.php">Risk</a><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>admin/support.php">Support</a></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Today</h2>
                    <span class="badge text-bg-primary"><?= e($today) ?></span>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Classes today</div>
                            <div class="fs-3 fw-bold"><?= $classesToday ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Active students</div>
                            <div class="fs-3 fw-bold"><?= $studentsActive ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Homework due (7d)</div>
                            <div class="fs-3 fw-bold"><?= $homeworkDue ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Marks recorded today</div>
                            <div class="fs-3 fw-bold"><?= $marksToday ?></div>
                        </div>
                    </div>
                </div>
                <a class="btn btn-sm btn-outline-primary mt-3" href="<?= e(BASE_URL) ?>campus/today.php">Open Today ops</a>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <h2 class="h5">Action queue</h2>
                <div class="row g-2">
                    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(BASE_URL) ?>admin/student_success.php"><div class="border rounded-3 p-3"><div class="small text-muted">At-risk success profiles</div><strong><?= $successAttention ?></strong></div></a></div>
                    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(BASE_URL) ?>campus/exam_control.php"><div class="border rounded-3 p-3"><div class="small text-muted">Low exam readiness</div><strong><?= $readinessLow ?></strong></div></a></div>
                    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(BASE_URL) ?>campus/assessment_mark.php"><div class="border rounded-3 p-3"><div class="small text-muted">Outstanding marking</div><strong><?= $outstandingMarking ?></strong></div></a></div>
                    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(BASE_URL) ?>admin/support.php"><div class="border rounded-3 p-3"><div class="small text-muted">Open requests</div><strong><?= $openTickets ?></strong></div></a></div>
                    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(BASE_URL) ?>admin/automation.php"><div class="border rounded-3 p-3"><div class="small text-muted">Automation failures (24h)</div><strong class="<?= $automationFailures > 0 ? 'text-danger' : '' ?>"><?= $automationFailures ?></strong></div></a></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <h2 class="h5 mb-2">Financial</h2>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Cash today</div>
                            <div class="fs-4 fw-bold">Rs <?= number_format((float)$day['cash']) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">OnePay today</div>
                            <div class="fs-4 fw-bold">Rs <?= number_format((float)$day['onepay']) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Bank today</div>
                            <div class="fs-4 fw-bold">Rs <?= number_format((float)($day['bank'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3">
                            <div class="small text-muted">Pending bank slips</div>
                            <div class="fs-4 fw-bold <?= $pendingSlips > 0 ? 'text-warning' : '' ?>"><?= $pendingSlips ?></div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/bank_slips.php">Bank slips</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/cash.php">Cash handover</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>admin/jobs.php">Day totals</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <h2 class="h5 mb-2">Admissions</h2>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 d-flex justify-content-between"><span>New enquiries</span><strong><?= (int)$admissionsToday['new_enquiries'] ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>New applications</span><strong><?= (int)$admissionsToday['new_applications'] ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>Awaiting review</span><strong><?= (int)$admissionsToday['pending_review'] ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>Pending payments</span><strong><?= (int)$admissionsToday['pending_payments'] ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>Follow-ups due</span><strong><?= (int)$admissionsToday['followups_due'] ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>Enrolled today</span><strong><?= (int)$admissionsToday['enrolled_today'] ?></strong></li>
                </ul>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>admin/admissions_control.php">Command centre</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>admin/leads.php">Leads</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>admin/admission_analytics.php">Analytics</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <h2 class="h5 mb-2">Academic</h2>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 d-flex justify-content-between"><span>Classes scheduled today</span><strong><?= $classesToday ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>Marks entered today</span><strong><?= $marksToday ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span>Homework due this week</span><strong><?= $homeworkDue ?></strong></li>
                </ul>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/progress.php">Marks</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/homework.php">Papers</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/attendance.php">Attendance</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <h2 class="h5 mb-2">System</h2>
                <ul class="list-unstyled mb-3">
                    <li class="mb-2 d-flex justify-content-between">
                        <span>Failed jobs</span>
                        <strong class="<?= $failedJobs > 0 ? 'text-danger' : '' ?>"><?= $failedJobs ?></strong>
                    </li>
                    <li class="mb-2 d-flex justify-content-between">
                        <span>Backup</span>
                        <strong><?= e((string)($backupHealth['status'] ?? $backupHealth['message'] ?? '—')) ?></strong>
                    </li>
                    <li class="mb-2 d-flex justify-content-between">
                        <span>Security alerts today</span>
                        <strong class="<?= count($secAlerts) > 0 ? 'text-warning' : '' ?>"><?= count($secAlerts) ?></strong>
                    </li>
                    <li class="mb-2 d-flex justify-content-between">
                        <span>WhatsApp outbox failed (24h)</span>
                        <strong><?= (int)($snap['counts']['failed_whatsapp'] ?? 0) ?></strong>
                    </li>
                </ul>
                <?php if ($secAlerts !== []): ?>
                    <div class="small text-muted mb-1">Latest alerts</div>
                    <ul class="small mb-3">
                        <?php foreach (array_slice($secAlerts, 0, 5) as $ev): ?>
                            <li><?= e((string)($ev['created_at'] ?? '')) ?> — <?= e((string)($ev['message'] ?? $ev['event_type'] ?? '')) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>admin/system_health.php">Health</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/jobs.php">Jobs</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/backup.php">Backups</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/security.php">Security</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
