<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class StudentSuccessService
{
    public function __construct(private PDO $pdo) {}

    public static function classifyScore(float $score): string
    { return $score>=70?'CRITICAL':($score>=50?'AT_RISK':($score>=25?'WATCH':'HEALTHY')); }

    /** @return array<string,mixed> */
    public function assess(int $studentId,bool $persist=true):array
    {
        $academic=(new AcademicProgressService($this->pdo))->forStudent($studentId);
        $recentAttendance=$this->attendance($studentId,30);$previousAttendance=$this->attendance($studentId,60,30);
        $recentAcademic=$this->marks($studentId,30);$previousAcademic=$this->marks($studentId,60,30);
        $attendance=$recentAttendance['percent']??$academic['attendance_percent'];
        $homework=$academic['homework']['completion_percent'];
        $fees=(new FinanceService($this->pdo))->studentBalance($studentId);
        $score=0.0;$reasons=[];$retention=[];$retentionScore=0.0;
        if($attendance!==null&&$attendance<75){$score+=25;$reasons[]='Recent attendance is '.round($attendance,1).'%, below the configured 75% baseline.';}
        if($recentAttendance['percent']!==null&&$previousAttendance['percent']!==null&&$recentAttendance['percent']<$previousAttendance['percent']-10){$score+=15;$retentionScore+=25;$reasons[]='Attendance decreased from '.round($previousAttendance['percent'],1).'% to '.round($recentAttendance['percent'],1).'%. ';$retention[]='Attendance decline over the last 60 days.';}
        if($recentAcademic!==null&&$recentAcademic<60){$score+=25;$reasons[]='Recent assessment average is '.round($recentAcademic,1).'%. '; }
        if($recentAcademic!==null&&$previousAcademic!==null&&$recentAcademic<$previousAcademic-10){$score+=15;$retentionScore+=20;$reasons[]='Recent assessment performance has declined.';$retention[]='Academic performance decline.';}
        if($homework!==null&&$homework<60){$score+=15;$retentionScore+=15;$reasons[]='Homework completion is '.round($homework,1).'%. ';$retention[]='Homework activity has reduced.';}
        if((float)$fees['outstanding']>0){$score+=10;$retentionScore+=10;$reasons[]='Outstanding balance exists.';$retention[]='Outstanding fees may require human follow-up.';}
        $activity=$this->activityCount($studentId,30);if($activity===0){$retentionScore+=15;$retention[]='No recorded portal/Courso activity in the last 30 days.';}
        $level=self::classifyScore($score);
        $retentionLevel=$retentionScore>=50?'HIGH':($retentionScore>=25?'MEDIUM':'LOW');
        $result=['student_id'=>$studentId,'success_level'=>$level,'success_score'=>round(min(100,$score),1),'attendance_recent'=>$attendance,'attendance_previous'=>$previousAttendance['percent'],'academic_recent'=>$recentAcademic,'academic_previous'=>$previousAcademic,'homework_percent'=>$homework,'outstanding_amount'=>(float)$fees['outstanding'],'reasons'=>array_values(array_filter(array_map('trim',$reasons))),'retention_level'=>$retentionLevel,'retention_reasons'=>array_values(array_filter($retention)),'calculated_at'=>date('Y-m-d H:i:s')];
        if($persist)$this->persist($result);
        return $result;
    }

    /** @return list<array<string,mixed>> */
    public function dashboard(?string $level=null,int $limit=200):array
    {
        try{$s=$this->pdo->query("SELECT id FROM users WHERE role='student' AND is_active=1 AND deleted_at IS NULL ORDER BY id LIMIT ".max(1,min(500,$limit)));$ids=$s->fetchAll(PDO::FETCH_COLUMN)?:[];}catch(Throwable $e){return [];}
        $out=[];foreach($ids as $id){$row=$this->assess((int)$id);if($level===null||$row['success_level']===$level)$out[]=$row;}usort($out,static fn($a,$b)=>$b['success_score']<=>$a['success_score']);return $out;
    }

    private function persist(array $r):void
    {
        try{$old=$this->pdo->prepare('SELECT success_level,success_score FROM student_success_snapshots WHERE student_id=?');$old->execute([$r['student_id']]);$previous=$old->fetch(PDO::FETCH_ASSOC);
            $this->pdo->prepare("INSERT INTO student_success_snapshots(student_id,success_level,success_score,attendance_recent,attendance_previous,academic_recent,academic_previous,homework_percent,outstanding_amount,reasons_json,retention_level,retention_reasons_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE success_level=VALUES(success_level),success_score=VALUES(success_score),attendance_recent=VALUES(attendance_recent),attendance_previous=VALUES(attendance_previous),academic_recent=VALUES(academic_recent),academic_previous=VALUES(academic_previous),homework_percent=VALUES(homework_percent),outstanding_amount=VALUES(outstanding_amount),reasons_json=VALUES(reasons_json),retention_level=VALUES(retention_level),retention_reasons_json=VALUES(retention_reasons_json),calculated_at=NOW()")->execute([$r['student_id'],$r['success_level'],$r['success_score'],$r['attendance_recent'],$r['attendance_previous'],$r['academic_recent'],$r['academic_previous'],$r['homework_percent'],$r['outstanding_amount'],json_encode($r['reasons']),$r['retention_level'],json_encode($r['retention_reasons'])]);
            $this->pdo->prepare('INSERT INTO student_success_history(student_id,success_level,success_score,reasons_json) VALUES(?,?,?,?)')->execute([$r['student_id'],$r['success_level'],$r['success_score'],json_encode($r['reasons'])]);
            if($previous&&$previous['success_level']!==$r['success_level']&&function_exists('log_audit'))log_audit($this->pdo,'student_success_changed','student_success_snapshots',(int)$r['student_id'],$previous,$r);
        }catch(Throwable $e){error_log('student success persist: '.$e->getMessage());}
    }
    private function attendance(int $id,int $days,int $offset=0):array
    {try{$from=date('Y-m-d',strtotime('-'.($days+$offset).' days'));$to=date('Y-m-d',strtotime('-'.$offset.' days'));return(new AttendanceAnalyticsService($this->pdo))->student($id,$from,$to);}catch(Throwable $e){return['percent'=>null];}}
    private function marks(int $id,int $days,int $offset=0):?float
    {try{$from=date('Y-m-d',strtotime('-'.($days+$offset).' days'));$to=date('Y-m-d',strtotime('-'.$offset.' days'));$s=$this->pdo->prepare('SELECT AVG(score/NULLIF(max_score,0)*100) FROM student_progress WHERE student_id=? AND recorded_at BETWEEN ? AND ? AND max_score>0');$s->execute([$id,$from,$to]);$v=$s->fetchColumn();return$v===false||$v===null?null:round((float)$v,1);}catch(Throwable $e){return null;}}
    private function activityCount(int $id,int $days):int
    {$days=max(1,min(365,$days));try{$s=$this->pdo->prepare("SELECT COUNT(*) FROM courso_activity WHERE student_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)");$s->execute([$id]);return(int)$s->fetchColumn();}catch(Throwable $e){return 0;}}
}
