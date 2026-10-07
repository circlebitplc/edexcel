<?php
declare(strict_types=1);

if (!headers_sent()) {
    $httpsOn = function_exists('eck_request_is_https')
        ? eck_request_is_https()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'));
    if ($httpsOn && PHP_SAPI !== 'cli') {
        // Apex only. includeSubDomains is omitted so other hosts are not forced onto HTTPS by this header.
        header('Strict-Transport-Security: max-age=15552000');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(self), camera=(self), display-capture=(self)');
    header("Feature-Policy: microphone 'self'; camera 'self'; display-capture 'self'");
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://connect.facebook.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://unpkg.com; "
        . "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: blob: https:; "
        . "media-src 'self' blob: https:; "
        . "connect-src 'self' https: wss: blob:; "
        . "frame-src 'self' https://iframe.mediadelivery.net https://www.facebook.com https://web.facebook.com https://www.youtube.com https://www.google.com https://maps.google.com; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        // Browsers apply form-action to the 302 after Pay Now. OnePay checkout is
        // https://payment.onepay.lk/redirect/… — not api.onepay.lk — so the card
        // button appeared to do nothing until those hosts were allowed.
        . "form-action 'self' https://api.onepay.lk https://payment.onepay.lk https://www.onepay.lk https://onepay.lk https://*.onepay.lk https://www.facebook.com"
    );
}
