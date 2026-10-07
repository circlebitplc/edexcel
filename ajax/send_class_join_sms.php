<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';

require_staff();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Use POST.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Security token expired. Refresh and try again.']);
    exit;
}

$timetableId = (int)($_POST['timetable_id'] ?? $_POST['lesson'] ?? 0);
$whatsapp = (string)($_POST['whatsapp'] ?? $_POST['phone'] ?? '');
$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$sentBy = (int)($_SESSION['user_id'] ?? 0);

try {
    ensure_classroom_schema($pdo);
    $lesson = classroom_lesson_for_join_sms($pdo, $timetableId);
    if (!$lesson) {
        throw new RuntimeException('Choose a class first.');
    }
    if (!$isAdmin && !campus_staff_can_mark_lesson($lesson, $isAdmin, $teacherId)) {
        throw new RuntimeException('You can only send join links for your classes.');
    }
    if (!campus_staff_can_access_class($pdo, (int)$lesson['class_id'], $isAdmin, $teacherId)) {
        throw new RuntimeException('You cannot send a join link for this class.');
    }

    $result = classroom_send_lesson_join_sms($pdo, $timetableId, $whatsapp, $sentBy, false);
    $status = !empty($result['ok']) ? 200 : 400;
    http_response_code($status);
    echo json_encode([
        'ok' => !empty($result['ok']),
        'needs_name' => !empty($result['needs_name']),
        'message' => (string)($result['message'] ?? ''),
        'name' => (string)($result['name'] ?? ''),
    ]);
} catch (Throwable $e) {
    error_log('send_class_join_sms: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
