<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\PaymentVerificationService;
use Edexcel\Services\StudentLessonFeeService;
use PHPUnit\Framework\TestCase;

final class PaymentVerificationServiceTest extends TestCase
{
    public function testPaidUnlocksAndUnpaidDoesNot(): void
    {
        $this->assertTrue(PaymentVerificationService::isIdempotentPaid('paid'));
        $this->assertFalse(PaymentVerificationService::isIdempotentPaid('pending'));
        $this->assertTrue(PaymentVerificationService::canMarkPaid('pending'));
        $this->assertFalse(PaymentVerificationService::canMarkPaid('paid'));
    }

    public function testTamperedAmountRejected(): void
    {
        $this->assertFalse(PaymentVerificationService::amountsMatch(500, 1));
        $this->assertTrue(PaymentVerificationService::amountsMatch(500, '500.00'));
        $this->assertTrue(PaymentVerificationService::amountsMatch(500.00, 500));
    }

    public function testTamperedStudentAndLessonRejected(): void
    {
        $this->assertFalse(PaymentVerificationService::studentMatches(10, 11));
        $this->assertTrue(PaymentVerificationService::studentMatches(10, 10));
        $this->assertFalse(PaymentVerificationService::lessonMatches(4, 9));
        $this->assertTrue(PaymentVerificationService::lessonMatches(4, 4));
        $this->assertSame(10, PaymentVerificationService::studentIdFromPayload(['student_id' => '10']));
        $this->assertNull(PaymentVerificationService::studentIdFromPayload(['status' => 1]));
        $this->assertFalse(PaymentVerificationService::studentMatches(
            10,
            PaymentVerificationService::studentIdFromPayload(['student_id' => 11]) ?? 0
        ));
    }

    public function testCurrencyMustMatch(): void
    {
        $this->assertTrue(PaymentVerificationService::currenciesMatch('LKR', 'lkr'));
        $this->assertFalse(PaymentVerificationService::currenciesMatch('LKR', 'USD'));
    }

    public function testDuplicatePaidIsIdempotent(): void
    {
        $this->assertTrue(PaymentVerificationService::isIdempotentPaid('paid'));
        $this->assertTrue(PaymentVerificationService::isIdempotentPaid('PAID'));
        $this->assertFalse(PaymentVerificationService::canMarkPaid('paid'));
    }

    public function testRefundedStatusIsTerminalAndNotMarkablePaid(): void
    {
        $this->assertFalse(PaymentVerificationService::isIdempotentPaid('refunded'));
        $this->assertFalse(PaymentVerificationService::canMarkPaid('refunded'));
        $this->assertFalse(PaymentVerificationService::canMarkPaid('REFUNDED'));
    }

    public function testMonthlyLedgerCoversOnlyPresentOrLatePaidMonths(): void
    {
        $this->assertTrue(StudentLessonFeeService::isCoveredByMonthlyLedger('present', 'paid'));
        $this->assertTrue(StudentLessonFeeService::isCoveredByMonthlyLedger('late', 'waived'));
        $this->assertFalse(StudentLessonFeeService::isCoveredByMonthlyLedger('absent', 'paid'));
        $this->assertFalse(StudentLessonFeeService::isCoveredByMonthlyLedger('excused', 'paid'));
        $this->assertFalse(StudentLessonFeeService::isCoveredByMonthlyLedger('present', 'due'));
    }
}
