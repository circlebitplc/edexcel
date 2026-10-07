<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentControlsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/config/payment_controls.php';
    }

    public function testTeacherCannotRecordOnlinePaymentWhenSwitchIsOff(): void
    {
        $online = ['delivery_mode' => 'online'];
        $hybrid = ['delivery_mode' => 'hybrid'];
        $physical = ['delivery_mode' => 'physical'];

        $this->assertFalse(teacher_manual_payment_allowed(false, false, $online));
        $this->assertFalse(teacher_manual_payment_allowed(false, false, $hybrid));
        $this->assertTrue(teacher_manual_payment_allowed(false, true, $online));
        $this->assertTrue(teacher_manual_payment_allowed(false, false, $physical));
        $this->assertTrue(teacher_manual_payment_allowed(true, false, $online));
        $this->assertTrue(teacher_manual_payment_allowed(true, false, $physical));
    }

    public function testManualAmountMustMatchTheClassFee(): void
    {
        $this->assertTrue(manual_payment_amount_matches(500.0, 500.0));
        $this->assertTrue(manual_payment_amount_matches(500.004, 500.0));
        $this->assertFalse(manual_payment_amount_matches(100.0, 500.0));
        $this->assertFalse(manual_payment_amount_matches(-1.0, 500.0));
    }

    public function testManualMethodIsLimited(): void
    {
        $this->assertSame('cash', manual_payment_method(' cash '));
        $this->assertSame('bank', manual_payment_method('bank'));
        $this->assertSame('other', manual_payment_method('other'));
        $this->expectException(RuntimeException::class);
        manual_payment_method('onepay');
    }

    public function testPaymentDateMustBeARealPastOrTodayDate(): void
    {
        $this->assertSame('2026-09-26', manual_payment_date('2026-09-26', '2026-09-26'));
        $this->assertSame('2026-01-02', manual_payment_date('2026-01-02', '2026-09-26'));
        try {
            manual_payment_date('2026-09-27', '2026-09-26');
            $this->fail('A future payment date should be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('future', $e->getMessage());
        }
        try {
            manual_payment_date('not-a-date', '2026-09-26');
            $this->fail('An invalid payment date should be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('date', strtolower($e->getMessage()));
        }
    }

    public function testRecordedMethodShowsOnHistoryWithoutChangingTheGateway(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Services/StudentLessonFeeService.php';
        $bank = \Edexcel\Services\StudentLessonFeeService::displayMethod([
            'gateway' => 'cash',
            'gateway_response' => json_encode(['manual_method' => 'bank']),
        ]);
        $card = \Edexcel\Services\StudentLessonFeeService::displayMethod([
            'gateway' => 'onepay',
            'gateway_response' => json_encode(['manual_method' => 'bank']),
        ]);
        $this->assertSame('bank', $bank);
        $this->assertSame('onepay', $card);
        $this->assertSame('Bank', \Edexcel\Services\StudentLessonFeeService::gatewayLabel($bank));
        $manual = \Edexcel\Services\StudentLessonFeeService::displayMethod([
            'gateway' => 'cash',
            'gateway_response' => json_encode(['manual_method' => 'manual']),
        ]);
        $this->assertSame('manual', $manual);
        $this->assertSame('Teacher Manual Payment', \Edexcel\Services\StudentLessonFeeService::gatewayLabel($manual));
    }
}
