<?php
// profile.php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/security.php';
require_staff();

$user = get_logged_in_user($pdo);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            // Hash new password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user['id']]);
            $success = 'Password changed successfully!';
        }
    }
    }
}

$username = htmlspecialchars($user['username']);
$role = $user['role'];
include 'includes/header.php';
?>
<h1><i class="bi bi-key"></i> Change Password</h1>

<?php if (function_exists('app_theme_render_settings_section')) { app_theme_render_settings_section(); } ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" class="col-md-6"><?= csrf_field() ?>
    <div class="mb-3">
        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" id="current_password" name="current_password" required>
    </div>
    <div class="mb-3">
        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
        <small class="text-muted">Minimum 6 characters.</small>
    </div>
    <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Change Password</button>
    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>