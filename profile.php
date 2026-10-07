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

$faceEnrolled = false;
$faceInfo = null;
try {
    $bioService = new \Edexcel\Services\AdminBiometricService($pdo);
    $faceService = new \Edexcel\Services\AdminFaceService($pdo, $bioService);
    $faceEnrolled = $faceService->isEnrolled((int)$user['id']);
    $faceInfo = $faceService->getEnrollmentStatus((int)$user['id']);
} catch (Throwable $e) {}

$username = htmlspecialchars($user['username']);
$role = $user['role'];
include 'includes/header.php';
?>
<h1><i class="bi bi-person-gear"></i> Account Security &amp; Profile</h1>

<?php if (function_exists('app_theme_render_settings_section')) { app_theme_render_settings_section(); } ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <!-- Face ID Card -->
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
                <div class="fw-bold"><i class="bi bi-person-bounding-box me-2 text-primary"></i>Face ID Sign-In</div>
                <span class="badge rounded-pill <?= !empty($faceEnrolled) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> px-3 py-2">
                    <i class="bi <?= !empty($faceEnrolled) ? 'bi-check-circle-fill' : 'bi-dash-circle' ?> me-1"></i>
                    <?= !empty($faceEnrolled) ? 'Active & Enrolled' : 'Not Enrolled' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Sign in with your face using your webcam. Your facial template is converted into mathematical descriptors and encrypted with AES-256-GCM.
                </p>

                <?php if (!empty($faceEnrolled)): ?>
                    <div class="alert alert-success-subtle border-success-subtle text-success-emphasis small py-2 px-3 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check fs-5"></i>
                        <div>
                            <strong>Face ID is active.</strong> You can sign in using your face from the staff login page.
                            <?php if (!empty($faceInfo['enrolled_at'])): ?>
                                <div class="text-muted" style="font-size:0.75rem;">Enrolled: <?= htmlspecialchars((string)$faceInfo['enrolled_at']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold js-btn-enrol-face" id="btnStaffEnrollFace" data-consent="1">
                        <i class="bi bi-camera-fill me-1"></i> <?= !empty($faceEnrolled) ? 'Re-enroll Face ID' : 'Enroll Face ID' ?>
                    </button>
                    <?php if (!empty($faceEnrolled)): ?>
                        <button type="button" class="btn btn-outline-danger rounded-3 px-3 py-2 fw-semibold" id="btnStaffDisableFace">
                            <i class="bi bi-trash me-1"></i> Remove Face ID
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 fw-bold">
                <i class="bi bi-key me-2 text-primary"></i>Change Password
            </div>
            <div class="card-body p-4">
                <form method="POST"><?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control rounded-3" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control rounded-3" id="new_password" name="new_password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control rounded-3" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 py-2"><i class="bi bi-save me-1"></i> Change Password</button>
                    <a href="dashboard.php" class="btn btn-secondary rounded-3 px-3 py-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Face ID Enrollment Modal -->
<div class="modal fade" id="faceEnrollModal" tabindex="-1" aria-labelledby="faceEnrollModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="faceEnrollModalLabel">
                    <i class="bi bi-person-bounding-box text-primary me-2"></i>Face ID Enrollment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger d-none js-enrol-alert small py-2 text-start" data-ui-keep="1"></div>

                <div class="position-relative mx-auto rounded-4 overflow-hidden shadow-sm" style="width: 100%; max-width: 440px; aspect-ratio: 4/3; background: #000;">
                    <video class="js-enrol-video w-100 h-100 object-fit-cover" playsinline autoplay muted></video>
                    <canvas class="js-enrol-canvas position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none;"></canvas>
                    <div class="position-absolute top-50 start-50 translate-middle" style="width: 210px; height: 270px; border: 2px dashed rgba(255,255,255,0.35); border-radius: 50%; pointer-events:none;"></div>
                </div>

                <div class="js-enrol-steps d-flex justify-content-center flex-wrap mb-2"></div>
                <div class="js-enrol-status text-muted small mb-2">Position your face inside the frame.</div>
                <div class="js-enrol-spinner spinner-border text-primary spinner-border-sm mb-2" role="status"></div>

                <!-- Automatic capture indicator & hold progress pill -->
                <div class="card border-0 bg-light p-2 mb-2 rounded-3 shadow-sm js-enrol-auto-pill text-start" data-ui-keep="1">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 text-start ps-1">
                            <span class="js-enrol-status-dot spinner-grow spinner-grow-sm text-primary" role="status" style="width: 0.85rem; height: 0.85rem;"></span>
                            <div>
                                <div class="small fw-bold text-dark js-enrol-auto-title">Detecting face...</div>
                                <div class="text-muted js-enrol-auto-subtitle" style="font-size: 0.75rem;">Center face inside oval</div>
                            </div>
                        </div>
                        <div class="pe-1 text-end" style="min-width: 95px;">
                            <div class="progress" style="height: 7px; width: 90px; background-color: #e2e8f0; border-radius: 4px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success js-enrol-hold-bar" role="progressbar" style="width: 0%; transition: width 0.15s ease;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm px-4 rounded-pill js-enrol-capture-btn" disabled>
                    <i class="bi bi-camera me-1"></i> Capture Pose
                </button>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/vendor/face-api/face-api.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-biometrics.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-face-ui.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var enrollBtn = document.getElementById('btnStaffEnrollFace');
    if (enrollBtn) {
        enrollBtn.addEventListener('click', function() {
            var csrf = '<?= function_exists('generate_csrf_token') ? generate_csrf_token() : '' ?>';
            if (window.AdminFaceUI) {
                window.AdminFaceUI.startEnrollmentModal(csrf, this);
            }
        });
    }

    var disableBtn = document.getElementById('btnStaffDisableFace');
    if (disableBtn) {
        disableBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to remove Face ID from your account?')) return;
            var csrf = '<?= function_exists('generate_csrf_token') ? generate_csrf_token() : '' ?>';
            this.disabled = true;
            try {
                var res = await fetch('/ajax/admin_biometrics.php?action=face_disable', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ csrf_token: csrf })
                });
                var data = await res.json();
                if (data.ok) {
                    alert('Face ID removed successfully.');
                    window.location.reload();
                } else {
                    alert(data.error || 'Could not remove Face ID.');
                    this.disabled = false;
                }
            } catch (e) {
                alert('Connection error.');
                this.disabled = false;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>