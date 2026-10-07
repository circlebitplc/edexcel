<?php
declare(strict_types=1);

require_once __DIR__ . '/evolution.php';

function whatsapp_provider(?PDO $pdo = null): string
{
    $fromEnv = strtolower(trim((string)(getenv('WHATSAPP_PROVIDER') ?: '')));
    if (in_array($fromEnv, ['meta', 'cloud', 'meta_cloud'], true)) {
        return 'meta';
    }
    if ($fromEnv === 'evolution') {
        return 'evolution';
    }

    $fromDb = strtolower(trim(evolution_setting($pdo, 'whatsapp_provider', 'evolution')));
    return $fromDb === 'meta' ? 'meta' : 'evolution';
}

function meta_setting(?PDO $pdo, string $envKey, string $settingKey, string $default = ''): string
{
    $fromEnv = trim((string)(getenv($envKey) ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    return trim(evolution_setting($pdo, $settingKey, $default));
}

function meta_default_waba_id(): string
{
    return '';
}

function meta_default_phone_number_id(): string
{
    return '';
}

function meta_default_display_phone(): string
{
    return '+94 71 339 6083';
}

/**
 *   phone_number_id:string,
 *   verify_token:string,
 *   graph_version:string,
 *   app_id:string,
 *   app_secret:string,
 *   config_id:string,
 *   waba_id:string,
 *   display_phone:string,
 *   onboarding_mode:string,
 *   connected_at:string
 * }
 */
function meta_cloud_credentials(?PDO $pdo = null): array
{
    $version = meta_setting($pdo, 'META_GRAPH_VERSION', 'meta_graph_version', 'v21.0');
    if ($version === '') {
        $version = 'v21.0';
    }

    return [
        'token' => meta_setting($pdo, 'META_WHATSAPP_TOKEN', 'meta_access_token'),
        'phone_number_id' => meta_setting($pdo, 'META_WHATSAPP_PHONE_NUMBER_ID', 'meta_phone_number_id'),
        'verify_token' => meta_setting($pdo, 'META_WHATSAPP_VERIFY_TOKEN', 'meta_webhook_verify_token'),
        'graph_version' => $version,
        'app_id' => meta_setting($pdo, 'META_APP_ID', 'meta_app_id'),
        'app_secret' => meta_setting($pdo, 'META_APP_SECRET', 'meta_app_secret'),
        'config_id' => meta_setting($pdo, 'META_EMBEDDED_SIGNUP_CONFIG_ID', 'meta_embedded_signup_config_id'),
        'waba_id' => meta_setting($pdo, 'META_WABA_ID', 'meta_waba_id'),
        'display_phone' => trim(evolution_setting($pdo, 'meta_display_phone_number')),
        'onboarding_mode' => trim(evolution_setting($pdo, 'meta_onboarding_mode')),
        'connected_at' => trim(evolution_setting($pdo, 'meta_connected_at')),
    ];
}

function whatsapp_sender(?PDO $pdo = null): \Edexcel\Services\WhatsAppSender
{
    if (whatsapp_provider($pdo) === 'meta') {
        return new \Edexcel\Services\MetaCloudApiService($pdo);
    }

    return new \Edexcel\Services\EvolutionApiService($pdo);
}
