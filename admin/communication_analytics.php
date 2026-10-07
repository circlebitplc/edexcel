<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\CommunicationAnalyticsService;
use Edexcel\Services\CommunicationAuth;
try { CommunicationAuth::require($pdo, 'communication.analytics'); } catch (Throwable $e) {
    if (!function_exists('is_admin') || !is_admin()) { http_response_code(403); echo 'Access denied.'; exit; }
}
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$svc = new CommunicationAnalyticsService($pdo);
$sum = $svc->summary($from, $to);
$eng = $svc->engagement();
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <a href="communications.php">← Communication centre</a>
    <div class="d-flex justify-content-between align-items-center"><h1 class="h3">Communication analytics</h1>
        <form class="d-flex gap-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-primary">Filter</button></form>
    </div>
    <div class="row g-2 my-2"><?php foreach ([['Messages',$sum['messages_created']],['Sent',$sum['recipients_sent']],['Delivered',$sum['provider_delivered']??0],['Provider read',$sum['provider_read']??0],['Failed',$sum['recipients_failed']],['Queued',$sum['recipients_queued']],['In-app read rate',$sum['read_rate']!==null?$sum['read_rate'].'%':'—']] as $c): ?>
        <div class="col"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-4 fw-bold"><?= e((string)$c[1]) ?></div></div></div>
    <?php endforeach; ?></div>
    <p class="small text-muted"><?= e($sum['note']) ?></p>
    <div class="row g-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3"><h2 class="h6">Channels</h2><ul><?php foreach ($sum['channels'] as $r): ?><li><?= e((string)$r['label']) ?> — <?= (int)$r['total'] ?></li><?php endforeach; ?></ul></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3"><h2 class="h6">Categories</h2><ul><?php foreach ($sum['categories'] as $r): ?><li><?= e((string)$r['label']) ?> — <?= (int)$r['total'] ?></li><?php endforeach; ?></ul></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3"><h2 class="h6">Delivery events (<?= (int)($sum['delivery_events']??0) ?>)</h2><ul><?php foreach (($sum['delivery_event_types']??[]) as $r): ?><li><?= e((string)$r['label']) ?> — <?= (int)$r['total'] ?></li><?php endforeach; ?></ul></div></div>
    </div>
    <div class="card border-0 shadow-sm p-3 mt-3">
        <h2 class="h5">Engagement dashboard (7 days)</h2>
        <p class="small text-muted"><?= e($eng['note']) ?></p>
        <ul>
            <li>Students with notifications: <?= (int)$eng['students_active_7d'] ?></li>
            <li>Parents with notifications: <?= (int)$eng['parents_active_7d'] ?></li>
            <li>Homework submissions: <?= (int)$eng['homework_submissions_7d'] ?></li>
            <li>Assessment attempts: <?= (int)$eng['assessment_attempts_7d'] ?></li>
            <li>Portal notifications created: <?= (int)$eng['portal_notifications_7d'] ?></li>
        </ul>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
