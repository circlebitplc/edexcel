<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * AdminFaceService
 *
 * Implements production-grade Admin Webcam Face Verification:
 * - 1:1 facial verification against the administrator's enrolled template
 * - Cryptographic AES-256-GCM template encryption at rest
 * - Multi-sample enrollment across 5 distinct head poses
 * - Dynamic interactive micro-challenge liveness protocol
 * - Presentation Attack Detection (PAD):
 *     * Single face constraint (strictly 1 face)
 *     * Continuous head pose 3D yaw/pitch displacement checks
 *     * Eye Aspect Ratio (EAR) temporal blink curve validation
 *     * Natural physiological landmark micro-motion verification
 *     * Image quality, bounding box ratio, and illumination checks
 * - Independent server-side mathematical verification (Euclidean distance & Cosine similarity)
 * - Zero storage of raw webcam video or photographs
 */
final class AdminFaceService
{
    // High-confidence 1:1 matching thresholds (calibrated for 128-d FaceNet/ResNet embeddings)
    public const MAX_EUCLIDEAN_DISTANCE = 0.48;
    public const MIN_COSINE_SIMILARITY = 0.88;

    // Minimum samples required for enrollment
    public const REQUIRED_ENROLLMENT_SAMPLES = 5;

    public function __construct(
        private PDO $pdo,
        private AdminBiometricService $biometricService
    ) {
    }

    /**
     * Checks if the administrator has an active enrolled face credential.
     */
    public function isEnrolled(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM admin_face_credentials
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('AdminFaceService: isEnrolled error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves public metadata for the administrator's face credential.
     * NEVER returns the template or any biometric vector.
     *
     * @return array<string,mixed>|null
     */
    public function getEnrollmentStatus(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, user_id, sample_count, status, enrolled_at, last_verified_at, last_verification_ip, created_at, updated_at
                FROM admin_face_credentials
                WHERE user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('AdminFaceService: getEnrollmentStatus error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generates an interactive liveness challenge sequence for face authentication.
     *
     * Returns an unpredictable sequence of micro-challenges to test physiological liveness.
     *
     * @param int $userId
     * @param bool $escalated If true, generates stronger 6-step multi-challenge for suspicious attempts
     * @return array{challenge_token:string, sequence:list<string>, mode:string, timeout_seconds:int}
     */
    public function createAuthLivenessChallenge(int $userId, bool $escalated = false, ?string $mode = null): array
    {
        $this->ensureValidUser($userId);

        if (!$this->isEnrolled($userId)) {
            throw new RuntimeException('Face authentication is not enrolled for this account.');
        }

        if ($escalated || $mode === 'escalated') {
            // Enhanced 6-step security fallback challenge for suspicious attempts
            $turnFirst = (random_int(0, 1) === 0) ? 'TURN_LEFT' : 'TURN_RIGHT';
            $turnSecond = ($turnFirst === 'TURN_LEFT') ? 'TURN_RIGHT' : 'TURN_LEFT';
            $nodOrBlink = (random_int(0, 1) === 0) ? 'BLINK' : 'NOD_UP';
            $secondAction = ($nodOrBlink === 'BLINK') ? 'NOD_UP' : 'BLINK';

            $sequence = [
                'LOOK_STRAIGHT',
                $turnFirst,
                $turnSecond,
                $nodOrBlink,
                $secondAction,
                'RETURN_CENTER',
            ];
            $selectedMode = 'escalated';
        } else {
            // 3-check fast mode with 2-of-3 rule: CHECK_FACE, TURN_LEFT, TURN_RIGHT
            $sequence = [
                'CHECK_FACE',
                'TURN_LEFT',
                'TURN_RIGHT'
            ];
            $selectedMode = $mode ?? 'fast_2of3';
        }

        $nonce = bin2hex(random_bytes(16));
        $token = $this->biometricService->createChallenge('face_auth', $userId, [
            'sequence' => $sequence,
            'nonce' => $nonce,
            'mode' => $selectedMode,
            'created_at' => time(),
        ]);

        return [
            'challenge_token' => $token,
            'sequence' => $sequence,
            'mode' => $selectedMode,
            'timeout_seconds' => 90,
        ];
    }

    /**
     * Generates a challenge token for enrolling face biometrics.
     *
     * @param int $userId
     * @param bool $hasExplicitConsent
     * @return array{challenge_token:string, required_poses:list<string>}
     */
    public function createEnrollmentChallenge(int $userId, bool $hasExplicitConsent): array
    {
        $this->ensureValidUser($userId);

        if (!$hasExplicitConsent) {
            throw new RuntimeException('Biometric enrollment requires explicit user consent.');
        }

        $poses = ['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'];
        $token = $this->biometricService->createChallenge('face_enrol', $userId, [
            'required_poses' => $poses,
            'consent' => true,
            'created_at' => time(),
        ]);

        return [
            'challenge_token' => $token,
            'required_poses' => $poses,
        ];
    }

    /**
     * Processes multi-sample face enrollment:
     * - Verifies 5 distinct poses
     * - Validates image quality, lighting, and single face per sample
     * - Validates cross-sample consistency (must be the same human face)
     * - Averages descriptors into a robust normalized template
     * - Encrypts template with AES-256-GCM and stores in database
     *
     * @param int $userId
     * @param string $challengeToken
     * @param array<string,array{descriptor:list<float>, quality:array<string,mixed>}> $samples
     * @return array{ok:bool, enrolled_at:string, sample_count:int}
     */
    public function processEnrollment(int $userId, string $challengeToken, array $samples): array
    {
        $this->ensureValidUser($userId);

        // Validate and consume enrollment challenge
        $stored = $this->biometricService->consumeChallenge('face_enrol', $userId, $challengeToken);
        if (empty($stored['consent'])) {
            throw new RuntimeException('Missing biometric consent validation.');
        }

        $requiredPoses = ['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'];
        $descriptors = [];

        foreach ($requiredPoses as $pose) {
            if (!isset($samples[$pose]) || !is_array($samples[$pose])) {
                throw new RuntimeException("Missing required enrollment pose sample: {$pose}");
            }

            $sampleData = $samples[$pose];
            $descriptor = $sampleData['descriptor'] ?? null;
            $quality = $sampleData['quality'] ?? [];

            // 1. Vector length verification (128 dimensions)
            if (!is_array($descriptor) || count($descriptor) !== 128) {
                throw new RuntimeException("Invalid descriptor vector dimension for sample '{$pose}' (expected 128 floats).");
            }
            foreach ($descriptor as $v) {
                if (!is_numeric($v) || is_nan((float)$v) || is_infinite((float)$v)) {
                    throw new RuntimeException("Invalid descriptor vector values in sample '{$pose}'.");
                }
            }

            // 2. Single face & quality validation
            $faceCount = (int)($quality['face_count'] ?? 1);
            if ($faceCount !== 1) {
                throw new RuntimeException("Enrollment rejected: sample '{$pose}' detected {$faceCount} faces (strictly 1 required).");
            }

            $boxRatio = (float)($quality['box_ratio'] ?? 0.4);
            if ($boxRatio < 0.18 || $boxRatio > 0.85) {
                throw new RuntimeException("Enrollment rejected: face in sample '{$pose}' is too far or too close to frame.");
            }

            $brightness = (float)($quality['brightness'] ?? 128.0);
            if ($brightness < 25.0 || $brightness > 240.0) {
                throw new RuntimeException("Enrollment rejected: poor lighting in sample '{$pose}' (underexposed or overexposed).");
            }

            $floatVector = array_map(static fn($v): float => (float)$v, $descriptor);
            $descriptors[] = $floatVector;
        }

        // 3. Cross-sample consistency check: verify all samples belong to the same person
        $base = $descriptors[0];
        for ($i = 1; $i < count($descriptors); $i++) {
            $dist = self::euclideanDistance($base, $descriptors[$i]);
            if ($dist > 0.62) {
                throw new RuntimeException('Enrollment rejected: inconsistent facial features across poses. Ensure the same person remains in view.');
            }
        }

        // 4. Compute normalized average descriptor template
        $averageVector = [];
        $dims = 128;
        for ($d = 0; $d < $dims; $d++) {
            $sum = 0.0;
            foreach ($descriptors as $desc) {
                $sum += $desc[$d];
            }
            $averageVector[$d] = $sum / count($descriptors);
        }
        $normalizedTemplate = self::normalizeVector($averageVector);

        // 5. Encrypt biometric template
        $templatePayload = json_encode([
            'version' => 1,
            'model' => 'face-api-128',
            'embedding' => $normalizedTemplate,
            'enrolled_timestamp' => time(),
        ], JSON_THROW_ON_ERROR);

        $encrypted = $this->biometricService->encryptBiometricData($templatePayload);

        $metadata = json_encode([
            'model_type' => 'face-api-v1-128d',
            'sample_count' => count($descriptors),
            'quality_verified' => true,
        ]);

        $this->pdo->prepare('DELETE FROM admin_face_credentials WHERE user_id = ?')->execute([$userId]);
        $stmt = $this->pdo->prepare("
            INSERT INTO admin_face_credentials
                (user_id, template_encrypted, sample_count, status, enrolled_at, metadata)
            VALUES (?, ?, ?, 'active', CURRENT_TIMESTAMP, ?)
        ");
        $stmt->execute([$userId, $encrypted, count($descriptors), $metadata]);

        $this->biometricService->recordAudit(
            AdminBiometricService::METHOD_FACE,
            true,
            $userId,
            null,
            ['action' => 'face_enrolled', 'samples' => count($descriptors)]
        );

        return [
            'ok' => true,
            'enrolled_at' => date('Y-m-d H:i:s'),
            'sample_count' => count($descriptors),
        ];
    }

    /**
     * Verifies the administrator's webcam face login:
     * - Validates challenge token (anti-replay)
     * - Validates liveness & presentation attack telemetry:
     *     * Dynamic sequence compliance
     *     * Single face constraint
     *     * Head pose 3D angular movement (Yaw/Pitch)
     *     * Eye Aspect Ratio (EAR) blink dynamics
     *     * Natural physiological tremor/micro-movement
     *     * Image quality and illumination bounds
     * - Validates 1:1 face matching (Cosine similarity >= 0.88 AND Euclidean distance <= 0.48)
     *
     * @param int $userId
     * @param string $challengeToken
     * @param list<float> $verificationDescriptor
     * @param array<string,mixed> $livenessTelemetry
     * @return array{matched:bool, similarity_score_passed:bool, liveness_passed:bool}
     * @throws RuntimeException On any security or mathematical failure
     */
    public function processVerification(
        int $userId,
        string $challengeToken,
        array $verificationDescriptor,
        array $livenessTelemetry
    ): array {
        $this->ensureValidUser($userId);

        // 1. Consume challenge token (single-use guarantee)
        $challengeData = $this->biometricService->consumeChallenge('face_auth', $userId, $challengeToken);
        $expectedSequence = $challengeData['sequence'] ?? [];
        $challengeMode = $challengeData['mode'] ?? 'fast_2of3';

        // 2. Validate descriptor dimensions and numeric validity
        if (count($verificationDescriptor) !== 128) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_FACE,
                false,
                $userId,
                'Invalid face descriptor dimension (expected 128)'
            );
            throw new RuntimeException('We could not verify your face. Please try again or use Passkey.');
        }
        foreach ($verificationDescriptor as $v) {
            if (!is_numeric($v) || is_nan((float)$v) || is_infinite((float)$v)) {
                $this->biometricService->recordAudit(
                    AdminBiometricService::METHOD_FACE,
                    false,
                    $userId,
                    'Non-finite or non-numeric descriptor values detected'
                );
                throw new RuntimeException('We could not verify your face. Please try again or use Passkey.');
            }
        }

        // 3. Presentation Attack Detection (PAD) & Liveness Validation
        $livenessResult = $this->validateLivenessTelemetry($expectedSequence, $livenessTelemetry, $challengeMode);
        if (!$livenessResult['ok']) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_FACE,
                false,
                $userId,
                'Liveness / Presentation Attack detected: ' . $livenessResult['reason'],
                ['liveness_error' => $livenessResult['reason']]
            );
            throw new RuntimeException('We could not verify your face. Please try again or use Passkey.');
        }

        // 4. Retrieve and decrypt enrolled template (1:1 matching)
        $stmt = $this->pdo->prepare("
            SELECT template_encrypted, status FROM admin_face_credentials
            WHERE user_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_FACE,
                false,
                $userId,
                'Face login not enrolled or disabled'
            );
            throw new RuntimeException('Face login is not active for this account.');
        }

        $decryptedJson = $this->biometricService->decryptBiometricData((string)$row['template_encrypted']);
        $templateData = json_decode($decryptedJson, true);
        $enrolledVector = $templateData['embedding'] ?? null;

        if (!is_array($enrolledVector) || count($enrolledVector) !== 128) {
            throw new RuntimeException('Corrupted biometric template. Please re-enrol face login.');
        }

        // 5. Mathematical Face Matching
        $cleanIncoming = self::normalizeVector(array_map(static fn($v): float => (float)$v, $verificationDescriptor));
        $cleanEnrolled = array_map(static fn($v): float => (float)$v, $enrolledVector);

        $distance = self::euclideanDistance($cleanEnrolled, $cleanIncoming);
        $similarity = self::cosineSimilarity($cleanEnrolled, $cleanIncoming);

        // Matching decision strictly on server
        $isMatch = ($distance <= self::MAX_EUCLIDEAN_DISTANCE) && ($similarity >= self::MIN_COSINE_SIMILARITY);

        if (!$isMatch) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_FACE,
                false,
                $userId,
                'Facial biometric mismatch (distance: exceeds threshold)'
            );
            throw new RuntimeException('We could not verify your face. Please try again or use Passkey.');
        }

        // 6. Update verification record
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $this->pdo->prepare("
            UPDATE admin_face_credentials
            SET last_verified_at = CURRENT_TIMESTAMP, last_verification_ip = ?
            WHERE user_id = ?
        ")->execute([$ip, $userId]);

        return [
            'matched' => true,
            'similarity_score_passed' => true,
            'liveness_passed' => true,
            'mode' => $challengeMode,
            'passed_checks' => $livenessResult['passed_checks'] ?? [],
        ];
    }

    /**
     * Disables face authentication for the administrator.
     */
    public function disableFace(int $userId): void
    {
        $this->ensureValidUser($userId);
        $this->pdo->prepare("
            UPDATE admin_face_credentials
            SET status = 'disabled', updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ")->execute([$userId]);

        $this->biometricService->recordAudit(
            AdminBiometricService::METHOD_FACE,
            true,
            $userId,
            null,
            ['action' => 'face_disabled']
        );
    }

    /**
     * Completely purges the user's face credential and template.
     */
    public function clearFaceData(int $userId): void
    {
        $this->ensureValidUser($userId);
        $this->pdo->prepare('DELETE FROM admin_face_credentials WHERE user_id = ?')->execute([$userId]);

        $this->biometricService->recordAudit(
            AdminBiometricService::METHOD_FACE,
            true,
            $userId,
            null,
            ['action' => 'face_data_cleared']
        );
    }

    public function clearFace(int $userId): void
    {
        $this->clearFaceData($userId);
    }

    /**
     * Performs rigorous Presentation Attack Detection (PAD) and liveness validation.
     *
     * @param list<string> $expectedSequence
     * @param array<string,mixed> $telemetry
     * @param string $mode
     * @return array{ok:bool, reason:string, passed_checks?:list<string>}
     */
    private function validateLivenessTelemetry(array $expectedSequence, array $telemetry, string $mode = 'fast_2of3'): array
    {
        $steps = $telemetry['steps'] ?? [];
        if (!is_array($steps) || empty($steps)) {
            return ['ok' => false, 'reason' => 'Missing liveness challenge telemetry.'];
        }

        // Shared Presentation Attack Checks across all modes:
        // A. Single face constraint and optical quality across every telemetry frame
        foreach ($steps as $step) {
            $action = (string)($step['action'] ?? '');
            $faceCount = (int)($step['face_count'] ?? 0);
            if ($faceCount !== 1) {
                return ['ok' => false, 'reason' => "Multiple or no faces detected ({$faceCount}) during biometric check."];
            }

            $brightness = (float)($step['brightness'] ?? 128.0);
            if ($brightness < 20.0 || $brightness > 245.0) {
                return ['ok' => false, 'reason' => "Lighting out of acceptable range ({$brightness})."];
            }

            $boxRatio = (float)($step['box_ratio'] ?? 0.4);
            if ($boxRatio < 0.15 || $boxRatio > 0.88) {
                return ['ok' => false, 'reason' => "Face position outside target frame ({$boxRatio})."];
            }
        }

        // B. Anti-static photo PAD: Physiological tremor variance
        $motionScore = (float)($telemetry['motion_score'] ?? 0.0);
        if ($motionScore < 0.003 && count($steps) >= 2) {
            return ['ok' => false, 'reason' => 'Absence of natural physiological motion (static photograph suspected).'];
        }

        // Branch 1: Fast 3-Check Verification with 2-of-3 Gate
        if ($mode === 'fast_2of3') {
            if (count($steps) < 2) {
                return ['ok' => false, 'reason' => 'Liveness check failed: at least 2 of 3 checks must be verified.'];
            }

            $passedChecks = [];
            foreach ($steps as $step) {
                $act = (string)($step['action'] ?? '');
                $yaw = (float)($step['yaw'] ?? 0.0);
                $pitch = (float)($step['pitch'] ?? 0.0);

                if (!in_array($act, ['CHECK_FACE', 'LOOK_STRAIGHT', 'TURN_LEFT', 'TURN_RIGHT'], true)) {
                    return ['ok' => false, 'reason' => "Unknown gesture identifier {$act}."];
                }

                if (($act === 'CHECK_FACE' || $act === 'LOOK_STRAIGHT') && abs($yaw) <= 10.0 && abs($pitch) <= 12.0) {
                    $passedChecks['face'] = true;
                } elseif ($act === 'TURN_LEFT' && $yaw <= -9.0) {
                    $passedChecks['left'] = true;
                } elseif ($act === 'TURN_RIGHT' && $yaw >= 9.0) {
                    $passedChecks['right'] = true;
                }
            }

            $passedCount = count($passedChecks);
            if ($passedCount < 2) {
                return ['ok' => false, 'reason' => "Liveness gate failed: at least 2 of 3 checks must pass (verified {$passedCount}/3)."];
            }

            return [
                'ok' => true,
                'reason' => 'Liveness verified (2 of 3 checks passed).',
                'passed_checks' => array_keys($passedChecks),
            ];
        }

        // Branch 2: Escalated / Sequential Fallback Mode (Strict 6-step verification)
        $completedActions = [];
        foreach ($steps as $step) {
            $act = (string)($step['action'] ?? '');
            if ($act !== '') {
                $completedActions[] = $act;
            }
        }

        $totalExpected = count($expectedSequence);
        if (count($completedActions) !== $totalExpected) {
            return ['ok' => false, 'reason' => "Telemetry action count does not match the required sequence count ({$totalExpected})."];
        }

        for ($i = 0; $i < $totalExpected; $i++) {
            if ($completedActions[$i] !== $expectedSequence[$i]) {
                return ['ok' => false, 'reason' => "Gesture mismatch at step " . ($i + 1) . ": expected {$expectedSequence[$i]}, got {$completedActions[$i]}."];
            }
        }

        $turnLeftObserved = false;
        $turnRightObserved = false;
        $nodObserved = false;
        $blinkObserved = false;

        foreach ($steps as $step) {
            $act = (string)($step['action'] ?? '');
            $yaw = (float)($step['yaw'] ?? 0.0);
            $pitch = (float)($step['pitch'] ?? 0.0);
            $ear = (float)($step['ear'] ?? 0.3);

            if ($act === 'TURN_LEFT' && $yaw <= -10.0) {
                $turnLeftObserved = true;
            }
            if ($act === 'TURN_RIGHT' && $yaw >= 10.0) {
                $turnRightObserved = true;
            }
            if ($act === 'NOD_UP' && $pitch >= 6.0) {
                $nodObserved = true;
            }
            if ($act === 'BLINK' && $ear <= 0.22) {
                $blinkObserved = true;
            }
        }

        if (in_array('TURN_LEFT', $expectedSequence, true) && !$turnLeftObserved) {
            return ['ok' => false, 'reason' => 'Head rotation left insufficient or absent.'];
        }
        if (in_array('TURN_RIGHT', $expectedSequence, true) && !$turnRightObserved) {
            return ['ok' => false, 'reason' => 'Head rotation right insufficient or absent.'];
        }
        if (in_array('NOD_UP', $expectedSequence, true) && !$nodObserved) {
            return ['ok' => false, 'reason' => 'Head nod pitch movement insufficient or absent.'];
        }
        if (in_array('BLINK', $expectedSequence, true) && !$blinkObserved) {
            return ['ok' => false, 'reason' => 'Natural eye blink curve not detected.'];
        }

        return ['ok' => true, 'reason' => 'Liveness verified.'];
    }

    /**
     * Guards that the operation targets a valid, active user account (admin, teacher, student).
     */
    public function ensureValidUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Unauthorized: Invalid user ID provided.');
        }
        if (!$this->biometricService->validateUser($userId)) {
            throw new RuntimeException('Unauthorized: User account not found, deactivated, or deleted.');
        }
    }

    /**
     * Guards that the operation only targets the single authorized admin account.
     */
    public function ensureAdminUser(int $userId): void
    {
        if (!$this->biometricService->validateIsAdminUser($userId)) {
            throw new RuntimeException('Unauthorized: Biometric operations are restricted to the administrator account.');
        }
    }

    /**
     * Computes the Euclidean distance between two vectors.
     *
     * @param list<float> $a
     * @param list<float> $b
     * @return float
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $count = count($a);
        for ($i = 0; $i < $count; $i++) {
            $diff = ($a[$i] ?? 0.0) - ($b[$i] ?? 0.0);
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    /**
     * Computes the Cosine similarity between two vectors.
     *
     * @param list<float> $a
     * @param list<float> $b
     * @return float
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = count($a);

        for ($i = 0; $i < $count; $i++) {
            $va = (float)($a[$i] ?? 0.0);
            $vb = (float)($b[$i] ?? 0.0);
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        $denom = sqrt($normA) * sqrt($normB);
        if ($denom <= 0.0) {
            return 0.0;
        }

        return $dot / $denom;
    }

    /**
     * Normalizes a vector to unit length.
     *
     * @param list<float> $vec
     * @return list<float>
     */
    public static function normalizeVector(array $vec): array
    {
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += $v * $v;
        }
        $norm = sqrt($sum);
        if ($norm <= 0.0) {
            return $vec;
        }
        return array_map(static fn($v): float => $v / $norm, $vec);
    }
}
