<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid request.'; header('Location: index.php'); exit();
}
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    try {
        // Soft-delete when supported, keeping historical timetable relationships intact.
        $stmt = $pdo->prepare("UPDATE rooms SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $_SESSION['success'] = $stmt->rowCount() ? 'Room removed from active scheduling.' : 'Room was already removed.';
    } catch (PDOException $e) { $_SESSION['error'] = 'Cannot delete this room while it is referenced by existing records.'; }
}
header('Location: index.php'); exit();
