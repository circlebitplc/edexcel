<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\LiveKitWebhookHandler;

date_default_timezone_set('Asia/Colombo');

$raw = file_get_contents('php://input') ?: '';
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

header('Content-Type: application/json; charset=utf-8');

try {
    if (!($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
        exit;
    }
    if (!function_exists('livekit_ready') || !livekit_ready($pdo)) {
        http_response_code(503);
        echo json_encode(['ok' => false]);
        exit;
    }
    $handler = new LiveKitWebhookHandler($pdo);
    $result = $handler->handle($raw, $auth);
    if (!$result['ok']) {
        http_response_code(401);
    }
    echo json_encode(['ok' => $result['ok']]);
} catch (Throwable $e) {
    error_log('LiveKit webhook error');
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
