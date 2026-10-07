<?php
declare(strict_types=1);

namespace Edexcel\Http;

use Throwable;

final class ApiResponse
{
    /** When true, responses are not stored by shared caches. Public catalogue turns this off. */
    public static bool $storeCache = true;

    /** @param array<string,mixed> $data @param array<string,mixed> $meta */
    public static function success(array $data = [], array $meta = [], int $status = 200): never
    {
        self::send(['success'=>true,'data'=>$data,'meta'=>$meta], $status);
    }

    /** @param array<string,mixed> $details */
    public static function error(string $code, string $message, array $details = [], int $status = 400): never
    {
        self::$storeCache = true;
        self::send(['success'=>false,'error'=>['code'=>$code,'message'=>$message,'details'=>$details]], $status);
    }

    public static function fromThrowable(Throwable $e, ?\Edexcel\Services\AppLogger $logger = null): never
    {
        if ($logger) $logger->error('api_exception', $e->getMessage(), ['exception'=>get_class($e)], 'api');
        self::error('INTERNAL_ERROR', 'An unexpected error occurred.', [], 500);
    }

    /** @param array<string,mixed> $payload */
    public static function raw(array $payload, int $status = 200): never
    {
        self::send($payload, $status);
    }

    /** @param array<string,mixed> $payload */
    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            if (self::$storeCache) {
                header('Cache-Control: no-store');
            }
            header('X-Request-Id: '.\Edexcel\Services\AppLogger::requestId());
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
}
