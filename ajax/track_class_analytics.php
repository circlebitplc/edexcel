<?php
declare(strict_types=1);

/**
 * Endpoint for async /class visitor and conversion tracking.
 * Dedicated ONLY to /class.
 * Non-blocking, lightweight (< 4ms), privacy-preserving.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!defined('DB_ALLOW_FAILURE')) {
    define('DB_ALLOW_FAILURE', true);
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Services/ClassPageAnalyticsService.php';

use Edexcel\Services\ClassPageAnalyticsService;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Fail silently with ok so client page never experiences breakage
    echo json_encode(['ok' => true, 'mock' => true]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
if (empty($payload)) {
    $payload = $_POST;
}

$action = (string)($payload['action'] ?? 'pageview');
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

try {
    if ($action === 'heartbeat') {
        $sessionHash = (string)($payload['session_hash'] ?? ($_COOKIE['_cls_sid'] ?? ''));
        $duration = (int)($payload['duration_seconds'] ?? 0);
        $ok = ClassPageAnalyticsService::recordHeartbeat($pdo, $sessionHash, $duration);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($action === 'event') {
        $ok = ClassPageAnalyticsService::recordEvent($pdo, $payload, false, $ua);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // Default: pageview
    $result = ClassPageAnalyticsService::recordVisit($pdo, $payload, $ua);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('ClassPageAnalytics telemetry error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}

