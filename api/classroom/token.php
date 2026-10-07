<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomAccessService;
use Edexcel\Services\LiveKitTokenService;
use Edexcel\Services\SecurityEventService;

classroom_require_post();
classroom_rate_limit('token:' . (int)($_SESSION['user_id'] ?? 0), 30, 60);

$loaded = classroom_load_lesson($pdo);
$lesson = $loaded['lesson'];
$access = $loaded['access'];
$svc = $loaded['svc'];

if ($access['code'] === ClassroomAccessService::DENY) {
    classroom_security_denied($pdo, (int)$lesson['id'], (string)$access['code']);
    $denyMessage = !empty($access['is_host'])
        ? (string)$access['message']
        : ClassroomAccessService::UNAUTHORIZED_MESSAGE;
    classroom_json(['ok' => false, 'error' => $denyMessage], 403);
}
if ($access['code'] === ClassroomAccessService::PAY) {
    classroom_security_denied($pdo, (int)$lesson['id'], 'payment');
    classroom_json([
        'ok' => false,
        'error' => $access['message'],
        'payment_required' => true,
        'amount_due' => (float)($access['amount_due'] ?? 0),
    ], 402);
}
if ($access['code'] === ClassroomAccessService::SETUP) {
    classroom_json(['ok' => false, 'error' => $access['message'], 'setup' => true], 503);
}
if ($access['code'] === ClassroomAccessService::WAIT) {
    $settings = classroom_settings($pdo);
    classroom_register_waiting_if_needed($loaded, $settings, (int)($_SESSION['user_id'] ?? 0));
    classroom_json([
        'ok' => false,
        'wait' => true,
        'wait_kind' => $access['wait_kind'] ?? null,
        'waiting_room' => classroom_meeting_waiting_room($loaded['meeting'], $settings),
        'error' => $access['message'],
        'status' => classroom_display_state($lesson, $loaded['meeting']),
    ], 409);
}
if (($access['code'] ?? '') !== ClassroomAccessService::ALLOW || classroom_join_forbidden($access)) {
    classroom_security_denied($pdo, (int)$lesson['id'], (string)($access['code'] ?? 'deny'));
    classroom_json(['ok' => false, 'error' => ClassroomAccessService::UNAUTHORIZED_MESSAGE], 403);
}

$meeting = $loaded['meeting'] ?? $svc->ensureForLesson((int)$lesson['id']);
if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'This lesson is not an online class.'], 400);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = current_role();
$hostRole = $access['is_host'] ? 'teacher' : $role;
$settings = classroom_settings($pdo);
$cfg = livekit_config($pdo);
$isStaff = $access['is_host'] || $role === 'admin';

$cameraBlocked = false;
$shareGranted = false;
$micBlocked = false;
if (!$isStaff) {
    $cameraBlocked = $svc->isCameraBlocked((int)$meeting['id'], $userId);
    $shareGranted = $svc->isScreenshareAllowed((int)$meeting['id'], $userId);
    $micBlocked = $svc->isMicBlocked((int)$meeting['id'], $userId);
}

$studentSources = $isStaff
    ? []
    : classroom_student_publish_sources($settings, $cameraBlocked, $shareGranted, $micBlocked);
$canShare = $isStaff || in_array('SCREEN_SHARE', $studentSources, true);
$canPublish = $isStaff || $studentSources !== [];

$name = classroom_display_name($pdo, $userId, $hostRole === 'teacher' ? 'teacher' : $role);
$identity = classroom_identity($userId);
$body = classroom_read_json_body();
$claimedIdentity = trim((string)($body['identity'] ?? ''));
$claimedUser = (int)($body['user_id'] ?? $body['userId'] ?? 0);
if (($claimedIdentity !== '' && $claimedIdentity !== $identity)
    || ($claimedUser > 0 && $claimedUser !== $userId)) {
    classroom_security_denied($pdo, (int)$lesson['id'], 'identity');
    classroom_json(['ok' => false, 'error' => ClassroomAccessService::UNAUTHORIZED_MESSAGE], 403);
}
$metadata = json_encode([
    'role' => $access['is_host'] ? 'teacher' : $role,
    'userId' => $userId,
], JSON_UNESCAPED_SLASHES);

$grant = [
    'roomJoin' => true,
    'room' => (string)$meeting['livekit_room'],
    'canPublish' => $canPublish,
    'canSubscribe' => true,
    'canPublishData' => !empty($settings['chat_enabled']) || $access['is_host'],
    'canUpdateOwnMetadata' => true,
    'roomAdmin' => $access['is_host'] || $role === 'admin',
    'hidden' => false,
];
// Hosts/admins: omit canPublishSources (LiveKit 1.13.6 UNKNOWN bug).
if (!$isStaff) {
    if ($studentSources !== []) {
        $grant['canPublishSources'] = classroom_livekit_jwt_publish_sources($studentSources);
    } else {
        $grant['canPublish'] = false;
    }
}

$classEnds = strtotime(trim((string)($lesson['date'] ?? '') . ' ' . (string)($lesson['end_time'] ?? ''))) ?: null;
$tokenTtl = SecurityEventService::tokenLifetime((int)$cfg['token_ttl'], $classEnds);
$token = LiveKitTokenService::participantToken(
    $cfg['api_key'],
    $cfg['api_secret'],
    $identity,
    $name,
    $grant,
    $tokenTtl,
    (string)$metadata
);
try {
    (new SecurityEventService($pdo))->record('LIVEKIT_TOKEN_ISSUED', [
        'user_id' => $userId,
        'timetable_id' => (int)$lesson['id'],
        'teacher_id' => (int)($lesson['teacher_id'] ?? 0),
        'result' => 'issued',
        'message' => 'Live class token issued',
        'reference_id' => (int)($meeting['id'] ?? 0),
    ]);
    if (!$isStaff) {
        (new SecurityEventService($pdo))->record('LIVE_CLASS_ACCESS_GRANTED', [
            'user_id' => $userId,
            'timetable_id' => (int)$lesson['id'],
            'teacher_id' => (int)($lesson['teacher_id'] ?? 0),
            'result' => 'granted',
            'reference_id' => (int)($meeting['id'] ?? 0),
        ]);
    }
} catch (Throwable $e) {
}

classroom_json([
    'ok' => true,
    'url' => $cfg['url'],
    'token' => $token,
    'identity' => $identity,
    'room' => $meeting['livekit_room'],
    'meeting_id' => (int)$meeting['id'],
    'lesson_id' => (int)$lesson['id'],
    'name' => $name,
    'role' => $access['is_host'] ? 'teacher' : $role,
    'is_host' => $access['is_host'],
    'settings' => [
        'chat' => !empty($settings['chat_enabled']),
        'whiteboard' => !empty($settings['whiteboard_enabled']),
        'student_camera' => !empty($settings['student_camera']),
        'student_mic' => !empty($settings['student_mic']),
        'student_share' => $canShare,
    ],
    'camera_blocked' => $cameraBlocked,
    'mic_blocked' => $micBlocked,
    'screenshare_allowed' => $canShare,
]);

function classroom_security_denied(PDO $pdo, int $timetableId, string $reason): void
{
    try {
        (new SecurityEventService($pdo))->record('LIVE_CLASS_ACCESS_DENIED', [
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
            'timetable_id' => $timetableId,
            'result' => 'denied',
            'message' => substr($reason, 0, 80),
        ]);
    } catch (Throwable $e) {
    }
}
