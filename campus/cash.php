<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CashHandoverService;

require_staff();
ensure_ops_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$svc = new CashHandoverService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'hand') {
            $date = (string)($_POST['period_date'] ?? date('Y-m-d'));
            $amount = (float)($_POST['amount'] ?? 0);
            $notes = trim((string)($_POST['notes'] ?? ''));
            $rowTeacher = (int)($_POST['row_teacher_id'] ?? $teacherId);
            $rowCollected = (int)($_POST['row_collected_by'] ?? $userId);
            if (!$isAdmin) {
                $ownsTeacher = $teacherId > 0 && $rowTeacher === $teacherId;
                $ownsCollect = $rowCollected === $userId;
                if (!$ownsTeacher && !$ownsCollect) {
                    throw new RuntimeException('You can only hand over your own cash.');
                }
            }
            $svc->handOver($userId, $rowTeacher, $date, $amount, $notes, false, $rowCollected);
            if (function_exists('log_audit')) {
                log_audit($pdo, 'cash_handover', 'cash_handovers', null, null, ['date' => $date, 'amount' => $amount]);
            }
            $success = 'Cash recorded as handed to the office.';
        } elseif ($action === 'receive' && $isAdmin) {
            $svc->receive((int)($_POST['handover_id'] ?? 0), $userId);
            $success = 'Office received this cash bag.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$open = $svc->outstandingForUser($userId, $teacherId, $isAdmin);
$recent = $isAdmin ? $svc->recent(40) : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-1"><i class="bi bi-cash-stack text-success"></i> Cash handover</h1>
    <p class="text-muted">Class fees cash sits with the teacher until it is handed to the office. This does not mark teacher payroll on Payments.</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <h2 class="h5">Cash not yet handed</h2>
            <?php if (!$open): ?>
                <p class="text-muted mb-0">No unmatched cash.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Date</th><th>Teacher</th><th>Count</th><th>Amount</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($open as $row): ?>
                            <tr>
                                <td><?= e($row['period_date']) ?></td>
                                <td><?= e($row['teacher_name'] ?: '—') ?></td>
                                <td><?= (int)$row['n'] ?></td>
                                <td>Rs <?= number_format((float)$row['amount'], 2) ?></td>
                                <td>
                                    <form method="post" class="d-flex gap-2 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hand">
                                        <input type="hidden" name="period_date" value="<?= e($row['period_date']) ?>">
                                        <input type="hidden" name="row_teacher_id" value="<?= (int)$row['teacher_id'] ?>">
                                        <input type="hidden" name="row_collected_by" value="<?= (int)$row['collected_by'] ?>">
                                        <input class="form-control" style="width:8rem" name="amount" type="number" step="0.01" value="<?= e((string)$row['amount']) ?>">
                                        <input class="form-control" style="width:12rem" name="notes" placeholder="Bag / note">
                                        <button class="btn btn-sm btn-success">Hand to office</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <h2 class="h5">Recent handovers</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Date</th><th>Teacher</th><th>Handed</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?= e($row['period_date']) ?></td>
                                <td><?= e($row['teacher_name'] ?: $row['collected_name']) ?></td>
                                <td>Rs <?= number_format((float)$row['amount_handed'], 2) ?></td>
                                <td><?= e($row['status']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'handed'): ?>
                                        <form method="post" class="d-inline"><?= csrf_field() ?>
                                            <input type="hidden" name="action" value="receive">
                                            <input type="hidden" name="handover_id" value="<?= (int)$row['id'] ?>">
                                            <button class="btn btn-sm btn-primary">Mark received</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recent): ?>
                            <tr><td colspan="5" class="text-muted">None yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
