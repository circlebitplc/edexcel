<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomAccessService;
use Edexcel\Services\ClassroomLiveRecordingService;
use Edexcel\Services\LiveKitRoomService;

classroom_require_post();
$loaded = classroom_load_lesson($pdo);
$access = $loaded['access'];
$meeting = $loaded['meeting'];
$svc = $loaded['svc'];

if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'Class is not live.'], 400);
}
classroom_api_require_access($access);

$input = classroom_read_json_body();
$action = strtolower(trim((string)($input['action'] ?? '')));
$userId = (int)($_SESSION['user_id'] ?? 0);
$targetId = (int)($input['user_id'] ?? 0);
$identity = (string)($input['identity'] ?? '');
if ($identity === '' && $targetId > 0) {
    $identity = classroom_identity($targetId);
}
if ($targetId < 1 && $identity !== '') {
    $targetId = classroom_user_id_from_identity($identity);
}
if ($targetId > 0 && !preg_match('/^u\d+$/i', $identity)) {
    $identity = classroom_identity($targetId);
}

$hostActions = classroom_host_control_actions();
$selfActions = ['raise_hand', 'lower_own_hand'];

if (in_array($action, $hostActions, true) && !$access['is_host'] && current_role() !== 'admin') {
    classroom_json(['ok' => false, 'error' => 'Only the teacher can do that.'], 403);
}
if (in_array($action, $selfActions, true)
    && $access['code'] !== ClassroomAccessService::ALLOW
) {
    classroom_json(['ok' => false, 'error' => $access['message']], 403);
}

$liveOnlyActions = [
    'raise_hand', 'lower_own_hand', 'lower_hand', 'admit', 'deny', 'mute', 'allow_mic',
    'block_camera', 'unblock_camera', 'allow_share', 'revoke_share', 'allow_speak', 'kick',
    'mute_all', 'start_recording', 'stop_recording',
];
if (in_array($action, $liveOnlyActions, true) && ($meeting['status'] ?? '') !== 'live') {
    classroom_json(['ok' => false, 'error' => 'Start the class before using that control.'], 409);
}

$targetActions = [
    'lower_hand', 'admit', 'deny', 'mute', 'allow_mic', 'block_camera', 'unblock_camera',
    'allow_share', 'revoke_share', 'allow_speak', 'kick',
];
if (in_array($action, $targetActions, true)) {
    if ($targetId < 1 || $svc->participantState((int)$meeting['id'], $targetId) === []) {
        classroom_json(['ok' => false, 'error' => 'That participant is not in this class.'], 403);
    }
}

$cfg = livekit_config($pdo);
$roomApi = livekit_ready($pdo) ? LiveKitRoomService::fromConfig($cfg) : null;
$roomName = (string)$meeting['livekit_room'];
$settings = classroom_settings($pdo);

$refuseStaffTarget = static function (int $targetId, int $actorId, array $meeting, $svc, string $what) use ($identity): void {
    $hostUid = (int)($meeting['host_user_id'] ?? 0);
    if ($identity !== '') {
        $actorIdent = classroom_identity($actorId);
        $hostIdent = $hostUid > 0 ? classroom_identity($hostUid) : '';
        if (($actorIdent !== '' && strcasecmp($identity, $actorIdent) === 0)
            || ($hostIdent !== '' && strcasecmp($identity, $hostIdent) === 0)) {
            classroom_json(['ok' => false, 'error' => 'You cannot change the teacher’s ' . $what . '.'], 422);
        }
    }
    if ($targetId > 0 && ($targetId === $actorId || $targetId === $hostUid)) {
        classroom_json(['ok' => false, 'error' => 'You cannot change the teacher’s ' . $what . '.'], 422);
    }
    if ($targetId > 0) {
        $targetRole = strtolower($svc->participantRole((int)$meeting['id'], $targetId));
        if (in_array($targetRole, ['teacher', 'admin'], true)) {
            classroom_json(['ok' => false, 'error' => 'You cannot change the teacher’s ' . $what . '.'], 422);
        }
    }
};

$applyStudentSources = static function (
    LiveKitRoomService $roomApi,
    string $roomName,
    string $identity,
    array $settings,
    bool $cameraBlocked,
    bool $shareGranted,
    bool $micBlocked = false
): void {
    try {
        $roomApi->setParticipantPublishSources(
            $roomName,
            $identity,
            classroom_student_publish_sources($settings, $cameraBlocked, $shareGranted, $micBlocked)
        );
    } catch (Throwable $e) {
        error_log('publish sources: ' . $e->getMessage());
    }
};

$notifyMic = static function (LiveKitRoomService $roomApi, string $roomName, array $identities, bool $blocked): void {
    if ($identities === []) {
        return;
    }
    try {
        $roomApi->sendData($roomName, json_encode([
            't' => 'mic',
            'blocked' => $blocked,
        ], JSON_UNESCAPED_SLASHES), $identities);
    } catch (Throwable $e) {
        error_log('mic control notify: ' . $e->getMessage());
    }
};

$applyMicControl = static function (
    LiveKitRoomService $roomApi,
    string $roomName,
    int $meetingId,
    string $identity,
    int $targetId,
    array $settings,
    $svc,
    int $actorId,
    bool $blocked
) use ($applyStudentSources, $notifyMic): void {
    // 1) MutePublishedTrack first so the class actually goes silent.
    if ($blocked) {
        try {
            $roomApi->muteParticipantAudio($roomName, $identity, true);
        } catch (Throwable $e) {
            error_log('mute audio: ' . $e->getMessage());
        }
    }
    // 2) Persist mic_blocked (never throws). Student cannot unmute after rejoin.
    if ($targetId > 0) {
        try {
            $svc->setMicBlocked($meetingId, $targetId, $blocked, $actorId);
        } catch (Throwable $e) {
            error_log('mic_blocked persist: ' . $e->getMessage());
        }
    } else {
        try {
            $svc->event($meetingId, $actorId, $blocked ? 'mute' : 'allow_mic', [
                'identity' => $identity,
            ]);
        } catch (Throwable $e) {
            error_log('mic event: ' . $e->getMessage());
        }
    }
    // 3) Tell the student client to setMicrophoneEnabled(false).
    $notifyMic($roomApi, $roomName, [$identity], $blocked);
    // 4) UpdateParticipant last. LiveKit 1.13.6 UNKNOWN must not fail HTTP or undo mute.
    $cameraBlocked = $targetId > 0 && $svc->isCameraBlocked($meetingId, $targetId);
    $shareGranted = $targetId > 0 && $svc->isScreenshareAllowed($meetingId, $targetId);
    $applyStudentSources($roomApi, $roomName, $identity, $settings, $cameraBlocked, $shareGranted, $blocked);
};

try {
    switch ($action) {
        case 'raise_hand':
            $svc->setHand((int)$meeting['id'], $userId, true);
            break;
        case 'lower_own_hand':
            $svc->setHand((int)$meeting['id'], $userId, false);
            break;
        case 'lower_hand':
            if ($targetId < 1) {
                classroom_json(['ok' => false, 'error' => 'Choose a student.'], 422);
            }
            $svc->setHand((int)$meeting['id'], $targetId, false);
            break;
        case 'lock':
            $svc->setLocked((int)$meeting['id'], true, $userId);
            break;
        case 'unlock':
            $svc->setLocked((int)$meeting['id'], false, $userId);
            break;
        case 'waiting_on':
            $svc->setWaitingRoom((int)$meeting['id'], true, $userId);
            break;
        case 'waiting_off':
            $svc->setWaitingRoom((int)$meeting['id'], false, $userId);
            break;
        case 'admit':
            if ($targetId < 1) {
                classroom_json(['ok' => false, 'error' => 'Choose a student.'], 422);
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'admission');
            $svc->admit((int)$meeting['id'], $targetId, $userId);
            break;
        case 'deny':
            if ($targetId < 1) {
                classroom_json(['ok' => false, 'error' => 'Choose a student.'], 422);
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'admission');
            $svc->denyWaiting((int)$meeting['id'], $targetId, $userId);
            if ($roomApi && $identity !== '') {
                try {
                    $roomApi->removeParticipant($roomName, $identity);
                } catch (Throwable $e) {
                    error_log('waiting deny remove: ' . $e->getMessage());
                }
            }
            break;
        case 'mute':
        case 'allow_mic':
            if ($identity === '' || !$roomApi) {
                classroom_json(['ok' => false, 'error' => $action === 'allow_mic'
                    ? 'Could not allow that student’s microphone.'
                    : 'Could not mute that student.'], 422);
            }
            if ($targetId < 1 && preg_match('/^u(\d+)$/i', $identity, $m)) {
                $targetId = (int)$m[1];
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'microphone');
            $applyMicControl(
                $roomApi,
                $roomName,
                (int)$meeting['id'],
                $identity,
                $targetId,
                $settings,
                $svc,
                $userId,
                $action === 'mute'
            );
            break;
        case 'block_camera':
        case 'unblock_camera':
            if ($identity === '' || !$roomApi) {
                classroom_json(['ok' => false, 'error' => 'Could not update that student’s camera.'], 422);
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'camera');
            $blocked = $action === 'block_camera';
            $shareGranted = $targetId > 0 && $svc->isScreenshareAllowed((int)$meeting['id'], $targetId);
            $micBlocked = $targetId > 0 && $svc->isMicBlocked((int)$meeting['id'], $targetId);
            if ($blocked) {
                try {
                    $roomApi->mutePublishedSources($roomName, $identity, ['CAMERA'], true);
                } catch (Throwable $e) {
                    error_log('camera mute: ' . $e->getMessage());
                }
            }
            $applyStudentSources($roomApi, $roomName, $identity, $settings, $blocked, $shareGranted, $micBlocked);
            if (!$blocked) {
                try {
                    $roomApi->mutePublishedSources($roomName, $identity, ['CAMERA'], false);
                } catch (Throwable $e) {
                    error_log('camera unmute: ' . $e->getMessage());
                }
            }
            if ($targetId > 0) {
                $svc->setCameraBlocked((int)$meeting['id'], $targetId, $blocked, $userId);
            } else {
                $svc->event((int)$meeting['id'], $userId, $blocked ? 'block_camera' : 'unblock_camera', [
                    'identity' => $identity,
                ]);
            }
            try {
                $roomApi->sendData($roomName, json_encode(
                    classroom_cam_control_payload($blocked, true),
                    JSON_UNESCAPED_SLASHES
                ), [$identity]);
            } catch (Throwable $e) {
                error_log('camera control notify: ' . $e->getMessage());
            }
            break;
        case 'allow_share':
        case 'revoke_share':
            if ($identity === '' || !$roomApi) {
                classroom_json(['ok' => false, 'error' => 'Could not update that student’s screen share.'], 422);
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'screen share');
            $shareGranted = $action === 'allow_share';
            $shareEffective = !empty($settings['student_screenshare']) || $shareGranted;
            $cameraBlocked = $targetId > 0 && $svc->isCameraBlocked((int)$meeting['id'], $targetId);
            $micBlocked = $targetId > 0 && $svc->isMicBlocked((int)$meeting['id'], $targetId);
            if (!$shareEffective) {
                $roomApi->mutePublishedSources($roomName, $identity, ['SCREEN_SHARE', 'SCREEN_SHARE_AUDIO'], true);
            }
            $applyStudentSources($roomApi, $roomName, $identity, $settings, $cameraBlocked, $shareGranted, $micBlocked);
            if ($targetId > 0) {
                $svc->setScreenshareAllowed((int)$meeting['id'], $targetId, $shareGranted, $userId);
            } else {
                $svc->event((int)$meeting['id'], $userId, $shareGranted ? 'allow_share' : 'revoke_share', [
                    'identity' => $identity,
                ]);
            }
            try {
                $roomApi->sendData($roomName, json_encode([
                    't' => 'share',
                    'allowed' => $shareEffective,
                ], JSON_UNESCAPED_SLASHES), [$identity]);
            } catch (Throwable $e) {
                error_log('share control notify: ' . $e->getMessage());
            }
            break;
        case 'allow_speak':
            if ($identity === '' || !$roomApi) {
                classroom_json(['ok' => false, 'error' => 'Could not unmute that student.'], 422);
            }
            if ($targetId > 0) {
                $svc->setHand((int)$meeting['id'], $targetId, false);
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'microphone');
            $roomApi->updatePublishPermission($roomName, $identity, true);
            $svc->event((int)$meeting['id'], $userId, 'allow_speak', ['identity' => $identity]);
            break;
        case 'kick':
            if ($targetId < 1) {
                classroom_json(['ok' => false, 'error' => 'Choose a student.'], 422);
            }
            $refuseStaffTarget($targetId, $userId, $meeting, $svc, 'kick');
            $svc->kick((int)$meeting['id'], $targetId, $userId);
            if ($roomApi && $identity !== '') {
                try {
                    $roomApi->removeParticipant($roomName, $identity);
                } catch (Throwable $e) {
                    error_log('kick remove: ' . $e->getMessage());
                }
            }
            break;
        case 'clear_chat':
            $pdo->prepare("
                UPDATE meeting_chat_messages
                SET deleted_at = NOW()
                WHERE meeting_id = ? AND deleted_at IS NULL AND IFNULL(is_private, 0) = 0
            ")->execute([(int)$meeting['id']]);
            $svc->event((int)$meeting['id'], $userId, 'clear_chat', null);
            break;
        case 'mute_all':
            if (!$roomApi) {
                classroom_json(['ok' => false, 'error' => 'Could not mute the class.'], 422);
            }
            $skip = [classroom_identity($userId)];
            $hostUid = (int)($meeting['host_user_id'] ?? 0);
            if ($hostUid > 0) {
                $skip[] = classroom_identity($hostUid);
            }
            $skip = array_values(array_unique($skip));
            $mutedIds = [];
            foreach ($roomApi->listParticipants($roomName) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $ident = LiveKitRoomService::participantIdentity($p);
                if ($ident === '' || in_array($ident, $skip, true)) {
                    continue;
                }
                $tid = classroom_user_id_from_identity($ident);
                if ($tid > 0 && ($tid === $userId || $tid === $hostUid)) {
                    continue;
                }
                if ($tid > 0) {
                    $targetRole = strtolower($svc->participantRole((int)$meeting['id'], $tid));
                    if (in_array($targetRole, ['teacher', 'admin'], true)) {
                        continue;
                    }
                }
                try {
                    $applyMicControl(
                        $roomApi,
                        $roomName,
                        (int)$meeting['id'],
                        $ident,
                        $tid,
                        $settings,
                        $svc,
                        $userId,
                        true
                    );
                    $mutedIds[] = $ident;
                } catch (Throwable $e) {
                    error_log('mute_all: ' . $e->getMessage());
                }
            }
            $svc->event((int)$meeting['id'], $userId, 'mute_all', ['count' => count($mutedIds)]);
            break;
        case 'start_recording':
        case 'stop_recording':
            if (($meeting['status'] ?? '') !== 'live') {
                classroom_json(['ok' => false, 'error' => 'Start the class before recording.'], 400);
            }
            $bridge = new ClassroomLiveRecordingService($pdo);
            if ($action === 'start_recording') {
                $result = $bridge->startForLiveClass($loaded['lesson'], $meeting, $userId, true);
                $fresh = $svc->findByLesson((int)$loaded['lesson']['id']) ?: $meeting;
                $recording = trim((string)($fresh['egress_id'] ?? '')) !== '';
                classroom_json([
                    'ok' => true,
                    'recording' => $recording,
                    'already' => !empty($result['already']),
                    'skipped' => !empty($result['skipped']) && !$recording,
                    'message' => (string)($result['message'] ?? ''),
                    'hands' => $svc->raisedHands((int)$meeting['id']),
                ]);
            }
            $result = $bridge->stopForMeeting($meeting, true);
            classroom_json([
                'ok' => true,
                'recording' => false,
                'message' => (string)($result['message'] ?? 'Recording stopped.'),
                'hands' => $svc->raisedHands((int)$meeting['id']),
            ]);
            break;
        default:
            classroom_json(['ok' => false, 'error' => 'Unknown action.'], 422);
    }
} catch (Throwable $e) {
    error_log('classroom control: ' . $e->getMessage());
    classroom_json(['ok' => false, 'error' => 'That action could not be completed. Please try again.'], 500);
}

$fresh = $svc->findByLesson((int)$loaded['lesson']['id']) ?: $meeting;
$payload = ['ok' => true, 'hands' => []];
try {
    $payload['hands'] = $svc->raisedHands((int)$meeting['id']);
} catch (Throwable $e) {
    error_log('classroom hands: ' . $e->getMessage());
}
if ($access['is_host'] || current_role() === 'admin') {
    try {
        $payload['waiting'] = $svc->waitingStudents((int)$meeting['id']);
        $payload['waiting_room'] = classroom_meeting_waiting_room($fresh, $settings);
    } catch (Throwable $e) {
        $payload['waiting'] = [];
    }
}
classroom_json($payload);
