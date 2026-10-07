<?php
declare(strict_types=1);

/**
 * Student unenroll endpoint.
 * Requires a reason, which is stored and shown to teachers and admin.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/campus.php';

use Edexcel\Repositories\StudentRepository;

require_student();

$returnTab = (string)($_POST['return_tab'] ?? 'join');
if (!in_array($returnTab, ['join', 'classes'], true)) {
    $returnTab = 'join';
}
$redirect = 'dashboard.php?tab=' . $returnTab;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));

if ($studentId <= 0 || $classId <= 0) {
    $_SESSION['student_error'] = 'Invalid student or class.';
    header('Location: ' . $redirect);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['student_error'] = 'Your security token expired. Please refresh the page and try again.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $repo = new StudentRepository($pdo);
    $enrolledIds = array_map('intval', $repo->getEnrolledClassIds($studentId));
    if (!in_array($classId, $enrolledIds, true)) {
        $_SESSION['student_error'] = 'You are not enrolled in that class.';
        header('Location: ' . $redirect);
        exit;
    }

    $studentName = trim((string)($_SESSION['username'] ?? 'Student'));
    try {
        $contacts = campus_student_contacts($pdo, $studentId);
        if (!empty($contacts['name'])) {
            $studentName = (string)$contacts['name'];
        }
    } catch (Throwable $e) {
    }

    campus_unenroll_from_class(
        $pdo,
        $studentId,
        $classId,
        $reason,
        'student',
        $studentId,
        $studentName
    );

    $_SESSION['student_success'] = 'You have left the class. Teachers and the office were notified with your reason.';
} catch (Throwable $e) {
    error_log('Student unenroll failed: ' . $e->getMessage());
    $_SESSION['student_error'] = $e->getMessage() !== ''
        ? $e->getMessage()
        : 'Unable to leave this class. Please try again.';
}

header('Location: ' . $redirect);
exit;
