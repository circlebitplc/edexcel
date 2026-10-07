<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ClassOperationsService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function classDetail(int $classId): array
    {
        $out=['class'=>null,'students'=>[],'lessons'=>[],'analytics'=>[]];
        try{$s=$this->pdo->prepare("SELECT c.*,t.name teacher_name,s.name subject_name FROM student_classes c LEFT JOIN teachers t ON t.id=c.teacher_id LEFT JOIN subjects s ON s.id=c.subject_id WHERE c.id=? AND c.deleted_at IS NULL");$s->execute([$classId]);$out['class']=$s->fetch(PDO::FETCH_ASSOC)?:null;}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT u.id,u.username,sp.full_name FROM student_enrollments se JOIN users u ON u.id=se.student_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE se.class_id=? AND u.deleted_at IS NULL ORDER BY COALESCE(sp.full_name,u.username)");$s->execute([$classId]);$out['students']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT tt.*,t.name teacher_name FROM timetable tt LEFT JOIN teachers t ON t.id=tt.teacher_id WHERE tt.class_id=? AND tt.deleted_at IS NULL AND tt.date>=CURDATE() ORDER BY tt.date,tt.start_time LIMIT 50");$s->execute([$classId]);$out['lessons']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        $out['analytics']=$this->classAnalytics($classId);return $out;
    }

    /** @return array<string,mixed> */
    public function classAnalytics(int $classId): array
    {
        try{$s=$this->pdo->prepare("SELECT COUNT(*) total,SUM(status IN ('present','late')) present,AVG(CASE WHEN max_score>0 THEN score/max_score*100 END) avg_marks FROM (SELECT se.student_id,sa.status,sp.score,sp.max_score FROM student_enrollments se LEFT JOIN student_attendance sa ON sa.student_id=se.student_id LEFT JOIN student_progress sp ON sp.student_id=se.student_id WHERE se.class_id=?) x");$s->execute([$classId]);$r=$s->fetch(PDO::FETCH_ASSOC)?:[];return['attendance_percent'=>!empty($r['total'])?round((float)$r['present']/(float)$r['total']*100,1):null,'average_marks'=>isset($r['avg_marks'])?(float)$r['avg_marks']:null];}catch(Throwable $e){return['attendance_percent'=>null,'average_marks'=>null];}
    }

    public function cancelLesson(int $timetableId, int $userId, string $reason): void
    {
        if (trim($reason) === '') throw new RuntimeException('Cancellation reason is required.');
        $s = $this->pdo->prepare("SELECT tt.*, s.name AS subject_name, c.name AS class_name FROM timetable tt JOIN subjects s ON s.id=tt.subject_id JOIN student_classes c ON c.id=tt.class_id WHERE tt.id=? AND tt.deleted_at IS NULL");
        $s->execute([$timetableId]); $old = $s->fetch(PDO::FETCH_ASSOC);
        if (!$old) throw new RuntimeException('Lesson not found.');
        $this->pdo->prepare("UPDATE timetable SET lesson_status='cancelled',cancel_reason=?,cancelled_at=NOW() WHERE id=?")->execute([$reason,$timetableId]);
        if (function_exists('log_audit')) log_audit($this->pdo, 'class_cancelled', 'timetable', $timetableId, $old, ['reason'=>$reason,'user_id'=>$userId]);
        $when = date('D d M, h:i A', strtotime((string)$old['date'].' '.(string)$old['start_time']));
        try {
            (new CommunicationEventService($this->pdo))->timetableChanged(
                (int)$old['class_id'],
                "Class cancelled: {$old['subject_name']} ({$old['class_name']}) on {$when}. {$reason}",
                ['timetable_id' => $timetableId, 'date' => (string)$old['date'], 'event_name' => 'timetable.cancelled', 'actor_id' => $userId]
            );
        } catch (Throwable $e) {
        }
    }

    public function transferStudent(int $studentId,int $fromClass,int $toClass,int $userId,string $reason,bool $override=false): void
    {
        if ($fromClass === $toClass) throw new RuntimeException('The student is already in this class.');
        (new CapacityService($this->pdo))->assertCanEnroll($toClass,$override);
        $check = $this->pdo->prepare("SELECT 1 FROM timetable old JOIN timetable new ON new.class_id=? AND new.date=old.date AND new.start_time<old.end_time AND new.end_time>old.start_time WHERE old.class_id=? AND old.deleted_at IS NULL AND new.deleted_at IS NULL LIMIT 1");
        $check->execute([$toClass,$fromClass]);
        if ($check->fetchColumn()) throw new RuntimeException('The transfer would create a timetable conflict.');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM student_enrollments WHERE student_id=? AND class_id=?')->execute([$studentId,$fromClass]);
            $this->pdo->prepare('INSERT IGNORE INTO student_enrollments(student_id,class_id) VALUES(?,?)')->execute([$studentId,$toClass]);
            $this->pdo->prepare("INSERT INTO enrollment_history(student_id,class_id,action,old_value,new_value,notes,created_by) VALUES(?,?,'transferred',?,?,?,?)")->execute([$studentId,$toClass,json_encode(['class_id'=>$fromClass]),json_encode(['class_id'=>$toClass]),$reason,$userId]);
            $this->pdo->commit();
            if (function_exists('log_audit')) log_audit($this->pdo,'student_transfer','student_enrollments',$studentId,['class_id'=>$fromClass],['class_id'=>$toClass,'reason'=>$reason]);
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function assignSubstitute(int $timetableId,int $substituteId,int $userId,string $reason): void
    {
        $s=$this->pdo->prepare("SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name FROM timetable tt JOIN subjects s ON s.id=tt.subject_id JOIN student_classes c ON c.id=tt.class_id JOIN teachers t ON t.id=tt.teacher_id WHERE tt.id=? AND tt.deleted_at IS NULL");
        $s->execute([$timetableId]); $lesson=$s->fetch(PDO::FETCH_ASSOC);
        if (!$lesson) throw new RuntimeException('Lesson not found.');
        $c=$this->pdo->prepare("SELECT 1 FROM timetable WHERE teacher_id=? AND date=? AND start_time<? AND end_time>? AND deleted_at IS NULL AND id<>? LIMIT 1");
        $c->execute([$substituteId,$lesson['date'],$lesson['end_time'],$lesson['start_time'],$timetableId]);
        if ($c->fetchColumn()) throw new RuntimeException('Substitute has a timetable conflict.');
        $subName='';
        try{$n=$this->pdo->prepare('SELECT name FROM teachers WHERE id=?');$n->execute([$substituteId]);$subName=(string)($n->fetchColumn()?:'');}catch(Throwable $e){}
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("INSERT INTO class_substitutions(timetable_id,original_teacher_id,substitute_teacher_id,reason,assigned_by) VALUES(?,?,?,?,?)")->execute([$timetableId,$lesson['teacher_id'],$substituteId,$reason,$userId]);
            $this->pdo->prepare("UPDATE timetable SET lesson_status='substituted', substitute_teacher_id=?, cancel_reason=? WHERE id=?")->execute([$substituteId,$reason!==''?$reason:null,$timetableId]);
            $this->pdo->commit();
            if (function_exists('log_audit')) log_audit($this->pdo,'substitute_assigned','timetable',$timetableId,$lesson,['substitute_teacher_id'=>$substituteId]);
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
        $when = date('D d M, h:i A', strtotime((string)$lesson['date'].' '.(string)$lesson['start_time']));
        try {
            (new CommunicationEventService($this->pdo))->timetableChanged(
                (int)$lesson['class_id'],
                "Substitute teacher: ".($subName!==''?$subName:'#'.$substituteId)." for {$lesson['subject_name']} ({$lesson['class_name']}) on {$when}.",
                ['timetable_id' => $timetableId, 'date' => (string)$lesson['date'], 'event_name' => 'timetable.substituted', 'actor_id' => $userId]
            );
        } catch (Throwable $e) {
        }
    }

    public function createMakeup(int $originalId,string $date,string $start,string $end,int $teacherId,?int $roomId,string $mode,int $userId,string $reason): int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || $start >= $end) throw new RuntimeException('Invalid make-up date/time.');
        $c=$this->pdo->prepare("SELECT 1 FROM timetable WHERE teacher_id=? AND date=? AND start_time<? AND end_time>? AND deleted_at IS NULL LIMIT 1");
        $c->execute([$teacherId,$date,$end,$start]); if ($c->fetchColumn()) throw new RuntimeException('Teacher has a timetable conflict.');
        $orig=null;
        try{$s=$this->pdo->prepare('SELECT class_id FROM timetable WHERE id=?');$s->execute([$originalId]);$orig=$s->fetch(PDO::FETCH_ASSOC);}catch(Throwable $e){}
        $this->pdo->prepare("INSERT INTO class_makeup_lessons(original_timetable_id,date,start_time,end_time,teacher_id,room_id,delivery_mode,reason,created_by) VALUES(?,?,?,?,?,?,?,?,?)")->execute([$originalId,$date,$start,$end,$teacherId,$roomId,$mode,$reason,$userId]);
        $id=(int)$this->pdo->lastInsertId();
        if (function_exists('log_audit')) log_audit($this->pdo,'makeup_created','class_makeup_lessons',$id,null,['original_timetable_id'=>$originalId]);
        if ($orig && !empty($orig['class_id'])) {
            try {
                (new CommunicationEventService($this->pdo))->timetableChanged(
                    (int)$orig['class_id'],
                    "Make-up class scheduled for {$date} {$start}-{$end}. {$reason}",
                    ['timetable_id' => $originalId, 'makeup_id' => $id, 'date' => $date, 'event_name' => 'timetable.makeup', 'actor_id' => $userId]
                );
            } catch (Throwable $e) {
            }
        }
        return $id;
    }
}
