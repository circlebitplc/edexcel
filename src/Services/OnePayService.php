<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * OnePay v3 redirection API (https://docs.onepay.lk/api-documentation/payment-api).
 *
 * Create: POST /v3/checkout/link/
 * Hash: sha256(app_id + currency + amount + HASH_SALT) lowercase hex
 * Status: POST /v3/transaction/status/ with app_id + onepay_transaction_id
 * Callback is unsigned; always re-verify with the status API.
 */
final class OnePayService
{
    private bool $enabled;
    private string $appId;
    private string $appToken;
    private string $hashSalt;
    private string $apiUrl;
    private string $currency;
    private string $returnUrl;

    public function __construct(?PDO $pdo = null)
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/config/onepay.php';
        $cfg = onepay_config($pdo);
        $this->enabled = $cfg['enabled'];
        $this->appId = $cfg['app_id'];
        $this->appToken = $cfg['app_token'];
        $this->hashSalt = $cfg['hash_salt'];
        $this->apiUrl = $cfg['api_url'];
        $this->currency = $cfg['currency'];
        $this->returnUrl = $cfg['return_url'];
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->hashSalt !== '';
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function returnUrl(): string
    {
        return $this->returnUrl;
    }

    public static function hashFor(string $appId, string $currency, float|string|int $amount, string $hashSalt): string
    {
        $amount = PaymentVerificationService::formatAmount($amount);
        return hash('sha256', $appId . $currency . $amount . $hashSalt);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'App ID and hash salt are required.'];
        }
        try {
            $this->request('/v3/transaction/status/', [
                'app_id' => $this->appId,
                'onepay_transaction_id' => 'EDEXCELTESTCONNECTION00',
            ]);
            return ['ok' => true, 'message' => 'Connection successful. OnePay accepted the application credentials.'];
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_contains(strtolower($msg), '401') || str_contains(strtolower($msg), 'unauthor')) {
                return ['ok' => false, 'message' => 'OnePay rejected the credentials. Check App ID, app token, and hash salt.'];
            }
            return ['ok' => true, 'message' => 'Connection successful. OnePay accepted the request (test transaction id is not a real payment).'];
        }
    }

    /**
     * @param array{
     *   reference:string,
     *   amount:float|string,
     *   first_name:string,
     *   last_name:string,
     *   phone:string,
     *   email:string,
     *   additional?:string
     * } $input
     * @return array{redirect_url:string,ipg_transaction_id:string,raw:array<string,mixed>}
     */
    public function createCheckout(array $input): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Online payments are not available yet.');
        }
        $amount = PaymentVerificationService::formatAmount($input['amount']);
        $reference = trim((string)$input['reference']);
        if (strlen($reference) < 10) {
            throw new RuntimeException('Payment reference is invalid.');
        }
        $payload = [
            'app_id' => $this->appId,
            'amount' => $amount,
            'currency' => $this->currency,
            'hash' => self::hashFor($this->appId, $this->currency, $amount, $this->hashSalt),
            'reference' => $reference,
            'customer_first_name' => $this->safeName((string)$input['first_name'], 'Student'),
            'customer_last_name' => $this->safeName((string)$input['last_name'], 'Edexcel'),
            'customer_phone_number' => $this->e164((string)$input['phone']),
            'customer_email' => $this->safeEmail((string)$input['email']),
            'transaction_redirect_url' => $this->returnUrl . '?ref=' . rawurlencode($reference),
        ];
        if (!empty($input['additional'])) {
            $payload['additionalData'] = (string)$input['additional'];
        }
        $raw = $this->request('/v3/checkout/link/', $payload);
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
        $redirect = (string)($data['gateway']['redirect_url'] ?? $data['redirect_url'] ?? '');
        $ipg = (string)($data['ipg_transaction_id']
            ?? $data['onepay_transaction_id']
            ?? $data['transaction_id']
            ?? '');
        if ($redirect === '') {
            $hint = trim((string)($raw['message'] ?? $data['message'] ?? $raw['error'] ?? ''));
            throw new RuntimeException($hint !== '' ? $hint : 'OnePay did not return a checkout URL.');
        }
        return [
            'redirect_url' => $redirect,
            'ipg_transaction_id' => $ipg,
            'raw' => $raw,
        ];
    }

    /**
     * Full refund via POST /v3/transaction/refund/. Live transactions only.
     *
     * @return array<string,mixed>
     */
    public function refund(string $onepayTransactionId, string $reason = 'REQUESTED_BY_CUSTOMER', string $note = ''): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('OnePay is not configured.');
        }
        $onepayTransactionId = trim($onepayTransactionId);
        if ($onepayTransactionId === '') {
            throw new RuntimeException('Missing OnePay transaction id.');
        }
        $allowed = ['DUPLICATED', 'FRAUDULENT', 'OUT_OF_ORDER', 'REQUESTED_BY_CUSTOMER', 'OTHER'];
        if (!in_array($reason, $allowed, true)) {
            $reason = 'REQUESTED_BY_CUSTOMER';
        }
        return $this->request('/v3/transaction/refund/', [
            'app_id' => $this->appId,
            'onepay_transaction_id' => $onepayTransactionId,
            'is_partially' => false,
            'refund_reason' => $reason,
            'refund_note' => $note !== '' ? mb_substr($note, 0, 200) : 'Class fee unmarked at Edexcel College',
        ]);
    }

    /**
     * @return array{paid:bool,amount:?float,currency:?string,paid_on:?string,ipg_transaction_id:?string,raw:array<string,mixed>}
     */
    public function transactionStatus(string $onepayTransactionId): array
    {
        if (!$this->isConfigured() || $onepayTransactionId === '') {
            return ['paid' => false, 'amount' => null, 'currency' => null, 'paid_on' => null, 'ipg_transaction_id' => null, 'raw' => []];
        }
        $raw = $this->request('/v3/transaction/status/', [
            'app_id' => $this->appId,
            'onepay_transaction_id' => $onepayTransactionId,
        ]);
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
        if (!is_array($data)) {
            $data = [];
        }
        $paidOn = isset($data['paid_on']) ? trim((string)$data['paid_on']) : '';
        if ($paidOn === '' || strtolower($paidOn) === 'null') {
            $paidOn = '';
        }
        $paid = self::isPaidFlag(
            $data['status'] ?? null,
            (string)($data['status_message'] ?? $raw['status_message'] ?? '')
        ) || $paidOn !== '';
        $amount = self::amountFromPayload($data);
        $currency = self::currencyFromPayload($data);
        return [
            'paid' => $paid,
            'amount' => $amount,
            'currency' => $currency,
            'paid_on' => $paidOn !== '' ? $paidOn : null,
            'ipg_transaction_id' => isset($data['ipg_transaction_id']) ? (string)$data['ipg_transaction_id'] : $onepayTransactionId,
            'raw' => $raw,
        ];
    }

    /**
     * Inner OnePay `data.status` is a boolean/1. Envelope `status: 200` is not a payment.
     *
     * @param mixed $status
     */
    public static function isPaidFlag(mixed $status, string $statusMessage = ''): bool
    {
        $message = strtoupper(trim($statusMessage));
        if (in_array($message, ['SUCCESS', 'PAID', 'COMPLETED', 'COMPLETE'], true)) {
            return true;
        }
        if ($status === true || $status === 1 || $status === '1') {
            return true;
        }
        if (!is_string($status)) {
            return false;
        }
        $s = strtoupper(trim($status));
        return in_array($s, ['SUCCESS', 'PAID', 'COMPLETED', 'TRUE'], true);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function amountFromPayload(array $data): ?float
    {
        $amount = $data['amount'] ?? $data['net_amount'] ?? $data['gross_amount'] ?? null;
        if (is_array($amount)) {
            return self::amountFromPayload($amount);
        }
        if (is_numeric($amount)) {
            return (float)$amount;
        }
        return null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function currencyFromPayload(array $data): ?string
    {
        $currency = $data['currency'] ?? null;
        if (is_string($currency) && $currency !== '') {
            return $currency;
        }
        $nested = $data['amount'] ?? null;
        if (is_array($nested) && isset($nested['currency']) && is_string($nested['currency']) && $nested['currency'] !== '') {
            return $nested['currency'];
        }
        return null;
    }

    private function safeName(string $name, string $fallback): string
    {
        $name = trim(preg_replace('/[^\p{L}\p{N}\s.\-\']/u', '', $name) ?? '');
        return $name !== '' ? mb_substr($name, 0, 80) : $fallback;
    }

    private function safeEmail(string $email): string
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return 'students@edexcel.college';
    }

    private function e164(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '94' . substr($digits, 1);
        }
        if ($digits === '') {
            $digits = '94710000000';
        }
        return '+' . $digits;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function request(string $path, array $payload): array
    {
        $url = $this->apiUrl . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start OnePay request.');
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->appToken !== '') {
            $headers[] = 'Authorization: ' . $this->appToken;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            throw new RuntimeException('OnePay connection failed.');
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($http === 401) {
            throw new RuntimeException('OnePay HTTP 401: credentials were rejected.');
        }
        if ($http >= 400) {
            $msg = is_array($decoded) ? (string)($decoded['message'] ?? $raw) : (string)$raw;
            throw new RuntimeException('OnePay HTTP ' . $http . ($msg !== '' ? ': ' . mb_substr($msg, 0, 180) : ''));
        }
        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }
}
