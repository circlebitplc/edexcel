<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class AutomationService
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $payload @return array{completed:int,skipped:int,failed:int} */
    public function handle(string $eventName,string $eventId,array $payload):array
    {
        $stats=['completed'=>0,'skipped'=>0,'failed'=>0];$s=$this->pdo->prepare('SELECT * FROM automation_rules WHERE event_name=? AND enabled=1 ORDER BY id');$s->execute([$eventName]);
        foreach($s->fetchAll(PDO::FETCH_ASSOC)?:[] as $rule){$conditions=json_decode((string)$rule['conditions_json'],true);if(!is_array($conditions)||!$this->matches($conditions,$payload)){$stats['skipped']++;continue;}$key=hash('sha256',$eventId.'|'.$rule['id'].'|'.(int)$rule['cooldown_seconds']);try{$existing=$this->pdo->prepare('SELECT id FROM automation_runs WHERE rule_id=? AND idempotency_key=? LIMIT 1');$existing->execute([(int)$rule['id'],$key]);if($existing->fetchColumn()){$stats['skipped']++;continue;}$this->pdo->prepare("INSERT INTO automation_runs(rule_id,event_id,idempotency_key,status) VALUES(?,?,?,'completed')")->execute([(int)$rule['id'],$eventId,$key]);$this->actions(json_decode((string)$rule['actions_json'],true)?:[],$payload);$stats['completed']++;if(function_exists('log_audit'))log_audit($this->pdo,'automation_completed','automation_rules',(int)$rule['id'],null,['event_id'=>$eventId,'payload'=>$payload]);}catch(Throwable $e){$stats['failed']++;try{$this->pdo->prepare("INSERT INTO automation_runs(rule_id,event_id,idempotency_key,status,error_message,completed_at) VALUES(?,?,?,'failed',?,NOW())")->execute([(int)$rule['id'],$eventId,$key,mb_substr($e->getMessage(),0,1000)]);}catch(Throwable $ignored){}error_log('automation: '.$e->getMessage());}}
        return$stats;
    }

    /** @return list<array<string,mixed>> */
    public function rules():array{try{return$this->pdo->query('SELECT id,name,event_name,conditions_json,actions_json,enabled,cooldown_seconds,created_at,updated_at FROM automation_rules ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return[];}}
    public function saveRule(array $data,int $userId):int{$name=trim((string)($data['name']??''));$event=trim((string)($data['event_name']??''));$conditions=json_decode((string)($data['conditions_json']??'[]'),true);$actions=json_decode((string)($data['actions_json']??'[]'),true);if($name===''||$event===''||!is_array($conditions)||!is_array($actions))throw new \RuntimeException('Valid name, event, conditions JSON and actions JSON are required.');$this->pdo->prepare('INSERT INTO automation_rules(name,event_name,conditions_json,actions_json,recipients_json,cooldown_seconds,enabled,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$name,$event,json_encode($conditions),json_encode($actions),$data['recipients_json']??null,max(60,(int)($data['cooldown_seconds']??86400),),!empty($data['enabled'])?1:0,$userId]);return(int)$this->pdo->lastInsertId();}

    private function matches(array $conditions,array $payload):bool{foreach($conditions as $c){$field=(string)($c['field']??'');$op=(string)($c['operator']??'equals');$actual=$payload[$field]??null;$expected=$c['value']??null;$ok=match($op){'equals'=>$actual==$expected,'not_equals'=>$actual!=$expected,'lt'=>(float)$actual<(float)$expected,'lte'=>(float)$actual<=(float)$expected,'gt'=>(float)$actual>(float)$expected,'gte'=>(float)$actual>=(float)$expected,'contains'=>is_string($actual)&&str_contains($actual,(string)$expected),default=>false};if(!$ok)return false;}return true;}
    private function actions(array $actions,array $payload):void
    {
        foreach($actions as $action){
            $type=(string)($action['type']??'');
            if($type==='notification'){
                $audience=(string)($action['audience']??'admin');
                $userId=isset($payload['user_id'])?(int)$payload['user_id']:null;
                (new NotificationCenterService($this->pdo))->create($audience,'system',(string)($action['title']??'Automated college alert'),(string)($action['body']??'An automated follow-up is required.'),$action['link']??null,$userId,null,(string)($action['priority']??'normal'),['automated'=>true,'event'=>$payload]);
            } elseif($type==='communication'){
                // Queue via hub — never send synchronously.
                $hub=new CommunicationHubService($this->pdo);
                $hub->preview([
                    'channel'=>(string)($action['channel']??'in_app'),
                    'audience_type'=>(string)($action['audience_type']??'individual'),
                    'audience_id'=>(int)($action['audience_id']??$payload['student_id']??0)?:null,
                    'recipient'=>(string)($action['recipient']??$payload['phone']??''),
                    'category'=>(string)($action['category']??'system'),
                    'subject'=>(string)($action['title']??'College notice'),
                    'body'=>(string)($action['body']??'An automated college notice is available in the portal.'),
                    'variables'=>is_array($payload)?$payload:[],
                    'idempotency_key'=>'auto-'.md5(json_encode([$action,$payload])),
                ], (int)($payload['actor_id']??0));
            } elseif($type==='intervention'&&!empty($payload['student_id'])){
                (new StudentRiskService($this->pdo))->createIntervention(['student_id'=>(int)$payload['student_id'],'intervention_type'=>$action['intervention_type']??'general','reason'=>$action['reason']??'Automated follow-up required','priority'=>$action['priority']??'normal'],(int)($_SESSION['user_id']??0));
            }
        }
    }
}
