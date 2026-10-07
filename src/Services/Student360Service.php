<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class Student360Service
{
    public function __construct(private PDO $pdo) {}

    public function canView(int $viewerId,string $role,int $studentId):bool
    {
        if($role==='admin'||$viewerId===$studentId&&$role==='student')return true;
        if($role==='teacher'){try{$s=$this->pdo->prepare("SELECT 1 FROM student_enrollments se JOIN timetable tt ON tt.class_id=se.class_id WHERE se.student_id=? AND tt.teacher_id=? AND tt.deleted_at IS NULL LIMIT 1");$s->execute([$studentId,(int)($_SESSION['teacher_id']??0)]);return(bool)$s->fetchColumn();}catch(Throwable $e){return false;}}
        if($role==='parent'){try{$s=$this->pdo->prepare("SELECT 1 FROM parent_students WHERE parent_id=? AND student_id=? LIMIT 1");$s->execute([$viewerId,$studentId]);return(bool)$s->fetchColumn();}catch(Throwable $e){return false;}}
        return false;
    }

    /** @return array<string,mixed> */
    public function profile(int $studentId):array
    {
        $out=['student'=>null,'parents'=>[],'classes'=>[],'academic'=>[],'attendance'=>[],'fees'=>[],'homework'=>[],'exams'=>[],'recordings'=>[],'documents'=>[],'devices'=>[],'activity'=>[],'risk'=>[]];
        try{$s=$this->pdo->prepare("SELECT u.id,u.username,u.is_active,u.created_at,sp.* FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=? AND u.role='student' AND u.deleted_at IS NULL");$s->execute([$studentId]);$out['student']=$s->fetch(PDO::FETCH_ASSOC)?:null;}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT pa.* FROM parent_students ps JOIN parent_accounts pa ON pa.id=ps.parent_id WHERE ps.student_id=?");$s->execute([$studentId]);$out['parents']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT DISTINCT c.id,c.name,c.class_code,t.name teacher_name,sub.name subject_name FROM student_enrollments se JOIN student_classes c ON c.id=se.class_id LEFT JOIN teachers t ON t.id=c.teacher_id LEFT JOIN subjects sub ON sub.id=c.subject_id WHERE se.student_id=? ORDER BY c.name");$s->execute([$studentId]);$out['classes']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        $out['academic']=(new AcademicProgressService($this->pdo))->forStudent($studentId);
        $out['attendance']=(new AttendanceAnalyticsService($this->pdo))->student($studentId);
        $out['fees']=(new FinanceService($this->pdo))->studentBalance($studentId);
        try{$s=$this->pdo->prepare("SELECT h.title,h.due_date,sub.status,sub.score,sub.max_score,sub.teacher_feedback,sub.submitted_at FROM student_homework h LEFT JOIN student_homework_submissions sub ON sub.homework_id=h.id AND sub.student_id=? WHERE h.class_id IN (SELECT class_id FROM student_enrollments WHERE student_id=?) ORDER BY h.due_date DESC LIMIT 50");$s->execute([$studentId,$studentId]);$out['homework']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT se.*,e.title,e.exam_date FROM student_exam_selections se LEFT JOIN exams e ON e.id=se.exam_id WHERE se.student_id=? ORDER BY e.exam_date");$s->execute([$studentId]);$out['exams']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT id,file_name,document_type,created_at FROM student_documents WHERE student_id=? ORDER BY created_at DESC");$s->execute([$studentId]);$out['documents']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT id,label,last_login_at,revoked_at FROM student_devices WHERE user_id=? ORDER BY last_login_at DESC");$s->execute([$studentId]);$out['devices']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        $out['risk']=(new StudentRiskService($this->pdo))->assess($studentId);
        $out['activity']=$this->activity($studentId);
        try{$out['assessments']=(new AssessmentAnalyticsService($this->pdo))->studentTrend($studentId);}catch(Throwable $e){$out['assessments']=[];}
        try{$out['readiness']=(new ExamReadinessService($this->pdo))->forStudent($studentId);}catch(Throwable $e){$out['readiness']=[];}
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function activity(int $studentId):array
    {
        $events=[];try{$s=$this->pdo->prepare("SELECT created_at,action AS event_type,table_name,record_id,new_values AS detail FROM audit_logs WHERE (record_id=? OR new_values LIKE ?) ORDER BY created_at DESC LIMIT 100");$s->execute([$studentId,'%\"student_id\":'.$studentId.'%']);$events=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT created_at,event_type,CONCAT('security: ',message) detail FROM security_events WHERE user_id=? ORDER BY created_at DESC LIMIT 50");$s->execute([$studentId]);$events=array_merge($events,$s->fetchAll(PDO::FETCH_ASSOC)?:[]);}catch(Throwable $e){}
        usort($events,static fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));return array_slice($events,0,100);
    }
}
