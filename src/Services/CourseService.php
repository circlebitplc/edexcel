<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Optional Course → Unit → Topic structure. Lessons still belong to their timetable class;
 * a lesson only points at a topic through online_lessons.topic_id.
 */
final class CourseService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function courses(bool $includeArchived = false): array
    {
        $sql = '
            SELECT c.*, s.name AS subject_name,
                   (SELECT COUNT(*) FROM lm_units u WHERE u.course_id = c.id) AS unit_count,
                   (SELECT COUNT(*) FROM lm_topics t JOIN lm_units u2 ON u2.id = t.unit_id WHERE u2.course_id = c.id) AS topic_count
            FROM lm_courses c
            LEFT JOIN subjects s ON s.id = c.subject_id
        ' . ($includeArchived ? '' : ' WHERE c.archived = 0 ') . '
            ORDER BY c.archived ASC, c.title ASC
            LIMIT 300';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function course(int $courseId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lm_courses WHERE id = ? LIMIT 1');
        $stmt->execute([$courseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function canEdit(array $course, int $userId, bool $isAdmin): bool
    {
        return $isAdmin || (int)($course['created_by'] ?? 0) === $userId;
    }

    public function saveCourse(int $courseId, string $title, int $subjectId, string $description, int $coverageMinPercent, int $userId, bool $isAdmin): int
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a course title.');
        }
        $coverageMinPercent = max(0, min(100, $coverageMinPercent));
        $description = mb_substr(trim($description), 0, 5000);
        if ($courseId > 0) {
            $course = $this->editable($courseId, $userId, $isAdmin);
            $this->pdo->prepare('UPDATE lm_courses SET title = ?, subject_id = ?, description = ?, coverage_min_percent = ? WHERE id = ?')
                ->execute([$title, $subjectId > 0 ? $subjectId : null, $description !== '' ? $description : null, $coverageMinPercent, (int)$course['id']]);
            return (int)$course['id'];
        }
        $this->pdo->prepare('
            INSERT INTO lm_courses (title, subject_id, description, coverage_min_percent, created_by, archived, created_at)
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ')->execute([$title, $subjectId > 0 ? $subjectId : null, $description !== '' ? $description : null, $coverageMinPercent, $userId > 0 ? $userId : null, date('Y-m-d H:i:s')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function setArchived(int $courseId, bool $archived, int $userId, bool $isAdmin): void
    {
        $course = $this->editable($courseId, $userId, $isAdmin);
        $this->pdo->prepare('UPDATE lm_courses SET archived = ? WHERE id = ?')->execute([$archived ? 1 : 0, (int)$course['id']]);
    }

    public function addUnit(int $courseId, string $title, int $userId, bool $isAdmin): int
    {
        $this->editable($courseId, $userId, $isAdmin);
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a unit title.');
        }
        $sort = (int)$this->scalar('SELECT COALESCE(MAX(sort_order), 0) FROM lm_units WHERE course_id = ?', [$courseId]) + 1;
        $this->pdo->prepare('INSERT INTO lm_units (course_id, title, sort_order) VALUES (?, ?, ?)')->execute([$courseId, $title, $sort]);
        return (int)$this->pdo->lastInsertId();
    }

    public function renameUnit(int $unitId, string $title, int $userId, bool $isAdmin): void
    {
        $unit = $this->unit($unitId);
        $this->editable((int)$unit['course_id'], $userId, $isAdmin);
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a unit title.');
        }
        $this->pdo->prepare('UPDATE lm_units SET title = ? WHERE id = ?')->execute([$title, $unitId]);
    }

    public function deleteUnit(int $unitId, int $userId, bool $isAdmin): void
    {
        $unit = $this->unit($unitId);
        $this->editable((int)$unit['course_id'], $userId, $isAdmin);
        foreach ($this->rows('SELECT id FROM lm_topics WHERE unit_id = ?', [$unitId]) as $topic) {
            $this->pdo->prepare('UPDATE online_lessons SET topic_id = NULL WHERE topic_id = ?')->execute([(int)$topic['id']]);
        }
        $this->pdo->prepare('DELETE FROM lm_topics WHERE unit_id = ?')->execute([$unitId]);
        $this->pdo->prepare('DELETE FROM lm_units WHERE id = ?')->execute([$unitId]);
    }

    public function saveTopic(int $topicId, int $unitId, string $title, string $objectives, int $plannedLessons, int $userId, bool $isAdmin): int
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a topic title.');
        }
        $plannedLessons = max(1, min(50, $plannedLessons));
        $objectives = mb_substr(trim($objectives), 0, 5000);
        if ($topicId > 0) {
            $topic = $this->topic($topicId);
            $unit = $this->unit((int)$topic['unit_id']);
            $this->editable((int)$unit['course_id'], $userId, $isAdmin);
            $this->pdo->prepare('UPDATE lm_topics SET title = ?, objectives = ?, planned_lessons = ? WHERE id = ?')
                ->execute([$title, $objectives !== '' ? $objectives : null, $plannedLessons, $topicId]);
            return $topicId;
        }
        $unit = $this->unit($unitId);
        $this->editable((int)$unit['course_id'], $userId, $isAdmin);
        $sort = (int)$this->scalar('SELECT COALESCE(MAX(sort_order), 0) FROM lm_topics WHERE unit_id = ?', [$unitId]) + 1;
        $this->pdo->prepare('INSERT INTO lm_topics (unit_id, title, objectives, planned_lessons, sort_order) VALUES (?, ?, ?, ?, ?)')
            ->execute([$unitId, $title, $objectives !== '' ? $objectives : null, $plannedLessons, $sort]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteTopic(int $topicId, int $userId, bool $isAdmin): void
    {
        $topic = $this->topic($topicId);
        $unit = $this->unit((int)$topic['unit_id']);
        $this->editable((int)$unit['course_id'], $userId, $isAdmin);
        $this->pdo->prepare('UPDATE online_lessons SET topic_id = NULL WHERE topic_id = ?')->execute([$topicId]);
        $this->pdo->prepare('DELETE FROM lm_topics WHERE id = ?')->execute([$topicId]);
    }

    /**
     * @return list<array<string,mixed>> units, each with 'topics'
     */
    public function tree(int $courseId): array
    {
        $units = $this->rows('SELECT * FROM lm_units WHERE course_id = ? ORDER BY sort_order ASC, id ASC', [$courseId]);
        $topics = $this->rows('
            SELECT t.* FROM lm_topics t JOIN lm_units u ON u.id = t.unit_id
            WHERE u.course_id = ? ORDER BY t.sort_order ASC, t.id ASC
        ', [$courseId]);
        $byUnit = [];
        foreach ($topics as $topic) {
            $byUnit[(int)$topic['unit_id']][] = $topic;
        }
        foreach ($units as &$unit) {
            $unit['topics'] = $byUnit[(int)$unit['id']] ?? [];
        }
        unset($unit);
        return $units;
    }

    /**
     * Flat topic list for a lesson's topic picker: "Course › Unit › Topic".
     *
     * @return list<array{id:int,label:string,course_id:int}>
     */
    public function topicOptions(int $subjectId = 0): array
    {
        $sql = '
            SELECT t.id, t.title AS topic_title, u.title AS unit_title, c.title AS course_title, c.id AS course_id, c.subject_id
            FROM lm_topics t
            JOIN lm_units u ON u.id = t.unit_id
            JOIN lm_courses c ON c.id = u.course_id
            WHERE c.archived = 0
            ORDER BY c.title ASC, u.sort_order ASC, t.sort_order ASC
            LIMIT 1000';
        $out = [];
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($subjectId > 0 && (int)($row['subject_id'] ?? 0) > 0 && (int)$row['subject_id'] !== $subjectId) {
                continue;
            }
            $out[] = [
                'id' => (int)$row['id'],
                'label' => $row['course_title'] . ' › ' . $row['unit_title'] . ' › ' . $row['topic_title'],
                'course_id' => (int)$row['course_id'],
            ];
        }
        return $out;
    }

    public function assignLesson(int $lessonId, int $topicId): void
    {
        if ($topicId > 0) {
            $this->topic($topicId);
        }
        $this->pdo->prepare('UPDATE online_lessons SET topic_id = ? WHERE id = ?')->execute([$topicId > 0 ? $topicId : null, $lessonId]);
    }

    /**
     * A topic is covered when enough of its lessons were delivered: released to students, the class date has passed,
     * and (when the course sets a minimum) the class's average completion reached that percentage.
     *
     * @param list<array{released:bool,date:string,avg_completion:int}> $lessons
     * @return array{status:string,qualifying:int,planned:int}
     */
    public static function topicStatus(array $lessons, int $plannedLessons, int $minPercent, string $today): array
    {
        $planned = max(1, $plannedLessons);
        $qualifying = 0;
        foreach ($lessons as $lesson) {
            if (empty($lesson['released']) || (string)$lesson['date'] > $today) {
                continue;
            }
            if ($minPercent > 0 && (int)$lesson['avg_completion'] < $minPercent) {
                continue;
            }
            $qualifying++;
        }
        $status = 'not_started';
        if ($qualifying >= $planned) {
            $status = 'covered';
        } elseif ($lessons !== []) {
            $status = 'in_progress';
        }
        return ['status' => $status, 'qualifying' => $qualifying, 'planned' => $planned];
    }

    /**
     * Syllabus coverage for a course, optionally limited to some classes.
     *
     * @param list<int>|null $classIds null = every class; [] = no class
     * @return array{units:list<array<string,mixed>>,covered:int,topics:int,percent:int}
     */
    public function coverage(int $courseId, ?array $classIds = null, ?string $today = null): array
    {
        $course = $this->course($courseId);
        if (!$course) {
            throw new RuntimeException('That course was not found.');
        }
        $today = $today ?? date('Y-m-d');
        $minPercent = (int)($course['coverage_min_percent'] ?? 0);
        $params = [$courseId];
        $classSql = '';
        if ($classIds !== null) {
            $classIds = array_values(array_filter(array_map('intval', $classIds)));
            $classSql = $classIds === [] ? ' AND 1 = 0' : ' AND tt.class_id IN (' . implode(',', array_fill(0, count($classIds), '?')) . ')';
            array_push($params, ...$classIds);
        }
        $lessons = $this->rows('
            SELECT ol.id, ol.title, ol.published, ol.publish_at, ol.topic_id, ol.timetable_id, tt.date, tt.class_id, c.name AS class_name
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            JOIN lm_topics t ON t.id = ol.topic_id
            JOIN lm_units u ON u.id = t.unit_id
            LEFT JOIN student_classes c ON c.id = tt.class_id
            WHERE u.course_id = ? AND tt.deleted_at IS NULL' . $classSql . '
            ORDER BY tt.date ASC
        ', $params);
        $avg = $this->averageCompletion($lessons);
        $byTopic = [];
        foreach ($lessons as $lesson) {
            $byTopic[(int)$lesson['topic_id']][] = [
                'id' => (int)$lesson['id'],
                'title' => (string)$lesson['title'],
                'timetable_id' => (int)$lesson['timetable_id'],
                'class_name' => (string)($lesson['class_name'] ?? ''),
                'date' => (string)$lesson['date'],
                'released' => OnlineLessonService::isReleased($lesson),
                'avg_completion' => $avg[(int)$lesson['id']] ?? 0,
            ];
        }
        $units = $this->tree($courseId);
        $covered = 0;
        $total = 0;
        foreach ($units as &$unit) {
            foreach ($unit['topics'] as &$topic) {
                $topicLessons = $byTopic[(int)$topic['id']] ?? [];
                $topic['lessons'] = $topicLessons;
                $topic['coverage'] = self::topicStatus($topicLessons, (int)($topic['planned_lessons'] ?? 1), $minPercent, $today);
                $total++;
                if ($topic['coverage']['status'] === 'covered') {
                    $covered++;
                }
            }
            unset($topic);
        }
        unset($unit);
        return [
            'units' => $units,
            'covered' => $covered,
            'topics' => $total,
            'percent' => OnlineLessonService::percentComplete($covered, $total),
        ];
    }

    /**
     * Average completion per lesson across the students enrolled in the lesson's class.
     *
     * @param list<array<string,mixed>> $lessons
     * @return array<int,int>
     */
    public function averageCompletion(array $lessons): array
    {
        if ($lessons === []) {
            return [];
        }
        $lessonIds = array_values(array_unique(array_map(static fn (array $l): int => (int)$l['id'], $lessons)));
        $classIds = array_values(array_unique(array_map(static fn (array $l): int => (int)$l['class_id'], $lessons)));
        $ph = static fn (array $v): string => implode(',', array_fill(0, count($v), '?'));
        $items = [];
        foreach ($this->rows('SELECT lesson_id, COUNT(*) AS n FROM online_lesson_items WHERE lesson_id IN (' . $ph($lessonIds) . ') GROUP BY lesson_id', $lessonIds) as $row) {
            $items[(int)$row['lesson_id']] = (int)$row['n'];
        }
        $done = [];
        foreach ($this->rows("
            SELECT i.lesson_id, COUNT(*) AS n
            FROM online_lesson_item_state s JOIN online_lesson_items i ON i.id = s.item_id
            WHERE s.status = 'completed' AND i.lesson_id IN (" . $ph($lessonIds) . ')
            GROUP BY i.lesson_id
        ', $lessonIds) as $row) {
            $done[(int)$row['lesson_id']] = (int)$row['n'];
        }
        $enrolled = [];
        foreach ($this->rows('SELECT class_id, COUNT(*) AS n FROM student_enrollments WHERE class_id IN (' . $ph($classIds) . ') GROUP BY class_id', $classIds) as $row) {
            $enrolled[(int)$row['class_id']] = (int)$row['n'];
        }
        $out = [];
        foreach ($lessons as $lesson) {
            $id = (int)$lesson['id'];
            $slots = ($items[$id] ?? 0) * ($enrolled[(int)$lesson['class_id']] ?? 0);
            $out[$id] = $slots > 0 ? (int)min(100, round(100 * ($done[$id] ?? 0) / $slots)) : 0;
        }
        return $out;
    }

    /**
     * Weighted progress: each lesson counts by its number of items, so a long lesson weighs more than a short one.
     * Assessment is kept separate: obtained marks over available marks.
     *
     * @param list<array{completed:int,total:int}> $lessons
     */
    public static function weightedProgress(array $lessons): int
    {
        $done = 0;
        $total = 0;
        foreach ($lessons as $lesson) {
            $total += max(0, (int)$lesson['total']);
            $done += min(max(0, (int)$lesson['completed']), max(0, (int)$lesson['total']));
        }
        return OnlineLessonService::percentComplete($done, $total);
    }

    /**
     * The student's released lessons in order, grouped by course when the lesson has a topic, otherwise by subject.
     *
     * @return array{groups:list<array<string,mixed>>,next:?array<string,mixed>,progress:int}
     */
    public function studentPath(int $studentId, ?int $now = null): array
    {
        $now = $now ?? time();
        $sqlWithTeacher = '
            SELECT ol.*, tt.date, tt.start_time, tt.end_time, tt.class_id, tt.teacher_id, tt.subject_id, tt.delivery_mode, tt.class_fee_per_student,
                   s.name AS subject_name, t.title AS topic_title, t.sort_order AS topic_sort,
                   u.title AS unit_title, u.sort_order AS unit_sort, c.id AS course_id, c.title AS course_title
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            JOIN student_enrollments e ON e.class_id = tt.class_id AND e.student_id = ?
            LEFT JOIN subjects s ON s.id = tt.subject_id
            LEFT JOIN lm_topics t ON t.id = ol.topic_id
            LEFT JOIN lm_units u ON u.id = t.unit_id
            LEFT JOIN lm_courses c ON c.id = u.course_id AND c.archived = 0
            WHERE tt.deleted_at IS NULL AND ol.published = 1
              %s
            ORDER BY tt.date ASC, tt.start_time ASC
            LIMIT 400';
        try {
            $rows = $this->rows(sprintf($sqlWithTeacher, 'AND (e.teacher_id IS NULL OR e.teacher_id = 0 OR e.teacher_id = tt.teacher_id)'), [$studentId]);
        } catch (\PDOException $e) {
            $rows = $this->rows(sprintf($sqlWithTeacher, ''), [$studentId]);
        }
        $rows = array_values(array_filter($rows, static fn (array $row): bool => OnlineLessonService::isReleased($row, $now)));
        if ($rows === []) {
            return ['groups' => [], 'next' => null, 'progress' => 0];
        }
        $lessonIds = array_map(static fn (array $r): int => (int)$r['id'], $rows);
        $access = new LessonAccessService($this->pdo);
        $percents = $access->completionPercents($studentId, $lessonIds);
        $unmet = $access->unmetForMany($studentId, $lessonIds);
        $feeMap = (new StudentLessonFeeService($this->pdo))->mapForStudentLessons($studentId, $rows);
        $ph = implode(',', array_fill(0, count($lessonIds), '?'));
        $totals = [];
        foreach ($this->rows("SELECT lesson_id, COUNT(*) AS n FROM online_lesson_items WHERE lesson_id IN ($ph) GROUP BY lesson_id", $lessonIds) as $r) {
            $totals[(int)$r['lesson_id']] = (int)$r['n'];
        }
        $groups = [];
        $next = null;
        $weights = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $percent = $percents[$id] ?? 0;
            $window = OnlineLessonService::availabilityWindow($row, $row, $now);
            $state = 'available';
            $reason = '';
            $fee = $feeMap[(int)$row['timetable_id']] ?? null;
            if ($percent >= 100) {
                $state = 'completed';
            } elseif ($fee !== null && !StudentLessonFeeService::isUnlocked((string)$fee['status'], !empty($fee['covered_by_monthly']))) {
                $state = 'payment';
                $reason = $fee['status'] === 'pending' ? StudentLessonFeeService::pendingPaywallMessage() : 'Payment required.';
            } elseif (!empty($unmet[$id])) {
                $state = 'locked';
                $reason = 'Complete ' . $unmet[$id][0]['title'] . ' first.';
            } elseif (empty($window['open'])) {
                $state = 'locked';
                $reason = (string)($window['message'] ?? 'Not open yet.');
            } elseif ($percent > 0) {
                $state = 'in_progress';
            }
            $total = $totals[$id] ?? 0;
            $weights[] = ['completed' => (int)round($percent * $total / 100), 'total' => $total];
            $entry = [
                'lesson_id' => $id,
                'timetable_id' => (int)$row['timetable_id'],
                'title' => (string)$row['title'],
                'date' => (string)$row['date'],
                'subject' => (string)($row['subject_name'] ?? ''),
                'unit' => (string)($row['unit_title'] ?? ''),
                'topic' => (string)($row['topic_title'] ?? ''),
                'percent' => $percent,
                'state' => $state,
                'reason' => $reason,
            ];
            if ($next === null && in_array($state, ['in_progress', 'available'], true)) {
                $next = $entry;
            }
            $key = !empty($row['course_id']) ? 'c' . (int)$row['course_id'] : 's' . (int)$row['subject_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'title' => !empty($row['course_id']) ? (string)$row['course_title'] : (string)($row['subject_name'] ?? 'Lessons'),
                    'is_course' => !empty($row['course_id']),
                    'lessons' => [],
                    'weights' => [],
                ];
            }
            $groups[$key]['lessons'][] = $entry + ['unit_sort' => (int)($row['unit_sort'] ?? 0), 'topic_sort' => (int)($row['topic_sort'] ?? 0)];
            $groups[$key]['weights'][] = ['completed' => (int)round($percent * $total / 100), 'total' => $total];
        }
        foreach ($groups as &$group) {
            if ($group['is_course']) {
                usort($group['lessons'], static fn (array $a, array $b): int => [$a['unit_sort'], $a['topic_sort'], $a['date']] <=> [$b['unit_sort'], $b['topic_sort'], $b['date']]);
            }
            $group['progress'] = self::weightedProgress($group['weights']);
            unset($group['weights']);
        }
        unset($group);
        return ['groups' => array_values($groups), 'next' => $next, 'progress' => self::weightedProgress($weights)];
    }

    /**
     * @return array<string,mixed>
     */
    private function editable(int $courseId, int $userId, bool $isAdmin): array
    {
        $course = $this->course($courseId);
        if (!$course || !$this->canEdit($course, $userId, $isAdmin)) {
            throw new RuntimeException('You cannot edit that course.');
        }
        return $course;
    }

    /**
     * @return array<string,mixed>
     */
    private function unit(int $unitId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lm_units WHERE id = ? LIMIT 1');
        $stmt->execute([$unitId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('That unit was not found.');
        }
        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    private function topic(int $topicId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lm_topics WHERE id = ? LIMIT 1');
        $stmt->execute([$topicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('That topic was not found.');
        }
        return $row;
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

    private function scalar(string $sql, array $params): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
