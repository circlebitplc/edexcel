<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/../includes/pagination.php';
require_login();

$is_admin = is_admin();
$teacher_id = $_SESSION['teacher_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        header('Location: payments.php');
        exit();
    }

    $entry_id = (int)($_POST['entry_id'] ?? 0);

    try {
        $services = TimetableServiceFactory::services($pdo);
        $result = $services['payment']->markPaid($entry_id);

        try {
            $sent = notify_payment(
                $pdo,
                (int)$result['teacher_id'],
                (float)$result['amount']
            );
            $_SESSION[$sent ? 'success' : 'error'] =
                $sent
                    ? 'Payment marked as paid and WhatsApp notification sent to teacher.'
                    : 'Payment marked as paid, but WhatsApp notification failed.';
        } catch (Throwable $notificationError) {
            error_log('Payment notification failed: '.$notificationError->getMessage());
            $_SESSION['error'] = 'Payment marked as paid, but WhatsApp notification failed.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'payments.php';
    header('Location: ' . $redirect);
    exit();
}

$where = ["t.deleted_at IS NULL"];
$params = [];

if (!empty($_GET['teacher_filter'])) {
    $where[] = 't.teacher_id = ?';
    $params[] = (int)$_GET['teacher_filter'];
} elseif (!$is_admin && $teacher_id) {
    $where[] = 't.teacher_id = ?';
    $params[] = (int)$teacher_id;
}

if (!empty($_GET['date_from'])) {
    $where[] = 't.date >= ?';
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[] = 't.date <= ?';
    $params[] = $_GET['date_to'];
}
if (!empty($_GET['status']) && in_array($_GET['status'], ['paid','pending'], true)) {
    $where[] = 't.payment_status = ?';
    $params[] = $_GET['status'];
}

$items_per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM timetable t JOIN teachers tc ON t.teacher_id = tc.id AND tc.deleted_at IS NULL WHERE $whereSql");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$pagination = paginate($totalItems, $items_per_page, $page);

$sql = "SELECT t.*, tc.name AS teacher_name, s.name AS subject_name, c.name AS class_name, r.name AS room_name
        FROM timetable t
        JOIN teachers tc ON t.teacher_id = tc.id AND tc.deleted_at IS NULL
        JOIN subjects s ON t.subject_id = s.id AND s.deleted_at IS NULL
        JOIN student_classes c ON t.class_id = c.id AND c.deleted_at IS NULL
        JOIN rooms r ON t.room_id = r.id AND r.deleted_at IS NULL
        WHERE $whereSql
        ORDER BY t.date DESC, t.start_time ASC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$idx = 1;
foreach ($params as $param) $stmt->bindValue($idx++, $param);
$stmt->bindValue($idx++, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll();

$totalStmt = $pdo->prepare("SELECT
    COALESCE(SUM(
        student_count *
        CASE
            WHEN (
                CASE
                    WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                    THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                    ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                END
            ) <= 150 THEN 500
            WHEN (
                CASE
                    WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                    THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                    ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                END
            ) <= 210 THEN 700
            WHEN (
                CASE
                    WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                    THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                    ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                END
            ) <= 270 THEN 900
            ELSE 1100
        END
    ), 0) AS total,

    COALESCE(SUM(
        CASE WHEN payment_status = 'paid'
        THEN student_count *
            CASE
                WHEN (
                    CASE
                        WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                        THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                        ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                    END
                ) <= 150 THEN 500
                WHEN (
                    CASE
                        WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                        THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                        ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                    END
                ) <= 210 THEN 700
                WHEN (
                    CASE
                        WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                        THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                        ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                    END
                ) <= 270 THEN 900
                ELSE 1100
            END
        ELSE 0 END
    ), 0) AS paid,

    COALESCE(SUM(
        CASE WHEN payment_status = 'pending'
        THEN student_count *
            CASE
                WHEN (
                    CASE
                        WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                        THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                        ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                    END
                ) <= 150 THEN 500
                WHEN (
                    CASE
                        WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                        THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                        ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                    END
                ) <= 210 THEN 700
                WHEN (
                    CASE
                        WHEN TIME_TO_SEC(end_time) >= TIME_TO_SEC(start_time)
                        THEN (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                        ELSE (TIME_TO_SEC(end_time) + 86400 - TIME_TO_SEC(start_time)) / 60
                    END
                ) <= 270 THEN 900
                ELSE 1100
            END
        ELSE 0 END
    ), 0) AS pending,

    COUNT(*) AS lessons
    FROM timetable t WHERE $whereSql");

$totalStmt->execute($params);
$totals = $totalStmt->fetch();

$allTeachers = [];
if ($is_admin) {
    $allTeachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

include __DIR__ . '/../includes/header.php';
?>
<div class="finance-page">
    <div class="finance-heading">
        <div>
            <h1><i class="bi bi-credit-card-2-front"></i> <?= $is_admin ? 'Teacher Payments' : 'My Payments' ?></h1>
            <p>Track lesson earnings, payment status and outstanding balances.</p>
        </div>
        <div class="finance-actions">
            <a class="btn btn-outline-primary" href="payment_ledger.php"><i class="bi bi-journal-text"></i> Payment Ledger</a>
            <?php if ($is_admin): ?><a class="btn btn-primary" href="../reports/revenue.php"><i class="bi bi-graph-up-arrow"></i> Revenue Report</a><?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="finance-stat-grid">
        <article class="finance-stat"><span class="icon"><i class="bi bi-wallet2"></i></span><span class="label">Total Earnings</span><span class="value"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format((float)$totals['total']) ?></span><span class="meta"><?= number_format((int)$totals['lessons']) ?> lessons</span></article>
        <article class="finance-stat"><span class="icon"><i class="bi bi-check-circle"></i></span><span class="label">Paid</span><span class="value text-success"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format((float)$totals['paid']) ?></span><span class="meta">Marked as paid</span></article>
        <article class="finance-stat"><span class="icon"><i class="bi bi-hourglass-split"></i></span><span class="label">Pending</span><span class="value text-warning-emphasis"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format((float)$totals['pending']) ?></span><span class="meta">Still outstanding</span></article>
        <article class="finance-stat"><span class="icon"><i class="bi bi-calculator"></i></span><span class="label">Rate</span><span class="value"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($FEE_PER_STUDENT_LIVE) ?></span><span class="meta">per student / lesson</span></article>
    </div>

    <div class="finance-toolbar">
        <form method="get" class="row g-3 align-items-end">
            <?php if ($is_admin): ?>
            <div class="col-xl-3 col-md-6"><label class="form-label" for="teacher_filter">Teacher</label><select class="form-select" id="teacher_filter" name="teacher_filter"><option value="">All teachers</option><?php foreach ($allTeachers as $t): ?><option value="<?= $t['id'] ?>" <?= ((string)($_GET['teacher_filter'] ?? '') === (string)$t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="col-xl-2 col-md-6"><label class="form-label" for="date_from">From</label><input class="form-control" type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"></div>
            <div class="col-xl-2 col-md-6"><label class="form-label" for="date_to">To</label><input class="form-control" type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"></div>
            <div class="col-xl-2 col-md-6"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option><option value="paid" <?= ($_GET['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option></select></div>
            <div class="col-xl-3 col-md-12 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Apply filters</button><a class="btn btn-outline-secondary" href="payments.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a></div>
        </form>
    </div>

    <div class="finance-panel">
        <div class="finance-panel-header"><div><h2>Lesson payment records</h2><small class="text-body-secondary">Showing <?= count($entries) ?> of <?= number_format($totalItems) ?> records</small></div><span class="badge text-bg-light border"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($FEE_PER_STUDENT_LIVE) ?> / student</span></div>
        <div class="finance-table-wrap">
            <table class="table finance-table finance-table-hover">
                <thead><tr><th>Date</th><th>Teacher</th><th>Lesson</th><th>Students</th><th>Amount</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php if (!$entries): ?><tr><td colspan="7"><div class="finance-empty"><i class="bi bi-receipt"></i><strong>No payment records found</strong><div>Try changing the filters.</div></div></td></tr>
                <?php else: foreach ($entries as $e): $amount = lesson_amount(
                    (int)$e['student_count'],
                    (string)$e['start_time'],
                    (string)$e['end_time']
                ); ?>
                    <tr>
                        <td><strong><?= date('d M Y', strtotime($e['date'])) ?></strong><small class="d-block text-body-secondary"><?= date('D', strtotime($e['date'])) ?></small></td>
                        <td class="teacher-cell"><strong><?= htmlspecialchars($e['teacher_name']) ?></strong><small><?= htmlspecialchars($e['room_name']) ?></small></td>
                        <td><strong><?= htmlspecialchars($e['subject_name']) ?></strong><small class="d-block text-body-secondary"><?= htmlspecialchars($e['class_name']) ?> · <?= date('h:i A', strtotime($e['start_time'])) ?>–<?= date('h:i A', strtotime($e['end_time'])) ?></small></td>
                        <td><?= number_format((int)$e['student_count']) ?></td>
                        <td class="amount"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($amount) ?></td>
                        <td><?php if ($e['payment_status'] === 'paid'): ?><span class="finance-status paid"><i class="bi bi-check-circle-fill"></i> Paid</span><small class="d-block text-body-secondary mt-1"><?= $e['payment_date'] ? date('d M Y', strtotime($e['payment_date'])) : '' ?></small><?php else: ?><span class="finance-status pending"><i class="bi bi-clock-fill"></i> Pending</span><?php endif; ?></td>
                        <td class="text-end"><?php if ($e['payment_status'] !== 'paid' && (!(int)$e['is_locked']) && ($is_admin || (int)$e['teacher_id'] === (int)$teacher_id)): ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>"><input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>"><button class="btn btn-sm btn-success" name="mark_paid" value="1" onclick="return confirm('Mark this lesson as paid?');"><i class="bi bi-check2"></i> Mark paid</button></form><?php elseif ((int)$e['is_locked']): ?><span class="text-body-secondary small">Locked</span><?php else: ?><span class="text-body-secondary small">—</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($pagination)): ?><div class="p-3 border-top"><?= $pagination ?></div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
