<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * iPromo Marketing SMS provider (console.ipromo.lk).
 *
 * This class is intentionally isolated so that the entire iPromo
 * integration can be replaced by updating only this file.
 *
 * API reference: https://console.ipromo.lk/api/documentation/
 *
 * iPromo uses a simple HTTP GET/POST request with the following parameters
 * (based on their documented API for Niftra Solutions platform):
 *   - username  : your iPromo account username
 *   - apikey    : your iPromo API key (from the console)
 *   - sender    : registered sender ID
 *   - to        : destination number in international format (e.g. 94771234567)
 *   - message   : URL-encoded message text
 *
 * The endpoint returns a JSON response.
 * On success: {"code":200,"message":"1","count":1}   (message field = number of recipients sent)
 * On failure: {"code":401,"message":"Invalid credentials"} or similar.
 *
 * IMPORTANT: If iPromo updates their API, only this class needs changing.
 * Never put credentials in logs, HTML, or error messages shown to non-admins.
 */
final class IPromoSmsProvider
{
    /** Default API endpoint (configurable per installation). */
    public const DEFAULT_API_URL = 'https://console.ipromo.lk/api/v3/sms/send';

    private string $apiUrl;
    private string $username;
    private string $apiKey;
    private string $senderId;

    public function __construct(
        string $apiUrl,
        string $username,
        string $apiKey,
        string $senderId
    ) {
        $this->apiUrl   = rtrim(trim($apiUrl), '/');
        $this->username = trim($username);
        $this->apiKey   = trim($apiKey);
        $this->senderId = trim($senderId);
    }

    /**
     * Build an IPromoSmsProvider from the application's settings table.
     *
     * @param PDO $pdo Live database connection
     */
    public static function fromSettings(PDO $pdo): self
    {
        $get = static function (string $key, string $default = '') use ($pdo): string {
            try {
                $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                $stmt->execute([$key]);
                $v = $stmt->fetchColumn();
                return $v === false ? $default : (string)$v;
            } catch (Throwable) {
                return $default;
            }
        };

        $url      = $get('ipromo_api_url', self::DEFAULT_API_URL);
        $username = $get('ipromo_username', '');
        $apiKey   = $get('ipromo_api_key', '');
        $sender   = $get('ipromo_sender_id', '');

        // Fall back to env vars so secrets can stay out of the DB entirely.
        if ($username === '') {
            $username = trim((string)(getenv('IPROMO_USERNAME') ?: ''));
        }
        if ($apiKey === '') {
            $apiKey = trim((string)(getenv('IPROMO_API_KEY') ?: ''));
        }
        if ($sender === '') {
            $sender = trim((string)(getenv('IPROMO_SENDER_ID') ?: ''));
        }
        if ($url === '' || $url === self::DEFAULT_API_URL) {
            $envUrl = trim((string)(getenv('IPROMO_API_URL') ?: ''));
            if ($envUrl !== '') {
                $url = $envUrl;
            }
        }

        return new self(
            $url ?: self::DEFAULT_API_URL,
            $username,
            $apiKey,
            $sender
        );
    }

    /**
     * Check whether the required credentials are all present.
     */
    public function isConfigured(): bool
    {
        return $this->username !== ''
            && $this->apiKey   !== ''
            && $this->senderId !== ''
            && $this->apiUrl   !== '';
    }

    /**
     * Return a safe description for the admin (never exposes the key itself).
     */
    public function configSummary(): string
    {
        if (!$this->isConfigured()) {
            return 'iPromo credentials are incomplete.';
        }
        $masked = strlen($this->apiKey) > 6
            ? substr($this->apiKey, 0, 3) . str_repeat('*', strlen($this->apiKey) - 6) . substr($this->apiKey, -3)
            : '***';
        return sprintf(
            'Username: %s | Sender: %s | Key: %s',
            htmlspecialchars($this->username, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->senderId, ENT_QUOTES, 'UTF-8'),
            $masked
        );
    }

    /**
     * Normalize a Sri Lankan phone number to iPromo's required format.
     *
     * iPromo expects the international format WITHOUT the leading '+':
     *   94771234567
     *
     * Accepted inputs:
     *   0771234567   → 94771234567
     *   771234567    → 94771234567
     *   94771234567  → 94771234567
     *   +94771234567 → 94771234567
     *
     * @return string Normalized number, or '' on failure.
     */
    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // Strip leading + sign that was stripped by preg_replace already (it is \D).
        // Handle leading 0 (local format)
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '94' . substr($digits, 1);
        }

        // Handle 9-digit number (missing both leading 0 and country code)
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '94' . $digits;
        }

        // Must now be 11 digits starting with 947
        if (!preg_match('/^947\d{8}$/', $digits)) {
            return '';
        }

        return $digits;
    }

    /**
     * Validate that the normalized number is a plausible Sri Lankan mobile number.
     */
    public static function isValidSriLankanMobile(string $normalized): bool
    {
        return preg_match('/^947\d{8}$/', $normalized) === 1;
    }

    /**
     * Send one SMS via the iPromo API.
     *
     * @param string $phone   Raw phone number (any supported format).
     * @param string $message Plain text message body.
     * @return array{success:bool, provider:string, status:string,
     *               message_id:string, error:string, raw_code:int}
     */
    public function send(string $phone, string $message): array
    {
        $fail = static function (string $error, int $code = 0): array {
            return [
                'success'    => false,
                'provider'   => 'ipromo',
                'status'     => 'failed',
                'message_id' => '',
                'error'      => $error,
                'raw_code'   => $code,
            ];
        };

        if (!$this->isConfigured()) {
            return $fail('iPromo credentials are not configured.');
        }

        $to = self::normalizePhone($phone);
        if ($to === '') {
            return $fail('Invalid or unsupported phone number: ' . self::redactPhone($phone));
        }

        $message = trim($message);
        if ($message === '') {
            return $fail('Message body is empty.');
        }

        // Build the request parameters exactly as documented by iPromo.
        $params = [
            'username' => $this->username,
            'apikey'   => $this->apiKey,
            'sender'   => $this->senderId,
            'to'       => $to,
            'message'  => $message,
        ];

        $url = $this->apiUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init();
        if ($ch === false) {
            error_log('iPromo SMS: curl_init failed');
            return $fail('Could not start HTTPS request to iPromo.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: EdexcelCollege/1.0',
            ],
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $cerr  = curl_error($ch);
        $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log at the server level — never expose credentials in the log.
        $logTo = self::redactPhone($to);

        if ($errno !== 0) {
            $msg = 'cURL error ' . $errno;
            error_log('iPromo SMS to=' . $logTo . ' curl_error=' . $errno . ' ' . $cerr);
            return $fail($msg, 0);
        }

        if ($http < 200 || $http >= 300) {
            error_log('iPromo SMS to=' . $logTo . ' HTTP=' . $http . ' body=' . substr((string)$body, 0, 200));
            return $fail('iPromo API returned HTTP ' . $http . '.', $http);
        }

        $decoded = is_string($body) ? json_decode($body, true) : null;

        if (!is_array($decoded)) {
            error_log('iPromo SMS to=' . $logTo . ' invalid JSON: ' . substr((string)$body, 0, 200));
            return $fail('Unexpected response from iPromo API.');
        }

        $apiCode    = (int)($decoded['code'] ?? 0);
        $apiMessage = (string)($decoded['message'] ?? '');
        $messageId  = (string)($decoded['msgid'] ?? $decoded['message_id'] ?? $decoded['id'] ?? '');

        error_log('iPromo SMS to=' . $logTo . ' code=' . $apiCode . ' msg=' . $apiMessage . ' id=' . $messageId);

        if ($apiCode === 200 || $apiCode === 201 || (string)$apiMessage === '1' || is_numeric($apiMessage)) {
            return [
                'success'    => true,
                'provider'   => 'ipromo',
                'status'     => 'sent',
                'message_id' => $messageId,
                'error'      => '',
                'raw_code'   => $apiCode,
            ];
        }

        // Map common iPromo API error codes to human-readable (non-credential-exposing) messages.
        $errorText = match ($apiCode) {
            401 => 'iPromo authentication failed. Check your API credentials.',
            402 => 'iPromo account has insufficient credit.',
            403 => 'iPromo API access forbidden.',
            404 => 'iPromo API endpoint not found. Check the API URL in settings.',
            429 => 'iPromo API rate limit exceeded. Please wait before sending more.',
            default => 'iPromo rejected the message (code ' . $apiCode . ').',
        };

        // Use API message if it does not contain credentials
        if ($apiMessage !== '' && !preg_match('/key|token|password|secret/i', $apiMessage)) {
            $errorText = self::clip($apiMessage, 180);
        }

        return $fail($errorText, $apiCode);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private static function redactPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return '***';
        }
        return substr($phone, 0, 3) . str_repeat('*', max(0, strlen($phone) - 6)) . substr($phone, -3);
    }

    private static function clip(string $s, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
    }
}
