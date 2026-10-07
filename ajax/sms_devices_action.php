<?php
declare(strict_types=1);

/**
 * AJAX endpoint for Admin SMS-Gate Android Device Management
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Services/IncomingSmsService.php';

use Edexcel\Services\IncomingSmsService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$fail = static function (string $error, int $http = 400): never {
    http_response_code($http);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_SLASHES);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $fail('POST request required.', 405);
}

// Require admin authentication
require_admin();

// Verify CSRF token
$csrfToken = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrfToken)) {
    $fail('Invalid security token. Please refresh the page and try again.', 403);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));

IncomingSmsService::ensureSchema($pdo);

switch ($action) {
    case 'list_devices':
        $devices = IncomingSmsService::getDevices($pdo);
        echo json_encode(['ok' => true, 'devices' => $devices], JSON_UNESCAPED_SLASHES);
        break;

    case 'add_device':
        $name     = trim((string)($_POST['device_name'] ?? ''));
        $deviceId = trim((string)($_POST['device_id'] ?? ''));
        $phone    = trim((string)($_POST['phone_number'] ?? ''));

        if ($name === '') {
            $fail('Device name is required.');
        }
        if ($deviceId === '') {
            $fail('Device ID is required.');
        }

        // Check if device_id already exists
        $existing = IncomingSmsService::getDeviceByDeviceId($pdo, $deviceId);
        if ($existing) {
            $fail('A device with this Device ID is already registered.');
        }

        try {
            $created = IncomingSmsService::createDevice($pdo, $name, $deviceId, $phone !== '' ? $phone : null);
            echo json_encode([
                'ok'      => true,
                'message' => 'SMS Gateway device registered successfully.',
                'device'  => $created,
                'token'   => $created['token'], // Returned once for admin to copy
            ], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            $fail('Failed to register device: ' . $e->getMessage());
        }
        break;

    case 'update_device':
    case 'rename_device':
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim((string)($_POST['device_name'] ?? ''));
        $phone    = trim((string)($_POST['phone_number'] ?? ''));

        if ($id <= 0) {
            $fail('Invalid device identifier.');
        }
        if ($name === '') {
            $fail('Device name cannot be empty.');
        }

        try {
            $ok = IncomingSmsService::updateDevice($pdo, $id, [
                'device_name'  => $name,
                'phone_number' => $phone !== '' ? $phone : null,
            ]);
            echo json_encode(['ok' => $ok, 'message' => 'Device updated successfully.'], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            $fail('Failed to update device: ' . $e->getMessage());
        }
        break;

    case 'toggle_device':
        $id     = (int)($_POST['id'] ?? 0);
        $enable = (int)($_POST['is_enabled'] ?? 0) === 1;

        if ($id <= 0) {
            $fail('Invalid device identifier.');
        }

        try {
            $ok = IncomingSmsService::toggleDevice($pdo, $id, $enable);
            echo json_encode([
                'ok'      => $ok,
                'message' => $enable ? 'Device enabled.' : 'Device disabled.',
            ], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            $fail('Failed to update device status: ' . $e->getMessage());
        }
        break;

    case 'regenerate_token':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fail('Invalid device identifier.');
        }

        try {
            $newToken = IncomingSmsService::regenerateToken($pdo, $id);
            echo json_encode([
                'ok'      => true,
                'message' => 'New API token generated successfully.',
                'token'   => $newToken, // Plaintext returned once for copy
            ], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            $fail('Failed to regenerate token: ' . $e->getMessage());
        }
        break;

    case 'delete_device':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fail('Invalid device identifier.');
        }

        try {
            $ok = IncomingSmsService::deleteDevice($pdo, $id);
            echo json_encode(['ok' => $ok, 'message' => 'Device deleted successfully.'], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            $fail('Failed to delete device: ' . $e->getMessage());
        }
        break;

    default:
        $fail('Unknown action: ' . htmlspecialchars($action));
}
