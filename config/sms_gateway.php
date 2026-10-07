<?php
declare(strict_types=1);

function student_otp_channel(?PDO $pdo = null): string
{
    $raw = sms_setting($pdo, 'student_otp_channel', (string)(getenv('STUDENT_OTP_CHANNEL') ?: ''));
    return strtolower(trim($raw)) === 'sms' ? 'sms' : 'whatsapp';
}

function student_otp_channel_name(?PDO $pdo = null): string
{
    return student_otp_channel($pdo) === 'sms' ? 'SMS' : 'WhatsApp';
}

/**
 * Preferred secret is X-SMS-Webhook-Secret. Authorization Bearer is next.
 * ?secret= is a temporary gateway fallback and cannot replace a header value.
 */
function sms_webhook_provided_secret(): string
{
    $header = trim((string)($_SERVER['HTTP_X_SMS_WEBHOOK_SECRET'] ?? ''));
    if ($header !== '') {
        return $header;
    }
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (str_starts_with(strtolower($authorization), 'bearer ')) {
        return trim(substr($authorization, 7));
    }
    return trim((string)($_GET['secret'] ?? ''));
}

function sms_setting(?PDO $pdo, string $key, string $default = ''): string
{
    if (!$pdo instanceof PDO) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function sms_gateway_config(?PDO $pdo = null): array
{
    $out = [
        'sms_gateway_mode' => sms_setting($pdo, 'sms_gateway_mode', 'cloud'),
        'sms_gateway_url' => sms_setting($pdo, 'sms_gateway_url', 'https://api.sms-gate.app/3rdparty/v1'),
        'sms_gateway_username' => sms_setting($pdo, 'sms_gateway_username', ''),
        'sms_gateway_password' => sms_setting($pdo, 'sms_gateway_password', ''),
        'sms_gateway_sim' => sms_setting($pdo, 'sms_gateway_sim', ''),
        'sms_gateway_device_id' => '',
    ];
    if ($out['sms_gateway_username'] === '') {
        $out['sms_gateway_username'] = trim((string)(getenv('SMS_GATEWAY_USERNAME') ?: ''));
    }
    if ($out['sms_gateway_password'] === '') {
        $out['sms_gateway_password'] = (string)(getenv('SMS_GATEWAY_PASSWORD') ?: '');
    }
    if ($out['sms_gateway_url'] === '') {
        $envUrl = trim((string)(getenv('SMS_GATEWAY_URL') ?: ''));
        $out['sms_gateway_url'] = $envUrl !== '' ? $envUrl : 'https://api.sms-gate.app/3rdparty/v1';
    }
    $mode = strtolower(trim($out['sms_gateway_mode']));
    $out['sms_gateway_mode'] = $mode === 'local' ? 'local' : 'cloud';
    $out['sms_gateway_url'] = sms_normalize_gateway_url($out['sms_gateway_url'], $out['sms_gateway_mode']);
    $out['sms_gateway_username'] = trim($out['sms_gateway_username']);
    $out['sms_gateway_password'] = trim($out['sms_gateway_password']);
    $out['sms_gateway_device_id'] = trim(sms_setting($pdo, 'sms_gateway_device_id', ''));
    return $out;
}

function sms_normalize_gateway_url(string $url, string $mode = 'cloud'): string
{
    $url = rtrim(trim($url), '/');
    $url = preg_replace('#/messages?$#i', '', $url) ?? $url;
    $url = rtrim($url, '/');
    if ($mode === 'local') {
        return $url;
    }
    if ($url === '' || preg_match('#^https://api\.sms-gate\.app(/mobile/v1)?$#i', $url)) {
        return 'https://api.sms-gate.app/3rdparty/v1';
    }
    if (stripos($url, '/mobile/v1') !== false) {
        $url = preg_replace('#/mobile/v1#i', '/3rdparty/v1', $url) ?? $url;
    }
    return rtrim($url, '/');
}

function sms_e164(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        $digits = '94' . substr($digits, 1);
    }
    if ($digits === '') {
        return '';
    }
    return '+' . $digits;
}

/**
 * Destination Android actually sends. Sri Lankan +94 numbers are often left in the
 * Honor Messages Outbox as international SMS; local 07XXXXXXXX goes out as domestic.
 */
function sms_android_address(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        $digits = '94' . substr($digits, 1);
    }
    if (preg_match('/^947\d{8}$/', $digits)) {
        return '0' . substr($digits, 2);
    }
    if ($digits === '') {
        return '';
    }
    return '+' . $digits;
}

function sms_gateway_endpoint(array $cfg): string
{
    $base = rtrim((string)($cfg['sms_gateway_url'] ?? ''), '/');
    if ($base === '') {
        return '';
    }
    if (preg_match('#/messages?$#i', $base)) {
        return $base;
    }
    if (($cfg['sms_gateway_mode'] ?? 'cloud') === 'local') {
        return $base . '/message';
    }
    return $base . '/messages';
}

function sms_send_last_error(?string $set = null): string
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

function sms_send_last_id(?string $set = null): string
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

function sms_send(?PDO $pdo, string $phone, string $text): bool
{
    sms_send_last_error('');
    sms_send_last_id('');
    $cfg = sms_gateway_config($pdo);
    $user = trim((string)$cfg['sms_gateway_username']);
    $pass = (string)$cfg['sms_gateway_password'];
    $endpoint = sms_gateway_endpoint($cfg);
    $to = sms_android_address($phone);
    $text = trim(str_replace('*', '', $text));
    if ($user === '' || $pass === '') {
        sms_send_last_error('Gateway username or password is missing. Copy both from the app Cloud Server section and save again.');
        return false;
    }
    if ($endpoint === '' || $to === '' || $text === '') {
        sms_send_last_error('Missing gateway URL or recipient number.');
        return false;
    }
    if ($cfg['sms_gateway_mode'] === 'cloud' && !preg_match('#^https://#i', $endpoint)) {
        sms_send_last_error('Cloud mode requires an https:// gateway URL.');
        return false;
    }

    $sep = str_contains($endpoint, '?') ? '&' : '?';
    $endpoint .= $sep . 'skipPhoneValidation=true';

    $payload = [
        'textMessage' => ['text' => $text],
        'phoneNumbers' => [$to],
        'withDeliveryReport' => true,
        'priority' => 100,
        'ttl' => 3600,
    ];
    $deviceId = trim((string)($cfg['sms_gateway_device_id'] ?? ''));
    if ($deviceId !== '') {
        $payload['deviceId'] = $deviceId;
    }
    $sim = trim((string)$cfg['sms_gateway_sim']);
    if ($sim !== '' && ctype_digit($sim)) {
        $payload['simNumber'] = (int)$sim;
    }

    $ch = curl_init($endpoint);
    if ($ch === false) {
        sms_send_last_error('Could not start the HTTPS request to the SMS gateway.');
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($user . ':' . $pass),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $cerr = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($errno !== 0) {
        $msg = 'Could not reach the SMS gateway (' . $errno . ').';
        if ($cerr !== '') {
            $msg .= ' ' . $cerr;
        }
        error_log('SMS gateway curl error ' . $errno . ' ' . $cerr);
        sms_send_last_error($msg);
        return false;
    }
    if ($status < 200 || $status >= 300) {
        $snippet = is_string($body) ? trim((string)preg_replace('/\s+/', ' ', $body)) : '';
        $snippet = substr($snippet, 0, 180);
        error_log('SMS gateway send failed HTTP ' . $status . ' ' . $snippet);
        if ($status === 401 || $status === 403) {
            sms_send_last_error('The gateway rejected the username/password (HTTP ' . $status . '). Use username/password from the app, keep the website URL as https://api.sms-gate.app/3rdparty/v1 (not mobile/v1), and restart the app if it says Restart required.');
        } elseif ($status === 404) {
            sms_send_last_error('Gateway URL was not found (HTTP 404). Use https://api.sms-gate.app/3rdparty/v1 for cloud mode.');
        } else {
            $hint = $snippet !== '' ? ' ' . $snippet : '';
            sms_send_last_error('Gateway returned HTTP ' . $status . '.' . $hint);
        }
        return false;
    }

    $decoded = json_decode(is_string($body) ? $body : '', true);
    $state = is_array($decoded) ? strtolower((string)($decoded['state'] ?? '')) : '';
    $msgId = is_array($decoded) ? (string)($decoded['id'] ?? '') : '';
    if ($msgId !== '') {
        sms_send_last_id($msgId);
    }
    error_log('SMS gateway queued to=' . $to . ' id=' . $msgId . ' state=' . $state);
    if ($state === 'failed') {
        $reason = is_array($decoded) ? trim((string)($decoded['reason'] ?? 'Failed')) : 'Failed';
        sms_send_last_error('The phone marked this SMS as failed: ' . $reason . '. Check Hutch credit, signal, and the default SMS SIM.');
        return false;
    }
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// iPromo SMS provider support
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Return the active SMS provider name ('sms-gate' or 'ipromo').
 */
function sms_active_provider(?PDO $pdo = null): string
{
    $raw = sms_setting($pdo, 'sms_provider', 'sms-gate');
    $raw = strtolower(trim($raw));
    return in_array($raw, ['sms-gate', 'ipromo'], true) ? $raw : 'sms-gate';
}

/**
 * Is iPromo enabled (master switch is ON)?
 */
function ipromo_enabled(?PDO $pdo = null): bool
{
    if (!$pdo instanceof PDO) {
        return false;
    }
    return sms_setting($pdo, 'ipromo_enabled', '0') === '1';
}

/**
 * Load iPromo configuration from the settings table + environment fallbacks.
 *
 * @return array{url:string, username:string, api_key:string, sender_id:string, enabled:bool}
 */
function ipromo_config(?PDO $pdo = null): array
{
    $url      = sms_setting($pdo, 'ipromo_api_url', 'https://console.ipromo.lk/api/v3/sms/send');
    $username = sms_setting($pdo, 'ipromo_username', '');
    $apiKey   = sms_setting($pdo, 'ipromo_api_key', '');
    $sender   = sms_setting($pdo, 'ipromo_sender_id', '');
    $enabled  = ipromo_enabled($pdo);

    if ($username === '') {
        $username = trim((string)(getenv('IPROMO_USERNAME') ?: ''));
    }
    if ($apiKey === '') {
        $apiKey = trim((string)(getenv('IPROMO_API_KEY') ?: ''));
    }
    if ($sender === '') {
        $sender = trim((string)(getenv('IPROMO_SENDER_ID') ?: ''));
    }
    $envUrl = trim((string)(getenv('IPROMO_API_URL') ?: ''));
    if ($url === '' && $envUrl !== '') {
        $url = $envUrl;
    }

    return [
        'url'       => $url !== '' ? rtrim($url, '/') : 'https://console.ipromo.lk/api/v3/sms/send',
        'username'  => trim($username),
        'api_key'   => $apiKey,
        'sender_id' => trim($sender),
        'enabled'   => $enabled,
    ];
}

/**
 * Is iPromo fully configured (all required credentials are present)?
 */
function ipromo_configured(?PDO $pdo = null): bool
{
    $cfg = ipromo_config($pdo);
    return $cfg['username'] !== '' && $cfg['api_key'] !== '' && $cfg['sender_id'] !== '';
}

/**
 * Normalize a phone number to iPromo's required format (11-digit, no + prefix):
 *   94771234567
 *
 * Returns '' on invalid number.
 */
function ipromo_normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';

    // 0XXXXXXXXX (10 digits, local Sri Lankan)
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        $digits = '94' . substr($digits, 1);
    }
    // 7XXXXXXXX (9 digits, missing leading 0 and country code)
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
 * Send one SMS via the iPromo API directly.
 *
 * Sets sms_send_last_error() / sms_send_last_id() for backward compatibility
 * with existing callers that inspect those functions after a send.
 */
function ipromo_send(PDO $pdo, string $phone, string $text): bool
{
    sms_send_last_error('');
    sms_send_last_id('');

    if (!ipromo_enabled($pdo)) {
        sms_send_last_error('iPromo SMS gateway is disabled.');
        sms_log_write($pdo, $phone, $text, 'ipromo', 'skipped', '', 'Gateway disabled.', null, null);
        return false;
    }

    $cfg  = ipromo_config($pdo);
    $to   = ipromo_normalize_phone($phone);
    $text = trim($text);

    if ($cfg['username'] === '' || $cfg['api_key'] === '' || $cfg['sender_id'] === '') {
        $err = 'iPromo credentials are not configured (username, API key, or Sender ID is missing).';
        sms_send_last_error($err);
        sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', $err, null, null);
        return false;
    }
    if ($to === '') {
        $err = 'Invalid phone number for iPromo.';
        sms_send_last_error($err);
        sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', $err, null, null);
        return false;
    }
    if ($text === '') {
        $err = 'Message body is empty.';
        sms_send_last_error($err);
        sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', $err, null, null);
        return false;
    }

    // Build request per iPromo API documentation.
    // Endpoint: GET/POST with query string parameters.
    $params = [
        'username' => $cfg['username'],
        'apikey'   => $cfg['api_key'],
        'sender'   => $cfg['sender_id'],
        'to'       => $to,
        'message'  => $text,
    ];
    $url = $cfg['url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init();
    if ($ch === false) {
        sms_send_last_error('Could not start HTTPS request to iPromo.');
        sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', 'curl_init failed.', null, null);
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: EdexcelCollege/1.0'],
    ]);

    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $cerr  = curl_error($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $logTo = sms_mask_phone($to);

    if ($errno !== 0) {
        $err = 'Could not reach iPromo gateway (error ' . $errno . ').';
        error_log('iPromo SMS cURL error ' . $errno . ' ' . $cerr . ' to=' . $logTo);
        sms_send_last_error($err);
        sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', $err, null, null);
        return false;
    }

    if ($http < 200 || $http >= 300) {
        $err = 'iPromo API returned HTTP ' . $http . '.';
        error_log('iPromo SMS HTTP=' . $http . ' to=' . $logTo . ' body=' . substr((string)$body, 0, 200));
        sms_send_last_error($err);
        sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', $err, null, null);
        return false;
    }

    $decoded   = is_string($body) ? json_decode($body, true) : null;
    $apiCode   = is_array($decoded) ? (int)($decoded['code']    ?? 0)  : 0;
    $apiMsg    = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
    $messageId = is_array($decoded) ? (string)($decoded['msgid'] ?? $decoded['message_id'] ?? $decoded['id'] ?? '') : '';

    error_log('iPromo SMS to=' . $logTo . ' code=' . $apiCode . ' msg=' . $apiMsg . ' id=' . $messageId);

    // iPromo success: code=200 and message field contains count of recipients
    if ($apiCode === 200 || $apiCode === 201 || ($apiMsg !== '' && is_numeric($apiMsg))) {
        if ($messageId !== '') {
            sms_send_last_id($messageId);
        }
        sms_log_write($pdo, $phone, $text, 'ipromo', 'sent', $messageId, null, null, null);
        return true;
    }

    // Map known iPromo API error codes
    $errText = match ($apiCode) {
        401     => 'iPromo authentication failed. Check API credentials in settings.',
        402     => 'iPromo account has insufficient credit.',
        403     => 'iPromo API access forbidden.',
        404     => 'iPromo API endpoint not found. Check the API URL in settings.',
        429     => 'iPromo rate limit exceeded.',
        default => 'iPromo rejected the message (code ' . $apiCode . ').',
    };
    // Use API message if it doesn't leak credentials
    if ($apiMsg !== '' && !preg_match('/key|token|password|secret/i', $apiMsg)) {
        $errText = substr(trim($apiMsg), 0, 180);
    }

    sms_send_last_error($errText);
    sms_log_write($pdo, $phone, $text, 'ipromo', 'failed', '', $errText, null, null);
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Central SMS dispatcher — use this for all new SMS sending calls
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Route an SMS through the currently configured provider.
 *
 * Provider selection:
 *   sms_provider = 'ipromo'   → iPromo Marketing API  (when ipromo_enabled = '1')
 *   sms_provider = 'sms-gate' → SMS-Gate Android app  (existing behaviour)
 *
 * If the selected provider is disabled or not configured, the send is
 * logged as 'skipped' and false is returned — it is NOT treated as a
 * provider failure.
 *
 * Existing code that calls sms_send() directly continues to work and
 * bypasses the provider switch (it always uses SMS-Gate). Gradually
 * migrate those call sites to sms_dispatch() as appropriate.
 *
 * @param PDO|null $pdo
 * @param string   $phone   Recipient number (any Sri Lankan format).
 * @param string   $text    Message body (plain text).
 * @param string   $context Optional log context tag (e.g. 'payment_sms', 'otp').
 * @param int|null $sentBy  users.id of the actor, null for system sends.
 * @return bool
 */
function sms_dispatch(?PDO $pdo, string $phone, string $text, string $context = '', ?int $sentBy = null): bool
{
    sms_send_last_error('');
    sms_send_last_id('');

    $provider = sms_active_provider($pdo);

    if ($provider === 'ipromo') {
        if (!$pdo instanceof PDO) {
            sms_send_last_error('iPromo requires a database connection.');
            return false;
        }
        if (!ipromo_enabled($pdo)) {
            sms_send_last_error('iPromo SMS gateway is disabled.');
            sms_log_write($pdo, $phone, $text, 'ipromo', 'skipped', '', 'Gateway disabled.', $sentBy, $context ?: null);
            return false;
        }
        $ok    = ipromo_send($pdo, $phone, $text);
        $msgId = sms_send_last_id();
        $err   = sms_send_last_error();
        // Update context in the last-written log row (simpler than rewriting ipromo_send).
        if ($context !== '') {
            try {
                $pdo->prepare(
                    "UPDATE sms_logs SET context=?, sent_by=? WHERE id=(SELECT MAX(id) FROM sms_logs WHERE provider='ipromo')"
                )->execute([$context, $sentBy]);
            } catch (Throwable) {
                // Non-critical
            }
        }
        return $ok;
    }

    // SMS-Gate (existing, unchanged behaviour)
    $ok = sms_send($pdo, $phone, $text);
    if ($pdo instanceof PDO) {
        $msgId = sms_send_last_id();
        $err   = sms_send_last_error();
        sms_log_write(
            $pdo, $phone, $text, 'sms-gate',
            $ok ? 'sent' : 'failed',
            $msgId,
            $ok ? null : ($err !== '' ? $err : null),
            $sentBy,
            $context !== '' ? $context : null
        );
    }
    return $ok;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Centralized SMS log
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Ensure the sms_logs table exists (idempotent, called lazily).
 */
function ensure_sms_log_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $done = true;
        return;
    }

    if ($driver === 'sqlite') {
        $sql = "CREATE TABLE IF NOT EXISTS sms_logs (
                    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                    recipient           VARCHAR(20)  NOT NULL DEFAULT '',
                    message             TEXT         NOT NULL DEFAULT '',
                    provider            VARCHAR(40)  NOT NULL DEFAULT '',
                    status              VARCHAR(20)  NOT NULL DEFAULT 'pending',
                    provider_message_id VARCHAR(120) NULL,
                    error_message       VARCHAR(500) NULL,
                    sent_by             INTEGER      NULL,
                    context             VARCHAR(80)  NULL,
                    created_at          TEXT         NOT NULL DEFAULT CURRENT_TIMESTAMP
                )";
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS sms_logs (
                    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recipient           VARCHAR(20)     NOT NULL DEFAULT '',
                    message             TEXT            NOT NULL,
                    provider            VARCHAR(40)     NOT NULL DEFAULT '',
                    status              VARCHAR(20)     NOT NULL DEFAULT 'pending',
                    provider_message_id VARCHAR(120)    NULL,
                    error_message       VARCHAR(500)    NULL,
                    sent_by             INT             NULL,
                    context             VARCHAR(80)     NULL,
                    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_sms_logs_recipient (recipient, created_at),
                    KEY idx_sms_logs_status    (status,    created_at),
                    KEY idx_sms_logs_provider  (provider,  created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
    try {
        $pdo->exec($sql);
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_sms_log_schema: ' . $e->getMessage());
        $done = true;
    }
}

/**
 * Write one row to sms_logs. Never stores API credentials.
 */
function sms_log_write(
    PDO $pdo,
    string $phone,
    string $message,
    string $provider,
    string $status,
    string $msgId = '',
    ?string $error = null,
    ?int $sentBy = null,
    ?string $context = null
): void {
    try {
        ensure_sms_log_schema($pdo);
        $clip = static fn (string $s, int $n): string =>
            function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
        $pdo->prepare(
            'INSERT INTO sms_logs
                (recipient, message, provider, status, provider_message_id, error_message, sent_by, context)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $clip($phone,    20),
            $clip($message, 1000),
            $clip($provider, 40),
            $clip($status,   20),
            $msgId  !== '' ? $clip($msgId,  120) : null,
            $error !== null && $error !== '' ? $clip($error, 500) : null,
            $sentBy > 0 ? $sentBy : null,
            $context !== null && $context !== '' ? $clip($context, 80) : null,
        ]);
    } catch (Throwable $e) {
        error_log('sms_log_write: ' . $e->getMessage());
    }
}

/**
 * Mask a phone number for display in admin views.
 * Example: 0771234567 → 077****567
 */
function sms_mask_phone(string $phone): string
{
    $len = strlen($phone);
    if ($len <= 6) {
        return str_repeat('*', $len);
    }
    return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
}
