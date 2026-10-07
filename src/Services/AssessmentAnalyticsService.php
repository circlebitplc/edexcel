<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class AssessmentAnalyticsService
{
    public function __construct(private PDO $pdo) {}

    public function recalculate(int $assessmentId): void
    {
        $this->questionAnalysis($assessmentId);
        $this->classAnalysis($assessmentId);
    }

    /** @return list<array<string,mixed>> */
    public function questionAnalysis(int $assessmentId): array
    {
        $out = [];
        try {
            $s = $this->pdo->prepare("
                SELECT q.id,q.prompt,q.topic_label,q.difficulty,q.marks,
                       COUNT(ans.id) attempt_count,
                       AVG(ans.marks_awarded/NULLIF(q.marks,0)*100) average_percent,
                       AVG(ans.is_correct)*100 success_rate
                FROM assessment_questions q
                LEFT JOIN assessment_answers ans ON ans.question_id=q.id
                LEFT JOIN assessment_attempts aa ON aa.id=ans.attempt_id AND aa.status IN ('submitted','marking','marked')
                WHERE q.assessment_id=?
                GROUP BY q.id,q.prompt,q.topic_label,q.difficulty,q.marks
                ORDER BY q.sort_order
            ");
            $s->execute([$assessmentId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $success = $row['success_rate'] === null ? null : round((float)$row['success_rate'], 1);
                $row['difficulty_observed'] = AssessmentScoring::observedDifficulty($success);
                $row['common_errors'] = $this->commonErrors((int)$row['id']);
                $row['average_percent'] = $row['average_percent'] === null ? null : round((float)$row['average_percent'], 1);
                $row['success_rate'] = $success;
                $this->pdo->prepare("INSERT INTO assessment_question_analysis(assessment_id,question_id,attempt_count,average_percent,success_rate,difficulty_observed,common_errors_json) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE attempt_count=VALUES(attempt_count),average_percent=VALUES(average_percent),success_rate=VALUES(success_rate),difficulty_observed=VALUES(difficulty_observed),common_errors_json=VALUES(common_errors_json),calculated_at=NOW()")
                    ->execute([$assessmentId, (int)$row['id'], (int)$row['attempt_count'], $row['average_percent'], $success, $row['difficulty_observed'], json_encode($row['common_errors'])]);
                $out[] = $row;
            }
        } catch (Throwable $e) {
            error_log('question analysis: '.$e->getMessage());
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function classAnalysis(int $assessmentId): array
    {
        $assessment = (new AssessmentService($this->pdo))->get($assessmentId);
        $percents = [];
        try {
            $s = $this->pdo->prepare("SELECT percent FROM assessment_attempts WHERE assessment_id=? AND percent IS NOT NULL AND status IN ('submitted','marking','marked')");
            $s->execute([$assessmentId]);
            $percents = array_map('floatval', $s->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
        }
        sort($percents);
        $n = count($percents);
        $avg = $n ? round(array_sum($percents) / $n, 1) : null;
        $median = $n ? $percents[(int)floor(($n - 1) / 2)] : null;
        $dist = ['0-39' => 0, '40-59' => 0, '60-79' => 0, '80-100' => 0];
        foreach ($percents as $p) {
            if ($p < 40) {
                $dist['0-39']++;
            } elseif ($p < 60) {
                $dist['40-59']++;
            } elseif ($p < 80) {
                $dist['60-79']++;
            } else {
                $dist['80-100']++;
            }
        }
        $topics = $this->topicBreakdown($assessmentId);
        $revision = [];
        foreach ($topics as $t) {
            if (($t['average_percent'] ?? 100) < 50) {
                $revision[] = ($t['topic_label'] ?? 'Topic').' ('.($t['average_percent'] ?? 0).'% class average)';
            }
        }
        $row = [
            'assessment_id' => $assessmentId,
            'class_id' => $assessment['class_id'] ?? null,
            'participant_count' => $n,
            'average_percent' => $avg,
            'median_percent' => $median,
            'highest_percent' => $n ? max($percents) : null,
            'lowest_percent' => $n ? min($percents) : null,
            'distribution' => $dist,
            'topics' => $topics,
            'class_revision_topics' => $revision,
            'previous_average' => $this->previousAverage((int)($assessment['class_id'] ?? 0), $assessmentId),
        ];
        try {
            $this->pdo->prepare("INSERT INTO assessment_class_analysis(assessment_id,class_id,participant_count,average_percent,median_percent,highest_percent,lowest_percent,distribution_json,topic_json,class_revision_topics_json,previous_average) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE participant_count=VALUES(participant_count),average_percent=VALUES(average_percent),median_percent=VALUES(median_percent),highest_percent=VALUES(highest_percent),lowest_percent=VALUES(lowest_percent),distribution_json=VALUES(distribution_json),topic_json=VALUES(topic_json),class_revision_topics_json=VALUES(class_revision_topics_json),previous_average=VALUES(previous_average),calculated_at=NOW()")
                ->execute([$assessmentId, $row['class_id'], $n, $avg, $median, $row['highest_percent'], $row['lowest_percent'], json_encode($dist), json_encode($topics), json_encode($revision), $row['previous_average']]);
        } catch (Throwable $e) {
        }
        return $row;
    }

    /** @return array<string,mixed> */
    public function studentAttempt(int $attemptId): array
    {
        if ($attemptId < 1) {
            return ['score' => null, 'strong' => [], 'improve' => [], 'action' => 'Complete the assessment to see analysis.', 'disclaimer' => 'This is personal improvement feedback, not a predicted grade.'];
        }
        try {
            $s = $this->pdo->prepare('SELECT q.topic_label,q.marks,ans.marks_awarded,aa.percent,aa.student_id,aa.assessment_id
                FROM assessment_attempts aa
                JOIN assessment_questions q ON q.assessment_id=aa.assessment_id
                LEFT JOIN assessment_answers ans ON ans.attempt_id=aa.id AND ans.question_id=q.id
                WHERE aa.id=?');
            $s->execute([$attemptId]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return ['score' => null, 'strong' => [], 'improve' => [], 'action' => 'Analysis unavailable.', 'disclaimer' => 'This is personal improvement feedback, not a predicted grade.'];
        }
        $topics = [];
        $percent = null;
        $studentId = 0;
        foreach ($rows as $r) {
            $percent = $r['percent'] !== null ? (float)$r['percent'] : $percent;
            $studentId = (int)$r['student_id'];
            $label = (string)($r['topic_label'] ?: 'General');
            $topics[$label]['marks'] = ($topics[$label]['marks'] ?? 0) + (float)$r['marks'];
            $topics[$label]['awarded'] = ($topics[$label]['awarded'] ?? 0) + (float)($r['marks_awarded'] ?? 0);
        }
        $strong = [];
        $improve = [];
        foreach ($topics as $label => $agg) {
            $p = AssessmentScoring::percent((float)$agg['awarded'], (float)$agg['marks']);
            if ($p !== null && $p >= 70) {
                $strong[] = $label;
            } elseif ($p !== null && $p < 60) {
                $improve[] = $label;
            }
        }
        $action = $improve
            ? 'Complete the revision pack for '.implode(', ', array_slice($improve, 0, 3)).' and attempt targeted practice questions.'
            : 'Keep practising to maintain these strengths. Review the exam-prep plan next.';
        $rec = $studentId > 0 ? (new TopicMasteryService($this->pdo))->recommend($studentId) : ['link' => 'student/exam_prep.php'];
        return [
            'score' => $percent,
            'strong' => $strong,
            'improve' => $improve,
            'action' => $action,
            'link' => $rec['link'] ?? 'student/exam_prep.php',
            'disclaimer' => 'This is personal improvement feedback, not a predicted examination grade.',
        ];
    }

    /** @return list<array<string,mixed>> */
    public function studentTrend(int $studentId): array
    {
        try {
            $s = $this->pdo->prepare("SELECT a.title,a.assessment_type,aa.percent,aa.submitted_at FROM assessment_attempts aa JOIN assessments a ON a.id=aa.assessment_id WHERE aa.student_id=? AND aa.percent IS NOT NULL ORDER BY aa.submitted_at,aa.id");
            $s->execute([$studentId]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }
        $percents = array_map(static fn($r) => (float)$r['percent'], $rows);
        $trend = AssessmentScoring::trend($percents);
        $weak = (new AcademicProgressService($this->pdo))->forStudent($studentId)['weak_topics'] ?? [];
        $labels = array_map(static fn($t) => (string)($t['topic_label'] ?? $t['topic_key'] ?? 'topic'), array_slice($weak, 0, 3));
        return ['points' => $rows, 'trend' => $trend, 'wording' => AssessmentScoring::predictiveWording($trend, $labels)];
    }

    /** @return list<array<string,mixed>> */
    public function adminSummary(string $from, string $to): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        try {
            $s = $this->pdo->prepare("SELECT COALESCE(s.name,'Unassigned') subject_name,COALESCE(a.qualification_label,'—') qualification_label,COUNT(DISTINCT aa.id) attempts,AVG(aa.percent) average_percent,AVG(aa.passed)*100 pass_rate
                FROM assessment_attempts aa
                JOIN assessments a ON a.id=aa.assessment_id
                LEFT JOIN subjects s ON s.id=a.subject_id
                WHERE aa.submitted_at BETWEEN ? AND ?
                GROUP BY s.name,a.qualification_label
                ORDER BY average_percent DESC");
            $s->execute([$from.' 00:00:00', $to.' 23:59:59']);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function comparison(int $studentId, int $assessmentId, array $auth): array
    {
        if (!(new AssessmentService($this->pdo))->canViewStudent($auth, $studentId)) {
            return [];
        }
        $mine = null;
        $classAvg = null;
        try {
            $s = $this->pdo->prepare("SELECT percent FROM assessment_attempts WHERE assessment_id=? AND student_id=? AND percent IS NOT NULL ORDER BY id DESC LIMIT 1");
            $s->execute([$assessmentId, $studentId]);
            $mine = $s->fetchColumn();
            $s = $this->pdo->prepare("SELECT AVG(percent) FROM assessment_attempts WHERE assessment_id=? AND percent IS NOT NULL");
            $s->execute([$assessmentId]);
            $classAvg = $s->fetchColumn();
        } catch (Throwable $e) {
        }
        return [
            'student_percent' => $mine === false || $mine === null ? null : (float)$mine,
            'class_average' => $classAvg === false || $classAvg === null ? null : round((float)$classAvg, 1),
            'note' => 'Shown to authorized teachers only. This is not a public ranking.',
        ];
    }

    /** @return list<array<string,mixed>> */
    private function topicBreakdown(int $assessmentId): array
    {
        try {
            $s = $this->pdo->prepare("SELECT q.topic_label,AVG(ans.marks_awarded/NULLIF(q.marks,0)*100) average_percent,COUNT(*) answers
                FROM assessment_questions q
                JOIN assessment_answers ans ON ans.question_id=q.id
                WHERE q.assessment_id=? AND q.topic_label IS NOT NULL
                GROUP BY q.topic_label");
            $s->execute([$assessmentId]);
            $out = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $r['average_percent'] = $r['average_percent'] === null ? null : round((float)$r['average_percent'], 1);
                $out[] = $r;
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<string> */
    private function commonErrors(int $questionId): array
    {
        try {
            $s = $this->pdo->prepare("SELECT choice_index,COUNT(*) c FROM assessment_answers WHERE question_id=? AND is_correct=0 AND choice_index IS NOT NULL GROUP BY choice_index ORDER BY c DESC LIMIT 3");
            $s->execute([$questionId]);
            $out = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[] = 'Incorrect option '.((int)$r['choice_index'] + 1).' selected '.$r['c'].' time(s)';
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function previousAverage(int $classId, int $currentId): ?float
    {
        if ($classId < 1) {
            return null;
        }
        try {
            $s = $this->pdo->prepare("SELECT AVG(aa.percent) FROM assessment_attempts aa JOIN assessments a ON a.id=aa.assessment_id WHERE a.class_id=? AND a.id<>? AND aa.percent IS NOT NULL");
            $s->execute([$classId, $currentId]);
            $v = $s->fetchColumn();
            return $v === false || $v === null ? null : round((float)$v, 1);
        } catch (Throwable $e) {
            return null;
        }
    }
}
