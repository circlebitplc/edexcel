<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Classroom whiteboard handwriting → text via Gemini vision.
 * API keys come from env / settings only (never exposed to the browser).
 */
final class HandwritingRecognitionService
{
    private const MAX_IMAGE_BYTES = 2_500_000;
    private const DEFAULT_MODEL = 'gemini-2.0-flash';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{subject?:string,hint?:string} $options
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   raw_text?:string,
     *   corrected_text?:string,
     *   confidence?:float,
     *   type?:string,
     *   latex?:string,
     *   alternatives?:list<array{text:string,confidence:float}>,
     *   corrections?:list<array{from:string,to:string}>,
     *   uncertain?:bool
     * }
     */
    public function recognize(string $imageBase64, string $mime, array $options = []): array
    {
        if ($this->setting('handwriting_enabled', '1') === '0') {
            return ['ok' => false, 'error' => 'Handwriting recognition is turned off in System Settings.'];
        }

        $mime = strtolower(trim($mime));
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'Unsupported image type.'];
        }

        $raw = preg_replace('/\s+/', '', $imageBase64) ?? '';
        if ($raw === '' || !preg_match('/^[A-Za-z0-9+\/=]+$/', $raw)) {
            return ['ok' => false, 'error' => 'Invalid image data.'];
        }
        $decodedLen = (int)(strlen($raw) * 0.75);
        if ($decodedLen < 80 || $decodedLen > self::MAX_IMAGE_BYTES) {
            return ['ok' => false, 'error' => 'Image is too small or too large to recognize.'];
        }

        $key = $this->geminiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'Handwriting recognition is not configured yet.'];
        }

        $subject = strtolower(trim((string)($options['subject'] ?? 'general')));
        if (!in_array($subject, ['general', 'math', 'chem', 'phys'], true)) {
            $subject = 'general';
        }
        $hint = trim((string)($options['hint'] ?? ''));
        if (function_exists('mb_substr')) {
            $hint = (string)mb_substr($hint, 0, 200);
        } else {
            $hint = substr($hint, 0, 200);
        }

        try {
            $text = $this->callGeminiVision($key, $raw, $mime, $subject, $hint);
        } catch (Throwable $e) {
            error_log('Handwriting OCR failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $this->publicError($e->getMessage())];
        }

        $parsed = $this->parseModelJson($text);
        if ($parsed === null) {
            $fallback = trim($text);
            if ($fallback === '') {
                return ['ok' => false, 'error' => 'No text could be read from the selection.'];
            }
            $parsed = [
                'raw_text' => $fallback,
                'corrected_text' => $fallback,
                'confidence' => 0.55,
                'type' => 'text',
                'latex' => '',
                'alternatives' => [],
                'corrections' => [],
            ];
        }

        $rawText = $this->cleanText((string)($parsed['raw_text'] ?? ''));
        $corrected = $this->cleanText((string)($parsed['corrected_text'] ?? $rawText));
        if ($rawText === '' && $corrected === '') {
            return ['ok' => false, 'error' => 'No text could be read from the selection.'];
        }
        if ($corrected === '') {
            $corrected = $rawText;
        }
        if ($rawText === '') {
            $rawText = $corrected;
        }

        $confidence = (float)($parsed['confidence'] ?? 0.7);
        if (!is_finite($confidence)) {
            $confidence = 0.5;
        }
        $confidence = max(0.0, min(1.0, round($confidence, 3)));

        $type = strtolower((string)($parsed['type'] ?? 'text'));
        if (!in_array($type, ['text', 'math', 'chemistry', 'physics'], true)) {
            $type = 'text';
        }

        $latex = trim((string)($parsed['latex'] ?? ''));
        if (function_exists('mb_substr')) {
            $latex = (string)mb_substr($latex, 0, 500);
        } else {
            $latex = substr($latex, 0, 500);
        }

        $alternatives = [];
        foreach ((array)($parsed['alternatives'] ?? []) as $alt) {
            if (!is_array($alt)) {
                continue;
            }
            $t = $this->cleanText((string)($alt['text'] ?? ''));
            if ($t === '' || $t === $corrected) {
                continue;
            }
            $c = (float)($alt['confidence'] ?? 0.4);
            $alternatives[] = [
                'text' => $t,
                'confidence' => max(0.0, min(1.0, round(is_finite($c) ? $c : 0.4, 3))),
            ];
            if (count($alternatives) >= 4) {
                break;
            }
        }

        $corrections = [];
        foreach ((array)($parsed['corrections'] ?? []) as $fix) {
            if (!is_array($fix)) {
                continue;
            }
            $from = $this->cleanText((string)($fix['from'] ?? ''));
            $to = $this->cleanText((string)($fix['to'] ?? ''));
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }
            $corrections[] = ['from' => $from, 'to' => $to];
            if (count($corrections) >= 20) {
                break;
            }
        }

        return [
            'ok' => true,
            'raw_text' => $rawText,
            'corrected_text' => $corrected,
            'confidence' => $confidence,
            'type' => $type,
            'latex' => $latex,
            'alternatives' => $alternatives,
            'corrections' => $corrections,
            'uncertain' => $confidence < 0.65 || $alternatives !== [],
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public static function testConfiguration(PDO $pdo): array
    {
        $svc = new self($pdo);
        if ($svc->setting('handwriting_enabled', '1') === '0') {
            return [
                'ok' => false,
                'message' => 'Handwriting recognition is turned off. Enable it and save, then test again.',
            ];
        }
        $key = $svc->geminiKey();
        if ($key === '') {
            return [
                'ok' => false,
                'message' => 'No Gemini API key found. Paste a key in Handwriting to text settings, or set HANDWRITING_GEMINI_API_KEY / GEMINI_API_KEY on the server.',
            ];
        }
        $model = trim($svc->setting('classroom_h2t_gemini_model', self::DEFAULT_MODEL));
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }

        $lastError = 'Gemini request failed.';
        foreach ($svc->modelCandidates($model) as $tryModel) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                    . rawurlencode($tryModel)
                    . ':generateContent?key='
                    . rawurlencode($key);
                // Thinking models count reasoning against maxOutputTokens; a tiny
                // budget produces finishReason=MAX_TOKENS with empty text.
                $data = $svc->httpJson($url, [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => 'Reply with the single word OK.']],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => 256,
                        'thinkingConfig' => [
                            'thinkingBudget' => 0,
                        ],
                    ],
                ], 30);
                $text = $svc->extractText($data);
                if ($text === '') {
                    $reason = $svc->emptyReplyReason($data);
                    $finish = (string)($data['candidates'][0]['finishReason'] ?? '');
                    $lastError = 'Gemini answered but returned empty text for model ' . $tryModel
                        . ($finish !== '' ? (' (finishReason=' . $finish . ')') : '')
                        . ($reason !== '' ? (' — ' . $reason) : '')
                        . '.';
                    continue;
                }
                return [
                    'ok' => true,
                    'message' => 'Handwriting API is working with model ' . $tryModel . '.',
                ];
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                $lower = strtolower($lastError);
                // thinkingConfig unsupported on older models — retry without it once.
                if (str_contains($lower, 'thinking') || str_contains($lower, 'unknown name')) {
                    try {
                        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                            . rawurlencode($tryModel)
                            . ':generateContent?key='
                            . rawurlencode($key);
                        $data = $svc->httpJson($url, [
                            'contents' => [[
                                'role' => 'user',
                                'parts' => [['text' => 'Reply with the single word OK.']],
                            ]],
                            'generationConfig' => [
                                'temperature' => 0,
                                'maxOutputTokens' => 256,
                            ],
                        ], 30);
                        $text = $svc->extractText($data);
                        if ($text !== '') {
                            return [
                                'ok' => true,
                                'message' => 'Handwriting API is working with model ' . $tryModel . '.',
                            ];
                        }
                    } catch (Throwable $e2) {
                        $lastError = $e2->getMessage();
                    }
                }
                if (str_contains($lower, 'api key not valid')
                    || str_contains($lower, 'invalid api key')
                    || str_contains($lower, 'http 401')) {
                    break;
                }
            }
        }

        $lastError = preg_replace('/key=[^&\s]+/', 'key=***', $lastError) ?? $lastError;
        return [
            'ok' => false,
            'message' => 'Handwriting API test failed: ' . $lastError,
        ];
    }

    /** @return list<string> */
    private function modelCandidates(string $preferred): array
    {
        // Prefer stable non-thinking-first flash models; flash-latest often thinks
        // and returns empty when maxOutputTokens is too small.
        $models = [
            $preferred,
            'gemini-2.0-flash',
            'gemini-2.0-flash-001',
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
            'gemini-2.5-flash',
            'gemini-flash-latest',
        ];
        $out = [];
        foreach ($models as $id) {
            $id = trim($id);
            if ($id === '' || in_array($id, $out, true)) {
                continue;
            }
            $out[] = $id;
        }
        return $out !== [] ? $out : [self::DEFAULT_MODEL];
    }

    private function publicError(string $raw): string
    {
        $raw = preg_replace('/key=[^&\s]+/', 'key=***', $raw) ?? $raw;
        $raw = preg_replace('/AIza[0-9A-Za-z_-]+/', 'AIza***', $raw) ?? $raw;
        $lower = strtolower($raw);
        if (str_contains($lower, 'api key not valid')
            || str_contains($lower, 'invalid api key')
            || (str_contains($lower, 'http 400') && str_contains($lower, 'api key'))) {
            return 'Gemini API key is invalid. Update it under System Settings → Handwriting to text.';
        }
        if (str_contains($lower, 'http 403') || str_contains($lower, 'permission')) {
            return 'Gemini refused the request (permission/billing). Check the API key in Google AI Studio.';
        }
        if (str_contains($lower, 'not found') || str_contains($lower, 'http 404')) {
            return 'Gemini model not found. Try model gemini-2.0-flash in Handwriting settings.';
        }
        if (str_contains($lower, 'quota') || str_contains($lower, 'rate') || str_contains($lower, 'resource_exhausted')) {
            return 'Gemini quota exceeded. Wait a minute or check usage in Google AI Studio.';
        }
        if (str_contains($lower, 'connection') || str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return 'Could not reach Gemini. Check server outbound HTTPS and try again.';
        }
        if (str_contains($lower, 'empty')) {
            return 'Gemini returned no text for that selection. Try a larger box around clearer handwriting.';
        }
        $short = trim($raw);
        if (function_exists('mb_substr')) {
            $short = (string)mb_substr($short, 0, 180);
        } else {
            $short = substr($short, 0, 180);
        }
        return $short !== ''
            ? ('Could not recognize that handwriting: ' . $short)
            : 'Could not recognize that handwriting. Please try again.';
    }

    private function callGeminiVision(
        string $key,
        string $base64,
        string $mime,
        string $subject,
        string $hint
    ): string {
        $preferred = $this->setting('classroom_h2t_gemini_model', self::DEFAULT_MODEL);
        if ($preferred === '') {
            $preferred = self::DEFAULT_MODEL;
        }

        $system = $this->systemPrompt($subject);
        $userText = "Recognize the handwriting in this whiteboard crop.\n"
            . "Subject mode: {$subject}.\n"
            . ($hint !== '' ? "Teacher hint: {$hint}\n" : '')
            . "Return ONLY valid JSON matching the schema in the system instruction.";

        // Try both JSON naming styles used by Gemini REST.
        $imagePartVariants = [
            [
                'inline_data' => [
                    'mime_type' => $mime,
                    'data' => $base64,
                ],
            ],
            [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => $base64,
                ],
            ],
        ];

        $lastError = 'Gemini vision request failed.';
        foreach ($this->modelCandidates($preferred) as $model) {
            foreach ($imagePartVariants as $imagePart) {
                foreach ([true, false] as $wantJsonMime) {
                    try {
                        $payload = [
                            'systemInstruction' => [
                                'parts' => [['text' => $system]],
                            ],
                            'contents' => [[
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $userText],
                                    $imagePart,
                                ],
                            ]],
                            'generationConfig' => [
                                'temperature' => 0.1,
                                // Leave room for any residual thinking + JSON output.
                                'maxOutputTokens' => 2048,
                                'thinkingConfig' => [
                                    'thinkingBudget' => 0,
                                ],
                            ],
                        ];
                        if ($wantJsonMime) {
                            $payload['generationConfig']['responseMimeType'] = 'application/json';
                        }

                        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                            . rawurlencode($model)
                            . ':generateContent?key='
                            . rawurlencode($key);

                        try {
                            $data = $this->httpJson($url, $payload, 60);
                        } catch (Throwable $e) {
                            // Older models reject thinkingConfig — retry without it.
                            $lower = strtolower($e->getMessage());
                            if (!str_contains($lower, 'thinking') && !str_contains($lower, 'unknown name')) {
                                throw $e;
                            }
                            unset($payload['generationConfig']['thinkingConfig']);
                            $data = $this->httpJson($url, $payload, 60);
                        }
                        $text = $this->extractText($data);
                        if ($text !== '') {
                            if ($model !== $preferred) {
                                error_log('Handwriting OCR succeeded with fallback model: ' . $model);
                            }
                            return $text;
                        }
                        $lastError = $this->emptyReplyReason($data)
                            ?: ('Gemini returned an empty OCR reply from ' . $model . '.');
                    } catch (Throwable $e) {
                        $lastError = $e->getMessage();
                        $lower = strtolower($lastError);
                        if (str_contains($lower, 'api key not valid')
                            || str_contains($lower, 'invalid api key')
                            || str_contains($lower, 'http 401')
                            || (str_contains($lower, 'http 403') && str_contains($lower, 'api key'))) {
                            throw $e;
                        }
                        if (str_contains($lower, 'not found') || str_contains($lower, 'http 404')) {
                            break 2; // next model
                        }
                    }
                }
            }
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function extractText(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $out = '';
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (!is_array($part) || !isset($part['text'])) {
                    continue;
                }
                // Skip thought/reasoning parts when includeThoughts is on.
                if (!empty($part['thought'])) {
                    continue;
                }
                $out .= (string)$part['text'];
            }
        }
        return trim($out);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function emptyReplyReason(array $data): string
    {
        $block = (string)($data['promptFeedback']['blockReason'] ?? '');
        if ($block !== '') {
            return 'Gemini blocked the request (' . $block . ').';
        }
        $finish = (string)($data['candidates'][0]['finishReason'] ?? '');
        if ($finish !== '' && strtoupper($finish) !== 'STOP') {
            return 'Gemini stopped early (' . $finish . '). Try a clearer selection.';
        }
        return '';
    }

    private function systemPrompt(string $subject): string
    {
        $bias = match ($subject) {
            'math' => 'Prioritize mathematical notation, equations, Greek letters, operators (√ π ∑ ∫ ≤ ≥ ≠ ∞ → θ Δ), superscripts and subscripts. Prefer type "math" when the content is primarily an equation.',
            'chem' => 'Prioritize chemical formulas, element symbols, reactions, and subscripts (H2O → H₂O). Prefer type "chemistry" for formulas/equations. Never "correct" valid formulas like NaCl, H2SO4, CaCO3.',
            'phys' => 'Prioritize physics terminology and symbols (force, acceleration, velocity, Ω, λ). Prefer type "physics" for formulas.',
            default => 'Use general English classroom language. Preserve scientific terms when present.',
        };

        return <<<PROMPT
You are an educational whiteboard handwriting OCR and smart-correction assistant.

Tasks:
1) Transcribe handwriting faithfully into raw_text (preserve line breaks).
2) Produce corrected_text with spelling, capitalization, and obvious OCR fixes.
3) Preserve scientific/math/chemistry terminology — do NOT invent or alter valid terms such as photosynthesis, stoichiometry, mitochondria, differentiation, integration, hydrochloric acid, sodium chloride, oxidation, equilibrium, acceleration.
4) For math, use Unicode where helpful (x², √, π, ≤) and also fill latex when the content is an equation.
5) For chemistry, use Unicode subscripts (H₂O, CO₂, H₂SO₄) and set type to chemistry.
6) If unsure between readings, lower confidence and list alternatives.

{$bias}

Return JSON only:
{
  "raw_text": "string",
  "corrected_text": "string",
  "confidence": 0.0,
  "type": "text|math|chemistry|physics",
  "latex": "string",
  "alternatives": [{"text":"string","confidence":0.0}],
  "corrections": [{"from":"string","to":"string"}]
}

Rules:
- confidence is 0–1.
- Keep corrected_text close to the handwriting; only fix clear mistakes.
- Empty alternatives array if confident.
- Never wrap JSON in markdown fences.
PROMPT;
    }

    /**
     * @return ?array<string,mixed>
     */
    private function parseModelJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        $text = trim($text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = trim($text);
        if (function_exists('mb_substr')) {
            return (string)mb_substr($text, 0, 800);
        }
        return substr($text, 0, 800);
    }

    private function geminiKey(): string
    {
        $envHw = $this->cleanSecret((string)(getenv('HANDWRITING_GEMINI_API_KEY') ?: ''));
        if ($envHw !== '') {
            return $envHw;
        }
        $env = $this->cleanSecret((string)(getenv('GEMINI_API_KEY') ?: ''));
        if ($env !== '') {
            return $env;
        }
        $dedicated = $this->cleanSecret($this->setting('handwriting_gemini_api_key'));
        if ($dedicated !== '') {
            return $dedicated;
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

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function httpJson(string $url, array $payload, int $timeout = 45): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('Could not encode recognition request.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to start recognition request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(15, $timeout),
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($response === false) {
            throw new \RuntimeException('Recognition connection error: ' . $error);
        }

        $decoded = json_decode((string)$response, true);
        if ($status < 200 || $status >= 300) {
            $detail = '';
            if (is_array($decoded)) {
                $detail = (string)(
                    $decoded['error']['message']
                    ?? $decoded['error']['status']
                    ?? $decoded['message']
                    ?? ''
                );
            } else {
                $detail = substr((string)$response, 0, 240);
            }
            $detail = preg_replace('/key=[^&\s]+/', 'key=***', $detail) ?? $detail;
            throw new \RuntimeException('Recognition HTTP ' . $status . ': ' . $detail);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
