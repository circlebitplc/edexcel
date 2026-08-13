<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST required.');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) throw new RuntimeException('Invalid security token.');

    $id=(int)($_POST['id']??0);
    if($id<=0) throw new RuntimeException('Invalid lesson ID.');

    $isAdmin=is_admin();
    $sessionTeacher=(int)($_SESSION['teacher_id']??0);

    $payment=$isAdmin
        ? (($_POST['payment_status']??'pending')==='paid'?'paid':'pending')
        : 'pending';

    $services=TimetableServiceFactory::services($pdo);
    $current=$services['repository']->find($id);
    if(!$current || !empty($current['deleted_at'])) throw new RuntimeException('Lesson not found.');
    if(!$isAdmin) $payment=$current['payment_status'];

    $data=[
        'teacher_id'=>$isAdmin?(int)($_POST['teacher_id']??0):$sessionTeacher,
        'subject_id'=>(int)($_POST['subject_id']??0),
        'class_id'=>(int)($_POST['class_id']??0),
        'room_id'=>(int)($_POST['room_id']??0),
        'student_count'=>(int)($_POST['student_count']??0),
        'date'=>substr(trim((string)($_POST['date']??'')),0,10),
        'start_time'=>substr(trim((string)($_POST['start_time']??'')),0,5),
        'end_time'=>substr(trim((string)($_POST['end_time']??'')),0,5),
        'payment_status'=>$payment,
        'payment_date'=>$payment==='paid'?date('Y-m-d'):null,
    ];

    $repeat=!empty($_POST['repeat_weekly']);
    $until=trim((string)($_POST['repeat_until']??''));

    $services['update']->update(
        $id,
        $data,
        static function(array $entry) use ($isAdmin,$sessionTeacher): void {
            if(!$isAdmin && (int)$entry['teacher_id']!==$sessionTeacher) {
                throw new RuntimeException('You can only edit your own lessons.');
            }
        },
        $repeat,
        $until
    );

    echo json_encode(['success'=>true,'id'=>$id]);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
