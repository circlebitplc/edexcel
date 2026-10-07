<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\GoogleOAuthService;

if (is_logged_in() && ($_SESSION['role'] ?? '') === 'student') {
    if (isset($pdo) && $pdo instanceof PDO) {
        enforce_active_session($pdo);
    }
    if (is_logged_in() && ($_SESSION['role'] ?? '') === 'student') {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
        exit;
    }
}

$error = trim((string)($_GET['error'] ?? ''));
$loginNotice = function_exists('student_login_notice_message') ? student_login_notice_message() : '';
$googleReady = false;
try {
    $googleReady = GoogleOAuthService::isPortalEnabled(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
} catch (Throwable $e) {
    $googleReady = false;
}

// Legacy phone/OTP/password login removed — Google only.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . rtrim((string)BASE_URL, '/') . '/portal/login.php?role=student&error=' . rawurlencode('Please sign in with Google.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Student Login - Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/student-auth.css">
    <?php
    require_once __DIR__ . '/../includes/ui_feedback.php';
    ui_feedback_css();
    ?>
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    <style>
        .google-btn { background:#fff; color:#3c4043; border:1px solid #dadce0; font-weight:700; }
        .google-btn:hover { background:#f8f9fa; color:#202124; border-color:#dadce0; }
    </style>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-icon">🎓</div>
        <div class="eyebrow">Edexcel College</div>
        <h1>Student Portal</h1>
        <p class="intro">Sign in with your Google account. Phone number, password, and SMS/OTP login are no longer available.</p>

        <?php if ($loginNotice): ?>
            <div class="alert alert-warning"><?= e($loginNotice) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="d-flex flex-column gap-2 mb-3">
            <button type="button" id="btnStudentFaceLogin" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm" style="font-weight:600;">
                <i class="bi bi-person-bounding-box fs-5"></i>
                <span>Sign in with Face ID</span>
            </button>
            <?php if (!$googleReady): ?>
                <div class="alert alert-warning small mb-0">Google sign-in is temporarily unavailable. Please contact the college office.</div>
            <?php else: ?>
                <a class="btn google-btn btn-lg w-100" href="<?= e(rtrim((string)BASE_URL, '/') . '/auth/google/start.php?intent=student') ?>">
                    Continue with Google
                </a>
            <?php endif; ?>
        </div>

        <div class="auth-links">
            <a href="<?= BASE_URL ?>portal/login.php?role=parent">Parent portal</a><br>
            <a href="<?= BASE_URL ?>index.php">Back to homepage</a>
        </div>
    </section>
</main>

<!-- Face Login Modal -->
<div class="modal fade" id="faceLoginModal" tabindex="-1" aria-labelledby="faceLoginModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="faceLoginModalLabel">
                    <i class="bi bi-person-bounding-box text-primary me-2"></i>Face ID Sign-In
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger d-none js-face-alert small py-2 text-start" data-ui-keep="1"></div>

                <div class="position-relative mx-auto rounded-4 overflow-hidden shadow-sm" style="width: 100%; max-width: 420px; aspect-ratio: 4/3; background: #000;">
                    <video class="js-face-video w-100 h-100 object-fit-cover" playsinline autoplay muted></video>
                    <canvas class="js-face-canvas position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none;"></canvas>
                    <div class="position-absolute top-50 start-50 translate-middle" style="width: 200px; height: 260px; border: 2px dashed rgba(255,255,255,0.35); border-radius: 50%; pointer-events:none;"></div>
                </div>

                <div class="mt-3">
                    <div class="js-face-status text-muted small mb-2">Position your face inside the frame.</div>
                    <div class="js-face-step-indicator d-flex justify-content-center gap-2 mb-2"></div>
                    <div class="js-face-spinner spinner-border text-primary spinner-border-sm mt-1" role="status"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<?php ui_feedback_js(); ?>
<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/vendor/face-api/face-api.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-biometrics.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-face-ui.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('btnStudentFaceLogin');
    if (btn) {
        btn.addEventListener('click', function() {
            AdminFaceUI.startLoginModal();
        });
    }
});
</script>
</body>
</html>
