<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CollegeBiService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function summary(string $from,string $to):array
    {
        $from=$this->date($from,date('Y-m-01'));$to=$this->date($to,date('Y-m-d'));
        $out=['from'=>$from,'to'=>$to,'students'=>[],'academic'=>[],'finance'=>(new FinanceService($this->pdo))->collectionReport($from,$to),'operations'=>[]];
        try{$s=$this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1 AND deleted_at IS NULL");$s->execute();$active=(int)$s->fetchColumn();$s=$this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role='student' AND created_at BETWEEN ? AND ?");$s->execute([$from,$to.' 23:59:59']);$out['students']=['active'=>$active,'new'=>(int)$s->fetchColumn()];}catch(Throwable $e){$out['students']=['active'=>null,'new'=>null];}
        try{$s=$this->pdo->prepare("SELECT AVG(score/NULLIF(max_score,0)*100) FROM student_progress WHERE recorded_at BETWEEN ? AND ? AND max_score>0");$s->execute([$from,$to]);$out['academic']['average_mark']=$s->fetchColumn();$s=$this->pdo->prepare("SELECT AVG(status IN ('present','late'))*100 FROM student_attendance WHERE marked_at BETWEEN ? AND ?");$s->execute([$from,$to]);$out['academic']['attendance_percent']=$s->fetchColumn();}catch(Throwable $e){}
        try{$classes=(int)$this->pdo->query("SELECT COUNT(*) FROM student_classes WHERE deleted_at IS NULL")->fetchColumn();$s=$this->pdo->prepare("SELECT COUNT(*) FROM timetable WHERE date BETWEEN ? AND ? AND deleted_at IS NULL");$s->execute([$from,$to]);$lessons=(int)$s->fetchColumn();$s=$this->pdo->prepare("SELECT COUNT(*) FROM timetable WHERE lesson_status='cancelled' AND date BETWEEN ? AND ? AND deleted_at IS NULL");$s->execute([$from,$to]);$out['operations']=['classes'=>$classes,'lessons'=>$lessons,'cancelled'=>(int)$s->fetchColumn()];}catch(Throwable $e){}
        return$out;
    }

    /** @return list<array<string,mixed>> */
    public function classProfitability(string $from,string $to):array
    {
        $from=$this->date($from,date('Y-m-01'));$to=$this->date($to,date('Y-m-d'));$rows=[];$costs=[];
        try{$s=$this->pdo->prepare("SELECT teacher_id,COALESCE(SUM(amount),0) total FROM teacher_payment_events WHERE payment_date BETWEEN ? AND ? GROUP BY teacher_id");$s->execute([$from,$to]);foreach($s->fetchAll(PDO::FETCH_ASSOC)?:[] as $c){$costs[(int)$c['teacher_id']] = (float)$c['total'];}}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT c.id,c.name,c.teacher_id,COUNT(DISTINCT se.student_id) student_count,COALESCE(SUM(pt.amount),0) revenue FROM student_classes c LEFT JOIN student_enrollments se ON se.class_id=c.id LEFT JOIN timetable tt ON tt.class_id=c.id AND tt.date BETWEEN ? AND ? LEFT JOIN payment_transactions pt ON pt.timetable_id=tt.id AND pt.status='paid' WHERE c.deleted_at IS NULL GROUP BY c.id,c.name,c.teacher_id ORDER BY revenue DESC");$s->execute([$from,$to]);$rows=$s->fetchAll(PDO::FETCH_ASSOC)?:[];foreach($rows as &$r){$r['revenue']=(float)$r['revenue'];$r['teacher_cost']=(float)($costs[(int)$r['teacher_id']]??0);$r['estimated_profit']=$r['revenue']-$r['teacher_cost'];$r['profit_margin']=$r['revenue']>0?round($r['estimated_profit']/$r['revenue']*100,1):null;}unset($r);}catch(Throwable $e){}
        return$rows;
    }

    private function date(string $v,string $fallback):string{return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:$fallback;}
}
