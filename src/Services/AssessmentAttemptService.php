<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AssessmentAttemptService
{
    public function __construct(private PDO $pdo) {}

    public function canAttempt(array $assessment, int $studentId): void
    {
        if (($assessment['status'] ?? '') !== 'published') {
            throw new RuntimeException('This assessment is not open.');
        }
        $now = time();
        if (!empty($assessment['start_at']) && strtotime((string)$assessment['start_at']) > $now) {
            throw new RuntimeException('This assessment has not started yet.');
        }
        if (!empty($assessment['end_at']) && strtotime((string)$assessment['end_at']) < $now) {
            throw new RuntimeException('This assessment has closed.');
        }
        $classId = (int)($assessment['class_id'] ?? 0);
        if ($classId > 0 && !$this->enrolled($studentId, $classId)) {
            throw new RuntimeException('You are not enrolled in this class.');
        }
        $limit = max(1, (int)($assessment['attempt_limit'] ?? 1));
        if ($this->completedCount((int)$assessment['id'], $studentId) >= $limit && $this->openAttempt((int)$assessment['id'], $studentId) === null) {
            throw new RuntimeException('Attempt limit reached.');
        }
    }

    /** @return array<string,mixed> */
    public function start(int $assessmentId, int $studentId, array $meta = []): array
    {
        $assessment = (new AssessmentService($this->pdo))->get($assessmentId);
        if (!$assessment) {
            throw new RuntimeException('Assessment not found.');
        }
        $open = $this->openAttempt($assessmentId, $studentId);
        if ($open) {
            return $this->payload($assessment, $open, $studentId);
        }
        $this->canAttempt($assessment, $studentId);
        $attemptNo = $this->completedCount($assessmentId, $studentId) + 1;
        $expires = date('Y-m-d H:i:s', time() + max(60, (int)$assessment['duration_minutes'] * 60));
        try {
            $this->pdo->prepare("
                INSERT INTO assessment_attempts(assessment_id,student_id,attempt_no,status,expires_at,ip_address,user_agent)
                VALUES(?,?,?,'in_progress',?,?,?)
            ")->execute([
                $assessmentId,
                $studentId,
                $attemptNo,
                $expires,
                mb_substr((string)($meta['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
                mb_substr((string)($meta['ua'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            $open = $this->openAttempt($assessmentId, $studentId);
            if ($open) {
                return $this->payload($assessment, $open, $studentId);
            }
            throw new RuntimeException('Could not start the attempt.');
        }
        $attempt = $this->openAttempt($assessmentId, $studentId);
        $this->event((int)$attempt['id'], 'started', 'Attempt opened');
        return $this->payload($assessment, $attempt ?: [], $studentId);
    }

    /** @param array<int,array<string,mixed>> $answers */
    public function save(int $attemptId, int $studentId, array $answers, array $flags = []): array
    {
        $attempt = $this->getAttempt($attemptId);
        if (!$attempt || (int)$attempt['student_id'] !== $studentId) {
            throw new RuntimeException('Attempt access denied.');
        }
        if ($attempt['status'] !== 'in_progress') {
            return ['saved' => 0, 'status' => $attempt['status'], 'recovered' => true];
        }
        if (!empty($attempt['expires_at']) && strtotime((string)$attempt['expires_at']) < time()) {
            $this->submit($attemptId, $studentId, true);
            return ['saved' => 0, 'status' => 'submitted', 'expired' => true];
        }
        $saved = 0;
        foreach ($answers as $questionId => $payload) {
            $qid = (int)$questionId;
            if ($qid < 1) {
                continue;
            }
            $choice = is_array($payload) ? ($payload['choice_index'] ?? null) : $payload;
            $text = is_array($payload) ? ($payload['answer_text'] ?? null) : null;
            $flag = is_array($payload) ? (!empty($payload['flagged']) ? 1 : 0) : (isset($flags[$qid]) ? 1 : 0);
            $this->pdo->prepare("
                INSERT INTO assessment_answers(attempt_id,question_id,choice_index,answer_text,flagged,saved_at)
                VALUES(?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE choice_index=VALUES(choice_index),answer_text=VALUES(answer_text),flagged=VALUES(flagged),saved_at=NOW()
            ")->execute([$attemptId, $qid, $choice === null || $choice === '' ? null : (int)$choice, $text !== null ? mb_substr((string)$text, 0, 8000) : null, $flag]);
            $saved++;
        }
        $this->pdo->prepare('UPDATE assessment_attempts SET last_saved_at=NOW() WHERE id=?')->execute([$attemptId]);
        return ['saved' => $saved, 'status' => 'in_progress'];
    }

    public function submit(int $attemptId, int $studentId, bool $auto = false): array
    {
        $attempt = $this->getAttempt($attemptId);
        if (!$attempt || (int)$attempt['student_id'] !== $studentId) {
            throw new RuntimeException('Attempt access denied.');
        }
        if (in_array($attempt['status'], ['submitted', 'marking', 'marked'], true)) {
            return $this->resultPayload((int)$attempt['assessment_id'], $attempt);
        }
        $this->pdo->beginTransaction();
        try {
            $locked = $this->pdo->prepare('SELECT * FROM assessment_attempts WHERE id=? FOR UPDATE');
            $locked->execute([$attemptId]);
            $attempt = $locked->fetch(PDO::FETCH_ASSOC);
            if (!$attempt || in_array($attempt['status'], ['submitted', 'marking', 'marked'], true)) {
                $this->pdo->commit();
                return $this->resultPayload((int)$attempt['assessment_id'], $attempt ?: []);
            }
            $marking = new AssessmentMarkingService($this->pdo);
            $scored = $marking->autoMarkAttempt($attemptId);
            $status = $scored['needs_teacher'] ? 'marking' : 'marked';
            $this->pdo->prepare("UPDATE assessment_attempts SET status=?,submitted_at=NOW(),score=?,max_score=?,percent=?,passed=? WHERE id=?")
                ->execute([$status, $scored['score'], $scored['max'], $scored['percent'], $scored['passed'] ? 1 : 0, $attemptId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $this->event($attemptId, $auto ? 'auto_submitted' : 'submitted', $auto ? 'Submitted after expiry or recovery' : 'Student submitted');
        $fresh = $this->getAttempt($attemptId) ?: $attempt;
        (new TopicMasteryService($this->pdo))->syncFromAttempt($attemptId);
        $this->recordPaperAttempt($fresh);
        $this->writeProgress($fresh);
        if ($status === 'marked') {
            (new AssessmentAnalyticsService($this->pdo))->recalculate((int)$fresh['assessment_id']);
            $this->notifyResult($fresh);
        }
        return $this->resultPayload((int)$fresh['assessment_id'], $fresh);
    }

    public function event(int $attemptId, string $type, ?string $detail = null): void
    {
        try {
            $this->pdo->prepare('INSERT INTO assessment_attempt_events(attempt_id,event_type,detail) VALUES(?,?,?)')
                ->execute([$attemptId, mb_substr($type, 0, 60), $detail !== null ? mb_substr($detail, 0, 500) : null]);
        } catch (Throwable $e) {
        }
    }

    /** @return array<string,mixed>|null */
    public function openAttempt(int $assessmentId, int $studentId): ?array
    {
        try {
            $s = $this->pdo->prepare("SELECT * FROM assessment_attempts WHERE assessment_id=? AND student_id=? AND status='in_progress' ORDER BY id DESC LIMIT 1");
            $s->execute([$assessmentId, $studentId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function latestForStudent(int $assessmentId, int $studentId): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM assessment_attempts WHERE assessment_id=? AND student_id=? ORDER BY attempt_no DESC,id DESC LIMIT 1');
            $s->execute([$assessmentId, $studentId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function getAttempt(int $id): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM assessment_attempts WHERE id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function answers(int $attemptId): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM assessment_answers WHERE attempt_id=?');
            $s->execute([$attemptId]);
            $out = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(int)$row['question_id']] = $row;
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed> */
    public function payload(array $assessment, array $attempt, int $studentId): array
    {
        $questions = (new AssessmentService($this->pdo))->questions((int)$assessment['id']);
        $seed = (int)($attempt['id'] ?? 0) + $studentId;
        if (!empty($assessment['randomize_questions'])) {
            $questions = AssessmentScoring::shuffleSeeded($questions, $seed);
        }
        $saved = isset($attempt['id']) ? $this->answers((int)$attempt['id']) : [];
        $safe = [];
        foreach ($questions as $q) {
            $choices = json_decode((string)($q['choices_json'] ?? '[]'), true) ?: [];
            if (!empty($assessment['randomize_answers']) && is_array($choices)) {
                $choices = AssessmentScoring::shuffleSeeded($choices, $seed + (int)$q['id']);
            }
            $ans = $saved[(int)$q['id']] ?? null;
            $safe[] = [
                'id' => (int)$q['id'],
                'sort_order' => (int)$q['sort_order'],
                'question_type' => $q['question_type'],
                'topic_label' => $q['topic_label'],
                'marks' => (float)$q['marks'],
                'prompt' => $q['prompt'],
                'choices' => $choices,
                'answer' => $ans ? ['choice_index' => $ans['choice_index'], 'answer_text' => $ans['answer_text'], 'flagged' => (int)$ans['flagged']] : null,
            ];
        }
        if (!empty($assessment['adaptive']) && $attempt) {
            $safe = $this->adaptiveSlice($questions, $saved, $safe);
        }
        return [
            'assessment' => [
                'id' => (int)$assessment['id'],
                'title' => $assessment['title'],
                'instructions' => $assessment['instructions'],
                'duration_minutes' => (int)$assessment['duration_minutes'],
                'copy_controls' => (int)$assessment['copy_controls'],
                'adaptive' => (int)$assessment['adaptive'],
                'security_note' => 'These are technical controls (timer, session, save, optional copy restriction). They are not a substitute for invigilation.',
            ],
            'attempt' => [
                'id' => (int)($attempt['id'] ?? 0),
                'status' => $attempt['status'] ?? 'in_progress',
                'expires_at' => $attempt['expires_at'] ?? null,
                'started_at' => $attempt['started_at'] ?? null,
            ],
            'questions' => $safe,
        ];
    }

    /** @return array<string,mixed> */
    public function resultPayload(int $assessmentId, array $attempt): array
    {
        $assessment = (new AssessmentService($this->pdo))->get($assessmentId) ?: ['id' => $assessmentId];
        return [
            'assessment' => $assessment,
            'attempt' => $attempt,
            'analysis' => (new AssessmentAnalyticsService($this->pdo))->studentAttempt((int)($attempt['id'] ?? 0)),
        ];
    }

    private function adaptiveSlice(array $all, array $saved, array $safe): array
    {
        $answered = count($saved);
        if ($answered === 0) {
            foreach ($safe as $q) {
                if (($q['question_type'] ?? '') !== '') {
                    return [$q];
                }
            }
        }
        return $safe;
    }

    private function enrolled(int $studentId, int $classId): bool
    {
        try {
            $s = $this->pdo->prepare('SELECT 1 FROM student_enrollments WHERE student_id=? AND class_id=? LIMIT 1');
            $s->execute([$studentId, $classId]);
            return (bool)$s->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function completedCount(int $assessmentId, int $studentId): int
    {
        try {
            $s = $this->pdo->prepare("SELECT COUNT(*) FROM assessment_attempts WHERE assessment_id=? AND student_id=? AND status IN ('submitted','marking','marked')");
            $s->execute([$assessmentId, $studentId]);
            return (int)$s->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function recordPaperAttempt(array $attempt): void
    {
        $assessment = (new AssessmentService($this->pdo))->get((int)$attempt['assessment_id']);
        if (!$assessment) {
            return;
        }
        try {
            $this->pdo->prepare('INSERT INTO paper_attempts(student_id,official_exam_id,paper_label,topic_label,attempt_date,score,max_score,notes) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([
                    (int)$attempt['student_id'],
                    ((int)($assessment['official_exam_id'] ?? 0)) ?: null,
                    (string)$assessment['title'],
                    (string)($assessment['unit_label'] ?? $assessment['assessment_type']),
                    date('Y-m-d'),
                    $attempt['score'],
                    $attempt['max_score'],
                    'assessment:'.$attempt['id'],
                ]);
        } catch (Throwable $e) {
        }
    }

    private function writeProgress(array $attempt): void
    {
        $assessment = (new AssessmentService($this->pdo))->get((int)$attempt['assessment_id']);
        if (!$assessment || $attempt['score'] === null) {
            return;
        }
        try {
            $this->pdo->prepare('INSERT INTO student_progress(student_id,class_id,subject_id,exam_id,metric,score,max_score,recorded_at,note,created_by,published) VALUES(?,?,?,?,?,?,?,CURDATE(),?,?,1)')
                ->execute([
                    (int)$attempt['student_id'],
                    ((int)($assessment['class_id'] ?? 0)) ?: null,
                    ((int)($assessment['subject_id'] ?? 0)) ?: null,
                    ((int)($assessment['college_exam_id'] ?? 0)) ?: null,
                    (string)$assessment['title'],
                    $attempt['score'],
                    $attempt['max_score'] ?: 100,
                    'assessment:'.$attempt['id'],
                    null,
                ]);
        } catch (Throwable $e) {
            try {
                $this->pdo->prepare('INSERT INTO student_progress(student_id,class_id,subject_id,metric,score,max_score,recorded_at,note) VALUES(?,?,?,?,?,?,CURDATE(),?)')
                    ->execute([
                        (int)$attempt['student_id'],
                        ((int)($assessment['class_id'] ?? 0)) ?: null,
                        ((int)($assessment['subject_id'] ?? 0)) ?: null,
                        (string)$assessment['title'],
                        $attempt['score'],
                        $attempt['max_score'] ?: 100,
                        'assessment:'.$attempt['id'],
                    ]);
            } catch (Throwable $ignored) {
            }
        }
    }

    private function notifyResult(array $attempt): void
    {
        try {
            $assessment = (new AssessmentService($this->pdo))->get((int)$attempt['assessment_id']);
            $notice = new NotificationCenterService($this->pdo);
            $notice->create('student', 'exams', 'Your result is available', ($assessment['title'] ?? 'Assessment').' result is ready.', BASE_URL.'student/assessment_result.php?attempt='.(int)$attempt['id'], (int)$attempt['student_id'], null, 'normal', ['attempt_id' => (int)$attempt['id']]);
        } catch (Throwable $e) {
        }
    }
}
