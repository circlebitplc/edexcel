<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class MetaCloudApiService implements WhatsAppSender
{
    private string $token;
    private string $phoneNumberId;
    private string $graphVersion;
    private string $appSecret;

    public function __construct(?PDO $pdo = null)
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';
        require_once dirname(__DIR__, 2) . '/config/whatsapp_gateway.php';

        $cfg = meta_cloud_credentials($pdo);
        $this->token = $cfg['token'];
        $this->phoneNumberId = $cfg['phone_number_id'];
        $this->graphVersion = $cfg['graph_version'];
        $this->appSecret = $cfg['app_secret'];

        if ($this->token === '' || $this->phoneNumberId === '') {
            throw new RuntimeException(
                'Meta Cloud API is not configured. Save the access token and Phone Number ID in System Settings.'
            );
        }
    }

    public function sendText(string $number, string $text): array
    {
        $to = preg_replace('/\D+/', '', $number) ?? '';
        if (str_starts_with($to, '0') && strlen($to) === 10) {
            $to = '94' . substr($to, 1);
        }
        if (strlen($to) < 10) {
            throw new RuntimeException('Invalid WhatsApp number.');
        }

        $text = trim($text);
        if (mb_strlen($text) > 4096) {
            $text = rtrim(mb_substr($text, 0, 4090)) . '…';
        }

        return $this->request('POST', '/' . rawurlencode($this->phoneNumberId) . '/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $payload): array
    {
        $url = 'https://graph.facebook.com/' . $this->graphVersion . $path;
        if ($this->appSecret !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?')
                . 'appsecret_proof=' . rawurlencode(hash_hmac('sha256', $this->token, $this->appSecret));
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start Meta Cloud API request.');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno) {
            throw new RuntimeException('Meta Cloud API connection failed.');
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($http >= 400) {
            $msg = is_array($decoded)
                ? (string)($decoded['error']['message'] ?? $raw)
                : (string)$raw;
            $code = is_array($decoded) ? (int)($decoded['error']['code'] ?? 0) : 0;
            if ($code === 133010 || str_contains(strtolower($msg), 'not registered')) {
            throw new RuntimeException(
                'This WhatsApp number is not registered on Cloud API. In the new app, open WhatsApp → API Setup, register the college number, then paste a fresh token.'
            );
            }
            throw new RuntimeException('Meta Cloud API HTTP ' . $http . ': ' . $msg);
        }

        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }
}
