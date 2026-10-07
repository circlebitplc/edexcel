<?php
/**
 * timetable/payments.php
 *
 * Teacher payment view.
 *
 * Rules:
 *  - Teachers can VIEW payment records only.
 *  - Teachers CANNOT mark payments as paid.
 *  - Admins may mark a payment as paid.
 *  - Future classes are never shown on this page.
 *  - Payment rates are based on the actual lesson duration.
 *  - The payment amount is calculated from student count and actual lesson duration.
 *
 * This also avoids the previous "Array to string conversion" problem by
 * explicitly converting/validating values before passing them to output
 * functions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../vendor/autoload.php';
\Edexcel\Services\ClassSessionFeeCalculator::ensureSchema($pdo);

require_staff();

$is_admin = is_admin();
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

/* ------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------ */

function payments_h($value): string
{
    if (is_array($value) || is_object($value)) {
        return htmlspecialchars(
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ENT_QUOTES,
            'UTF-8'
        );
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function payments_date($value, string $format = 'd M Y'): string
{
    $value = is_scalar($value) ? (string)$value : '';

    if ($value === '' || strtotime($value) === false) {
        return '';
    }

    return date($format, strtotime($value));
}

/* ------------------------------------------------------------
 * ADMIN ONLY: mark payment as paid
 *
 * Teachers are deliberately rejected here even if somebody tries
 * to submit the old form manually.
 * ------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {

    if (!$is_admin) {
        http_response_code(403);
        $_SESSION['error'] = 'Teachers cannot mark payments as paid. Please contact the institute administrator.';
        header('Location: payments.php');
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        header('Location: payments.php');
        exit;
    }

    $entry_id = (int)($_POST['entry_id'] ?? 0);

    if ($entry_id <= 0) {
        $_SESSION['error'] = 'Invalid lesson ID.';
        header('Location: payments.php');
        exit;
    }

    try {
        /*
         * Future lessons are deliberately blocked here as well.
         * This matches the page rule that only today/past classes are
         * payment records.
         */
        $stmt = $pdo->prepare("
            SELECT
                id,
                teacher_id,
                student_count,
                payment_status,
                is_locked,
                deleted_at,
                date,
                start_time,
                end_time,
                class_fee_per_student,
                delivery_mode,
                fee_rule,
                institute_online_fee,
                transaction_handling_fee,
                teacher_net_amount
            FROM timetable
            WHERE id = ?
              AND deleted_at IS NULL
              AND date <= CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$entry_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            throw new RuntimeException('Lesson not found, deleted, or scheduled for a future date.');
        }

        if ((int)$entry['is_locked'] === 1) {
            throw new RuntimeException('This lesson is locked and cannot be marked as paid.');
        }

        if (($entry['payment_status'] ?? '') === 'paid') {
            throw new RuntimeException('This lesson is already marked as paid.');
        }

        $student_count = max(0, (int)($entry['student_count'] ?? 0));
        $amount = \Edexcel\Services\TeacherPaymentSmsService::payableCents($entry) / 100;
        $payment_date = date('Y-m-d');

        $update = $pdo->prepare("
            UPDATE timetable
            SET
                payment_status = 'paid',
                payment_date = ?
            WHERE id = ?
              AND deleted_at IS NULL
              AND date <= CURDATE()
              AND payment_status <> 'paid'
        ");

        $update->execute([$payment_date, $entry_id]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Payment could not be updated. The record may have changed.');
        }

        if (function_exists('log_audit')) {
            try {
                log_audit(
                    $pdo,
                    'payment_marked',
                    'timetable',
                    $entry_id,
                    [
                        'payment_status' => $entry['payment_status'] ?? null,
                    ],
                    [
                        'payment_status' => 'paid',
                        'payment_date' => $payment_date,
                        'amount' => $amount,
                        'student_count' => $student_count,
                    ]
                );
            } catch (Throwable $auditError) {
                error_log(
                    'Payment audit failed for timetable ' .
                    $entry_id . ': ' .
                    $auditError->getMessage()
                );
            }
        }

        /*
         * SMS is sent only after the paid status is stored.
         * A gateway failure must not undo the payment.
         */
        try {
            $sms = \Edexcel\Services\TeacherPaymentSmsService::notifyTimetablePaid(
                $pdo,
                $entry_id,
                (int)($_SESSION['user_id'] ?? 0),
                false
            );
        } catch (Throwable $smsError) {
            error_log('Payment SMS failed: ' . $smsError->getMessage());
            $sms = [
                'status' => 'failed',
                'sms_notice' => 'Payment SMS could not be sent.',
            ];
        }
        $smsStatus = (string)($sms['status'] ?? 'failed');
        $smsNotice = (string)($sms['sms_notice'] ?? 'Payment SMS could not be sent.');
        $_SESSION['success'] = 'Payment marked as PAID.';
        if (in_array($smsStatus, ['sent', 'resent', 'already_sent'], true)) {
            $_SESSION['success'] .= ' ' . $smsNotice;
        } else {
            $_SESSION['warning'] = $smsNotice;
        }

    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'payments.php';

    /*
     * Prevent an external Referer from becoming a redirect target.
     * Keep the redirect local to this page.
     */
    $redirectPath = parse_url($redirect, PHP_URL_PATH);

    if (
        !is_string($redirectPath) ||
        basename($redirectPath) !== 'payments.php'
    ) {
        $redirect = 'payments.php';
    }

    header('Location: ' . $redirect);
    exit;
}

/* ------------------------------------------------------------
 * FILTERS
 *
 * Hard rule: t.date <= CURDATE() (no future lessons).
 * Default range: current month through today.
 * ------------------------------------------------------------ */

$today = date('Y-m-d');
$range = strtolower(trim((string)($_GET['range'] ?? '')));
$hasCustomDates = (isset($_GET['date_from']) && $_GET['date_from'] !== '')
    || (isset($_GET['date_to']) && $_GET['date_to'] !== '');

if ($hasCustomDates) {
    $range = 'custom';
} elseif ($range === '') {
    $range = 'month';
}

$date_from = '';
$date_to = $today;

if ($range === 'month') {
    $date_from = date('Y-m-01');
    $date_to = $today;
} elseif ($range === 'last') {
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to = date('Y-m-t', strtotime('last month'));
    if ($date_to > $today) {
        $date_to = $today;
    }
} elseif ($range === 'all') {
    $date_from = '';
    $date_to = $today;
} else {
    $date_from = (string)($_GET['date_from'] ?? '');
    $date_to = (string)($_GET['date_to'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_to = $today;
    }
    if ($date_to > $today) {
        $date_to = $today;
    }
    if ($date_from > $date_to) {
        [$date_from, $date_to] = [$date_to, $date_from];
    }
}

$where = [
    't.deleted_at IS NULL',
    't.date <= CURDATE()',
];

$params = [];

if (!empty($_GET['teacher_filter']) && $is_admin) {
    $where[] = 't.teacher_id = ?';
    $params[] = (int)$_GET['teacher_filter'];
} elseif (!$is_admin) {

    if ($teacher_id <= 0) {
        http_response_code(403);
        exit('Teacher account is not linked to a teacher record.');
    }

    $where[] = 't.teacher_id = ?';
    $params[] = $teacher_id;
}

if ($date_from !== '') {
    $where[] = 't.date >= ?';
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = 't.date <= ?';
    $params[] = $date_to;
}

$status = (string)($_GET['status'] ?? '');

if (in_array($status, ['paid', 'pending'], true)) {
    $where[] = 't.payment_status = ?';
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

/* ------------------------------------------------------------
 * PAGINATION
 * ------------------------------------------------------------ */

$items_per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM timetable t
    JOIN teachers tc
        ON t.teacher_id = tc.id
       AND tc.deleted_at IS NULL
    JOIN subjects s
        ON t.subject_id = s.id
       AND s.deleted_at IS NULL
    JOIN student_classes c
        ON t.class_id = c.id
       AND c.deleted_at IS NULL
    JOIN rooms r
        ON t.room_id = r.id
       AND r.deleted_at IS NULL
    WHERE $whereSql
");

$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();

$pagination = paginate(
    $totalItems,
    $items_per_page,
    $page
);

/* ------------------------------------------------------------
 * PAYMENT RECORDS
 * ------------------------------------------------------------ */

$sql = "
    SELECT
        t.id,
        t.teacher_id,
        t.student_count,
        t.date,
        t.start_time,
        t.end_time,
        t.payment_status,
        t.payment_date,
        t.is_locked,
        t.class_fee_per_student,
        t.delivery_mode,
        t.fee_rule,
        t.institute_online_fee,
        t.transaction_handling_fee,
        t.teacher_net_amount,

        tc.name AS teacher_name,
        s.name AS subject_name,
        c.name AS class_name,
        r.name AS room_name

    FROM timetable t

    JOIN teachers tc
        ON t.teacher_id = tc.id
       AND tc.deleted_at IS NULL

    JOIN subjects s
        ON t.subject_id = s.id
       AND s.deleted_at IS NULL

    JOIN student_classes c
        ON t.class_id = c.id
       AND c.deleted_at IS NULL

    JOIN rooms r
        ON t.room_id = r.id
       AND r.deleted_at IS NULL

    WHERE $whereSql

    ORDER BY
        t.date DESC,
        t.start_time ASC

    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);

$idx = 1;

foreach ($params as $param) {
    $stmt->bindValue($idx++, $param);
}

$stmt->bindValue(
    $idx++,
    $items_per_page,
    PDO::PARAM_INT
);

$stmt->bindValue(
    $idx++,
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paymentSmsById = [];
if ($is_admin && $entries) {
    $paymentSmsById = \Edexcel\Services\TeacherPaymentSmsService::latestFor(
        $pdo,
        \Edexcel\Services\TeacherPaymentSmsService::KIND_TIMETABLE,
        array_map(static fn (array $row): int => (int)$row['id'], $entries)
    );
}

/* ------------------------------------------------------------
 * SUMMARY TOTALS
 * ------------------------------------------------------------ */

$amountExpr = \Edexcel\Services\ClassSessionFeeCalculator::instituteAmountSql('t');

$totalStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM({$amountExpr}), 0) AS total,
        COALESCE(SUM(CASE WHEN t.payment_status = 'paid' THEN {$amountExpr} ELSE 0 END), 0) AS paid,
        COALESCE(SUM(CASE WHEN t.payment_status = 'pending' THEN {$amountExpr} ELSE 0 END), 0) AS pending,
        COUNT(*) AS lessons
    FROM timetable t
    WHERE $whereSql
");

$totalStmt->execute($params);

$totals = $totalStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total' => 0,
    'paid' => 0,
    'pending' => 0,
    'lessons' => 0,
];

/* ------------------------------------------------------------
 * ADMIN TEACHER FILTER
 * ------------------------------------------------------------ */

$allTeachers = [];

if ($is_admin) {
    $allTeachers = $pdo
        ->query("
            SELECT id, name
            FROM teachers
            WHERE deleted_at IS NULL
            ORDER BY name
        ")
        ->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------
 * FLASH MESSAGES
 * ------------------------------------------------------------ */

$success = $_SESSION['success'] ?? null;
$warning = $_SESSION['warning'] ?? null;
$error = $_SESSION['error'] ?? null;

unset(
    $_SESSION['success'],
    $_SESSION['warning'],
    $_SESSION['error']
);

/* ------------------------------------------------------------
 * PAGE
 * ------------------------------------------------------------ */

include __DIR__ . '/../includes/header.php';
?>

<div class="finance-page">

    <div class="finance-heading">

        <div>
            <h1>
                <i class="bi bi-credit-card-2-front"></i>
                <?= $is_admin ? 'Teacher Payments' : 'My Payments' ?>
            </h1>

            <p>
                <?= $is_admin ? 'Completed and today\'s lessons only. Future classes are never included.' : 'Your completed and today\'s lessons. Future classes are hidden.' ?>
                Row amounts are the teacher payment. The totals below are the institute fee.
                <?php if ($range === 'month'): ?>
                    Showing <strong>this month</strong>.
                <?php elseif ($range === 'last'): ?>
                    Showing <strong>last month</strong>.
                <?php elseif ($range === 'all'): ?>
                    Showing <strong>all past lessons</strong>.
                <?php endif; ?>
            </p>
        </div>

        <div class="finance-actions">

            <a
                class="btn btn-outline-primary"
                href="payment_ledger.php"
            >
                <i class="bi bi-journal-text"></i>
                Payment Ledger
            </a>

            <?php if ($is_admin): ?>

                <a
                    class="btn btn-primary"
                    href="../reports/revenue.php"
                >
                    <i class="bi bi-graph-up-arrow"></i>
                    Revenue Report
                </a>

            <?php endif; ?>

        </div>

    </div>

    <?php if ($success): ?>

        <div class="alert alert-success">
            <?= payments_h($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($warning): ?>

        <div class="alert alert-warning">
            <?= payments_h($warning) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= payments_h($error) ?>
        </div>

    <?php endif; ?>


    <!-- SUMMARY -->

    <div class="finance-stat-grid">

        <article class="finance-stat">

            <span class="icon">
                <i class="bi bi-wallet2"></i>
            </span>

            <span class="label">
                Institute total
            </span>

            <span class="value">
                <?= payments_h($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format((float)$totals['total']) ?>
            </span>

            <span class="meta">
                <?= number_format((int)$totals['lessons']) ?>
                lessons
            </span>

        </article>


        <article class="finance-stat">

            <span class="icon">
                <i class="bi bi-check-circle"></i>
            </span>

            <span class="label">
                Institute paid
            </span>

            <span class="value text-success">
                <?= payments_h($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format((float)$totals['paid']) ?>
            </span>

            <span class="meta">
                Payments recorded
            </span>

        </article>


        <article class="finance-stat">

            <span class="icon">
                <i class="bi bi-hourglass-split"></i>
            </span>

            <span class="label">
                Institute pending
            </span>

            <span class="value text-warning-emphasis">
                <?= payments_h($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format((float)$totals['pending']) ?>
            </span>

            <span class="meta">
                Still outstanding
            </span>

        </article>


        <article class="finance-stat">

            <span class="icon">
                <i class="bi bi-calculator"></i>
            </span>

            <span class="label">
                Rate
            </span>

            <span class="value">
                Duration based
            </span>

            <span class="meta">
                Rs 500–Rs 1100 per student
            </span>

        </article>

    </div>


    <!-- FILTERS -->

    <div class="finance-toolbar">

        <form
            method="get"
            class="row g-3 align-items-end"
        >

            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <?php
                    $payExtra = '';
                    if ($is_admin && !empty($_GET['teacher_filter'])) {
                        $payExtra .= '&teacher_filter=' . (int)$_GET['teacher_filter'];
                    }
                    if ($status !== '') {
                        $payExtra .= '&status=' . rawurlencode($status);
                    }
                    ?>
                    <a class="btn btn-sm <?= $range === 'month' ? 'btn-primary' : 'btn-outline-primary' ?>" href="payments.php?range=month<?= $payExtra ?>">This month</a>
                    <a class="btn btn-sm <?= $range === 'last' ? 'btn-primary' : 'btn-outline-primary' ?>" href="payments.php?range=last<?= $payExtra ?>">Last month</a>
                    <a class="btn btn-sm <?= $range === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="payments.php?range=all<?= $payExtra ?>">All past</a>
                </div>
            </div>

            <?php if ($is_admin): ?>

                <div class="col-xl-3 col-md-6">

                    <label
                        class="form-label"
                        for="teacher_filter"
                    >
                        Teacher
                    </label>

                    <select
                        class="form-select"
                        id="teacher_filter"
                        name="teacher_filter"
                    >

                        <option value="">
                            All teachers
                        </option>

                        <?php foreach ($allTeachers as $t): ?>

                            <option
                                value="<?= (int)$t['id'] ?>"
                                <?= (
                                    (string)($_GET['teacher_filter'] ?? '')
                                    ===
                                    (string)$t['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= payments_h($t['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            <?php endif; ?>


            <div class="col-xl-2 col-md-6">

                <label
                    class="form-label"
                    for="date_from"
                >
                    From
                </label>

                <input
                    class="form-control"
                    type="date"
                    id="date_from"
                    name="date_from"
                    max="<?= date('Y-m-d') ?>"
                    value="<?= payments_h($date_from) ?>"
                >

            </div>


            <div class="col-xl-2 col-md-6">

                <label
                    class="form-label"
                    for="date_to"
                >
                    To
                </label>

                <input
                    class="form-control"
                    type="date"
                    id="date_to"
                    name="date_to"
                    max="<?= date('Y-m-d') ?>"
                    value="<?= payments_h($date_to) ?>"
                >

            </div>


            <div class="col-xl-2 col-md-6">

                <label
                    class="form-label"
                    for="status"
                >
                    Status
                </label>

                <select
                    class="form-select"
                    id="status"
                    name="status"
                >

                    <option value="">
                        All statuses
                    </option>

                    <option
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="paid"
                        <?= $status === 'paid' ? 'selected' : '' ?>
                    >
                        Paid
                    </option>

                </select>

            </div>


            <div class="col-xl-3 col-md-12 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary flex-fill"
                >
                    <i class="bi bi-funnel"></i>
                    Apply filters
                </button>

                <a
                    class="btn btn-outline-secondary"
                    href="payments.php"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- RECORDS -->

    <div class="finance-panel">

        <div class="finance-panel-header">

            <div>

                <h2>
                    Lesson payment records
                </h2>

                <small class="text-body-secondary">

                    Showing
                    <?= number_format(count($entries)) ?>
                    of
                    <?= number_format($totalItems) ?>
                    records

                    · Future classes excluded

                </small>

            </div>

            <span class="badge text-bg-light border">

                Duration based · Rs 500–Rs 1100 / student

            </span>

        </div>


        <div class="finance-table-wrap">

            <table class="table finance-table finance-table-hover">

                <thead>

                    <tr>

                        <th>Date</th>
                        <th>Teacher</th>
                        <th>Lesson</th>
                        <th>Students</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$entries): ?>

                    <tr>

                        <td colspan="7">

                            <div class="finance-empty">

                                <i class="bi bi-receipt"></i>

                                <strong>
                                    No payment records found
                                </strong>

                                <div>
                                    Try changing the filters.
                                </div>

                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($entries as $e): ?>

                        <?php
                        /*
                         * IMPORTANT:
                         * lesson_amount() accepts:
                         *     lesson_amount(student_count, start_time, end_time)
                         *
                         * The shared payment configuration calculates the correct rate
                         * from the actual lesson duration.
                         */
                        $studentCount = max(
                            0,
                            (int)($e['student_count'] ?? 0)
                        );

                        $settlement = \Edexcel\Services\ClassSessionFeeCalculator::settlement($e);
                        $amountLabel = \Edexcel\Services\TeacherPaymentSmsService::amountLabel($e);

                        $entryDate = (string)($e['date'] ?? '');
                        $startTime = (string)($e['start_time'] ?? '');
                        $endTime = (string)($e['end_time'] ?? '');
                        ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= payments_date($entryDate, 'd M Y') ?>
                                </strong>

                                <small class="d-block text-body-secondary">
                                    <?= payments_date($entryDate, 'D') ?>
                                </small>

                            </td>


                            <td class="teacher-cell">

                                <strong>
                                    <?= payments_h($e['teacher_name'] ?? '') ?>
                                </strong>

                                <small>
                                    <?= payments_h($e['room_name'] ?? '') ?>
                                </small>

                            </td>


                            <td>

                                <strong>
                                    <?= payments_h($e['subject_name'] ?? '') ?>
                                </strong>

                                <small class="d-block text-body-secondary">

                                    <?= payments_h($e['class_name'] ?? '') ?>

                                    ·

                                    <?= payments_date($startTime, 'h:i A') ?>

                                    –

                                    <?= payments_date($endTime, 'h:i A') ?>

                                </small>

                            </td>


                            <td>
                                <?= number_format($studentCount) ?>
                            </td>


                            <td class="amount">

                                <?= payments_h($amountLabel) ?>
                                <small class="d-block text-body-secondary">Teacher payment</small>

                                <?php if (!empty($settlement['uses_online_rule'])): ?>
                                    <small class="d-block text-body-secondary">
                                        Online class<br>
                                        Student fee <?= \Edexcel\Services\ClassSessionFeeCalculator::formatRs(\Edexcel\Services\ClassSessionFeeCalculator::toCents($settlement['totals']['gross_class_fee'])) ?><br>
                                        Institute fee <?= \Edexcel\Services\ClassSessionFeeCalculator::formatRs(\Edexcel\Services\ClassSessionFeeCalculator::toCents($settlement['totals']['institute_online_fee'])) ?><br>
                                        Handling <?= \Edexcel\Services\ClassSessionFeeCalculator::formatRs(\Edexcel\Services\ClassSessionFeeCalculator::toCents($settlement['totals']['transaction_handling_fee'])) ?><br>
                                        Teacher net <?= \Edexcel\Services\ClassSessionFeeCalculator::formatRs(\Edexcel\Services\ClassSessionFeeCalculator::toCents($settlement['totals']['teacher_net_amount'])) ?>
                                    </small>
                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (($e['payment_status'] ?? '') === 'paid'): ?>

                                    <span class="finance-status paid">

                                        <i class="bi bi-check-circle-fill"></i>

                                        Paid

                                    </span>

                                    <?php if (!empty($e['payment_date'])): ?>

                                        <small class="d-block text-body-secondary mt-1">

                                            <?= payments_date(
                                                $e['payment_date'],
                                                'd M Y'
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                    <?php if ($is_admin): ?>
                                        <?php
                                        $smsRow = $paymentSmsById[(int)$e['id']] ?? null;
                                        $smsStatus = (string)($smsRow['status'] ?? '');
                                        $smsWhen = (string)($smsRow['sent_at'] ?? $smsRow['created_at'] ?? '');
                                        ?>
                                        <small class="d-block mt-1">
                                            Teacher SMS:
                                            <?= payments_h(\Edexcel\Services\TeacherPaymentSmsService::statusLabel($smsStatus)) ?>
                                        </small>
                                        <?php if ($smsWhen !== ''): ?>
                                            <small class="d-block text-body-secondary">
                                                <?= $smsStatus === 'failed' ? 'SMS attempted' : 'SMS sent' ?>:
                                                <?= payments_h(date('d/m/Y H:i', strtotime($smsWhen))) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="finance-status pending">

                                        <i class="bi bi-clock-fill"></i>

                                        Pending

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td class="text-end">

                                <?php if ($is_admin): ?>

                                    <?php if (
                                        ($e['payment_status'] ?? '') !== 'paid'
                                        &&
                                        !(int)($e['is_locked'] ?? 0)
                                    ): ?>

                                        <form
                                            method="post"
                                            class="d-inline js-mark-paid-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= payments_h(
                                                    generate_csrf_token()
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="entry_id"
                                                value="<?= (int)$e['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="mark_paid"
                                                value="1"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-success"
                                            >

                                                <i class="bi bi-check2"></i>
                                                Mark paid

                                            </button>

                                        </form>

                                    <?php elseif (
                                        ($e['payment_status'] ?? '') !== 'paid'
                                        && (int)($e['is_locked'] ?? 0)
                                    ): ?>

                                        <span class="text-body-secondary small">
                                            Locked
                                        </span>

                                    <?php elseif (($e['payment_status'] ?? '') === 'paid'): ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary js-resend-payment-sms"
                                            data-kind="timetable"
                                            data-id="<?= (int)$e['id'] ?>"
                                        >
                                            <i class="bi bi-chat-dots"></i>
                                            Resend SMS
                                        </button>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span
                                        class="text-body-secondary small"
                                        title="Only the institute administrator can mark payments as paid."
                                    >
                                        <i class="bi bi-shield-lock"></i>
                                        Admin only
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <?php if (!empty($pagination)): ?>

            <div class="p-3 border-top">

                <?= render_pagination($pagination, $_GET) ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<style>
/*
 * Small page-specific improvements.
 * The main finance styling continues to come from the existing
 * site/header styles.
 */

.finance-table td {
    vertical-align: middle;
}

.finance-table .teacher-cell strong {
    display: block;
}

.finance-table .teacher-cell small {
    display: block;
    margin-top: 3px;
    color: var(--bs-secondary-color, #6c757d);
}

.finance-table .amount {
    font-weight: 700;
    white-space: nowrap;
}

.finance-table th {
    white-space: nowrap;
}

.finance-table td:last-child {
    white-space: nowrap;
}

.finance-toolbar {
    margin-bottom: 1.25rem;
}

@media (max-width: 768px) {

    .finance-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .finance-table {
        min-width: 900px;
    }

    .finance-heading {
        gap: 15px;
    }

}

/* Payment status icon colours */
.finance-status.paid i {
    color: #16a34a;
}

.finance-status.pending i {
    color: #f59e0b;
}

/* Keep the status text readable and consistent */
.finance-status.paid {
    color: #15803d;
}

.finance-status.pending {
    color: #b45309;
}

.pay-confirm-modal .modal-dialog {
    max-width: 420px;
}

.pay-confirm-modal .modal-content {
    border: 1px solid var(--panel-border);
    border-radius: 20px;
    background: var(--surface);
    color: var(--text);
    box-shadow: 0 24px 60px rgba(8, 12, 24, .45);
}

.pay-confirm-modal .modal-body {
    padding: 28px 26px 24px;
    text-align: center;
}

.pay-confirm-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 16px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: rgba(22, 163, 74, .16);
    color: #4ade80;
    font-size: 1.6rem;
}

.pay-confirm-title {
    margin: 0 0 8px;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text);
}

.pay-confirm-text {
    margin: 0 0 22px;
    color: var(--muted);
    font-size: .95rem;
    line-height: 1.5;
}

.pay-confirm-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.pay-confirm-actions .btn {
    min-width: 118px;
    border-radius: 999px;
    font-weight: 700;
}

html[data-bs-theme="dark"] .pay-confirm-modal .modal-content {
    background: #1c2230;
    border-color: rgba(255,255,255,.1);
}

html[data-bs-theme="dark"] .pay-confirm-modal .btn-outline-secondary {
    color: #d7deee;
    border-color: rgba(255,255,255,.16);
}

</style>

<div class="modal fade pay-confirm-modal" id="markPaidModal" tabindex="-1" aria-labelledby="markPaidTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <div class="pay-confirm-icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></div>
                <h2 class="pay-confirm-title" id="markPaidTitle">Mark this lesson as paid?</h2>
                <p class="pay-confirm-text">This records the payment for this class. After it is saved as paid, the teacher receives one SMS. You can still review it later in the payment list.</p>
                <div class="pay-confirm-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="markPaidConfirmBtn">
                        <i class="bi bi-check2 me-1"></i> Mark paid
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('markPaidModal');
    var confirmBtn = document.getElementById('markPaidConfirmBtn');
    if (!modalEl || !confirmBtn || !window.bootstrap) {
        return;
    }
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var pendingForm = null;
    document.querySelectorAll('form.js-mark-paid-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            pendingForm = form;
            modal.show();
        });
    });
    confirmBtn.addEventListener('click', function () {
        if (!pendingForm) {
            return;
        }
        var form = pendingForm;
        pendingForm = null;
        modal.hide();
        HTMLFormElement.prototype.submit.call(form);
    });
});

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
    body.set('kind', button.getAttribute('data-kind') || 'timetable');
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
                    throw new Error(sent.error || sent.sms_notice || 'Payment SMS could not be sent.');
                }
                var line = sent.sms_status === 'resent' || sent.sms_status === 'sent'
                    ? 'SMS notification sent to the teacher. The payment stays PAID.'
                    : (sent.sms_notice || 'Payment SMS could not be sent. The payment stays PAID.');
                alert(line);
                if (sent.sms_status === 'resent' || sent.sms_status === 'sent') {
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