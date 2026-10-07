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
use Edexcel\Services\AdminTotpService;

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
                $identifier = $getParam('identifier');
                $targetUser = null;
                if ($identifier !== '') {
                    $targetUser = $bioService->findUserByIdentifier($identifier);
                } elseif (function_exists('is_logged_in') && is_logged_in() && !empty($_SESSION['user_id'])) {
                    $targetUser = $bioService->getUserAccount((int)$_SESSION['user_id']);
                }

                if ($targetUser) {
                    $targetId = (int)$targetUser['id'];
                    $faceEnrolled = $faceService->isEnrolled($targetId);
                    $throttle = $bioService->checkThrottling((string)$targetUser['username'], $targetId);

                    echo json_encode([
                        'ok' => true,
                        'user_found' => true,
                        'face_available' => $faceEnrolled,
                        'is_throttled' => $throttle['is_locked'],
                        'role' => (string)($targetUser['role'] ?? 'student'),
                        'display_name' => (string)($targetUser['teacher_name'] ?? $targetUser['username']),
                    ]);
                    exit;
                }

                // General status check
                $admin = null;
                try {
                    $admin = $bioService->getAdminAccount();
                } catch (Throwable) {}

                $adminId = $admin ? (int)$admin['id'] : 0;
                $passkeys = ($adminId > 0) ? $passkeyService->listPasskeys($adminId) : [];
                $faceEnrolled = ($adminId > 0) ? $faceService->isEnrolled($adminId) : false;
                $throttle = $admin ? $bioService->checkThrottling($admin['username'], $adminId) : ['is_locked' => false];

                $anyFaceEnrolled = false;
                try {
                    $stmtCount = $pdo->query("SELECT COUNT(*) FROM admin_face_credentials WHERE status = 'active'");
                    $anyFaceEnrolled = ((int)$stmtCount->fetchColumn()) > 0;
                } catch (Throwable) {}

                echo json_encode([
                    'ok' => true,
                    'passkey_available' => !empty($passkeys),
                    'passkey_count' => count($passkeys),
                    'face_available' => $faceEnrolled || $anyFaceEnrolled,
                    'is_throttled' => $throttle['is_locked'],
                    'admin_exists' => $admin !== null,
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
            $identifier = $getParam('identifier');
            $targetUser = null;

            if ($identifier !== '') {
                $targetUser = $bioService->findUserByIdentifier($identifier);
                if (!$targetUser) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'No account found matching "' . htmlspecialchars($identifier) . '".']);
                    exit;
                }
            } elseif (function_exists('is_logged_in') && is_logged_in() && !empty($_SESSION['user_id'])) {
                $targetUser = $bioService->getUserAccount((int)$_SESSION['user_id']);
            } else {
                // Check enrolled count: if exactly 1 user in the system is enrolled in Face ID, automatically use that user
                $stmtEnrolled = $pdo->query("SELECT user_id FROM admin_face_credentials WHERE status = 'active' LIMIT 2");
                $enrolledRows = $stmtEnrolled->fetchAll(PDO::FETCH_COLUMN);
                if (count($enrolledRows) === 1) {
                    $targetUser = $bioService->getUserAccount((int)$enrolledRows[0]);
                } else {
                    // Multiple or 0 users enrolled: request identifier
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'require_identifier' => true,
                        'error' => 'Please enter your username, email, or mobile number to continue with Face ID.'
                    ]);
                    exit;
                }
            }

            $targetId = (int)$targetUser['id'];

            if (!$faceService->isEnrolled($targetId)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Face ID is not enrolled for this account. Please sign in and enroll in settings.']);
                exit;
            }

            $throttle = $bioService->checkThrottling((string)$targetUser['username'], $targetId);
            if ($throttle['is_locked']) {
                http_response_code(429);
                echo json_encode(['ok' => false, 'error' => 'Too many failed sign-in attempts. Please wait 15 minutes and try again.']);
                exit;
            }
            if ($throttle['delay_seconds'] > 0) {
                sleep($throttle['delay_seconds']);
            }

            $escalate = !empty($_GET['escalate']) || !empty($_POST['escalate']) || !empty($input['escalate']) || ($throttle['attempts_recent'] > 0);
            $challengeData = $faceService->createAuthLivenessChallenge($targetId, (bool)$escalate);

            $displayName = !empty($targetUser['teacher_name'])
                ? (string)$targetUser['teacher_name']
                : (string)$targetUser['username'];

            echo json_encode([
                'ok' => true,
                'challenge_token' => $challengeData['challenge_token'],
                'sequence' => $challengeData['sequence'],
                'mode' => $challengeData['mode'] ?? ($escalate ? 'escalated' : 'fast_3step'),
                'timeout_seconds' => $challengeData['timeout_seconds'],
                'user_name' => $displayName,
                'role' => (string)($targetUser['role'] ?? 'student'),
            ]);
            exit;

        case 'face_auth_verify':
            $challengeToken = $getParam('challenge_token');
            $descriptor = $_POST['descriptor'] ?? $input['descriptor'] ?? [];
            $telemetry = $_POST['telemetry'] ?? $input['telemetry'] ?? [];

            if ($challengeToken === '' || !is_array($descriptor) || !is_array($telemetry)) {
                throw new RuntimeException('Missing facial verification response parameters.');
            }

            // Look up challenge to resolve which user ID was assigned to this token
            $chStmt = $pdo->prepare("
                SELECT user_id FROM admin_biometric_challenges
                WHERE challenge_token = ? AND challenge_type = 'face_auth'
                LIMIT 1
            ");
            $chStmt->execute([$challengeToken]);
            $targetUserId = (int)$chStmt->fetchColumn();

            if ($targetUserId <= 0) {
                throw new RuntimeException('Invalid or expired facial verification challenge.');
            }

            $targetUser = $bioService->getUserAccount($targetUserId);

            $verifyResult = $faceService->processVerification(
                $targetUserId,
                $challengeToken,
                $descriptor,
                $telemetry
            );

            if (empty($verifyResult['matched'])) {
                throw new RuntimeException('We could not verify your face. Please try again.');
            }

            // Establish role-appropriate session (admin, teacher, student)
            $redirectUrl = $bioService->establishSession($targetUser, AdminBiometricService::METHOD_FACE);

            echo json_encode([
                'ok' => true,
                'message' => 'Face verified successfully.',
                'redirect' => $redirectUrl,
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
        // 5. FACE ENROLLMENT & MANAGEMENT (ALL AUTHENTICATED USERS)
        // ==============================================================
        case 'face_enrol_challenge':
            require_login();
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
            require_login();
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
            require_login();
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
            require_login();
            $csrf = $getParam('csrf_token', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!verify_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Security token expired.']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $role = current_role();
            if ($role === 'admin') {
                $totp = new AdminTotpService($pdo);
                $recentReauth = $totp->hasValidReauth($userId, 'sensitive') || $totp->hasValidReauth($userId, 'biometrics');
                if (!$recentReauth) {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Clearing biometric face data requires recent password re-authentication. Please re-authenticate on the security page.']);
                    exit;
                }
            }

            $faceService->clearFaceData($userId);
            echo json_encode(['ok' => true, 'message' => 'Biometric face data permanently cleared.']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown biometric action requested.']);
            exit;
    }
} catch (Throwable $e) {
    error_log('Biometrics AJAX error: ' . $e->getMessage());
    http_response_code(400);

    $msg = $e->getMessage();
    // Prevent disclosure of internal SQLSTATE, queries, or filesystem paths
    if (
        $e instanceof PDOException
        || stripos($msg, 'SQLSTATE') !== false
        || stripos($msg, 'SELECT') !== false
        || stripos($msg, 'INSERT') !== false
        || stripos($msg, 'UPDATE') !== false
        || stripos($msg, 'DELETE') !== false
        || stripos($msg, 'public_html') !== false
        || stripos($msg, 'SQL syntax') !== false
    ) {
        $msg = 'A database or system error occurred while processing biometric request.';
    }

    echo json_encode([
        'ok' => false,
        'error' => $msg,
    ]);
    exit;
}
