<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/lesson_checkout.php';

use Edexcel\Services\OnePayService;
use Edexcel\Services\ParentAuthService;
use Edexcel\Services\PaymentTransactionService;
use Edexcel\Services\StudentLessonFeeService;

$isParent = ParentAuthService::isLoggedIn();
$isStudent = false;
try {
    if (function_exists('current_role') && current_role() === 'student' && (int)($_SESSION['user_id'] ?? 0) > 0) {
        $isStudent = true;
    }
} catch (Throwable $e) {
}

if (!$isStudent && !$isParent) {
    header('Location: ' . student_login_url());
    exit;
}

ensure_recordings_schema($pdo);

$studentId = $isStudent ? (int)($_SESSION['user_id'] ?? 0) : 0;
$ref = trim((string)($_GET['ref'] ?? $_GET['reference'] ?? $_GET['additional_data'] ?? ''));
$gatewayReturnId = trim((string)($_GET['transaction_id'] ?? $_GET['ipg_transaction_id'] ?? $_GET['onepay_transaction_id'] ?? ''));
$txn = null;
$payments = new PaymentTransactionService($pdo);
if ($ref !== '') {
    $txn = $payments->findByReference($ref);
}
if (!$txn && $gatewayReturnId !== '') {
    $txn = $payments->findByGatewayId($gatewayReturnId);
    if (!$txn) {
        $txn = $payments->findByReference($gatewayReturnId);
    }
}

if ($txn) {
    $txnStudent = (int)$txn['student_id'];
    if ($isStudent && $txnStudent !== $studentId) {
        $txn = null;
    } elseif ($isParent && !$isStudent) {
        $auth = new ParentAuthService($pdo);
        if (!$auth->ownsStudent((int)$_SESSION['parent_id'], $txnStudent)) {
            $txn = null;
        } else {
            $studentId = $txnStudent;
        }
    }
} elseif ($isParent && !$isStudent) {
    $studentId = (int)($_SESSION['parent_student_id'] ?? 0);
}

$view = 'unknown';
$message = 'We could not find that payment.';
$recordingId = 0;
$timetableId = 0;
$returnTo = 'class';
$payReturn = $_SESSION['lesson_pay_return'] ?? null;
if (is_array($payReturn)) {
    $returnTo = (string)($payReturn['to'] ?? 'class');
    $recordingId = (int)($payReturn['recording_id'] ?? 0);
    $timetableId = (int)($payReturn['timetable_id'] ?? 0);
    if (!$isStudent && (int)($payReturn['student_id'] ?? 0) > 0) {
        $studentId = (int)$payReturn['student_id'];
    }
}

if (!$txn && $studentId > 0 && $timetableId > 0) {
    $txn = $payments->latestForLesson($studentId, $timetableId, 'onepay');
}

if ($txn) {
    $recordingId = (int)($txn['recording_id'] ?? $recordingId);
    $timetableId = (int)($txn['timetable_id'] ?? $timetableId);
    if ($studentId < 1) {
        $studentId = (int)$txn['student_id'];
    }
    if ($gatewayReturnId !== '' && trim((string)($txn['gateway_transaction_id'] ?? '')) === '') {
        $payments->attachGatewayId((int)$txn['id'], $gatewayReturnId, ['return_query' => array_intersect_key($_GET, array_flip(['transaction_id', 'ipg_transaction_id', 'ref']))]);
        $txn = $payments->findById((int)$txn['id']) ?? $txn;
    }
}

if ($studentId > 0 && $timetableId > 0) {
    lesson_sync_onepay($pdo, $studentId, $timetableId);
    if ($txn && (int)($txn['id'] ?? 0) > 0) {
        $txn = $payments->findById((int)$txn['id']) ?? $txn;
    }
}

if ($txn) {
    $recordingId = (int)($txn['recording_id'] ?? $recordingId);
    $timetableId = (int)($txn['timetable_id'] ?? $timetableId);
    try {
        $ipg = (string)($txn['gateway_transaction_id'] ?? '');
        if ($ipg !== '' && (string)$txn['status'] !== 'paid') {
            $onepay = new OnePayService($pdo);
            $verified = $onepay->transactionStatus($ipg);
            $txn = $payments->applyVerifiedStatus($txn, $verified, new StudentLessonFeeService($pdo));
        }
    } catch (Throwable $e) {
        error_log('Payment return verify failed');
    }
    $status = (string)$txn['status'];
    $recordingId = (int)($txn['recording_id'] ?? $recordingId);
    $timetableId = (int)($txn['timetable_id'] ?? $timetableId);
    if ($status === 'paid') {
        $view = 'success';
        $message = 'Payment completed. You can join the live class and access the class recording.';
        unset($_SESSION['lesson_pay_return']);
    } elseif (in_array($status, ['initiated', 'pending'], true)) {
        $view = 'pending';
        $message = 'Payment is still pending. The live class and class recording stay locked until it is confirmed.';
    } elseif ($status === 'cancelled') {
        $view = 'cancelled';
        $message = 'Payment was not completed. The live class and class recording are still locked.';
    } else {
        $view = 'failed';
        $message = 'Payment was not completed. The live class and class recording are still locked.';
    }
}

$classHref = $timetableId > 0
    ? ($isStudent
        ? student_class_page_url($timetableId, 'fees')
        : parent_class_page_url($timetableId, $studentId))
    : '';
$joinHref = ($isStudent && $timetableId > 0) ? classroom_room_url($timetableId) : '';
$watchHref = ($isStudent && $recordingId > 0) ? (BASE_URL . 'student/recording.php?id=' . $recordingId) : '';
$retryHref = $classHref !== '' ? $classHref : $watchHref;
$backHref = $classHref !== ''
    ? $classHref
    : ($isStudent ? (BASE_URL . 'student/dashboard.php?tab=fees') : '/parent/home.php');
$backLabel = 'Class';

if ($isStudent) {
    include __DIR__ . '/../includes/header.php';
} else {
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment status</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/system.css">
        <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    </head>
    <body>
    <main style="min-height:100vh;padding:24px 16px">
    <div style="width:min(100%,720px);margin:0 auto">
    <?php
}

$h = $h ?? static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<p class="mb-3"><a href="<?= $h($backHref) ?>">&larr; <?= $h($backLabel) ?></a></p>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <h1 class="h4"><?= $view === 'success' ? 'Payment successful' : 'Payment status' ?></h1>
    <p><?= $h($message) ?></p>
    <?php if ($txn): ?>
        <p class="small text-muted">Reference: <?= $h((string)$txn['gateway_reference']) ?> · Rs <?= number_format((float)$txn['amount'], 2) ?></p>
    <?php endif; ?>
    <?php if ($view === 'success'): ?>
        <?php if ($classHref !== ''): ?>
            <a class="btn btn-primary" href="<?= $h($classHref) ?>">Open class</a>
        <?php endif; ?>
        <?php if ($joinHref !== ''): ?>
            <a class="btn btn-outline-primary" href="<?= $h($joinHref) ?>">Join live class</a>
        <?php endif; ?>
        <?php if ($watchHref !== ''): ?>
            <a class="btn btn-outline-primary" href="<?= $h($watchHref) ?>">Watch recording</a>
        <?php endif; ?>
        <?php if ($isStudent): ?>
            <a class="btn btn-outline-secondary" href="<?= $h(BASE_URL . 'student/payment_receipt.php?id=' . (int)$txn['id']) ?>">View receipt</a>
        <?php endif; ?>
    <?php elseif ($retryHref !== ''): ?>
        <a class="btn btn-primary" href="<?= $h($retryHref) ?>">Try again</a>
    <?php endif; ?>
</div>
<?php if ($isStudent): ?>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
<?php else: ?>
    </div></main></body></html>
<?php endif; ?>
