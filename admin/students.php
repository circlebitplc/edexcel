<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';
require_admin();
ensure_campus_schema($pdo);
$leaveLog = campus_class_leave_log($pdo, null, 40);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (!empty($_POST['ajax']));

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
        error_log('CSRF validation failed.');
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => $error]);
            exit;
        }
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_student') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $class_ids = $_POST['class_ids'] ?? [];

            if (empty($username) || empty($password)) {
                $error = 'Username and password are required.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif (empty($class_ids)) {
                $error = 'Please select at least one class.';
            } else {
                $phone = function_exists('campus_lk_whatsapp') ? campus_lk_whatsapp($username) : '';
                $digits = preg_replace('/\D+/', '', $username) ?? '';
                if ($phone === '' && preg_match('/^0?7\d{8}$/', $digits)) {
                    $error = 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.';
                } else {
                    if ($phone !== '') {
                        $username = $phone;
                    }
                    $exists = false;
                    if ($phone !== '' && function_exists('campus_find_student_by_whatsapp')) {
                        $exists = campus_find_student_by_whatsapp($pdo, $phone) !== null;
                    }
                    if (!$exists) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $check->execute([$username]);
                        $exists = $check->rowCount() > 0;
                    }
                    if ($exists) {
                        $error = 'Username already exists.';
                    } else {
                try {
                    $pdo->beginTransaction();
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, deleted_at) VALUES (?, ?, 'student', NULL)");
                    $stmt->execute([$username, $hash]);
                    $student_id = $pdo->lastInsertId();

                    try {
                        $pdo->prepare("
                            INSERT INTO student_profiles (user_id, full_name, whatsapp_number)
                            VALUES (?, ?, ?)
                        ")->execute([(int)$student_id, $username, $phone !== '' ? $phone : $username]);
                    } catch (Throwable $e) {
                        error_log('admin create student profile: ' . $e->getMessage());
                    }

                    $stmt = $pdo->prepare("INSERT INTO student_enrollments (student_id, class_id) VALUES (?, ?)");
                        foreach ($class_ids as $cid) {
                            $stmt->execute([$student_id, $cid]);
                        }
                        $pdo->commit();
                        log_audit($pdo, 'create_student', 'users', $student_id, null, ['username' => $username, 'classes' => $class_ids]);
                        if (function_exists('campus_notify_new_student_registration')) {
                            campus_notify_new_student_registration(
                                $pdo,
                                (int)$student_id,
                                '',
                                (string)$username,
                                'admin'
                            );
                        }
                        $success = "Student '$username' created and enrolled.";
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = 'Database error: ' . $e->getMessage();
                    }
                    }
                }
            }
        } elseif ($action === 'delete_student') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            if ($student_id <= 0) {
                $error = 'Student ID is required.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND role = 'student'");
                $stmt->execute([$student_id]);
                if ($stmt->rowCount()) {
                    log_audit($pdo, 'delete_student', 'users', $student_id);
                    $success = 'Student deleted.';
                } else {
                    $error = 'Student not found or already deleted.';
                }
            }
            if (!empty($is_ajax)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => empty($error),
                    'error' => $error ?: null,
                    'message' => $success ?: null,
                    'student_id' => $student_id
                ]);
                exit;
            }
        } elseif ($action === 'reset_devices') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            if ($student_id < 1) {
                $error = 'Student ID is required.';
            } else {
                require_once __DIR__ . '/../student/device_helpers.php';
                $svc = student_devices($pdo);
                if ($svc) {
                    $svc->revokeAll($student_id);
                    log_audit($pdo, 'reset_student_devices', 'student_devices', $student_id);
                    $success = 'All registered devices were cleared. The student must confirm a new device with an SMS code.';
                } else {
                    $error = 'Could not reset devices right now.';
                }
            }
            if (!empty($is_ajax)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => empty($error),
                    'error' => $error ?: null,
                    'message' => $success ?: null,
                    'student_id' => $student_id
                ]);
                exit;
            }
        } elseif ($action === 'update_enrollment') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $class_ids = $_POST['class_ids'] ?? [];

            if (!$student_id) {
                $error = 'Student ID is required.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("DELETE FROM student_enrollments WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    if (!empty($class_ids)) {
                        $stmt = $pdo->prepare("INSERT INTO student_enrollments (student_id, class_id) VALUES (?, ?)");
                        foreach ($class_ids as $cid) {
                            $stmt->execute([$student_id, $cid]);
                        }
                    }
                    $pdo->commit();
                    log_audit($pdo, 'update_enrollment', 'student_enrollments', $student_id, null, ['classes' => $class_ids]);
                    $success = 'Enrollment updated successfully.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Error updating enrollment: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Invalid action: ' . htmlspecialchars($action);
        }
    }
}

if (empty($error) && empty($success) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'No action performed. Check form submission.';
}
if (!empty($is_ajax)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => empty($error),
        'error' => $error ?: null,
        'message' => $success ?: null
    ]);
    exit;
}

// Fetch all students
try {
    $students = $pdo->query("SELECT u.*,
                                    COALESCE(NULLIF(TRIM(sp.full_name), ''), '') AS full_name,
                                    COALESCE(NULLIF(TRIM(u.google_email), ''), '') AS google_login_email,
                                    COALESCE(NULLIF(TRIM(sp.email), ''), '') AS profile_email,
                                    GROUP_CONCAT(c.name SEPARATOR ', ') as classes
                             FROM users u
                             LEFT JOIN student_profiles sp ON sp.user_id = u.id
                             LEFT JOIN student_enrollments se ON u.id = se.student_id
                             LEFT JOIN student_classes c ON se.class_id = c.id
                             WHERE u.role = 'student' AND u.deleted_at IS NULL
                             GROUP BY u.id
                             ORDER BY u.username")->fetchAll();
} catch (PDOException $e) {
    $students = [];
    $error = 'Error fetching students: ' . $e->getMessage();
}

// Fetch all classes for dropdown
try {
    $all_classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $all_classes = [];
    $error = 'Error fetching classes: ' . $e->getMessage();
}

// Get enrollments for editing
$edit_student = null;
$edit_enrollments = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$edit_id]);
    $edit_student = $stmt->fetch();
    if ($edit_student) {
        $stmt = $pdo->prepare("SELECT class_id FROM student_enrollments WHERE student_id = ?");
        $stmt->execute([$edit_id]);
        $edit_enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="student-dashboard-page admin-students-page">
    <section class="student-page-hero">
        <div><p class="eyebrow">Administration</p><h1><i class="bi bi-people me-2"></i>Student Management</h1><p>Create accounts, manage enrolments and keep class access up to date.</p></div>
        <div class="hero-icon"><i class="bi bi-mortarboard"></i></div>
    </section>

    <?php if ($success): ?><div class="alert alert-success rounded-4"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger rounded-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <style>
    .admin-students-page tr.is-deleting {
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
        opacity: 0 !important;
        transform: translateX(30px) !important;
        background-color: rgba(239, 68, 68, 0.12) !important;
    }
    .admin-students-page .table td {
        transition: background-color 0.2s ease;
    }
    #studentToastContainer {
        z-index: 99999;
    }
    #studentToastContainer .toast {
        min-width: 280px;
        border-radius: 12px;
        backdrop-filter: blur(8px);
    }
    </style>

    <div class="student-stat-grid">
        <div class="student-stat"><div class="icon"><i class="bi bi-people"></i></div><strong id="statActiveStudents"><?= count($students) ?></strong><span>Active Students</span></div>
        <div class="student-stat"><div class="icon"><i class="bi bi-collection"></i></div><strong><?= count($all_classes) ?></strong><span>Available Classes</span></div>
        <div class="student-stat"><div class="icon"><i class="bi bi-person-check"></i></div><strong id="statEnrolledStudents"><?= count(array_filter($students, fn($s) => !empty($s['classes']))) ?></strong><span>Enrolled Students</span></div>
        <div class="student-stat"><div class="icon"><i class="bi bi-person-plus"></i></div><strong id="statStudentAccounts"><?= count($students) ? count($students) : 0 ?></strong><span>Student Accounts</span></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <section class="student-panel h-100">
                <div class="student-panel-header"><div><h2><?= $edit_student ? 'Edit Enrollment' : 'Add New Student' ?></h2><p><?= $edit_student ? 'Update this student’s class access.' : 'Create a login and assign one or more classes.' ?></p></div></div>
                <div class="student-panel-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $edit_student ? 'update_enrollment' : 'create_student' ?>">
                        <?php if ($edit_student): ?>
                            <input type="hidden" name="student_id" value="<?= $edit_student['id'] ?>">
                            <div class="mb-3"><label class="form-label fw-semibold">Student</label><input class="form-control" value="<?= htmlspecialchars($edit_student['username']) ?>" disabled></div>
                        <?php else: ?>
                            <div class="mb-3"><label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label><input type="text" class="form-control" id="username" name="username" required></div>
                            <div class="mb-3"><label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label><input type="password" class="form-control" id="password" name="password" required minlength="6"><small class="text-muted">Minimum 6 characters.</small></div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label fw-semibold mb-0">Enrol in Classes</label><button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="toggleAllBtn"><i class="bi bi-check-all"></i> Select all</button></div>
                            <input type="search" class="form-control form-control-sm mb-2" id="classPickSearch" placeholder="Filter class list..." autocomplete="off">
                            <div class="row g-2" style="max-height:360px;overflow:auto;">
                            <?php foreach ($all_classes as $c): ?><div class="col-12 col-md-6 class-pick-row" data-search="<?= htmlspecialchars(strtolower($c['name'])) ?>"><label class="d-flex align-items-center gap-2 p-2 rounded-3 border"><input class="form-check-input m-0" type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>" <?= ($edit_student && in_array($c['id'], $edit_enrollments)) ? 'checked' : '' ?>><span><?= htmlspecialchars($c['name']) ?></span></label></div><?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-check2-circle me-1"></i><?= $edit_student ? 'Update Enrollment' : 'Create Student' ?></button>
                        <?php if ($edit_student): ?><a href="students.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-xl-7">
            <section class="student-panel h-100" data-live-scope>
                <div class="student-panel-header"><div><h2>Existing Students</h2><p><span data-live-count><?= count($students) ?></span> matching of <span data-live-total><?= count($students) ?></span> account<span data-live-plural><?= count($students)==1?'':'s' ?></span></p></div></div>
                <div class="student-panel-body">
                    <?php if (empty($students)): ?><div class="student-empty"><div class="empty-icon"><i class="bi bi-person-x"></i></div><h3>No students found</h3><p>Create the first student account using the form.</p></div>
                    <?php else: ?>
                    <div class="mb-3">
                        <input type="search" class="form-control" placeholder="Search name, email, username, or class..." data-live-search autocomplete="off">
                    </div>
                    <div class="table-responsive" id="studentsTableWrap"><table class="table align-middle mb-0" id="studentsTable"><thead><tr><th>Student</th><th>Name</th><th>Classes</th><th class="text-end">Actions</th></tr></thead><tbody>
                    <?php foreach ($students as $s): ?>
                    <?php
                        $studentName = trim((string)($s['full_name'] ?? ''));
                        $googleEmail = trim((string)($s['google_login_email'] ?? ($s['google_email'] ?? '')));
                        $profileEmail = trim((string)($s['profile_email'] ?? ''));
                        $displayEmail = $googleEmail !== '' ? $googleEmail : $profileEmail;
                        $searchHaystack = strtolower(trim(
                            ($s['username'] ?? '') . ' ' .
                            $studentName . ' ' .
                            ($s['classes'] ?? '') . ' ' .
                            $googleEmail . ' ' .
                            $profileEmail
                        ));
                    ?>
                    <tr data-live-item data-student-id="<?= $s['id'] ?>" data-has-classes="<?= !empty($s['classes']) ? '1' : '0' ?>" data-search="<?= htmlspecialchars($searchHaystack, ENT_QUOTES, 'UTF-8') ?>"><td><div class="fw-bold student-username"><?= htmlspecialchars($s['username']) ?></div><?php if ($displayEmail !== ''): ?><small class="text-muted text-break"><?= htmlspecialchars($displayEmail) ?></small><?php else: ?><small class="text-muted">Student account</small><?php endif; ?></td><td><?= $studentName !== '' ? htmlspecialchars($studentName) : '<span class="text-muted">—</span>' ?></td><td><?= htmlspecialchars($s['classes'] ?: 'Not enrolled') ?></td><td class="text-end text-nowrap"><a href="students.php?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill" title="Edit enrolment"><i class="bi bi-pencil"></i></a><form method="POST" class="d-inline js-device-reset-form"><?= csrf_field() ?><input type="hidden" name="action" value="reset_devices"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button type="button" class="btn btn-sm btn-outline-secondary rounded-pill js-reset-device-btn" data-student-id="<?= $s['id'] ?>" data-username="<?= htmlspecialchars($s['username']) ?>" title="Reset devices"><i class="bi bi-phone"></i></button></form><form method="POST" class="d-inline js-student-delete-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_student"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button type="button" class="btn btn-sm btn-outline-danger rounded-pill js-student-delete-btn" data-student-id="<?= $s['id'] ?>" data-username="<?= htmlspecialchars($s['username']) ?>" title="Delete student"><i class="bi bi-trash"></i></button></form></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <div class="student-empty d-none" data-live-empty><div class="empty-icon"><i class="bi bi-search"></i></div><h3>No matching students</h3><p>Try a different name, email, or class.</p></div>
                    <div class="student-empty d-none" id="noStudentsEver"><div class="empty-icon"><i class="bi bi-person-x"></i></div><h3>No students found</h3><p>Create the first student account using the form.</p></div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
    <?php if ($leaveLog): ?>
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-1">Class unenrolls</h2>
            <p class="text-muted small">Reasons given when a student left or was removed. Teachers see the same note on My students.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>When</th><th>Student</th><th>Class</th><th>By</th><th>Reason</th></tr></thead>
                    <tbody>
                    <?php foreach ($leaveLog as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d M Y H:i', strtotime((string)$row['created_at']))) ?></td>
                            <td><?= htmlspecialchars((string)$row['student_name']) ?></td>
                            <td><?= htmlspecialchars((string)$row['class_name']) ?></td>
                            <td><?= htmlspecialchars((string)($row['actor_name'] ?: $row['initiated_by'])) ?></td>
                            <td><?= htmlspecialchars((string)$row['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
    const b=document.getElementById('toggleAllBtn'),c=[...document.querySelectorAll('input[name="class_ids[]"]')];
    if(b){b.addEventListener('click',()=>{const select=c.some(x=>!x.checked);c.forEach(x=>x.checked=select);b.innerHTML=select?'<i class="bi bi-check-all"></i> Deselect all':'<i class="bi bi-check-all"></i> Select all';});}
    const pick=document.getElementById('classPickSearch');
    if(pick){
        pick.addEventListener('input',function(){
            const toks=this.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
            document.querySelectorAll('.class-pick-row').forEach(function(row){
                const hay=row.getAttribute('data-search')||'';
                row.style.display=!toks.length||toks.every(t=>hay.indexOf(t)!==-1)?'':'none';
            });
        });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function confirmAction(message, title, tone) {
        if (typeof window.appConfirm === 'function') {
            return window.appConfirm(message, title || 'Please confirm', {
                tone: tone || 'is-warning',
                okLabel: tone === 'is-danger' ? 'Delete' : 'Confirm'
            });
        }
        if (typeof window.customConfirm === 'function') {
            return new Promise(function (resolve) {
                window.customConfirm(title || 'Please confirm', message, resolve, {
                    tone: tone || 'is-warning',
                    okLabel: tone === 'is-danger' ? 'Delete' : 'Confirm'
                });
            });
        }
        return Promise.resolve(window.confirm(message));
    }

    function showStudentToast(message, isError) {
        let container = document.getElementById('studentToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'studentToastContainer';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }
        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center text-white border-0 shadow-lg ' + (isError ? 'bg-danger' : 'bg-dark');
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2 py-2 px-3">
                    <i class="bi ${isError ? 'bi-exclamation-triangle-fill text-warning' : 'bi-check-circle-fill text-success'} fs-5"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        container.appendChild(toastEl);
        if (window.bootstrap && window.bootstrap.Toast) {
            const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        } else {
            toastEl.style.display = 'block';
            setTimeout(() => {
                toastEl.style.transition = 'opacity 0.3s ease';
                toastEl.style.opacity = '0';
                setTimeout(() => toastEl.remove(), 300);
            }, 3500);
        }
    }

    // AJAX Student Deletion via direct click
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-student-delete-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        if (btn.disabled) return;

        const row = btn.closest('tr');
        const form = btn.closest('form');
        const username = btn.getAttribute('data-username') || 'Student';
        const studentId = btn.getAttribute('data-student-id') || form?.querySelector('input[name="student_id"]')?.value;

        const confirmed = await confirmAction(
            `Delete student ${username}?`,
            'Delete Student',
            'is-danger'
        );
        if (!confirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        if (row) {
            row.style.pointerEvents = 'none';
            row.style.opacity = '0.5';
        }

        const formData = form ? new FormData(form) : new FormData();
        formData.set('action', 'delete_student');
        if (studentId) formData.set('student_id', String(studentId));
        formData.set('ajax', '1');
        if (!formData.get('csrf_token')) {
            const tokenInput = document.querySelector('input[name="csrf_token"]');
            if (tokenInput) formData.set('csrf_token', tokenInput.value);
        }

        try {
            const res = await fetch('students.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json().catch(() => ({ ok: false, error: 'Invalid server response' }));
            if (!data.ok) {
                throw new Error(data.error || 'Could not delete student.');
            }

            if (row) {
                row.classList.add('is-deleting');
                setTimeout(() => {
                    const hadClasses = row.getAttribute('data-has-classes') === '1';
                    row.remove();

                    // Refresh live search filter so counts and visibility update instantly
                    const scope = document.querySelector('[data-live-scope]');
                    if (scope) {
                        scope.dispatchEvent(new CustomEvent('live-filter:refresh'));
                    }

                    // Update stats grid
                    const activeEl = document.getElementById('statActiveStudents');
                    if (activeEl) {
                        activeEl.textContent = String(Math.max(0, (parseInt(activeEl.textContent, 10) || 0) - 1));
                    }
                    const accountsEl = document.getElementById('statStudentAccounts');
                    if (accountsEl) {
                        accountsEl.textContent = String(Math.max(0, (parseInt(accountsEl.textContent, 10) || 0) - 1));
                    }
                    if (hadClasses) {
                        const enrolledEl = document.getElementById('statEnrolledStudents');
                        if (enrolledEl) {
                            enrolledEl.textContent = String(Math.max(0, (parseInt(enrolledEl.textContent, 10) || 0) - 1));
                        }
                    }

                    // Check if no students remain in the system
                    const remaining = document.querySelectorAll('tr[data-live-item]');
                    if (!remaining.length) {
                        const tableWrap = document.getElementById('studentsTableWrap');
                        if (tableWrap) tableWrap.style.display = 'none';
                        const searchWrap = document.querySelector('[data-live-search]')?.closest('.mb-3');
                        if (searchWrap) searchWrap.style.display = 'none';
                        const noEver = document.getElementById('noStudentsEver');
                        if (noEver) noEver.classList.remove('d-none');
                    }

                    showStudentToast(`Student <strong>${escapeHtml(username)}</strong> deleted successfully.`);
                }, 350);
            } else {
                showStudentToast(`Student <strong>${escapeHtml(username)}</strong> deleted successfully.`);
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash"></i>';
            if (row) {
                row.style.opacity = '';
                row.style.pointerEvents = '';
                row.classList.remove('is-deleting');
            }
            showStudentToast(err.message || 'Error deleting student.', true);
        }
    });

    // AJAX Reset Devices via direct click
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-reset-device-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        if (btn.disabled) return;

        const form = btn.closest('form');
        const username = btn.getAttribute('data-username') || 'Student';
        const studentId = btn.getAttribute('data-student-id') || form?.querySelector('input[name="student_id"]')?.value;

        const confirmed = await confirmAction(
            `Clear all registered devices for student ${username}? They will need an SMS code on the next sign-in.`,
            'Reset Devices',
            'is-warning'
        );
        if (!confirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        const formData = form ? new FormData(form) : new FormData();
        formData.set('action', 'reset_devices');
        if (studentId) formData.set('student_id', String(studentId));
        formData.set('ajax', '1');
        if (!formData.get('csrf_token')) {
            const tokenInput = document.querySelector('input[name="csrf_token"]');
            if (tokenInput) formData.set('csrf_token', tokenInput.value);
        }

        try {
            const res = await fetch('students.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json().catch(() => ({ ok: false, error: 'Invalid server response' }));
            if (!data.ok) {
                throw new Error(data.error || 'Could not reset devices.');
            }

            btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-phone"></i>';
            }, 2000);

            showStudentToast(data.message || `Devices reset for <strong>${escapeHtml(username)}</strong>.`);
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-phone"></i>';
            showStudentToast(err.message || 'Error resetting devices.', true);
        }
    });

    // Form submission fallback
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form) return;
        if (form.classList.contains('js-student-delete-form')) {
            e.preventDefault();
            const btn = form.querySelector('.js-student-delete-btn');
            if (btn) btn.click();
            return;
        }
        if (form.classList.contains('js-device-reset-form')) {
            e.preventDefault();
            const btn = form.querySelector('.js-reset-device-btn');
            if (btn) btn.click();
            return;
        }
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
