<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CommunicationAiAssistant
{
    public function __construct(private PDO $pdo) {}

    /**
     * Draft only — never sends. Facts must be supplied by caller; AI must not invent them.
     * @param array<string,mixed> $facts
     * @return array{draft:string,disclaimer:string,facts:array<string,mixed>}
     */
    public function draft(string $intent, array $facts, string $tone = 'professional'): array
    {
        $safeFacts = $this->sanitizeFacts($facts);
        $prompt = 'Draft a short education-focused '.$tone.' message for: '.$intent.'. Use ONLY the supplied facts. Do not invent attendance, marks, fees, dates, or names. Do not include passwords or OTPs. Return message body only.';
        $text = '';
        $ai = new WhatsAppAiService($this->pdo);
        if ($ai->enabled()) {
            try {
                $text = trim((string)$ai->completeText($prompt, json_encode($safeFacts, JSON_UNESCAPED_UNICODE), [], 500));
            } catch (Throwable $e) {
            }
        }
        if ($text === '') {
            $text = $this->deterministic($intent, $safeFacts);
        }
        return [
            'draft' => $text,
            'disclaimer' => 'AI draft only. Staff must review before sending. Facts were not invented by the model beyond the supplied data.',
            'facts' => $safeFacts,
        ];
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @return array{summary:string,disclaimer:string}
     */
    public function summarizeThread(array $messages): array
    {
        $facts = ['message_count' => count($messages), 'recent' => array_slice(array_map(static function ($m) {
            return ['role' => $m['sender_role'] ?? '', 'body' => mb_substr((string)($m['body'] ?? ''), 0, 200)];
        }, $messages), -12)];
        $text = '';
        $ai = new WhatsAppAiService($this->pdo);
        if ($ai->enabled()) {
            try {
                $text = trim((string)$ai->completeText(
                    'Summarize this college communication thread for staff. Do not invent facts. Keep it professional and short.',
                    json_encode($facts, JSON_UNESCAPED_UNICODE),
                    [],
                    400
                ));
            } catch (Throwable $e) {
            }
        }
        if ($text === '') {
            $text = 'Thread has '.(int)$facts['message_count'].' message(s). Latest: '.((string)($facts['recent'][count($facts['recent']) - 1]['body'] ?? '—'));
        }
        return ['summary' => $text, 'disclaimer' => 'Advisory summary only. Not a substitute for reading the thread.'];
    }

    /** @param array<string,mixed> $facts @return array<string,mixed> */
    private function sanitizeFacts(array $facts): array
    {
        $out = [];
        foreach ($facts as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $s = (string)$v;
                $lower = strtolower($s);
                if (str_contains($lower, 'password') || str_contains($lower, 'otp') || str_contains($lower, 'api_key')) {
                    continue;
                }
                $out[(string)$k] = mb_substr($s, 0, 200);
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $facts */
    private function deterministic(string $intent, array $facts): string
    {
        $name = (string)($facts['parent_name'] ?? $facts['student_name'] ?? 'Parent/Student');
        $detail = (string)($facts['detail'] ?? $facts['reason'] ?? 'Please review the college portal for details.');
        return 'Dear '.$name.', regarding '.$intent.': '.$detail.' — Edexcel College';
    }
}
