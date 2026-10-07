<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_student();
ensure_online_lesson_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? 0);
$from = strtolower(trim((string)($_GET['from'] ?? 'timetable')));
if (!in_array($from, ['timetable', 'overview', 'fees', 'recordings', 'classroom', 'class'], true)) {
    $from = 'timetable';
}
$backHref = BASE_URL . 'student/class.php?lesson=' . $lessonId . '&from=' . rawurlencode($from);
if ($lessonId < 1) {
    header('Location: ' . BASE_URL . 'student/dashboard.php');
    exit;
}

$recordings = new RecordingService($pdo);
$fees = new StudentLessonFeeService($pdo);
$lessons = new OnlineLessonService($pdo);
$timetable = $recordings->findLesson($lessonId);
$onlineLesson = $timetable ? $lessons->findPublishedByTimetable($lessonId) : null;
$allowed = $timetable
    && empty($timetable['deleted_at'])
    && $fees->isEnrolled($studentId, $timetable)
    && $onlineLesson;
$error = $allowed ? '' : 'That lesson result is not available.';
$detail = null;
if ($allowed) {
    $recording = $recordings->activeForLesson($lessonId);
    $state = OnlineLessonService::feeUnlockState(
        $fees->resolve($studentId, $timetable, $recording ? (int)$recording['id'] : null)
    );
    if ($state !== RecordingAccessService::ACCESS_GRANTED) {
        $error = 'Pay for this class to see your result.';
    } else {
        $pass = $onlineLesson['pass_percent'] ?? null;
        $report = $lessons->lessonResultReport(
            (int)$onlineLesson['id'],
            (int)$timetable['class_id'],
            $pass === null || $pass === '' ? null : (int)$pass,
            $studentId
        );
        $detail = $report['students'][0] ?? null;
        if ($detail === null) {
            $error = 'That lesson result is not available.';
        }
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= $h($backHref) ?>">&larr; Class</a></p>
<h1 class="h3 mb-1">Your video lesson result</h1>
<p class="text-muted"><?= $h((string)($timetable['subject_name'] ?? '')) ?></p>
<?php if ($error !== ''): ?>
    <div class="alert alert-warning"><?= $h($error) ?></div>
<?php elseif ($detail): ?>
    <?php include __DIR__ . '/../includes/lesson_result_detail.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
