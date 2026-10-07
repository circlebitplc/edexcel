<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\RecordingAccessService;
use PHPUnit\Framework\TestCase;

final class RecordingAccessServiceTest extends TestCase
{
    public function testAbsentPaidReadyIsGranted(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
            'attendance' => 'absent',
        ]);
        $this->assertSame(RecordingAccessService::ACCESS_GRANTED, $state);
    }

    public function testPresentUnpaidRequiresPayment(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'unpaid',
        ]);
        $this->assertSame(RecordingAccessService::PAYMENT_REQUIRED, $state);
    }

    public function testLateAndExcusedDoNotAffectAccess(): void
    {
        foreach (['late', 'excused', 'present', 'absent'] as $att) {
            $granted = RecordingAccessService::decide([
                'authenticated' => true,
                'recording_exists' => true,
                'recording_status' => 'ready',
                'lesson_exists' => true,
                'enrolled' => true,
                'fee_status' => 'paid',
                'attendance' => $att,
            ]);
            $this->assertSame(RecordingAccessService::ACCESS_GRANTED, $granted);
        }
    }

    public function testWrongStudentDenied(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => false,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(RecordingAccessService::NOT_AUTHORIZED, $state);
    }

    public function testProcessingCannotWatch(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'processing',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(RecordingAccessService::RECORDING_PROCESSING, $state);
    }

    public function testFailedUnavailable(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'failed',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(RecordingAccessService::RECORDING_UNAVAILABLE, $state);
    }

    public function testDeletedLessonDenied(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'lesson_deleted' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(RecordingAccessService::NOT_AUTHORIZED, $state);
    }

    public function testWaivedIsGranted(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'waived',
        ]);
        $this->assertSame(RecordingAccessService::ACCESS_GRANTED, $state);
    }
}
