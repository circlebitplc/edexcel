<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * AWS Signature Version 4 query-string GET (MinIO / S3-compatible).
 * Used so Bunny can fetch a private egress MP4 without PHP proxying the file.
 */
final class S3PresignedUrl
{
    /**
     * @param array{endpoint:string,bucket:string,region?:string,access_key:string,secret:string,force_path_style?:bool} $cfg
     */
    public static function getObject(array $cfg, string $key, int $expires = 7200, ?int $now = null): string
    {
        $key = self::normalizeKey($key);
        if ($key === '' || trim((string)($cfg['endpoint'] ?? '')) === '' || trim((string)($cfg['bucket'] ?? '')) === '') {
            return '';
        }
        $now = $now ?? time();
        $expires = max(60, min(604800, $expires));
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $region = trim((string)($cfg['region'] ?? '')) !== '' ? trim((string)$cfg['region']) : 'us-east-1';
        $endpoint = rtrim((string)$cfg['endpoint'], '/');
        if (!preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }
        $parts = parse_url($endpoint);
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host = (string)($parts['host'] ?? '');
        if ($host === '') {
            return '';
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        $hostHeader = strtolower($host . ($port ? ':' . $port : ''));
        $pathStyle = !empty($cfg['force_path_style']);
        $bucket = trim((string)$cfg['bucket']);
        $encodedKey = self::encodeKey($key);

        if ($pathStyle) {
            $canonicalUri = '/' . $bucket . '/' . $encodedKey;
            $urlHost = $hostHeader;
        } else {
            $canonicalUri = '/' . $encodedKey;
            $urlHost = strtolower($bucket) . '.' . $hostHeader;
        }

        $credential = $cfg['access_key'] . '/' . $dateStamp . '/' . $region . '/s3/aws4_request';
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $credential,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string)$expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $canonicalHeaders = 'host:' . $urlHost . "\n";
        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$dateStamp}/{$region}/s3/aws4_request\n" . hash('sha256', $canonicalRequest);
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $cfg['secret'], true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return $scheme . '://' . $urlHost . $canonicalUri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    public static function normalizeKey(string $key): string
    {
        $key = trim(str_replace('\\', '/', $key));
        $key = ltrim($key, '/');
        if ($key === '' || str_contains($key, '{time}')) {
            return '';
        }
        return $key;
    }

    private static function encodeKey(string $key): string
    {
        $parts = explode('/', $key);
        foreach ($parts as $i => $part) {
            $parts[$i] = rawurlencode($part);
        }
        return implode('/', $parts);
    }
}
