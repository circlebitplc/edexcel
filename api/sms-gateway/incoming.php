<?php
declare(strict_types=1);

/**
 * SMS-Gate (Android) Incoming SMS Webhook / API Endpoint
 *
 * Secure HTTPS endpoint for synchronizing incoming SMS messages from the
 * SMS-Gate Android application to Edexcel College.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Require HTTPS in production (allow HTTP only for loopback / local dev)
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

$remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true)
    || str_starts_with($remoteAddr, '192.168.')
    || str_starts_with($remoteAddr, '10.');

if (!$isHttps && !$isLocal) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => 'HTTPS is required for incoming SMS synchronization.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/IncomingSmsService.php';

use Edexcel\Services\IncomingSmsService;

// Ensure database schema exists
IncomingSmsService::ensureSchema($pdo);

// Health check / GET ping
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'ok'      => true,
        'service' => 'edexcel-incoming-sms',
        'time'    => date('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

// Read raw body
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    // Fallback to $_POST form-data
    $payload = $_POST;
}

if (!is_array($payload) || $payload === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid or empty request payload.'], JSON_UNESCAPED_SLASHES);
    exit;
}

// Collect request headers
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
        $headers[$headerName] = $value;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}
if (isset($_SERVER['PHP_AUTH_USER'])) {
    $headers['authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
}

// Authenticate device
$device = IncomingSmsService::authenticateRequest($pdo, $headers, $payload, $_GET);

if ($device === null) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Unauthorized: Invalid device identity or API token.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($device['is_enabled']) && (int)$device['is_enabled'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => 'Forbidden: This SMS Gateway device has been disabled by an administrator.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Process incoming SMS payload
$result = IncomingSmsService::processIncomingPayload($pdo, $payload, $device);

if (empty($result['ok'])) {
    $code = (int)($result['code'] ?? 400);
    http_response_code($code);
    echo json_encode([
        'ok'    => false,
        'error' => $result['error'] ?? 'Failed to process incoming SMS.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Successful ingestion or duplicate acknowledgment
echo json_encode([
    'ok'        => true,
    'status'    => $result['status'],
    'message'   => $result['message'],
    'id'        => $result['id'] ?? null,
    'duplicate' => $result['duplicate'] ?? false,
    'device_id' => $device['device_id'],
], JSON_UNESCAPED_SLASHES);
