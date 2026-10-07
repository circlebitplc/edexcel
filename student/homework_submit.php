<?php
declare(strict_types=1);

/**
 * student/homework_submit.php — student uploads / notes a homework reply.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\HomeworkSubmissionService;

require_student();

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId < 1) {
    http_response_code(403);
    exit('Not linked');
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Location: ' . BASE_URL . 'student/dashboard.php?tab=services');
    exit;
}

if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['student_error'] = 'Security token expired. Try again.';
    header('Location: ' . BASE_URL . 'student/dashboard.php?tab=services');
    exit;
}

try {
    ensure_ops_schema($pdo);
    $svc = new HomeworkSubmissionService($pdo);
    $svc->submit(
        $studentId,
        (int)($_POST['homework_id'] ?? 0),
        (string)($_POST['note'] ?? ''),
        $_FILES['file'] ?? null,
        dirname(__DIR__) . '/files/homework',
        rtrim((string)BASE_URL, '/')
    );
    $_SESSION['student_success'] = 'Homework submitted. Your teacher can mark it from Papers.';
} catch (Throwable $e) {
    $_SESSION['student_error'] = $e->getMessage();
}

header('Location: ' . BASE_URL . 'student/dashboard.php?tab=services');
exit;
