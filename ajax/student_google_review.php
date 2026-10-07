<?php
declare(strict_types=1);

/**
 * Mark the Google review prompt as shown (dismiss or after opening the review link).
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../student/otp_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || (($_SESSION['role'] ?? '') !== 'student')) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1 || !($pdo instanceof PDO)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid session']);
    exit;
}

student_mark_google_review_prompt_shown($pdo, $userId);
echo json_encode([
    'ok' => true,
    'review_url' => student_google_review_url(),
]);
