<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RecurringScheduleRepository
{
    public function __construct(private PDO $pdo) {}

    public function dueSchedules(string $today): array
    {
        $stmt=$this->pdo->prepare(
            "SELECT rs.*
             FROM recurring_schedules rs
             LEFT JOIN teachers t ON rs.teacher_id=t.id
             LEFT JOIN subjects s ON rs.subject_id=s.id
             LEFT JOIN student_classes c ON rs.class_id=c.id
             LEFT JOIN rooms r ON rs.room_id=r.id
             WHERE rs.end_date >= ?
               AND t.id IS NOT NULL AND t.deleted_at IS NULL
               AND s.id IS NOT NULL AND s.deleted_at IS NULL
               AND c.id IS NOT NULL AND c.deleted_at IS NULL
               AND r.id IS NOT NULL AND r.deleted_at IS NULL
             ORDER BY rs.last_generated_date
             FOR UPDATE"
        );
        $stmt->execute([$today]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function entryExists(array $schedule,string $date): bool
    {
        $stmt=$this->pdo->prepare(
            "SELECT id FROM timetable
             WHERE teacher_id=? AND subject_id=? AND class_id=? AND room_id=?
               AND date=? AND start_time=? AND end_time=?
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([
            $schedule['teacher_id'],$schedule['subject_id'],$schedule['class_id'],
            $schedule['room_id'],$date,$schedule['start_time'],$schedule['end_time']
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function generateEntry(array $schedule,string $date): int
    {
        $stmt=$this->pdo->prepare(
            "INSERT INTO timetable
             (teacher_id,subject_id,class_id,room_id,student_count,date,
              start_time,end_time,payment_status,is_locked,deleted_at)
             VALUES (?,?,?,?,0,?,?,?,'pending',0,NULL)"
        );
        $stmt->execute([
            $schedule['teacher_id'],$schedule['subject_id'],$schedule['class_id'],
            $schedule['room_id'],$date,$schedule['start_time'],$schedule['end_time']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findMatching(array $data): ?array
    {
        $stmt=$this->pdo->prepare(
            "SELECT id FROM recurring_schedules
             WHERE teacher_id=? AND subject_id=? AND class_id=? AND room_id=?
               AND day_of_week=? AND start_time=? AND end_time=?
               AND start_date<=? AND end_date>=?
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([
            $data['teacher_id'],$data['subject_id'],$data['class_id'],
            $data['room_id'],$data['day_of_week'],$data['start_time'],
            $data['end_time'],$data['date'],$data['repeat_until']
        ]);
        $row=$stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt=$this->pdo->prepare(
            "INSERT INTO recurring_schedules
             (teacher_id,subject_id,class_id,room_id,day_of_week,
              start_time,end_time,start_date,end_date,last_generated_date)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['teacher_id'],$data['subject_id'],$data['class_id'],
            $data['room_id'],$data['day_of_week'],$data['start_time'],
            $data['end_time'],$data['date'],$data['repeat_until'],$data['date']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt=$this->pdo->prepare(
            "DELETE FROM recurring_schedules WHERE id=?"
        );
        $stmt->execute([$id]);
        if($stmt->rowCount()<1) {
            throw new \RuntimeException('Recurring schedule not found.');
        }
    }

    public function updateEndDate(int $id,string $until,string $lastGenerated): void
    {
        $stmt=$this->pdo->prepare(
            "UPDATE recurring_schedules
             SET end_date=?,last_generated_date=?
             WHERE id=?"
        );
        $stmt->execute([$until,$lastGenerated,$id]);
    }
}
