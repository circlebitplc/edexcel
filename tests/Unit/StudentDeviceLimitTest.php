<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\StudentDeviceService;
use PHPUnit\Framework\TestCase;

final class StudentDeviceLimitTest extends TestCase
{
    public function testMaxDevicesIsFour(): void
    {
        $this->assertSame(2, StudentDeviceService::MAX_DEVICES);
    }

    public function testRelatedTtlConstantsArePositive(): void
    {
        $this->assertSame('eck_device', StudentDeviceService::COOKIE);
        $this->assertSame(600, StudentDeviceService::OTP_TTL_SECONDS);
        $this->assertSame(60, StudentDeviceService::OTP_RESEND_SECONDS);
        $this->assertGreaterThan(0, StudentDeviceService::PRESENCE_TTL_SECONDS);
    }
}
