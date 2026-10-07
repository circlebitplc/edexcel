<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Completes Meta Embedded Signup after FB.login returns a code.
 * Skips /register when the number is already on Cloud API or the WhatsApp Business app.
 */
final class MetaEmbeddedSignupService
{
    private PDO $pdo;
    private string $graphVersion;
    private string $appId;
    private string $appSecret;
    /** @var list<string> */
    private array $discoverNotes = [];

    public function __construct(PDO $pdo)
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';
        require_once dirname(__DIR__, 2) . '/config/whatsapp_gateway.php';

        $this->pdo = $pdo;
        $creds = meta_cloud_credentials($pdo);
        $this->graphVersion = $creds['graph_version'] !== '' ? $creds['graph_version'] : 'v21.0';
        $this->appId = $creds['app_id'];
        $this->appSecret = self::normalizeSecret($creds['app_secret']);
    }

    /**
     * @return array{
     *   ready:bool,
     *   connected:bool,
     *   provider:string,
     *   has_app_id:bool,
     *   has_app_secret:bool,
     *   has_config_id:bool,
     *   app_id:string,
     *   config_id:string,
     *   graph_version:string,
     *   waba_id:string,
     *   phone_number_id:string,
     *   display_phone:string,
     *   onboarding_mode:string,
     *   connected_at:string,
     *   is_on_biz_app:string,
     *   platform_type:string,
     *   webhook_url:string,
     *   verify_token:string,
     *   app_secret_length:int
     * }
     */
    public static function publicStatus(?PDO $pdo): array
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';
        require_once dirname(__DIR__, 2) . '/config/whatsapp_gateway.php';

        $creds = meta_cloud_credentials($pdo);
        $connected = $creds['token'] !== '' && $creds['phone_number_id'] !== '';
        $isOnBiz = evolution_setting($pdo, 'meta_is_on_biz_app');
        $platform = evolution_setting($pdo, 'meta_platform_type');
        $phoneStatus = trim(evolution_setting($pdo, 'meta_phone_status'));
        $codeVerification = trim(evolution_setting($pdo, 'meta_code_verification_status'));
        $displayPhone = $creds['display_phone'];

        if ($connected) {
            try {
                $live = (new self($pdo))->livePhoneStatus();
                if ($live !== []) {
                    $isOnBiz = !empty($live['is_on_biz_app']) ? '1' : '0';
                    $platform = (string)($live['platform_type'] ?? $platform);
                    $phoneStatus = (string)($live['status'] ?? $phoneStatus);
                    $codeVerification = (string)($live['code_verification_status'] ?? $codeVerification);
                    if (trim((string)($live['display_phone_number'] ?? '')) !== '') {
                        $displayPhone = (string)$live['display_phone_number'];
                    }
                }
            } catch (Throwable $e) {
                // Keep the last saved phone fields if Graph is unreachable.
            }
        }

        $registered = self::phoneLooksRegistered([
            'is_on_biz_app' => $isOnBiz === '1',
            'platform_type' => $platform,
            'status' => $phoneStatus,
        ]);
        $blockedUntil = (int)evolution_setting($pdo, 'meta_register_blocked_until');
        $pinBlocked = $blockedUntil > time();
        $qrOnly = $pinBlocked
            || strtoupper($platform) === 'NOT_APPLICABLE'
            || strtoupper($codeVerification) === 'NOT_VERIFIED';

        return [
            'ready' => $creds['app_id'] !== '' && $creds['app_secret'] !== '' && $creds['config_id'] !== '',
            'connected' => $connected,
            'registered' => $registered,
            'needs_register' => $connected && !$registered,
            'qr_only' => $connected && $qrOnly,
            'pin_blocked' => $pinBlocked,
            'pin_blocked_until' => $pinBlocked ? gmdate('c', $blockedUntil) : '',
            'provider' => whatsapp_provider($pdo),
            'has_app_id' => $creds['app_id'] !== '',
            'has_app_secret' => $creds['app_secret'] !== '',
            'has_config_id' => $creds['config_id'] !== '',
            'app_id' => $creds['app_id'],
            'config_id' => $creds['config_id'],
            'graph_version' => $creds['graph_version'],
            'waba_id' => $creds['waba_id'],
            'phone_number_id' => $creds['phone_number_id'],
            'display_phone' => $displayPhone,
            'onboarding_mode' => $creds['onboarding_mode'],
            'connected_at' => $creds['connected_at'],
            'is_on_biz_app' => $isOnBiz,
            'platform_type' => $platform,
            'phone_status' => $phoneStatus,
            'code_verification_status' => $codeVerification,
            'webhook_url' => rtrim(edexcel_public_app_url(), '/') . '/api/whatsapp/webhook.php',
            'verify_token' => $creds['verify_token'],
            'app_secret_length' => strlen($creds['app_secret']),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function complete(string $code, string $wabaId = '', string $phoneNumberId = '', string $redirectUri = '', string $businessId = '', string $pin = ''): array
    {
        $code = trim($code);
        $wabaId = preg_replace('/\D+/', '', trim($wabaId)) ?? '';
        $phoneNumberId = preg_replace('/\D+/', '', trim($phoneNumberId)) ?? '';
        $businessId = preg_replace('/\D+/', '', trim($businessId)) ?? '';
        $pin = preg_replace('/\D+/', '', trim($pin)) ?? '';

        if ($code === '') {
            throw new RuntimeException('Meta did not return an authorization code. Close the popup and try Connect WhatsApp again.');
        }
        if ($this->appId === '' || $this->appSecret === '') {
            throw new RuntimeException('Save the Meta App ID and App Secret first.');
        }

        $this->discoverNotes = [];
        $token = $this->exchangeCode($code, $redirectUri);
        if ($wabaId === '' && $phoneNumberId !== '') {
            if ($this->nodeIsWaba($phoneNumberId, $token)) {
                $wabaId = $phoneNumberId;
                $phoneNumberId = '';
            } else {
                $wabaId = $this->discoverWabaFromPhone($phoneNumberId, $token);
            }
        }
        if ($wabaId === '') {
            $wabaId = $this->discoverWabaId($token, $businessId);
        }

        $phones = $this->listPhoneNumbers($wabaId, $token);
        $chosen = $this->choosePhone($phones, $phoneNumberId);
        $phoneNumberId = (string)($chosen['id'] ?? '');
        if ($phoneNumberId === '') {
            throw new RuntimeException('No WhatsApp phone number was found on that business account.');
        }

        $verifyToken = $this->ensureVerifyToken();
        $webhookUrl = rtrim(edexcel_public_app_url(), '/') . '/api/whatsapp/webhook.php';

        $this->subscribeApp($wabaId, $token);
        $overrideOk = $this->tryOverrideWebhook($wabaId, $token, $webhookUrl, $verifyToken);

        $status = $this->phoneStatus($phoneNumberId, $token);
        if (!self::phoneLooksRegistered($status) && $pin !== '' && self::canPinRegister($status)) {
            $this->registerNumber($phoneNumberId, $token, $pin);
            $status = $this->phoneStatus($phoneNumberId, $token);
        }

        $sync = ['contacts' => false, 'history' => false, 'contacts_request_id' => '', 'history_request_id' => ''];
        if (self::phoneLooksRegistered($status)) {
            $sync = $this->trySmbSync($phoneNumberId, $token);
        }

        $display = (string)($status['display_phone_number'] ?? $chosen['display_phone_number'] ?? '');
        $coexistence = !empty($status['is_on_biz_app'])
            && strtoupper((string)($status['platform_type'] ?? '')) === 'CLOUD_API';
        $this->saveConnection([
            'meta_access_token' => $token,
            'meta_waba_id' => $wabaId,
            'meta_phone_number_id' => $phoneNumberId,
            'meta_display_phone_number' => $display,
            'meta_onboarding_mode' => $coexistence ? 'coexistence' : 'cloud',
            'meta_connected_at' => gmdate('c'),
            'meta_is_on_biz_app' => !empty($status['is_on_biz_app']) ? '1' : '0',
            'meta_platform_type' => (string)($status['platform_type'] ?? ''),
            'meta_phone_status' => (string)($status['status'] ?? ''),
            'meta_code_verification_status' => (string)($status['code_verification_status'] ?? ''),
            'meta_smb_sync_request_id' => (string)($sync['contacts_request_id'] ?? ''),
            'meta_history_sync_request_id' => (string)($sync['history_request_id'] ?? ''),
            'whatsapp_provider' => 'meta',
            'whatsapp_enabled' => '1',
            'meta_webhook_verify_token' => $verifyToken,
        ]);

        return [
            'ok' => true,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'display_phone' => $display,
            'is_on_biz_app' => !empty($status['is_on_biz_app']),
            'platform_type' => (string)($status['platform_type'] ?? ''),
            'phone_status' => (string)($status['status'] ?? ''),
            'registered' => self::phoneLooksRegistered($status),
            'needs_register' => !self::phoneLooksRegistered($status),
            'webhook_overridden' => $overrideOk,
            'contacts_sync' => $sync['contacts'] ?? false,
            'history_sync' => $sync['history'] ?? false,
        ];
    }

    /**
     * Registers a saved Cloud API phone number with the WhatsApp two-step PIN.
     *
     * @return array<string,mixed>
     */
    public function registerSavedNumber(string $pin): array
    {
        $creds = meta_cloud_credentials($this->pdo);
        if ($creds['token'] === '' || $creds['phone_number_id'] === '') {
            throw new RuntimeException('Connect WhatsApp first, then activate the number.');
        }

        $blockedUntil = (int)evolution_setting($this->pdo, 'meta_register_blocked_until');
        if ($blockedUntil > time()) {
            throw new RuntimeException(
                'Meta blocked PIN registration for this number until '
                . gmdate('j M H:i', $blockedUntil) . ' UTC. Do not click Activate Cloud API. Click Connect WhatsApp and finish QR pairing instead.'
            );
        }

        $live = $this->phoneStatus($creds['phone_number_id'], $creds['token']);
        if (!self::canPinRegister($live)) {
            throw new RuntimeException(
                'This number is still on the WhatsApp Business app (status '
                . strtoupper((string)($live['status'] ?? 'PENDING'))
                . ', platform ' . strtoupper((string)($live['platform_type'] ?? 'NOT_APPLICABLE'))
                . '). Meta will not accept a PIN. Click Connect WhatsApp, choose the existing Business app, and finish QR pairing on the college phone.'
            );
        }

        $this->registerNumber($creds['phone_number_id'], $creds['token'], $pin);
        $status = $this->phoneStatus($creds['phone_number_id'], $creds['token']);
        $sync = self::phoneLooksRegistered($status)
            ? $this->trySmbSync($creds['phone_number_id'], $creds['token'])
            : ['contacts' => false, 'history' => false, 'contacts_request_id' => '', 'history_request_id' => ''];

        $this->saveConnection([
            'meta_display_phone_number' => (string)($status['display_phone_number'] ?? $creds['display_phone']),
            'meta_is_on_biz_app' => !empty($status['is_on_biz_app']) ? '1' : '0',
            'meta_platform_type' => (string)($status['platform_type'] ?? ''),
            'meta_phone_status' => (string)($status['status'] ?? ''),
            'meta_code_verification_status' => (string)($status['code_verification_status'] ?? ''),
            'meta_onboarding_mode' => (!empty($status['is_on_biz_app'])
                && strtoupper((string)($status['platform_type'] ?? '')) === 'CLOUD_API')
                ? 'coexistence'
                : 'cloud',
            'meta_smb_sync_request_id' => (string)($sync['contacts_request_id'] ?? ''),
            'meta_history_sync_request_id' => (string)($sync['history_request_id'] ?? ''),
        ]);

        if (!self::phoneLooksRegistered($status)) {
            throw new RuntimeException(
                'Meta accepted the PIN request, but the number is still not registered. Finish QR pairing in Connect WhatsApp, or check two-step verification in the WhatsApp Business app.'
            );
        }

        return [
            'ok' => true,
            'registered' => true,
            'display_phone' => (string)($status['display_phone_number'] ?? ''),
            'platform_type' => (string)($status['platform_type'] ?? ''),
            'phone_status' => (string)($status['status'] ?? ''),
            'is_on_biz_app' => !empty($status['is_on_biz_app']),
        ];
    }

    /**
     * Saves a token copied from Meta WhatsApp API Setup or a system user.
     *
     * @return array<string,mixed>
     */
    public function applyManualToken(string $token, string $wabaId = '', string $phoneNumberId = ''): array
    {
        $token = preg_replace('/\s+/', '', trim($token, " \t\n\r\0\x0B\"'")) ?? '';
        $wabaId = preg_replace('/\D+/', '', trim($wabaId)) ?? '';
        $phoneNumberId = preg_replace('/\D+/', '', trim($phoneNumberId)) ?? '';
        $creds = meta_cloud_credentials($this->pdo);
        if ($wabaId === '') {
            $wabaId = $creds['waba_id'];
        }
        if ($phoneNumberId === '') {
            $phoneNumberId = $creds['phone_number_id'];
        }
        if ($token === '' || strlen($token) < 40) {
            throw new RuntimeException(
                'Paste the access token from Meta for Developers → WhatsApp → API Setup (Generate access token), not the App Secret.'
            );
        }
        if ($this->appSecret !== '' && hash_equals($this->appSecret, $token)) {
            throw new RuntimeException('That value is the App Secret. Paste the WhatsApp access token from API Setup instead.');
        }
        if ($wabaId === '') {
            $wabaId = $this->discoverWabaId($token, '');
        }
        if ($phoneNumberId === '' && $wabaId !== '') {
            $phones = $this->listPhoneNumbers($wabaId, $token);
            $chosen = $this->choosePhone($phones, '');
            $phoneNumberId = (string)($chosen['id'] ?? '');
        }
        if ($phoneNumberId === '') {
            throw new RuntimeException(
                'Paste the WABA ID and Phone number ID from WhatsApp → API Setup together with the token.'
            );
        }

        $status = $this->phoneStatus($phoneNumberId, $token);
        if ($status === []) {
            throw new RuntimeException(
                'Meta did not accept that token for that phone number ID. In API Setup, select the WABA on this new app, generate a new token, copy the WABA ID and Phone number ID into Connect WhatsApp, and paste the token again.'
            );
        }

        $verifyToken = $this->ensureVerifyToken();
        $webhookUrl = rtrim(edexcel_public_app_url(), '/') . '/api/whatsapp/webhook.php';
        try {
            $this->subscribeApp($wabaId, $token);
        } catch (Throwable $e) {
            // Token may still send if the app is already subscribed in the dashboard.
        }
        $this->tryOverrideWebhook($wabaId, $token, $webhookUrl, $verifyToken);

        $this->saveConnection([
            'meta_access_token' => $token,
            'meta_waba_id' => $wabaId,
            'meta_phone_number_id' => $phoneNumberId,
            'meta_display_phone_number' => (string)($status['display_phone_number'] ?? $creds['display_phone']),
            'meta_is_on_biz_app' => !empty($status['is_on_biz_app']) ? '1' : '0',
            'meta_platform_type' => (string)($status['platform_type'] ?? ''),
            'meta_phone_status' => (string)($status['status'] ?? ''),
            'meta_code_verification_status' => (string)($status['code_verification_status'] ?? ''),
            'meta_onboarding_mode' => (!empty($status['is_on_biz_app'])
                && strtoupper((string)($status['platform_type'] ?? '')) === 'CLOUD_API')
                ? 'coexistence'
                : 'cloud',
            'whatsapp_provider' => 'meta',
            'whatsapp_enabled' => '1',
            'meta_webhook_verify_token' => $verifyToken,
            'meta_connected_at' => gmdate('c'),
            'meta_register_blocked_until' => '',
        ]);

        return [
            'ok' => true,
            'registered' => self::phoneLooksRegistered($status),
            'display_phone' => (string)($status['display_phone_number'] ?? ''),
            'phone_status' => (string)($status['status'] ?? ''),
            'platform_type' => (string)($status['platform_type'] ?? ''),
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ];
    }

    /**
     * Points the saved Cloud API connection at a specific WABA and phone number.
     *
     * @return array<string,mixed>
     */
    public function usePhone(string $wabaId, string $phoneNumberId): array
    {
        $creds = meta_cloud_credentials($this->pdo);
        if ($creds['token'] === '') {
            throw new RuntimeException(
                'Paste the access token from WhatsApp → API Setup first, then this registered number can be saved.'
            );
        }

        return $this->applyManualToken($creds['token'], $wabaId, $phoneNumberId);
    }

    /**
     * @return array<string,mixed>
     */
    public function livePhoneStatus(): array
    {
        $creds = meta_cloud_credentials($this->pdo);
        if ($creds['token'] === '' || $creds['phone_number_id'] === '') {
            return [];
        }

        return $this->phoneStatus($creds['phone_number_id'], $creds['token']);
    }

    /**
     * @param array<string,mixed> $status
     */
    private static function phoneLooksRegistered(array $status): bool
    {
        $platform = strtoupper((string)($status['platform_type'] ?? ''));
        $state = strtoupper((string)($status['status'] ?? ''));

        return $platform === 'CLOUD_API'
            || $state === 'CONNECTED'
            || $state === 'REGISTERED';
    }

    private static function canPinRegister(array $status): bool
    {
        $platform = strtoupper((string)($status['platform_type'] ?? ''));
        $verify = strtoupper((string)($status['code_verification_status'] ?? ''));

        return $platform !== 'NOT_APPLICABLE' && $verify !== 'NOT_VERIFIED';
    }

    private function registerNumber(string $phoneNumberId, string $token, string $pin): void
    {
        $pin = preg_replace('/\D+/', '', $pin) ?? '';
        if (!preg_match('/^\d{6}$/', $pin)) {
            throw new RuntimeException(
                'Enter the 6-digit two-step PIN from WhatsApp Business → Settings → Account → Two-step verification. If two-step is off, choose any 6 digits and use them here.'
            );
        }

        $status = $this->phoneStatus($phoneNumberId, $token);
        $state = strtoupper((string)($status['status'] ?? ''));
        $verify = strtoupper((string)($status['code_verification_status'] ?? ''));
        $platform = strtoupper((string)($status['platform_type'] ?? ''));
        $context = trim(implode(', ', array_filter([
            $state !== '' ? 'status ' . $state : '',
            $verify !== '' ? 'verification ' . $verify : '',
            $platform !== '' ? 'platform ' . $platform : '',
            !empty($status['is_on_biz_app']) ? 'WhatsApp Business app' : '',
        ])));

        if (!self::canPinRegister($status)) {
            throw new RuntimeException($this->friendlyRegisterError('not verified for Cloud API PIN register', $context));
        }

        try {
            $this->request(
                'POST',
                '/' . rawurlencode($phoneNumberId) . '/register',
                [
                    'messaging_product' => 'whatsapp',
                    'pin' => $pin,
                ],
                $token,
                false
            );
        } catch (RuntimeException $e) {
            $last = $e->getMessage();
            if (str_contains(strtolower($last), '133016') || str_contains(strtolower($last), 'too many')) {
                $this->saveConnection([
                    'meta_register_blocked_until' => (string)(time() + 72 * 3600),
                ]);
            }
            throw new RuntimeException($this->friendlyRegisterError($last, $context), 0, $e);
        }
    }

    private function friendlyRegisterError(string $message, string $context = ''): string
    {
        $lower = strtolower($message);
        $suffix = $context !== '' ? ' (' . $context . ')' : '';
        $graph = $message !== '' ? ' ' . $message : '';

        if (str_contains($lower, '133005') || str_contains($lower, 'pin mismatch') || str_contains($lower, 'pin incorrect')) {
            return 'Meta rejected that PIN. Use the current 6-digit PIN from WhatsApp Business → Settings → Account → Two-step verification. If it still fails, turn two-step off, turn it on again, and use the new PIN.' . $suffix;
        }
        if (str_contains($lower, '133006') || str_contains($lower, 're-verification')) {
            return 'Meta says this number must be verified before Cloud API registration. Click Connect WhatsApp, choose the existing WhatsApp Business app, and finish QR or pairing on the college phone.' . $suffix;
        }
        if (str_contains($lower, '133012') || str_contains($lower, 'companion')) {
            return 'Meta already linked this number as a Cloud API companion. Finish pairing in the WhatsApp Business app (Settings → Account → Business platform), then send a test again.' . $suffix;
        }
        if (str_contains($lower, '133016') || str_contains($lower, 'too many')) {
            return 'Meta blocked PIN registration for this number for 72 hours. Do not click Activate Cloud API again. Click Connect WhatsApp and finish QR pairing — that path is not the PIN register limit.' . $suffix;
        }
        if (str_contains($lower, 'not verified for cloud api')) {
            return 'This WhatsApp Business app number is not verified for Cloud API (PENDING / NOT_APPLICABLE). Skip the PIN. Click Connect WhatsApp, choose the existing Business app, and finish QR pairing on the college phone.' . $suffix;
        }
        if (str_contains($lower, 'invalid parameter') || str_contains($lower, 'code 100')) {
            return 'Meta will not PIN-register this number. That is normal for a WhatsApp Business app number: Cloud API is turned on by Connect WhatsApp → existing Business app → QR/pairing, not by the two-step PIN. Keep the Facebook window open through pairing, then send a test.'
                . $suffix . $graph;
        }

        return 'Could not register the WhatsApp number on Cloud API.' . $suffix . $graph;
    }

    public function disconnect(): void
    {
        $creds = meta_cloud_credentials($this->pdo);
        if ($creds['waba_id'] !== '' && $creds['token'] !== '') {
            try {
                $this->request('DELETE', '/' . rawurlencode($creds['waba_id']) . '/subscribed_apps', [], $creds['token']);
            } catch (Throwable $e) {
                // Local disconnect should still succeed if Meta is unreachable.
            }
        }

        $this->saveConnection([
            'meta_access_token' => '',
            'meta_waba_id' => '',
            'meta_phone_number_id' => '',
            'meta_display_phone_number' => '',
            'meta_onboarding_mode' => '',
            'meta_connected_at' => '',
            'meta_is_on_biz_app' => '',
            'meta_platform_type' => '',
            'meta_phone_status' => '',
            'meta_code_verification_status' => '',
            'meta_register_blocked_until' => '',
            'meta_smb_sync_request_id' => '',
            'meta_history_sync_request_id' => '',
            'whatsapp_provider' => 'evolution',
        ]);
    }

    /**
     * @param array<string,string> $pairs
     */
    public function saveAppCredentials(string $appId, string $appSecret, string $configId, string $graphVersion = ''): void
    {
        $appId = preg_replace('/\D+/', '', trim($appId)) ?? '';
        $appSecret = self::normalizeSecret($appSecret);
        $configId = preg_replace('/\D+/', '', trim($configId)) ?? '';
        $graphVersion = trim($graphVersion);

        if ($appId === '') {
            throw new RuntimeException('Meta App ID is required.');
        }
        if ($configId === '') {
            throw new RuntimeException('Embedded Signup configuration ID is required.');
        }
        if ($graphVersion === '') {
            $graphVersion = $this->graphVersion ?: 'v21.0';
        }
        if (!preg_match('/^v\d+\.\d+$/', $graphVersion)) {
            throw new RuntimeException('Graph API version must look like v21.0.');
        }

        $this->appId = $appId;
        if ($appSecret !== '') {
            $this->rejectNonAppSecret($appSecret);
            $this->appSecret = $appSecret;
        } elseif ($this->appSecret === '') {
            throw new RuntimeException('Meta App Secret is required the first time.');
        }

        $this->assertAppCredentials();
        $this->tryPushAppWebsiteSettings();

        $pairs = [
            'meta_app_id' => $appId,
            'meta_embedded_signup_config_id' => $configId,
            'meta_graph_version' => $graphVersion,
        ];
        if ($appSecret !== '') {
            $pairs['meta_app_secret'] = $appSecret;
        }

        $this->saveConnection($pairs);
        $this->ensureVerifyToken();
    }

    private static function normalizeSecret(string $secret): string
    {
        $secret = trim($secret);
        $secret = preg_replace('/^\xEF\xBB\xBF/u', '', $secret) ?? $secret;
        $secret = trim($secret, " \t\n\r\0\x0B\"'");
        return preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]+/u', '', $secret) ?? $secret;
    }

    private function rejectNonAppSecret(string $secret): void
    {
        $verify = strtolower(self::normalizeSecret(meta_cloud_credentials($this->pdo)['verify_token']));
        if ($verify !== '' && strtolower($secret) === $verify) {
            throw new RuntimeException(
                'That value is the webhook verify token, not the App Secret. Copy App Secret from Meta for Developers → App settings → Basic.'
            );
        }
    }

    private function assertAppCredentials(): void
    {
        try {
            $this->request('POST', '/oauth/access_token', [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'grant_type' => 'client_credentials',
            ], null, false);
        } catch (RuntimeException $e) {
            throw new RuntimeException($this->friendlyClientSecretError($e->getMessage()), 0, $e);
        }
    }

    private function friendlyClientSecretError(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'client secret') || str_contains($lower, 'invalid client')) {
            return 'The App Secret does not match App ID ' . $this->appId
                . '. Open Meta for Developers → that same app → App settings → Basic, click Show next to App secret, paste it into App secret, then Save app details. Do not use the webhook verify token.';
        }
        if (str_contains($lower, 'app\'s domains') || str_contains($lower, 'app domains') || str_contains($lower, "can't load url")) {
            return 'Meta still rejected the domain. In App settings → Basic, first paste Privacy Policy URL https://edexcel.college/privacy-policy and Terms of Service URL https://edexcel.college/terms, then Add platform → Website with Site URL https://edexcel.college/, then Save. After that, type edexcel.college in App Domains (no https://), press Enter so it becomes a chip, and Save again.';
        }
        if (str_contains($lower, 'redirect_uri') || str_contains($lower, 'verification code')) {
            $raw = trim(preg_replace('/^Meta Graph API HTTP \d+:\s*/', '', $message) ?? $message);
            return 'Meta could not exchange the WhatsApp signup code (' . $raw
                . '). That code lasts about 30 seconds. Click Connect WhatsApp again, finish the Facebook window, and leave this tab open until it says Connected.';
        }

        return $message;
    }

    private function oauthApiVersion(): string
    {
        return 'v22.0';
    }

    private function siteRedirectUri(): string
    {
        return rtrim(edexcel_public_app_url(), '/') . '/admin/whatsapp_connect.php';
    }

    private function tryPushAppWebsiteSettings(): void
    {
        if ($this->appId === '' || $this->appSecret === '') {
            return;
        }

        $base = rtrim(edexcel_public_app_url(), '/');
        $host = strtolower((string)(parse_url($base . '/', PHP_URL_HOST) ?: 'edexcel.college'));
        $appToken = $this->appId . '|' . $this->appSecret;
        $privacy = $base . '/privacy-policy';
        $terms = $base . '/terms';
        $site = $base . '/';

        $payloads = [
            [
                'access_token' => $appToken,
                'website_url' => $site,
                'privacy_policy_url' => $privacy,
                'terms_of_service_url' => $terms,
                'app_domains' => '["' . $host . '"]',
            ],
            [
                'access_token' => $appToken,
                'website_url' => $site,
                'privacy_policy_url' => $privacy,
                'terms_of_service_url' => $terms,
                'app_domains' => "['" . $host . "']",
            ],
            [
                'access_token' => $appToken,
                'website_url' => $site,
                'privacy_policy_url' => $privacy,
                'terms_of_service_url' => $terms,
            ],
        ];

        foreach ($payloads as $body) {
            try {
                $this->request('POST', '/' . rawurlencode($this->appId), $body, null, false);
                return;
            } catch (Throwable $e) {
                // Console save still works if Graph rejects app-token updates.
            }
        }
    }

    private function trustedRedirectUri(string $provided): ?string
    {
        $provided = trim($provided);
        if ($provided === '') {
            return null;
        }

        $parts = parse_url($provided);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        $uri = 'https://' . $host . $path . $query;

        if (
            str_ends_with($host, '.facebook.com')
            || $host === 'facebook.com'
            || $host === 'www.facebook.com'
            || str_ends_with($host, '.fbcdn.net')
        ) {
            return $uri . $fragment;
        }

        $allowed = strtolower((string)(parse_url(edexcel_public_app_url(), PHP_URL_HOST) ?: ''));
        $httpHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if (
            in_array($host, array_filter([$allowed, $httpHost]), true)
            && str_contains($path, '/admin/whatsapp_connect.php')
        ) {
            return 'https://' . $host . $path;
        }

        return null;
    }

    /**
     * @return list<?string>
     */
    private function redirectUriCandidates(string $provided): array
    {
        $out = [];
        $seen = [];
        $add = static function (?string $uri) use (&$out, &$seen): void {
            $key = $uri === null ? "\0omit" : $uri;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = $uri;
        };

        $captured = $this->trustedRedirectUri($provided);
        if ($captured !== null) {
            $add($captured);
            $withoutHash = (string)strtok($captured, '#');
            $add($withoutHash);
            $parts = parse_url($withoutHash);
            if (is_array($parts) && isset($parts['query'])) {
                $add('https://' . strtolower((string)($parts['host'] ?? '')) . (string)($parts['path'] ?? ''));
            }
        }

        $add('https://staticxx.facebook.com/x/connect/xd_arbiter/?version=46');
        $add('https://static.xx.fbcdn.net/x/connect/xd_arbiter/?version=46');
        $add('https://www.facebook.com/connect/login_success.html');
        $add('__EMPTY__');
        $add(null);

        return $out;
    }

    private function isRetryableOauthError(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'redirect_uri')
            || str_contains($lower, 'verification code')
            || str_contains($lower, "can't load url")
            || str_contains($lower, 'app\'s domains')
            || str_contains($lower, 'app domains');
    }

    private function exchangeCode(string $code, string $redirectUri = ''): string
    {
        $lastError = 'Meta did not return an access token for that signup code.';
        foreach ($this->redirectUriCandidates($redirectUri) as $candidate) {
            try {
                return $this->requestAccessToken($code, $candidate);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                if (!$this->isRetryableOauthError($lastError)) {
                    throw new RuntimeException($this->friendlyClientSecretError($lastError), 0, $e);
                }
            }
        }

        throw new RuntimeException($this->friendlyClientSecretError($lastError)
            . ($redirectUri !== ''
                ? ' Facebook callback captured.'
                : ' Facebook callback was not captured from the login window.'));
    }

    private function requestAccessToken(string $code, ?string $redirectUri = null): string
    {
        $query = [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'code' => $code,
        ];
        if ($redirectUri === '__EMPTY__') {
            $query['redirect_uri'] = '';
        } elseif ($redirectUri !== null && $redirectUri !== '') {
            $query['redirect_uri'] = $redirectUri;
        }
        $url = 'https://graph.facebook.com/' . $this->oauthApiVersion() . '/oauth/access_token?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start a Meta Graph API request.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 12,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Could not reach Meta Graph API.');
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($http >= 400) {
            $msg = is_array($decoded)
                ? (string)($decoded['error']['message'] ?? $raw)
                : (string)$raw;
            throw new RuntimeException('Meta Graph API HTTP ' . $http . ': ' . $msg);
        }

        $token = is_array($decoded) ? trim((string)($decoded['access_token'] ?? '')) : '';
        if ($token === '') {
            throw new RuntimeException('Meta did not return an access token for that signup code.');
        }

        return $token;
    }

    private function discoverWabaId(string $token, string $businessId = ''): string
    {
        $fromDebug = $this->wabaIdFromDebugToken($token);
        if ($fromDebug !== '') {
            return $fromDebug;
        }

        if ($businessId === '') {
            $businessId = '757119500319688';
        }

        foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
            $id = $this->firstIdFromList('/' . $businessId . '/' . $edge, $token);
            if ($id !== '') {
                return $id;
            }
        }

        try {
            $businesses = $this->request('GET', '/me/businesses', ['fields' => 'id,name', 'limit' => '25'], $token, true, 'v22.0');
            $rows = $businesses['data'] ?? [];
            if (is_array($rows)) {
                foreach ($rows as $biz) {
                    $bizId = preg_replace('/\D+/', '', (string)($biz['id'] ?? '')) ?? '';
                    if ($bizId === '' || $bizId === $businessId) {
                        continue;
                    }
                    foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                        $id = $this->firstIdFromList('/' . $bizId . '/' . $edge, $token);
                        if ($id !== '') {
                            return $id;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->discoverNotes[] = 'user businesses: missing business_management on the Embedded Signup config';
        }

        return '';
    }

    private function tokenScopeHint(string $token): string
    {
        $appToken = $this->appId . '|' . $this->appSecret;
        try {
            $debug = $this->request('GET', '/debug_token', [
                'input_token' => $token,
            ], $appToken, false, 'v22.0');
            $scopes = $debug['data']['scopes'] ?? [];
            if (!is_array($scopes) || $scopes === []) {
                return '';
            }
            $names = array_values(array_filter(array_map('strval', $scopes)));
            $hint = ' Token permissions: ' . implode(', ', $names) . '.';
            $notes = array_values(array_filter(
                $this->discoverNotes,
                static fn (string $note): bool => !str_contains($note, 'whatsapp_business_accounts')
                    && !str_contains($note, 'assigned_whatsapp_business_accounts')
            ));
            if ($notes !== []) {
                $hint .= ' Lookup: ' . implode(' | ', array_slice($notes, 0, 3)) . '.';
            }

            return $hint;
        } catch (Throwable $e) {
            return '';
        }
    }

    private function discoverWabaFromPhone(string $phoneNumberId, string $token): string
    {
        $fieldSets = [
            'id,display_phone_number,whatsapp_business_account{id,name}',
            'id,whatsapp_business_account',
            'id,whatsapp_business_account_id',
        ];
        $versions = ['v22.0', 'v21.0', 'v23.0'];
        foreach ($versions as $version) {
            foreach ($fieldSets as $fields) {
                try {
                    $data = $this->request('GET', '/' . rawurlencode($phoneNumberId), [
                        'fields' => $fields,
                    ], $token, true, $version);
                    $id = $this->firstWabaIdIn($data);
                    if ($id !== '' && $id !== $phoneNumberId) {
                        return $id;
                    }
                    $waba = $data['whatsapp_business_account'] ?? $data['whatsapp_business_account_id'] ?? null;
                    if (is_array($waba)) {
                        $id = preg_replace('/\D+/', '', (string)($waba['id'] ?? '')) ?? '';
                    } else {
                        $id = preg_replace('/\D+/', '', (string)$waba) ?? '';
                    }
                    if ($id !== '' && $id !== $phoneNumberId) {
                        return $id;
                    }
                } catch (Throwable $e) {
                    $this->discoverNotes[] = '/' . $phoneNumberId . ' (' . $version . ') ' . $e->getMessage();
                }
            }
        }

        return '';
    }

    private function wabaIdFromDebugToken(string $token): string
    {
        $appToken = $this->appId . '|' . $this->appSecret;
        try {
            $debug = $this->request('GET', '/debug_token', [
                'input_token' => $token,
            ], $appToken, false, 'v22.0');
        } catch (Throwable $e) {
            return '';
        }

        $info = is_array($debug['data'] ?? null) ? $debug['data'] : [];
        $scopes = is_array($info['granular_scopes'] ?? null) ? $info['granular_scopes'] : [];
        $candidateIds = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }
            $name = strtolower((string)($scope['scope'] ?? ''));
            $ids = $scope['target_ids'] ?? [];
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                $id = preg_replace('/\D+/', '', (string)$id) ?? '';
                if ($id === '') {
                    continue;
                }
                if (str_contains($name, 'whatsapp')) {
                    return $id;
                }
                $candidateIds[$id] = true;
            }
        }
        $userId = preg_replace('/\D+/', '', (string)($info['user_id'] ?? '')) ?? '';
        foreach (array_keys($candidateIds) as $id) {
            if ($userId !== '' && $id === $userId) {
                continue;
            }
            if ($this->nodeIsWaba($id, $token)) {
                return $id;
            }
            foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                $found = $this->firstIdFromList('/' . $id . '/' . $edge, $token);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return $this->firstWabaIdIn($debug);
    }

    private function nodeIsWaba(string $id, string $token): bool
    {
        try {
            $phones = $this->request('GET', '/' . rawurlencode($id) . '/phone_numbers', ['limit' => '1'], $token, true, 'v22.0');

            return isset($phones['data']) && is_array($phones['data']);
        } catch (Throwable $e) {
            try {
                $node = $this->request('GET', '/' . rawurlencode($id), [
                    'fields' => 'id,message_template_namespace,account_review_status',
                ], $token, true, 'v22.0');

                return isset($node['message_template_namespace']) || isset($node['account_review_status']);
            } catch (Throwable $e2) {
                return false;
            }
        }
    }

    private function firstIdFromList(string $path, string $token): string
    {
        try {
            $data = $this->request('GET', $path, ['limit' => '25'], $token, true, 'v22.0');
            $rows = $data['data'] ?? [];
            if (!is_array($rows)) {
                return '';
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = preg_replace('/\D+/', '', (string)($row['id'] ?? '')) ?? '';
                if ($id !== '') {
                    return $id;
                }
            }
        } catch (Throwable $e) {
            $this->discoverNotes[] = $path . ' ' . $e->getMessage();
            return '';
        }

        return '';
    }

    private function firstWabaIdIn(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }

        foreach (['waba_id', 'whatsapp_business_account_id'] as $key) {
            if (!isset($value[$key])) {
                continue;
            }
            $raw = $value[$key];
            $id = preg_replace(
                '/\D+/',
                '',
                is_array($raw) ? (string)($raw['id'] ?? '') : (string)$raw
            ) ?? '';
            if ($id !== '') {
                return $id;
            }
        }

        if (isset($value['whatsapp_business_account'])) {
            $raw = $value['whatsapp_business_account'];
            $id = preg_replace(
                '/\D+/',
                '',
                is_array($raw) ? (string)($raw['id'] ?? '') : (string)$raw
            ) ?? '';
            if ($id !== '') {
                return $id;
            }
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                $found = $this->firstWabaIdIn($item);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listPhoneNumbers(string $wabaId, string $token): array
    {
        $data = $this->request('GET', '/' . rawurlencode($wabaId) . '/phone_numbers', [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,is_on_biz_app,platform_type,status,code_verification_status',
        ], $token);

        $rows = $data['data'] ?? [];
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<array<string,mixed>> $phones
     * @return array<string,mixed>
     */
    private function choosePhone(array $phones, string $preferredId): array
    {
        if ($preferredId !== '') {
            foreach ($phones as $phone) {
                if ((string)($phone['id'] ?? '') === $preferredId) {
                    return $phone;
                }
            }
        }
        foreach ($phones as $phone) {
            $state = strtoupper((string)($phone['status'] ?? ''));
            if ($state === 'CONNECTED' || $state === 'REGISTERED') {
                return $phone;
            }
        }
        foreach ($phones as $phone) {
            if (!empty($phone['is_on_biz_app'])) {
                return $phone;
            }
        }

        return is_array($phones[0] ?? null) ? $phones[0] : [];
    }

    private function subscribeApp(string $wabaId, string $token): void
    {
        $this->request('POST', '/' . rawurlencode($wabaId) . '/subscribed_apps', [], $token, false);
    }

    private function tryOverrideWebhook(string $wabaId, string $token, string $callback, string $verifyToken): bool
    {
        try {
            $this->request('POST', '/' . rawurlencode($wabaId) . '/subscribed_apps', [
                'override_callback_uri' => $callback,
                'verify_token' => $verifyToken,
            ], $token, false);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function phoneStatus(string $phoneNumberId, string $token): array
    {
        try {
            return $this->request('GET', '/' . rawurlencode($phoneNumberId), [
                'fields' => 'is_on_biz_app,platform_type,display_phone_number,verified_name,status,code_verification_status',
            ], $token);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{contacts:bool,history:bool,contacts_request_id:string,history_request_id:string}
     */
    private function trySmbSync(string $phoneNumberId, string $token): array
    {
        $out = [
            'contacts' => false,
            'history' => false,
            'contacts_request_id' => '',
            'history_request_id' => '',
        ];

        try {
            $contacts = $this->request('POST', '/' . rawurlencode($phoneNumberId) . '/smb_app_data', [
                'messaging_product' => 'whatsapp',
                'sync_type' => 'smb_app_state_sync',
            ], $token);
            $out['contacts'] = true;
            $out['contacts_request_id'] = (string)($contacts['request_id'] ?? '');
        } catch (Throwable $e) {
            // One-shot; a failure here must not undo a successful Cloud API link.
        }

        try {
            $history = $this->request('POST', '/' . rawurlencode($phoneNumberId) . '/smb_app_data', [
                'messaging_product' => 'whatsapp',
                'sync_type' => 'history',
            ], $token);
            $out['history'] = true;
            $out['history_request_id'] = (string)($history['request_id'] ?? '');
        } catch (Throwable $e) {
        }

        return $out;
    }

    public function ensureWebhookVerifyToken(): string
    {
        return $this->ensureVerifyToken();
    }

    private function ensureVerifyToken(): string
    {
        $existing = trim(meta_cloud_credentials($this->pdo)['verify_token']);
        if ($existing !== '') {
            return $existing;
        }
        $generated = bin2hex(random_bytes(16));
        evolution_save_setting($this->pdo, 'meta_webhook_verify_token', $generated);
        return $generated;
    }

    /**
     * @param array<string,string> $pairs
     */
    private function saveConnection(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            evolution_save_setting($this->pdo, $key, $value);
        }
    }

    /**
     * @param array<string,scalar> $queryOrBody
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        array $queryOrBody = [],
        ?string $token = null,
        bool $jsonBody = true,
        ?string $apiVersion = null
    ): array {
        $version = $apiVersion !== null && $apiVersion !== '' ? $apiVersion : $this->graphVersion;
        $url = 'https://graph.facebook.com/' . $version . $path;
        $method = strtoupper($method);
        $useJson = $jsonBody && in_array($method, ['POST', 'PUT', 'PATCH'], true);

        if ($method === 'GET' || $method === 'DELETE') {
            if ($queryOrBody !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryOrBody);
            }
        }

        if ($token !== null && $token !== '' && $this->appSecret !== '' && !str_contains($token, '|')) {
            $proof = hash_hmac('sha256', $token, $this->appSecret);
            $url .= (str_contains($url, '?') ? '&' : '?') . 'appsecret_proof=' . rawurlencode($proof);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start a Meta Graph API request.');
        }

        $headers = ['Accept: application/json'];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
        ];

        if ($useJson) {
            $body = json_encode($queryOrBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($body)) {
                $body = '{}';
            }
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . (string)strlen($body);
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $body;
            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
            }
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $queryOrBody !== [] ? http_build_query($queryOrBody) : '';
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Could not reach Meta Graph API.');
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($http >= 400) {
            throw new RuntimeException($this->formatGraphError($http, $decoded, is_string($raw) ? $raw : ''));
        }

        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }

    /**
     * @param mixed $decoded
     */
    private function formatGraphError(int $http, mixed $decoded, string $raw): string
    {
        if (!is_array($decoded) || !is_array($decoded['error'] ?? null)) {
            return 'Meta Graph API HTTP ' . $http . ': ' . $raw;
        }

        $err = $decoded['error'];
        $parts = [];
        $message = trim((string)($err['message'] ?? ''));
        if ($message !== '') {
            $parts[] = $message;
        }
        $user = trim((string)($err['error_user_msg'] ?? ''));
        $title = trim((string)($err['error_user_title'] ?? ''));
        if ($title !== '' && $title !== $message) {
            $parts[] = $title;
        }
        if ($user !== '' && $user !== $message && $user !== $title) {
            $parts[] = $user;
        }
        $code = (int)($err['code'] ?? 0);
        $sub = (int)($err['error_subcode'] ?? 0);
        if ($code > 0) {
            $parts[] = 'code ' . $code . ($sub > 0 ? '/' . $sub : '');
        }
        $data = $err['error_data'] ?? null;
        if (is_array($data)) {
            if (isset($data['details']) && is_string($data['details']) && $data['details'] !== '') {
                $parts[] = $data['details'];
            }
            if (isset($data['blame_field_specs']) && is_array($data['blame_field_specs']) && $data['blame_field_specs'] !== []) {
                $parts[] = 'fields ' . json_encode($data['blame_field_specs']);
            }
        }

        return 'Meta Graph API HTTP ' . $http . ': ' . implode(' — ', $parts);
    }
}
