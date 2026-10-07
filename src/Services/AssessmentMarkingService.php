<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AssessmentMarkingService
{
    public function __construct(private PDO $pdo) {}

    /** @return array{score:float,max:float,percent:?float,passed:bool,needs_teacher:bool} */
    public function autoMarkAttempt(int $attemptId): array
    {
        $attempt = $this->attempt($attemptId);
        $questions = $this->questions((int)$attempt['assessment_id']);
        $answers = (new AssessmentAttemptService($this->pdo))->answers($attemptId);
        $score = 0.0;
        $max = 0.0;
        $needs = false;
        foreach ($questions as $q) {
            $max += (float)$q['marks'];
            $ans = $answers[(int)$q['id']] ?? null;
            $accepted = json_decode((string)($q['accepted_answers_json'] ?? '[]'), true) ?: [];
            $result = AssessmentScoring::autoMark(
                (string)$q['question_type'],
                $ans['choice_index'] ?? $ans['answer_text'] ?? null,
                $q['correct_index'],
                is_array($accepted) ? $accepted : [],
                (float)$q['marks']
            );
            if ($result === null) {
                $needs = true;
                continue;
            }
            $score += $result['marks'];
            $this->pdo->prepare('INSERT INTO assessment_answers(attempt_id,question_id,choice_index,answer_text,is_correct,marks_awarded,auto_marked,saved_at) VALUES(?,?,?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE is_correct=VALUES(is_correct),marks_awarded=VALUES(marks_awarded),auto_marked=1')
                ->execute([$attemptId, (int)$q['id'], $ans['choice_index'] ?? null, $ans['answer_text'] ?? null, $result['correct'] ? 1 : 0, $result['marks']]);
        }
        $assessment = (new AssessmentService($this->pdo))->get((int)$attempt['assessment_id']);
        $percent = AssessmentScoring::percent($score, $max);
        return [
            'score' => round($score, 2),
            'max' => round($max, 2),
            'percent' => $percent,
            'passed' => (bool)AssessmentScoring::passed($percent, (float)($assessment['pass_threshold'] ?? 40)),
            'needs_teacher' => $needs,
        ];
    }

    /** @return array<string,mixed> */
    public function suggest(int $attemptId, int $questionId, array $auth): array
    {
        $this->assertMarker($attemptId, $auth);
        $q = $this->question($questionId);
        $ans = $this->answer($attemptId, $questionId);
        $facts = [
            'prompt' => $q['prompt'] ?? '',
            'marks' => (float)($q['marks'] ?? 0),
            'marking_guidance' => json_decode((string)($q['marking_guidance_json'] ?? 'null'), true),
            'accepted_answers' => json_decode((string)($q['accepted_answers_json'] ?? '[]'), true),
            'student_answer' => $ans['answer_text'] ?? $ans['choice_index'] ?? '',
            'disclaimer' => 'This is an AI suggestion, not official Pearson/Edexcel marking guidance.',
        ];
        $text = '';
        $ai = new WhatsAppAiService($this->pdo);
        if ($ai->enabled()) {
            try {
                $text = (string)$ai->completeText(
                    'You are a marking assistant. Use only the supplied question, student answer, and teacher marking guidance. Suggest a mark, what was correct, what was missing, why the mark was suggested, and how to improve. Never claim official Pearson or Edexcel authority. Teacher remains the final marker.',
                    json_encode($facts, JSON_UNESCAPED_UNICODE),
                    [],
                    700
                );
            } catch (Throwable $e) {
                error_log('ai marking: '.$e->getMessage());
            }
        }
        if (trim($text) === '') {
            $text = $this->deterministicSuggestion($facts);
        }
        $suggested = $this->extractSuggestedMark($text, (float)$q['marks']);
        $this->pdo->prepare('INSERT INTO marking_reviews(attempt_id,question_id,suggested_mark,reason,provider,status) VALUES(?,?,?,?,?,"suggested")')
            ->execute([$attemptId, $questionId, $suggested, $text, $ai->providerName()]);
        return ['suggested_mark' => $suggested, 'reason' => $text, 'facts' => $facts, 'teacher_final' => true];
    }

    public function override(int $attemptId, int $questionId, float $marks, string $comment, array $auth, string $notes = ''): void
    {
        $this->assertMarker($attemptId, $auth);
        $q = $this->question($questionId);
        $max = (float)$q['marks'];
        $marks = max(0, min($max, $marks));
        $before = $this->answer($attemptId, $questionId);
        $this->pdo->prepare('INSERT INTO assessment_answers(attempt_id,question_id,marks_awarded,teacher_comment,marking_notes,marked_by,marked_at,is_correct,auto_marked) VALUES(?,?,?,?,?,?,NOW(),?,0) ON DUPLICATE KEY UPDATE marks_awarded=VALUES(marks_awarded),teacher_comment=VALUES(teacher_comment),marking_notes=VALUES(marking_notes),marked_by=VALUES(marked_by),marked_at=NOW(),is_correct=VALUES(is_correct),auto_marked=0')
            ->execute([$attemptId, $questionId, $marks, mb_substr($comment, 0, 2000), mb_substr($notes, 0, 2000), (int)($auth['user_id'] ?? 0) ?: null, $marks >= $max ? 1 : 0]);
        $this->recomputeAttempt($attemptId);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'assessment_mark_override', 'assessment_answers', $questionId, $before, ['marks' => $marks, 'attempt_id' => $attemptId]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function queue(array $auth): array
    {
        $sql = "SELECT aa.id attempt_id,aa.student_id,aa.percent,aa.status,a.title,a.class_id,a.teacher_id
                FROM assessment_attempts aa JOIN assessments a ON a.id=aa.assessment_id
                WHERE aa.status IN ('submitted','marking') ORDER BY aa.submitted_at DESC LIMIT 200";
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $svc = new AssessmentService($this->pdo);
        return array_values(array_filter($rows, fn($r) => $svc->canManage($auth, $r)));
    }

    private function recomputeAttempt(int $attemptId): void
    {
        $attempt = $this->attempt($attemptId);
        $questions = $this->questions((int)$attempt['assessment_id']);
        $answers = (new AssessmentAttemptService($this->pdo))->answers($attemptId);
        $score = 0.0;
        $max = 0.0;
        $pending = false;
        foreach ($questions as $q) {
            $max += (float)$q['marks'];
            $ans = $answers[(int)$q['id']] ?? null;
            if ($ans === null || $ans['marks_awarded'] === null) {
                $pending = true;
                continue;
            }
            $score += (float)$ans['marks_awarded'];
        }
        $assessment = (new AssessmentService($this->pdo))->get((int)$attempt['assessment_id']);
        $percent = AssessmentScoring::percent($score, $max);
        $status = $pending ? 'marking' : 'marked';
        $this->pdo->prepare('UPDATE assessment_attempts SET score=?,max_score=?,percent=?,passed=?,status=? WHERE id=?')
            ->execute([$score, $max, $percent, AssessmentScoring::passed($percent, (float)($assessment['pass_threshold'] ?? 40)) ? 1 : 0, $status, $attemptId]);
        if ($status === 'marked') {
            (new TopicMasteryService($this->pdo))->syncFromAttempt($attemptId);
            (new AssessmentAnalyticsService($this->pdo))->recalculate((int)$attempt['assessment_id']);
        }
    }

    private function assertMarker(int $attemptId, array $auth): void
    {
        $attempt = $this->attempt($attemptId);
        $assessment = (new AssessmentService($this->pdo))->get((int)$attempt['assessment_id']);
        if (!$assessment || !(new AssessmentService($this->pdo))->canManage($auth, $assessment)) {
            throw new RuntimeException('Marking access denied.');
        }
    }

    /** @return array<string,mixed> */
    private function attempt(int $id): array
    {
        $row = (new AssessmentAttemptService($this->pdo))->getAttempt($id);
        if (!$row) {
            throw new RuntimeException('Attempt not found.');
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function questions(int $assessmentId): array
    {
        return (new AssessmentService($this->pdo))->questions($assessmentId);
    }

    /** @return array<string,mixed> */
    private function question(int $id): array
    {
        $s = $this->pdo->prepare('SELECT * FROM assessment_questions WHERE id=?');
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Question not found.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function answer(int $attemptId, int $questionId): array
    {
        $s = $this->pdo->prepare('SELECT * FROM assessment_answers WHERE attempt_id=? AND question_id=?');
        $s->execute([$attemptId, $questionId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function deterministicSuggestion(array $facts): string
    {
        $max = (float)$facts['marks'];
        return "Suggested mark: ".round($max * 0.5, 1)." / {$max}. The student submitted a written response. Compare it against the teacher marking guidance and accepted answers. This is a deterministic fallback, not official Pearson/Edexcel marking guidance. The teacher must set the final mark.";
    }

    private function extractSuggestedMark(string $text, float $max): float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)/', $text, $m)) {
            return max(0, min($max, (float)$m[1]));
        }
        return round($max * 0.5, 2);
    }
}
