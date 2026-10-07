<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Resolve visitor country from IP (Cloudflare header or lightweight lookup).
 * Used to require Sri Lanka SMS phone linking only for LK traffic.
 */
final class GeoIpService
{
    private const SESSION_KEY = 'geo_country';
    private const SESSION_AT = 'geo_country_at';
    private const CACHE_TTL = 3600;

    public static function clientIp(): string
    {
        if (!function_exists('eck_client_ip')) {
            $file = dirname(__DIR__, 2) . '/config/client_ip.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (function_exists('eck_client_ip')) {
            return eck_client_ip();
        }
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }

    /**
     * ISO 3166-1 alpha-2 country code, or empty when unknown.
     */
    public static function countryCode(): string
    {
        $forced = strtoupper(trim((string)(getenv('GEOIP_FORCE_COUNTRY') ?: '')));
        if (preg_match('/^[A-Z]{2}$/', $forced) === 1) {
            return self::remember($forced);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $cached = strtoupper(trim((string)($_SESSION[self::SESSION_KEY] ?? '')));
            $at = (int)($_SESSION[self::SESSION_AT] ?? 0);
            if (preg_match('/^[A-Z]{2}$/', $cached) === 1 && $at > 0 && (time() - $at) < self::CACHE_TTL) {
                return $cached;
            }
        }

        if (!function_exists('eck_proxy_is_trusted')) {
            $ipFile = dirname(__DIR__, 2) . '/config/client_ip.php';
            if (is_file($ipFile)) {
                require_once $ipFile;
            }
        }
        $trusted = function_exists('eck_proxy_is_trusted') && eck_proxy_is_trusted();
        $cf = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
        if ($trusted && preg_match('/^[A-Z]{2}$/', $cf) === 1 && $cf !== 'XX' && $cf !== 'T1') {
            return self::remember($cf);
        }

        $ip = self::clientIp();
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // Private/local: treat as Sri Lanka so campus/dev keeps SMS flow.
            return self::remember('LK');
        }

        $looked = self::lookupCountry($ip);
        if ($looked !== '') {
            return self::remember($looked);
        }

        // Lookup failed: default LK (require SMS) — majority of portal users are local.
        return self::remember('LK');
    }

    public static function isSriLanka(): bool
    {
        return self::countryCode() === 'LK';
    }

    /**
     * SMS mobile linking is required only for Sri Lanka visitors.
     */
    public static function requiresSmsPhoneLink(): bool
    {
        return self::isSriLanka();
    }

    private static function remember(string $code): string
    {
        $code = strtoupper($code);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $code;
            $_SESSION[self::SESSION_AT] = time();
        }
        return $code;
    }

    private static function lookupCountry(string $ip): string
    {
        $urls = [
            'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode',
            'https://ipapi.co/' . rawurlencode($ip) . '/country_code/',
        ];
        foreach ($urls as $url) {
            $body = self::httpGet($url);
            if ($body === null || $body === '') {
                continue;
            }
            $body = trim($body);
            if (preg_match('/^[A-Za-z]{2}$/', $body) === 1) {
                return strtoupper($body);
            }
            $json = json_decode($body, true);
            if (is_array($json)) {
                $status = strtolower((string)($json['status'] ?? 'success'));
                $code = strtoupper(trim((string)($json['countryCode'] ?? $json['country_code'] ?? '')));
                if ($status !== 'fail' && preg_match('/^[A-Z]{2}$/', $code) === 1) {
                    return $code;
                }
            }
        }
        return '';
    }

    private static function httpGet(string $url): ?string
    {
        if (!ini_get('allow_url_fopen')) {
            return null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2.0,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: EdexcelCollegePortal/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        try {
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return null;
            }
            return (string)$raw;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
