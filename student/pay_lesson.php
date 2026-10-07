<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/lesson_checkout.php';

require_student();
ensure_recordings_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$recordingId = (int)($_POST['recording_id'] ?? 0);
$timetableId = (int)($_POST['timetable_id'] ?? $_POST['lesson'] ?? 0);
$returnTo = strtolower(trim((string)($_POST['return'] ?? '')));
$method = strtolower(trim((string)($_POST['method'] ?? 'onepay')));
$pack = lesson_checkout_return($returnTo, $recordingId, $timetableId);
$returnTo = $pack['to'];

$go = static function (string $url): never {
    header('Location: ' . $url);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['student_error'] = 'Please tap Pay Now again to continue to card payment.';
    $go(lesson_checkout_fail_url('student', $returnTo, $recordingId, $timetableId, $studentId));
}

try {
    $resolved = lesson_checkout_resolve_fee($pdo, $studentId, $recordingId, $timetableId);
    $recordingId = (int)$resolved['recording_id'];
    $timetableId = (int)$resolved['timetable_id'];
    if (!empty($resolved['already_paid'])) {
        $_SESSION['student_success'] = 'You have already paid for this lesson.';
        $go(lesson_checkout_ok_url('student', $returnTo, $recordingId, $timetableId, $studentId));
    }
    $lesson = $resolved['lesson'];
    $fee = $resolved['fee'];
    $amount = (float)$fee['amount_due'];
    if ($amount <= 0) {
        $go(lesson_checkout_ok_url('student', $returnTo, $recordingId, $timetableId, $studentId));
    }

    $_SESSION['lesson_pay_return'] = [
        'to' => $returnTo,
        'timetable_id' => $timetableId,
        'recording_id' => $recordingId,
    ];

    if ($method === 'bank') {
        lesson_submit_bank_slip(
            $pdo,
            $studentId,
            $lesson,
            $fee,
            $_FILES['slip'] ?? [],
            $recordingId,
            null,
            trim((string)($_POST['slip_note'] ?? ''))
        );
        $_SESSION['student_success'] = 'Bank slip uploaded. The class unlocks after the office confirms the transfer.';
        $go(lesson_checkout_ok_url('student', 'class', $recordingId, $timetableId, $studentId) . '#pay-bank');
    }

    $profile = ['full_name' => '', 'email' => '', 'whatsapp_number' => ''];
    try {
        $p = $pdo->prepare('SELECT full_name, email, whatsapp_number FROM student_profiles WHERE user_id = ? LIMIT 1');
        $p->execute([$studentId]);
        $profile = array_merge($profile, $p->fetch(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
    }
    if (trim((string)$profile['whatsapp_number']) === '') {
        $profile['whatsapp_number'] = (string)($_SESSION['username'] ?? '');
    }
} catch (Throwable $e) {
    lesson_checkout_log_failure($e);
    $_SESSION['student_error'] = $e->getMessage();
    $go(lesson_checkout_fail_url('student', $returnTo, $recordingId, $timetableId, $studentId));
}

try {
    lesson_run_onepay_checkout($pdo, $studentId, $lesson, $fee, $recordingId, $returnTo, $profile, 'student', null);
} catch (Throwable $e) {
    lesson_checkout_log_failure($e);
    $_SESSION['student_error'] = lesson_checkout_public_start_error();
    $go(lesson_checkout_fail_url('student', $returnTo, $recordingId, $timetableId, $studentId));
}
