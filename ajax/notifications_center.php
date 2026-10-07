<?php
declare(strict_types=1);

/**
 * ajax/notifications_center.php
 * Mark notification_center rows read (JSON + CSRF).
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\NotificationCenterService;
use Edexcel\Services\ParentAuthService;

header('Content-Type: application/json; charset=utf-8');

$audience = '';
$userId = null;
$parentId = null;

if (function_exists('is_admin') && is_admin()) {
    $audience = 'admin';
    $userId = (int)($_SESSION['user_id'] ?? 0);
} elseif (function_exists('is_teacher') && is_teacher()) {
    $audience = 'teacher';
    $userId = (int)($_SESSION['user_id'] ?? 0);
} elseif (function_exists('is_student') && is_student()) {
    $audience = 'student';
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
} elseif (ParentAuthService::isLoggedIn()) {
    $audience = 'parent';
    $parentId = (int)($_SESSION['parent_id'] ?? 0);
} else {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Please sign in to update notifications.']);
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

$nc = new NotificationCenterService($pdo);
$id = (int)($input['id'] ?? 0);
$all = !empty($input['all']);

if ($all) {
    $n = $nc->markAllRead($audience, $userId, $parentId);
    $ok = true;
} elseif ($id < 1) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Choose a notification to mark as read.']);
    exit;
} else {
    $ok = $nc->markRead($id, $audience, $userId, $parentId);
    $n = $ok ? 1 : 0;
    if (!$ok) {
        echo json_encode(['ok' => false, 'marked' => 0, 'unread' => $nc->unreadCount($audience, $userId, $parentId), 'error' => 'That notification could not be marked as read (it may be shared or already cleared).']);
        exit;
    }
}

$unread = $nc->unreadCount($audience, $userId, $parentId);

echo json_encode(['ok' => $ok, 'marked' => $n, 'unread' => $unread]);
