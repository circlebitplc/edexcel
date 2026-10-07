<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BankTransferService;

require_staff();
ensure_recordings_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$bank = new BankTransferService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['transaction_id'] ?? 0);
        if ($action === 'verify') {
            $bank->verify($id, $userId);
            log_audit($pdo, 'bank_slip_verify', 'payment_transactions', $id, null, []);
            $success = 'Bank slip confirmed. The student can join class and watch the recording.';
        } elseif ($action === 'reject') {
            $bank->reject($id, $userId, trim((string)($_POST['reason'] ?? '')));
            log_audit($pdo, 'bank_slip_reject', 'payment_transactions', $id, null, []);
            $success = 'Slip rejected. The student can upload another copy or pay with the card.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $bank->pendingList($isAdmin ? null : $teacherId);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-bank text-primary"></i> Bank slips</h1>
            <p class="text-muted mb-0">Students and parents can pay a class fee before it starts by card (OnePay) or by uploading a transfer slip. Confirm a slip to unlock the live class and that lesson’s recording.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>campus/fees.php">Student fees</a>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>When</th>
                        <th>Amount</th>
                        <th>Slip</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e((string)$row['student_name']) ?></td>
                        <td>
                            <?= e((string)$row['subject_name']) ?>
                            <div class="small text-muted"><?= e((string)$row['class_name']) ?></div>
                        </td>
                        <td>
                            <?= e(!empty($row['date']) ? date('d M Y', strtotime((string)$row['date'])) : '') ?>
                            <?php if (!empty($row['start_time'])): ?>
                                <div class="small text-muted"><?= e(date('g:i A', strtotime((string)$row['start_time']))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>Rs <?= number_format((float)$row['amount'], 2) ?></td>
                        <td>
                            <?php if (!empty($row['slip_path'])): ?>
                                <a href="<?= e(BASE_URL . 'campus/payment_slip.php?id=' . (int)$row['id']) ?>" target="_blank" rel="noopener">Open slip</a>
                            <?php else: ?>
                                <span class="text-muted">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="d-inline"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="transaction_id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-sm btn-success">Confirm paid</button>
                            </form>
                            <form method="post" class="d-inline mt-1"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="transaction_id" value="<?= (int)$row['id'] ?>">
                                <input type="hidden" name="reason" value="The bank slip was not accepted. Please upload a clearer copy or pay with the card.">
                                <button class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="text-muted p-4">No bank slips waiting for verification.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
