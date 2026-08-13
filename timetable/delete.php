<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

if($_SERVER['REQUEST_METHOD']!=='POST') {
    header('Location: index.php'); exit;
}
if(!verify_csrf_token($_POST['csrf_token']??'')) {
    $_SESSION['error']='Invalid security token.';
    header('Location: index.php'); exit;
}

try {
    $id=(int)($_POST['id']??0);
    $isAdmin=is_admin();
    $teacher=(int)($_SESSION['teacher_id']??0);

    TimetableServiceFactory::services($pdo)['delete']->delete(
        $id,
        static function(array $entry) use ($isAdmin,$teacher): void {
            if(!$isAdmin && (int)$entry['teacher_id']!==$teacher) {
                throw new RuntimeException('You can only delete your own entries.');
            }
        }
    );
    $_SESSION['success']='Entry deleted successfully.';
} catch(Throwable $e) {
    $_SESSION['error']=$e->getMessage();
}
header('Location: index.php');
exit();
