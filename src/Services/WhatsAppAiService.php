<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class WhatsAppAiService
{
    private int $maxTokens = 700;

    public function __construct(private PDO $pdo)
    {
    }

    public function enabled(): bool
    {
        $flag = $this->setting('whatsapp_ai_enabled', '1');
        if ($flag !== '1') {
            return false;
        }

        return $this->groqKey() !== '' || $this->geminiKey() !== '';
    }

    public function providerName(): string
    {
        if ($this->groqKey() !== '') {
            return 'Groq';
        }
        if ($this->geminiKey() !== '') {
            return 'Gemini';
        }
        return 'none';
    }

    public function testConnection(): string
    {
        if ($this->groqKey() === '' && $this->geminiKey() === '') {
            throw new \RuntimeException(
                'No API key is saved. Paste the Groq key, click Save AI settings, then test again.'
            );
        }

        $reply = $this->complete(
            'You are a connection test. Reply with exactly: AI ready',
            'Say AI ready',
            []
        );

        if ($reply === null || trim($reply) === '') {
            throw new \RuntimeException('The AI provider returned an empty reply.');
        }

        $modelNote = $this->providerName() === 'Groq'
            ? $this->setting('whatsapp_ai_groq_model', 'qwen/qwen3.6-27b')
            : $this->setting('whatsapp_ai_gemini_model', 'gemini-2.0-flash');

        return $this->providerName() . ' connected using ' . $modelNote . '.';
    }

    /**
     * @param list<array{role:string,content:string}> $history
     */
    public function reply(
        string $userMessage,
        string $collegeContext,
        array $history
    ): ?string {
        $system = $this->systemPrompt($collegeContext);
        $text = $this->complete($system, $userMessage, $history);
        if ($text === null) {
            return null;
        }

        $text = trim($text);
        $text = $this->stripReasoning($text);
        $text = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > 1600) {
            $text = rtrim(mb_substr($text, 0, 1590)) . '…';
        }

        return $text;
    }

    /**
     * Longer completions for Courso AI (portal quizzes and study chat).
     *
     * @param list<array{role:string,content:string}> $history
     */
    public function completeText(
        string $system,
        string $userMessage,
        array $history = [],
        int $maxTokens = 900
    ): ?string {
        $previous = $this->maxTokens;
        $this->maxTokens = max(200, min(2000, $maxTokens));
        try {
            $text = $this->complete($system, $userMessage, $history);
        } finally {
            $this->maxTokens = $previous;
        }
        if ($text === null) {
            return null;
        }
        $text = trim($this->stripReasoning($text));
        $text = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text) ?? $text;
        return trim($text) !== '' ? trim($text) : null;
    }

    private function stripReasoning(string $text): string
    {
        $text = preg_replace('/<think>.*?<\/think>/is', '', $text) ?? $text;
        $text = preg_replace('/<thinking>.*?<\/thinking>/is', '', $text) ?? $text;
        $text = preg_replace('/<\/?think>/i', '', $text) ?? $text;
        if (preg_match('/<think>/i', $text)) {
            $text = preg_replace('/<think>.*$/is', '', $text) ?? $text;
        }

        $starters = [
            "here's a thinking process",
            'here is a thinking process',
            'thinking process:',
            'let me think',
            'internal reasoning',
            'chain of thought',
        ];
        $lower = mb_strtolower($text);
        foreach ($starters as $start) {
            if (str_starts_with($lower, $start) || str_contains($lower, "\n" . $start)) {
                if (preg_match('/\n\s*(?:draft response|final answer|reply)\s*:?\s*\n/i', $text, $m, PREG_OFFSET_CAPTURE)) {
                    $text = substr($text, (int)$m[0][1] + strlen($m[0][0]));
                    break;
                }
                return '';
            }
        }

        return trim($text);
    }

    /**
     * @param list<array{role:string,content:string}> $history
     */
    private function complete(
        string $system,
        string $userMessage,
        array $history
    ): ?string {
        if ($this->groqKey() !== '') {
            try {
                return $this->completeGroq($system, $userMessage, $history);
            } catch (Throwable $e) {
                error_log('Groq WhatsApp AI failed: ' . $e->getMessage());
            }
        }

        if ($this->geminiKey() !== '') {
            try {
                return $this->completeGemini($system, $userMessage, $history);
            } catch (Throwable $e) {
                error_log('Gemini WhatsApp AI failed: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * @param list<array{role:string,content:string}> $history
     */
    private function completeGroq(
        string $system,
        string $userMessage,
        array $history
    ): string {
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $turn) {
            $role = $turn['role'] === 'assistant' ? 'assistant' : 'user';
            $content = trim($turn['content']);
            if ($content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $lastError = 'Groq request failed.';
        $modelSets = [$this->groqModels(false)];
        foreach ($modelSets as $models) {
            foreach ($models as $model) {
            foreach ([true, false] as $useMaxCompletion) {
                try {
                    $payload = [
                        'model' => $model,
                        'temperature' => 0.3,
                        'messages' => $messages,
                        'reasoning_format' => 'hidden',
                    ];
                    if ($useMaxCompletion) {
                        $payload['max_completion_tokens'] = $this->maxTokens;
                    } else {
                        $payload['max_tokens'] = $this->maxTokens;
                    }

                    $data = $this->httpJson(
                        'https://api.groq.com/openai/v1/chat/completions',
                        [
                            'Authorization: Bearer ' . $this->groqKey(),
                            'Content-Type: application/json',
                        ],
                        $payload
                    );

                    $text = $this->groqText($data);
                    if ($text === '') {
                        throw new \RuntimeException('Groq returned an empty reply from ' . $model . '.');
                    }

                    $this->rememberGroqModel($model);
                    return $text;
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                    $lower = strtolower($lastError);
                    if (str_contains($lower, 'invalid api key') || str_contains($lower, 'http 401')) {
                        throw $e;
                    }
                    if (
                        $useMaxCompletion
                        && (
                            str_contains($lastError, 'max_completion_tokens')
                            || str_contains($lastError, 'max_tokens')
                        )
                    ) {
                        continue;
                    }
                    if (
                        isset($payload['reasoning_format'])
                        && (
                            str_contains($lower, 'reasoning')
                            || str_contains($lower, 'unknown')
                            || str_contains($lower, 'unrecognized')
                        )
                    ) {
                        unset($payload['reasoning_format']);
                        try {
                            $data = $this->httpJson(
                                'https://api.groq.com/openai/v1/chat/completions',
                                [
                                    'Authorization: Bearer ' . $this->groqKey(),
                                    'Content-Type: application/json',
                                ],
                                $payload
                            );
                            $text = $this->groqText($data);
                            if ($text !== '') {
                                $this->rememberGroqModel($model);
                                return $text;
                            }
                        } catch (Throwable $ignored) {
                        }
                    }
                    if ($this->isRetryableModelError($lastError)) {
                        break;
                    }
                }
            }
            }
        }

        try {
            foreach ($this->groqModels(true) as $model) {
                if (in_array($model, $modelSets[0], true)) {
                    continue;
                }
                $data = $this->httpJson(
                    'https://api.groq.com/openai/v1/chat/completions',
                    [
                        'Authorization: Bearer ' . $this->groqKey(),
                        'Content-Type: application/json',
                    ],
                    [
                        'model' => $model,
                        'temperature' => 0.3,
                        'messages' => $messages,
                        'max_completion_tokens' => 700,
                        'reasoning_format' => 'hidden',
                    ]
                );
                $text = $this->groqText($data);
                if ($text !== '') {
                    $this->rememberGroqModel($model);
                    return $text;
                }
            }
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @return list<string>
     */
    private function groqModels(bool $includeListed = false): array
    {
        $preferred = trim($this->setting('whatsapp_ai_groq_model', 'qwen/qwen3.6-27b'));
        $blocked = [
            'llama-3.1-8b-instant',
            'llama-3.3-70b-versatile',
            'openai/gpt-oss-20b',
            'openai/gpt-oss-120b',
            'openai/gpt-oss-safeguard-20b',
        ];
        if ($preferred === '' || in_array($preferred, $blocked, true)) {
            $preferred = 'qwen/qwen3.6-27b';
        }

        $models = [
            $preferred,
            'qwen/qwen3.6-27b',
            'groq/compound-mini',
            'groq/compound',
        ];

        if ($includeListed) {
            foreach ($this->groqListedChatModels() as $id) {
                $models[] = $id;
            }
        }

        $unique = [];
        foreach ($models as $id) {
            $id = trim($id);
            if ($id === '' || in_array($id, $blocked, true) || in_array($id, $unique, true)) {
                continue;
            }
            $unique[] = $id;
        }

        return $unique !== [] ? $unique : ['qwen/qwen3.6-27b'];
    }

    /**
     * @return list<string>
     */
    private function groqListedChatModels(): array
    {
        try {
            $data = $this->httpJson(
                'https://api.groq.com/openai/v1/models',
                [
                    'Authorization: Bearer ' . $this->groqKey(),
                    'Accept: application/json',
                ],
                null,
                'GET'
            );
        } catch (Throwable $e) {
            error_log('Groq model list failed: ' . $e->getMessage());
            return [];
        }

        $skip = ['whisper', 'tts', 'orpheus', 'guard', 'safeguard'];
        $ids = [];
        foreach ((array)($data['data'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $lower = strtolower($id);
            foreach ($skip as $needle) {
                if (str_contains($lower, $needle)) {
                    continue 2;
                }
            }
            $ids[] = $id;
        }

        return $ids;
    }

    private function rememberGroqModel(string $model): void
    {
        if ($this->setting('whatsapp_ai_groq_model') === $model) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute(['whatsapp_ai_groq_model', $model]);
        } catch (Throwable $e) {
            error_log('Could not save Groq model: ' . $e->getMessage());
        }
    }

    private function isRetryableModelError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, 'decommissioned')
            || str_contains($message, 'deprecated')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'not found')
            || str_contains($message, 'invalid model')
            || str_contains($message, 'blocked')
            || str_contains($message, 'organization')
            || str_contains($message, 'http 403');
    }

    private function groqText(array $data): string
    {
        $message = $data['choices'][0]['message'] ?? [];
        if (!is_array($message)) {
            $message = [];
        }

        $content = $message['content'] ?? ($data['choices'][0]['text'] ?? '');
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $parts[] = (string)$part['text'];
                }
            }
            $content = implode("\n", $parts);
        }

        $text = $this->stripReasoning(trim((string)$content));
        return $text;
    }

    /**
     * @param list<array{role:string,content:string}> $history
     */
    private function completeGemini(
        string $system,
        string $userMessage,
        array $history
    ): string {
        $model = $this->setting('whatsapp_ai_gemini_model', 'gemini-2.0-flash');
        if ($model === '') {
            $model = 'gemini-2.0-flash';
        }

        $contents = [];
        foreach ($history as $turn) {
            $role = $turn['role'] === 'assistant' ? 'model' : 'user';
            $content = trim($turn['content']);
            if ($content === '') {
                continue;
            }
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $content]],
            ];
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key='
            . rawurlencode($this->geminiKey());

        $data = $this->httpJson(
            $url,
            ['Content-Type: application/json'],
            [
                'systemInstruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => $this->maxTokens,
                ],
            ]
        );

        $text = $this->stripReasoning(trim((string)($data['candidates'][0]['content']['parts'][0]['text'] ?? '')));
        if ($text === '') {
            throw new \RuntimeException('Gemini returned an empty reply.');
        }

        return $text;
    }

    private function systemPrompt(string $collegeContext): string
    {
        return <<<PROMPT
You are the WhatsApp assistant for Edexcel College (Sri Lanka), including Talk with AI when a LEARNER SNAPSHOT is present.

Speak like a helpful college receptionist for timetable, fees, rooms, and admin.
When the student asks about studying, quizzes, homework, marks, goals, or how to improve, switch into a personal learning assistant: remember their snapshot, give actionable next steps, and explain mistakes clearly.
Reply in the same language the student used (English, Sinhala, or Tamil). Mixed Sinhala-English and Tamil-English is OK.
Use WhatsApp formatting only: *bold* and _italic_. No markdown headings or tables.

COLLEGE DATA (this is the only source of truth for classes, times, rooms, teachers):
{$collegeContext}

Rules:
- Answer from COLLEGE DATA and OFFICIAL ANSWERS. Never invent a class time, room, teacher, fee, street address, or WhatsApp group.
- If asked "do you have / do you teach / do you offer" a course, use LIVE AVAILABLE CLASSES. Match IGCSE/AS/IAL/Year from the class name OR the level column. Match ICT from the class name OR subjects. If an IGCSE + ICT row exists, answer yes and name that class. Do not say you only have AS ICT or Year 6 Computing when an IGCSE ICT class/level is listed.
- Teacher names, subjects, phone numbers and emails from LIVE AVAILABLE CLASSES / teacher lists may be shared with anyone who asks (registered or not). Do not invent a number if it is not in the data.
- If asked how to register, join, enrol, or create an account, tell them to open the register link and follow the instructions.
- Students and parents may both message. If LIVE STUDENT FEES or attendance is present, use those numbers. If not, point to the portal tab and *7* — never guess rupees.
- If the data does not contain the answer, say so and offer to type *7* to reach an administrator.
- Keep replies under 12 short lines unless listing a timetable or exam/mock schedule.
- Do not mention Groq, Gemini, OpenAI, or that you are an AI unless asked.
- You may greet the student by name when it is in the data.
- Never output thinking, analysis, hidden reasoning, or tags such as <think>. Reply with the student-facing message only.
PROMPT;
    }

    private function groqKey(): string
    {
        $env = $this->cleanSecret((string)(getenv('GROQ_API_KEY') ?: ''));
        if ($env !== '') {
            return $env;
        }
        return $this->cleanSecret($this->setting('groq_api_key'));
    }

    private function geminiKey(): string
    {
        $env = $this->cleanSecret((string)(getenv('GEMINI_API_KEY') ?: ''));
        if ($env !== '') {
            return $env;
        }
        return $this->cleanSecret($this->setting('gemini_api_key'));
    }

    private function cleanSecret(string $value): string
    {
        $value = trim($value);
        $value = trim($value, "\"'");
        $value = preg_replace('/\s+/', '', $value) ?? $value;
        return $value;
    }

    private function setting(string $key, string $default = ''): string
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1'
            );
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value === false ? $default : (string)$value;
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function httpJson(
        string $url,
        array $headers,
        ?array $payload = null,
        string $method = 'POST'
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to start AI request.');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 18,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($response === false) {
            throw new \RuntimeException('AI connection error: ' . $error);
        }

        $decoded = json_decode((string)$response, true);
        if ($status < 200 || $status >= 300) {
            $detail = '';
            if (is_array($decoded)) {
                $detail = (string)(
                    $decoded['error']['message']
                    ?? $decoded['error']['code']
                    ?? $decoded['message']
                    ?? json_encode($decoded)
                );
            } else {
                $detail = substr((string)$response, 0, 300);
            }
            $detail = preg_replace('/gsk_[A-Za-z0-9]+/', 'gsk_***', $detail) ?? $detail;
            $detail = preg_replace('/key=[^&\s]+/', 'key=***', $detail) ?? $detail;
            throw new \RuntimeException('AI HTTP ' . $status . ': ' . $detail);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
