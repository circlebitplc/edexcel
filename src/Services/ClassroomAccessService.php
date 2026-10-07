<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Pure access rules for live classroom tokens. No database.
 *
 * Context keys:
 * authenticated, role (admin|teacher|student), user_id,
 * teacher_id (session), lesson_teacher_id, substitute_teacher_id,
 * enrolled, delivery_mode, lesson_status, meeting_status,
 * locked, kicked, classroom_enabled, livekit_ready,
 * within_join_window, join_window_closed, is_host, waiting_room, waiting_status,
 * has_participant, fee_status (paid|waived|pending|unpaid|partial), payment_pending
 */
final class ClassroomAccessService
{
    public const ALLOW = 'ALLOW';
    public const DENY = 'DENY';
    public const WAIT = 'WAIT';
    public const SETUP = 'SETUP';
    public const PAY = 'PAY';

    /**
     * Hosts/admins always enter. Students need admit when the waiting room is on.
     * Denied for this meeting stays out even if the waiting room is later turned off.
     *
     * @param array<string,mixed> $ctx
     */
    public static function studentMayEnterLiveRoom(array $ctx): bool
    {
        $role = strtolower((string)($ctx['role'] ?? ''));
        if ($role === 'admin' || !empty($ctx['is_host'])) {
            return true;
        }
        $st = strtolower((string)($ctx['waiting_status'] ?? ''));
        if ($st === 'denied') {
            return false;
        }
        if (empty($ctx['waiting_room'])) {
            return true;
        }
        if ($st === 'admitted') {
            return true;
        }
        // Joined this meeting before waiting-room (legacy none), or already in the room.
        if ($st === 'none' && !empty($ctx['has_participant'])) {
            return true;
        }
        return false;
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array{code:string,message:string,wait_kind:?string}
     */
    public static function decide(array $ctx): array
    {
        if (empty($ctx['authenticated'])) {
            return self::result(self::DENY, 'Please sign in to join class.');
        }
        if (empty($ctx['classroom_enabled'])) {
            return self::result(self::SETUP, 'Online classes are turned off right now.');
        }
        if (empty($ctx['livekit_ready'])) {
            return self::result(self::SETUP, 'Online classroom is not connected yet. Ask the administrator.');
        }

        $lessonStatus = strtolower((string)($ctx['lesson_status'] ?? 'scheduled'));
        if ($lessonStatus === 'cancelled') {
            return self::result(self::DENY, 'This class was cancelled.');
        }

        $mode = strtolower((string)($ctx['delivery_mode'] ?? 'physical'));
        if (!in_array($mode, ['online', 'hybrid'], true)) {
            return self::result(self::DENY, 'This lesson is in college, not online.');
        }

        $role = strtolower((string)($ctx['role'] ?? ''));
        $isAdmin = $role === 'admin';
        $isTeacher = $role === 'teacher';
        $isHost = !empty($ctx['is_host']) || $isAdmin
            || ($isTeacher && RecordingAccessService::teacherCanManageLesson(
                (int)($ctx['teacher_id'] ?? 0),
                (int)($ctx['lesson_teacher_id'] ?? 0),
                $isAdmin,
                (int)($ctx['substitute_teacher_id'] ?? 0)
            ));

        if ($role === 'student' && empty($ctx['enrolled']) && !$isAdmin) {
            return self::result(self::DENY, 'You are not enrolled in this class.');
        }

        if (!$isHost && $role !== 'student' && !$isAdmin) {
            return self::result(self::DENY, 'You cannot join this class.');
        }

        if (!empty($ctx['kicked']) && !$isHost && !$isAdmin) {
            return self::result(self::DENY, 'The teacher removed you from this class.');
        }

        $meeting = strtolower((string)($ctx['meeting_status'] ?? 'scheduled'));
        if ($meeting === 'cancelled') {
            return self::result(self::DENY, 'This online class was cancelled.');
        }
        if ($meeting === 'ended' && !$isHost && !$isAdmin) {
            return self::result(self::DENY, 'This class has ended.');
        }

        if (!empty($ctx['join_window_closed']) && !$isHost && !$isAdmin) {
            return self::result(self::DENY, 'Join is closed. This class has finished.');
        }

        if (!empty($ctx['locked']) && !$isHost && !$isAdmin) {
            return self::result(self::DENY, 'The teacher has locked this class.');
        }

        if ($isHost || $isAdmin) {
            return self::result(self::ALLOW, 'You can open this class.');
        }

        $fee = strtolower((string)($ctx['fee_status'] ?? 'unpaid'));
        if (!in_array($fee, ['paid', 'waived'], true)) {
            if ($fee === 'pending' || !empty($ctx['payment_pending'])) {
                return self::result(self::WAIT, StudentLessonFeeService::pendingPaywallMessage(), 'pay_pending');
            }
            return self::result(self::PAY, StudentLessonFeeService::paywallMessage(), 'pay');
        }

        if ($meeting !== 'live') {
            if (!empty($ctx['within_join_window'])) {
                return self::result(self::WAIT, 'Waiting for the teacher to start class.', 'start');
            }
            return self::result(self::WAIT, 'This class has not started yet.', 'start');
        }

        if (!self::studentMayEnterLiveRoom($ctx)) {
            $st = strtolower((string)($ctx['waiting_status'] ?? ''));
            if ($st === 'denied') {
                return self::result(self::DENY, 'The teacher did not let you into this class.');
            }
            return self::result(self::WAIT, 'Waiting for the teacher to let you in', 'lobby');
        }

        return self::result(self::ALLOW, 'You can join this class.');
    }

    /**
     * @return array{code:string,message:string,wait_kind:?string}
     */
    private static function result(string $code, string $message, ?string $waitKind = null): array
    {
        return ['code' => $code, 'message' => $message, 'wait_kind' => $waitKind];
    }

    public static function canPublish(array $ctx, bool $studentCamera, bool $studentMic): bool
    {
        $role = strtolower((string)($ctx['role'] ?? ''));
        if ($role === 'admin' || !empty($ctx['is_host'])) {
            return true;
        }
        return $studentCamera || $studentMic;
    }
}
