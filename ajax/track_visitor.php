<?php
declare(strict_types=1);

/**
 * Endpoint for async visitor tracking beacon / heartbeat.
 * Non-blocking, privacy-preserving, fast (< 5ms).
 * Incorporates authenticated registered user detection (admin, teacher, student, parent).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Accept GET for active_count & admin recent_sessions, POST for beacons
$isGet = $_SERVER['REQUEST_METHOD'] === 'GET';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isGet && !$isPost) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/abuse.php';

if (!defined('DB_ALLOW_FAILURE')) {
    define('DB_ALLOW_FAILURE', true);
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Services/SiteVisitorAnalyticsService.php';

use Edexcel\Services\SiteVisitorAnalyticsService;

if ($isGet) {
    $action = (string)($_GET['action'] ?? '');

    if ($action === 'active_count') {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            echo json_encode(['ok' => true, 'active' => 0]);
            exit;
        }
        $cnt = SiteVisitorAnalyticsService::getRealtimeActiveCount($pdo, 5);
        echo json_encode(['ok' => true, 'active' => $cnt]);
        exit;
    }

    if ($action === 'recent_sessions') {
        $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
            exit;
        }
        $limit = max(5, min(50, (int)($_GET['limit'] ?? 15)));
        $filter = [
            'q' => trim((string)($_GET['sq'] ?? '')),
            'device_type' => trim((string)($_GET['sdevice'] ?? '')),
            'traffic_source' => trim((string)($_GET['ssource'] ?? '')),
        ];
        $sessions = SiteVisitorAnalyticsService::getSessions($pdo, $filter, 1, $limit);
        echo json_encode(['ok' => true, 'sessions' => $sessions]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

// Read JSON input
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

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Return OK even if database is temporarily unavailable so telemetry never breaks client experience
    echo json_encode(['ok' => true, 'mock' => true]);
    exit;
}

// Authoritative user identification from server session
$userId = null;
$userType = null;
if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $userId = (int)$_SESSION['user_id'];
    $userType = !empty($_SESSION['role']) ? strtolower((string)$_SESSION['role']) : 'user';
} elseif (!empty($_SESSION['parent_id']) && (int)$_SESSION['parent_id'] > 0) {
    $userId = (int)$_SESSION['parent_id'];
    $userType = 'parent';
}

$payload['user_id'] = $userId;
$payload['user_type'] = $userType;

try {
    if ($action === 'heartbeat') {
        $sessionId = (string)($payload['session_id'] ?? '');
        $duration = (int)($payload['duration_seconds'] ?? 0);
        $exitPage = isset($payload['exit_page']) ? (string)$payload['exit_page'] : null;

        $ok = SiteVisitorAnalyticsService::updateHeartbeat($pdo, $sessionId, $duration, $exitPage, $userId, $userType);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // Default: pageview
    $result = SiteVisitorAnalyticsService::recordVisit($pdo, $payload);
    echo json_encode($result);
} catch (Throwable $e) {
    // Telemetry must never crash or output fatal errors
    error_log('Visitor tracking error: ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}
