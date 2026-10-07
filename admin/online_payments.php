<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

use Edexcel\Services\ClassSessionFeeCalculator;
use Edexcel\Services\TeacherPaymentSmsService;
use Edexcel\Services\TeacherPayoutService;

ClassSessionFeeCalculator::ensureSchema($pdo);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$smsWarning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } elseif (($_POST['action'] ?? '') === 'pay_teacher') {
        try {
            TeacherPayoutService::payOutstanding(
                $pdo,
                (int)($_POST['teacher_id'] ?? 0),
                $userId,
                (string)($_POST['payout_reference'] ?? ''),
                (string)($_POST['payout_date'] ?? ''),
                (string)($_POST['payment_method'] ?? 'bank'),
                (string)($_POST['notes'] ?? '')
            );
            $success = 'Teacher payment recorded. The amount was calculated on the server from paid online class fees.';
            [$success, $smsWarning] = online_payment_sms_flash($success);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        try {
            $ids = array_map('intval', (array)($_POST['payment_ids'] ?? []));
            TeacherPayoutService::record(
                $pdo,
                $ids,
                (string)($_POST['payout_status'] ?? ''),
                $userId,
                (string)($_POST['payout_reference'] ?? ''),
                (string)($_POST['payout_date'] ?? ''),
                (string)($_POST['payment_method'] ?? 'bank'),
                (string)($_POST['notes'] ?? '')
            );
            $success = 'Teacher payout recorded. The student payment status was not changed.';
            [$success, $smsWarning] = online_payment_sms_flash($success);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

function online_payment_sms_flash(string $success): array
{
    $warning = '';
    foreach (TeacherPaymentSmsService::consumeLastNotices() as $notice) {
        $status = (string)($notice['status'] ?? '');
        $text = (string)($notice['notice'] ?? '');
        if ($text === '') {
            continue;
        }
        if (in_array($status, ['sent', 'resent', 'already_sent'], true)) {
            $success .= ' ' . $text;
        } else {
            $warning = $text;
        }
    }
    return [$success, $warning];
}

TeacherPayoutService::attachOpenOnlinePayments($pdo);
$dueTeachers = [];
try {
    $dueStmt = $pdo->query(
        "SELECT te.id, te.name,
                COALESCE(SUM(COALESCE(pt.teacher_net_amount, t.teacher_net_amount)), 0) AS due_amount,
                SUM(CASE WHEN pt.payout_status = 'processing' THEN 1 ELSE 0 END) AS requested_count,
                b.bank_name, b.account_holder_name, b.account_number, b.branch
         FROM payment_transactions pt
         INNER JOIN timetable t ON t.id = pt.timetable_id
         INNER JOIN teachers te ON te.id = COALESCE(pt.teacher_id, t.teacher_id) AND te.deleted_at IS NULL
         LEFT JOIN teacher_bank_accounts b ON b.teacher_id = te.id
         WHERE t.fee_rule = 'online_v1'
           AND pt.status = 'paid'
           AND (pt.payout_status IS NULL OR pt.payout_status IN ('', 'pending', 'processing'))
         GROUP BY te.id, te.name, b.bank_name, b.account_holder_name, b.account_number, b.branch
         HAVING due_amount > 0
         ORDER BY te.name"
    );
    $dueTeachers = $dueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('teacher payment due list: ' . $e->getMessage());
}

$teacherFilter = (int)($_GET['teacher_id'] ?? 0);
$student = trim((string)($_GET['student'] ?? ''));
$class = trim((string)($_GET['class'] ?? ''));
$status = strtolower(trim((string)($_GET['status'] ?? '')));
$payout = strtolower(trim((string)($_GET['payout'] ?? '')));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$allowedStatus = ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'initiated', 'expired'];
$allowedPayout = ['pending', 'processing', 'paid', 'failed'];

$where = ["t.fee_rule = 'online_v1'"];
$params = [];
if ($teacherFilter > 0) {
    $where[] = 'COALESCE(pt.teacher_id, t.teacher_id) = ?';
    $params[] = $teacherFilter;
}
if ($student !== '') {
    $where[] = '(sp.full_name LIKE ? OR CAST(pt.student_id AS CHAR) = ?)';
    $params[] = '%' . $student . '%';
    $params[] = $student;
}
if ($class !== '') {
    $where[] = '(s.name LIKE ? OR c.name LIKE ?)';
    $params[] = '%' . $class . '%';
    $params[] = '%' . $class . '%';
}
if (in_array($status, $allowedStatus, true)) {
    $where[] = 'pt.status = ?';
    $params[] = $status;
}
if (in_array($payout, $allowedPayout, true)) {
    $where[] = 'pt.payout_status = ?';
    $params[] = $payout;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 't.date >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 't.date <= ?';
    $params[] = $to;
}

$sql = 'SELECT pt.id, pt.student_id, pt.status, pt.gateway, pt.gateway_transaction_id, pt.gateway_reference,
            pt.amount, pt.gross_class_fee, pt.institute_online_fee, pt.transaction_handling_fee, pt.teacher_net_amount,
            pt.payout_status, pt.payout_id, pt.paid_at, pt.created_at,
            t.date AS class_date, t.delivery_mode,
            COALESCE(sp.full_name, CONCAT(\'Student \', pt.student_id)) AS student_name,
            te.name AS teacher_name,
            s.name AS subject_name,
            c.name AS class_name
        FROM payment_transactions pt
        INNER JOIN timetable t ON t.id = pt.timetable_id
        LEFT JOIN student_profiles sp ON sp.user_id = pt.student_id
        LEFT JOIN teachers te ON te.id = COALESCE(pt.teacher_id, t.teacher_id)
        LEFT JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN student_classes c ON c.id = t.class_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY pt.created_at DESC
        LIMIT 200';
$rows = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = $error !== '' ? $error : 'Online payment ledger is not available yet.';
    error_log('online payments ledger: ' . $e->getMessage());
}
$teachers = $pdo->query('SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$payoutSmsById = [];
if ($rows) {
    $payoutIds = [];
    foreach ($rows as $row) {
        if (strtolower((string)($row['payout_status'] ?? '')) === 'paid' && (int)($row['payout_id'] ?? 0) > 0) {
            $payoutIds[] = (int)$row['payout_id'];
        }
    }
    $payoutSmsById = TeacherPaymentSmsService::latestFor($pdo, TeacherPaymentSmsService::KIND_PAYOUT, $payoutIds);
}

include __DIR__ . '/../includes/header.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$money = static fn($v) => 'Rs. ' . number_format((float)$v, 2);
?>
<div class="container-fluid py-4">
    <h1 class="h3">Online class teacher payments</h1>
    <p class="text-muted">Pay a teacher the net amount from online classes after the student has paid. The amount is calculated on the server. The student payment stays paid.</p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Pay a teacher</h2>
            <?php if (!$dueTeachers): ?>
                <p class="text-muted mb-0">No teacher has an online class amount waiting to be paid.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Bank</th>
                                <th>Account</th>
                                <th>Amount due</th>
                                <th>Request</th>
                                <th>Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dueTeachers as $due): ?>
                            <tr>
                                <td><?= $h($due['name']) ?></td>
                                <td><?= $h((string)($due['bank_name'] ?? '')) ?><div class="small text-muted"><?= $h((string)($due['account_holder_name'] ?? '')) ?></div></td>
                                <td class="font-monospace"><?= $h(\Edexcel\Services\TeacherBankAccountService::mask((string)($due['account_number'] ?? ''))) ?></td>
                                <td><?= $money($due['due_amount']) ?></td>
                                <td><?= (int)$due['requested_count'] > 0 ? 'Requested' : 'Waiting' ?></td>
                                <td>
                                    <form method="post" class="row g-1" style="min-width: 280px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="pay_teacher">
                                        <input type="hidden" name="teacher_id" value="<?= (int)$due['id'] ?>">
                                        <div class="col-12 col-md-5"><input class="form-control form-control-sm" name="payout_reference" placeholder="Reference" required maxlength="80"></div>
                                        <div class="col-6 col-md-4"><input class="form-control form-control-sm" type="date" name="payout_date" value="<?= $h(date('Y-m-d')) ?>" required></div>
                                        <input type="hidden" name="payment_method" value="bank">
                                        <div class="col-6 col-md-3"><button class="btn btn-sm btn-primary w-100" type="submit">Pay</button></div>
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
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
    <?php if ($smsWarning !== ''): ?><div class="alert alert-warning"><?= $h($smsWarning) ?></div><?php endif; ?>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-2">
            <label class="form-label">From</label>
            <input type="date" class="form-control" name="from" value="<?= $h($from) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">To</label>
            <input type="date" class="form-control" name="to" value="<?= $h($to) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Teacher</label>
            <select class="form-select" name="teacher_id">
                <option value="0">All</option>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= (int)$teacher['id'] ?>" <?= $teacherFilter === (int)$teacher['id'] ? 'selected' : '' ?>><?= $h($teacher['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Student</label>
            <input class="form-control" name="student" value="<?= $h($student) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Class</label>
            <input class="form-control" name="class" value="<?= $h($class) ?>">
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Payment</label>
            <select class="form-select" name="status">
                <option value="">All</option>
                <?php foreach (['pending', 'paid', 'failed', 'cancelled', 'refunded'] as $item): ?>
                    <option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= strtoupper($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Payout</label>
            <select class="form-select" name="payout">
                <option value="">All</option>
                <?php foreach (['pending', 'processing', 'paid', 'failed'] as $item): ?>
                    <option value="<?= $item ?>" <?= $payout === $item ? 'selected' : '' ?>><?= strtoupper($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary" type="submit">Filter</button></div>
    </form>

    <form method="post">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th></th>
                        <th>Date</th>
                        <th>Class</th>
                        <th>Student</th>
                        <th>Teacher</th>
                        <th>Class fee</th>
                        <th>Institute</th>
                        <th>Handling</th>
                        <th>Teacher net</th>
                        <th>Payment</th>
                        <th>Gateway</th>
                        <th>Payout</th>
                        <th>Teacher SMS</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="13" class="text-muted">No online class payments match these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php $canSettle = strtolower((string)$row['status']) === 'paid' && strtolower((string)($row['payout_status'] ?? '')) !== 'paid'; ?>
                    <tr>
                        <td><?php if ($canSettle): ?><input type="checkbox" name="payment_ids[]" value="<?= (int)$row['id'] ?>"><?php endif; ?></td>
                        <td><?= $h($row['class_date'] ? date('d/m/Y', strtotime((string)$row['class_date'])) : '') ?></td>
                        <td><?= $h(trim((string)$row['subject_name'] . ' ' . (string)$row['class_name'])) ?></td>
                        <td><?= $h($row['student_name']) ?></td>
                        <td><?= $h($row['teacher_name']) ?></td>
                        <td><?= $money($row['gross_class_fee'] ?? $row['amount']) ?></td>
                        <td><?= $money($row['institute_online_fee'] ?? 0) ?></td>
                        <td><?= $money($row['transaction_handling_fee'] ?? 0) ?></td>
                        <td><?= $money($row['teacher_net_amount'] ?? 0) ?></td>
                        <td><?= $h(strtoupper((string)$row['status'])) ?></td>
                        <td class="small"><?= $h($row['gateway_transaction_id'] ?: $row['gateway_reference']) ?></td>
                        <td><?= $h(strtoupper((string)($row['payout_status'] ?: '—'))) ?></td>
                        <td>
                            <?php if (strtolower((string)($row['payout_status'] ?? '')) === 'paid' && (int)($row['payout_id'] ?? 0) > 0): ?>
                                <?php
                                $smsRow = $payoutSmsById[(int)$row['payout_id']] ?? null;
                                $smsStatus = (string)($smsRow['status'] ?? '');
                                $smsWhenRaw = (string)($smsRow['sent_at'] ?? $smsRow['created_at'] ?? '');
                                $smsWhen = $smsWhenRaw !== '' && strtotime($smsWhenRaw) !== false ? date('d/m/Y H:i', strtotime($smsWhenRaw)) : '';
                                ?>
                                <div>Payment: PAID</div>
                                <div>Teacher SMS: <?= $h(TeacherPaymentSmsService::statusLabel($smsStatus)) ?></div>
                                <?php if ($smsWhen !== ''): ?>
                                    <div class="small text-muted"><?= $smsStatus === 'failed' ? 'SMS attempted' : 'SMS sent' ?>: <?= $h($smsWhen) ?></div>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-1 js-resend-payment-sms" data-kind="teacher_payout" data-id="<?= (int)$row['payout_id'] ?>">Resend SMS</button>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="row g-2 align-items-end" style="max-width: 980px;">
            <div class="col-md-3">
                <label class="form-label">Payout status</label>
                <select class="form-select" name="payout_status">
                    <option value="processing">Processing</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Payout reference</label>
                <input class="form-control" name="payout_reference" maxlength="80">
            </div>
            <div class="col-md-2">
                <label class="form-label">Payout date</label>
                <input class="form-control" type="date" name="payout_date" value="<?= $h(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Method</label>
                <input class="form-control" name="payment_method" value="bank" maxlength="40">
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input class="form-control" name="notes" maxlength="500">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary" type="submit">Record teacher payout</button>
            </div>
        </div>
        <p class="form-text">Select paid payments for one teacher. The payout stores the bank-account record id, not a fresh copy of the account number. Marking a payout as paid sends one SMS to the teacher after the payout is saved.</p>
    </form>
</div>
<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-resend-payment-sms');
    if (!button) {
        return;
    }
    event.preventDefault();
    var csrf = document.querySelector('input[name="csrf_token"]');
    var token = csrf ? csrf.value : '';
    if (!token) {
        alert('Security token not found. Please refresh the page.');
        return;
    }
    var body = new URLSearchParams();
    body.set('csrf_token', token);
    body.set('kind', button.getAttribute('data-kind') || 'teacher_payout');
    body.set('id', button.getAttribute('data-id') || '0');
    body.set('action', 'preview');
    button.disabled = true;
    fetch('../ajax/resend_payment_sms.php', {
        method: 'POST',
        body: body,
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.error || 'Payment SMS could not be prepared.');
        }
        if (!data.can_send) {
            alert(data.warning || 'Payment SMS could not be sent.');
            return;
        }
        var preview = 'Send this SMS to the teacher?\n\n' + (data.message || '') + '\n\nIt uses the mobile number saved on the teacher profile.';
        var proceed = function (confirmed) {
            if (!confirmed) {
                return;
            }
            body.set('action', 'send');
            fetch('../ajax/resend_payment_sms.php', {
                method: 'POST',
                body: body,
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            })
            .then(function (response) { return response.json(); })
            .then(function (sent) {
                if (!sent.success) {
                    throw new Error(sent.error || 'Payment SMS could not be sent.');
                }
                var ok = sent.sms_status === 'sent' || sent.sms_status === 'resent';
                alert(ok
                    ? 'SMS notification sent to the teacher. The payment stays PAID.'
                    : ((sent.sms_notice || 'Payment SMS could not be sent.') + ' The payment stays PAID.'));
                if (ok) {
                    window.location.reload();
                }
            })
            .catch(function (err) {
                alert(err.message || 'Payment SMS could not be sent.');
            });
        };
        if (typeof customConfirm === 'function') {
            customConfirm('Resend SMS', preview, proceed, {okLabel: 'Send SMS'});
        } else {
            proceed(window.confirm(preview));
        }
    })
    .catch(function (err) {
        alert(err.message || 'Payment SMS could not be prepared.');
    })
    .finally(function () {
        button.disabled = false;
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
