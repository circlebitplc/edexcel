<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class EvolutionApiService implements WhatsAppSender
{
    private string $baseUrl;
    private string $apiKey;
    private string $instance;

    public function __construct(?PDO $pdo = null)
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';

        $cfg = evolution_credentials($pdo);
        $this->baseUrl = evolution_normalize_api_url((string)($cfg['url'] ?? ''));
        $this->apiKey = trim((string)($cfg['key'] ?? ''));
        $this->instance = trim((string)($cfg['instance'] ?? 'edexcel'));

        if ($this->baseUrl === '' || $this->apiKey === '' || $this->instance === '') {
            throw new RuntimeException(
                'Evolution API is not configured. Set API URL to the Evolution server, e.g. http://127.0.0.1:8080 — not the college website.'
            );
        }
    }

    public function sendText(string $number, string $text): array
    {
        $target = self::normalizeSendTarget($number);
        if ($target === '') {
            throw new RuntimeException('Invalid WhatsApp number.');
        }

        $payloads = [
            [
                'number' => $target,
                'text' => $text,
                'delay' => 250,
                'linkPreview' => false,
            ],
            [
                'number' => $target,
                'textMessage' => ['text' => $text],
                'delay' => 250,
                'linkPreview' => false,
            ],
        ];

        $lastError = null;
        foreach ($payloads as $payload) {
            try {
                return $this->request(
                    'POST',
                    '/message/sendText/' . rawurlencode($this->instance),
                    $payload
                );
            } catch (\Throwable $e) {
                $lastError = $e;
                $msg = strtolower($e->getMessage());
                $retryable = str_contains($msg, 'http 400')
                    || str_contains($msg, 'requires property')
                    || str_contains($msg, 'textmessage')
                    || str_contains($msg, '"text"');
                if (!$retryable) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new RuntimeException('Evolution sendText failed.');
    }

    public function setWebhook(string $webhookUrl, array $events = ['MESSAGES_UPSERT'], string $secret = ''): array
    {
        $webhook = [
            'enabled' => true,
            'url' => $webhookUrl,
            'webhookByEvents' => false,
            'webhookBase64' => false,
            'events' => $events,
        ];
        if ($secret !== '') {
            $webhook['headers'] = [
                'x-evolution-webhook-secret' => $secret,
            ];
        }

        return $this->request('POST', '/webhook/set/' . rawurlencode($this->instance), [
            'webhook' => $webhook,
        ]);
    }

    public function fetchInstances(): array
    {
        return $this->request('GET', '/instance/fetchInstances?instanceName=' . rawurlencode($this->instance));
    }

    public function resolveGroupJid(string $whatsappLink): string
    {
        $whatsappLink = trim($whatsappLink);
        if ($whatsappLink === '') {
            throw new RuntimeException('Missing WhatsApp group link.');
        }

        $direct = self::extractWhatsAppJid($whatsappLink);
        if ($direct !== '' && str_ends_with($direct, '@g.us')) {
            return $direct;
        }

        $inviteCode = null;
        if (preg_match('~chat\.whatsapp\.com/([A-Za-z0-9_-]+)~', $whatsappLink, $matches)) {
            $inviteCode = $matches[1];
        }
        if ($inviteCode === null) {
            throw new RuntimeException('Invalid WhatsApp group invite link.');
        }

        $result = $this->request(
            'GET',
            '/group/inviteInfo/' . rawurlencode($this->instance)
            . '?inviteCode=' . rawurlencode($inviteCode)
        );

        $jid = self::extractWhatsAppJid($result);
        if ($jid === '' || !str_ends_with($jid, '@g.us')) {
            throw new RuntimeException(
                'Evolution API did not return a valid group JID: '
                . substr(json_encode($result) ?: '', 0, 300)
            );
        }

        return $jid;
    }

    public function fetchProfilePictureUrl(string $number): ?string
    {
        $number = preg_replace('/\D+/', '', $number) ?? '';
        if ($number === '') {
            return null;
        }

        try {
            $data = $this->request('POST', '/chat/fetchProfilePictureUrl/' . rawurlencode($this->instance), [
                'number' => $number,
            ]);
            $url = trim((string)($data['profilePictureUrl'] ?? ''));
            return $url !== '' ? $url : null;
        } catch (\Throwable $e) {
            error_log('Evolution API DP fetch failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param mixed $value
     */
    public static function extractWhatsAppJid(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/[0-9.]+@g\.us/', $value, $m)) {
                return $m[0];
            }
            if (str_ends_with($value, '@g.us')) {
                return $value;
            }
            return '';
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['_serialized', 'id', 'groupJid', 'remoteJid', 'jid', 'participant'] as $key) {
            if (!isset($value[$key])) {
                continue;
            }
            $found = self::extractWhatsAppJid($value[$key]);
            if ($found !== '') {
                return $found;
            }
        }

        foreach ($value as $nested) {
            if (!is_array($nested) && !is_string($nested)) {
                continue;
            }
            $found = self::extractWhatsAppJid($nested);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    public static function normalizeSendTarget(string $number): string
    {
        $number = trim($number);
        $jid = self::extractWhatsAppJid($number);
        if ($jid !== '') {
            return $jid;
        }

        if (str_ends_with($number, '@g.us')) {
            return $number;
        }

        return preg_replace('/\D+/', '', $number) ?? '';
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required.');
        }

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($response === false) {
            throw new RuntimeException('Evolution API connection error: ' . $error);
        }

        $decoded = json_decode((string)$response, true);
        if ($status < 200 || $status >= 300) {
            $detail = is_array($decoded) ? json_encode($decoded) : substr((string)$response, 0, 500);
            throw new RuntimeException('Evolution API HTTP ' . $status . ': ' . $detail);
        }

        return is_array($decoded) ? $decoded : ['raw' => $response];
    }
}
