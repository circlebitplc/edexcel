<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Http\ApiAuth;
use Edexcel\Http\ApiGuard;
use Edexcel\Http\ApiRateLimiter;
use Edexcel\Http\ApiRequest;
use Edexcel\Http\ApiResponse;
use Edexcel\Services\AppLogger;
use Edexcel\Services\ApiIdempotencyService;
use Edexcel\Services\CollegeReadService;
use Edexcel\Services\AssessmentAttemptService;
use Edexcel\Services\AssessmentService;
use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\AdmissionLifecycleService;
use Edexcel\Services\LeadService;
use Edexcel\Services\NotificationCenterService;
use Edexcel\Services\PublicCatalogueService;
use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\CommunicationThreadService;
use Edexcel\Services\CommunicationPreferenceService;
use Edexcel\Services\CommunicationAnalyticsService;
use Edexcel\Services\AnnouncementService;
use Edexcel\Services\MessageTemplateService;

header('X-API-Version: 1');
ApiGuard::assertTransport();
$logger=new AppLogger($pdo instanceof PDO?$pdo:null);

try {
    if (!($pdo instanceof PDO)) ApiResponse::error('SERVICE_UNAVAILABLE','Database unavailable.',[],503);
    $auth=ApiAuth::requireSession($pdo);
    ApiRateLimiter::check(($auth['role']??'guest').':'.($auth['user_id']?:$auth['parent_id']),120,60);
    $resource=strtolower(trim((string)($_GET['resource']??'')));
    $id=(int)($_GET['id']??0);
    if ($resource==='') {
        ApiResponse::success(['version'=>'v1','resources'=>['students','parents','teachers','classes','subjects','timetable','notifications','analytics','assessments','admissions','leads','applications','enrollments','programmes','class-availability','communications','messages','threads','announcements','message-templates','notification-preferences'],'public'=>'/api/v1/public.php']);
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && in_array($resource,['leads','admissions'],true)) {
        if(!AdmissionAuth::can($pdo,(int)$auth['user_id'],'admissions.create'))ApiResponse::error('FORBIDDEN','Admissions permission required.',[],403);
        $body=ApiRequest::body();ApiAuth::csrfRequired($body);
        $key=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??$body['idempotency_key']??''));
        if($key==='')ApiResponse::error('IDEMPOTENCY_REQUIRED','Idempotency-Key is required for this mutation.',[],422);
        $hash=hash('sha256',json_encode($body,JSON_UNESCAPED_UNICODE));$idem=new ApiIdempotencyService($pdo);$existing=$idem->existing($key,(int)$auth['user_id'],'/api/v1/'.$resource,$hash);
        if($existing)ApiResponse::raw($existing['payload'],$existing['status']);
        $id=(new LeadService($pdo))->create($body,(int)$auth['user_id']);
        $payload=['success'=>true,'data'=>['lead_id'=>$id],'meta'=>[]];$idem->store($key,(int)$auth['user_id'],'/api/v1/'.$resource,$hash,200,$payload);ApiResponse::raw($payload);
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && $resource==='assessments') {
        $body=ApiRequest::body();ApiAuth::csrfRequired($body);
        $key=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??$body['idempotency_key']??''));
        if($key==='')ApiResponse::error('IDEMPOTENCY_REQUIRED','Idempotency-Key is required for this mutation.',[],422);
        $hash=hash('sha256',json_encode($body,JSON_UNESCAPED_UNICODE));$idem=new ApiIdempotencyService($pdo);$existing=$idem->existing($key,(int)($auth['user_id']?:$auth['parent_id']),'/api/v1/assessments',$hash);
        if($existing)ApiResponse::raw($existing['payload'],$existing['status']);
        $engine=new AssessmentAttemptService($pdo);
        $action=(string)($body['action']??'');
        $out=[];
        if($action==='start'){$out=$engine->start((int)($body['assessment_id']??0),(int)$auth['user_id']);}
        elseif($action==='save'){$out=$engine->save((int)($body['attempt_id']??0),(int)$auth['user_id'],is_array($body['answers']??null)?$body['answers']:[]);}
        elseif($action==='submit'){$out=$engine->submit((int)($body['attempt_id']??0),(int)$auth['user_id']);}
        elseif($action==='event'){$engine->event((int)($body['attempt_id']??0),(string)($body['event_type']??'activity'),(string)($body['detail']??''));$out=['recorded'=>true];}
        else ApiResponse::error('VALIDATION_ERROR','Unsupported assessment action.',[],422);
        $payload=['success'=>true,'data'=>$out,'meta'=>[]];$idem->store($key,(int)($auth['user_id']?:$auth['parent_id']),'/api/v1/assessments',$hash,200,$payload);ApiResponse::raw($payload);
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && $resource==='notifications') {
        $body=ApiRequest::body();ApiAuth::csrfRequired($body);
        $key=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??$body['idempotency_key']??''));
        if($key==='')ApiResponse::error('IDEMPOTENCY_REQUIRED','Idempotency-Key is required for this mutation.',[],422);
        $hash=hash('sha256',json_encode($body,JSON_UNESCAPED_UNICODE));$idem=new ApiIdempotencyService($pdo);$existing=$idem->existing($key,(int)($auth['user_id']?:$auth['parent_id']),'/api/v1/notifications',$hash);
        if($existing)ApiResponse::raw($existing['payload'],$existing['status']);
        $audience=($auth['role']??'')==='parent'?'parent':(string)$auth['role'];$notice=new NotificationCenterService($pdo);
        $marked=!empty($body['all'])?$notice->markAllRead($audience,$auth['user_id']?:null,$auth['parent_id']?:null):($notice->markRead((int)($body['id']??0),$audience,$auth['user_id']?:null,$auth['parent_id']?:null)?1:0);
        $payload=['success'=>true,'data'=>['marked'=>$marked],'meta'=>[]];$idem->store($key,(int)($auth['user_id']?:$auth['parent_id']),'/api/v1/notifications',$hash,200,$payload);ApiResponse::raw($payload);
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') ApiResponse::error('METHOD_NOT_ALLOWED','This endpoint is currently read-only.',[],405);
    $page=ApiRequest::pagination();$search=ApiRequest::search();$read=new CollegeReadService($pdo);
    switch($resource){
        case 'students':
            if($id>0){$row=$read->student($id,$auth);if(!$row)ApiResponse::error('NOT_FOUND','Student not found.',[],404);ApiResponse::success(['student'=>$row]);}
            $result=$read->students($auth,$search,$page['per_page'],$page['offset']);break;
        case 'teachers':
            $result=$read->teachers($auth,$search,$page['per_page'],$page['offset']);break;
        case 'classes':
            $result=$read->classes($auth,$search,$page['per_page'],$page['offset']);break;
        case 'subjects':
            $result=$read->subjects($auth,$search,$page['per_page'],$page['offset']);break;
        case 'timetable':
            $from=(string)($_GET['from']??date('Y-m-d'));$to=(string)($_GET['to']??date('Y-m-d',strtotime('+7 days')));
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to)ApiResponse::error('VALIDATION_ERROR','Invalid date range.',[],422);
            $result=$read->timetable($auth,$from,$to,$page['per_page'],$page['offset']);$result['date_from']=$from;$result['date_to']=$to;break;
        case 'notifications':
            $audience=($auth['role']??'')==='parent'?'parent':(string)$auth['role'];
            if(!in_array($audience,NotificationCenterService::AUDIENCES,true))ApiResponse::error('FORBIDDEN','Notifications are not available for this account.',[],403);
            $rows=(new NotificationCenterService($pdo))->listFor($audience,$auth['user_id']?:null,$auth['parent_id']?:null,['limit'=>$page['per_page'],'offset'=>$page['offset'],'category'=>$_GET['category']??'']);
            ApiResponse::success(['notifications'=>$rows],['page'=>$page['page'],'per_page'=>$page['per_page'],'total'=>count($rows),'total_pages'=>count($rows)<$page['per_page']?$page['page']:null]);break;
        case 'parents':
            if(($auth['role']??'')==='parent'){$s=$pdo->prepare('SELECT id,phone,name,last_login_at FROM parent_accounts WHERE id=?');$s->execute([$auth['parent_id']]);$rows=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
            elseif(($auth['role']??'')==='admin'){$rows=$pdo->query('SELECT id,phone,name,last_login_at FROM parent_accounts ORDER BY name,id LIMIT '.(int)$page['per_page'].' OFFSET '.(int)$page['offset'])->fetchAll(PDO::FETCH_ASSOC)?:[];}
            else ApiResponse::error('FORBIDDEN','Parent records are restricted.',[],403);
            ApiResponse::success(['parents'=>$rows],['page'=>$page['page'],'per_page'=>$page['per_page'],'total'=>count($rows)]);break;
        case 'assessments':
            $asvc=new AssessmentService($pdo);
            if($id>0){
                $row=$asvc->get($id);if(!$row)ApiResponse::error('NOT_FOUND','Assessment not found.',[],404);
                if(($auth['role']??'')==='student'){
                    $engine=new AssessmentAttemptService($pdo);
                    $open=$engine->openAttempt($id,(int)$auth['user_id']);
                    if($open){ApiResponse::success($engine->payload($row,$open,(int)$auth['user_id']));}
                    ApiResponse::success(['assessment'=>['id'=>(int)$row['id'],'title'=>$row['title'],'status'=>$row['status'],'start_at'=>$row['start_at'],'end_at'=>$row['end_at'],'duration_minutes'=>$row['duration_minutes']],'attempt'=>null]);
                }
                if(!$asvc->canManage($auth,$row))ApiResponse::error('FORBIDDEN','Assessment access denied.',[],403);
                ApiResponse::success(['assessment'=>$row,'questions'=>$asvc->questions($id)]);
            }
            $result=['rows'=>$asvc->dashboard(['status'=>(string)($_GET['status']??''),'assessment_type'=>(string)($_GET['type']??'')],$auth),'total'=>0];
            $result['total']=count($result['rows']);break;
        case 'leads':
        case 'admissions':
        case 'applications':
            if(!AdmissionAuth::can($pdo,(int)$auth['user_id'],'admissions.view'))ApiResponse::error('FORBIDDEN','Admissions records are restricted.',[],403);
            if($resource==='leads'){$rows=(new LeadService($pdo))->pipeline(['status'=>(string)($_GET['status']??'')]);}
            else {$life=new AdmissionLifecycleService($pdo);if($id>0){$row=$life->get($id);if(!$row)ApiResponse::error('NOT_FOUND','Application not found.',[],404);ApiResponse::success(['application'=>$row,'timeline'=>$life->timeline((int)($row['lead_id']??0)?:null,$id,(int)($row['student_id']??0)?:null)]);} $rows=$life->dashboard(['status'=>(string)($_GET['status']??'')]);}
            $result=['rows'=>$rows,'total'=>count($rows)];break;
        case 'enrollments':
            if(($auth['role']??'')==='student'){$s=$pdo->prepare('SELECT se.class_id,c.name FROM student_enrollments se JOIN student_classes c ON c.id=se.class_id WHERE se.student_id=?');$s->execute([(int)$auth['user_id']]);$rows=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
            elseif(AdmissionAuth::can($pdo,(int)$auth['user_id'],'admissions.view')||($auth['role']??'')==='admin'){$rows=$pdo->query('SELECT student_id,class_id,enrolled_at FROM student_enrollments ORDER BY enrolled_at DESC LIMIT '.(int)$page['per_page'].' OFFSET '.(int)$page['offset'])->fetchAll(PDO::FETCH_ASSOC)?:[];}
            else ApiResponse::error('FORBIDDEN','Enrollment records are restricted.',[],403);
            $result=['rows'=>$rows,'total'=>count($rows)];break;
        case 'programmes':
        case 'class-availability':
            $cat=new PublicCatalogueService($pdo);ApiResponse::success($resource==='class-availability'?['classes'=>$cat->classes()]:$cat->catalogue(),['note'=>'Staff catalogue. Public website should use /api/v1/public.php']);break;
        case 'communications':
        case 'messages':
            if(!CommunicationAuth::can($pdo,(int)$auth['user_id'],'communication.view')&&($auth['role']??'')!=='admin'&&($auth['role']??'')!=='teacher')ApiResponse::error('FORBIDDEN','Communication access denied.',[],403);
            $hub=new CommunicationHubService($pdo);
            if($id>0){
                $rows=$hub->search(['q'=>''],50);
                $row=null;foreach($rows as $r){if((int)$r['id']===$id){$row=$r;break;}}
                if(!$row){try{$s=$pdo->prepare('SELECT * FROM communication_messages WHERE id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC)?:null;}catch(Throwable $e){$row=null;}}
                if(!$row)ApiResponse::error('NOT_FOUND','Message not found.',[],404);
                ApiResponse::success(['message'=>$row]);
            }
            $result=['rows'=>$hub->search([
                'channel'=>(string)($_GET['channel']??''),
                'status'=>(string)($_GET['status']??''),
                'category'=>(string)($_GET['category']??''),
                'from'=>(string)($_GET['from']??''),
                'to'=>(string)($_GET['to']??''),
                'q'=>(string)($_GET['q']??$search),
            ],$page['per_page']),'total'=>0];
            $result['total']=count($result['rows']);
            if(($auth['role']??'')==='admin'||CommunicationAuth::can($pdo,(int)$auth['user_id'],'communication.analytics')){
                $result['centre']=$hub->centreSnapshot();
            }
            break;
        case 'threads':
            $role=(string)($auth['role']??'');
            $uid=(int)($auth['user_id']?:$auth['parent_id']?:0);
            if($role==='parent'){$role='parent';$uid=(int)$auth['parent_id'];}
            $tsvc=new CommunicationThreadService($pdo);
            if($id>0){
                if(!$tsvc->canAccess($id,$role==='admin'?'admin':$role,$uid))ApiResponse::error('FORBIDDEN','Thread access denied.',[],403);
                ApiResponse::success(['thread'=>$tsvc->get($id),'messages'=>$tsvc->messages($id)]);
            }
            $result=['rows'=>$tsvc->inbox($role==='admin'?'admin':$role,$uid,$page['per_page']),'total'=>0];
            $result['total']=count($result['rows']);break;
        case 'announcements':
            $asvc=new AnnouncementService($pdo);
            if(($auth['role']??'')==='admin'||CommunicationAuth::can($pdo,(int)$auth['user_id'],'communication.view')){
                $result=['rows'=>$asvc->recent($page['per_page']),'total'=>0];
            } else {
                $aud=($auth['role']??'')==='parent'?'parents':((($auth['role']??'')==='teacher')?'teachers':'students');
                $result=['rows'=>$asvc->visible($aud,(int)($auth['user_id']?:0)?:null),'total'=>0];
            }
            $result['total']=count($result['rows']);break;
        case 'message-templates':
            if(!CommunicationAuth::can($pdo,(int)$auth['user_id'],'communication.templates')&&($auth['role']??'')!=='admin'&&($auth['role']??'')!=='teacher')ApiResponse::error('FORBIDDEN','Templates restricted.',[],403);
            $rows=(new MessageTemplateService($pdo))->list(true);
            $result=['rows'=>$rows,'total'=>count($rows)];break;
        case 'notification-preferences':
            $prefs=new CommunicationPreferenceService($pdo);
            $aud=($auth['role']??'')==='parent'?'parent':(string)($auth['role']??'student');
            $cats=['attendance','fees','exams','homework','announcements','classes','payments','system'];
            $rows=[];
            foreach($cats as $cat){$rows[]=$prefs->get($aud,$auth['user_id']?:null,$auth['parent_id']?:null,$cat);}
            ApiResponse::success(['preferences'=>$rows,'mandatory'=>CommunicationPreferenceService::MANDATORY]);break;
        case 'communication-analytics':
            if(!CommunicationAuth::can($pdo,(int)$auth['user_id'],'communication.analytics')&&($auth['role']??'')!=='admin')ApiResponse::error('FORBIDDEN','Analytics restricted.',[],403);
            $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
            $analytics=new CommunicationAnalyticsService($pdo);
            ApiResponse::success(['summary'=>$analytics->summary($from,$to),'engagement'=>$analytics->engagement()]);break;
        case 'analytics':
            $data=$read->analytics($auth);if($data===[])ApiResponse::error('FORBIDDEN','Analytics are restricted to administrators.',[],403);ApiResponse::success($data);break;
        default: ApiResponse::error('NOT_FOUND','Unknown API resource.',[],404);
    }
    $total=(int)($result['total']??count($result['rows']??[]));$payload=['items'=>$result['rows']??[]];unset($result['rows'],$result['total']);$payload=array_merge($payload,$result);
    ApiResponse::success($payload,['page'=>$page['page'],'per_page'=>$page['per_page'],'total'=>$total,'total_pages'=>$total>0?(int)ceil($total/$page['per_page']):0]);
} catch (\InvalidArgumentException $e) {
    ApiResponse::error('VALIDATION_ERROR',$e->getMessage(),[],422);
} catch (\RuntimeException $e) {
    $message=strtolower($e->getMessage());$status=str_contains($message,'authentication')?401:(str_contains($message,'csrf')?403:(str_contains($message,'rate limit')?429:400));
    ApiResponse::error($status===401?'UNAUTHENTICATED':($status===403?'FORBIDDEN':($status===429?'RATE_LIMITED':'BAD_REQUEST')),$e->getMessage(),[],$status);
} catch (\Throwable $e) {
    ApiResponse::fromThrowable($e,$logger);
}
