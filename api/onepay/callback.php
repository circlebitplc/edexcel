<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\OnePayCallbackHandler;
use Edexcel\Services\OnePayService;
use Edexcel\Services\PaymentTransactionService;
use Edexcel\Services\StudentLessonFeeService;

date_default_timezone_set('Asia/Colombo');

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if (!($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
        exit;
    }
    ensure_recordings_schema($pdo);
    $handler = new OnePayCallbackHandler(
        $pdo,
        new OnePayService($pdo),
        new PaymentTransactionService($pdo),
        new StudentLessonFeeService($pdo)
    );
    $result = $handler->handle(is_array($payload) ? $payload : []);
    echo json_encode(['ok' => $result['ok']]);
} catch (Throwable $e) {
    error_log('OnePay callback error');
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
