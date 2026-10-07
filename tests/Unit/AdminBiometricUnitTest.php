<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\AdminBiometricService;
use Edexcel\Services\AdminFaceService;
use Edexcel\Services\AdminPasskeyService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/config/auth.php';

final class AdminBiometricUnitTest extends TestCase
{
    private PDO $pdo;
    private AdminBiometricService $bio;
    private AdminPasskeyService $passkey;
    private AdminFaceService $face;

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Build SQLite schema mimicking MySQL tables
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'student',
                teacher_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT NULL
            );
            CREATE TABLE teachers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NULL,
                email TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE admin_passkeys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                credential_id TEXT UNIQUE NOT NULL,
                public_key TEXT NOT NULL,
                user_handle TEXT NULL,
                name TEXT NOT NULL,
                attestation_format TEXT NULL,
                sign_count INTEGER NOT NULL DEFAULT 0,
                transports TEXT NULL,
                last_used_at TEXT NULL,
                revoked_at TEXT NULL,
                created_ip TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE admin_face_credentials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                template_encrypted TEXT NOT NULL,
                sample_count INTEGER NOT NULL DEFAULT 5,
                status TEXT NOT NULL DEFAULT 'active',
                enrolled_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_verified_at TEXT NULL,
                last_verification_ip TEXT NULL,
                metadata TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE authentication_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                authentication_method TEXT NOT NULL,
                success INTEGER NOT NULL,
                failure_reason TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                session_reference TEXT NULL,
                metadata TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE security_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_code TEXT NOT NULL,
                user_id INTEGER NULL,
                actor_user_id INTEGER NULL,
                device_id INTEGER NULL,
                timetable_id INTEGER NULL,
                teacher_id INTEGER NULL,
                result TEXT NULL,
                message TEXT NULL,
                reference_id INTEGER NULL,
                ip_address TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                severity TEXT NOT NULL DEFAULT 'info',
                context TEXT NOT NULL DEFAULT 'auth'
            );
            CREATE TABLE admin_biometric_challenges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                challenge_type TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                challenge_token TEXT UNIQUE NOT NULL,
                challenge_payload TEXT NOT NULL,
                ip_address TEXT NULL,
                used_at TEXT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE student_active_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_id TEXT NOT NULL,
                device_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed single administrator account who also has teacher duties
        $this->pdo->exec("
            INSERT INTO teachers (id, name, phone, email) VALUES (1, 'Admin Teacher', '0771234567', 'admin@edexcel.college');
            INSERT INTO users (id, username, password_hash, role, teacher_id, is_active)
            VALUES (1, 'local.test.admin', '\$2y\$10\$xyz', 'admin', 1, 1);
        ");

        $this->bio = new AdminBiometricService($this->pdo);
        $this->passkey = new AdminPasskeyService($this->pdo, $this->bio);
        $this->face = new AdminFaceService($this->pdo, $this->bio);
    }

    // ==============================================================
    // 1. BIOMETRIC CHALLENGE LIFECYCLE & SECURITY
    // ==============================================================

    public function testChallengeGenerationAndSingleUseConsumption(): void
    {
        $token = $this->bio->createChallenge('passkey_auth', 1, ['nonce' => '12345']);
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token));

        // Consuming valid token returns payload
        $payload = $this->bio->consumeChallenge('passkey_auth', 1, $token);
        $this->assertSame('12345', $payload['nonce']);

        // Attempting to consume a second time must throw (Anti-replay protection)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This biometric challenge has already been used.');
        $this->bio->consumeChallenge('passkey_auth', 1, $token);
    }

    public function testChallengeRejectsExpiredToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $past = date('Y-m-d H:i:s', time() - 30);
        $this->pdo->prepare("
            INSERT INTO admin_biometric_challenges
                (challenge_type, user_id, challenge_token, challenge_payload, expires_at)
            VALUES ('face_auth', 1, ?, '{}', ?)
        ")->execute([$token, $past]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Biometric challenge expired.');
        $this->bio->consumeChallenge('face_auth', 1, $token);
    }

    public function testChallengeRejectsWrongTypeOrWrongUser(): void
    {
        $token = $this->bio->createChallenge('face_auth', 1, ['test' => true]);

        // Wrong challenge type
        $this->expectException(RuntimeException::class);
        $this->bio->consumeChallenge('passkey_auth', 1, $token);
    }

    // ==============================================================
    // 2. ENCRYPTION AT REST
    // ==============================================================

    public function testBiometricEncryptionAndDecryption(): void
    {
        $sensitiveVector = json_encode(array_fill(0, 128, 0.12345));
        $encrypted = $this->bio->encryptBiometricData($sensitiveVector);

        $this->assertNotSame($sensitiveVector, $encrypted);
        $this->assertGreaterThan(50, strlen($encrypted));

        $decrypted = $this->bio->decryptBiometricData($encrypted);
        $this->assertSame($sensitiveVector, $decrypted);
    }

    public function testTamperedCiphertextThrowsException(): void
    {
        $encrypted = $this->bio->encryptBiometricData('test');
        $tampered = substr_replace($encrypted, 'Z', 15, 1);

        $this->expectException(RuntimeException::class);
        $this->bio->decryptBiometricData($tampered);
    }

    // ==============================================================
    // 3. FACIAL MATHEMATICAL MATCHING (EUCLIDEAN & COSINE)
    // ==============================================================

    public function testEuclideanDistanceAndCosineSimilarity(): void
    {
        $v1 = array_fill(0, 128, 0.5);
        $v2 = array_fill(0, 128, 0.5);

        // Identical vectors: distance = 0, cosine = 1
        $this->assertEqualsWithDelta(0.0, AdminFaceService::euclideanDistance($v1, $v2), 0.0001);
        $this->assertEqualsWithDelta(1.0, AdminFaceService::cosineSimilarity($v1, $v2), 0.0001);

        // Slightly perturbed vectors (small natural variation)
        $v3 = $v1;
        $v3[0] += 0.05;
        $v3[1] -= 0.05;
        $dist = AdminFaceService::euclideanDistance($v1, $v3);
        $sim = AdminFaceService::cosineSimilarity($v1, $v3);

        $this->assertLessThan(AdminFaceService::MAX_EUCLIDEAN_DISTANCE, $dist);
        $this->assertGreaterThan(AdminFaceService::MIN_COSINE_SIMILARITY, $sim);

        // Completely different / opposite vector
        $vDiff = array_fill(0, 128, -0.5);
        $distDiff = AdminFaceService::euclideanDistance($v1, $vDiff);
        $simDiff = AdminFaceService::cosineSimilarity($v1, $vDiff);

        $this->assertGreaterThan(AdminFaceService::MAX_EUCLIDEAN_DISTANCE, $distDiff);
        $this->assertLessThan(AdminFaceService::MIN_COSINE_SIMILARITY, $simDiff);
    }

    public function testVectorNormalizationProducesUnitLength(): void
    {
        $vec = array_fill(0, 128, 2.0);
        $norm = AdminFaceService::normalizeVector($vec);

        $sumSq = 0.0;
        foreach ($norm as $val) {
            $sumSq += $val * $val;
        }
        $this->assertEqualsWithDelta(1.0, sqrt($sumSq), 0.0001);
    }

    // ==============================================================
    // 4. FACE ENROLLMENT & VERIFICATION LIFECYCLE
    // ==============================================================

    public function testEnrollmentWithoutConsentFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Biometric enrollment requires explicit user consent');
        $this->face->createEnrollmentChallenge(1, false);
    }

    public function testSuccessfulEnrollmentAndVerification(): void
    {
        // 1. Create challenge
        $ch = $this->face->createEnrollmentChallenge(1, true);
        $token = $ch['challenge_token'];

        // 2. Build 5 consistent pose samples
        $baseVector = array_fill(0, 128, 0.088);
        $samples = [];
        $poses = ['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'];

        foreach ($poses as $p) {
            $sampleVec = $baseVector;
            $sampleVec[0] += 0.01;
            $samples[$p] = [
                'descriptor' => $sampleVec,
                'quality' => [
                    'face_count' => 1,
                    'box_ratio' => 0.45,
                    'brightness' => 120.0,
                    'sharpness' => 30.0,
                ],
            ];
        }

        $res = $this->face->processEnrollment(1, $token, $samples);
        $this->assertTrue($res['ok']);
        $this->assertSame(5, $res['sample_count']);
        $this->assertTrue($this->face->isEnrolled(1));

        // 3. Authenticate with verified face
        $authCh = $this->face->createAuthLivenessChallenge(1);
        $authToken = $authCh['challenge_token'];
        $sequence = $authCh['sequence'];

        // Build valid liveness telemetry satisfying the sequence
        $telemetrySteps = [];
        foreach ($sequence as $act) {
            $telemetrySteps[] = [
                'action' => $act,
                'yaw' => ($act === 'TURN_LEFT') ? -15.0 : (($act === 'TURN_RIGHT') ? 15.0 : 0.0),
                'pitch' => ($act === 'NOD_UP') ? 9.0 : 0.0,
                'ear' => ($act === 'BLINK') ? 0.18 : 0.30,
                'face_count' => 1,
                'box_ratio' => 0.45,
                'brightness' => 125.0,
            ];
        }

        $incomingVec = $baseVector;
        $incomingVec[0] += 0.01;

        $authRes = $this->face->processVerification(1, $authToken, $incomingVec, [
            'steps' => $telemetrySteps,
            'motion_score' => 0.02,
        ]);

        $this->assertTrue($authRes['matched']);
        $this->assertTrue($authRes['liveness_passed']);
    }

    public function testFaceVerificationRejectsWrongFace(): void
    {
        // Enrol base template
        $ch = $this->face->createEnrollmentChallenge(1, true);
        $baseVector = array_fill(0, 128, 0.088);
        $samples = [];
        foreach (['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'] as $p) {
            $samples[$p] = [
                'descriptor' => $baseVector,
                'quality' => ['face_count' => 1, 'box_ratio' => 0.45, 'brightness' => 120.0],
            ];
        }
        $this->face->processEnrollment(1, $ch['challenge_token'], $samples);

        // Attempt verification with an entirely different face vector
        $authCh = $this->face->createAuthLivenessChallenge(1);
        $wrongVector = array_fill(0, 128, -0.088);

        $telemetrySteps = [];
        foreach ($authCh['sequence'] as $act) {
            $telemetrySteps[] = [
                'action' => $act,
                'yaw' => ($act === 'TURN_LEFT') ? -15.0 : (($act === 'TURN_RIGHT') ? 15.0 : 0.0),
                'pitch' => ($act === 'NOD_UP') ? 9.0 : 0.0,
                'ear' => ($act === 'BLINK') ? 0.18 : 0.30,
                'face_count' => 1,
                'box_ratio' => 0.45,
                'brightness' => 125.0,
            ];
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('We could not verify your face.');
        $this->face->processVerification(1, $authCh['challenge_token'], $wrongVector, [
            'steps' => $telemetrySteps,
            'motion_score' => 0.02,
        ]);
    }

    public function testLivenessFailsWhenMultipleFacesDetected(): void
    {
        // Enrol base template
        $ch = $this->face->createEnrollmentChallenge(1, true);
        $baseVector = array_fill(0, 128, 0.088);
        $samples = [];
        foreach (['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'] as $p) {
            $samples[$p] = [
                'descriptor' => $baseVector,
                'quality' => ['face_count' => 1, 'box_ratio' => 0.45, 'brightness' => 120.0],
            ];
        }
        $this->face->processEnrollment(1, $ch['challenge_token'], $samples);

        $authCh = $this->face->createAuthLivenessChallenge(1);

        // Inject 2 faces into one of the frames
        $telemetrySteps = [];
        foreach ($authCh['sequence'] as $idx => $act) {
            $telemetrySteps[] = [
                'action' => $act,
                'yaw' => 0.0,
                'pitch' => 0.0,
                'ear' => 0.30,
                'face_count' => ($idx === 1) ? 2 : 1, // Multiple faces violation!
                'box_ratio' => 0.45,
                'brightness' => 125.0,
            ];
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('We could not verify your face.');
        $this->face->processVerification(1, $authCh['challenge_token'], $baseVector, [
            'steps' => $telemetrySteps,
            'motion_score' => 0.02,
        ]);
    }

    public function testLivenessFailsWhenStaticPhotoWithoutMotion(): void
    {
        $ch = $this->face->createEnrollmentChallenge(1, true);
        $baseVector = array_fill(0, 128, 0.088);
        $samples = [];
        foreach (['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'] as $p) {
            $samples[$p] = [
                'descriptor' => $baseVector,
                'quality' => ['face_count' => 1, 'box_ratio' => 0.45, 'brightness' => 120.0],
            ];
        }
        $this->face->processEnrollment(1, $ch['challenge_token'], $samples);

        $authCh = $this->face->createAuthLivenessChallenge(1);
        $telemetrySteps = [];
        foreach ($authCh['sequence'] as $act) {
            $telemetrySteps[] = [
                'action' => $act,
                'yaw' => ($act === 'TURN_LEFT') ? -15.0 : (($act === 'TURN_RIGHT') ? 15.0 : 0.0),
                'pitch' => ($act === 'NOD_UP') ? 9.0 : 0.0,
                'ear' => ($act === 'BLINK') ? 0.18 : 0.30,
                'face_count' => 1,
                'box_ratio' => 0.45,
                'brightness' => 125.0,
            ];
        }

        // Zero physiological tremor / motion score (indicates static planar paper photograph)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('We could not verify your face.');
        $this->face->processVerification(1, $authCh['challenge_token'], $baseVector, [
            'steps' => $telemetrySteps,
            'motion_score' => 0.0001, // Failed motion score
        ]);
    }

    // ==============================================================
    // 5. ROLE & PERMISSION PRESERVATION (ADMIN IS ALSO TEACHER)
    // ==============================================================

    public function testDualRolePreservedOnBiometricSession(): void
    {
        $admin = $this->bio->getAdminAccount();
        $this->assertSame('admin', $admin['role']);
        $this->assertSame(1, (int)$admin['teacher_id']);

        // Establish biometric session
        $this->bio->establishAdminSession($admin, AdminBiometricService::METHOD_PASSKEY);

        // Verify session preserved both sets of capabilities
        $this->assertSame(1, $_SESSION['user_id']);
        $this->assertSame('admin', $_SESSION['role']);
        $this->assertSame(1, $_SESSION['teacher_id']);
        $this->assertTrue($_SESSION['can_teach']);
        $this->assertTrue($_SESSION['has_teacher_role']);
        $this->assertSame(AdminBiometricService::METHOD_PASSKEY, $_SESSION['auth_method']);

        // Verify helper functions
        $this->assertTrue(\is_admin());
        $this->assertTrue(\is_teacher());
    }

    // ==============================================================
    // 6. PASSKEY BASE64URL & REVOCATION
    // ==============================================================

    public function testPasskeyBase64UrlEncodingAndDecoding(): void
    {
        $data = "edexcel-fido2-challenge-binary-\x00\xff\xfe";
        $encoded = AdminPasskeyService::base64UrlEncode($data);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);

        $decoded = AdminPasskeyService::base64UrlDecode($encoded);
        $this->assertSame($data, $decoded);
    }

    public function testPasskeyRevocation(): void
    {
        $this->pdo->prepare("
            INSERT INTO admin_passkeys
                (user_id, credential_id, public_key, name, sign_count)
            VALUES (1, 'cred_test_123', 'fake_pem_key', 'Windows Hello Test', 0)
        ")->execute();

        $passkeys = $this->passkey->listPasskeys(1);
        $this->assertCount(1, $passkeys);
        $pkId = (int)$passkeys[0]['id'];

        $this->passkey->revokePasskey(1, $pkId);

        $activeAfter = $this->passkey->listPasskeys(1);
        $this->assertCount(0, $activeAfter);
    }

    // ==============================================================
    // 7. AUTHORIZATION ENFORCEMENT (ADMIN ONLY)
    // ==============================================================

    public function testNonAdminUserRejectedFromBiometricOperations(): void
    {
        // Non-admin user ID 999
        $this->assertFalse($this->bio->validateIsAdminUser(999));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');
        $this->passkey->getRegistrationOptions(999, 'student_test');
    }
}
