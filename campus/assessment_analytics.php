<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentReportService;
require_staff();
$auth = ['user_id' => (int)($_SESSION['user_id'] ?? 0), 'role' => (string)($_SESSION['role'] ?? ''), 'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0)];
$id = (int)($_GET['id'] ?? 0);
$report = $id ? (new AssessmentReportService($pdo))->teacher($id, $auth) : [];
$class = $report['class'] ?? [];
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3">Class performance analysis</h1>
    <?php if (!$report): ?><div class="alert alert-warning">Assessment not found or access denied.</div><?php else: ?>
    <p class="text-muted"><?= e($report['assessment']['title'] ?? '') ?> — comparisons are teacher-only and not a public ranking.</p>
    <div class="row g-2 mb-3">
        <?php foreach ([['Average',$class['average_percent'] ?? '—'],['Median',$class['median_percent'] ?? '—'],['Highest',$class['highest_percent'] ?? '—'],['Lowest',$class['lowest_percent'] ?? '—'],['Participants',$class['participant_count'] ?? 0]] as $c): ?>
        <div class="col"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-4 fw-bold"><?= e((string)$c[1]) ?></div></div></div>
        <?php endforeach; ?>
    </div>
    <div class="card border-0 shadow-sm p-3 mb-3">
        <h2 class="h5">Topics requiring whole-class revision</h2>
        <ul><?php foreach (($class['class_revision_topics'] ?? []) as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
        <?php if (($class['previous_average'] ?? null) !== null): ?><p>Previous class average: <?= e((string)$class['previous_average']) ?>%</p><?php endif; ?>
    </div>
    <div class="card border-0 shadow-sm p-3">
        <h2 class="h5">Question analysis</h2>
        <div class="table-responsive"><table class="table"><thead><tr><th>Question</th><th>Topic</th><th>Attempts</th><th>Average</th><th>Success</th><th>Observed difficulty</th><th>Common errors</th></tr></thead>
        <tbody><?php foreach (($report['questions'] ?? []) as $q): ?><tr>
            <td><?= e(mb_substr((string)$q['prompt'],0,80)) ?></td>
            <td><?= e((string)($q['topic_label'] ?? '')) ?></td>
            <td><?= (int)$q['attempt_count'] ?></td>
            <td><?= e((string)($q['average_percent'] ?? '—')) ?>%</td>
            <td><?= e((string)($q['success_rate'] ?? '—')) ?>%</td>
            <td><?= e((string)($q['difficulty_observed'] ?? '')) ?></td>
            <td class="small"><?php foreach (($q['common_errors'] ?? []) as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
