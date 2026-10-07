<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\ClassSessionFeeCalculator;
use Edexcel\Services\TeacherBankAccountService;
use PHPUnit\Framework\TestCase;

final class ClassSessionFeeCalculatorTest extends TestCase
{
    public function testOnlineExamplesMatchTheAgreedBreakdown(): void
    {
        $cases = [
            '2000' => ['500.00', '120.00', '1380.00'],
            '1000' => ['500.00', '60.00', '440.00'],
            '1500' => ['500.00', '90.00', '910.00'],
            '2500' => ['500.00', '150.00', '1850.00'],
            '500' => ['500.00', '30.00', '-30.00'],
        ];
        foreach ($cases as $gross => [$institute, $handling, $net]) {
            $quote = ClassSessionFeeCalculator::quote($gross, 'online');
            $this->assertTrue($quote['valid']);
            $this->assertSame('online', $quote['class_type']);
            $this->assertSame(number_format((float)$gross, 2, '.', ''), $quote['gross_class_fee']);
            $this->assertSame($quote['gross_class_fee'], $quote['total_student_payable']);
            $this->assertSame($institute, $quote['institute_online_fee']);
            $this->assertSame($handling, $quote['transaction_handling_fee']);
            $this->assertSame($net, $quote['teacher_net_amount']);
        }
    }

    public function testEmptyZeroDecimalAndInvalidAmounts(): void
    {
        $empty = ClassSessionFeeCalculator::quote('', 'online');
        $this->assertTrue($empty['valid']);
        $this->assertSame('0.00', $empty['gross_class_fee']);
        $this->assertSame('500.00', $empty['institute_online_fee']);
        $this->assertSame('0.00', $empty['transaction_handling_fee']);
        $this->assertSame('-500.00', $empty['teacher_net_amount']);

        $zero = ClassSessionFeeCalculator::quote('0', 'online');
        $this->assertSame($empty['teacher_net_amount'], $zero['teacher_net_amount']);

        $decimal = ClassSessionFeeCalculator::quote('10.10', 'online');
        $this->assertSame('10.10', $decimal['gross_class_fee']);
        $this->assertSame('0.61', $decimal['transaction_handling_fee']);
        $this->assertSame('-490.51', $decimal['teacher_net_amount']);

        $rounded = ClassSessionFeeCalculator::quote('10.555', 'online');
        $this->assertSame('10.56', $rounded['gross_class_fee']);

        $this->assertFalse(ClassSessionFeeCalculator::quote('-5', 'online')['valid']);
        $this->assertFalse(ClassSessionFeeCalculator::quote('abc', 'online')['valid']);
    }

    public function testInCollegeUsesAFlatInstituteFeeAndNoHandlingFee(): void
    {
        foreach (['1000' => '500.00', '2000' => '1500.00', '8000' => '7500.00'] as $gross => $net) {
            $quote = ClassSessionFeeCalculator::quote($gross, 'physical');
            $this->assertSame(ClassSessionFeeCalculator::IN_COLLEGE_RULE, $quote['fee_rule']);
            $this->assertSame('500.00', $quote['institute_online_fee']);
            $this->assertSame('0.00', $quote['transaction_handling_fee']);
            $this->assertSame($net, $quote['teacher_net_amount']);
            $this->assertSame($quote['gross_class_fee'], $quote['total_student_payable']);
            $this->assertSame($quote['total_student_payable'], $quote['student_payable_amount']);
        }
    }

    public function testHybridKeepsTheDurationRule(): void
    {
        $quote = ClassSessionFeeCalculator::quote('2000', 'hybrid');
        $this->assertSame('0.00', $quote['institute_online_fee']);
        $this->assertSame('0.00', $quote['transaction_handling_fee']);
        $this->assertSame('2000.00', $quote['total_student_payable']);
        $this->assertSame(ClassSessionFeeCalculator::PHYSICAL_RULE, $quote['fee_rule']);
    }

    public function testStampIgnoresBrowserFeeComponents(): void
    {
        $stamped = ClassSessionFeeCalculator::stampPayload([
            'delivery_mode' => 'online',
            'class_fee_per_student' => '2000',
            'institute_online_fee' => '1.00',
            'transaction_handling_fee' => '1.00',
            'teacher_net_amount' => '99999.00',
            'fee_rule' => 'physical_duration',
        ]);
        $this->assertSame('2000.00', $stamped['class_fee_per_student']);
        $this->assertSame('500.00', $stamped['institute_online_fee']);
        $this->assertSame('120.00', $stamped['transaction_handling_fee']);
        $this->assertSame('1380.00', $stamped['teacher_net_amount']);
        $this->assertSame(ClassSessionFeeCalculator::ONLINE_RULE, $stamped['fee_rule']);
    }

    public function testUnchangedOnlineSnapshotIsKept(): void
    {
        $existing = [
            'delivery_mode' => 'online',
            'fee_rule' => ClassSessionFeeCalculator::ONLINE_RULE,
            'class_fee_per_student' => '2000.00',
            'institute_online_fee' => '400.00',
            'transaction_handling_fee' => '80.00',
            'teacher_net_amount' => '1520.00',
        ];
        $stamped = ClassSessionFeeCalculator::stampPayload([
            'delivery_mode' => 'online',
            'class_fee_per_student' => '2000.00',
            'teacher_net_amount' => '1.00',
        ], null, $existing);
        $this->assertSame('400.00', $stamped['institute_online_fee']);
        $this->assertSame('80.00', $stamped['transaction_handling_fee']);
        $this->assertSame('1520.00', $stamped['teacher_net_amount']);
    }

    public function testLegacyLessonsKeepDurationInstituteFee(): void
    {
        $legacy = ClassSessionFeeCalculator::settlement([
            'delivery_mode' => 'online',
            'student_count' => 2,
            'class_fee_per_student' => '2000.00',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ], static fn (): float => 700.0);
        $this->assertFalse($legacy['uses_online_rule']);
        $this->assertSame('0.00', $legacy['per_student']['institute_online_fee']);
        $this->assertSame('0.00', $legacy['per_student']['transaction_handling_fee']);
        $this->assertSame('700.00', $legacy['per_student']['institute_fee']);
        $this->assertSame('1300.00', $legacy['per_student']['teacher_net_amount']);
        $this->assertSame('1400.00', $legacy['totals']['institute_fee']);

        $saved = ClassSessionFeeCalculator::settlement([
            'delivery_mode' => 'online',
            'fee_rule' => ClassSessionFeeCalculator::ONLINE_RULE,
            'student_count' => 2,
            'class_fee_per_student' => '2000.00',
            'institute_online_fee' => '500.00',
            'transaction_handling_fee' => '120.00',
            'teacher_net_amount' => '1380.00',
        ], static fn (): float => 1100.0);
        $this->assertTrue($saved['uses_online_rule']);
        $this->assertSame('1000.00', $saved['totals']['institute_online_fee']);
        $this->assertSame('240.00', $saved['totals']['transaction_handling_fee']);
        $this->assertSame('2760.00', $saved['totals']['teacher_net_amount']);
        $this->assertSame('4000.00', $saved['totals']['total_student_payable']);
    }

    public function testSwitchingAwayFromOnlineClearsTheOnlineSnapshot(): void
    {
        $stamped = ClassSessionFeeCalculator::stampPayload([
            'delivery_mode' => 'physical',
            'class_fee_per_student' => '2000',
        ], null, [
            'delivery_mode' => 'online',
            'fee_rule' => ClassSessionFeeCalculator::ONLINE_RULE,
            'class_fee_per_student' => '2000.00',
            'institute_online_fee' => '500.00',
            'transaction_handling_fee' => '120.00',
            'teacher_net_amount' => '1380.00',
        ]);
        $this->assertSame(ClassSessionFeeCalculator::IN_COLLEGE_RULE, $stamped['fee_rule']);
        $this->assertSame('500.00', $stamped['institute_online_fee']);
        $this->assertSame('0.00', $stamped['transaction_handling_fee']);
        $this->assertSame('1500.00', $stamped['teacher_net_amount']);
    }

    public function testLegacyInCollegeEditKeepsTheDurationSnapshot(): void
    {
        $stamped = ClassSessionFeeCalculator::stampPayload([
            'delivery_mode' => 'physical',
            'class_fee_per_student' => '2000',
            'institute_online_fee' => '999.00',
            'teacher_net_amount' => '1.00',
        ], null, [
            'delivery_mode' => 'physical',
            'fee_rule' => null,
            'class_fee_per_student' => '1800.00',
            'institute_online_fee' => null,
            'transaction_handling_fee' => null,
            'teacher_net_amount' => null,
        ]);
        $this->assertNull($stamped['fee_rule']);
        $this->assertNull($stamped['institute_online_fee']);
        $this->assertNull($stamped['teacher_net_amount']);
        $this->assertSame('2000.00', $stamped['class_fee_per_student']);
    }

    public function testJavascriptUsesTheSameCentCalculation(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('Node is not available to compare the browser preview.');
        }
        $script = tempnam(sys_get_temp_dir(), 'feejs');
        $jsFile = dirname(__DIR__, 2) . '/assets/js/class-session-fee.js';
        file_put_contents($script, <<<'JS'
const fs = require('fs');
const vm = require('vm');
const ctx = { CLASS_SESSION_FEE_CONFIG: { instituteFeeCents: 50000, inCollegeFeeCents: 50000, rateBps: 600 } };
ctx.globalThis = ctx;
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(process.argv[2], 'utf8'), ctx);
const inputs = ['2000', '1000', '1500', '2500', '0', '', '10.10', '10.555', '-5', 'abc', '1999.995'];
const out = { online: {}, physical: ctx.ClassSessionFee.quote('2000', 'physical') };
for (const input of inputs) out.online[input] = ctx.ClassSessionFee.quote(input, 'online');
process.stdout.write(JSON.stringify(out));
JS);
        $command = escapeshellarg($node) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jsFile);
        $json = shell_exec($command);
        @unlink($script);
        $this->assertIsString($json);
        $parsed = json_decode((string)$json, true);
        $this->assertIsArray($parsed);
        foreach (['2000', '1000', '1500', '2500', '0', '', '10.10', '10.555', '1999.995'] as $input) {
            $php = ClassSessionFeeCalculator::quote($input, 'online');
            $js = $parsed['online'][$input];
            $this->assertSame(ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($php['gross_class_fee'])), $js['gross']);
            $this->assertSame(ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($php['institute_online_fee'])), $js['institute']);
            $this->assertSame(ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($php['transaction_handling_fee'])), $js['txn']);
            $this->assertSame(ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($php['teacher_net_amount'])), $js['net']);
            $this->assertSame($php['total_student_payable'], ClassSessionFeeCalculator::formatCents($js['grossCents']));
        }
        $this->assertFalse($parsed['online']['-5']['valid']);
        $this->assertFalse($parsed['online']['abc']['valid']);
        $physical = ClassSessionFeeCalculator::quote('2000', 'physical');
        $this->assertSame(ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($physical['institute_online_fee'])), $parsed['physical']['institute']);
        $this->assertSame(ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($physical['teacher_net_amount'])), $parsed['physical']['net']);
        $this->assertSame(0, $parsed['physical']['txnCents']);
        $this->assertSame(50000, $parsed['physical']['instituteCents']);
    }

    public function testBankDetailsAreMaskedAndCompletionIsServerSide(): void
    {
        $this->assertSame('******1234', TeacherBankAccountService::mask('0000001234'));
        $this->assertSame('******1234', TeacherBankAccountService::mask('12 34-56 78 1234'));
        $this->assertFalse(TeacherBankAccountService::isCompleteRow(null));
        $this->assertFalse(TeacherBankAccountService::isCompleteRow([
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Teacher',
            'account_number' => '123',
            'branch' => 'Kandy',
            'account_type' => 'savings',
        ]));
        $this->assertTrue(TeacherBankAccountService::isCompleteRow([
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Teacher',
            'account_number' => '1234567890',
            'branch' => 'Kandy',
            'account_type' => 'savings',
        ]));
        $this->assertCount(30, TeacherBankAccountService::banks());
        $this->assertSame('Hatton National Bank PLC', TeacherBankAccountService::canonicalBank('HNB'));
        $this->assertSame('Bank of Ceylon', TeacherBankAccountService::canonicalBank('boc'));
        $this->assertSame("People's Bank", TeacherBankAccountService::canonicalBank("People's Bank"));
        $this->assertNull(TeacherBankAccountService::canonicalBank('Not A Bank'));
        $clean = TeacherBankAccountService::validate([
            'bank_name' => 'hnb',
            'account_holder_name' => 'Teacher',
            'account_number' => '12-3456-7890',
            'branch' => 'Kandy',
            'branch_code' => '',
            'account_type' => 'current',
        ], true);
        $this->assertSame('Hatton National Bank PLC', $clean['bank_name']);
        $this->assertNull($clean['branch_code']);
        $this->assertSame('1234567890', $clean['account_number']);
    }

    private function nodeBinary(): ?string
    {
        $candidates = ['node', 'node.exe'];
        foreach ($candidates as $candidate) {
            $path = shell_exec(escapeshellarg(PHP_OS_FAMILY === 'Windows' ? 'where' : 'which') . ' ' . $candidate);
            if (is_string($path) && trim($path) !== '') {
                return strtok(trim($path), "\r\n");
            }
        }
        return null;
    }
}
