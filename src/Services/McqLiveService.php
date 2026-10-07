<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Live view of which question each student is on during an MCQ activity.
 *
 * State lives on the existing in-progress attempt row (online_lesson_attempt_sessions).
 * Question numbers and answer letters are always worked out on the server from the order the
 * student was actually shown; the browser only reports a question id and a displayed choice.
 */
final class McqLiveService
{
    public const HEARTBEAT_SECONDS = 30;
    public const STATUSES = ['active', 'idle', 'offline', 'submitted'];
    private const RECENT_HOURS = 6;
    private const STALE_RESTART_SECONDS = 7200;

    private OnlineLessonService $lessons;

    public function __construct(private PDO $pdo, ?OnlineLessonService $lessons = null)
    {
        $this->lessons = $lessons ?? new OnlineLessonService($pdo);
    }

    /** Seconds without input or with the tab hidden before a student shows as idle. */
    public static function idleSeconds(): int
    {
        return self::envSeconds('MCQ_LIVE_IDLE_SECONDS', 120, 30, 1800);
    }

    /**
     * Seconds without any heartbeat before a student shows as offline. Browsers slow timers in
     * background tabs to about once a minute, so this stays well above that.
     */
    public static function offlineSeconds(): int
    {
        return max(self::HEARTBEAT_SECONDS * 3, self::envSeconds('MCQ_LIVE_OFFLINE_SECONDS', 150, 60, 3600));
    }

    private static function envSeconds(string $key, int $default, int $min, int $max): int
    {
        $raw = $_ENV[$key] ?? getenv($key);
        if ($raw === false || $raw === null || trim((string)$raw) === '' || !is_numeric($raw)) {
            return $default;
        }
        return max($min, min($max, (int)$raw));
    }

    /**
     * Idle is about input; offline is about the page no longer reporting in. A student reading a
     * long question keeps sending heartbeats, so they can become idle but never offline.
     *
     * @param array<string,mixed> $row
     */
    public static function deriveStatus(array $row, int $now, int $idleSeconds, int $offlineSeconds): string
    {
        $stored = (string)($row['live_status'] ?? '');
        if ($stored === 'submitted') {
            return 'submitted';
        }
        $heartbeat = self::ts($row['last_heartbeat_at'] ?? null);
        if ($stored === 'left' || $heartbeat === 0 || $now - $heartbeat > $offlineSeconds) {
            return 'offline';
        }
        $interaction = self::ts($row['last_interaction_at'] ?? null);
        if ($stored === 'idle' || $interaction === 0 || $now - $interaction > $idleSeconds) {
            return 'idle';
        }
        return 'active';
    }

    public static function choiceLetter(?int $index): string
    {
        return $index === null || $index < 0 || $index > 25 ? '' : chr(65 + $index);
    }

    /**
     * Question ids in the order this student sees them, matching OnlineLessonService::playerPayload().
     *
     * @param array<string,mixed> $activity
     * @return list<array<string,mixed>>
     */
    public function displayedQuestions(int $studentId, int $itemId, array $activity): array
    {
        $activityId = (int)($activity['id'] ?? 0);
        $questions = $this->lessons->studentQuestions($activityId);
        if ($questions === []) {
            return [];
        }
        $session = $this->lessons->attemptSession($studentId, $itemId, $activity, $questions, false);
        if ($session !== null) {
            return QuestionPool::orderQuestions($questions, $session['question_ids']);
        }
        if ((int)($activity['shuffle_questions'] ?? 0) === 1 && count($questions) > 1) {
            $shuffled = [];
            foreach (OnlineLessonService::permutation(count($questions), 'q:' . $studentId . ':' . $activityId) as $idx) {
                if (isset($questions[$idx])) {
                    $shuffled[] = $questions[$idx];
                }
            }
            return $shuffled !== [] ? $shuffled : $questions;
        }
        return $questions;
    }

    /**
     * Records one student event. The caller has already checked the student may open this item.
     *
     * @param array<string,mixed> $item lesson item row (from OnlineLessonService::item())
     * @param array<string,mixed> $input question_id, choice, text, answers, interacted, visible
     * @return array<string,mixed>
     */
    public function record(int $studentId, array $item, string $event, array $input, ?int $now = null): array
    {
        $now = $now ?? time();
        $stamp = date('Y-m-d H:i:s', $now);
        $itemId = (int)($item['id'] ?? 0);
        if ($studentId < 1 || $itemId < 1 || (string)($item['item_type'] ?? '') !== 'activity') {
            throw new RuntimeException('That is not a question activity.');
        }

        if ($event === 'heartbeat' || $event === 'leave') {
            $row = $this->latestRow($studentId, $itemId);
            if (!$row || (string)($row['live_status'] ?? '') === 'submitted' || (string)($row['live_status'] ?? '') === '') {
                return ['ok' => true, 'tracked' => false];
            }
            if ($event === 'leave') {
                $this->update($row, ['live_status' => 'left', 'last_heartbeat_at' => $stamp, 'live_updated_at' => $stamp]);
                return ['ok' => true, 'tracked' => true];
            }
            $visible = !empty($input['visible']);
            $fields = [
                'last_heartbeat_at' => $stamp,
                'live_status' => $visible ? 'active' : 'idle',
                'live_updated_at' => $stamp,
            ];
            if ($visible && !empty($input['interacted'])) {
                $fields['last_interaction_at'] = $stamp;
            }
            $this->update($row, $fields);
            return ['ok' => true, 'tracked' => true];
        }

        $activity = $this->lessons->activity((int)$item['activity_id']);
        if (!$activity) {
            throw new RuntimeException('Activity was not found.');
        }
        $questions = $this->displayedQuestions($studentId, $itemId, $activity);
        if ($questions === []) {
            return ['ok' => true, 'tracked' => false];
        }
        $position = [];
        $byId = [];
        foreach ($questions as $i => $question) {
            $position[(int)$question['id']] = $i + 1;
            $byId[(int)$question['id']] = $question;
        }
        $questionId = (int)($input['question_id'] ?? 0);
        if ($questionId !== 0 && !isset($position[$questionId])) {
            throw new RuntimeException('That question is not part of this attempt.');
        }

        if ($event === 'open') {
            $row = $this->ensureRow($studentId, $item, $activity, $stamp);
            if ($row === null) {
                return ['ok' => true, 'tracked' => false];
            }
            if ($questionId === 0) {
                $questionId = (int)$questions[0]['id'];
            }
            $answers = [];
            $raw = $input['answers'] ?? [];
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (is_array($raw)) {
                foreach (array_slice($raw, 0, 500, true) as $qid => $value) {
                    $qid = (int)$qid;
                    if (!isset($byId[$qid])) {
                        continue;
                    }
                    $stored = $this->storedAnswer($studentId, $byId[$qid], (bool)($activity['shuffle_choices'] ?? false), $value);
                    if ($stored !== null) {
                        $answers[$qid] = $stored;
                    }
                }
            }
            $restart = (int)($row['live_only'] ?? 0) === 1
                && self::ts($row['last_heartbeat_at'] ?? null) > 0
                && $now - self::ts($row['last_heartbeat_at'] ?? null) > self::STALE_RESTART_SECONDS;
            $fields = [
                'current_question_id' => $questionId,
                'current_question_no' => $position[$questionId],
                'total_questions' => count($questions),
                'selected_choice' => $this->selectedFor($answers, $questionId),
                'answers_json' => json_encode((object)$answers),
                'question_started_at' => (int)($row['current_question_id'] ?? 0) === $questionId && !$restart && !empty($row['question_started_at'])
                    ? (string)$row['question_started_at']
                    : $stamp,
                'last_interaction_at' => $stamp,
                'last_heartbeat_at' => $stamp,
                'live_status' => 'active',
                'live_updated_at' => $stamp,
            ];
            if ($restart) {
                $fields['started_at'] = $stamp;
            }
            $this->update($row, $fields);
            return ['ok' => true, 'tracked' => true, 'question_no' => $position[$questionId], 'total' => count($questions)];
        }

        $row = $this->latestRow($studentId, $itemId);
        $expectedAttempt = $this->attemptCount($studentId, $itemId) + 1;
        if (!$row || (int)$row['attempt_no'] !== $expectedAttempt || in_array((string)($row['live_status'] ?? ''), ['', 'submitted'], true)) {
            return ['ok' => true, 'tracked' => false];
        }
        if ($questionId === 0) {
            throw new RuntimeException('Question was not found.');
        }
        $answers = self::decodeAnswers($row['answers_json'] ?? null);
        $fields = [
            'last_interaction_at' => $stamp,
            'last_heartbeat_at' => $stamp,
            'live_status' => 'active',
            'live_updated_at' => $stamp,
            'total_questions' => count($questions),
        ];
        if ($event === 'answer') {
            $value = array_key_exists('choice', $input) && $input['choice'] !== '' && $input['choice'] !== null
                ? (int)$input['choice']
                : (!empty($input['text']) ? 't' : null);
            $stored = $this->storedAnswer($studentId, $byId[$questionId], (bool)($activity['shuffle_choices'] ?? false), $value);
            if ($stored === null) {
                unset($answers[$questionId]);
            } else {
                $answers[$questionId] = $stored;
            }
            $fields['answers_json'] = json_encode((object)$answers);
        } elseif ($event !== 'question') {
            throw new RuntimeException('Unknown event.');
        }
        if ((int)($row['current_question_id'] ?? 0) !== $questionId || empty($row['question_started_at'])) {
            $fields['question_started_at'] = $stamp;
        }
        $fields['current_question_id'] = $questionId;
        $fields['current_question_no'] = $position[$questionId];
        $fields['selected_choice'] = $this->selectedFor($answers, $questionId);
        $this->update($row, $fields);
        return ['ok' => true, 'tracked' => true, 'question_no' => $position[$questionId], 'total' => count($questions)];
    }

    public function markSubmitted(int $studentId, int $itemId, int $attemptNo, int $attemptId, ?int $now = null): void
    {
        $stamp = date('Y-m-d H:i:s', $now ?? time());
        $this->pdo->prepare("
            UPDATE online_lesson_attempt_sessions
            SET live_status = 'submitted', attempt_id = ?, live_updated_at = ?, last_interaction_at = ?, last_heartbeat_at = ?
            WHERE student_id = ? AND item_id = ? AND attempt_no = ?
        ")->execute([$attemptId, $stamp, $stamp, $stamp, $studentId, $itemId, $attemptNo]);
    }

    /**
     * Live rows for one lesson, limited to students enrolled in the class. With $since only rows
     * written since then are returned.
     *
     * @return list<array<string,mixed>>
     */
    public function monitorRows(int $lessonId, int $classId, int $itemId = 0, int $attemptNo = 0, ?string $since = null, ?int $now = null): array
    {
        $now = $now ?? time();
        $floor = date('Y-m-d H:i:s', $now - self::RECENT_HOURS * 3600);
        if ($since !== null && $since > $floor) {
            $floor = $since;
        }
        $sql = "
            SELECT s.student_id, s.item_id, s.attempt_no, s.attempt_id, s.current_question_id, s.current_question_no,
                   s.total_questions, s.selected_choice, s.answers_json, s.question_started_at, s.started_at,
                   s.last_interaction_at, s.last_heartbeat_at, s.live_status, s.live_updated_at,
                   COALESCE(NULLIF(sp.full_name, ''), u.username) AS name
            FROM online_lesson_attempt_sessions s
            JOIN users u ON u.id = s.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE s.lesson_id = ?
              AND s.live_updated_at >= ?
              AND s.live_status IS NOT NULL
              AND EXISTS (SELECT 1 FROM student_enrollments se WHERE se.student_id = s.student_id AND se.class_id = ?)
        ";
        $params = [$lessonId, $floor, $classId];
        if ($itemId > 0) {
            $sql .= ' AND s.item_id = ?';
            $params[] = $itemId;
        }
        if ($attemptNo > 0) {
            $sql .= ' AND s.attempt_no = ?';
            $params[] = $attemptNo;
        }
        $sql .= ' ORDER BY s.attempt_no ASC LIMIT 2000';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $latest = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $latest[(int)$row['student_id'] . ':' . (int)$row['item_id']] = $row;
        }
        $idle = self::idleSeconds();
        $offline = self::offlineSeconds();
        $out = [];
        foreach ($latest as $row) {
            $out[] = $this->publicRow($row, $now, $idle, $offline);
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    /**
     * One student's live panel, including a per-question progress strip. Never includes correct answers.
     *
     * @param array<string,mixed> $item
     * @return array<string,mixed>|null
     */
    public function studentDetail(int $lessonId, int $classId, array $item, int $studentId, ?int $now = null): ?array
    {
        $now = $now ?? time();
        $rows = $this->monitorRows($lessonId, $classId, (int)$item['id'], 0, null, $now);
        $summary = null;
        foreach ($rows as $row) {
            if ($row['student_id'] === $studentId) {
                $summary = $row;
                break;
            }
        }
        if ($summary === null) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT answers_json FROM online_lesson_attempt_sessions WHERE student_id = ? AND item_id = ? AND attempt_no = ? LIMIT 1');
        $stmt->execute([$studentId, (int)$item['id'], $summary['attempt_no']]);
        $answers = self::decodeAnswers((string)$stmt->fetchColumn());
        $activity = $this->lessons->activity((int)$item['activity_id']);
        $questions = [];
        if ($activity) {
            $shown = $this->shownQuestionsForAttempt($studentId, $item, $activity, $summary);
            foreach ($shown as $i => $question) {
                $qid = (int)$question['id'];
                $answer = $answers[$qid] ?? null;
                $state = $answer !== null ? 'answered' : 'unanswered';
                if ($qid === (int)$summary['current_question_id'] && $summary['status'] !== 'submitted') {
                    $state = 'current';
                }
                $questions[] = [
                    'n' => $i + 1,
                    'state' => $state,
                    'answered' => $answer !== null,
                    'answer' => $answer === 't' ? 'Written' : (is_int($answer) ? self::choiceLetter($answer) : ''),
                ];
            }
        }
        $summary['questions'] = $questions;
        return $summary;
    }

    /**
     * @param array<string,mixed> $activity
     * @param array<string,mixed> $summary
     * @return list<array<string,mixed>>
     */
    private function shownQuestionsForAttempt(int $studentId, array $item, array $activity, array $summary): array
    {
        if ($summary['status'] === 'submitted' && (int)($summary['attempt_id'] ?? 0) > 0) {
            $stmt = $this->pdo->prepare('SELECT question_ids_json FROM online_lesson_attempts WHERE id = ? AND student_id = ? LIMIT 1');
            $stmt->execute([(int)$summary['attempt_id'], $studentId]);
            $ids = QuestionPool::decodeIds((string)$stmt->fetchColumn());
            if ($ids !== null) {
                return QuestionPool::orderQuestions($this->lessons->studentQuestions((int)$activity['id']), $ids);
            }
        }
        return $this->displayedQuestions($studentId, (int)$item['id'], $activity);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function publicRow(array $row, int $now, int $idle, int $offline): array
    {
        $status = self::deriveStatus($row, $now, $idle, $offline);
        $answers = self::decodeAnswers($row['answers_json'] ?? null);
        $total = max(0, (int)($row['total_questions'] ?? 0));
        $answered = min($total > 0 ? $total : PHP_INT_MAX, count($answers));
        $end = $status === 'submitted' ? self::ts($row['live_updated_at'] ?? null) : $now;
        $selected = $row['selected_choice'] === null || $row['selected_choice'] === '' ? null : (int)$row['selected_choice'];
        $currentAnswer = $answers[(int)($row['current_question_id'] ?? 0)] ?? null;
        return [
            'key' => (int)$row['student_id'] . ':' . (int)$row['item_id'],
            'student_id' => (int)$row['student_id'],
            'name' => (string)$row['name'],
            'item_id' => (int)$row['item_id'],
            'attempt_no' => (int)$row['attempt_no'],
            'attempt_id' => $row['attempt_id'] === null ? null : (int)$row['attempt_id'],
            'status' => $status,
            'current_question_id' => (int)($row['current_question_id'] ?? 0),
            'question_no' => (int)($row['current_question_no'] ?? 0),
            'total' => $total,
            'answered' => $answered,
            'progress' => $total > 0 ? (int)round($answered / $total * 100) : 0,
            'answer' => $currentAnswer === 't' ? 'Written' : self::choiceLetter($selected),
            'question_seconds' => self::elapsed($row['question_started_at'] ?? null, $end),
            'total_seconds' => self::elapsed($row['started_at'] ?? null, $end),
            'last_activity_seconds' => max(0, $now - (self::ts($row['last_interaction_at'] ?? null) ?: self::ts($row['last_heartbeat_at'] ?? null))),
            'last_input_seconds' => max(0, $now - self::ts($row['last_interaction_at'] ?? null)),
            'last_heartbeat_seconds' => max(0, $now - self::ts($row['last_heartbeat_at'] ?? null)),
            'stored_status' => (string)($row['live_status'] ?? ''),
        ];
    }

    /**
     * Makes sure the current attempt has a row. Pool and timed activities already have one once the
     * student starts; for plain MCQs a live-only row is added without any draw or start time.
     *
     * @param array<string,mixed> $item
     * @param array<string,mixed> $activity
     * @return array<string,mixed>|null
     */
    private function ensureRow(int $studentId, array $item, array $activity, string $stamp): ?array
    {
        $itemId = (int)$item['id'];
        $latest = $this->lessons->latestAttempt($studentId, $itemId);
        $maxAttempts = (int)($activity['max_attempts'] ?? 0);
        if (
            $this->lessons->isItemComplete($studentId, $itemId)
            || ($latest && ((int)($latest['passed'] ?? 0) === 1 || ($maxAttempts > 0 && (int)$latest['attempt_no'] >= $maxAttempts)))
        ) {
            return null;
        }
        $attemptNo = $this->attemptCount($studentId, $itemId) + 1;
        $row = $this->row($studentId, $itemId, $attemptNo);
        if (!$row) {
            if (QuestionPool::usesSession($activity)) {
                return null;
            }
            try {
                $this->pdo->prepare('
                    INSERT INTO online_lesson_attempt_sessions
                        (student_id, item_id, attempt_no, question_ids_json, started_at, lesson_id, activity_id, live_only, live_updated_at)
                    VALUES (?, ?, ?, NULL, ?, ?, ?, 1, ?)
                ')->execute([$studentId, $itemId, $attemptNo, $stamp, (int)$item['lesson_id'], (int)$activity['id'], $stamp]);
            } catch (\PDOException $e) {
                // Another tab created it first.
            }
            $row = $this->row($studentId, $itemId, $attemptNo);
            if (!$row) {
                return null;
            }
        }
        if ((int)($row['lesson_id'] ?? 0) !== (int)$item['lesson_id'] || (int)($row['activity_id'] ?? 0) !== (int)$activity['id']) {
            $this->update($row, ['lesson_id' => (int)$item['lesson_id'], 'activity_id' => (int)$activity['id']]);
            $row['lesson_id'] = (int)$item['lesson_id'];
            $row['activity_id'] = (int)$activity['id'];
        }
        if ((string)($row['live_status'] ?? '') === 'submitted') {
            return null;
        }
        return $row;
    }

    /**
     * Stores the original (unshuffled) choice index, or 't' for a written answer.
     *
     * @param array<string,mixed> $question
     */
    private function storedAnswer(int $studentId, array $question, bool $shuffleChoices, mixed $value): int|string|null
    {
        $type = (string)($question['question_type'] ?? 'mcq');
        if (OnlineLessonService::isManualQuestionType($type)) {
            return ($value === 't' || $value === true || $value === 1 || $value === '1') ? 't' : null;
        }
        if ($value === null || $value === '' || $value === 't' || !is_numeric($value)) {
            return null;
        }
        $display = (int)$value;
        $count = count($question['choices'] ?? []);
        if ($display < 0 || $display >= $count) {
            return null;
        }
        $original = $shuffleChoices && $type === 'mcq'
            ? OnlineLessonService::originalChoiceIndex($studentId, (int)$question['id'], $count, $display)
            : $display;
        return $original === null ? null : (int)$original;
    }

    /**
     * @param array<int,int|string> $answers
     */
    private function selectedFor(array $answers, int $questionId): ?int
    {
        $value = $answers[$questionId] ?? null;
        return is_int($value) ? $value : null;
    }

    /**
     * @return array<int,int|string>
     */
    private static function decodeAnswers(mixed $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $qid => $value) {
            if ((int)$qid < 1) {
                continue;
            }
            $out[(int)$qid] = $value === 't' ? 't' : (int)$value;
        }
        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function row(int $studentId, int $itemId, int $attemptNo): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_attempt_sessions WHERE student_id = ? AND item_id = ? AND attempt_no = ? LIMIT 1');
        $stmt->execute([$studentId, $itemId, $attemptNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function latestRow(int $studentId, int $itemId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_attempt_sessions WHERE student_id = ? AND item_id = ? ORDER BY attempt_no DESC LIMIT 1');
        $stmt->execute([$studentId, $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function attemptCount(int $studentId, int $itemId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM online_lesson_attempts WHERE student_id = ? AND item_id = ?');
        $stmt->execute([$studentId, $itemId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $fields
     */
    private function update(array $row, array $fields): void
    {
        $allowed = [
            'lesson_id', 'activity_id', 'current_question_id', 'current_question_no', 'total_questions', 'selected_choice',
            'answers_json', 'question_started_at', 'last_interaction_at', 'last_heartbeat_at', 'live_status',
            'live_updated_at', 'started_at',
        ];
        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }
        if ($sets === []) {
            return;
        }
        $params[] = (int)$row['student_id'];
        $params[] = (int)$row['item_id'];
        $params[] = (int)$row['attempt_no'];
        $this->pdo->prepare('
            UPDATE online_lesson_attempt_sessions SET ' . implode(', ', $sets) . "
            WHERE student_id = ? AND item_id = ? AND attempt_no = ?
              AND (live_status IS NULL OR live_status <> 'submitted')
        ")->execute($params);
    }

    private static function ts(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $t = strtotime((string)$value);
        return $t === false ? 0 : $t;
    }

    private static function elapsed(mixed $from, int $to): int
    {
        $start = self::ts($from);
        return $start > 0 && $to > $start ? $to - $start : 0;
    }
}
