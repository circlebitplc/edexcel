<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use Edexcel\Services\OfficialExamService;

require_admin();
ensure_campus_schema($pdo);

$service = new OfficialExamService($pdo);
$service->ensureSchema();

$error = '';
$success = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired. Please try again.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'import_seed') {
            $count = $service->importFromSeed();
            $success = 'Imported / updated ' . $count . ' official exam papers from the Pearson seed file.';
        } elseif ($action === 'series') {
            $service->createSeries($_POST);
            $success = 'Exam series saved. You can now add papers, or import a future Pearson timetable into this series.';
        } elseif ($action === 'delete_exam') {
            $service->deleteExam((int)($_POST['id'] ?? 0));
            $success = 'Official exam paper removed.';
        } elseif ($action === 'save_exam') {
            $id = (int)($_POST['id'] ?? 0);
            $service->saveExam($_POST, $id);
            $success = $id > 0 ? 'Exam paper updated.' : 'Exam paper added.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$seriesList = $service->listSeries();
$seriesId = (int)($_GET['series'] ?? 0);
if ($seriesId < 1) {
    $seriesId = $service->defaultSeriesId($seriesList);
}
$search = trim((string)($_GET['q'] ?? ''));
$subjectFilter = trim((string)($_GET['subject'] ?? ''));
$subjects = $seriesId > 0 ? $service->listSubjects($seriesId) : [];
$exams = $seriesId > 0 ? $service->listExams($seriesId, $search, $subjectFilter) : [];
$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0) {
    $editing = $service->findExam($editId);
    if ($editing) {
        $seriesId = (int)$editing['exam_series_id'];
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Official exam timetable</h1>
            <p class="text-muted mb-0">Pearson Edexcel series are stored once and reused. Students pick papers in the Student portal. New series do not need a schema change.</p>
        </div>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_seed">
            <button class="btn btn-outline-primary rounded-pill">
                <i class="bi bi-download me-1"></i> Import Pearson seed
            </button>
        </form>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <?php foreach ($seriesList as $s): ?>
            <div class="col-md-3">
                <a class="text-decoration-none" href="?series=<?= (int)$s['id'] ?>">
                    <div class="card border-0 shadow-sm rounded-4 h-100 <?= $seriesId === (int)$s['id'] ? 'border border-primary' : '' ?>">
                        <div class="card-body">
                            <div class="text-muted small"><?= e((string)$s['qualification_type']) ?> · <?= e((string)$s['session']) ?> <?= (int)$s['year'] ?></div>
                            <div class="fw-bold"><?= e((string)$s['name']) ?></div>
                            <div class="small text-muted mt-1"><?= (int)$s['exam_count'] ?> papers<?php if (!empty($s['first_exam'])): ?> · <?= e(date('j M', strtotime((string)$s['first_exam']))) ?>–<?= e(date('j M Y', strtotime((string)$s['last_exam']))) ?><?php endif; ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        <?php if (!$seriesList): ?>
            <div class="col-12"><div class="alert alert-info mb-0">No series yet. Import the Pearson seed (October 2026 IAL plus January / May–June 2027) or add a series below.</div></div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Add future series</h5>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="series">
                        <label class="form-label">Name</label>
                        <input class="form-control mb-2" name="name" required placeholder="January 2028 IAL">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Year</label>
                                <input class="form-control mb-2" type="number" name="year" required min="2024" max="2100" value="2027">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Session</label>
                                <input class="form-control mb-2" name="session" required placeholder="January / May/June / October">
                            </div>
                        </div>
                        <label class="form-label">Qualification</label>
                        <select class="form-select mb-2" name="qualification_type" required>
                            <option value="IAL">IAL</option>
                            <option value="IGCSE">IGCSE</option>
                        </select>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">SL morning</label>
                                <input class="form-control mb-2" type="time" name="morning_start" value="10:30">
                            </div>
                            <div class="col-6">
                                <label class="form-label">SL afternoon</label>
                                <input class="form-control mb-2" type="time" name="afternoon_start" value="13:30">
                            </div>
                        </div>
                        <label class="form-label">Source document</label>
                        <input class="form-control mb-3" name="source" placeholder="Pearson timetable title / filename">
                        <button class="btn btn-primary btn-sm rounded-pill">Save series</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><?= $editing ? 'Edit paper' : 'Add paper' ?></h5>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_exam">
                        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
                        <label class="form-label">Series</label>
                        <select class="form-select mb-2" name="exam_series_id" required>
                            <option value="">Choose series</option>
                            <?php foreach ($seriesList as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= (int)($editing['exam_series_id'] ?? $seriesId) === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label">Subject</label>
                        <input class="form-control mb-2" name="subject" required value="<?= e((string)($editing['subject'] ?? '')) ?>" placeholder="Mathematics">
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">Subject code</label>
                                <input class="form-control mb-2" name="subject_code" value="<?= e((string)($editing['subject_code'] ?? '')) ?>" placeholder="WMA">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Unit</label>
                                <input class="form-control mb-2" name="unit_code" required value="<?= e((string)($editing['unit_code'] ?? '')) ?>" placeholder="WMA11">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Paper</label>
                                <input class="form-control mb-2" name="paper_code" value="<?= e((string)($editing['paper_code'] ?? '')) ?>" placeholder="01">
                            </div>
                        </div>
                        <label class="form-label">Unit / paper title</label>
                        <input class="form-control mb-2" name="unit_title" required value="<?= e((string)($editing['unit_title'] ?? '')) ?>" placeholder="Pure Mathematics 1">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Date</label>
                                <input class="form-control mb-2" type="date" name="exam_date" required value="<?= e((string)($editing['exam_date'] ?? '')) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Session</label>
                                <select class="form-select mb-2" name="session">
                                    <?php $curSess = (string)($editing['session'] ?? 'Morning'); ?>
                                    <option value="Morning" <?= $curSess === 'Morning' ? 'selected' : '' ?>>Morning</option>
                                    <option value="Afternoon" <?= $curSess === 'Afternoon' ? 'selected' : '' ?>>Afternoon</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">Start (SL)</label>
                                <input class="form-control mb-2" type="time" name="start_time" value="<?= e(substr((string)($editing['start_time'] ?? ''), 0, 5)) ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label">End (SL)</label>
                                <input class="form-control mb-2" type="time" name="end_time" value="<?= e(substr((string)($editing['end_time'] ?? ''), 0, 5)) ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Minutes</label>
                                <input class="form-control mb-2" type="number" name="duration_minutes" min="0" value="<?= e((string)($editing['duration_minutes'] ?? '')) ?>">
                            </div>
                        </div>
                        <label class="form-label">Notes</label>
                        <textarea class="form-control mb-3" name="notes" rows="2"><?= e((string)($editing['notes'] ?? '')) ?></textarea>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary rounded-pill"><?= $editing ? 'Save paper' : 'Add paper' ?></button>
                            <?php if ($editing): ?>
                                <a class="btn btn-outline-secondary rounded-pill" href="?series=<?= (int)$seriesId ?>">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <form class="row g-2 mb-3" method="get">
                        <input type="hidden" name="series" value="<?= (int)$seriesId ?>">
                        <div class="col-sm-4">
                            <select class="form-select" name="subject" onchange="this.form.submit()">
                                <option value="">All subjects</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?= e($sub) ?>" <?= $subjectFilter === $sub ? 'selected' : '' ?>><?= e($sub) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search code or title">
                        </div>
                        <div class="col-sm-3">
                            <button class="btn btn-outline-secondary w-100">Filter</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Paper</th>
                                    <th>SL time</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($exams as $row): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= e($row['date_label']) ?><div class="text-muted"><?= e((string)($row['session'] ?? '')) ?></div></td>
                                    <td>
                                        <div class="fw-semibold"><?= e((string)$row['subject']) ?></div>
                                        <div class="small"><?= e((string)$row['unit_title']) ?></div>
                                        <div class="small text-muted font-monospace"><?= e((string)$row['paper_label']) ?></div>
                                    </td>
                                    <td class="small text-nowrap">
                                        <?= e((string)$row['time_label']) ?>
                                        <?php if ($row['end_time_label'] !== ''): ?>
                                            – <?= e((string)$row['end_time_label']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="?series=<?= (int)$seriesId ?>&id=<?= (int)$row['id'] ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this official paper? Students who selected it will lose it.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_exam">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$exams): ?>
                                <tr><td colspan="4" class="text-muted">No papers in this filter. Import the seed or add a paper on the left.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0">Start times are Sri Lanka Standard Time (UTC+5:30) from Pearson’s international centre start-time tables. Morning/afternoon labels stay as published on the UK timetable.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
