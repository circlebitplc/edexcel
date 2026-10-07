<?php
declare(strict_types=1);

namespace Edexcel\Http;

use RuntimeException;

final class ApiRateLimiter
{
    public static function check(string $bucket,int $max=120,int $window=60): void
    {
        if(!AbuseGuard::allow('api',$bucket,$max,$window,$window)){
            if(!headers_sent()) header('Retry-After: '.$window);
            throw new RuntimeException('Rate limit exceeded.');
        }
    }
}
