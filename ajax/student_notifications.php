<?php
declare(strict_types=1);

/**
 * ajax/student_notifications.php
 * Mark student portal notifications as read (single or all).
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_student()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Please sign in as a student to update notifications.']);
    exit;
}

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId < 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your student account is not linked. Sign out and sign in again.']);
    exit;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST to update notifications.']);
    exit;
}

$input = $_POST;
$raw = file_get_contents('php://input');
if (is_string($raw) && $raw !== '' && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

if (!verify_csrf_token((string)($input['csrf_token'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$id = (int)($input['id'] ?? 0);
$all = !empty($input['all']);
ensure_ops_schema($pdo);

$ok = campus_mark_student_notification_read($pdo, $studentId, $all ? 0 : $id);
$count = campus_student_unread_notification_count($pdo, $studentId);

echo json_encode(['ok' => $ok, 'unread' => $count]);
