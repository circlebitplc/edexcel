<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/lesson_checkout.php';

use Edexcel\Services\BankTransferService;
use Edexcel\Services\OnePayService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\ParentAuthService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

header('Cache-Control: no-store');

if (!ParentAuthService::isLoggedIn() || !isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: /parent/login.php');
    exit;
}

$auth = new ParentAuthService($pdo);
$parentId = (int)$_SESSION['parent_id'];
$studentId = (int)($_GET['student'] ?? $_SESSION['parent_student_id'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? 0);
if (!$auth->ownsStudent($parentId, $studentId) || $lessonId < 1) {
    header('Location: /parent/home.php');
    exit;
}
$_SESSION['parent_student_id'] = $studentId;

ensure_recordings_schema($pdo);
$recordings = new RecordingService($pdo);
$fees = new StudentLessonFeeService($pdo);
$lesson = $recordings->findLesson($lessonId);
if (!$lesson || !empty($lesson['deleted_at']) || !$fees->isEnrolled($studentId, $lesson)) {
    $_SESSION['parent_error'] = 'That class was not found for this student.';
    header('Location: /parent/home.php');
    exit;
}

try {
    $roomStmt = $pdo->prepare('SELECT name FROM rooms WHERE id = ? LIMIT 1');
    $roomStmt->execute([(int)($lesson['room_id'] ?? 0)]);
    $lesson['room_name'] = (string)($roomStmt->fetchColumn() ?: '');
} catch (Throwable $e) {
    $lesson['room_name'] = '';
}

$childName = '';
foreach ($auth->children($parentId) as $c) {
    if ((int)$c['id'] === $studentId) {
        $childName = (string)$c['student_name'];
        break;
    }
}

$recording = $recordings->activeForLesson($lessonId);
$recordingId = $recording ? (int)$recording['id'] : 0;
$lessonProgress = null;
if (OnlineLessonService::supportsDeliveryMode((string)($lesson['delivery_mode'] ?? 'physical'))) {
    $publishedLesson = (new OnlineLessonService($pdo))->findPublishedByTimetable($lessonId);
    if ($publishedLesson) {
        $lessonProgress = (new OnlineLessonService($pdo))->studentProgressSummary($studentId, (int)$publishedLesson['id']);
    }
}
lesson_sync_onepay($pdo, $studentId, $lessonId);
$fee = $fees->resolve($studentId, $lesson, $recordingId > 0 ? $recordingId : null);
$openSlip = (new BankTransferService($pdo))->openSlip($studentId, $lessonId);
$mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
$cancelled = strtolower((string)($lesson['lesson_status'] ?? 'scheduled')) === 'cancelled';
$onepayEnabled = (new OnePayService($pdo))->isEnabled();
$bankCfg = bank_transfer_config($pdo);
$payAction = rtrim((string)BASE_URL, '/') . '/parent/pay_lesson.php';
$returnTo = 'class';
$amount = (float)$fee['amount_due'];
$feeStatus = !empty($fee['covered_by_monthly']) ? 'paid' : (string)$fee['status'];
$timetableId = $lessonId;
$successMessage = (string)($_SESSION['parent_success'] ?? '');
$errorMessage = (string)($_SESSION['parent_error'] ?? '');
unset($_SESSION['parent_success'], $_SESSION['parent_error']);

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$when = date('l, d F Y', strtotime((string)$lesson['date']))
    . ' · '
    . date('g:i A', strtotime((string)$lesson['start_time']))
    . ' – '
    . date('g:i A', strtotime((string)$lesson['end_time']));
$place = $mode === 'online'
    ? 'Online classroom'
    : ($mode === 'hybrid'
        ? 'Online and in college'
        : ((string)$lesson['room_name'] !== '' ? (string)$lesson['room_name'] : 'In college'));
$lang = eck_lang();
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'si' ? 'si' : 'en' ?>" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= $h((string)($lesson['subject_name'] ?? 'Class')) ?> · Pay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <link rel="stylesheet" href="/assets/css/student-portal.css?v=<?= is_file(__DIR__ . '/../assets/css/student-portal.css') ? filemtime(__DIR__ . '/../assets/css/student-portal.css') : '1' ?>">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main style="min-height:100vh;padding:24px 16px">
    <div style="width:min(100%,720px);margin:0 auto">
        <p class="mb-3"><a href="/parent/home.php?student=<?= (int)$studentId ?>">&larr; <?= $h(eck_t('parent.view')) ?></a></p>
        <?php if ($successMessage !== ''): ?><div class="alert alert-success"><?= $h($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="alert alert-danger"><?= $h($errorMessage) ?></div><?php endif; ?>
        <article class="card border-0 shadow-sm rounded-4 p-4 lesson-class-page">
            <p class="small text-muted mb-1"><?= $h($childName) ?> · <?= $h($place) ?></p>
            <h1 class="h3 fw-bold mb-1"><?= $h((string)($lesson['subject_name'] ?? 'Class')) ?></h1>
            <p class="mb-1"><?= $h((string)($lesson['class_name'] ?? '')) ?></p>
            <p class="text-muted mb-3"><?= $h($when) ?> · <?= $h((string)($lesson['teacher_name'] ?? '')) ?></p>
            <?php if ($lessonProgress && (int)$lessonProgress['total'] > 0): ?>
                <p class="mb-3">
                    Video lesson:
                    <strong><?= (int)$lessonProgress['completed'] ?> of <?= (int)$lessonProgress['total'] ?> complete</strong>
                    (<?= (int)$lessonProgress['percent'] ?>%)
                </p>
            <?php endif; ?>
            <?php if (!$cancelled && in_array($feeStatus, ['paid', 'waived'], true)): ?>
                <span class="badge text-bg-success mb-3"><?= $feeStatus === 'waived' ? 'Fee waived' : 'Already paid' ?></span>
            <?php endif; ?>
            <?php if ($cancelled): ?>
                <p class="mb-0">This class will not run.</p>
            <?php else: ?>
                <div id="pay">
                    <?php include __DIR__ . '/../includes/lesson_pay_panel.php'; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>
