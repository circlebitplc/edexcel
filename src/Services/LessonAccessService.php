<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Scheduled publishing and lesson prerequisites.
 * Payment, enrolment, and the class availability window are still checked by the existing pages.
 */
final class LessonAccessService
{
    public function __construct(private PDO $pdo)
    {
    }

    public static function normalizeDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime(str_replace('T', ' ', $raw));
        if ($ts === false) {
            throw new RuntimeException('Enter a valid date and time.');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Publishes now, or at $publishAt when it is in the future. $unpublishAt closes the lesson later.
     */
    public function saveSchedule(int $lessonId, ?string $publishAt, ?string $unpublishAt, bool $publish, ?int $now = null): void
    {
        $now = $now ?? time();
        if ($publishAt !== null && strtotime($publishAt) <= $now) {
            $publishAt = null;
        }
        if ($unpublishAt !== null) {
            $start = $publishAt !== null ? strtotime($publishAt) : $now;
            if (strtotime($unpublishAt) <= $start) {
                throw new RuntimeException('The closing time must be after the publishing time.');
            }
        }
        if ($publish) {
            $this->pdo->prepare('UPDATE online_lessons SET published = 1, publish_at = ?, unpublish_at = ? WHERE id = ?')
                ->execute([$publishAt, $unpublishAt, $lessonId]);
            return;
        }
        $this->pdo->prepare('UPDATE online_lessons SET publish_at = ?, unpublish_at = ? WHERE id = ?')
            ->execute([$publishAt, $unpublishAt, $lessonId]);
    }

    public function clearSchedule(int $lessonId): void
    {
        $this->pdo->prepare('UPDATE online_lessons SET publish_at = NULL WHERE id = ?')->execute([$lessonId]);
    }

    /**
     * @param array<int,list<int>> $edges lesson id => lesson ids it requires
     */
    public static function wouldCreateCycle(array $edges, int $lessonId, int $requiresLessonId): bool
    {
        if ($lessonId === $requiresLessonId) {
            return true;
        }
        $stack = [$requiresLessonId];
        $seen = [];
        while ($stack !== []) {
            $current = array_pop($stack);
            if ($current === $lessonId) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($edges[$current] ?? [] as $next) {
                $stack[] = (int)$next;
            }
        }
        return false;
    }

    /**
     * @return array<int,list<int>>
     */
    public function edges(): array
    {
        $edges = [];
        foreach ($this->pdo->query('SELECT lesson_id, requires_lesson_id FROM online_lesson_prerequisites')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $edges[(int)$row['lesson_id']][] = (int)$row['requires_lesson_id'];
        }
        return $edges;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function prerequisites(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.requires_lesson_id, p.min_percent, ol.title, ol.timetable_id, ol.published, ol.publish_at
            FROM online_lesson_prerequisites p
            JOIN online_lessons ol ON ol.id = p.requires_lesson_id
            WHERE p.lesson_id = ?
            ORDER BY p.id ASC
        ');
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lessons in the same class that can be required before this one.
     *
     * @return list<array<string,mixed>>
     */
    public function candidates(int $lessonId, int $classId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ol.id, ol.title, ol.published, tt.date
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            WHERE tt.class_id = ? AND tt.deleted_at IS NULL AND ol.id <> ?
            ORDER BY tt.date DESC, ol.id DESC
            LIMIT 200
        ');
        $stmt->execute([$classId, $lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addPrerequisite(int $lessonId, int $requiresLessonId, int $minPercent): void
    {
        $minPercent = max(1, min(100, $minPercent));
        $class = $this->pdo->prepare('
            SELECT ol.id, tt.class_id
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            WHERE ol.id IN (?, ?)
        ');
        $class->execute([$lessonId, $requiresLessonId]);
        $classes = [];
        foreach ($class->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $classes[(int)$row['id']] = (int)$row['class_id'];
        }
        if (!isset($classes[$lessonId], $classes[$requiresLessonId]) || $classes[$lessonId] !== $classes[$requiresLessonId]) {
            throw new RuntimeException('Choose a lesson from the same class.');
        }
        if (self::wouldCreateCycle($this->edges(), $lessonId, $requiresLessonId)) {
            throw new RuntimeException('That would create a circular prerequisite. The chosen lesson already depends on this one.');
        }
        $existing = $this->pdo->prepare('SELECT id FROM online_lesson_prerequisites WHERE lesson_id = ? AND requires_lesson_id = ?');
        $existing->execute([$lessonId, $requiresLessonId]);
        $id = (int)$existing->fetchColumn();
        if ($id > 0) {
            $this->pdo->prepare('UPDATE online_lesson_prerequisites SET min_percent = ? WHERE id = ?')->execute([$minPercent, $id]);
            return;
        }
        $this->pdo->prepare('INSERT INTO online_lesson_prerequisites (lesson_id, requires_lesson_id, min_percent) VALUES (?, ?, ?)')
            ->execute([$lessonId, $requiresLessonId, $minPercent]);
    }

    public function removePrerequisite(int $lessonId, int $prerequisiteId): void
    {
        $this->pdo->prepare('DELETE FROM online_lesson_prerequisites WHERE id = ? AND lesson_id = ?')->execute([$prerequisiteId, $lessonId]);
    }

    /**
     * Prerequisites the student has not met yet. A required lesson that students cannot open yet does not block.
     *
     * @return list<array{lesson_id:int,timetable_id:int,title:string,min_percent:int,percent:int}>
     */
    public function unmetFor(int $studentId, int $lessonId): array
    {
        return $this->unmetForMany($studentId, [$lessonId])[$lessonId] ?? [];
    }

    /**
     * @param list<int> $lessonIds
     * @return array<int,list<array{lesson_id:int,timetable_id:int,title:string,min_percent:int,percent:int}>>
     */
    public function unmetForMany(int $studentId, array $lessonIds): array
    {
        $lessonIds = array_values(array_unique(array_filter(array_map('intval', $lessonIds), static fn (int $id): bool => $id > 0)));
        if ($lessonIds === [] || $studentId < 1) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT p.lesson_id, p.requires_lesson_id, p.min_percent, ol.title, ol.timetable_id, ol.published, ol.publish_at
            FROM online_lesson_prerequisites p
            JOIN online_lessons ol ON ol.id = p.requires_lesson_id
            WHERE p.lesson_id IN ($placeholders)
        ");
        $stmt->execute($lessonIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $required = [];
        foreach ($rows as $row) {
            if (OnlineLessonService::isReleased($row)) {
                $required[(int)$row['requires_lesson_id']] = true;
            }
        }
        $percent = $this->completionPercents($studentId, array_keys($required));
        $out = [];
        foreach ($rows as $row) {
            if (!OnlineLessonService::isReleased($row)) {
                continue;
            }
            $reqId = (int)$row['requires_lesson_id'];
            $have = $percent[$reqId] ?? 0;
            if ($have < (int)$row['min_percent']) {
                $out[(int)$row['lesson_id']][] = [
                    'lesson_id' => $reqId,
                    'timetable_id' => (int)$row['timetable_id'],
                    'title' => (string)$row['title'],
                    'min_percent' => (int)$row['min_percent'],
                    'percent' => $have,
                ];
            }
        }
        return $out;
    }

    /**
     * @param list<int> $lessonIds
     * @return array<int,int>
     */
    public function completionPercents(int $studentId, array $lessonIds): array
    {
        $lessonIds = array_values(array_filter(array_map('intval', $lessonIds), static fn (int $id): bool => $id > 0));
        if ($lessonIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
        $totals = $this->pdo->prepare("SELECT lesson_id, COUNT(*) AS n FROM online_lesson_items WHERE lesson_id IN ($placeholders) GROUP BY lesson_id");
        $totals->execute($lessonIds);
        $total = [];
        foreach ($totals->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $total[(int)$row['lesson_id']] = (int)$row['n'];
        }
        $done = $this->pdo->prepare("
            SELECT i.lesson_id, COUNT(*) AS n
            FROM online_lesson_item_state s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE s.student_id = ? AND s.status = 'completed' AND i.lesson_id IN ($placeholders)
            GROUP BY i.lesson_id
        ");
        $done->execute(array_merge([$studentId], $lessonIds));
        $completed = [];
        foreach ($done->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $completed[(int)$row['lesson_id']] = (int)$row['n'];
        }
        $out = [];
        foreach ($lessonIds as $id) {
            $out[$id] = OnlineLessonService::percentComplete($completed[$id] ?? 0, $total[$id] ?? 0);
        }
        return $out;
    }
}
