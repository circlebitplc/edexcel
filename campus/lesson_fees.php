<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/payment_controls.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_staff();
ensure_campus_schema($pdo);
ensure_recordings_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$selectedLesson = (int)($_GET['lesson'] ?? $_POST['timetable_id'] ?? 0);

$fees = new StudentLessonFeeService($pdo);
$recordings = new RecordingService($pdo);

$loadLesson = static function (PDO $pdo, int $lessonId) use ($teacherId, $isAdmin): ?array {
    if ($lessonId < 1) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        JOIN teachers t ON t.id = tt.teacher_id
        WHERE tt.id = ? AND tt.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$lesson) {
        return null;
    }
    $sub = (int)($lesson['substitute_teacher_id'] ?? 0);
    if (!RecordingAccessService::teacherCanManageLesson($teacherId, (int)$lesson['teacher_id'], $isAdmin, $sub)) {
        return null;
    }
    return $lesson;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }
        $lessonId = (int)($_POST['timetable_id'] ?? 0);
        $lesson = $loadLesson($pdo, $lessonId);
        if (!$lesson) {
            throw new RuntimeException('Choose one of your classes first.');
        }
        $paidIds = $_POST['paid'] ?? [];
        $unpaidIds = $_POST['unpaid'] ?? [];
        $statuses = $_POST['status'] ?? [];
        if (!is_array($paidIds)) {
            $paidIds = [];
        }
        if (!is_array($unpaidIds)) {
            $unpaidIds = [];
        }
        if (!is_array($statuses)) {
            $statuses = [];
        }
        foreach ($statuses as $studentId => $state) {
            $studentId = (int)$studentId;
            if ($studentId < 1) {
                continue;
            }
            if ($state === 'unpaid') {
                $unpaidIds[] = $studentId;
            } else {
                $paidIds[] = $studentId;
            }
        }
        $paidIds = array_values(array_unique(array_filter(array_map('intval', $paidIds))));
        $unpaidIds = array_values(array_unique(array_filter(array_map('intval', $unpaidIds))));
        $unpaidIds = array_values(array_diff($unpaidIds, $paidIds));
        if ($paidIds === [] && $unpaidIds === []) {
            throw new RuntimeException('Choose Paid or Unpaid for at least one student, then save.');
        }
        if (!teacher_manual_payment_allowed(teacher_manual_payment_enabled($pdo), $isAdmin, $lesson)) {
            throw new RuntimeException('Manual teacher payments are currently disabled by the administrator.');
        }
        $manualMethod = 'cash';
        if (!$isAdmin && lesson_is_online_class($lesson) && $paidIds !== []) {
            $manualMethod = 'manual';
        }

        $recording = $recordings->activeForLesson($lessonId);
        $recordingId = $recording ? (int)$recording['id'] : null;
        $marked = 0;
        $unmarked = 0;
        $already = 0;
        $failed = [];
        foreach ($paidIds as $studentId) {
            try {
                $result = $fees->collectCash($studentId, $lesson, $userId, $recordingId, [
                    'method' => $manualMethod,
                ]);
                if (!empty($result['marked'])) {
                    $marked++;
                } else {
                    $already++;
                }
                if (function_exists('log_audit') && !empty($result['marked'])) {
                    log_audit($pdo, 'lesson_fee_cash', 'student_lesson_fees', $lessonId, null, [
                        'student_id' => $studentId,
                        'class_id' => (int)($lesson['class_id'] ?? 0),
                        'amount' => $fees->lessonAmount($lesson),
                        'payment_method' => $manualMethod === 'manual' ? 'Teacher Manual Payment' : $manualMethod,
                        'collected_by' => $userId,
                        'transaction_id' => $result['transaction_id'] ?? null,
                    ]);
                }
            } catch (Throwable $e) {
                $failed[] = $e->getMessage() !== '' ? $e->getMessage() : (string)$studentId;
            }
        }
        foreach ($unpaidIds as $studentId) {
            try {
                $result = $fees->unpay($studentId, $lesson, $userId);
                if (!empty($result['unmarked'])) {
                    $unmarked++;
                } else {
                    $already++;
                }
                if (function_exists('log_audit') && !empty($result['unmarked'])) {
                    log_audit($pdo, 'lesson_fee_unpay', 'student_lesson_fees', $lessonId, null, [
                        'student_id' => $studentId,
                    ]);
                }
            } catch (Throwable $e) {
                $failed[] = $e->getMessage();
            }
        }
        $parts = [];
        if ($marked > 0) {
            $parts[] = $marked . ' marked paid — they can watch the recording.';
        }
        if ($unmarked > 0) {
            $parts[] = $unmarked . ' marked unpaid — recording locked.';
        }
        if ($already > 0) {
            $parts[] = $already . ' already on that status.';
        }
        if ($failed !== []) {
            $parts[] = implode(' ', array_unique($failed));
        }
        if ($parts === []) {
            throw new RuntimeException('No fee changes recorded.');
        }
        $success = implode(' ', $parts);
        $selectedLesson = $lessonId;
        $date = (string)$lesson['date'];
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$hasSubstitute = campus_column_exists($pdo, 'timetable', 'substitute_teacher_id');
$sql = "
    SELECT tt.id, tt.date, tt.start_time, tt.end_time, tt.teacher_id, tt.delivery_mode,
           s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
    FROM timetable tt
    JOIN subjects s ON s.id = tt.subject_id
    JOIN student_classes c ON c.id = tt.class_id
    JOIN teachers t ON t.id = tt.teacher_id
    WHERE tt.deleted_at IS NULL
      AND tt.date = ?
";
$params = [$date];
if (!$isAdmin && $teacherId > 0) {
    if ($hasSubstitute) {
        $sql .= ' AND (tt.teacher_id = ? OR COALESCE(tt.substitute_teacher_id, 0) = ?)';
        $params[] = $teacherId;
        $params[] = $teacherId;
    } else {
        $sql .= ' AND tt.teacher_id = ?';
        $params[] = $teacherId;
    }
}
$sql .= ' ORDER BY tt.start_time';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roster = [];
$currentLesson = null;
$feeAmount = 0.0;
$paidCount = 0;
$dueCount = 0;
if ($selectedLesson > 0) {
    foreach ($lessons as $lessonRow) {
        if ((int)$lessonRow['id'] === $selectedLesson) {
            $currentLesson = $loadLesson($pdo, $selectedLesson);
            break;
        }
    }
    if ($currentLesson) {
        $feeAmount = $fees->lessonAmount($currentLesson);
        $recording = $recordings->activeForLesson($selectedLesson);
        $recordingId = $recording ? (int)$recording['id'] : null;
        $classId = (int)$currentLesson['class_id'];
        $paidGateways = $fees->paidGatewaysForLesson($selectedLesson);
        $rosterStmt = $pdo->prepare("
            SELECT u.id, COALESCE(sp.full_name, u.username) AS name, sa.status AS attendance_status
            FROM student_enrollments se
            JOIN users u ON u.id = se.student_id AND u.deleted_at IS NULL
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN student_attendance sa
                ON sa.student_id = u.id AND sa.timetable_id = ?
            WHERE se.class_id = ?
            ORDER BY name
        ");
        $rosterStmt->execute([$selectedLesson, $classId]);
        foreach ($rosterStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!$fees->isEnrolled((int)$row['id'], $currentLesson)) {
                continue;
            }
            $resolved = $fees->resolve((int)$row['id'], $currentLesson, $recordingId);
            $row['fee_status'] = (string)$resolved['status'];
            $row['covered_by_monthly'] = (bool)$resolved['covered_by_monthly'];
            $row['amount_due'] = (float)$resolved['amount_due'];
            $row['unlocked'] = StudentLessonFeeService::isUnlocked($row['fee_status'], $row['covered_by_monthly']);
            $row['may_collect'] = StudentLessonFeeService::teacherMayCollect($row['fee_status'], $row['covered_by_monthly']);
            $row['may_unpay'] = StudentLessonFeeService::teacherMayUnpay($row['fee_status'], $row['covered_by_monthly'], $row['amount_due']);
            $gw = (string)($paidGateways[(int)$row['id']] ?? '');
            if ($gw === '' && $row['unlocked'] && !$row['covered_by_monthly'] && $row['fee_status'] !== 'waived') {
                $gw = 'cash';
            }
            $row['pay_gateway'] = $gw;
            $row['pay_method_label'] = StudentLessonFeeService::gatewayLabel($gw);
            if ($row['unlocked']) {
                $paidCount++;
            } else {
                $dueCount++;
            }
            $roster[] = $row;
        }
    }
}

$onlineClass = is_array($currentLesson) && lesson_is_online_class($currentLesson);
$manualSettingOn = teacher_manual_payment_enabled($pdo);
$manualAllowed = !is_array($currentLesson) || teacher_manual_payment_allowed($manualSettingOn, $isAdmin, $currentLesson);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cash-coin text-primary"></i> Class fees</h1>
            <p class="text-muted mb-0">Mark paid or unpaid. Paid students can join the live class and watch this lesson’s recording. Unpaid locks both again.</p>
        </div>
        <form class="d-flex gap-2" method="get">
            <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
            <button class="btn btn-outline-primary">Go</button>
        </form>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h5>Classes on <?= e(date('d M Y', strtotime($date))) ?></h5>
                    <?php if (!$lessons): ?>
                        <p class="text-muted mb-0">No classes on this date.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($lessons as $lesson): ?>
                                <a class="list-group-item list-group-item-action <?= (int)$lesson['id'] === $selectedLesson ? 'active' : '' ?>"
                                   href="?date=<?= e($date) ?>&amp;lesson=<?= (int)$lesson['id'] ?>">
                                    <strong><?= e($lesson['subject_name']) ?></strong>
                                    <?php if (lesson_is_online_class($lesson)): ?><span class="badge text-bg-info ms-1">Online</span><?php endif; ?>
                                    <div class="small"><?= e(date('h:i A', strtotime($lesson['start_time']))) ?> · <?= e($lesson['class_name']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <?php if (!$currentLesson): ?>
                        <p class="text-muted mb-0">Select a class to mark who paid.</p>
                    <?php else: ?>
                        <h5 class="mb-1"><?= e($currentLesson['subject_name']) ?> · <?= e($currentLesson['class_name']) ?></h5>
                        <p class="text-muted mb-2">
                            Fee Rs <?= number_format($feeAmount, 2) ?> per student
                            · <?= $paidCount ?> paid
                            · <?= $dueCount ?> unpaid
                        </p>
                        <?php
                        $lessonSettlement = \Edexcel\Services\ClassSessionFeeCalculator::settlement(array_merge($currentLesson, ['student_count' => 1]));
                        if (!empty($lessonSettlement['uses_online_rule']) || !empty($lessonSettlement['uses_in_college_rule'])):
                            $per = $lessonSettlement['per_student'];
                        ?>
                            <div class="border rounded-3 p-3 mb-3 small">
                                <div class="fw-semibold mb-1"><?= !empty($lessonSettlement['uses_online_rule']) ? 'Online class' : 'In-college class' ?></div>
                                <div>Class fee: Rs <?= number_format((float)$per['gross_class_fee'], 2) ?></div>
                                <div>Institute fee: Rs <?= number_format((float)$per['institute_online_fee'], 2) ?></div>
                                <div>Transaction &amp; handling: Rs <?= number_format((float)$per['transaction_handling_fee'], 2) ?></div>
                                <div>Teacher net: Rs <?= number_format((float)$per['teacher_net_amount'], 2) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($onlineClass && !$manualAllowed): ?>
                            <div class="alert alert-secondary"><strong>Manual teacher payments are currently disabled by the administrator.</strong></div>
                        <?php else: ?>
                            <p class="small mb-3">Choose Paid or Unpaid for each student, then save. Unpaid locks the recording until you mark them paid again.</p>
                        <?php endif; ?>
                        <?php if (!$roster): ?>
                            <p class="text-muted mb-0">No enrolled students on this class list yet.</p>
                        <?php else: ?>
                            <form method="post" id="classFeesForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="timetable_id" value="<?= (int)$currentLesson['id'] ?>">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Attendance</th>
                                                <th>Status</th>
                                                <th>Paid</th>
                                                <th>Unpaid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($roster as $row): ?>
                                            <?php $lockedZero = $row['amount_due'] <= 0; ?>
                                            <tr>
                                                <td><?= e($row['name']) ?></td>
                                                <td class="small text-muted"><?= e($row['attendance_status'] ? ucfirst((string)$row['attendance_status']) : 'Not marked') ?></td>
                                                <td>
                                                    <?php if ($row['covered_by_monthly'] && $row['unlocked']): ?>
                                                        <span class="badge text-bg-success">Paid (monthly wallet)</span>
                                                    <?php elseif ($row['fee_status'] === 'waived'): ?>
                                                        <span class="badge text-bg-secondary">Waived</span>
                                                    <?php elseif ($row['unlocked']): ?>
                                                        <?php
                                                        $method = (string)($row['pay_method_label'] ?? 'Paid');
                                                        $gw = (string)($row['pay_gateway'] ?? '');
                                                        $badgeClass = match ($gw) {
                                                            'onepay' => 'text-bg-info',
                                                            'bank' => 'text-bg-primary',
                                                            'cash' => 'text-bg-success',
                                                            'manual' => 'text-bg-success',
                                                            'other' => 'text-bg-dark',
                                                            default => 'text-bg-success',
                                                        };
                                                        ?>
                                                        <span class="badge <?= e($badgeClass) ?>"><?= e($method) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-warning text-dark">Unpaid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$manualAllowed): ?>
                                                        <span class="text-muted">—</span>
                                                    <?php else: ?>
                                                        <input class="form-check-input fee-paid-radio" type="radio"
                                                               name="status[<?= (int)$row['id'] ?>]" value="paid"
                                                               <?= $row['unlocked'] ? 'checked' : '' ?>
                                                               <?= $lockedZero ? 'disabled' : '' ?>
                                                               aria-label="Paid <?= e($row['name']) ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$manualAllowed || $lockedZero): ?>
                                                        <span class="text-muted">—</span>
                                                    <?php else: ?>
                                                        <input class="form-check-input fee-unpaid-radio" type="radio"
                                                               name="status[<?= (int)$row['id'] ?>]" value="unpaid"
                                                               <?= !$row['unlocked'] ? 'checked' : '' ?>
                                                               aria-label="Unpaid <?= e($row['name']) ?>">
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <?php if ($manualAllowed): ?>
                                        <button class="btn btn-primary rounded-pill" type="submit">Save payments</button>
                                        <button class="btn btn-outline-primary rounded-pill" type="button" id="markAllPaid">All paid</button>
                                        <button class="btn btn-outline-secondary rounded-pill" type="button" id="markAllUnpaid">All unpaid</button>
                                    <?php endif; ?>
                                    <a class="btn btn-outline-secondary rounded-pill" href="<?= e(BASE_URL . 'campus/attendance.php?date=' . urlencode($date) . '&lesson=' . (int)$currentLesson['id']) ?>">Attendance</a>
                                    <a class="btn btn-outline-secondary rounded-pill" href="<?= e(BASE_URL . 'campus/recordings.php?lesson=' . (int)$currentLesson['id']) ?>">Recording</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var paidBtn = document.getElementById('markAllPaid');
    var unpaidBtn = document.getElementById('markAllUnpaid');
    if (paidBtn) {
        paidBtn.addEventListener('click', function () {
            document.querySelectorAll('.fee-paid-radio:not(:disabled)').forEach(function (box) { box.checked = true; });
        });
    }
    if (unpaidBtn) {
        unpaidBtn.addEventListener('click', function () {
            document.querySelectorAll('.fee-unpaid-radio:not(:disabled)').forEach(function (box) { box.checked = true; });
        });
    }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
