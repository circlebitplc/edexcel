<?php
declare(strict_types=1);

namespace Edexcel\Http;

/**
 * Transport checks for API entry points: method, content type, and body size.
 * Authentication, CSRF, and rate limits stay with the caller so existing
 * routes keep their own authorization rules.
 */
final class ApiGuard
{
    /** @param list<string> $methods */
    public static function assertTransport(array $methods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], int $maxBytes = 1048576): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $allowed = array_map('strtoupper', $methods);
        if (!in_array($method, $allowed, true)) {
            ApiResponse::error('METHOD_NOT_ALLOWED', 'Method not allowed.', [], 405);
        }
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($len > $maxBytes) {
            ApiResponse::error('PAYLOAD_TOO_LARGE', 'Request is too large.', [], 413);
        }
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true) || $len < 1) {
            return;
        }
        $type = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        $ok = $type === ''
            || str_contains($type, 'application/json')
            || str_contains($type, 'application/x-www-form-urlencoded')
            || str_contains($type, 'multipart/form-data');
        if (!$ok) {
            ApiResponse::error('UNSUPPORTED_MEDIA_TYPE', 'Unsupported content type.', [], 415);
        }
    }
}
