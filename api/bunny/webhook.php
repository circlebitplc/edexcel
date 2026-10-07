<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\BunnyWebhookHandler;
use Edexcel\Services\RecordingService;

date_default_timezone_set('Asia/Colombo');

$raw = file_get_contents('php://input') ?: '';
$signature = (string)($_SERVER['HTTP_X_BUNNYSTREAM_SIGNATURE'] ?? '');

header('Content-Type: application/json; charset=utf-8');

try {
    if (!($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
        exit;
    }
    ensure_recordings_schema($pdo);
    $handler = new BunnyWebhookHandler($pdo, new RecordingService($pdo));
    $result = $handler->handle($raw, $signature);
    if (!$result['ok']) {
        http_response_code(401);
    }
    echo json_encode(['ok' => $result['ok']]);
} catch (Throwable $e) {
    error_log('Bunny webhook error');
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
