<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/lesson_checkout.php';

use Edexcel\Services\BankTransferService;
use Edexcel\Services\OnePayService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_student();
ensure_recordings_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? $_GET['id'] ?? 0);
$from = strtolower(trim((string)($_GET['from'] ?? 'timetable')));
if (!in_array($from, ['timetable', 'overview', 'fees', 'recordings', 'classroom'], true)) {
    $from = 'timetable';
}

$backHref = BASE_URL . 'student/dashboard.php?tab=' . ($from === 'classroom' ? 'overview' : $from);
$backLabel = [
    'timetable' => 'Timetable',
    'overview' => 'Home',
    'fees' => 'Fees',
    'recordings' => 'Recordings',
    'classroom' => 'Home',
][$from];

if ($lessonId < 1) {
    header('Location: ' . $backHref);
    exit;
}

$recordings = new RecordingService($pdo);
$fees = new StudentLessonFeeService($pdo);
$lesson = $recordings->findLesson($lessonId);
if (!$lesson || !empty($lesson['deleted_at']) || !$fees->isEnrolled($studentId, $lesson)) {
    $_SESSION['student_error'] = 'That class was not found, or you are not enrolled.';
    header('Location: ' . $backHref);
    exit;
}

try {
    $roomStmt = $pdo->prepare('SELECT name FROM rooms WHERE id = ? LIMIT 1');
    $roomStmt->execute([(int)($lesson['room_id'] ?? 0)]);
    $lesson['room_name'] = (string)($roomStmt->fetchColumn() ?: '');
} catch (Throwable $e) {
    $lesson['room_name'] = '';
}

$recording = $recordings->activeForLesson($lessonId);
$recordingId = $recording ? (int)$recording['id'] : 0;
lesson_sync_onepay($pdo, $studentId, $lessonId);
$fee = $fees->resolve($studentId, $lesson, $recordingId > 0 ? $recordingId : null);
$openSlip = (new BankTransferService($pdo))->openSlip($studentId, $lessonId);
$mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
$online = in_array($mode, ['online', 'hybrid'], true);
$lessons = new OnlineLessonService($pdo);
$publishedLesson = $lessons->findPublishedByTimetable($lessonId);
$videoItems = [];
$videoDone = [];
if ($publishedLesson) {
    $videoItems = $lessons->items((int)$publishedLesson['id']);
    if ($videoItems !== []) {
        $videoDone = array_fill_keys($lessons->completedItemIds($studentId, (int)$publishedLesson['id']), true);
    }
}
$hasVideoLesson = $videoItems !== [];
$videoDoneCount = 0;
if ($hasVideoLesson) {
    foreach ($videoItems as $videoRow) {
        if (isset($videoDone[(int)$videoRow['id']])) {
            $videoDoneCount++;
        }
    }
}
$videoTitle = $publishedLesson ? trim((string)($publishedLesson['title'] ?? '')) : '';
$videoActiveSeconds = 0;
$videoLastSeen = '';
if ($publishedLesson && $studentId > 0) {
    try {
        $timeStmt = $pdo->prepare('SELECT active_seconds, last_seen_at FROM online_lesson_progress WHERE student_id = ? AND lesson_id = ? LIMIT 1');
        $timeStmt->execute([$studentId, (int)$publishedLesson['id']]);
        $timeRow = $timeStmt->fetch(PDO::FETCH_ASSOC);
        if ($timeRow) {
            $videoActiveSeconds = (int)($timeRow['active_seconds'] ?? 0);
            $videoLastSeen = (string)($timeRow['last_seen_at'] ?? '');
        }
    } catch (Throwable $e) {
        $videoActiveSeconds = 0;
        $videoLastSeen = '';
    }
}
$videoWindow = ($publishedLesson && $hasVideoLesson)
    ? OnlineLessonService::availabilityWindow($publishedLesson, $lesson)
    : ['open' => false, 'message' => '', 'opens_at' => 0, 'closes_at' => 0];
$videoWaiting = $hasVideoLesson && empty($videoWindow['open']) && (int)$videoWindow['opens_at'] > time();
$videoClosed = $hasVideoLesson && empty($videoWindow['open']) && !$videoWaiting;
$showVideoLesson = $hasVideoLesson && !$videoWaiting;
$videoNeedsClips = false;
if ($showVideoLesson) {
    foreach ($videoItems as $videoRow) {
        if (OnlineLessonService::normalizeItemType((string)($videoRow['item_type'] ?? '')) === 'video') {
            $videoNeedsClips = true;
            break;
        }
    }
}
$recordingStatus = strtolower((string)($recording['status'] ?? ''));
$recordingReady = $recordingId > 0 && $recordingStatus === 'ready';
$cancelled = strtolower((string)($lesson['lesson_status'] ?? 'scheduled')) === 'cancelled';
$successMessage = (string)(function_exists('student_flash') ? (student_flash('student_success') ?? '') : ($_SESSION['student_success'] ?? ''));
$errorMessage = (string)(function_exists('student_flash') ? (student_flash('student_error') ?? '') : ($_SESSION['student_error'] ?? ''));
if (!function_exists('student_flash')) {
    unset($_SESSION['student_success'], $_SESSION['student_error']);
}

$onepayEnabled = (new OnePayService($pdo))->isEnabled();
$bankCfg = bank_transfer_config($pdo);
$payAction = rtrim((string)BASE_URL, '/') . '/student/pay_lesson.php';
$returnTo = 'class';
$amount = (float)$fee['amount_due'];
$feeStatus = (string)$fee['status'];
if (!empty($fee['covered_by_monthly'])) {
    $feeStatus = 'paid';
}
$feeUnlocked = in_array($feeStatus, ['paid', 'waived'], true);
$joinLateMinutes = (int)(classroom_settings($pdo)['join_late_minutes'] ?? 15);
$joinClosed = classroom_join_window_closed($lesson, $joinLateMinutes);
$joinHref = $online && $feeUnlocked && !$joinClosed ? classroom_room_url($lessonId, '') : '';
$continueHref = ($feeUnlocked && $showVideoLesson && !$videoClosed)
    ? student_online_lesson_url($lessonId, 0, $from)
    : '';
$watchHref = ($feeUnlocked && $recordingReady && !$showVideoLesson)
    ? (BASE_URL . 'student/recording.php?id=' . $recordingId . '&from=' . rawurlencode($from === 'classroom' ? 'timetable' : $from))
    : '';
$timetableId = $lessonId;
$studentIdHidden = 0;

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$when = date('l, d F Y', strtotime((string)$lesson['date']))
    . ' · '
    . date('g:i A', strtotime((string)$lesson['start_time']))
    . ' – '
    . date('g:i A', strtotime((string)$lesson['end_time']));
$place = $mode === 'online'
    ? 'Online classroom'
    : ($mode === 'hybrid'
        ? 'Online and ' . ((string)$lesson['room_name'] !== '' ? (string)$lesson['room_name'] : 'in college')
        : ((string)$lesson['room_name'] !== '' ? (string)$lesson['room_name'] : 'In college'));

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= $h($backHref) ?>">&larr; <?= $h($backLabel) ?></a></p>

<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?= $h($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?= $h($errorMessage) ?></div>
<?php endif; ?>

<article class="card border-0 shadow-sm rounded-4 p-4 lesson-class-page">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <div>
            <p class="text-muted small mb-1"><?= $h($place) ?></p>
            <h1 class="h3 fw-bold mb-1"><?= $h((string)($lesson['subject_name'] ?? 'Class')) ?></h1>
            <p class="mb-0"><?= $h((string)($lesson['class_name'] ?? '')) ?></p>
        </div>
        <?php if ($cancelled): ?>
            <span class="badge text-bg-danger align-self-start">Cancelled</span>
        <?php elseif (in_array($feeStatus, ['paid', 'waived'], true)): ?>
            <span class="badge text-bg-success align-self-start">Paid</span>
        <?php elseif ($feeStatus === 'pending'): ?>
            <span class="badge text-bg-warning text-dark align-self-start">Payment pending</span>
        <?php else: ?>
            <span class="badge text-bg-primary align-self-start">Pay to unlock</span>
        <?php endif; ?>
    </div>
    <p class="text-muted mb-1"><i class="bi bi-clock"></i> <?= $h($when) ?></p>
    <p class="text-muted mb-3"><i class="bi bi-person"></i> <?= $h((string)($lesson['teacher_name'] ?? '')) ?></p>

    <?php if ($cancelled): ?>
        <p class="mb-0">This class will not run<?= !empty($lesson['cancel_reason']) ? ': ' . $h((string)$lesson['cancel_reason']) : '.' ?></p>
    <?php else: ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php if ($joinHref !== ''): ?>
                <a class="btn btn-primary" href="<?= $h($joinHref) ?>"><i class="bi bi-broadcast me-1"></i> Open online classroom</a>
            <?php elseif ($online && $feeUnlocked && $joinClosed && !$cancelled): ?>
                <button type="button" class="btn btn-secondary" disabled title="Join closes <?= (int)$joinLateMinutes ?> minutes after class ends"><i class="bi bi-broadcast me-1"></i> Join closed</button>
            <?php endif; ?>
            <?php if ($joinHref === '' && $continueHref === '' && $watchHref === ''): ?>
                <?php if ($online && !$feeUnlocked): ?>
                    <span class="text-warning small">Pay for this lesson to unlock the online classroom and lesson content.</span>
                <?php elseif ($feeUnlocked): ?>
                    <span class="text-success small">You have already paid for this lesson.</span>
                <?php else: ?>
                    <span class="text-muted small">This lesson is in college. Pay below if you have not paid yet — you do not have to wait until class starts.</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($showVideoLesson): ?>
            <section class="lesson-video-card" aria-labelledby="lesson-video-heading">
                <p class="lesson-video-kicker">Learning module</p>
                <h2 class="lesson-video-title" id="lesson-video-heading"><?= $h((string)($lesson['subject_name'] ?? 'Class')) ?></h2>
                <p class="lesson-video-class mb-1"><?= $h((string)($lesson['class_name'] ?? '')) ?></p>
                <?php if ($videoTitle !== '' && strcasecmp($videoTitle, (string)($lesson['subject_name'] ?? '')) !== 0): ?>
                    <p class="lesson-video-lead mb-1"><?= $h($videoTitle) ?></p>
                <?php endif; ?>
                <p class="lesson-video-lead">Published learning material</p>
                <?php if ($videoClosed): ?>
                    <p class="lesson-video-locked mb-0"><?= $h((string)$videoWindow['message']) ?></p>
                <?php elseif ($feeUnlocked): ?>
                    <?php $videoPercent = count($videoItems) > 0 ? (int)round($videoDoneCount / count($videoItems) * 100) : 0; ?>
                    <div class="progress mb-2" style="height:8px" role="progressbar" aria-valuenow="<?= $videoPercent ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Your progress">
                        <div class="progress-bar" style="width:<?= $videoPercent ?>%"></div>
                    </div>
                    <p class="lesson-video-count mb-3">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <?= $videoPercent ?>% · <?= count($videoItems) ?> items
                        <?php if ($videoDoneCount > 0): ?>
                            <span>· <?= (int)$videoDoneCount ?> / <?= count($videoItems) ?> completed</span>
                        <?php endif; ?>
                        <?php if ($videoActiveSeconds > 0): ?>
                            <span>· <?= $h(OnlineLessonService::formatActiveTime($videoActiveSeconds)) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php
                        $resumeTitle = '';
                        if ($videoDoneCount < count($videoItems)) {
                            $resumeId = OnlineLessonService::resumeItemId($videoItems, array_keys($videoDone));
                            foreach ($videoItems as $videoRow) {
                                if ((int)$videoRow['id'] === $resumeId) {
                                    $resumeTitle = (string)$videoRow['title'];
                                    break;
                                }
                            }
                        }
                        $remainMinutes = OnlineLessonService::remainingMinutes($videoItems, array_keys($videoDone));
                    ?>
                    <?php if ($resumeTitle !== '' && $videoDoneCount > 0 && $videoDoneCount < count($videoItems)): ?>
                        <p class="lesson-video-note">Continue from: <?= $h($resumeTitle) ?></p>
                    <?php endif; ?>
                    <?php if ($remainMinutes > 0): ?>
                        <p class="lesson-video-note">About <?= (int)$remainMinutes ?> minutes left</p>
                    <?php endif; ?>
                    <?php if ($videoLastSeen !== ''): ?>
                        <p class="lesson-video-note">Last activity <?= $h(date('d M Y, g:i A', strtotime($videoLastSeen))) ?></p>
                    <?php endif; ?>
                    <?php if ($continueHref !== ''): ?>
                        <a class="btn btn-primary lesson-video-continue" href="<?= $h($continueHref) ?>">Continue learning</a>
                        <a class="btn btn-outline-primary lesson-video-continue" href="<?= $h(student_lesson_result_url($lessonId)) ?>">Your result</a>
                    <?php endif; ?>
                    <?php if ($videoNeedsClips && !$recordingReady): ?>
                        <p class="lesson-video-note">Video clips from this class are not ready yet. The published activities are available.</p>
                    <?php elseif ($recordingReady): ?>
                        <p class="lesson-video-note">The class recording is included in this lesson.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="lesson-video-locked mb-0">Pay for this lesson to unlock the video lesson.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($feeUnlocked && $recordingId > 0 && !$showVideoLesson && !$videoWaiting): ?>
            <section class="lesson-video-card" aria-labelledby="lesson-recording-heading">
                <p class="lesson-video-kicker">Class recording</p>
                <h2 class="lesson-video-title" id="lesson-recording-heading"><?= $h((string)($lesson['subject_name'] ?? 'Class')) ?></h2>
                <?php if ($recordingReady && $watchHref !== ''): ?>
                    <p class="lesson-video-lead">The recording from this live class is available.</p>
                    <a class="btn btn-primary lesson-video-continue" href="<?= $h($watchHref) ?>">Watch Recording →</a>
                <?php elseif (in_array($recordingStatus, ['uploading', 'processing', 'draft'], true)): ?>
                    <p class="lesson-video-locked mb-0">The class recording is still being prepared.</p>
                <?php else: ?>
                    <p class="lesson-video-locked mb-0">This class recording is not available yet.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div id="pay">
            <?php
            $studentId = 0;
            include __DIR__ . '/../includes/lesson_pay_panel.php';
            ?>
        </div>
    <?php endif; ?>
</article>
<?php include __DIR__ . '/../includes/footer.php'; ?>
