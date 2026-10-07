<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\RecordingAccessService;
use PHPUnit\Framework\TestCase;

final class StudentRecordingAuthorizationTest extends TestCase
{
    public function testPaidEqualsAccess(): void
    {
        $this->assertSame(
            RecordingAccessService::ACCESS_GRANTED,
            RecordingAccessService::decide($this->ctx(['fee_status' => 'paid']))
        );
    }

    public function testUnpaidEqualsPaymentRequired(): void
    {
        $this->assertSame(
            RecordingAccessService::PAYMENT_REQUIRED,
            RecordingAccessService::decide($this->ctx(['fee_status' => 'unpaid']))
        );
    }

    public function testUnauthorizedStudentDeniedEvenIfPaid(): void
    {
        $this->assertSame(
            RecordingAccessService::NOT_AUTHORIZED,
            RecordingAccessService::decide($this->ctx(['enrolled' => false, 'fee_status' => 'paid']))
        );
    }

    public function testMissingRecordingDenied(): void
    {
        $this->assertSame(
            RecordingAccessService::NOT_AUTHORIZED,
            RecordingAccessService::decide($this->ctx(['recording_exists' => false]))
        );
    }

    /**
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function ctx(array $override): array
    {
        return array_merge([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
        ], $override);
    }
}
