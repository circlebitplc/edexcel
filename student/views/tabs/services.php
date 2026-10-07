<?php
declare(strict_types=1);
/**
 * student/views/tabs/services.php
 * Tab 5: Academic Services (Materials, Homework, Exams, Events, Progress)
 */

$materials = $academicServices['materials'] ?? [];
$homework  = $academicServices['homework'] ?? [];
$exams     = $academicServices['exams'] ?? [];
$events    = $academicServices['events'] ?? [];
$progress  = $academicServices['progress'] ?? [];
?>
<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-journal-check me-2 text-primary"></i>Homework & notes</h3>
    <p class="text-muted mb-0">Class materials, homework, exam notices, and college events in one place.</p>
</div>

<div class="row g-4">
    <!-- 1. Learning Materials -->
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Learning Materials</span>
                <span class="badge bg-primary-subtle text-primary"><?= count($materials) ?> available</span>
            </div>
            <div class="card-body p-0">
                <?php if ($materials): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($materials as $m): ?>
                            <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-semibold mb-1 text-dark"><?= student_e($m['title']) ?></h6>
                                    <p class="small text-muted mb-0"><?= student_e($m['description'] ?? 'Class material resource') ?></p>
                                </div>
                                <?php if (!empty($m['file_url'])): ?>
                                    <a href="<?= student_e($m['file_url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">No class materials uploaded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. Homework & Assignments -->
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="bi bi-journal-check me-2 text-warning"></i>Homework & Tasks</span>
                <span class="badge bg-warning-subtle text-warning"><?= count($homework) ?> tasks</span>
            </div>
            <div class="card-body p-0">
                <?php if ($homework): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($homework as $hw): ?>
                            <div class="list-group-item p-3" id="hw-<?= (int)$hw['id'] ?>">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-semibold mb-0 text-dark"><?= student_e($hw['title']) ?></h6>
                                    <?php if (!empty($hw['due_date'])): ?>
                                        <span class="badge bg-danger-subtle text-danger small">Due: <?= student_e(date('M d', strtotime($hw['due_date']))) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="small text-muted mb-2"><?= student_e($hw['description'] ?? 'Homework task') ?></p>
                                <?php if (!empty($hw['class_name']) || !empty($hw['teacher_name'])): ?>
                                    <div class="small text-muted mb-2">
                                        <?= student_e((string)($hw['class_name'] ?? '')) ?>
                                        <?php if (!empty($hw['teacher_name'])): ?> · <?= student_e((string)$hw['teacher_name']) ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($hw['link'])): ?>
                                    <a href="<?= student_e($hw['link']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 mb-2" style="font-size:0.75rem;">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open paper
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($hw['submission_status'])): ?>
                                    <div class="alert alert-light border py-2 small mb-2">
                                        Status: <strong><?= student_e((string)$hw['submission_status']) ?></strong>
                                        <?php if ($hw['score'] !== null && $hw['score'] !== ''): ?>
                                            · Score <?= student_e((string)$hw['score']) ?><?php if (!empty($hw['max_score'])): ?>/<?= student_e((string)$hw['max_score']) ?><?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($hw['teacher_feedback'])): ?>
                                            <div class="mt-1"><?= student_e((string)$hw['teacher_feedback']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hw['submission_file'])): ?>
                                            <div><a href="<?= student_e((string)$hw['submission_file']) ?>" target="_blank" rel="noopener">Your file</a></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (($hw['submission_status'] ?? '') !== 'done'): ?>
                                    <form method="post" action="<?= student_e(BASE_URL . 'student/homework_submit.php') ?>" enctype="multipart/form-data" class="border-top pt-2 mt-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="homework_id" value="<?= (int)$hw['id'] ?>">
                                        <label class="form-label small mb-1">Submit note or file</label>
                                        <textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="Short note to your teacher"><?= student_e((string)($hw['submission_note'] ?? '')) ?></textarea>
                                        <input class="form-control form-control-sm mb-2" type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                        <button class="btn btn-sm btn-primary"><?= !empty($hw['submission_id']) ? 'Update submission' : 'Submit homework' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">No pending homework assignments.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. Exam Schedule -->
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 fw-bold text-dark"><i class="bi bi-clipboard-data me-2 text-danger"></i>Upcoming Exams & Assessments</div>
            <div class="card-body p-0">
                <?php if ($exams): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($exams as $ex):
                            $kind = (($ex['exam_type'] ?? 'exam') === 'mock') ? 'Mock' : 'Exam';
                        ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge <?= $kind === 'Mock' ? 'bg-warning text-dark' : 'bg-danger' ?> mb-1"><?= student_e($kind) ?></span>
                                        <h6 class="fw-bold mb-1 text-danger"><?= student_e($ex['title']) ?></h6>
                                        <div class="small text-muted">
                                            <i class="bi bi-calendar-event"></i> <?= student_e(date('l, M d, Y', strtotime($ex['exam_date']))) ?>
                                            <?php if (!empty($ex['exam_time'])): ?>
                                                · <i class="bi bi-clock"></i> <?= student_e(date('g:i A', strtotime($ex['exam_time']))) ?>
                                            <?php endif; ?>
                                            <?php
                                                $venue = function_exists('campus_exam_venue') ? campus_exam_venue($ex) : (string)($ex['location'] ?? '');
                                                if ($venue !== ''):
                                            ?>
                                                · <i class="bi bi-door-open"></i> <?= student_e($venue) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">No upcoming examinations scheduled.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. College Events -->
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 fw-bold text-dark"><i class="bi bi-megaphone me-2 text-info"></i>College Events & Notices</div>
            <div class="card-body p-0">
                <?php if ($events): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($events as $ev): ?>
                            <div class="list-group-item p-3">
                                <h6 class="fw-bold mb-1 text-dark"><?= student_e($ev['title']) ?></h6>
                                <div class="small text-muted mb-1"><i class="bi bi-calendar-date"></i> <?= student_e(date('M d, Y', strtotime($ev['event_date']))) ?></div>
                                <p class="small text-muted mb-0"><?= student_e($ev['description'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">No events scheduled at this time.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 5. Progress -->
    <div class="col-12">
        <div class="card border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 fw-bold text-dark"><i class="bi bi-graph-up me-2 text-success"></i>My Progress</div>
            <div class="card-body p-0">
                <?php if ($progress): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progress as $row): ?>
                                    <tr>
                                        <td><?= student_e($row['metric'] ?? 'Progress') ?></td>
                                        <td><?= student_e(($row['score'] ?? '0') . ' / ' . ($row['max_score'] ?? '100')) ?></td>
                                        <td><?= !empty($row['recorded_at']) ? student_e(date('d M Y', strtotime((string)$row['recorded_at']))) : '—' ?></td>
                                        <td><?= student_e($row['note'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">Progress records will appear here when teachers add results.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
