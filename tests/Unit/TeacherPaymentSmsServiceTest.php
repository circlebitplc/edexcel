<?php
declare(strict_types=1);

namespace {
    if (!function_exists('lesson_duration_minutes')) {
        function lesson_duration_minutes(string $startTime, string $endTime): int
        {
            $start = strtotime($startTime);
            $end = strtotime($endTime);
            if ($start === false || $end === false) {
                return 0;
            }
            if ($end < $start) {
                $end += 86400;
            }
            return max(0, (int)round(($end - $start) / 60));
        }
    }

    if (!function_exists('lesson_rate_per_student')) {
        function lesson_rate_per_student(int $durationMinutes): float
        {
            $hours = $durationMinutes / 60;
            if ($hours <= 2.5) {
                return 500.0;
            }
            if ($hours <= 3.5) {
                return 700.0;
            }
            if ($hours <= 4.5) {
                return 900.0;
            }
            return 1100.0;
        }
    }
}

namespace Edexcel\Tests\Unit {

use Edexcel\Services\TeacherPaymentSmsService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TeacherPaymentSmsServiceTest extends TestCase
{
    public function testOnlineSmsUsesStoredTeacherNetTotal(): void
    {
        $lesson = [
            'student_count' => 1,
            'delivery_mode' => 'online',
            'class_fee_per_student' => '2000.00',
            'fee_rule' => 'online_v1',
            'institute_online_fee' => '500.00',
            'transaction_handling_fee' => '120.00',
            'teacher_net_amount' => '1380.00',
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
        ];
        $this->assertSame('Rs. 1,380.00', TeacherPaymentSmsService::amountLabel($lesson));

        $lesson['student_count'] = 2;
        $this->assertSame('Rs. 2,760.00', TeacherPaymentSmsService::amountLabel($lesson));
    }

    public function testPhysicalSmsUsesDurationAmountFromTheLesson(): void
    {
        $lesson = [
            'student_count' => 4,
            'delivery_mode' => 'physical',
            'class_fee_per_student' => '2000.00',
            'fee_rule' => '',
            'institute_online_fee' => null,
            'transaction_handling_fee' => null,
            'teacher_net_amount' => null,
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
        ];
        $this->assertSame('Rs. 2,000.00', TeacherPaymentSmsService::amountLabel($lesson));
    }

    public function testMessageIsShortAndOmitsBankAndStudentDetails(): void
    {
        $text = TeacherPaymentSmsService::buildMessage(
            'Hasitha Sandaruwan Nishshankaarachchi',
            'Rs. 1,380.00',
            '23/09/2026',
            'PAY-1025'
        );
        $this->assertSame(
            'Edexcel College: Dear Hasitha, your teacher payment of Rs. 1,380.00 has been successfully paid on 23/09/2026. Ref: PAY-1025. Thank you.',
            $text
        );
        $this->assertStringNotContainsString('account', strtolower($text));
        $this->assertStringNotContainsString('student', strtolower($text));
    }

    public function testPhoneNormalizationAcceptsSriLankanMobilesOnly(): void
    {
        $this->assertSame('0771234567', TeacherPaymentSmsService::normalizePhone('0771234567'));
        $this->assertSame('0771234567', TeacherPaymentSmsService::normalizePhone('+94 77 123 4567'));
        $this->assertSame('', TeacherPaymentSmsService::normalizePhone(''));
        $this->assertSame('', TeacherPaymentSmsService::normalizePhone('12345'));
        $this->assertSame('', TeacherPaymentSmsService::normalizePhone('0112345678'));
    }

    public function testSafeReasonDoesNotRepeatSecrets(): void
    {
        $this->assertSame(
            'The SMS gateway did not accept the message.',
            TeacherPaymentSmsService::safeReason('Gateway username or password is missing.')
        );
    }

    public function testPaidTransitionSendsOnceAndResendIsLoggedSeparately(): void
    {
        $pdo = $this->pdo();
        $calls = 0;
        $sender = static function (string $phone, string $text) use (&$calls): array {
            $calls++;
            return ['ok' => true, 'provider_id' => 'msg-' . $calls, 'error' => ''];
        };

        $first = TeacherPaymentSmsService::notifyTimetablePaid($pdo, 7, 4, false, $sender);
        $second = TeacherPaymentSmsService::notifyTimetablePaid($pdo, 7, 4, false, $sender);
        $resend = TeacherPaymentSmsService::notifyTimetablePaid($pdo, 7, 4, true, $sender);

        $this->assertSame('sent', $first['status']);
        $this->assertSame('already_sent', $second['status']);
        $this->assertSame('resent', $resend['status']);
        $this->assertSame(2, $calls);
        $this->assertSame('paid', (string)$pdo->query('SELECT payment_status FROM timetable WHERE id = 7')->fetchColumn());

        $rows = $pdo->query('SELECT status, attempt_kind, provider_message_id, phone FROM teacher_payment_sms_log ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame('sent', $rows[0]['status']);
        $this->assertSame('auto', $rows[0]['attempt_kind']);
        $this->assertSame('msg-1', $rows[0]['provider_message_id']);
        $this->assertSame('0771234567', $rows[0]['phone']);
        $this->assertSame('resent', $rows[1]['status']);
        $this->assertSame('resend', $rows[1]['attempt_kind']);
        $this->assertSame('msg-2', $rows[1]['provider_message_id']);
    }

    public function testMissingMobileStillLeavesPaymentPaidAndRecordsTheReason(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("UPDATE teachers SET phone = '' WHERE id = 3");
        $calls = 0;
        $sender = static function () use (&$calls): array {
            $calls++;
            return ['ok' => true, 'provider_id' => 'x', 'error' => ''];
        };
        $result = TeacherPaymentSmsService::notifyTimetablePaid($pdo, 7, 4, false, $sender);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('no valid mobile number', (string)$result['sms_notice']);
        $this->assertSame(0, $calls);
        $this->assertSame('paid', (string)$pdo->query('SELECT payment_status FROM timetable WHERE id = 7')->fetchColumn());
        $reason = (string)$pdo->query('SELECT failure_reason FROM teacher_payment_sms_log ORDER BY id DESC LIMIT 1')->fetchColumn();
        $this->assertSame('Teacher has no valid mobile number.', $reason);
    }

    public function testGatewayFailureDoesNotChangeThePaidStatus(): void
    {
        $pdo = $this->pdo();
        $sender = static function (): array {
            return ['ok' => false, 'provider_id' => '', 'error' => 'Gateway returned HTTP 500.'];
        };
        $result = TeacherPaymentSmsService::notifyTimetablePaid($pdo, 7, 4, false, $sender);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('Payment SMS could not be sent.', $result['sms_notice']);
        $this->assertSame('paid', (string)$pdo->query('SELECT payment_status FROM timetable WHERE id = 7')->fetchColumn());
        $status = (string)$pdo->query('SELECT status FROM teacher_payment_sms_log ORDER BY id DESC LIMIT 1')->fetchColumn();
        $this->assertSame('failed', $status);
    }

    public function testOpeningAnUnpaidLessonDoesNotSend(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("UPDATE timetable SET payment_status = 'pending', payment_date = NULL WHERE id = 7");
        $calls = 0;
        $sender = static function () use (&$calls): array {
            $calls++;
            return ['ok' => true, 'provider_id' => 'x', 'error' => ''];
        };
        $result = TeacherPaymentSmsService::notifyTimetablePaid($pdo, 7, 4, false, $sender);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame(0, $calls);
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM teacher_payment_sms_log')->fetchColumn());
    }

    public function testPaymentEndpointsKeepAdminChecks(): void
    {
        $root = dirname(__DIR__, 2);
        $mark = (string)file_get_contents($root . '/ajax/mark_paid.php');
        $resend = (string)file_get_contents($root . '/ajax/resend_payment_sms.php');
        $this->assertStringContainsString('is_admin()', $mark);
        $this->assertStringContainsString('is_admin()', $resend);
        $this->assertStringContainsString("Only an administrator can resend a payment SMS.", $resend);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE teachers (
                id INTEGER PRIMARY KEY,
                name TEXT,
                phone TEXT,
                deleted_at TEXT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE timetable (
                id INTEGER PRIMARY KEY,
                teacher_id INTEGER,
                student_count INTEGER,
                payment_status TEXT,
                payment_date TEXT,
                start_time TEXT,
                end_time TEXT,
                class_fee_per_student TEXT,
                delivery_mode TEXT,
                fee_rule TEXT,
                institute_online_fee TEXT,
                transaction_handling_fee TEXT,
                teacher_net_amount TEXT,
                deleted_at TEXT NULL
            )'
        );
        $pdo->exec("INSERT INTO teachers (id, name, phone) VALUES (3, 'Hasitha Sandaruwan', '0771234567')");
        $pdo->exec(
            "INSERT INTO timetable (
                id, teacher_id, student_count, payment_status, payment_date, start_time, end_time,
                class_fee_per_student, delivery_mode, fee_rule, institute_online_fee, transaction_handling_fee, teacher_net_amount
            ) VALUES (
                7, 3, 1, 'paid', '2026-09-23', '15:00:00', '17:00:00',
                '2000.00', 'online', 'online_v1', '500.00', '120.00', '1380.00'
            )"
        );
        TeacherPaymentSmsService::ensureSchema($pdo);
        return $pdo;
    }
}

}
