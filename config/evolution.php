<?php
declare(strict_types=1);

function edexcel_is_loopback_url(string $url): bool
{
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: $url));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * Fix common Evolution URL mistakes such as https://host/:8080
 * (port stored as a path, which becomes POST /:8080/webhook/set/...).
 */
function evolution_normalize_api_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $url = preg_replace('/\s+/', '', $url) ?? $url;
    $url = preg_replace('#/+#', '/', str_replace('\\', '/', $url)) ?? $url;
    $url = preg_replace('#^(https?:)/+#i', '$1//', $url) ?? $url;

    if (preg_match('#^(https?://)([^/:]+)(?:/+:|/)(\d+)(/.*)?$#i', $url, $m)) {
        $url = $m[1] . $m[2] . ':' . $m[3] . ($m[4] ?? '');
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . ltrim($url, '/');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return rtrim($url, '/');
    }

    $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
    $host = strtolower((string)$parts['host']);
    $port = isset($parts['port']) ? (int)$parts['port'] : 0;
    $path = (string)($parts['path'] ?? '');

    if (preg_match('#^/:(\d+)$#', $path, $m)) {
        $port = (int)$m[1];
        $path = '';
    }

    $collegeHosts = ['edexcel.college', 'www.edexcel.college', 'kandy.edexcel.college', 'www.kandy.edexcel.college'];
    if (in_array($host, $collegeHosts, true)) {
        if ($port > 0 && $port !== 80 && $port !== 443) {
            $scheme = 'http';
            $host = '127.0.0.1';
        } else {
            return '';
        }
    }

    $out = $scheme . '://' . $host;
    if ($port > 0 && !in_array($port, [80, 443], true)) {
        $out .= ':' . $port;
    }
    if ($path !== '' && $path !== '/') {
        $out .= rtrim($path, '/');
    }

    return $out;
}

function edexcel_public_app_url(): string
{
    $url = rtrim(trim((string)(getenv('APP_URL') ?: '')), '/');
    if (
        $url !== ''
        && $url !== '/'
        && !edexcel_is_loopback_url($url)
    ) {
        return $url;
    }

    $httpHost = preg_replace(
        '/:\d+$/',
        '',
        (string)($_SERVER['HTTP_HOST'] ?? '')
    ) ?? '';

    if ($httpHost !== '' && !edexcel_is_loopback_url($httpHost)) {
        return 'https://' . $httpHost;
    }

    return 'https://edexcel.college';
}

function evolution_setting(?PDO $pdo, string $key, string $default = ''): string
{
    if (!$pdo instanceof PDO) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function evolution_save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function evolution_credentials(?PDO $pdo = null): array
{
    if ($pdo === null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    $envUrl = evolution_normalize_api_url((string)(getenv('EVOLUTION_API_URL') ?: ''));
    $envKey = trim((string)(getenv('EVOLUTION_API_KEY') ?: ''));
    $envInstance = trim((string)(getenv('EVOLUTION_INSTANCE') ?: ''));

    $dbUrl = evolution_normalize_api_url(evolution_setting($pdo instanceof PDO ? $pdo : null, 'evolution_api_url'));
    $dbKey = trim(evolution_setting($pdo instanceof PDO ? $pdo : null, 'evolution_api_key'));
    $dbInstance = trim(evolution_setting($pdo instanceof PDO ? $pdo : null, 'evolution_instance'));

    $url = '';
    if ($envUrl !== '' && !edexcel_is_loopback_url($envUrl)) {
        $url = $envUrl;
    } elseif ($dbUrl !== '' && !edexcel_is_loopback_url($dbUrl)) {
        $url = $dbUrl;
    } elseif ($envUrl !== '') {
        $url = $envUrl;
    } else {
        $url = $dbUrl;
    }

    $key = $envKey !== '' ? $envKey : $dbKey;
    $instance = $envInstance !== ''
        ? $envInstance
        : ($dbInstance !== '' ? $dbInstance : 'edexcel');

    $url = evolution_normalize_api_url($url);

    if (
        $pdo instanceof PDO
        && $url !== ''
        && $url !== $dbUrl
    ) {
        try {
            evolution_save_setting($pdo, 'evolution_api_url', $url);
        } catch (Throwable $e) {
            error_log('Failed to save normalized Evolution URL: ' . $e->getMessage());
        }
    }

    if (
        $pdo instanceof PDO
        && $url !== ''
        && $key !== ''
        && !edexcel_is_loopback_url($url)
        && ($dbUrl === '' || edexcel_is_loopback_url($dbUrl))
    ) {
        try {
            evolution_save_setting($pdo, 'evolution_api_url', $url);
            evolution_save_setting($pdo, 'evolution_api_key', $key);
            evolution_save_setting($pdo, 'evolution_instance', $instance);
        } catch (Throwable $e) {
            error_log('Failed to sync Evolution settings: ' . $e->getMessage());
        }
    }

    return [
        'url' => $url,
        'key' => $key,
        'instance' => $instance,
    ];
}

function evolution_webhook_secret(?PDO $pdo = null): string
{
    $env = trim((string)(getenv('EVOLUTION_WEBHOOK_SECRET') ?: ''));
    if ($env !== '') {
        return $env;
    }

    return trim(evolution_setting($pdo, 'evolution_webhook_secret'));
}

function evolution_ensure_webhook_secret(?PDO $pdo = null): string
{
    $secret = evolution_webhook_secret($pdo);
    if ($secret !== '') {
        return $secret;
    }

    if (!$pdo instanceof PDO) {
        return '';
    }

    try {
        $secret = bin2hex(random_bytes(24));
        evolution_save_setting($pdo, 'evolution_webhook_secret', $secret);
        return $secret;
    } catch (Throwable $e) {
        error_log('Could not generate Evolution webhook secret: ' . $e->getMessage());
        return '';
    }
}

function evolution_bot_enabled(?PDO $pdo = null): bool
{
    return evolution_setting($pdo, 'whatsapp_bot_enabled', '1') === '1';
}
