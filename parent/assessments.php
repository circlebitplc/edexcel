<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentReportService;
if (empty($_SESSION['parent_id'])) { header('Location: '.BASE_URL.'parent/login.php'); exit; }
$parentId = (int)$_SESSION['parent_id'];
$children = [];
try {
    $s = $pdo->prepare('SELECT u.id,COALESCE(sp.full_name,u.username) name FROM parent_students ps JOIN users u ON u.id=ps.student_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE ps.parent_id=?');
    $s->execute([$parentId]);
    $children = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}
$svc = new AssessmentReportService($pdo);
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3">Assessment progress</h1>
    <p class="text-muted">Simplified results for linked children. Internal teacher notes and AI marking drafts are not shown.</p>
    <?php foreach ($children as $child): $rep = $svc->parent((int)$child['id'], ['role'=>'parent','parent_id'=>$parentId,'user_id'=>$parentId]); ?>
    <div class="card border-0 shadow-sm p-3 mb-3">
        <h2 class="h5"><?= e($child['name']) ?></h2>
        <p><?= e((string)($rep['feedback'] ?? '')) ?></p>
        <div class="small text-muted">Readiness: <?= e((string)($rep['readiness']['score'] ?? '—')) ?>% — <?= e((string)($rep['readiness']['disclaimer'] ?? '')) ?></div>
        <h3 class="h6 mt-3">Upcoming</h3>
        <ul><?php foreach (array_slice($rep['upcoming'] ?? [], 0, 8) as $u): ?><li><?= e((string)$u['date']) ?> · <?= e((string)$u['title']) ?></li><?php endforeach; ?></ul>
        <ol><?php foreach (($rep['trend']['points'] ?? []) as $p): ?><li><?= e($p['title']) ?> → <?= e((string)$p['percent']) ?>%</li><?php endforeach; ?></ol>
    </div>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
