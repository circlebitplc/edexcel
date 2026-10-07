<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class ExamReadinessService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function forStudent(int $studentId): array
    {
        $prep = (new ExamPrepService($this->pdo))->dashboard($studentId);
        $papers = $prep['papers'] ?? [];
        $paper = $prep['next_exam'] ?? ($papers[0] ?? null);
        $examId = $paper['id'] ?? $paper['exam_id'] ?? null;
        $syllabus = $prep['prep_progress']['topic_percent'] ?? null;
        $homework = $prep['prep_progress']['homework_percent'] ?? null;
        $academic = (new AcademicProgressService($this->pdo))->forStudent($studentId);
        $topicMastery = $this->topicMastery($academic);
        $recent = $this->recentAssessments($studentId);
        $mocks = $this->mockAverage($studentId);
        $past = $this->pastPaper($studentId, $examId);
        $improvement = $this->improvement($studentId, $examId);
        $consistency = $this->consistency($studentId);
        $weakPenalty = $this->weakPenalty($academic['weak_topics'] ?? []);
        $timeRemaining = $this->timeRemaining($paper);
        $components = [
            'syllabus_coverage' => $syllabus,
            'topic_mastery' => $topicMastery ?? $syllabus,
            'recent_assessment_performance' => $recent,
            'mock_exams' => $mocks,
            'past_paper_performance' => $past,
            'recent_improvement' => $improvement,
            'revision_consistency' => $consistency,
            'weak_topic_penalty' => $weakPenalty,
            'time_remaining_factor' => $timeRemaining,
            'homework_completion' => $homework,
        ];
        $score = AssessmentScoring::readiness([
            $components['topic_mastery'],
            $recent,
            $mocks ?? $past,
            $improvement,
            $consistency,
            $weakPenalty,
            $timeRemaining,
        ]) ?? $homework;
        $out = [
            'student_id' => $studentId,
            'exam' => $paper,
            'readiness_score' => $score,
            'components' => $components,
            'disclaimer' => 'Exam readiness is a learning indicator from recent work. It is not a guaranteed prediction of the official examination result.',
        ];
        try {
            $this->pdo->prepare("INSERT INTO exam_readiness_snapshots(student_id,official_exam_id,readiness_score,syllabus_coverage,topic_mastery,past_paper_performance,recent_improvement,revision_consistency,components_json) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE readiness_score=VALUES(readiness_score),syllabus_coverage=VALUES(syllabus_coverage),topic_mastery=VALUES(topic_mastery),past_paper_performance=VALUES(past_paper_performance),recent_improvement=VALUES(recent_improvement),revision_consistency=VALUES(revision_consistency),components_json=VALUES(components_json),calculated_at=NOW()")
                ->execute([$studentId, $examId, $score, $syllabus, $components['topic_mastery'], $past, $improvement, $consistency, json_encode($components)]);
        } catch (Throwable $e) {
            error_log('exam readiness persist: '.$e->getMessage());
        }
        return $out;
    }

    private function topicMastery(array $academic): ?float
    {
        $topics = array_merge($academic['weak_topics'] ?? [], $academic['strong_topics'] ?? []);
        if ($topics === []) {
            return null;
        }
        $vals = [];
        foreach ($topics as $t) {
            if (isset($t['score_percent']) && $t['score_percent'] !== null) {
                $vals[] = (float)$t['score_percent'];
            }
        }
        return $vals ? round(array_sum($vals) / count($vals), 1) : null;
    }

    private function recentAssessments(int $studentId): ?float
    {
        try {
            $s = $this->pdo->prepare("SELECT AVG(percent) FROM assessment_attempts WHERE student_id=? AND percent IS NOT NULL AND submitted_at>=DATE_SUB(NOW(),INTERVAL 60 DAY)");
            $s->execute([$studentId]);
            $v = $s->fetchColumn();
            return $v === false || $v === null ? null : round((float)$v, 1);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function mockAverage(int $studentId): ?float
    {
        try {
            $s = $this->pdo->prepare("SELECT AVG(aa.percent) FROM assessment_attempts aa JOIN assessments a ON a.id=aa.assessment_id WHERE aa.student_id=? AND a.assessment_type='mock' AND aa.percent IS NOT NULL");
            $s->execute([$studentId]);
            $v = $s->fetchColumn();
            return $v === false || $v === null ? null : round((float)$v, 1);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function weakPenalty(array $weak): ?float
    {
        if ($weak === []) {
            return 100.0;
        }
        return max(20.0, 100.0 - count($weak) * 8);
    }

    private function timeRemaining(?array $paper): ?float
    {
        $date = $paper['exam_date'] ?? $paper['date'] ?? null;
        if (!$date) {
            return null;
        }
        $days = (int)floor((strtotime((string)$date) - time()) / 86400);
        if ($days < 0) {
            return 40.0;
        }
        if ($days > 90) {
            return 90.0;
        }
        return max(40.0, min(95.0, 50 + $days * 0.4));
    }

    private function pastPaper(int $studentId, $examId): ?float
    {
        try {
            $sql = 'SELECT AVG(score/NULLIF(max_score,0)*100) FROM paper_attempts WHERE student_id=?';
            $p = [$studentId];
            if ($examId) {
                $sql .= ' AND official_exam_id=?';
                $p[] = $examId;
            }
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            $v = $s->fetchColumn();
            return $v === null || $v === false ? null : round((float)$v, 1);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function improvement(int $studentId, $examId): ?float
    {
        try {
            $sql = 'SELECT score/NULLIF(max_score,0)*100 pct FROM paper_attempts WHERE student_id=?';
            $p = [$studentId];
            if ($examId) {
                $sql .= ' AND official_exam_id=?';
                $p[] = $examId;
            }
            $sql .= ' ORDER BY attempt_date DESC,id DESC LIMIT 2';
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            $r = $s->fetchAll(PDO::FETCH_COLUMN) ?: [];
            return count($r) >= 2 ? round(max(0, min(100, 100 + ((float)$r[0] - (float)$r[1]) * 2)), 1) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function consistency(int $studentId): ?float
    {
        try {
            $s = $this->pdo->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM courso_activity WHERE student_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");
            $s->execute([$studentId]);
            $n = (int)$s->fetchColumn();
            return min(100, round($n / 20 * 100, 1));
        } catch (Throwable $e) {
            return null;
        }
    }
}
