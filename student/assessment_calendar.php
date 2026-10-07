<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentReportService;
require_student();
$items = (new AssessmentReportService($pdo))->calendar(['role'=>'student','user_id'=>(int)$_SESSION['user_id']], (int)$_SESSION['user_id']);
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3">Assessment calendar</h1>
    <p class="text-muted">Official examinations, college assessments, and homework deadlines in one list.</p>
    <div class="list-group"><?php foreach ($items as $i): ?>
        <div class="list-group-item d-flex justify-content-between"><span><?= e((string)$i['title']) ?></span><span class="small text-muted"><?= e((string)$i['date']) ?> · <?= e((string)$i['kind']) ?></span></div>
    <?php endforeach; ?></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
