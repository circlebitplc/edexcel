<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CoursoChatService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_courso_schema')) {
            ensure_courso_schema($this->pdo);
        }
    }

    /**
     * @return list<array{role:string,content:string,created_at:?string}>
     */
    public function history(int $studentId, int $limit = 24): array
    {
        try {
            $limit = max(1, min(40, $limit));
            $stmt = $this->pdo->prepare("
                SELECT id, role, content, created_at, rating
                FROM courso_chat_messages
                WHERE student_id = ?
                ORDER BY id DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$studentId]);
            $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id' => (int)$row['id'],
                    'role' => (string)$row['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => (string)$row['content'],
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'rating' => (string)($row['rating'] ?? ''),
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{reply:string,snapshot:array<string,mixed>,message_id:int}
     */
    public function ask(int $studentId, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new \RuntimeException('Type a question first.');
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        $learner = new CoursoLearnerService($this->pdo);
        $learn = new CoursoLearnService($this->pdo);
        foreach (CoursoLearnerService::extractFactsFromMessage($message) as $key => $value) {
            $learner->remember($studentId, $key, $value, 'chat');
        }

        $correction = CoursoLearnService::looksLikeCorrection($message);
        if ($correction) {
            $learn->markLastAssistant($studentId, 'unhelpful');
        }
        $repeat = $learn->isRepeatQuestion($studentId, $message);

        $this->store($studentId, 'user', $message);
        $learner->logActivity($studentId, 'chat', null, null, true);

        $snapshot = $learner->snapshot($studentId);

        $dbAnswer = null;
        try {
            $dbAnswer = (new WhatsAppAssistant($this->pdo))->replyForStudent($studentId, $message);
            if (is_string($dbAnswer)) {
                $dbAnswer = trim($dbAnswer);
                if ($dbAnswer === '') {
                    $dbAnswer = null;
                }
            }
        } catch (Throwable $e) {
            error_log('Courso DB lookup: ' . $e->getMessage());
            $dbAnswer = null;
        }

        $lessons = $learn->similarLessons($studentId, $message);
        $kind = ($dbAnswer !== null && WhatsAppAssistant::looksLikeCollegeFactQuestion($message)) ? 'fact' : 'study';

        $reply = $this->modelReply($studentId, $message, $snapshot, $dbAnswer, $lessons, $repeat, $correction);
        if ($reply === '') {
            $reply = $dbAnswer !== null
                ? $this->forWeb($dbAnswer)
                : $this->fallbackReply($message, $snapshot);
        }
        $messageId = $this->store($studentId, 'assistant', $reply);
        $learn->rememberPair($studentId, $message, $reply, $kind);

        return ['reply' => $reply, 'snapshot' => $snapshot, 'message_id' => $messageId];
    }

    public function rate(int $studentId, int $messageId, bool $helpful): string
    {
        return (new CoursoLearnService($this->pdo))->rate($studentId, $messageId, $helpful);
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    public function fallbackReply(string $message, array $snapshot): string
    {
        $lower = mb_strtolower($message);
        $steps = $snapshot['next_steps'] ?? [];
        $first = $steps[0]['title'] ?? 'Tell me your exam goal so I can plan the week';

        if (preg_match('/\b(hi|hello|hey|ayubowan|vanakkam)\b/u', $lower)) {
            $streak = (int)($snapshot['streak'] ?? 0);
            $streakBit = $streak > 0 ? " You are on a {$streak}-day learning streak." : '';
            return "Hi — I am your study assistant for Edexcel College.{$streakBit} Next up: {$first}. Ask me about a topic, start a practice quiz, or tell me what grade you want.";
        }
        if (preg_match('/\b(quiz|practice|mcq|exercise|test me)\b/u', $lower)) {
            return "Open Practice on this page and I will pitch questions at "
                . ($snapshot['difficulty'] ?? 'core')
                . ' level. After you submit, every wrong answer gets a why + a real-world example. Weak areas right now: '
                . ($snapshot['weaknesses'] !== [] ? implode(', ', $snapshot['weaknesses']) : 'not enough marks yet, so I will mix topics.');
        }
        if (preg_match('/\b(weak|strength|progress|streak|dashboard)\b/u', $lower)) {
            return $this->progressBlurb($snapshot);
        }
        if (preg_match('/\b(homework|assignment|due)\b/u', $lower)) {
            $hw = $snapshot['homework'] ?? [];
            if ($hw === []) {
                return 'No homework rows are due in Homework & notes right now. If a teacher just posted a paper, refresh that tab.';
            }
            $lines = ['Here is what is still open:'];
            foreach (array_slice($hw, 0, 5) as $row) {
                $lines[] = '• ' . ($row['title'] ?? 'Task') . (!empty($row['due_date']) ? ' — due ' . $row['due_date'] : '');
            }
            $lines[] = 'After you finish one, tell me which question was hard and I will walk through it.';
            return implode("\n", $lines);
        }
        if (preg_match('/\b(exam|paper|revision|revise)\b/u', $lower)
            && !preg_match('/\b(teacher|class|phone|number|timetable|schedule|room)\b/u', $lower)
        ) {
            $ex = $snapshot['next_exam'] ?? null;
            $when = $ex['when'] ?? 'soon';
            $title = $ex['title'] ?? 'your next paper';
            return "Focus: {$title} ({$when}). Suggested loop: (1) 20 minutes on a weak topic, (2) 5 mixed questions here, (3) one past-paper timing drill. {$first}.";
        }

        if (WhatsAppAssistant::looksLikeCollegeFactQuestion($message)) {
            return 'I look that up from the college database — classes, teachers, phone numbers, rooms, and times. Ask again with the subject or teacher name (for example “ICT teacher phone” or “when is my next class”).';
        }

        $lines = ['I can already see your college progress, so here is a concrete plan:'];
        foreach (array_slice($steps, 0, 4) as $i => $step) {
            $lines[] = ($i + 1) . '. ' . $step['title'] . ' — ' . $step['why'];
        }
        $lines[] = 'Reply with a topic (for example “IAL Physics kinematics”) and I will explain it with a local example, or start a quiz on Practice.';
        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    public function progressBlurb(array $snapshot): string
    {
        $parts = [
            'Streak: ' . (int)$snapshot['streak'] . ' days',
            'Lessons attended (30 days): ' . (int)$snapshot['completed_lessons'],
            'Recordings watched: ' . (int)$snapshot['recordings_watched'],
            'Practice quizzes: ' . (int)$snapshot['quizzes_done'],
        ];
        if ($snapshot['strengths'] !== []) {
            $parts[] = 'Strengths: ' . implode(', ', $snapshot['strengths']);
        }
        if ($snapshot['weaknesses'] !== []) {
            $parts[] = 'Focus: ' . implode(', ', $snapshot['weaknesses']);
        }
        $next = $snapshot['next_steps'][0]['title'] ?? 'Set a goal in Preferences';
        $parts[] = 'Recommended next step: ' . $next;
        return implode('. ', $parts) . '.';
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param list<array<string,mixed>> $lessons
     */
    private function modelReply(
        int $studentId,
        string $message,
        array $snapshot,
        ?string $dbAnswer,
        array $lessons = [],
        bool $repeat = false,
        bool $correction = false
    ): string {
        $ai = new WhatsAppAiService($this->pdo);
        if (!$ai->enabled()) {
            return '';
        }

        $style = (string)($snapshot['profile']['ai_style'] ?? 'coach');
        $styleNote = match ($style) {
            'tutor' => 'Teach like a subject tutor: short worked steps, then a check-your-understanding question.',
            'concise' => 'Be brief but complete. No pep talk. Still cover the answer, why, and one example.',
            'encouraging' => 'Be warm and encouraging, but still specific. Never empty praise.',
            default => 'Be a personal coach: direct answer first, then the explanation, then one next action.',
        };

        $learner = new CoursoLearnerService($this->pdo);
        $collegeFacts = '';
        try {
            $collegeFacts = (new WhatsAppAssistant($this->pdo))->collegeFactsText($studentId);
        } catch (Throwable $e) {
            $collegeFacts = '';
        }

        $verified = $dbAnswer !== null && $dbAnswer !== ''
            ? "VERIFIED DATABASE ANSWER (use this for classes, teachers, phones, rooms, times — do not change names or numbers; you MAY rewrite it more clearly for the student):\n{$dbAnswer}\n\n"
            : '';

        $learnBlock = CoursoLearnService::lessonsPrompt($lessons, $repeat, $correction);

        $system = "You are the personal learning assistant for Edexcel College (Pearson IGCSE / IAS / IAL).\n"
            . "You get better from this student's questions, thumbs, and follow-ups. Use LEARNED LESSONS when they match.\n"
            . "{$styleNote}\n"
            . "For classes, teachers, phone numbers, emails, rooms, and lesson times: answer ONLY from COLLEGE DATA or the VERIFIED DATABASE ANSWER. Never invent a teacher, phone, room, or time. If a phone is listed as not saved, say so.\n"
            . "Teacher names, subjects, phone numbers and emails from COLLEGE DATA may be shared with this logged-in student.\n"
            . "Reply in the student's language. No markdown headings. No <think>. Do not call yourself Courso.\n\n"
            . $learnBlock . "\n\n"
            . $verified
            . $collegeFacts . "\n\n"
            . $learner->learnerContextText($studentId);

        $history = [];
        foreach (array_slice($this->history($studentId, 10), 0, -1) as $turn) {
            $history[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        try {
            $text = $ai->completeText($system, $message, $history, 1100);
        } catch (Throwable $e) {
            error_log('Courso chat: ' . $e->getMessage());
            return '';
        }
        $text = trim((string)$text);
        if (mb_strlen($text) > 4000) {
            $text = rtrim(mb_substr($text, 0, 3990)) . '…';
        }
        return $text;
    }

    private function forWeb(string $text): string
    {
        $text = preg_replace('/\*(.+?)\*/u', '$1', $text) ?? $text;
        $text = str_replace('Reply 7 for the office.', 'Ask the college office if you still need help.', $text);
        return trim($text);
    }

    private function store(int $studentId, string $role, string $content): int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO courso_chat_messages (student_id, role, content)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$studentId, $role, $content]);
            return (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('Courso chat store: ' . $e->getMessage());
            return 0;
        }
    }
}
