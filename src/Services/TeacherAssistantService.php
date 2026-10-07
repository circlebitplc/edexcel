<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class TeacherAssistantService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function generate(int $teacherId,int $classId,string $request):array
    {
        if(!$this->ownsClass($teacherId,$classId))throw new RuntimeException('Class access denied.');
        $request=mb_substr(trim($request),0,1200);if($request==='')throw new RuntimeException('Assistant request is required.');
        $s=$this->pdo->prepare("SELECT c.name class_name,s.name subject_name,COUNT(DISTINCT se.student_id) student_count,AVG(sp.score/NULLIF(sp.max_score,0)*100) average_mark FROM student_classes c LEFT JOIN subjects s ON s.id=c.subject_id LEFT JOIN student_enrollments se ON se.class_id=c.id LEFT JOIN student_progress sp ON sp.class_id=c.id WHERE c.id=? GROUP BY c.id,c.name,s.name");$s->execute([$classId]);$facts=$s->fetch(PDO::FETCH_ASSOC)?:['class_name'=>'Class','subject_name'=>'Subject','student_count'=>0,'average_mark'=>null];
        $ai=new WhatsAppAiService($this->pdo);$text='';if($ai->enabled()){try{$text=(string)$ai->completeText('You are a teacher planning assistant. Use only verified class facts. Do not invent student identities, marks, dates, or policy. Return a practical draft that a teacher must review.',json_encode(['facts'=>$facts,'request'=>$request],JSON_UNESCAPED_UNICODE),[],900);}catch(Throwable $e){error_log('teacher assistant: '.$e->getMessage());}}
        if($text==='')$text='Verified class facts: '.($facts['class_name']??'Class').' / '.($facts['subject_name']??'Subject').'. Suggested draft: review the class average, select one weak-topic practice activity, and include a short check-for-understanding. Human teacher review is required.';
        $this->pdo->prepare("INSERT INTO ai_recommendations(scope_type,scope_id,recommendation_type,facts_json,recommendation_text,provider,created_by) VALUES('class',?,?,?,?,?,?)")->execute([$classId,'teacher_assistant',json_encode($facts),$text,$ai->providerName(),(int)($_SESSION['user_id']??0)?:null]);
        return['class'=>$facts,'request'=>$request,'recommendation'=>$text];
    }
    private function ownsClass(int $teacherId,int $classId):bool
    {try{$s=$this->pdo->prepare('SELECT 1 FROM timetable WHERE teacher_id=? AND class_id=? AND deleted_at IS NULL LIMIT 1');$s->execute([$teacherId,$classId]);return(bool)$s->fetchColumn();}catch(Throwable $e){return false;}}
}
