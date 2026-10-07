<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\ClassroomAccessService;

$loaded = classroom_load_lesson($pdo);
$lesson = $loaded['lesson'];
$meeting = $loaded['meeting'];
$access = $loaded['access'];
$settings = classroom_settings($pdo);

$state = classroom_display_state($lesson, $meeting, (int)$settings['join_early_minutes']);
$hands = [];
$waiting = [];
$inRoom = 0;
$startedAt = ($state === 'live' && is_array($meeting))
    ? classroom_started_at_iso((string)($meeting['started_at'] ?? ''))
    : null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$waitingOn = classroom_meeting_waiting_room($meeting, $settings);
if ($meeting && !$access['is_host'] && $access['code'] === ClassroomAccessService::WAIT) {
    classroom_register_waiting_if_needed($loaded, $settings, $userId);
}
if ($meeting && ($access['is_host'] || $access['code'] === ClassroomAccessService::ALLOW)) {
    $svc = $loaded['svc'];
    try {
        $hands = $svc->raisedHands((int)$meeting['id']);
        $c = $pdo->prepare("
            SELECT COUNT(*) FROM meeting_participants
            WHERE meeting_id = ? AND left_at IS NULL
              AND COALESCE(waiting_status, 'none') IN ('none', 'admitted')
        ");
        $c->execute([(int)$meeting['id']]);
        $inRoom = (int)$c->fetchColumn();
        if ($access['is_host']) {
            $waiting = $svc->waitingStudents((int)$meeting['id']);
        }
    } catch (Throwable $e) {
    }
}

classroom_json([
    'ok' => true,
    'status' => $state,
    'meeting_status' => $meeting['status'] ?? 'scheduled',
    'locked' => !empty($meeting['locked']),
    'waiting_room' => $waitingOn,
    'waiting' => $waiting,
    'recording' => is_array($meeting) && trim((string)($meeting['egress_id'] ?? '')) !== '',
    'students_can_draw' => is_array($meeting) && !empty($meeting['students_can_draw']),
    'started_at' => $startedAt,
    'startedAt' => $startedAt,
    'code' => $access['code'],
    'wait_kind' => $access['wait_kind'] ?? null,
    'message' => $access['message'],
    'payment_required' => $access['code'] === ClassroomAccessService::PAY,
    'amount_due' => (float)($access['amount_due'] ?? 0),
    'is_host' => $access['is_host'],
    'in_room' => $inRoom,
    'hands' => $hands,
    'subject' => $lesson['subject_name'] ?? '',
    'class_name' => $lesson['class_name'] ?? '',
    'teacher' => $lesson['substitute_name'] ?: ($lesson['teacher_name'] ?? ''),
    'start_time' => $lesson['start_time'] ?? '',
    'end_time' => $lesson['end_time'] ?? '',
    'configured' => livekit_ready($pdo) && !empty($settings['enabled']),
]);
