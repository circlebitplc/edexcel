<?php
declare(strict_types=1);

namespace Edexcel\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Identify caller → understand intent/entities → authorize → run a fixed query.
 * The model must not invent SQL.
 */
final class WhatsAppAssistant
{
    private DateTimeZone $tz;

    public function __construct(private PDO $pdo)
    {
        $this->tz = new DateTimeZone('Asia/Colombo');
        $this->ensureSessionTable();
        $this->ensureAuditTable();
    }

    public function reply(string $phone, string $original, string $normalized): ?string
    {
        $identity = $this->identify($phone);
        $session = $this->loadSession($phone);
        $parsed = $this->parse($original, $normalized, $identity, $session);

        $this->saveSession($phone, $parsed);

        if ($parsed['intent'] === '') {
            $this->audit($phone, $identity, $original, $parsed, 'none', 'unclassified', null);
            return null;
        }

        $denied = $this->authorize((string)$parsed['intent'], $identity, $parsed);
        if ($denied !== null) {
            $this->audit($phone, $identity, $original, $parsed, 'blocked', 'unauthorized', $denied);
            return $denied;
        }

        try {
            $answer = $this->dispatch($identity, $parsed);
        } catch (Throwable $e) {
            error_log('WhatsApp query failed: ' . $e->getMessage());
            $fail = "Sorry, I couldn't retrieve that information right now. Please try again in a moment.";
            $this->audit($phone, $identity, $original, $parsed, 'error', 'query_failed', $fail);
            return $fail;
        }

        if ($answer === null || trim($answer) === '') {
            $this->audit($phone, $identity, $original, $parsed, 'empty', 'no_rows', null);
            return null;
        }

        $this->audit($phone, $identity, $original, $parsed, 'ok', 'verified', $answer);
        return $answer;
    }

    /**
     * Same verified class/teacher/timetable queries as WhatsApp, for a logged-in student.
     */
    public function replyForStudent(int $studentId, string $message): ?string
    {
        if ($studentId < 1) {
            return null;
        }
        $identity = $this->identityForStudent($studentId);
        $sessionKey = 'courso:' . $studentId;
        $normalized = mb_strtolower(trim($message));
        $session = $this->loadSession($sessionKey);
        $parsed = $this->parse($message, $normalized, $identity, $session);
        $this->saveSession($sessionKey, $parsed);

        if ($parsed['intent'] === '') {
            return null;
        }

        $denied = $this->authorize((string)$parsed['intent'], $identity, $parsed);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $answer = $this->dispatch($identity, $parsed);
        } catch (Throwable $e) {
            error_log('Courso college query failed: ' . $e->getMessage());
            return null;
        }

        if ($answer === null || trim($answer) === '') {
            return null;
        }

        return $answer;
    }

    /**
     * @return array{
     *   role:string,
     *   student_id:?int,
     *   teacher_id:?int,
     *   is_admin:bool,
     *   class_ids:list<int>,
     *   child_name:string
     * }
     */
    public function identityForStudent(int $studentId): array
    {
        return [
            'role' => 'student',
            'student_id' => $studentId,
            'teacher_id' => null,
            'is_admin' => false,
            'class_ids' => $this->classIdsForStudent($studentId),
            'child_name' => $this->studentName($studentId),
        ];
    }

    /**
     * Live classes + teacher contacts for Courso AI. Never invent these rows.
     */
    public function collegeFactsText(int $studentId): string
    {
        $identity = $this->identityForStudent($studentId);
        $parts = [
            $this->classCatalogueText(),
            $this->formatTeacherDirectory([
                'text' => 'teachers list',
                'teacher_id' => 0,
                'teacher_name' => '',
                'subject_id' => 0,
                'subject_name' => '',
                'programme' => '',
            ], true),
            $this->enrolledWithTeachersText($studentId, $identity),
            $this->upcomingLessonsText($identity),
        ];
        $text = implode("\n\n---\n\n", array_filter($parts, static fn ($p) => trim((string)$p) !== ''));
        if (mb_strlen($text) > 4500) {
            $text = mb_substr($text, 0, 4490) . '…';
        }
        return $text;
    }

    /**
     * Classes, teachers, and how to register — no personal timetable or marks.
     */
    public function publicFactsText(): string
    {
        $parts = [
            $this->classCatalogueText(),
            $this->formatTeacherDirectory([
                'text' => 'teachers list',
                'teacher_id' => 0,
                'teacher_name' => '',
                'subject_id' => 0,
                'subject_name' => '',
                'programme' => '',
            ], true),
            'HOW TO REGISTER: ' . WhatsAppGuideService::page('student/register.php'),
            'STUDENT LOGIN: ' . WhatsAppGuideService::page('index.php') . '#student-login',
            'College: Edexcel College (Pearson IGCSE / IAS / IAL). Website: ' . WhatsAppGuideService::publicBase(),
        ];
        $text = implode("\n\n---\n\n", array_filter($parts, static fn ($p) => trim((string)$p) !== ''));
        if (mb_strlen($text) > 4500) {
            $text = mb_substr($text, 0, 4490) . '…';
        }
        return $text;
    }

    /**
     * Verified college answers for homepage visitors (not signed in).
     */
    public function replyForVisitor(string $message): ?string
    {
        $identity = [
            'role' => 'public',
            'student_id' => null,
            'teacher_id' => null,
            'is_admin' => false,
            'class_ids' => [],
            'child_name' => '',
        ];
        $sid = function_exists('session_id') ? (string)session_id() : 'anon';
        $sessionKey = 'web:' . substr(hash('sha256', $sid !== '' ? $sid : 'anon'), 0, 32);
        $normalized = mb_strtolower(trim($message));
        $session = $this->loadSession($sessionKey);
        $parsed = $this->parse($message, $normalized, $identity, $session);
        $this->saveSession($sessionKey, $parsed);

        if ($parsed['intent'] === '') {
            return null;
        }

        $denied = $this->authorize((string)$parsed['intent'], $identity, $parsed);
        if ($denied !== null) {
            return "I can show your personal timetable, marks, and fees after you sign in.\n"
                . 'Create an account: ' . WhatsAppGuideService::page('student/register.php') . "\n"
                . 'Already a student? Log in: ' . WhatsAppGuideService::page('index.php') . '#student-login';
        }

        try {
            $answer = $this->dispatch($identity, $parsed);
        } catch (Throwable $e) {
            error_log('Public AI college query failed: ' . $e->getMessage());
            return null;
        }

        if ($answer === null || trim($answer) === '') {
            return null;
        }

        return $answer;
    }

    public static function looksLikeCollegeFactQuestion(string $message): bool
    {
        $text = mb_strtolower($message);
        $needles = [
            'class', 'classes', 'teacher', 'teachers', 'sir', 'miss', 'madam',
            'phone', 'number', 'whatsapp', 'contact', 'email', 'mobile',
            'timetable', 'schedule', 'room', 'when is', 'what time',
            'who teach', 'who teaches', 'who is teaching', 'lecturer',
            'enrol', 'enroll', 'available', 'do you have', 'do you offer',
            'subject', 'join class', 'my classes', 'koheda', 'kawda', 'denna',
            'aasaan', 'aashiriyar',
        ];
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($text, $n)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $identity
     */
    private function enrolledWithTeachersText(int $studentId, array $identity): string
    {
        $classIds = $identity['class_ids'] ?? [];
        if ($classIds === []) {
            return 'THIS STUDENT ENROLLED CLASSES: none yet.';
        }
        $in = implode(',', array_fill(0, count($classIds), '?'));
        $out = "THIS STUDENT ENROLLED CLASSES (with teacher contact from the database):\n";
        try {
            $hasEnrolTeacher = false;
            try {
                $col = $this->pdo->query("SHOW COLUMNS FROM student_enrollments LIKE 'teacher_id'");
                $hasEnrolTeacher = $col && $col->fetch();
            } catch (Throwable $e) {
                $hasEnrolTeacher = false;
            }
            $teacherPick = $hasEnrolTeacher
                ? 'COALESCE(et.name, t.name)'
                : 't.name';
            $phonePick = $hasEnrolTeacher
                ? 'COALESCE(et.phone, t.phone)'
                : 't.phone';
            $emailPick = $hasEnrolTeacher
                ? 'COALESCE(et.email, t.email)'
                : 't.email';
            $enrolJoin = $hasEnrolTeacher
                ? 'LEFT JOIN teachers et ON et.id = se.teacher_id AND et.deleted_at IS NULL'
                : '';
            $stmt = $this->pdo->prepare("
                SELECT c.name AS class_name,
                       {$teacherPick} AS teacher_name,
                       {$phonePick} AS phone,
                       {$emailPick} AS email,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
                FROM student_enrollments se
                JOIN student_classes c ON c.id = se.class_id AND c.deleted_at IS NULL
                {$enrolJoin}
                LEFT JOIN timetable tt ON tt.class_id = c.id AND tt.deleted_at IS NULL
                LEFT JOIN teachers t ON t.id = tt.teacher_id AND t.deleted_at IS NULL
                LEFT JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
                WHERE se.student_id = ? AND se.class_id IN ($in)
                GROUP BY c.id, c.name, teacher_name, phone, email
                ORDER BY c.name
            ");
            $stmt->execute(array_merge([$studentId], $classIds));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $this->qMyClasses($identity);
        }
        if ($rows === []) {
            return $this->qMyClasses($identity);
        }
        foreach ($rows as $row) {
            $out .= '- class: ' . ($row['class_name'] ?? '')
                . ' | teacher: ' . (($row['teacher_name'] ?? '') !== '' ? $row['teacher_name'] : 'not assigned')
                . ' | phone: ' . (($row['phone'] ?? '') !== '' ? $row['phone'] : 'not saved')
                . ' | email: ' . (($row['email'] ?? '') !== '' ? $row['email'] : '-')
                . ' | subjects: ' . (($row['subjects'] ?? '') !== '' ? $row['subjects'] : '-')
                . "\n";
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $identity
     */
    private function upcomingLessonsText(array $identity): string
    {
        $classIds = $identity['class_ids'] ?? [];
        if ($classIds === []) {
            return '';
        }
        $in = implode(',', array_fill(0, count($classIds), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT tt.date, tt.start_time, tt.end_time, s.name AS subject_name,
                       c.name AS class_name, t.name AS teacher_name, t.phone AS teacher_phone,
                       r.name AS room_name
                FROM timetable tt
                JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
                JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
                JOIN teachers t ON t.id = tt.teacher_id AND t.deleted_at IS NULL
                LEFT JOIN rooms r ON r.id = tt.room_id
                WHERE tt.deleted_at IS NULL AND tt.class_id IN ($in)
                  AND (tt.date > CURDATE() OR (tt.date = CURDATE() AND tt.start_time >= CURTIME()))
                ORDER BY tt.date ASC, tt.start_time ASC
                LIMIT 12
            ");
            $stmt->execute($classIds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return '';
        }
        if ($rows === []) {
            return 'UPCOMING LESSONS: none in the next period.';
        }
        $out = "UPCOMING LESSONS (from timetable):\n";
        foreach ($rows as $row) {
            $out .= '- ' . ($row['date'] ?? '') . ' ' . substr((string)($row['start_time'] ?? ''), 0, 5)
                . '-' . substr((string)($row['end_time'] ?? ''), 0, 5)
                . ' ' . ($row['subject_name'] ?? '')
                . ' (' . ($row['class_name'] ?? '') . ')'
                . ' teacher ' . ($row['teacher_name'] ?? '')
                . ' phone ' . (($row['teacher_phone'] ?? '') !== '' ? $row['teacher_phone'] : 'not saved')
                . ' room ' . ($row['room_name'] ?? '-')
                . "\n";
        }
        return $out;
    }

    /**
     * Intent only — used by the coverage test. Does not query student data.
     */
    public function detectIntent(string $message): string
    {
        $identity = [
            'role' => 'public',
            'student_id' => null,
            'teacher_id' => null,
            'is_admin' => false,
            'class_ids' => [],
            'child_name' => '',
        ];
        $normalized = mb_strtolower(trim($message));
        $parsed = $this->parse($message, $normalized, $identity, []);
        return (string)$parsed['intent'];
    }

    /**
     * @return array{
     *   role:string,
     *   student_id:?int,
     *   teacher_id:?int,
     *   is_admin:bool,
     *   class_ids:list<int>,
     *   child_name:string
     * }
     */
    public function identify(string $phone): array
    {
        $empty = [
            'role' => 'public',
            'student_id' => null,
            'teacher_id' => null,
            'is_admin' => false,
            'class_ids' => [],
            'child_name' => '',
        ];

        $variants = $this->phoneVariants($phone);
        if ($variants === []) {
            return $empty;
        }

        $admin = $this->lookupUserByPhones($variants, ['admin']);
        if ($admin !== null) {
            $empty['role'] = 'admin';
            $empty['is_admin'] = true;
            $empty['student_id'] = null;
        }

        $teacherId = $this->lookupTeacherId($variants);
        if ($teacherId !== null) {
            $empty['teacher_id'] = $teacherId;
            if ($empty['role'] === 'public') {
                $empty['role'] = 'teacher';
            }
        }

        $studentId = $this->lookupStudentId($variants);
        if ($studentId !== null) {
            $empty['student_id'] = $studentId;
            $empty['class_ids'] = $this->classIdsForStudent($studentId);
            $empty['child_name'] = $this->studentName($studentId);
            if ($empty['role'] === 'public') {
                $empty['role'] = $this->isParentPhone($variants, $studentId) ? 'parent' : 'student';
            } elseif ($empty['role'] === 'teacher' || $empty['role'] === 'admin') {
                // keep staff role; still attach child/student if the same phone is saved
            } else {
                $empty['role'] = $this->isParentPhone($variants, $studentId) ? 'parent' : 'student';
            }
        }

        return $empty;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    private function parse(string $original, string $normalized, array $identity, array $session): array
    {
        $text = $this->expandSlang(WhatsAppI18n::fold($original . ' ' . $normalized));
        $range = $this->extractDateRange($text);
        $part = $this->extractPartOfDay($text);
        $subject = $this->matchSubject($text);
        $teacher = $this->matchTeacher($text);
        $followUp = $subject === null && $teacher === null && $this->isFollowUp($text, $original);
        $intent = $this->classifyIntent($text, $subject, $teacher, $identity);
        $programme = $this->extractProgramme($text);

        if ($session !== [] && ($followUp || $this->isCourseFollowOn($text))) {
            if ($this->containsAny($text, ['who', 'teacher', 'sir', 'miss', 'teach'])) {
                $intent = 'GET_TEACHER';
            } elseif ($this->containsAny($text, ['time', 'timetable', 'schedule', 'when'])) {
                $intent = 'GET_SUBJECT_SCHEDULE';
            } elseif ($intent === '' || $intent === 'GET_TIMETABLE') {
                $keep = (string)($session['intent'] ?? '');
                if ($keep !== '') {
                    $intent = $keep;
                }
            }
            if ($subject === null && !empty($session['subject_id'])) {
                $subject = [
                    'id' => (int)$session['subject_id'],
                    'name' => (string)($session['subject_name'] ?? ''),
                ];
            }
            if ($programme === '' && !empty($session['programme'])) {
                $programme = (string)$session['programme'];
            }
            if (
                $teacher === null
                && !empty($session['teacher_id'])
                && $subject === null
                && !$this->containsAny($text, ['all teacher', 'teachers name', 'teachers phone', 'teachers number'])
            ) {
                $teacher = [
                    'id' => (int)$session['teacher_id'],
                    'name' => (string)($session['teacher_name'] ?? ''),
                ];
            }
            if ($range === null && !empty($session['date_from']) && !$this->containsAny($text, ['time', 'timetable', 'schedule'])) {
                $range = [
                    'from' => (string)$session['date_from'],
                    'to' => (string)($session['date_to'] ?? $session['date_from']),
                    'label' => (string)($session['date_label'] ?? ''),
                ];
            }
            if ($this->containsAny($text, ['where', 'room', 'koheda', 'location'])) {
                $intent = 'GET_CLASS_ROOM';
            }
        }

        if ($intent === '' && $subject !== null) {
            $intent = 'GET_SUBJECT_SCHEDULE';
        }
        if ($intent === '' && $teacher !== null) {
            $intent = 'GET_TEACHER_SCHEDULE';
        }

        if ($range === null && in_array($intent, [
            'GET_TIMETABLE', 'GET_SUBJECT_SCHEDULE', 'GET_TEACHER_SCHEDULE',
            'GET_CLASS_ROOM', 'GET_CLASS_TEACHER', 'CHECK_CANCELLATION',
            'GET_TODAY_CLASSES', 'GET_TOMORROW_CLASSES', 'GET_NEXT_CLASS',
        ], true)) {
            $range = $this->defaultRangeForIntent($intent, $text);
        }

        return [
            'intent' => $intent,
            'text' => $text,
            'original' => $original,
            'date_from' => $range['from'] ?? null,
            'date_to' => $range['to'] ?? null,
            'date_label' => $range['label'] ?? '',
            'part' => $part,
            'subject_id' => $subject['id'] ?? null,
            'subject_name' => $subject['name'] ?? '',
            'programme' => $programme,
            'teacher_id' => $teacher['id'] ?? null,
            'teacher_name' => $teacher['name'] ?? '',
            'want_next' => $this->containsAny($text, ['next class', 'coming up', 'after this', 'upcoming', 'mage next']),
            'want_contact' => $this->containsAny($text, [
                'whatsapp', 'number', 'phone', 'contact', 'call', 'denna',
                'mobile', 'landline', 'email',
            ]),
        ];
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function dispatch(array $identity, array $parsed): ?string
    {
        $intent = (string)$parsed['intent'];

        if ($parsed['want_contact'] && ($parsed['teacher_id'] || $parsed['subject_id'] || $this->containsAny($parsed['text'], [
            'teacher', 'sir', 'miss', 'lecturer',
        ]))) {
            $intent = 'GET_TEACHER_CONTACT';
        }

        return match ($intent) {
            'GET_NEXT_CLASS' => $this->qTimetable($identity, $parsed, true),
            'GET_TIMETABLE', 'GET_TODAY_CLASSES', 'GET_TOMORROW_CLASSES' => $this->qTimetable($identity, $parsed, false),
            'GET_SUBJECT_SCHEDULE' => $this->qTimetable($identity, $parsed, !empty($parsed['want_next'])),
            'GET_TEACHER_SCHEDULE' => $this->qTeacherSchedule($identity, $parsed),
            'GET_CLASS_ROOM' => $this->qTimetable($identity, $parsed, !empty($parsed['want_next'])),
            'GET_CLASS_TEACHER' => $this->qTimetable($identity, $parsed, !empty($parsed['want_next'])),
            'CHECK_CANCELLATION' => $this->qTimetable($identity, $parsed, false),
            'GET_TEACHER' => $this->qTeachers($identity, $parsed),
            'GET_TEACHER_PROFILE' => $this->qTeacherProfile($identity, $parsed),
            'GET_TEACHER_CONTACT' => $this->qTeacherContact($identity, $parsed),
            'GET_ATTENDANCE', 'GET_ATTENDANCE_BY_SUBJECT' => $this->qAttendance($identity, $parsed),
            'REPORT_ATTENDANCE_ERROR' => $this->qAttendanceDispute($identity, $parsed),
            'GET_FEES', 'GET_OUTSTANDING_FEES', 'GET_PAYMENT_HISTORY' => $this->qFees($identity, $parsed),
            'PAYMENT_INQUIRY' => $this->qFees($identity, $parsed),
            'GET_EXAMS', 'GET_NEXT_EXAM' => $this->qExams($identity, $parsed),
            'GET_HOMEWORK' => $this->qHomework($identity, $parsed),
            'GET_MATERIALS' => $this->qMaterials($identity, $parsed),
            'GET_PROGRESS' => $this->qProgress($identity, $parsed),
            'GET_MY_CLASSES' => $this->qMyClasses($identity),
            'GET_AVAILABLE_CLASSES' => $this->qAvailableClasses($parsed),
            'CHECK_CLASS_CAPACITY', 'JOIN_WAITLIST' => $this->qCapacity($parsed),
            'GET_WHATSAPP_GROUP' => $this->qGroupLink($identity, $parsed),
            'GET_HOLIDAYS' => $this->qHolidays($parsed),
            'GET_NEWS' => $this->qNews(),
            'REGISTER' => $this->qRegister(),
            'TEACHER_MY_TIMETABLE' => $this->qTeacherOwnTimetable($identity, $parsed),
            'TEACHER_MY_STUDENTS' => $this->qTeacherStudents($identity, $parsed),
            'TEACHER_PAYMENTS' => $this->qTeacherPayments($identity, $parsed),
            'ADMIN_STATS' => $this->qAdminStats($identity),
            'REFUSE' => $this->refuse(),
            default => null,
        };
    }

    private function classifyIntent(string $text, ?array $subject, ?array $teacher, array $identity): string
    {
        if ($this->containsAny($text, [
            'all students', 'every student', 'give me all', 'parent phone',
            'another student', 'nithish', 'who hasnt paid', 'who has not paid',
        ])) {
            return 'REFUSE';
        }

        if ($this->containsAny($text, [
            'register', 'sign up', 'admission', 'how to join', 'enrol', 'enroll',
            'ලියාපදිංචි',
        ])) {
            return 'REGISTER';
        }

        if ($this->containsAny($text, [
            'marked absent but', 'attendance is wrong', 'i was there',
            'i attended', 'correct my attendance', 'forgot to sign',
            'request an attendance',
        ])) {
            return 'REPORT_ATTENDANCE_ERROR';
        }

        if ($this->containsAny($text, [
            'already paid', 'payment isnt showing', "payment isn't showing",
            'marked unpaid', 'how do i pay', 'pay online', 'where should i send',
        ])) {
            return 'PAYMENT_INQUIRY';
        }

        if ($this->containsAny($text, [
            'fee', 'fees', 'owe', 'outstanding', 'due', 'paid this month',
            'payment', 'ගාස්තු', 'ගෙවීම', 'how much do i', 'how much does my child',
        ])) {
            return 'GET_FEES';
        }

        if ($this->containsAny($text, [
            'attendance', 'absent', 'present today', 'missed', 'පැමිණීම',
            'did my child attend', 'was my child',
        ])) {
            return 'GET_ATTENDANCE';
        }

        if ($this->containsAny($text, ['homework', 'assignment', 'due homework'])) {
            return 'GET_HOMEWORK';
        }

        if ($this->containsAny($text, [
            'material', 'materials', 'notes', 'past paper', 'pdf', 'revision',
        ])) {
            return 'GET_MATERIALS';
        }

        if ($this->containsAny($text, [
            'progress', 'score', 'marks', 'average', 'how am i doing',
            'how is my child doing', 'test result',
        ])) {
            return 'GET_PROGRESS';
        }

        if ($this->containsAny($text, ['exam', 'exams', 'mock', 'විභාග'])) {
            return 'GET_EXAMS';
        }

        if ($this->containsAny($text, [
            'whatsapp group', 'group link', 'class group', 'add me to the class group',
            'resend my group',
        ])) {
            return 'GET_WHATSAPP_GROUP';
        }

        if ($this->containsAny($text, [
            'list of teachers', 'teachers list', 'all teachers', 'lecturers',
            'who teaches', 'who teach', 'who is teaching', 'how does teach',
            'who does teach', 'teachers', 'teacher phone', 'teacher number',
            'teachers phone', 'teachers number',
        ])) {
            return $this->containsAny($text, ['phone', 'number', 'whatsapp', 'contact', 'mobile', 'email', 'denna'])
                ? 'GET_TEACHER_CONTACT'
                : 'GET_TEACHER';
        }

        if ($this->containsAny($text, [
            'class times', 'class time', 'time table', 'timetable', 'schedule',
            'what time', 'when is', 'when are', 'lesson times',
        ])) {
            return $subject ? 'GET_SUBJECT_SCHEDULE' : 'GET_TIMETABLE';
        }

        if ($this->containsAny($text, [
            'waitlist', 'waiting list', 'class full', 'is the class full',
            'space available', 'places available', 'how many students',
        ])) {
            return 'CHECK_CLASS_CAPACITY';
        }

        if ($this->containsAny($text, [
            'available class', 'classes available', 'do you have', 'do you offer',
            'is there an', 'is there a', 'what subjects', 'what classes are available',
            'do you teach',
        ])) {
            return 'GET_AVAILABLE_CLASSES';
        }

        if ($this->containsAny($text, [
            'enrolled', 'my classes', 'subjects am i', 'am i enrolled',
            'courses am i', 'can i leave',
        ])) {
            return 'GET_MY_CLASSES';
        }

        if ($this->containsAny($text, [
            'holiday', 'holidays', 'closed', 'institute open', 'නිවාඩු',
        ])) {
            return 'GET_HOLIDAYS';
        }

        if ($this->containsAny($text, ['news', 'announcement', 'whats new', 'latest update'])) {
            return 'GET_NEWS';
        }

        if ($this->containsAny($text, [
            'cancelled', 'cancel', 'happening', 'still on', 'substitute',
            'replacement', 'postponed', 'should i come', 'class on',
            'are we having',
        ])) {
            return 'CHECK_CANCELLATION';
        }

        if ($this->containsAny($text, [
            'qualification', 'experience', 'achievement', 'website',
            'facebook', 'instagram', 'youtube', 'tell me about',
        ]) && $teacher) {
            return 'GET_TEACHER_PROFILE';
        }

        if ($identity['role'] === 'teacher' || $identity['role'] === 'admin') {
            if ($this->containsAny($text, ['my students', 'who is enrolled', 'who should attend', 'show today students'])) {
                return 'TEACHER_MY_STUDENTS';
            }
            if ($this->containsAny($text, ['am i owed', 'will i be paid', 'my payment', 'pending payments', 'earned this month'])) {
                return 'TEACHER_PAYMENTS';
            }
            if ($this->containsAny($text, ['how many students are registered', 'how many teachers', 'fees outstanding', 'fees collected'])) {
                return $identity['is_admin'] ? 'ADMIN_STATS' : 'REFUSE';
            }
        }

        if ($this->containsAny($text, [
            'next class', 'coming up', 'after this', 'upcoming', 'first class',
            'last class', 'when should i come', 'what should i come',
        ])) {
            return 'GET_NEXT_CLASS';
        }

        if ($this->containsAny($text, ['tomorrow']) && !$this->containsAny($text, ['do you have', 'do you offer'])) {
            return $subject ? 'GET_SUBJECT_SCHEDULE' : 'GET_TOMORROW_CLASSES';
        }

        if ($this->containsAny($text, ['today', 'tonight']) && !$this->containsAny($text, ['do you have', 'do you offer'])) {
            return $subject ? 'GET_SUBJECT_SCHEDULE' : 'GET_TODAY_CLASSES';
        }

        if ($teacher && $this->containsAny($text, ['when', 'class', 'timetable', 'teach', 'schedule'])) {
            return 'GET_TEACHER_SCHEDULE';
        }

        if ($identity['teacher_id'] && $this->containsAny($text, ['my timetable', 'classes do i have', 'what classes do i'])) {
            return 'TEACHER_MY_TIMETABLE';
        }

        if ($subject || $this->containsAny($text, [
            'timetable', 'schedule', 'class', 'classes', 'today', 'tomorrow',
            'tmr', 'weekend', 'saturday', 'sunday', 'monday', 'tuesday',
            'wednesday', 'thursday', 'friday', 'ada', 'heta', 'thiyenawada',
            'doing', 'free', 'anything', 'come for',
        ])) {
            return $subject ? 'GET_SUBJECT_SCHEDULE' : 'GET_TIMETABLE';
        }

        return $this->scoreIntent($text, $subject);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTimetable(array $identity, array $parsed, bool $nextOnly): ?string
    {
        $courseIds = $this->classIdsForCourse($parsed);
        $classIds = $courseIds !== [] ? $courseIds : $identity['class_ids'];
        $fromCourse = $courseIds !== [];

        if ($classIds === []) {
            if (in_array($identity['role'], ['student', 'parent'], true) && !$fromCourse) {
                return "I couldn't find enrolled classes for this WhatsApp number yet.\nJoin a class in the portal, or reply *6* to register.";
            }
            $label = $this->courseLabel($parsed);
            return 'No published timetable for *' . $label . "* yet.\nJoin: "
                . WhatsAppGuideService::page('student/dashboard.php?tab=join');
        }

        $from = (string)($parsed['date_from'] ?? $this->today());
        $to = (string)($parsed['date_to'] ?? $from);
        if ($nextOnly) {
            $from = $this->today();
            $to = (new DateTimeImmutable($from, $this->tz))->modify('+21 days')->format('Y-m-d');
        }

        $fetchParsed = $parsed;
        if ($fromCourse) {
            $fetchParsed['subject_id'] = null;
        }

        $rows = $this->fetchTimetable($classIds, $from, $to, $fetchParsed);
        $rows = $this->mergeRecurringLessons($rows, $classIds, $from, $to);
        $rows = $this->verifyLessons($rows, $identity, $fromCourse);
        if ($nextOnly) {
            $now = new DateTimeImmutable('now', $this->tz);
            $rows = array_values(array_filter($rows, function (array $row) use ($now): bool {
                $start = new DateTimeImmutable($row['date'] . ' ' . $row['start_time'], $this->tz);
                return $start >= $now;
            }));
            $rows = array_slice($rows, 0, 1);
        }

        if ($rows === []) {
            $label = $parsed['date_label'] !== '' ? $parsed['date_label'] : 'that period';
            $subj = $parsed['subject_name'] !== '' ? $parsed['subject_name'] . ' ' : '';
            return "I couldn't find a timetable entry for {$subj}{$label}.";
        }

        if (!$fromCourse && !empty($parsed['subject_id'])) {
            $matched = [];
            foreach ($rows as $row) {
                if ((int)$row['subject_id'] === (int)$parsed['subject_id']) {
                    $matched[] = $row;
                }
            }
            if (count($matched) === 0) {
                $alts = [];
                foreach ($rows as $row) {
                    $alts[(int)$row['class_id']] = $row['class_name'];
                }
                if (count($alts) > 1 && $parsed['subject_name'] !== '') {
                    return 'You have more than one related class: '
                        . implode(', ', $alts)
                        . '. Which one do you mean?';
                }
            } else {
                $rows = $matched;
            }
        }

        $who = $identity['role'] === 'parent' && $identity['child_name'] !== ''
            ? $identity['child_name'] . "'s "
            : '';
        if ($nextOnly) {
            $title = 'Next class';
        } elseif ($fromCourse) {
            $title = $this->courseLabel($parsed) . ' timetable';
        } else {
            $title = trim((string)($parsed['date_label'] ?: 'Classes'));
        }
        return $this->formatLessons('📅 *' . $who . $title . '*', $rows);
    }

    /**
     * @param list<int> $classIds
     * @param array<string,mixed> $parsed
     * @return list<array<string,mixed>>
     */
    private function fetchTimetable(array $classIds, string $from, string $to, array $parsed): array
    {
        $in = implode(',', array_fill(0, count($classIds), '?'));
        $sql = "
            SELECT
                t.id, t.date, t.start_time, t.end_time, t.lesson_status, t.cancel_reason,
                t.subject_id, t.class_id, t.teacher_id, t.room_id,
                COALESCE(s.name, 'Subject') AS subject_name,
                COALESCE(c.name, 'Class') AS class_name,
                COALESCE(tc.name, 'Teacher') AS teacher_name,
                COALESCE(st.name, '') AS substitute_name,
                COALESCE(r.name, 'Room') AS room_name
            FROM timetable t
            LEFT JOIN subjects s ON s.id = t.subject_id
            LEFT JOIN student_classes c ON c.id = t.class_id
            LEFT JOIN teachers tc ON tc.id = t.teacher_id
            LEFT JOIN teachers st ON st.id = t.substitute_teacher_id
            LEFT JOIN rooms r ON r.id = t.room_id
            WHERE t.deleted_at IS NULL
              AND t.class_id IN ($in)
              AND t.date BETWEEN ? AND ?
        ";
        $params = array_merge($classIds, [$from, $to]);

        if (!empty($parsed['subject_id'])) {
            $sql .= ' AND t.subject_id = ?';
            $params[] = (int)$parsed['subject_id'];
        }
        if (!empty($parsed['teacher_id'])) {
            $sql .= ' AND (t.teacher_id = ? OR t.substitute_teacher_id = ?)';
            $params[] = (int)$parsed['teacher_id'];
            $params[] = (int)$parsed['teacher_id'];
        }
        if (($parsed['part'] ?? '') === 'morning') {
            $sql .= " AND t.start_time < '12:00:00'";
        } elseif (($parsed['part'] ?? '') === 'afternoon') {
            $sql .= " AND t.start_time >= '12:00:00' AND t.start_time < '17:00:00'";
        } elseif (($parsed['part'] ?? '') === 'evening') {
            $sql .= " AND t.start_time >= '17:00:00'";
        }

        $sql .= ' ORDER BY t.date, t.start_time LIMIT 40';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $sql = str_replace(
                ['t.lesson_status, t.cancel_reason,', 'COALESCE(st.name, \'\') AS substitute_name,', 'LEFT JOIN teachers st ON st.id = t.substitute_teacher_id'],
                ['', '', '']
            );
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                error_log('WA timetable: ' . $e2->getMessage());
                return [];
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function formatLessons(string $title, array $rows): string
    {
        $out = $title . "\n\n";
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            $status = strtolower((string)($row['lesson_status'] ?? 'scheduled'));
            $start = date('g:i A', strtotime((string)$row['start_time']));
            $end = date('g:i A', strtotime((string)$row['end_time']));
            $day = date('l, d M', strtotime((string)$row['date']));
            $prefix = count($rows) > 1 ? $i . '️⃣ ' : '';
            if ($status === 'cancelled') {
                $out .= $prefix . "🚫 Cancelled\n";
            } else {
                $out .= $prefix;
            }
            $out .= '📚 *' . ($row['subject_name'] ?? 'Class') . '*';
            if (!empty($row['class_name'])) {
                $out .= ' · ' . $row['class_name'];
            }
            $out .= "\n📅 " . $day;
            $out .= "\n🕒 " . $start . ' – ' . $end . "\n";
            $teacher = trim((string)($row['substitute_name'] ?? '')) !== ''
                ? $row['substitute_name'] . ' (substitute)'
                : (string)($row['teacher_name'] ?? '');
            if ($teacher !== '') {
                $out .= '👨‍🏫 ' . $teacher . "\n";
            }
            if (!empty($row['room_name'])) {
                $out .= '🚪 ' . $row['room_name'] . "\n";
            }
            if ($status === 'cancelled' && !empty($row['cancel_reason'])) {
                $out .= 'Reason: ' . $row['cancel_reason'] . "\n";
            } elseif ($status !== 'cancelled') {
                $out .= "The class is currently scheduled.\n";
            }
            $out .= "\n";
        }

        return trim($out);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeacherSchedule(array $identity, array $parsed): string
    {
        if (empty($parsed['teacher_id'])) {
            return $this->qTeachers($identity, $parsed);
        }

        $from = (string)($parsed['date_from'] ?? $this->today());
        $to = (string)($parsed['date_to'] ?? $from);
        $sql = "
            SELECT t.date, t.start_time, t.end_time, t.lesson_status,
                   s.name AS subject_name, c.name AS class_name,
                   tc.name AS teacher_name, r.name AS room_name,
                   t.subject_id, t.class_id, t.teacher_id
            FROM timetable t
            LEFT JOIN subjects s ON s.id = t.subject_id
            LEFT JOIN student_classes c ON c.id = t.class_id
            LEFT JOIN teachers tc ON tc.id = t.teacher_id
            LEFT JOIN rooms r ON r.id = t.room_id
            WHERE t.deleted_at IS NULL
              AND t.teacher_id = ?
              AND t.date BETWEEN ? AND ?
        ";
        $params = [(int)$parsed['teacher_id'], $from, $to];
        if ($identity['class_ids'] !== [] && !$identity['is_admin'] && $identity['role'] !== 'teacher') {
            $in = implode(',', array_fill(0, count($identity['class_ids']), '?'));
            $sql .= " AND t.class_id IN ($in)";
            $params = array_merge($params, $identity['class_ids']);
        }
        $sql .= ' ORDER BY t.date, t.start_time LIMIT 20';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return 'I could not load that teacher timetable right now.';
        }

        if ($rows === []) {
            return 'No upcoming classes found for *' . $parsed['teacher_name'] . '*.';
        }

        return $this->formatLessons('👨‍🏫 *' . $parsed['teacher_name'] . '*', $rows);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeachers(array $identity, array $parsed): string
    {
        return $this->formatTeacherDirectory($parsed, true);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeacherProfile(array $identity, array $parsed): string
    {
        if (empty($parsed['teacher_id'])) {
            return $this->formatTeacherDirectory($parsed, true);
        }
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name, t.phone, t.email, p.bio, p.qualifications, p.experience_years, p.achievements,
                   p.website, p.facebook, p.instagram, p.youtube
            FROM teachers t
            LEFT JOIN teacher_profiles p ON p.teacher_id = t.id
            WHERE t.id = ? AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([(int)$parsed['teacher_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 'I could not find that teacher.';
        }
        $out = $this->formatTeacherCard($row, true);
        if (!empty($row['qualifications'])) {
            $out .= "Qualifications: " . $row['qualifications'] . "\n";
        }
        if ($row['experience_years'] !== null && $row['experience_years'] !== '') {
            $out .= "Experience: " . (int)$row['experience_years'] . " years\n";
        }
        if (!empty($row['bio'])) {
            $out .= trim((string)$row['bio']) . "\n";
        }
        if (!empty($row['achievements'])) {
            $out .= "Achievements: " . $row['achievements'] . "\n";
        }
        foreach (['website' => 'Web', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $key => $label) {
            if (!empty($row[$key])) {
                $out .= "{$label}: " . $row[$key] . "\n";
            }
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeacherContact(array $identity, array $parsed): string
    {
        return $this->formatTeacherDirectory($parsed, true);
    }

    /**
     * Public teacher directory from the live teachers table. Phone/email are included
     * for registered and unregistered WhatsApp users.
     *
     * @param array<string,mixed> $parsed
     */
    private function formatTeacherDirectory(array $parsed, bool $withContact): string
    {
        $teacherId = (int)($parsed['teacher_id'] ?? 0);
        $subjectId = (int)($parsed['subject_id'] ?? 0);
        $subjectName = strtolower((string)($parsed['subject_name'] ?? ''));
        $text = strtolower((string)($parsed['text'] ?? ''));
        $asksIct = $this->textAsksIct($text) || str_contains($subjectName, 'ict');
        $namedTeacher = $teacherId > 0
            && ($parsed['teacher_name'] ?? '') !== ''
            && str_contains($text, mb_strtolower((string)$parsed['teacher_name']));

        if ($teacherId > 0 && !$namedTeacher && ($subjectId > 0 || $asksIct || ($parsed['programme'] ?? '') !== '')) {
            $teacherId = 0;
        }

        $courseIds = $this->classIdsForCourse($parsed);
        $rows = [];
        if ($teacherId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name, t.phone, t.email,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
                FROM teachers t
                LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.id
                LEFT JOIN subjects s ON s.id = ts.subject_id
                WHERE t.deleted_at IS NULL AND t.id = ?
                GROUP BY t.id, t.name, t.phone, t.email
            ");
            $stmt->execute([$teacherId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if ($rows === [] && ($subjectId > 0 || $asksIct || $subjectName !== '')) {
            $rows = $this->teachersLinkedToSubject($subjectId, $subjectName, $asksIct);
        }

        if ($rows === [] && $courseIds !== [] && $subjectId === 0 && !$asksIct && $subjectName === '') {
            $in = implode(',', array_fill(0, count($courseIds), '?'));
            try {
                $stmt = $this->pdo->prepare("
                    SELECT t.id, t.name, t.phone, t.email,
                           GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS subjects
                    FROM teachers t
                    INNER JOIN timetable tt ON tt.teacher_id = t.id AND tt.deleted_at IS NULL
                    LEFT JOIN student_classes c ON c.id = tt.class_id
                    WHERE t.deleted_at IS NULL AND tt.class_id IN ($in)
                    GROUP BY t.id, t.name, t.phone, t.email
                    ORDER BY t.name
                ");
                $stmt->execute($courseIds);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
        }

        if ($rows === [] && ($subjectId > 0 || $asksIct || $subjectName !== '')) {
            $label = $this->courseLabel($parsed);
            return 'No teacher is linked to *' . $label . '* in the database yet.';
        }

        if ($rows === []) {
            $stmt = $this->pdo->query("
                SELECT t.id, t.name, t.phone, t.email,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
                FROM teachers t
                LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.id
                LEFT JOIN subjects s ON s.id = ts.subject_id
                WHERE t.deleted_at IS NULL
                GROUP BY t.id, t.name, t.phone, t.email
                ORDER BY t.name
            ");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        if (!$rows) {
            return 'No teachers are in the database yet.';
        }

        $label = 'Edexcel College teachers';
        if ($teacherId > 0 && !empty($rows[0]['name'])) {
            $label = (string)$rows[0]['name'];
        } elseif (($parsed['subject_name'] ?? '') !== '' || ($parsed['programme'] ?? '') !== '') {
            $label = $this->courseLabel($parsed) . ' teachers';
        }

        $out = '👨‍🏫 *' . $label . "*\n\n";
        foreach (array_slice($rows, 0, 40) as $row) {
            $out .= $this->formatTeacherCard($row, $withContact) . "\n";
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function formatTeacherCard(array $row, bool $withContact): string
    {
        $out = '• *' . $row['name'] . "*\n";
        if (!empty($row['subjects'])) {
            $out .= '  📚 ' . $row['subjects'] . "\n";
        }
        if ($withContact) {
            $digits = preg_replace('/\D+/', '', (string)($row['phone'] ?? '')) ?? '';
            if ($digits !== '') {
                if (str_starts_with($digits, '0') && strlen($digits) === 10) {
                    $digits = '94' . substr($digits, 1);
                }
                $out .= '  📞 ' . (string)$row['phone'] . "\n";
                $out .= '  WhatsApp: https://wa.me/' . $digits . "\n";
            } else {
                $out .= "  📞 No phone is saved for this teacher.\n";
            }
            if (!empty($row['email'])) {
                $out .= '  ✉️ ' . $row['email'] . "\n";
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qAttendance(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('attendance');
        }
        $studentId = (int)$identity['student_id'];
        $from = (string)($parsed['date_from'] ?? (new DateTimeImmutable('first day of this month', $this->tz))->format('Y-m-d'));
        $to = (string)($parsed['date_to'] ?? $this->today());

        $sql = "
            SELECT sa.status, tt.date, s.name AS subject_name
            FROM student_attendance sa
            JOIN timetable tt ON tt.id = sa.timetable_id
            LEFT JOIN subjects s ON s.id = tt.subject_id
            WHERE sa.student_id = ? AND tt.date BETWEEN ? AND ?
        ";
        $params = [$studentId, $from, $to];
        if (!empty($parsed['subject_id'])) {
            $sql .= ' AND tt.subject_id = ?';
            $params[] = (int)$parsed['subject_id'];
        }
        $sql .= ' ORDER BY tt.date DESC, tt.start_time DESC LIMIT 40';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return 'Attendance records are not available right now.';
        }

        $who = $identity['role'] === 'parent' ? ($identity['child_name'] ?: 'Your child') : 'You';
        if ($rows === []) {
            return "{$who}: no attendance marks between {$from} and {$to}.";
        }
        $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($rows as $row) {
            $st = (string)($row['status'] ?? '');
            if (isset($counts[$st])) {
                $counts[$st]++;
            }
        }
        $total = max(1, array_sum($counts));
        $pct = round(100 * ($counts['present'] + $counts['late']) / $total);
        $out = "✅ *Attendance*\n{$who}: {$pct}% present/late ({$counts['present']} present, {$counts['absent']} absent, {$counts['late']} late)\n\n";
        foreach (array_slice($rows, 0, 12) as $row) {
            $out .= date('d M', strtotime((string)$row['date'])) . ' '
                . ($row['subject_name'] ?? 'Class') . ' — ' . $row['status'] . "\n";
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qAttendanceDispute(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('attendance');
        }
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO student_notifications (student_id, title, message, type)
                VALUES (?, 'Attendance check requested', ?, 'warning')
            ");
            $stmt->execute([
                (int)$identity['student_id'],
                'WhatsApp ' . ($parsed['original'] ?? '') . ' from ' . ($identity['child_name'] ?: 'student'),
            ]);
        } catch (Throwable $e) {
            error_log('attendance dispute: ' . $e->getMessage());
        }
        return "I've logged an attendance check request for the office. I won't change the mark myself.\nReply *7* if you need to speak to someone now.";
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qFees(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('fees');
        }
        if (!function_exists('campus_fee_summary')) {
            require_once dirname(__DIR__, 2) . '/config/campus.php';
        }
        $wallet = campus_fee_summary($this->pdo, (int)$identity['student_id']);
        $who = $identity['role'] === 'parent' ? ($identity['child_name'] ?: 'Your child') : 'You';
        $due = (float)($wallet['due'] ?? 0);
        $paid = (float)($wallet['paid'] ?? 0);
        $out = "💰 *Fees*\n{$who}\nDue: Rs " . number_format($due)
            . "\nPaid: Rs " . number_format($paid)
            . "\n\nPay at the *college counter*. A WhatsApp receipt is sent when admin marks it paid.\n"
            . "Portal: " . WhatsAppGuideService::page('student/dashboard.php?tab=fees');

        $rows = $wallet['rows'] ?? [];
        $breakdown = $wallet['breakdown'] ?? [];
        if (is_array($breakdown) && $breakdown !== []) {
            $out .= "\n\n*Due by class and date*\n";
            foreach ($breakdown as $group) {
                $out .= '*' . ($group['class_name'] ?? 'Class') . '* — Rs ' . number_format((float)($group['total'] ?? 0)) . "\n";
                foreach ($group['lessons'] ?? [] as $lesson) {
                    $d = (string)($lesson['date'] ?? '');
                    $when = $d !== '' ? date('d M Y', strtotime($d)) : '';
                    $out .= '• ' . $when . ' — Rs ' . number_format((float)($lesson['amount'] ?? 0)) . "\n";
                }
            }
        } elseif (is_array($rows) && $rows !== []) {
            $out .= "\n\nRecent:\n";
            foreach (array_slice($rows, 0, 6) as $row) {
                $out .= ($row['period_ym'] ?? '') . ' '
                    . ($row['class_name'] ?? $row['description'] ?? '')
                    . ' — ' . ($row['status'] ?? '')
                    . ' (due Rs ' . number_format((float)($row['amount_due'] ?? 0))
                    . ', paid Rs ' . number_format((float)($row['amount_paid'] ?? 0)) . ")\n";
            }
        }
        if ($this->containsAny($parsed['text'], ['already paid', 'isnt showing', "isn't showing", 'online'])) {
            $out .= "\nIf you already paid your teacher or the counter, they must mark it on Class fees. I cannot record cash from WhatsApp. Reply *7*.";
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qExams(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('exams');
        }
        if (!function_exists('campus_student_exams')) {
            require_once dirname(__DIR__, 2) . '/config/campus.php';
        }
        $official = '';
        try {
            $official = (new OfficialExamService($this->pdo))->whatsappTimetable((int)$identity['student_id'], 8);
        } catch (Throwable $e) {
            $official = '';
        }
        $from = (string)($parsed['date_from'] ?? $this->today());
        $to = (string)($parsed['date_to'] ?? '');
        $rows = campus_student_exams(
            $this->pdo,
            $identity['class_ids'],
            $from,
            $to !== '' ? $to : null,
            12
        );
        if ($rows === [] && $official === '') {
            return 'No official papers on your planner yet, and no college mocks are published. Open Student portal → Exams to add Pearson papers.';
        }
        $out = $official !== '' ? $official . "\n\n" : '';
        if ($rows === []) {
            return trim($out);
        }
        $out .= "🏫 *College mocks / class exams*\n\n";
        foreach ($rows as $row) {
            if (!empty($parsed['subject_id']) && (int)($row['subject_id'] ?? 0) !== (int)$parsed['subject_id']) {
                continue;
            }
            $out .= '*' . ($row['title'] ?? 'Paper') . "*\n";
            $out .= ($row['exam_date'] ?? '') . ' ' . ($row['exam_time'] ?? '') . "\n";
            if (!empty($row['class_name'])) {
                $out .= $row['class_name'] . "\n";
            }
            $venue = trim((string)($row['room_name'] ?? $row['location'] ?? ''));
            if ($venue !== '') {
                $out .= "Room: {$venue}\n";
            }
            $out .= "\n";
        }
        $trimmed = trim($out);
        return $trimmed !== '🏫 *College mocks / class exams*' ? $trimmed : 'No matching exam for that subject.';
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qHomework(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('homework');
        }
        if ($identity['class_ids'] === []) {
            return 'No classes enrolled, so there is no homework list yet.';
        }
        $in = implode(',', array_fill(0, count($identity['class_ids']), '?'));
        $sql = "
            SELECT h.title, h.description, h.due_date, h.link, s.name AS subject_name, t.name AS teacher_name
            FROM student_homework h
            LEFT JOIN subjects s ON s.id = h.subject_id
            LEFT JOIN teachers t ON t.id = h.teacher_id
            WHERE (h.class_id IN ($in) OR h.class_id IS NULL)
        ";
        $params = $identity['class_ids'];
        if (!empty($parsed['subject_id'])) {
            $sql .= ' AND (h.subject_id = ? OR h.subject_id IS NULL)';
            $params[] = (int)$parsed['subject_id'];
        }
        $sql .= ' ORDER BY h.due_date IS NULL, h.due_date ASC, h.id DESC LIMIT 15';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return 'Homework is not available in the database yet.';
        }
        if ($rows === []) {
            return 'No homework has been posted for your classes yet.';
        }
        $out = "📌 *Homework*\n\n";
        foreach ($rows as $row) {
            $out .= '*' . ($row['title'] ?: 'Task') . "*\n";
            if (!empty($row['subject_name'])) {
                $out .= $row['subject_name'] . "\n";
            }
            if (!empty($row['due_date'])) {
                $out .= 'Due ' . date('d M', strtotime((string)$row['due_date'])) . "\n";
            }
            if (!empty($row['link'])) {
                $out .= $row['link'] . "\n";
            }
            if (!empty($row['description'])) {
                $out .= trim((string)$row['description']) . "\n";
            }
            $out .= "\n";
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qMaterials(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('notes');
        }
        if ($identity['class_ids'] === []) {
            return 'Join a class first to receive notes.';
        }
        $in = implode(',', array_fill(0, count($identity['class_ids']), '?'));
        $sql = "
            SELECT m.title, m.file_url, m.description, s.name AS subject_name, m.created_at
            FROM student_materials m
            LEFT JOIN subjects s ON s.id = m.subject_id
            WHERE (m.class_id IN ($in) OR m.class_id IS NULL)
        ";
        $params = $identity['class_ids'];
        if (!empty($parsed['subject_id'])) {
            $sql .= ' AND (m.subject_id = ? OR m.subject_id IS NULL)';
            $params[] = (int)$parsed['subject_id'];
        }
        $sql .= ' ORDER BY m.created_at DESC LIMIT 12';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return 'Study materials are not in the database yet.';
        }
        if ($rows === []) {
            return 'No notes or PDFs have been uploaded for your classes yet.';
        }
        $out = "📎 *Study materials*\n\n";
        foreach ($rows as $row) {
            $out .= '*' . ($row['title'] ?: 'File') . "*\n";
            if (!empty($row['subject_name'])) {
                $out .= $row['subject_name'] . "\n";
            }
            if (!empty($row['file_url'])) {
                $out .= $row['file_url'] . "\n";
            }
            $out .= "\n";
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qProgress(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('progress');
        }
        $sql = "
            SELECT p.metric, p.score, p.max_score, p.recorded_at, p.note, s.name AS subject_name
            FROM student_progress p
            LEFT JOIN subjects s ON s.id = p.subject_id
            WHERE p.student_id = ?
              AND p.published = 1
        ";
        $params = [(int)$identity['student_id']];
        if (!empty($parsed['subject_id'])) {
            $sql .= ' AND p.subject_id = ?';
            $params[] = (int)$parsed['subject_id'];
        }
        $sql .= ' ORDER BY p.recorded_at DESC, p.id DESC LIMIT 12';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return 'Progress scores have not been recorded yet.';
        }
        if ($rows === []) {
            return 'No test scores are saved for this student yet.';
        }
        $who = $identity['role'] === 'parent' ? ($identity['child_name'] ?: 'Your child') : 'You';
        $out = "📈 *Progress — {$who}*\n\n";
        foreach ($rows as $row) {
            $out .= ($row['subject_name'] ?: $row['metric']) . ': '
                . $row['score'] . '/' . $row['max_score']
                . ' (' . date('d M', strtotime((string)$row['recorded_at'])) . ")\n";
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $identity
     */
    private function qMyClasses(array $identity): string
    {
        if (!$this->requireStudent($identity)) {
            return $this->needAccount('classes');
        }
        if ($identity['class_ids'] === []) {
            return "You're not enrolled in a class yet. Open "
                . WhatsAppGuideService::page('student/dashboard.php?tab=join');
        }
        $in = implode(',', array_fill(0, count($identity['class_ids']), '?'));
        $stmt = $this->pdo->prepare("
            SELECT name, description FROM student_classes
            WHERE id IN ($in) AND deleted_at IS NULL ORDER BY name
        ");
        $stmt->execute($identity['class_ids']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = "📚 *Enrolled classes*\n\n";
        foreach ($rows as $row) {
            $out .= '• *' . $row['name'] . "*\n";
        }
        $out .= "\nJoin/leave: " . WhatsAppGuideService::page('student/dashboard.php?tab=join');
        return $out;
    }

    /**
     * @param array<string,mixed> $parsed
     */
    /**
     * @return list<array<string,mixed>>
     */
    public function classCatalogue(): array
    {
        $queries = [
            "
                SELECT
                    c.id,
                    c.name,
                    c.description,
                    c.capacity,
                    l.name AS level_name,
                    GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects,
                    (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = c.id) AS enrolled
                FROM student_classes c
                LEFT JOIN levels l ON l.id = c.level_id
                LEFT JOIN subject_classes sc ON sc.class_id = c.id
                LEFT JOIN subjects s ON s.id = sc.subject_id
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.description, c.capacity, l.name
                ORDER BY c.name
            ",
            "
                SELECT
                    c.id,
                    c.name,
                    c.description,
                    NULL AS capacity,
                    l.name AS level_name,
                    GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects,
                    0 AS enrolled
                FROM student_classes c
                LEFT JOIN levels l ON l.id = c.level_id
                LEFT JOIN subject_classes sc ON sc.class_id = c.id
                LEFT JOIN subjects s ON s.id = sc.subject_id
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.description, l.name
                ORDER BY c.name
            ",
            "
                SELECT
                    c.id,
                    c.name,
                    c.description,
                    NULL AS capacity,
                    NULL AS level_name,
                    NULL AS subjects,
                    0 AS enrolled
                FROM student_classes c
                WHERE c.deleted_at IS NULL
                ORDER BY c.name
            ",
        ];

        foreach ($queries as $sql) {
            try {
                $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return [];
    }

    public function classCatalogueText(): string
    {
        $rows = $this->classCatalogue();
        if ($rows === []) {
            return 'LIVE AVAILABLE CLASSES: none listed.';
        }
        $out = "LIVE AVAILABLE CLASSES (match programme from class name OR level; match subject from class name OR subjects):\n";
        foreach ($rows as $row) {
            $out .= '- class: ' . $row['name']
                . ' | level: ' . ($row['level_name'] ?: '-')
                . ' | subjects: ' . ($row['subjects'] ?: '-')
                . "\n";
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $parsed
     * @return list<int>
     */
    private function classIdsForCourse(array $parsed): array
    {
        $programme = strtolower((string)($parsed['programme'] ?? ''));
        $subjectName = strtolower((string)($parsed['subject_name'] ?? ''));
        $text = strtolower((string)($parsed['text'] ?? ''));
        $asksIct = $this->textAsksIct($text) || str_contains($subjectName, 'ict');
        if ($programme === '' && $subjectName === '' && !$asksIct) {
            return [];
        }

        $ids = [];
        foreach ($this->classCatalogue() as $row) {
            $blob = $this->classSearchBlob($row);
            $okProgramme = $this->blobHasProgramme($blob, $programme);
            $okSubject = $asksIct
                ? $this->blobLooksLikeIct($blob)
                : ($subjectName === '' || $this->blobHasSubjectName($blob, $subjectName));
            if ($okProgramme && $okSubject) {
                $ids[] = (int)$row['id'];
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function qAvailableClasses(array $parsed): string
    {
        $rows = $this->classCatalogue();
        if ($rows === []) {
            return 'No classes are listed yet. Reply *6* to register and *7* for the office.';
        }

        $text = strtolower((string)($parsed['text'] ?? ''));
        $programme = strtolower((string)($parsed['programme'] ?? ''));
        $subjectName = strtolower((string)($parsed['subject_name'] ?? ''));
        $asksIct = $this->textAsksIct($text);

        $matched = [];
        $close = [];
        foreach ($rows as $row) {
            $blob = $this->classSearchBlob($row);
            $okProgramme = $this->blobHasProgramme($blob, $programme);
            $okSubject = $asksIct
                ? $this->blobLooksLikeIct($blob)
                : ($subjectName === '' || $this->blobHasSubjectName($blob, $subjectName));

            if ($okProgramme && $okSubject) {
                $matched[] = $row;
            } elseif ($programme !== '' && ($asksIct || $subjectName !== '') && ($okProgramme || $okSubject)) {
                $close[] = $row;
            }
        }

        $join = WhatsAppGuideService::page('student/dashboard.php?tab=join');
        $register = WhatsAppGuideService::page('student/register.php');
        $label = $this->courseLabel($parsed);

        if ($programme === '' && $subjectName === '' && !$asksIct) {
            $out = "🏫 *Available classes*\n\n";
            foreach (array_slice($rows, 0, 18) as $row) {
                $out .= $this->formatClassOffer($row);
            }
            $out .= "\nJoin: {$join}\nRegister: {$register}";
            return $out;
        }

        if ($matched !== []) {
            $out = 'Yes — we offer *' . $label . "*:\n\n";
            foreach (array_slice($matched, 0, 12) as $row) {
                $out .= $this->formatClassOffer($row);
            }
            $out .= "\nTo join: {$join}\nRegister if you do not have an account: {$register}";
            return $out;
        }

        $out = 'I could not find *' . $label . "* as a listed class.\n";
        if ($close !== []) {
            $out .= "\nClosest matches:\n";
            foreach (array_slice($close, 0, 8) as $row) {
                $out .= $this->formatClassOffer($row);
            }
        }
        $out .= "\nRegister: {$register}\nJoin: {$join}\nOr reply *7* for the office.";
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function teachersLinkedToSubject(int $subjectId, string $subjectName, bool $asksIct): array
    {
        $byId = [];
        $likes = [];
        if ($asksIct) {
            $likes = ['%ict%', '%information and communication%', '%information technology%'];
        } elseif ($subjectName !== '') {
            $likes = ['%' . $subjectName . '%'];
        }

        try {
            $sql = "
                SELECT t.id, t.name, t.phone, t.email,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
                FROM teachers t
                JOIN teacher_subjects ts ON ts.teacher_id = t.id
                JOIN subjects s ON s.id = ts.subject_id
                WHERE t.deleted_at IS NULL AND (
                    (? > 0 AND ts.subject_id = ?)
            ";
            $params = [$subjectId, $subjectId];
            foreach ($likes as $like) {
                $sql .= ' OR LOWER(s.name) LIKE ?';
                $params[] = $like;
            }
            $sql .= ') GROUP BY t.id, t.name, t.phone, t.email ORDER BY t.name';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $byId[(int)$row['id']] = $row;
            }
        } catch (Throwable $e) {
        }

        try {
            $sql = "
                SELECT t.id, t.name, t.phone, t.email,
                       GROUP_CONCAT(DISTINCT COALESCE(s.name, c.name) ORDER BY s.name SEPARATOR ', ') AS subjects
                FROM teachers t
                INNER JOIN timetable tt ON tt.teacher_id = t.id AND tt.deleted_at IS NULL
                LEFT JOIN subjects s ON s.id = tt.subject_id
                LEFT JOIN student_classes c ON c.id = tt.class_id
                WHERE t.deleted_at IS NULL AND (
                    (? > 0 AND tt.subject_id = ?)
            ";
            $params = [$subjectId, $subjectId];
            foreach ($likes as $like) {
                $sql .= ' OR LOWER(s.name) LIKE ? OR LOWER(c.name) LIKE ?';
                $params[] = $like;
                $params[] = $like;
            }
            $sql .= ') GROUP BY t.id, t.name, t.phone, t.email ORDER BY t.name';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int)$row['id'];
                if (!isset($byId[$id])) {
                    $byId[$id] = $row;
                }
            }
        } catch (Throwable $e) {
        }

        return array_values($byId);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function classSearchBlob(array $row): string
    {
        return strtolower(trim(
            (string)($row['name'] ?? '') . ' '
            . (string)($row['description'] ?? '') . ' '
            . (string)($row['level_name'] ?? '') . ' '
            . (string)($row['subjects'] ?? '')
        ));
    }

    /**
     * @param array<string,mixed> $row
     */
    private function formatClassOffer(array $row): string
    {
        $line = '• *' . $row['name'] . '*';
        if (!empty($row['level_name'])) {
            $line .= ' — ' . $row['level_name'];
        }
        $cap = $row['capacity'] !== null && $row['capacity'] !== '' ? (int)$row['capacity'] : 0;
        $en = (int)($row['enrolled'] ?? 0);
        if ($cap > 0) {
            $line .= $en >= $cap ? ' (full / waitlist)' : " ({$en}/{$cap} enrolled)";
        }
        if (!empty($row['subjects'])) {
            $line .= "\n  " . $row['subjects'];
        }
        return $line . "\n";
    }

    /**
     * @param array<string,mixed> $parsed
     */
    private function courseLabel(array $parsed): string
    {
        $programme = trim((string)($parsed['programme'] ?? ''));
        $text = strtolower((string)($parsed['text'] ?? ''));
        $subject = $this->textAsksIct($text) ? 'ICT' : trim((string)($parsed['subject_name'] ?? ''));
        $label = trim($programme . ' ' . $subject);
        return $label !== '' ? $label : 'that course';
    }

    private function textAsksIct(string $text): bool
    {
        return (bool)preg_match('/\bict\b/', $text)
            || str_contains($text, 'information and communication')
            || str_contains($text, 'information technology');
    }

    private function blobLooksLikeIct(string $blob): bool
    {
        return (bool)preg_match('/\bict\b/', $blob)
            || str_contains($blob, 'information and communication')
            || str_contains($blob, 'information communication')
            || str_contains($blob, 'information technology')
            || str_contains($blob, '0417');
    }

    private function blobHasSubjectName(string $blob, string $subjectName): bool
    {
        $subjectName = trim($subjectName);
        if ($subjectName === '') {
            return true;
        }
        if (str_contains($subjectName, 'ict') || $subjectName === 'it') {
            return $this->blobLooksLikeIct($blob);
        }
        if (str_contains($blob, $subjectName)) {
            return true;
        }
        $parts = preg_split('/\s+/', $subjectName) ?: [];
        $last = strtolower((string)end($parts));
        return $last !== '' && strlen($last) > 2 && str_contains($blob, $last);
    }

    private function blobHasProgramme(string $blob, string $programme): bool
    {
        if ($programme === '') {
            return true;
        }
        if ($programme === 'as') {
            return (bool)preg_match('/\bas\b/', $blob)
                || str_contains($blob, 'a-level')
                || str_contains($blob, 'a level');
        }
        if ($programme === 'igcse') {
            return str_contains($blob, 'igcse')
                || str_contains($blob, 'i.g.c.s.e');
        }
        return str_contains($blob, $programme);
    }

    private function extractProgramme(string $text): string
    {
        if (str_contains($text, 'igcse')) {
            return 'IGCSE';
        }
        if (preg_match('/\bial\b/', $text)) {
            return 'IAL';
        }
        if (preg_match('/\bas\b/', $text) || str_contains($text, 'a level') || str_contains($text, 'a-level')) {
            return 'AS';
        }
        if (preg_match('/year\s*([678])/', $text, $m)) {
            return 'Year ' . $m[1];
        }
        return '';
    }

    /**
     * @param array<string,mixed> $parsed
     */
    private function qCapacity(array $parsed): string
    {
        return $this->qAvailableClasses($parsed)
            . "\n\nIf a class is full, join the waitlist from the same Join tab. When a seat opens you get 24 hours to accept the offer.";
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qGroupLink(array $identity, array $parsed): string
    {
        if (!$this->requireStudent($identity) || $identity['class_ids'] === []) {
            return $this->needAccount('class WhatsApp group');
        }
        $in = implode(',', array_fill(0, count($identity['class_ids']), '?'));
        $links = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT name, whatsapp_link FROM student_classes
                WHERE id IN ($in) AND whatsapp_link IS NOT NULL AND whatsapp_link <> ''
            ");
            $stmt->execute($identity['class_ids']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $links[] = '*' . $row['name'] . "*\n" . $row['whatsapp_link'];
            }
            $stmt = $this->pdo->prepare("
                SELECT c.name, w.whatsapp_link
                FROM class_teacher_whatsapp w
                JOIN student_classes c ON c.id = w.class_id
                WHERE w.class_id IN ($in) AND w.whatsapp_link IS NOT NULL AND w.whatsapp_link <> ''
            ");
            $stmt->execute($identity['class_ids']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $links[] = '*' . $row['name'] . "*\n" . $row['whatsapp_link'];
            }
        } catch (Throwable $e) {
            return 'I could not load group links right now.';
        }
        $links = array_values(array_unique($links));
        if ($links === []) {
            return "No WhatsApp group link is saved for your classes yet. Join the class in the portal or reply *7*.";
        }
        return "💬 *Class WhatsApp groups*\n\n" . implode("\n\n", $links);
    }

    /**
     * @param array<string,mixed> $parsed
     */
    private function qHolidays(array $parsed): string
    {
        $from = (string)($parsed['date_from'] ?? $this->today());
        try {
            $stmt = $this->pdo->prepare("
                SELECT date, name FROM holidays
                WHERE date >= ? ORDER BY date LIMIT 10
            ");
            $stmt->execute([$from]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            try {
                $stmt = $this->pdo->prepare("SELECT date FROM holidays WHERE date >= ? ORDER BY date LIMIT 10");
                $stmt->execute([$from]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                return 'Holiday calendar is not available.';
            }
        }
        if ($rows === []) {
            return 'No upcoming holiday is listed. Reply *7* to confirm with the office.';
        }
        $out = "📅 *Holidays*\n\n";
        foreach ($rows as $row) {
            $out .= '• ' . date('D d M Y', strtotime((string)$row['date']));
            if (!empty($row['name'])) {
                $out .= ' — ' . $row['name'];
            }
            $out .= "\n";
        }
        $out .= "\nClass reminders are not sent on holiday dates.";
        return $out;
    }

    private function qRegister(): string
    {
        return "🎓 *How to register*\n\n"
            . "Open this link and follow the instructions:\n"
            . WhatsAppGuideService::page('student/register.php') . "\n\n"
            . "Login: " . WhatsAppGuideService::page('student/login.php');
    }

    private function qNews(): string
    {
        foreach (['news', 'announcements', 'student_notifications'] as $table) {
            try {
                if ($table === 'student_notifications') {
                    $stmt = $this->pdo->query("
                        SELECT title, message, created_at FROM student_notifications
                        WHERE student_id IS NULL ORDER BY created_at DESC LIMIT 5
                    ");
                } else {
                    $stmt = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 5");
                }
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                if (!$rows) {
                    continue;
                }
                $out = "📢 *Updates*\n\n";
                foreach ($rows as $row) {
                    $title = $row['title'] ?? $row['headline'] ?? $row['name'] ?? 'Update';
                    $out .= '*' . $title . "*\n";
                    $msg = trim((string)($row['message'] ?? $row['body'] ?? $row['content'] ?? ''));
                    if ($msg !== '') {
                        $out .= mb_substr($msg, 0, 280) . "\n";
                    }
                    $out .= "\n";
                }
                return trim($out);
            } catch (Throwable $e) {
                continue;
            }
        }
        return 'No announcements are posted yet. Reply *7* for the office.';
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeacherOwnTimetable(array $identity, array $parsed): string
    {
        if (empty($identity['teacher_id'])) {
            return 'This WhatsApp is not linked to a teacher profile.';
        }
        $parsed['teacher_id'] = $identity['teacher_id'];
        $parsed['class_ids_bypass'] = true;
        $from = (string)($parsed['date_from'] ?? $this->today());
        $to = (string)($parsed['date_to'] ?? $from);
        $stmt = $this->pdo->prepare("
            SELECT t.date, t.start_time, t.end_time, t.lesson_status,
                   s.name AS subject_name, c.name AS class_name,
                   tc.name AS teacher_name, r.name AS room_name
            FROM timetable t
            LEFT JOIN subjects s ON s.id = t.subject_id
            LEFT JOIN student_classes c ON c.id = t.class_id
            LEFT JOIN teachers tc ON tc.id = t.teacher_id
            LEFT JOIN rooms r ON r.id = t.room_id
            WHERE t.deleted_at IS NULL AND t.teacher_id = ? AND t.date BETWEEN ? AND ?
            ORDER BY t.date, t.start_time
        ");
        $stmt->execute([(int)$identity['teacher_id'], $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return 'No classes on your timetable for that date.';
        }
        return $this->formatLessons('👩‍🏫 *Your classes*', $rows);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeacherStudents(array $identity, array $parsed): string
    {
        if (empty($identity['teacher_id'])) {
            return 'Teacher WhatsApp is not linked.';
        }
        $date = (string)($parsed['date_from'] ?? $this->today());
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT c.id, c.name
            FROM timetable t
            JOIN student_classes c ON c.id = t.class_id
            WHERE t.teacher_id = ? AND t.date = ? AND t.deleted_at IS NULL
        ");
        $stmt->execute([(int)$identity['teacher_id'], $date]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($classes === []) {
            return 'No class of yours is scheduled that day.';
        }
        $out = "👥 *Students ({$date})*\n\n";
        foreach ($classes as $class) {
            $st = $this->pdo->prepare("
                SELECT u.username, sp.full_name
                FROM student_enrollments se
                JOIN users u ON u.id = se.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE se.class_id = ?
                ORDER BY sp.full_name, u.username
                LIMIT 40
            ");
            $st->execute([(int)$class['id']]);
            $out .= '*' . $class['name'] . "*\n";
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out .= '• ' . ($row['full_name'] ?: $row['username']) . "\n";
            }
            $out .= "\n";
        }
        $out .= "Mark attendance in the portal: " . WhatsAppGuideService::page('campus/attendance.php');
        return $out;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function qTeacherPayments(array $identity, array $parsed): string
    {
        if (empty($identity['teacher_id'])) {
            return 'Teacher WhatsApp is not linked.';
        }
        $monthStart = (new DateTimeImmutable('first day of this month', $this->tz))->format('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT payment_status,
                   COUNT(*) AS n,
                   SUM(class_fee_per_student * student_count) AS gross
            FROM timetable
            WHERE teacher_id = ? AND deleted_at IS NULL AND date >= ?
            GROUP BY payment_status
        ");
        $stmt->execute([(int)$identity['teacher_id'], $monthStart]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return 'No paid/pending class rows this month.';
        }
        $out = "💳 *Your classes this month*\n\n";
        foreach ($rows as $row) {
            $out .= ($row['payment_status'] ?: 'unknown') . ': '
                . (int)$row['n'] . ' classes, Rs ' . number_format((float)$row['gross']) . "\n";
        }
        $out .= "\nOfficial settlement is done by admin. Reply *7* for payroll questions.";
        return $out;
    }

    /**
     * @param array<string,mixed> $identity
     */
    private function qAdminStats(array $identity): string
    {
        if (!$identity['is_admin']) {
            return $this->refuse();
        }
        $students = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND deleted_at IS NULL")->fetchColumn();
        $teachers = (int)$this->pdo->query("SELECT COUNT(*) FROM teachers WHERE deleted_at IS NULL")->fetchColumn();
        $classes = (int)$this->pdo->query("SELECT COUNT(*) FROM student_classes WHERE deleted_at IS NULL")->fetchColumn();
        $today = (int)$this->pdo->query("SELECT COUNT(*) FROM timetable WHERE deleted_at IS NULL AND date = CURDATE()")->fetchColumn();
        return "📊 *Admin snapshot*\nStudents: {$students}\nTeachers: {$teachers}\nClasses: {$classes}\nLessons today: {$today}";
    }

    private function refuse(): string
    {
        return "I can't share other people's private data (phones, fees, attendance, or full student lists).\nAsk about *your* classes, or reply *7* for admin.";
    }

    /**
     * @param array<string,mixed> $identity
     */
    private function requireStudent(array $identity): bool
    {
        return !empty($identity['student_id']);
    }

    private function needAccount(string $topic): string
    {
        return "I can only show *your* {$topic} after this WhatsApp is linked to a student (or the child's parent number).\nRegister: "
            . WhatsAppGuideService::page('student/register.php')
            . "\nOr reply *7*.";
    }

    private function expandSlang(string $text): string
    {
        $map = [
            'tmr' => 'tomorrow',
            'tmrw' => 'tomorrow',
            'tomo' => 'tomorrow',
            'tod' => 'today',
            'tody' => 'today',
            'ada' => 'today',
            'heta' => 'tomorrow',
            'maths' => 'mathematics',
            'math' => 'mathematics',
            'phy' => 'physics',
            'chem' => 'chemistry',
            'bio' => 'biology',
            'cs' => 'computer science',
            'acc' => 'accounting',
            'eco' => 'economics',
            'bus' => 'business',
            'eng' => 'english',
            'fm' => 'further mathematics',
            'thiyenawada' => 'class',
            'thiyenawa' => 'class',
            'ekak' => '',
            'eka' => '',
            'koheda' => 'where',
            'kohomada' => 'how',
            'kawda' => 'who',
            'denna' => 'number',
            'mage' => 'my',
        ];
        $text = ' ' . $text . ' ';
        foreach ($map as $from => $to) {
            $text = preg_replace('/\b' . preg_quote($from, '/') . '\b/u', $to, $text) ?? $text;
        }
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function isFollowUp(string $text, string $original): bool
    {
        $raw = trim(mb_strtolower($original));
        if (in_array($raw, ['who', 'where', 'room', 'tomorrow', 'today', 'weekend', 'and', 'sir', '?'], true)) {
            return true;
        }
        return $this->containsAny($text, [
            'what about', 'next week', 'and that', 'the room', 'who teaches it',
            'class times', 'timetable', 'teachers',
        ]);
    }

    private function isCourseFollowOn(string $text): bool
    {
        return $this->containsAny($text, [
            'class times', 'class time', 'timetable', 'time table',
            'teachers', 'who teach', 'who teaches', 'how does teach',
        ]);
    }

    /**
     * @return array{from:string,to:string,label:string}|null
     */
    private function extractDateRange(string $text): ?array
    {
        $today = new DateTimeImmutable('now', $this->tz);
        if ($this->containsAny($text, ['today', 'tonight', 'this evening', 'this morning', 'this afternoon'])) {
            $d = $today->format('Y-m-d');
            return ['from' => $d, 'to' => $d, 'label' => 'Today'];
        }
        if ($this->containsAny($text, ['tomorrow'])) {
            $d = $today->modify('+1 day')->format('Y-m-d');
            return ['from' => $d, 'to' => $d, 'label' => 'Tomorrow'];
        }
        if ($this->containsAny($text, ['yesterday'])) {
            $d = $today->modify('-1 day')->format('Y-m-d');
            return ['from' => $d, 'to' => $d, 'label' => 'Yesterday'];
        }
        if ($this->containsAny($text, ['this weekend', 'weekend'])) {
            $sat = $today->modify('saturday this week');
            if ($sat < $today && (int)$today->format('N') !== 6) {
                $sat = $today->modify('saturday next week');
            }
            $sun = $sat->modify('+1 day');
            return ['from' => $sat->format('Y-m-d'), 'to' => $sun->format('Y-m-d'), 'label' => 'This weekend'];
        }
        if ($this->containsAny($text, ['this week', 'weekly'])) {
            return [
                'from' => $today->modify('monday this week')->format('Y-m-d'),
                'to' => $today->modify('sunday this week')->format('Y-m-d'),
                'label' => 'This week',
            ];
        }
        if ($this->containsAny($text, ['next week'])) {
            return [
                'from' => $today->modify('monday next week')->format('Y-m-d'),
                'to' => $today->modify('sunday next week')->format('Y-m-d'),
                'label' => 'Next week',
            ];
        }
        $days = [
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 7,
        ];
        foreach ($days as $name => $n) {
            if (!str_contains($text, $name)) {
                continue;
            }
            $target = $today->modify($name . ' this week');
            if ($target < $today->setTime(0, 0)) {
                $target = $today->modify($name . ' next week');
            }
            $d = $target->format('Y-m-d');
            return ['from' => $d, 'to' => $d, 'label' => ucfirst($name)];
        }
        return null;
    }

    private function extractPartOfDay(string $text): string
    {
        if ($this->containsAny($text, ['morning'])) {
            return 'morning';
        }
        if ($this->containsAny($text, ['afternoon'])) {
            return 'afternoon';
        }
        if ($this->containsAny($text, ['evening', 'tonight', 'night'])) {
            return 'evening';
        }
        return '';
    }

    private function defaultRangeForIntent(string $intent, string $text): array
    {
        $today = $this->today();
        $start = new DateTimeImmutable($today, $this->tz);
        if ($intent === 'GET_NEXT_CLASS') {
            return [
                'from' => $today,
                'to' => $start->modify('+21 days')->format('Y-m-d'),
                'label' => 'Upcoming',
            ];
        }
        if ($intent === 'GET_TOMORROW_CLASSES') {
            $d = $start->modify('+1 day')->format('Y-m-d');
            return ['from' => $d, 'to' => $d, 'label' => 'Tomorrow'];
        }
        if ($intent === 'GET_TODAY_CLASSES') {
            return ['from' => $today, 'to' => $today, 'label' => 'Today'];
        }
        if (in_array($intent, [
            'GET_SUBJECT_SCHEDULE', 'GET_TEACHER_SCHEDULE', 'GET_CLASS_ROOM', 'GET_CLASS_TEACHER',
        ], true)) {
            return [
                'from' => $today,
                'to' => $start->modify('+14 days')->format('Y-m-d'),
                'label' => 'Next 14 days',
            ];
        }
        return ['from' => $today, 'to' => $today, 'label' => 'Today'];
    }

    /**
     * @return array{id:int,name:string}|null
     */
    private function matchSubject(string $text): ?array
    {
        try {
            $rows = $this->pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            try {
                $rows = $this->pdo->query("SELECT id, name FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e2) {
                return null;
            }
        }
        $best = null;
        $bestLen = 0;
        foreach ($rows ?: [] as $row) {
            $name = mb_strtolower(trim((string)$row['name']));
            if ($name === '' || mb_strlen($name) < 2) {
                continue;
            }
            if (preg_match('/\bnot\s+' . preg_quote($name, '/') . '\b/u', $text)) {
                continue;
            }
            if (str_contains($text, $name)) {
                $score = mb_strlen($name);
                if (preg_match('/\b(?:need|want|for)\s+' . preg_quote($name, '/') . '\b/u', $text)) {
                    $score += 50;
                }
                if ($score > $bestLen) {
                    $best = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
                    $bestLen = $score;
                }
            }
        }
        $aliases = [
            'ict' => 'ict',
            'information and communication' => 'ict',
            'information technology' => 'information',
            'computer science' => 'computer',
            'further mathematics' => 'further',
            'pure mathematics' => 'pure',
            'mathematics' => 'math',
        ];
        if ($best === null) {
            foreach ($rows ?: [] as $row) {
                $name = mb_strtolower((string)$row['name']);
                foreach ($aliases as $needle => $hit) {
                    if (str_contains($text, $needle) && str_contains($name, $hit)) {
                        return ['id' => (int)$row['id'], 'name' => (string)$row['name']];
                    }
                }
            }
        }
        return $best;
    }

    /**
     * @return array{id:int,name:string}|null
     */
    private function matchTeacher(string $text): ?array
    {
        try {
            $rows = $this->pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        $best = null;
        $bestLen = 0;
        foreach ($rows ?: [] as $row) {
            $name = mb_strtolower(trim((string)$row['name']));
            if ($name === '') {
                continue;
            }
            if (str_contains($text, $name) && mb_strlen($name) > $bestLen) {
                $best = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
                $bestLen = mb_strlen($name);
                continue;
            }
            $first = explode(' ', $name)[0] ?? '';
            $stop = ['give', 'have', 'with', 'from', 'this', 'that', 'what', 'when', 'need', 'want', 'names', 'phone', 'number', 'class', 'teacher', 'miss', 'then'];
            if (strlen($first) >= 4 && !in_array($first, $stop, true) && preg_match('/\b' . preg_quote($first, '/') . '\b/u', $text)) {
                if (strlen($first) > $bestLen) {
                    $best = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
                    $bestLen = strlen($first);
                }
            }
        }
        return $best;
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $phone): array
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if ($phone === '') {
            return [];
        }
        $out = [$phone];
        if (str_starts_with($phone, '94') && strlen($phone) >= 11) {
            $out[] = '0' . substr($phone, 2);
        }
        return $out;
    }

    /**
     * @param list<string> $variants
     * @param list<string> $roles
     */
    private function lookupUserByPhones(array $variants, array $roles): ?array
    {
        $ph = implode(',', array_fill(0, count($variants), '?'));
        $rh = implode(',', array_fill(0, count($roles), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, role, teacher_id FROM users
                WHERE deleted_at IS NULL AND role IN ($rh) AND username IN ($ph)
                LIMIT 1
            ");
            $stmt->execute(array_merge($roles, $variants));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<string> $variants
     */
    private function lookupTeacherId(array $variants): ?int
    {
        $user = $this->lookupUserByPhones($variants, ['teacher']);
        if ($user && (int)($user['teacher_id'] ?? 0) > 0) {
            return (int)$user['teacher_id'];
        }
        $ph = implode(',', array_fill(0, count($variants), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM teachers
                WHERE deleted_at IS NULL AND phone IS NOT NULL AND phone <> ''
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $n = preg_replace('/\D+/', '', (string)$row['phone']) ?? '';
                if (in_array($n, $variants, true)) {
                    return (int)$row['id'];
                }
            }
        } catch (Throwable $e) {
        }
        return $user ? (int)($user['teacher_id'] ?? 0) ?: null : null;
    }

    /**
     * @param list<string> $variants
     */
    private function lookupStudentId(array $variants): ?int
    {
        $ph = implode(',', array_fill(0, count($variants), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id FROM users u
                JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.role='student' AND u.deleted_at IS NULL
                  AND sp.whatsapp_number IN ($ph)
                LIMIT 1
            ");
            $stmt->execute($variants);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
            $stmt = $this->pdo->prepare("
                SELECT u.id FROM users u
                WHERE u.role='student' AND u.deleted_at IS NULL
                  AND u.username IN ($ph)
                LIMIT 1
            ");
            $stmt->execute($variants);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
            $stmt = $this->pdo->prepare("
                SELECT u.id FROM users u
                JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.role='student' AND u.deleted_at IS NULL
                  AND sp.parent_whatsapp IN ($ph)
                LIMIT 1
            ");
            $stmt->execute($variants);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<string> $variants
     */
    private function isParentPhone(array $variants, int $studentId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT parent_whatsapp FROM student_profiles WHERE user_id = ?");
            $stmt->execute([$studentId]);
            $p = preg_replace('/\D+/', '', (string)$stmt->fetchColumn()) ?? '';
            return $p !== '' && in_array($p, $variants, true);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<int>
     */
    private function classIdsForStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare("SELECT class_id FROM student_enrollments WHERE student_id = ?");
        $stmt->execute([$studentId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function studentName(int $studentId): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT full_name FROM student_profiles WHERE user_id = ?");
            $stmt->execute([$studentId]);
            return trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', $this->tz))->format('Y-m-d');
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($text, $n)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadSession(string $phone): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM whatsapp_bot_sessions WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $parsed
     */
    private function saveSession(string $phone, array $parsed): void
    {
        if ($parsed['intent'] === '' || $parsed['intent'] === 'REFUSE') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO whatsapp_bot_sessions
                    (phone, intent, subject_id, subject_name, programme, teacher_id, teacher_name, date_from, date_to, date_label, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    intent = VALUES(intent),
                    subject_id = COALESCE(VALUES(subject_id), subject_id),
                    subject_name = COALESCE(VALUES(subject_name), subject_name),
                    programme = COALESCE(VALUES(programme), programme),
                    teacher_id = VALUES(teacher_id),
                    teacher_name = VALUES(teacher_name),
                    date_from = COALESCE(VALUES(date_from), date_from),
                    date_to = COALESCE(VALUES(date_to), date_to),
                    date_label = COALESCE(VALUES(date_label), date_label),
                    updated_at = NOW()
            ");
            $stmt->execute([
                $phone,
                $parsed['intent'],
                $parsed['subject_id'],
                $parsed['subject_name'] !== '' ? $parsed['subject_name'] : null,
                $parsed['programme'] !== '' ? $parsed['programme'] : null,
                $parsed['teacher_id'],
                $parsed['teacher_name'] !== '' ? $parsed['teacher_name'] : null,
                $parsed['date_from'],
                $parsed['date_to'],
                $parsed['date_label'] !== '' ? $parsed['date_label'] : null,
            ]);
        } catch (Throwable $e) {
            error_log('WA session: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function authorize(string $intent, array $identity, array $parsed): ?string
    {
        if (in_array($intent, WhatsAppIntentMap::teacherIntents(), true)) {
            if (empty($identity['teacher_id']) && !$identity['is_admin']) {
                return 'This WhatsApp is not linked to a teacher account.';
            }
            return null;
        }
        if ($intent === 'ADMIN_STATS' && !$identity['is_admin']) {
            return $this->refuse();
        }
        $namedCourse = ($parsed['programme'] ?? '') !== '' || ($parsed['subject_name'] ?? '') !== '';
        if (in_array($intent, ['GET_SUBJECT_SCHEDULE', 'GET_TIMETABLE', 'GET_CLASS_ROOM', 'GET_CLASS_TEACHER', 'CHECK_CANCELLATION'], true)
            && $namedCourse) {
            return null;
        }
        if (in_array($intent, WhatsAppIntentMap::personalIntents(), true)
            && !$this->requireStudent($identity)
            && empty($identity['teacher_id'])
            && !$identity['is_admin']) {
            return $this->needAccount('that information');
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $identity
     * @return list<array<string,mixed>>
     */
    private function verifyLessons(array $rows, array $identity, bool $publicCourse): array
    {
        $allowed = $identity['class_ids'];
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $classId = (int)($row['class_id'] ?? 0);
            if (!$publicCourse && $allowed !== [] && $classId > 0 && !in_array($classId, $allowed, true)) {
                continue;
            }
            $date = (string)($row['date'] ?? '');
            $start = (string)($row['start_time'] ?? '');
            $key = $classId . '|' . $date . '|' . $start;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($date === '' || $start === '') {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $classIds
     * @return list<array<string,mixed>>
     */
    private function mergeRecurringLessons(array $rows, array $classIds, string $from, string $to): array
    {
        if ($classIds === []) {
            return $rows;
        }
        $in = implode(',', array_fill(0, count($classIds), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT rs.teacher_id, rs.subject_id, rs.class_id, rs.room_id,
                       rs.day_of_week, rs.start_time, rs.end_time,
                       s.name AS subject_name, c.name AS class_name,
                       tc.name AS teacher_name, r.name AS room_name
                FROM recurring_schedules rs
                INNER JOIN subjects s ON s.id = rs.subject_id AND s.deleted_at IS NULL
                INNER JOIN student_classes c ON c.id = rs.class_id AND c.deleted_at IS NULL
                INNER JOIN teachers tc ON tc.id = rs.teacher_id AND tc.deleted_at IS NULL
                INNER JOIN rooms r ON r.id = rs.room_id AND r.deleted_at IS NULL
                WHERE rs.class_id IN ($in)
                  AND rs.start_date <= ?
                  AND rs.end_date >= ?
            ");
            $stmt->execute([...$classIds, $to, $from]);
            $recurring = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $rows;
        }

        $have = [];
        foreach ($rows as $row) {
            $have[(int)$row['class_id'] . '|' . $row['date'] . '|' . substr((string)$row['start_time'], 0, 8)] = true;
        }

        $cursor = new DateTimeImmutable($from, $this->tz);
        $end = new DateTimeImmutable($to, $this->tz);
        if (!function_exists('is_holiday')) {
            require_once dirname(__DIR__, 2) . '/includes/holidays.php';
        }
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $dow = $cursor->format('l');
            if (function_exists('is_holiday') && is_holiday($this->pdo, $date)) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }
            foreach ($recurring as $rs) {
                if (strcasecmp((string)$rs['day_of_week'], $dow) !== 0) {
                    continue;
                }
                $start = substr((string)$rs['start_time'], 0, 8);
                $key = (int)$rs['class_id'] . '|' . $date . '|' . $start;
                if (isset($have[$key])) {
                    continue;
                }
                $have[$key] = true;
                $rows[] = [
                    'id' => 0,
                    'date' => $date,
                    'start_time' => $rs['start_time'],
                    'end_time' => $rs['end_time'],
                    'lesson_status' => 'scheduled',
                    'cancel_reason' => '',
                    'subject_id' => $rs['subject_id'],
                    'class_id' => $rs['class_id'],
                    'teacher_id' => $rs['teacher_id'],
                    'room_id' => $rs['room_id'],
                    'subject_name' => $rs['subject_name'],
                    'class_name' => $rs['class_name'],
                    'teacher_name' => $rs['teacher_name'],
                    'substitute_name' => '',
                    'room_name' => $rs['room_name'],
                ];
            }
            $cursor = $cursor->modify('+1 day');
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)$a['date'] . $a['start_time'], (string)$b['date'] . $b['start_time']);
        });

        return $rows;
    }

    private function scoreIntent(string $text, ?array $subject): string
    {
        $best = '';
        $bestScore = 0;
        foreach (WhatsAppIntentMap::signals() as $intent => $needles) {
            $score = 0;
            foreach ($needles as $needle) {
                if ($this->containsAny($text, [$needle])) {
                    $score += max(2, strlen($needle));
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }
        if ($bestScore < 8) {
            return '';
        }
        if ($best === 'GET_TIMETABLE' && $subject) {
            return 'GET_SUBJECT_SCHEDULE';
        }
        return $best;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $parsed
     */
    private function audit(
        string $phone,
        array $identity,
        string $question,
        array $parsed,
        string $status,
        string $verify,
        ?string $answer
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO whatsapp_bot_query_log
                    (phone, role, question, intent, params_json, result_status, verification, answer, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                substr($phone, 0, 32),
                (string)($identity['role'] ?? 'public'),
                mb_substr($question, 0, 500),
                (string)($parsed['intent'] ?? ''),
                json_encode([
                    'subject' => $parsed['subject_name'] ?? '',
                    'programme' => $parsed['programme'] ?? '',
                    'from' => $parsed['date_from'] ?? null,
                    'to' => $parsed['date_to'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                $status,
                $verify,
                $answer !== null ? mb_substr($answer, 0, 1000) : null,
            ]);
        } catch (Throwable $e) {
        }
    }

    private function ensureAuditTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS whatsapp_bot_query_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    phone VARCHAR(32) NOT NULL,
                    role VARCHAR(20) NULL,
                    question VARCHAR(500) NOT NULL,
                    intent VARCHAR(80) NULL,
                    params_json TEXT NULL,
                    result_status VARCHAR(40) NULL,
                    verification VARCHAR(40) NULL,
                    answer TEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_wa_query_phone (phone),
                    KEY idx_wa_query_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
        }
    }

    private function ensureSessionTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS whatsapp_bot_sessions (
                    phone VARCHAR(32) NOT NULL,
                    intent VARCHAR(80) NULL,
                    subject_id INT NULL,
                    subject_name VARCHAR(120) NULL,
                    teacher_id INT NULL,
                    teacher_name VARCHAR(150) NULL,
                    date_from DATE NULL,
                    date_to DATE NULL,
                    date_label VARCHAR(40) NULL,
                    programme VARCHAR(40) NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (phone)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE whatsapp_bot_sessions ADD COLUMN programme VARCHAR(40) NULL");
        } catch (Throwable $e) {
        }
    }
}
