<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;

/**
 * Lesson quality report (issues with severity and a fix) and factual improvement insights
 * computed from what students actually did. Insights never guess; each one states the numbers behind it.
 */
final class LessonInsightService
{
    public const SEVERITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];
    public const MIN_STUDENTS = 3;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Pure checks. "high" blocks publishing; "medium" and "low" are advice.
     *
     * @param array{
     *   lesson:array<string,mixed>, items:list<array<string,mixed>>, questions:list<array<string,mixed>>,
     *   sections?:list<array<string,mixed>>, objectives?:list<array<string,mixed>>, links?:list<array<string,mixed>>,
     *   activities?:array<int,array<string,mixed>>, archived_resource_ids?:list<int>, superseded_resource_ids?:list<int>,
     *   criteria_counts?:array<int,int>, prerequisites?:list<array<string,mixed>>
     * } $ctx
     * @return list<array{message:string,severity:string,fix:string,activity_id:int,item_id:int}>
     */
    public static function qualityIssues(array $ctx): array
    {
        $lesson = $ctx['lesson'];
        $items = $ctx['items'];
        $questions = $ctx['questions'];
        $sections = $ctx['sections'] ?? [];
        $issues = [];
        $add = static function (string $message, string $severity, string $fix, int $activityId = 0, int $itemId = 0) use (&$issues): void {
            $issues[] = ['message' => $message, 'severity' => $severity, 'fix' => $fix, 'activity_id' => $activityId, 'item_id' => $itemId];
        };

        foreach (LessonAuthoringService::issueRows($lesson, $items, $questions, $sections, $ctx['archived_resource_ids'] ?? []) as $row) {
            $message = (string)$row['message'];
            $fix = 'Open the activity and complete it.';
            if (str_contains($message, 'no correct answer')) {
                $fix = 'Choose the correct option for each multiple-choice question.';
            } elseif (str_contains($message, 'no marks')) {
                $fix = 'Give every question at least 1 mark.';
            } elseif (str_contains($message, 'no questions')) {
                $fix = 'Add questions or insert them from a question bank.';
            } elseif (str_contains($message, 'empty section')) {
                $fix = 'Move an activity into the section, or delete the section.';
            } elseif (str_contains($message, 'archived resource')) {
                $fix = 'Restore the resource in the library, or replace it.';
            } elseif (str_contains($message, 'archived')) {
                $fix = 'Restore the lesson to the active list.';
            } elseif (str_contains($message, 'no activities')) {
                $fix = 'Add a page, link, resource, or question activity.';
            } elseif (str_contains($message, 'invalid link')) {
                $fix = 'Edit the link and enter a full https:// address.';
            }
            $add($message, 'high', $fix, (int)$row['activity_id'], (int)$row['item_id']);
        }

        $byActivity = [];
        foreach ($questions as $q) {
            $byActivity[(int)($q['activity_id'] ?? 0)][] = $q;
        }
        $activities = $ctx['activities'] ?? [];
        $criteria = $ctx['criteria_counts'] ?? [];
        foreach ($items as $item) {
            if (OnlineLessonService::normalizeItemType((string)($item['item_type'] ?? '')) !== 'activity') {
                continue;
            }
            $aid = (int)($item['activity_id'] ?? 0);
            $itemId = (int)($item['id'] ?? 0);
            $title = trim((string)($item['title'] ?? '')) ?: 'Untitled activity';
            $rows = $byActivity[$aid] ?? [];
            $settings = $activities[$aid] ?? [];
            $draw = (int)($settings['draw_count'] ?? 0);
            if ($draw > 0 && $rows !== [] && $draw > count($rows)) {
                $add($title . ' draws ' . $draw . ' questions but only has ' . count($rows) . '.', 'high', 'Add more questions to the pool or lower “questions per student”.', $aid, $itemId);
            }
            foreach ($rows as $q) {
                if (!empty($q['ai_generated'])) {
                    $add($title . ' has an AI-generated question that has not been reviewed.', 'high', 'Open the question, check it, and save it to approve.', $aid, $itemId);
                    break;
                }
            }
            $missingExplanation = 0;
            $manualWithoutScheme = 0;
            $noDifficulty = 0;
            foreach ($rows as $q) {
                $kind = (string)($q['question_type'] ?? 'mcq');
                if (!OnlineLessonService::isManualQuestionType($kind) && trim((string)($q['explanation'] ?? '')) === '') {
                    $missingExplanation++;
                }
                if (OnlineLessonService::isManualQuestionType($kind)
                    && trim((string)($q['expected_answer'] ?? '')) === ''
                    && (int)($criteria[(int)($q['id'] ?? 0)] ?? 0) === 0) {
                    $manualWithoutScheme++;
                }
                if (trim((string)($q['difficulty'] ?? '')) === '') {
                    $noDifficulty++;
                }
            }
            if ($manualWithoutScheme > 0) {
                $add($title . ' has ' . $manualWithoutScheme . ' written question' . ($manualWithoutScheme === 1 ? '' : 's') . ' with no expected answer or mark scheme.', 'medium', 'Add an expected answer or mark-scheme criteria so marking is consistent.', $aid, $itemId);
            }
            if ($missingExplanation > 0) {
                $add($title . ' has ' . $missingExplanation . ' multiple-choice question' . ($missingExplanation === 1 ? '' : 's') . ' without an explanation.', 'low', 'Add a short explanation students see after answering.', $aid, $itemId);
            }
            if ($rows !== [] && $noDifficulty === count($rows)) {
                $add($title . ' has no difficulty set on any question.', 'low', 'Set Easy, Medium or Hard so questions can be filtered later.', $aid, $itemId);
            }
            if ((int)($settings['time_limit_minutes'] ?? 0) > 0 && (int)($settings['max_attempts'] ?? 0) === 0) {
                $add($title . ' is timed but allows unlimited attempts.', 'low', 'Set a maximum number of attempts if the time limit matters.', $aid, $itemId);
            }
        }

        $objectives = $ctx['objectives'] ?? [];
        if ($objectives === []) {
            $add('The lesson has no learning objectives.', 'medium', 'Add objectives in the lesson plan so results can be reported by objective.');
        } else {
            $linked = [];
            foreach ($ctx['links'] ?? [] as $link) {
                $linked[(int)$link['objective_id']] = true;
            }
            foreach ($objectives as $objective) {
                if (!isset($linked[(int)$objective['id']])) {
                    $add('Objective “' . mb_strimwidth((string)$objective['body'], 0, 60, '…') . '” is not linked to any question.', 'medium', 'Link at least one question to this objective when editing the question.');
                }
            }
        }

        $duration = LessonAuthoringService::durationStatus(
            isset($lesson['plan_minutes']) && $lesson['plan_minutes'] !== '' && $lesson['plan_minutes'] !== null ? (int)$lesson['plan_minutes'] : null,
            $sections,
            $items
        );
        if ($duration['state'] === 'over') {
            $add($duration['message'], 'medium', 'Shorten or remove an activity, or increase the planned duration.');
        } elseif ($duration['estimated'] === null && $items !== []) {
            $add('No activity has an estimated duration.', 'low', 'Add estimated minutes to activities or sections to check the lesson fits.');
        }

        $superseded = array_fill_keys(array_map('intval', $ctx['superseded_resource_ids'] ?? []), true);
        foreach ($items as $item) {
            $rid = (int)($item['resource_id'] ?? 0);
            if ($rid > 0 && isset($superseded[$rid])) {
                $add((trim((string)($item['title'] ?? '')) ?: 'A resource') . ' uses an older version of the resource.', 'low', 'Switch it to the latest version from the lesson manager.', 0, (int)($item['id'] ?? 0));
            }
        }

        foreach ($ctx['prerequisites'] ?? [] as $pre) {
            if (!OnlineLessonService::isReleased($pre)) {
                $add('Prerequisite “' . (string)$pre['title'] . '” is not published, so it will not block students.', 'low', 'Publish the prerequisite lesson or remove the prerequisite.');
            }
        }

        usort($issues, static fn (array $a, array $b): int => self::SEVERITY_ORDER[$a['severity']] <=> self::SEVERITY_ORDER[$b['severity']]);
        return $issues;
    }

    public static function isReady(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'high') {
                return false;
            }
        }
        return true;
    }

    /**
     * Collects everything qualityIssues needs for one lesson.
     *
     * @return list<array{message:string,severity:string,fix:string,activity_id:int,item_id:int}>
     */
    public function qualityReport(int $lessonId): array
    {
        $lessons = new OnlineLessonService($this->pdo);
        $lesson = $lessons->find($lessonId);
        if (!$lesson) {
            return [];
        }
        $questions = $this->rows('
            SELECT q.* FROM online_lesson_questions q
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ?
        ', [$lessonId]);
        $activities = [];
        foreach ($this->rows('SELECT * FROM online_lesson_activities WHERE lesson_id = ?', [$lessonId]) as $a) {
            $activities[(int)$a['id']] = $a;
        }
        $criteria = [];
        foreach ($this->rows('
            SELECT c.question_id, COUNT(*) AS n FROM online_lesson_criteria c
            JOIN online_lesson_questions q ON q.id = c.question_id
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ? GROUP BY c.question_id
        ', [$lessonId]) as $row) {
            $criteria[(int)$row['question_id']] = (int)$row['n'];
        }
        $objectives = $this->rows('SELECT * FROM online_lesson_objectives WHERE lesson_id = ?', [$lessonId]);
        $links = $objectives === [] ? [] : $this->rows('
            SELECT l.objective_id FROM online_lesson_objective_links l
            JOIN online_lesson_objectives o ON o.id = l.objective_id
            WHERE o.lesson_id = ?
        ', [$lessonId]);
        $archived = [];
        $superseded = [];
        foreach ($this->rows('
            SELECT r.id, r.archived, r.superseded_by FROM online_lesson_items i
            JOIN online_lesson_resources r ON r.id = i.resource_id
            WHERE i.lesson_id = ?
        ', [$lessonId]) as $r) {
            if ((int)$r['archived'] === 1) {
                $archived[] = (int)$r['id'];
            }
            if ((int)($r['superseded_by'] ?? 0) > 0) {
                $superseded[] = (int)$r['id'];
            }
        }
        return self::qualityIssues([
            'lesson' => $lesson,
            'items' => $lessons->items($lessonId),
            'questions' => $questions,
            'sections' => (new LessonAuthoringService($this->pdo, $lessons))->sections($lessonId),
            'objectives' => $objectives,
            'links' => $links,
            'activities' => $activities,
            'archived_resource_ids' => $archived,
            'superseded_resource_ids' => $superseded,
            'criteria_counts' => $criteria,
            'prerequisites' => (new LessonAccessService($this->pdo))->prerequisites($lessonId),
        ]);
    }

    /**
     * Factual insights from student activity. Each insight includes the numbers it is based on.
     *
     * @return list<array{kind:string,message:string,item_id:int}>
     */
    public function insights(int $lessonId): array
    {
        $items = $this->rows('SELECT id, title, item_type, estimated_minutes, activity_id FROM online_lesson_items WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC', [$lessonId]);
        if ($items === []) {
            return [];
        }
        $states = [];
        foreach ($this->rows("
            SELECT s.item_id,
                   COUNT(*) AS opened,
                   SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                   AVG(CASE WHEN s.active_seconds > 0 THEN s.active_seconds END) AS avg_seconds
            FROM online_lesson_item_state s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE i.lesson_id = ?
            GROUP BY s.item_id
        ", [$lessonId]) as $row) {
            $states[(int)$row['item_id']] = $row;
        }
        $stops = [];
        foreach ($this->rows('
            SELECT current_item_id, COUNT(*) AS n FROM online_lesson_progress
            WHERE lesson_id = ? AND completed_at IS NULL AND current_item_id IS NOT NULL
            GROUP BY current_item_id
        ', [$lessonId]) as $row) {
            $stops[(int)$row['current_item_id']] = (int)$row['n'];
        }
        $questionStats = $this->rows('
            SELECT q.id, q.prompt, a2.id AS activity_id,
                   COUNT(an.id) AS answered,
                   SUM(CASE WHEN an.is_correct = 1 THEN 1 ELSE 0 END) AS correct
            FROM online_lesson_questions q
            JOIN online_lesson_activities a2 ON a2.id = q.activity_id
            JOIN online_lesson_answers an ON an.question_id = q.id
            WHERE a2.lesson_id = ? AND an.is_correct IS NOT NULL
            GROUP BY q.id, q.prompt, a2.id
        ', [$lessonId]);
        return self::buildInsights($items, $states, $stops, $questionStats);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array<int,array<string,mixed>> $states
     * @param array<int,int> $stops
     * @param list<array<string,mixed>> $questionStats
     * @return list<array{kind:string,message:string,item_id:int}>
     */
    public static function buildInsights(array $items, array $states, array $stops, array $questionStats): array
    {
        $out = [];
        $itemByActivity = [];
        foreach ($items as $item) {
            $id = (int)$item['id'];
            $title = trim((string)$item['title']) ?: 'Untitled item';
            if ((int)($item['activity_id'] ?? 0) > 0) {
                $itemByActivity[(int)$item['activity_id']] = $id;
            }
            $state = $states[$id] ?? null;
            $opened = (int)($state['opened'] ?? 0);
            $completed = (int)($state['completed'] ?? 0);
            if ($opened >= self::MIN_STUDENTS && $completed < $opened) {
                $rate = (int)round(100 * ($opened - $completed) / $opened);
                if ($rate >= 30) {
                    $out[] = ['kind' => 'completion', 'message' => $rate . '% of students who opened “' . $title . '” did not complete it (' . ($opened - $completed) . ' of ' . $opened . ').', 'item_id' => $id];
                }
            }
            $estimated = (int)($item['estimated_minutes'] ?? 0);
            $avgSeconds = (float)($state['avg_seconds'] ?? 0);
            if ($estimated > 0 && $opened >= self::MIN_STUDENTS && $avgSeconds > 0) {
                $avgMinutes = (int)round($avgSeconds / 60);
                if ($avgMinutes >= $estimated * 2) {
                    $out[] = ['kind' => 'time', 'message' => 'Students spend about ' . $avgMinutes . ' minutes on “' . $title . '”; the estimate is ' . $estimated . ' minutes.', 'item_id' => $id];
                } elseif ($avgMinutes > 0 && $avgMinutes * 3 <= $estimated) {
                    $out[] = ['kind' => 'time', 'message' => 'Students spend about ' . $avgMinutes . ' minute' . ($avgMinutes === 1 ? '' : 's') . ' on “' . $title . '”; the estimate is ' . $estimated . ' minutes.', 'item_id' => $id];
                }
            }
            if (($stops[$id] ?? 0) >= self::MIN_STUDENTS) {
                $out[] = ['kind' => 'dropoff', 'message' => $stops[$id] . ' students who have not finished the lesson stopped at “' . $title . '”.', 'item_id' => $id];
            }
        }
        foreach ($questionStats as $q) {
            $answered = (int)$q['answered'];
            if ($answered < self::MIN_STUDENTS) {
                continue;
            }
            $correct = (int)$q['correct'];
            $rate = (int)round(100 * $correct / $answered);
            if ($rate <= 40) {
                $prompt = trim(preg_replace('/\s+/', ' ', (string)$q['prompt']) ?? '');
                $out[] = [
                    'kind' => 'question',
                    'message' => 'Only ' . $rate . '% of answers to “' . mb_strimwidth($prompt, 0, 70, '…') . '” were correct (' . $correct . ' of ' . $answered . ').',
                    'item_id' => $itemByActivity[(int)$q['activity_id']] ?? 0,
                ];
            }
        }
        return $out;
    }

    /**
     * Pool question usage, first vs latest attempt, resource use and submission rates for one class.
     *
     * @return array{attempts:list<array<string,mixed>>,pool:list<array<string,mixed>>,resources:list<array<string,mixed>>,submissions:list<array<string,mixed>>,enrolled:int}
     */
    public function advancedAnalytics(int $lessonId, int $classId): array
    {
        $students = array_map('intval', array_column($this->rows('SELECT DISTINCT student_id FROM student_enrollments WHERE class_id = ?', [$classId]), 'student_id'));
        $enrolled = array_fill_keys($students, true);
        $items = $this->rows('
            SELECT i.id, i.title, i.item_type, i.activity_id, a.activity_type, a.draw_count, a.due_at
            FROM online_lesson_items i
            LEFT JOIN online_lesson_activities a ON a.id = i.activity_id
            WHERE i.lesson_id = ?
            ORDER BY i.sort_order ASC, i.id ASC
        ', [$lessonId]);
        $attempts = array_values(array_filter($this->rows("
            SELECT at.id, at.student_id, at.item_id, at.attempt_no, at.score, at.max_score, at.question_ids_json
            FROM online_lesson_attempts at
            JOIN online_lesson_items i ON i.id = at.item_id
            WHERE i.lesson_id = ? AND at.status <> 'in_progress'
            ORDER BY at.student_id, at.item_id, at.attempt_no, at.id
        ", [$lessonId]), static fn (array $r): bool => isset($enrolled[(int)$r['student_id']])));

        $poolActivities = [];
        foreach ($items as $item) {
            if ((int)($item['draw_count'] ?? 0) > 0) {
                $poolActivities[(int)$item['activity_id']] = (int)$item['id'];
            }
        }
        $pool = [];
        if ($poolActivities !== []) {
            $ph = implode(',', array_fill(0, count($poolActivities), '?'));
            $questions = $this->rows("SELECT id, activity_id, prompt, difficulty FROM online_lesson_questions WHERE activity_id IN ($ph) AND (ai_generated IS NULL OR ai_generated = 0) ORDER BY activity_id, sort_order, id", array_keys($poolActivities));
            $poolItemIds = array_fill_keys(array_values($poolActivities), true);
            $poolAttempts = array_values(array_filter($attempts, static fn (array $a): bool => isset($poolItemIds[(int)$a['item_id']])));
            $answers = [];
            if ($poolAttempts !== []) {
                $aph = implode(',', array_fill(0, count($poolAttempts), '?'));
                $answers = $this->rows("SELECT attempt_id, question_id, is_correct FROM online_lesson_answers WHERE attempt_id IN ($aph)", array_map(static fn (array $a): int => (int)$a['id'], $poolAttempts));
            }
            $pool = self::poolUsage($questions, $poolAttempts, $answers);
        }

        $stateRows = $this->rows('
            SELECT s.item_id, s.student_id, s.status, s.open_count
            FROM online_lesson_item_state s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE i.lesson_id = ? AND i.item_type IN (\'resource\', \'external_link\', \'text\')
        ', [$lessonId]);
        $stateRows = array_values(array_filter($stateRows, static fn (array $r): bool => isset($enrolled[(int)$r['student_id']])));
        $submissionRows = array_values(array_filter($this->rows('
            SELECT s.item_id, s.student_id, s.status, s.submitted_at
            FROM online_lesson_submissions s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE i.lesson_id = ?
        ', [$lessonId]), static fn (array $r): bool => isset($enrolled[(int)$r['student_id']])));

        return [
            'attempts' => self::attemptComparison($items, $attempts),
            'pool' => $pool,
            'resources' => self::resourceUsage($items, $stateRows, count($students)),
            'submissions' => self::submissionRates($items, $submissionRows, count($students)),
            'enrolled' => count($students),
        ];
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $attempts ordered by student, item, attempt_no
     * @return list<array<string,mixed>>
     */
    public static function attemptComparison(array $items, array $attempts): array
    {
        $byItem = [];
        foreach ($attempts as $a) {
            $max = (float)($a['max_score'] ?? 0);
            if ($max <= 0 || $a['score'] === null) {
                continue;
            }
            $byItem[(int)$a['item_id']][(int)$a['student_id']][] = 100 * (float)$a['score'] / $max;
        }
        $out = [];
        foreach ($items as $item) {
            $students = $byItem[(int)$item['id']] ?? [];
            if ($students === []) {
                continue;
            }
            $first = $latest = $best = [];
            $count = 0;
            $retried = 0;
            foreach ($students as $percents) {
                $first[] = $percents[0];
                $latest[] = $percents[count($percents) - 1];
                $best[] = max($percents);
                $count += count($percents);
                if (count($percents) > 1) {
                    $retried++;
                }
            }
            $n = count($students);
            $out[] = [
                'item_id' => (int)$item['id'],
                'title' => (string)$item['title'],
                'students' => $n,
                'retried' => $retried,
                'avg_attempts' => round($count / $n, 1),
                'first' => (int)round(array_sum($first) / $n),
                'latest' => (int)round(array_sum($latest) / $n),
                'best' => (int)round(array_sum($best) / $n),
            ];
        }
        return $out;
    }

    /**
     * How often each pool question was drawn and how often it was answered correctly.
     *
     * @param list<array<string,mixed>> $questions
     * @param list<array<string,mixed>> $attempts with question_ids_json
     * @param list<array<string,mixed>> $answers
     * @return list<array<string,mixed>>
     */
    public static function poolUsage(array $questions, array $attempts, array $answers): array
    {
        $shown = [];
        foreach ($attempts as $a) {
            $ids = json_decode((string)($a['question_ids_json'] ?? ''), true);
            if (!is_array($ids)) {
                continue;
            }
            foreach (array_unique(array_map('intval', $ids)) as $qid) {
                $shown[$qid] = ($shown[$qid] ?? 0) + 1;
            }
        }
        $answered = [];
        $correct = [];
        foreach ($answers as $an) {
            if ($an['is_correct'] === null) {
                continue;
            }
            $qid = (int)$an['question_id'];
            $answered[$qid] = ($answered[$qid] ?? 0) + 1;
            if ((int)$an['is_correct'] === 1) {
                $correct[$qid] = ($correct[$qid] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $out[] = [
                'question_id' => $qid,
                'activity_id' => (int)$q['activity_id'],
                'prompt' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags((string)$q['prompt'])) ?? ''), 0, 90, '…'),
                'difficulty' => (string)($q['difficulty'] ?? ''),
                'shown' => $shown[$qid] ?? 0,
                'answered' => $answered[$qid] ?? 0,
                'correct_percent' => ($answered[$qid] ?? 0) > 0 ? (int)round(100 * ($correct[$qid] ?? 0) / $answered[$qid]) : null,
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $states
     * @return list<array<string,mixed>>
     */
    public static function resourceUsage(array $items, array $states, int $enrolled): array
    {
        $byItem = [];
        foreach ($states as $s) {
            $id = (int)$s['item_id'];
            $byItem[$id]['opened'] = ($byItem[$id]['opened'] ?? 0) + ((int)($s['open_count'] ?? 0) > 0 || $s['status'] === 'completed' ? 1 : 0);
            $byItem[$id]['opens'] = ($byItem[$id]['opens'] ?? 0) + (int)($s['open_count'] ?? 0);
            $byItem[$id]['completed'] = ($byItem[$id]['completed'] ?? 0) + ($s['status'] === 'completed' ? 1 : 0);
        }
        $out = [];
        foreach ($items as $item) {
            if (!in_array((string)$item['item_type'], ['resource', 'external_link', 'text'], true)) {
                continue;
            }
            $row = $byItem[(int)$item['id']] ?? [];
            $opened = (int)($row['opened'] ?? 0);
            $out[] = [
                'item_id' => (int)$item['id'],
                'title' => (string)$item['title'],
                'type' => (string)$item['item_type'],
                'opened' => $opened,
                'opens' => (int)($row['opens'] ?? 0),
                'completed' => (int)($row['completed'] ?? 0),
                'enrolled' => $enrolled,
                'percent' => $enrolled > 0 ? (int)round(100 * $opened / $enrolled) : 0,
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $submissions
     * @return list<array<string,mixed>>
     */
    public static function submissionRates(array $items, array $submissions, int $enrolled): array
    {
        $byItem = [];
        foreach ($submissions as $s) {
            $byItem[(int)$s['item_id']][] = $s;
        }
        $out = [];
        foreach ($items as $item) {
            if (!in_array((string)($item['activity_type'] ?? ''), ['assignment', 'homework'], true)) {
                continue;
            }
            $rows = $byItem[(int)$item['id']] ?? [];
            $due = trim((string)($item['due_at'] ?? ''));
            $dueTs = $due !== '' ? strtotime($due) : false;
            $late = 0;
            $marked = 0;
            $submitted = 0;
            foreach ($rows as $s) {
                if (empty($s['submitted_at'])) {
                    continue;
                }
                $submitted++;
                if ($dueTs !== false && strtotime((string)$s['submitted_at']) > $dueTs) {
                    $late++;
                }
                if (in_array((string)$s['status'], ['marked', 'returned'], true)) {
                    $marked++;
                }
            }
            $out[] = [
                'item_id' => (int)$item['id'],
                'title' => (string)$item['title'],
                'submitted' => $submitted,
                'late' => $late,
                'marked' => $marked,
                'enrolled' => $enrolled,
                'percent' => $enrolled > 0 ? (int)round(100 * $submitted / $enrolled) : 0,
                'due_at' => $due,
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
