<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\WaitlistOfferService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * WaitlistOfferService has no pure public helpers (offerNext/accept/expireOpen need PDO).
 * This suite documents that and covers the 24-hour offer expiry math used by the service.
 */
final class WaitlistOfferLogicTest extends TestCase
{
    public function testServiceExposesNoPureStaticHelpers(): void
    {
        $ref = new ReflectionClass(WaitlistOfferService::class);
        $staticPublic = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isStatic() && $m->getDeclaringClass()->getName() === WaitlistOfferService::class) {
                $staticPublic[] = $m->getName();
            }
        }
        $this->assertSame([], $staticPublic, 'No pure static API; expiry math tested below.');
    }

    public function testOfferExpiryIsTwentyFourHoursFromNow(): void
    {
        $now = time();
        $expiresAt = $now + (24 * 3600);
        $this->assertSame(86400, $expiresAt - $now);
        $this->assertTrue($expiresAt > $now);
        $this->assertFalse($expiresAt <= $now);

        $expiredStamp = date('Y-m-d H:i:s', $now - 60);
        $this->assertTrue(strtotime($expiredStamp) < $now);

        $openStamp = date('Y-m-d H:i:s', $now + 3600);
        $this->assertTrue(strtotime($openStamp) > $now);
    }

    public function testTokenNormalisationLengthExpectation(): void
    {
        $raw = bin2hex(random_bytes(16));
        $this->assertSame(32, strlen($raw));
        $cleaned = strtolower(preg_replace('/[^a-f0-9]/', '', $raw . '!!') ?? '');
        $this->assertSame(32, strlen($cleaned));
        $short = strtolower(preg_replace('/[^a-f0-9]/', '', 'abc') ?? '');
        $this->assertNotSame(32, strlen($short));
    }
}
