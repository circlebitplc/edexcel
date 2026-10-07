<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Recording watch decision. Attendance is deliberately ignored.
 *
 * Context keys used by decide():
 * authenticated, recording_exists, recording_status, lesson_exists, lesson_deleted,
 * enrolled, fee_status (paid|waived|pending|unpaid|partial), payment_pending
 */
final class RecordingAccessService
{
    public function __construct(private \PDO $pdo)
    {
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    public const ACCESS_GRANTED = 'ACCESS_GRANTED';
    public const PAYMENT_REQUIRED = 'PAYMENT_REQUIRED';
    public const RECORDING_PROCESSING = 'RECORDING_PROCESSING';
    public const RECORDING_UNAVAILABLE = 'RECORDING_UNAVAILABLE';
    public const NOT_AUTHORIZED = 'NOT_AUTHORIZED';
    public const PAYMENT_PENDING = 'PAYMENT_PENDING';

    /**
     * @param array<string,mixed> $ctx
     */
    public static function decide(array $ctx): string
    {
        if (empty($ctx['authenticated'])) {
            return self::NOT_AUTHORIZED;
        }
        if (empty($ctx['recording_exists']) || empty($ctx['lesson_exists'])) {
            return self::NOT_AUTHORIZED;
        }
        if (!empty($ctx['lesson_deleted'])) {
            return self::NOT_AUTHORIZED;
        }
        if (empty($ctx['enrolled'])) {
            return self::NOT_AUTHORIZED;
        }

        $recStatus = strtolower((string)($ctx['recording_status'] ?? ''));
        if (in_array($recStatus, ['uploading', 'processing', 'draft'], true)) {
            return self::RECORDING_PROCESSING;
        }
        if ($recStatus !== 'ready') {
            return self::RECORDING_UNAVAILABLE;
        }

        $fee = strtolower((string)($ctx['fee_status'] ?? 'unpaid'));
        if (in_array($fee, ['paid', 'waived'], true)) {
            return self::ACCESS_GRANTED;
        }
        if ($fee === 'pending' || !empty($ctx['payment_pending'])) {
            return self::PAYMENT_PENDING;
        }
        return self::PAYMENT_REQUIRED;
    }

    public static function studentMessage(string $state): string
    {
        return match ($state) {
            self::ACCESS_GRANTED => 'You can watch this recording.',
            self::PAYMENT_REQUIRED => StudentLessonFeeService::paywallMessage(),
            self::PAYMENT_PENDING => StudentLessonFeeService::pendingPaywallMessage(),
            self::RECORDING_PROCESSING => 'Recording is being processed. Please check again later.',
            self::RECORDING_UNAVAILABLE => 'This recording is currently unavailable. Please contact the college.',
            default => 'You are not authorised to watch this recording.',
        };
    }

    public static function teacherCanManageLesson(int $sessionTeacherId, int $lessonTeacherId, bool $isAdmin, int $substituteTeacherId = 0): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ($sessionTeacherId < 1) {
            return false;
        }
        if ($sessionTeacherId === $lessonTeacherId) {
            return true;
        }
        return $substituteTeacherId > 0 && $sessionTeacherId === $substituteTeacherId;
    }

    public static function teacherOwnsLibraryItem(int $sessionTeacherId, int $ownerTeacherId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        return $sessionTeacherId > 0 && $sessionTeacherId === $ownerTeacherId;
    }

    /**
     * @return array{
     *   state:string,
     *   recording:?array,
     *   lesson:?array,
     *   fee:array,
     *   attendance:?string,
     *   assets:list<array<string,mixed>>
     * }
     */
    public function evaluate(int $studentId, int $recordingId): array
    {
        $recordings = new RecordingService($this->pdo);
        $fees = new StudentLessonFeeService($this->pdo);
        $recording = $recordings->find($recordingId);
        $assets = $recording ? $recordings->assets($recordingId) : [];
        $authenticated = $studentId > 0;
        $enrolled = false;
        $fee = [
            'status' => 'unpaid',
            'amount_due' => 0.0,
            'amount_paid' => 0.0,
            'row' => null,
            'covered_by_monthly' => false,
        ];
        $attendance = null;
        $lessonDeleted = false;

        if ($recording) {
            $lessonDeleted = !empty($recording['lesson_deleted_at']);
            $enrolled = $fees->isEnrolled($studentId, $recording);
            $attendance = $fees->attendanceStatus($studentId, (int)$recording['timetable_id']);
            $fee = $fees->resolve($studentId, $recording, $recordingId);
        }

        $state = self::decide([
            'authenticated' => $authenticated,
            'recording_exists' => $recording !== null && $recording['deleted_at'] === null && (string)$recording['status'] !== 'deleted',
            'recording_status' => $recording['status'] ?? '',
            'lesson_exists' => $recording !== null,
            'lesson_deleted' => $lessonDeleted,
            'enrolled' => $enrolled,
            'fee_status' => $fee['status'],
            'payment_pending' => ($fee['status'] ?? '') === 'pending',
        ]);

        return [
            'state' => $state,
            'recording' => $recording,
            'lesson' => $recording,
            'fee' => $fee,
            'attendance' => $attendance,
            'assets' => $assets,
        ];
    }

    public function logAccess(int $studentId, int $recordingId, int $timetableId, string $result, ?string $paymentStatus): void
    {
        try {
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $this->pdo->prepare("
                INSERT INTO recording_access_logs
                    (student_id, recording_id, timetable_id, access_result, payment_status, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$studentId, $recordingId, $timetableId, $result, $paymentStatus, $ip, $ua]);
        } catch (\Throwable $e) {
            // Access logging must not block playback.
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function studentCatalogue(int $studentId, int $limit = 150): array
    {
        $fees = new StudentLessonFeeService($this->pdo);
        $stmt = $this->pdo->prepare("
            SELECT cr.id AS recording_id, cr.status AS recording_status, cr.title, cr.duration_seconds,
                   cr.created_at AS uploaded_at, tt.id AS timetable_id, tt.date, tt.start_time, tt.end_time,
                   tt.class_id, tt.teacher_id, tt.class_fee_per_student, tt.lesson_status, tt.delivery_mode,
                   s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
                   sa.status AS attendance_status
            FROM class_recordings cr
            JOIN timetable tt ON tt.id = cr.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
            LEFT JOIN student_attendance sa ON sa.timetable_id = tt.id AND sa.student_id = ?
            WHERE cr.deleted_at IS NULL AND cr.status <> 'deleted' AND tt.deleted_at IS NULL
            ORDER BY tt.date DESC, tt.start_time DESC
            LIMIT " . max(1, min(300, $limit))
        );
        $stmt->execute([$studentId, $studentId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (!$fees->isEnrolled($studentId, $row)) {
                continue;
            }
            $fee = $fees->resolve($studentId, $row, (int)$row['recording_id']);
            $state = self::decide([
                'authenticated' => true,
                'recording_exists' => true,
                'recording_status' => $row['recording_status'],
                'lesson_exists' => true,
                'lesson_deleted' => false,
                'enrolled' => true,
                'fee_status' => $fee['status'],
                'payment_pending' => $fee['status'] === 'pending',
            ]);
            $row['access_state'] = $state;
            $row['fee_status'] = $fee['status'];
            $row['amount_due'] = $fee['amount_due'];
            $row['covered_by_monthly'] = $fee['covered_by_monthly'];
            $out[] = $row;
        }
        return $out;
    }
}
