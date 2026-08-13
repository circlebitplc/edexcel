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
        $stmt = $pdo->prepare("UPDATE subjects SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $_SESSION[$stmt->rowCount() ? 'success' : 'error'] = $stmt->rowCount() ? 'Subject removed from the active directory.' : 'Subject was already removed or could not be found.';
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Unable to remove this subject.';
        error_log('Subject delete failed: ' . $e->getMessage());
    }
}
header('Location: index.php');
exit();
