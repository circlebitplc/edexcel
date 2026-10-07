<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\ExamPrepService;

require_student();

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId <= 0) {
    http_response_code(403);
    exit('Student account is not correctly linked.');
}

$error = '';
$success = '';
$prep = new ExamPrepService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create_plan') {
                $itemsRaw = trim((string)($_POST['plan_items'] ?? ''));
                $items = [];
                foreach (preg_split('/\r\n|\r|\n/', $itemsRaw) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $items[] = ['task' => $line, 'done' => false];
                    }
                }
                $prep->createPlan($studentId, [
                    'title' => (string)($_POST['title'] ?? ''),
                    'subject_label' => (string)($_POST['subject_label'] ?? ''),
                    'qualification_label' => (string)($_POST['qualification_label'] ?? ''),
                    'exam_date' => trim((string)($_POST['exam_date'] ?? '')),
                    'plan' => $items,
                    'created_by' => 'student',
                ]);
                $success = 'Revision plan created.';
            } elseif ($action === 'update_progress') {
                $ok = $prep->updatePlanProgress(
                    $studentId,
                    (int)($_POST['plan_id'] ?? 0),
                    (float)($_POST['progress_percent'] ?? 0)
                );
                $success = $ok ? 'Progress updated.' : 'Could not update that plan.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$dash = $prep->dashboard($studentId);
$officialDates = [];
foreach ($dash['papers'] as $p) {
    $d = (string)($p['exam_date'] ?? '');
    if ($d !== '' && !in_array($d, $officialDates, true)) {
        $officialDates[] = $d;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:920px">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-journal-check"></i> Exam preparation</h1>
            <p class="text-muted mb-0 small">Official papers only — dates come from the Pearson/Edexcel timetable.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>student/dashboard.php?tab=exams">Manage papers</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>student/progress.php">Progress</a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="small text-muted">Next official paper</div>
                <?php if ($dash['next_exam']): ?>
                    <div class="fw-bold"><?= e((string)($dash['next_exam']['subject'] ?? '')) ?></div>
                    <div class="small"><?= e((string)($dash['next_exam']['unit_title'] ?? '')) ?></div>
                    <div class="mt-2">
                        <?= e((string)($dash['next_exam']['date_label'] ?? $dash['next_exam']['exam_date'] ?? '')) ?>
                    </div>
                    <div class="text-primary fw-semibold">
                        <?php if ($dash['next_exam']['days_remaining'] !== null): ?>
                            <?= (int)$dash['next_exam']['days_remaining'] ?> days remaining
                        <?php else: ?>
                            <?= e((string)($dash['next_exam']['countdown']['label'] ?? '')) ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">No upcoming selected papers.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="small text-muted">Prep progress</div>
                <div class="fs-3 fw-bold">
                    <?= $dash['prep_progress']['overall'] !== null ? e((string)$dash['prep_progress']['overall']) . '%' : '—' ?>
                </div>
                <div class="small text-muted">
                    Topics: <?= $dash['prep_progress']['topic_percent'] !== null ? e((string)$dash['prep_progress']['topic_percent']) . '%' : '—' ?>
                    · Homework: <?= $dash['prep_progress']['homework_percent'] !== null ? e((string)$dash['prep_progress']['homework_percent']) . '%' : '—' ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="small text-muted">Selected papers</div>
                <div class="fs-3 fw-bold"><?= count($dash['papers']) ?></div>
                <div class="small text-muted">From your official exam timetable</div>
            </div>
        </div>
    </div>

    <section class="mb-4">
        <h2 class="h5">Recommendations</h2>
        <ul class="mb-0">
            <?php foreach ($dash['recommendations'] as $rec): ?>
                <li><?= e($rec) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="mb-4">
        <h2 class="h5">Selected papers</h2>
        <?php if ($dash['papers'] === []): ?>
            <p class="text-muted">Add papers on the <a href="<?= e(BASE_URL) ?>student/dashboard.php?tab=exams">Exams</a> tab. Dates are never invented here.</p>
        <?php else: ?>
            <?php foreach ($dash['papers'] as $p): ?>
                <div class="border rounded-4 p-3 mb-2">
                    <div class="fw-semibold"><?= e((string)($p['subject'] ?? '')) ?> — <?= e((string)($p['unit_title'] ?? '')) ?></div>
                    <div class="small text-muted">
                        <?= e((string)($p['date_label'] ?? $p['exam_date'] ?? '')) ?>
                        · <?= e((string)($p['countdown']['label'] ?? $p['countdown_label'] ?? '')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($dash['weak_areas'] !== []): ?>
        <section class="mb-4">
            <h2 class="h5">Weak areas</h2>
            <ul>
                <?php foreach ($dash['weak_areas'] as $w): ?>
                    <li><?= e((string)($w['topic_label'] ?? '')) ?>
                        <?php if (!empty($w['subject_name'])): ?>
                            <span class="text-muted">(<?= e((string)$w['subject_name']) ?>)</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="mb-4">
        <h2 class="h5">Revision plans</h2>
        <?php if ($dash['plans'] === []): ?>
            <p class="text-muted small">No revision plans yet.</p>
        <?php else: ?>
            <?php foreach ($dash['plans'] as $plan): ?>
                <div class="card border-0 shadow-sm rounded-4 p-3 mb-2">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold"><?= e((string)$plan['title']) ?></div>
                            <div class="small text-muted">
                                <?= e((string)($plan['subject_label'] ?? '')) ?>
                                <?php if (!empty($plan['exam_date'])): ?> · exam <?= e((string)$plan['exam_date']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="fw-bold"><?= e((string)$plan['progress_percent']) ?>%</div>
                    </div>
                    <?php if (!empty($plan['plan']) && is_array($plan['plan'])): ?>
                        <ul class="small mb-2 mt-2">
                            <?php foreach (array_slice($plan['plan'], 0, 8) as $item): ?>
                                <li><?= e(is_array($item) ? (string)($item['task'] ?? json_encode($item)) : (string)$item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_progress">
                        <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Progress %</label>
                            <input type="number" class="form-control form-control-sm" name="progress_percent" min="0" max="100" step="1"
                                   value="<?= e((string)$plan['progress_percent']) ?>" style="width:6rem">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card border-0 shadow-sm rounded-4 p-3">
        <h2 class="h5">Create revision plan</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_plan">
            <div class="mb-2">
                <label class="form-label">Title</label>
                <input class="form-control" name="title" required maxlength="255" placeholder="e.g. Pure Maths P1 sprint">
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Subject label</label>
                    <input class="form-control" name="subject_label" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Qualification</label>
                    <input class="form-control" name="qualification_label" maxlength="255" placeholder="IGCSE / IAL">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Official exam date</label>
                    <select class="form-select" name="exam_date">
                        <option value="">None</option>
                        <?php foreach ($officialDates as $d): ?>
                            <option value="<?= e($d) ?>"><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Only dates from your selected official papers.</div>
                </div>
            </div>
            <div class="mb-2 mt-2">
                <label class="form-label">Tasks (one per line)</label>
                <textarea class="form-control" name="plan_items" rows="5" placeholder="Revise chapter 3&#10;Do past paper June 2023&#10;Mark scheme review"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Create plan</button>
        </form>
    </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
