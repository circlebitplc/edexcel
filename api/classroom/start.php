<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomAccessService;
use Edexcel\Services\ClassroomLiveRecordingService;

classroom_require_post();

$loaded = classroom_load_lesson($pdo);
$lesson = $loaded['lesson'];
$access = $loaded['access'];
$svc = $loaded['svc'];

if (!$access['is_host']) {
    classroom_json(['ok' => false, 'error' => 'Only the teacher can start this class.'], 403);
}
if ($access['code'] === ClassroomAccessService::SETUP) {
    classroom_json(['ok' => false, 'error' => $access['message'], 'setup' => true], 503);
}
if ($access['code'] === ClassroomAccessService::DENY) {
    classroom_json(['ok' => false, 'error' => $access['message']], 403);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$meeting = $svc->start((int)$lesson['id'], $userId);
$recording = false;
try {
    $settings = classroom_settings($pdo);
    if (!empty($settings['auto_record'])) {
        $pdo->prepare("UPDATE online_meetings SET recording_requested = 1 WHERE id = ?")
            ->execute([(int)$meeting['id']]);
        $meeting['recording_requested'] = 1;
    }
    $bridge = new ClassroomLiveRecordingService($pdo);
    $bridge->startForLiveClass($lesson, $meeting, $userId);
    $meeting = $svc->findByLesson((int)$lesson['id']) ?: $meeting;
    $recording = trim((string)($meeting['egress_id'] ?? '')) !== '';
} catch (Throwable $e) {
    error_log('classroom live recording start: ' . $e->getMessage());
}
$joinUrl = classroom_room_url((int)$lesson['id'], (string)$meeting['public_id']);
try {
    classroom_notify_live($pdo, $lesson, $joinUrl);
} catch (Throwable $e) {
    error_log('classroom notify live: ' . $e->getMessage());
}

classroom_json([
    'ok' => true,
    'status' => 'live',
    'meeting_id' => (int)$meeting['id'],
    'join_url' => $joinUrl,
    'recording' => $recording,
    'message' => $recording
        ? 'Class is live and being recorded. Students can join now.'
        : 'Class is live. Students can join now.',
]);
