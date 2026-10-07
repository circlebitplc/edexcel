<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class BunnyWebhookHandler
{
    public function __construct(
        private PDO $pdo,
        private RecordingService $recordings
    ) {
        date_default_timezone_set('Asia/Colombo');
    }

    /**
     * @return array{ok:bool,updated:bool,message:string}
     */
    public function handle(string $rawBody, string $signature): array
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'updated' => false, 'message' => 'invalid json'];
        }

        $libraryId = trim((string)($payload['VideoLibraryId'] ?? $payload['videoLibraryId'] ?? ''));
        $videoId = (string)($payload['VideoGuid'] ?? $payload['videoGuid'] ?? '');
        $statusInt = BunnyVideoService::statusFromPayload($payload);

        $bunny = null;
        if ($libraryId !== '') {
            $bunny = BunnyVideoService::tryForLibraryId($this->pdo, $libraryId);
        }
        if ($bunny === null && $videoId !== '') {
            $bunny = BunnyVideoService::tryForStoredVideo($this->pdo, $videoId);
        }

        $sigOk = $bunny !== null && $bunny->verifyWebhookSignature($rawBody, $signature);
        if (!$sigOk) {
            return ['ok' => false, 'updated' => false, 'message' => 'invalid signature'];
        }
        if ($bunny === null) {
            return ['ok' => false, 'updated' => false, 'message' => 'unknown library'];
        }
        if ($videoId === '') {
            return ['ok' => false, 'updated' => false, 'message' => 'missing video'];
        }

        $mapped = $statusInt >= 0 ? BunnyVideoService::mapStatus($statusInt) : null;
        $video = [];
        try {
            $video = $bunny->getVideo($videoId);
        } catch (Throwable $e) {
            if ($mapped === null) {
                return ['ok' => true, 'updated' => false, 'message' => 'ignored'];
            }
            $video = ['status' => $statusInt, 'guid' => $videoId];
        }

        $this->recordings->applyBunnyMetadata($videoId, $video, $bunny);

        return ['ok' => true, 'updated' => true, 'message' => $mapped ?? 'ignored'];
    }
}
