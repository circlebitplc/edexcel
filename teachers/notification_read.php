<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_staff();

header('Content-Type: application/json; charset=UTF-8');

if (($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Teacher access required.']);
    exit;
}

$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

if ($teacherId <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid request.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Session expired.']);
    exit;
}

if (($_POST['action'] ?? '') !== 'mark_read') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid action.']);
    exit;
}

$ids = $_POST['notification_ids'] ?? [];
if (!is_array($ids)) $ids = [$ids];

$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    fn($id) => $id > 0
)));

if (!$ids) {
    echo json_encode(['success'=>true,'updated'=>0]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        UPDATE teacher_notifications
        SET is_read = 1
        WHERE teacher_id = ?
          AND id IN ($placeholders)
          AND is_read = 0
    ");

    $stmt->execute(array_merge([$teacherId], $ids));

    echo json_encode([
        'success'=>true,
        'updated'=>$stmt->rowCount()
    ]);
} catch (Throwable $e) {
    error_log('Teacher notification read update failed: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to update notifications.']);
}  