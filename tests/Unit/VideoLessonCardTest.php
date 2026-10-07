<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\OnlineLessonService;
use PHPUnit\Framework\TestCase;

final class VideoLessonCardTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Services/StudentLessonFeeService.php';
        require_once dirname(__DIR__, 2) . '/src/Services/OnlineLessonService.php';
    }

    public function testFeeUnlockMatchesClassPaymentRules(): void
    {
        $this->assertSame('ACCESS_GRANTED', OnlineLessonService::feeUnlockState(['status' => 'paid', 'covered_by_monthly' => false]));
        $this->assertSame('ACCESS_GRANTED', OnlineLessonService::feeUnlockState(['status' => 'waived', 'covered_by_monthly' => false]));
        $this->assertSame('ACCESS_GRANTED', OnlineLessonService::feeUnlockState(['status' => 'unpaid', 'covered_by_monthly' => true]));
        $this->assertSame('PAYMENT_PENDING', OnlineLessonService::feeUnlockState(['status' => 'pending', 'covered_by_monthly' => false]));
        $this->assertSame('PAYMENT_REQUIRED', OnlineLessonService::feeUnlockState(['status' => 'unpaid', 'covered_by_monthly' => false]));
    }

    public function testLessonStaysHiddenUntilClassEndsWhenThatOptionIsOn(): void
    {
        $timetable = ['date' => '2026-09-26', 'end_time' => '11:00:00'];
        $end = strtotime('2026-09-26 11:00:00');
        $before = OnlineLessonService::availabilityWindow(
            ['available_after_class' => 1, 'close_after_days' => 0],
            $timetable,
            $end - 1800
        );
        $after = OnlineLessonService::availabilityWindow(
            ['available_after_class' => 1, 'close_after_days' => 0],
            $timetable,
            $end + 60
        );
        $always = OnlineLessonService::availabilityWindow(
            ['available_after_class' => 0, 'close_after_days' => 0],
            $timetable,
            $end - 1800
        );
        $this->assertFalse($before['open']);
        $this->assertTrue($after['open']);
        $this->assertTrue($always['open']);
    }

    public function testPhysicalClassesCanHaveVideoLessons(): void
    {
        $this->assertTrue(OnlineLessonService::supportsDeliveryMode('physical'));
        $this->assertTrue(OnlineLessonService::supportsDeliveryMode('online'));
        $this->assertTrue(OnlineLessonService::supportsDeliveryMode('hybrid'));
        $this->assertSame('1h 12m', OnlineLessonService::formatActiveTime(4320));
    }

    public function testItemKindsCoverPublishedActivityTypes(): void
    {
        $this->assertSame('Video', OnlineLessonService::itemKindLabel(['item_type' => 'video']));
        $this->assertSame('Text page', OnlineLessonService::itemKindLabel(['item_type' => 'page']));
        $this->assertSame('MCQ', OnlineLessonService::itemKindLabel(['item_type' => 'activity', 'activity_type' => 'mcq']));
        $this->assertSame('Essay', OnlineLessonService::itemKindLabel(['item_type' => 'activity', 'activity_type' => 'essay']));
        $this->assertSame('External link', OnlineLessonService::itemKindLabel(['item_type' => 'external_link']));
    }
}
