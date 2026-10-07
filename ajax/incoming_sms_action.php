<?php
declare(strict_types=1);

/**
 * AJAX endpoint for Admin Incoming SMS Inbox Actions
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Services/IncomingSmsService.php';

use Edexcel\Services\IncomingSmsService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$fail = static function (string $error, int $http = 400): never {
    http_response_code($http);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_SLASHES);
    exit;
};

// Require admin authentication
require_admin();

IncomingSmsService::ensureSchema($pdo);

$adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Allow GET for quick unread counter polling
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? '')));
    if ($action === 'get_unread_count') {
        $unread = IncomingSmsService::getUnreadCount($pdo);
        $stats  = IncomingSmsService::getStats($pdo);
        echo json_encode([
            'ok'           => true,
            'unread_count' => $unread,
            'stats'        => $stats,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $fail('Invalid GET request.', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $fail('POST request required.', 405);
}

// Verify CSRF token for all state-changing POST requests
$csrfToken = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrfToken)) {
    $fail('Invalid security token. Please refresh the page and try again.', 403);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));

switch ($action) {
    case 'get_message':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fail('Invalid message identifier.');
        }

        $message = IncomingSmsService::getMessageById($pdo, $id);
        if (!$message) {
            $fail('Message not found.', 404);
        }

        // Auto mark as read on view
        $wasUnread = ((int)($message['is_read'] ?? 0)) === 0;
        if ($wasUnread) {
            IncomingSmsService::markAsRead($pdo, $id, $adminId);
            $message['is_read'] = 1;
            $message['read_at'] = date('Y-m-d H:i:s');
        }

        $unreadCount = IncomingSmsService::getUnreadCount($pdo);

        echo json_encode([
            'ok'           => true,
            'message'      => $message,
            'was_unread'   => $wasUnread,
            'unread_count' => $unreadCount,
        ], JSON_UNESCAPED_SLASHES);
        break;

    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fail('Invalid message identifier.');
        }

        $ok = IncomingSmsService::markAsRead($pdo, $id, $adminId);
        $unreadCount = IncomingSmsService::getUnreadCount($pdo);

        echo json_encode([
            'ok'           => $ok,
            'unread_count' => $unreadCount,
            'message'      => 'Message marked as read.',
        ], JSON_UNESCAPED_SLASHES);
        break;

    case 'mark_unread':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fail('Invalid message identifier.');
        }

        $ok = IncomingSmsService::markAsUnread($pdo, $id);
        $unreadCount = IncomingSmsService::getUnreadCount($pdo);

        echo json_encode([
            'ok'           => $ok,
            'unread_count' => $unreadCount,
            'message'      => 'Message marked as unread.',
        ], JSON_UNESCAPED_SLASHES);
        break;

    case 'mark_all_read':
        $count = IncomingSmsService::markAllAsRead($pdo, $adminId);
        $unreadCount = IncomingSmsService::getUnreadCount($pdo);

        echo json_encode([
            'ok'           => true,
            'updated_count'=> $count,
            'unread_count' => $unreadCount,
            'message'      => "$count message(s) marked as read.",
        ], JSON_UNESCAPED_SLASHES);
        break;

    case 'delete_message':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fail('Invalid message identifier.');
        }

        $ok = IncomingSmsService::deleteMessage($pdo, $id);
        $unreadCount = IncomingSmsService::getUnreadCount($pdo);

        echo json_encode([
            'ok'           => $ok,
            'unread_count' => $unreadCount,
            'message'      => 'Message deleted.',
        ], JSON_UNESCAPED_SLASHES);
        break;

    default:
        $fail('Unknown action: ' . htmlspecialchars($action));
}
