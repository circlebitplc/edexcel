<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AcademicAdvisorService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function recommend(int $studentId,array $auth):array
    {
        $ctx=new AuthorizedContextService($this->pdo);
        $academic=$ctx->studentAcademic($studentId,$auth);$exam=$ctx->studentExam($studentId,$auth);$learning=$ctx->studentLearning($studentId,$auth);
        $facts=['weak_topics'=>array_slice($academic['weak_topics'],0,8),'strong_topics'=>array_slice($academic['strong_topics'],0,8),'subject_averages'=>array_slice($academic['subject_averages'],0,12),'next_exam'=>$exam['exam_prep']['next_exam']??null,'prep_progress'=>$exam['exam_prep']['prep_progress']??[],'learning'=>$learning['learning']];
        $ai=new WhatsAppAiService($this->pdo);$text='';
        if($ai->enabled()){try{$text=(string)$ai->completeText("You are an academic advisor. Use only the supplied database facts. Return concise, practical revision recommendations. Clearly label recommendations as AI recommendations. Never invent exam dates, scores, names, fees, or classes.",json_encode($facts,JSON_UNESCAPED_UNICODE),[],900);}catch(Throwable $e){error_log('academic advisor: '.$e->getMessage());}}
        if(trim($text)==='')$text=$this->deterministic($facts);
        $id=$this->persist($studentId,$facts,$text,$ai->providerName(),(int)($auth['user_id']??0));
        return['recommendation_id'=>$id,'facts'=>$facts,'recommendation'=>$text,'source'=>'database_facts_plus_ai_or_deterministic_fallback'];
    }

    /** @return list<array<string,mixed>> */
    public function history(int $studentId,array $auth):array
    { (new AuthorizedContextService($this->pdo))->studentAcademic($studentId,$auth);try{$s=$this->pdo->prepare("SELECT id,recommendation_type,recommendation_text,provider,status,created_at,expires_at FROM ai_recommendations WHERE scope_type='student' AND scope_id=? ORDER BY id DESC LIMIT 30");$s->execute([$studentId]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return[];}}

    private function persist(int $studentId,array $facts,string $text,string $provider,int $createdBy):int
    {try{$this->pdo->prepare("INSERT INTO ai_recommendations(scope_type,scope_id,recommendation_type,facts_json,recommendation_text,provider,created_by,expires_at) VALUES('student',?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 14 DAY))")->execute([$studentId,'academic_advisor',json_encode($facts,JSON_UNESCAPED_UNICODE),$text,$provider?:null,$createdBy?:null]);return(int)$this->pdo->lastInsertId();}catch(Throwable $e){return 0;}}

    private function deterministic(array $facts):string
    { $weak=array_map(static fn($r)=>(string)($r['topic_label']??$r['topic_key']??'topic'),array_slice($facts['weak_topics'],0,3));$focus=$weak?implode(', ',$weak):'your least recent subject areas';return"Database facts: focus areas are {$focus}. Recommendation: schedule three short practice sessions this week, complete one targeted quiz after each session, and review the next official exam plan. This is a deterministic fallback because the AI provider was unavailable."; }
}
