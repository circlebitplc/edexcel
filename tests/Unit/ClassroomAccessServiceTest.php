<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\ClassroomAccessService;
use PHPUnit\Framework\TestCase;

final class ClassroomAccessServiceTest extends TestCase
{
    public function testStudentMustBeEnrolled(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => false,
            'delivery_mode' => 'online',
            'lesson_status' => 'scheduled',
            'meeting_status' => 'live',
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $r['code']);
    }

    public function testEnrolledStudentCanJoinLive(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => true,
            'delivery_mode' => 'online',
            'lesson_status' => 'scheduled',
            'meeting_status' => 'live',
            'fee_status' => 'paid',
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $r['code']);
    }

    public function testStudentWaitsUntilTeacherStarts(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => true,
            'delivery_mode' => 'online',
            'lesson_status' => 'scheduled',
            'meeting_status' => 'scheduled',
            'within_join_window' => true,
            'fee_status' => 'paid',
        ]);
        $this->assertSame(ClassroomAccessService::WAIT, $r['code']);
    }

    public function testPhysicalLessonDenied(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => true,
            'delivery_mode' => 'physical',
            'meeting_status' => 'live',
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $r['code']);
    }

    public function testLockedRoomBlocksStudentNotTeacher(): void
    {
        $base = [
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'delivery_mode' => 'online',
            'lesson_status' => 'scheduled',
            'meeting_status' => 'live',
            'locked' => true,
            'lesson_teacher_id' => 7,
        ];
        $student = ClassroomAccessService::decide($base + [
            'role' => 'student',
            'enrolled' => true,
            'teacher_id' => 0,
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $student['code']);

        $teacher = ClassroomAccessService::decide($base + [
            'role' => 'teacher',
            'teacher_id' => 7,
            'is_host' => true,
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $teacher['code']);
    }

    public function testOtherTeacherDenied(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'teacher_id' => 2,
            'lesson_teacher_id' => 9,
            'substitute_teacher_id' => 0,
            'delivery_mode' => 'hybrid',
            'meeting_status' => 'live',
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $r['code']);
    }

    public function testAdminCanOpen(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'admin',
            'delivery_mode' => 'online',
            'meeting_status' => 'scheduled',
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $r['code']);
    }

    public function testKickedStudentDenied(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => true,
            'kicked' => true,
            'delivery_mode' => 'online',
            'meeting_status' => 'live',
        ]);
        $this->assertSame(ClassroomAccessService::DENY, $r['code']);
    }

    public function testHostAlwaysAllowedWithWaitingRoom(): void
    {
        $this->assertTrue(ClassroomAccessService::studentMayEnterLiveRoom([
            'is_host' => true,
            'waiting_room' => true,
            'waiting_status' => 'waiting',
        ]));
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'teacher',
            'is_host' => true,
            'delivery_mode' => 'online',
            'meeting_status' => 'live',
            'waiting_room' => true,
        ]);
        $this->assertSame(ClassroomAccessService::ALLOW, $r['code']);
    }

    public function testWaitingRoomStudentMustBeAdmitted(): void
    {
        $base = [
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => true,
            'role' => 'student',
            'enrolled' => true,
            'delivery_mode' => 'online',
            'lesson_status' => 'scheduled',
            'meeting_status' => 'live',
            'waiting_room' => true,
            'fee_status' => 'paid',
        ];
        $wait = ClassroomAccessService::decide($base + ['waiting_status' => 'waiting']);
        $this->assertSame(ClassroomAccessService::WAIT, $wait['code']);
        $this->assertSame('lobby', $wait['wait_kind']);
        $this->assertFalse(ClassroomAccessService::studentMayEnterLiveRoom($base + ['waiting_status' => 'waiting']));

        $in = ClassroomAccessService::decide($base + ['waiting_status' => 'admitted']);
        $this->assertSame(ClassroomAccessService::ALLOW, $in['code']);
        $this->assertTrue(ClassroomAccessService::studentMayEnterLiveRoom($base + ['waiting_status' => 'admitted']));

        $denied = ClassroomAccessService::decide($base + ['waiting_status' => 'denied']);
        $this->assertSame(ClassroomAccessService::DENY, $denied['code']);
    }

    public function testSetupWhenLiveKitMissing(): void
    {
        $r = ClassroomAccessService::decide([
            'authenticated' => true,
            'classroom_enabled' => true,
            'livekit_ready' => false,
            'role' => 'teacher',
            'is_host' => true,
            'delivery_mode' => 'online',
        ]);
        $this->assertSame(ClassroomAccessService::SETUP, $r['code']);
    }
}
