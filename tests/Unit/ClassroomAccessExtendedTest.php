<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\ClassroomAccessService;
use PHPUnit\Framework\TestCase;

final class ClassroomAccessExtendedTest extends TestCase
{
    /** @return array<string,mixed> */
    private function liveStudentBase(): array
    {
        return [
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => true,
            'delivery_mode' => 'online',
            'lesson_status' => 'scheduled',
            'meeting_status' => 'live',
        ];
    }

    public function testWaivedFeeAllowsJoin(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'fee_status' => 'waived',
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $r['code']);
    }

    public function testUnpaidRequiresPay(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'fee_status' => 'unpaid',
        ]);
        $this->assertSame(ClassroomAccessService::PAY, $r['code']);
        $this->assertSame('pay', $r['wait_kind']);
    }

    public function testPartialFeeRequiresPay(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'fee_status' => 'partial',
        ]);
        $this->assertSame(ClassroomAccessService::PAY, $r['code']);
    }

    /**
     * force_unpaid is resolved upstream into fee_status unpaid (not a decide() key).
     */
    public function testForceUnpaidResolvedAsUnpaidBlocksJoin(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'fee_status' => 'unpaid',
            'force_unpaid' => true,
        ]);
        $this->assertSame(ClassroomAccessService::PAY, $r['code']);
    }

    public function testPendingPaymentWaits(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'fee_status' => 'pending',
        ]);
        $this->assertSame(ClassroomAccessService::WAIT, $r['code']);
        $this->assertSame('pay_pending', $r['wait_kind']);
    }

    public function testPaymentPendingFlagWaitsEvenIfUnpaidLabel(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'fee_status' => 'unpaid',
            'payment_pending' => true,
        ]);
        $this->assertSame(ClassroomAccessService::WAIT, $r['code']);
        $this->assertSame('pay_pending', $r['wait_kind']);
    }

    public function testSubstituteTeacherAllowedOtherTeacherDenied(): void
    {
        $base = [
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'delivery_mode' => 'hybrid',
            'meeting_status' => 'live',
            'lesson_teacher_id' => 10,
            'substitute_teacher_id' => 22,
        ];

        $sub = ClassroomAccessService::decide($base + [
            'teacher_id' => 22,
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $sub['code']);

        $other = ClassroomAccessService::decide($base + [
            'teacher_id' => 99,
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $other['code']);
    }

    public function testAssignedTeacherIsolatedFromOtherLesson(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'teacher_id' => 5,
            'lesson_teacher_id' => 8,
            'substitute_teacher_id' => 0,
            'delivery_mode' => 'online',
            'meeting_status' => 'live',
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $r['code']);
    }

    public function testUnlinkedTeacherAccountCannotHostLesson(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'teacher_id' => 0,
            'lesson_teacher_id' => 8,
            'substitute_teacher_id' => 0,
            'delivery_mode' => 'online',
            'meeting_status' => 'live',
        ]);

        $this->assertSame(ClassroomAccessService::DENY, $r['code']);
    }

    public function testAdminRoleDoesNotInheritTeacherIdAssignment(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'admin',
            'teacher_id' => 8,
            'lesson_teacher_id' => 8,
            'delivery_mode' => 'online',
            'meeting_status' => 'live',
        ]);

        $this->assertSame(ClassroomAccessService::ALLOW, $r['code']);
    }

    public function testStudentRoleWithTeacherIdNeverBecomesHost(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase() + [
            'teacher_id' => 8,
            'lesson_teacher_id' => 8,
            'fee_status' => 'unpaid',
        ]);

        $this->assertSame(ClassroomAccessService::PAY, $r['code']);
    }

    public function testDefaultFeeStatusTreatedAsUnpaid(): void
    {
        $r = ClassroomAccessService::decide($this->liveStudentBase());
        $this->assertSame(ClassroomAccessService::PAY, $r['code']);
    }

    public function testEndedMeetingDeniesStudentAllowsHost(): void
    {
        $student = ClassroomAccessService::decide(array_merge($this->liveStudentBase(), [
            'meeting_status' => 'ended',
            'fee_status' => 'paid',
        ]));
        $this->assertSame(ClassroomAccessService::DENY, $student['code']);

        $host = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'is_host' => true,
            'delivery_mode' => 'online',
            'meeting_status' => 'ended',
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $host['code']);
    }

    public function testJoinWindowClosedDeniesStudentAllowsHost(): void
    {
        $student = ClassroomAccessService::decide(array_merge($this->liveStudentBase(), [
            'fee_status' => 'paid',
            'join_window_closed' => true,
        ]));
        $this->assertSame(ClassroomAccessService::DENY, $student['code']);
        $this->assertStringContainsString('Join is closed', $student['message']);

        $host = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'is_host' => true,
            'delivery_mode' => 'online',
            'meeting_status' => 'live',
            'join_window_closed' => true,
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $host['code']);
    }
}
