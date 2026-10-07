<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        if (isset($_POST['create_user'])) {
            $username = validate_input($_POST['username'] ?? '', 'string');
            $password = $_POST['password'] ?? '';
            $role = strtolower(trim((string)validate_input($_POST['role'] ?? 'teacher', 'string')));
            if (!in_array($role, ['admin', 'teacher'], true)) {
                $role = 'teacher';
            }
            $teacher_id = (int)($_POST['teacher_id'] ?? 0);
            
            if (empty($username) || empty($password)) {
                $error = 'Username and password are required.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                // Check duplicate username
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->rowCount() > 0) {
                    $error = 'Username already exists.';
                } else {
                    try {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, teacher_id, deleted_at) VALUES (?, ?, ?, ?, NULL)");
                        $stmt->execute([$username, $hash, $role, $teacher_id]);
                        log_audit($pdo, 'create_user', 'users', $pdo->lastInsertId(), null, ['username' => $username, 'role' => $role]);
                        $success = "User '$username' created successfully.";
                    } catch (PDOException $e) {
                        $error = 'Error: ' . $e->getMessage();
                    }
                }
            }
        } elseif (isset($_POST['reset_password'])) {
            $user_id = (int)$_POST['user_id'];
            $new_password = $_POST['new_password'] ?? '';
            if (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                try {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, $user_id]);
                    log_audit($pdo, 'reset_password', 'users', $user_id);
                    $success = 'Password reset successfully.';
                } catch (PDOException $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        } elseif (isset($_POST['toggle_user'])) {
            $user_id = (int)$_POST['user_id'];
            $stmt = $pdo->prepare("SELECT deleted_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if ($user) {
                $new_deleted = $user['deleted_at'] ? NULL : date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = ? WHERE id = ?");
                $stmt->execute([$new_deleted, $user_id]);
                log_audit($pdo, $new_deleted ? 'disable_user' : 'enable_user', 'users', $user_id);
                $success = $new_deleted ? 'User disabled.' : 'User enabled.';
            }
        }
    }
}

// Get all users with teacher names
$sql = "SELECT u.*, t.name as teacher_name 
        FROM users u
        LEFT JOIN teachers t ON u.teacher_id = t.id
        ORDER BY u.id";
$users = $pdo->query($sql)->fetchAll();

// Get all teachers for dropdown
$teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h1><i class="bi bi-people"></i> User Management</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <h4>Create New User</h4>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                <small class="text-muted">Minimum 6 characters.</small>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="teacher_id" class="form-label">Linked Teacher (optional)</label>
                <select class="form-select" id="teacher_id" name="teacher_id">
                    <option value="0">None</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="create_user" class="btn btn-success"><i class="bi bi-plus-circle"></i> Create User</button>
        </form>
    </div>
    <div class="col-md-8">
        <h4>Existing Users</h4>
        <div class="mb-3">
            <input type="search" class="form-control" placeholder="Search username, role, or teacher..." data-table-search="#userTable" autocomplete="off">
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="userTable">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Teacher</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr data-search="<?= htmlspecialchars(strtolower($u['id'].' '.$u['username'].' '.$u['role'].' '.($u['teacher_name'] ?? '').' '.($u['deleted_at'] ? 'disabled' : 'active'))) ?>">
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><span class="badge <?= $u['role'] == 'admin' ? 'bg-danger' : 'bg-info' ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><?= $u['teacher_name'] ? htmlspecialchars($u['teacher_name']) : '-' ?></td>
                            <td>
                                <span class="badge <?= $u['deleted_at'] ? 'bg-secondary' : 'bg-success' ?>">
                                    <?= $u['deleted_at'] ? 'Disabled' : 'Active' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['username'] !== 'admin'): ?>
                                    <form method="POST" style="display:inline;" class="d-inline-block mb-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="toggle_user" value="1">
                                        <button type="submit" class="btn btn-sm <?= $u['deleted_at'] ? 'btn-outline-success' : 'btn-outline-secondary' ?>" onclick="return confirm('Toggle user status?')">
                                            <?= $u['deleted_at'] ? 'Enable' : 'Disable' ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#resetModal<?= $u['id'] ?>">
                                        Reset Password
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">Cannot modify admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- Reset Password Modal -->
                        <div class="modal fade" id="resetModal<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reset Password for <?= htmlspecialchars($u['username']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="mb-3">
                                                <label for="new_password_<?= $u['id'] ?>" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password_<?= $u['id'] ?>" name="new_password" required minlength="6">
                                                <small class="text-muted">Minimum 6 characters.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>