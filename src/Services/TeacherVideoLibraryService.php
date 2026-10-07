<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class TeacherVideoLibraryService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
            FROM teacher_video_library v
            JOIN teachers t ON t.id = v.teacher_id
            LEFT JOIN subjects s ON s.id = v.subject_id
            LEFT JOIN student_classes c ON c.id = v.class_id
            WHERE v.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForTeacher(int $teacherId, bool $isAdmin): array
    {
        $sql = "
            SELECT v.*, s.name AS subject_name, c.name AS class_name
            FROM teacher_video_library v
            LEFT JOIN subjects s ON s.id = v.subject_id
            LEFT JOIN student_classes c ON c.id = v.class_id
            WHERE v.deleted_at IS NULL AND v.status <> 'deleted'
        ";
        $params = [];
        if (!$isAdmin) {
            $sql .= ' AND v.teacher_id = ?';
            $params[] = $teacherId;
        }
        $sql .= ' ORDER BY v.id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>
     */
    public function createDraft(
        int $teacherId,
        string $bunnyVideoId,
        string $libraryId,
        string $title,
        string $description,
        ?int $subjectId,
        ?int $classId,
        string $tags,
        string $visibility
    ): array {
        $vis = in_array($visibility, ['private', 'class_students', 'selected_students'], true)
            ? $visibility
            : 'private';
        $this->pdo->prepare("
            INSERT INTO teacher_video_library
                (teacher_id, bunny_video_id, bunny_library_id, title, description, subject_id, class_id, tags, visibility, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'uploading')
        ")->execute([
            $teacherId,
            $bunnyVideoId,
            $libraryId,
            $title,
            $description !== '' ? $description : null,
            $subjectId,
            $classId,
            $tags !== '' ? $tags : null,
            $vis,
        ]);
        $row = $this->find((int)$this->pdo->lastInsertId());
        if (!$row) {
            throw new RuntimeException('Could not create library video.');
        }
        return $row;
    }

    public function markUploaded(int $id, int $teacherId, bool $isAdmin): bool
    {
        $row = $this->find($id);
        if (!$row || !RecordingAccessService::teacherOwnsLibraryItem($teacherId, (int)$row['teacher_id'], $isAdmin)) {
            return false;
        }
        $this->pdo->prepare("
            UPDATE teacher_video_library
            SET status = 'processing', uploaded_at = COALESCE(uploaded_at, NOW())
            WHERE id = ?
        ")->execute([$id]);
        return true;
    }

    public function archive(int $id, int $teacherId, bool $isAdmin): bool
    {
        $row = $this->find($id);
        if (!$row || !RecordingAccessService::teacherOwnsLibraryItem($teacherId, (int)$row['teacher_id'], $isAdmin)) {
            return false;
        }
        $this->pdo->prepare("
            UPDATE teacher_video_library
            SET status = 'deleted', deleted_at = NOW()
            WHERE id = ?
        ")->execute([$id]);
        return true;
    }

    /**
     * Students may watch class_students videos for classes they are enrolled in,
     * or selected_students videos they were attached to. Never another teacher's private library.
     */
    public function studentCanWatch(int $studentId, array $video): bool
    {
        if ((string)$video['status'] !== 'ready' || $video['deleted_at'] !== null) {
            return false;
        }
        $vis = (string)$video['visibility'];
        if ($vis === 'private') {
            return false;
        }
        if ($vis === 'selected_students') {
            $stmt = $this->pdo->prepare('SELECT 1 FROM teacher_video_library_students WHERE video_id = ? AND student_id = ? LIMIT 1');
            $stmt->execute([(int)$video['id'], $studentId]);
            return (bool)$stmt->fetchColumn();
        }
        if ($vis === 'class_students') {
            $classId = (int)($video['class_id'] ?? 0);
            if ($classId < 1) {
                return false;
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
            $stmt->execute([$studentId, $classId]);
            return (bool)$stmt->fetchColumn();
        }
        return false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function studentVisible(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
            FROM teacher_video_library v
            JOIN teachers t ON t.id = v.teacher_id
            LEFT JOIN subjects s ON s.id = v.subject_id
            LEFT JOIN student_classes c ON c.id = v.class_id
            WHERE v.deleted_at IS NULL AND v.status = 'ready'
              AND (
                    (v.visibility = 'class_students' AND v.class_id IN (
                        SELECT class_id FROM student_enrollments WHERE student_id = ?
                    ))
                 OR (v.visibility = 'selected_students' AND v.id IN (
                        SELECT video_id FROM teacher_video_library_students WHERE student_id = ?
                    ))
              )
            ORDER BY v.id DESC
            LIMIT 100
        ");
        $stmt->execute([$studentId, $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
