<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/payment.php';
require_login();

$is_admin = is_admin();
$loggedTeacherId = (int)($_SESSION['teacher_id'] ?? 0);

$teacherId = $is_admin ? (int)($_GET['teacher_id'] ?? 0) : $loggedTeacherId;
$defaultStart = date('Y-m-01');
$defaultEnd = date('Y-m-t');
$start = $_GET['date_from'] ?? $defaultStart;
$end = $_GET['date_to'] ?? $defaultEnd;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = $defaultStart;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = $defaultEnd;
if ($start > $end) [$start, $end] = [$end, $start];

$teachers = [];
if ($is_admin) {
    $teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
}

$teacherName = 'All Teachers';
if ($teacherId > 0) {
    $stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$teacherId]);
    $teacherName = $stmt->fetchColumn() ?: 'Teacher not found';
}

// Ledger dates are based on class date and payment date. Opening balance is the
// unpaid value carried into each day; same-day payments reduce that day's balance.
$openingSql = "SELECT COALESCE(SUM(
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
               ),0)
               FROM timetable
               WHERE deleted_at IS NULL
                 AND date < ?
                 AND (payment_status = 'pending' OR payment_date >= ?)
                 AND (? = 0 OR teacher_id = ?)
                 AND date IS NOT NULL";

$earnedSql = "SELECT date, COALESCE(SUM(
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
                   ),0) AS earned, COUNT(*) AS lessons, COALESCE(SUM(student_count),0) AS students
              FROM timetable
              WHERE deleted_at IS NULL AND date BETWEEN ? AND ?
                AND (? = 0 OR teacher_id = ?)
              GROUP BY date ORDER BY date";

$paidSql = "SELECT payment_date, COALESCE(SUM(
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
                 ),0) AS paid, COUNT(*) AS payments
            FROM timetable
            WHERE deleted_at IS NULL AND payment_status = 'paid' AND payment_date BETWEEN ? AND ?
              AND (? = 0 OR teacher_id = ?)
            GROUP BY payment_date ORDER BY payment_date";

$stmt = $pdo->prepare($openingSql);
$stmt->execute([$start, $start, $teacherId, $teacherId]);
$opening = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare($earnedSql);
$stmt->execute([$start, $end, $teacherId, $teacherId]);
$earnedRows = $stmt->fetchAll();

$stmt = $pdo->prepare($paidSql);
$stmt->execute([$start, $end, $teacherId, $teacherId]);
$paidRows = $stmt->fetchAll();

$earnedByDate = [];
foreach ($earnedRows as $row) $earnedByDate[$row['date']] = $row;
$paidByDate = [];
foreach ($paidRows as $row) $paidByDate[$row['payment_date']] = $row;

$days = [];
$cursor = new DateTimeImmutable($start);
$last = new DateTimeImmutable($end);
$balance = $opening;
while ($cursor <= $last) {
    $date = $cursor->format('Y-m-d');
    $earned = (float)($earnedByDate[$date]['earned'] ?? 0);
    $paid = (float)($paidByDate[$date]['paid'] ?? 0);
    $students = (int)($earnedByDate[$date]['students'] ?? 0);
    $lessons = (int)($earnedByDate[$date]['lessons'] ?? 0);
    $closing = $balance + $earned - $paid;
    $days[] = compact('date','balance','earned','paid','closing','students','lessons');
    $balance = $closing;
    $cursor = $cursor->modify('+1 day');
}

$totalEarned = array_sum(array_column($days, 'earned'));
$totalPaid = array_sum(array_column($days, 'paid'));
$closingBalance = $balance;
$totalLessons = array_sum(array_column($days, 'lessons'));
$totalStudents = array_sum(array_column($days, 'students'));

// Detailed ledger rows for the selected period.
$where = ["t.deleted_at IS NULL", "t.date BETWEEN ? AND ?"];
$params = [$start, $end];
if ($teacherId > 0) { $where[] = 't.teacher_id = ?'; $params[] = $teacherId; }
$detailSql = "SELECT t.id, t.date, t.start_time, t.end_time, t.student_count, t.payment_status, t.payment_date,
                     s.name AS subject_name, c.name AS class_name, r.name AS room_name, tc.name AS teacher_name
              FROM timetable t
              JOIN teachers tc ON tc.id=t.teacher_id
              JOIN subjects s ON s.id=t.subject_id
              JOIN student_classes c ON c.id=t.class_id
              JOIN rooms r ON r.id=t.room_id
              WHERE " . implode(' AND ', $where) . " ORDER BY t.date ASC, t.start_time ASC";
$stmt = $pdo->prepare($detailSql);
$stmt->execute($params);
$details = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php'; 


?>
 
<div class="finance-page">
    <div class="finance-heading">
        <div>
            <h1><i class="bi bi-journal-text"></i> Teacher Payment Ledger</h1>
            <p><?= htmlspecialchars($teacherName) ?> · Daily earnings, payments and carried balance.</p>
        </div>
        <div class="finance-actions"><button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print ledger</button><a class="btn btn-primary" href="payments.php"><i class="bi bi-credit-card"></i> Payment status</a></div>
    </div>

    <div class="finance-toolbar">
        <form method="get" class="row g-3 align-items-end">
            <?php if ($is_admin): ?><div class="col-lg-3 col-md-6"><label class="form-label" for="teacher_id">Teacher</label><select class="form-select" id="teacher_id" name="teacher_id"><option value="0">All teachers</option><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>" <?= $teacherId === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-lg-3 col-md-6"><label class="form-label" for="date_from">Period start</label><input class="form-control" type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($start) ?>"></div>
            <div class="col-lg-3 col-md-6"><label class="form-label" for="date_to">Period end</label><input class="form-control" type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($end) ?>"></div>
            <div class="col-lg-3 col-md-6 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Generate</button><a class="btn btn-outline-secondary" href="payment_ledger.php"><i class="bi bi-calendar-month"></i> This month</a></div>
        </form>
    </div>

    <div class="finance-stat-grid">
        <article class="finance-stat"><span class="icon"><i class="bi bi-box-arrow-in-right"></i></span><span class="label">Opening balance</span><span class="value"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($opening) ?></span><span class="meta">Carried in</span></article>
        <article class="finance-stat"><span class="icon"><i class="bi bi-plus-circle"></i></span><span class="label">Earned</span><span class="value text-primary"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($totalEarned) ?></span><span class="meta"><?= number_format($totalLessons) ?> lessons · <?= number_format($totalStudents) ?> student places</span></article>
        <article class="finance-stat"><span class="icon"><i class="bi bi-dash-circle"></i></span><span class="label">Paid</span><span class="value text-success"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($totalPaid) ?></span><span class="meta">Payments recorded in period</span></article>
        <article class="finance-stat"><span class="icon"><i class="bi bi-wallet2"></i></span><span class="label">Closing balance</span><span class="value <?= $closingBalance > 0 ? 'text-warning-emphasis' : 'text-success' ?>"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($closingBalance) ?></span><span class="meta">Outstanding at period end</span></article>
    </div>

    <div class="finance-panel mb-4">
        <div class="finance-panel-header"><div><h2>Daily ledger</h2><small class="text-body-secondary">Opening + earned − paid = closing balance</small></div><span class="badge text-bg-light border"><?= htmlspecialchars($start) ?> → <?= htmlspecialchars($end) ?></span></div>
        <div class="finance-table-wrap"><table class="table finance-table finance-ledger-table"><thead><tr><th>Date</th><th>Lessons</th><th>Opening</th><th>Earned</th><th>Paid</th><th>Closing</th></tr></thead><tbody>
        <?php foreach ($days as $day): ?><tr><td><strong><?= date('d M Y', strtotime($day['date'])) ?></strong><small class="d-block text-body-secondary"><?= date('D', strtotime($day['date'])) ?></small></td><td><?= number_format($day['lessons']) ?><small class="d-block text-body-secondary"><?= number_format($day['students']) ?> students</small></td><td class="opening"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($day['balance']) ?></td><td class="earned"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($day['earned']) ?></td><td class="paid">− <?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($day['paid']) ?></td><td class="closing"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($day['closing']) ?></td></tr><?php endforeach; ?>
        <?php if (!$days): ?><tr><td colspan="6"><div class="finance-empty"><i class="bi bi-calendar-x"></i>No ledger dates found.</div></td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="finance-panel">
        <div class="finance-panel-header"><div><h2>Lesson detail</h2><small class="text-body-secondary">Every class contributing to the selected period.</small></div></div>
        <div class="finance-table-wrap"><table class="table finance-table"><thead><tr><th>Date / Time</th><th>Teacher</th><th>Subject / Class</th><th>Students</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        <?php if (!$details): ?><tr><td colspan="6"><div class="finance-empty"><i class="bi bi-receipt"></i>No lessons found.</div></td></tr><?php else: foreach ($details as $d): $amount=lesson_amount(
    (int)$d['student_count'],
    (string)$d['start_time'],
    (string)$d['end_time']
); ?><tr><td><strong><?= date('d M Y',strtotime($d['date'])) ?></strong><small class="d-block text-body-secondary"><?= date('h:i A',strtotime($d['start_time'])) ?>–<?= date('h:i A',strtotime($d['end_time'])) ?></small></td><td><?= htmlspecialchars($d['teacher_name']) ?><small class="d-block text-body-secondary"><?= htmlspecialchars($d['room_name']) ?></small></td><td><strong><?= htmlspecialchars($d['subject_name']) ?></strong><small class="d-block text-body-secondary"><?= htmlspecialchars($d['class_name']) ?></small></td><td><?= number_format((int)$d['student_count']) ?></td><td class="amount"><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format($amount) ?></td><td><?php if ($d['payment_status']==='paid'): ?><span class="finance-status paid"><i class="bi bi-check-circle-fill"></i> Paid</span><small class="d-block text-body-secondary mt-1"><?= $d['payment_date'] ? date('d M Y',strtotime($d['payment_date'])) : '' ?></small><?php else: ?><span class="finance-status pending"><i class="bi bi-clock-fill"></i> Pending</span><?php endif; ?></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
