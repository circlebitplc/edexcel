<?php
declare(strict_types=1);

/**
 * AJAX endpoint: Save iPromo settings and optionally send a test SMS.
 *
 * POST parameters:
 *   action        : 'save_ipromo' | 'test_ipromo'
 *   csrf_token    : required
 *   ipromo_enabled: '1' or '0'
 *   ipromo_api_url, ipromo_username, ipromo_api_key, ipromo_sender_id
 *   sms_provider  : 'ipromo' | 'sms-gate'
 *   test_phone    : mobile number (for test_ipromo action only)
 *   test_message  : message body (for test_ipromo action only)
 *
 * Security:
 *   - Requires admin session
 *   - CSRF protected (POST only)
 *   - Rate-limited (test SMS: max 5 per 5 minutes per admin)
 *   - API key never returned in response
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/sms_gateway.php';
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$fail = static function (string $error, int $http = 400): never {
    http_response_code($http);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $fail('POST required.', 405);
}

// Admin check first (before CSRF so session is confirmed)
require_admin();

if (!verify_csrf_token((string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    $fail('Invalid security token. Refresh the page and try again.', 403);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
if (!in_array($action, ['save_ipromo', 'test_ipromo'], true)) {
    $fail('Unknown action.');
}

// ─── Rate limit for test SMS ──────────────────────────────────────────────────
if ($action === 'test_ipromo') {
    if (!isset($_SESSION['ipromo_test_rl']) || !is_array($_SESSION['ipromo_test_rl'])) {
        $_SESSION['ipromo_test_rl'] = [];
    }
    $now    = time();
    $window = 300; // 5 minutes
    $max    = 5;
    $_SESSION['ipromo_test_rl'] = array_values(
        array_filter($_SESSION['ipromo_test_rl'], static fn ($t) => is_int($t) && ($now - $t) < $window)
    );
    if (count($_SESSION['ipromo_test_rl']) >= $max) {
        $fail('Rate limit reached. You can send at most 5 test SMS messages per 5 minutes.', 429);
    }
    $_SESSION['ipromo_test_rl'][] = $now;
}

// ─── Save settings ────────────────────────────────────────────────────────────
$saved = sms_gateway_config($pdo);    // existing SMS-Gate config (preserved)
$ipromoCfg = ipromo_config($pdo);    // existing iPromo config

$enabled   = isset($_POST['ipromo_enabled']) && (string)$_POST['ipromo_enabled'] === '1';
$apiUrl    = trim((string)($_POST['ipromo_api_url']    ?? ''));
$username  = trim((string)($_POST['ipromo_username']   ?? ''));
$apiKey    = (string)($_POST['ipromo_api_key']         ?? '');  // preserve exact case
$senderId  = trim((string)($_POST['ipromo_sender_id']  ?? ''));
$provider  = strtolower(trim((string)($_POST['sms_provider'] ?? 'sms-gate')));

if (!in_array($provider, ['sms-gate', 'ipromo'], true)) {
    $provider = 'sms-gate';
}

// Keep existing value if new value is blank (same pattern as existing SMS-Gate settings)
if ($apiUrl   === '') { $apiUrl   = $ipromoCfg['url']; }
if ($username === '') { $username = $ipromoCfg['username']; }
if ($apiKey   === '') { $apiKey   = $ipromoCfg['api_key']; }
if ($senderId === '') { $senderId = $ipromoCfg['sender_id']; }

if ($apiUrl === '') {
    $apiUrl = \Edexcel\Services\IPromoSmsProvider::DEFAULT_API_URL;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?'
    );
    foreach ([
        ['ipromo_enabled',   $enabled ? '1' : '0'],
        ['ipromo_api_url',   $apiUrl],
        ['ipromo_username',  $username],
        ['ipromo_api_key',   $apiKey],
        ['ipromo_sender_id', $senderId],
        ['sms_provider',     $provider],
    ] as [$key, $val]) {
        $stmt->execute([$key, $val, $val]);
    }

    // Audit log (never includes the raw API key)
    if (function_exists('log_audit')) {
        log_audit($pdo, 'update_settings', 'settings', null, null, [
            'ipromo_enabled'   => $enabled ? '1' : '0',
            'ipromo_api_url'   => $apiUrl,
            'ipromo_username'  => $username,
            'ipromo_api_key'   => '(saved)',
            'ipromo_sender_id' => $senderId,
            'sms_provider'     => $provider,
        ]);
    }
} catch (Throwable $e) {
    error_log('ipromo save settings: ' . $e->getMessage());
    $fail('Could not save settings: ' . $e->getMessage());
}

if ($action === 'save_ipromo') {
    echo json_encode([
        'ok'      => true,
        'message' => 'iPromo settings saved successfully.',
        'enabled' => $enabled,
    ]);
    exit;
}

// ─── Test SMS ─────────────────────────────────────────────────────────────────
$testPhone = trim((string)($_POST['test_phone']   ?? ''));
$testMsg   = trim((string)($_POST['test_message'] ?? 'Edexcel College iPromo SMS test. Gateway OK.'));

if ($testPhone === '') {
    $fail('Please enter a test mobile number.');
}

$normalizedPhone = ipromo_normalize_phone($testPhone);
if ($normalizedPhone === '') {
    $fail('Invalid phone number. Use a Sri Lankan mobile number such as 077XXXXXXX.');
}

if (!$enabled) {
    $fail('iPromo SMS is disabled. Enable it first to send a test.');
}

if (!ipromo_configured($pdo)) {
    $fail('iPromo credentials are incomplete. Save username, API key, and Sender ID first.');
}

$actorId = (int)($_SESSION['user_id'] ?? 0);

// Reload config after save to use fresh values
$ok = ipromo_send($pdo, $normalizedPhone, $testMsg);

// Update log with context
try {
    $pdo->prepare(
        "UPDATE sms_logs SET context='admin_test', sent_by=? WHERE id=(SELECT id FROM (SELECT MAX(id) AS id FROM sms_logs WHERE provider='ipromo') sub)"
    )->execute([$actorId]);
} catch (Throwable) {}

if ($ok) {
    $msgId = sms_send_last_id();
    echo json_encode([
        'ok'         => true,
        'message'    => 'Test SMS sent successfully via iPromo' . ($msgId !== '' ? ' (ID: ' . htmlspecialchars($msgId, ENT_QUOTES) . ')' : '') . '.',
        'message_id' => $msgId,
    ]);
} else {
    $err = sms_send_last_error();
    echo json_encode([
        'ok'    => false,
        'error' => $err !== '' ? $err : 'iPromo did not accept the test SMS. Check credentials and API URL.',
    ]);
}
