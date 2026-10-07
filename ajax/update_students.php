<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_staff();
header('Content-Type: application/json; charset=utf-8');

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('POST required.');
    if(!verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Invalid CSRF token.');

    $id=(int)($_POST['id']??0);
    $count=(int)($_POST['student_count']??-1);
    $isAdmin=is_admin();
    $teacher=(int)($_SESSION['teacher_id']??0);

    $services=TimetableServiceFactory::services($pdo);
    if(!isset($services['student_count'])) {
        throw new RuntimeException('Student-count service is not configured.');
    }

    $result=$services['student_count']->update(
        $id,$count,
        static function(array $entry) use ($isAdmin,$teacher): void {
            if(!$isAdmin && (int)$entry['teacher_id']!==$teacher) {
                throw new RuntimeException('Permission denied.');
            }
        }
    );

    echo json_encode(['success'=>true,'message'=>'Student count updated successfully.']+$result);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
