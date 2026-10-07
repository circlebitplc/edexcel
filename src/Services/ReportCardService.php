<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class ReportCardService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function build(int $studentId,string $term,string $year=''): ?array
    {
        if($studentId<1||$term==='')return null;
        $attendance=(new AttendanceAnalyticsService($this->pdo))->student($studentId);
        $items=[];
        try{
            $s=$this->pdo->prepare("SELECT sp.subject_id,s.name subject_name,AVG(sp.score/NULLIF(sp.max_score,0)*100) overall_percent,MAX(sp.score/NULLIF(sp.max_score,0)*100) exam_mark FROM student_progress sp LEFT JOIN subjects s ON s.id=sp.subject_id WHERE sp.student_id=? GROUP BY sp.subject_id,s.name ORDER BY s.name");
            $s->execute([$studentId]);$items=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
        }catch(Throwable $e){}
        foreach($items as &$item){$item['attendance_percent']=$attendance['percent'];$item['grade']=$this->grade((float)$item['overall_percent']);}
        $overall=$items?round(array_sum(array_map(static fn($r)=>(float)$r['overall_percent'],$items))/count($items),1):null;
        return ['student_id'=>$studentId,'term'=>$term,'academic_year'=>$year,'items'=>$items,'attendance'=>$attendance,'overall_percent'=>$overall];
    }

    public function save(int $studentId,string $term,string $year,array $data,int $userId,bool $publish=false): int
    {
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare("INSERT INTO report_cards(student_id,term_label,academic_year,status,overall_comment,published_by,published_at) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),overall_comment=VALUES(overall_comment),published_by=VALUES(published_by),published_at=VALUES(published_at)");
            $status=$publish?'published':'draft';$s->execute([$studentId,$term,$year?:null,$status,$data['overall_comment']??null,$publish?$userId:null,$publish?date('Y-m-d H:i:s'):null]);$id=(int)$this->pdo->lastInsertId();
            if($id<1){$q=$this->pdo->prepare('SELECT id FROM report_cards WHERE student_id=? AND term_label=? AND academic_year <=> ?');$q->execute([$studentId,$term,$year?:null]);$id=(int)$q->fetchColumn();}
            foreach((array)($data['items']??[]) as $item){$this->pdo->prepare("INSERT INTO report_card_items(report_card_id,subject_id,subject_name,test_mark,exam_mark,homework_percent,attendance_percent,overall_percent,grade,teacher_comment) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE test_mark=VALUES(test_mark),exam_mark=VALUES(exam_mark),homework_percent=VALUES(homework_percent),attendance_percent=VALUES(attendance_percent),overall_percent=VALUES(overall_percent),grade=VALUES(grade),teacher_comment=VALUES(teacher_comment)")->execute([$id,$item['subject_id']??null,$item['subject_name']??'Subject',$item['test_mark']??null,$item['exam_mark']??null,$item['homework_percent']??null,$item['attendance_percent']??null,$item['overall_percent']??null,$item['grade']??null,$item['teacher_comment']??null]);}
            $this->pdo->commit();if(function_exists('log_audit'))log_audit($this->pdo,$publish?'report_card_published':'report_card_saved','report_cards',$id,null,['student_id'=>$studentId,'term'=>$term]);return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @return array<string,mixed>|null */
    public function published(int $studentId,string $term=''): ?array
    {
        try{$sql="SELECT * FROM report_cards WHERE student_id=? AND status='published'";$p=[$studentId];if($term!==''){$sql.=' AND term_label=?';$p[]=$term;}$sql.=' ORDER BY id DESC LIMIT 1';$s=$this->pdo->prepare($sql);$s->execute($p);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;$i=$this->pdo->prepare('SELECT * FROM report_card_items WHERE report_card_id=? ORDER BY subject_name');$i->execute([(int)$r['id']]);$r['items']=$i->fetchAll(PDO::FETCH_ASSOC)?:[];return $r;}catch(Throwable $e){return null;}
    }

    private function grade(float $p):string{return $p>=80?'A':($p>=70?'B':($p>=60?'C':($p>=50?'D':'E')));}
}
