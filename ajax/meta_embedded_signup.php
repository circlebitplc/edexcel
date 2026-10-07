<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/evolution.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\MetaEmbeddedSignupService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrf = (string)($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Refresh the page and try again.']);
    exit;
}

$action = strtolower(trim((string)($input['action'] ?? 'complete')));

try {
    $service = new MetaEmbeddedSignupService($pdo);
    if ($action === 'disconnect') {
        $service->disconnect();
        log_audit($pdo, 'whatsapp_disconnect', 'settings', null, null, ['provider' => 'evolution']);
        echo json_encode(['ok' => true, 'disconnected' => true]);
        exit;
    }

    if ($action === 'register') {
        $result = $service->registerSavedNumber((string)($input['pin'] ?? ''));
        log_audit($pdo, 'whatsapp_register', 'settings', null, null, [
            'phone_status' => $result['phone_status'] ?? '',
            'platform_type' => $result['platform_type'] ?? '',
        ]);
        echo json_encode($result);
        exit;
    }

    if ($action === 'save_token') {
        $result = $service->applyManualToken(
            (string)($input['access_token'] ?? ''),
            (string)($input['waba_id'] ?? ''),
            (string)($input['phone_number_id'] ?? '')
        );
        log_audit($pdo, 'whatsapp_save_token', 'settings', null, null, [
            'waba_id' => (string)($input['waba_id'] ?? ''),
            'phone_number_id' => (string)($input['phone_number_id'] ?? ''),
            'registered' => !empty($result['registered']),
        ]);
        echo json_encode($result);
        exit;
    }

    if ($action === 'use_phone') {
        $result = $service->usePhone(
            (string)($input['waba_id'] ?? meta_default_waba_id()),
            (string)($input['phone_number_id'] ?? meta_default_phone_number_id())
        );
        log_audit($pdo, 'whatsapp_use_phone', 'settings', null, null, [
            'waba_id' => $result['waba_id'] ?? '',
            'phone_number_id' => (string)($input['phone_number_id'] ?? ''),
            'registered' => !empty($result['registered']),
        ]);
        echo json_encode($result);
        exit;
    }

    $result = $service->complete(
        (string)($input['code'] ?? ''),
        (string)($input['waba_id'] ?? ''),
        (string)($input['phone_number_id'] ?? ''),
        (string)($input['redirect_uri'] ?? ''),
        (string)($input['business_id'] ?? ''),
        (string)($input['pin'] ?? '')
    );
    log_audit($pdo, 'whatsapp_connect', 'settings', null, null, [
        'waba_id' => $result['waba_id'] ?? '',
        'phone_number_id' => $result['phone_number_id'] ?? '',
        'onboarding' => 'coexistence',
    ]);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
