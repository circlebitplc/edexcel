<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_admin();
ensure_campus_schema($pdo);

$error = '';
$success = '';
$periodYm = trim((string)($_GET['period'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
    $periodYm = date('Y-m');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'generate') {
            $students = $pdo->query("SELECT id FROM users WHERE role='student' AND deleted_at IS NULL AND is_active=1")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($students as $id) {
                campus_ensure_month_fees($pdo, (int)$id, $periodYm);
            }
            $success = 'Monthly fees generated for ' . date('F Y', strtotime($periodYm . '-01')) . '.';
        } elseif ($action === 'pay') {
            $feeId = (int)($_POST['fee_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM student_fee_ledger WHERE id = ? LIMIT 1");
            $stmt->execute([$feeId]);
            $fee = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fee) {
                throw new RuntimeException('Fee record not found.');
            }
            if (strtolower((string)$fee['status']) === 'waived') {
                throw new RuntimeException('That fee is waived. Do not mark it paid.');
            }
            $pdo->prepare("
                UPDATE student_fee_ledger
                SET amount_paid = amount_due, status = 'paid', paid_at = NOW(), marked_by = ?
                WHERE id = ?
            ")->execute([(int)$_SESSION['user_id'], $feeId]);

            $contacts = campus_student_contacts($pdo, (int)$fee['student_id']);
            $amount = number_format((float)$fee['amount_due']);
            campus_notify_phones(
                $pdo,
                (int)$fee['student_id'],
                "💳 *Payment received*\n\nStudent: *{$contacts['name']}*\n{$fee['description']}\nAmount: *Rs {$amount}*\nPaid at the college counter.\n\nThank you.",
                'FEE_RECEIPT'
            );
            $success = 'Marked as paid and WhatsApp receipt sent.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $pdo->prepare("
    SELECT f.*, COALESCE(sp.full_name, u.username) AS student_name, c.name AS class_name
    FROM student_fee_ledger f
    JOIN users u ON u.id = f.student_id
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN student_classes c ON c.id = f.class_id
    WHERE f.period_ym = ?
    ORDER BY f.status ASC, student_name
");
$rows->execute([$periodYm]);
$fees = $rows->fetchAll(PDO::FETCH_ASSOC);

$dayEnd = ['cash' => 0, 'onepay' => 0, 'wallet' => 0, 'outstanding_cash' => 0, 'total' => 0, 'date' => $periodYm . '-01'];
try {
    $day = date('Y-m-d');
    $dayEnd = (new \Edexcel\Services\FeeStatementService($pdo))->dayEnd($day);
} catch (Throwable $e) {
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-wallet2 text-primary"></i> Student fees</h1>
            <p class="text-muted mb-0">Counter payments for the monthly wallet. Teachers mark per-lesson cash on <a href="<?= e(BASE_URL) ?>campus/lesson_fees.php">Class fees</a>. Confirm bank slips on <a href="<?= e(BASE_URL) ?>campus/bank_slips.php">Bank slips</a>. Hand cash to the office on <a href="<?= e(BASE_URL) ?>campus/cash.php">Cash handover</a>.</p>
        </div>
        <form class="d-flex gap-2" method="get">
            <input type="month" name="period" class="form-control" value="<?= e($periodYm) ?>">
            <button class="btn btn-outline-primary">View</button>
        </form>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted">Cash today</div><strong>Rs <?= number_format((float)$dayEnd['cash']) ?></strong></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted">OnePay today</div><strong>Rs <?= number_format((float)$dayEnd['onepay']) ?></strong></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted">Bank slips today</div><strong>Rs <?= number_format((float)($dayEnd['bank'] ?? 0)) ?></strong></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted">Wallet marked paid</div><strong>Rs <?= number_format((float)$dayEnd['wallet']) ?></strong></div></div>
    </div>

    <form method="post" class="mb-3"><?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <button class="btn btn-primary rounded-pill">Generate this month’s class fees</button>
    </form>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Student</th><th>Class</th><th>Due</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($fees as $fee): ?>
                    <tr>
                        <td><?= e($fee['student_name']) ?></td>
                        <td><?= e($fee['class_name'] ?: $fee['description']) ?></td>
                        <td>Rs <?= number_format((float)$fee['amount_due']) ?></td>
                        <td>Rs <?= number_format((float)$fee['amount_paid']) ?></td>
                        <td>
                            <span class="badge <?= $fee['status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= e($fee['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!in_array((string)$fee['status'], ['paid', 'waived'], true)): ?>
                                <form method="post" class="d-inline"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="pay">
                                    <input type="hidden" name="fee_id" value="<?= (int)$fee['id'] ?>">
                                    <button class="btn btn-sm btn-success">Mark paid + receipt</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$fees): ?>
                    <tr><td colspan="6" class="text-muted p-4">No fees for this month yet. Generate them first.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
