<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Exam preparation dashboard + revision plans.
 * Official exam dates come only from OfficialExamService / exams tables — never invented.
 */
final class ExamPrepService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *   papers: list<array<string,mixed>>,
     *   next_exam: ?array<string,mixed>,
     *   prep_progress: array{topic_percent:?float,homework_percent:?float,overall:?float},
     *   weak_areas: list<array<string,mixed>>,
     *   recommendations: list<string>,
     *   plans: list<array<string,mixed>>
     * }
     */
    public function dashboard(int $studentId): array
    {
        $empty = [
            'papers' => [],
            'next_exam' => null,
            'prep_progress' => ['topic_percent' => null, 'homework_percent' => null, 'overall' => null],
            'weak_areas' => [],
            'recommendations' => [],
            'plans' => [],
        ];
        if ($studentId < 1) {
            return $empty;
        }

        $official = new OfficialExamService($this->pdo);
        try {
            $official->ensureSchema();
        } catch (Throwable $e) {
        }

        $papers = [];
        try {
            $papers = $official->studentTimetable($studentId);
        } catch (Throwable $e) {
            $papers = [];
        }

        $next = null;
        $decorated = [];
        foreach ($papers as $paper) {
            $cd = $official->countdown($paper);
            $paper['days_remaining'] = !empty($cd['past']) ? null : (int)$cd['days'];
            $paper['countdown'] = $cd;
            $decorated[] = $paper;
            if ($next === null && empty($cd['past'])) {
                $next = $paper;
            }
        }
        $papers = $decorated;

        $progress = new AcademicProgressService($this->pdo);
        $academic = $progress->forStudent($studentId);
        $weak = $academic['weak_topics'];
        $hwPct = $academic['homework']['completion_percent'];
        $topicPct = $this->topicCompletionPercent($studentId);

        $overall = null;
        $parts = array_filter([$topicPct, $hwPct], static fn($v) => $v !== null);
        if ($parts !== []) {
            $overall = round(array_sum($parts) / count($parts), 1);
        }

        $recommendations = $this->buildRecommendations($papers, $weak, $topicPct, $hwPct, $next);

        return [
            'papers' => $papers,
            'next_exam' => $next,
            'prep_progress' => [
                'topic_percent' => $topicPct,
                'homework_percent' => $hwPct,
                'overall' => $overall,
            ],
            'weak_areas' => $weak,
            'recommendations' => $recommendations,
            'plans' => $this->listPlans($studentId),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function createPlan(int $studentId, array $data): int
    {
        if ($studentId < 1) {
            throw new RuntimeException('Invalid student.');
        }
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Plan title is required.');
        }
        $examDate = trim((string)($data['exam_date'] ?? ''));
        if ($examDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
            throw new RuntimeException('Exam date must be YYYY-MM-DD or empty.');
        }
        // Only allow exam_date if it matches an official selected exam for this student.
        if ($examDate !== '') {
            $ok = $this->officialDateOwnedByStudent($studentId, $examDate);
            if (!$ok) {
                throw new RuntimeException('Exam date must match an official paper on your timetable.');
            }
        } else {
            $examDate = null;
        }

        $planItems = $data['plan'] ?? $data['plan_json'] ?? [];
        if (is_string($planItems)) {
            $decoded = json_decode($planItems, true);
            $planItems = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($planItems)) {
            $planItems = [];
        }
        $progress = (float)($data['progress_percent'] ?? 0);
        $progress = max(0, min(100, $progress));

        $this->pdo->prepare("
            INSERT INTO exam_revision_plans
                (student_id, qualification_label, subject_label, exam_date, title, plan_json, progress_percent, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
        ")->execute([
            $studentId,
            trim((string)($data['qualification_label'] ?? '')) ?: null,
            trim((string)($data['subject_label'] ?? '')) ?: null,
            $examDate,
            mb_substr($title, 0, 255),
            json_encode(array_values($planItems)),
            $progress,
            trim((string)($data['created_by'] ?? 'student')) ?: 'student',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listPlans(int $studentId, string $status = ''): array
    {
        if ($studentId < 1) {
            return [];
        }
        try {
            $sql = 'SELECT * FROM exam_revision_plans WHERE student_id = ?';
            $params = [$studentId];
            if ($status !== '') {
                $sql .= ' AND status = ?';
                $params[] = $status;
            }
            $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 50';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $plan = json_decode((string)($row['plan_json'] ?? '[]'), true);
                $row['plan'] = is_array($plan) ? $plan : [];
            }
            unset($row);
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function updatePlanProgress(int $studentId, int $planId, float $percent): bool
    {
        if ($studentId < 1 || $planId < 1) {
            return false;
        }
        $percent = max(0, min(100, $percent));
        try {
            $stmt = $this->pdo->prepare("
                UPDATE exam_revision_plans
                SET progress_percent = ?, updated_at = NOW()
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$percent, $planId, $studentId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function officialDateOwnedByStudent(int $studentId, string $examDate): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM student_exam_selections sel
                JOIN exams e ON e.id = sel.exam_id
                WHERE sel.student_id = ? AND e.exam_date = ?
                LIMIT 1
            ");
            $stmt->execute([$studentId, $examDate]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function topicCompletionPercent(int $studentId): ?float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status IN ('completed','strong') THEN 1 ELSE 0 END) AS done
                FROM student_topic_progress
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['total'] < 1) {
                return null;
            }
            return round(((int)$row['done'] / (int)$row['total']) * 100, 1);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<array<string,mixed>> $papers
     * @param list<array<string,mixed>> $weak
     * @return list<string>
     */
    private function buildRecommendations(array $papers, array $weak, ?float $topicPct, ?float $hwPct, ?array $next): array
    {
        $out = [];
        if ($papers === []) {
            $out[] = 'Add official exam papers on the Exams tab so prep can track real dates.';
            return $out;
        }
        if ($next !== null && isset($next['days_remaining']) && $next['days_remaining'] !== null && $next['days_remaining'] <= 14) {
            $out[] = 'Your next official paper is within two weeks — prioritise weak topics and past papers.';
        }
        if ($weak !== []) {
            $labels = [];
            foreach (array_slice($weak, 0, 3) as $t) {
                $labels[] = (string)($t['topic_label'] ?? $t['topic_key'] ?? 'topic');
            }
            $out[] = 'Focus revision on: ' . implode(', ', $labels) . '.';
        }
        if ($hwPct !== null && $hwPct < 70) {
            $out[] = 'Homework completion is below 70%. Finish outstanding papers before new revision.';
        }
        if ($topicPct !== null && $topicPct < 50) {
            $out[] = 'Fewer than half of tracked topics are strong/completed — schedule daily topic drills.';
        }
        if ($out === []) {
            $out[] = 'Keep reviewing selected papers and update your revision plan weekly.';
        }
        return $out;
    }
}
