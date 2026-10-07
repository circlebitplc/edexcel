<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Repositories\TimetableRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class TimetableRepositoryVisibilityTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is not installed.');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE teachers (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE subjects (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE student_classes (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE rooms (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)');
        $this->pdo->exec(
            'CREATE TABLE timetable (
                id INTEGER PRIMARY KEY, date TEXT, start_time TEXT, end_time TEXT,
                teacher_id INTEGER, substitute_teacher_id INTEGER, subject_id INTEGER,
                class_id INTEGER, room_id INTEGER, student_count INTEGER,
                class_fee_per_student REAL, payment_status TEXT, is_locked INTEGER,
                delivery_mode TEXT, deleted_at TEXT
            )'
        );

        $this->pdo->exec("INSERT INTO teachers VALUES (1, 'Assigned', NULL), (2, 'Substitute', NULL)");
        $this->pdo->exec("INSERT INTO subjects VALUES (1, 'Maths', NULL)");
        $this->pdo->exec("INSERT INTO student_classes VALUES (1, 'Year 12', NULL)");
        $this->pdo->exec("INSERT INTO rooms VALUES (1, 'Online', NULL)");
        $this->pdo->exec(
            "INSERT INTO timetable VALUES
            (10, '2026-09-22', '09:00:00', '10:00:00', 1, 2, 1, 1, 1, 8, 0, 'pending', 0, 'online', NULL)"
        );
    }

    public function testSubstituteTeacherCanSeeAssignedLesson(): void
    {
        $rows = (new TimetableRepository($this->pdo))
            ->listFiltered(2, 0, 0, '2026-09-22', '2026-09-22');

        $this->assertCount(1, $rows);
        $this->assertSame('Substitute', $rows[0]['teacher_name']);
        $this->assertSame('Assigned', $rows[0]['assigned_teacher_name']);
    }

    public function testMissingOptionalDisplayRelationDoesNotDropLesson(): void
    {
        $this->pdo->exec("UPDATE rooms SET deleted_at = '2026-09-22' WHERE id = 1");

        $rows = (new TimetableRepository($this->pdo))
            ->listFiltered(1, 0, 0, '2026-09-22', '2026-09-22');

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['room_name']);
    }
}
