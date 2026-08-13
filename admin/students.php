<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
        error_log('CSRF validation failed.');
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
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->rowCount() > 0) {
                    $error = 'Username already exists.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, deleted_at) VALUES (?, ?, 'student', NULL)");
                        $stmt->execute([$username, $hash]);
                        $student_id = $pdo->lastInsertId();

                        $stmt = $pdo->prepare("INSERT INTO student_enrollments (student_id, class_id) VALUES (?, ?)");
                        foreach ($class_ids as $cid) {
                            $stmt->execute([$student_id, $cid]);
                        }
                        $pdo->commit();
                        log_audit($pdo, 'create_student', 'users', $student_id, null, ['username' => $username, 'classes' => $class_ids]);
                        $success = "Student '$username' created and enrolled.";
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'delete_student') {
            $student_id = (int)$_POST['student_id'];
            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND role = 'student'");
            $stmt->execute([$student_id]);
            if ($stmt->rowCount()) {
                log_audit($pdo, 'delete_student', 'users', $student_id);
                $success = 'Student deleted.';
            } else {
                $error = 'Student not found.';
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

// Fetch all students
try {
    $students = $pdo->query("SELECT u.*, GROUP_CONCAT(c.name SEPARATOR ', ') as classes
                             FROM users u
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

    <div class="student-stat-grid">
        <div class="student-stat"><div class="icon"><i class="bi bi-people"></i></div><strong><?= count($students) ?></strong><span>Active Students</span></div>
        <div class="student-stat"><div class="icon"><i class="bi bi-collection"></i></div><strong><?= count($all_classes) ?></strong><span>Available Classes</span></div>
        <div class="student-stat"><div class="icon"><i class="bi bi-person-check"></i></div><strong><?= count(array_filter($students, fn($s) => !empty($s['classes']))) ?></strong><span>Enrolled Students</span></div>
        <div class="student-stat"><div class="icon"><i class="bi bi-person-plus"></i></div><strong><?= count($students) ? count($students) : 0 ?></strong><span>Student Accounts</span></div>
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
                            <div class="row g-2" style="max-height:360px;overflow:auto;">
                            <?php foreach ($all_classes as $c): ?><div class="col-12 col-md-6"><label class="d-flex align-items-center gap-2 p-2 rounded-3 border"><input class="form-check-input m-0" type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>" <?= ($edit_student && in_array($c['id'], $edit_enrollments)) ? 'checked' : '' ?>><span><?= htmlspecialchars($c['name']) ?></span></label></div><?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-check2-circle me-1"></i><?= $edit_student ? 'Update Enrollment' : 'Create Student' ?></button>
                        <?php if ($edit_student): ?><a href="students.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-xl-7">
            <section class="student-panel h-100">
                <div class="student-panel-header"><div><h2>Existing Students</h2><p><?= count($students) ?> active student account<?= count($students)==1?'':'s' ?></p></div></div>
                <div class="student-panel-body">
                    <?php if (empty($students)): ?><div class="student-empty"><div class="empty-icon"><i class="bi bi-person-x"></i></div><h3>No students found</h3><p>Create the first student account using the form.</p></div>
                    <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Student</th><th>Classes</th><th class="text-end">Actions</th></tr></thead><tbody>
                    <?php foreach ($students as $s): ?><tr><td><div class="fw-bold"><?= htmlspecialchars($s['username']) ?></div><small class="text-muted">Student account</small></td><td><?= htmlspecialchars($s['classes'] ?: 'Not enrolled') ?></td><td class="text-end text-nowrap"><a href="students.php?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-pencil"></i></a><form method="POST" class="d-inline" onsubmit="return confirm('Delete this student?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_student"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){const b=document.getElementById('toggleAllBtn'),c=[...document.querySelectorAll('input[name="class_ids[]"]')];if(!b)return;b.addEventListener('click',()=>{const select=c.some(x=>!x.checked);c.forEach(x=>x.checked=select);b.innerHTML=select?'<i class="bi bi-check-all"></i> Deselect all':'<i class="bi bi-check-all"></i> Select all';});});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
