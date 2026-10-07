<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentReportService;
require_student();
$attemptId = (int)($_GET['attempt'] ?? 0);
$report = (new AssessmentReportService($pdo))->student($attemptId, ['user_id' => (int)$_SESSION['user_id'], 'role' => 'student']);
include __DIR__ . '/../includes/header.php';
$a = $report['analysis'] ?? [];
$t = $report['trend'] ?? [];
$r = $report['readiness'] ?? [];
?>
<div class="container py-4">
    <h1 class="h3">Assessment analysis</h1>
    <?php if (!$report): ?><div class="alert alert-warning">Result not available.</div><?php else: ?>
    <div class="display-5"><?= e((string)($a['score'] ?? $report['attempt']['percent'] ?? '—')) ?>%</div>
    <p class="text-muted"><?= e((string)($a['disclaimer'] ?? $report['disclaimer'] ?? '')) ?></p>
    <div class="row g-3">
        <div class="col-md-6"><div class="card border-0 shadow-sm p-3"><h2 class="h5">Strong areas</h2><ul><?php foreach (($a['strong'] ?? []) as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm p-3"><h2 class="h5">Areas to improve</h2><ul><?php foreach (($a['improve'] ?? []) as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul></div></div>
    </div>
    <div class="card border-0 shadow-sm p-3 my-3">
        <h2 class="h5">Recommended action</h2>
        <p><?= e((string)($a['action'] ?? '')) ?></p>
        <a class="btn btn-primary" href="<?= e(BASE_URL.($a['link'] ?? 'student/exam_prep.php')) ?>">Open targeted practice</a>
    </div>
    <div class="card border-0 shadow-sm p-3">
        <h2 class="h5">Progress over time</h2>
        <p><?= e((string)($t['wording'] ?? '')) ?></p>
        <ol><?php foreach (($t['points'] ?? []) as $p): ?><li><?= e($p['title']) ?> → <?= e((string)$p['percent']) ?>%</li><?php endforeach; ?></ol>
        <div>Exam readiness: <?= e((string)($r['readiness_score'] ?? '—')) ?>% — <?= e((string)($r['disclaimer'] ?? '')) ?></div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
