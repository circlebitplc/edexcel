<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class HomeworkSubmissionService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function forStudent(int $studentId, array $classIds, int $limit = 30): array
    {
        if ($studentId < 1) {
            return [];
        }
        $limit = max(1, min(60, $limit));
        $classIds = array_values(array_filter(array_map('intval', $classIds)));
        try {
            if ($classIds === []) {
                $stmt = $this->pdo->prepare("
                    SELECT h.*, NULL AS class_name, NULL AS subject_name, NULL AS teacher_name,
                           sub.id AS submission_id, sub.status AS submission_status, sub.file_url AS submission_file,
                           sub.note AS submission_note, sub.score, sub.max_score, sub.teacher_feedback, sub.submitted_at
                    FROM student_homework h
                    LEFT JOIN student_homework_submissions sub
                        ON sub.homework_id = h.id AND sub.student_id = ?
                    WHERE h.class_id IS NULL
                    ORDER BY (h.due_date IS NULL), h.due_date ASC, h.created_at DESC
                    LIMIT {$limit}
                ");
                $stmt->execute([$studentId]);
            } else {
                $in = implode(',', array_fill(0, count($classIds), '?'));
                $stmt = $this->pdo->prepare("
                    SELECT h.*, c.name AS class_name, s.name AS subject_name, t.name AS teacher_name,
                           sub.id AS submission_id, sub.status AS submission_status, sub.file_url AS submission_file,
                           sub.note AS submission_note, sub.score, sub.max_score, sub.teacher_feedback, sub.submitted_at
                    FROM student_homework h
                    LEFT JOIN student_classes c ON c.id = h.class_id
                    LEFT JOIN subjects s ON s.id = h.subject_id
                    LEFT JOIN teachers t ON t.id = h.teacher_id
                    LEFT JOIN student_homework_submissions sub
                        ON sub.homework_id = h.id AND sub.student_id = ?
                    WHERE h.class_id IS NULL OR h.class_id IN ($in)
                    ORDER BY (h.due_date IS NULL), h.due_date ASC, h.created_at DESC
                    LIMIT {$limit}
                ");
                $stmt->execute(array_merge([$studentId], $classIds));
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Homework forStudent: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function dueSoonForStudent(int $studentId, array $classIds, int $days = 14): array
    {
        $rows = $this->forStudent($studentId, $classIds, 40);
        $cutoff = date('Y-m-d', strtotime('+' . max(1, $days) . ' days'));
        $out = [];
        foreach ($rows as $row) {
            $due = (string)($row['due_date'] ?? '');
            $status = (string)($row['submission_status'] ?? '');
            if ($status === 'done') {
                continue;
            }
            if ($due !== '' && $due > $cutoff) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= 8) {
                break;
            }
        }
        return $out;
    }

    public function submit(
        int $studentId,
        int $homeworkId,
        string $note,
        ?array $file,
        string $uploadDir,
        string $publicBase
    ): void {
        if ($studentId < 1 || $homeworkId < 1) {
            throw new RuntimeException('Invalid homework.');
        }

        $hw = $this->pdo->prepare("SELECT * FROM student_homework WHERE id = ? LIMIT 1");
        $hw->execute([$homeworkId]);
        $homework = $hw->fetch(PDO::FETCH_ASSOC);
        if (!$homework) {
            throw new RuntimeException('Homework not found.');
        }

        $classId = (int)($homework['class_id'] ?? 0);
        if ($classId > 0) {
            $en = $this->pdo->prepare("SELECT 1 FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1");
            $en->execute([$studentId, $classId]);
            if (!$en->fetchColumn()) {
                throw new RuntimeException('You are not enrolled in this class.');
            }
        }

        $note = trim($note);
        $fileUrl = null;
        $fileName = null;
        if ($file && !empty($file['name']) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], true)) {
                throw new RuntimeException('Upload a PDF, Word, or image file.');
            }
            if ((int)$file['size'] > 12 * 1024 * 1024) {
                throw new RuntimeException('File is larger than 12 MB.');
            }
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $fileName = 'hw_' . $studentId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file((string)$file['tmp_name'], rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName)) {
                throw new RuntimeException('Could not save your file.');
            }
            $fileUrl = rtrim($publicBase, '/') . '/files/homework/' . $fileName;
        }

        if ($note === '' && $fileUrl === null) {
            throw new RuntimeException('Add a short note or upload a file.');
        }

        $existing = $this->pdo->prepare("SELECT id FROM student_homework_submissions WHERE homework_id = ? AND student_id = ? LIMIT 1");
        $existing->execute([$homeworkId, $studentId]);
        $existingId = (int)$existing->fetchColumn();

        if ($existingId > 0) {
            $sql = "
                UPDATE student_homework_submissions
                SET note = ?, status = 'submitted', teacher_feedback = NULL, score = NULL,
                    reviewed_by = NULL, reviewed_at = NULL, submitted_at = NOW()
            ";
            $params = [$note !== '' ? $note : null];
            if ($fileUrl !== null) {
                $sql .= ", file_url = ?, file_name = ?";
                $params[] = $fileUrl;
                $params[] = $fileName;
            }
            $sql .= " WHERE id = ?";
            $params[] = $existingId;
            $this->pdo->prepare($sql)->execute($params);
        } else {
            $this->pdo->prepare("
                INSERT INTO student_homework_submissions
                    (homework_id, student_id, note, file_url, file_name, status)
                VALUES (?, ?, ?, ?, ?, 'submitted')
            ")->execute([
                $homeworkId,
                $studentId,
                $note !== '' ? $note : null,
                $fileUrl,
                $fileName,
            ]);
        }

        $teacherId = (int)($homework['teacher_id'] ?? 0);
        if ($teacherId > 0 && function_exists('create_teacher_dashboard_notification')) {
            create_teacher_dashboard_notification(
                $this->pdo,
                $teacherId,
                'Homework submitted',
                (string)($homework['title'] ?? 'Homework'),
                'class',
                (defined('BASE_URL') ? BASE_URL : '/') . 'campus/homework.php?view=submissions'
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pendingForTeacher(int $teacherId, bool $isAdmin, int $limit = 40): array
    {
        $limit = max(1, min(80, $limit));
        try {
            if ($isAdmin) {
                $stmt = $this->pdo->query("
                    SELECT sub.*, h.title, h.due_date, h.class_id, c.name AS class_name,
                           COALESCE(sp.full_name, u.username) AS student_name
                    FROM student_homework_submissions sub
                    JOIN student_homework h ON h.id = sub.homework_id
                    LEFT JOIN student_classes c ON c.id = h.class_id
                    LEFT JOIN users u ON u.id = sub.student_id
                    LEFT JOIN student_profiles sp ON sp.user_id = sub.student_id
                    WHERE sub.status = 'submitted'
                    ORDER BY sub.submitted_at ASC
                    LIMIT {$limit}
                ");
                return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            }
            if ($teacherId < 1) {
                return [];
            }
            $stmt = $this->pdo->prepare("
                SELECT sub.*, h.title, h.due_date, h.class_id, c.name AS class_name,
                       COALESCE(sp.full_name, u.username) AS student_name
                FROM student_homework_submissions sub
                JOIN student_homework h ON h.id = sub.homework_id
                LEFT JOIN student_classes c ON c.id = h.class_id
                LEFT JOIN users u ON u.id = sub.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = sub.student_id
                WHERE sub.status = 'submitted'
                  AND (h.teacher_id = ? OR h.teacher_id IS NULL)
                ORDER BY sub.submitted_at ASC
                LIMIT {$limit}
            ");
            $stmt->execute([$teacherId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function review(
        int $submissionId,
        int $reviewerUserId,
        int $teacherId,
        bool $isAdmin,
        string $status,
        ?float $score,
        ?float $maxScore,
        string $feedback
    ): void {
        if ($submissionId < 1) {
            throw new RuntimeException('Submission not found.');
        }
        if (!in_array($status, ['done', 'returned', 'submitted'], true)) {
            throw new RuntimeException('Invalid review status.');
        }

        $stmt = $this->pdo->prepare("
            SELECT sub.*, h.teacher_id, h.title, h.class_id
            FROM student_homework_submissions sub
            JOIN student_homework h ON h.id = sub.homework_id
            WHERE sub.id = ?
            LIMIT 1
        ");
        $stmt->execute([$submissionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Submission not found.');
        }
        if (!$isAdmin && (int)($row['teacher_id'] ?? 0) !== $teacherId && (int)($row['teacher_id'] ?? 0) !== 0) {
            throw new RuntimeException('You can only mark homework for your classes.');
        }

        $this->pdo->prepare("
            UPDATE student_homework_submissions
            SET status = ?, score = ?, max_score = ?, teacher_feedback = ?,
                reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ")->execute([
            $status,
            $score,
            $maxScore,
            $feedback !== '' ? $feedback : null,
            $reviewerUserId > 0 ? $reviewerUserId : null,
            $submissionId,
        ]);

        $studentId = (int)$row['student_id'];
        $title = (string)($row['title'] ?? 'Homework');
        $msg = $status === 'done'
            ? "Your homework \"{$title}\" was marked done."
            : "Your homework \"{$title}\" was returned with feedback.";
        if ($score !== null) {
            $msg .= ' Score: ' . rtrim(rtrim(number_format($score, 2), '0'), '.') .
                ($maxScore !== null ? '/' . rtrim(rtrim(number_format($maxScore, 2), '0'), '.') : '');
        }
        if ($feedback !== '') {
            $msg .= "\n" . $feedback;
        }

        if (function_exists('campus_notify_students')) {
            campus_notify_students($this->pdo, [$studentId], "✅ *Homework reviewed*\n\n{$msg}", 'HOMEWORK_MARKED');
        } elseif (function_exists('campus_portal_notify')) {
            campus_portal_notify($this->pdo, [$studentId], 'Homework reviewed', $msg, 'dashboard.php?tab=services', 'success');
        }
    }
}
