<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('POST required.');
    if(!verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Invalid security token.');

    $id=(int)($_POST['id']??0);
    if($id<=0) throw new RuntimeException('Invalid lesson ID.');

    $isAdmin=is_admin();
    $sessionTeacher=(int)($_SESSION['teacher_id']??0);
    $services=TimetableServiceFactory::services($pdo);

    $services['delete']->delete(
        $id,
        static function(array $entry) use ($isAdmin,$sessionTeacher): void {
            if(!$isAdmin && (int)$entry['teacher_id']!==$sessionTeacher) {
                throw new RuntimeException('You can only delete your own entries.');
            }
        }
    );

    echo json_encode(['success'=>true,'id'=>$id]);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
