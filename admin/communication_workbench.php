<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\StaffCommunicationWorkbenchService;

try {
    CommunicationAuth::require($pdo, 'communication.view');
} catch (Throwable $e) {
    require_staff();
}

$wb = new StaffCommunicationWorkbenchService($pdo);
$q = trim((string)($_GET['q'] ?? ''));
$results = $q !== '' ? $wb->searchStudents($q, 30) : [];
$role = (string)($_SESSION['role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:720px">
    <a href="communications.php">← Communication centre</a>
    <h1 class="h3 mt-2">Staff communication workbench</h1>
    <p class="text-muted">Open a student communication timeline (authorized classes only for teachers).</p>
    <form class="card border-0 shadow-sm p-3 mb-3" method="get">
        <label class="form-label">Find student</label>
        <div class="input-group">
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, username, or ID" autofocus>
            <button class="btn btn-primary">Search</button>
        </div>
    </form>
    <?php if ($q !== '' && $results === []): ?>
        <div class="alert alert-light border">No students matched.</div>
    <?php endif; ?>
    <div class="list-group shadow-sm">
        <?php foreach ($results as $r):
            $allowed = $wb->canViewStudent($userId, $role, (int)$r['id']);
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div><strong><?= e($r['label']) ?></strong> <span class="text-muted">#<?= (int)$r['id'] ?></span></div>
                <?php if ($allowed): ?>
                    <a class="btn btn-sm btn-outline-primary" href="student360.php?student=<?= (int)$r['id'] ?>&tab=communication">Communication</a>
                <?php else: ?>
                    <span class="small text-muted">No access</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
