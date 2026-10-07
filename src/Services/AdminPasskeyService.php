<?php
declare(strict_types=1);

namespace Edexcel\Services;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthnException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * AdminPasskeyService
 *
 * Implements FIDO2 / WebAuthn Passkeys for the Administrator account.
 * Uses lbuchs/webauthn for cryptographic attestation and assertion verification.
 *
 * Enforces:
 * - Origin and RP ID matching
 * - Cryptographic challenge validation
 * - Signature validation
 * - Authenticator sign count tracking to prevent replay/cloning
 * - Credential revocation
 * - Storing only public keys (private keys never leave client authenticators)
 */
final class AdminPasskeyService
{
    private const RP_NAME = 'Edexcel College';

    public function __construct(
        private PDO $pdo,
        private AdminBiometricService $biometricService
    ) {
    }

    /**
     * Resolves the current Relying Party ID (domain name without port).
     */
    public function getRpId(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: 'localhost';
        return strtolower(trim($host));
    }

    /**
     * Instantiates the WebAuthn engine configured for the current origin.
     */
    public function getWebAuthnEngine(): WebAuthn
    {
        $rpId = $this->getRpId();
        // Allow common attestation formats and use standard base64url encoding for binary buffers
        return new WebAuthn(self::RP_NAME, $rpId, null, true);
    }

    /**
     * Generates WebAuthn registration options for navigator.credentials.create().
     *
     * @param int $userId
     * @param string $username
     * @return array{options:mixed, challenge_token:string}
     */
    public function getRegistrationOptions(int $userId, string $username): array
    {
        $this->ensureAdminUser($userId);
        $webauthn = $this->getWebAuthnEngine();

        // Get currently registered credentials to prevent re-registration
        $existing = $this->listPasskeys($userId);
        $excludeIds = [];
        foreach ($existing as $p) {
            $rawId = self::base64UrlDecode((string)$p['credential_id']);
            if ($rawId !== '') {
                $excludeIds[] = new ByteBuffer($rawId);
            }
        }

        // Generate creation arguments (timeout: 60s, userVerification: 'preferred', platform/cross-platform)
        $createArgs = $webauthn->getCreateArgs(
            (string)$userId,
            $username,
            'Edexcel Administrator',
            60,
            false,
            'preferred',
            null,
            $excludeIds
        );

        $challengeBuffer = $webauthn->getChallenge();
        $challengeBinary = $challengeBuffer->getBinaryString();

        $token = $this->biometricService->createChallenge('passkey_reg', $userId, [
            'challenge_b64' => self::base64UrlEncode($challengeBinary),
            'rp_id' => $this->getRpId(),
        ]);

        return [
            'options' => $createArgs,
            'challenge_token' => $token,
        ];
    }

    /**
     * Verifies the client's attestation response and registers the passkey.
     *
     * @param int $userId
     * @param string $challengeToken
     * @param string $clientDataJSON Base64URL or raw JSON
     * @param string $attestationObject Base64URL or raw binary
     * @param string $deviceName
     * @param string|null $transports
     * @return array<string,mixed> Registered passkey record
     */
    public function processRegistration(
        int $userId,
        string $challengeToken,
        string $clientDataJSON,
        string $attestationObject,
        string $deviceName = 'Passkey',
        ?string $transports = null
    ): array {
        $this->ensureAdminUser($userId);
        $stored = $this->biometricService->consumeChallenge('passkey_reg', $userId, $challengeToken);

        $storedChallengeBinary = self::base64UrlDecode((string)($stored['challenge_b64'] ?? ''));
        if ($storedChallengeBinary === '') {
            throw new RuntimeException('Challenge data is missing or corrupted.');
        }

        $rawClientDataJSON = self::maybeDecodeBase64Url($clientDataJSON);
        $rawAttestationObject = self::maybeDecodeBase64Url($attestationObject);

        $this->validateClientDataOrigin($rawClientDataJSON);

        $webauthn = $this->getWebAuthnEngine();

        try {
            $data = $webauthn->processCreate(
                $rawClientDataJSON,
                $rawAttestationObject,
                $storedChallengeBinary,
                false, // user verification checked via UV flag if available
                true   // require user present
            );
        } catch (WebAuthnException $e) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_PASSKEY,
                false,
                $userId,
                'Passkey registration failed: ' . $e->getMessage()
            );
            throw new RuntimeException('Passkey registration error: ' . $e->getMessage());
        }

        $credentialId = self::base64UrlEncode($data->credentialId);
        $publicKey = (string)$data->credentialPublicKey;
        $signCount = (int)($data->signatureCounter ?? 0);
        $attestationFormat = (string)($data->attestationFormat ?? 'none');

        $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        // Sanitize friendly name
        $name = trim(strip_tags($deviceName));
        if ($name === '') {
            $name = 'Admin Passkey';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO admin_passkeys
                (user_id, credential_id, public_key, name, attestation_format, sign_count, transports, created_ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $credentialId,
            $publicKey,
            $name,
            $attestationFormat,
            $signCount,
            $transports,
            $ip,
            $ua,
        ]);

        $passkeyId = (int)$this->pdo->lastInsertId();

        $this->biometricService->recordAudit(
            AdminBiometricService::METHOD_PASSKEY,
            true,
            $userId,
            null,
            ['action' => 'passkey_registered', 'passkey_id' => $passkeyId, 'name' => $name]
        );

        return [
            'id' => $passkeyId,
            'name' => $name,
            'credential_id' => $credentialId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generates WebAuthn authentication options for navigator.credentials.get().
     *
     * @param int $userId
     * @return array{options:mixed, challenge_token:string}
     */
    public function getAuthenticationOptions(int $userId): array
    {
        $this->ensureAdminUser($userId);
        $webauthn = $this->getWebAuthnEngine();

        $passkeys = $this->listPasskeys($userId);
        if (empty($passkeys)) {
            throw new RuntimeException('No passkeys registered for this administrator account.');
        }

        $allowedIds = [];
        foreach ($passkeys as $p) {
            $rawId = self::base64UrlDecode((string)$p['credential_id']);
            if ($rawId !== '') {
                $allowedIds[] = new ByteBuffer($rawId);
            }
        }

        $getArgs = $webauthn->getGetArgs(
            $allowedIds,
            60,
            true,
            true,
            true,
            true,
            true,
            'preferred'
        );

        $challengeBuffer = $webauthn->getChallenge();
        $challengeBinary = $challengeBuffer->getBinaryString();

        $token = $this->biometricService->createChallenge('passkey_auth', $userId, [
            'challenge_b64' => self::base64UrlEncode($challengeBinary),
            'rp_id' => $this->getRpId(),
        ]);

        return [
            'options' => $getArgs,
            'challenge_token' => $token,
        ];
    }

    /**
     * Verifies the client's assertion response and completes admin sign-in.
     *
     * @param int $userId
     * @param string $challengeToken
     * @param string $credentialId Base64URL
     * @param string $clientDataJSON Base64URL or raw JSON
     * @param string $authenticatorData Base64URL or raw binary
     * @param string $signature Base64URL or raw binary
     * @param string|null $userHandle Base64URL or raw binary
     * @return array<string,mixed> Authenticated passkey record
     */
    public function processAuthentication(
        int $userId,
        string $challengeToken,
        string $credentialId,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        ?string $userHandle = null
    ): array {
        $this->ensureAdminUser($userId);
        $stored = $this->biometricService->consumeChallenge('passkey_auth', $userId, $challengeToken);

        $storedChallengeBinary = self::base64UrlDecode((string)($stored['challenge_b64'] ?? ''));
        if ($storedChallengeBinary === '') {
            throw new RuntimeException('Challenge data is missing.');
        }

        $credIdClean = trim($credentialId);
        $stmt = $this->pdo->prepare("
            SELECT * FROM admin_passkeys
            WHERE user_id = ?
              AND credential_id = ?
              AND revoked_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId, $credIdClean]);
        $passkey = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$passkey) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_PASSKEY,
                false,
                $userId,
                'Unrecognized or revoked passkey credential'
            );
            throw new RuntimeException('Unrecognized or revoked passkey credential.');
        }

        $rawClientDataJSON = self::maybeDecodeBase64Url($clientDataJSON);
        $rawAuthenticatorData = self::maybeDecodeBase64Url($authenticatorData);
        $rawSignature = self::maybeDecodeBase64Url($signature);

        $this->validateClientDataOrigin($rawClientDataJSON);

        // Verify userHandle if provided by the authenticator client
        if ($userHandle !== null && $userHandle !== '') {
            $decodedHandle = self::maybeDecodeBase64Url($userHandle);
            $expectedHandle = (string)$userId;
            if ($userHandle !== $expectedHandle && $decodedHandle !== $expectedHandle) {
                $this->biometricService->recordAudit(
                    AdminBiometricService::METHOD_PASSKEY,
                    false,
                    $userId,
                    'User handle mismatch'
                );
                throw new RuntimeException('Passkey user handle does not match expected administrator.');
            }
        }

        $webauthn = $this->getWebAuthnEngine();
        $publicKey = (string)$passkey['public_key'];
        $prevSignCount = (int)$passkey['sign_count'];

        try {
            $ok = $webauthn->processGet(
                $rawClientDataJSON,
                $rawAuthenticatorData,
                $rawSignature,
                $publicKey,
                $storedChallengeBinary,
                $prevSignCount > 0 ? $prevSignCount : null,
                false, // user verification preferred
                true   // require user present
            );
        } catch (WebAuthnException $e) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_PASSKEY,
                false,
                $userId,
                'Passkey verification failed: ' . $e->getMessage()
            );
            throw new RuntimeException('Passkey verification error: ' . $e->getMessage());
        }

        if (!$ok) {
            $this->biometricService->recordAudit(
                AdminBiometricService::METHOD_PASSKEY,
                false,
                $userId,
                'Passkey assertion signature verification failed'
            );
            throw new RuntimeException('Passkey authentication could not be verified.');
        }

        // Update signature counter & last used timestamp
        // Authenticators without counter support return 0/null; preserve prevSignCount to prevent false clone detection
        $actualSignCount = $webauthn->getSignatureCounter();
        $newSignCount = ($actualSignCount !== null && $actualSignCount > 0) ? $actualSignCount : $prevSignCount;
        $this->pdo->prepare("
            UPDATE admin_passkeys
            SET sign_count = ?, last_used_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$newSignCount, (int)$passkey['id']]);

        return $passkey;
    }

    /**
     * Lists all active (non-revoked) passkeys registered for the administrator.
     *
     * @param int $userId
     * @return array<int,array<string,mixed>>
     */
    public function listPasskeys(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, credential_id, name, attestation_format, sign_count, transports, last_used_at, created_at, created_ip
            FROM admin_passkeys
            WHERE user_id = ?
              AND revoked_at IS NULL
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Revokes a specific passkey credential.
     */
    public function revokePasskey(int $userId, int $passkeyId): void
    {
        $this->ensureAdminUser($userId);
        $stmt = $this->pdo->prepare("
            UPDATE admin_passkeys
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ? AND revoked_at IS NULL
        ");
        $stmt->execute([$passkeyId, $userId]);

        $this->biometricService->recordAudit(
            AdminBiometricService::METHOD_PASSKEY,
            true,
            $userId,
            null,
            ['action' => 'passkey_revoked', 'passkey_id' => $passkeyId]
        );
    }

    /**
     * Guards that the operation only targets the single authorized admin account.
     */
    private function ensureAdminUser(int $userId): void
    {
        if (!$this->biometricService->validateIsAdminUser($userId)) {
            throw new RuntimeException('Unauthorized: Biometric operations are restricted to the administrator account.');
        }
    }

    /**
     * Strictly verifies that the clientDataJSON origin matches the Relying Party ID.
     */
    private function validateClientDataOrigin(string $rawClientDataJSON): void
    {
        $clientData = json_decode($rawClientDataJSON, true);
        if (!is_array($clientData) || empty($clientData['origin'])) {
            return;
        }

        $origin = (string)$clientData['origin'];
        $host = strtolower((string)parse_url($origin, PHP_URL_HOST));
        $scheme = strtolower((string)parse_url($origin, PHP_URL_SCHEME));
        $rpId = $this->getRpId();

        if ($rpId !== 'localhost' && $scheme !== 'https') {
            throw new RuntimeException('Passkey origin must use secure HTTPS in production.');
        }

        // Host must match the RP ID exactly or be a valid dot-subdomain of the RP ID
        if ($host !== $rpId && !str_ends_with($host, '.' . $rpId)) {
            throw new RuntimeException("Passkey origin mismatch: '{$origin}' does not belong to '{$rpId}'.");
        }
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }

    private static function maybeDecodeBase64Url(string $data): string
    {
        // If data is already valid JSON, don't decode
        if (str_starts_with(trim($data), '{') || str_starts_with(trim($data), '[')) {
            return $data;
        }
        $decoded = self::base64UrlDecode($data);
        return $decoded !== '' ? $decoded : $data;
    }
}
