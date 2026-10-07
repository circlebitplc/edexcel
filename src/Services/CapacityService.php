<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class CapacityService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function status(int $classId): array
    {
        $max=30;
        try{$s=$this->pdo->prepare('SELECT max_students,warning_percent FROM class_capacity_settings WHERE class_id=?');$s->execute([$classId]);$r=$s->fetch(PDO::FETCH_ASSOC);if($r){$max=max(1,(int)$r['max_students']);$warning=(int)$r['warning_percent'];}else{$warning=85;}}catch(Throwable $e){$warning=85;}
        try{$s=$this->pdo->prepare("SELECT COUNT(*) FROM student_enrollments WHERE class_id=?");$s->execute([$classId]);$current=(int)$s->fetchColumn();}catch(Throwable $e){$current=0;}
        $pct=round(($current/$max)*100,1);
        return ['class_id'=>$classId,'max_students'=>$max,'current_students'=>$current,'available_seats'=>max(0,$max-$current),'occupancy_percent'=>$pct,'full'=>$current >= $max,'warning'=>$pct >= $warning];
    }

    public function assertCanEnroll(int $classId, bool $override=false): void
    {
        $s=$this->status($classId);
        if($s['full'] && !$override) throw new RuntimeException('Class is full. Add the student to the existing waitlist instead.');
    }

    public function setLimit(int $classId,int $max,int $userId): void
    {
        if($classId<1||$max<1||$max>1000) throw new RuntimeException('Invalid class capacity.');
        $this->pdo->prepare("INSERT INTO class_capacity_settings(class_id,max_students,updated_by,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE max_students=VALUES(max_students),updated_by=VALUES(updated_by),updated_at=NOW()")->execute([$classId,$max,$userId]);
        if(function_exists('log_audit'))log_audit($this->pdo,'class_capacity_updated','class_capacity_settings',$classId,null,['max_students'=>$max]);
    }

    public function transfer(int $studentId,int $fromClass,int $toClass,int $userId,bool $override=false): void
    {
        $this->assertCanEnroll($toClass,$override);
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('DELETE FROM student_enrollments WHERE student_id=? AND class_id=?')->execute([$studentId,$fromClass]);
            $this->pdo->prepare('INSERT IGNORE INTO student_enrollments(student_id,class_id) VALUES(?,?)')->execute([$studentId,$toClass]);
            $this->pdo->prepare("INSERT INTO enrollment_history(student_id,class_id,action,old_value,new_value,created_by) VALUES(?,?, 'transferred', ?, ?, ?)")->execute([$studentId,$toClass,json_encode(['class_id'=>$fromClass]),json_encode(['class_id'=>$toClass]),$userId]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @return list<array<string,mixed>> */
    public function all(int $limit=200): array
    {
        try{$s=$this->pdo->prepare("SELECT c.id,c.name,c.class_code,COALESCE(cc.max_students,30) max_students,COUNT(se.student_id) current_students FROM student_classes c LEFT JOIN class_capacity_settings cc ON cc.class_id=c.id LEFT JOIN student_enrollments se ON se.class_id=c.id WHERE c.deleted_at IS NULL GROUP BY c.id,c.name,c.class_code,cc.max_students ORDER BY c.name LIMIT ?");$s->bindValue(1,$limit,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll(PDO::FETCH_ASSOC)?:[];foreach($rows as &$r){$r['available_seats']=max(0,(int)$r['max_students']-(int)$r['current_students']);$r['occupancy_percent']=$r['max_students']?round(((int)$r['current_students']/(int)$r['max_students'])*100,1):0;$r['full']=$r['available_seats']<=0;}return $rows;}catch(Throwable $e){return [];}
    }
}
