<?php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../config/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function bulk_json(bool $success,string $message='',array $extra=[],int $status=200): never {
    while(ob_get_level()>0) ob_end_clean();
    http_response_code($status);
    echo json_encode(array_merge(['success'=>$success,'message'=>$message],$extra),JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') bulk_json(false,'POST request required.',[],405);
    if(!is_admin()) bulk_json(false,'Only administrators can perform bulk timetable actions.',[],403);
    if(!verify_csrf_token($_POST['csrf_token']??'')) bulk_json(false,'Invalid security token. Please refresh the page.',[],403);

    $action=trim((string)($_POST['action']??''));
    if(!in_array($action,['mark_paid','delete','lock','unlock'],true)) {
        bulk_json(false,'Invalid bulk action.',[],400);
    }

    $raw=$_POST['ids']??'';
    if(is_array($raw)) $ids=$raw;
    else {
        $raw=trim((string)$raw);
        $decoded=json_decode($raw,true);
        $ids=is_array($decoded)?$decoded:($raw!==''?preg_split('/\s*,\s*/',$raw):($_POST['ids_list']??[]));
    }
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn($id)=>$id>0)));
    if(!$ids) bulk_json(false,'No timetable entries were selected.',[],400);

    $services=TimetableServiceFactory::services($pdo);
    $result=TimetableServiceFactory::bulk($pdo)->execute(
        $action,
        $ids,
        static function(array $entry): void {
            if(!is_admin()) throw new RuntimeException('Only administrators can perform bulk actions.');
        }
    );

    $changed=(int)($result['changed']??0);
    $message=$changed.' '.($changed===1?'entry':'entries').' '.
        match($action){
            'mark_paid'=>'marked as paid.',
            'delete'=>'deleted.',
            'lock'=>'locked.',
            'unlock'=>'unlocked.'
        };

    $smsSent=0;
    $smsFailed=0;
    if($action==='mark_paid') {
        foreach($result['sms']??[] as $sms) {
            $status=(string)($sms['status']??'');
            if($status==='sent' || $status==='resent' || $status==='already_sent') {
                $smsSent++;
            } else {
                $smsFailed++;
            }
        }
        if($changed>0) {
            $message.=' SMS sent: '.$smsSent.'.';
            if($smsFailed>0) {
                $message.=' SMS not sent: '.$smsFailed.'. The payments stay paid.';
            }
        }
    }

    bulk_json(true,$message,[
        'action'=>$action,
        'requested'=>count($ids),
        'changed'=>$changed,
        'notifications_sent'=>$smsSent,
        'sms_sent'=>$smsSent,
        'sms_failed'=>$smsFailed
    ]);
} catch(Throwable $e) {
    error_log('Bulk timetable AJAX error: '.$e->getMessage());
    bulk_json(false,'Server error: '.$e->getMessage(),[],500);
}
