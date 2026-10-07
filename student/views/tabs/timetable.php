<?php
declare(strict_types=1);
/**
 * student/views/tabs/timetable.php
 * Tab 2: Weekly Timetable Grid & ±52 Week Navigation
 */
$lessonRecordings = $lessonRecordings ?? [];
$lessonFees = $lessonFees ?? [];
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i>Weekly Schedule</h3>
        <p class="text-muted mb-0">
            <?= student_e(date('M d', strtotime($weekStart))) ?> – <?= student_e(date('M d, Y', strtotime($weekEnd))) ?>
            <?php if ($weekOffset === 0): ?>
                <span class="badge bg-primary ms-2">Current Week</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2"><?= $weekOffset > 0 ? "+{$weekOffset} weeks" : "{$weekOffset} weeks" ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-group shadow-sm">
        <a href="?tab=timetable&week=<?= $weekOffset - 1 ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> Previous</a>
        <a href="?tab=timetable&week=0" class="btn btn-outline-secondary btn-sm <?= $weekOffset === 0 ? 'active' : '' ?>">Today</a>
        <a href="?tab=timetable&week=<?= $weekOffset + 1 ?>" class="btn btn-outline-secondary btn-sm">Next <i class="bi bi-chevron-right"></i></a>
    </div>
</div>

<div data-live-scope>
<div class="input-group mb-3 rounded-4 overflow-hidden sdr-week-search">
    <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
    <input type="search" class="form-control border-start-0" placeholder="Filter this week by subject, teacher, room, or class..." data-live-search autocomplete="off">
</div>
<div class="sdr-timetable-grid">
    <?php foreach ($days as $dateStr => $dayEntries):
        $dayTime = strtotime($dateStr);
        $isToday = ($dateStr === $today);
        $dayHaystack = strtolower(date('l d M', $dayTime));
        foreach ($dayEntries as $lesson) {
            $dayHaystack .= ' ' . strtolower(trim(
                ($lesson['subject_name'] ?? '') . ' ' .
                ($lesson['title'] ?? '') . ' ' .
                ($lesson['class_name'] ?? '') . ' ' .
                ($lesson['teacher_name'] ?? '') . ' ' .
                ($lesson['room_name'] ?? '') . ' ' .
                ($lesson['exam_type'] ?? '')
            ));
        }
    ?>
        <div class="sdr-day-card<?= $isToday ? ' is-today' : '' ?>" data-live-item data-search="<?= student_e($dayHaystack) ?>">
            <div class="sdr-day-header">
                <span class="fw-bold"><?= student_e(date('l', $dayTime)) ?></span>
                <span class="small"><?= student_e(date('d M', $dayTime)) ?></span>
                <?php if ($isToday): ?>
                    <span class="sdr-badge">Today</span>
                <?php endif; ?>
            </div>
            <div class="sdr-day-body">
                <?php if ($dayEntries): ?>
                    <?php foreach ($dayEntries as $lesson):
                        $isExam = ($lesson['_kind'] ?? 'lesson') === 'exam';
                        $examKind = (($lesson['exam_type'] ?? 'exam') === 'mock') ? 'Mock' : 'Exam';
                        $recMeta = !$isExam ? ($lessonRecordings[(int)($lesson['id'] ?? 0)] ?? null) : null;
                        $recStatus = (string)($recMeta['status'] ?? '');
                        $lessonId = (int)($lesson['id'] ?? 0);
                        $classHref = !$isExam && $lessonId > 0
                            ? student_class_page_url($lessonId, 'timetable')
                            : '';
                        $feeMeta = !$isExam ? ($lessonFees[$lessonId] ?? null) : null;
                        $needsPay = !$isExam && !empty($feeMeta['needs_pay']);
                        $feeStatus = (string)($feeMeta['status'] ?? '');
                        $mode = !$isExam ? classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical')) : 'physical';
                        $placeLabel = $isExam
                            ? (string)($lesson['room_name'] ?: ($lesson['location'] ?? 'TBA'))
                            : ($mode === 'online'
                                ? 'Online classroom'
                                : ($mode === 'hybrid'
                                    ? 'Online + ' . (string)($lesson['room_name'] ?: 'college')
                                    : (string)($lesson['room_name'] ?: 'In college')));
                    ?>
                        <div class="sdr-lesson-card <?= $isExam ? (($examKind === 'Mock') ? 'sdr-exam-card sdr-mock-card' : 'sdr-exam-card') : '' ?><?= $classHref !== '' ? ' is-clickable' : '' ?>">
                            <div class="sdr-lesson-top">
                                <span class="badge <?= $isExam ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?> border-0 font-monospace small">
                                    <?php if (!empty($lesson['exam_time']) || (!$isExam && !empty($lesson['start_time']))): ?>
                                        <?= student_e(date('g:i A', strtotime((string)($isExam ? ($lesson['exam_time'] ?: $lesson['start_time']) : $lesson['start_time'])))) ?>
                                        <?php if ($isExam && !empty($lesson['end_time'])): ?>
                                            – <?= student_e(date('g:i A', strtotime((string)$lesson['end_time']))) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Time TBA
                                    <?php endif; ?>
                                </span>
                                <span class="badge bg-secondary-subtle text-body-secondary border-0 small">
                                    <i class="bi <?= $mode === 'online' || $mode === 'hybrid' ? 'bi-broadcast' : 'bi-door-open' ?>"></i> <?= student_e($placeLabel) ?>
                                </span>
                            </div>
                            <?php if ($isExam): ?>
                                <div class="badge <?= $examKind === 'Mock' ? 'bg-warning text-dark' : 'bg-danger' ?> mb-1"><?= student_e($examKind) ?></div>
                            <?php endif; ?>
                            <div class="sdr-lesson-title"><?= student_e($isExam ? $lesson['title'] : ($lesson['subject_name'] ?? $lesson['class_name'])) ?></div>
                            <?php
                                $lessonStatus = $lesson['lesson_status'] ?? 'scheduled';
                                if (!$isExam && $lessonStatus === 'cancelled'):
                            ?>
                                <div class="badge bg-danger mb-1">Cancelled<?= !empty($lesson['cancel_reason']) ? ': ' . student_e($lesson['cancel_reason']) : '' ?></div>
                            <?php elseif (!$isExam && $lessonStatus === 'substituted'): ?>
                                <div class="badge bg-info text-dark mb-1">Substitute teacher</div>
                            <?php endif; ?>
                            <?php if (!empty($lesson['teacher_name'])): ?>
                                <div class="sdr-lesson-meta"><i class="bi bi-person"></i> <?= student_e($lesson['teacher_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($lesson['class_name'])): ?>
                                <div class="sdr-lesson-meta"><?= student_e($lesson['class_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($classHref !== ''): ?>
                                <?php
                                    $watchLabel = 'View class';
                                    $watchIcon = 'bi-box-arrow-up-right';
                                    if ($needsPay) {
                                        $watchLabel = $feeStatus === 'pending' ? 'Payment pending' : 'Pay now';
                                        $watchIcon = $feeStatus === 'pending' ? 'bi-hourglass-split' : 'bi-credit-card';
                                    } elseif (in_array($feeStatus, ['paid', 'waived'], true)) {
                                        $watchLabel = $feeStatus === 'waived' ? 'Fee waived' : 'Already paid';
                                        $watchIcon = 'bi-check-circle-fill';
                                    } elseif ($recStatus === 'ready') {
                                        $watchLabel = 'Watch recording';
                                        $watchIcon = 'bi-play-circle-fill';
                                    } elseif (in_array($recStatus, ['uploading', 'processing', 'draft'], true)) {
                                        $watchLabel = 'Recording processing';
                                        $watchIcon = 'bi-hourglass-split';
                                    } elseif ($mode === 'online' || $mode === 'hybrid') {
                                        $watchLabel = 'Open class';
                                        $watchIcon = 'bi-broadcast';
                                    }
                                ?>
                                <div class="sdr-watch-hint<?= $needsPay ? ' is-pay' : (in_array($feeStatus, ['paid', 'waived'], true) ? ' is-paid' : ($recStatus === 'ready' ? ' is-ready' : '')) ?>">
                                    <i class="bi <?= $watchIcon ?>"></i> <?= student_e($watchLabel) ?>
                                </div>
                                <a class="stretched-link" href="<?= student_e($classHref) ?>" aria-label="<?= student_e($watchLabel . ': ' . ($lesson['subject_name'] ?? 'class')) ?>"></a>
                            <?php endif; ?>
                            <?php if (!$isExam && !empty($lesson['whatsapp_link'])): ?>
                                <a href="<?= student_e($lesson['whatsapp_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm w-100 py-1 mt-2 sdr-lesson-wa" style="font-size:0.75rem;">
                                    <i class="bi bi-whatsapp me-1"></i> Class Group
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sdr-empty-day">No classes or exams</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>
