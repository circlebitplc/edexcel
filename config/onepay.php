<?php
declare(strict_types=1);

require_once __DIR__ . '/bunny.php';

/**
 * OnePay (docs.onepay.lk v3 redirection API).
 *
 * Required: app_id, hash_salt.
 * app_token is sent as the Authorization header (official PHP checkout SDK).
 * Hash: sha256(app_id + currency + amount + HASH_SALT) lowercase hex.
 * Verify payments via POST /v3/transaction/status/ — never from browser query params.
 *
 * @return array{
 *   enabled:bool,
 *   environment:string,
 *   app_id:string,
 *   app_token:string,
 *   hash_salt:string,
 *   api_url:string,
 *   currency:string,
 *   webhook_url:string,
 *   return_url:string
 * }
 */
function onepay_config(?PDO $pdo = null): array
{
    $enabled = recordings_env_or_setting($pdo, 'ONEPAY_ENABLED', 'onepay_enabled', '0');
    $env = strtolower(recordings_env_or_setting($pdo, 'ONEPAY_ENVIRONMENT', 'onepay_environment', 'production'));
    if (!in_array($env, ['sandbox', 'production'], true)) {
        $env = 'production';
    }
    $apiUrl = recordings_env_or_setting($pdo, 'ONEPAY_API_URL', 'onepay_api_url', 'https://api.onepay.lk');
    $currency = strtoupper(recordings_env_or_setting($pdo, 'ONEPAY_CURRENCY', 'onepay_currency', 'LKR'));
    if ($currency === '') {
        $currency = 'LKR';
    }

    $base = recordings_app_url();
    return [
        'enabled' => $enabled === '1' || strtolower($enabled) === 'true',
        'environment' => $env,
        'app_id' => recordings_env_or_setting($pdo, 'ONEPAY_APP_ID', 'onepay_app_id'),
        'app_token' => recordings_env_or_setting($pdo, 'ONEPAY_APP_TOKEN', 'onepay_app_token'),
        'hash_salt' => recordings_env_or_setting($pdo, 'ONEPAY_HASH_SALT', 'onepay_hash_salt'),
        'api_url' => rtrim($apiUrl !== '' ? $apiUrl : 'https://api.onepay.lk', '/'),
        'currency' => $currency,
        'webhook_url' => $base . '/api/onepay/callback.php',
        'return_url' => $base . '/student/payment_result.php',
    ];
}
