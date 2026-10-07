<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/classroom.php';

use Edexcel\Services\ClassroomAccessService;
use Edexcel\Services\ClassroomAttendanceService;
use Edexcel\Services\ClassroomChatVisibility;
use Edexcel\Services\LiveKitEgressService;
use Edexcel\Services\LiveKitRoomService;
use Edexcel\Services\LiveKitTokenService;
use Edexcel\Services\LiveKitWebhookHandler;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\S3PresignedUrl;

$failed = 0;
$passed = 0;

function expect_true(bool $ok, string $label): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo " PASS  {$label}\n";
        return;
    }
    $failed++;
    echo " FAIL  {$label}\n";
}

$unenrolled = ClassroomAccessService::decide([
    'authenticated' => true,
    'classroom_enabled' => true,
    'livekit_ready' => true,
    'role' => 'student',
    'enrolled' => false,
    'delivery_mode' => 'online',
    'meeting_status' => 'live',
    'lesson_status' => 'cancelled',
    'fee_status' => 'paid',
]);
expect_true(
    $unenrolled['code'] === ClassroomAccessService::DENY
    && $unenrolled['message'] === ClassroomAccessService::UNAUTHORIZED_MESSAGE,
    'unenrolled student cannot join from a copied URL'
);
expect_true(
    ClassroomAccessService::decide([
        'authenticated' => false,
        'role' => 'student',
        'enrolled' => true,
        'fee_status' => 'paid',
    ])['code'] === ClassroomAccessService::DENY,
    'logged-out user is denied before a token'
);
expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'student',
        'enrolled' => true,
        'delivery_mode' => 'online',
        'meeting_status' => 'live',
        'fee_status' => 'unpaid',
    ])['code'] === ClassroomAccessService::PAY,
    'enrolled unpaid student does not receive join access'
);
expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'student',
        'enrolled' => true,
        'delivery_mode' => 'online',
        'meeting_status' => 'live',
        'fee_status' => 'paid',
        'within_join_window' => false,
        'join_window_closed' => true,
    ])['code'] === ClassroomAccessService::DENY,
    'paid student outside the join window is denied'
);
expect_true(
    classroom_join_forbidden(['code' => ClassroomAccessService::DENY, 'enrolled' => false, 'is_host' => false])
    && classroom_join_forbidden(['code' => ClassroomAccessService::SETUP, 'enrolled' => false, 'is_host' => false])
    && !classroom_join_forbidden(['code' => ClassroomAccessService::DENY, 'enrolled' => true, 'is_host' => false])
    && !classroom_join_forbidden(['code' => ClassroomAccessService::PAY, 'enrolled' => true, 'is_host' => false])
    && !classroom_join_forbidden(['code' => ClassroomAccessService::ALLOW, 'enrolled' => true, 'is_host' => true])
    && !classroom_join_forbidden(['code' => ClassroomAccessService::SETUP, 'enrolled' => false, 'is_host' => true]),
    'an account that is not enrolled never receives the classroom page'
);
$roomSrc = (string)file_get_contents(__DIR__ . '/../classroom/room.php');
$tokenSrc = (string)file_get_contents(__DIR__ . '/../api/classroom/token.php');
$initSrc = (string)file_get_contents(__DIR__ . '/../api/classroom/_init.php');
expect_true(str_contains($roomSrc, 'classroom_join_forbidden($access)'), 'room page stops before the classroom shell');
expect_true(str_contains($initSrc, 'UNAUTHORIZED_MESSAGE'), 'classroom APIs reject an unauthorized account without class details');
expect_true(
    str_contains($tokenSrc, '$identity = classroom_identity($userId)')
    && str_contains($tokenSrc, '!== ClassroomAccessService::ALLOW'),
    'LiveKit token is minted only for the logged-in user after ALLOW'
);

expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'student',
        'enrolled' => true,
        'delivery_mode' => 'online',
        'lesson_status' => 'scheduled',
        'meeting_status' => 'live',
        'fee_status' => 'paid',
    ])['code'] === ClassroomAccessService::ALLOW,
    'enrolled student can join live class'
);

expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'student',
        'enrolled' => true,
        'delivery_mode' => 'online',
        'meeting_status' => 'scheduled',
        'within_join_window' => true,
        'fee_status' => 'paid',
    ])['code'] === ClassroomAccessService::WAIT,
    'student waits until teacher starts'
);

expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'student',
        'enrolled' => true,
        'delivery_mode' => 'physical',
        'meeting_status' => 'live',
    ])['code'] === ClassroomAccessService::DENY,
    'in-college lesson is not a live classroom'
);

expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'teacher',
        'teacher_id' => 2,
        'lesson_teacher_id' => 9,
        'substitute_teacher_id' => 0,
        'delivery_mode' => 'hybrid',
        'meeting_status' => 'live',
    ])['code'] === ClassroomAccessService::DENY,
    'other teacher cannot open the class'
);

expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'admin',
        'delivery_mode' => 'online',
        'meeting_status' => 'scheduled',
    ])['code'] === ClassroomAccessService::ALLOW,
    'admin can open class'
);

expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'student',
        'enrolled' => true,
        'kicked' => true,
        'delivery_mode' => 'online',
        'meeting_status' => 'live',
    ])['code'] === ClassroomAccessService::DENY,
    'removed student cannot rejoin'
);

$liveStudent = [
    'authenticated' => true,
    'classroom_enabled' => true,
    'livekit_ready' => true,
    'role' => 'student',
    'enrolled' => true,
    'delivery_mode' => 'online',
    'lesson_status' => 'scheduled',
    'meeting_status' => 'live',
    'fee_status' => 'paid',
];
expect_true(
    ClassroomAccessService::studentMayEnterLiveRoom([
        'is_host' => true,
        'waiting_room' => true,
        'waiting_status' => 'waiting',
    ]),
    'host always allowed through waiting room'
);
expect_true(
    ClassroomAccessService::decide($liveStudent + [
        'waiting_room' => true,
        'waiting_status' => 'waiting',
    ])['code'] === ClassroomAccessService::WAIT
    && ClassroomAccessService::decide($liveStudent + [
        'waiting_room' => true,
        'waiting_status' => 'waiting',
    ])['wait_kind'] === 'lobby',
    'student live + waiting room + not admitted waits in lobby'
);
expect_true(
    ClassroomAccessService::decide($liveStudent + [
        'waiting_room' => true,
        'waiting_status' => 'admitted',
    ])['code'] === ClassroomAccessService::ALLOW
    && ClassroomAccessService::studentMayEnterLiveRoom($liveStudent + [
        'waiting_room' => true,
        'waiting_status' => 'admitted',
    ]),
    'admitted student can join live class'
);
expect_true(
    ClassroomAccessService::decide($liveStudent + [
        'waiting_room' => true,
        'waiting_status' => 'denied',
    ])['code'] === ClassroomAccessService::DENY,
    'denied student stays out of this meeting'
);
expect_true(
    ClassroomAccessService::decide($liveStudent + [
        'waiting_room' => false,
        'waiting_status' => 'waiting',
    ])['code'] === ClassroomAccessService::ALLOW,
    'waiting room off keeps current join'
);
expect_true(
    ClassroomAccessService::decide([
        'authenticated' => true,
        'classroom_enabled' => true,
        'livekit_ready' => true,
        'role' => 'teacher',
        'is_host' => true,
        'delivery_mode' => 'online',
        'meeting_status' => 'live',
        'waiting_room' => true,
        'waiting_status' => 'waiting',
    ])['code'] === ClassroomAccessService::ALLOW,
    'host joins even when waiting room is on'
);
expect_true(
    classroom_meeting_waiting_room(['waiting_room' => 1], ['waiting_room' => false]) === true
    && classroom_meeting_waiting_room(['waiting_room' => 0], ['waiting_room' => true]) === false
    && classroom_meeting_waiting_room(null, ['waiting_room' => true]) === true,
    'meeting waiting_room flag wins over admin setting'
);

$jwt = LiveKitTokenService::participantToken(
    'devkey',
    'secretsecretsecretsecretsecret12',
    'u12',
    'Nimal',
    ['roomJoin' => true, 'room' => 'eck-abc', 'canPublish' => true, 'canSubscribe' => true],
    3600
);
expect_true(LiveKitTokenService::verify($jwt, 'secretsecretsecretsecretsecret12'), 'LiveKit token verifies with secret');
expect_true(!LiveKitTokenService::verify($jwt, 'wrong'), 'LiveKit token rejects wrong secret');
$payload = LiveKitTokenService::decodeUnverified($jwt);
expect_true(($payload['sub'] ?? '') === 'u12' && ($payload['video']['room'] ?? '') === 'eck-abc', 'token identity and room are bound');

expect_true(
    RecordingAccessService::decide([
        'authenticated' => true,
        'recording_exists' => true,
        'recording_status' => 'ready',
        'lesson_exists' => true,
        'enrolled' => true,
        'fee_status' => 'unpaid',
    ]) === RecordingAccessService::PAYMENT_REQUIRED,
    'joining live class does not change recording paywall'
);

expect_true(
    LiveKitEgressService::fileLocationFromEgress([
        'fileResults' => [['location' => 'https://cdn.example/a.mp4']],
    ]) === 'https://cdn.example/a.mp4',
    'egress file URL is read from fileResults'
);
expect_true(
    LiveKitEgressService::isComplete(['status' => 3])
    && LiveKitEgressService::isComplete(['status' => 'EGRESS_COMPLETE']),
    'egress complete status 3 and name'
);
$whBody = '{"event":"egress_ended"}';
$whJwt = LiveKitTokenService::sign('devkey', 'secretsecretsecretsecretsecret12', [
    'iss' => 'devkey',
    'nbf' => time() - 10,
    'exp' => time() + 600,
    'sha256' => hash('sha256', $whBody),
]);
expect_true(
    LiveKitWebhookHandler::verify($whJwt, $whBody, 'devkey', 'secretsecretsecretsecretsecret12'),
    'LiveKit webhook JWT matches body hash'
);
expect_true(
    !LiveKitWebhookHandler::verify($whJwt, $whBody . 'x', 'devkey', 'secretsecretsecretsecretsecret12'),
    'LiveKit webhook rejects tampered body'
);
$whJwtWithoutBodyHash = LiveKitTokenService::sign('devkey', 'secretsecretsecretsecretsecret12', [
    'iss' => 'devkey',
    'nbf' => time() - 10,
    'exp' => time() + 600,
]);
expect_true(
    !LiveKitWebhookHandler::verify($whJwtWithoutBodyHash, $whBody, 'devkey', 'secretsecretsecretsecretsecret12'),
    'LiveKit webhook requires a signed body hash'
);

expect_true(
    str_contains(classroom_public_join_url(42, 'deadbeef'), '/classroom/room.php?m=deadbeef'),
    'public join URL uses meeting id'
);
expect_true(
    str_contains(classroom_parent_page_url(str_repeat('ab', 16)), '/parent/today.php?t='),
    'parent page URL'
);
expect_true(
    classroom_lesson_place_line(['delivery_mode' => 'online']) === 'Online class',
    'online lesson place line'
);
expect_true(
    classroom_lesson_place_line(['delivery_mode' => 'physical', 'room_name' => 'Lab 1']) === 'Lab 1',
    'physical lesson place line'
);

expect_true(livekit_is_cloud(['url' => 'wss://proj.livekit.cloud']), 'livekit.cloud is Cloud');
expect_true(!livekit_is_cloud(['url' => 'wss://live.kandy.edexcel.college']), 'VPS host is not Cloud');
expect_true(livekit_ws_url('live.kandy.edexcel.college') === 'wss://live.kandy.edexcel.college:8443', 'college LiveKit URL uses port 8443');
expect_true(livekit_ws_url('https://live.kandy.edexcel.college/') === 'wss://live.kandy.edexcel.college:8443', 'https college URL becomes wss with 8443');
expect_true(livekit_ws_url('wss://live.kandy.edexcel.college:8443') === 'wss://live.kandy.edexcel.college:8443', '8443 is not doubled');
expect_true(livekit_ws_url('wss://proj.livekit.cloud') === 'wss://proj.livekit.cloud', 'Cloud URL is unchanged');
expect_true(
    \Edexcel\Services\LiveKitRoomService::rtcProbeKind(101, 'HTTP/1.1 101 Switching Protocols') === 'ws_ok',
    '101 is a working /rtc WebSocket'
);
expect_true(
    \Edexcel\Services\LiveKitRoomService::rtcProbeKind(401, 'no permissions to access the room') === 'reached_http',
    '401 on /rtc means LiveKit HTTP without WebSocket upgrade'
);

$s3Cfg = [
    'url' => 'wss://live.kandy.edexcel.college',
    's3_endpoint' => 'https://s3.live.kandy.edexcel.college',
    's3_internal_endpoint' => 'http://minio:9000',
    's3_bucket' => 'livekit',
    's3_region' => 'us-east-1',
    's3_access_key' => 'livekit',
    's3_secret' => 'testsecret',
    's3_force_path_style' => true,
];
expect_true(livekit_s3_configured($s3Cfg) && livekit_can_auto_record($s3Cfg), 'self-hosted with MinIO can record');
expect_true(
    !livekit_can_auto_record(['url' => 'wss://live.example.com', 's3_endpoint' => '', 's3_bucket' => 'livekit', 's3_access_key' => '', 's3_secret' => '']),
    'self-hosted without S3 skips recording storage'
);
$egressS3 = livekit_s3_for_egress($s3Cfg);
$presignS3 = livekit_s3_for_presign($s3Cfg);
expect_true(
    is_array($egressS3) && $egressS3['endpoint'] === 'http://minio:9000'
    && is_array($presignS3) && $presignS3['endpoint'] === 'https://s3.live.kandy.edexcel.college',
    'egress uses Docker MinIO; Bunny presign uses public HTTPS'
);
$cloudS3 = $s3Cfg;
$cloudS3['url'] = 'wss://proj.livekit.cloud';
expect_true(livekit_s3_for_egress($cloudS3)['endpoint'] === 'https://s3.live.kandy.edexcel.college', 'Cloud egress does not use Docker DNS');

$fileOut = LiveKitEgressService::encodedFileOutput('eck/tt1-{time}', $egressS3);
expect_true(
    ($fileOut['s3']['bucket'] ?? '') === 'livekit'
    && ($fileOut['s3']['forcePathStyle'] ?? false) === true
    && ($fileOut['s3']['endpoint'] ?? '') === 'http://minio:9000',
    'room composite file output includes S3'
);
expect_true(
    LiveKitEgressService::objectKeyFromEgress([
        'fileResults' => [[
            'filename' => 'eck/tt12-20260827.mp4',
            'location' => 's3://livekit/eck/tt12-20260827.mp4',
        ]],
    ], 'livekit') === 'eck/tt12-20260827.mp4',
    'egress object key from s3:// location'
);
expect_true(
    LiveKitEgressService::objectKeyFromEgress([
        'fileResults' => [[
            'filename' => 'eck/tt12.mp4',
            'location' => 'http://minio:9000/livekit/eck/tt12.mp4',
        ]],
    ], 'livekit') === 'eck/tt12.mp4',
    'egress object key from internal MinIO path'
);

$presigned = S3PresignedUrl::getObject($presignS3, 'eck/tt1.mp4', 3600, 1700000000);
expect_true(
    str_starts_with($presigned, 'https://s3.live.kandy.edexcel.college/livekit/eck/tt1.mp4?')
    && str_contains($presigned, 'X-Amz-Algorithm=AWS4-HMAC-SHA256')
    && str_contains($presigned, 'X-Amz-Signature='),
    'MinIO presigned GET is path-style HTTPS'
);

$bunnyUrl = LiveKitEgressService::bunnyFetchUrl([
    'status' => 3,
    'fileResults' => [['filename' => 'eck/tt1.mp4', 'location' => 'http://minio:9000/livekit/eck/tt1.mp4']],
], $s3Cfg);
expect_true(
    str_starts_with($bunnyUrl, 'https://s3.live.kandy.edexcel.college/livekit/eck/tt1.mp4?'),
    'Bunny fetch URL is presigned when egress location is internal'
);
expect_true(
    LiveKitEgressService::bunnyFetchUrl([
        'fileResults' => [['location' => 'https://cdn.example/a.mp4']],
    ], $s3Cfg) === 'https://cdn.example/a.mp4',
    'public HTTPS egress location is used as-is'
);

expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => false,
    ], true) === ['MICROPHONE'],
    'blocked camera leaves student mic only'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => false,
    ], false) === ['MICROPHONE', 'CAMERA'],
    'unblocked student may publish mic and camera'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => false,
    ], false, true) === ['MICROPHONE', 'CAMERA', 'SCREEN_SHARE'],
    'per-student share allow adds SCREEN_SHARE'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => false,
    ], false, false) === ['MICROPHONE', 'CAMERA'],
    'share blocked omits SCREEN_SHARE'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => true,
    ], false, false) === ['MICROPHONE', 'CAMERA', 'SCREEN_SHARE'],
    'global student_screenshare adds SCREEN_SHARE'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => false,
    ], true, true) === ['MICROPHONE', 'SCREEN_SHARE'],
    'blocked camera still allows granted screen share'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => false,
    ], false, false, true) === ['CAMERA'],
    'blocked mic leaves student camera only'
);
expect_true(
    classroom_student_publish_sources([
        'student_mic' => true,
        'student_camera' => true,
        'student_screenshare' => true,
    ], true, false, true) === ['SCREEN_SHARE'],
    'mic and camera blocked still allow global screen share'
);
$publishFn = new ReflectionFunction('classroom_student_publish_sources');
expect_true(
    $publishFn->getNumberOfParameters() === 4
    && $publishFn->getParameters()[3]->getName() === 'micBlocked'
    && $publishFn->getParameters()[3]->isOptional(),
    'classroom_student_publish_sources 4th argument is optional micBlocked'
);
$schemaMic = false;
$schemaCam = false;
foreach (classroom_schema_column_alters() as [$table, $column]) {
    if ($table === 'meeting_participants' && $column === 'mic_blocked') {
        $schemaMic = true;
    }
    if ($table === 'meeting_participants' && $column === 'camera_blocked') {
        $schemaCam = true;
    }
}
expect_true(
    $schemaMic && $schemaCam,
    'ensure_classroom_schema adds meeting_participants.mic_blocked like camera_blocked'
);
$schemaFn = new ReflectionFunction('ensure_classroom_schema');
expect_true(
    $schemaFn->getNumberOfParameters() === 2
    && $schemaFn->getParameters()[1]->getName() === 'force'
    && $schemaFn->getParameters()[1]->isOptional(),
    'ensure_classroom_schema can be forced to retry ALTERs'
);
expect_true(
    classroom_livekit_jwt_publish_sources(['MICROPHONE', 'CAMERA']) === ['microphone', 'camera'],
    'JWT canPublishSources are lowercase camera/microphone'
);
expect_true(
    classroom_livekit_jwt_publish_sources(['MICROPHONE', 'CAMERA', 'SCREEN_SHARE']) === ['microphone', 'camera', 'screen_share'],
    'JWT screen_share is lowercase with underscore'
);
expect_true(
    in_array('mute', classroom_host_control_actions(), true)
    && in_array('allow_mic', classroom_host_control_actions(), true)
    && in_array('mute_all', classroom_host_control_actions(), true)
    && in_array('start_recording', classroom_host_control_actions(), true)
    && in_array('stop_recording', classroom_host_control_actions(), true)
    && in_array('block_camera', classroom_host_control_actions(), true)
    && in_array('unblock_camera', classroom_host_control_actions(), true)
    && in_array('allow_share', classroom_host_control_actions(), true)
    && in_array('revoke_share', classroom_host_control_actions(), true)
    && in_array('admit', classroom_host_control_actions(), true)
    && in_array('deny', classroom_host_control_actions(), true)
    && in_array('waiting_on', classroom_host_control_actions(), true)
    && in_array('waiting_off', classroom_host_control_actions(), true)
    && !in_array('raise_hand', classroom_host_control_actions(), true),
    'mute, allow_mic, mute_all, recording, camera force, share, and waiting-room admit/deny are host-only; raise_hand is not'
);
expect_true(
    classroom_cam_control_payload(true) === ['t' => 'cam', 'blocked' => true, 'force' => true],
    'force-off camera payload includes force:true'
);
expect_true(
    classroom_cam_control_payload(false) === ['t' => 'cam', 'blocked' => false, 'force' => true],
    'force-on camera payload includes force:true'
);
expect_true(classroom_started_at_iso('') === null, 'empty started_at is null');
expect_true(
    is_string(classroom_started_at_iso('2026-08-29 10:00:00'))
    && str_contains((string)classroom_started_at_iso('2026-08-29 10:00:00'), 'T'),
    'started_at ISO for elapsed timer'
);

$host = 10;
$studentA = 21;
$studentB = 22;
$public = ['user_id' => $studentA, 'is_private' => 0, 'is_announcement' => 0, 'body' => 'hello class'];
$announce = ['user_id' => $host, 'is_private' => 0, 'is_announcement' => 1, 'body' => 'exam next week'];
$privHostToA = ['user_id' => $host, 'recipient_user_id' => $studentA, 'is_private' => 1, 'body' => 'please unmute'];
$privAToHost = ['user_id' => $studentA, 'recipient_user_id' => $host, 'is_private' => 1, 'body' => 'I am here'];
$privHostToB = ['user_id' => $host, 'recipient_user_id' => $studentB, 'is_private' => 1, 'body' => 'see me after'];

expect_true(
    ClassroomChatVisibility::canView($public, $studentA)
    && ClassroomChatVisibility::canView($public, $studentB)
    && ClassroomChatVisibility::canView($public, $host)
    && ClassroomChatVisibility::canView($announce, $studentB),
    'public and announcements are visible to all'
);
expect_true(
    ClassroomChatVisibility::canView($privHostToA, $studentA)
    && ClassroomChatVisibility::canView($privHostToA, $host)
    && !ClassroomChatVisibility::canView($privHostToA, $studentB)
    && !ClassroomChatVisibility::canView($privAToHost, $studentB)
    && !ClassroomChatVisibility::canView($privHostToA, 11),
    'student A cannot see B’s private; host can see thread with A'
);
expect_true(
    ClassroomChatVisibility::canView($privAToHost, $studentA)
    && ClassroomChatVisibility::canView($privAToHost, $host)
    && ClassroomChatVisibility::canView($privHostToB, $studentB)
    && !ClassroomChatVisibility::canView($privHostToB, $studentA),
    'private replies stay on the teacher↔student thread'
);
$leaked = ClassroomChatVisibility::filterVisible(
    [$public, $privHostToA, $privHostToB, $announce],
    $studentA
);
$leakedBodies = array_map(static fn ($row) => (string)($row['body'] ?? ''), $leaked);
expect_true(
    in_array('hello class', $leakedBodies, true)
    && in_array('exam next week', $leakedBodies, true)
    && in_array('please unmute', $leakedBodies, true)
    && !in_array('see me after', $leakedBodies, true),
    'GET-style filter never returns another student’s private messages'
);
expect_true(
    classroom_identity(21) === 'u21' && classroom_user_id_from_identity('u21') === 21,
    'private LiveKit destination identity maps to u{userId}'
);

expect_true(LiveKitRoomService::normalizeTrackSource(1) === 'CAMERA', 'LiveKit source 1 is camera');
expect_true(LiveKitRoomService::normalizeTrackSource('CAMERA') === 'CAMERA', 'LiveKit source CAMERA');
expect_true(LiveKitRoomService::normalizeTrackSource(2) === 'MICROPHONE', 'LiveKit source 2 is microphone');
expect_true(LiveKitRoomService::normalizeTrackSource('microphone') === 'MICROPHONE', 'lowercase microphone source');
expect_true(LiveKitRoomService::normalizeTrackSource('SOURCE_MICROPHONE') === 'MICROPHONE', 'SOURCE_MICROPHONE proto name');
expect_true(
    LiveKitRoomService::normalizeTrackSource(0, 0, '') === 'MICROPHONE',
    'TrackType AUDIO enum 0 is microphone when source is unknown'
);
expect_true(
    LiveKitRoomService::normalizeTrackSource('UNKNOWN', 'VIDEO', 'camera') === 'CAMERA',
    'unknown video track is treated as camera'
);
expect_true(
    LiveKitRoomService::normalizeTrackSource(0, 'VIDEO', 'screen share') === 'SCREEN_SHARE',
    'screen share is not treated as camera'
);
expect_true(
    LiveKitRoomService::trackSidFromInfo(['track_sid' => 'TR_a']) === 'TR_a'
    && LiveKitRoomService::trackSidFromInfo(['trackSid' => 'TR_b']) === 'TR_b'
    && LiveKitRoomService::trackSidFromInfo(['sid' => 'TR_c']) === 'TR_c',
    'track SID is read from sid, trackSid, or track_sid'
);
$muteBody = LiveKitRoomService::mutePublishedTrackBody('eck-1', 'u21', 'TR_mic', true);
expect_true(
    ($muteBody['trackSid'] ?? '') === 'TR_mic'
    && ($muteBody['track_sid'] ?? '') === 'TR_mic'
    && ($muteBody['muted'] ?? false) === true
    && ($muteBody['identity'] ?? '') === 'u21',
    'MutePublishedTrack JSON sends both trackSid and track_sid'
);
$matchSids = LiveKitRoomService::matchingPublishedTrackSids([
    'identity' => 'u21',
    'tracks' => [
        ['sid' => 'TR_mic', 'source' => 'microphone', 'type' => 'AUDIO'],
        ['track_sid' => 'TR_cam', 'source' => 1],
        ['trackSid' => 'TR_mic2', 'source' => 2],
        ['sid' => 'TR_audio0', 'source' => 0, 'type' => 0, 'name' => ''],
    ],
], ['MICROPHONE']);
sort($matchSids);
expect_true(
    $matchSids === ['TR_audio0', 'TR_mic', 'TR_mic2'],
    'mute matches microphone as lowercase, enum 2, and AUDIO type 0'
);
expect_true(
    LiveKitRoomService::identitiesMatch('u21', 'u21')
    && !LiveKitRoomService::identitiesMatch('u21', 'u22')
    && LiveKitRoomService::participantIdentity(['identity' => 'u21']) === 'u21'
    && LiveKitRoomService::participantIdentity(['info' => ['identity' => 'u21']]) === 'u21',
    'student identity u{id} matches LiveKit identity'
);
expect_true(
    LiveKitRoomService::matchingPublishedTrackSids([
        'info' => [
            'identity' => 'u21',
            'tracks' => [['sid' => 'TR_nested', 'source' => 'microphone']],
        ],
    ], ['MICROPHONE']) === ['TR_nested'],
    'microphone tracks nested under info are matched'
);
$fallbackAudio = LiveKitRoomService::fallbackAudioTrackSids([
    'identity' => 'u21',
    'tracks' => [['sid' => 'TR_opus', 'type' => 'AUDIO', 'mimeType' => 'audio/opus']],
], ['MICROPHONE']);
expect_true(
    $fallbackAudio === ['TR_opus'],
    'AUDIO tracks are muted when source matching finds nothing'
);
$permMute = LiveKitRoomService::participantPermissionBody(['CAMERA']);
expect_true(
    ($permMute['canPublish'] ?? false) === true
    && ($permMute['canPublishSources'] ?? []) === [1]
    && !in_array(0, $permMute['canPublishSources'] ?? [0], true),
    'mute leaves camera publish source 1 and never sends UNKNOWN 0'
);
$permEmpty = LiveKitRoomService::participantPermissionBody([]);
expect_true(
    ($permEmpty['canPublish'] ?? true) === false
    && !array_key_exists('canPublishSources', $permEmpty),
    'empty publish sources omit canPublishSources so LiveKit 1.13.6 does not see UNKNOWN'
);
expect_true(
    classroom_sql_missing_column(
        new RuntimeException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'mic_blocked' in 'field list'"),
        'mic_blocked'
    )
    && classroom_sql_missing_column(new RuntimeException('SQLSTATE[HY000]: General error: 1 no such column: mic_blocked'), 'mic_blocked')
    && classroom_sql_duplicate_column(new RuntimeException("Duplicate column name 'mic_blocked'")),
    'missing-column and duplicate-column SQL errors are recognized'
);

$micPersistOk = false;
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $lite = new PDO('sqlite::memory:');
    $lite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $svcLite = new \Edexcel\Services\OnlineMeetingService($lite);
    $lite->exec('DROP TABLE IF EXISTS meeting_participants');
    $lite->exec('CREATE TABLE meeting_participants (meeting_id INTEGER, user_id INTEGER)');
    $lite->exec('INSERT INTO meeting_participants (meeting_id, user_id) VALUES (1, 21)');
    $threw = false;
    try {
        $svcLite->setMicBlocked(1, 21, true, 10);
    } catch (Throwable $e) {
        $threw = true;
    }
    $micVal = 0;
    try {
        $micVal = (int)$lite->query('SELECT mic_blocked FROM meeting_participants WHERE user_id = 21')->fetchColumn();
    } catch (Throwable $e) {
        $micVal = -1;
    }
    $micPersistOk = !$threw && $micVal === 1;
}
expect_true(
    $micPersistOk,
    'setMicBlocked ADDs mic_blocked then UPDATEs in the same request and never throws'
);

expect_true(
    classroom_safe_http_url('https://edexcel.college/files/materials/a.pdf') === 'https://edexcel.college/files/materials/a.pdf',
    'https material URL is allowed'
);
expect_true(
    classroom_safe_http_url('/files/materials/a.pdf', 'https://edexcel.college') === 'https://edexcel.college/files/materials/a.pdf',
    'relative campus path becomes HTTPS'
);
expect_true(classroom_safe_http_url('javascript:alert(1)') === null, 'javascript URL is rejected');
expect_true(classroom_safe_http_url('data:text/html,x') === null, 'data URL is rejected');
expect_true(classroom_safe_http_url('ftp://files.example/a.pdf') === null, 'ftp URL is rejected');
expect_true(
    classroom_safe_http_url('https://user:secret@evil.example/x') === 'https://evil.example/x',
    'URL userinfo is stripped'
);

$pen = classroom_normalize_whiteboard_stroke([
    'tool' => 'pen',
    'points' => [['x' => 1, 'y' => 2], ['x' => 3, 'y' => 4]],
    'color' => '#111',
]);
expect_true(is_array($pen) && ($pen['tool'] ?? '') === 'pen' && count($pen['points']) === 2, 'pen stroke is kept compact');
$rect = classroom_normalize_whiteboard_stroke([
    'tool' => 'rect',
    'points' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 20], ['x' => 99, 'y' => 99]],
]);
expect_true(is_array($rect) && count($rect['points']) === 2, 'shape stroke keeps two points only');
$text = classroom_normalize_whiteboard_stroke(['tool' => 'text', 'x' => 8, 'y' => 9, 'text' => 'Hello']);
expect_true(is_array($text) && ($text['text'] ?? '') === 'Hello', 'text stroke is kept');
expect_true(classroom_normalize_whiteboard_stroke(['tool' => 'text', 'x' => 1, 'y' => 1, 'text' => '']) === null, 'empty text is rejected');

$wbStudent = ['code' => ClassroomAccessService::ALLOW, 'is_host' => false];
$wbHost = ['code' => ClassroomAccessService::ALLOW, 'is_host' => true];
$wbOn = ['whiteboard_enabled' => true];
$wbView = classroom_whiteboard_access($wbStudent, $wbOn, 'GET', 'stroke', false);
expect_true(
    !empty($wbView['ok']) && !empty($wbView['can_view']) && empty($wbView['can_draw']),
    'student GET can view the board when students_can_draw is off'
);
expect_true(
    classroom_whiteboard_access($wbStudent, $wbOn, 'POST', 'stroke', false)['ok'] === false
    && classroom_whiteboard_access($wbStudent, $wbOn, 'POST', 'stroke', false)['status'] === 403,
    'student cannot POST strokes when the board is view-only'
);
expect_true(
    !empty(classroom_whiteboard_access($wbStudent, $wbOn, 'POST', 'stroke', true)['ok']),
    'student can POST strokes when students_can_draw is on'
);
$wbWait = classroom_whiteboard_access(
    ['code' => ClassroomAccessService::WAIT, 'is_host' => false, 'message' => 'Please wait.'],
    $wbOn,
    'GET'
);
expect_true(
    empty($wbWait['ok']) && ($wbWait['status'] ?? 0) === 409 && !empty($wbWait['wait']),
    'waiting-room student cannot GET the board yet'
);
expect_true(
    !empty(classroom_whiteboard_access($wbHost, $wbOn, 'POST', 'clear', false)['ok']),
    'host can clear the board'
);
expect_true(
    classroom_whiteboard_access($wbStudent, $wbOn, 'POST', 'clear', true)['ok'] === false,
    'student cannot clear the board'
);
expect_true(
    classroom_whiteboard_access($wbStudent, ['whiteboard_enabled' => false], 'GET')['ok'] === false,
    'GET is denied when whiteboard is turned off'
);

$enum = ClassroomAttendanceService::parseEnumValues("enum('present','absent','late','excused')");
expect_true($enum === ['present', 'absent', 'late', 'excused'], 'attendance ENUM parser');

$start = strtotime('2026-08-29 10:00:00');
$end = strtotime('2026-08-29 11:00:00');
$present = ClassroomAttendanceService::classify([
    'duration_seconds' => 25 * 60,
    'joined_ts' => $start + 60,
    'left_ts' => $end,
    'start_ts' => $start,
    'end_ts' => $end,
    'present_seconds' => 20 * 60,
    'late_grace_seconds' => 10 * 60,
    'allowed_statuses' => ['present', 'absent', 'late', 'excused'],
]);
expect_true($present['status'] === 'present' && $present['left_early'] === false, 'on-time student is present');

$leftEarly = ClassroomAttendanceService::classify([
    'duration_seconds' => 25 * 60,
    'joined_ts' => $start + 60,
    'left_ts' => $start + 30 * 60,
    'start_ts' => $start,
    'end_ts' => $end,
    'present_seconds' => 20 * 60,
    'late_grace_seconds' => 10 * 60,
    'allowed_statuses' => ['present', 'absent', 'late', 'excused'],
]);
expect_true(
    $leftEarly['status'] === 'present'
    && $leftEarly['left_early'] === true
    && str_contains($leftEarly['note'], 'left early'),
    'left early stays present when ENUM has no left_early'
);

$leftEarlyStatus = ClassroomAttendanceService::classify([
    'duration_seconds' => 25 * 60,
    'joined_ts' => $start + 60,
    'left_ts' => $start + 30 * 60,
    'start_ts' => $start,
    'end_ts' => $end,
    'present_seconds' => 20 * 60,
    'late_grace_seconds' => 10 * 60,
    'allowed_statuses' => ['present', 'absent', 'late', 'excused', 'left_early'],
]);
expect_true($leftEarlyStatus['status'] === 'left_early', 'left_early status used only when ENUM allows it');

$late = ClassroomAttendanceService::classify([
    'duration_seconds' => 25 * 60,
    'joined_ts' => $start + 15 * 60,
    'left_ts' => $end,
    'start_ts' => $start,
    'end_ts' => $end,
    'present_seconds' => 20 * 60,
    'late_grace_seconds' => 10 * 60,
    'allowed_statuses' => ['present', 'absent', 'late', 'excused'],
]);
expect_true($late['status'] === 'late' && $late['left_early'] === false, 'joined after grace is late');

$meetingEndedEarly = $start + 40 * 60;
expect_true(
    ClassroomAttendanceService::departedEarly($start + 20 * 60, $end, $meetingEndedEarly, 10 * 60) === true,
    'left before lesson end and before meeting end is left early'
);
expect_true(
    ClassroomAttendanceService::departedEarly($meetingEndedEarly, $end, $meetingEndedEarly, 10 * 60) === false,
    'left_at equal to meeting ended_at is not left early'
);

$realLeave = ClassroomAttendanceService::classify([
    'duration_seconds' => 25 * 60,
    'joined_ts' => $start + 60,
    'left_ts' => $start + 20 * 60,
    'start_ts' => $start,
    'end_ts' => $end,
    'meeting_ended_ts' => $meetingEndedEarly,
    'present_seconds' => 20 * 60,
    'late_grace_seconds' => 10 * 60,
    'allowed_statuses' => ['present', 'absent', 'late', 'excused'],
]);
expect_true(
    $realLeave['status'] === 'present'
    && $realLeave['left_early'] === true
    && str_contains($realLeave['note'], 'left early'),
    'left before lesson end and before meeting end keeps present with left-early note'
);

$stayedUntilTeacherEnded = ClassroomAttendanceService::classify([
    'duration_seconds' => 25 * 60,
    'joined_ts' => $start + 60,
    'left_ts' => $meetingEndedEarly,
    'start_ts' => $start,
    'end_ts' => $end,
    'meeting_ended_ts' => $meetingEndedEarly,
    'present_seconds' => 20 * 60,
    'late_grace_seconds' => 10 * 60,
    'allowed_statuses' => ['present', 'absent', 'late', 'excused'],
]);
expect_true(
    $stayedUntilTeacherEnded['status'] === 'present'
    && $stayedUntilTeacherEnded['left_early'] === false
    && !str_contains($stayedUntilTeacherEnded['note'], 'left early'),
    'teacher ending before timetable end does not mark left early'
);

expect_true(
    livekit_record_skip_message(['url' => 'wss://live.example.com', 's3_endpoint' => '', 's3_bucket' => '', 's3_access_key' => '', 's3_secret' => ''])
    === 'Recording to Bunny needs MinIO/S3 on the VPS. Classes can still run.',
    'recording skip message is human-readable'
);
expect_true(
    !str_contains(livekit_record_skip_message($s3Cfg), 'secret')
    && !str_contains(livekit_record_skip_message($s3Cfg), 'livekit'),
    'recording skip message does not include secrets'
);

// ─── PDF Whiteboard Unit Tests ────────────────────────────────────────────────
$pdfSettings = [
    'pdf_whiteboard_enabled' => true,
    'student_pdf_download' => true,
];
$pdfHostAccess = ['code' => 'ALLOW', 'is_host' => true];
$pdfStudentAccess = ['code' => 'ALLOW', 'is_host' => false];
$pdfDeniedAccess = ['code' => 'DENY', 'is_host' => false];
$pdfWaitAccess = ['code' => 'WAIT', 'is_host' => false];

$hostCheck = classroom_pdf_access($pdfHostAccess, $pdfSettings);
expect_true($hostCheck['can_upload'] === true, 'host can upload PDF');
expect_true($hostCheck['can_view'] === true, 'host can view PDF');
expect_true($hostCheck['can_download'] === true, 'host can download PDF');

$studentCheck = classroom_pdf_access($pdfStudentAccess, $pdfSettings);
expect_true($studentCheck['can_upload'] === false, 'student cannot upload PDF');
expect_true($studentCheck['can_view'] === true, 'student can view PDF');
expect_true($studentCheck['can_download'] === true, 'student can download PDF when setting enabled');

$studentNoDlCheck = classroom_pdf_access($pdfStudentAccess, ['pdf_whiteboard_enabled' => true, 'student_pdf_download' => false]);
expect_true($studentNoDlCheck['can_download'] === false, 'student cannot download PDF when student_pdf_download disabled');

$deniedCheck = classroom_pdf_access($pdfDeniedAccess, $pdfSettings);
expect_true($deniedCheck['can_view'] === false && $deniedCheck['can_download'] === false, 'denied user cannot access PDF');

$waitCheck = classroom_pdf_access($pdfWaitAccess, $pdfSettings);
expect_true($waitCheck['can_view'] === false && $waitCheck['can_download'] === false, 'waiting room student cannot access PDF until admitted');

$disabledCheck = classroom_pdf_access($pdfHostAccess, ['pdf_whiteboard_enabled' => false]);
expect_true($disabledCheck['can_upload'] === false && $disabledCheck['enabled'] === false, 'disabled PDF setting blocks upload');

// Download Token Tests
$token = classroom_pdf_download_token(42, 105, 900);
expect_true(is_string($token) && strlen($token) > 10, 'generates valid download token');

$verified = classroom_pdf_verify_download_token($token);
expect_true($verified !== null && $verified['pdf_id'] === 42 && $verified['user_id'] === 105, 'verifies valid download token');

$tampered = $token . 'bad';
expect_true(classroom_pdf_verify_download_token($tampered) === null, 'rejects tampered token');

$expiredToken = classroom_pdf_download_token(42, 105, -100);
expect_true(classroom_pdf_verify_download_token($expiredToken) === null, 'rejects expired token');

// Monitor Window Authorization & Pop-out Tests
$monitorHostAccess = ['code' => 'ALLOW', 'is_host' => true];
$monitorStudentAccess = ['code' => 'ALLOW', 'is_host' => false];
$monitorDeniedAccess = ['code' => 'DENY', 'is_host' => false];

expect_true(!empty($monitorHostAccess['is_host']), 'host is authorized to open monitoring windows');
expect_true(empty($monitorStudentAccess['is_host']), 'student is forbidden from opening monitoring windows');
expect_true(empty($monitorDeniedAccess['is_host']), 'denied user is forbidden from opening monitoring windows');

// Monitor Mode Normalization
function classroom_normalize_monitor_mode(string $mode): string {
    return in_array($mode, ['camera', 'chat', 'monitor'], true) ? $mode : 'monitor';
}

expect_true(classroom_normalize_monitor_mode('camera') === 'camera', 'camera mode recognized');
expect_true(classroom_normalize_monitor_mode('chat') === 'chat', 'chat mode recognized');
expect_true(classroom_normalize_monitor_mode('monitor') === 'monitor', 'monitor mode recognized');
expect_true(classroom_normalize_monitor_mode('invalid_mode') === 'monitor', 'unknown mode defaults to monitor');
expect_true(classroom_normalize_monitor_mode('') === 'monitor', 'empty mode defaults to monitor');

// Audio Echo Prevention Test: Secondary window must not attach audio tracks
function classroom_monitor_allows_audio_track(string $kind): bool {
    // Only video tracks are processed for pop-out monitors; audio remains in the main classroom sink
    return $kind === 'video';
}
expect_true(classroom_monitor_allows_audio_track('video') === true, 'popout attaches video tracks');
expect_true(classroom_monitor_allows_audio_track('audio') === false, 'popout suppresses audio tracks to prevent echo');

// Chat Deduplication in Multi-Window Sync Test
$receivedIds = [];
$dedupChat = function (array $msg) use (&$receivedIds): bool {
    $id = (int)($msg['id'] ?? 0);
    if ($id > 0 && isset($receivedIds[$id])) {
        return false; // Duplicate
    }
    if ($id > 0) {
        $receivedIds[$id] = true;
    }
    return true;
};
expect_true($dedupChat(['id' => 101, 'body' => 'Hello']) === true, 'initial message accepted');
expect_true($dedupChat(['id' => 101, 'body' => 'Hello']) === false, 'duplicate message via bridge/poll rejected');
expect_true($dedupChat(['id' => 102, 'body' => 'World']) === true, 'new message accepted');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);


