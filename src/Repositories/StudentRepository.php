<?php
declare(strict_types=1);

namespace Edexcel\Repositories;

use PDO;
use Throwable;

final class StudentRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, username, role, is_active, profile_image
            FROM users
            WHERE id = ? AND role = 'student' AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getEnrolledClassIds(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT class_id FROM student_enrollments WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getEnrolledClasses(int $studentId): array
    {
        $chosenTeacher = $this->enrollmentHasTeacherId()
            ? "LEFT JOIN teachers et ON et.id = se.teacher_id AND et.deleted_at IS NULL"
            : "";
        $teacherIdSelect = $this->enrollmentHasTeacherId()
            ? "COALESCE(et.id, MIN(t.id)) AS teacher_id"
            : "MIN(t.id) AS teacher_id";
        $teacherNameSelect = $this->enrollmentHasTeacherId()
            ? "COALESCE(et.name, GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ')) AS teacher_name"
            : "GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS teacher_name";
        $teacherPhotoSelect = $this->enrollmentHasTeacherId()
            ? "SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT COALESCE(et.photo, t.photo) ORDER BY t.id SEPARATOR ','), ',', 1) AS teacher_photo"
            : "SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT t.photo ORDER BY t.id SEPARATOR ','), ',', 1) AS teacher_photo";
        $groupExtra = $this->enrollmentHasTeacherId() ? ", et.id, et.name, et.photo" : "";

        $stmt = $this->pdo->prepare("
            SELECT
                c.id,
                c.name AS class_name,
                c.description,
                c.whatsapp_link,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subject_name,
                {$teacherIdSelect},
                {$teacherNameSelect},
                {$teacherPhotoSelect}
            FROM student_enrollments se
            INNER JOIN student_classes c ON c.id = se.class_id AND c.deleted_at IS NULL
            LEFT JOIN subject_classes sc ON sc.class_id = c.id
            LEFT JOIN subjects s ON s.id = sc.subject_id AND s.deleted_at IS NULL
            {$chosenTeacher}
            {$this->classStaffJoinSql()}
            WHERE se.student_id = ?
            GROUP BY c.id, c.name, c.description, c.whatsapp_link{$groupExtra}
            ORDER BY c.name
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAvailableClassesForStudent(int $studentId, string $query = ''): array
    {
        $capacitySelect = $this->classHasColumn('capacity') ? 'c.capacity' : 'NULL';
        $capacityGroup = $this->classHasColumn('capacity') ? ', c.capacity' : '';

        $sql = "
            SELECT
                c.id,
                c.name,
                c.name AS class_name,
                c.description,
                c.whatsapp_link,
                {$capacitySelect} AS capacity,
                t.id AS class_teacher_id,
                t.name AS class_teacher_name,
                GROUP_CONCAT(DISTINCT COALESCE(st.name, s.name) ORDER BY COALESCE(st.name, s.name) SEPARATOR ', ') AS subject_name,
                t.id AS teacher_id,
                t.name AS teacher_name,
                t.photo AS teacher_photo,
                (
                    SELECT CONCAT(
                        DATE_FORMAT(tt.date, '%a %e %b'),
                        ' · ',
                        TIME_FORMAT(tt.start_time, '%l:%i %p')
                    )
                    FROM timetable tt
                    WHERE tt.class_id = c.id
                      AND tt.deleted_at IS NULL
                      AND (t.id IS NULL OR tt.teacher_id = t.id)
                      AND (tt.date > CURDATE() OR (tt.date = CURDATE() AND tt.start_time >= CURTIME()))
                    ORDER BY tt.date ASC, tt.start_time ASC
                    LIMIT 1
                ) AS next_lesson,
                (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = c.id) AS student_count,
                (SELECT COUNT(*) FROM student_enrollments se2 WHERE se2.class_id = c.id AND se2.student_id = ?) AS is_enrolled,
                (SELECT COUNT(*) FROM student_waitlist w WHERE w.class_id = c.id AND w.student_id = ?) AS on_waitlist
            FROM student_classes c
            LEFT JOIN subject_classes sc ON sc.class_id = c.id
            LEFT JOIN subjects s ON s.id = sc.subject_id AND s.deleted_at IS NULL
            {$this->classStaffJoinSql()}
            LEFT JOIN timetable tt_subj
                ON tt_subj.class_id = c.id
               AND tt_subj.deleted_at IS NULL
               AND t.id IS NOT NULL
               AND tt_subj.teacher_id = t.id
            LEFT JOIN subjects st ON st.id = tt_subj.subject_id AND st.deleted_at IS NULL
            WHERE c.deleted_at IS NULL
        ";
        $params = [$studentId, $studentId];

        if ($query !== '') {
            $sql .= " AND (c.name LIKE ? OR s.name LIKE ? OR st.name LIKE ? OR t.name LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }

        $sql .= "
            GROUP BY c.id, c.name, c.description, c.whatsapp_link, t.id, t.name, t.photo{$capacityGroup}
            ORDER BY subject_name, t.name, c.name
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function classStaffJoinSql(): string
    {
        $parts = [
            "SELECT class_id, teacher_id
             FROM timetable
             WHERE deleted_at IS NULL
               AND class_id IS NOT NULL
               AND class_id > 0
               AND teacher_id IS NOT NULL
               AND teacher_id > 0",
        ];
        if ($this->classHasColumn('teacher_id')) {
            $parts[] = "SELECT id AS class_id, teacher_id
                        FROM student_classes
                        WHERE deleted_at IS NULL
                          AND teacher_id IS NOT NULL
                          AND teacher_id > 0";
        }

        return "
            LEFT JOIN (
                " . implode("\n                UNION\n                ", $parts) . "
            ) class_staff ON class_staff.class_id = c.id
            LEFT JOIN teachers t ON t.id = class_staff.teacher_id AND t.deleted_at IS NULL
        ";
    }

    private function enrollmentHasTeacherId(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            if (function_exists('campus_column_exists')) {
                $cached = \campus_column_exists($this->pdo, 'student_enrollments', 'teacher_id');
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'student_enrollments'
                      AND COLUMN_NAME = 'teacher_id'
                ");
                $stmt->execute();
                $cached = (int)$stmt->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    private function classHasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        try {
            if (function_exists('campus_column_exists')) {
                $cache[$column] = \campus_column_exists($this->pdo, 'student_classes', $column);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'student_classes'
                      AND COLUMN_NAME = ?
                ");
                $stmt->execute([$column]);
                $cache[$column] = (int)$stmt->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
            $cache[$column] = false;
        }
        return $cache[$column];
    }

    public function enroll(int $studentId, int $classId): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO student_enrollments (student_id, class_id)
            VALUES (?, ?)
        ");
        return $stmt->execute([$studentId, $classId]);
    }

    public function unenroll(int $studentId, int $classId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM student_enrollments WHERE student_id = ? AND class_id = ?
        ");
        return $stmt->execute([$studentId, $classId]);
    }
}
