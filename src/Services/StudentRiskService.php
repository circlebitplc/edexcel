<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class StudentRiskService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function assess(int $studentId, bool $persist=true): array
    {
        $academic=(new AcademicProgressService($this->pdo))->forStudent($studentId);
        $attendance=(float)($academic['attendance_percent']??100);
        $academicPercent=$this->averageMarks($academic['subject_averages']);
        $homework=$academic['homework']['completion_percent'];
        $fees=(new FinanceService($this->pdo))->studentBalance($studentId);
        $cfg=$this->config();$score=0.0;$reasons=[];
        if($academic['attendance_percent']!==null && $attendance<$cfg['attendance']){$score+=min(35,($cfg['attendance']-$attendance)*1.4);$reasons[]='Attendance is '.$attendance.'%, below the '.$cfg['attendance'].'% threshold.';}
        if($academicPercent!==null && $academicPercent<$cfg['academic']){$score+=min(30,($cfg['academic']-$academicPercent)*1.2);$reasons[]='Academic average is '.$academicPercent.'%, below the '.$cfg['academic'].'% threshold.';}
        if($homework!==null && $homework<$cfg['homework']){$score+=min(20,($cfg['homework']-$homework)*0.8);$reasons[]='Homework completion is '.$homework.'%, below the '.$cfg['homework'].'% threshold.';}
        if((float)$fees['outstanding'] >= $cfg['fee']){$score+=15;$reasons[]='Outstanding fees are Rs '.number_format((float)$fees['outstanding'],2).'.';}
        $level=$score>=$cfg['critical_score']?'CRITICAL':($score>=$cfg['high_score']?'HIGH':($score>=$cfg['medium_score']?'MEDIUM':'LOW'));
        $result=['student_id'=>$studentId,'risk_level'=>$level,'risk_score'=>round($score,1),'attendance_percent'=>$academic['attendance_percent'],'academic_percent'=>$academicPercent,'homework_percent'=>$homework,'outstanding_amount'=>(float)$fees['outstanding'],'reasons'=>$reasons,'calculated_at'=>date('Y-m-d H:i:s')];
        if($persist){try{$this->pdo->prepare("INSERT INTO student_risk_assessments(student_id,risk_level,risk_score,attendance_percent,academic_percent,homework_percent,outstanding_amount,reasons_json) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE risk_level=VALUES(risk_level),risk_score=VALUES(risk_score),attendance_percent=VALUES(attendance_percent),academic_percent=VALUES(academic_percent),homework_percent=VALUES(homework_percent),outstanding_amount=VALUES(outstanding_amount),reasons_json=VALUES(reasons_json),calculated_at=NOW()")->execute([$studentId,$level,$score,$academic['attendance_percent'],$academicPercent,$homework,$fees['outstanding'],json_encode($reasons)]);}catch(Throwable $e){error_log('risk persist: '.$e->getMessage());}}
        return $result;
    }

    /** @return list<array<string,mixed>> */
    public function dashboard(?string $level=null,int $limit=200):array
    {
        $students=[];try{$students=$this->pdo->query("SELECT id,username FROM users WHERE role='student' AND deleted_at IS NULL AND is_active=1 ORDER BY id LIMIT ".max(1,min(500,$limit)))->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        $out=[];foreach($students as $s){$r=$this->assess((int)$s['id']);$r['username']=$s['username'];if($level===null||$r['risk_level']===$level)$out[]=$r;}usort($out,static fn($a,$b)=>$b['risk_score']<=>$a['risk_score']);return $out;
    }

    public function createIntervention(array $data,int $userId):int
    {
        if((int)($data['student_id']??0)<1||trim((string)($data['reason']??''))==='')throw new RuntimeException('Student and reason are required.');
        $this->pdo->prepare("INSERT INTO student_interventions(student_id,risk_assessment_id,intervention_type,reason,notes,assigned_to,priority,due_date,created_by) VALUES(?,?,?,?,?,?,?,?,?)")->execute([(int)$data['student_id'],$data['risk_assessment_id']??null,$data['intervention_type']??'general',$data['reason'],$data['notes']??null,$data['assigned_to']??null,$data['priority']??'normal',$data['due_date']??null,$userId]);
        $id=(int)$this->pdo->lastInsertId();if(function_exists('log_audit'))log_audit($this->pdo,'intervention_created','student_interventions',$id,null,$data);return $id;
    }

    /** @return list<array<string,mixed>> */
    public function interventions(?int $studentId=null):array
    {try{$sql='SELECT i.*,u.username assigned_name FROM student_interventions i LEFT JOIN users u ON u.id=i.assigned_to';$p=[];if($studentId){$sql.=' WHERE i.student_id=?';$p[]=$studentId;}$sql.=' ORDER BY i.created_at DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return [];}}

    /** @return array<string,float> */
    private function config():array
    { $get=fn(string $key,string $default) => function_exists('ops_setting')?(float)ops_setting($this->pdo,$key,$default):(float)$default;return ['attendance'=>$get('risk_attendance_threshold','75'),'academic'=>$get('risk_academic_threshold','60'),'homework'=>$get('risk_homework_threshold','60'),'fee'=>$get('risk_fee_warning_amount','5000'),'medium_score'=>$get('risk_medium_score','25'),'high_score'=>$get('risk_high_score','50'),'critical_score'=>$get('risk_critical_score','75')]; }
    private function averageMarks(array $rows):?float{if(!$rows)return null;return round(array_sum(array_map(static fn($r)=>(float)$r['average_percent'],$rows))/count($rows),1);}
}
