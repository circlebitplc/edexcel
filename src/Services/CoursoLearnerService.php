<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CoursoLearnerService
{
    public const STYLES = ['coach', 'tutor', 'concise', 'encouraging'];
    public const DIFFICULTIES = ['adaptive', 'foundation', 'core', 'stretch'];
    public const DENSITIES = ['comfortable', 'compact'];

    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_courso_schema')) {
            ensure_courso_schema($this->pdo);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function profile(int $studentId): array
    {
        $defaults = [
            'student_id' => $studentId,
            'goals' => '',
            'interests' => '',
            'ai_style' => 'coach',
            'difficulty' => 'adaptive',
            'notify_study' => 1,
            'notify_streak' => 1,
            'notify_community' => 1,
            'study_days' => '1,2,3,4,5',
            'study_hour' => 19,
            'density' => 'comfortable',
        ];
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM courso_learner_profiles WHERE student_id = ? LIMIT 1');
            $stmt->execute([$studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $defaults;
            }
            return array_merge($defaults, $row);
        } catch (Throwable $e) {
            return $defaults;
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function saveProfile(int $studentId, array $input): array
    {
        $style = (string)($input['ai_style'] ?? 'coach');
        if (!in_array($style, self::STYLES, true)) {
            $style = 'coach';
        }
        $difficulty = (string)($input['difficulty'] ?? 'adaptive');
        if (!in_array($difficulty, self::DIFFICULTIES, true)) {
            $difficulty = 'adaptive';
        }
        $density = (string)($input['density'] ?? 'comfortable');
        if (!in_array($density, self::DENSITIES, true)) {
            $density = 'comfortable';
        }
        $hour = (int)($input['study_hour'] ?? 19);
        $hour = max(6, min(22, $hour));
        $days = $this->normalizeStudyDays((string)($input['study_days'] ?? '1,2,3,4,5'));
        $goals = mb_substr(trim((string)($input['goals'] ?? '')), 0, 800);
        $interests = mb_substr(trim((string)($input['interests'] ?? '')), 0, 500);

        $stmt = $this->pdo->prepare("
            INSERT INTO courso_learner_profiles
                (student_id, goals, interests, ai_style, difficulty, notify_study, notify_streak,
                 notify_community, study_days, study_hour, density)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                goals = VALUES(goals),
                interests = VALUES(interests),
                ai_style = VALUES(ai_style),
                difficulty = VALUES(difficulty),
                notify_study = VALUES(notify_study),
                notify_streak = VALUES(notify_streak),
                notify_community = VALUES(notify_community),
                study_days = VALUES(study_days),
                study_hour = VALUES(study_hour),
                density = VALUES(density)
        ");
        $stmt->execute([
            $studentId,
            $goals,
            $interests,
            $style,
            $difficulty,
            !empty($input['notify_study']) ? 1 : 0,
            !empty($input['notify_streak']) ? 1 : 0,
            !empty($input['notify_community']) ? 1 : 0,
            $days,
            $hour,
            $density,
        ]);

        if ($goals !== '') {
            $this->remember($studentId, 'goal', $goals, 'settings');
        }

        return $this->profile($studentId);
    }

    public function remember(int $studentId, string $key, string $value, string $source = 'chat'): void
    {
        $key = mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower($key)) ?? '', 0, 80);
        $value = trim($value);
        if ($key === '' || $value === '') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO courso_memory (student_id, fact_key, fact_value, source)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE fact_value = VALUES(fact_value), source = VALUES(source)
            ");
            $stmt->execute([$studentId, $key, mb_substr($value, 0, 500), $source]);
        } catch (Throwable $e) {
            error_log('Courso memory: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array{key:string,value:string}>
     */
    public function memory(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT fact_key, fact_value FROM courso_memory WHERE student_id = ? ORDER BY updated_at DESC');
            $stmt->execute([$studentId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[] = [
                    'key' => (string)$row['fact_key'],
                    'value' => (string)$row['fact_value'],
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    public static function extractFactsFromMessage(string $message): array
    {
        $facts = [];
        $text = trim($message);
        if ($text === '') {
            return $facts;
        }
        if (preg_match('/\b(?:my goal is|i want to|i\'m aiming for|aiming for|i hope to)\s+(.{4,120})/iu', $text, $m)) {
            $facts['goal'] = trim($m[1], " \t\n\r.,!");
        }
        if (preg_match('/\b(?:i(?:\'m| am)? (?:weak|struggling|stuck|bad) (?:at|in|with)|need help with|improve my)\s+(.{3,80})/iu', $text, $m)) {
            $facts['weakness'] = trim($m[1], " \t\n\r.,!");
        }
        if (preg_match('/\b(?:i(?:\'m| am)? (?:strong|good|confident) (?:at|in|with))\s+(.{3,80})/iu', $text, $m)) {
            $facts['strength'] = trim($m[1], " \t\n\r.,!");
        }
        if (preg_match('/\b(?:interested in|i like|i enjoy)\s+(.{3,80})/iu', $text, $m)) {
            $facts['interest'] = trim($m[1], " \t\n\r.,!");
        }
        return $facts;
    }

    public function logActivity(int $studentId, string $kind, ?string $refType = null, ?int $refId = null, bool $oncePerDay = false): void
    {
        if ($studentId < 1 || $kind === '') {
            return;
        }
        try {
            if ($oncePerDay) {
                $check = $this->pdo->prepare("
                    SELECT id FROM courso_activity
                    WHERE student_id = ? AND kind = ? AND DATE(created_at) = CURDATE()
                    LIMIT 1
                ");
                $check->execute([$studentId, $kind]);
                if ($check->fetchColumn()) {
                    return;
                }
            }
            $stmt = $this->pdo->prepare("
                INSERT INTO courso_activity (student_id, kind, ref_type, ref_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$studentId, $kind, $refType, $refId]);
        } catch (Throwable $e) {
            error_log('Courso activity: ' . $e->getMessage());
        }
    }

    /**
     * Consecutive study days ending today or yesterday.
     *
     * @param list<string> $ymdDates
     */
    public static function streakFromDates(array $ymdDates, string $today): int
    {
        $set = [];
        foreach ($ymdDates as $d) {
            $d = substr((string)$d, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $set[$d] = true;
            }
        }
        if ($set === []) {
            return 0;
        }
        $cursor = $today;
        if (!isset($set[$cursor])) {
            $yesterday = date('Y-m-d', strtotime($today . ' -1 day') ?: time());
            if (!isset($set[$yesterday])) {
                return 0;
            }
            $cursor = $yesterday;
        }
        $streak = 0;
        while (isset($set[$cursor])) {
            $streak++;
            $cursor = date('Y-m-d', strtotime($cursor . ' -1 day') ?: time());
        }
        return $streak;
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(int $studentId): array
    {
        $profile = $this->profile($studentId);
        $classes = $this->enrolledClasses($studentId);
        $scores = $this->subjectScores($studentId);
        $attendance = $this->attendanceBySubject($studentId);
        $quizAvg = $this->recentQuizAverage($studentId);
        $strengths = $this->deriveStrengths($scores, $profile, $this->memory($studentId));
        $weaknesses = $this->deriveWeaknesses($scores, $profile, $this->memory($studentId));
        $dates = $this->activityDates($studentId);
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
        $streak = self::streakFromDates($dates, $today);
        $completed = $this->completedLessons($studentId);
        $watched = $this->recordingsWatched($studentId);
        $homework = $this->homeworkDue($studentId);
        $nextExam = $this->nextExam($studentId);
        $nextClass = $this->nextClass($studentId);
        $unwatched = $this->unwatchedRecordings($studentId);
        $joinIdeas = $this->recommendedClasses($studentId, (string)$profile['interests'], $weaknesses);
        $difficulty = $this->resolvedDifficulty((string)$profile['difficulty'], $scores, $quizAvg);

        $nextSteps = $this->nextSteps([
            'homework' => $homework,
            'next_exam' => $nextExam,
            'weaknesses' => $weaknesses,
            'unwatched' => $unwatched,
            'next_class' => $nextClass,
            'join' => $joinIdeas,
            'streak' => $streak,
            'difficulty' => $difficulty,
        ]);

        return [
            'student_id' => $studentId,
            'profile' => $profile,
            'classes' => $classes,
            'subject_scores' => $scores,
            'attendance' => $attendance,
            'quiz_average' => $quizAvg,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'streak' => $streak,
            'completed_lessons' => $completed,
            'recordings_watched' => $watched,
            'quizzes_done' => $this->quizzesDone($studentId),
            'homework' => $homework,
            'next_exam' => $nextExam,
            'next_class' => $nextClass,
            'unwatched' => $unwatched,
            'join_ideas' => $joinIdeas,
            'next_steps' => $nextSteps,
            'difficulty' => $difficulty,
            'memory' => $this->memory($studentId),
            'ai_ready' => (new WhatsAppAiService($this->pdo))->enabled(),
        ];
    }

    public function learnerContextText(int $studentId): string
    {
        $s = $this->snapshot($studentId);
        $p = $s['profile'];
        $lines = [
            'LEARNER SNAPSHOT (use this to stay a personal learning assistant, not a one-off chatbot):',
            'Name context: student id ' . $studentId,
            'Goals: ' . ((string)($p['goals'] ?? '') !== '' ? $p['goals'] : 'not set yet'),
            'Interests: ' . ((string)($p['interests'] ?? '') !== '' ? $p['interests'] : 'not set yet'),
            'Preferred AI style: ' . $p['ai_style'],
            'Practice difficulty: ' . $s['difficulty'],
            'Learning streak: ' . (int)$s['streak'] . ' days',
            'Lessons attended (30 days): ' . (int)$s['completed_lessons'],
            'Recordings watched: ' . (int)$s['recordings_watched'],
            'Practice quizzes completed: ' . (int)$s['quizzes_done'],
        ];
        $classNames = [];
        foreach ($s['classes'] as $c) {
            $classNames[] = (string)($c['class_name'] ?? $c['name'] ?? '');
        }
        $lines[] = 'Enrolled classes: ' . ($classNames !== [] ? implode(', ', $classNames) : 'none');
        $lines[] = 'Strengths: ' . ($s['strengths'] !== [] ? implode('; ', $s['strengths']) : 'not enough marks yet');
        $lines[] = 'Focus areas: ' . ($s['weaknesses'] !== [] ? implode('; ', $s['weaknesses']) : 'not enough marks yet');
        if (!empty($s['next_exam'])) {
            $ex = $s['next_exam'];
            $lines[] = 'Next exam: ' . ($ex['title'] ?? '') . ' on ' . ($ex['when'] ?? '');
        }
        if (!empty($s['next_class'])) {
            $nc = $s['next_class'];
            $lines[] = 'Next lesson: ' . ($nc['subject_name'] ?? '') . ' ' . ($nc['when'] ?? '');
        }
        if ($s['homework'] !== []) {
            $hw = [];
            foreach (array_slice($s['homework'], 0, 4) as $row) {
                $hw[] = ($row['title'] ?? 'Task') . (!empty($row['due_date']) ? ' due ' . $row['due_date'] : '');
            }
            $lines[] = 'Homework: ' . implode('; ', $hw);
        }
        if ($s['next_steps'] !== []) {
            $steps = [];
            foreach (array_slice($s['next_steps'], 0, 4) as $step) {
                $steps[] = $step['title'];
            }
            $lines[] = 'Recommended next steps: ' . implode(' | ', $steps);
        }
        foreach ($s['memory'] as $fact) {
            $lines[] = 'Remembered ' . $fact['key'] . ': ' . $fact['value'];
        }
        foreach ($s['subject_scores'] as $row) {
            $lines[] = 'Marks in ' . $row['subject'] . ': ' . $row['percent'] . '% (' . $row['count'] . ' records)';
        }

        $text = implode("\n", $lines);
        if (mb_strlen($text) > 2500) {
            $text = mb_substr($text, 0, 2490) . '…';
        }
        return $text;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function search(int $studentId, string $query): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . $this->likeEscape($q) . '%';
        $hits = [];
        $classIds = $this->enrolledClassIds($studentId);
        $weak = strtolower(implode(' ', $this->deriveWeaknesses($this->subjectScores($studentId), $this->profile($studentId), [])));

        try {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.name, c.description
                FROM student_classes c
                WHERE c.deleted_at IS NULL AND (c.name LIKE ? OR c.description LIKE ?)
                ORDER BY c.name
                LIMIT 8
            ");
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $enrolled = in_array((int)$row['id'], $classIds, true);
                $hits[] = [
                    'type' => 'class',
                    'title' => (string)$row['name'],
                    'blurb' => (string)($row['description'] ?? ''),
                    'href' => '/student/dashboard.php?tab=join&q=' . rawurlencode($q),
                    'score' => self::searchScore($q, (string)$row['name'], $enrolled, $weak),
                ];
            }
        } catch (Throwable $e) {
        }

        if ($classIds !== []) {
            $in = implode(',', array_fill(0, count($classIds), '?'));
            try {
                $stmt = $this->pdo->prepare("
                    SELECT title, description, file_url FROM student_materials
                    WHERE (title LIKE ? OR description LIKE ?) AND (class_id IN ($in) OR class_id IS NULL)
                    ORDER BY created_at DESC LIMIT 8
                ");
                $stmt->execute(array_merge([$like, $like], $classIds));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $hits[] = [
                        'type' => 'material',
                        'title' => (string)$row['title'],
                        'blurb' => (string)($row['description'] ?? ''),
                        'href' => (string)($row['file_url'] ?: '/student/dashboard.php?tab=services'),
                        'score' => self::searchScore($q, (string)$row['title'], true, $weak),
                    ];
                }
            } catch (Throwable $e) {
            }
            try {
                $stmt = $this->pdo->prepare("
                    SELECT title, description, due_date, link FROM student_homework
                    WHERE (title LIKE ? OR description LIKE ?) AND (class_id IN ($in) OR class_id IS NULL)
                    ORDER BY due_date IS NULL, due_date ASC LIMIT 8
                ");
                $stmt->execute(array_merge([$like, $like], $classIds));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $hits[] = [
                        'type' => 'homework',
                        'title' => (string)$row['title'],
                        'blurb' => trim((string)($row['description'] ?? '') . (!empty($row['due_date']) ? ' Due ' . $row['due_date'] : '')),
                        'href' => (string)($row['link'] ?: '/student/dashboard.php?tab=services'),
                        'score' => self::searchScore($q, (string)$row['title'], true, $weak) + 4,
                    ];
                }
            } catch (Throwable $e) {
            }
            try {
                $stmt = $this->pdo->prepare("
                    SELECT cr.id, cr.title, s.name AS subject_name, tt.date
                    FROM class_recordings cr
                    JOIN timetable tt ON tt.id = cr.timetable_id AND tt.deleted_at IS NULL
                    JOIN subjects s ON s.id = tt.subject_id
                    WHERE cr.deleted_at IS NULL AND cr.status = 'ready'
                      AND tt.class_id IN ($in)
                      AND (cr.title LIKE ? OR s.name LIKE ?)
                    ORDER BY tt.date DESC LIMIT 8
                ");
                $stmt->execute(array_merge($classIds, [$like, $like]));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $hits[] = [
                        'type' => 'recording',
                        'title' => (string)($row['title'] ?: $row['subject_name']),
                        'blurb' => (string)($row['subject_name'] ?? '') . ' · ' . (string)($row['date'] ?? ''),
                        'href' => '/student/recording.php?id=' . (int)$row['id'],
                        'score' => self::searchScore($q, (string)$row['title'] . ' ' . (string)$row['subject_name'], true, $weak),
                    ];
                }
            } catch (Throwable $e) {
            }
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT name FROM teachers
                WHERE deleted_at IS NULL AND name LIKE ?
                ORDER BY name LIMIT 6
            ");
            $stmt->execute([$like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $hits[] = [
                    'type' => 'teacher',
                    'title' => (string)$row['name'],
                    'blurb' => 'Teacher',
                    'href' => '/student/dashboard.php?tab=teachers',
                    'score' => self::searchScore($q, (string)$row['name'], false, $weak),
                ];
            }
        } catch (Throwable $e) {
        }

        usort($hits, static fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcasecmp((string)$a['title'], (string)$b['title']));
        return array_slice($hits, 0, 20);
    }

    public static function searchScore(string $query, string $title, bool $enrolledOrOwned, string $weakBlob): int
    {
        $q = mb_strtolower(trim($query));
        $t = mb_strtolower($title);
        $score = 0;
        if ($t === $q) {
            $score += 40;
        } elseif (str_starts_with($t, $q)) {
            $score += 24;
        } elseif (str_contains($t, $q)) {
            $score += 14;
        } else {
            foreach (preg_split('/\s+/', $q) ?: [] as $word) {
                if ($word !== '' && str_contains($t, $word)) {
                    $score += 6;
                }
            }
        }
        if ($enrolledOrOwned) {
            $score += 8;
        }
        if ($weakBlob !== '') {
            foreach (preg_split('/\s+/', $q) ?: [] as $word) {
                if (mb_strlen($word) > 3 && str_contains($weakBlob, $word)) {
                    $score += 10;
                    break;
                }
            }
        }
        return $score;
    }

    /**
     * @param array<string,mixed> $bits
     * @return list<array{kind:string,title:string,why:string,href:string,cta:string}>
     */
    public function nextSteps(array $bits): array
    {
        $steps = [];
        foreach (array_slice($bits['homework'] ?? [], 0, 2) as $hw) {
            $steps[] = [
                'kind' => 'homework',
                'title' => 'Finish: ' . (string)($hw['title'] ?? 'Homework'),
                'why' => !empty($hw['due_date']) ? 'Due ' . $hw['due_date'] : 'Set by your teacher',
                'href' => '/student/dashboard.php?tab=services',
                'cta' => 'Open Homework & notes',
            ];
        }
        if (!empty($bits['next_exam'])) {
            $ex = $bits['next_exam'];
            $steps[] = [
                'kind' => 'exam',
                'title' => 'Revise for ' . (string)($ex['title'] ?? 'your exam'),
                'why' => (string)($ex['when'] ?? 'Coming up'),
                'href' => '/student/dashboard.php?tab=courso#practice',
                'cta' => 'Start a practice quiz',
            ];
        }
        foreach (array_slice($bits['weaknesses'] ?? [], 0, 2) as $w) {
            $steps[] = [
                'kind' => 'practice',
                'title' => 'Practise ' . $w,
                'why' => 'Marks or recent quizzes show this as a focus area',
                'href' => '/student/dashboard.php?tab=courso#practice',
                'cta' => 'Adaptive practice',
            ];
        }
        foreach (array_slice($bits['unwatched'] ?? [], 0, 1) as $rec) {
            $steps[] = [
                'kind' => 'recording',
                'title' => 'Rewatch ' . (string)($rec['title'] ?? 'a lesson'),
                'why' => 'Ready to watch — useful before the next class',
                'href' => '/student/recording.php?id=' . (int)($rec['id'] ?? 0),
                'cta' => 'Open recording',
            ];
        }
        if (!empty($bits['next_class'])) {
            $nc = $bits['next_class'];
            $steps[] = [
                'kind' => 'class',
                'title' => 'Get ready for ' . (string)($nc['subject_name'] ?? 'class'),
                'why' => (string)($nc['when'] ?? ''),
                'href' => '/student/dashboard.php?tab=timetable',
                'cta' => 'Open timetable',
            ];
        }
        foreach (array_slice($bits['join'] ?? [], 0, 1) as $idea) {
            $steps[] = [
                'kind' => 'join',
                'title' => 'Consider joining ' . (string)($idea['name'] ?? 'a class'),
                'why' => (string)($idea['why'] ?? 'Matches your interests or focus areas'),
                'href' => '/student/dashboard.php?tab=join',
                'cta' => 'Browse classes',
            ];
        }
        if ((int)($bits['streak'] ?? 0) > 0) {
            $steps[] = [
                'kind' => 'streak',
                'title' => 'Keep your ' . (int)$bits['streak'] . '-day learning streak',
                'why' => 'A short quiz or recap today is enough',
                'href' => '/student/dashboard.php?tab=courso#practice',
                'cta' => 'Do 5 questions',
            ];
        }

        $seen = [];
        $out = [];
        foreach ($steps as $step) {
            $k = $step['kind'] . ':' . $step['title'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $step;
            if (count($out) >= 6) {
                break;
            }
        }
        if ($out === []) {
            $out[] = [
                'kind' => 'start',
                'title' => 'Tell the AI your exam goal',
                'why' => 'Recommendations get sharper once it knows what you are aiming for',
                'href' => '/student/dashboard.php?tab=courso#assistant',
                'cta' => 'Talk with AI',
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $scores
     * @param array<string,mixed> $profile
     * @param list<array{key:string,value:string}> $memory
     * @return list<string>
     */
    public function deriveStrengths(array $scores, array $profile, array $memory): array
    {
        $out = [];
        foreach ($scores as $row) {
            if ((int)$row['percent'] >= 70) {
                $out[] = $row['subject'] . ' (' . $row['percent'] . '%)';
            }
        }
        foreach ($memory as $fact) {
            if ($fact['key'] === 'strength' && $fact['value'] !== '') {
                $out[] = $fact['value'];
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param list<array<string,mixed>> $scores
     * @param array<string,mixed> $profile
     * @param list<array{key:string,value:string}> $memory
     * @return list<string>
     */
    public function deriveWeaknesses(array $scores, array $profile, array $memory): array
    {
        $out = [];
        foreach ($scores as $row) {
            if ((int)$row['percent'] < 55) {
                $out[] = $row['subject'] . ' (' . $row['percent'] . '%)';
            }
        }
        foreach ($memory as $fact) {
            if ($fact['key'] === 'weakness' && $fact['value'] !== '') {
                $out[] = $fact['value'];
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param list<array<string,mixed>> $scores
     */
    public function resolvedDifficulty(string $pref, array $scores, ?float $quizAvg): string
    {
        if (in_array($pref, ['foundation', 'core', 'stretch'], true)) {
            return $pref;
        }
        $vals = [];
        foreach ($scores as $row) {
            $vals[] = (float)$row['percent'];
        }
        if ($quizAvg !== null) {
            $vals[] = $quizAvg;
        }
        if ($vals === []) {
            return 'core';
        }
        $avg = array_sum($vals) / count($vals);
        if ($avg < 50) {
            return 'foundation';
        }
        if ($avg >= 78) {
            return 'stretch';
        }
        return 'core';
    }

    public function enrolledClassIds(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT class_id FROM student_enrollments WHERE student_id = ?');
            $stmt->execute([$studentId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function enrolledClasses(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.name AS class_name
                FROM student_enrollments se
                JOIN student_classes c ON c.id = se.class_id AND c.deleted_at IS NULL
                WHERE se.student_id = ?
                ORDER BY c.name
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{subject:string,subject_id:int,percent:int,count:int}>
     */
    private function subjectScores(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(s.name, p.metric) AS subject,
                       COALESCE(p.subject_id, 0) AS subject_id,
                       AVG(CASE WHEN p.max_score > 0 THEN (p.score / p.max_score) * 100 ELSE p.score END) AS pct,
                       COUNT(*) AS n
                FROM student_progress p
                LEFT JOIN subjects s ON s.id = p.subject_id
                WHERE p.student_id = ?
                  AND p.published = 1
                GROUP BY COALESCE(s.name, p.metric), COALESCE(p.subject_id, 0)
                ORDER BY pct ASC
            ");
            $stmt->execute([$studentId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[] = [
                    'subject' => (string)$row['subject'],
                    'subject_id' => (int)$row['subject_id'],
                    'percent' => (int)round((float)$row['pct']),
                    'count' => (int)$row['n'],
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function attendanceBySubject(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.name AS subject,
                       SUM(sa.status IN ('present','late')) AS present,
                       COUNT(*) AS total
                FROM student_attendance sa
                JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
                JOIN subjects s ON s.id = tt.subject_id
                WHERE sa.student_id = ? AND tt.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY s.name
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function recentQuizAverage(int $studentId): ?float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT AVG((score / NULLIF(max_score,0)) * 100)
                FROM (
                    SELECT score, max_score
                    FROM courso_quizzes
                    WHERE student_id = ? AND status = 'completed' AND score IS NOT NULL
                    ORDER BY completed_at DESC
                    LIMIT 8
                ) recent
            ");
            $stmt->execute([$studentId]);
            $v = $stmt->fetchColumn();
            return $v === false || $v === null ? null : (float)$v;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function activityDates(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT DATE(created_at) AS d
                FROM courso_activity
                WHERE student_id = ?
                ORDER BY d DESC
                LIMIT 60
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function completedLessons(int $studentId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM student_attendance sa
                JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
                WHERE sa.student_id = ?
                  AND sa.status IN ('present','late')
                  AND tt.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$studentId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function recordingsWatched(int $studentId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT recording_id)
                FROM recording_access_logs
                WHERE student_id = ? AND access_result = 'ACCESS_GRANTED'
            ");
            $stmt->execute([$studentId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function quizzesDone(int $studentId): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM courso_quizzes WHERE student_id = ? AND status = 'completed'");
            $stmt->execute([$studentId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function homeworkDue(int $studentId): array
    {
        $ids = $this->enrolledClassIds($studentId);
        if ($ids === []) {
            return [];
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                SELECT title, due_date, description, link
                FROM student_homework
                WHERE (due_date >= CURDATE() OR due_date IS NULL)
                  AND (class_id IN ($in) OR class_id IS NULL)
                ORDER BY due_date IS NULL, due_date ASC
                LIMIT 6
            ");
            $stmt->execute($ids);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function nextExam(int $studentId): ?array
    {
        try {
            $official = new OfficialExamService($this->pdo);
            $rows = $official->studentTimetable($studentId);
            foreach ($rows as $row) {
                if (empty($row['is_past'])) {
                    return [
                        'title' => trim((string)($row['subject'] ?? '') . ' ' . (string)($row['paper_label'] ?? '')),
                        'when' => (string)($row['date_label'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
        }
        $ids = $this->enrolledClassIds($studentId);
        if ($ids === []) {
            return null;
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                SELECT title, exam_date, exam_time
                FROM student_exams
                WHERE exam_date >= CURDATE() AND (class_id IN ($in) OR class_id IS NULL)
                ORDER BY exam_date ASC, exam_time ASC
                LIMIT 1
            ");
            $stmt->execute($ids);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return [
                'title' => (string)$row['title'],
                'when' => date('D d M', strtotime((string)$row['exam_date'])),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private function nextClass(int $studentId): ?array
    {
        $ids = $this->enrolledClassIds($studentId);
        if ($ids === []) {
            return null;
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                SELECT s.name AS subject_name, tt.date, tt.start_time
                FROM timetable tt
                JOIN subjects s ON s.id = tt.subject_id
                WHERE tt.deleted_at IS NULL AND tt.class_id IN ($in)
                  AND (tt.date > CURDATE() OR (tt.date = CURDATE() AND tt.start_time >= CURTIME()))
                ORDER BY tt.date ASC, tt.start_time ASC
                LIMIT 1
            ");
            $stmt->execute($ids);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return [
                'subject_name' => (string)$row['subject_name'],
                'when' => date('D d M g:i A', strtotime($row['date'] . ' ' . $row['start_time'])),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private function unwatchedRecordings(int $studentId): array
    {
        $ids = $this->enrolledClassIds($studentId);
        if ($ids === []) {
            return [];
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                SELECT cr.id, COALESCE(NULLIF(cr.title,''), s.name) AS title
                FROM class_recordings cr
                JOIN timetable tt ON tt.id = cr.timetable_id AND tt.deleted_at IS NULL
                JOIN subjects s ON s.id = tt.subject_id
                LEFT JOIN recording_access_logs l
                    ON l.recording_id = cr.id AND l.student_id = ? AND l.access_result = 'ACCESS_GRANTED'
                WHERE cr.deleted_at IS NULL AND cr.status = 'ready'
                  AND tt.class_id IN ($in)
                  AND l.id IS NULL
                ORDER BY tt.date DESC
                LIMIT 5
            ");
            $stmt->execute(array_merge([$studentId], $ids));
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<string> $weaknesses
     */
    private function recommendedClasses(int $studentId, string $interests, array $weaknesses): array
    {
        $blob = strtolower($interests . ' ' . implode(' ', $weaknesses));
        try {
            $enrolled = $this->pdo->prepare('SELECT class_id FROM student_enrollments WHERE student_id = ?');
            $enrolled->execute([$studentId]);
            $have = array_map('intval', $enrolled->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $stmt = $this->pdo->query("
                SELECT id, name, description FROM student_classes
                WHERE deleted_at IS NULL
                ORDER BY name
                LIMIT 40
            ");
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (in_array((int)$row['id'], $have, true)) {
                    continue;
                }
                $hay = strtolower((string)$row['name'] . ' ' . (string)($row['description'] ?? ''));
                $score = 0;
                foreach (preg_split('/[\s,]+/', $blob) ?: [] as $word) {
                    if (mb_strlen($word) > 3 && str_contains($hay, $word)) {
                        $score += 1;
                    }
                }
                if ($score < 1) {
                    continue;
                }
                $out[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'why' => 'Looks relevant to your interests or focus areas',
                    'score' => $score,
                ];
            }
            usort($out, static fn ($a, $b) => $b['score'] <=> $a['score']);
            return array_slice($out, 0, 3);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function normalizeStudyDays(string $raw): string
    {
        $parts = preg_split('/[,\s]+/', $raw) ?: [];
        $days = [];
        foreach ($parts as $p) {
            $n = (int)$p;
            if ($n >= 0 && $n <= 6) {
                $days[$n] = $n;
            }
        }
        if ($days === []) {
            $days = [1, 2, 3, 4, 5];
        }
        sort($days);
        return implode(',', $days);
    }

    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
