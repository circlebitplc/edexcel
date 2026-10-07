<?php
declare(strict_types=1);
/**
 * student/views/tabs/exams.php
 * Official Pearson exam planner + college mock / class exam slots.
 */

$plannerExams = $myOfficialExams ?? [];
$catalogue = $officialCatalogue ?? [];
$seriesOptions = $officialSeries ?? [];
$selectedIds = $officialSelectedIds ?? [];
$matchedSubjects = $officialMatchedSubjects ?? [];
$catalogueBySubject = [];
foreach ($catalogue as $paper) {
    $catalogueBySubject[(string)$paper['subject']][] = $paper;
}
if ($matchedSubjects) {
    $matchedSet = array_flip($matchedSubjects);
    uksort($catalogueBySubject, static function (string $a, string $b) use ($matchedSet): int {
        $am = isset($matchedSet[$a]) ? 0 : 1;
        $bm = isset($matchedSet[$b]) ? 0 : 1;
        return $am === $bm ? strcasecmp($a, $b) : $am <=> $bm;
    });
}
$hiddenReturn = static function () use ($officialSeriesId, $officialSubject, $officialSearch): string {
    $html = '<input type="hidden" name="series" value="' . (int)$officialSeriesId . '">';
    if ($officialSubject !== '') {
        $html .= '<input type="hidden" name="subject" value="' . student_e($officialSubject) . '">';
    }
    if ($officialSearch !== '') {
        $html .= '<input type="hidden" name="q" value="' . student_e($officialSearch) . '">';
    }
    return $html;
};
$nextPlanner = null;
foreach ($plannerExams as $row) {
    if (empty($row['is_past'])) {
        $nextPlanner = $row;
        break;
    }
}
$visibleCampusExams = $upcomingExams ?? [];
if (($examFilter ?? 'all') !== 'all') {
    $visibleCampusExams = array_values(array_filter(
        $visibleCampusExams,
        static fn(array $row): bool => strtolower((string)($row['exam_type'] ?? 'exam')) === $examFilter
    ));
}
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 sep-no-print">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-journal-text me-2 text-danger"></i>My exam timetable</h3>
        <p class="text-muted mb-0">Choose official Edexcel papers. Countdown uses Sri Lanka Standard Time (UTC+5:30).</p>
    </div>
    <?php if ($plannerExams): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm rounded-pill" href="exam_planner.php?action=ics">
                <i class="bi bi-calendar-plus me-1"></i> Add to calendar
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if ($nextPlanner): ?>
<section class="sdr-next-card mb-4 sep-next-exam">
    <div class="sdr-card-header">
        <div class="sdr-label"><i class="bi bi-hourglass-split"></i> Next official exam</div>
        <span class="badge bg-danger-subtle text-danger"><?= student_e((string)($nextPlanner['qualification_type'] ?? '')) ?> · <?= student_e((string)($nextPlanner['series_name'] ?? '')) ?></span>
    </div>
    <div class="sdr-card-body">
        <h3 class="mb-1 text-danger fw-bold"><?= student_e((string)$nextPlanner['subject']) ?></h3>
        <p class="mb-1 fw-semibold"><?= student_e((string)$nextPlanner['unit_title']) ?> <span class="text-muted font-monospace"><?= student_e((string)$nextPlanner['paper_label']) ?></span></p>
        <p class="text-muted mb-2">
            <?= student_e((string)$nextPlanner['date_label']) ?> — <?= student_e((string)$nextPlanner['time_label']) ?>
            <span class="small">(Sri Lanka time)</span>
        </p>
        <div class="sep-countdown sep-countdown-lg" data-exam-at="<?= student_e((string)($nextPlanner['starts_at'] ?? '')) ?>">
            <?= student_e((string)$nextPlanner['countdown_label']) ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="card border shadow-sm rounded-4 mb-4">
    <div class="card-body p-0">
        <?php if ($plannerExams): ?>
            <div class="sep-timeline">
                <?php
                $lastDate = '';
                foreach ($plannerExams as $ex):
                    if ((string)$ex['exam_date'] !== $lastDate):
                        $lastDate = (string)$ex['exam_date'];
                ?>
                    <div class="sep-day-head"><?= student_e((string)$ex['date_label']) ?></div>
                <?php endif; ?>
                    <article class="sep-exam-card <?= !empty($ex['is_past']) ? 'is-past' : '' ?> <?= !empty($ex['has_clash']) ? 'has-clash' : '' ?>">
                        <div class="sep-exam-main">
                            <div class="sep-exam-kicker">
                                <span class="badge bg-danger-subtle text-danger"><?= student_e((string)($ex['qualification_type'] ?? 'Exam')) ?></span>
                                <span class="small text-muted"><?= student_e((string)($ex['series_name'] ?? '')) ?></span>
                                <?php if (!empty($ex['has_clash'])): ?>
                                    <span class="badge bg-warning text-dark">Time clash</span>
                                <?php endif; ?>
                            </div>
                            <h4 class="sep-exam-title"><?= student_e((string)$ex['subject']) ?></h4>
                            <div class="sep-exam-unit"><?= student_e((string)$ex['unit_title']) ?></div>
                            <div class="small text-muted font-monospace mb-2"><?= student_e((string)$ex['paper_label']) ?></div>
                            <div class="sep-exam-when">
                                <?= student_e((string)$ex['time_label']) ?>
                                <?php if (!empty($ex['end_time_label'])): ?>
                                    – <?= student_e((string)$ex['end_time_label']) ?>
                                <?php endif; ?>
                                <span class="small">(Sri Lanka time)</span>
                            </div>
                            <?php if (!empty($ex['has_clash'])): ?>
                                <div class="small text-warning mb-2">Overlaps <?= student_e(implode(', ', $ex['clash_with'])) ?></div>
                            <?php endif; ?>
                            <div class="sep-countdown" data-exam-at="<?= student_e((string)($ex['starts_at'] ?? '')) ?>">
                                <?= student_e((string)$ex['countdown_label']) ?>
                            </div>
                        </div>
                        <form method="post" action="exam_planner.php" class="sep-exam-remove sep-no-print">
                            <?= csrf_field() ?>
                            <?= $hiddenReturn() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="exam_id" value="<?= (int)$ex['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit">Remove</button>
                        </form>
                    </article>
                    <?php if ($ex['study_days_after'] !== null): ?>
                        <div class="sep-study-gap">
                            <?php if ((int)$ex['study_days_after'] === 0): ?>
                                <span>No full study day between these exams</span>
                            <?php else: ?>
                                <strong><?= (int)$ex['study_days_after'] ?></strong>
                                <?= (int)$ex['study_days_after'] === 1 ? 'study day' : 'study days' ?>
                                <span>between these examinations</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-plus fs-1 d-block mb-2"></i>
                <p class="mb-0">You have not selected any official exams yet. Add papers from the catalogue below.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border shadow-sm rounded-4 mb-4 sep-no-print">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h4 class="fw-bold mb-0">Add official exams</h4>
                <p class="text-muted small mb-0">Works with any series stored in the database — October, January, May/June, and future releases.</p>
            </div>
        </div>
        <form class="row g-2 mb-3" method="get">
            <input type="hidden" name="tab" value="exams">
            <div class="col-md-4">
                <select class="form-select" name="series" onchange="this.form.submit()">
                    <?php foreach ($seriesOptions as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$officialSeriesId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= student_e((string)$s['name']) ?> (<?= (int)$s['exam_count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="subject" onchange="this.form.submit()">
                    <option value="">All subjects</option>
                    <?php foreach ($officialSubjects as $sub): ?>
                        <option value="<?= student_e($sub) ?>" <?= $officialSubject === $sub ? 'selected' : '' ?>><?= student_e($sub) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <div class="input-group">
                    <input class="form-control" name="q" value="<?= student_e($officialSearch) ?>" placeholder="Search subject, unit or paper code">
                    <button class="btn btn-outline-secondary">Search</button>
                </div>
            </div>
        </form>

        <?php if ($catalogueBySubject): ?>
            <form method="post" action="exam_planner.php">
                <?= csrf_field() ?>
                <?= $hiddenReturn() ?>
                <input type="hidden" name="action" value="add_many">
                <?php foreach ($catalogueBySubject as $subjectName => $papers):
                    $isYours = in_array($subjectName, $matchedSubjects, true);
                ?>
                    <section class="sep-subject-block mb-3">
                        <div class="sep-subject-head d-flex align-items-center justify-content-between gap-2 flex-wrap">
                            <h5 class="mb-0">
                                <?= student_e($subjectName) ?>
                                <?php if ($isYours): ?>
                                    <span class="badge bg-primary-subtle text-primary">Your subject</span>
                                <?php endif; ?>
                            </h5>
                            <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit" form="<?= student_e('sep-add-subject-' . preg_replace('/[^a-z0-9]+/i', '-', $subjectName)) ?>">
                                Add all papers
                            </button>
                        </div>
                        <div class="list-group rounded-3 overflow-hidden">
                            <?php foreach ($papers as $paper):
                                $already = in_array((int)$paper['id'], $selectedIds, true);
                            ?>
                                <label class="list-group-item d-flex align-items-start gap-3 sep-pick-row <?= $already ? 'is-added' : '' ?>">
                                    <input class="form-check-input mt-1" type="checkbox" name="exam_ids[]" value="<?= (int)$paper['id'] ?>" <?= $already ? 'disabled' : '' ?>>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                                            <strong><?= student_e((string)$paper['unit_title']) ?></strong>
                                            <span class="small font-monospace text-muted"><?= student_e((string)$paper['paper_label']) ?></span>
                                        </div>
                                        <div class="small text-muted">
                                            <?= student_e((string)$paper['date_label']) ?> — <?= student_e((string)$paper['time_label']) ?>
                                            · <?= student_e((string)($paper['session'] ?? '')) ?>
                                            <?php if (!empty($paper['duration_minutes'])): ?>
                                                · <?= (int)$paper['duration_minutes'] ?> min
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($already): ?>
                                        <span class="badge bg-success-subtle text-success">Added</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
                <button class="btn btn-danger rounded-pill">
                    <i class="bi bi-plus-lg me-1"></i> Add selected exams
                </button>
            </form>
            <?php foreach ($catalogueBySubject as $subjectName => $_papers):
                $formId = 'sep-add-subject-' . preg_replace('/[^a-z0-9]+/i', '-', $subjectName);
            ?>
                <form id="<?= student_e($formId) ?>" method="post" action="exam_planner.php" class="d-none">
                    <?= csrf_field() ?>
                    <?= $hiddenReturn() ?>
                    <input type="hidden" name="action" value="add_subject">
                    <input type="hidden" name="series" value="<?= (int)$officialSeriesId ?>">
                    <input type="hidden" name="subject_name" value="<?= student_e($subjectName) ?>">
                </form>
            <?php endforeach; ?>
        <?php elseif ($seriesOptions): ?>
            <p class="text-muted mb-0">No papers match this filter.</p>
        <?php else: ?>
            <p class="text-muted mb-0">Official exam series have not been imported yet. Ask the office to load the Pearson timetable.</p>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3 sep-no-print">
    <div>
        <h4 class="fw-bold mb-1">College mocks & class exams</h4>
        <p class="text-muted mb-0">Slots published by your teachers for enrolled classes.</p>
    </div>
    <div class="btn-group shadow-sm">
        <a href="?tab=exams&exam_type=all&series=<?= (int)$officialSeriesId ?>" class="btn btn-outline-secondary btn-sm <?= ($examFilter ?? 'all') === 'all' ? 'active' : '' ?>">All</a>
        <a href="?tab=exams&exam_type=exam&series=<?= (int)$officialSeriesId ?>" class="btn btn-outline-secondary btn-sm <?= ($examFilter ?? '') === 'exam' ? 'active' : '' ?>">Exams</a>
        <a href="?tab=exams&exam_type=mock&series=<?= (int)$officialSeriesId ?>" class="btn btn-outline-secondary btn-sm <?= ($examFilter ?? '') === 'mock' ? 'active' : '' ?>">Mocks</a>
    </div>
</div>

<div class="card border shadow-sm rounded-4 sep-no-print">
    <div class="card-body p-0">
        <?php if ($visibleCampusExams): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($visibleCampusExams as $ex):
                    $isMock = strtolower((string)($ex['exam_type'] ?? '')) === 'mock';
                    $venue = campus_exam_venue($ex);
                ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <span class="badge <?= $isMock ? 'bg-secondary' : 'bg-danger' ?>"><?= student_e(campus_exam_type_label((string)$ex['exam_type'])) ?></span>
                                <h6 class="fw-bold mb-1 mt-2"><?= student_e($ex['title']) ?></h6>
                                <div class="small text-muted">
                                    <?= student_e(campus_exam_when($ex)) ?>
                                    <?php if (!empty($ex['subject_name'])): ?> · <?= student_e($ex['subject_name']) ?><?php endif; ?>
                                    <?php if (!empty($ex['class_name'])): ?> · <?= student_e($ex['class_name']) ?><?php endif; ?>
                                    <?php if ($venue !== ''): ?> · <i class="bi bi-door-open"></i> <?= student_e($venue) ?><?php endif; ?>
                                    <?php if (!empty($ex['teacher_name'])): ?> · <i class="bi bi-person"></i> <?= student_e($ex['teacher_name']) ?><?php endif; ?>
                                </div>
                                <?php if (!empty($ex['notes'])): ?>
                                    <div class="small mt-1"><?= student_e($ex['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-check fs-1 d-block mb-2"></i>
                <p class="mb-0">No college exam or mock papers are scheduled yet for your classes.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

