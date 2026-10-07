<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class TopicMasteryService
{
    public function __construct(private PDO $pdo) {}

    public function syncFromAttempt(int $attemptId): void
    {
        try {
            $s = $this->pdo->prepare('SELECT aa.student_id,a.subject_id,q.topic_key,q.topic_label,q.marks,ans.marks_awarded
                FROM assessment_attempts aa
                JOIN assessments a ON a.id=aa.assessment_id
                JOIN assessment_questions q ON q.assessment_id=a.id
                LEFT JOIN assessment_answers ans ON ans.attempt_id=aa.id AND ans.question_id=q.id
                WHERE aa.id=?');
            $s->execute([$attemptId]);
            $byTopic = [];
            $studentId = 0;
            $subjectId = null;
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $studentId = (int)$row['student_id'];
                $subjectId = ((int)($row['subject_id'] ?? 0)) ?: null;
                $key = AssessmentScoring::normalizeText((string)($row['topic_key'] ?: $row['topic_label'] ?: 'general')) ?: 'general';
                $byTopic[$key]['label'] = $row['topic_label'] ?: $key;
                $byTopic[$key]['marks'] = ($byTopic[$key]['marks'] ?? 0) + (float)$row['marks'];
                $byTopic[$key]['awarded'] = ($byTopic[$key]['awarded'] ?? 0) + (float)($row['marks_awarded'] ?? 0);
            }
            foreach ($byTopic as $key => $agg) {
                $percent = AssessmentScoring::percent((float)$agg['awarded'], (float)$agg['marks']);
                $this->upsert($studentId, $subjectId, $key, (string)$agg['label'], $percent, 'assessment:'.$attemptId);
            }
        } catch (Throwable $e) {
            error_log('topic mastery sync: '.$e->getMessage());
        }
    }

    public function upsert(int $studentId, ?int $subjectId, string $topicKey, string $label, ?float $percent, string $source): void
    {
        if ($studentId < 1 || $topicKey === '') {
            return;
        }
        $status = $this->statusFromPercent($percent);
        try {
            $this->pdo->prepare("
                INSERT INTO student_topic_progress(student_id,subject_id,topic_key,topic_label,status,score_percent,source,last_activity_at)
                VALUES(?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id),topic_label=VALUES(topic_label),status=VALUES(status),score_percent=VALUES(score_percent),source=VALUES(source),last_activity_at=NOW()
            ")->execute([$studentId, $subjectId, mb_substr($topicKey, 0, 120), mb_substr($label, 0, 255), $status, $percent, mb_substr($source, 0, 64)]);
        } catch (Throwable $e) {
        }
    }

    public function statusFromPercent(?float $percent): string
    {
        if ($percent === null) {
            return 'in_progress';
        }
        if ($percent >= 80) {
            return 'strong';
        }
        if ($percent >= 60) {
            return 'completed';
        }
        if ($percent >= 40) {
            return 'revision_required';
        }
        return 'weak';
    }

    /** @return array<string,mixed> */
    public function recommend(int $studentId): array
    {
        $academic = (new AcademicProgressService($this->pdo))->forStudent($studentId);
        $weak = $academic['weak_topics'];
        $labels = array_map(static fn($t) => (string)($t['topic_label'] ?? $t['topic_key'] ?? 'topic'), array_slice($weak, 0, 3));
        $text = $labels
            ? 'Complete targeted practice on '.implode(', ', $labels).' and then re-test those topics.'
            : 'No weak topics are recorded yet. Attempt a class test or revision quiz to generate recommendations.';
        try {
            (new NotificationCenterService($this->pdo))->create(
                'student',
                'exams',
                'Revision recommended',
                $text,
                BASE_URL.'student/exam_prep.php',
                $studentId,
                null,
                'normal',
                ['weak_topics' => $labels]
            );
        } catch (Throwable $e) {
        }
        return ['weak_topics' => $weak, 'action' => $text, 'link' => 'student/exam_prep.php'];
    }
}
