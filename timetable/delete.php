<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_staff();

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

    $row=$pdo->prepare("
        SELECT tt.*, s.name AS subject_name, c.name AS class_name, r.name AS room_name
        FROM timetable tt
        LEFT JOIN subjects s ON s.id = tt.subject_id
        LEFT JOIN student_classes c ON c.id = tt.class_id
        LEFT JOIN rooms r ON r.id = tt.room_id
        WHERE tt.id = ?
    ");
    $row->execute([$id]);
    $entry=$row->fetch(PDO::FETCH_ASSOC);

    TimetableServiceFactory::services($pdo)['delete']->delete(
        $id,
        static function(array $entry) use ($isAdmin,$teacher): void {
            if(!$isAdmin && (int)$entry['teacher_id']!==$teacher) {
                throw new RuntimeException('You can only delete your own entries.');
            }
        }
    );
    if ($entry) {
        require_once __DIR__ . '/../config/notifications.php';
        try {
            notify_class_change($pdo, (int)$entry['teacher_id'], 'delete', $entry);
            notify_class_students($pdo, (int)$entry['class_id'], 'delete', $entry);
        } catch (Throwable $e) {
            error_log('Timetable delete notify: ' . $e->getMessage());
        }
    }
    $_SESSION['success']='Entry deleted successfully.';
} catch(Throwable $e) {
    $_SESSION['error']=$e->getMessage();
}
header('Location: index.php');
exit();
