<?php
declare(strict_types=1);
/**
 * student/views/tabs/overview.php
 * Student "Today" hub: next class, pay/join, homework due, fees, attendance, notifications.
 */

$lessonFees = $lessonFees ?? [];
$homeworkDueSoon = $homeworkDueSoon ?? [];
$studentNotifications = $studentNotifications ?? [];
$studentUnreadNotifications = (int)($studentUnreadNotifications ?? 0);
$feeStatement = $feeStatement ?? ['due' => 0, 'lesson_due' => 0];
$unpaidLessons = [];
foreach (($feeStatement['lessons'] ?? []) as $row) {
    if (in_array((string)($row['status'] ?? ''), ['unpaid', 'pending', 'partial'], true) && empty($row['covered_by_monthly'])) {
        $unpaidLessons[] = $row;
    }
}
$primaryUnpaid = $unpaidLessons[0] ?? null;
$walletDue = (float)(($feeStatement['wallet']['due'] ?? $feeWallet['due'] ?? 0));
$totalDue = (float)($feeStatement['due'] ?? $walletDue);
?>
<?php include dirname(__DIR__, 3) . '/includes/classroom_now.php'; ?>

<section class="sdr-stats">
    <article>
        <div class="sdr-stat-icon"><i class="bi bi-calendar-day"></i></div>
        <strong><?= count($todayEntries) ?></strong>
        <span>Today</span>
    </article>
    <article>
        <div class="sdr-stat-icon"><i class="bi bi-journal-check"></i></div>
        <strong><?= count($homeworkDueSoon) ?></strong>
        <span>Homework due</span>
    </article>
    <article>
        <div class="sdr-stat-icon"><i class="bi bi-wallet2"></i></div>
        <strong>Rs <?= number_format($totalDue) ?></strong>
        <span>Fees due</span>
    </article>
    <article>
        <div class="sdr-stat-icon"><i class="bi bi-bell"></i></div>
        <strong><?= $studentUnreadNotifications ?></strong>
        <span>Unread</span>
    </article>
</section>

<?php
$continueLesson = null;
if (isset($pdo) && $pdo instanceof PDO && (int)($studentId ?? 0) > 0) {
    try {
        $continueLesson = (new \Edexcel\Services\CourseService($pdo))->studentPath((int)$studentId)['next'];
    } catch (Throwable $e) {
        error_log('continue learning: ' . $e->getMessage());
    }
}
?>
<?php if ($continueLesson): ?>
    <section class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="small text-muted"><i class="bi bi-play-circle me-1"></i>Continue learning</div>
                <div class="fw-semibold"><?= htmlspecialchars((string)$continueLesson['title'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="small text-muted"><?= htmlspecialchars((string)$continueLesson['subject'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$continueLesson['percent'] ?>% complete</div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL . 'student/learning_path.php', ENT_QUOTES, 'UTF-8') ?>">Learning path</a>
                <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(student_online_lesson_url((int)$continueLesson['timetable_id'], 0, 'overview'), ENT_QUOTES, 'UTF-8') ?>"><?= (int)$continueLesson['percent'] > 0 ? 'Continue' : 'Start' ?></a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!$whatsappVerified && empty($phoneNumberVerified)): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-shield-exclamation fs-4"></i>
        <div>
            <strong>WhatsApp verification pending</strong> — Link your phone for reminders.
            <a href="?tab=settings" class="alert-link ms-2">Settings</a>
        </div>
    </div>
<?php endif; ?>

<section class="sdr-next-card mb-4">
    <div class="sdr-card-header">
        <div class="sdr-label"><i class="bi bi-sun"></i> TODAY — NEXT CLASS</div>
        <a href="?tab=timetable" class="small">Full timetable</a>
    </div>
    <div class="sdr-card-body">
        <?php if ($nextClass): ?>
            <?php
            $nextOnline = in_array(classroom_normalize_delivery_mode((string)($nextClass['delivery_mode'] ?? 'physical')), ['online', 'hybrid'], true);
            $nextFee = $lessonFees[(int)$nextClass['id']] ?? null;
            $nextNeedsPay = !empty($nextFee['needs_pay']);
            $nextStatus = (string)($nextClass['lesson_status'] ?? 'scheduled');
            $nextHref = student_class_page_url((int)$nextClass['id'], 'overview');
            $payHref = BASE_URL . 'student/dashboard.php?tab=fees';
            if ($nextNeedsPay && !empty($nextClass['id'])) {
                $payHref = student_class_page_url((int)$nextClass['id'], 'fees');
            }
            $joinLateMinutes = (int)(classroom_settings($pdo ?? null)['join_late_minutes'] ?? 15);
            $nextJoinClosed = $nextOnline && classroom_join_window_closed($nextClass, $joinLateMinutes);
            ?>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <?php if ($nextStatus === 'cancelled'): ?>
                        <span class="badge bg-danger mb-2">Cancelled</span>
                    <?php elseif ($nextStatus === 'substituted'): ?>
                        <span class="badge bg-info text-dark mb-2">Substitute teacher</span>
                    <?php endif; ?>
                    <h3 class="mb-1 text-primary fw-bold"><?= student_e($nextClass['subject_name'] ?? $nextClass['class_name'] ?? 'Class') ?></h3>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar-event"></i> <?= student_e(date('l, d M Y', strtotime($nextClass['date']))) ?> ·
                        <i class="bi bi-clock"></i> <?= student_e(date('g:i A', strtotime($nextClass['start_time']))) ?> – <?= student_e(date('g:i A', strtotime($nextClass['end_time']))) ?>
                    </p>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-person"></i> <?= student_e($nextClass['teacher_name'] ?? 'Teacher') ?>
                        · <i class="bi <?= $nextOnline ? 'bi-broadcast' : 'bi-door-open' ?>"></i> <?= student_e($nextClass['room_name'] ?? 'Room TBA') ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($nextNeedsPay): ?>
                        <a class="btn btn-primary rounded-pill" href="<?= student_e($payHref) ?>"><i class="bi bi-credit-card"></i> Pay to unlock</a>
                    <?php elseif ($nextOnline && $nextStatus !== 'cancelled' && !$nextJoinClosed): ?>
                        <a class="btn btn-danger rounded-pill" href="<?= student_e($nextHref) ?>"><i class="bi bi-broadcast"></i> Join / open</a>
                    <?php elseif ($nextOnline && $nextJoinClosed): ?>
                        <button type="button" class="btn btn-secondary rounded-pill" disabled>Join closed</button>
                    <?php else: ?>
                        <a class="btn btn-outline-primary rounded-pill" href="<?= student_e($nextHref) ?>"><i class="bi bi-box-arrow-up-right"></i> Open class</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                <p class="mb-0">No upcoming classes in the next 30 days.</p>
                <a href="?tab=join" class="btn btn-outline-primary btn-sm mt-3"><i class="bi bi-search"></i> Browse classes</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-calendar-day me-2 text-primary"></i>Today’s schedule</span>
                <span class="badge bg-primary-subtle text-primary"><?= count($todayEntries) + count($todayExams ?? []) ?></span>
            </div>
            <div class="card-body">
                <?php
                $todayAll = array_merge($todayEntries, $todayExams ?? []);
                usort($todayAll, static function ($a, $b) {
                    return strcmp((string)($a['start_time'] ?? ''), (string)($b['start_time'] ?? ''));
                });
                ?>
                <?php if ($todayAll): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todayAll as $entry):
                            $isExam = ($entry['_kind'] ?? '') === 'exam';
                            $st = (string)($entry['lesson_status'] ?? 'scheduled');
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <div class="fw-semibold text-dark">
                                        <?= student_e($isExam ? $entry['title'] : ($entry['subject_name'] ?? $entry['class_name'])) ?>
                                        <?php if (!$isExam && $st === 'cancelled'): ?><span class="badge bg-danger ms-1">Cancelled</span><?php endif; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if ($isExam): ?><span class="badge <?= strtolower((string)($entry['exam_type'] ?? '')) === 'mock' ? 'bg-warning text-dark' : 'bg-danger' ?> me-1"><?= student_e(campus_exam_type_label((string)($entry['exam_type'] ?? 'exam'))) ?></span><?php endif; ?>
                                        <?php if (!empty($entry['teacher_name'])): ?><i class="bi bi-person"></i> <?= student_e($entry['teacher_name']) ?> · <?php endif; ?>
                                        <i class="bi bi-door-open"></i> <?= student_e($entry['room_name'] ?: ($entry['location'] ?? '')) ?>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark border px-2 py-1"><?= student_e(!empty($entry['start_time']) && $entry['start_time'] !== '23:59:00' ? date('g:i A', strtotime((string)$entry['start_time'])) : 'TBA') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 py-3 text-center">No classes or exams today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100 border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-journal-check me-2 text-warning"></i>Homework due</span>
                <a href="?tab=services" class="small">Open all</a>
            </div>
            <div class="card-body p-0">
                <?php if ($homeworkDueSoon): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($homeworkDueSoon as $hw): ?>
                            <div class="list-group-item px-3 py-3">
                                <div class="d-flex justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold"><?= student_e((string)$hw['title']) ?></div>
                                        <div class="small text-muted">
                                            <?= student_e((string)($hw['class_name'] ?? $hw['subject_name'] ?? '')) ?>
                                            <?php if (!empty($hw['due_date'])): ?> · due <?= student_e(date('d M', strtotime((string)$hw['due_date']))) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($hw['submission_status'])): ?>
                                        <span class="badge bg-success-subtle text-success"><?= student_e((string)$hw['submission_status']) ?></span>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline-primary" href="?tab=services#hw-<?= (int)$hw['id'] ?>">Submit</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 p-4 text-center">Nothing due soon.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between">
                <span class="fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Balance &amp; pay</span>
                <a href="?tab=fees" class="small">Full statement</a>
            </div>
            <div class="card-body">
                <div class="fs-3 fw-bold mb-1">Rs <?= number_format($totalDue, 2) ?></div>
                <p class="text-muted small mb-3">
                    Monthly wallet Rs <?= number_format($walletDue, 2) ?>
                    · Class fees Rs <?= number_format((float)($feeStatement['lesson_due'] ?? 0), 2) ?>
                    (live + recordings)
                </p>
                <?php if ($primaryUnpaid): ?>
                    <a class="btn btn-primary rounded-pill" href="<?= student_e(student_class_page_url((int)$primaryUnpaid['timetable_id'], 'fees')) ?>">
                        Pay next class fee — Rs <?= number_format((float)$primaryUnpaid['amount_due'], 2) ?>
                    </a>
                <?php elseif ($totalDue > 0): ?>
                    <a class="btn btn-primary rounded-pill" href="?tab=fees">View what to pay</a>
                <?php else: ?>
                    <span class="badge text-bg-success">Nothing blocking live / recordings</span>
                <?php endif; ?>
                <div class="small text-muted mt-3">
                    Attendance this month:
                    <?= isset($attendanceSummary['percent']) && $attendanceSummary['percent'] !== null ? (int)$attendanceSummary['percent'] . '%' : '—' ?>
                    · <a href="?tab=attendance">Details</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-bell me-2 text-primary"></i>Notifications</span>
                <?php if ($studentUnreadNotifications > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="markAllNotificationsRead">Mark all read</button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($studentNotifications): ?>
                    <div class="list-group list-group-flush" id="studentNotificationList">
                        <?php foreach ($studentNotifications as $note): ?>
                            <?php
                            $link = (string)($note['link'] ?? '');
                            if ($link !== '' && !preg_match('#^https?://#i', $link) && !str_starts_with($link, '/')) {
                                $link = BASE_URL . 'student/' . ltrim($link, '/');
                            } elseif ($link !== '' && str_starts_with($link, '/')) {
                                $link = rtrim(BASE_URL, '/') . $link;
                            }
                            ?>
                            <a class="list-group-item list-group-item-action px-3 py-3 <?= empty($note['is_read']) ? 'bg-primary-subtle' : '' ?>"
                               href="<?= student_e($link !== '' ? $link : '#') ?>"
                               data-notification-id="<?= (int)$note['id'] ?>">
                                <div class="fw-semibold"><?= student_e($note['title'] ?? 'Notice') ?></div>
                                <?php if (!empty($note['message'])): ?>
                                    <div class="small text-muted text-truncate"><?= student_e($note['message']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted"><?= student_e(date('d M · g:i A', strtotime((string)$note['created_at']))) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 p-4 text-center">No notifications yet. Class cancels, homework, and fee reminders appear here.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($nextOfficialExam)): ?>
<section class="sdr-next-card mb-4 sep-next-exam">
    <div class="sdr-card-header">
        <div class="sdr-label"><i class="bi bi-hourglass-split"></i> NEXT OFFICIAL EXAM</div>
        <a href="?tab=exams" class="small">Open planner</a>
    </div>
    <div class="sdr-card-body">
        <h3 class="mb-1 text-danger fw-bold"><?= student_e((string)$nextOfficialExam['subject']) ?></h3>
        <p class="mb-1"><?= student_e((string)$nextOfficialExam['unit_title']) ?> <span class="text-muted font-monospace"><?= student_e((string)$nextOfficialExam['paper_label']) ?></span></p>
        <p class="text-muted mb-2">
            <?= student_e((string)$nextOfficialExam['date_label']) ?> — <?= student_e((string)$nextOfficialExam['time_label']) ?>
        </p>
        <div class="sep-countdown" data-exam-at="<?= student_e((string)($nextOfficialExam['starts_at'] ?? '')) ?>">
            <?= student_e((string)$nextOfficialExam['countdown_label']) ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="sdr-next-card mb-4">
    <div class="sdr-card-header">
        <div class="sdr-label"><i class="bi bi-stars"></i> TALK WITH AI</div>
        <a href="?tab=courso" class="small">Open assistant</a>
    </div>
    <div class="sdr-card-body">
        <p class="mb-2">
            <strong><?= (int)($coursoSnapshot['streak'] ?? 0) ?>-day streak</strong>
            · <?= (int)($coursoSnapshot['completed_lessons'] ?? 0) ?> lessons this month
        </p>
        <?php if (!empty($coursoSnapshot['next_steps'])): ?>
            <?php $step = $coursoSnapshot['next_steps'][0]; ?>
            <h3 class="h5 fw-bold mb-1"><?= student_e((string)$step['title']) ?></h3>
            <p class="text-muted mb-3"><?= student_e((string)$step['why']) ?></p>
            <a class="btn btn-primary btn-sm rounded-pill" href="<?= student_e((string)$step['href']) ?>"><?= student_e((string)($step['cta'] ?? 'Continue')) ?></a>
        <?php else: ?>
            <a class="btn btn-primary btn-sm rounded-pill" href="?tab=courso">Talk with AI</a>
        <?php endif; ?>
    </div>
</section>
