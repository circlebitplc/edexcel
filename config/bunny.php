<?php
declare(strict_types=1);

/**
 * Bunny.net Stream configuration.
 * Secrets prefer .env; non-secret values may live in settings.
 */

function recordings_env_or_setting(?PDO $pdo, string $envKey, string $settingKey, string $default = ''): string
{
    $fromEnv = trim((string)(getenv($envKey) ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    if (!$pdo instanceof PDO) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$settingKey]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function recordings_save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function recordings_app_url(): string
{
    if (function_exists('edexcel_public_app_url')) {
        return rtrim(edexcel_public_app_url(), '/');
    }
    $url = rtrim(trim((string)(getenv('APP_URL') ?: '')), '/');
    if ($url !== '' && $url !== '/') {
        return $url;
    }
    return 'https://edexcel.college';
}

/**
 * @return array{
 *   enabled:bool,
 *   library_id:string,
 *   api_key:string,
 *   cdn_hostname:string,
 *   token_key:string,
 *   webhook_secret:string,
 *   account_api_key:string,
 *   api_base:string,
 *   embed_base:string,
 *   playback_ttl:int,
 *   retention_enabled:bool,
 *   retention_days:int,
 *   webhook_url:string
 * }
 */
function bunny_config(?PDO $pdo = null): array
{
    $enabled = recordings_env_or_setting($pdo, 'BUNNY_ENABLED', 'bunny_enabled', '0');
    $ttl = (int)recordings_env_or_setting($pdo, 'BUNNY_PLAYBACK_TTL', 'bunny_playback_ttl', '7200');
    if ($ttl < 300) {
        $ttl = 7200;
    }
    $days = (int)recordings_env_or_setting($pdo, 'RECORDING_RETENTION_DAYS', 'recording_retention_days', '365');
    $apiBase = recordings_env_or_setting($pdo, 'BUNNY_API_BASE_URL', 'bunny_api_base_url', 'https://video.bunnycdn.com');
    $embedBase = recordings_env_or_setting($pdo, 'BUNNY_EMBED_BASE_URL', 'bunny_embed_base_url', 'https://iframe.mediadelivery.net');

    return [
        'enabled' => $enabled === '1' || strtolower($enabled) === 'true',
        'library_id' => recordings_env_or_setting($pdo, 'BUNNY_LIBRARY_ID', 'bunny_library_id'),
        'api_key' => recordings_env_or_setting($pdo, 'BUNNY_API_KEY', 'bunny_api_key'),
        'cdn_hostname' => recordings_env_or_setting($pdo, 'BUNNY_CDN_HOSTNAME', 'bunny_cdn_hostname'),
        'token_key' => recordings_env_or_setting($pdo, 'BUNNY_TOKEN_AUTH_KEY', 'bunny_token_authentication_key'),
        'webhook_secret' => recordings_env_or_setting($pdo, 'BUNNY_WEBHOOK_SECRET', 'bunny_webhook_secret'),
        'account_api_key' => bunny_account_api_key($pdo),
        'api_base' => rtrim($apiBase !== '' ? $apiBase : 'https://video.bunnycdn.com', '/'),
        'embed_base' => rtrim($embedBase !== '' ? $embedBase : 'https://iframe.mediadelivery.net', '/'),
        'playback_ttl' => $ttl,
        'retention_enabled' => recordings_env_or_setting($pdo, 'RECORDING_RETENTION_ENABLED', 'recording_retention_enabled', '0') === '1',
        'retention_days' => max(1, $days),
        'webhook_url' => recordings_app_url() . '/api/bunny/webhook.php',
    ];
}

function bunny_account_api_key(?PDO $pdo = null): string
{
    return recordings_env_or_setting($pdo, 'BUNNY_ACCOUNT_API_KEY', 'bunny_account_api_key');
}

function bunny_teacher_is_configured(PDO $pdo, int $teacherId): bool
{
    if ($teacherId < 1) {
        return false;
    }
    try {
        return (new \Edexcel\Services\TeacherBunnyLibraryService($pdo))->teacherIsConfigured($teacherId);
    } catch (Throwable $e) {
        return false;
    }
}

function bunny_secret_configured_via_env(string $envKey): bool
{
    return trim((string)(getenv($envKey) ?: '')) !== '';
}
