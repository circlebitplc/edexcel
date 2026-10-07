<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\AcademicProgressService;

require_student();

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId <= 0) {
    http_response_code(403);
    exit('Student account is not correctly linked.');
}

$progress = (new AcademicProgressService($pdo))->forStudent($studentId);

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4 student-progress-v2" style="max-width:900px">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-graph-up-arrow"></i> Academic progress</h1>
            <p class="text-muted mb-0 small">Subject averages, attendance, homework, and topic focus areas.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>student/dashboard.php">Portal home</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>student/exam_prep.php">Exam prep</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="small text-muted">Attendance</div>
                <div class="fs-3 fw-bold">
                    <?= $progress['attendance_percent'] !== null ? e((string)$progress['attendance_percent']) . '%' : '—' ?>
                </div>
                <div class="small text-muted">
                    <?= (int)$progress['attendance']['present'] ?> present ·
                    <?= (int)$progress['attendance']['absent'] ?> absent ·
                    <?= (int)$progress['attendance']['late'] ?> late
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="small text-muted">Homework completion</div>
                <div class="fs-3 fw-bold">
                    <?= $progress['homework']['completion_percent'] !== null
                        ? e((string)$progress['homework']['completion_percent']) . '%'
                        : '—' ?>
                </div>
                <div class="small text-muted">
                    <?= (int)$progress['homework']['submitted'] ?> submitted
                    <?php if ((int)$progress['homework']['assigned'] > 0): ?>
                        / <?= (int)$progress['homework']['assigned'] ?> assigned
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="small text-muted">Subjects with marks</div>
                <div class="fs-3 fw-bold"><?= count($progress['subject_averages']) ?></div>
                <div class="small text-muted">From published progress records only</div>
            </div>
        </div>
    </div>

    <section class="mb-4">
        <h2 class="h5">Subject averages</h2>
        <?php if ($progress['subject_averages'] === []): ?>
            <p class="text-muted">No marks recorded yet.</p>
        <?php else: ?>
            <?php foreach ($progress['subject_averages'] as $row): ?>
                <?php $pct = (float)$row['average_percent']; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold"><?= e($row['subject_name']) ?></span>
                        <span><?= e((string)$pct) ?>% · <?= (int)$row['count'] ?> marks</span>
                    </div>
                    <div class="progress" style="height:10px">
                        <div class="progress-bar" role="progressbar" style="width:<?= min(100, max(0, $pct)) ?>%"
                             aria-valuenow="<?= (int)$pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <h2 class="h5">Weak / revision topics</h2>
            <?php if ($progress['weak_topics'] === []): ?>
                <p class="text-muted small">No weak topics tracked yet.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush rounded-4 border">
                    <?php foreach ($progress['weak_topics'] as $t): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span>
                                <span class="fw-semibold"><?= e((string)$t['topic_label']) ?></span>
                                <?php if (!empty($t['subject_name'])): ?>
                                    <span class="text-muted small"> · <?= e((string)$t['subject_name']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="badge text-bg-warning"><?= e((string)$t['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h2 class="h5">Strong topics</h2>
            <?php if ($progress['strong_topics'] === []): ?>
                <p class="text-muted small">No strong topics tracked yet.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush rounded-4 border">
                    <?php foreach ($progress['strong_topics'] as $t): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span>
                                <span class="fw-semibold"><?= e((string)$t['topic_label']) ?></span>
                                <?php if (!empty($t['subject_name'])): ?>
                                    <span class="text-muted small"> · <?= e((string)$t['subject_name']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="badge text-bg-success"><?= e((string)$t['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <section>
        <h2 class="h5">Recent marks</h2>
        <?php if ($progress['recent_marks'] === []): ?>
            <p class="text-muted">No recent marks.</p>
        <?php else: ?>
            <div class="table-responsive card border-0 shadow-sm rounded-4">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Metric</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($progress['recent_marks'] as $m): ?>
                            <tr>
                                <td class="text-nowrap"><?= e((string)($m['recorded_at'] ?? '')) ?></td>
                                <td><?= e((string)($m['subject_name'] ?? '')) ?></td>
                                <td><?= e((string)($m['metric'] ?? '')) ?></td>
                                <td>
                                    <?php if ($m['percent'] !== null): ?>
                                        <?= e((string)$m['percent']) ?>%
                                    <?php else: ?>
                                        <?= e((string)$m['score']) ?>/<?= e((string)$m['max_score']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
