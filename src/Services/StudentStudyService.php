<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Student bookmarks and private notes. Every query is scoped to the student who owns the row.
 * Teachers and admins have no read path to private notes.
 */
final class StudentStudyService
{
    public const NOTE_LIMIT = 5000;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return bool true when the bookmark now exists
     */
    public function toggleBookmark(int $studentId, int $lessonId, int $itemId, int $questionId = 0): bool
    {
        $this->assertItem($lessonId, $itemId, $questionId);
        $find = $this->pdo->prepare('SELECT id FROM online_lesson_bookmarks WHERE student_id = ? AND item_id = ? AND question_id = ?');
        $find->execute([$studentId, $itemId, $questionId]);
        $id = (int)$find->fetchColumn();
        if ($id > 0) {
            $this->pdo->prepare('DELETE FROM online_lesson_bookmarks WHERE id = ? AND student_id = ?')->execute([$id, $studentId]);
            return false;
        }
        $this->pdo->prepare('INSERT INTO online_lesson_bookmarks (student_id, lesson_id, item_id, question_id) VALUES (?, ?, ?, ?)')
            ->execute([$studentId, $lessonId, $itemId, $questionId]);
        return true;
    }

    /**
     * @return array<string,true> keys "item:question"
     */
    public function bookmarkKeys(int $studentId, int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT item_id, question_id FROM online_lesson_bookmarks WHERE student_id = ? AND lesson_id = ?');
        $stmt->execute([$studentId, $lessonId]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $keys[(int)$row['item_id'] . ':' . (int)$row['question_id']] = true;
        }
        return $keys;
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function myBookmarks(int $studentId, int $page = 1, int $perPage = 30): array
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM online_lesson_bookmarks WHERE student_id = ?');
        $count->execute([$studentId]);
        $stmt = $this->pdo->prepare('
            SELECT b.id, b.item_id, b.question_id, b.created_at, i.title AS item_title, i.item_type,
                   ol.title AS lesson_title, ol.timetable_id, q.prompt AS question_prompt
            FROM online_lesson_bookmarks b
            JOIN online_lesson_items i ON i.id = b.item_id AND i.lesson_id = b.lesson_id
            JOIN online_lessons ol ON ol.id = b.lesson_id
            LEFT JOIN online_lesson_questions q ON q.id = b.question_id
            WHERE b.student_id = ?
            ORDER BY b.created_at DESC, b.id DESC
            LIMIT ' . max(1, min(100, $perPage)) . ' OFFSET ' . (max(0, $page - 1) * max(1, min(100, $perPage))));
        $stmt->execute([$studentId]);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int)$count->fetchColumn()];
    }

    public function removeBookmark(int $studentId, int $bookmarkId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM online_lesson_bookmarks WHERE id = ? AND student_id = ?');
        $stmt->execute([$bookmarkId, $studentId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Saves a private note on the lesson (section 0, item 0), a section, or an activity. An empty note is deleted.
     */
    public function saveNote(int $studentId, int $lessonId, int $sectionId, int $itemId, string $body, ?string $now = null): void
    {
        if ($itemId > 0) {
            $this->assertItem($lessonId, $itemId, 0);
            $sectionId = 0;
        } elseif ($sectionId > 0) {
            $check = $this->pdo->prepare('SELECT id FROM online_lesson_sections WHERE id = ? AND lesson_id = ?');
            $check->execute([$sectionId, $lessonId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('That section is not part of this lesson.');
            }
        }
        $body = trim($body);
        if (mb_strlen($body) > self::NOTE_LIMIT) {
            throw new RuntimeException('Keep the note under ' . self::NOTE_LIMIT . ' characters.');
        }
        $find = $this->pdo->prepare('SELECT id FROM online_lesson_notes WHERE student_id = ? AND lesson_id = ? AND section_id = ? AND item_id = ?');
        $find->execute([$studentId, $lessonId, $sectionId, $itemId]);
        $id = (int)$find->fetchColumn();
        if ($body === '') {
            if ($id > 0) {
                $this->pdo->prepare('DELETE FROM online_lesson_notes WHERE id = ? AND student_id = ?')->execute([$id, $studentId]);
            }
            return;
        }
        $now = $now ?? date('Y-m-d H:i:s');
        if ($id > 0) {
            $this->pdo->prepare('UPDATE online_lesson_notes SET body = ?, updated_at = ? WHERE id = ? AND student_id = ?')
                ->execute([$body, $now, $id, $studentId]);
            return;
        }
        $this->pdo->prepare('
            INSERT INTO online_lesson_notes (student_id, lesson_id, section_id, item_id, body, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([$studentId, $lessonId, $sectionId, $itemId, $body, $now]);
    }

    /**
     * @return array<string,string> keys "section:item"
     */
    public function notesForLesson(int $studentId, int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT section_id, item_id, body FROM online_lesson_notes WHERE student_id = ? AND lesson_id = ?');
        $stmt->execute([$studentId, $lessonId]);
        $notes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $notes[(int)$row['section_id'] . ':' . (int)$row['item_id']] = (string)$row['body'];
        }
        return $notes;
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function myNotes(int $studentId, int $page = 1, int $perPage = 30): array
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM online_lesson_notes WHERE student_id = ?');
        $count->execute([$studentId]);
        $stmt = $this->pdo->prepare('
            SELECT n.id, n.section_id, n.item_id, n.body, n.updated_at, ol.title AS lesson_title, ol.timetable_id,
                   i.title AS item_title, s.title AS section_title
            FROM online_lesson_notes n
            JOIN online_lessons ol ON ol.id = n.lesson_id
            LEFT JOIN online_lesson_items i ON i.id = n.item_id AND i.lesson_id = n.lesson_id
            LEFT JOIN online_lesson_sections s ON s.id = n.section_id AND s.lesson_id = n.lesson_id
            WHERE n.student_id = ?
            ORDER BY n.updated_at DESC, n.id DESC
            LIMIT ' . max(1, min(100, $perPage)) . ' OFFSET ' . (max(0, $page - 1) * max(1, min(100, $perPage))));
        $stmt->execute([$studentId]);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int)$count->fetchColumn()];
    }

    private function assertItem(int $lessonId, int $itemId, int $questionId): void
    {
        $stmt = $this->pdo->prepare('SELECT activity_id FROM online_lesson_items WHERE id = ? AND lesson_id = ?');
        $stmt->execute([$itemId, $lessonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('That part of the lesson was not found.');
        }
        if ($questionId > 0) {
            $q = $this->pdo->prepare('SELECT id FROM online_lesson_questions WHERE id = ? AND activity_id = ?');
            $q->execute([$questionId, (int)($row['activity_id'] ?? 0)]);
            if (!$q->fetchColumn()) {
                throw new RuntimeException('That question was not found.');
            }
        }
    }
}
