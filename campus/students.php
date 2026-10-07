<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$classes = campus_staff_classes($pdo, $isAdmin, $teacherId);
$classId = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$allowed = array_map(static fn(array $r): int => (int)$r['id'], $classes);
if ($classId > 0 && !in_array($classId, $allowed, true)) {
    $classId = 0;
}

$error = '';
$success = '';
$newLogin = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }
        $classId = (int)($_POST['class_id'] ?? 0);
        if (!campus_staff_can_access_class($pdo, $classId, $isAdmin, $teacherId)) {
            throw new RuntimeException('Choose one of your classes.');
        }
        $action = (string)($_POST['action'] ?? 'add');
        if ($action === 'remove_student') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            if ($studentId < 1) {
                throw new RuntimeException('Choose a student to remove.');
            }
            $actorName = 'Teacher';
            if ($teacherId > 0) {
                $tn = $pdo->prepare('SELECT name FROM teachers WHERE id = ? LIMIT 1');
                $tn->execute([$teacherId]);
                $actorName = trim((string)($tn->fetchColumn() ?: 'Teacher'));
            } elseif ($isAdmin) {
                $actorName = 'Admin';
            }
            $presets = campus_teacher_remove_reason_options();
            $preset = trim((string)($_POST['reason_preset'] ?? ''));
            $extra = trim((string)($_POST['reason_extra'] ?? ''));
            if ($preset === 'other') {
                $reason = $extra;
            } elseif (isset($presets[$preset])) {
                $reason = $presets[$preset];
                if ($extra !== '') {
                    $reason .= ' — ' . $extra;
                }
            } else {
                throw new RuntimeException('Choose a reason, or Other and type one.');
            }
            campus_unenroll_from_class(
                $pdo,
                $studentId,
                $classId,
                $reason,
                $isAdmin && $teacherId < 1 ? 'admin' : 'teacher',
                (int)($_SESSION['user_id'] ?? 0),
                $actorName
            );
            $success = 'Student removed from this class. They were notified with your reason.';
        } else {
            $result = campus_teacher_add_student(
                $pdo,
                $classId,
                (string)($_POST['full_name'] ?? ''),
                (string)($_POST['whatsapp'] ?? ''),
                (string)($_POST['parent_name'] ?? ''),
                (string)($_POST['parent_whatsapp'] ?? ''),
                0,
                false,
                (int)($_SESSION['user_id'] ?? 0),
                (int)($_POST['student_id'] ?? 0),
                (string)($_POST['google_email'] ?? '')
            );
            if ($result['created']) {
                $newLogin = $result;
                $success = $result['name'] . ' is registered and enrolled.';
            } elseif ($result['already_enrolled']) {
                $success = $result['name'] . ' is already enrolled in this class.';
            } else {
                $success = $result['name'] . ' was added to this class.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$roster = [];
$leaveLog = [];
$rosterLastSeen = [];
if ($classId > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name, u.username, sp.parent_name, sp.parent_whatsapp
        FROM student_enrollments se
        JOIN users u ON u.id = se.student_id AND u.deleted_at IS NULL
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE se.class_id = ?
        ORDER BY name
    ");
    $stmt->execute([$classId]);
    $roster = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $leaveLog = campus_class_leave_log($pdo, $classId, 15);
    $rosterLastSeen = [];
    try {
        require_once __DIR__ . '/../student/device_helpers.php';
        $deviceSvc = student_devices($pdo);
        if ($deviceSvc) {
            $rosterLastSeen = $deviceSvc->lastSeenMap(array_map(static fn(array $r): int => (int)$r['id'], $roster));
        }
    } catch (Throwable $e) {
        $rosterLastSeen = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-3"><i class="bi bi-people"></i> My students</h1>
    <p class="text-muted">Enrolled students for classes you teach. Register a walk-in student into the selected class.</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if (!empty($newLogin['plain_password'])): ?>
        <div class="alert alert-warning">
            <strong>Give these login details to the student now</strong> (also sent on WhatsApp if sending worked).
            <div class="mt-2">
                Login: <a href="<?= e(student_login_url()) ?>"><?= e(student_login_url()) ?></a><br>
                Username: <code><?= e('+' . (string)$newLogin['phone']) ?></code><br>
                Temporary password: <code><?= e((string)$newLogin['plain_password']) ?></code>
            </div>
            <p class="small mb-0 mt-2">If they lose it, they can open Student Login → Forgot password and use a WhatsApp OTP.</p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <h5 class="mb-3">Class</h5>
                <?php if ($classes): ?>
                <div class="class-picker">
                    <?php foreach ($classes as $c): ?>
                        <a class="class-picker-item <?= (int)$c['id'] === $classId ? 'is-active' : '' ?>" href="?class_id=<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No classes assigned.</p>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <?php if ($classId < 1): ?>
                    <p class="text-muted mb-0">Select a class.</p>
                <?php else: ?>
                    <?php if (!$roster): ?>
                        <p class="text-muted">No enrolled students yet.</p>
                    <?php else: ?>
                    <table class="table"><thead><tr><th>Student</th><th>Login</th><th>Last sign-in</th><th>Parent</th><th></th></tr></thead><tbody>
                    <?php foreach ($roster as $s): ?>
                        <?php
                        $seen = $rosterLastSeen[(int)$s['id']] ?? null;
                        $seenWhen = is_array($seen) && !empty($seen['last_login_at'])
                            ? date('d M, g:i A', strtotime((string)$seen['last_login_at']))
                            : 'Never';
                        $seenLabel = is_array($seen) ? trim((string)($seen['label'] ?? '')) : '';
                        ?>
                        <tr>
                            <td><?= e((string)$s['name']) ?></td>
                            <td><?= e((string)$s['username']) ?></td>
                            <td>
                                <?= e($seenWhen) ?>
                                <?php if ($seenLabel !== ''): ?>
                                    <div class="small text-muted"><?= e($seenLabel) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string)($s['parent_name'] ?? '')) ?> <?= e((string)($s['parent_whatsapp'] ?? '')) ?></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger js-remove-student"
                                    data-student-id="<?= (int)$s['id'] ?>"
                                    data-student-name="<?= e((string)$s['name']) ?>"
                                >Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-primary" href="<?= e(BASE_URL) ?>campus/progress.php?class_id=<?= $classId ?>">Enter marks</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/attendance.php">Attendance</a>
                    <?php if ($leaveLog): ?>
                        <h6 class="fw-bold mt-4">Recent unenrolls</h6>
                        <p class="small text-muted">Reasons left by students, teachers, or admin for this class.</p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>When</th><th>Student</th><th>By</th><th>Reason</th></tr></thead>
                                <tbody>
                                <?php foreach ($leaveLog as $row): ?>
                                    <tr>
                                        <td><?= e(date('d M Y H:i', strtotime((string)$row['created_at']))) ?></td>
                                        <td><?= e((string)$row['student_name']) ?></td>
                                        <td><?= e((string)($row['actor_name'] ?: $row['initiated_by'])) ?></td>
                                        <td><?= e((string)$row['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <hr class="my-4">
                    <h6 class="fw-bold"><i class="bi bi-person-plus me-1"></i> Register and add to this class</h6>
                    <p class="small text-muted">Search an existing student by Google login email, name, or phone number to add them, or enter details below to register a new student account.</p>
                    <form method="post" class="row g-3 js-walkin-form" data-lookup-url="<?= e(BASE_URL) ?>ajax/lookup_student.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="class_id" value="<?= $classId ?>">
                        <input type="hidden" name="student_id" class="js-walkin-student-id" value="">
                        <input type="hidden" name="google_email" class="js-walkin-google-email-val" value="">

                        <!-- Search existing student by Google email, name, or phone -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Search existing student <span class="text-muted fw-normal">(by Google email, name, or phone)</span></label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0 js-student-search-input"
                                           placeholder="Type Google email, name, or mobile number to search…"
                                           autocomplete="off">
                                    <button class="btn btn-outline-secondary js-student-search-clear d-none" type="button" title="Clear search">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="dropdown-menu w-100 shadow-lg js-student-search-results p-0 mt-1"
                                     style="max-height: 280px; overflow-y: auto; display: none; z-index: 1050;">
                                </div>
                            </div>
                        </div>

                        <!-- Selected Student Confirmation Banner -->
                        <div class="col-12 js-selected-student-banner d-none">
                            <div class="alert alert-primary d-flex align-items-center justify-content-between py-2 px-3 mb-0">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <i class="bi bi-person-check-fill fs-5 text-primary flex-shrink-0"></i>
                                    <div class="overflow-hidden">
                                        <strong class="js-selected-student-name"></strong>
                                        <div class="small js-selected-student-meta text-muted text-truncate"></div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill js-walkin-clear-selection flex-shrink-0 ms-2">Change</button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Student WhatsApp</label>
                            <input class="form-control js-walkin-whatsapp" name="whatsapp" required inputmode="tel" placeholder="077XXXXXXX" autocomplete="tel">
                            <div class="form-text js-walkin-status"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full name</label>
                            <input class="form-control js-walkin-name" name="full_name" placeholder="Student name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent name <span class="text-muted">(optional)</span></label>
                            <input class="form-control js-walkin-parent-name" name="parent_name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent WhatsApp <span class="text-muted">(optional)</span></label>
                            <input class="form-control js-walkin-parent-whatsapp" name="parent_whatsapp" inputmode="tel" placeholder="077XXXXXXX">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success rounded-pill"><i class="bi bi-person-plus me-1"></i> <span class="js-walkin-submit-label">Register and add to class</span></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php if ($classId > 0): ?>
<div class="modal fade" id="removeStudentModal" tabindex="-1" aria-labelledby="removeStudentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_student">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="student_id" id="removeStudentId" value="">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="removeStudentTitle">Remove student</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="removeStudentText">This student will be unenrolled and notified.</p>
                    <label class="form-label">Reason</label>
                    <p class="small text-muted">The student, other teachers, and admin will see this.</p>
                    <?php foreach (campus_teacher_remove_reason_options() as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input js-remove-reason" type="radio" name="reason_preset" id="removeReason_<?= e($key) ?>" value="<?= e($key) ?>" required>
                            <label class="form-check-label" for="removeReason_<?= e($key) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-check">
                        <input class="form-check-input js-remove-reason" type="radio" name="reason_preset" id="removeReason_other" value="other" required>
                        <label class="form-check-label" for="removeReason_other">Other</label>
                    </div>
                    <div class="mt-3" id="removeReasonExtraWrap">
                        <label class="form-label" for="removeStudentReasonExtra">Extra note <span class="text-muted" id="removeReasonExtraHint">(optional)</span></label>
                        <textarea class="form-control" id="removeStudentReasonExtra" name="reason_extra" rows="2" maxlength="400" placeholder="Add a short note if needed"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove and notify</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('removeStudentModal');
    if (!modalEl || !window.bootstrap) {
        return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const idInput = document.getElementById('removeStudentId');
    const textEl = document.getElementById('removeStudentText');
    const extra = document.getElementById('removeStudentReasonExtra');
    const extraHint = document.getElementById('removeReasonExtraHint');
    function syncOther() {
        const other = document.getElementById('removeReason_other');
        const isOther = !!(other && other.checked);
        if (extra) {
            extra.required = isOther;
            extra.minLength = isOther ? 5 : 0;
            extra.placeholder = isOther ? 'Type the reason' : 'Add a short note if needed';
        }
        if (extraHint) {
            extraHint.textContent = isOther ? '(required)' : '(optional)';
        }
    }
    document.querySelectorAll('.js-remove-reason').forEach(function (el) {
        el.addEventListener('change', syncOther);
    });
    document.querySelectorAll('.js-remove-student').forEach(function (btn) {
        btn.addEventListener('click', function () {
            idInput.value = btn.getAttribute('data-student-id') || '';
            const name = btn.getAttribute('data-student-name') || 'this student';
            textEl.textContent = 'Remove ' + name + ' from this class? They will be notified with your reason.';
            document.querySelectorAll('.js-remove-reason').forEach(function (el) { el.checked = false; });
            if (extra) {
                extra.value = '';
            }
            syncOther();
            modal.show();
        });
    });
});
</script>
<?php
$walkinLookupJs = __DIR__ . '/../assets/js/campus-student-lookup.js';
if (is_file($walkinLookupJs)):
?>
<script src="<?= e(BASE_URL) ?>assets/js/campus-student-lookup.js?v=<?= filemtime($walkinLookupJs) ?>"></script>
<?php endif; ?>
<?php endif; ?>
