<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAnalyticsService;
use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\AdmissionLifecycleService;
use Edexcel\Services\LeadService;
try { AdmissionAuth::require($pdo, 'admissions.view'); } catch (Throwable $e) { http_response_code(403); echo 'Access denied.'; exit; }
$counts = (new AdmissionAnalyticsService($pdo))->todayCounts();
$funnel = (new AdmissionAnalyticsService($pdo))->funnel(date('Y-m-01'), date('Y-m-d'));
$follow = (new LeadService($pdo))->dueToday();
$apps = (new AdmissionLifecycleService($pdo))->dashboard([]);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center">
        <div><h1 class="h3">Admissions command centre</h1><p class="text-muted mb-0">Lead → application → admission → payment → enrollment</p></div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="leads.php">Leads</a>
            <a class="btn btn-sm btn-outline-primary" href="admissions.php">Applications</a>
            <a class="btn btn-sm btn-outline-secondary" href="admission_analytics.php">Analytics</a>
            <a class="btn btn-sm btn-outline-secondary" href="student_lifecycle.php">Lifecycle</a>
        </div>
    </div>
    <h2 class="h5 mt-3">Today</h2>
    <div class="row g-2"><?php foreach ([['New enquiries',$counts['new_enquiries']],['New applications',$counts['new_applications']],['Payments pending',$counts['pending_payments']],['Enrolled today',$counts['enrolled_today']],['Follow-ups due',$counts['followups_due']]] as $c): ?>
        <div class="col"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-3 fw-bold"><?= (int)$c[1] ?></div></div></div>
    <?php endforeach; ?></div>
    <h2 class="h5 mt-4">Action required</h2>
    <div class="row g-2"><?php foreach ([['Awaiting review',$counts['pending_review'],'admissions.php'],['Pending payments',$counts['pending_payments'],'admissions.php?status=payment_required'],['Awaiting allocation',$counts['awaiting_allocation'],'admissions.php?status=payment_received'],['Overdue follow-ups',$counts['overdue_followups'],'leads.php']] as $c): ?>
        <div class="col-md-3"><a class="text-decoration-none" href="<?= e($c[2]) ?>"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><strong><?= (int)$c[1] ?></strong></div></a></div>
    <?php endforeach; ?></div>
    <div class="row g-3 mt-2">
        <div class="col-lg-6"><div class="card border-0 shadow-sm p-3"><h2 class="h5">Funnel this month</h2>
            <ol>
                <li>Enquiries <?= (int)$funnel['enquiries'] ?></li>
                <li>Contacted <?= (int)$funnel['contacted'] ?></li>
                <li>Applications <?= (int)$funnel['applications'] ?></li>
                <li>Approved <?= (int)$funnel['approved'] ?></li>
                <li>Enrolled <?= (int)$funnel['enrolled'] ?></li>
            </ol>
            <div class="small text-muted">Enquiry→application <?= e((string)($funnel['enquiry_to_application'] ?? '—')) ?>% · Application→admission <?= e((string)($funnel['application_to_admission'] ?? '—')) ?>% · Admission→enrollment <?= e((string)($funnel['admission_to_enrollment'] ?? '—')) ?>%</div>
        </div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm p-3"><h2 class="h5">Follow-ups due</h2>
            <ul class="mb-0"><?php foreach (array_slice($follow, 0, 8) as $f): ?><li><?= e((string)($f['full_name'] ?? 'Lead')) ?> · <?= e($f['reason']) ?> · <?= e((string)$f['due_at']) ?></li><?php endforeach; ?></ul>
        </div></div>
    </div>
    <div class="card border-0 shadow-sm mt-3"><div class="table-responsive"><table class="table"><thead><tr><th>Recent applications</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach (array_slice($apps, 0, 12) as $a): ?><tr><td><?= e($a['full_name']) ?> <span class="small text-muted"><?= e($a['application_no']) ?></span></td><td><?= e((string)($a['lifecycle_status'] ?? $a['status'])) ?></td><td><a href="admission_review.php?id=<?= (int)$a['id'] ?>">Review</a></td></tr><?php endforeach; ?></tbody>
    </table></div></div>
    <?php if (function_exists('is_admin') && is_admin()): ?>
    <form method="post" action="admission_permissions.php" class="card border-0 shadow-sm p-3 mt-3">
        <p class="small mb-0">Teachers do not see admissions data unless granted a permission on <a href="admission_permissions.php">staff permissions</a>.</p>
    </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
