<?php
declare(strict_types=1);

namespace Edexcel\Http;

/**
 * Honest edge/origin status for the admin Protection page.
 * A control is reported active only when this request or a local marker shows it.
 */
final class EdgeProtectionStatus
{
    /** @return list<array{group:string,label:string,state:string,detail:string}> */
    public static function items(): array
    {
        $cf = self::cloudflare();
        $proxied = $cf['state'] === 'YES';
        $lock = self::originLock();
        $proxy = self::trustedProxy();
        $abuse = self::classGuard(AbuseGuard::class, 'ABUSE_GUARD', 'AbuseGuard');
        $api = self::classGuard(ApiGuard::class, '', 'ApiGuard');
        return array_merge([
            ['group' => 'Network edge', 'label' => 'Cloudflare', 'state' => $cf['state'], 'detail' => $cf['detail']],
            ['group' => 'Network edge', 'label' => 'WAF', 'state' => self::edgeControl($proxied, 'waf')['state'], 'detail' => self::edgeControl($proxied, 'waf')['detail']],
            ['group' => 'Network edge', 'label' => 'Bot protection', 'state' => self::edgeControl($proxied, 'bot')['state'], 'detail' => self::edgeControl($proxied, 'bot')['detail']],
            ['group' => 'Network edge', 'label' => 'Edge rate limiting', 'state' => self::edgeControl($proxied, 'rate')['state'], 'detail' => self::edgeControl($proxied, 'rate')['detail']],
            ['group' => 'Network edge', 'label' => 'Trusted proxy', 'state' => $proxy['state'], 'detail' => $proxy['detail']],
            ['group' => 'Origin', 'label' => 'Origin lock', 'state' => $lock['state'], 'detail' => $lock['detail']],
            ['group' => 'Application', 'label' => 'AbuseGuard', 'state' => $abuse['state'], 'detail' => $abuse['detail']],
            ['group' => 'Application', 'label' => 'API Guard', 'state' => $api['state'], 'detail' => $api['detail']],
        ], HostPortStatus::items());
    }

    /** @return array{state:string,detail:string} */
    public static function cloudflare(): array
    {
        $ray = trim((string)($_SERVER['HTTP_CF_RAY'] ?? ''));
        $trusted = function_exists('eck_proxy_is_trusted') && eck_proxy_is_trusted();
        if ($ray !== '' && $trusted) {
            return [
                'state' => 'YES',
                'detail' => 'This request came through Cloudflare from a trusted proxy address.',
            ];
        }
        if ($ray !== '' && !$trusted) {
            return [
                'state' => 'NO',
                'detail' => 'A Cloudflare header was present, but the connection is not from a trusted proxy, so it was ignored.',
            ];
        }
        return [
            'state' => 'NO',
            'detail' => 'This request has no Cloudflare edge header. The public site is still being served directly until DNS is proxied.',
        ];
    }

    /** @return array{state:string,detail:string} */
    public static function originLock(): array
    {
        $flag = strtolower(trim((string)(getenv('ORIGIN_LOCK_CLOUDFLARE') ?: '')));
        if (in_array($flag, ['1', 'true', 'on'], true)) {
            return [
                'state' => 'ENABLED',
                'detail' => 'Application origin lock is on. Direct clients that are not Cloudflare or a local proxy receive HTTP 403.',
            ];
        }
        return [
            'state' => 'DISABLED',
            'detail' => 'Not yet enabled. Leave it off until Cloudflare proxying is verified.',
        ];
    }

    /** @return array{state:string,detail:string} */
    public static function trustedProxy(): array
    {
        if (!function_exists('eck_cloudflare_cidrs') || eck_cloudflare_cidrs() === []) {
            return [
                'state' => 'WARNING',
                'detail' => 'Cloudflare address ranges are not loaded. Forwarded client IP headers must not be trusted.',
            ];
        }
        return [
            'state' => 'OK',
            'detail' => 'Forwarded client addresses are accepted only from Cloudflare, loopback, private proxies, or TRUSTED_PROXIES.',
        ];
    }

    /** @return array{state:string,detail:string} */
    private static function edgeControl(bool $proxied, string $kind): array
    {
        $names = [
            'waf' => 'WAF',
            'bot' => 'Bot protection',
            'rate' => 'Edge rate limiting',
        ];
        $label = $names[$kind] ?? 'Edge control';
        if (!$proxied) {
            return [
                'state' => 'INACTIVE',
                'detail' => $label . ' is not in front of this request. A missing Cloudflare header is not treated as an enabled rule.',
            ];
        }
        $token = trim((string)(getenv('CLOUDFLARE_API_TOKEN') ?: ''));
        $zone = trim((string)(getenv('CLOUDFLARE_ZONE_ID') ?: ''));
        if ($token === '' || $zone === '') {
            return [
                'state' => 'INACTIVE',
                'detail' => 'This request is proxied, but no Cloudflare API token is configured, so ' . $label . ' is not marked active.',
            ];
        }
        return [
            'state' => 'INACTIVE',
            'detail' => $label . ' was not confirmed from the Cloudflare API on this request.',
        ];
    }

    /** @return array{state:string,detail:string} */
    private static function classGuard(string $class, string $envName, string $label): array
    {
        if ($envName !== '') {
            $flag = strtolower(trim((string)(getenv($envName) ?: '')));
            if (in_array($flag, ['0', 'off', 'false'], true)) {
                return ['state' => 'INACTIVE', 'detail' => $envName . ' is turned off.'];
            }
        }
        if (!class_exists($class)) {
            return ['state' => 'INACTIVE', 'detail' => $label . ' is not loaded.'];
        }
        return ['state' => 'ACTIVE', 'detail' => $label . ' is loaded and not disabled.'];
    }
}
