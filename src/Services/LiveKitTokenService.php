<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * LiveKit access tokens (HS256). API secret never leaves the server.
 */
final class LiveKitTokenService
{
    /**
     * @param array<string,mixed> $videoGrant
     */
    public static function participantToken(
        string $apiKey,
        string $apiSecret,
        string $identity,
        string $name,
        array $videoGrant,
        int $ttlSeconds = 7200,
        string $metadata = ''
    ): string {
        $now = time();
        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'nbf' => $now - 10,
            'exp' => $now + max(300, $ttlSeconds),
            'jti' => bin2hex(random_bytes(8)),
            'name' => $name,
            'video' => $videoGrant,
        ];
        if ($metadata !== '') {
            $payload['metadata'] = $metadata;
        }
        return self::sign($apiKey, $apiSecret, $payload);
    }

    public static function serverToken(string $apiKey, string $apiSecret, int $ttlSeconds = 600): string
    {
        $now = time();
        return self::sign($apiKey, $apiSecret, [
            'iss' => $apiKey,
            'nbf' => $now - 10,
            'exp' => $now + max(60, $ttlSeconds),
            'jti' => bin2hex(random_bytes(8)),
            'video' => [
                'roomCreate' => true,
                'roomList' => true,
                'roomAdmin' => true,
                'roomJoin' => true,
                'roomRecord' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function sign(string $apiKey, string $apiSecret, array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $apiKey];
        $h = self::b64url((string)json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = self::b64url((string)json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', $h . '.' . $p, $apiSecret, true);
        return $h . '.' . $p . '.' . self::b64url($sig);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function decodeUnverified(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $json = self::b64urlDecode($parts[1]);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public static function verify(string $jwt, string $apiSecret): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        $expected = self::b64url(hash_hmac('sha256', $parts[0] . '.' . $parts[1], $apiSecret, true));
        if (!hash_equals($expected, $parts[2])) {
            return false;
        }
        $claims = self::decodeUnverified($jwt);
        if (!is_array($claims)) {
            return false;
        }
        $exp = (int)($claims['exp'] ?? 0);
        if ($exp > 0 && $exp < (time() - 30)) {
            return false;
        }
        return true;
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($data, '-_', '+/'), true);
        return $raw === false ? '' : $raw;
    }
}
