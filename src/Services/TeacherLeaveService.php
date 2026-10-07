<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class TeacherLeaveService
{
    public function __construct(private PDO $pdo)
    {
        self::ensureSchema($this->pdo);
    }

    public static function ensureSchema(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            return;
        }
        static $done = [];
        $key = spl_object_id($pdo);
        if (isset($done[$key])) {
            return;
        }
        $done[$key] = true;
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS teacher_leave (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    teacher_id INT NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    reason VARCHAR(500) NULL,
                    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                    reviewed_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_teacher_leave (teacher_id, start_date, end_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            error_log('TeacherLeaveService schema: ' . $e->getMessage());
        }
    }

    public function request(int $teacherId,string $start,string $end,string $reason):int
    {
        if($teacherId<1||$start>$end)throw new RuntimeException('Invalid leave dates.');
        $this->pdo->prepare('INSERT INTO teacher_leave(teacher_id,start_date,end_date,reason) VALUES(?,?,?,?)')->execute([$teacherId,$start,$end,trim($reason)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function review(int $id,string $status,int $adminId):void
    {
        if(!in_array($status,['approved','rejected'],true))throw new RuntimeException('Invalid leave status.');
        $this->pdo->prepare("UPDATE teacher_leave SET status=?,reviewed_by=? WHERE id=? AND status='pending'")->execute([$status,$adminId,$id]);
        if(function_exists('log_audit'))log_audit($this->pdo,'teacher_leave_'.$status,'teacher_leave',$id,null,['reviewed_by'=>$adminId]);
    }

    /** @return list<array<string,mixed>> */
    public function affectedLessons(int $leaveId): array
    {
        $s=$this->pdo->prepare("SELECT l.*,tt.id timetable_id,tt.class_id,tt.subject_id,tt.date,tt.start_time,tt.end_time,c.name class_name FROM teacher_leave l JOIN timetable tt ON tt.teacher_id=l.teacher_id AND tt.date BETWEEN l.start_date AND l.end_date AND tt.deleted_at IS NULL LEFT JOIN student_classes c ON c.id=tt.class_id WHERE l.id=? AND l.status='approved' ORDER BY tt.date,tt.start_time");
        $s->execute([$leaveId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function list(?string $status=null):array
    { $sql='SELECT l.*,t.name teacher_name FROM teacher_leave l LEFT JOIN teachers t ON t.id=l.teacher_id';$p=[];if($status){$sql.=' WHERE l.status=?';$p[]=$status;}$sql.=' ORDER BY l.start_date DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
}
