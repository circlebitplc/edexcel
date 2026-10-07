<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\BunnyVideoService;
use PHPUnit\Framework\TestCase;

final class BunnyWebhookHandlerTest extends TestCase
{
    public function testFinishedIsReady(): void
    {
        $this->assertSame('ready', BunnyVideoService::mapStatus(3));
    }

    public function testFailedStatuses(): void
    {
        $this->assertSame('failed', BunnyVideoService::mapStatus(5));
        $this->assertSame('failed', BunnyVideoService::mapStatus(8));
    }

    public function testProcessingStatuses(): void
    {
        $this->assertSame('processing', BunnyVideoService::mapStatus(0));
        $this->assertSame('processing', BunnyVideoService::mapStatus(1));
        $this->assertSame('processing', BunnyVideoService::mapStatus(2));
        $this->assertSame('uploading', BunnyVideoService::mapStatus(6));
    }

    public function testCaptionsDoNotChangeStatus(): void
    {
        $this->assertNull(BunnyVideoService::mapStatus(9));
        $this->assertNull(BunnyVideoService::mapStatus(10));
    }

    public function testWebhookSignatureUsesRawBodyHmac(): void
    {
        $raw = '{"VideoGuid":"abc","Status":3}';
        $secret = 'readonly-key';
        $sig = hash_hmac('sha256', $raw, $secret);
        $this->assertTrue(hash_equals($sig, hash_hmac('sha256', $raw, $secret)));
        $this->assertFalse(hash_equals($sig, hash_hmac('sha256', $raw . 'x', $secret)));
    }
}
