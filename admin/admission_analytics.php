<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAnalyticsService;
use Edexcel\Services\AdmissionAuth;
try { AdmissionAuth::require($pdo, 'admissions.analytics'); } catch (Throwable $e) { http_response_code(403); echo 'Access denied.'; exit; }
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$svc = new AdmissionAnalyticsService($pdo);
$funnel = $svc->funnel($from, $to);
$rev = $svc->revenue($from, $to);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between"><h1 class="h3">Admissions analytics</h1>
        <form class="d-flex gap-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-primary">Filter</button></form>
    </div>
    <div class="row g-2 my-2"><?php foreach ([['Enquiries',$funnel['enquiries']],['Applications',$funnel['applications']],['Approved',$funnel['approved']],['Enrolled',$funnel['enrolled']],['Lost leads',$funnel['lost']]] as $c): ?>
        <div class="col"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-4 fw-bold"><?= e((string)$c[1]) ?></div></div></div>
    <?php endforeach; ?></div>
    <div class="card border-0 shadow-sm p-3 mb-3">
        <h2 class="h5">Conversion</h2>
        <p>Enquiry → application <?= e((string)($funnel['enquiry_to_application'] ?? '—')) ?>%</p>
        <p>Application → admission <?= e((string)($funnel['application_to_admission'] ?? '—')) ?>%</p>
        <p>Admission → enrollment <?= e((string)($funnel['admission_to_enrollment'] ?? '—')) ?>%</p>
        <p>Average days to admission <?= e((string)($funnel['time_to_admission_days'] ?? '—')) ?></p>
    </div>
    <div class="card border-0 shadow-sm p-3 mb-3">
        <h2 class="h5">Admission revenue view</h2>
        <p>Expected Rs <?= number_format((float)$rev['expected'], 2) ?> · Collected Rs <?= number_format((float)$rev['collected'], 2) ?> · Pending Rs <?= number_format((float)$rev['pending'], 2) ?></p>
        <p class="small text-muted"><?= e($rev['note']) ?></p>
    </div>
    <div class="row"><div class="col-md-4"><h2 class="h6">Sources</h2><ul><?php foreach ($funnel['sources'] as $s): ?><li><?= e((string)$s['label']) ?> — <?= (int)$s['total'] ?></li><?php endforeach; ?></ul></div>
        <div class="col-md-4"><h2 class="h6">Programmes</h2><ul><?php foreach ($funnel['subjects'] as $s): ?><li><?= e((string)$s['label']) ?> — <?= (int)$s['total'] ?></li><?php endforeach; ?></ul></div>
        <div class="col-md-4"><h2 class="h6">Locations</h2><ul><?php foreach ($funnel['locations'] ?? [] as $s): ?><li><?= e((string)$s['label']) ?> — <?= (int)$s['total'] ?></li><?php endforeach; ?></ul></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
