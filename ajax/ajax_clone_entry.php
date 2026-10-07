<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_staff();
header('Content-Type: application/json; charset=utf-8');

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('POST required.');
    if(!verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Invalid CSRF token.');

    $id=(int)($_POST['id']??0);
    $date=trim((string)($_POST['date']??date('Y-m-d')));
    $isAdmin=is_admin();
    $teacher=(int)($_SESSION['teacher_id']??0);

    $newId=TimetableServiceFactory::services($pdo)['clone']->clone(
        $id,$date,
        static function(array $entry) use ($isAdmin,$teacher): void {
            if(!$isAdmin && (int)$entry['teacher_id']!==$teacher) {
                throw new RuntimeException('You can only clone your own entries.');
            }
        }
    );

    echo json_encode(['success'=>true,'new_id'=>$newId]);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
