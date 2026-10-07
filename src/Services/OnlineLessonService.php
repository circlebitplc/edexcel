<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Moodle-style sequenced lesson: video clips and question activities
 * are separate database items. The player never hard-codes questions.
 */
final class OnlineLessonService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_online_lesson_schema')) {
            ensure_online_lesson_schema($this->pdo);
        }
    }

    public static function supportsDeliveryMode(string $mode): bool
    {
        $mode = strtolower(trim($mode));
        if ($mode === '') {
            $mode = 'physical';
        }
        return in_array($mode, ['physical', 'online', 'hybrid'], true);
    }

    public static function formatActiveTime(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes . 'm';
        }
        return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
    }

    public static function normalizeItemType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, ['video', 'activity', 'page', 'external_link', 'resource'], true) ? $type : 'video';
    }

    public static function defaultClipTitle(int $index, string $assetTitle = ''): string
    {
        $assetTitle = trim($assetTitle);
        return $assetTitle !== '' ? $assetTitle : ('Video Clip ' . max(1, $index));
    }

    public static function defaultActivityTitle(string $type, int $index): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'essay' => 'Essay Question ' . max(1, $index),
            'mixed' => 'Question Activity ' . max(1, $index),
            'short' => 'Short Answer ' . max(1, $index),
            'exam' => 'Exam Question ' . max(1, $index),
            'assignment' => 'Assignment ' . max(1, $index),
            'homework' => 'Homework ' . max(1, $index),
            default => 'MCQ Quiz ' . max(1, $index),
        };
    }

    public static function itemIcon(string $type): string
    {
        return match (self::normalizeItemType($type)) {
            'activity' => 'bi-ui-checks-grid',
            'page' => 'bi-file-text',
            'external_link' => 'bi-box-arrow-up-right',
            'resource' => 'bi-file-earmark',
            default => 'bi-play-circle',
        };
    }

    /**
     * Short label for a lesson item on the student class summary.
     *
     * @param array<string,mixed> $item
     */
    public static function itemKindLabel(array $item): string
    {
        $type = self::normalizeItemType((string)($item['item_type'] ?? ''));
        if ($type === 'page') {
            return 'Text page';
        }
        if ($type === 'external_link') {
            return 'External link';
        }
        if ($type === 'resource') {
            return 'Resource';
        }
        if ($type === 'activity') {
            $activity = strtolower(trim((string)($item['activity_type'] ?? 'mcq')));
            return match ($activity) {
                'essay' => 'Essay',
                'mixed' => 'Questions',
                'short' => 'Short answer',
                'exam' => 'Exam question',
                'assignment' => 'Assignment',
                'homework' => 'Homework',
                default => 'MCQ',
            };
        }
        return 'Video';
    }

    /**
     * Payment gate for a published video lesson that has no recording file yet.
     * Same unlock rule as the class page: paid, waived, or covered by a monthly fee.
     *
     * @param array<string,mixed> $fee
     */
    public static function feeUnlockState(array $fee): string
    {
        if (StudentLessonFeeService::isUnlocked((string)($fee['status'] ?? ''), !empty($fee['covered_by_monthly']))) {
            return 'ACCESS_GRANTED';
        }
        if (strtolower(trim((string)($fee['status'] ?? ''))) === 'pending') {
            return 'PAYMENT_PENDING';
        }
        return 'PAYMENT_REQUIRED';
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<int> $completedItemIds
     */
    public static function canOpenItem(array $items, array $completedItemIds, int $targetItemId, bool $sequential): bool
    {
        if ($targetItemId < 1) {
            return false;
        }
        $completed = array_fill_keys(array_map('intval', $completedItemIds), true);
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id === $targetItemId) {
                return true;
            }
            if (!$sequential) {
                continue;
            }
            $required = (int)($item['required'] ?? 1) === 1;
            if ($required && !isset($completed[$id])) {
                return false;
            }
        }
        return false;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<int> $completedItemIds
     */
    public static function resumeItemId(array $items, array $completedItemIds): int
    {
        $completed = array_fill_keys(array_map('intval', $completedItemIds), true);
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id > 0 && !isset($completed[$id])) {
                return $id;
            }
        }
        if ($items === []) {
            return 0;
        }
        return (int)($items[count($items) - 1]['id'] ?? 0);
    }

    public static function isManualQuestionType(string $type): bool
    {
        return in_array(strtolower(trim($type)), ['essay', 'short', 'exam'], true);
    }

    public static function isSubmissionActivity(string $type): bool
    {
        return in_array(strtolower(trim($type)), ['assignment', 'homework'], true);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<int> $completedItemIds
     */
    public static function remainingMinutes(array $items, array $completedItemIds): int
    {
        $completed = array_fill_keys(array_map('intval', $completedItemIds), true);
        $sum = 0;
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id < 1 || isset($completed[$id])) {
                continue;
            }
            $minutes = $item['estimated_minutes'] ?? null;
            if ($minutes !== null && $minutes !== '') {
                $sum += max(0, (int)$minutes);
            }
        }
        return $sum;
    }

    public static function normalizeExternalUrl(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/[\x00-\x1F\x7F]/', '', $raw) ?? '';
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > 500) {
            throw new RuntimeException('Enter an http or https link.');
        }
        $parts = parse_url($raw);
        $scheme = strtolower((string)(is_array($parts) ? ($parts['scheme'] ?? '') : ''));
        $host = (string)(is_array($parts) ? ($parts['host'] ?? '') : '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || preg_match('/\s/', $host)) {
            throw new RuntimeException('Only http and https links are allowed.');
        }
        return $raw;
    }

    public static function externalLinkLabel(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }
        $label = (string)($parts['host'] ?? '');
        $path = (string)($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            $label .= $path;
        }
        if (strlen($label) > 64) {
            $label = substr($label, 0, 61) . '...';
        }
        return $label;
    }

    public static function percentComplete(int $done, int $total): int
    {
        if ($total < 1) {
            return 0;
        }
        return (int)round(100 * min($done, $total) / $total);
    }

    public static function scoreMcq(?int $choice, ?int $correct): bool
    {
        return $choice !== null && $correct !== null && $choice === $correct;
    }

    public static function activityPassed(float $score, float $max, ?int $passPercent): bool
    {
        if ($max <= 0) {
            return true;
        }
        if ($passPercent === null || $passPercent <= 0) {
            return true;
        }
        return (100 * $score / $max) >= $passPercent;
    }

    /**
     * @param list<mixed> $choices
     * @return list<string>
     */
    public static function normalizeChoices(array $choices): array
    {
        $out = [];
        foreach ($choices as $choice) {
            $choice = trim((string)$choice);
            if ($choice !== '') {
                $out[] = $choice;
            }
        }
        return array_values($out);
    }

    /**
     * Asset ids on the recording that do not yet have a video item.
     *
     * @param list<int> $existingVideoAssetIds
     * @param list<int> $recordingAssetIds
     * @return list<int>
     */
    public static function missingAssetIds(array $existingVideoAssetIds, array $recordingAssetIds): array
    {
        $have = array_fill_keys(array_map('intval', $existingVideoAssetIds), true);
        $missing = [];
        foreach ($recordingAssetIds as $id) {
            $id = (int)$id;
            if ($id > 0 && !isset($have[$id])) {
                $missing[] = $id;
            }
        }
        return $missing;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array{prev:int,next:int}
     */
    public static function neighbors(array $items, int $currentId): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $prev = 0;
        $next = 0;
        foreach ($ids as $i => $id) {
            if ($id !== $currentId) {
                continue;
            }
            $prev = $i > 0 ? $ids[$i - 1] : 0;
            $next = isset($ids[$i + 1]) ? $ids[$i + 1] : 0;
            break;
        }
        return ['prev' => $prev, 'next' => $next];
    }

    public static function normalizeMinWatchPercent(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 80;
        }
        return max(0, min(100, (int)$value));
    }

    public static function watchPercent(int $watchedSeconds, int $durationSeconds): int
    {
        if ($durationSeconds < 1) {
            return 0;
        }
        return (int)floor(100 * min($watchedSeconds, $durationSeconds) / $durationSeconds);
    }

    public static function hasWatchedEnough(int $watchedSeconds, int $durationSeconds, int $minPercent, bool $ended = false): bool
    {
        $minPercent = self::normalizeMinWatchPercent($minPercent);
        if ($minPercent <= 0) {
            return true;
        }
        if ($ended) {
            return true;
        }
        if ($durationSeconds < 1) {
            return false;
        }
        return self::watchPercent($watchedSeconds, $durationSeconds) >= $minPercent;
    }

    public static function withPlayerJs(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }
        if (stripos($url, 'playerjs=') !== false) {
            return $url;
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . 'playerjs=true';
    }

    /**
     * @param list<array<string,mixed>> $students
     * @return array{students:int,started:int,completed:int,not_started:int,in_progress:int,essays_pending:int}
     */
    public static function resultsTotals(array $students): array
    {
        $totals = [
            'students' => count($students),
            'started' => 0,
            'completed' => 0,
            'not_started' => 0,
            'in_progress' => 0,
            'essays_pending' => 0,
        ];
        foreach ($students as $row) {
            $status = (string)($row['status'] ?? 'not_started');
            if ($status === 'complete') {
                $totals['completed']++;
                $totals['started']++;
            } elseif ($status === 'in_progress') {
                $totals['in_progress']++;
                $totals['started']++;
            } else {
                $totals['not_started']++;
            }
            $totals['essays_pending'] += (int)($row['essays_pending'] ?? 0);
        }
        return $totals;
    }

    /**
     * Deterministic shuffle order (0..count-1) for a student/question seed.
     *
     * @return list<int>
     */
    public static function permutation(int $count, string $seed): array
    {
        if ($count < 1) {
            return [];
        }
        $order = range(0, $count - 1);
        $material = $seed;
        for ($i = $count - 1; $i > 0; $i--) {
            $material = hash('sha256', $material . ':' . $i);
            $n = hexdec(substr($material, 0, 8));
            $j = $n % ($i + 1);
            $tmp = $order[$i];
            $order[$i] = $order[$j];
            $order[$j] = $tmp;
        }
        return $order;
    }

    public static function originalChoiceIndex(int $studentId, int $questionId, int $choiceCount, ?int $displayIndex): ?int
    {
        if ($displayIndex === null || $choiceCount < 1) {
            return $displayIndex;
        }
        $order = self::permutation($choiceCount, 'c:' . $studentId . ':' . $questionId);
        return $order[$displayIndex] ?? null;
    }

    /**
     * @return array{open:bool,message:string,opens_at:int,closes_at:int}
     */
    public static function availabilityWindow(array $lesson, array $timetable, ?int $now = null): array
    {
        $now = $now ?? time();
        $afterClass = (int)($lesson['available_after_class'] ?? 0) === 1;
        $closeDays = max(0, (int)($lesson['close_after_days'] ?? 0));
        $end = self::classEndedAt($timetable);
        $opensAt = ($afterClass && $end > 0) ? $end : 0;
        $closesAt = ($closeDays > 0 && $end > 0) ? strtotime('+' . $closeDays . ' days', $end) : 0;
        $until = trim((string)($lesson['unpublish_at'] ?? ''));
        $untilTs = $until !== '' ? strtotime($until) : false;
        if ($untilTs !== false && $untilTs > 0 && ($closesAt === 0 || $untilTs < $closesAt)) {
            $closesAt = $untilTs;
        }
        if ($opensAt > 0 && $now < $opensAt) {
            return [
                'open' => false,
                'message' => 'This lesson opens after class ends on ' . date('d M Y, g:i A', $opensAt) . '.',
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ];
        }
        if ($closesAt > 0 && $now > $closesAt) {
            return [
                'open' => false,
                'message' => 'This lesson closed on ' . date('d M Y, g:i A', $closesAt) . '.',
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ];
        }
        return [
            'open' => true,
            'message' => '',
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ];
    }

    public static function classEndedAt(array $timetable): int
    {
        $date = trim((string)($timetable['date'] ?? ''));
        if ($date === '') {
            return 0;
        }
        $end = trim((string)($timetable['end_time'] ?? '23:59:59'));
        $ts = strtotime($date . ' ' . $end);
        return $ts ?: 0;
    }

    /**
     * @param array{totals?:array<string,int>,students?:list<array<string,mixed>>} $results
     */
    public static function resultsCsv(array $results): string
    {
        $lines = ['Student,Status,Completed,Total,Percent,Now on,Quizzes,Essays to mark,Last seen'];
        foreach (($results['students'] ?? []) as $row) {
            $quizzes = [];
            foreach (($row['quizzes'] ?? []) as $quiz) {
                $score = $quiz['score'] === null ? '-' : (string)$quiz['score'];
                $max = $quiz['max_score'] === null ? '' : '/' . $quiz['max_score'];
                $quizzes[] = (string)($quiz['title'] ?? 'Quiz') . ' ' . $score . $max;
            }
            $lines[] = self::csvRow([
                (string)($row['name'] ?? ''),
                (string)($row['status'] ?? ''),
                (string)(int)($row['completed_count'] ?? 0),
                (string)(int)($row['total'] ?? 0),
                (string)(int)($row['percent'] ?? 0),
                (string)($row['current_title'] ?? ''),
                implode('; ', $quizzes),
                (string)(int)($row['essays_pending'] ?? 0),
                (string)($row['last_seen_at'] ?? ''),
            ]);
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param list<string> $fields
     */
    public static function csvRow(array $fields): string
    {
        $out = [];
        foreach ($fields as $field) {
            $field = str_replace('"', '""', $field);
            if (strpbrk($field, ",\"\r\n") !== false) {
                $field = '"' . $field . '"';
            }
            $out[] = $field;
        }
        return implode(',', $out);
    }

    /**
     * Moodle Aiken MCQ text. Questions end with ANSWER: A (or B, C, …).
     *
     * @return array{questions:list<array{prompt:string,choices:list<string>,correct_index:int}>,errors:list<string>}
     */
    public static function parseAiken(string $text): array
    {
        $text = self::normalizeAikenText($text);
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $questions = [];
        $errors = [];
        $n = count($lines);
        $i = 0;
        $qnum = 0;

        while ($i < $n) {
            while ($i < $n && trim((string)$lines[$i]) === '') {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            $startLine = $i + 1;
            $qnum++;
            $stem = [];
            while ($i < $n) {
                $raw = (string)$lines[$i];
                $trim = trim($raw);
                if ($trim === '') {
                    break;
                }
                if (self::aikenAnswerLetter($trim) !== null) {
                    break;
                }
                if (self::aikenChoice($trim) !== null) {
                    break;
                }
                $stem[] = $trim;
                $i++;
            }
            $choices = [];
            $letters = [];
            while ($i < $n) {
                $trim = trim((string)$lines[$i]);
                if ($trim === '') {
                    $i++;
                    if ($choices !== [] && $i < $n && self::aikenAnswerLetter(trim((string)$lines[$i])) !== null) {
                        continue;
                    }
                    if ($choices !== []) {
                        break;
                    }
                    continue;
                }
                if (self::aikenAnswerLetter($trim) !== null) {
                    break;
                }
                $choice = self::aikenChoice($trim);
                if ($choice === null) {
                    break;
                }
                $letters[] = $choice['letter'];
                $choices[] = $choice['text'];
                $i++;
            }
            $answer = null;
            if ($i < $n) {
                $answer = self::aikenAnswerLetter(trim((string)$lines[$i]));
                if ($answer !== null) {
                    $i++;
                }
            }
            $prompt = trim(implode("\n", $stem));
            if ($prompt === '') {
                $errors[] = 'Block starting at line ' . $startLine . ': missing question text.';
                continue;
            }
            if (count($choices) < 2) {
                $errors[] = 'Question ' . $qnum . ' (line ' . $startLine . '): need at least two choices (A. … B. …).';
                continue;
            }
            if ($answer === null) {
                $errors[] = 'Question ' . $qnum . ' (line ' . $startLine . '): missing ANSWER: line.';
                continue;
            }
            $correct = array_search($answer, $letters, true);
            if ($correct === false) {
                $errors[] = 'Question ' . $qnum . ' (line ' . $startLine . '): ANSWER: ' . $answer . ' does not match a listed choice.';
                continue;
            }
            $questions[] = [
                'prompt' => $prompt,
                'choices' => $choices,
                'correct_index' => (int)$correct,
            ];
        }

        return ['questions' => $questions, 'errors' => $errors];
    }

    public static function normalizeAikenText(string $text): string
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        if ($text !== '' && !mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-16LE,UTF-16BE,Windows-1252,ISO-8859-1');
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }
        return $text;
    }

    /**
     * @param array<string,mixed> $file $_FILES row
     */
    public static function aikenFromUpload(array $file): string
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Choose an Aiken .txt file.');
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Could not upload that file.');
        }
        if ((int)($file['size'] ?? 0) > 1024 * 1024) {
            throw new RuntimeException('Aiken file must be 1 MB or smaller.');
        }
        $name = (string)($file['name'] ?? 'questions.txt');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'text', 'aiken'], true)) {
            throw new RuntimeException('Upload a Moodle Aiken file with a .txt extension.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            throw new RuntimeException('Could not read the uploaded file.');
        }
        if (PHP_SAPI !== 'cli' && function_exists('is_uploaded_file') && !is_uploaded_file($tmp)) {
            throw new RuntimeException('Could not read the uploaded file.');
        }
        $bytes = file_get_contents($tmp);
        if (!is_string($bytes) || trim($bytes) === '') {
            throw new RuntimeException('That file is empty.');
        }
        return self::normalizeAikenText($bytes);
    }

    /**
     * @return array{letter:string,text:string}|null
     */
    private static function aikenChoice(string $line): ?array
    {
        if (!preg_match('/^([A-Za-z])[.)]\s+(\S.*)$/u', $line, $m)) {
            return null;
        }
        return [
            'letter' => strtoupper($m[1]),
            'text' => trim($m[2]),
        ];
    }

    private static function aikenAnswerLetter(string $line): ?string
    {
        if (!preg_match('/^ANSWER:\s*([A-Za-z])\s*\.?\s*$/iu', $line, $m)) {
            return null;
        }
        return strtoupper($m[1]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lessons WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByTimetable(int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lessons WHERE timetable_id = ? LIMIT 1');
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    /**
     * A lesson with a future publish_at stays hidden until that time.
     */
    public function findPublishedByTimetable(int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lessons WHERE timetable_id = ? AND published = 1 LIMIT 1');
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !self::isReleased($row)) {
            return null;
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $lesson
     */
    public static function isReleased(array $lesson, ?int $now = null): bool
    {
        if ((int)($lesson['published'] ?? 0) !== 1) {
            return false;
        }
        $publishAt = trim((string)($lesson['publish_at'] ?? ''));
        if ($publishAt === '') {
            return true;
        }
        $ts = strtotime($publishAt);
        return $ts === false || $ts <= ($now ?? time());
    }

    /**
     * draft, scheduled, published, closed, or archived.
     *
     * @param array<string,mixed> $lesson
     */
    public static function publicationState(array $lesson, ?int $now = null): string
    {
        $now = $now ?? time();
        if (!empty($lesson['archived'])) {
            return 'archived';
        }
        if ((int)($lesson['published'] ?? 0) !== 1) {
            return 'draft';
        }
        if (!self::isReleased($lesson, $now)) {
            return 'scheduled';
        }
        $until = trim((string)($lesson['unpublish_at'] ?? ''));
        if ($until !== '' && ($ts = strtotime($until)) !== false && $ts <= $now) {
            return 'closed';
        }
        return 'published';
    }

    /**
     * @param list<int> $timetableIds
     * @return array<int,array{id:int,published:int,item_count:int}>
     */
    public function mapForLessons(array $timetableIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $timetableIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT ol.*,
                   (SELECT COUNT(*) FROM online_lesson_items i WHERE i.lesson_id = ol.id) AS item_count
            FROM online_lessons ol
            WHERE ol.timetable_id IN ($placeholders)
        ");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['timetable_id']] = [
                'id' => (int)$row['id'],
                'published' => self::isReleased($row) ? 1 : 0,
                'archived' => (int)($row['archived'] ?? 0),
                'item_count' => (int)$row['item_count'],
                'state' => self::publicationState($row),
                'publish_at' => (string)($row['publish_at'] ?? ''),
            ];
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $lesson timetable row
     * @param array<string,mixed>|null $recording
     * @return array<string,mixed>
     */
    public function getOrCreateForTimetable(array $lesson, ?array $recording, int $userId): array
    {
        if (!self::supportsDeliveryMode((string)($lesson['delivery_mode'] ?? 'physical'))) {
            throw new RuntimeException('That class cannot have a video lesson.');
        }
        $timetableId = (int)($lesson['id'] ?? 0);
        if ($timetableId < 1) {
            throw new RuntimeException('Lesson was not found.');
        }
        $existing = $this->findByTimetable($timetableId);
        if ($existing) {
            if ($recording && (int)($existing['recording_id'] ?? 0) !== (int)$recording['id']) {
                $this->pdo->prepare('UPDATE online_lessons SET recording_id = ? WHERE id = ?')
                    ->execute([(int)$recording['id'], (int)$existing['id']]);
            }
            if ($recording) {
                $this->syncVideoItems((int)$existing['id'], (int)$recording['id']);
            }
            return $this->findByTimetable($timetableId) ?? $existing;
        }

        $title = trim((string)($recording['title'] ?? ''));
        if ($title === '') {
            $title = trim((string)($lesson['subject_name'] ?? 'Lesson') . ' — ' . date('d M Y', strtotime((string)($lesson['date'] ?? 'now'))));
        }
        $this->pdo->prepare('
            INSERT INTO online_lessons (timetable_id, recording_id, title, created_by)
            VALUES (?, ?, ?, ?)
        ')->execute([
            $timetableId,
            $recording ? (int)$recording['id'] : null,
            mb_substr($title, 0, 200),
            $userId > 0 ? $userId : null,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        if ($recording) {
            $this->syncVideoItems($id, (int)$recording['id']);
        }
        $row = $this->find($id);
        if (!$row) {
            throw new RuntimeException('Could not create the video lesson.');
        }
        return $row;
    }

    public function syncVideoItems(int $lessonId, int $recordingId): int
    {
        $assets = (new RecordingService($this->pdo))->assets($recordingId);
        $items = $this->items($lessonId);
        $have = [];
        $maxSort = 0;
        foreach ($items as $item) {
            $maxSort = max($maxSort, (int)$item['sort_order']);
            if ((string)$item['item_type'] === 'video') {
                $have[(int)$item['video_asset_id']] = (int)$item['id'];
            }
        }
        $added = 0;
        $index = 0;
        foreach ($assets as $asset) {
            $index++;
            $assetId = (int)$asset['id'];
            if (isset($have[$assetId])) {
                continue;
            }
            $maxSort++;
            $this->insertItem(
                $lessonId,
                'video',
                self::defaultClipTitle($index, (string)($asset['title'] ?? '')),
                $maxSort,
                $assetId,
                null,
                null
            );
            $added++;
        }
        return $added;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function items(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.*, a.activity_type, a.pass_percent, a.max_attempts, a.show_correct, a.instructions AS activity_instructions,
                   a.due_at, a.max_marks, a.allow_text, a.allow_file,
                   (SELECT COUNT(*) FROM online_lesson_questions q WHERE q.activity_id = i.activity_id) AS question_count
            FROM online_lesson_items i
            LEFT JOIN online_lesson_activities a ON a.id = i.activity_id
            WHERE i.lesson_id = ?
            ORDER BY i.sort_order ASC, i.id ASC
        ');
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function item(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.*, a.activity_type, a.pass_percent, a.max_attempts, a.show_correct,
                   a.title AS activity_title, a.instructions AS activity_instructions,
                   a.due_at, a.max_marks, a.allow_text, a.allow_file
            FROM online_lesson_items i
            LEFT JOIN online_lesson_activities a ON a.id = i.activity_id
            WHERE i.id = ?
            LIMIT 1
        ');
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function publish(int $lessonId, bool $published): void
    {
        $this->pdo->prepare('UPDATE online_lessons SET published = ? WHERE id = ?')
            ->execute([$published ? 1 : 0, $lessonId]);
        try {
            $this->pdo->prepare('UPDATE online_lessons SET publish_at = NULL WHERE id = ?')->execute([$lessonId]);
        } catch (\PDOException $e) {
            // column added by the phase 3 schema
        }
    }

    public function saveMeta(
        int $lessonId,
        string $title,
        string $intro,
        bool $sequential,
        int $minWatchPercent = 80,
        bool $availableAfterClass = false,
        int $closeAfterDays = 0
    ): void {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a lesson title.');
        }
        $minWatchPercent = self::normalizeMinWatchPercent($minWatchPercent);
        $closeAfterDays = max(0, min(365, $closeAfterDays));
        $params = [
            $title,
            $intro !== '' ? $intro : null,
            $sequential ? 1 : 0,
            $minWatchPercent,
            $availableAfterClass ? 1 : 0,
            $closeAfterDays,
            $lessonId,
        ];
        try {
            $this->pdo->prepare('
                UPDATE online_lessons
                SET title = ?, intro = ?, sequential = ?, min_watch_percent = ?,
                    available_after_class = ?, close_after_days = ?
                WHERE id = ?
            ')->execute($params);
        } catch (\Throwable $e) {
            $this->pdo->prepare('UPDATE online_lessons SET title = ?, intro = ?, sequential = ?, min_watch_percent = ? WHERE id = ?')
                ->execute([$title, $intro !== '' ? $intro : null, $sequential ? 1 : 0, $minWatchPercent, $lessonId]);
        }
    }

    public function renameItem(int $itemId, string $title): void
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter an item title.');
        }
        $this->pdo->prepare('UPDATE online_lesson_items SET title = ? WHERE id = ?')->execute([$title, $itemId]);
    }

    public function moveItem(int $lessonId, int $itemId, string $direction): void
    {
        $items = $this->items($lessonId);
        $index = -1;
        foreach ($items as $i => $item) {
            if ((int)$item['id'] === $itemId) {
                $index = $i;
                break;
            }
        }
        if ($index < 0) {
            throw new RuntimeException('That lesson item was not found.');
        }
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($items[$swapWith])) {
            return;
        }
        $a = (int)$items[$index]['id'];
        $b = (int)$items[$swapWith]['id'];
        $sortA = (int)$items[$index]['sort_order'];
        $sortB = (int)$items[$swapWith]['sort_order'];
        $this->pdo->prepare('UPDATE online_lesson_items SET sort_order = ? WHERE id = ?')->execute([$sortB, $a]);
        $this->pdo->prepare('UPDATE online_lesson_items SET sort_order = ? WHERE id = ?')->execute([$sortA, $b]);
    }

    /**
     * @return array<string,mixed>
     */
    public function addPageAfter(int $lessonId, int $afterItemId, string $title, string $body): array
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = 'Introduction';
        }
        $sort = $this->sortAfter($lessonId, $afterItemId);
        $id = $this->insertItem($lessonId, 'page', $title, $sort, null, null, $body);
        $item = $this->item($id);
        if (!$item) {
            throw new RuntimeException('Could not add the page.');
        }
        return $item;
    }

    /**
     * @return array<string,mixed>
     */
    public function addActivityAfter(int $lessonId, int $afterItemId, string $type, string $title): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['mcq', 'essay', 'mixed', 'short', 'exam', 'assignment', 'homework'], true)) {
            $type = 'mcq';
        }
        $count = 1;
        foreach ($this->items($lessonId) as $item) {
            if ((string)$item['item_type'] === 'activity') {
                $count++;
            }
        }
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = self::defaultActivityTitle($type, $count);
        }
        $this->pdo->prepare('
            INSERT INTO online_lesson_activities (lesson_id, activity_type, title)
            VALUES (?, ?, ?)
        ')->execute([$lessonId, $type, $title]);
        $activityId = (int)$this->pdo->lastInsertId();
        $sort = $this->sortAfter($lessonId, $afterItemId);
        $itemId = $this->insertItem($lessonId, 'activity', $title, $sort, null, $activityId, null);
        $item = $this->item($itemId);
        if (!$item) {
            throw new RuntimeException('Could not add the activity.');
        }
        return $item;
    }

    public function deleteItem(int $lessonId, int $itemId): void
    {
        $item = $this->item($itemId);
        if (!$item || (int)$item['lesson_id'] !== $lessonId) {
            throw new RuntimeException('That lesson item was not found.');
        }
        if ((string)$item['item_type'] === 'video') {
            throw new RuntimeException('Video clips come from the class recording. Remove the clip there, not from the lesson.');
        }
        $activityId = (int)($item['activity_id'] ?? 0);
        $this->pdo->prepare('DELETE FROM online_lesson_items WHERE id = ? AND lesson_id = ?')->execute([$itemId, $lessonId]);
        if ($activityId > 0) {
            $this->pdo->prepare('DELETE FROM online_lesson_questions WHERE activity_id = ?')->execute([$activityId]);
            $this->pdo->prepare('DELETE FROM online_lesson_activities WHERE id = ?')->execute([$activityId]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function activity(int $activityId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_activities WHERE id = ? LIMIT 1');
        $stmt->execute([$activityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveActivity(
        int $activityId,
        string $title,
        string $instructions,
        string $type,
        ?int $passPercent,
        int $maxAttempts,
        bool $showCorrect,
        bool $shuffleChoices = false,
        bool $shuffleQuestions = false,
        ?string $dueAt = null,
        ?float $maxMarks = null,
        ?bool $allowText = null,
        ?bool $allowFile = null
    ): void {
        $type = strtolower(trim($type));
        if (!in_array($type, ['mcq', 'essay', 'mixed', 'short', 'exam', 'assignment', 'homework'], true)) {
            $type = 'mcq';
        }
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter an activity title.');
        }
        if ($passPercent !== null) {
            $passPercent = max(0, min(100, $passPercent));
        }
        $maxAttempts = max(0, $maxAttempts);
        try {
            $this->pdo->prepare('
                UPDATE online_lesson_activities
                SET title = ?, instructions = ?, activity_type = ?, pass_percent = ?, max_attempts = ?,
                    show_correct = ?, shuffle_choices = ?, shuffle_questions = ?
                WHERE id = ?
            ')->execute([
                $title,
                $instructions !== '' ? $instructions : null,
                $type,
                $passPercent,
                $maxAttempts,
                $showCorrect ? 1 : 0,
                $shuffleChoices ? 1 : 0,
                $shuffleQuestions ? 1 : 0,
                $activityId,
            ]);
        } catch (\Throwable $e) {
            $this->pdo->prepare('
                UPDATE online_lesson_activities
                SET title = ?, instructions = ?, activity_type = ?, pass_percent = ?, max_attempts = ?, show_correct = ?
                WHERE id = ?
            ')->execute([
                $title,
                $instructions !== '' ? $instructions : null,
                $type,
                $passPercent,
                $maxAttempts,
                $showCorrect ? 1 : 0,
                $activityId,
            ]);
        }
        $this->pdo->prepare('UPDATE online_lesson_items SET title = ? WHERE activity_id = ?')->execute([$title, $activityId]);
        if (self::isSubmissionActivity($type)) {
            $this->pdo->prepare('
                UPDATE online_lesson_activities
                SET due_at = ?, max_marks = ?, allow_text = ?, allow_file = ?
                WHERE id = ?
            ')->execute([
                $dueAt,
                $maxMarks !== null ? max(0, round($maxMarks, 2)) : null,
                $allowText === false ? 0 : 1,
                $allowFile === false ? 0 : 1,
                $activityId,
            ]);
        }
    }

    /**
     * Pool, time limit, scoring rule, and result display for a question activity.
     * A draw count of 0 means every student gets every question.
     */
    public function savePoolSettings(
        int $activityId,
        int $drawCount,
        ?string $scoringRule,
        int $timeLimitMinutes,
        bool $showScore,
        bool $showExplanation
    ): void {
        $drawCount = max(0, min(500, $drawCount));
        $timeLimitMinutes = max(0, min(600, $timeLimitMinutes));
        $this->pdo->prepare('
            UPDATE online_lesson_activities
            SET draw_count = ?, scoring_rule = ?, time_limit_minutes = ?, show_score = ?, show_explanation = ?
            WHERE id = ?
        ')->execute([
            $drawCount > 0 ? $drawCount : null,
            QuestionPool::normalizeRule($scoringRule),
            $timeLimitMinutes > 0 ? $timeLimitMinutes : null,
            $showScore ? 1 : 0,
            $showExplanation ? 1 : 0,
            $activityId,
        ]);
    }

    public function setQuestionTags(int $questionId, string $tags): void
    {
        $this->pdo->prepare('UPDATE online_lesson_questions SET tags = ? WHERE id = ?')
            ->execute([QuestionPool::normalizeTags($tags), $questionId]);
    }

    public function markQuestionAiGenerated(int $questionId, bool $generated): void
    {
        $this->pdo->prepare('UPDATE online_lesson_questions SET ai_generated = ? WHERE id = ?')
            ->execute([$generated ? 1 : 0, $questionId]);
    }

    /**
     * The record of which questions a student sees in their next (or current) attempt.
     * Only used when the activity draws from a pool or has a time limit.
     *
     * @param array<string,mixed> $activity
     * @param list<array<string,mixed>> $questions
     * @return array{attempt_no:int,question_ids:list<int>,started_at:string}|null
     */
    public function attemptSession(int $studentId, int $itemId, array $activity, array $questions, bool $create): ?array
    {
        if (!QuestionPool::usesSession($activity)) {
            return null;
        }
        $attemptNo = $studentId > 0 ? $this->attemptCount($studentId, $itemId) + 1 : 1;
        $orderedIds = array_map(static fn (array $q): int => (int)$q['id'], $questions);
        $draw = static fn (): array => QuestionPool::drawQuestionIds(
            $orderedIds,
            (int)($activity['draw_count'] ?? 0),
            $studentId,
            (int)($activity['id'] ?? 0),
            $attemptNo,
            (int)($activity['shuffle_questions'] ?? 0) === 1
        );
        if ($studentId < 1) {
            return ['attempt_no' => $attemptNo, 'question_ids' => $draw(), 'started_at' => date('Y-m-d H:i:s')];
        }
        $stmt = $this->pdo->prepare('
            SELECT * FROM online_lesson_attempt_sessions
            WHERE student_id = ? AND item_id = ? AND attempt_no = ?
            LIMIT 1
        ');
        $stmt->execute([$studentId, $itemId, $attemptNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)($row['live_only'] ?? 0) === 1) {
            // Written by the live monitor before a pool or timer applied; it holds no draw and no start time.
            if (!$create) {
                return null;
            }
            $ids = $draw();
            $startedAt = date('Y-m-d H:i:s');
            $this->pdo->prepare('
                UPDATE online_lesson_attempt_sessions
                SET question_ids_json = ?, started_at = ?, live_only = 0
                WHERE student_id = ? AND item_id = ? AND attempt_no = ?
            ')->execute([json_encode($ids), $startedAt, $studentId, $itemId, $attemptNo]);
            return ['attempt_no' => $attemptNo, 'question_ids' => $ids, 'started_at' => $startedAt];
        }
        if ($row) {
            $ids = QuestionPool::decodeIds((string)$row['question_ids_json']) ?? $draw();
            $present = array_fill_keys($orderedIds, true);
            $ids = array_values(array_filter($ids, static fn (int $id): bool => isset($present[$id])));
            return ['attempt_no' => $attemptNo, 'question_ids' => $ids, 'started_at' => (string)$row['started_at']];
        }
        if (!$create) {
            return null;
        }
        $ids = $draw();
        $startedAt = date('Y-m-d H:i:s');
        try {
            $this->pdo->prepare('
                INSERT INTO online_lesson_attempt_sessions (student_id, item_id, attempt_no, question_ids_json, started_at)
                VALUES (?, ?, ?, ?, ?)
            ')->execute([$studentId, $itemId, $attemptNo, json_encode($ids), $startedAt]);
        } catch (\PDOException $e) {
            $stmt->execute([$studentId, $itemId, $attemptNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'attempt_no' => $attemptNo,
                    'question_ids' => QuestionPool::decodeIds((string)$row['question_ids_json']) ?? $ids,
                    'started_at' => (string)$row['started_at'],
                ];
            }
            throw $e;
        }
        return ['attempt_no' => $attemptNo, 'question_ids' => $ids, 'started_at' => $startedAt];
    }

    /**
     * Starts the timer for a timed activity. Returns the existing session if one is already running.
     *
     * @return array{attempt_no:int,question_ids:list<int>,started_at:string}|null
     */
    public function startAttempt(int $studentId, int $itemId): ?array
    {
        $item = $this->item($itemId);
        if (!$item || (string)$item['item_type'] !== 'activity') {
            throw new RuntimeException('That is not a question activity.');
        }
        $activity = $this->activity((int)$item['activity_id']);
        if (!$activity) {
            throw new RuntimeException('Activity was not found.');
        }
        $latest = $this->latestAttempt($studentId, $itemId);
        $maxAttempts = (int)($activity['max_attempts'] ?? 0);
        if ($latest && ((int)$latest['passed'] === 1 || ($maxAttempts > 0 && (int)$latest['attempt_no'] >= $maxAttempts))) {
            throw new RuntimeException('No attempts are left for this activity.');
        }
        return $this->attemptSession($studentId, $itemId, $activity, $this->studentQuestions((int)$activity['id']), true);
    }

    public function currentVersionId(int $lessonId): ?int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT MAX(id) FROM online_lesson_versions WHERE lesson_id = ?');
            $stmt->execute([$lessonId]);
            $id = (int)$stmt->fetchColumn();
            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function questions(int $activityId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM online_lesson_questions
            WHERE activity_id = ?
            ORDER BY sort_order ASC, id ASC
        ');
        $stmt->execute([$activityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['choices'] = $this->decodeChoices((string)($row['choices_json'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    /**
     * Questions students can see: AI-generated questions stay hidden until the teacher reviews them.
     *
     * @return list<array<string,mixed>>
     */
    public function studentQuestions(int $activityId): array
    {
        return array_values(array_filter(
            $this->questions($activityId),
            static fn (array $q): bool => (int)($q['ai_generated'] ?? 0) !== 1
        ));
    }

    /**
     * @param list<string> $choices
     */
    public function addQuestion(
        int $activityId,
        string $questionType,
        string $prompt,
        array $choices,
        ?int $correctIndex,
        float $marks,
        string $explanation,
        string $topic = '',
        string $difficulty = '',
        string $examRef = '',
        string $expectedAnswer = ''
    ): int {
        $questionType = strtolower(trim($questionType));
        if (!in_array($questionType, ['mcq', 'essay', 'short', 'exam'], true)) {
            $questionType = 'mcq';
        }
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Enter the question.');
        }
        $choices = self::normalizeChoices($choices);
        if ($questionType === 'mcq') {
            if (count($choices) < 2) {
                throw new RuntimeException('Add at least two answer choices.');
            }
            if ($correctIndex === null || $correctIndex < 0 || $correctIndex >= count($choices)) {
                throw new RuntimeException('Mark the correct choice.');
            }
        } else {
            $choices = [];
            $correctIndex = null;
        }
        $sortStmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM online_lesson_questions WHERE activity_id = ?');
        $sortStmt->execute([$activityId]);
        $sort = (int)$sortStmt->fetchColumn();
        $marksValue = max(0.5, round($marks, 2));
        $choiceJson = $choices === [] ? null : json_encode($choices, JSON_UNESCAPED_UNICODE);
        $explanationValue = $explanation !== '' ? $explanation : null;
        try {
            $this->pdo->prepare('
                INSERT INTO online_lesson_questions
                    (activity_id, question_type, sort_order, prompt, choices_json, correct_index, marks, explanation,
                     topic, difficulty, exam_ref, expected_answer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $activityId,
                $questionType,
                $sort,
                $prompt,
                $choiceJson,
                $correctIndex,
                $marksValue,
                $explanationValue,
                self::blankMeta($topic, 120),
                self::blankMeta($difficulty, 40),
                self::blankMeta($examRef, 80),
                trim($expectedAnswer) !== '' ? $expectedAnswer : null,
            ]);
        } catch (\Throwable $e) {
            $this->pdo->prepare('
                INSERT INTO online_lesson_questions
                    (activity_id, question_type, sort_order, prompt, choices_json, correct_index, marks, explanation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $activityId,
                $questionType === 'mcq' ? 'mcq' : 'essay',
                $sort,
                $prompt,
                $choiceJson,
                $correctIndex,
                $marksValue,
                $explanationValue,
            ]);
        }
        $this->refreshActivityType($activityId);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return array{imported:int,skipped:int,errors:list<string>}
     */
    public function importAiken(int $activityId, string $text, float $marks = 1.0): array
    {
        $parsed = self::parseAiken($text);
        if ($parsed['questions'] === []) {
            $hint = $parsed['errors'] !== []
                ? implode(' ', array_slice($parsed['errors'], 0, 3))
                : 'Each question needs a stem, choices like A. … B. …, then ANSWER: B';
            throw new RuntimeException('No Aiken MCQs found. ' . $hint);
        }
        $imported = 0;
        foreach ($parsed['questions'] as $question) {
            $this->addQuestion(
                $activityId,
                'mcq',
                (string)$question['prompt'],
                $question['choices'],
                (int)$question['correct_index'],
                $marks,
                ''
            );
            $imported++;
        }
        return [
            'imported' => $imported,
            'skipped' => count($parsed['errors']),
            'errors' => $parsed['errors'],
        ];
    }

    /**
     * @param list<string> $choices
     */
    public function updateQuestion(
        int $questionId,
        string $prompt,
        array $choices,
        ?int $correctIndex,
        float $marks,
        string $explanation,
        ?array $meta = null
    ): void {
        $row = $this->question($questionId);
        if (!$row) {
            throw new RuntimeException('Question not found.');
        }
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Enter the question.');
        }
        $type = (string)$row['question_type'];
        $choices = self::normalizeChoices($choices);
        if ($type === 'mcq') {
            if (count($choices) < 2) {
                throw new RuntimeException('Add at least two answer choices.');
            }
            if ($correctIndex === null || $correctIndex < 0 || $correctIndex >= count($choices)) {
                throw new RuntimeException('Mark the correct choice.');
            }
        } else {
            $choices = [];
            $correctIndex = null;
        }
        $this->pdo->prepare('
            UPDATE online_lesson_questions
            SET prompt = ?, choices_json = ?, correct_index = ?, marks = ?, explanation = ?
            WHERE id = ?
        ')->execute([
            $prompt,
            $choices === [] ? null : json_encode($choices, JSON_UNESCAPED_UNICODE),
            $correctIndex,
            max(0.5, round($marks, 2)),
            $explanation !== '' ? $explanation : null,
            $questionId,
        ]);
        if (!empty($row['ai_generated'])) {
            $this->markQuestionAiGenerated($questionId, false);
        }
        if ($meta !== null) {
            $this->pdo->prepare('
                UPDATE online_lesson_questions
                SET topic = ?, difficulty = ?, exam_ref = ?, expected_answer = ?
                WHERE id = ?
            ')->execute([
                self::blankMeta((string)($meta['topic'] ?? ''), 120),
                self::blankMeta((string)($meta['difficulty'] ?? ''), 40),
                self::blankMeta((string)($meta['exam_ref'] ?? ''), 80),
                trim((string)($meta['expected_answer'] ?? '')) !== '' ? (string)$meta['expected_answer'] : null,
                $questionId,
            ]);
        }
    }

    public function duplicateQuestion(int $questionId, int $lessonId): int
    {
        $row = $this->question($questionId);
        if (!$row) {
            throw new RuntimeException('Question not found.');
        }
        $activity = $this->activity((int)$row['activity_id']);
        if (!$activity || (int)$activity['lesson_id'] !== $lessonId) {
            throw new RuntimeException('That question is not part of this lesson.');
        }
        $copy = LearningModuleService::independentQuestionCopy($row);
        return $this->addQuestion(
            (int)$row['activity_id'],
            (string)$copy['question_type'],
            'Copy of ' . (string)$copy['prompt'],
            $copy['choices'],
            $copy['correct_index'],
            (float)$copy['marks'],
            (string)$copy['explanation'],
            (string)$copy['topic'],
            (string)$copy['difficulty'],
            (string)$copy['exam_ref'],
            (string)$copy['expected_answer']
        );
    }

    public function deleteQuestion(int $questionId): void
    {
        $row = $this->question($questionId);
        if (!$row) {
            return;
        }
        $this->pdo->prepare('DELETE FROM online_lesson_questions WHERE id = ?')->execute([$questionId]);
        $this->refreshActivityType((int)$row['activity_id']);
    }

    /**
     * @return list<int>
     */
    public function completedItemIds(int $studentId, int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.item_id
            FROM online_lesson_item_state s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE s.student_id = ? AND i.lesson_id = ? AND s.status = \'completed\'
        ');
        $stmt->execute([$studentId, $lessonId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function touchProgress(int $studentId, int $lessonId, int $currentItemId): void
    {
        $completed = count($this->completedItemIds($studentId, $lessonId));
        $total = count($this->items($lessonId));
        $done = $total > 0 && $completed >= $total;
        $this->pdo->prepare('
            INSERT INTO online_lesson_progress (student_id, lesson_id, current_item_id, completed_count, completed_at, last_seen_at)
            VALUES (?, ?, ?, ?, IF(?, NOW(), NULL), NOW())
            ON DUPLICATE KEY UPDATE
                current_item_id = VALUES(current_item_id),
                completed_count = VALUES(completed_count),
                completed_at = IF(?, COALESCE(completed_at, NOW()), completed_at),
                last_seen_at = NOW()
        ')->execute([$studentId, $lessonId, $currentItemId > 0 ? $currentItemId : null, $completed, $done ? 1 : 0, $done ? 1 : 0]);
    }

    public function markItemComplete(int $studentId, int $itemId): void
    {
        $this->pdo->prepare('
            INSERT INTO online_lesson_item_state (student_id, item_id, status, completed_at)
            VALUES (?, ?, \'completed\', NOW())
            ON DUPLICATE KEY UPDATE
                status = \'completed\',
                completed_at = COALESCE(completed_at, NOW())
        ')->execute([$studentId, $itemId]);
        $item = $this->item($itemId);
        if ($item) {
            $this->touchProgress($studentId, (int)$item['lesson_id'], $itemId);
        }
    }

    /**
     * @param array<string,mixed> $lesson
     * @param array<string,mixed> $item
     */
    public function completeNavigableItem(int $studentId, array $lesson, array $item): void
    {
        $itemId = (int)($item['id'] ?? 0);
        $type = self::normalizeItemType((string)($item['item_type'] ?? ''));
        if ($type === 'activity' && !$this->isItemComplete($studentId, $itemId)) {
            throw new RuntimeException('Submit the questions before continuing.');
        }
        if ($type === 'video' && !$this->isItemComplete($studentId, $itemId)) {
            $watch = $this->watchState($studentId, $lesson, $item);
            if (!empty($watch['required']) && empty($watch['enough'])) {
                throw new RuntimeException('Watch this clip before continuing.');
            }
        }
        if ($type === 'external_link' && (int)($item['required'] ?? 1) === 1 && !$this->isItemComplete($studentId, $itemId)) {
            throw new RuntimeException('Open the learning material before continuing.');
        }
        $this->markItemComplete($studentId, $itemId);
    }

    /**
     * @param array<string,mixed> $lesson
     * @param array<string,mixed> $item
     * @return array{
     *   required:bool,
     *   enough:bool,
     *   min_percent:int,
     *   seconds:int,
     *   duration:int,
     *   percent:int
     * }
     */
    public function watchState(int $studentId, array $lesson, array $item): array
    {
        $min = self::normalizeMinWatchPercent($lesson['min_watch_percent'] ?? 80);
        $completed = $this->isItemComplete($studentId, (int)$item['id']);
        $row = $this->itemState($studentId, (int)$item['id']);
        $seconds = (int)($row['video_seconds'] ?? 0);
        $duration = max((int)($row['video_duration_seconds'] ?? 0), $this->assetDuration((int)($item['video_asset_id'] ?? 0)));
        $ended = $duration > 0 && $seconds >= max(1, $duration - 1);
        $playable = $this->assetIsPlayable((int)($item['video_asset_id'] ?? 0));
        $enough = $completed || !$playable || self::hasWatchedEnough($seconds, $duration, $min, $ended);
        $required = $min > 0 && !$completed && $playable
            && self::normalizeItemType((string)($item['item_type'] ?? '')) === 'video';
        return [
            'required' => $required,
            'enough' => $enough,
            'min_percent' => $min,
            'seconds' => $seconds,
            'duration' => $duration,
            'percent' => self::watchPercent($seconds, $duration),
        ];
    }

    public function recordWatchProgress(int $studentId, int $itemId, int $seconds, int $duration, bool $ended = false): array
    {
        $item = $this->item($itemId);
        if (!$item || self::normalizeItemType((string)$item['item_type']) !== 'video') {
            throw new RuntimeException('That is not a video clip.');
        }
        $seconds = max(0, $seconds);
        $duration = max(0, $duration);
        if ($duration < 1) {
            $duration = $this->assetDuration((int)($item['video_asset_id'] ?? 0));
        }
        if ($ended && $duration > 0) {
            $seconds = max($seconds, $duration);
        }
        if ($duration > 0) {
            $seconds = min($seconds, $duration + 2);
        }
        try {
            $this->pdo->prepare('
                INSERT INTO online_lesson_item_state (student_id, item_id, status, video_seconds, video_duration_seconds)
                VALUES (?, ?, \'incomplete\', ?, ?)
                ON DUPLICATE KEY UPDATE
                    video_seconds = GREATEST(video_seconds, VALUES(video_seconds)),
                    video_duration_seconds = GREATEST(video_duration_seconds, VALUES(video_duration_seconds))
            ')->execute([$studentId, $itemId, $seconds, $duration]);
        } catch (\Throwable $e) {
            $this->pdo->prepare('
                INSERT INTO online_lesson_item_state (student_id, item_id, status, video_seconds)
                VALUES (?, ?, \'incomplete\', ?)
                ON DUPLICATE KEY UPDATE
                    video_seconds = GREATEST(video_seconds, VALUES(video_seconds))
            ')->execute([$studentId, $itemId, $seconds]);
        }
        $this->touchProgress($studentId, (int)$item['lesson_id'], $itemId);
        $lesson = $this->find((int)$item['lesson_id']) ?? [];
        return $this->watchState($studentId, $lesson, $item);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function itemState(int $studentId, int $itemId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM online_lesson_item_state
            WHERE student_id = ? AND item_id = ? LIMIT 1
        ');
        $stmt->execute([$studentId, $itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function assetDuration(int $assetId): int
    {
        $asset = $this->assetRow($assetId);
        return max(0, (int)($asset['duration_seconds'] ?? 0));
    }

    private function assetIsPlayable(int $assetId): bool
    {
        $asset = $this->assetRow($assetId);
        if ($asset === null) {
            return false;
        }
        if (!empty($asset['ready_at'])) {
            return true;
        }
        if (class_exists(BunnyVideoService::class)) {
            return BunnyVideoService::mapStatus((int)($asset['bunny_status'] ?? -1)) === 'ready';
        }
        return (int)($asset['bunny_status'] ?? -1) === 3 || (int)($asset['bunny_status'] ?? -1) === 4;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function assetRow(int $assetId): ?array
    {
        if ($assetId < 1) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT duration_seconds, bunny_status, ready_at FROM class_recording_assets WHERE id = ? LIMIT 1');
            $stmt->execute([$assetId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isItemComplete(int $studentId, int $itemId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT status FROM online_lesson_item_state
            WHERE student_id = ? AND item_id = ? LIMIT 1
        ');
        $stmt->execute([$studentId, $itemId]);
        return (string)$stmt->fetchColumn() === 'completed';
    }

    /**
     * @param array<int,array<string,mixed>> $answers questionId => {choice?:int, essay?:string}
     * @return array<string,mixed>
     */
    public function submitActivity(int $studentId, int $itemId, array $answers): array
    {
        $item = $this->item($itemId);
        if (!$item || (string)$item['item_type'] !== 'activity') {
            throw new RuntimeException('That is not a question activity.');
        }
        $activityId = (int)$item['activity_id'];
        $activity = $this->activity($activityId);
        if (!$activity) {
            throw new RuntimeException('Activity was not found.');
        }
        $questions = $this->studentQuestions($activityId);
        if ($questions === []) {
            throw new RuntimeException('This activity has no questions yet.');
        }

        $attemptCount = $this->attemptCount($studentId, $itemId);
        $maxAttempts = (int)$activity['max_attempts'];
        $latest = $this->latestAttempt($studentId, $itemId);
        if ($latest && (int)$latest['passed'] === 1) {
            return $this->attemptPublic($latest, $activity, $questions, true);
        }
        if ($maxAttempts > 0 && $attemptCount >= $maxAttempts && $latest) {
            return $this->attemptPublic($latest, $activity, $questions, true);
        }

        $session = $this->attemptSession($studentId, $itemId, $activity, $questions, true);
        $timeLimit = (int)($activity['time_limit_minutes'] ?? 0);
        $lenient = $timeLimit > 0;
        $startedAt = $session['started_at'] ?? null;
        $overTime = $session !== null && QuestionPool::isOverTime($startedAt, $timeLimit);
        if ($session !== null) {
            $questions = QuestionPool::orderQuestions($questions, $session['question_ids']);
            if ($questions === []) {
                throw new RuntimeException('This activity has no questions yet.');
            }
        } elseif (!empty($activity['shuffle_questions']) && count($questions) > 1) {
            $order = self::permutation(count($questions), 'q:' . $studentId . ':' . $activityId);
            $shuffled = [];
            foreach ($order as $idx) {
                if (isset($questions[$idx])) {
                    $shuffled[] = $questions[$idx];
                }
            }
            $questions = $shuffled !== [] ? $shuffled : $questions;
        }
        $shownIds = array_map(static fn (array $q): int => (int)$q['id'], $questions);

        $score = 0.0;
        $max = 0.0;
        $autoAll = true;
        $answerRows = [];
        foreach ($questions as $question) {
            $qid = (int)$question['id'];
            $marks = (float)$question['marks'];
            $max += $marks;
            $payload = $answers[$qid] ?? [];
            if (self::isManualQuestionType((string)$question['question_type'])) {
                $autoAll = false;
                $text = trim((string)($payload['essay'] ?? ''));
                if ($text === '' && !$lenient) {
                    throw new RuntimeException('Write an answer for every essay question.');
                }
                if (mb_strlen($text) > 20000) {
                    throw new RuntimeException('An essay answer is too long.');
                }
                $answerRows[] = [
                    'question_id' => $qid,
                    'choice_index' => null,
                    'essay_text' => $text,
                    'is_correct' => null,
                    'marks_awarded' => null,
                ];
                continue;
            }
            $choice = isset($payload['choice']) ? (int)$payload['choice'] : null;
            $choices = $question['choices'] ?? [];
            if (!empty($activity['shuffle_choices']) && (string)$question['question_type'] === 'mcq') {
                $choice = self::originalChoiceIndex($studentId, $qid, count($choices), $choice);
            }
            if ($choice === null || $choice < 0 || $choice >= count($choices)) {
                if (!$lenient) {
                    throw new RuntimeException('Choose an answer for every multiple-choice question.');
                }
                $answerRows[] = [
                    'question_id' => $qid,
                    'choice_index' => null,
                    'essay_text' => null,
                    'is_correct' => 0,
                    'marks_awarded' => 0,
                ];
                continue;
            }
            $correct = self::scoreMcq($choice, $question['correct_index'] === null ? null : (int)$question['correct_index']);
            if ($correct) {
                $score += $marks;
            }
            $answerRows[] = [
                'question_id' => $qid,
                'choice_index' => $choice,
                'essay_text' => null,
                'is_correct' => $correct ? 1 : 0,
                'marks_awarded' => $correct ? $marks : 0,
            ];
        }

        $passPercent = $activity['pass_percent'] === null ? null : (int)$activity['pass_percent'];
        $passed = $autoAll && self::activityPassed($score, $max, $passPercent);

        try {
            $this->pdo->prepare('
                INSERT INTO online_lesson_attempts
                    (student_id, item_id, activity_id, attempt_no, status, score, max_score, passed, submitted_at,
                     question_ids_json, version_id, started_at, over_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
            ')->execute([
                $studentId,
                $itemId,
                $activityId,
                $attemptCount + 1,
                'submitted',
                $score,
                $max,
                $passed ? 1 : 0,
                json_encode($shownIds),
                $this->currentVersionId((int)$item['lesson_id']),
                $startedAt,
                $overTime ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            $this->pdo->prepare('
                INSERT INTO online_lesson_attempts
                    (student_id, item_id, activity_id, attempt_no, status, score, max_score, passed, submitted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ')->execute([
                $studentId,
                $itemId,
                $activityId,
                $attemptCount + 1,
                'submitted',
                $score,
                $max,
                $passed ? 1 : 0,
            ]);
        }
        $attemptId = (int)$this->pdo->lastInsertId();
        $ins = $this->pdo->prepare('
            INSERT INTO online_lesson_answers
                (attempt_id, question_id, choice_index, essay_text, is_correct, marks_awarded)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        foreach ($answerRows as $row) {
            $ins->execute([
                $attemptId,
                $row['question_id'],
                $row['choice_index'],
                $row['essay_text'],
                $row['is_correct'],
                $row['marks_awarded'],
            ]);
        }

        try {
            (new McqLiveService($this->pdo, $this))->markSubmitted($studentId, $itemId, $attemptCount + 1, $attemptId);
        } catch (\Throwable $e) {
            error_log('mcq live submit: ' . $e->getMessage());
        }

        if ($passed) {
            $this->markItemComplete($studentId, $itemId);
        }

        $attempt = $this->attemptById($attemptId);
        $public = $this->attemptPublic($attempt ?? [], $activity, $questions, true);
        $public['awaiting_mark'] = !$autoAll;
        return $public;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestAttempt(int $studentId, int $itemId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM online_lesson_attempts
            WHERE student_id = ? AND item_id = ?
            ORDER BY attempt_no DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([$studentId, $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function ungradedEssays(int $lessonId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT at.id AS attempt_id, at.student_id, at.item_id, at.submitted_at, at.score, at.max_score,
                   q.id AS question_id, q.question_type, q.prompt, q.marks, an.essay_text, an.marks_awarded, an.teacher_comment,
                   i.title AS item_title, u.username, sp.full_name
            FROM online_lesson_answers an
            JOIN online_lesson_attempts at ON at.id = an.attempt_id
            JOIN online_lesson_questions q ON q.id = an.question_id
            JOIN online_lesson_items i ON i.id = at.item_id
            LEFT JOIN users u ON u.id = at.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = at.student_id
            WHERE i.lesson_id = ?
              AND q.question_type IN ('essay','short','exam')
              AND an.essay_text IS NOT NULL
              AND an.marks_awarded IS NULL
            ORDER BY at.submitted_at ASC, at.id ASC
        ");
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Enrolled-class progress for one video lesson.
     *
     * @return array{totals:array<string,int>,students:list<array<string,mixed>>}
     */
    public function classResults(int $lessonId, int $classId): array
    {
        $items = $this->items($lessonId);
        $total = count($items);
        $itemTitles = [];
        $activityItems = [];
        foreach ($items as $item) {
            $id = (int)$item['id'];
            $itemTitles[$id] = (string)$item['title'];
            if ((string)$item['item_type'] === 'activity') {
                $activityItems[$id] = (string)$item['title'];
            }
        }

        $rosterStmt = $this->pdo->prepare("
            SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name
            FROM student_enrollments se
            JOIN users u ON u.id = se.student_id AND u.deleted_at IS NULL
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE se.class_id = ?
            ORDER BY name
        ");
        try {
            $rosterStmt->execute([$classId]);
            $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $rosterStmt = $this->pdo->prepare("
                SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name
                FROM student_enrollments se
                JOIN users u ON u.id = se.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE se.class_id = ?
                ORDER BY name
            ");
            $rosterStmt->execute([$classId]);
            $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $progressByStudent = [];
        $progStmt = $this->pdo->prepare('SELECT * FROM online_lesson_progress WHERE lesson_id = ?');
        $progStmt->execute([$lessonId]);
        foreach ($progStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $progressByStudent[(int)$row['student_id']] = $row;
        }

        $completedMap = [];
        if ($items !== []) {
            $itemIds = array_map(static fn (array $item): int => (int)$item['id'], $items);
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $doneStmt = $this->pdo->prepare("
                SELECT student_id, item_id
                FROM online_lesson_item_state
                WHERE item_id IN ($placeholders) AND status = 'completed'
            ");
            $doneStmt->execute($itemIds);
            foreach ($doneStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $completedMap[(int)$row['student_id']][(int)$row['item_id']] = true;
            }
        }

        $attemptsByStudent = [];
        if ($activityItems !== []) {
            $actIds = array_keys($activityItems);
            $placeholders = implode(',', array_fill(0, count($actIds), '?'));
            $attStmt = $this->pdo->prepare("
                SELECT a.student_id, a.item_id, a.score, a.max_score, a.passed, a.status, a.submitted_at
                FROM online_lesson_attempts a
                INNER JOIN (
                    SELECT student_id, item_id, MAX(attempt_no) AS max_no
                    FROM online_lesson_attempts
                    WHERE item_id IN ($placeholders)
                    GROUP BY student_id, item_id
                ) latest ON latest.student_id = a.student_id AND latest.item_id = a.item_id AND latest.max_no = a.attempt_no
            ");
            $attStmt->execute($actIds);
            foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $sid = (int)$row['student_id'];
                $iid = (int)$row['item_id'];
                $attemptsByStudent[$sid][] = [
                    'item_id' => $iid,
                    'title' => $activityItems[$iid] ?? 'Quiz',
                    'score' => $row['score'] === null ? null : (float)$row['score'],
                    'max_score' => $row['max_score'] === null ? null : (float)$row['max_score'],
                    'passed' => $row['passed'] === null ? null : ((int)$row['passed'] === 1),
                    'status' => (string)$row['status'],
                ];
            }
        }

        $pendingByStudent = [];
        foreach ($this->ungradedEssays($lessonId) as $row) {
            $sid = (int)$row['student_id'];
            $pendingByStudent[$sid] = ($pendingByStudent[$sid] ?? 0) + 1;
        }

        $students = [];
        foreach ($roster as $person) {
            $sid = (int)$person['id'];
            $doneIds = $completedMap[$sid] ?? [];
            $done = count($doneIds);
            $progress = $progressByStudent[$sid] ?? null;
            $currentId = (int)($progress['current_item_id'] ?? 0);
            $status = 'not_started';
            if ($total > 0 && $done >= $total) {
                $status = 'complete';
            } elseif ($progress || $done > 0) {
                $status = 'in_progress';
            }
            $stuckTitle = '';
            if ($status === 'in_progress') {
                foreach ($items as $item) {
                    $iid = (int)$item['id'];
                    if (!isset($doneIds[$iid])) {
                        $stuckTitle = (string)$item['title'];
                        break;
                    }
                }
            } elseif ($status === 'complete') {
                $stuckTitle = 'Finished';
            }
            if ($stuckTitle === '' && $currentId > 0) {
                $stuckTitle = $itemTitles[$currentId] ?? '';
            }
            $students[] = [
                'id' => $sid,
                'name' => (string)$person['name'],
                'completed_count' => $done,
                'total' => $total,
                'percent' => self::percentComplete($done, $total),
                'status' => $status,
                'current_title' => $stuckTitle,
                'last_seen_at' => (string)($progress['last_seen_at'] ?? ''),
                'active_seconds' => (int)($progress['active_seconds'] ?? 0),
                'quizzes' => $attemptsByStudent[$sid] ?? [],
                'essays_pending' => (int)($pendingByStudent[$sid] ?? 0),
            ];
        }

        return [
            'totals' => self::resultsTotals($students),
            'students' => $students,
        ];
    }

    public function noteItemOpen(int $studentId, int $itemId): void
    {
        if ($studentId < 1 || $itemId < 1) {
            return;
        }
        $this->pdo->prepare('
            INSERT INTO online_lesson_item_state (student_id, item_id, status, open_count, last_seen_at)
            VALUES (?, ?, \'incomplete\', 1, NOW())
            ON DUPLICATE KEY UPDATE
                open_count = open_count + 1,
                last_seen_at = NOW()
        ')->execute([$studentId, $itemId]);
    }

    /**
     * $moveCurrent is false for the flush sent while leaving a page, which can
     * arrive after the next item has already become current.
     */
    public function recordActiveTime(int $studentId, int $lessonId, int $itemId, int $seconds, bool $moveCurrent = true): void
    {
        $seconds = max(0, min(40, $seconds));
        if ($studentId < 1 || $lessonId < 1 || $itemId < 1 || $seconds < 1) {
            return;
        }
        $item = $this->item($itemId);
        if (!$item || (int)$item['lesson_id'] !== $lessonId) {
            throw new RuntimeException('That lesson item was not found.');
        }
        $this->pdo->prepare('
            INSERT INTO online_lesson_item_state (student_id, item_id, status, active_seconds, last_seen_at)
            VALUES (?, ?, \'incomplete\', ?, NOW())
            ON DUPLICATE KEY UPDATE
                active_seconds = active_seconds + VALUES(active_seconds),
                last_seen_at = NOW()
        ')->execute([$studentId, $itemId, $seconds]);
        $this->pdo->prepare('
            INSERT INTO online_lesson_progress (student_id, lesson_id, current_item_id, completed_count, last_seen_at, active_seconds)
            VALUES (?, ?, ?, 0, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                active_seconds = active_seconds + VALUES(active_seconds),
                last_seen_at = NOW(),
                current_item_id = IF(?, VALUES(current_item_id), current_item_id)
        ')->execute([$studentId, $lessonId, $itemId, $seconds, $moveCurrent ? 1 : 0]);
    }

    /**
     * @return array{students:list<array<string,mixed>>,items:list<array<string,mixed>>,summary:array<string,int|string>}
     */
    public function lessonAnalytics(int $lessonId, int $classId): array
    {
        $report = $this->classResults($lessonId, $classId);
        $roster = [];
        foreach ($report['students'] as $student) {
            $roster[(int)$student['id']] = true;
        }
        $items = $this->items($lessonId);
        $states = [];
        if ($items !== []) {
            $ids = array_map(static fn (array $item): int => (int)$item['id'], $items);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                SELECT student_id, item_id, status, video_seconds, video_duration_seconds, active_seconds, open_count
                FROM online_lesson_item_state
                WHERE item_id IN ($placeholders)
            ");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (!isset($roster[(int)$row['student_id']])) {
                    continue;
                }
                $states[(int)$row['item_id']][] = $row;
            }
        }
        $enrolled = count($report['students']);
        $started = 0;
        $completed = 0;
        $progressSum = 0;
        $timeSum = 0;
        $timedStudents = 0;
        $opens = 0;
        $last = '';
        foreach ($report['students'] as $student) {
            $progressSum += (int)$student['percent'];
            if (($student['status'] ?? '') === 'complete') {
                $completed++;
                $started++;
            } elseif (($student['status'] ?? '') === 'in_progress') {
                $started++;
            }
            $secs = (int)($student['active_seconds'] ?? 0);
            if ($secs > 0) {
                $timeSum += $secs;
                $timedStudents++;
            }
            $seen = (string)($student['last_seen_at'] ?? '');
            if ($seen !== '' && $seen > $last) {
                $last = $seen;
            }
        }
        $activityRows = [];
        foreach ($items as $item) {
            $id = (int)$item['id'];
            $rows = $states[$id] ?? [];
            $done = 0;
            $itemStarted = 0;
            $active = 0;
            $timed = 0;
            $watchSum = 0;
            $watchN = 0;
            $watchSeconds = 0;
            $itemOpens = 0;
            foreach ($rows as $row) {
                $itemOpens += (int)$row['open_count'];
                $opens += (int)$row['open_count'];
                $sec = (int)$row['active_seconds'];
                if ($sec > 0) {
                    $active += $sec;
                    $timed++;
                }
                $duration = (int)$row['video_duration_seconds'];
                $watched = (int)$row['video_seconds'];
                if ($duration > 0 && $watched > 0) {
                    $watchSum += min(100, (int)round($watched / $duration * 100));
                    $watchN++;
                    $watchSeconds += $watched;
                }
                if ((string)$row['status'] === 'completed' || $watched > 0 || (int)$row['open_count'] > 0) {
                    $itemStarted++;
                }
                if ((string)$row['status'] === 'completed') {
                    $done++;
                }
            }
            $scores = [];
            foreach ($report['students'] as $student) {
                foreach ($student['quizzes'] ?? [] as $quiz) {
                    if ((int)$quiz['item_id'] === $id && $quiz['score'] !== null && (float)$quiz['max_score'] > 0) {
                        $scores[] = ((float)$quiz['score'] / (float)$quiz['max_score']) * 100;
                    }
                }
            }
            $activityRows[] = [
                'id' => $id,
                'title' => (string)$item['title'],
                'kind' => self::itemKindLabel($item),
                'type' => self::normalizeItemType((string)$item['item_type']),
                'completed' => $done,
                'started' => $itemStarted,
                'enrolled' => $enrolled,
                'opens' => $itemOpens,
                'avg_seconds' => $timed > 0 ? (int)round($active / $timed) : 0,
                'avg_watch_percent' => $watchN > 0 ? (int)round($watchSum / $watchN) : 0,
                'avg_watch_seconds' => $watchN > 0 ? (int)round($watchSeconds / $watchN) : 0,
                'avg_score' => $scores !== [] ? (int)round(array_sum($scores) / count($scores)) : null,
            ];
        }
        return [
            'students' => $report['students'],
            'items' => $activityRows,
            'summary' => [
                'enrolled' => $enrolled,
                'started' => $started,
                'completed' => $completed,
                'in_progress' => max(0, $started - $completed),
                'not_started' => max(0, $enrolled - $started),
                'completion_rate' => $enrolled > 0 ? (int)round($completed / $enrolled * 100) : 0,
                'avg_progress' => $enrolled > 0 ? (int)round($progressSum / $enrolled) : 0,
                'avg_seconds' => $timedStudents > 0 ? (int)round($timeSum / $timedStudents) : 0,
                'opens' => $opens,
                'last_activity' => $last,
            ],
        ];
    }

    public function savePassPercent(int $lessonId, ?int $percent): void
    {
        if ($percent !== null && ($percent < 1 || $percent > 100)) {
            throw new RuntimeException('Pass mark must be between 1 and 100, or left blank.');
        }
        $this->pdo->prepare('UPDATE online_lessons SET pass_percent = ? WHERE id = ?')->execute([$percent, $lessonId]);
    }

    /**
     * Marks and progress for a class, or for one enrolled student when $onlyStudentId is set.
     * A student id that is not on this class returns an empty student list.
     *
     * @return array<string,mixed>
     */
    public function lessonResultReport(int $lessonId, int $classId, ?int $passPercent, ?int $onlyStudentId = null, ?array $knownStudents = null): array
    {
        $items = $this->withActivitySettings($this->items($lessonId), $lessonId);
        $itemIds = array_map(static fn (array $item): int => (int)$item['id'], $items);
        if ($onlyStudentId !== null) {
            $students = $this->oneStudentResultRow($lessonId, $classId, $onlyStudentId, count($items));
        } elseif ($knownStudents !== null) {
            $students = $knownStudents;
        } else {
            $students = $this->classResults($lessonId, $classId)['students'];
        }
        $questions = $this->lessonQuestionRows($lessonId);
        $attempts = [];
        $answers = [];
        $states = [];
        $attemptCounts = [];
        if ($itemIds !== [] && $students !== []) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $params = $itemIds;
            $studentSql = '';
            if ($onlyStudentId !== null) {
                $studentSql = ' AND a.student_id = ?';
                $params[] = $onlyStudentId;
            }
            $attemptStmt = $this->pdo->prepare("
                SELECT a.*
                FROM online_lesson_attempts a
                WHERE a.item_id IN ($placeholders)$studentSql
            ");
            $attemptStmt->execute($params);
            $rules = [];
            foreach ($items as $item) {
                $rules[(int)$item['id']] = $item['scoring_rule'] ?? null;
            }
            $grouped = [];
            foreach ($attemptStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $grouped[(int)$row['student_id'] . ':' . (int)$row['item_id']][] = $row;
            }
            foreach ($grouped as $key => $rows) {
                $itemKey = (int)explode(':', $key)[1];
                $chosen = QuestionPool::chooseAttempt($rows, $rules[$itemKey] ?? null);
                if ($chosen !== null) {
                    $attempts[] = $chosen;
                }
            }
            $attemptIds = array_map(static fn (array $row): int => (int)$row['id'], $attempts);
            if ($attemptIds !== []) {
                $answerPlaceholders = implode(',', array_fill(0, count($attemptIds), '?'));
                $answerStmt = $this->pdo->prepare("
                    SELECT attempt_id, question_id, choice_index, is_correct, marks_awarded
                    FROM online_lesson_answers
                    WHERE attempt_id IN ($answerPlaceholders)
                ");
                $answerStmt->execute($attemptIds);
                $answers = $answerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            $stateParams = $itemIds;
            $stateStudent = '';
            if ($onlyStudentId !== null) {
                $stateStudent = ' AND student_id = ?';
                $stateParams[] = $onlyStudentId;
            }
            $stateStmt = $this->pdo->prepare("
                SELECT student_id, item_id, status, active_seconds, open_count, video_seconds, video_duration_seconds
                FROM online_lesson_item_state
                WHERE item_id IN ($placeholders)$stateStudent
            ");
            $stateStmt->execute($stateParams);
            $states = $stateStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $countParams = $itemIds;
            $countStudent = '';
            if ($onlyStudentId !== null) {
                $countStudent = ' AND student_id = ?';
                $countParams[] = $onlyStudentId;
            }
            $countStmt = $this->pdo->prepare("
                SELECT student_id, item_id, COUNT(*) AS attempts
                FROM online_lesson_attempts
                WHERE item_id IN ($placeholders)$countStudent
                GROUP BY student_id, item_id
            ");
            $countStmt->execute($countParams);
            foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $attemptCounts[(int)$row['student_id'] . ':' . (int)$row['item_id']] = (int)$row['attempts'];
            }
        }
        $rosterIds = [];
        foreach ($students as $student) {
            $rosterIds[(int)$student['id']] = true;
        }
        $attempts = array_values(array_filter($attempts, static fn (array $row): bool => isset($rosterIds[(int)$row['student_id']])));
        $states = array_values(array_filter($states, static fn (array $row): bool => isset($rosterIds[(int)$row['student_id']])));
        $submissions = [];
        try {
            $submissions = (new LearningModuleService($this->pdo))->submissionsByStudentItem($itemIds, $onlyStudentId);
        } catch (\Throwable $e) {
            $submissions = [];
        }
        return LessonResultBuilder::analyse($items, $questions, $students, $attempts, $answers, $states, $attemptCounts, $passPercent, $submissions);
    }

    /**
     * Adds pool size and scoring rule to activity items without depending on those columns in items().
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    public function withActivitySettings(array $items, int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_activities WHERE lesson_id = ?');
        $stmt->execute([$lessonId]);
        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $byId[(int)$row['id']] = $row;
        }
        foreach ($items as &$item) {
            $activity = $byId[(int)($item['activity_id'] ?? 0)] ?? null;
            $item['draw_count'] = $activity ? (int)($activity['draw_count'] ?? 0) : 0;
            $item['scoring_rule'] = $activity ? QuestionPool::normalizeRule($activity['scoring_rule'] ?? null) : null;
            $item['time_limit_minutes'] = $activity ? (int)($activity['time_limit_minutes'] ?? 0) : 0;
        }
        unset($item);
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lessonQuestionRows(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT q.id, q.activity_id, q.question_type, q.sort_order, q.prompt, q.choices_json, q.correct_index, q.marks
            FROM online_lesson_questions q
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ?
            ORDER BY q.activity_id ASC, q.sort_order ASC, q.id ASC
        ');
        $stmt->execute([$lessonId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['choices'] = $this->decodeChoices((string)($row['choices_json'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function oneStudentResultRow(int $lessonId, int $classId, int $studentId, int $itemCount): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name
                FROM student_enrollments se
                JOIN users u ON u.id = se.student_id AND u.deleted_at IS NULL
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE se.class_id = ? AND u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$classId, $studentId]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $stmt = $this->pdo->prepare("
                SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name
                FROM student_enrollments se
                JOIN users u ON u.id = se.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE se.class_id = ? AND u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$classId, $studentId]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$person) {
            return [];
        }
        $doneStmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM online_lesson_item_state s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE s.student_id = ? AND i.lesson_id = ? AND s.status = \'completed\'
        ');
        $doneStmt->execute([$studentId, $lessonId]);
        $done = (int)$doneStmt->fetchColumn();
        $progStmt = $this->pdo->prepare('SELECT active_seconds, last_seen_at FROM online_lesson_progress WHERE student_id = ? AND lesson_id = ? LIMIT 1');
        $progStmt->execute([$studentId, $lessonId]);
        $progress = $progStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $status = 'not_started';
        if ($itemCount > 0 && $done >= $itemCount) {
            $status = 'complete';
        } elseif ($progress || $done > 0) {
            $status = 'in_progress';
        }
        return [[
            'id' => (int)$person['id'],
            'name' => (string)$person['name'],
            'completed_count' => $done,
            'total' => $itemCount,
            'percent' => self::percentComplete($done, $itemCount),
            'status' => $status,
            'last_seen_at' => (string)($progress['last_seen_at'] ?? ''),
            'active_seconds' => (int)($progress['active_seconds'] ?? 0),
        ]];
    }

    public function gradeEssay(int $attemptId, int $questionId, float $marks, string $comment, int $requireLessonId = 0): void
    {
        $attempt = $this->attemptById($attemptId);
        if (!$attempt) {
            throw new RuntimeException('Attempt not found.');
        }
        $item = $this->item((int)$attempt['item_id']);
        if ($requireLessonId > 0 && (!$item || (int)$item['lesson_id'] !== $requireLessonId)) {
            throw new RuntimeException('That attempt is not for this lesson.');
        }
        $question = $this->question($questionId);
        if (!$question) {
            throw new RuntimeException('Question not found.');
        }
        if ((int)$question['activity_id'] !== (int)$attempt['activity_id']) {
            throw new RuntimeException('That question is not on this attempt.');
        }
        $marks = max(0, min((float)$question['marks'], round($marks, 2)));
        $this->pdo->prepare('
            UPDATE online_lesson_answers
            SET marks_awarded = ?, teacher_comment = ?
            WHERE attempt_id = ? AND question_id = ?
        ')->execute([$marks, $comment !== '' ? $comment : null, $attemptId, $questionId]);

        $sumStmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(marks_awarded),0) FROM online_lesson_answers WHERE attempt_id = ?
        ');
        $sumStmt->execute([$attemptId]);
        $score = (float)$sumStmt->fetchColumn();
        $pending = $this->pdo->prepare("
            SELECT COUNT(*) FROM online_lesson_answers an
            JOIN online_lesson_questions q ON q.id = an.question_id
            WHERE an.attempt_id = ? AND q.question_type IN ('essay','short','exam') AND an.marks_awarded IS NULL
        ");
        $pending->execute([$attemptId]);
        $ungraded = (int)$pending->fetchColumn();
        $status = $ungraded > 0 ? 'submitted' : 'graded';
        $passed = 0;
        if ($ungraded === 0) {
            $activity = $this->activity((int)$attempt['activity_id']);
            $passPercent = $activity && $activity['pass_percent'] !== null ? (int)$activity['pass_percent'] : null;
            $max = (float)($attempt['max_score'] ?? 0);
            $passed = self::activityPassed($score, $max, $passPercent) ? 1 : 0;
        }
        $this->pdo->prepare('UPDATE online_lesson_attempts SET score = ?, status = ?, passed = ? WHERE id = ?')
            ->execute([$score, $status, $passed, $attemptId]);
        $studentId = (int)$attempt['student_id'];
        $itemId = (int)$attempt['item_id'];
        if ($ungraded === 0 && $passed === 1) {
            $this->markItemComplete($studentId, $itemId);
        } elseif ($ungraded === 0 && $passed === 0) {
            $this->pdo->prepare("
                UPDATE online_lesson_item_state
                SET status = 'in_progress', completed_at = NULL
                WHERE student_id = ? AND item_id = ?
            ")->execute([$studentId, $itemId]);
        }
    }

    /**
     * Student-facing payload for one lesson item.
     *
     * @return array<string,mixed>
     */
    public function playerPayload(int $studentId, array $lesson, array $item, bool $revealAnswers): array
    {
        $type = self::normalizeItemType((string)$item['item_type']);
        $payload = [
            'id' => (int)$item['id'],
            'type' => $type,
            'title' => (string)$item['title'],
            'body' => (string)($item['body'] ?? ''),
            'required' => (int)($item['required'] ?? 1) === 1,
            'completed' => $this->isItemComplete($studentId, (int)$item['id']),
            'embed_url' => null,
            'activity' => null,
            'attempt' => null,
        ];
        if ($type === 'video') {
            $assetId = (int)($item['video_asset_id'] ?? 0);
            if ($assetId > 0) {
                $payload['embed_url'] = self::withPlayerJs(
                    (new RecordingService($this->pdo))->playableEmbedUrlForAsset($assetId)
                );
            }
            $watch = $this->watchState($studentId, $lesson, $item);
            if ($payload['embed_url'] === null || $payload['embed_url'] === '') {
                $watch['required'] = false;
                $watch['enough'] = true;
            }
            $payload['watch'] = $watch;
            return $payload;
        }
        if ($type === 'external_link') {
            $url = (string)($item['link_url'] ?? '');
            $payload['url'] = $url;
            $payload['open_new_tab'] = (int)($item['open_new_tab'] ?? 1) === 1;
            $payload['host_label'] = self::externalLinkLabel($url);
            return $payload;
        }
        if ($type !== 'activity') {
            return $payload;
        }
        $activityId = (int)$item['activity_id'];
        $activity = $this->activity($activityId);
        $questions = $revealAnswers ? $this->questions($activityId) : $this->studentQuestions($activityId);
        $attempt = $this->latestAttempt($studentId, (int)$item['id']);
        $reveal = $revealAnswers || $payload['completed'];
        $shuffleChoices = (int)($activity['shuffle_choices'] ?? 0) === 1;
        $shuffleQuestions = (int)($activity['shuffle_questions'] ?? 0) === 1;
        $maxAttempts = (int)($activity['max_attempts'] ?? 0);
        $attemptsUsed = $attempt ? (int)$attempt['attempt_no'] : 0;
        $finished = $payload['completed']
            || ($attempt && (int)($attempt['passed'] ?? 0) === 1)
            || ($maxAttempts > 0 && $attemptsUsed >= $maxAttempts);
        $formQuestions = $questions;
        $formAttempt = $attempt;
        $time = ['limit' => 0, 'deadline' => 0, 'remaining' => 0, 'expired' => false];
        $needsStart = false;
        if ($finished && $attempt) {
            $formQuestions = QuestionPool::orderQuestions($questions, QuestionPool::decodeIds($attempt['question_ids_json'] ?? null));
            $shuffleForm = QuestionPool::decodeIds($attempt['question_ids_json'] ?? null) === null && $shuffleQuestions;
        } else {
            $timed = (int)($activity['time_limit_minutes'] ?? 0) > 0;
            $session = $activity ? $this->attemptSession($studentId, (int)$item['id'], $activity, $questions, !$revealAnswers && !$timed) : null;
            $shuffleForm = $session === null && $shuffleQuestions;
            if ($session !== null) {
                $formQuestions = QuestionPool::orderQuestions($questions, $session['question_ids']);
                $formAttempt = null;
                $time = QuestionPool::timeState($session['started_at'], (int)($activity['time_limit_minutes'] ?? 0));
            } elseif ($timed && $studentId > 0) {
                $formQuestions = [];
                $formAttempt = null;
                $needsStart = true;
            }
        }
        $showExplanation = (int)($activity['show_explanation'] ?? 1) === 1;
        $payload['activity'] = [
            'id' => $activityId,
            'type' => (string)($activity['activity_type'] ?? 'mcq'),
            'instructions' => (string)($activity['instructions'] ?? ''),
            'pass_percent' => $activity['pass_percent'] === null ? null : (int)$activity['pass_percent'],
            'max_attempts' => $maxAttempts,
            'attempts_used' => $attemptsUsed,
            'show_correct' => (int)($activity['show_correct'] ?? 1) === 1,
            'show_score' => (int)($activity['show_score'] ?? 1) === 1,
            'show_explanation' => $showExplanation,
            'shuffle_choices' => $shuffleChoices,
            'shuffle_questions' => $shuffleQuestions,
            'draw_count' => (int)($activity['draw_count'] ?? 0),
            'pool_size' => count($questions),
            'time' => $time,
            'time_limit_minutes' => (int)($activity['time_limit_minutes'] ?? 0),
            'needs_start' => $needsStart,
            'finished' => (bool)$finished,
            'questions' => $this->publicQuestions(
                $formQuestions,
                $formAttempt,
                $reveal && (int)($activity['show_correct'] ?? 1) === 1,
                $studentId,
                $shuffleChoices,
                $shuffleForm,
                $showExplanation
            ),
        ];
        if ($attempt) {
            $payload['attempt'] = $this->attemptPublic($attempt, $activity ?? [], $questions, $reveal);
        }
        return $payload;
    }

    /**
     * @param list<array<string,mixed>> $questions
     * @param array<string,mixed>|null $attempt
     * @return list<array<string,mixed>>
     */
    private function publicQuestions(
        array $questions,
        ?array $attempt,
        bool $reveal,
        int $studentId = 0,
        bool $shuffleChoices = false,
        bool $shuffleQuestions = false,
        bool $showExplanation = true
    ): array {
        $answersByQ = [];
        if ($attempt) {
            $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_answers WHERE attempt_id = ?');
            $stmt->execute([(int)$attempt['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $answersByQ[(int)$row['question_id']] = $row;
            }
        }
        if ($shuffleQuestions && count($questions) > 1 && $studentId > 0) {
            $order = self::permutation(count($questions), 'q:' . $studentId . ':' . (int)($questions[0]['activity_id'] ?? 0));
            $shuffled = [];
            foreach ($order as $idx) {
                if (isset($questions[$idx])) {
                    $shuffled[] = $questions[$idx];
                }
            }
            $questions = $shuffled !== [] ? $shuffled : $questions;
        }
        $out = [];
        foreach ($questions as $question) {
            $qid = (int)$question['id'];
            $ans = $answersByQ[$qid] ?? null;
            $choices = $question['choices'] ?? [];
            $correctIndex = $question['correct_index'] === null ? null : (int)$question['correct_index'];
            $studentChoice = $ans && $ans['choice_index'] !== null ? (int)$ans['choice_index'] : null;
            if ($shuffleChoices && (string)$question['question_type'] === 'mcq' && count($choices) > 1 && $studentId > 0) {
                $order = self::permutation(count($choices), 'c:' . $studentId . ':' . $qid);
                $mapped = [];
                foreach ($order as $orig) {
                    $mapped[] = $choices[$orig] ?? '';
                }
                $choices = $mapped;
                if ($correctIndex !== null) {
                    $found = array_search($correctIndex, $order, true);
                    $correctIndex = $found === false ? null : (int)$found;
                }
                if ($studentChoice !== null) {
                    $found = array_search($studentChoice, $order, true);
                    $studentChoice = $found === false ? null : (int)$found;
                }
            }
            $row = [
                'id' => $qid,
                'type' => (string)$question['question_type'],
                'prompt' => (string)$question['prompt'],
                'marks' => (float)$question['marks'],
                'exam_ref' => (string)($question['exam_ref'] ?? ''),
                'choices' => $choices,
                'student_choice' => $studentChoice,
                'student_essay' => '',
                'teacher_comment' => '',
                'marks_awarded' => null,
            ];
            if ($ans) {
                $row['student_essay'] = (string)($ans['essay_text'] ?? '');
                $row['teacher_comment'] = (string)($ans['teacher_comment'] ?? '');
                $row['marks_awarded'] = $ans['marks_awarded'] === null ? null : (float)$ans['marks_awarded'];
            }
            if ($reveal) {
                $row['correct_index'] = $correctIndex;
                $row['explanation'] = $showExplanation ? (string)($question['explanation'] ?? '') : '';
                $row['is_correct'] = isset($ans['is_correct']) && $ans['is_correct'] !== null ? ((int)$ans['is_correct'] === 1) : null;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $activity
     * @param list<array<string,mixed>> $questions
     * @return array<string,mixed>
     */
    private function attemptPublic(array $attempt, array $activity, array $questions, bool $reveal): array
    {
        $storedIds = QuestionPool::decodeIds($attempt['question_ids_json'] ?? null);
        if ($storedIds !== null) {
            $activity['shuffle_questions'] = 0;
            $questions = QuestionPool::orderQuestions($questions, $storedIds);
        }
        return [
            'id' => (int)($attempt['id'] ?? 0),
            'attempt_no' => (int)($attempt['attempt_no'] ?? 1),
            'status' => (string)($attempt['status'] ?? ''),
            'score' => $attempt['score'] === null ? null : (float)$attempt['score'],
            'max_score' => $attempt['max_score'] === null ? null : (float)$attempt['max_score'],
            'passed' => isset($attempt['passed']) ? ((int)$attempt['passed'] === 1) : null,
            'submitted_at' => (string)($attempt['submitted_at'] ?? ''),
            'questions' => $this->publicQuestions(
                $questions,
                $attempt,
                $reveal && (int)($activity['show_correct'] ?? 1) === 1,
                (int)($attempt['student_id'] ?? 0),
                (int)($activity['shuffle_choices'] ?? 0) === 1,
                (int)($activity['shuffle_questions'] ?? 0) === 1,
                (int)($activity['show_explanation'] ?? 1) === 1
            ),
        ];
    }

    private function attemptCount(int $studentId, int $itemId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM online_lesson_attempts WHERE student_id = ? AND item_id = ?');
        $stmt->execute([$studentId, $itemId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function attemptById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_attempts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function question(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_questions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['choices'] = $this->decodeChoices((string)($row['choices_json'] ?? ''));
        return $row;
    }

    /**
     * @return list<string>
     */
    private function decodeChoices(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? self::normalizeChoices($decoded) : [];
    }

    private function refreshActivityType(int $activityId): void
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT question_type FROM online_lesson_questions WHERE activity_id = ?');
        $stmt->execute([$activityId]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $activity = $this->activity($activityId);
        $current = strtolower((string)($activity['activity_type'] ?? ''));
        if (self::isSubmissionActivity($current)) {
            return;
        }
        $manual = array_values(array_intersect($types, ['essay', 'short', 'exam']));
        $hasMcq = in_array('mcq', $types, true);
        if ($hasMcq && $manual !== []) {
            $type = 'mixed';
        } elseif (count($manual) > 1) {
            $type = 'mixed';
        } elseif (count($manual) === 1) {
            $type = $manual[0];
        } else {
            $type = 'mcq';
        }
        $this->pdo->prepare('UPDATE online_lesson_activities SET activity_type = ? WHERE id = ?')->execute([$type, $activityId]);
    }

    private function sortAfter(int $lessonId, int $afterItemId): int
    {
        $items = $this->items($lessonId);
        if ($afterItemId < 1 || $items === []) {
            $max = 0;
            foreach ($items as $item) {
                $max = max($max, (int)$item['sort_order']);
            }
            return $max + 1;
        }
        $afterSort = 0;
        foreach ($items as $item) {
            if ((int)$item['id'] === $afterItemId) {
                $afterSort = (int)$item['sort_order'];
                break;
            }
        }
        $newSort = $afterSort + 1;
        $this->pdo->prepare('UPDATE online_lesson_items SET sort_order = sort_order + 1 WHERE lesson_id = ? AND sort_order >= ?')
            ->execute([$lessonId, $newSort]);
        return $newSort;
    }

    private function insertItem(
        int $lessonId,
        string $type,
        string $title,
        int $sort,
        ?int $videoAssetId,
        ?int $activityId,
        ?string $body
    ): int {
        $this->pdo->prepare('
            INSERT INTO online_lesson_items
                (lesson_id, item_type, title, sort_order, video_asset_id, activity_id, body)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $lessonId,
            self::normalizeItemType($type),
            mb_substr($title, 0, 200),
            $sort,
            $videoAssetId,
            $activityId,
            $body !== null && trim($body) !== '' ? $body : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return array<string,mixed>
     */
    public function saveExternalLink(
        int $lessonId,
        int $itemId,
        int $afterItemId,
        string $title,
        string $url,
        bool $newTab,
        bool $required
    ): array {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a title for the link.');
        }
        $url = self::normalizeExternalUrl($url);
        if ($itemId > 0) {
            $item = $this->item($itemId);
            if (!$item || (int)$item['lesson_id'] !== $lessonId || (string)$item['item_type'] !== 'external_link') {
                throw new RuntimeException('That link was not found.');
            }
            $this->pdo->prepare('
                UPDATE online_lesson_items
                SET title = ?, link_url = ?, open_new_tab = ?, required = ?
                WHERE id = ? AND lesson_id = ? AND item_type = \'external_link\'
            ')->execute([$title, $url, $newTab ? 1 : 0, $required ? 1 : 0, $itemId, $lessonId]);
            $saved = $this->item($itemId);
            if (!$saved) {
                throw new RuntimeException('Could not save the link.');
            }
            return $saved;
        }
        $sort = $this->sortAfter($lessonId, $afterItemId);
        $newId = $this->insertExternalLink($lessonId, $title, $url, $sort, $newTab, $required);
        $saved = $this->item($newId);
        if (!$saved) {
            throw new RuntimeException('Could not add the link.');
        }
        return $saved;
    }

    public function recordExternalOpen(int $studentId, int $itemId): void
    {
        $item = $this->item($itemId);
        if (!$item || (string)$item['item_type'] !== 'external_link') {
            throw new RuntimeException('That lesson item was not found.');
        }
        $this->markItemComplete($studentId, $itemId);
    }

    private function insertExternalLink(
        int $lessonId,
        string $title,
        string $url,
        int $sort,
        bool $newTab,
        bool $required
    ): int {
        $this->pdo->prepare('
            INSERT INTO online_lesson_items
                (lesson_id, item_type, title, sort_order, link_url, open_new_tab, required)
            VALUES (?, \'external_link\', ?, ?, ?, ?, ?)
        ')->execute([
            $lessonId,
            mb_substr($title, 0, 200),
            $sort,
            $url,
            $newTab ? 1 : 0,
            $required ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function savePageBody(int $itemId, string $title, string $body): void
    {
        $this->renameItem($itemId, $title);
        $this->pdo->prepare('UPDATE online_lesson_items SET body = ? WHERE id = ? AND item_type = \'page\'')
            ->execute([$body !== '' ? $body : null, $itemId]);
    }

    /**
     * @return array{completed:int,total:int,percent:int,title:string,published:int}
     */
    public function studentProgressSummary(int $studentId, int $lessonId): array
    {
        $lesson = $this->find($lessonId);
        $items = $this->items($lessonId);
        $done = count($this->completedItemIds($studentId, $lessonId));
        $total = count($items);
        return [
            'completed' => $done,
            'total' => $total,
            'percent' => self::percentComplete($done, $total),
            'title' => (string)($lesson['title'] ?? ''),
            'published' => (int)($lesson['published'] ?? 0),
        ];
    }

    /**
     * @param list<int> $timetableIds
     * @return array<int,array{completed:int,total:int,percent:int,published:int,title:string,lesson_id:int}>
     */
    public function mapProgressForStudent(int $studentId, array $timetableIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $timetableIds), static fn (int $id): bool => $id > 0)));
        if ($ids === [] || $studentId < 1) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT ol.*,
                   (SELECT COUNT(*) FROM online_lesson_items i WHERE i.lesson_id = ol.id) AS total
            FROM online_lessons ol
            WHERE ol.timetable_id IN ($placeholders) AND ol.published = 1
        ");
        $stmt->execute($ids);
        $rows = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], static fn (array $row): bool => self::isReleased($row)));
        $doneByLesson = [];
        if ($rows !== []) {
            $lessonIds = array_map(static fn (array $row): int => (int)$row['id'], $rows);
            $done = $this->pdo->prepare("
                SELECT i.lesson_id, COUNT(*) AS n
                FROM online_lesson_item_state s
                JOIN online_lesson_items i ON i.id = s.item_id
                WHERE s.student_id = ? AND s.status = 'completed'
                  AND i.lesson_id IN (" . implode(',', array_fill(0, count($lessonIds), '?')) . ")
                GROUP BY i.lesson_id
            ");
            $done->execute(array_merge([$studentId], $lessonIds));
            foreach ($done->fetchAll(PDO::FETCH_ASSOC) ?: [] as $d) {
                $doneByLesson[(int)$d['lesson_id']] = (int)$d['n'];
            }
        }
        $map = [];
        foreach ($rows as $row) {
            $lessonId = (int)$row['id'];
            $done = $doneByLesson[$lessonId] ?? 0;
            $total = (int)$row['total'];
            $map[(int)$row['timetable_id']] = [
                'lesson_id' => $lessonId,
                'completed' => $done,
                'total' => $total,
                'percent' => self::percentComplete($done, $total),
                'published' => (int)$row['published'],
                'title' => (string)$row['title'],
            ];
        }
        return $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function copyTargets(array $staffLesson, int $excludeTimetableId, int $teacherId = 0, bool $isAdmin = false): array
    {
        $classId = (int)($staffLesson['class_id'] ?? 0);
        $subjectId = (int)($staffLesson['subject_id'] ?? 0);
        $date = (string)($staffLesson['date'] ?? date('Y-m-d'));
        if ($classId < 1 || $subjectId < 1) {
            return [];
        }
        $ownerId = $teacherId > 0 ? $teacherId : (int)($staffLesson['teacher_id'] ?? 0);
        $stmt = $this->pdo->prepare("
            SELECT tt.id, tt.date, tt.start_time, tt.delivery_mode,
                   s.name AS subject_name, c.name AS class_name,
                   ol.id AS online_lesson_id, ol.published,
                   (SELECT COUNT(*) FROM online_lesson_items i WHERE i.lesson_id = ol.id) AS item_count
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            LEFT JOIN online_lessons ol ON ol.timetable_id = tt.id
            WHERE tt.deleted_at IS NULL
              AND (tt.class_id = ? OR tt.teacher_id = ? OR tt.substitute_teacher_id = ? OR ? = 1)
              AND tt.subject_id = ?
              AND tt.id <> ?
              AND tt.date BETWEEN DATE_SUB(?, INTERVAL 21 DAY) AND DATE_ADD(?, INTERVAL 90 DAY)
            ORDER BY (tt.class_id = ?) DESC, tt.date DESC, tt.start_time DESC
            LIMIT 60
        ");
        try {
            $stmt->execute([$classId, $ownerId, $ownerId, $isAdmin ? 1 : 0, $subjectId, $excludeTimetableId, $date, $date, $classId]);
        } catch (\PDOException $e) {
            $stmt = $this->pdo->prepare("
                SELECT tt.id, tt.date, tt.start_time, tt.delivery_mode,
                       s.name AS subject_name, c.name AS class_name,
                       ol.id AS online_lesson_id, ol.published,
                       (SELECT COUNT(*) FROM online_lesson_items i WHERE i.lesson_id = ol.id) AS item_count
                FROM timetable tt
                JOIN subjects s ON s.id = tt.subject_id
                JOIN student_classes c ON c.id = tt.class_id
                LEFT JOIN online_lessons ol ON ol.timetable_id = tt.id
                WHERE tt.deleted_at IS NULL AND (tt.class_id = ? OR tt.teacher_id = ? OR ? = 1) AND tt.subject_id = ? AND tt.id <> ?
                  AND tt.date BETWEEN DATE_SUB(?, INTERVAL 21 DAY) AND DATE_ADD(?, INTERVAL 90 DAY)
                ORDER BY tt.date DESC, tt.start_time DESC
                LIMIT 60
            ");
            $stmt->execute([$classId, $ownerId, $isAdmin ? 1 : 0, $subjectId, $excludeTimetableId, $date, $date]);
        }
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!self::supportsDeliveryMode((string)($row['delivery_mode'] ?? 'physical'))) {
                continue;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $targetLesson timetable row
     * @param array<string,mixed>|null $targetRecording
     * @return array<string,mixed>
     */
    public function copySequenceToTimetable(
        int $sourceLessonId,
        array $targetLesson,
        ?array $targetRecording,
        int $userId
    ): array {
        $source = $this->find($sourceLessonId);
        if (!$source) {
            throw new RuntimeException('The source lesson was not found.');
        }
        $targetTimetableId = (int)($targetLesson['id'] ?? 0);
        if ($targetTimetableId < 1) {
            throw new RuntimeException('Choose a class date to copy to.');
        }
        if ((int)$source['timetable_id'] === $targetTimetableId) {
            throw new RuntimeException('Choose a different class date.');
        }
        $sourceItems = $this->items($sourceLessonId);
        if ($sourceItems === []) {
            throw new RuntimeException('This lesson has nothing to copy yet.');
        }

        $target = $this->getOrCreateForTimetable($targetLesson, $targetRecording, $userId);
        $targetId = (int)$target['id'];
        if ($this->lessonHasStudentWork($targetId)) {
            throw new RuntimeException('That class already has student progress. Copy to a date nobody has started.');
        }

        $this->wipeLessonItems($targetId);

        $this->pdo->prepare('
            UPDATE online_lessons
            SET sequential = ?, min_watch_percent = ?, intro = ?
            WHERE id = ?
        ')->execute([
            (int)($source['sequential'] ?? 1),
            self::normalizeMinWatchPercent($source['min_watch_percent'] ?? 80),
            $source['intro'] ?? null,
            $targetId,
        ]);
        try {
            $this->pdo->prepare('
                UPDATE online_lessons
                SET available_after_class = ?, close_after_days = ?
                WHERE id = ?
            ')->execute([
                (int)($source['available_after_class'] ?? 0),
                (int)($source['close_after_days'] ?? 0),
                $targetId,
            ]);
        } catch (\Throwable $e) {
            // older schema
        }

        $assetIds = [];
        if ($targetRecording) {
            foreach ((new RecordingService($this->pdo))->assets((int)$targetRecording['id']) as $asset) {
                $id = (int)($asset['id'] ?? 0);
                if ($id > 0) {
                    $assetIds[] = $id;
                }
            }
        }
        $videoIndex = 0;
        $sort = 0;
        foreach ($sourceItems as $item) {
            $type = self::normalizeItemType((string)$item['item_type']);
            $sort++;
            if ($type === 'video') {
                $assetId = $assetIds[$videoIndex] ?? null;
                $videoIndex++;
                if ($assetId) {
                    $this->insertItem($targetId, 'video', (string)$item['title'], $sort, $assetId, null, null);
                }
                continue;
            }
            if ($type === 'page') {
                $this->insertItem($targetId, 'page', (string)$item['title'], $sort, null, null, (string)($item['body'] ?? ''));
                continue;
            }
            if ($type === 'external_link') {
                $this->insertExternalLink(
                    $targetId,
                    (string)$item['title'],
                    (string)($item['link_url'] ?? ''),
                    $sort,
                    (int)($item['open_new_tab'] ?? 1) === 1,
                    (int)($item['required'] ?? 1) === 1
                );
                continue;
            }
            $activityId = (int)($item['activity_id'] ?? 0);
            $activity = $activityId > 0 ? $this->activity($activityId) : null;
            if (!$activity) {
                continue;
            }
            $newActivityId = $this->cloneActivity($targetId, $activity);
            $this->insertItem($targetId, 'activity', (string)$item['title'], $sort, null, $newActivityId, null);
        }
        while ($videoIndex < count($assetIds)) {
            $sort++;
            $videoIndex++;
            $this->insertItem(
                $targetId,
                'video',
                self::defaultClipTitle($videoIndex),
                $sort,
                $assetIds[$videoIndex - 1],
                null,
                null
            );
        }

        $row = $this->find($targetId);
        if (!$row) {
            throw new RuntimeException('Could not copy the lesson.');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $timetable
     */
    public function notifyPublished(array $lesson, array $timetable): int
    {
        $classId = (int)($timetable['class_id'] ?? 0);
        if ($classId < 1 || !function_exists('campus_class_student_ids')) {
            return 0;
        }
        $ids = campus_class_student_ids($this->pdo, $classId);
        $subject = trim((string)($timetable['subject_name'] ?? 'Class'));
        $date = !empty($timetable['date']) ? date('d M Y', strtotime((string)$timetable['date'])) : '';
        $title = 'Video lesson ready';
        $plain = $subject . ($date !== '' ? ' — ' . $date : '');
        $timetableId = (int)($timetable['id'] ?? $lesson['timetable_id'] ?? 0);
        if (function_exists('campus_portal_notify')) {
            campus_portal_notify(
                $this->pdo,
                $ids,
                $title,
                $plain . '. Open the sequenced lesson from Class recordings.',
                'lesson.php?lesson=' . $timetableId
            );
        }
        if (function_exists('campus_notify_students')) {
            $wa = "🎬 *Video lesson ready*\n\n*{$subject}*"
                . ($date !== '' ? "\n" . $date : '')
                . "\n\nOpen Student Portal → Class recordings to watch the clips and answer the questions.";
            campus_notify_students($this->pdo, $ids, $wa, 'VIDEO_LESSON');
        }
        return count($ids);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function banksForUser(int $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            $stmt = $this->pdo->query('
                SELECT b.*, (SELECT COUNT(*) FROM online_question_bank_items i WHERE i.bank_id = b.id) AS question_count
                FROM online_question_banks b
                ORDER BY b.updated_at DESC, b.id DESC
                LIMIT 80
            ');
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }
        $stmt = $this->pdo->prepare('
            SELECT b.*, (SELECT COUNT(*) FROM online_question_bank_items i WHERE i.bank_id = b.id) AS question_count
            FROM online_question_banks b
            WHERE b.owner_user_id = ?
            ORDER BY b.updated_at DESC, b.id DESC
            LIMIT 80
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createBankFromActivity(int $activityId, int $ownerUserId, string $title): int
    {
        $questions = $this->questions($activityId);
        if ($questions === []) {
            throw new RuntimeException('This activity has no questions to save.');
        }
        $activity = $this->activity($activityId);
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = mb_substr(trim((string)($activity['title'] ?? 'Question bank')), 0, 200);
        }
        return $this->insertBank($ownerUserId, $title, $questions);
    }

    public function createBankFromAiken(int $ownerUserId, string $title, string $text, float $marks = 1.0): int
    {
        $parsed = self::parseAiken($text);
        if ($parsed['questions'] === []) {
            throw new RuntimeException('No Aiken MCQs found to save as a bank.');
        }
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = 'Aiken import ' . date('d M Y');
        }
        $rows = [];
        foreach ($parsed['questions'] as $question) {
            $rows[] = [
                'question_type' => 'mcq',
                'prompt' => $question['prompt'],
                'choices' => $question['choices'],
                'correct_index' => $question['correct_index'],
                'marks' => $marks,
                'explanation' => '',
            ];
        }
        return $this->insertBank($ownerUserId, $title, $rows);
    }

    public function insertBankIntoActivity(int $activityId, int $bankId, int $ownerUserId, bool $isAdmin): int
    {
        $bank = $this->bank($bankId);
        if (!$bank) {
            throw new RuntimeException('That question bank was not found.');
        }
        if (!$isAdmin && (int)$bank['owner_user_id'] !== $ownerUserId) {
            throw new RuntimeException('You cannot use that question bank.');
        }
        $stmt = $this->pdo->prepare('
            SELECT * FROM online_question_bank_items WHERE bank_id = ? ORDER BY sort_order ASC, id ASC
        ');
        $stmt->execute([$bankId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            throw new RuntimeException('That question bank is empty.');
        }
        $added = 0;
        foreach ($rows as $row) {
            $newQuestionId = $this->addQuestion(
                $activityId,
                (string)$row['question_type'],
                (string)$row['prompt'],
                $this->decodeChoices((string)($row['choices_json'] ?? '')),
                $row['correct_index'] === null ? null : (int)$row['correct_index'],
                (float)$row['marks'],
                (string)($row['explanation'] ?? ''),
                (string)($row['topic'] ?? ''),
                (string)($row['difficulty'] ?? '')
            );
            if (trim((string)($row['tags'] ?? '')) !== '') {
                $this->setQuestionTags($newQuestionId, (string)$row['tags']);
            }
            $added++;
        }
        return $added;
    }

    /**
     * Copies selected bank questions into an activity. The bank row is not changed.
     *
     * @param list<int> $itemIds
     */
    public function insertBankItems(int $activityId, array $itemIds, int $ownerUserId, bool $isAdmin): int
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn (int $id): bool => $id > 0)));
        if ($itemIds === []) {
            throw new RuntimeException('Select at least one question.');
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT i.*, b.owner_user_id
            FROM online_question_bank_items i
            JOIN online_question_banks b ON b.id = i.bank_id
            WHERE i.id IN ($placeholders)
        ");
        $stmt->execute($itemIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $added = 0;
        foreach ($rows as $row) {
            if (!$isAdmin && (int)$row['owner_user_id'] !== $ownerUserId) {
                continue;
            }
            $newQuestionId = $this->addQuestion(
                $activityId,
                (string)$row['question_type'],
                (string)$row['prompt'],
                $this->decodeChoices((string)($row['choices_json'] ?? '')),
                $row['correct_index'] === null ? null : (int)$row['correct_index'],
                (float)$row['marks'],
                (string)($row['explanation'] ?? ''),
                (string)($row['topic'] ?? ''),
                (string)($row['difficulty'] ?? ''),
                '',
                ''
            );
            if (trim((string)($row['tags'] ?? '')) !== '') {
                $this->setQuestionTags($newQuestionId, (string)$row['tags']);
            }
            $added++;
        }
        if ($added < 1) {
            throw new RuntimeException('Those questions could not be added.');
        }
        return $added;
    }

    /**
     * Preview payload: same student view, nothing is saved.
     *
     * @return array<string,mixed>
     */
    public function previewPayload(array $lesson, array $item): array
    {
        $payload = $this->playerPayload(0, $lesson, $item, true);
        $payload['preview'] = true;
        $payload['completed'] = false;
        if (($payload['type'] ?? '') === 'video') {
            $watch = $payload['watch'] ?? [];
            $watch['required'] = false;
            $watch['enough'] = true;
            $payload['watch'] = $watch;
        }
        return $payload;
    }

    public function lessonHasStudentWork(int $lessonId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM online_lesson_item_state s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE i.lesson_id = ? AND s.student_id > 0
            LIMIT 1
        ');
        $stmt->execute([$lessonId]);
        if ($stmt->fetchColumn()) {
            return true;
        }
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM online_lesson_attempts a
            JOIN online_lesson_items i ON i.id = a.item_id
            WHERE i.lesson_id = ? AND a.student_id > 0
            LIMIT 1
        ');
        $stmt->execute([$lessonId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $activity
     */
    private function cloneActivity(int $targetLessonId, array $activity): int
    {
        try {
            $this->pdo->prepare('
                INSERT INTO online_lesson_activities
                    (lesson_id, activity_type, title, instructions, pass_percent, max_attempts,
                     show_correct, shuffle_choices, shuffle_questions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $targetLessonId,
                (string)($activity['activity_type'] ?? 'mcq'),
                (string)$activity['title'],
                $activity['instructions'] ?? null,
                $activity['pass_percent'] ?? null,
                (int)($activity['max_attempts'] ?? 0),
                (int)($activity['show_correct'] ?? 1),
                (int)($activity['shuffle_choices'] ?? 0),
                (int)($activity['shuffle_questions'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $this->pdo->prepare('
                INSERT INTO online_lesson_activities
                    (lesson_id, activity_type, title, instructions, pass_percent, max_attempts, show_correct)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $targetLessonId,
                (string)($activity['activity_type'] ?? 'mcq'),
                (string)$activity['title'],
                $activity['instructions'] ?? null,
                $activity['pass_percent'] ?? null,
                (int)($activity['max_attempts'] ?? 0),
                (int)($activity['show_correct'] ?? 1),
            ]);
        }
        $newId = (int)$this->pdo->lastInsertId();
        try {
            $this->savePoolSettings(
                $newId,
                (int)($activity['draw_count'] ?? 0),
                $activity['scoring_rule'] ?? null,
                (int)($activity['time_limit_minutes'] ?? 0),
                (int)($activity['show_score'] ?? 1) === 1,
                (int)($activity['show_explanation'] ?? 1) === 1
            );
        } catch (\Throwable $e) {
            // older schema
        }
        foreach ($this->questions((int)$activity['id']) as $question) {
            $copyId = $this->addQuestion(
                $newId,
                (string)$question['question_type'],
                (string)$question['prompt'],
                $question['choices'] ?? [],
                $question['correct_index'] === null ? null : (int)$question['correct_index'],
                (float)$question['marks'],
                (string)($question['explanation'] ?? ''),
                (string)($question['topic'] ?? ''),
                (string)($question['difficulty'] ?? ''),
                (string)($question['exam_ref'] ?? ''),
                (string)($question['expected_answer'] ?? '')
            );
            if (trim((string)($question['tags'] ?? '')) !== '') {
                $this->setQuestionTags($copyId, (string)$question['tags']);
            }
        }
        return $newId;
    }

    private function wipeLessonItems(int $lessonId): void
    {
        $items = $this->items($lessonId);
        $activityIds = [];
        foreach ($items as $item) {
            $aid = (int)($item['activity_id'] ?? 0);
            if ($aid > 0) {
                $activityIds[] = $aid;
            }
        }
        $this->pdo->prepare('DELETE FROM online_lesson_items WHERE lesson_id = ?')->execute([$lessonId]);
        foreach (array_unique($activityIds) as $activityId) {
            $this->pdo->prepare('DELETE FROM online_lesson_questions WHERE activity_id = ?')->execute([$activityId]);
            $this->pdo->prepare('DELETE FROM online_lesson_activities WHERE id = ?')->execute([$activityId]);
        }
        $this->pdo->prepare('DELETE FROM online_lesson_activities WHERE lesson_id = ?')->execute([$lessonId]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function bank(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_question_banks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param list<array<string,mixed>> $questions
     */
    private function insertBank(int $ownerUserId, string $title, array $questions): int
    {
        $this->pdo->prepare('INSERT INTO online_question_banks (owner_user_id, title) VALUES (?, ?)')
            ->execute([$ownerUserId, $title]);
        $bankId = (int)$this->pdo->lastInsertId();
        try {
            $ins = $this->pdo->prepare('
                INSERT INTO online_question_bank_items
                    (bank_id, question_type, sort_order, prompt, choices_json, correct_index, marks, explanation, topic, difficulty)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $withMeta = true;
        } catch (\Throwable $e) {
            $ins = $this->pdo->prepare('
                INSERT INTO online_question_bank_items
                    (bank_id, question_type, sort_order, prompt, choices_json, correct_index, marks, explanation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $withMeta = false;
        }
        $sort = 0;
        foreach ($questions as $question) {
            $sort++;
            $type = strtolower((string)($question['question_type'] ?? $question['type'] ?? 'mcq'));
            if (!in_array($type, ['mcq', 'essay', 'short', 'exam'], true)) {
                $type = 'mcq';
            }
            $choices = self::normalizeChoices($question['choices'] ?? []);
            $params = [
                $bankId,
                $type,
                $sort,
                (string)$question['prompt'],
                $choices === [] ? null : json_encode($choices, JSON_UNESCAPED_UNICODE),
                $question['correct_index'] === null || $question['correct_index'] === '' ? null : (int)$question['correct_index'],
                max(0.5, round((float)($question['marks'] ?? 1), 2)),
                trim((string)($question['explanation'] ?? '')) !== '' ? (string)$question['explanation'] : null,
            ];
            if ($withMeta) {
                $params[] = self::blankMeta((string)($question['topic'] ?? ''), 120);
                $params[] = self::blankMeta((string)($question['difficulty'] ?? ''), 40);
            }
            $ins->execute($params);
            if (trim((string)($question['tags'] ?? '')) !== '') {
                try {
                    $this->pdo->prepare('UPDATE online_question_bank_items SET tags = ? WHERE id = ?')
                        ->execute([QuestionPool::normalizeTags((string)$question['tags']), (int)$this->pdo->lastInsertId()]);
                } catch (\Throwable $e) {
                    // older schema
                }
            }
        }
        return $bankId;
    }

    private static function blankMeta(string $value, int $limit): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $limit);
    }
}
