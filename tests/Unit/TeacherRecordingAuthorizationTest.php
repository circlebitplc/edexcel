<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\RecordingAccessService;
use PHPUnit\Framework\TestCase;

final class TeacherRecordingAuthorizationTest extends TestCase
{
    public function testTeacherCanManageOwnLesson(): void
    {
        $this->assertTrue(RecordingAccessService::teacherCanManageLesson(7, 7, false));
    }

    public function testTeacherCannotManageAnotherTeachersLesson(): void
    {
        $this->assertFalse(RecordingAccessService::teacherCanManageLesson(7, 9, false));
    }

    public function testAdminCanManageAnyLesson(): void
    {
        $this->assertTrue(RecordingAccessService::teacherCanManageLesson(0, 9, true));
    }

    public function testSubstituteCanManage(): void
    {
        $this->assertTrue(RecordingAccessService::teacherCanManageLesson(3, 9, false, 3));
        $this->assertFalse(RecordingAccessService::teacherCanManageLesson(3, 9, false, 4));
    }

    public function testTeacherLibraryIsolation(): void
    {
        $this->assertTrue(RecordingAccessService::teacherOwnsLibraryItem(5, 5, false));
        $this->assertFalse(RecordingAccessService::teacherOwnsLibraryItem(5, 8, false));
        $this->assertTrue(RecordingAccessService::teacherOwnsLibraryItem(5, 8, true));
    }
}
