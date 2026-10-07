<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAuth;
require_admin();
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        AdmissionAuth::grant($pdo, (int)$_POST['user_id'], (string)$_POST['permission'], (int)($_SESSION['user_id'] ?? 0));
        $success = 'Permission granted. The staff member can use only that admissions action.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$rows = [];
try { $rows = $pdo->query('SELECT user_id,permission,created_at FROM staff_permissions ORDER BY user_id,permission')->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:720px">
    <a href="admissions_control.php">← Command centre</a>
    <h1 class="h3">Admissions permissions</h1>
    <p class="text-muted">Administrators always have access. Teachers have none unless granted here.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?>
        <input class="form-control mb-2" name="user_id" placeholder="Staff user ID" required>
        <select class="form-select mb-2" name="permission"><?php foreach (AdmissionAuth::PERMISSIONS as $p): ?><option><?= e($p) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary">Grant</button>
    </form>
    <ul><?php foreach ($rows as $r): ?><li>User <?= (int)$r['user_id'] ?> · <?= e($r['permission']) ?></li><?php endforeach; ?></ul>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
