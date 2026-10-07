<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Academic progress aggregates for a student.
 * Never invents data — empty arrays / nulls when no rows exist.
 */
final class AcademicProgressService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *   subject_averages: list<array<string,mixed>>,
     *   attendance_percent: ?float,
     *   attendance: array{present:int,absent:int,late:int,excused:int,total:int},
     *   homework: array{assigned:int,submitted:int,completion_percent:?float},
     *   recent_marks: list<array<string,mixed>>,
     *   weak_topics: list<array<string,mixed>>,
     *   strong_topics: list<array<string,mixed>>
     * }
     */
    public function forStudent(int $studentId): array
    {
        $empty = [
            'subject_averages' => [],
            'attendance_percent' => null,
            'attendance' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0],
            'homework' => ['assigned' => 0, 'submitted' => 0, 'completion_percent' => null],
            'recent_marks' => [],
            'weak_topics' => [],
            'strong_topics' => [],
        ];
        if ($studentId < 1) {
            return $empty;
        }

        return [
            'subject_averages' => $this->subjectAverages($studentId),
            'attendance_percent' => $this->attendancePercent($studentId)['percent'],
            'attendance' => $this->attendancePercent($studentId),
            'homework' => $this->homeworkCompletion($studentId),
            'recent_marks' => $this->recentMarks($studentId),
            'weak_topics' => $this->topicsByStatus($studentId, ['weak', 'revision_required']),
            'strong_topics' => $this->topicsByStatus($studentId, ['strong', 'completed']),
        ];
    }

    /**
     * @return list<array{subject_id:?int,subject_name:string,average_percent:float,count:int}>
     */
    private function subjectAverages(int $studentId): array
    {
        try {
            $hasPublished = $this->columnExists('student_progress', 'published');
            $pub = $hasPublished ? ' AND COALESCE(p.published, 1) = 1' : '';
            $stmt = $this->pdo->prepare("
                SELECT
                    p.subject_id,
                    COALESCE(s.name, 'Subject') AS subject_name,
                    AVG(p.score / NULLIF(p.max_score, 0) * 100) AS average_percent,
                    COUNT(*) AS cnt
                FROM student_progress p
                LEFT JOIN subjects s ON s.id = p.subject_id
                WHERE p.student_id = ?
                  AND p.max_score > 0
                  {$pub}
                GROUP BY p.subject_id, s.name
                HAVING COUNT(*) > 0
                ORDER BY subject_name
            ");
            $stmt->execute([$studentId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[] = [
                    'subject_id' => isset($row['subject_id']) ? (int)$row['subject_id'] : null,
                    'subject_name' => (string)($row['subject_name'] ?? 'Subject'),
                    'average_percent' => round((float)$row['average_percent'], 1),
                    'count' => (int)$row['cnt'],
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{present:int,absent:int,late:int,excused:int,total:int,percent:?float}
     */
    private function attendancePercent(int $studentId): array
    {
        $base = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'percent' => null];
        try {
            $stmt = $this->pdo->prepare("
                SELECT sa.status, COUNT(*) AS cnt
                FROM student_attendance sa
                JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
                WHERE sa.student_id = ?
                GROUP BY sa.status
            ");
            $stmt->execute([$studentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $st = strtolower((string)($row['status'] ?? ''));
                if (isset($base[$st])) {
                    $base[$st] = (int)$row['cnt'];
                }
            }
            $base['total'] = $base['present'] + $base['absent'] + $base['late'] + $base['excused'];
            if ($base['total'] > 0) {
                $attended = $base['present'] + $base['late'];
                $base['percent'] = round(($attended / $base['total']) * 100, 1);
            }
            return $base;
        } catch (Throwable $e) {
            return $base;
        }
    }

    /**
     * @return array{assigned:int,submitted:int,completion_percent:?float}
     */
    private function homeworkCompletion(int $studentId): array
    {
        $out = ['assigned' => 0, 'submitted' => 0, 'completion_percent' => null];
        try {
            // Homework assigned to classes the student is enrolled in, or matching enrolled subjects.
            $assignedStmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT h.id)
                FROM student_homework h
                WHERE h.class_id IN (SELECT class_id FROM student_enrollments WHERE student_id = ?)
                   OR (
                        h.subject_id IS NOT NULL
                        AND h.subject_id IN (
                            SELECT DISTINCT tt.subject_id
                            FROM timetable tt
                            JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
                            WHERE tt.deleted_at IS NULL
                        )
                   )
            ");
            $assignedStmt->execute([$studentId, $studentId]);
            $assigned = (int)$assignedStmt->fetchColumn();

            $subStmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM student_homework_submissions
                WHERE student_id = ? AND status IN ('submitted','returned','done')
            ");
            $subStmt->execute([$studentId]);
            $submitted = (int)$subStmt->fetchColumn();

            $out['assigned'] = $assigned;
            $out['submitted'] = $submitted;
            if ($assigned > 0) {
                $out['completion_percent'] = round(min(100, ($submitted / $assigned) * 100), 1);
            }
            return $out;
        } catch (Throwable $e) {
            return $out;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function recentMarks(int $studentId, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $hasPublished = $this->columnExists('student_progress', 'published');
            $pub = $hasPublished ? ' AND COALESCE(p.published, 1) = 1' : '';
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    p.metric,
                    p.score,
                    p.max_score,
                    p.recorded_at,
                    p.note,
                    COALESCE(s.name, '') AS subject_name,
                    CASE WHEN p.max_score > 0 THEN ROUND(p.score / p.max_score * 100, 1) ELSE NULL END AS percent
                FROM student_progress p
                LEFT JOIN subjects s ON s.id = p.subject_id
                WHERE p.student_id = ?
                  {$pub}
                ORDER BY p.recorded_at DESC, p.id DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<string> $statuses
     * @return list<array<string,mixed>>
     */
    private function topicsByStatus(int $studentId, array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $params = array_merge([$studentId], $statuses);
            $stmt = $this->pdo->prepare("
                SELECT
                    t.id,
                    t.topic_key,
                    t.topic_label,
                    t.status,
                    t.score_percent,
                    t.subject_id,
                    COALESCE(s.name, '') AS subject_name,
                    t.last_activity_at
                FROM student_topic_progress t
                LEFT JOIN subjects s ON s.id = t.subject_id
                WHERE t.student_id = ?
                  AND t.status IN ({$placeholders})
                ORDER BY t.last_activity_at DESC, t.id DESC
                LIMIT 40
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
