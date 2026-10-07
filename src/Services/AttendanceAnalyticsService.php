<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class AttendanceAnalyticsService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function student(int $studentId, ?string $from=null, ?string $to=null): array
    {
        $from=$from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)?$from:date('Y-m-01',strtotime('-5 months'));
        $to=$to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)?$to:date('Y-m-d');
        $rows=[];
        try{
            $s=$this->pdo->prepare("SELECT sa.status,tt.date,tt.class_id,tt.subject_id,c.name class_name,s.name subject_name FROM student_attendance sa JOIN timetable tt ON tt.id=sa.timetable_id LEFT JOIN student_classes c ON c.id=tt.class_id LEFT JOIN subjects s ON s.id=tt.subject_id WHERE sa.student_id=? AND tt.date BETWEEN ? AND ? AND tt.deleted_at IS NULL ORDER BY tt.date");
            $s->execute([$studentId,$from,$to]);$rows=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
        }catch(Throwable $e){}
        $total=count($rows);$present=0;$absent=0;$streak=0;$maxStreak=0;$bySubject=[];$byClass=[];$months=[];
        foreach($rows as $r){
            $status=(string)$r['status'];$isPresent=in_array($status,['present','late','excused'],true);
            if($isPresent){$present++;$streak=0;}else{$absent++;$streak++;$maxStreak=max($maxStreak,$streak);}
            foreach([['subject_name',$r['subject_name']??'Unknown',&$bySubject],['class_name',$r['class_name']??'Unknown',&$byClass]] as $item){
                $key=(string)$item[1]; if(!isset($item[2][$key]))$item[2][$key]=['total'=>0,'present'=>0];
                $item[2][$key]['total']++; if($isPresent)$item[2][$key]['present']++;
            }
            $month=substr((string)$r['date'],0,7);if(!isset($months[$month]))$months[$month]=['total'=>0,'present'=>0];$months[$month]['total']++;if($isPresent)$months[$month]['present']++;
        }
        $map=function(array $items):array{foreach($items as &$v)$v['percent']=$v['total']?round($v['present']/$v['total']*100,1):0;return $items;};
        $threshold=80;if(function_exists('ops_setting'))$threshold=(float)ops_setting($this->pdo,'attendance_warning_threshold','80');
        return ['student_id'=>$studentId,'from'=>$from,'to'=>$to,'total'=>$total,'present'=>$present,'absent'=>$absent,'percent'=>$total?round($present/$total*100,1):null,'consecutive_absences'=>$maxStreak,'by_subject'=>$map($bySubject),'by_class'=>$map($byClass),'monthly'=>$map($months),'at_risk'=>$total>0 && ($present/$total*100)<$threshold,'threshold'=>$threshold];
    }

    /** @return list<array<string,mixed>> */
    public function atRisk(int $limit=100): array
    {
        try{$students=$this->pdo->query("SELECT id FROM users WHERE role='student' AND deleted_at IS NULL AND is_active=1 LIMIT ".max(1,min(500,$limit)))->fetchAll(PDO::FETCH_COLUMN)?:[];$out=[];foreach($students as $id){$row=$this->student((int)$id);if($row['at_risk']){$row['student_id']=(int)$id;$out[]=$row;}}return $out;}catch(Throwable $e){return [];}
    }
}
