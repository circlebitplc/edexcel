<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Planning calendar, bulk draft creation and lesson timelines, built on the existing timetable.
 * Never publishes anything.
 */
final class LessonPlannerService
{
    public const VIEWS = ['week', 'month', 'term'];
    public const TERM_WEEKS = 13;
    public const BULK_LIMIT = 40;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{from:string,to:string,prev:string,next:string,label:string}
     */
    public static function range(string $view, string $anchor): array
    {
        $view = in_array($view, self::VIEWS, true) ? $view : 'week';
        $ts = strtotime($anchor) ?: time();
        if ($view === 'month') {
            $from = date('Y-m-01', $ts);
            $to = date('Y-m-t', $ts);
            return [
                'from' => $from,
                'to' => $to,
                'prev' => date('Y-m-d', strtotime($from . ' -1 month')),
                'next' => date('Y-m-d', strtotime($from . ' +1 month')),
                'label' => date('F Y', $ts),
            ];
        }
        $monday = strtotime('monday this week', $ts);
        $weeks = $view === 'term' ? self::TERM_WEEKS : 1;
        $from = date('Y-m-d', $monday);
        $to = date('Y-m-d', strtotime($from . ' +' . ($weeks * 7 - 1) . ' days'));
        return [
            'from' => $from,
            'to' => $to,
            'prev' => date('Y-m-d', strtotime($from . ' -' . $weeks . ' weeks')),
            'next' => date('Y-m-d', strtotime($from . ' +' . $weeks . ' weeks')),
            'label' => date('d M', strtotime($from)) . ' – ' . date('d M Y', strtotime($to)),
        ];
    }

    /**
     * Timetable slots in range with their lesson state and duration check.
     *
     * @return list<array<string,mixed>>
     */
    public function slots(int $teacherId, bool $isAdmin, string $from, string $to, int $classId = 0): array
    {
        $rows = [];
        foreach ((new RecordingService($this->pdo))->teacherLessons($teacherId, $isAdmin, $from, $to, 400) as $row) {
            if (!OnlineLessonService::supportsDeliveryMode((string)($row['delivery_mode'] ?? 'physical'))) {
                continue;
            }
            if ($classId > 0 && (int)$row['class_id'] !== $classId) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, static fn (array $a, array $b): int => [(string)$a['date'], (string)$a['start_time']] <=> [(string)$b['date'], (string)$b['start_time']]);
        if ($rows === []) {
            return [];
        }
        $ttIds = array_map(static fn (array $r): int => (int)$r['id'], $rows);
        $ph = implode(',', array_fill(0, count($ttIds), '?'));
        $lessons = [];
        foreach ($this->rows("SELECT * FROM online_lessons WHERE timetable_id IN ($ph)", $ttIds) as $lesson) {
            $lessons[(int)$lesson['timetable_id']] = $lesson;
        }
        $lessonIds = array_map(static fn (array $l): int => (int)$l['id'], array_values($lessons));
        $items = [];
        $sections = [];
        if ($lessonIds !== []) {
            $lph = implode(',', array_fill(0, count($lessonIds), '?'));
            foreach ($this->rows("SELECT lesson_id, item_type, estimated_minutes FROM online_lesson_items WHERE lesson_id IN ($lph)", $lessonIds) as $item) {
                $items[(int)$item['lesson_id']][] = $item;
            }
            foreach ($this->rows("SELECT lesson_id, estimated_minutes FROM online_lesson_sections WHERE lesson_id IN ($lph)", $lessonIds) as $section) {
                $sections[(int)$section['lesson_id']][] = $section;
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $lesson = $lessons[(int)$row['id']] ?? null;
            $state = 'none';
            $duration = null;
            $itemCount = 0;
            if ($lesson) {
                $state = !empty($lesson['archived']) ? 'archived' : OnlineLessonService::publicationState($lesson);
                $lid = (int)$lesson['id'];
                $itemCount = count($items[$lid] ?? []);
                $duration = LessonAuthoringService::durationStatus(
                    self::plannedMinutes($lesson, $row),
                    $sections[$lid] ?? [],
                    $items[$lid] ?? []
                );
            }
            $out[] = [
                'timetable_id' => (int)$row['id'],
                'date' => (string)$row['date'],
                'start_time' => (string)$row['start_time'],
                'end_time' => (string)$row['end_time'],
                'class_id' => (int)$row['class_id'],
                'class_name' => (string)($row['class_name'] ?? ''),
                'subject_name' => (string)($row['subject_name'] ?? ''),
                'teacher_name' => (string)($row['teacher_name'] ?? ''),
                'delivery_mode' => (string)($row['delivery_mode'] ?? ''),
                'lesson_id' => $lesson ? (int)$lesson['id'] : 0,
                'title' => $lesson ? (string)$lesson['title'] : '',
                'state' => $state,
                'publish_at' => (string)($lesson['publish_at'] ?? ''),
                'item_count' => $itemCount,
                'duration' => $duration,
            ];
        }
        return $out;
    }

    /**
     * Planned minutes: the lesson plan value, otherwise the timetable slot length.
     *
     * @param array<string,mixed> $lesson
     * @param array<string,mixed> $slot
     */
    public static function plannedMinutes(array $lesson, array $slot): ?int
    {
        if (isset($lesson['plan_minutes']) && $lesson['plan_minutes'] !== null && $lesson['plan_minutes'] !== '' && (int)$lesson['plan_minutes'] > 0) {
            return (int)$lesson['plan_minutes'];
        }
        $start = strtotime('2000-01-01 ' . (string)($slot['start_time'] ?? ''));
        $end = strtotime('2000-01-01 ' . (string)($slot['end_time'] ?? ''));
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        return (int)round(($end - $start) / 60);
    }

    /**
     * Create unpublished lessons for slots that have none. Existing lessons are never touched.
     *
     * @param list<int> $timetableIds
     * @return array{created:list<int>,skipped:list<array{timetable_id:int,reason:string}>}
     */
    public function bulkCreateDrafts(array $timetableIds, int $teacherId, bool $isAdmin, int $userId, int $templateId = 0): array
    {
        $timetableIds = array_values(array_unique(array_filter(array_map('intval', $timetableIds), static fn (int $id): bool => $id > 0)));
        if ($timetableIds === []) {
            throw new RuntimeException('Choose at least one class slot.');
        }
        if (count($timetableIds) > self::BULK_LIMIT) {
            throw new RuntimeException('Choose at most ' . self::BULK_LIMIT . ' slots at a time.');
        }
        $recordings = new RecordingService($this->pdo);
        $lessons = new OnlineLessonService($this->pdo);
        $authoring = new LessonAuthoringService($this->pdo, $lessons);
        $created = [];
        $skipped = [];
        foreach ($timetableIds as $ttId) {
            $slot = $recordings->lessonForStaff($ttId, $teacherId, $isAdmin);
            if (!$slot) {
                $skipped[] = ['timetable_id' => $ttId, 'reason' => 'You cannot manage this class.'];
                continue;
            }
            if (!OnlineLessonService::supportsDeliveryMode((string)($slot['delivery_mode'] ?? 'physical'))) {
                $skipped[] = ['timetable_id' => $ttId, 'reason' => 'This class cannot have a learning module.'];
                continue;
            }
            if ($lessons->findByTimetable($ttId)) {
                $skipped[] = ['timetable_id' => $ttId, 'reason' => 'A lesson already exists.'];
                continue;
            }
            $lesson = $lessons->getOrCreateForTimetable($slot, null, $userId);
            $this->pdo->prepare('UPDATE online_lessons SET published = 0 WHERE id = ?')->execute([(int)$lesson['id']]);
            if ($templateId > 0) {
                try {
                    $authoring->applyTemplate((int)$lesson['id'], $templateId, $userId, $isAdmin);
                } catch (RuntimeException $e) {
                    $skipped[] = ['timetable_id' => $ttId, 'reason' => 'Draft created, but the template was not applied: ' . $e->getMessage()];
                }
            }
            $created[] = (int)$lesson['id'];
        }
        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Timeline of a lesson: each item's start and end minute, flagging anything past the planned time.
     *
     * @param list<array<string,mixed>> $items ordered items with estimated_minutes and optional section_id
     * @param list<array<string,mixed>> $sections ordered sections with id and estimated_minutes
     * @return array{rows:list<array<string,mixed>>,total:int,planned:?int,over:int,unestimated:int}
     */
    public static function timeline(array $items, array $sections, ?int $planned): array
    {
        $useItems = $sections === [];
        foreach ($items as $item) {
            if (($item['estimated_minutes'] ?? null) !== null && $item['estimated_minutes'] !== '') {
                $useItems = true;
                break;
            }
        }
        $source = $useItems ? $items : $sections;
        $rows = [];
        $clock = 0;
        $unestimated = 0;
        foreach ($source as $entry) {
            $minutes = ($entry['estimated_minutes'] ?? null) === null || $entry['estimated_minutes'] === '' ? null : max(0, (int)$entry['estimated_minutes']);
            if ($minutes === null) {
                $unestimated++;
            }
            $start = $clock;
            $clock += (int)$minutes;
            $rows[] = [
                'id' => (int)($entry['id'] ?? 0),
                'title' => (string)($entry['title'] ?? ''),
                'type' => $useItems ? (string)($entry['item_type'] ?? '') : 'section',
                'minutes' => $minutes,
                'start' => $start,
                'end' => $clock,
                'over' => $planned !== null && $planned > 0 && $clock > $planned,
            ];
        }
        return [
            'rows' => $rows,
            'total' => $clock,
            'planned' => $planned,
            'over' => $planned !== null && $planned > 0 ? max(0, $clock - $planned) : 0,
            'unestimated' => $unestimated,
        ];
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
