<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\WaitlistOfferService;

require_student();
ensure_ops_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$token = strtolower(preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? $_POST['t'] ?? '')) ?? '');
$svc = new WaitlistOfferService($pdo);
$offer = $svc->findByToken($token);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $svc->accept($token, $studentId);
        $success = 'You are enrolled. Open My Classes.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="max-width:560px">
    <h1 class="h4">Waitlist seat</h1>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <a class="btn btn-primary" href="/student/dashboard.php?tab=classes">My Classes</a>
    <?php elseif (!$offer): ?>
        <p class="text-muted">This offer link is not valid.</p>
    <?php elseif ((int)$offer['student_id'] !== $studentId): ?>
        <p class="text-muted">Sign in with the student account that received this offer.</p>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <p>A seat opened in <strong><?= e($offer['class_name']) ?></strong>. Offer expires <?= e($offer['expires_at']) ?>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= e($token) ?>">
            <button class="btn btn-primary rounded-pill">Join this class</button>
        </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
