<?php
declare(strict_types=1);

namespace Edexcel\Services;

use RuntimeException;

/**
 * Server-side Google OAuth 2.0 (authorization code) for student/parent portals only.
 * Client secret never leaves the server.
 */
final class GoogleOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $callbackUrl;

    public function __construct(?string $clientId = null, ?string $clientSecret = null, ?string $callbackUrl = null)
    {
        $this->clientId = trim((string)($clientId ?? ''));
        $this->clientSecret = trim((string)($clientSecret ?? ''));
        $configured = trim((string)($callbackUrl ?? ''));
        if ($configured === '') {
            $base = rtrim((string)(defined('APP_URL') ? APP_URL : (getenv('APP_URL') ?: '')), '/');
            $configured = $base !== '' ? ($base . '/auth/google/callback.php') : '';
        }
        $this->callbackUrl = $configured;
    }

    /**
     * Resolve credentials: environment variables win; otherwise settings table.
     */
    public static function fromConfig(?\PDO $pdo = null): self
    {
        $clientId = self::envOrSetting($pdo, 'GOOGLE_CLIENT_ID', 'google_client_id');
        $secret = self::envOrSetting($pdo, 'GOOGLE_CLIENT_SECRET', 'google_client_secret');
        $callback = self::envOrSetting($pdo, 'GOOGLE_CALLBACK_URL', 'google_callback_url');
        if ($callback === '') {
            $base = '';
            if (function_exists('edexcel_public_app_url')) {
                $base = rtrim(edexcel_public_app_url(), '/');
            } elseif (defined('APP_URL')) {
                $base = rtrim((string)APP_URL, '/');
            } else {
                $base = rtrim((string)(getenv('APP_URL') ?: ''), '/');
            }
            if ($base !== '' && $base !== '/') {
                $callback = $base . '/auth/google/callback.php';
            }
        }
        return new self($clientId, $secret, $callback);
    }

    /**
     * Portal should offer Google only when credentials exist and the feature is enabled.
     */
    public static function isPortalEnabled(?\PDO $pdo = null): bool
    {
        $raw = self::envOrSetting($pdo, 'GOOGLE_OAUTH_ENABLED', 'google_oauth_enabled');
        $oauth = self::fromConfig($pdo);
        if (!$oauth->isConfigured()) {
            return false;
        }
        if ($raw === '') {
            return true;
        }
        return $raw === '1' || strtolower($raw) === 'true';
    }

    private static function envOrSetting(?\PDO $pdo, string $envKey, string $settingKey): string
    {
        if (function_exists('recordings_env_or_setting')) {
            return trim(recordings_env_or_setting($pdo, $envKey, $settingKey, ''));
        }
        $fromEnv = trim((string)(getenv($envKey) ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        if (!$pdo instanceof \PDO) {
            return '';
        }
        try {
            $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$settingKey]);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? '' : trim((string)$value);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->callbackUrl !== '';
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function clientSecretConfigured(): bool
    {
        return $this->clientSecret !== '';
    }

    public function callbackUrl(): string
    {
        return $this->callbackUrl;
    }

    /**
     * Create CSRF state and store portal intent in the session.
     */
    public function begin(string $intent, ?string $inviteToken = null): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google sign-in is not configured.');
        }
        $intent = strtolower(trim($intent));
        if (!in_array($intent, ['student', 'parent', 'staff', 'teacher', 'link_teacher', 'admin_link_teacher', 'link_admin'], true)) {
            throw new RuntimeException('Invalid sign-in intent.');
        }
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli' && !headers_sent()) {
            session_start();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Allow PHPUnit / CLI tests to use $_SESSION without session_start.
            if (PHP_SAPI === 'cli' || headers_sent()) {
                if (!isset($_SESSION) || !is_array($_SESSION)) {
                    $_SESSION = [];
                }
            } else {
                throw new RuntimeException('Session required for Google sign-in.');
            }
        }
        $state = bin2hex(random_bytes(32));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_intent'] = $intent;
        $_SESSION['google_oauth_started_at'] = time();
        if ($inviteToken !== null && $inviteToken !== '') {
            $_SESSION['google_oauth_invite'] = $inviteToken;
        } else {
            unset($_SESSION['google_oauth_invite']);
        }
        return $this->authorizationUrl($state);
    }

    public function authorizationUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Validate state, exchange code, return Google profile.
     *
     * @return array{google_id:string,email:string,email_verified:bool,name:string,picture:string,intent:string,invite:?string}
     */
    public function complete(string $code, string $state): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google sign-in is not configured.');
        }
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli' && !headers_sent()) {
            session_start();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Allow PHPUnit / CLI tests to use $_SESSION without session_start.
            if (PHP_SAPI === 'cli' || headers_sent()) {
                if (!isset($_SESSION) || !is_array($_SESSION)) {
                    $_SESSION = [];
                }
            } else {
                throw new RuntimeException('Session required for Google sign-in.');
            }
        }
        $expected = (string)($_SESSION['google_oauth_state'] ?? '');
        $started = (int)($_SESSION['google_oauth_started_at'] ?? 0);
        $intent = strtolower(trim((string)($_SESSION['google_oauth_intent'] ?? '')));
        $invite = isset($_SESSION['google_oauth_invite']) ? (string)$_SESSION['google_oauth_invite'] : null;

        unset(
            $_SESSION['google_oauth_state'],
            $_SESSION['google_oauth_intent'],
            $_SESSION['google_oauth_started_at'],
            $_SESSION['google_oauth_invite']
        );

        if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
            throw new RuntimeException('Invalid sign-in state. Please try again.');
        }
        if ($started > 0 && (time() - $started) > 900) {
            throw new RuntimeException('Sign-in expired. Please try again.');
        }
        if (!in_array($intent, ['student', 'parent', 'staff', 'teacher', 'link_teacher', 'admin_link_teacher', 'link_admin'], true)) {
            throw new RuntimeException('Invalid sign-in intent.');
        }
        $code = trim($code);
        if ($code === '') {
            throw new RuntimeException('Google did not return an authorization code.');
        }

        $token = $this->exchangeCode($code);
        $accessToken = (string)($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Could not obtain Google access token.');
        }
        $profile = $this->fetchUserInfo($accessToken);
        $googleId = trim((string)($profile['sub'] ?? ''));
        $email = strtolower(trim((string)($profile['email'] ?? '')));
        $verified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || $profile['email_verified'] === 'true'
            || $profile['email_verified'] === true;
        if ($googleId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google account is missing a verified email.');
        }
        if (!$verified) {
            throw new RuntimeException('Please use a Google account with a verified email address.');
        }

        $picture = trim((string)($profile['picture'] ?? ''));
        
        return [
            'google_id' => $googleId,
            'email' => $email,
            'email_verified' => true,
            'name' => trim((string)($profile['name'] ?? '')),
            'picture' => $picture,
            'intent' => $intent,
            'invite' => $invite,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function exchangeCode(string $code): array
    {
        return $this->httpPostJson('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->callbackUrl,
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchUserInfo(string $accessToken): array
    {
        return $this->httpGetJson('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private function httpPostJson(string $url, array $fields): array
    {
        $body = http_build_query($fields);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
            ]);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($errno !== 0 || $raw === false) {
                throw new RuntimeException('Network error contacting Google.');
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || $status >= 400) {
                throw new RuntimeException('Google token exchange failed.');
            }
            return $decoded;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Network error contacting Google.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Google token exchange failed.');
        }
        return $decoded;
    }

    /**
     * @return array<string,mixed>
     */
    private function httpGetJson(string $url, string $accessToken): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Accept: application/json',
                ],
            ]);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($errno !== 0 || $raw === false) {
                throw new RuntimeException('Network error contacting Google.');
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || $status >= 400) {
                throw new RuntimeException('Could not load Google profile.');
            }
            return $decoded;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Network error contacting Google.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Could not load Google profile.');
        }
        return $decoded;
    }
}
