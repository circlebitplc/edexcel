<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: index.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE teachers SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE teacher_id = ? AND deleted_at IS NULL")->execute([$id]);
            $pdo->commit();
            $_SESSION['success'] = 'Teacher removed from the active directory.';
        } else {
            $pdo->rollBack();
            $_SESSION['error'] = 'Teacher was already removed or could not be found.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = 'Unable to remove this teacher.';
        error_log('Teacher delete failed: ' . $e->getMessage());
    }
}
header('Location: index.php');
exit();
