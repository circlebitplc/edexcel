<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomChatVisibility;

$loaded = classroom_load_lesson($pdo);
$access = $loaded['access'];
$lesson = $loaded['lesson'];
$meeting = $loaded['meeting'];
if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'Class not found.'], 404);
}
classroom_api_require_access($access);

$settings = classroom_settings($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = (int)($_SESSION['user_id'] ?? 0);
$isHost = !empty($access['is_host']);
$hostUserId = classroom_chat_default_host_user_id($pdo, $lesson, $meeting);
$hostIds = classroom_lesson_host_user_ids($pdo, $lesson, $meeting);
if (empty($settings['chat_enabled']) && !$isHost) {
    classroom_json(['ok' => false, 'error' => 'Chat is turned off.'], 403);
}

if ($method === 'GET') {
    $after = (int)($_GET['after'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT id, user_id, display_name, body, is_announcement, is_private, recipient_user_id, created_at
        FROM meeting_chat_messages
        WHERE meeting_id = ? AND deleted_at IS NULL AND id > ?
          AND (IFNULL(is_private, 0) = 0 OR user_id = ? OR recipient_user_id = ?)
        ORDER BY id ASC
        LIMIT 200
    ");
    $stmt->execute([(int)$meeting['id'], $after, $userId, $userId]);
    $rows = ClassroomChatVisibility::filterVisible($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $userId);
    $messages = [];
    $peerIds = [];
    foreach ($rows as $row) {
        $msg = classroom_chat_normalize_row($row);
        $messages[] = $msg;
        if ((int)$msg['is_private'] === 1) {
            $other = (int)$msg['user_id'] === $userId
                ? (int)$msg['recipient_user_id']
                : (int)$msg['user_id'];
            if ($other > 0 && $other !== $userId) {
                $peerIds[$other] = $other;
            }
        }
    }
    $threads = [];
    foreach ($peerIds as $peerId) {
        $peerRole = in_array($peerId, $hostIds, true) ? 'teacher' : 'student';
        $threads[] = [
            'user_id' => $peerId,
            'display_name' => classroom_display_name($pdo, $peerId, $peerRole),
            'identity' => classroom_identity($peerId),
        ];
    }
    classroom_json([
        'ok' => true,
        'messages' => $messages,
        'host_user_id' => $hostUserId,
        'host_identity' => $hostUserId > 0 ? classroom_identity($hostUserId) : '',
        'threads' => $threads,
        'private_open' => $threads !== [],
    ]);
}

classroom_require_post();

$input = classroom_read_json_body();
$body = trim((string)($input['body'] ?? ''));
$body = preg_replace('/\s+/u', ' ', $body) ?? $body;
if ($body === '' || mb_strlen($body) > 500) {
    classroom_json(['ok' => false, 'error' => 'Type a short message.'], 422);
}

classroom_rate_limit('chat:' . $userId, 20, 60);

$announce = !empty($input['announcement']) && $isHost;
$wantPrivate = !$announce && (!empty($input['private']) || (int)($input['recipient_user_id'] ?? 0) > 0);
$recipientId = (int)($input['recipient_user_id'] ?? 0);
$isPrivate = 0;

if ($wantPrivate) {
    if ($isHost) {
        if ($recipientId < 1 || $recipientId === $userId) {
            classroom_json(['ok' => false, 'error' => 'Choose a student to message privately.'], 422);
        }
        if (in_array($recipientId, $hostIds, true)) {
            classroom_json(['ok' => false, 'error' => 'Private chat is for students.'], 422);
        }
        $classId = (int)($lesson['class_id'] ?? 0);
        if (!classroom_chat_recipient_is_student($pdo, (int)$meeting['id'], $classId, $recipientId)) {
            classroom_json(['ok' => false, 'error' => 'That student is not in this class.'], 403);
        }
        $isPrivate = 1;
    } else {
        if ($recipientId < 1) {
            $recipientId = $hostUserId;
        }
        if ($recipientId < 1 || !in_array($recipientId, $hostIds, true)) {
            classroom_json(['ok' => false, 'error' => 'You can only message the teacher privately.'], 403);
        }
        if ($recipientId === $userId) {
            classroom_json(['ok' => false, 'error' => 'You can only message the teacher privately.'], 403);
        }
        $isPrivate = 1;
    }
} elseif (!$isHost && $recipientId > 0) {
    classroom_json(['ok' => false, 'error' => 'You can only message the teacher privately.'], 403);
}

$name = classroom_display_name($pdo, $userId, $isHost ? 'teacher' : current_role());
$ins = $pdo->prepare("
    INSERT INTO meeting_chat_messages
        (meeting_id, user_id, display_name, body, is_announcement, is_private, recipient_user_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$ins->execute([
    (int)$meeting['id'],
    $userId,
    $name,
    $body,
    $announce ? 1 : 0,
    $isPrivate,
    $isPrivate === 1 ? $recipientId : null,
]);

classroom_json([
    'ok' => true,
    'message' => [
        'id' => (int)$pdo->lastInsertId(),
        'user_id' => $userId,
        'display_name' => $name,
        'body' => $body,
        'is_announcement' => $announce ? 1 : 0,
        'is_private' => $isPrivate,
        'recipient_user_id' => $isPrivate === 1 ? $recipientId : null,
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);
