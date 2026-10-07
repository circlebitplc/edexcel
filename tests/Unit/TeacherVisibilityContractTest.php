<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TeacherVisibilityContractTest extends TestCase
{
    public function testTeacherSurfacesIncludeSubstituteAssignments(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            'timetable/index.php',
            'timetable/teacher_schedule.php',
            'timetable/weekly.php',
            'timetable/export_csv.php',
            'campus/today.php',
            'src/Repositories/TimetableRepository.php',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents($root . '/' . $file);
            $this->assertStringContainsString('substitute_teacher_id', $source, $file);
            $this->assertMatchesRegularExpression(
                '/teacher_id\s*=\s*\?[^\n]*OR[^\n]*substitute_teacher_id\s*=\s*\?/i',
                $source,
                $file
            );
        }
    }

    public function testTeacherFacingSurfacesRejectMissingOrInactiveLinks(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['timetable/index.php', 'timetable/teacher_schedule.php', 'timetable/weekly.php', 'timetable/export_csv.php', 'campus/today.php', 'api/timetable.php'] as $file) {
            $source = (string) file_get_contents($root . '/' . $file);
            $this->assertStringContainsString('teacher profile', $source, $file);
        }
    }
}
