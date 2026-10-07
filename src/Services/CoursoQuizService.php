<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CoursoQuizService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_courso_schema')) {
            ensure_courso_schema($this->pdo);
        }
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public function start(int $studentId, array $snapshot, ?int $subjectId = null, string $topic = ''): array
    {
        $snapshot['student_id'] = $studentId;
        $difficulty = (string)($snapshot['difficulty'] ?? 'core');
        if (!in_array($difficulty, ['foundation', 'core', 'stretch'], true)) {
            $difficulty = 'core';
        }
        $topic = mb_substr(trim($topic), 0, 120);
        $needles = $this->topicNeedles($snapshot, $topic, $subjectId);

        $items = $this->generateWithAi($snapshot, $needles, $difficulty, $topic);
        if ($items === []) {
            $items = CoursoQuizBank::pick($needles, $difficulty, 5);
        }
        $items = array_slice($items, 0, 5);

        $label = $topic !== '' ? $topic : ($needles[0] ?? 'Mixed practice');
        $stmt = $this->pdo->prepare("
            INSERT INTO courso_quizzes (student_id, subject_id, topic, difficulty, status, max_score)
            VALUES (?, ?, ?, ?, 'open', ?)
        ");
        $stmt->execute([$studentId, $subjectId, $label, $difficulty, count($items)]);
        $quizId = (int)$this->pdo->lastInsertId();

        $ins = $this->pdo->prepare("
            INSERT INTO courso_quiz_items
                (quiz_id, sort_order, prompt, choices, correct_index, explanation, example_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $i => $item) {
            $choices = $item['choices'] ?? [];
            $ins->execute([
                $quizId,
                $i,
                (string)$item['prompt'],
                json_encode(array_values($choices), JSON_UNESCAPED_UNICODE),
                (int)($item['correct_index'] ?? 0),
                (string)($item['explanation'] ?? ''),
                (string)($item['example'] ?? $item['example_text'] ?? ''),
            ]);
        }

        (new CoursoLearnerService($this->pdo))->logActivity($studentId, 'quiz_start', 'quiz', $quizId);

        return $this->publicQuiz($quizId, $studentId, false);
    }

    /**
     * @param array<int,int> $answers itemId => choice index
     * @return array<string,mixed>
     */
    public function submit(int $studentId, int $quizId, array $answers): array
    {
        $quiz = $this->ownedQuiz($quizId, $studentId);
        if ($quiz === null) {
            throw new \RuntimeException('Practice quiz not found.');
        }
        if (($quiz['status'] ?? '') === 'completed') {
            return $this->publicQuiz($quizId, $studentId, true);
        }

        $items = $this->items($quizId);
        $correct = 0;
        $upd = $this->pdo->prepare("
            UPDATE courso_quiz_items
            SET student_choice = ?, is_correct = ?, feedback = ?
            WHERE id = ? AND quiz_id = ?
        ");
        foreach ($items as $item) {
            $id = (int)$item['id'];
            $choice = array_key_exists($id, $answers) ? (int)$answers[$id] : -1;
            $ok = $choice === (int)$item['correct_index'];
            if ($ok) {
                $correct++;
            }
            $feedback = $this->buildFeedback($item, $choice, $ok);
            $upd->execute([$choice < 0 ? null : $choice, $ok ? 1 : 0, $feedback, $id, $quizId]);
        }

        $this->pdo->prepare("
            UPDATE courso_quizzes
            SET status = 'completed', score = ?, completed_at = NOW()
            WHERE id = ? AND student_id = ?
        ")->execute([$correct, $quizId, $studentId]);

        $learner = new CoursoLearnerService($this->pdo);
        $learner->logActivity($studentId, 'quiz_complete', 'quiz', $quizId);
        $pct = count($items) > 0 ? (int)round(100 * $correct / count($items)) : 0;
        if ($pct < 55) {
            $learner->remember($studentId, 'weakness', (string)$quiz['topic'], 'quiz');
        } elseif ($pct >= 80) {
            $learner->remember($studentId, 'strength', (string)$quiz['topic'], 'quiz');
        }

        return $this->publicQuiz($quizId, $studentId, true);
    }

    /**
     * @return array<string,mixed>
     */
    public function publicQuiz(int $quizId, int $studentId, bool $reveal): array
    {
        $quiz = $this->ownedQuiz($quizId, $studentId);
        if ($quiz === null) {
            throw new \RuntimeException('Practice quiz not found.');
        }
        $reveal = $reveal || ($quiz['status'] ?? '') === 'completed';
        $outItems = [];
        foreach ($this->items($quizId) as $item) {
            $choices = json_decode((string)$item['choices'], true);
            if (!is_array($choices)) {
                $choices = [];
            }
            $row = [
                'id' => (int)$item['id'],
                'prompt' => (string)$item['prompt'],
                'choices' => array_values($choices),
            ];
            if ($reveal) {
                $row['correct_index'] = (int)$item['correct_index'];
                $row['student_choice'] = $item['student_choice'] === null ? null : (int)$item['student_choice'];
                $row['is_correct'] = $item['is_correct'] === null ? null : (int)$item['is_correct'] === 1;
                $row['feedback'] = (string)($item['feedback'] ?? '');
                $row['explanation'] = (string)($item['explanation'] ?? '');
                $row['example'] = (string)($item['example_text'] ?? '');
            }
            $outItems[] = $row;
        }

        return [
            'id' => (int)$quiz['id'],
            'topic' => (string)$quiz['topic'],
            'difficulty' => (string)$quiz['difficulty'],
            'status' => (string)$quiz['status'],
            'score' => $quiz['score'] === null ? null : (float)$quiz['score'],
            'max_score' => (float)$quiz['max_score'],
            'items' => $outItems,
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    public static function scoreItem(int $correctIndex, int $choice): bool
    {
        return $choice === $correctIndex;
    }

    /**
     * @param array<string,mixed> $item
     */
    public function buildFeedback(array $item, int $choice, bool $ok): string
    {
        $explanation = trim((string)($item['explanation'] ?? ''));
        $example = trim((string)($item['example_text'] ?? $item['example'] ?? ''));
        $choices = json_decode((string)($item['choices'] ?? '[]'), true);
        if (!is_array($choices)) {
            $choices = [];
        }
        $picked = $choices[$choice] ?? 'no answer';
        $right = $choices[(int)$item['correct_index']] ?? '';

        if ($ok) {
            $text = 'Correct. ' . $explanation;
        } else {
            $text = 'Not quite. You chose “' . $picked . '”. The better answer is “' . $right . '”. ' . $explanation;
        }
        if ($example !== '') {
            $text .= ' Real-world link: ' . $example;
        }
        return trim($text);
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param list<string> $needles
     * @return list<array<string,mixed>>
     */
    private function generateWithAi(array $snapshot, array $needles, string $difficulty, string $topic): array
    {
        $ai = new WhatsAppAiService($this->pdo);
        if (!$ai->enabled()) {
            return [];
        }
        $focus = $topic !== '' ? $topic : implode(', ', array_slice($needles, 0, 4));
        $weak = implode('; ', $snapshot['weaknesses'] ?? []);
        $system = "You write Pearson Edexcel-style multiple-choice practice for secondary students in Sri Lanka.\n"
            . "Return JSON only: {\"questions\":[{\"prompt\":\"...\",\"choices\":[\"A\",\"B\",\"C\",\"D\"],\"correct_index\":0,\"explanation\":\"why the right answer is right and why typical wrong answers fail\",\"example\":\"one short real-world example\"}]}\n"
            . "Exactly 5 questions. One correct option. Difficulty: {$difficulty}. No markdown.";
        $user = "Subject focus: {$focus}. Weak areas: {$weak}. Enrolled: "
            . implode(', ', array_column($snapshot['classes'] ?? [], 'class_name'))
            . ". Prefer Pearson Edexcel IAL/IAS unit wording from the student's papers and official exam picks.";
        try {
            $raw = $ai->completeText($system, $user, [], 1400);
        } catch (Throwable $e) {
            return [];
        }
        return $this->parseGenerated($raw ?? '');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function parseGenerated(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $data = json_decode($m[0], true);
            }
        }
        $list = [];
        if (isset($data['questions']) && is_array($data['questions'])) {
            $list = $data['questions'];
        } elseif (isset($data[0]) && is_array($data[0])) {
            $list = $data;
        }
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $prompt = trim((string)($row['prompt'] ?? $row['question'] ?? ''));
            $choices = $row['choices'] ?? $row['options'] ?? [];
            if ($prompt === '' || !is_array($choices) || count($choices) < 2) {
                continue;
            }
            $choices = array_values(array_map(static fn ($c) => (string)$c, $choices));
            $idx = (int)($row['correct_index'] ?? $row['answer'] ?? 0);
            if ($idx < 0 || $idx >= count($choices)) {
                $idx = 0;
            }
            $out[] = [
                'prompt' => $prompt,
                'choices' => $choices,
                'correct_index' => $idx,
                'explanation' => (string)($row['explanation'] ?? $row['feedback'] ?? ''),
                'example' => (string)($row['example'] ?? $row['real_world'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return list<string>
     */
    private function topicNeedles(array $snapshot, string $topic, ?int $subjectId): array
    {
        $needles = [];
        if ($topic !== '') {
            $needles[] = $topic;
        }
        foreach ($snapshot['weaknesses'] ?? [] as $w) {
            $needles[] = (string)$w;
        }
        foreach ($snapshot['classes'] ?? [] as $c) {
            $needles[] = (string)($c['class_name'] ?? '');
        }
        if ($subjectId) {
            try {
                $stmt = $this->pdo->prepare('SELECT name FROM subjects WHERE id = ? LIMIT 1');
                $stmt->execute([$subjectId]);
                $name = (string)($stmt->fetchColumn() ?: '');
                if ($name !== '') {
                    array_unshift($needles, $name);
                }
            } catch (Throwable $e) {
            }
        }
        $studentId = (int)($snapshot['student_id'] ?? 0);
        if ($studentId > 0) {
            foreach ($this->pearsonNeedles($studentId) as $unit) {
                $needles[] = $unit;
            }
        }
        $needles = array_values(array_unique(array_filter(array_map('trim', $needles))));
        return $needles !== [] ? $needles : ['study', 'exam'];
    }

    /**
     * @return list<string>
     */
    private function pearsonNeedles(int $studentId): array
    {
        $out = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT pearson_unit FROM student_homework
                WHERE pearson_unit IS NOT NULL AND pearson_unit <> ''
                  AND class_id IN (SELECT class_id FROM student_enrollments WHERE student_id = ?)
                ORDER BY id DESC LIMIT 8
            ");
            $stmt->execute([$studentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $unit) {
                $out[] = (string)$unit;
            }
        } catch (Throwable $e) {
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT pearson_unit FROM student_materials
                WHERE pearson_unit IS NOT NULL AND pearson_unit <> ''
                  AND class_id IN (SELECT class_id FROM student_enrollments WHERE student_id = ?)
                ORDER BY id DESC LIMIT 8
            ");
            $stmt->execute([$studentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $unit) {
                $out[] = (string)$unit;
            }
        } catch (Throwable $e) {
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT e.unit_title, e.unit_code, e.paper_code, e.subject
                FROM student_exam_selections ses
                JOIN exams e ON e.id = ses.exam_id
                WHERE ses.student_id = ?
                ORDER BY e.exam_date ASC
                LIMIT 8
            ");
            $stmt->execute([$studentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                foreach (['unit_title', 'unit_code', 'paper_code', 'subject'] as $k) {
                    if (!empty($row[$k])) {
                        $out[] = (string)$row[$k];
                    }
                }
            }
        } catch (Throwable $e) {
        }
        return $out;
    }

    private function ownedQuiz(int $quizId, int $studentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM courso_quizzes WHERE id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$quizId, $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function items(int $quizId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM courso_quiz_items WHERE quiz_id = ? ORDER BY sort_order, id');
        $stmt->execute([$quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
