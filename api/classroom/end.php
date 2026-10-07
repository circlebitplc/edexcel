<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomAccessService;

classroom_require_post();

$loaded = classroom_load_lesson($pdo);
$lesson = $loaded['lesson'];
$access = $loaded['access'];
$svc = $loaded['svc'];

if (!$access['is_host'] && current_role() !== 'admin') {
    classroom_json(['ok' => false, 'error' => 'Only the teacher can end this class.'], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$meeting = $svc->end((int)$lesson['id'], $userId);
$recording = trim((string)($meeting['egress_id'] ?? '')) !== '' || (int)($meeting['class_recording_id'] ?? 0) > 0;
try {
    classroom_notify_ended($pdo, $lesson);
} catch (Throwable $e) {
    error_log('classroom notify ended: ' . $e->getMessage());
}

classroom_json([
    'ok' => true,
    'status' => 'ended',
    'meeting_id' => (int)($meeting['id'] ?? 0),
    'recording' => $recording,
    'message' => $recording
        ? 'Class ended. The recording will appear under Recordings when Bunny has finished processing. Attendance was saved where students had joined.'
        : 'Class ended. Attendance was saved where students had joined.',
]);
