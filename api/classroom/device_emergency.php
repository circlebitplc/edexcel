<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../student/device_helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\EmergencyDeviceAccessService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sign in required.']);
    exit;
}

$actor = emergency_device_actor($pdo);
$service = new EmergencyDeviceAccessService($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isset($_SESSION['emergency_rl']) || !is_array($_SESSION['emergency_rl'])) {
        $_SESSION['emergency_rl'] = [];
    }
    $now = time();
    $_SESSION['emergency_rl'] = array_values(array_filter(
        $_SESSION['emergency_rl'],
        static fn ($t) => is_int($t) && ($now - $t) < 60
    ));
    if (count($_SESSION['emergency_rl']) >= 20) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Please wait a moment and try again.']);
        exit;
    }
    $_SESSION['emergency_rl'][] = $now;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    if (!verify_csrf_token((string)($payload['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
        exit;
    }
    $action = (string)($payload['action'] ?? '');
    if ($action !== 'decide' || ($actor['role'] !== 'admin' && $actor['role'] !== 'teacher')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'You cannot decide this request.']);
        exit;
    }
    $decision = (string)($payload['decision'] ?? '');
    $result = $service->decide((int)($payload['request_id'] ?? 0), $decision, $actor);
    if (!empty($result['ok']) && function_exists('log_audit')) {
        log_audit(
            $pdo,
            'emergency_device_' . $result['status'],
            'emergency_device_requests',
            (int)($payload['request_id'] ?? 0),
            null,
            ['decision' => $result['status']]
        );
    }
    if (empty($result['ok'])) {
        http_response_code(409);
    }
    echo json_encode([
        'ok' => !empty($result['ok']),
        'error' => (string)($result['error'] ?? ''),
        'status' => (string)($result['status'] ?? ''),
    ]);
    exit;
}

if ($actor['role'] === 'student') {
    $lessonId = (int)($_GET['lesson'] ?? 0);
    $row = $lessonId > 0 ? $service->latestForCurrentDevice($actor['user_id'], $lessonId) : null;
    echo json_encode([
        'ok' => true,
        'request' => $row ? $service->publicRequest($row) : null,
    ]);
    exit;
}

if ($actor['role'] !== 'admin' && $actor['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You cannot view device requests.']);
    exit;
}

$rows = [];
foreach ($service->pendingForActor($actor) as $row) {
    $rows[] = $service->publicRequest($row);
}
echo json_encode(['ok' => true, 'requests' => $rows]);
