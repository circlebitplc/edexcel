<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$iso = 'LK';
try {
    if (class_exists('Edexcel\\Services\\GeoIpService')) {
        $iso = \Edexcel\Services\GeoIpService::countryCode();
    } elseif (is_file(dirname(__DIR__) . '/src/Services/GeoIpService.php')) {
        require_once dirname(__DIR__) . '/src/Services/GeoIpService.php';
        if (class_exists('Edexcel\\Services\\GeoIpService')) {
            $iso = \Edexcel\Services\GeoIpService::countryCode();
        }
    }
} catch (\Throwable $e) {
    $iso = 'LK';
}

if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $cf = strtoupper(trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']));
    if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
        $iso = $cf;
    }
}

if (empty($iso) || !preg_match('/^[A-Z]{2}$/', $iso)) {
    $iso = 'LK';
}

echo json_encode([
    'ok' => true,
    'country' => $iso,
    'ip' => class_exists('Edexcel\\Services\\GeoIpService') ? \Edexcel\Services\GeoIpService::clientIp() : '',
], JSON_UNESCAPED_SLASHES);
