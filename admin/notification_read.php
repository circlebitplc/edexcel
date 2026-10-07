<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';

require_admin();

header('Content-Type: application/json; charset=UTF-8');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

if (($_POST['action'] ?? '') !== 'mark_read') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$ids = $_POST['notification_ids'] ?? [];
if (!is_array($ids)) {
    $ids = [$ids];
}

$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    static fn($id) => $id > 0
)));

if (!$ids) {
    echo json_encode(['success' => true, 'updated' => 0]);
    exit;
}

try {
    ensure_campus_schema($pdo);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        UPDATE admin_notifications
        SET is_read = 1
        WHERE user_id = ?
          AND id IN ($placeholders)
          AND is_read = 0
    ");
    $stmt->execute(array_merge([$userId], $ids));
    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    error_log('Admin notification read update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update notifications.']);
}
