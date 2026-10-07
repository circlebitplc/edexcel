<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Homepage AI for visitors and unregistered students.
 * Does not expose another student's marks, timetable, or fees.
 */
final class CoursoPublicChatService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_courso_schema')) {
            ensure_courso_schema($this->pdo);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['public_ai']) || !is_array($_SESSION['public_ai'])) {
            $_SESSION['public_ai'] = ['messages' => []];
        }
        if (!isset($_SESSION['public_ai']['messages']) || !is_array($_SESSION['public_ai']['messages'])) {
            $_SESSION['public_ai']['messages'] = [];
        }
    }

    /**
     * @return list<array{role:string,content:string}>
     */
    public function history(): array
    {
        $out = [];
        foreach ($_SESSION['public_ai']['messages'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $role = ((string)($row['role'] ?? '')) === 'assistant' ? 'assistant' : 'user';
            $content = trim((string)($row['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        return array_slice($out, -24);
    }

    /**
     * @return array{reply:string}
     */
    public function ask(string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new \RuntimeException('Type a question first.');
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        $learn = new CoursoLearnService($this->pdo);
        $history = $this->history();
        $prevUser = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['role'] === 'user') {
                $prevUser = $history[$i]['content'];
                break;
            }
        }
        $correction = CoursoLearnService::looksLikeCorrection($message);
        $repeat = $prevUser !== '' && CoursoLearnService::overlapScore($message, $prevUser) >= 0.55;

        $this->push('user', $message);

        $dbAnswer = null;
        try {
            $dbAnswer = (new WhatsAppAssistant($this->pdo))->replyForVisitor($message);
            if (is_string($dbAnswer)) {
                $dbAnswer = trim($dbAnswer);
                if ($dbAnswer === '') {
                    $dbAnswer = null;
                }
            }
        } catch (Throwable $e) {
            error_log('Public AI DB lookup: ' . $e->getMessage());
            $dbAnswer = null;
        }

        $lessons = $learn->similarLessons(0, $message);
        $kind = ($dbAnswer !== null && WhatsAppAssistant::looksLikeCollegeFactQuestion($message)) ? 'fact' : 'study';

        $reply = $this->modelReply($message, $dbAnswer, $lessons, $repeat, $correction);
        if ($reply === '') {
            $reply = $dbAnswer !== null
                ? $this->forWeb($dbAnswer)
                : $this->fallbackReply($message);
        }

        $this->push('assistant', $reply);
        $learn->rememberPair(0, $message, $reply, $kind);

        return ['reply' => $reply];
    }

    public function rateLast(bool $helpful): string
    {
        $history = $this->history();
        $answer = '';
        $question = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($answer === '' && $history[$i]['role'] === 'assistant') {
                $answer = $history[$i]['content'];
                continue;
            }
            if ($answer !== '' && $history[$i]['role'] === 'user') {
                $question = $history[$i]['content'];
                break;
            }
        }
        if ($question === '' || $answer === '') {
            throw new \RuntimeException('Rate a reply first.');
        }
        return (new CoursoLearnService($this->pdo))->ratePair($question, $answer, $helpful);
    }

    public function fallbackReply(string $message): string
    {
        $lower = mb_strtolower($message);
        $register = '/student/register.php';
        $login = '/index.php#student-login';
        try {
            $register = WhatsAppGuideService::page('student/register.php');
            $login = WhatsAppGuideService::page('index.php') . '#student-login';
        } catch (Throwable $e) {
            // keep relative links
        }

        if (preg_match('/\b(hi|hello|hey|ayubowan|vanakkam)\b/u', $lower)) {
            return 'Hi — I am the Edexcel College AI. Ask about classes, teachers, how to register, or any IGCSE / IAS / IAL topic.';
        }
        if (preg_match('/\b(register|sign up|admission|enrol|enroll|join|new student)\b/u', $lower)) {
            return "Create a student account here:\n{$register}\n\nAlready registered? Log in:\n{$login}\nAfter you sign in you can join classes and ask about your own timetable.";
        }
        if (WhatsAppAssistant::looksLikeCollegeFactQuestion($message)) {
            return 'I look that up from the college database — classes, teachers, and how to join. Ask with the subject or teacher name (for example “do you have IAL Physics” or “ICT teacher”). For your own timetable, register or log in first.';
        }

        return "I can help with Pearson IGCSE / IAS / IAL topics and with this college (classes, teachers, how to register).\n"
            . "Ask a specific question, or create an account so I can use your classes and marks:\n{$register}";
    }

    /**
     * @param list<array{question:string,answer:string,helpful:int,unhelpful:int}> $lessons
     */
    private function modelReply(
        string $message,
        ?string $dbAnswer,
        array $lessons,
        bool $repeat,
        bool $correction
    ): string {
        $ai = new WhatsAppAiService($this->pdo);
        if (!$ai->enabled()) {
            return '';
        }

        $collegeFacts = '';
        try {
            $collegeFacts = (new WhatsAppAssistant($this->pdo))->publicFactsText();
        } catch (Throwable $e) {
            $collegeFacts = '';
        }

        $verified = $dbAnswer !== null && $dbAnswer !== ''
            ? "VERIFIED DATABASE ANSWER (use this for classes, teachers, phones, rooms, times, and how to register — do not change names or numbers; you MAY rewrite it more clearly):\n{$dbAnswer}\n\n"
            : '';

        $learnBlock = CoursoLearnService::lessonsPrompt($lessons, $repeat, $correction);

        $system = "You are the public AI assistant for Edexcel College (Pearson IGCSE / IAS / IAL).\n"
            . "The visitor may be new and not registered. Help them join the college and learn the subject.\n"
            . "Be a coach: direct answer first, then a short explanation, then one next action.\n"
            . "For classes, teachers, phone numbers, emails, rooms, times, and registration: answer ONLY from COLLEGE DATA or the VERIFIED DATABASE ANSWER. Never invent a teacher, phone, room, fee, or time.\n"
            . "Do not reveal another student's marks, attendance, fees, or timetable. If they ask for personal records, tell them to register or log in.\n"
            . "Teacher names, subjects, and listed phone numbers from COLLEGE DATA may be shared (they are already on the public website).\n"
            . "Reply in the visitor's language. No markdown headings. No <think>. Do not call yourself Courso.\n\n"
            . $learnBlock . "\n\n"
            . $verified
            . $collegeFacts;

        $history = [];
        foreach (array_slice($this->history(), 0, -1) as $turn) {
            $history[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        try {
            $text = $ai->completeText($system, $message, $history, 1100);
        } catch (Throwable $e) {
            error_log('Public AI chat: ' . $e->getMessage());
            return '';
        }
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) > 4000) {
            $text = rtrim(mb_substr($text, 0, 3990)) . '…';
        }
        return $this->forWeb($text);
    }

    private function forWeb(string $text): string
    {
        $text = preg_replace('/\*(.+?)\*/u', '$1', $text) ?? $text;
        $text = str_replace('Reply 7 for the office.', 'Ask the college office if you still need help.', $text);
        $text = str_replace('Or reply *7* for the office.', 'Ask the college office if you still need help.', $text);
        $text = preg_replace('/Reply \*7\*.*/u', 'Ask the college office if you still need help.', $text) ?? $text;
        return trim($text);
    }

    private function push(string $role, string $content): void
    {
        $_SESSION['public_ai']['messages'][] = ['role' => $role, 'content' => $content];
        if (count($_SESSION['public_ai']['messages']) > 40) {
            $_SESSION['public_ai']['messages'] = array_slice($_SESSION['public_ai']['messages'], -40);
        }
    }

    public function assertRateLimit(string $ip, int $max = 18, int $windowSeconds = 300): void
    {
        $hash = hash('sha256', $ip !== '' ? $ip : 'unknown');
        $now = time();
        try {
            $stmt = $this->pdo->prepare("SELECT hits, window_start FROM courso_public_rl WHERE ip_hash = ? LIMIT 1");
            $stmt->execute([$hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || ($now - (int)$row['window_start']) >= $windowSeconds) {
                $this->pdo->prepare("
                    INSERT INTO courso_public_rl (ip_hash, window_start, hits)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE window_start = VALUES(window_start), hits = 1
                ")->execute([$hash, $now]);
                return;
            }
            $hits = (int)$row['hits'];
            if ($hits >= $max) {
                throw new \RuntimeException('Please wait a moment and try again.');
            }
            $this->pdo->prepare("UPDATE courso_public_rl SET hits = hits + 1 WHERE ip_hash = ?")
                ->execute([$hash]);
        } catch (Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            error_log('Public AI rate: ' . $e->getMessage());
        }
    }
}
