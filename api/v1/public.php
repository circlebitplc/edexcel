<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Http\ApiGuard;
use Edexcel\Http\ApiRateLimiter;
use Edexcel\Http\ApiResponse;
use Edexcel\Services\PublicCatalogueService;

header('X-API-Version: 1');
header('Cache-Control: public, max-age=60');
ApiResponse::$storeCache = false;

try {
    if (!($pdo instanceof PDO)) {
        ApiResponse::error('SERVICE_UNAVAILABLE', 'Database unavailable.', [], 503);
    }
    ApiGuard::assertTransport(['GET', 'HEAD'], 65536);
    $ip = function_exists('eck_client_ip') ? eck_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    if ($ip === '') {
        $ip = '0';
    }
    ApiRateLimiter::check('public:'.$ip, 90, 60);
    $resource = strtolower(trim((string)($_GET['resource'] ?? 'catalogue')));
    $cat = new PublicCatalogueService($pdo);
    $data = match ($resource) {
        'subjects' => ['subjects' => $cat->subjects()],
        'programmes' => ['programmes' => $cat->programmes()],
        'teachers' => ['teachers' => $cat->teachers()],
        'classes', 'class-availability' => ['classes' => $cat->classes()],
        default => $cat->catalogue(),
    };
    ApiResponse::success($data, ['public' => true, 'note' => 'Public catalogue only. No student or parent records are included.']);
} catch (RuntimeException $e) {
    $status = str_contains(strtolower($e->getMessage()), 'rate') ? 429 : 400;
    ApiResponse::error($status === 429 ? 'RATE_LIMITED' : 'BAD_REQUEST', $e->getMessage(), [], $status);
} catch (Throwable $e) {
    ApiResponse::error('SERVER_ERROR', 'Public catalogue unavailable.', [], 500);
}
