<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_student();
ensure_recordings_schema($pdo);
if (function_exists('student_require_presence')) {
    student_require_presence($pdo);
}

$studentId = (int)($_SESSION['user_id'] ?? 0);
$recordingId = (int)($_GET['id'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? $_GET['timetable_id'] ?? 0);
$fromTab = strtolower(trim((string)($_GET['from'] ?? '')));
$backHref = BASE_URL . 'student/dashboard.php?tab=' . ($fromTab === 'timetable' ? 'timetable' : 'recordings');
$backLabel = $fromTab === 'timetable' ? 'Timetable' : 'Class recordings';
$missingLesson = null;

if ($recordingId < 1 && $lessonId > 0) {
    $recordings = new RecordingService($pdo);
    $existing = $recordings->activeForLesson($lessonId);
    if ($existing) {
        $recordingId = (int)$existing['id'];
    } else {
        $lesson = $recordings->findLesson($lessonId);
        $enrolled = $lesson && empty($lesson['deleted_at'])
            && (new StudentLessonFeeService($pdo))->isEnrolled($studentId, $lesson);
        if ($enrolled) {
            $missingLesson = $lesson;
        }
    }
}

$access = new RecordingAccessService($pdo);
$result = $access->evaluate($studentId, $recordingId);
$state = $result['state'];
$recording = $result['recording'];
$fee = $result['fee'];
$attendance = $result['attendance'];
$assets = $result['assets'];
$timetableLesson = null;

if ($recording) {
    $timetableLesson = (new RecordingService($pdo))->findLesson((int)$recording['timetable_id']);
    if (
        $timetableLesson
        && OnlineLessonService::supportsDeliveryMode((string)($timetableLesson['delivery_mode'] ?? 'physical'))
        && (new OnlineLessonService($pdo))->findPublishedByTimetable((int)$recording['timetable_id'])
    ) {
        header('Location: ' . student_online_lesson_url((int)$recording['timetable_id'], 0, $fromTab !== '' ? $fromTab : 'recordings'));
        exit;
    }
    $access->logAccess($studentId, $recordingId, (int)$recording['timetable_id'], $state, (string)$fee['status']);
}

$embedUrls = [];
if ($state === RecordingAccessService::ACCESS_GRANTED) {
    try {
        $recSvc = new RecordingService($pdo);
        $embedUrls = $recSvc->playableEmbedUrls($recordingId, false);
        if ($embedUrls === []) {
            $state = RecordingAccessService::RECORDING_UNAVAILABLE;
        } else {
            (new \Edexcel\Services\CoursoLearnerService($pdo))->logActivity($studentId, 'recording', 'recording', $recordingId);
        }
    } catch (Throwable $e) {
        $state = RecordingAccessService::RECORDING_UNAVAILABLE;
    }
}

include __DIR__ . '/../includes/header.php';
$attLabel = $attendance ? ucfirst($attendance) : 'Not marked';
$feeLabel = $fee['covered_by_monthly'] ? 'Paid (monthly wallet)' : ucfirst((string)$fee['status']);
?>
<p class="mb-3"><a href="<?= htmlspecialchars($backHref) ?>">&larr; <?= htmlspecialchars($backLabel) ?></a></p>

<?php if ($missingLesson && !$recording): ?>
    <?php
        $missFee = (new StudentLessonFeeService($pdo))->resolve($studentId, $missingLesson);
        $missPaid = StudentLessonFeeService::isUnlocked((string)$missFee['status'], (bool)$missFee['covered_by_monthly']);
        $classHref = student_class_page_url((int)$missingLesson['id'], $fromTab === 'timetable' ? 'timetable' : 'recordings');
    ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4 mb-1"><?= htmlspecialchars((string)($missingLesson['subject_name'] ?? 'Class')) ?></h1>
        <p class="text-muted mb-2">
            <?= htmlspecialchars((string)($missingLesson['class_name'] ?? '')) ?>
            · <?= htmlspecialchars(date('d F Y', strtotime((string)$missingLesson['date']))) ?>
            · <?= htmlspecialchars(date('g:i A', strtotime((string)$missingLesson['start_time'])) . ' – ' . date('g:i A', strtotime((string)$missingLesson['end_time']))) ?>
        </p>
        <?php if (!$missPaid): ?>
            <p>Pay for this class even before it starts. The same payment unlocks the live class and the recording when it is uploaded.</p>
            <p class="fs-3 fw-bold">Rs <?= number_format((float)$missFee['amount_due'], 2) ?></p>
            <a class="btn btn-primary" href="<?= htmlspecialchars($classHref) ?>">Pay now</a>
        <?php else: ?>
            <p class="mb-0">The teacher has not uploaded a recording for this lesson yet. Check again after class.</p>
        <?php endif; ?>
    </div>
<?php elseif ($state === RecordingAccessService::NOT_AUTHORIZED || !$recording): ?>
    <div class="alert alert-danger">You are not authorised to watch this recording.</div>
<?php elseif ($state === RecordingAccessService::RECORDING_PROCESSING): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4"><?= htmlspecialchars((string)$recording['title']) ?></h1>
        <p class="text-muted mb-0">Recording is being processed. Please check again later.</p>
    </div>
<?php elseif ($state === RecordingAccessService::RECORDING_UNAVAILABLE): ?>
    <div class="alert alert-warning">This recording is currently unavailable. Please contact the college.</div>
<?php elseif ($state === RecordingAccessService::PAYMENT_PENDING): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4"><?= htmlspecialchars((string)$recording['title']) ?></h1>
        <p><?= htmlspecialchars(\Edexcel\Services\StudentLessonFeeService::pendingPaywallMessage()) ?> Please refresh after a moment.</p>
        <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL . 'student/payment_result.php') ?>">Check payment</a>
    </div>
<?php elseif ($state === RecordingAccessService::PAYMENT_REQUIRED): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4 mb-1">Payment required</h1>
        <p class="text-muted"><?= htmlspecialchars((string)$recording['subject_name']) ?> · <?= htmlspecialchars(date('d F Y', strtotime((string)$recording['date']))) ?></p>
        <p><?= htmlspecialchars(\Edexcel\Services\StudentLessonFeeService::paywallMessage()) ?></p>
        <?php if (function_exists('classroom_normalize_delivery_mode') && classroom_normalize_delivery_mode((string)($recording['delivery_mode'] ?? '')) === 'online'): ?>
            <p class="mb-1">Online class</p>
            <p class="text-muted small mb-1">This is the class fee for this session.</p>
        <?php else: ?>
            <p class="mb-1">Your class fee for this lesson is:</p>
        <?php endif; ?>
        <p class="fs-3 fw-bold">Rs <?= number_format((float)$fee['amount_due'], 2) ?></p>
        <?php student_pay_now_form([
            'timetable_id' => (int)$recording['timetable_id'],
            'recording_id' => $recordingId,
            'return' => 'recording',
        ]); ?>
        <p class="mt-3 mb-0"><a href="<?= htmlspecialchars(student_class_page_url((int)$recording['timetable_id'], 'recordings')) ?>#pay-bank">Pay by bank transfer instead</a></p>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4 mb-1"><?= htmlspecialchars((string)$recording['title']) ?></h1>
        <p class="mb-1"><?= htmlspecialchars((string)$recording['subject_name']) ?></p>
        <p class="text-muted">
            <?= htmlspecialchars(date('d F Y', strtotime((string)$recording['date']))) ?>
            · <?= htmlspecialchars(date('g:i A', strtotime((string)$recording['start_time'])) . ' – ' . date('g:i A', strtotime((string)$recording['end_time']))) ?>
            · Teacher: <?= htmlspecialchars((string)$recording['teacher_name']) ?>
        </p>
        <p>Attendance: <?= htmlspecialchars($attLabel) ?> · Class fee: <?= htmlspecialchars($feeLabel) ?></p>
        <?php foreach ($embedUrls as $url): ?>
            <div class="bunny-player ratio ratio-16x9 mb-3 rounded-4 overflow-hidden bg-dark">
                <iframe src="<?= htmlspecialchars($url) ?>" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        <?php endforeach; ?>
        <?php if ($embedUrls === []): ?>
            <div class="alert alert-warning">The player could not be prepared. Please try again shortly.</div>
        <?php endif; ?>
        <p class="small text-muted mb-0">Recording uploaded: <?= htmlspecialchars(date('d M Y', strtotime((string)$recording['created_at']))) ?></p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
