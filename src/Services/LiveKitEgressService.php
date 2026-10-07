<?php
declare(strict_types=1);

namespace Edexcel\Services;

use RuntimeException;

/**
 * LiveKit Egress (Twirp JSON). Room composite MP4 for class recordings.
 */
final class LiveKitEgressService
{
    public function __construct(
        private string $httpBase,
        private string $apiKey,
        private string $apiSecret
    ) {
        $this->httpBase = rtrim($httpBase, '/');
    }

    public static function fromConfig(array $cfg): self
    {
        return new self(
            (string)($cfg['http_base'] ?? ''),
            (string)($cfg['api_key'] ?? ''),
            (string)($cfg['api_secret'] ?? '')
        );
    }

    /**
     * @param array{access_key:string,secret:string,region:string,endpoint:string,bucket:string,force_path_style?:bool}|null $s3
     * @return array<string,mixed>
     */
    public function startRoomComposite(string $room, string $filepath, ?array $s3 = null): array
    {
        $file = self::encodedFileOutput($filepath, $s3);
        $body = [
            'roomName' => $room,
            'layout' => 'speaker',
            'preset' => 'H264_720P_30',
            'fileOutputs' => [$file],
        ];
        try {
            return $this->call('StartRoomCompositeEgress', $body, 25);
        } catch (RuntimeException $e) {
            unset($body['fileOutputs']);
            $body['file'] = $file;
            return $this->call('StartRoomCompositeEgress', $body, 25);
        }
    }

    /**
     * @param array{access_key:string,secret:string,region:string,endpoint:string,bucket:string,force_path_style?:bool}|null $s3
     * @return array<string,mixed>
     */
    public static function encodedFileOutput(string $filepath, ?array $s3 = null): array
    {
        $file = [
            'fileType' => 'MP4',
            'filepath' => $filepath,
        ];
        if ($s3 === null) {
            return $file;
        }
        $file['s3'] = [
            'accessKey' => (string)$s3['access_key'],
            'secret' => (string)$s3['secret'],
            'region' => trim((string)($s3['region'] ?? '')) !== '' ? (string)$s3['region'] : 'us-east-1',
            'endpoint' => (string)$s3['endpoint'],
            'bucket' => (string)$s3['bucket'],
            'forcePathStyle' => !empty($s3['force_path_style']),
        ];
        return $file;
    }

    /**
     * @return array<string,mixed>
     */
    public function stop(string $egressId): array
    {
        return $this->call('StopEgress', ['egressId' => $egressId], 20);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForRoom(string $room): array
    {
        $res = $this->call('ListEgress', ['roomName' => $room], 15);
        $items = $res['items'] ?? $res['egressInfo'] ?? $res['egress_info'] ?? [];
        if (isset($res['egressId']) || isset($res['egress_id'])) {
            return [$res];
        }
        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @return array<string,mixed>
     */
    public function get(string $egressId): array
    {
        try {
            $res = $this->call('ListEgress', ['egressId' => $egressId], 15);
            if (isset($res['egressId']) || isset($res['status'])) {
                return $res;
            }
            $items = $res['items'] ?? [];
            if (is_array($items) && isset($items[0]) && is_array($items[0])) {
                return $items[0];
            }
        } catch (RuntimeException $e) {
            // fall through
        }
        return ['egressId' => $egressId];
    }

    public static function egressIdFrom(array $info): string
    {
        return trim((string)($info['egressId'] ?? $info['egress_id'] ?? ''));
    }

    public static function isComplete(array $info): bool
    {
        $status = $info['status'] ?? '';
        if (is_int($status) || (is_string($status) && is_numeric($status))) {
            return (int)$status === 3;
        }
        $s = strtoupper((string)$status);
        return str_contains($s, 'COMPLETE');
    }

    public static function isFailed(array $info): bool
    {
        $status = $info['status'] ?? '';
        if (is_int($status) || (is_string($status) && is_numeric($status))) {
            return in_array((int)$status, [4, 5, 6], true);
        }
        $s = strtoupper((string)$status);
        return str_contains($s, 'FAIL') || str_contains($s, 'ABORT') || str_contains($s, 'LIMIT');
    }

    public static function fileLocationFromEgress(array $info): string
    {
        foreach (self::fileRows($info) as $row) {
            $loc = (string)($row['location'] ?? $row['downloadUrl'] ?? $row['download_url'] ?? '');
            if (self::isHttpUrl($loc)) {
                return $loc;
            }
        }
        return '';
    }

    public static function objectKeyFromEgress(array $info, string $bucket = ''): string
    {
        foreach (self::fileRows($info) as $row) {
            foreach (['filename', 'fileName', 'filepath', 'filePath'] as $k) {
                $fromName = self::keyFromLocationOrPath((string)($row[$k] ?? ''), $bucket);
                if ($fromName !== '') {
                    return $fromName;
                }
            }
            $fromLoc = self::keyFromLocationOrPath((string)($row['location'] ?? $row['downloadUrl'] ?? $row['download_url'] ?? ''), $bucket);
            if ($fromLoc !== '') {
                return $fromLoc;
            }
        }
        foreach (['filename', 'filepath', 'filePath', 'location'] as $k) {
            $fromTop = self::keyFromLocationOrPath((string)($info[$k] ?? ''), $bucket);
            if ($fromTop !== '') {
                return $fromTop;
            }
        }
        return '';
    }

    /**
     * HTTPS URL Bunny can fetch. Cloud often returns a public file URL; self-hosted MinIO needs a SigV4 GET.
     *
     * @param array<string,mixed> $cfg livekit_config()
     */
    public static function bunnyFetchUrl(array $info, array $cfg): string
    {
        $http = self::fileLocationFromEgress($info);
        if ($http !== '' && self::isPublicHttpsUrl($http)) {
            return $http;
        }
        $s3 = function_exists('livekit_s3_for_presign') ? livekit_s3_for_presign($cfg) : null;
        if ($s3 === null) {
            return '';
        }
        $key = self::objectKeyFromEgress($info, (string)$s3['bucket']);
        if ($key === '' && $http !== '') {
            $key = self::keyFromLocationOrPath($http, (string)$s3['bucket']);
        }
        if ($key === '') {
            return '';
        }
        return S3PresignedUrl::getObject($s3, $key, 7200);
    }

    public static function hasUsableOutput(array $info, string $bucket = ''): bool
    {
        return self::fileLocationFromEgress($info) !== ''
            || self::objectKeyFromEgress($info, $bucket) !== '';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function fileRows(array $info): array
    {
        $nested = $info['egressInfo'] ?? $info['egress_info'] ?? null;
        $rows = [];
        if (is_array($nested) && $nested !== $info) {
            $rows = array_merge($rows, self::fileRows($nested));
        }
        $buckets = [
            $info['fileResults'] ?? null,
            $info['file_results'] ?? null,
            $info['file'] ?? null,
            $info['result'] ?? null,
        ];
        foreach ($buckets as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }
            if (self::looksLikeFileRow($bucket)) {
                $rows[] = $bucket;
            }
            foreach ($bucket as $row) {
                if (is_array($row) && self::looksLikeFileRow($row)) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function looksLikeFileRow(array $row): bool
    {
        return isset($row['location'])
            || isset($row['downloadUrl'])
            || isset($row['download_url'])
            || isset($row['filename'])
            || isset($row['fileName'])
            || isset($row['filepath'])
            || isset($row['filePath']);
    }

    public static function keyFromLocationOrPath(string $loc, string $bucket = ''): string
    {
        $loc = trim($loc);
        if ($loc === '' || str_contains($loc, '{time}')) {
            return '';
        }
        if (str_starts_with($loc, 's3://')) {
            $rest = substr($loc, 5);
            $slash = strpos($rest, '/');
            if ($slash === false) {
                return '';
            }
            return S3PresignedUrl::normalizeKey(rawurldecode(substr($rest, $slash + 1)));
        }
        if (self::isHttpUrl($loc)) {
            $path = rawurldecode((string)parse_url($loc, PHP_URL_PATH));
            $path = ltrim(str_replace('\\', '/', $path), '/');
            $host = strtolower((string)parse_url($loc, PHP_URL_HOST));
            if ($bucket !== '' && (str_starts_with($host, strtolower($bucket) . '.') || $host === strtolower($bucket))) {
                return S3PresignedUrl::normalizeKey($path);
            }
            if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
                return S3PresignedUrl::normalizeKey(substr($path, strlen($bucket) + 1));
            }
            return S3PresignedUrl::normalizeKey($path);
        }
        $key = S3PresignedUrl::normalizeKey($loc);
        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            return S3PresignedUrl::normalizeKey(substr($key, strlen($bucket) + 1));
        }
        return $key;
    }

    private static function isHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://');
    }

    public static function isPublicHttpsUrl(string $url): bool
    {
        if (!str_starts_with($url, 'https://')) {
            return false;
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || $host === 'minio' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        if (!str_contains($host, '.')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return true;
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function call(string $method, array $body, int $timeout = 12): array
    {
        if ($this->httpBase === '') {
            throw new RuntimeException('LiveKit is not configured.');
        }
        $url = $this->httpBase . '/twirp/livekit.Egress/' . $method;
        $token = LiveKitTokenService::serverToken($this->apiKey, $this->apiSecret);
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not contact the recording service.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(8, $timeout),
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Could not reach LiveKit egress. ' . $err);
        }
        $json = json_decode((string)$raw, true);
        if ($code >= 400) {
            $msg = is_array($json) ? (string)($json['msg'] ?? $json['message'] ?? $raw) : (string)$raw;
            throw new RuntimeException('Recording service: ' . $msg);
        }
        return is_array($json) ? $json : [];
    }
}
