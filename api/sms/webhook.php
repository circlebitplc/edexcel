<?php
declare(strict_types=1);

/**
 * SMS-Gate delivery report webhook.
 * Preferred authentication is the X-SMS-Webhook-Secret header.
 * Authorization: Bearer is also accepted.
 * ?secret= remains only until the SMS gateway is reconfigured. Remove it after that.
 * Do not log the secret. A query value never overrides a header value.
 */
require_once __DIR__ . '/../../config/abuse.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/load_env.php';
require_once __DIR__ . '/../../config/sms_gateway.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\CommunicationHubService;

header('Content-Type: application/json; charset=utf-8');

$expected = trim((string)(sms_setting($pdo instanceof PDO ? $pdo : null, 'sms_webhook_secret', (string)(getenv('SMS_WEBHOOK_SECRET') ?: ''))));
$provided = sms_webhook_provided_secret();
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => true, 'service' => 'edexcel-sms-delivery']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    // Some gateways send form-encoded
    $data = $_POST;
}
if (!is_array($data) || $data === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$items = [];
if (isset($data['id']) || isset($data['messageId']) || isset($data['state']) || isset($data['status'])) {
    $items[] = $data;
} elseif (isset($data['messages']) && is_array($data['messages'])) {
    $items = $data['messages'];
} elseif (isset($data['events']) && is_array($data['events'])) {
    $items = $data['events'];
} else {
    $items = [$data];
}

$hub = new CommunicationHubService($pdo);
$matched = 0;
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $id = (string)($item['id'] ?? $item['messageId'] ?? $item['message_id'] ?? '');
    $status = (string)($item['state'] ?? $item['status'] ?? $item['deliveryStatus'] ?? '');
    if ($id === '' || $status === '') {
        continue;
    }
    $detail = trim((string)($item['reason'] ?? $item['error'] ?? 'SMS gateway '.$status));
    $result = $hub->recordProviderStatus($id, $status, 'sms', $detail);
    if (!empty($result['matched'])) {
        $matched++;
    }
}

echo json_encode(['ok' => true, 'matched' => $matched]);
