<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/lesson_checkout.php';

use Edexcel\Services\ParentAuthService;

header('Cache-Control: no-store');

if (!ParentAuthService::isLoggedIn() || !isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: /parent/login.php');
    exit;
}

$auth = new ParentAuthService($pdo);
$parentId = (int)$_SESSION['parent_id'];
$studentId = (int)($_POST['student_id'] ?? $_SESSION['parent_student_id'] ?? 0);
$recordingId = (int)($_POST['recording_id'] ?? 0);
$timetableId = (int)($_POST['timetable_id'] ?? $_POST['lesson'] ?? 0);
$returnTo = 'class';
$method = strtolower(trim((string)($_POST['method'] ?? 'onepay')));

$go = static function (int $timetableId, int $studentId): never {
    header('Location: ' . parent_class_page_url($timetableId, $studentId));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['parent_error'] = 'Please tap Pay Now again to continue to card payment.';
    $go($timetableId, $studentId);
}
if (!$auth->ownsStudent($parentId, $studentId)) {
    $_SESSION['parent_error'] = 'That student is not linked to this parent login.';
    header('Location: /parent/home.php');
    exit;
}

try {
    ensure_recordings_schema($pdo);
    $resolved = lesson_checkout_resolve_fee($pdo, $studentId, $recordingId, $timetableId);
    $recordingId = (int)$resolved['recording_id'];
    $timetableId = (int)$resolved['timetable_id'];
    if (!empty($resolved['already_paid'])) {
        $_SESSION['parent_success'] = 'This lesson is already paid.';
        $go($timetableId, $studentId);
    }
    $lesson = $resolved['lesson'];
    $fee = $resolved['fee'];
    if ((float)$fee['amount_due'] <= 0) {
        $go($timetableId, $studentId);
    }

    $_SESSION['lesson_pay_return'] = [
        'to' => 'class',
        'timetable_id' => $timetableId,
        'recording_id' => $recordingId,
        'parent' => 1,
        'student_id' => $studentId,
    ];
    $_SESSION['parent_student_id'] = $studentId;

    if ($method === 'bank') {
        lesson_submit_bank_slip(
            $pdo,
            $studentId,
            $lesson,
            $fee,
            $_FILES['slip'] ?? [],
            $recordingId,
            $parentId,
            trim((string)($_POST['slip_note'] ?? ''))
        );
        $_SESSION['parent_success'] = 'Bank slip uploaded. The class unlocks after the office confirms the transfer.';
        $go($timetableId, $studentId);
    }

    $profile = [
        'full_name' => (string)($_SESSION['parent_name'] ?? 'Parent'),
        'phone' => (string)($_SESSION['parent_phone'] ?? ''),
        'email' => '',
        'whatsapp_number' => (string)($_SESSION['parent_phone'] ?? ''),
    ];
    try {
        $p = $pdo->prepare('SELECT full_name, email, whatsapp_number FROM student_profiles WHERE user_id = ? LIMIT 1');
        $p->execute([$studentId]);
        $student = $p->fetch(PDO::FETCH_ASSOC) ?: [];
        if (trim($profile['full_name']) === '') {
            $profile['full_name'] = (string)($student['full_name'] ?? 'Parent');
        }
        if (trim((string)$profile['email']) === '') {
            $profile['email'] = (string)($student['email'] ?? '');
        }
    } catch (Throwable $e) {
    }
} catch (Throwable $e) {
    lesson_checkout_log_failure($e);
    $_SESSION['parent_error'] = $e->getMessage();
    $go($timetableId, $studentId);
}

try {
    lesson_run_onepay_checkout($pdo, $studentId, $lesson, $fee, $recordingId, 'class', $profile, 'parent', $parentId);
} catch (Throwable $e) {
    lesson_checkout_log_failure($e);
    $_SESSION['parent_error'] = lesson_checkout_public_start_error();
    $go($timetableId, $studentId);
}
