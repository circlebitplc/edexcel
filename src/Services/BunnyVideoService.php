<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Bunny Stream API (video.bunnycdn.com).
 * Authentication: AccessKey header = Stream library API key (not the account key).
 *
 * Uploads: create video server-side, then return a TUS signature so the browser
 * uploads directly to https://video.bunnycdn.com/tusupload.
 * Signature: SHA256(libraryId + apiKey + expiration + videoId)
 *
 * Embed tokens: SHA256_HEX(token_security_key + videoId + expires)
 */
final class BunnyVideoService
{
    private string $libraryId;
    private string $apiKey;
    private string $cdnHostname;
    private string $tokenKey;
    private string $webhookSecret;
    private string $apiBase;
    private string $embedBase;
    private int $playbackTtl;

    /**
     * @param array<string,mixed>|null $config Stream library config (per-teacher). Null uses global settings (legacy).
     */
    public function __construct(?PDO $pdo = null, ?array $config = null)
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/config/bunny.php';
        $this->applyConfig($config ?? bunny_config($pdo));
    }

    /**
     * Stream client for this teacher's unique Bunny library.
     */
    public static function forTeacher(PDO $pdo, int $teacherId): self
    {
        $libraries = new TeacherBunnyLibraryService($pdo);
        $row = $libraries->credentialsForTeacher($teacherId);
        if ($row === null) {
            throw new RuntimeException(
                'This teacher does not have a Bunny Stream library yet. An administrator must assign a unique library ID on Teachers → Edit.'
            );
        }
        return new self($pdo, $libraries->streamConfig($row));
    }

    public static function tryForTeacher(PDO $pdo, int $teacherId): ?self
    {
        try {
            $client = self::forTeacher($pdo, $teacherId);
            return $client->isConfigured() ? $client : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Stream client for the library stored on an asset (playback / webhook / cron).
     */
    public static function forLibraryId(PDO $pdo, string $libraryId): self
    {
        $libraries = new TeacherBunnyLibraryService($pdo);
        $row = $libraries->credentialsForLibraryId($libraryId);
        if ($row === null) {
            throw new RuntimeException('No teacher is assigned Bunny library ID ' . trim($libraryId) . '.');
        }
        return new self($pdo, $libraries->streamConfig($row));
    }

    public static function tryForLibraryId(PDO $pdo, string $libraryId): ?self
    {
        try {
            $client = self::forLibraryId($pdo, $libraryId);
            return $client->isConfigured() ? $client : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function tryForStoredVideo(PDO $pdo, string $videoId): ?self
    {
        $libraries = new TeacherBunnyLibraryService($pdo);
        $row = $libraries->credentialsForStoredVideo($videoId);
        if ($row === null) {
            return null;
        }
        $client = new self($pdo, $libraries->streamConfig($row));
        return $client->isConfigured() ? $client : null;
    }

    /**
     * @param array<string,mixed> $cfg
     */
    private function applyConfig(array $cfg): void
    {
        $this->libraryId = trim((string)($cfg['library_id'] ?? ''));
        $this->apiKey = trim((string)($cfg['api_key'] ?? ''));
        $cdn = preg_replace('#^https?://#i', '', (string)($cfg['cdn_hostname'] ?? '')) ?? '';
        $this->cdnHostname = rtrim($cdn, '/');
        $tokenKey = trim((string)($cfg['token_key'] ?? ''));
        $webhookSecret = trim((string)($cfg['webhook_secret'] ?? ''));
        $this->tokenKey = $tokenKey !== '' ? $tokenKey : $this->apiKey;
        $this->webhookSecret = $webhookSecret !== '' ? $webhookSecret : $this->apiKey;
        $this->apiBase = rtrim((string)($cfg['api_base'] ?? 'https://video.bunnycdn.com'), '/');
        $this->embedBase = rtrim((string)($cfg['embed_base'] ?? 'https://iframe.mediadelivery.net'), '/');
        $ttl = (int)($cfg['playback_ttl'] ?? 7200);
        $this->playbackTtl = $ttl < 300 ? 7200 : $ttl;
    }

    public function isConfigured(): bool
    {
        return $this->libraryId !== '' && $this->apiKey !== '';
    }

    public function libraryId(): string
    {
        return $this->libraryId;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Library ID and API key are required.'];
        }
        try {
            $this->request('GET', '/library/' . rawurlencode($this->libraryId) . '/videos?page=1&itemsPerPage=1');
            return ['ok' => true, 'message' => 'Connection successful. Bunny Stream accepted the library credentials.'];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function createVideo(string $title, ?string $collectionId = null): array
    {
        $payload = ['title' => $title];
        if ($collectionId !== null && $collectionId !== '') {
            $payload['collectionId'] = $collectionId;
        }
        $data = $this->request('POST', '/library/' . rawurlencode($this->libraryId) . '/videos', $payload);
        $guid = (string)($data['guid'] ?? '');
        if ($guid === '') {
            throw new RuntimeException('Bunny did not return a video ID.');
        }
        return $data;
    }

    /**
     * Ask Bunny to pull a remote MP4 (LiveKit egress file URL).
     *
     * @return array<string,mixed>
     */
    public function fetchFromUrl(string $videoId, string $sourceUrl): array
    {
        $sourceUrl = trim($sourceUrl);
        if ($videoId === '' || !str_starts_with($sourceUrl, 'http')) {
            throw new RuntimeException('Missing video file URL.');
        }
        return $this->request(
            'POST',
            '/library/' . rawurlencode($this->libraryId) . '/videos/' . rawurlencode($videoId) . '/fetch',
            ['url' => $sourceUrl],
            60
        );
    }

    /**
     * Presigned TUS headers for browser-to-Bunny upload. Does not include the API key.
     *
     * @return array{libraryId:string,videoId:string,expiration:int,signature:string,endpoint:string}
     */
    public function tusAuthorization(string $videoId, int $ttlSeconds = 3600): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Bunny Stream is not configured.');
        }
        $expiration = time() + max(300, $ttlSeconds);
        $signature = hash('sha256', $this->libraryId . $this->apiKey . $expiration . $videoId);
        return [
            'libraryId' => $this->libraryId,
            'videoId' => $videoId,
            'expiration' => $expiration,
            'signature' => $signature,
            'endpoint' => 'https://video.bunnycdn.com/tusupload',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getVideo(string $videoId, int $timeoutSeconds = 30): array
    {
        return $this->request('GET', '/library/' . rawurlencode($this->libraryId) . '/videos/' . rawurlencode($videoId), null, $timeoutSeconds);
    }

    public function deleteVideo(string $videoId): void
    {
        $this->request('DELETE', '/library/' . rawurlencode($this->libraryId) . '/videos/' . rawurlencode($videoId));
    }

    public function signedEmbedUrl(string $videoId, ?int $ttlSeconds = null): string
    {
        $expires = time() + max(300, $ttlSeconds ?? $this->playbackTtl);
        $token = hash('sha256', $this->tokenKey . $videoId . $expires);
        // responsive=false: we already wrap the iframe in a 16:9 box. Bunny's
        // responsive mode adds a second navy “Processing video” panel under the player.
        return $this->embedBase . '/embed/' . rawurlencode($this->libraryId) . '/' . rawurlencode($videoId)
            . '?token=' . rawurlencode($token)
            . '&expires=' . $expires
            . '&autoplay=false&loop=false&muted=false&preload=true&responsive=false&playsinline=true';
    }

    public function thumbnailUrl(string $videoId, ?string $fileName = null): string
    {
        if ($this->cdnHostname === '') {
            return '';
        }
        $file = $fileName !== null && $fileName !== '' ? $fileName : 'thumbnail.jpg';
        return 'https://' . $this->cdnHostname . '/' . rawurlencode($videoId) . '/' . ltrim($file, '/');
    }

    public function verifyWebhookSignature(string $rawBody, string $headerSignature): bool
    {
        if ($headerSignature === '') {
            return false;
        }
        $header = strtolower(trim($headerSignature));
        foreach ([$this->webhookSecret, $this->apiKey] as $secret) {
            if ($secret === '') {
                continue;
            }
            $expected = hash_hmac('sha256', $rawBody, $secret);
            if (hash_equals($expected, $header)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Encoding status from a Stream API or webhook payload.
     *
     * @param array<string,mixed> $video
     */
    public static function statusFromPayload(array $video): int
    {
        foreach (['status', 'Status'] as $key) {
            if (array_key_exists($key, $video) && $video[$key] !== null && $video[$key] !== '') {
                return (int)$video[$key];
            }
        }
        return -1;
    }

    /**
     * Map Bunny integer status to our recording status, or null to leave unchanged.
     * 3 = fully encoded. 4 = a resolution finished (video is already playable).
     * 9/10 are captions/title events and must not roll a ready video back to processing.
     */
    public static function mapStatus(int $bunnyStatus): ?string
    {
        return match ($bunnyStatus) {
            3, 4 => 'ready',
            5, 8 => 'failed',
            6 => 'uploading',
            0, 1, 2, 7 => 'processing',
            default => null,
        };
    }

    public static function processingLabel(int $bunnyStatus): string
    {
        return match ($bunnyStatus) {
            0 => 'queued',
            1 => 'processing',
            2 => 'encoding',
            3 => 'finished',
            4 => 'resolution_finished',
            5 => 'failed',
            6 => 'upload_started',
            7 => 'upload_finished',
            8 => 'upload_failed',
            9 => 'captions',
            10 => 'title_generated',
            default => 'unknown',
        };
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, int $timeoutSeconds = 30): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Bunny Stream is not configured.');
        }
        $url = $this->apiBase . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start Bunny Stream request.');
        }
        $headers = [
            'AccessKey: ' . $this->apiKey,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => max(5, $timeoutSeconds),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Bunny Stream connection failed.');
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($http === 401 || $http === 403) {
            throw new RuntimeException('Bunny rejected the library API key. Use the Stream library AccessKey, not the account key.');
        }
        if ($http >= 400) {
            $msg = is_array($decoded) ? (string)($decoded['Message'] ?? $decoded['title'] ?? $raw) : (string)$raw;
            if (strlen($msg) > 240) {
                $msg = substr($msg, 0, 240);
            }
            throw new RuntimeException('Bunny Stream HTTP ' . $http . ($msg !== '' ? ': ' . $msg : ''));
        }
        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }
}
