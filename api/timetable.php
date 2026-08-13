<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_login();

if($_SERVER['REQUEST_METHOD']!=='GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','error'=>'Method not allowed']);
    exit;
}

$teacher_id=(int)($_GET['teacher_id']??0);
if(!is_admin()) {
    $sessionTeacher=(int)($_SESSION['teacher_id']??0);
    if($sessionTeacher<=0) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'error','error'=>'Teacher account is not linked.']);
        exit;
    }
    // Teachers may only read their own timetable.
    if($teacher_id>0 && $teacher_id!==$sessionTeacher) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'error','error'=>'You can only access your own timetable.']);
        exit;
    }
    $teacher_id=$sessionTeacher;
}
$room_id=(int)($_GET['room_id']??0);
$class_id=(int)($_GET['class_id']??0);
$date_from=trim((string)($_GET['date_from']??date('Y-m-d')));
$date_to=trim((string)($_GET['date_to']??date('Y-m-d',strtotime('+7 days'))));
$page=max(1,(int)($_GET['page']??1));
$page_size=max(1,min(500,(int)($_GET['page_size']??100)));
$offset=($page-1)*$page_size;

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from) ||
   !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to) ||
   $date_from>$date_to) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','error'=>'Invalid date range']);
    exit;
}

$entries=TimetableServiceFactory::services($pdo)['repository']
    ->listFiltered($teacher_id,$room_id,$class_id,$date_from,$date_to,$page_size,$offset);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'=>'success',
    'data'=>$entries,
    'meta'=>[
        'date_from'=>$date_from,
        'date_to'=>$date_to,
        'total'=>count($entries)
    ]
]);
