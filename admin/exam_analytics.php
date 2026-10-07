<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentReportService;
require_admin();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$report = (new AssessmentReportService($pdo))->admin($from, $to, ['role' => 'admin', 'user_id' => (int)$_SESSION['user_id']]);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3">Examination analytics</h1>
        <form class="d-flex gap-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-primary">Filter</button></form>
    </div>
    <p class="text-muted">College-level assessment results. These figures describe completed attempts, not predicted official grades.</p>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table">
        <thead><tr><th>Subject</th><th>Qualification</th><th>Attempts</th><th>Average</th><th>Pass rate</th></tr></thead>
        <tbody><?php foreach (($report['by_subject'] ?? []) as $r): ?><tr>
            <td><?= e($r['subject_name']) ?></td><td><?= e($r['qualification_label']) ?></td>
            <td><?= (int)$r['attempts'] ?></td><td><?= e((string)round((float)($r['average_percent'] ?? 0),1)) ?>%</td>
            <td><?= e((string)round((float)($r['pass_rate'] ?? 0),1)) ?>%</td>
        </tr><?php endforeach; ?></tbody>
    </table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
