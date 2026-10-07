<?php
declare(strict_types=1);

/**
 * Real client IP and HTTPS detection behind Cloudflare or a local reverse proxy.
 * Forwarding headers are ignored unless the TCP peer is a trusted proxy.
 */

function eck_cloudflare_cidrs(): array
{
    return [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];
}

function eck_valid_ip(string $ip): bool
{
    return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function eck_cidr_contains(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '' || !eck_valid_ip($ip)) {
        return false;
    }
    if (!str_contains($cidr, '/')) {
        return strcasecmp($ip, $cidr) === 0;
    }
    [$net, $bits] = explode('/', $cidr, 2);
    $bits = (int)$bits;
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rem = $bits % 8;
    if ($rem === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}

function eck_remote_addr(): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return eck_valid_ip($ip) ? $ip : '';
}

function eck_proxy_is_trusted(?string $remote = null): bool
{
    $remote = $remote ?? eck_remote_addr();
    if ($remote === '') {
        return false;
    }
    if ($remote === '127.0.0.1' || $remote === '::1') {
        return true;
    }
    // On-host and LAN reverse proxies (CyberPanel / local nginx). Public clients stay untrusted.
    if (filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }
    foreach (eck_cloudflare_cidrs() as $cidr) {
        if (eck_cidr_contains($remote, $cidr)) {
            return true;
        }
    }
    $extra = (string)(getenv('TRUSTED_PROXIES') ?: '');
    if ($extra === '' && isset($_ENV['TRUSTED_PROXIES'])) {
        $extra = (string)$_ENV['TRUSTED_PROXIES'];
    }
    foreach (preg_split('/\s*,\s*/', $extra) ?: [] as $cidr) {
        if ($cidr !== '' && eck_cidr_contains($remote, $cidr)) {
            return true;
        }
    }
    return false;
}

function eck_client_ip(): string
{
    $remote = eck_remote_addr();
    if (!eck_proxy_is_trusted($remote)) {
        return $remote;
    }
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if (eck_valid_ip($cf)) {
        return $cf;
    }
    $xff = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    $first = '';
    foreach (explode(',', $xff) as $part) {
        $ip = trim($part);
        if (!eck_valid_ip($ip)) {
            continue;
        }
        if ($first === '') {
            $first = $ip;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    return $first !== '' ? $first : $remote;
}

function eck_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (!eck_proxy_is_trusted()) {
        return false;
    }
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return str_contains((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https');
}
