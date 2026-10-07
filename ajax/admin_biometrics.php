<?php
declare(strict_types=1);

/**
 * ajax/admin_biometrics.php
 *
 * Secure AJAX API endpoint for the Admin-Only Biometric Authentication System.
 * Handles:
 * - Public WebAuthn Passkey assertion generation and verification for the admin
 * - Public Webcam Face liveness challenge generation and 1:1 mathematical verification
 * - Authenticated Passkey registration and revocation (Admin only)
 * - Authenticated Face enrollment, disabling, and template purging (Admin only)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AdminBiometricService;
use Edexcel\Services\AdminPasskeyService;
use Edexcel\Services\AdminFaceService;

// Ensure database is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

// Request size limit (protect against oversized payloads)
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 2097152) { // 2MB
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Payload too large.']);
    exit;
}

$bioService = new AdminBiometricService($pdo);
$passkeyService = new AdminPasskeyService($pdo, $bioService);
$faceService = new AdminFaceService($pdo, $bioService);

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));

// Parse JSON body if present
$input = [];
$rawBody = file_get_contents('php://input');
if ($rawBody !== false && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($action) && isset($input['action'])) {
    $action = strtolower(trim((string)$input['action']));
}

$getParam = static fn(string $k, string $default = ''): string => trim((string)($_POST[$k] ?? $input[$k] ?? $default));

try {
    switch ($action) {
        // ==============================================================
        // 1. PUBLIC ADMIN BIOMETRIC STATUS (For Login UI rendering)
        // ==============================================================
        case 'status':
            try {
                $admin = $bioService->getAdminAccount();
                $adminId = (int)$admin['id'];
                $passkeys = $passkeyService->listPasskeys($adminId);
                $faceEnrolled = $faceService->isEnrolled($adminId);
                $throttle = $bioService->checkThrottling($admin['username']);

                echo json_encode([
                    'ok' => true,
                    'passkey_available' => !empty($passkeys),
                    'passkey_count' => count($passkeys),
                    'face_available' => $faceEnrolled,
                    'is_throttled' => $throttle['is_locked'],
                    'admin_exists' => true,
                ]);
            } catch (Throwable) {
                echo json_encode([
                    'ok' => true,
                    'passkey_available' => false,
                    'face_available' => false,
                    'admin_exists' => false,
                ]);
            }
            exit;

        // ==============================================================
        // 2. PASSKEY LOGIN (AUTHENTICATION)
        // ==============================================================
        case 'passkey_auth_options':
            $admin = $bioService->getAdminAccount();
            $throttle = $bioService->checkThrottling($admin['username']);
            if ($throttle['is_locked']) {
                http_response_code(429);
                echo json_encode(['ok' => false, 'error' => 'Too many failed sign-in attempts. Please wait 15 minutes and try again.']);
                exit;
            }
            if ($throttle['delay_seconds'] > 0) {
                sleep($throttle['delay_seconds']);
            }

            $res = $passkeyService->getAuthenticationOptions((int)$admin['id']);
            echo json_encode([
                'ok' => true,
                'options' => $res['options'],
                'challenge_token' => $res['challenge_token'],
            ]);
            exit;

        case 'passkey_auth_verify':
            $admin = $bioService->getAdminAccount();
            $adminId = (int)$admin['id'];

            $challengeToken = $getParam('challenge_token');
            $credentialId = $getParam('credential_id');
            $clientDataJSON = $getParam('client_data_json');
            $authenticatorData = $getParam('authenticator_data');
            $signature = $getParam('signature');
            $userHandle = $getParam('user_handle');

            if ($challengeToken === '' || $credentialId === '' || $clientDataJSON === '' || $authenticatorData === '' || $signature === '') {
                throw new RuntimeException('Missing required Passkey authentication response data.');
            }

            $passkey = $passkeyService->processAuthentication(
                $adminId,
                $challengeToken,
                $credentialId,
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $userHandle !== '' ? $userHandle : null
            );

            // Establish full admin + teacher session
            $bioService->establishAdminSession($admin, AdminBiometricService::METHOD_PASSKEY);

            echo json_encode([
                'ok' => true,
                'message' => 'Passkey authenticated successfully.',
                'redirect' => (string)BASE_URL . 'dashboard.php',
            ]);
            exit;

        // ==============================================================
        // 3. WEBCAM FACE LOGIN (AUTHENTICATION)
        // ==============================================================
        case 'face_auth_challenge':
            $admin = $bioService->getAdminAccount();
            $throttle = $bioService->checkThrottling($admin['username']);
            if ($throttle['is_locked']) {
                http_response_code(429);
                echo json_encode(['ok' => false, 'error' => 'Too many failed sign-in attempts. Please wait 15 minutes and try again.']);
                exit;
            }
            if ($throttle['delay_seconds'] > 0) {
                sleep($throttle['delay_seconds']);
            }

            $challengeData = $faceService->createAuthLivenessChallenge((int)$admin['id']);
            echo json_encode([
                'ok' => true,
                'challenge_token' => $challengeData['challenge_token'],
                'sequence' => $challengeData['sequence'],
                'timeout_seconds' => $challengeData['timeout_seconds'],
            ]);
            exit;

        case 'face_auth_verify':
            $admin = $bioService->getAdminAccount();
            $adminId = (int)$admin['id'];

            $challengeToken = $getParam('challenge_token');
            $descriptor = $_POST['descriptor'] ?? $input['descriptor'] ?? [];
            $telemetry = $_POST['telemetry'] ?? $input['telemetry'] ?? [];

            if ($challengeToken === '' || !is_array($descriptor) || !is_array($telemetry)) {
                throw new RuntimeException('Missing facial verification response parameters.');
            }

            $verifyResult = $faceService->processVerification(
                $adminId,
                $challengeToken,
                $descriptor,
                $telemetry
            );

            if (empty($verifyResult['matched'])) {
                throw new RuntimeException('We could not verify your face. Please try again or use Passkey.');
            }

            // Establish full admin + teacher session
            $bioService->establishAdminSession($admin, AdminBiometricService::METHOD_FACE);

            echo json_encode([
                'ok' => true,
                'message' => 'Face verified successfully.',
                'redirect' => (string)BASE_URL . 'dashboard.php',
            ]);
            exit;

        // ==============================================================
        // 4. PASSKEY REGISTRATION & MANAGEMENT (ADMIN ONLY)
        // ==============================================================
        case 'passkey_reg_options':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired. Please refresh the page.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $username = (string)$_SESSION['username'];
            $res = $passkeyService->getRegistrationOptions($userId, $username);

            echo json_encode([
                'ok' => true,
                'options' => $res['options'],
                'challenge_token' => $res['challenge_token'],
            ]);
            exit;

        case 'passkey_reg_verify':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired. Please refresh the page.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $challengeToken = $getParam('challenge_token');
            $clientDataJSON = $getParam('client_data_json');
            $attestationObject = $getParam('attestation_object');
            $deviceName = $getParam('device_name', 'Passkey');
            $transports = $getParam('transports', 'internal');

            if ($challengeToken === '' || $clientDataJSON === '' || $attestationObject === '') {
                throw new RuntimeException('Missing required Passkey registration response parameters.');
            }

            $record = $passkeyService->processRegistration(
                $userId,
                $challengeToken,
                $clientDataJSON,
                $attestationObject,
                $deviceName,
                $transports
            );

            echo json_encode([
                'ok' => true,
                'message' => 'Passkey registered successfully.',
                'passkey' => $record,
            ]);
            exit;

        case 'passkey_revoke':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $passkeyId = (int)$getParam('passkey_id');
            if ($passkeyId < 1) {
                throw new RuntimeException('Invalid passkey ID.');
            }

            $passkeyService->revokePasskey($userId, $passkeyId);

            echo json_encode(['ok' => true, 'message' => 'Passkey revoked successfully.']);
            exit;

        // ==============================================================
        // 5. FACE ENROLLMENT & MANAGEMENT (ADMIN ONLY)
        // ==============================================================
        case 'face_enrol_challenge':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $consent = !empty($_POST['consent']) || !empty($input['consent']);
            if (!$consent) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Explicit biometric consent is required before face enrollment.']);
                exit;
            }

            $res = $faceService->createEnrollmentChallenge($userId, true);
            echo json_encode([
                'ok' => true,
                'challenge_token' => $res['challenge_token'],
                'required_poses' => $res['required_poses'],
            ]);
            exit;

        case 'face_enrol_submit':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $challengeToken = $getParam('challenge_token');
            $samples = $_POST['samples'] ?? $input['samples'] ?? [];

            if ($challengeToken === '' || !is_array($samples)) {
                throw new RuntimeException('Missing enrollment pose samples.');
            }

            $result = $faceService->processEnrollment($userId, $challengeToken, $samples);
            echo json_encode([
                'ok' => true,
                'message' => 'Face biometrics enrolled successfully.',
                'enrolled_at' => $result['enrolled_at'],
            ]);
            exit;

        case 'face_disable':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $faceService->disableFace($userId);
            echo json_encode(['ok' => true, 'message' => 'Face login disabled.']);
            exit;

        case 'face_clear':
            require_admin();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $faceService->clearFaceData($userId);
            echo json_encode(['ok' => true, 'message' => 'Biometric face data permanently cleared.']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown biometric action requested.']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
    exit;
}
