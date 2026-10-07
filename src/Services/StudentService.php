<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\StudentRepository;
use PDO;

final class StudentService
{
    public function __construct(
        private StudentRepository $repository,
        private PDO $pdo
    ) {}

    public function getEnrolledClasses(int $studentId): array
    {
        $classes = $this->repository->getEnrolledClasses($studentId);
        foreach ($classes as &$c) {
            $c['teacher_initials'] = teacherInitials((string)($c['teacher_name'] ?? ''));
            $c['teacher_color'] = teacherAvatarColor((string)($c['teacher_name'] ?? ''));
            $c['teacher_photo_path'] = teacherPhotoPath((int)($c['teacher_id'] ?? 0), $c['teacher_photo'] ?? null);
        }
        unset($c);
        return $classes;
    }

    public function getAcademicServices(int $studentId): array
    {
        $enrolledIds = $this->repository->getEnrolledClassIds($studentId);

        $materials = [];
        $homework = [];
        $exams = [];
        $events = [];
        $progress = [];

        try {
            // 1. Materials
            $matSql = "SELECT * FROM student_materials WHERE 1=1";
            if ($enrolledIds) {
                $in = implode(',', array_fill(0, count($enrolledIds), '?'));
                $matSql .= " AND (class_id IN ($in) OR class_id IS NULL)";
                $stmt = $this->pdo->prepare($matSql . " ORDER BY created_at DESC LIMIT 20");
                $stmt->execute($enrolledIds);
            } else {
                $stmt = $this->pdo->query($matSql . " AND class_id IS NULL ORDER BY created_at DESC LIMIT 20");
            }
            $materials = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            // 2. Homework
            $hwSql = "SELECT * FROM student_homework WHERE (due_date >= CURDATE() OR due_date IS NULL)";
            if ($enrolledIds) {
                $in = implode(',', array_fill(0, count($enrolledIds), '?'));
                $hwSql .= " AND (class_id IN ($in) OR class_id IS NULL)";
                $stmt = $this->pdo->prepare($hwSql . " ORDER BY due_date ASC LIMIT 20");
                $stmt->execute($enrolledIds);
            } else {
                $stmt = $this->pdo->query($hwSql . " AND class_id IS NULL ORDER BY due_date ASC LIMIT 20");
            }
            $homework = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            // 3. Exams
            $exSql = "SELECT * FROM student_exams WHERE exam_date >= CURDATE()";
            if ($enrolledIds) {
                $in = implode(',', array_fill(0, count($enrolledIds), '?'));
                $exSql .= " AND (class_id IN ($in) OR class_id IS NULL)";
                $stmt = $this->pdo->prepare($exSql . " ORDER BY exam_date ASC LIMIT 20");
                $stmt->execute($enrolledIds);
            } else {
                $stmt = $this->pdo->query($exSql . " AND class_id IS NULL ORDER BY exam_date ASC LIMIT 20");
            }
            $exams = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            // 4. Events
            $evStmt = $this->pdo->prepare("SELECT * FROM student_events WHERE event_date >= ? ORDER BY event_date ASC LIMIT 20");
            $evStmt->execute([date('Y-m-d')]);
            $events = $evStmt ? $evStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            // 5. Progress
            $prStmt = $this->pdo->prepare("
                SELECT * FROM student_progress
                WHERE student_id = ?
                  AND published = 1
                ORDER BY recorded_at DESC LIMIT 30
            ");
            $prStmt->execute([$studentId]);
            $progress = $prStmt ? $prStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $ignore) {}

        return [
            'materials' => $materials,
            'homework'  => $homework,
            'exams'     => $exams,
            'events'    => $events,
            'progress'  => $progress,
        ];
    }
}
