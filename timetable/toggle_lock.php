<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

if($_SERVER['REQUEST_METHOD']!=='POST') {
    header('Location: index.php'); exit;
}
if(!verify_csrf_token($_POST['csrf_token']??'')) {
    $_SESSION['error']='Invalid CSRF token.';
    header('Location: index.php'); exit;
}

try {
    $id=(int)($_POST['id']??0);
    if($id<=0) throw new RuntimeException('No ID provided.');

    $result=TimetableServiceFactory::services($pdo)['lock']->toggle(
        $id,
        static function(array $entry): void {
            if(!is_admin()) throw new RuntimeException('Only admin can toggle lock.');
        }
    );

    $_SESSION['success']=$result['status']==='locked'
        ? 'Entry locked successfully.'
        : 'Entry unlocked successfully.';
} catch(Throwable $e) {
    $_SESSION['error']=$e->getMessage();
}
header('Location: index.php');
exit();
