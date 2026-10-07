<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_teacher();

use Edexcel\Services\ClassSessionFeeCalculator;
use Edexcel\Services\TeacherBankAccountService;
use Edexcel\Services\TeacherPayoutService;

$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($teacherId < 1) {
    http_response_code(403);
    exit('Your teacher account is not linked to a teacher profile.');
}
ClassSessionFeeCalculator::ensureSchema($pdo);
$payError = '';
$paySuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $payError = 'Invalid security token. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'request_payout') {
        try {
            TeacherPayoutService::requestOutstanding($pdo, $teacherId, $userId);
            $paySuccess = 'Payment requested. The office will transfer your teacher net amount to your saved bank account.';
        } catch (Throwable $e) {
            $payError = $e->getMessage();
        }
    }
}
$outstanding = TeacherPayoutService::outstandingRows($pdo, $teacherId);
$outstandingCents = TeacherPayoutService::outstandingCents($outstanding);
$requestedCount = 0;
foreach ($outstanding as $outstandingRow) {
    if (strtolower((string)($outstandingRow['payout_status'] ?? '')) === 'processing') {
        $requestedCount++;
    }
}
$bank = TeacherBankAccountService::findForTeacher($pdo, $teacherId);
$bankReady = TeacherBankAccountService::isCompleteRow($bank);

$range = (string)($_GET['range'] ?? 'month');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$classId = (int)($_GET['class_id'] ?? 0);
$student = trim((string)($_GET['student'] ?? ''));
$status = strtolower(trim((string)($_GET['status'] ?? '')));
if (!in_array($range, ['today', 'week', 'month', 'custom'], true)) {
    $range = 'month';
}
if ($range === 'today') {
    $from = date('Y-m-d');
    $to = $from;
} elseif ($range === 'week') {
    $from = date('Y-m-d', strtotime('monday this week'));
    $to = date('Y-m-d', strtotime('sunday this week'));
} elseif ($range === 'month') {
    $from = date('Y-m-01');
    $to = date('Y-m-t');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-t');
}

$where = ["t.fee_rule = 'online_v1'", 'COALESCE(pt.teacher_id, t.teacher_id) = ?', 't.date BETWEEN ? AND ?'];
$params = [$teacherId, $from, $to];
if ($classId > 0) {
    $where[] = 't.class_id = ?';
    $params[] = $classId;
}
if ($student !== '') {
    $where[] = 'sp.full_name LIKE ?';
    $params[] = '%' . $student . '%';
}
if (in_array($status, ['pending', 'paid', 'failed', 'cancelled', 'refunded'], true)) {
    $where[] = 'pt.status = ?';
    $params[] = $status;
}

$sql = 'SELECT pt.status, pt.payout_status, pt.amount, pt.gross_class_fee, pt.institute_online_fee,
            pt.transaction_handling_fee, pt.teacher_net_amount, pt.paid_at,
            t.date AS class_date, s.name AS subject_name, c.name AS class_name,
            COALESCE(sp.full_name, CONCAT(\'Student \', pt.student_id)) AS student_name
        FROM payment_transactions pt
        INNER JOIN timetable t ON t.id = pt.timetable_id
        LEFT JOIN student_profiles sp ON sp.user_id = pt.student_id
        LEFT JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN student_classes c ON c.id = t.class_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY t.date DESC, pt.id DESC
        LIMIT 300';
$rows = [];
$error = '';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = 'Online earnings are not available yet.';
    error_log('teacher online earnings: ' . $e->getMessage());
}

$totals = ['fees' => 0, 'institute' => 0, 'handling' => 0, 'net' => 0, 'paid_out' => 0, 'pending' => 0];
foreach ($rows as $row) {
    if (strtolower((string)$row['status']) !== 'paid') {
        continue;
    }
    $totals['fees'] += (float)($row['gross_class_fee'] ?? $row['amount'] ?? 0);
    $totals['institute'] += (float)($row['institute_online_fee'] ?? 0);
    $totals['handling'] += (float)($row['transaction_handling_fee'] ?? 0);
    $net = (float)($row['teacher_net_amount'] ?? 0);
    $totals['net'] += $net;
    $payoutStatus = strtolower((string)($row['payout_status'] ?? ''));
    if ($payoutStatus === 'paid') {
        $totals['paid_out'] += $net;
    } elseif (in_array($payoutStatus, ['pending', 'processing', ''], true)) {
        $totals['pending'] += $net;
    }
}
$classes = $pdo->prepare(
    'SELECT DISTINCT c.id, c.name FROM timetable t INNER JOIN student_classes c ON c.id = t.class_id
     WHERE t.teacher_id = ? AND t.deleted_at IS NULL ORDER BY c.name'
);
$classes->execute([$teacherId]);
$classRows = $classes->fetchAll(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . '/../includes/header.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$money = static fn($v) => 'Rs. ' . number_format((float)$v, 2);
?>
<div class="container-fluid py-4">
    <h1 class="h3">Online class teacher payments</h1>
    <p class="text-muted">This is the amount the college owes you after students pay for your online classes. It is paid to your saved bank account. A student payment is not marked as paid to you until the office records the transfer.</p>
    <?php if ($payError !== ''): ?><div class="alert alert-danger"><?= $h($payError) ?></div><?php endif; ?>
    <?php if ($paySuccess !== ''): ?><div class="alert alert-success"><?= $h($paySuccess) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-4" style="max-width: 720px;">
        <div class="card-body">
            <div class="text-muted small">Waiting to be paid to you</div>
            <div class="h3 mb-2"><?= $money($outstandingCents / 100) ?></div>
            <?php if ($bankReady): ?>
                <p class="mb-1"><?= $h((string)$bank['bank_name']) ?></p>
                <p class="mb-1"><?= $h((string)$bank['account_holder_name']) ?></p>
                <p class="mb-3 font-monospace"><?= $h(TeacherBankAccountService::mask((string)$bank['account_number'])) ?></p>
            <?php else: ?>
                <div class="alert alert-warning">Add your bank details before you can request this payment.</div>
                <a class="btn btn-primary" href="<?= $h(rtrim((string)BASE_URL, '/') . '/teachers/bank_details.php') ?>">Add Bank Details</a>
            <?php endif; ?>
            <?php if ($bankReady && $outstandingCents > 0 && $requestedCount < count($outstanding)): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request_payout">
                    <button class="btn btn-primary" type="submit">Request payment</button>
                </form>
            <?php elseif ($bankReady && $requestedCount > 0): ?>
                <p class="mb-0 text-success">Payment requested. The office will transfer this to your bank account.</p>
            <?php elseif ($bankReady): ?>
                <p class="mb-0 text-muted">Nothing is waiting. Teacher payments appear here after a student pays for an online class.</p>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-2">
            <label class="form-label">Period</label>
            <select class="form-select" name="range">
                <?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'custom' => 'Date range'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $range === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">From</label>
            <input class="form-control" type="date" name="from" value="<?= $h($from) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">To</label>
            <input class="form-control" type="date" name="to" value="<?= $h($to) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id">
                <option value="0">All</option>
                <?php foreach ($classRows as $classRow): ?>
                    <option value="<?= (int)$classRow['id'] ?>" <?= $classId === (int)$classRow['id'] ? 'selected' : '' ?>><?= $h($classRow['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Student</label>
            <input class="form-control" name="student" value="<?= $h($student) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Payment</label>
            <select class="form-select" name="status">
                <option value="">All</option>
                <?php foreach (['pending', 'paid', 'failed', 'cancelled', 'refunded'] as $item): ?>
                    <option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= strtoupper($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary" type="submit">Filter</button></div>
    </form>

    <div class="row g-2 mb-3">
        <?php foreach ([
            'Total online class fees' => $totals['fees'],
            'Institute fees' => $totals['institute'],
            'Transaction / handling fees' => $totals['handling'],
            'Teacher net earnings' => $totals['net'],
            'Paid to teacher' => $totals['paid_out'],
            'Pending teacher payments' => $totals['pending'],
        ] as $label => $amount): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="border rounded p-2 h-100">
                    <div class="small text-muted"><?= $h($label) ?></div>
                    <div class="fw-semibold"><?= $money($amount) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Date</th><th>Class</th><th>Student</th><th>Class fee</th><th>Institute</th><th>Handling</th><th>Your net</th><th>Payment</th><th>Payout</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-muted">No online payments in this period.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $h(date('d/m/Y', strtotime((string)$row['class_date']))) ?></td>
                    <td><?= $h(trim((string)$row['subject_name'] . ' ' . (string)$row['class_name'])) ?></td>
                    <td><?= $h($row['student_name']) ?></td>
                    <td><?= $money($row['gross_class_fee'] ?? $row['amount']) ?></td>
                    <td><?= $money($row['institute_online_fee'] ?? 0) ?></td>
                    <td><?= $money($row['transaction_handling_fee'] ?? 0) ?></td>
                    <td><?= $money($row['teacher_net_amount'] ?? 0) ?></td>
                    <td><?= $h(strtoupper((string)$row['status'])) ?></td>
                    <td><?= $h(strtoupper((string)($row['payout_status'] ?: '—'))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
