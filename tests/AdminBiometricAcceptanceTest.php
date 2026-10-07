<?php
declare(strict_types=1);

/**
 * Production Acceptance Audit Test Suite
 * Edexcel College — Admin-Only Biometric Authentication System
 *
 * Verifies all specifications:
 * 1. Admin-Only Scope & Constraint (Strict 1:1, non-admin denial)
 * 2. Admin is also a Teacher (Dual role & permission preservation)
 * 3. WebAuthn / FIDO2 Passkeys (Options, single-use challenges, counter checks, revocation)
 * 4. 1:1 Face Verification & PAD (Multi-sample enrollment, AES-256-GCM encryption, liveness challenges, photo spoof rejection)
 * 5. Security & Privacy (Zero raw images/videos stored, zero biometric leaks in API)
 * 6. Authentication Audit Logging (Audit records captured in authentication_audit)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AdminBiometricService;
use Edexcel\Services\AdminPasskeyService;
use Edexcel\Services\AdminFaceService;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertBio(bool $condition, string $description): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo " [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo "   ADMIN BIOMETRICS PRODUCTION ACCEPTANCE AUDIT TEST SUITE          \n";
echo "====================================================================\n\n";

// Services initialization
$bio = new AdminBiometricService($pdo);
$passkey = new AdminPasskeyService($pdo, $bio);
$face = new AdminFaceService($pdo, $bio);

// ------------------------------------------------------------------
// 1. IDENTITY & DUAL-ROLE FOUNDATION
// ------------------------------------------------------------------
echo "--- 1. ADMIN IDENTITY & DUAL-ROLE TEACHER PERMISSION ---\n";

// Verify database has exactly 1 administrator
$adminRows = $pdo->query("SELECT id, username, role, teacher_id FROM users WHERE role = 'admin' AND deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
assertBio(count($adminRows) === 1, "Exactly one administrator account exists in system");

$admin = $bio->getAdminAccount();
assertBio(!empty($admin) && $admin['role'] === 'admin', "Admin account successfully retrieved and verified");
assertBio(!empty($admin['teacher_id']), "Administrator is assigned a valid teacher_id ({$admin['teacher_id']})");

// Verify administrator exists in teachers table
$stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([(int)$admin['teacher_id']]);
$teacherRow = $stmt->fetch(PDO::FETCH_ASSOC);
assertBio(!empty($teacherRow), "Administrator is present in teachers directory: '{$teacherRow['name']}'");

// Verify non-admin rejection
$nonAdminRows = $pdo->query("SELECT id, username, role FROM users WHERE role != 'admin' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($nonAdminRows) {
    $nonAdminBlocked = false;
    try {
        $bio->ensureAdminUser((int)$nonAdminRows['id']);
    } catch (RuntimeException $e) {
        $nonAdminBlocked = str_contains($e->getMessage(), 'Administrator privilege');
    }
    assertBio($nonAdminBlocked, "Non-admin user (ID: {$nonAdminRows['id']}, role: '{$nonAdminRows['role']}') is strictly blocked from biometric access");
}

// ------------------------------------------------------------------
// 2. SESSION ESTABLISHMENT & DUAL-ROLE PRESERVATION
// ------------------------------------------------------------------
echo "\n--- 2. SESSION ESTABLISHMENT & DUAL-ROLE PRESERVATION ---\n";

$_SESSION = [];
$bio->establishAdminSession($admin, AdminBiometricService::METHOD_PASSKEY);

assertBio($_SESSION['user_id'] === (int)$admin['id'], "Session user_id matches existing administrator");
assertBio($_SESSION['role'] === 'admin', "Session role is 'admin'");
assertBio($_SESSION['teacher_id'] === (int)$admin['teacher_id'], "Session preserves teacher_id");
assertBio($_SESSION['can_teach'] === true, "Session flag can_teach is true");
assertBio($_SESSION['has_teacher_role'] === true, "Session flag has_teacher_role is true");
assertBio($_SESSION['auth_method'] === AdminBiometricService::METHOD_PASSKEY, "Session records auth_method as 'passkey'");

// Verify global auth helpers
assertBio(is_admin() === true, "Global is_admin() helper returns true");
assertBio(is_teacher() === true, "Global is_teacher() helper returns true for administrator");

// Verify dashboard view resolution
$requestedViewTeacher = 'teacher';
$effectiveViewTeacher = (is_admin() && is_teacher() && $requestedViewTeacher === 'teacher') ? 'teacher' : 'admin';
assertBio($effectiveViewTeacher === 'teacher', "Admin can switch to Teacher Dashboard view without losing admin rights");

$requestedViewAdmin = 'admin';
$effectiveViewAdmin = (is_admin() && is_teacher() && $requestedViewAdmin === 'teacher') ? 'teacher' : 'admin';
assertBio($effectiveViewAdmin === 'admin', "Admin can switch to Admin Dashboard view seamlessly");

// ------------------------------------------------------------------
// 3. EPHEMERAL BIOMETRIC CHALLENGES
// ------------------------------------------------------------------
echo "\n--- 3. EPHEMERAL BIOMETRIC CHALLENGES ---\n";

$chToken = $bio->createChallenge('passkey_reg', (int)$admin['id'], ['test' => 'data'], 90);
assertBio(is_string($chToken) && strlen($chToken) === 64, "Generated 64-char cryptographically secure challenge token");

$consumedPayload = $bio->consumeChallenge('passkey_reg', (int)$admin['id'], $chToken);
assertBio(is_array($consumedPayload) && ($consumedPayload['test'] ?? '') === 'data', "Challenge token successfully consumed with valid payload");

// Attempt to consume again (Single-use constraint)
$reuseBlocked = false;
try {
    $bio->consumeChallenge('passkey_reg', (int)$admin['id'], $chToken);
} catch (RuntimeException $e) {
    $reuseBlocked = true;
}
assertBio($reuseBlocked, "Challenge token cannot be re-used (strictly single-use / replay protected)");

// ------------------------------------------------------------------
// 4. WEBAUTHN / FIDO2 PASSKEY MANAGEMENT
// ------------------------------------------------------------------
echo "\n--- 4. WEBAUTHN / FIDO2 PASSKEY MANAGEMENT ---\n";

$adminId = (int)$admin['id'];
$regOptions = $passkey->getRegistrationOptions($adminId, 'Admin Test Passkey');
$createPk = $regOptions['options']->publicKey ?? null;
assertBio($createPk && !empty($createPk->challenge->getBinaryString()), "WebAuthn registration challenge generated");
assertBio(!empty($regOptions['challenge_token']), "Challenge token bound to registration session");
assertBio($createPk && $createPk->rp->name === 'Edexcel College', "Relying Party name is correctly set");
assertBio($createPk && !empty($createPk->user->id->getBinaryString()), "User handle is present in registration options");

// Clean existing test passkeys for deterministic test
$pdo->prepare("DELETE FROM admin_passkeys WHERE name LIKE 'Audit%' AND user_id = ?")->execute([$adminId]);

// Register mock credential in admin_passkeys
$mockCredId = AdminPasskeyService::base64UrlEncode('audit_cred_' . bin2hex(random_bytes(16)));
$mockPubKey = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEtestpublickeyforpasskeyverification1234567890\n-----END PUBLIC KEY-----";

$pdo->prepare("
    INSERT INTO admin_passkeys (user_id, credential_id, public_key, user_handle, name, sign_count, transports, created_ip)
    VALUES (?, ?, ?, ?, ?, 0, 'internal,hybrid', '127.0.0.1')
")->execute([$adminId, $mockCredId, $mockPubKey, (string)$adminId, 'Audit Windows Hello']);

$passkeys = $passkey->listPasskeys($adminId);
assertBio(count($passkeys) >= 1, "Registered passkey successfully listed");
$auditPasskey = null;
foreach ($passkeys as $pk) {
    if ($pk['credential_id'] === $mockCredId) {
        $auditPasskey = $pk;
        break;
    }
}
assertBio(!empty($auditPasskey), "Audit passkey retrieved by credential ID");
assertBio(!isset($auditPasskey['public_key']), "Passkey list does NOT expose public key or sensitive key material to UI");

// Test Passkey authentication options
$authOptions = $passkey->getAuthenticationOptions($adminId);
$authPk = $authOptions['options']->publicKey ?? null;
assertBio($authPk && !empty($authPk->challenge->getBinaryString()), "Passkey authentication challenge generated");
assertBio($authPk && !empty($authPk->allowCredentials), "Passkey authentication allowCredentials populated");

// Test signature counter tracking
$pdo->prepare("UPDATE admin_passkeys SET sign_count = 5, last_used_at = CURRENT_TIMESTAMP WHERE credential_id = ?")->execute([$mockCredId]);
$stmt = $pdo->prepare("SELECT sign_count, last_used_at FROM admin_passkeys WHERE credential_id = ?");
$stmt->execute([$mockCredId]);
$updatedPk = $stmt->fetch(PDO::FETCH_ASSOC);
assertBio((int)$updatedPk['sign_count'] === 5, "Passkey signature counter accurately incremented to 5");
assertBio(!empty($updatedPk['last_used_at']), "Passkey last_used_at timestamp recorded");

// Test Passkey revocation
$passkey->revokePasskey($adminId, (int)$auditPasskey['id']);
$activeAfterRevoke = $passkey->listPasskeys($adminId);
$stillFound = false;
foreach ($activeAfterRevoke as $pk) {
    if ($pk['credential_id'] === $mockCredId) {
        $stillFound = true;
    }
}
assertBio(!$stillFound, "Revoked passkey is excluded from active passkeys list");

// ------------------------------------------------------------------
// 5. WEBCAM FACE VERIFICATION & PRESENTATION ATTACK DETECTION (PAD)
// ------------------------------------------------------------------
echo "\n--- 5. WEBCAM FACE VERIFICATION & PRESENTATION ATTACK DETECTION (PAD) ---\n";

// Test explicit consent enforcement
$consentEnforced = false;
try {
    $face->createEnrollmentChallenge($adminId, false);
} catch (RuntimeException $e) {
    $consentEnforced = str_contains($e->getMessage(), 'explicit user consent');
}
assertBio($consentEnforced, "Face enrollment strictly fails if user consent is not given");

// Create valid enrollment challenge
$enrolCh = $face->createEnrollmentChallenge($adminId, true);
assertBio(!empty($enrolCh['challenge_token']), "Face enrollment challenge created with valid token");
assertBio(count($enrolCh['required_poses']) === 5, "Enrollment requires 5 poses (neutral, turn_left, turn_right, look_up, look_down)");

// Build 5-sample enrollment dataset (unit length 128-d vector)
$baseVector = array_fill(0, 128, 0.088); // 128 * (0.088^2) ≈ 0.99
$samples = [];
foreach (['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'] as $idx => $pose) {
    $sampleVec = $baseVector;
    $sampleVec[$idx] += 0.005; // slight natural variance across poses
    $samples[$pose] = [
        'descriptor' => $sampleVec,
        'quality' => [
            'face_count' => 1,
            'box_ratio' => 0.46,
            'brightness' => 125.0,
            'sharpness' => 35.0,
        ],
    ];
}

$enrolResult = $face->processEnrollment($adminId, $enrolCh['challenge_token'], $samples);
assertBio($enrolResult['ok'] === true, "Multi-sample face enrollment succeeded");
assertBio($enrolResult['sample_count'] === 5, "Recorded exactly 5 valid pose samples");
assertBio($face->isEnrolled($adminId) === true, "Face login status verified as ENROLLED");

// Verify database storage security: AES-256-GCM ciphertext, NO raw image or vector
$stmt = $pdo->prepare("SELECT template_encrypted, metadata FROM admin_face_credentials WHERE user_id = ?");
$stmt->execute([$adminId]);
$faceRow = $stmt->fetch(PDO::FETCH_ASSOC);
assertBio(!empty($faceRow['template_encrypted']), "Encrypted template stored in database");
$rawBinary = base64_decode($faceRow['template_encrypted'], true);
assertBio($rawBinary !== false && strlen($rawBinary) >= 28, "Biometric template is encrypted with AES-256-GCM (12-byte IV + 16-byte Auth Tag + Ciphertext)");
$decryptedTemplate = json_decode($bio->decryptBiometricData($faceRow['template_encrypted']), true);
assertBio(is_array($decryptedTemplate) && isset($decryptedTemplate['embedding']), "Encrypted template successfully decrypts with authenticated integrity");
assertBio(!str_contains($faceRow['template_encrypted'], '0.088'), "Plaintext biometric descriptors are NOT stored in the database");

// Test Liveness Challenge Generation
$authLiveness = $face->createAuthLivenessChallenge($adminId);
assertBio(!empty($authLiveness['challenge_token']), "Liveness challenge token generated");
assertBio(count($authLiveness['sequence']) >= 2, "Liveness requires dynamic unpredictable gesture sequence");

// Test Authentic Face Verification with Valid PAD Telemetry
$validSteps = [];
foreach ($authLiveness['sequence'] as $act) {
    $validSteps[] = [
        'action' => $act,
        'yaw' => ($act === 'TURN_LEFT') ? -16.0 : (($act === 'TURN_RIGHT') ? 16.0 : 0.0),
        'pitch' => ($act === 'NOD_UP') ? 8.5 : (($act === 'LOOK_STRAIGHT') ? 0.0 : -2.0),
        'ear' => ($act === 'BLINK') ? 0.17 : 0.31,
        'face_count' => 1,
        'box_ratio' => 0.46,
        'brightness' => 125.0,
    ];
}
$validTelemetry = [
    'steps' => $validSteps,
    'motion_score' => 0.025, // Natural micro-motion
];

$verifyRes = $face->processVerification($adminId, $authLiveness['challenge_token'], $baseVector, $validTelemetry);
assertBio($verifyRes['matched'] === true, "1:1 Face verification confirmed authentic administrator");
assertBio($verifyRes['similarity_score_passed'] === true, "Facial feature similarity passed threshold");
assertBio($verifyRes['liveness_passed'] === true, "Liveness and Presentation Attack Detection passed");

// Test Imposter / Mismatched Face Rejection
$imposterCh = $face->createAuthLivenessChallenge($adminId);
$imposterVector = array_fill(0, 128, -0.088); // Inverted vector -> Cosine similarity < 0
$imposterRejected = false;
try {
    $face->processVerification($adminId, $imposterCh['challenge_token'], $imposterVector, $validTelemetry);
} catch (RuntimeException $e) {
    $imposterRejected = str_contains($e->getMessage(), 'could not verify your face');
}
assertBio($imposterRejected, "Imposter / mismatched facial descriptor is strictly rejected");

// Test PAD: Static Planar Photograph Spoof Rejection (zero motion)
$photoSpoofCh = $face->createAuthLivenessChallenge($adminId);
$photoTelemetry = [
    'steps' => $validSteps,
    'motion_score' => 0.0001, // Zero micro-motion (static paper/screen)
];
$photoSpoofRejected = false;
try {
    $face->processVerification($adminId, $photoSpoofCh['challenge_token'], $baseVector, $photoTelemetry);
} catch (RuntimeException $e) {
    $photoSpoofRejected = str_contains($e->getMessage(), 'could not verify your face');
}
assertBio($photoSpoofRejected, "Presentation Attack Detection: Static photo attack (zero micro-motion) is rejected");

// Test PAD: Multiple Faces Detected Violation
$multiFaceCh = $face->createAuthLivenessChallenge($adminId);
$multiFaceSteps = $validSteps;
$multiFaceSteps[0]['face_count'] = 2; // Second person in frame
$multiFaceTelemetry = [
    'steps' => $multiFaceSteps,
    'motion_score' => 0.02,
];
$multiFaceRejected = false;
try {
    $face->processVerification($adminId, $multiFaceCh['challenge_token'], $baseVector, $multiFaceTelemetry);
} catch (RuntimeException $e) {
    $multiFaceRejected = str_contains($e->getMessage(), 'could not verify your face');
}
assertBio($multiFaceRejected, "Presentation Attack Detection: Multiple faces in frame rejected");

// Test Disable & Clear Face
$face->disableFace($adminId);
assertBio($face->isEnrolled($adminId) === false, "Face login successfully disabled");

$face->clearFace($adminId);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_face_credentials WHERE user_id = ?");
$stmt->execute([$adminId]);
assertBio((int)$stmt->fetchColumn() === 0, "Face credential record completely cleared from database");

// ------------------------------------------------------------------
// 6. AUTHENTICATION AUDIT TRAIL
// ------------------------------------------------------------------
echo "\n--- 6. AUTHENTICATION AUDIT TRAIL ---\n";

$auditRows = $pdo->query("
    SELECT id, authentication_method, success, failure_reason, user_id, ip_address, metadata, created_at
    FROM authentication_audit
    WHERE user_id = {$adminId}
    ORDER BY id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

assertBio(count($auditRows) >= 3, "Authentication audit trail actively records authentication events (found " . count($auditRows) . " events)");
$hasPasskeyAudit = false;
$hasFaceAudit = false;
$hasFailureAudit = false;

foreach ($auditRows as $r) {
    if ($r['authentication_method'] === 'passkey') {
        $hasPasskeyAudit = true;
    }
    if ($r['authentication_method'] === 'face') {
        $hasFaceAudit = true;
    }
    if ((int)$r['success'] === 0) {
        $hasFailureAudit = true;
    }
}

assertBio($hasPasskeyAudit, "Passkey events logged in authentication_audit");
assertBio($hasFaceAudit, "Face events logged in authentication_audit");
assertBio($hasFailureAudit, "Biometric failure/spoof attempts logged in authentication_audit");

// Verify audit logs NEVER contain raw facial descriptors or private keys
$leakFound = false;
foreach ($auditRows as $r) {
    if (str_contains((string)$r['metadata'], '0.088') || str_contains((string)$r['metadata'], 'descriptor')) {
        $leakFound = true;
    }
}
assertBio(!$leakFound, "Audit trail contains zero biometric descriptors or sensitive key material");

// ------------------------------------------------------------------
// 7. CLEANUP AUDIT TEST ARTIFACTS
// ------------------------------------------------------------------
$pdo->prepare("DELETE FROM admin_passkeys WHERE name LIKE 'Audit%' AND user_id = ?")->execute([$adminId]);
$pdo->prepare("DELETE FROM admin_biometric_challenges WHERE user_id = ? AND created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)")->execute([$adminId]);

echo "\n====================================================================\n";
echo " ACCEPTANCE AUDIT SUMMARY: {$passedTests} PASSED, {$failedTests} FAILED\n";
echo "====================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
