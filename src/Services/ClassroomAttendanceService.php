<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class ClassroomAttendanceService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function finalizeMeeting(int $meetingId): void
    {
        $settings = classroom_settings($this->pdo);
        if (empty($settings['auto_attendance'])) {
            return;
        }
        $stmt = $this->pdo->prepare("
            SELECT om.timetable_id, om.ended_at, tt.date, tt.start_time, tt.end_time, tt.class_id
            FROM online_meetings om
            JOIN timetable tt ON tt.id = om.timetable_id
            WHERE om.id = ?
            LIMIT 1
        ");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meeting) {
            return;
        }
        $timetableId = (int)$meeting['timetable_id'];
        $startTs = strtotime((string)$meeting['date'] . ' ' . (string)$meeting['start_time']) ?: 0;
        $endTs = strtotime((string)$meeting['date'] . ' ' . (string)$meeting['end_time']) ?: 0;
        $meetingEndedTs = strtotime((string)($meeting['ended_at'] ?? '')) ?: 0;
        $presentMin = (int)$settings['present_minutes'] * 60;
        $lateGrace = (int)$settings['late_grace_minutes'] * 60;
        $allowed = $this->allowedStatuses();

        $parts = $this->pdo->prepare("
            SELECT user_id, role, joined_at, left_at, last_seen_at, duration_seconds,
                   COALESCE(waiting_status, 'none') AS waiting_status
            FROM meeting_participants
            WHERE meeting_id = ? AND role = 'student'
        ");
        $rows = [];
        try {
            $parts->execute([$meetingId]);
            $rows = $parts->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $parts = $this->pdo->prepare("
                SELECT user_id, role, joined_at, left_at, last_seen_at, duration_seconds
                FROM meeting_participants
                WHERE meeting_id = ? AND role = 'student'
            ");
            $parts->execute([$meetingId]);
            $rows = $parts->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $save = $this->pdo->prepare("
            INSERT INTO student_attendance (student_id, timetable_id, status, note, marked_by)
            VALUES (?, ?, ?, ?, NULL)
            ON DUPLICATE KEY UPDATE
                status = IF(marked_by IS NULL, VALUES(status), status),
                note = IF(marked_by IS NULL, VALUES(note), note)
        ");

        $seen = [];
        foreach ($rows as $row) {
            $studentId = (int)$row['user_id'];
            $waitSt = strtolower((string)($row['waiting_status'] ?? 'none'));
            if (in_array($waitSt, ['waiting', 'denied'], true) && (int)$row['duration_seconds'] === 0) {
                continue;
            }
            $seen[$studentId] = true;
            $decision = self::classify([
                'duration_seconds' => (int)$row['duration_seconds'],
                'joined_ts' => strtotime((string)$row['joined_at']) ?: $startTs,
                'left_ts' => strtotime((string)($row['left_at'] ?? '')) ?: 0,
                'last_seen_ts' => strtotime((string)($row['last_seen_at'] ?? '')) ?: 0,
                'start_ts' => $startTs,
                'end_ts' => $endTs,
                'meeting_ended_ts' => $meetingEndedTs,
                'present_seconds' => $presentMin,
                'late_grace_seconds' => $lateGrace,
                'allowed_statuses' => $allowed,
            ]);
            try {
                $save->execute([$studentId, $timetableId, $decision['status'], $decision['note']]);
            } catch (Throwable $e) {
                error_log('Classroom attendance: ' . $e->getMessage());
            }
        }

        $enrolled = $this->pdo->prepare("
            SELECT student_id FROM student_enrollments WHERE class_id = ?
        ");
        $enrolled->execute([(int)$meeting['class_id']]);
        foreach ($enrolled->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            $sid = (int)$sid;
            if ($sid < 1 || isset($seen[$sid])) {
                continue;
            }
            try {
                $save->execute([$sid, $timetableId, 'absent', 'Online class: did not join']);
            } catch (Throwable $e) {
                error_log('Classroom attendance absent: ' . $e->getMessage());
            }
        }
    }

    /**
     * True only when the student actually left (or was last seen) well before
     * both the scheduled lesson end and the meeting's actual end.
     * Students still in the room when the teacher ends class (left_at ≈ ended_at,
     * or left_at still NULL) are not left-early.
     */
    public static function departedEarly(
        int $leftTs,
        int $lessonEndTs,
        int $meetingEndedTs,
        int $graceSeconds,
        int $lastSeenTs = 0
    ): bool {
        $grace = max(0, $graceSeconds);
        $departedTs = $leftTs;
        if ($lastSeenTs > 0 && ($departedTs <= 0 || $lastSeenTs < $departedTs)) {
            $departedTs = $lastSeenTs;
        }
        if ($departedTs <= 0 || $lessonEndTs <= 0) {
            return false;
        }
        if ($departedTs >= ($lessonEndTs - $grace)) {
            return false;
        }
        // closeOpenParticipants stamps left_at to now; heartbeat last_seen is ~20s stale.
        $nearMeetingEnd = 120;
        if ($meetingEndedTs > 0 && $departedTs >= ($meetingEndedTs - $nearMeetingEnd)) {
            return false;
        }
        return true;
    }

    /**
     * Pure attendance decision for a live-class session. Does not use wall time from first join.
     *
     * @param array{
     *   duration_seconds:int,
     *   joined_ts:int,
     *   left_ts?:int,
     *   last_seen_ts?:int,
     *   start_ts:int,
     *   end_ts?:int,
     *   meeting_ended_ts?:int,
     *   present_seconds:int,
     *   late_grace_seconds:int,
     *   allowed_statuses?:list<string>
     * } $input
     * @return array{status:string,left_early:bool,note:string}
     */
    public static function classify(array $input): array
    {
        $duration = max(0, (int)($input['duration_seconds'] ?? 0));
        $joinedTs = (int)($input['joined_ts'] ?? 0);
        $leftTs = (int)($input['left_ts'] ?? 0);
        $lastSeenTs = (int)($input['last_seen_ts'] ?? 0);
        $startTs = (int)($input['start_ts'] ?? 0);
        $endTs = (int)($input['end_ts'] ?? 0);
        $meetingEndedTs = (int)($input['meeting_ended_ts'] ?? 0);
        $presentMin = max(1, (int)($input['present_seconds'] ?? 1200));
        $lateGrace = max(0, (int)($input['late_grace_seconds'] ?? 0));
        $allowed = $input['allowed_statuses'] ?? ['present', 'absent', 'late', 'excused'];
        if (!is_array($allowed) || $allowed === []) {
            $allowed = ['present', 'absent', 'late', 'excused'];
        }

        $status = 'absent';
        if ($duration >= $presentMin) {
            $status = ($startTs > 0 && $joinedTs > $startTs + $lateGrace) ? 'late' : 'present';
        } elseif ($duration >= 60) {
            $status = 'late';
        }

        $earlyGrace = $lateGrace > 0 ? $lateGrace : 300;
        $leftEarly = false;
        if (in_array($status, ['present', 'late'], true)) {
            $leftEarly = self::departedEarly($leftTs, $endTs, $meetingEndedTs, $earlyGrace, $lastSeenTs);
        }

        $joinLabel = $joinedTs > 0 ? date('g:i A', $joinedTs) : 'unknown';
        $mins = (int)round($duration / 60);
        $note = 'Online class: joined ' . $joinLabel . ', ' . $mins . ' min in class';
        if ($leftEarly) {
            $note .= ' · left early';
            if (in_array('left_early', $allowed, true)) {
                $status = 'left_early';
            }
        }

        return [
            'status' => $status,
            'left_early' => $leftEarly,
            'note' => $note,
        ];
    }

    /**
     * @return list<string>
     */
    public static function parseEnumValues(string $columnType): array
    {
        if (preg_match_all("/'((?:\\\\'|[^'])*)'/", $columnType, $m)) {
            return array_values(array_filter($m[1], static fn ($v) => $v !== ''));
        }
        return [];
    }

    /**
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'student_attendance'
                  AND COLUMN_NAME = 'status'
                LIMIT 1
            ");
            $stmt->execute();
            $type = (string)$stmt->fetchColumn();
            $vals = self::parseEnumValues($type);
            if ($vals !== []) {
                return $vals;
            }
        } catch (Throwable $e) {
        }
        return ['present', 'absent', 'late', 'excused'];
    }
}
