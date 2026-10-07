<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomLiveRecordingService;

classroom_require_post();
$loaded = classroom_load_lesson($pdo);
$access = $loaded['access'];
$meeting = $loaded['meeting'];
if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'Class is not live.'], 400);
}
classroom_api_require_access($access, [
    \Edexcel\Services\ClassroomAccessService::ALLOW,
    \Edexcel\Services\ClassroomAccessService::WAIT,
]);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($access['code'] === \Edexcel\Services\ClassroomAccessService::WAIT) {
    classroom_register_waiting_if_needed($loaded, classroom_settings($pdo), $userId);
    classroom_json(['ok' => true, 'wait' => true, 'wait_kind' => $access['wait_kind'] ?? null]);
}

$input = classroom_read_json_body();
$action = strtolower(trim((string)($input['action'] ?? 'ping')));
$identity = classroom_identity($userId);
$svc = $loaded['svc'];
$role = $access['is_host'] ? 'teacher' : 'student';

if ($action === 'leave') {
    $svc->leaveParticipant((int)$meeting['id'], $userId);
    classroom_json(['ok' => true]);
}

if (empty($input['connected'])) {
    classroom_json(['ok' => false, 'error' => 'Classroom connection is not active.'], 409);
}

$svc->touchParticipant((int)$meeting['id'], $userId, $role, $identity);
$wantRecord = (int)($meeting['recording_requested'] ?? 0) === 1;
if (
    $access['is_host']
    && ($meeting['status'] ?? '') === 'live'
    && trim((string)($meeting['egress_id'] ?? '')) === ''
    && $wantRecord
) {
    try {
        (new ClassroomLiveRecordingService($pdo))->startForLiveClass($loaded['lesson'], $meeting, $userId, true);
    } catch (Throwable $e) {
        error_log('classroom live recording retry: ' . $e->getMessage());
    }
}
classroom_json(['ok' => true]);
