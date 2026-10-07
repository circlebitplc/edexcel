<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class WhatsAppBotService
{
    public function __construct(
        private PDO $pdo,
        private WhatsAppSender $evolution
    ) {
    }

    public function handle(string $phone, string $message, array $meta = []): void
    {
        $phone = $this->normalizePhone($phone);
        $message = trim($message);

        date_default_timezone_set('Asia/Colombo');

        if (!function_exists('ensure_campus_schema')) {
            require_once dirname(__DIR__, 2) . '/config/campus.php';
        }
        ensure_campus_schema($this->pdo);

        if ($phone === '' || $message === '') {
            return;
        }

        $messageId = trim((string)($meta['message_id'] ?? ''));
        if ($messageId !== '' && $this->alreadyProcessed($messageId)) {
            return;
        }

        $this->ensureContact($phone);
        $this->logInbound($phone, $message, $meta);

        $command = $this->normalizeText($message);

        $reply = $this->routeMessage($phone, $message, $command);
        $reply = WhatsAppI18n::localize($reply, WhatsAppI18n::detect($message));

        if ($reply !== '') {
            $this->send($phone, $reply);
        }
    }

    private function routeMessage(
        string $phone,
        string $original,
        string $command
    ): string {
        /*
         * Shortcuts stay instant. Everything else is free-form AI chat
         * when a Groq or Gemini key is configured.
         */
        if (in_array($command, [
            'menu',
            'help',
            'main menu',
            'main',
            'back',
            'go back',
            'cancel',
            'home',
        ], true)) {
            return $this->menu($phone);
        }

        if ($command === '1') {
            return $this->todayClasses($phone);
        }

        if ($command === '2') {
            return $this->classesForDate(
                $phone,
                date('Y-m-d', strtotime('+1 day')),
                'Tomorrow'
            );
        }

        if ($command === '3') {
            return $this->myClasses($phone);
        }

        if ($command === '4') {
            return $this->teachers();
        }

        if ($command === '5') {
            return $this->weekSchedule($phone);
        }

        if ($command === '6') {
            return $this->admissions();
        }

        if ($command === '7') {
            return $this->humanSupport();
        }

        if ($command === '8') {
            return $this->examSchedule($phone);
        }

        if (in_array($command, [
            'hi',
            'hello',
            'hey',
            'start',
            'yo',
            'hai',
            'ayubowan',
            'hii',
            'hiii',
            'good morning',
            'good afternoon',
            'good evening',
        ], true)) {
            return $this->menu($phone);
        }

        try {
            $engine = new WhatsAppAssistant($this->pdo);
            $hit = $engine->reply($phone, $original, $command);
            if ($hit !== null && trim($hit) !== '') {
                return $hit;
            }
        } catch (Throwable $e) {
            error_log('WhatsApp assistant: ' . $e->getMessage());
        }

        $guide = $this->answerGuideTopic($phone, $command);
        if ($guide !== null) {
            return $guide;
        }

        if (!$this->looksLikeFactualRecordQuestion($command)) {
            $aiReply = $this->aiDrivenReply($phone, $original);
            if ($aiReply !== null && $aiReply !== '') {
                return $aiReply;
            }
        }

        /*
         * Today's classes.
         */
        if (
            $this->containsAny($command, [
                'today',
                'todays',
                'today class',
                'today classes',
                'classes today',
                'class today',
                'what classes today',
                'what class today',
                'classes for today',
                'class for today',
            ])
        ) {
            return $this->todayClasses($phone);
        }

        /*
         * Tomorrow's classes.
         */
        if (
            $this->containsAny($command, [
                'tomorrow',
                'tomorrows',
                'tomorrow class',
                'tomorrow classes',
                'classes tomorrow',
                'class tomorrow',
                'what classes tomorrow',
                'what class tomorrow',
            ])
        ) {
            return $this->classesForDate(
                $phone,
                date('Y-m-d', strtotime('+1 day')),
                'Tomorrow'
            );
        }

        /*
         * Weekly timetable.
         */
        if (
            $this->containsAny($command, [
                'exam',
                'exams',
                'mock',
                'mocks',
                'mock paper',
                'mock papers',
                'exam timetable',
                'mock timetable',
                'assessment',
                'assessments',
            ])
        ) {
            return $this->examSchedule($phone);
        }

        if (
            $this->containsAny($command, [
                'this week',
                'this weeks',
                'weekly',
                'weekly timetable',
                'week timetable',
                'weekly schedule',
                'week schedule',
                'full timetable',
                'my timetable',
                'my schedule',
            ])
        ) {
            return $this->weekSchedule($phone);
        }

        /*
         * Student's enrolled classes.
         */
        if (
            $this->containsAny($command, [
                'my classes',
                'my class',
                'my enrolled classes',
                'enrolled classes',
                'enrolment',
                'enrollment',
                'what classes am i in',
                'which classes am i in',
            ])
        ) {
            return $this->myClasses($phone);
        }

        /*
         * Teacher-related questions.
         */
        if (
            $this->containsAny($command, [
                'teacher',
                'teachers',
                'lecturer',
                'lecturers',
                'who teaches',
                'who is my teacher',
                'my teacher',
            ])
        ) {
            return $this->teacherQuestion($command);
        }

        /*
         * Admissions / joining / fees.
         */
        if (
            $this->containsAny($command, [
                'admission',
                'admissions',
                'join',
                'joining',
                'enroll',
                'enrol',
                'register',
                'registration',
                'new class',
                'new batch',
                'fee',
                'fees',
                'price',
                'prices',
                'how much',
                'course',
                'courses',
            ])
        ) {
            return $this->admissions();
        }

        /*
         * Human support.
         */
        if (
            $this->containsAny($command, [
                'admin',
                'administrator',
                'human',
                'person',
                'staff',
                'support',
                'contact',
                'talk to someone',
                'talk to admin',
                'speak to someone',
            ])
        ) {
            return $this->humanSupport();
        }

        /*
         * More general timetable/class questions.
         */
        if (
            $this->containsAny($command, [
                'class',
                'classes',
                'schedule',
                'timetable',
                'lesson',
                'lessons',
            ])
        ) {
            return $this->todayClasses($phone)
                . "\n\nReply *2* for tomorrow or *5* for this week's timetable.";
        }

        /*
         * If the message contains a likely subject, try teacher search.
         */
        if ($this->looksLikeTeacherQuestion($command)) {
            return $this->teacherQuestion($command);
        }

        return $this->fallback($phone);
    }

    private function looksLikeFactualRecordQuestion(string $command): bool
    {
        return $this->containsAny($command, [
            'timetable', 'class', 'classes', 'lesson', 'schedule', 'today', 'tomorrow',
            'fee', 'fees', 'payment', 'paid', 'attendance', 'absent', 'present',
            'exam', 'mock', 'mark', 'marks', 'score', 'result', 'progress',
        ]);
    }

    private function aiDrivenReply(string $phone, string $original): ?string
    {
        $ai = new WhatsAppAiService($this->pdo);
        if (!$ai->enabled()) {
            return null;
        }

        try {
            return $ai->reply(
                $original,
                $this->collegeContext($phone),
                $this->recentChatHistory($phone)
            );
        } catch (Throwable $e) {
            error_log('WhatsApp AI reply failed: ' . $e->getMessage());
            return null;
        }
    }

    private function collegeContext(string $phone): string
    {
        $tz = new \DateTimeZone('Asia/Colombo');
        $now = new \DateTimeImmutable('now', $tz);
        $name = $this->studentDisplayName($phone);
        $linked = $this->getLinkedStudent($phone) !== null;

        $classList = '';
        try {
            $classList = (new WhatsAppAssistant($this->pdo))->classCatalogueText();
        } catch (Throwable $e) {
            $classList = '';
        }

        $parts = [
            'College: Edexcel College',
            'Now: ' . $now->format('l, d F Y h:i A') . ' Asia/Colombo',
            'Student WhatsApp: ' . $phone,
            'Student name: ' . ($name !== '' ? $name : 'unknown'),
            'Account linked: ' . ($linked ? 'yes' : 'no'),
            $classList,
            WhatsAppGuideService::handbook(),
            $this->liveStudentFacts($phone),
            $this->coursoLearnerSnapshot($phone),
            $this->teachers(),
            $this->todayClasses($phone),
            $this->classesForDate(
                $phone,
                $now->modify('+1 day')->format('Y-m-d'),
                'Tomorrow'
            ),
            $this->myClasses($phone),
            $this->weekSchedule($phone),
            $this->examSchedule($phone),
            'HOW TO REGISTER (always use this exact link): '
            . $this->registerUrl()
            . "\nTell the student to open that page and follow the on-screen instructions (name, WhatsApp number, password, WhatsApp OTP). "
            . 'Login after registering: ' . $this->loginUrl(),
            $this->admissions(),
            $this->humanSupport(),
        ];

        $text = implode("\n\n---\n\n", $parts);
        if (mb_strlen($text) > 9000) {
            $text = mb_substr($text, 0, 9000) . "\n…";
        }

        return $text;
    }

    /**
     * @return list<array{role:string,content:string}>
     */
    private function recentChatHistory(string $phone): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT direction, message
                FROM whatsapp_bot_messages
                WHERE phone = ?
                ORDER BY id DESC
                LIMIT 8
            ");
            $stmt->execute([$phone]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        $rows = array_reverse($rows);
        if ($rows !== []) {
            array_pop($rows);
        }

        $history = [];
        foreach ($rows as $row) {
            $content = trim((string)($row['message'] ?? ''));
            if ($content === '') {
                continue;
            }
            $history[] = [
                'role' => ($row['direction'] ?? '') === 'outbound' ? 'assistant' : 'user',
                'content' => mb_strlen($content) > 500 ? mb_substr($content, 0, 500) . '…' : $content,
            ];
        }

        return $history;
    }

    private function studentDisplayName(string $phone): string
    {
        $contact = $this->getLinkedStudent($phone);
        if ($contact === null) {
            return '';
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT full_name
                FROM student_profiles
                WHERE user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$contact['student_id']]);
            $name = trim((string)($stmt->fetchColumn() ?: ''));
            return $name;
        } catch (Throwable $e) {
            return '';
        }
    }

    private function menu(string $phone): string
    {
        $student = $this->getLinkedStudent($phone);
        $name = $this->studentDisplayName($phone);

        $greeting = $name !== ''
            ? "👋 *Hi {$name}!*\n\n"
            : ($student !== null
                ? "👋 *Welcome back!*\n\n"
                : "👋 *Welcome to Edexcel College!*\n\n");

        return $greeting
            . "I'm the Edexcel College assistant for *students and parents*. Ask in English, Sinhala, or Tamil about registration, login, classes, timetable, fees, attendance, exams, or parent WhatsApp updates.\n\n"
            . "For example:\n"
            . "• _How do I register?_\n"
            . "• _What do I have today?_\n"
            . "• _How much fees are due?_\n"
            . "• _How do parents get updates?_\n\n"
            . "*Shortcuts:* _1_ today · _2_ tomorrow · _3_ my classes · _4_ teachers · _5_ this week · _6_ register · _7_ admin · _8_ exams\n\n"
            . "Type *menu* anytime to see this again.";
    }

    private function todayClasses(string $phone): string
    {
        return $this->classesForDate(
            $phone,
            date('Y-m-d'),
            'Today'
        );
    }

    private function classesForDate(
        string $phone,
        string $date,
        string $label
    ): string {
        $classIds = $this->studentClassIds($phone);

        if ($classIds === null) {
            return $this->identifyStudentMessage();
        }

        if ($classIds === []) {
            return "📚 *$label*\n\n"
                . "I couldn't find any enrolled classes linked to this WhatsApp number.\n\n"
                . "Please contact an administrator to link your student account.";
        }

        $in = implode(
            ',',
            array_fill(0, count($classIds), '?')
        );

        $params = array_merge($classIds, [$date]);

        $sql = "
            SELECT
                t.start_time,
                t.end_time,
                s.name AS subject_name,
                c.name AS class_name,
                r.name AS room_name,
                tc.name AS teacher_name
            FROM timetable t
            LEFT JOIN subjects s
                ON s.id = t.subject_id
            LEFT JOIN student_classes c
                ON c.id = t.class_id
            LEFT JOIN rooms r
                ON r.id = t.room_id
            LEFT JOIN teachers tc
                ON tc.id = t.teacher_id
            WHERE t.class_id IN ($in)
              AND t.date = ?
              AND t.deleted_at IS NULL
              AND COALESCE(t.lesson_status, 'scheduled') <> 'cancelled'
            ORDER BY t.start_time
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return "📅 *$label — "
                . date('d M Y', strtotime($date))
                . "*\n\n"
                . "No classes are scheduled for your enrolled classes.";
        }

        $out = "📅 *$label — "
            . date('d M Y', strtotime($date))
            . "*\n\n";

        foreach ($rows as $index => $row) {
            $out .= ($index + 1)
                . ". *"
                . ($row['subject_name'] ?: 'Class')
                . "*\n";

            $out .= "⏰ "
                . date('h:i A', strtotime((string)$row['start_time']))
                . " – "
                . date('h:i A', strtotime((string)$row['end_time']))
                . "\n";

            $out .= "👨‍🏫 "
                . ($row['teacher_name'] ?: 'Teacher')
                . "\n";

            $out .= "🏫 "
                . ($row['room_name'] ?: 'Room')
                . "\n";

            $out .= "📚 "
                . ($row['class_name'] ?: 'Class')
                . "\n\n";
        }

        $out .= "Type *menu* for more options.";

        return trim($out);
    }

    private function myClasses(string $phone): string
    {
        $classIds = $this->studentClassIds($phone);

        if ($classIds === null) {
            return $this->identifyStudentMessage();
        }

        if ($classIds === []) {
            return "📚 *My Classes*\n\n"
                . "No enrolled classes are linked to this WhatsApp number.";
        }

        $in = implode(
            ',',
            array_fill(0, count($classIds), '?')
        );

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                description
            FROM student_classes
            WHERE id IN ($in)
              AND deleted_at IS NULL
            ORDER BY name
        ");

        $stmt->execute($classIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return "📚 *My Classes*\n\n"
                . "No enrolled classes are currently available.";
        }

        $out = "📚 *Your Enrolled Classes*\n\n";

        foreach ($rows as $row) {
            $out .= "• *"
                . $row['name']
                . "*";

            if (!empty($row['description'])) {
                $out .= "\n  "
                    . trim($row['description']);
            }

            $out .= "\n\n";
        }

        $out .= "Reply *1* for today's classes.";

        return trim($out);
    }

    private function teachers(): string
    {
        $stmt = $this->pdo->query("
            SELECT
                t.id,
                t.name,
                t.phone,
                t.email,
                GROUP_CONCAT(
                    DISTINCT s.name
                    ORDER BY s.name
                    SEPARATOR ', '
                ) AS subjects
            FROM teachers t
            LEFT JOIN teacher_subjects ts
                ON ts.teacher_id = t.id
            LEFT JOIN subjects s
                ON s.id = ts.subject_id
            WHERE t.deleted_at IS NULL
            GROUP BY
                t.id,
                t.name,
                t.phone,
                t.email
            ORDER BY t.name
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return "👨‍🏫 *Teachers*\n\n"
                . "Teacher information is currently unavailable.";
        }

        $out = "👨‍🏫 *Edexcel College Teachers*\n\n";

        foreach (array_slice($rows, 0, 40) as $row) {
            $out .= "• *"
                . $row['name']
                . "*";

            if (!empty($row['subjects'])) {
                $out .= "\n  📚 "
                    . $row['subjects'];
            }
            if (!empty($row['phone'])) {
                $digits = preg_replace('/\D+/', '', (string)$row['phone']) ?? '';
                if (str_starts_with($digits, '0') && strlen($digits) === 10) {
                    $digits = '94' . substr($digits, 1);
                }
                $out .= "\n  📞 " . $row['phone'];
                if ($digits !== '') {
                    $out .= "\n  WhatsApp: https://wa.me/" . $digits;
                }
            }
            if (!empty($row['email'])) {
                $out .= "\n  ✉️ " . $row['email'];
            }

            $out .= "\n\n";
        }

        if (count($rows) > 40) {
            $out .= "Showing the first 40 teachers. Reply *7* for more.";
        }

        return trim($out);
    }

    private function teacherQuestion(string $command): string
    {
        /*
         * First try to identify a subject from the subjects table.
         */
        $subjects = $this->pdo->query("
            SELECT id, name
            FROM subjects
            ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $matchedSubjectIds = [];

        foreach ($subjects as $subject) {
            $name = $this->normalizeText((string)$subject['name']);

            if (
                $name !== ''
                && (
                    str_contains($command, $name)
                    || $this->wordsOverlap($command, $name)
                )
            ) {
                $matchedSubjectIds[] = (int)$subject['id'];
            }
        }

        if ($matchedSubjectIds) {
            $in = implode(
                ',',
                array_fill(
                    0,
                    count($matchedSubjectIds),
                    '?'
                )
            );

            $stmt = $this->pdo->prepare("
                SELECT
                    t.name,
                    GROUP_CONCAT(
                        DISTINCT s.name
                        ORDER BY s.name
                        SEPARATOR ', '
                    ) AS subjects
                FROM teachers t
                JOIN teacher_subjects ts
                    ON ts.teacher_id = t.id
                JOIN subjects s
                    ON s.id = ts.subject_id
                WHERE t.deleted_at IS NULL
                  AND s.id IN ($in)
                GROUP BY
                    t.id,
                    t.name
                ORDER BY t.name
            ");

            $stmt->execute($matchedSubjectIds);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                $subjectNames = [];

                foreach ($subjects as $subject) {
                    if (
                        in_array(
                            (int)$subject['id'],
                            $matchedSubjectIds,
                            true
                        )
                    ) {
                        $subjectNames[] = $subject['name'];
                    }
                }

                $out = "👨‍🏫 *Teachers";

                if ($subjectNames) {
                    $out .= " — "
                        . implode(', ', $subjectNames);
                }

                $out .= "*\n\n";

                foreach ($rows as $row) {
                    $out .= "• *"
                        . $row['name']
                        . "*";

                    if (!empty($row['subjects'])) {
                        $out .= "\n  📚 "
                            . $row['subjects'];
                    }

                    $out .= "\n\n";
                }

                return trim($out);
            }
        }

        /*
         * If no subject was detected, show the teacher list.
         */
        return $this->teachers();
    }

    private function examSchedule(string $phone): string
    {
        $classIds = $this->studentClassIds($phone);

        if ($classIds === null) {
            return $this->identifyStudentMessage();
        }

        if (!function_exists('\\campus_student_exams')) {
            require_once dirname(__DIR__, 2) . '/config/campus.php';
        }

        $rows = \campus_student_exams($this->pdo, $classIds, date('Y-m-d'), null, 12);

        $official = '';
        try {
            $contact = $this->getLinkedStudent($phone);
            $studentId = (int)($contact['student_id'] ?? 0);
            if ($studentId > 0) {
                $official = (new OfficialExamService($this->pdo))->whatsappTimetable($studentId, 8);
            }
        } catch (Throwable $e) {
            $official = '';
        }

        if ($rows === [] && $official === '') {
            return "📝 *Exam timetable*\n\n"
                . "No official papers on your planner yet, and no college mocks are published for your classes.\n"
                . "Open the Student portal → Exams to add your Pearson papers.";
        }

        $out = $official !== '' ? $official . "\n\n" : '';
        if ($rows === []) {
            return trim($out);
        }

        $out .= "🏫 *College mocks / class exams*\n\n";
        foreach ($rows as $row) {
            $kind = function_exists('campus_exam_type_label')
                ? campus_exam_type_label((string)($row['exam_type'] ?? 'exam'))
                : 'Exam';
            $when = function_exists('campus_exam_when')
                ? campus_exam_when($row)
                : (string)($row['exam_date'] ?? '');
            $venue = function_exists('campus_exam_venue') ? campus_exam_venue($row) : '';
            $out .= '*' . $kind . '* — ' . trim((string)($row['title'] ?? 'Paper')) . "\n";
            $out .= $when;
            if (!empty($row['class_name'])) {
                $out .= "\n" . $row['class_name'];
            }
            if ($venue !== '') {
                $out .= "\nRoom: " . $venue;
            }
            $out .= "\n\n";
        }

        return trim($out);
    }

    private function weekSchedule(string $phone): string
    {
        $classIds = $this->studentClassIds($phone);

        if ($classIds === null) {
            return $this->identifyStudentMessage();
        }

        if ($classIds === []) {
            return "📅 *Weekly Timetable*\n\n"
                . "No enrolled classes are linked to this WhatsApp number.";
        }

        $start = date(
            'Y-m-d',
            strtotime('monday this week')
        );

        $end = date(
            'Y-m-d',
            strtotime('sunday this week')
        );

        $in = implode(
            ',',
            array_fill(0, count($classIds), '?')
        );

        $stmt = $this->pdo->prepare("
            SELECT
                t.date,
                t.start_time,
                t.end_time,
                s.name AS subject_name,
                c.name AS class_name,
                r.name AS room_name,
                tc.name AS teacher_name
            FROM timetable t
            LEFT JOIN subjects s
                ON s.id = t.subject_id
            LEFT JOIN student_classes c
                ON c.id = t.class_id
            LEFT JOIN rooms r
                ON r.id = t.room_id
            LEFT JOIN teachers tc
                ON tc.id = t.teacher_id
            WHERE t.class_id IN ($in)
              AND t.date BETWEEN ? AND ?
              AND t.deleted_at IS NULL
              AND COALESCE(t.lesson_status, 'scheduled') <> 'cancelled'
            ORDER BY
                t.date,
                t.start_time
        ");

        $stmt->execute(
            array_merge(
                $classIds,
                [$start, $end]
            )
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return "📅 *This Week's Timetable*\n\n"
                . "No classes are scheduled for your enrolled classes this week.";
        }

        $out = "📅 *This Week's Timetable*\n\n";

        $lastDate = null;

        foreach ($rows as $row) {
            if ($lastDate !== $row['date']) {
                if ($lastDate !== null) {
                    $out .= "\n";
                }

                $out .= "📌 *"
                    . date(
                        'l d M',
                        strtotime($row['date'])
                    )
                    . "*\n";

                $lastDate = $row['date'];
            }

            $out .= "⏰ "
                . date(
                    'h:i A',
                    strtotime($row['start_time'])
                )
                . " – "
                . date(
                    'h:i A',
                    strtotime($row['end_time'])
                )
                . "\n";

            $out .= "📖 *"
                . $row['subject_name']
                . "*\n";

            $out .= "👨‍🏫 "
                . $row['teacher_name']
                . "\n";

            $out .= "🏫 "
                . $row['room_name']
                . "\n\n";
        }

        return trim($out);
    }

    private function answerGuideTopic(string $phone, string $command): ?string
    {
        $topic = WhatsAppGuideService::matchTopic($command);
        if ($topic === null) {
            return null;
        }

        return match ($topic) {
            'register' => $this->admissions(),
            'login' => $this->loginGuide(),
            'password' => $this->passwordGuide(),
            'join' => $this->joinClassGuide(),
            'fees' => $this->feesGuide($phone),
            'attendance' => $this->attendanceGuide($phone),
            'parent' => $this->parentGuide(),
            'holiday' => $this->holidayGuide(),
            'exam' => $this->examSchedule($phone),
            'verify' => $this->verifyGuide(),
            'contact' => $this->contactGuide(),
            'teachers_list' => $this->teachers(),
            'subject_teacher' => $this->teacherQuestion($command),
            'teacher' => $this->teacherGuide(),
            'timetable' => $this->todayClasses($phone)
                . "\n\nReply *2* for tomorrow or *5* for this week's timetable.",
            default => null,
        };
    }

    private function loginGuide(): string
    {
        $login = WhatsAppGuideService::page('student/login.php');
        $register = WhatsAppGuideService::page('student/register.php');

        return "🔐 *Student login*\n\n"
            . "Open this link:\n"
            . $login . "\n\n"
            . "• Username = the student's *WhatsApp number*\n"
            . "• Password = the one created at registration\n\n"
            . "No account yet? Register here and follow the instructions:\n"
            . $register . "\n\n"
            . "Stuck? Reply *7* for an administrator.";
    }

    private function passwordGuide(): string
    {
        $settings = WhatsAppGuideService::page('student/dashboard.php?tab=settings');

        return "🔑 *Password*\n\n"
            . "If you can still log in, change it here:\n"
            . $settings . "\n\n"
            . "Use your *current* password, then a new password of at least 8 characters.\n\n"
            . "If you cannot log in, reply *7* — an administrator must help. There is no email reset link.";
    }

    private function joinClassGuide(): string
    {
        $join = WhatsAppGuideService::page('student/dashboard.php?tab=join');

        return "📚 *Join a class*\n\n"
            . "1. Log in to the student portal\n"
            . "2. Open:\n"
            . $join . "\n"
            . "3. Choose the class and tap Join\n\n"
            . "Your WhatsApp must be verified (message this bot from the *student* number).\n"
            . "After you join, the class WhatsApp group link is sent here.\n"
            . "If the class is full you go on the *waitlist* and get a seat when one opens.\n\n"
            . "Need a person? Reply *7*.";
    }

    private function feesGuide(string $phone): string
    {
        $feesUrl = WhatsAppGuideService::page('student/dashboard.php?tab=fees');
        $out = "💰 *Class fees*\n\n"
            . "Pay at the *college counter*. When admin marks it paid, a WhatsApp receipt is sent.\n\n"
            . "See the fee wallet here:\n"
            . $feesUrl . "\n";

        $facts = $this->liveFeeLine($phone);
        if ($facts !== '') {
            $out .= "\n" . $facts . "\n";
        } else {
            $out .= "\nI can show the exact amount due after this WhatsApp is linked to a student who has joined classes.\n";
        }

        $out .= "\nReply *7* if you need the office.";

        return $out;
    }

    private function attendanceGuide(string $phone): string
    {
        $url = WhatsAppGuideService::page('student/dashboard.php?tab=attendance');
        $out = "✅ *Attendance*\n\n"
            . "Teachers mark present, absent, or late in class.\n"
            . "See the full month here:\n"
            . $url . "\n";

        $today = $this->liveAttendanceLine($phone);
        if ($today !== '') {
            $out .= "\n*Today:*\n" . $today . "\n";
        }

        $out .= "\nParents get today's attendance in the 6:00 pm evening WhatsApp note if a parent number is saved in Settings.";

        return $out;
    }

    private function parentGuide(): string
    {
        $settings = WhatsAppGuideService::page('student/dashboard.php?tab=settings');

        return "👨‍👩‍👧 *Parent updates*\n\n"
            . "1. Student logs in to the portal\n"
            . "2. Opens:\n"
            . $settings . "\n"
            . "3. Saves *parent name* and *parent WhatsApp*\n\n"
            . "Around *6:00 pm* (Sri Lanka time) we send an evening note: tomorrow's classes, fees due, and today's attendance.\n\n"
            . "Parents can also message this bot from that saved number.";
    }

    private function holidayGuide(): string
    {
        $lines = $this->upcomingHolidayLines();
        $out = "📅 *Holidays*\n\n";
        if ($lines === []) {
            $out .= "I don't see an upcoming holiday in the college calendar right now.\n"
                . "Timetable days with no class listed are simply not scheduled.\n\n"
                . "Reply *7* to confirm with the office.";
        } else {
            $out .= "Upcoming:\n" . implode("\n", $lines);
        }

        return $out;
    }

    private function verifyGuide(): string
    {
        $register = WhatsAppGuideService::page('student/register.php');

        return "📲 *WhatsApp verification*\n\n"
            . "New students: register at\n"
            . $register . "\n"
            . "and enter the *OTP* we send on WhatsApp.\n\n"
            . "Already registered? Send any message (like *hi*) to this college bot from the *student* WhatsApp number to link it.\n\n"
            . "Then you can join classes in the portal.";
    }

    private function teacherGuide(): string
    {
        $base = WhatsAppGuideService::publicBase();

        return "👩‍🏫 *Teachers & staff*\n\n"
            . "Staff login (teachers and admin — *not* the student portal):\n"
            . $base . "/login.php\n\n"
            . "After login:\n"
            . "• Timetable: " . $base . "/timetable/index.php\n"
            . "• Mark attendance: " . $base . "/campus/attendance.php\n"
            . "• Mark class fees (unlocks recordings): " . $base . "/campus/lesson_fees.php\n"
            . "• Today board: " . $base . "/campus/today.php\n\n"
            . "WhatsApp: class groups get 6:00 pm and 6:00 am reminders. You also get notices if your *teacher phone* is saved when a class changes or a payment is recorded.\n\n"
            . "Students must use " . WhatsAppGuideService::page('student/login.php') . "\n"
            . "Need the office? Reply *7*.";
    }

    private function contactGuide(): string
    {
        $home = WhatsAppGuideService::publicBase() . '/';

        return "🏫 *Edexcel College*\n\n"
            . "Website: " . $home . "\n"
            . "Student register: " . WhatsAppGuideService::page('student/register.php') . "\n"
            . "Student login: " . WhatsAppGuideService::page('student/login.php') . "\n\n"
            . "For a person at the office, reply *7*.";
    }

    private function liveStudentFacts(string $phone): string
    {
        $parts = array_filter([
            $this->liveFeeLine($phone),
            $this->liveAttendanceLine($phone),
            implode("\n", $this->upcomingHolidayLines()),
        ]);

        if ($parts === []) {
            return 'LIVE STUDENT FEES / ATTENDANCE: not linked or no rows yet.';
        }

        return "LIVE STUDENT DATA:\n" . implode("\n", $parts);
    }

    private function liveFeeLine(string $phone): string
    {
        $contact = $this->getLinkedStudent($phone);
        if ($contact === null) {
            return '';
        }

        try {
            if (!function_exists('campus_fee_summary')) {
                require_once dirname(__DIR__, 2) . '/config/campus.php';
            }
            $wallet = campus_fee_summary($this->pdo, (int)$contact['student_id']);
            $due = (float)($wallet['due'] ?? 0);
            $paid = (float)($wallet['paid'] ?? 0);

            return '*Fees for this linked student:* due Rs '
                . number_format($due)
                . ' · paid Rs '
                . number_format($paid);
        } catch (Throwable $e) {
            return '';
        }
    }

    private function liveAttendanceLine(string $phone): string
    {
        $contact = $this->getLinkedStudent($phone);
        if ($contact === null) {
            return '';
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT sa.status, s.name AS subject_name
                FROM student_attendance sa
                JOIN timetable tt ON tt.id = sa.timetable_id
                JOIN subjects s ON s.id = tt.subject_id
                WHERE sa.student_id = ?
                  AND tt.date = CURDATE()
                ORDER BY tt.start_time
            ");
            $stmt->execute([(int)$contact['student_id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return '*Attendance today:* not marked yet.';
            }
            $bits = [];
            foreach ($rows as $row) {
                $bits[] = ($row['subject_name'] ?? 'Class') . ' — ' . ($row['status'] ?? '');
            }

            return '*Attendance today:* ' . implode('; ', $bits);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @return list<string>
     */
    private function upcomingHolidayLines(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT date, name
                FROM holidays
                WHERE date >= CURDATE()
                ORDER BY date
                LIMIT 8
            ");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            try {
                $stmt = $this->pdo->query("
                    SELECT date
                    FROM holidays
                    WHERE date >= CURDATE()
                    ORDER BY date
                    LIMIT 8
                ");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            } catch (Throwable $e2) {
                return [];
            }
        }

        $lines = [];
        foreach ($rows as $row) {
            $date = (string)($row['date'] ?? '');
            if ($date === '') {
                continue;
            }
            $label = trim((string)($row['name'] ?? ''));
            $lines[] = '• ' . date('D d M Y', strtotime($date))
                . ($label !== '' ? ' — ' . $label : '');
        }

        return $lines;
    }

    private function admissions(): string
    {
        $registerUrl = $this->registerUrl();

        return "🎓 *How to register*\n\n"
            . "Open this link and follow the instructions on the page:\n"
            . $registerUrl . "\n\n"
            . "You will:\n"
            . "1. Enter your *full name* and *WhatsApp number*\n"
            . "2. Set a password\n"
            . "3. Receive a WhatsApp OTP to verify your number\n"
            . "4. Log in with that WhatsApp number as your username\n\n"
            . "Already registered? Student login:\n"
            . $this->loginUrl() . "\n\n"
            . "Need help? Reply *7* to talk to an administrator.";
    }

    private function humanSupport(): string
    {
        $number = preg_replace(
            '/\D+/',
            '',
            (string)(getenv('HOTLINE_NUMBER') ?: '')
        ) ?? '';

        $out = "👤 *Administrator Support*\n\n";

        $out .= "I'll direct you to an administrator.\n\n";

        if ($number !== '') {
            $out .= "📱 WhatsApp: https://wa.me/"
                . $number
                . "\n\n";
        }

        $out .= "Please send your *name* and briefly explain what you need help with.";

        return trim($out);
    }

    private function fallback(string $phone): string
    {
        return "I can help you with classes, timetables, teachers and admissions. 🎓\n\n"
            . $this->menu($phone);
    }

    private function identifyStudentMessage(): string
    {
        return "👋 I can show your personal timetable, but this WhatsApp number isn't linked to a student account yet.\n\n"
            . "Register here and follow the instructions:\n"
            . $this->registerUrl() . "\n\n"
            . "You can still use:\n"
            . "👨‍🏫 *4* — Teachers\n"
            . "📝 *6* — How to register\n"
            . "👤 *7* — Administrator\n\n"
            . "Type *menu* anytime.";
    }

    private function registerUrl(): string
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';

        return rtrim(edexcel_public_app_url(), '/') . '/student/register.php';
    }

    private function loginUrl(): string
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';

        return rtrim(edexcel_public_app_url(), '/') . '/student/login.php';
    }

    private function admissionsUrl(): string
    {
        return $this->registerUrl();
    }

    public function linkRegisteredStudents(): int
    {
        $updated = 0;

        try {
            $stmt = $this->pdo->query("
                SELECT id, phone, student_id
                FROM whatsapp_bot_contacts
            ");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log('WhatsApp contact backfill failed: ' . $e->getMessage());
            return 0;
        }

        foreach ($rows as $row) {
            $studentId = $this->lookupStudentId(
                $this->normalizePhone((string)$row['phone'])
            );

            if ($studentId === null || (int)$row['student_id'] === $studentId) {
                continue;
            }

            $update = $this->pdo->prepare("
                UPDATE whatsapp_bot_contacts
                SET student_id = ?, active = 1
                WHERE id = ?
            ");
            $update->execute([$studentId, (int)$row['id']]);
            $updated++;
        }

        try {
            $stmt = $this->pdo->query("
                SELECT
                    u.id,
                    sp.whatsapp_number
                FROM student_profiles sp
                JOIN users u
                    ON u.id = sp.user_id
                WHERE u.role = 'student'
                  AND u.deleted_at IS NULL
                  AND sp.whatsapp_number IS NOT NULL
                  AND sp.whatsapp_number <> ''
            ");
            $profiles = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return $updated;
        }

        foreach ($profiles as $profile) {
            $phone = $this->normalizePhone((string)$profile['whatsapp_number']);
            if ($phone === '') {
                continue;
            }

            $insert = $this->pdo->prepare("
                INSERT INTO whatsapp_bot_contacts
                    (phone, student_id, active, last_seen_at)
                VALUES
                    (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    student_id = COALESCE(whatsapp_bot_contacts.student_id, VALUES(student_id)),
                    active = 1,
                    verified_at = COALESCE(whatsapp_bot_contacts.verified_at, NOW())
            ");
            $insert->execute([$phone, (int)$profile['id']]);
        }

        return $updated;
    }

    private function coursoLearnerSnapshot(string $phone): string
    {
        $linked = $this->getLinkedStudent($phone);
        $studentId = (int)($linked['student_id'] ?? 0);
        if ($studentId < 1) {
            return '';
        }
        try {
            return (new CoursoLearnerService($this->pdo))->learnerContextText($studentId);
        } catch (Throwable $e) {
            return '';
        }
    }

    private function getLinkedStudent(string $phone): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                student_id
            FROM whatsapp_bot_contacts
            WHERE phone = ?
              AND active = 1
            LIMIT 1
        ");

        $stmt->execute([$phone]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $studentId = $row ? (int)($row['student_id'] ?? 0) : 0;

        if ($studentId < 1) {
            $studentId = $this->lookupStudentId($phone) ?? 0;
            if ($studentId > 0) {
                $this->storeStudentLink($phone, $studentId);
            }
        }

        if ($studentId < 1) {
            return null;
        }

        return [
            'student_id' => $studentId,
        ];
    }

    private function studentClassIds(string $phone): ?array
    {
        $contact = $this->getLinkedStudent($phone);

        if ($contact === null) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT class_id
            FROM student_enrollments
            WHERE student_id = ?
            ORDER BY class_id
        ");

        $stmt->execute([
            $contact['student_id']
        ]);

        return array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function alreadyProcessed(string $messageId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM whatsapp_bot_messages
                WHERE message_id = ?
                  AND direction = 'inbound'
                LIMIT 1
            ");
            $stmt->execute([$messageId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function lookupStudentId(string $phone): ?int
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return null;
        }

        $variants = [$phone];
        if (str_starts_with($phone, '94') && strlen($phone) >= 11) {
            $variants[] = '0' . substr($phone, 2);
        }

        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN student_profiles sp
                    ON sp.user_id = u.id
                WHERE u.role = 'student'
                  AND u.deleted_at IS NULL
                  AND sp.whatsapp_number IN ($placeholders)
                LIMIT 1
            ");
            $stmt->execute($variants);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }

            $stmt = $this->pdo->prepare("
                SELECT u.id
                FROM users u
                WHERE u.role = 'student'
                  AND u.deleted_at IS NULL
                  AND u.username REGEXP '^[0-9]+$'
                  AND u.username IN ($placeholders)
                LIMIT 1
            ");
            $stmt->execute($variants);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }

            $stmt = $this->pdo->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN student_profiles sp
                    ON sp.user_id = u.id
                WHERE u.role = 'student'
                  AND u.deleted_at IS NULL
                  AND sp.parent_whatsapp IN ($placeholders)
                LIMIT 1
            ");
            $stmt->execute($variants);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }

            return null;
        } catch (Throwable $e) {
            error_log('WhatsApp student lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    private function storeStudentLink(string $phone, int $studentId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_bot_contacts
                (phone, student_id, active, last_seen_at, verified_at)
            VALUES
                (?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                student_id = COALESCE(whatsapp_bot_contacts.student_id, VALUES(student_id)),
                active = 1,
                last_seen_at = NOW(),
                verified_at = COALESCE(whatsapp_bot_contacts.verified_at, NOW())
        ");
        $stmt->execute([$phone, $studentId]);
    }

    private function ensureContact(string $phone): void
    {
        $studentId = $this->lookupStudentId($phone);

        if ($studentId !== null) {
            $this->storeStudentLink($phone, $studentId);
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_bot_contacts
                (phone, last_seen_at)
            VALUES
                (?, NOW())
            ON DUPLICATE KEY UPDATE
                last_seen_at = NOW()
        ");

        $stmt->execute([$phone]);
    }

    private function logInbound(
        string $phone,
        string $message,
        array $meta
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_bot_messages
                (
                    phone,
                    direction,
                    message,
                    event_name,
                    message_id,
                    payload_json
                )
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $phone,
            'inbound',
            $message,
            (string)($meta['event'] ?? ''),
            (string)($meta['message_id'] ?? ''),
            json_encode(
                $meta['payload'] ?? [],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }

    private function send(
        string $phone,
        string $reply
    ): void {
        try {
            $this->evolution->sendText(
                $phone,
                $reply
            );

            $stmt = $this->pdo->prepare("
                INSERT INTO whatsapp_bot_messages
                    (
                        phone,
                        direction,
                        message,
                        event_name
                    )
                VALUES
                    (?, ?, ?, 'BOT_REPLY')
            ");

            $stmt->execute([
                $phone,
                'outbound',
                $reply,
            ]);
        } catch (Throwable $e) {
            error_log(
                'WhatsApp bot send failed: '
                . $e->getMessage()
            );
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace(
            '/@.*$/',
            '',
            trim($phone)
        ) ?? trim($phone);

        $phone = preg_replace(
            '/:\d+$/',
            '',
            $phone
        ) ?? $phone;

        $phone = preg_replace(
            '/\D+/',
            '',
            $phone
        ) ?? '';

        if (
            str_starts_with($phone, '0')
            && strlen($phone) === 10
        ) {
            $phone = '94'
                . substr($phone, 1);
        }

        return $phone;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(
            trim($text),
            'UTF-8'
        );

        $text = preg_replace(
            '/[[:punct:]]+/u',
            ' ',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        ) ?? $text;

        return trim($text);
    }

    private function containsAny(
        string $text,
        array $phrases
    ): bool {
        foreach ($phrases as $phrase) {
            if ($text === $phrase) {
                return true;
            }

            if (
                strlen($phrase) > 4
                && str_contains($text, $phrase)
            ) {
                return true;
            }
        }

        return false;
    }

    private function wordsOverlap(
        string $text,
        string $candidate
    ): bool {
        $words = preg_split(
            '/\s+/u',
            $candidate,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!$words) {
            return false;
        }

        $matches = 0;

        foreach ($words as $word) {
            if (strlen($word) < 3) {
                continue;
            }

            if (str_contains($text, $word)) {
                $matches++;
            }
        }

        return $matches > 0;
    }

    private function looksLikeTeacherQuestion(
        string $command
    ): bool {
        return str_contains($command, 'who')
            && (
                str_contains($command, 'teach')
                || str_contains($command, 'teacher')
            );
    }
}
