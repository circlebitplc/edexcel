<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\OnePayCallbackHandler;
use Edexcel\Services\OnePayService;
use Edexcel\Services\PaymentVerificationService;
use PHPUnit\Framework\TestCase;

final class OnePayCallbackHandlerTest extends TestCase
{
    public function testSuccessCallbackDetection(): void
    {
        $this->assertTrue(OnePayCallbackHandler::isSuccessCallback([
            'status' => 1,
            'status_message' => 'SUCCESS',
        ]));
        $this->assertFalse(OnePayCallbackHandler::isSuccessCallback([
            'status' => 0,
            'status_message' => 'FAILED',
        ]));
    }

    public function testHashUsesConcatenatedSha256WithTwoDecimals(): void
    {
        $this->assertSame(
            hash('sha256', 'APPIDLKR100.00SALT'),
            OnePayService::hashFor('APPID', 'LKR', 100, 'SALT')
        );
        $this->assertNotSame(
            OnePayService::hashFor('APPID', 'LKR', '100.00', 'SALT'),
            OnePayService::hashFor('APPID', 'LKR', '1.00', 'SALT')
        );
    }

    public function testDuplicatePaidDoesNotCreateSecondCharge(): void
    {
        $this->assertTrue(PaymentVerificationService::isIdempotentPaid('paid'));
        $this->assertFalse(PaymentVerificationService::canMarkPaid('paid'));
    }

    public function testRefundedCallbackCannotBeReMarkedPaid(): void
    {
        // applyVerifiedStatus short-circuits refunded rows; verification helpers enforce that.
        $this->assertFalse(PaymentVerificationService::canMarkPaid('refunded'));
        $this->assertFalse(PaymentVerificationService::isIdempotentPaid('refunded'));
        $this->assertFalse(OnePayCallbackHandler::isSuccessCallback([
            'status' => 0,
            'status_message' => 'REFUNDED',
        ]));
    }

    public function testFailedAndCancelledAreNotSuccessCallbacks(): void
    {
        $this->assertFalse(OnePayCallbackHandler::isSuccessCallback([
            'status' => 0,
            'status_message' => 'FAILED',
        ]));
        $this->assertFalse(OnePayCallbackHandler::isSuccessCallback([
            'status' => 0,
            'status_message' => 'CANCELLED',
        ]));
        $this->assertTrue(OnePayCallbackHandler::isSuccessCallback([
            'status' => 0,
            'status_message' => 'SUCCESS',
        ]));
    }

    public function testStatusAmountReadsNestedNetAmount(): void
    {
        $this->assertFalse(OnePayService::isPaidFlag(200));
        $this->assertTrue(OnePayService::isPaidFlag(true));
        $this->assertSame(200.0, OnePayService::amountFromPayload([
            'amount' => ['net_amount' => '200.00', 'currency' => 'LKR'],
        ]));
    }
}
