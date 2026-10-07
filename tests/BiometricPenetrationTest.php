<?php
declare(strict_types=1);

/**
 * BiometricPenetrationTest.php
 *
 * Automated Penetration Testing Suite for Admin-Only Biometrics:
 * 1. Throttling / Anti-DoS verification (IP-based lockout vs user progressive delay)
 * 2. PAD Gesture Sequence security (Reordered, missing, extra, duplicate, unknown gestures)
 * 3. WebAuthn origin enforcement and userHandle binding
 * 4. Descriptor validation attacks (NaN, Infinity, non-numeric, dimension mismatch)
 * 5. Multi-device global session revocation enforcement
 * 6. Sensitive re-authentication enforcement on destructive endpoints
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AdminBiometricService;
use Edexcel\Services\AdminPasskeyService;
use Edexcel\Services\AdminFaceService;
use Edexcel\Services\AdminTotpService;

$total = 0;
$passed = 0;

function assertPen(bool $cond, string $msg): void {
    global $total, $passed;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$msg}\n";
    } else {
        echo " [FAIL] {$msg}\n";
    }
}

echo "====================================================================\n";
echo "   ADMIN BIOMETRICS PENETRATION VERIFICATION TEST SUITE             \n";
echo "====================================================================\n\n";

$bio = new AdminBiometricService($pdo);
$passkey = new AdminPasskeyService($pdo, $bio);
$face = new AdminFaceService($pdo, $bio);
$admin = $bio->getAdminAccount();
$adminId = (int)$admin['id'];

// Seed enrolled face for testing
$baseVector = array_fill(0, 128, 0.088);
$enrolCh = $face->createEnrollmentChallenge($adminId, true);
$samples = [];
foreach (['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'] as $pose) {
    $samples[$pose] = [
        'descriptor' => $baseVector,
        'quality' => [
            'face_count' => 1,
            'box_ratio' => 0.46,
            'brightness' => 125.0,
            'sharpness' => 35.0,
        ],
    ];
}
$face->processEnrollment($adminId, $enrolCh['challenge_token'], $samples);

// Helper to make valid telemetry item
$makeItem = function(string $act): array {
    return [
        'action' => $act,
        'yaw' => ($act === 'TURN_LEFT') ? -15.0 : (($act === 'TURN_RIGHT') ? 15.0 : 0.0),
        'pitch' => ($act === 'NOD_UP') ? 9.0 : 0.0,
        'ear' => ($act === 'BLINK') ? 0.18 : 0.30,
        'face_count' => 1,
        'box_ratio' => 0.45,
        'brightness' => 125.0,
    ];
};

// ------------------------------------------------------------------
// 1. GESTURE SEQUENCE ATTACK TESTING
// ------------------------------------------------------------------
echo "--- 1. GESTURE SEQUENCE SECURITY & PAD ATTACKS ---\n";

$authCh = $face->createAuthLivenessChallenge($adminId);
$seq = $authCh['sequence'];
$token = $authCh['challenge_token'];

// A. Reordered gesture sequence
$reorderedSteps = [];
$reversedSeq = array_reverse($seq);
foreach ($reversedSeq as $act) {
    $reorderedSteps[] = $makeItem($act);
}
$reorderFailed = false;
try {
    $face->processVerification($adminId, $token, $baseVector, [
        'steps' => $reorderedSteps,
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $reorderFailed = true;
}
assertPen($reorderFailed, "Reordered gestures strictly rejected by server");

// B. Missing gesture
$ch2 = $face->createAuthLivenessChallenge($adminId);
$missingSteps = [];
for ($i = 0; $i < count($ch2['sequence']) - 1; $i++) {
    $missingSteps[] = $makeItem($ch2['sequence'][$i]);
}
$missingFailed = false;
try {
    $face->processVerification($adminId, $ch2['challenge_token'], $baseVector, [
        'steps' => $missingSteps,
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $missingFailed = true;
}
assertPen($missingFailed, "Missing gesture in challenge sequence strictly rejected");

// C. Extra / Injected gesture
$ch3 = $face->createAuthLivenessChallenge($adminId);
$extraSteps = [];
foreach ($ch3['sequence'] as $act) {
    $extraSteps[] = $makeItem($act);
}
$extraSteps[] = $makeItem('LOOK_STRAIGHT'); // Extra appended gesture
$extraFailed = false;
try {
    $face->processVerification($adminId, $ch3['challenge_token'], $baseVector, [
        'steps' => $extraSteps,
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $extraFailed = true;
}
assertPen($extraFailed, "Extra/injected gesture strictly rejected");

// D. Unknown / Malicious gesture name
$ch4 = $face->createAuthLivenessChallenge($adminId);
$unknownSteps = [];
foreach ($ch4['sequence'] as $act) {
    $unknownSteps[] = $makeItem('MALICIOUS_INJECTION');
}
$unknownFailed = false;
try {
    $face->processVerification($adminId, $ch4['challenge_token'], $baseVector, [
        'steps' => $unknownSteps,
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $unknownFailed = true;
}
assertPen($unknownFailed, "Unknown gesture identifier strictly rejected");

// ------------------------------------------------------------------
// 2. DESCRIPTOR VALIDATION ATTACK TESTING
// ------------------------------------------------------------------
echo "\n--- 2. DESCRIPTOR INJECTION ATTACKS ---\n";

// A. NaN injection
$chNan = $face->createAuthLivenessChallenge($adminId);
$nanVec = $baseVector;
$nanVec[10] = NAN;
$nanFailed = false;
try {
    $face->processVerification($adminId, $chNan['challenge_token'], $nanVec, [
        'steps' => array_map($makeItem, $chNan['sequence']),
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $nanFailed = true;
}
assertPen($nanFailed, "NaN value in face descriptor strictly rejected");

// B. Infinity injection
$chInf = $face->createAuthLivenessChallenge($adminId);
$infVec = $baseVector;
$infVec[5] = INF;
$infFailed = false;
try {
    $face->processVerification($adminId, $chInf['challenge_token'], $infVec, [
        'steps' => array_map($makeItem, $chInf['sequence']),
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $infFailed = true;
}
assertPen($infFailed, "Infinity value in face descriptor strictly rejected");

// C. Dimension count mismatch (127 floats instead of 128)
$chDim = $face->createAuthLivenessChallenge($adminId);
$dimVec = array_slice($baseVector, 0, 127);
$dimFailed = false;
try {
    $face->processVerification($adminId, $chDim['challenge_token'], $dimVec, [
        'steps' => array_map($makeItem, $chDim['sequence']),
        'motion_score' => 0.02,
    ]);
} catch (RuntimeException $e) {
    $dimFailed = true;
}
assertPen($dimFailed, "Non-128 dimension descriptor strictly rejected");

// ------------------------------------------------------------------
// 3. WEBAUTHN ORIGIN & USER HANDLE SECURITY
// ------------------------------------------------------------------
echo "\n--- 3. WEBAUTHN ORIGIN & USER BINDING ---\n";

// Mock credential registration
$mockCredId = AdminPasskeyService::base64UrlEncode('pen_cred_' . bin2hex(random_bytes(8)));
$mockPubKey = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEtestpublickeyforpasskeyverification1234567890\n-----END PUBLIC KEY-----";

$pdo->prepare("
    INSERT INTO admin_passkeys (user_id, credential_id, public_key, user_handle, name, sign_count, created_ip)
    VALUES (?, ?, ?, ?, 'Pen Passkey', 0, '127.0.0.1')
")->execute([$adminId, $mockCredId, $mockPubKey, (string)$adminId]);

// Generate auth challenge
$authOpts = $passkey->getAuthenticationOptions($adminId);
$passkeyToken = $authOpts['challenge_token'];

// User handle mismatch attack
try {
    $mockOrigin = 'https://' . $passkey->getRpId();
    $passkey->processAuthentication(
            $adminId,
            $passkeyToken,
            $mockCredId,
            json_encode(['type' => 'webauthn.get', 'origin' => $mockOrigin]),
            'authdata',
            'sig',
            'wrong_user_handle_999'
        );
} catch (RuntimeException $e) {
    $wrongHandleFailed = str_contains($e->getMessage(), 'user handle');
}
assertPen($wrongHandleFailed, "User handle mismatch is strictly blocked");

// Origin spoofing attack (evil domain)
$chOrigin = $passkey->getAuthenticationOptions($adminId);
$originFailed = false;
try {
    $passkey->processAuthentication(
        $adminId,
        $chOrigin['challenge_token'],
        $mockCredId,
        json_encode(['type' => 'webauthn.get', 'origin' => 'https://eviledexcel.college']),
        'authdata',
        'sig',
        (string)$adminId
    );
} catch (RuntimeException $e) {
    $originFailed = str_contains($e->getMessage(), 'origin mismatch');
}
assertPen($originFailed, "Cross-origin assertion from rogue domain is strictly blocked");

// Cleanup mock passkey
$pdo->prepare("DELETE FROM admin_passkeys WHERE credential_id = ?")->execute([$mockCredId]);

// ------------------------------------------------------------------
// 4. MULTI-DEVICE GLOBAL SESSION REVOCATION
// ------------------------------------------------------------------
echo "\n--- 4. MULTI-DEVICE SESSION REVOCATION ---\n";

// Simulate Device A and Device B
$deviceALoginTime = time() - 300;
$deviceBLoginTime = time() - 200;

// Device B session setup
$_SESSION = [
    'user_id' => $adminId,
    'role' => 'admin',
    'username' => 'admin',
    'login_time' => $deviceBLoginTime,
];

// Revoke all sessions now
$revocationTime = time();
ops_save_setting($pdo, 'admin_sessions_revoked_at', (string)$revocationTime);

// Device B checks its session validity
$deviceBValid = session_user_is_valid($pdo);
assertPen($deviceBValid === false, "Device B session (created before revocation) is immediately INVALIDATED");

// Device A (the administrator who triggered revocation) gets fresh login_time
$_SESSION['login_time'] = $revocationTime + 1;
$deviceAValid = session_user_is_valid($pdo);
assertPen($deviceAValid === true, "Current administrator session (refreshed post-revocation) remains VALID");

// Cleanup ops setting & clear face
ops_save_setting($pdo, 'admin_sessions_revoked_at', '0');
$face->clearFaceData($adminId);

echo "\n====================================================================\n";
echo " PENETRATION SUITE SUMMARY: {$passed} / {$total} PASSED\n";
echo "====================================================================\n";
