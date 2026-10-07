<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionLifecycleService;
$life = new AdmissionLifecycleService($pdo);
$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['token'] ?? $_POST['token'] ?? '')) ?? '';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $life->acceptOffer((int)($_POST['offer_id'] ?? 0), $token);
        $success = 'Offer accepted. Complete payment using the college payment methods.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Your session expired. Please try again.';
}
$status = $token !== '' ? $life->publicStatus($token) : null;
$steps = ['submitted','under_review','approved','payment_required','enrolled'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:640px">
    <h1 class="h3">Application status</h1>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form class="card border-0 shadow-sm p-3 mb-3" method="get"><input class="form-control" name="token" placeholder="Tracking token" value="<?= e($token) ?>"><button class="btn btn-primary mt-2">View</button></form>
    <?php if ($token && !$status): ?><div class="alert alert-warning">No application found for that token.</div><?php endif; ?>
    <?php if ($status): ?>
        <div class="card border-0 shadow-sm p-3">
            <div class="fw-bold"><?= e($status['application_no']) ?></div>
            <p><?= e($status['full_name']) ?></p>
            <ol><?php foreach ($steps as $step): ?><li class="<?= ($status['lifecycle_status'] ?? '') === $step ? 'fw-bold' : 'text-muted' ?>"><?= e(ucwords(str_replace('_', ' ', $step))) ?></li><?php endforeach; ?></ol>
            <?php if (!empty($status['applicant_message'])): ?><p><?= e($status['applicant_message']) ?></p><?php endif; ?>
            <?php if (!empty($status['offer'])): $offer = $status['offer']; ?>
                <div class="border rounded p-3 mb-3">
                    <h2 class="h6">Admission offer <?= e((string)$offer['offer_no']) ?></h2>
                    <p class="mb-1"><?= e((string)$offer['programme_label']) ?> · <?= e((string)$offer['teacher_label']) ?></p>
                    <p class="mb-1"><?= e((string)$offer['location_label']) ?> · <?= e((string)$offer['schedule_text']) ?></p>
                    <p class="mb-1">Fees: Rs <?= number_format((float)$offer['fee_amount'], 2) ?></p>
                    <p class="small"><?= e((string)$offer['payment_instructions']) ?></p>
                    <p class="small text-muted"><?= e((string)$offer['terms_text']) ?></p>
                    <?php if (($offer['status'] ?? '') === 'issued'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                        <button class="btn btn-success">Accept offer</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <a class="btn btn-outline-primary" href="apply.php?token=<?= e($token) ?>">Update application</a>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
