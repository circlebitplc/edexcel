<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/class_fee_summary.php';
require_staff();
\Edexcel\Services\ClassSessionFeeCalculator::ensureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    $_SESSION['error'] = 'No entry specified.';
    header('Location: index.php');
    exit();
}

// Fetch entry
$stmt = $pdo->prepare("SELECT * FROM timetable WHERE id = ?");
$stmt->execute([$id]);
$entry = $stmt->fetch();
if (!$entry) { 
    $_SESSION['error'] = 'Entry not found.'; 
    header('Location: index.php'); 
    exit(); 
}

$is_admin = is_admin();
$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$is_admin && (int)$teacher_id !== (int)$entry['teacher_id']) {
    $_SESSION['error'] = 'You can only open your own lessons.';
    header('Location: index.php');
    exit();
}
$can_delete = ($is_admin || ($teacher_id && $entry['teacher_id'] == $teacher_id)) && !$entry['is_locked'] && !$entry['deleted_at'];

// --- DELETE HANDLER (service-backed) ---
$delete_success = false;
$delete_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $delete_error = 'Invalid security token.';
    } elseif (!$can_delete) {
        $delete_error = 'You do not have permission to delete this entry.';
    } else {
        try {
            $services = TimetableServiceFactory::services($pdo);

            $services['delete']->delete(
                $id,
                static function (array $currentEntry) use ($is_admin, $teacher_id): void {
                    if (
                        !$is_admin &&
                        (int)($currentEntry['teacher_id'] ?? 0) !== (int)$teacher_id
                    ) {
                        throw new RuntimeException(
                            'You do not have permission to delete this entry.'
                        );
                    }
                }
            );

            $_SESSION['success'] = 'Entry deleted successfully.';
            header('Location: index.php');
            exit();
        } catch (Throwable $e) {
            $delete_error = $e->getMessage();
            error_log('Timetable delete error: ' . $e->getMessage());
        }
    }
}

// If entry is already deleted, disable editing
$is_deleted = !empty($entry['deleted_at']);

// --- EDIT HANDLER (service-backed) ---
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_entry'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $postedTeacherId = (int)validate_input($_POST['teacher_id'] ?? 0, 'int');
            $subject_id = (int)validate_input($_POST['subject_id'] ?? 0, 'int');
            $class_id = (int)validate_input($_POST['class_id'] ?? 0, 'int');
            $room_id = (int)validate_input($_POST['room_id'] ?? 0, 'int');
            $date = validate_input($_POST['date'] ?? '', 'date');
            $start_time = validate_input(
                substr((string)($_POST['start_time'] ?? ''), 0, 5),
                'time'
            );
            $end_time = validate_input(
                substr((string)($_POST['end_time'] ?? ''), 0, 5),
                'time'
            );
            $student_count = (int)validate_input(
                $_POST['student_count'] ?? 0,
                'int'
            );

            $class_fee_per_student = (float)($_POST['class_fee_per_student'] ?? 0);

            $payment_status = validate_input(
                $_POST['payment_status'] ?? 'pending',
                'string'
            );

            $repeat = isset($_POST['repeat_weekly']);
            $repeat_until = trim((string)($_POST['repeat_until'] ?? ''));

            // Teachers cannot edit another teacher's timetable.
            if (!$is_admin) {
                $postedTeacherId = (int)$teacher_id;
                $payment_status = $entry['payment_status'];
            }

            if (
                !$postedTeacherId ||
                !$subject_id ||
                !$class_id ||
                !$room_id ||
                !$date ||
                !$start_time ||
                !$end_time
            ) {
                throw new RuntimeException('All fields are required.');
            }

            if ($start_time >= $end_time) {
                throw new RuntimeException('Start time must be before end time.');
            }

            if ($student_count < 0) {
                throw new RuntimeException('Student count cannot be negative.');
            }

            if ($class_fee_per_student < 0) {
                throw new RuntimeException('Class fee per student cannot be negative.');
            }

            if (
                !in_array(
                    $payment_status,
                    ['pending', 'paid'],
                    true
                )
            ) {
                throw new RuntimeException('Invalid payment status.');
            }

            if ($repeat && empty($repeat_until)) {
                throw new RuntimeException(
                    'Please specify the repeat end date.'
                );
            }

            if ($repeat && $repeat_until < $date) {
                throw new RuntimeException(
                    'Repeat until date must be on or after the lesson date.'
                );
            }

            $payment_date =
                $payment_status === 'paid'
                    ? ($entry['payment_status'] === 'paid' && !empty($entry['payment_date'])
                        ? $entry['payment_date']
                        : date('Y-m-d'))
                    : null;

            $data = [
                'teacher_id' => $postedTeacherId,
                'subject_id' => $subject_id,
                'class_id' => $class_id,
                'room_id' => $room_id,
                'student_count' => $student_count,
                'class_fee_per_student' => $class_fee_per_student,
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'payment_status' => $payment_status,
                'payment_date' => $payment_date,
                'delivery_mode' => classroom_normalize_delivery_mode((string)($_POST['delivery_mode'] ?? $entry['delivery_mode'] ?? 'physical')),
            ];

            $services = TimetableServiceFactory::services($pdo);

            $services['update']->update(
                $id,
                $data,
                static function (array $currentEntry) use ($is_admin, $teacher_id): void {
                    if (
                        !$is_admin &&
                        (int)($currentEntry['teacher_id'] ?? 0) !== (int)$teacher_id
                    ) {
                        throw new RuntimeException(
                            'You do not have permission to edit this entry.'
                        );
                    }
                },
                $repeat,
                $repeat_until
            );

            try {
                require_once __DIR__ . '/../config/notifications.php';
                $lookup = $pdo->prepare("
                    SELECT s.name AS subject_name, c.name AS class_name, r.name AS room_name
                    FROM subjects s, student_classes c, rooms r
                    WHERE s.id = ? AND c.id = ? AND r.id = ?
                ");
                $lookup->execute([$subject_id, $class_id, $room_id]);
                $timetable_data = $lookup->fetch(PDO::FETCH_ASSOC) ?: [];
                $timetable_data['date'] = $date;
                $timetable_data['start_time'] = $start_time;
                $timetable_data['end_time'] = $end_time;
                notify_class_change($pdo, $postedTeacherId, 'edit', $timetable_data);
                notify_class_students($pdo, $class_id, 'edit', $timetable_data);
            } catch (Throwable $e) {
                error_log('Timetable edit notify: ' . $e->getMessage());
            }

            $smsNote = '';
            if (
                $is_admin
                && (($entry['payment_status'] ?? '') !== 'paid')
                && $payment_status === 'paid'
            ) {
                try {
                    $sms = \Edexcel\Services\TeacherPaymentSmsService::notifyTimetablePaid(
                        $pdo,
                        $id,
                        (int)($_SESSION['user_id'] ?? 0),
                        false
                    );
                    $smsNote = ' Payment marked as PAID. ' . (string)($sms['sms_notice'] ?? 'Payment SMS could not be sent.');
                } catch (Throwable $smsError) {
                    error_log('Lesson edit payment SMS failed: ' . $smsError->getMessage());
                    $smsNote = ' Payment marked as PAID. Payment SMS could not be sent.';
                }
            }

            $_SESSION['success'] =
                'Timetable entry updated successfully.' .
                ($repeat
                    ? " (repeats weekly until $repeat_until)"
                    : '') .
                $smsNote;

            header('Location: index.php');
            exit();

        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log('Timetable edit error: ' . $e->getMessage());
        }
    }
}

// AJAX check for edit — service-backed and read-only.
if (isset($_GET['check']) && (string)$_GET['check'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $teacher_id_check = filter_var(
            $_GET['teacher_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $room_id_check = filter_var(
            $_GET['room_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $class_id_check = filter_var(
            $_GET['class_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $date_check = trim((string)($_GET['date'] ?? ''));
        $start_check = substr(trim((string)($_GET['start'] ?? '')), 0, 5);
        $end_check = substr(trim((string)($_GET['end'] ?? '')), 0, 5);
        $exclude_check = (int)($_GET['exclude'] ?? 0);

        if (
            $teacher_id_check === false ||
            $room_id_check === false ||
            $class_id_check === false ||
            $teacher_id_check <= 0 ||
            $room_id_check <= 0 ||
            $class_id_check <= 0 ||
            $date_check === '' ||
            $start_check === '' ||
            $end_check === '' ||
            $start_check >= $end_check
        ) {
            throw new InvalidArgumentException(
                'Invalid conflict-check parameters.'
            );
        }

        $services = TimetableServiceFactory::services($pdo);

        $conflict = $services['conflict']->message(
            (int)$teacher_id_check,
            (int)$room_id_check,
            (int)$class_id_check,
            $date_check,
            $start_check,
            $end_check,
            $exclude_check
        );

        echo json_encode([
            'conflict' => $conflict !== null,
            'message' => $conflict
        ]);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode([
            'conflict' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// Load dropdowns (exclude soft-deleted)
if ($is_admin) {
    $teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
} else {
    $teachers = $pdo->prepare("SELECT id, name FROM teachers WHERE id = ? AND deleted_at IS NULL");
    $teachers->execute([$teacher_id]);
    $teachers = $teachers->fetchAll();
}
$subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$rooms = $pdo->query("SELECT id, name FROM rooms WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$bankReady = \Edexcel\Services\TeacherBankAccountService::publicSummaries(
    $pdo,
    array_map(static fn(array $row): int => (int)$row['id'], $teachers)
);
$bankDetailsUrl = $is_admin
    ? rtrim((string)BASE_URL, '/') . '/admin/teacher_banks.php?teacher='
    : rtrim((string)BASE_URL, '/') . '/teachers/bank_details.php';
$entryAlreadyOnline = classroom_normalize_delivery_mode((string)($entry['delivery_mode'] ?? 'physical')) === 'online';

// Check if this entry already has a recurring schedule
$has_recurring = false;
$recurring_end_date = '';
$stmt = $pdo->prepare("SELECT end_date FROM recurring_schedules 
                       WHERE teacher_id = ? AND subject_id = ? AND class_id = ? AND room_id = ?
                       AND day_of_week = ? AND start_time = ? AND end_time = ?
                       AND start_date <= ? AND end_date >= ?");
$day_of_week = date('l', strtotime($entry['date']));
$stmt->execute([
    $entry['teacher_id'], $entry['subject_id'], $entry['class_id'], $entry['room_id'],
    $day_of_week, $entry['start_time'], $entry['end_time'],
    $entry['date'], $entry['date']
]);
$recurring = $stmt->fetch();
if ($recurring) {
    $has_recurring = true;
    $recurring_end_date = $recurring['end_date'];
}

include __DIR__ . '/../includes/header.php';
?>
<h1><i class="bi bi-pencil-square"></i> Edit Timetable Entry</h1>

<?php if ($delete_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($delete_error) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($is_deleted): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> This entry has been deleted (soft delete). You cannot edit it.
    </div>
<?php endif; ?>

<form id="timetableForm" method="POST" action="edit.php?id=<?= $id ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="teacher_id" class="form-label">Teacher <span class="text-danger">*</span></label>
                <select class="form-select" id="teacher_id" name="teacher_id" required <?= $is_deleted ? 'disabled' : '' ?>>
                    <option value="">Select Teacher</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($entry['teacher_id'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                <select class="form-select" id="subject_id" name="subject_id" required <?= $is_deleted ? 'disabled' : '' ?>>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($entry['subject_id'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="mb-3">
                <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                <select class="form-select" id="class_id" name="class_id" required <?= $is_deleted ? 'disabled' : '' ?>>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($entry['class_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label for="room_id" class="form-label">Room <span class="text-danger">*</span></label>
                <select class="form-select" id="room_id" name="room_id" required <?= $is_deleted ? 'disabled' : '' ?>>
                    <option value="">Select Room</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($entry['room_id'] == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="date" name="date" required value="<?= $entry['date'] ?>" <?= $is_deleted ? 'disabled' : '' ?>>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label for="student_count" class="form-label">Students</label>
                <input type="number" class="form-control" id="student_count" name="student_count" min="0" value="<?= $entry['student_count'] ?>" <?= $is_deleted ? 'disabled' : '' ?>>
                <small class="text-muted">Update after class</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label for="delivery_mode" class="form-label">How this class runs</label>
                <?php $mode = classroom_normalize_delivery_mode((string)($entry['delivery_mode'] ?? 'physical')); ?>
                <select class="form-select" id="delivery_mode" name="delivery_mode" <?= $is_deleted ? 'disabled' : '' ?>>
                    <option value="physical" <?= $mode === 'physical' ? 'selected' : '' ?>>In college</option>
                    <option value="online" <?= $mode === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="hybrid" <?= $mode === 'hybrid' ? 'selected' : '' ?>>Online and in college</option>
                </select>
            </div>
        </div>

        <div class="col-md-3">
            <div class="mb-3">
                <label for="class_fee_per_student" class="form-label">
                    Class Fee / Student / Session
                </label>
                <div class="input-group">
                    <span class="input-group-text">Rs.</span>
                    <input
                        type="number"
                        class="form-control"
                        id="class_fee_per_student"
                        name="class_fee_per_student"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars((string)($entry['class_fee_per_student'] ?? '0.00')) ?>"
                        <?= $is_deleted ? 'disabled' : '' ?>
                    >
                </div>
                <small class="text-muted">
                    Amount charged to each student for this session.
                </small>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="start_time" name="start_time" required value="<?= substr($entry['start_time'], 0, 5) ?>" <?= $is_deleted ? 'disabled' : '' ?>>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="end_time" name="end_time" required value="<?= substr($entry['end_time'], 0, 5) ?>" <?= $is_deleted ? 'disabled' : '' ?>>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <?php if ($is_admin): ?>
                    <label for="payment_status" class="form-label">Payment Status</label>
                    <select class="form-select" id="payment_status" name="payment_status" <?= $is_deleted ? 'disabled' : '' ?>>
                        <option value="pending" <?= ($entry['payment_status'] == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= ($entry['payment_status'] == 'paid') ? 'selected' : '' ?>>Paid</option>
                    </select>
                    <?php if ($entry['payment_status'] == 'paid' && $entry['payment_date']): ?>
                        <small class="text-muted">Paid on <?= date('d M Y', strtotime($entry['payment_date'])) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <label for="payment_status_display" class="form-label">Payment Status</label>
                    <div class="form-control bg-light" id="payment_status_display" style="cursor: not-allowed; min-height: 38px; display: flex; align-items: center;">
                        <span class="badge <?= $entry['payment_status'] == 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= ucfirst($entry['payment_status']) ?>
                        </span>
                        <?php if ($entry['payment_status'] == 'paid' && $entry['payment_date']): ?>
                            <span class="ms-2 text-muted small">(Paid on <?= date('d M Y', strtotime($entry['payment_date'])) ?>)</span>
                        <?php endif; ?>
                        <input type="hidden" name="payment_status" value="<?= $entry['payment_status'] ?>">
                    </div>
                    <small class="text-muted">Payment status can only be changed by admin.</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div id="onlineBankNotice" class="alert alert-warning" hidden>
                <p class="mb-1 fw-semibold">Bank account details are required before creating an online class.</p>
                <p class="mb-2">Online classes require a completed bank account profile so that your class earnings can be settled.</p>
                <a class="btn btn-sm btn-primary" id="onlineBankLink" href="<?= htmlspecialchars($bankDetailsUrl, ENT_QUOTES, 'UTF-8') ?>">Add Bank Details</a>
            </div>
            <div id="onlineBankReady" class="alert alert-success" hidden>
                <p class="mb-2 fw-semibold">Bank details completed. You can create online classes.</p>
                <div data-bank-lines class="bank-saved-lines"></div>
                <a class="btn btn-sm btn-outline-success mt-2" id="onlineBankEdit" href="<?= htmlspecialchars($bankDetailsUrl, ENT_QUOTES, 'UTF-8') ?>">Update bank details</a>
            </div>
            <?php class_fee_summary_assets($pdo); ?>
            <?php class_fee_summary_card('onlineFeeSummary', $entry); ?>
        </div>
    </div>

    <!-- ===== REPEAT WEEKLY SECTION (NEW) ===== -->
    <div class="row">
        <div class="col-md-12">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="repeat_weekly" name="repeat_weekly" 
                           <?= $has_recurring ? 'checked' : '' ?> 
                           onchange="toggleRepeat()" <?= $is_deleted ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="repeat_weekly">
                        <i class="bi bi-arrow-repeat"></i> Repeat weekly
                    </label>
                    <?php if ($has_recurring): ?>
                        <span class="badge bg-info ms-2">Currently repeating until <?= date('d M Y', strtotime($recurring_end_date)) ?></span>
                    <?php endif; ?>
                </div>
                <div id="repeat_until_container" style="display: <?= ($has_recurring) ? 'block' : 'none' ?>; margin-top:10px;">
                    <label for="repeat_until" class="form-label">Repeat until (end date) <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="repeat_until" name="repeat_until" 
                           value="<?= $recurring_end_date ?: '' ?>" <?= $is_deleted ? 'disabled' : '' ?>>
                    <small class="text-muted">Future weeks will be generated automatically when the week arrives.</small>
                </div>
            </div>
        </div>
    </div>

    <div id="conflictAlert" class="alert alert-danger" style="display: none;"></div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary" <?= $is_deleted ? 'disabled' : '' ?>><i class="bi bi-save"></i> Update Entry</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>

        <?php if ($can_delete && !$is_deleted): ?>
        <button type="submit" name="delete_entry" value="1" class="btn btn-danger">
            <i class="bi bi-trash"></i> Delete This Entry
        </button>
        <?php endif; ?>
    </div>
</form></div>

<script>
function toggleRepeat() {
    const checkbox = document.getElementById('repeat_weekly');
    const container = document.getElementById('repeat_until_container');
    container.style.display = checkbox.checked ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const conflictAlert = document.getElementById('conflictAlert');
    const inputs = ['teacher_id', 'room_id', 'class_id', 'date', 'start_time', 'end_time'];
    let timeout;
    function checkConflicts() {
        const teacher = document.getElementById('teacher_id').value;
        const room = document.getElementById('room_id').value;
        const classId = document.getElementById('class_id').value;
        const date = document.getElementById('date').value;
        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        if (!teacher || !room || !classId || !date || !start || !end) { conflictAlert.style.display = 'none'; return; }
        if (start >= end) { conflictAlert.textContent = 'Start time must be before end time.'; conflictAlert.style.display = 'block'; return; }
        const exclude = <?= $id ?>;
        fetch(`edit.php?check=1&teacher_id=${teacher}&room_id=${room}&class_id=${classId}&date=${date}&start=${start}&end=${end}&exclude=${exclude}`)
            .then(r => r.json())
            .then(data => {
                if (data.conflict) {
                    conflictAlert.textContent = '⚠️ ' + data.message;
                    conflictAlert.style.display = 'block';
                } else {
                    conflictAlert.style.display = 'none';
                }
            });
    }
    inputs.forEach(id => {
        document.getElementById(id).addEventListener('change', function() { clearTimeout(timeout); timeout = setTimeout(checkConflicts, 300); });
        document.getElementById(id).addEventListener('input', function() { clearTimeout(timeout); timeout = setTimeout(checkConflicts, 300); });
    });
});
if (window.ClassSessionFee) {
    ClassSessionFee.mount({
        feeId: 'class_fee_per_student',
        modeId: 'delivery_mode',
        cardId: 'onlineFeeSummary',
        teacherId: 'teacher_id',
        bankMap: <?= json_encode($bankReady, JSON_UNESCAPED_SLASHES) ?>,
        bankNoticeId: 'onlineBankNotice',
        bankReadyId: 'onlineBankReady',
        bankLinkId: 'onlineBankLink',
        bankEditId: 'onlineBankEdit',
        bankUrl: <?= json_encode($bankDetailsUrl, JSON_UNESCAPED_SLASHES) ?>,
        bankUrlNeedsTeacher: <?= $is_admin ? 'true' : 'false' ?>,
        alreadyOnline: <?= $entryAlreadyOnline ? 'true' : 'false' ?>
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
