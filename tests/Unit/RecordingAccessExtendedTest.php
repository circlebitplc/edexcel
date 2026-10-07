<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\RecordingAccessService;
use PHPUnit\Framework\TestCase;

final class RecordingAccessExtendedTest extends TestCase
{
    /** @return array<string,mixed> */
    private function readyBase(): array
    {
        return [
            'authenticated' => true,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
        ];
    }

    public function testPendingFeeReturnsPaymentPending(): void
    {
        $state = RecordingAccessService::decide($this->readyBase() + [
            'fee_status' => 'pending',
        ]);
        $this->assertSame(RecordingAccessService::PAYMENT_PENDING, $state);
    }

    public function testPaymentPendingFlag(): void
    {
        $state = RecordingAccessService::decide($this->readyBase() + [
            'fee_status' => 'unpaid',
            'payment_pending' => true,
        ]);
        $this->assertSame(RecordingAccessService::PAYMENT_PENDING, $state);
    }

    public function testPartialRequiresPayment(): void
    {
        $state = RecordingAccessService::decide($this->readyBase() + [
            'fee_status' => 'partial',
        ]);
        $this->assertSame(RecordingAccessService::PAYMENT_REQUIRED, $state);
    }

    public function testUploadingAndDraftAreProcessing(): void
    {
        foreach (['uploading', 'draft', 'processing'] as $status) {
            $state = RecordingAccessService::decide(array_merge($this->readyBase(), [
                'recording_status' => $status,
                'fee_status' => 'paid',
            ]));
            $this->assertSame(RecordingAccessService::RECORDING_PROCESSING, $state, $status);
        }
    }

    public function testUnauthenticatedDenied(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => false,
            'recording_exists' => true,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(RecordingAccessService::NOT_AUTHORIZED, $state);
    }

    public function testMissingRecordingDenied(): void
    {
        $state = RecordingAccessService::decide([
            'authenticated' => true,
            'recording_exists' => false,
            'recording_status' => 'ready',
            'lesson_exists' => true,
            'enrolled' => true,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(RecordingAccessService::NOT_AUTHORIZED, $state);
    }

    public function testTeacherCanManageLessonIsolation(): void
    {
        $this->assertTrue(RecordingAccessService::teacherCanManageLesson(5, 5, false));
        $this->assertTrue(RecordingAccessService::teacherCanManageLesson(9, 5, false, 9));
        $this->assertFalse(RecordingAccessService::teacherCanManageLesson(9, 5, false, 0));
        $this->assertTrue(RecordingAccessService::teacherCanManageLesson(0, 5, true));
        $this->assertFalse(RecordingAccessService::teacherCanManageLesson(0, 5, false));
    }

    public function testTeacherOwnsLibraryItem(): void
    {
        $this->assertTrue(RecordingAccessService::teacherOwnsLibraryItem(3, 3, false));
        $this->assertFalse(RecordingAccessService::teacherOwnsLibraryItem(3, 8, false));
        $this->assertTrue(RecordingAccessService::teacherOwnsLibraryItem(3, 8, true));
    }

    public function testStudentMessagesForStates(): void
    {
        $this->assertStringContainsString('watch', strtolower(RecordingAccessService::studentMessage(
            RecordingAccessService::ACCESS_GRANTED
        )));
        $this->assertStringContainsString('processed', strtolower(RecordingAccessService::studentMessage(
            RecordingAccessService::RECORDING_PROCESSING
        )));
        $this->assertStringContainsString('unavailable', strtolower(RecordingAccessService::studentMessage(
            RecordingAccessService::RECORDING_UNAVAILABLE
        )));
    }
}
