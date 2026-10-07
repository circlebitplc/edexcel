<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Courso cannot retrain Groq/Gemini. It improves by remembering questions,
 * thumbs, and “that didn’t help” follow-ups, then feeding the best past
 * answers into the next reply.
 */
final class CoursoLearnService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_courso_schema')) {
            ensure_courso_schema($this->pdo);
        }
    }

    public static function normalizeQuestion(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $stop = [
            'please', 'can', 'you', 'the', 'a', 'an', 'to', 'of', 'for', 'me',
            'my', 'what', 'whats', 'is', 'are', 'how', 'do', 'does', 'tell',
            'give', 'i', 'want', 'need', 'about',
        ];
        $words = [];
        foreach (preg_split('/\s+/', $text) ?: [] as $w) {
            if ($w === '' || (mb_strlen($w) < 2 && !is_numeric($w))) {
                continue;
            }
            if (in_array($w, $stop, true) && !is_numeric($w)) {
                continue;
            }
            $words[] = $w;
        }
        return trim(implode(' ', $words));
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $norm = self::normalizeQuestion($text);
        if ($norm === '') {
            return [];
        }
        return array_values(array_unique(preg_split('/\s+/', $norm) ?: []));
    }

    public static function overlapScore(string $a, string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        $setB = array_fill_keys($tb, true);
        $inter = 0;
        foreach ($ta as $t) {
            if (isset($setB[$t])) {
                $inter++;
            }
        }
        $union = count(array_unique(array_merge($ta, $tb)));
        return $union > 0 ? $inter / $union : 0.0;
    }

    public static function looksLikeCorrection(string $message): bool
    {
        $t = mb_strtolower($message);
        $needles = [
            'not helpful', 'that\'s wrong', 'thats wrong', 'not what i meant',
            'too vague', 'too short', 'more detail', 'explain more',
            'i don\'t understand', 'i dont understand', 'still confused',
            'doesn\'t answer', 'doesnt answer', 'try again', 'not enough',
            'go deeper', 'be more specific', 'wrong answer', 'not clear',
            'i meant',
        ];
        foreach ($needles as $n) {
            if (str_contains($t, $n)) {
                return true;
            }
        }
        return false;
    }

    public function rememberPair(int $studentId, string $question, string $answer, string $kind = 'study'): void
    {
        $norm = self::normalizeQuestion($question);
        if ($norm === '' || trim($answer) === '') {
            return;
        }
        $hash = hash('sha256', mb_substr($norm, 0, 180));
        $kind = $kind === 'fact' ? 'fact' : 'study';
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO courso_learned_qa
                    (q_hash, q_norm, question, answer, kind, ask_count, last_student_id)
                VALUES (?, ?, ?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE
                    ask_count = ask_count + 1,
                    last_student_id = VALUES(last_student_id),
                    question = IF(CHAR_LENGTH(VALUES(question)) > CHAR_LENGTH(question), VALUES(question), question),
                    answer = IF(helpful >= unhelpful, answer, VALUES(answer))
            ");
            $stmt->execute([
                $hash,
                mb_substr($norm, 0, 240),
                mb_substr(trim($question), 0, 500),
                mb_substr(trim($answer), 0, 2000),
                $kind,
                $studentId,
            ]);
        } catch (Throwable $e) {
            error_log('Courso learn remember: ' . $e->getMessage());
        }
    }

    /**
     * Past Q&A that should make the next answer stronger.
     *
     * @return list<array{question:string,answer:string,helpful:int,unhelpful:int,score:float}>
     */
    public function similarLessons(int $studentId, string $question, int $limit = 5): array
    {
        $limit = max(1, min(8, $limit));
        $rows = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT question, answer, helpful, unhelpful, q_norm, last_student_id
                FROM courso_learned_qa
                WHERE answer <> ''
                ORDER BY (helpful - unhelpful) DESC, ask_count DESC
                LIMIT 80
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }

        $scored = [];
        foreach ($rows as $row) {
            $overlap = self::overlapScore($question, (string)($row['q_norm'] ?: $row['question']));
            if ($overlap < 0.18) {
                continue;
            }
            $helpful = (int)$row['helpful'];
            $unhelpful = (int)$row['unhelpful'];
            $boost = $helpful - $unhelpful;
            if ((int)$row['last_student_id'] === $studentId) {
                $boost += 1;
            }
            $scored[] = [
                'question' => (string)$row['question'],
                'answer' => (string)$row['answer'],
                'helpful' => $helpful,
                'unhelpful' => $unhelpful,
                'score' => $overlap + ($boost * 0.08),
            ];
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    public function lastUserQuestion(int $studentId): string
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT content FROM courso_chat_messages
                WHERE student_id = ? AND role = 'user'
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            return trim((string)($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    public function isRepeatQuestion(int $studentId, string $question): bool
    {
        $prev = $this->lastUserQuestion($studentId);
        if ($prev === '') {
            return false;
        }
        return self::overlapScore($question, $prev) >= 0.55;
    }

    public function markLastAssistant(int $studentId, string $rating): void
    {
        if (!in_array($rating, ['helpful', 'unhelpful'], true)) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM courso_chat_messages
                WHERE student_id = ? AND role = 'assistant'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $id = (int)$stmt->fetchColumn();
            if ($id > 0) {
                $this->rate($studentId, $id, $rating === 'helpful');
            }
        } catch (Throwable $e) {
            error_log('Courso mark last: ' . $e->getMessage());
        }
    }

    public function rate(int $studentId, int $messageId, bool $helpful): string
    {
        $rating = $helpful ? 'helpful' : 'unhelpful';
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, content FROM courso_chat_messages
                WHERE id = ? AND student_id = ? AND role = 'assistant'
                LIMIT 1
            ");
            $stmt->execute([$messageId, $studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('That reply was not found.');
            }
            $this->pdo->prepare("UPDATE courso_chat_messages SET rating = ? WHERE id = ?")
                ->execute([$rating, $messageId]);

            $qStmt = $this->pdo->prepare("
                SELECT content FROM courso_chat_messages
                WHERE student_id = ? AND role = 'user' AND id < ?
                ORDER BY id DESC LIMIT 1
            ");
            $qStmt->execute([$studentId, $messageId]);
            $question = trim((string)($qStmt->fetchColumn() ?: ''));
            if ($question !== '') {
                $this->applyRatingToLearned($question, (string)$row['content'], $helpful);
            }
            return $rating;
        } catch (Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            error_log('Courso rate: ' . $e->getMessage());
            throw new \RuntimeException('Could not save that rating.');
        }
    }

    public function ratePair(string $question, string $answer, bool $helpful): string
    {
        $this->applyRatingToLearned($question, $answer, $helpful);
        return $helpful ? 'helpful' : 'unhelpful';
    }

    public static function lessonsPrompt(array $lessons, bool $repeat, bool $correction): string
    {
        $lines = [
            'HOW TO GIVE A STRONG ANSWER:',
            '- Lead with the direct answer in the first two sentences.',
            '- Then explain why, with one worked step or example.',
            '- End with one next action the student can do now.',
            '- Do not pad with generic study tips if they asked a specific question.',
        ];
        if ($repeat) {
            $lines[] = 'The student asked something very similar again — the last reply was not strong enough. Go deeper, not shorter.';
        }
        if ($correction) {
            $lines[] = 'The student said the previous reply was weak or wrong. Fix it: be specific, use college data, and check what they actually asked.';
        }
        if ($lessons !== []) {
            $lines[] = 'LESSONS FROM PAST STUDENT QUESTIONS (prefer answers that were marked helpful; avoid patterns that were marked unhelpful):';
            foreach (array_slice($lessons, 0, 4) as $i => $row) {
                $flag = ((int)$row['helpful'] >= (int)$row['unhelpful']) ? 'helpful' : 'was marked weak';
                $lines[] = ($i + 1) . '. Q: ' . mb_substr((string)$row['question'], 0, 160)
                    . "\n   ({$flag}) A: " . mb_substr((string)$row['answer'], 0, 280);
            }
        }
        $text = implode("\n", $lines);
        if (mb_strlen($text) > 1800) {
            $text = mb_substr($text, 0, 1790) . '…';
        }
        return $text;
    }

    private function applyRatingToLearned(string $question, string $answer, bool $helpful): void
    {
        $norm = self::normalizeQuestion($question);
        if ($norm === '') {
            return;
        }
        $hash = hash('sha256', mb_substr($norm, 0, 180));
        $col = $helpful ? 'helpful' : 'unhelpful';
        try {
            $upd = $this->pdo->prepare("UPDATE courso_learned_qa SET {$col} = {$col} + 1 WHERE q_hash = ?");
            $upd->execute([$hash]);
            if ($upd->rowCount() === 0) {
                $this->rememberPair(0, $question, $answer, 'study');
                $this->pdo->prepare("UPDATE courso_learned_qa SET {$col} = {$col} + 1 WHERE q_hash = ?")->execute([$hash]);
            }
            if ($helpful) {
                $this->pdo->prepare("UPDATE courso_learned_qa SET answer = ? WHERE q_hash = ?")
                    ->execute([mb_substr(trim($answer), 0, 2000), $hash]);
            }
        } catch (Throwable $e) {
            error_log('Courso learned rating: ' . $e->getMessage());
        }
    }
}
