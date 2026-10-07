<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentService;
require_staff();
$auth = ['user_id' => (int)($_SESSION['user_id'] ?? 0), 'role' => (string)($_SESSION['role'] ?? ''), 'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0)];
$filters = [
    'qualification_label' => trim((string)($_GET['qualification'] ?? '')),
    'subject_id' => (int)($_GET['subject_id'] ?? 0),
    'class_id' => (int)($_GET['class_id'] ?? 0),
    'teacher_id' => (int)($_GET['teacher_id'] ?? 0),
    'unit_label' => trim((string)($_GET['unit'] ?? '')),
    'assessment_type' => (string)($_GET['type'] ?? ''),
    'status' => (string)($_GET['status'] ?? ''),
    'from' => (string)($_GET['from'] ?? ''),
    'to' => (string)($_GET['to'] ?? ''),
];
$svc = new AssessmentService($pdo);
$rows = $svc->dashboard($filters, $auth);
$counts = $svc->counters($auth);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-ui-checks-grid"></i> Examination control centre</h1>
            <p class="text-muted mb-0">Create, assign, mark, analyse and re-test. Official Pearson papers stay separate from generated practice.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>campus/assessment_builder.php">New assessment</a>
    </div>
    <div class="row g-2 my-3">
        <?php foreach ([['Upcoming',$counts['upcoming']],['Active',$counts['active']],['Completed',$counts['completed']],['Outstanding marking',$counts['outstanding']]] as $card): ?>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($card[0]) ?></div><div class="fs-3 fw-bold"><?= (int)$card[1] ?></div></div></div>
        <?php endforeach; ?>
    </div>
    <form class="card border-0 shadow-sm p-3 mb-3">
        <div class="row g-2">
            <div class="col-md-2"><input class="form-control" name="qualification" placeholder="Qualification" value="<?= e($filters['qualification_label']) ?>"></div>
            <div class="col-md-2"><input class="form-control" name="unit" placeholder="Unit" value="<?= e($filters['unit_label']) ?>"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="subject_id" placeholder="Subject ID" value="<?= $filters['subject_id'] ?: '' ?>"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="class_id" placeholder="Class ID" value="<?= $filters['class_id'] ?: '' ?>"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
            <div class="col-md-2">
                <select class="form-select" name="type">
                    <option value="">All types</option>
                    <?php foreach (AssessmentService::TYPES as $t): ?><option <?= $filters['assessment_type']===$t?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (AssessmentService::STATUSES as $st): ?><option <?= $filters['status']===$st?'selected':'' ?>><?= e($st) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </div>
    </form>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Assessment</th><th>Type</th><th>Class</th><th>Status</th><th>When</th><th>Participation</th><th>Average</th><th>High/Low</th><th>Marking</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['title']) ?><div class="small text-muted"><?= e($r['qualification_label'] ?? '') ?> <?= e($r['unit_label'] ?? '') ?></div></td>
                <td><?= e($r['assessment_type']) ?></td>
                <td><?= e($r['class_name'] ?? '—') ?></td>
                <td><span class="badge text-bg-secondary"><?= e($r['status']) ?></span></td>
                <td class="small"><?= e($r['start_at'] ?? $r['created_at']) ?></td>
                <td><?= (int)$r['participants'] ?></td>
                <td><?= e((string)($r['average_percent'] ?? '—')) ?></td>
                <td><?= e((string)($r['highest_percent'] ?? '—')) ?> / <?= e((string)($r['lowest_percent'] ?? '—')) ?></td>
                <td><?= (int)$r['outstanding_marking'] ?></td>
                <td class="text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="assessment_builder.php?id=<?= (int)$r['id'] ?>">Open</a>
                    <a class="btn btn-sm btn-outline-secondary" href="assessment_analytics.php?id=<?= (int)$r['id'] ?>">Analyse</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
