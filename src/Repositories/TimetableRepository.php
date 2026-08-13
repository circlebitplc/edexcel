<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class TimetableRepository
{
    public function __construct(private PDO $pdo) {}

    public function listFiltered(
        int $teacherId,int $roomId,int $classId,
        string $dateFrom,string $dateTo,
        int $limit=500,int $offset=0
    ): array {
        $where=["t.deleted_at IS NULL","t.date BETWEEN ? AND ?"];
        $params=[$dateFrom,$dateTo];

        if($teacherId>0){$where[]="t.teacher_id=?";$params[]=$teacherId;}
        if($roomId>0){$where[]="t.room_id=?";$params[]=$roomId;}
        if($classId>0){$where[]="t.class_id=?";$params[]=$classId;}

        $sql="SELECT t.id,t.date,t.start_time,t.end_time,
                     tc.name teacher_name,s.name subject_name,
                     c.name class_name,r.name room_name,
                     t.student_count,t.payment_status,t.is_locked
              FROM timetable t
              JOIN teachers tc ON t.teacher_id=tc.id AND tc.deleted_at IS NULL
              JOIN subjects s ON t.subject_id=s.id AND s.deleted_at IS NULL
              JOIN student_classes c ON t.class_id=c.id AND c.deleted_at IS NULL
              JOIN rooms r ON t.room_id=r.id AND r.deleted_at IS NULL
              WHERE ".implode(" AND ",$where)."
              ORDER BY t.date,t.start_time
              LIMIT ? OFFSET ?";

        $limit=max(1,min(500,$limit));
        $offset=max(0,$offset);
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute(array_merge($params,[$limit,$offset]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForUpdate(int $id): ?array
    {
        $stmt=$this->pdo->prepare(
            "SELECT * FROM timetable WHERE id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt=$this->pdo->prepare(
            "SELECT * FROM timetable WHERE id=? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt=$this->pdo->prepare(
            "INSERT INTO timetable
             (teacher_id,subject_id,class_id,room_id,student_count,
              date,start_time,end_time,payment_status,payment_date,
              is_locked,deleted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,0,NULL)"
        );
        $stmt->execute([
            (int)$data['teacher_id'],
            (int)$data['subject_id'],
            (int)$data['class_id'],
            (int)$data['room_id'],
            (int)$data['student_count'],
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['payment_status'],
            $data['payment_date'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function cloneForDate(int $sourceId,string $date): int
    {
        $stmt=$this->pdo->prepare(
            "INSERT INTO timetable
             (teacher_id,subject_id,class_id,room_id,student_count,
              date,start_time,end_time,payment_status,is_locked,deleted_at)
             SELECT teacher_id,subject_id,class_id,room_id,student_count,
                    ?,start_time,end_time,'pending',0,NULL
             FROM timetable source
             WHERE source.id=? AND source.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM timetable existing
                   WHERE existing.teacher_id=source.teacher_id
                     AND existing.subject_id=source.subject_id
                     AND existing.class_id=source.class_id
                     AND existing.room_id=source.room_id
                     AND existing.date=?
                     AND existing.start_time=source.start_time
                     AND existing.end_time=source.end_time
                     AND existing.deleted_at IS NULL
               )"
        );
        $stmt->execute([$date,$sourceId,$date]);
        if($stmt->rowCount()!==1) {
            throw new RuntimeException('The timetable entry could not be cloned.');
        }
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id,array $data): void
    {
        $stmt=$this->pdo->prepare(
            "UPDATE timetable SET
                teacher_id=?,subject_id=?,class_id=?,room_id=?,
                student_count=?,date=?,start_time=?,end_time=?,
                payment_status=?,payment_date=?
             WHERE id=? AND deleted_at IS NULL"
        );
        $stmt->execute([
            (int)$data['teacher_id'],
            (int)$data['subject_id'],
            (int)$data['class_id'],
            (int)$data['room_id'],
            (int)$data['student_count'],
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['payment_status'],
            $data['payment_date'] ?? null,
            $id
        ]);
        /*
         * rowCount() may be 0 when the submitted values are identical
         * to the existing values. The service already locked and verified
         * the row with findForUpdate(), so a zero affected-row count is
         * not an update failure.
         */
    }

    public function softDelete(int $id): void
    {
        $stmt=$this->pdo->prepare(
            "UPDATE timetable
             SET deleted_at=NOW()
             WHERE id=? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Delete failed.');
        }
    }

    public function setLockState(int $id,int $state): void
    {
        $stmt=$this->pdo->prepare(
            "UPDATE timetable
             SET is_locked=?
             WHERE id=? AND deleted_at IS NULL"
        );
        $stmt->execute([$state,$id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Lock status could not be updated.');
        }
    }

    public function updateStudentCount(int $id,int $count): void
    {
        $stmt=$this->pdo->prepare(
            "UPDATE timetable
             SET student_count=?
             WHERE id=? AND deleted_at IS NULL"
        );
        $stmt->execute([$count,$id]);
        if($stmt->rowCount()<1) {
            throw new RuntimeException('Student count could not be updated.');
        }
    }

    public function markPaid(int $id,string $date): void
    {
        $stmt=$this->pdo->prepare(
            "UPDATE timetable
             SET payment_status='paid',payment_date=?
             WHERE id=? AND deleted_at IS NULL
               AND payment_status <> 'paid'"
        );
        $stmt->execute([$date,$id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Payment could not be updated.');
        }
    }

    public function conflict(
        string $column,int $value,string $date,
        string $start,string $end,int $excludeId=0
    ): ?array {
        $allowed=['teacher_id'=>'teachers','room_id'=>'rooms','class_id'=>'student_classes'];
        if (!isset($allowed[$column])) {
            throw new RuntimeException('Invalid conflict field.');
        }

        $label=$column==='teacher_id'?'teacher_name':
               ($column==='room_id'?'room_name':'class_name');

        $stmt=$this->pdo->prepare(
            "SELECT t.*, x.name AS {$label}
             FROM timetable t
             JOIN {$allowed[$column]} x ON t.{$column}=x.id
             WHERE t.{$column}=?
               AND t.date=?
               AND (? < t.end_time AND ? > t.start_time)
               AND t.deleted_at IS NULL
               AND t.id <> ?
             LIMIT 1"
        );
        $stmt->execute([$value,$date,$start,$end,$excludeId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
