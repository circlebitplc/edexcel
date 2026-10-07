<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('POST required.');
    if(!verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Invalid security token.');
    if(!is_admin()) throw new RuntimeException('Only an administrator can lock or unlock lessons.');

    $id=(int)($_POST['entry_id']??0);
    if($id<=0) throw new RuntimeException('Invalid lesson ID.');

    $services=TimetableServiceFactory::services($pdo);
    $result=$services['lock']->toggle(
        $id,
        static function(array $entry): void {
            if(!is_admin()) throw new RuntimeException('Only an administrator can lock or unlock lessons.');
        }
    );

    echo json_encode(['success'=>true]+$result);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
