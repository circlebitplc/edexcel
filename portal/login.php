<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\ParentAuthService;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (ParentAuthService::isLoggedIn()) {
    header('Location: /parent/home.php');
    exit;
}
if (function_exists('is_logged_in') && is_logged_in() && (($_SESSION['role'] ?? '') === 'student')) {
    header('Location: /student/dashboard.php');
    exit;
}

$googleReady = GoogleOAuthService::isPortalEnabled(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
$error = trim((string)($_GET['error'] ?? ''));
$notice = trim((string)($_GET['notice'] ?? ''));
$invite = trim((string)($_GET['invite'] ?? ''));
$role = strtolower(trim((string)($_GET['role'] ?? 'student')));
if (!in_array($role, ['student', 'parent'], true)) {
    $role = 'student';
}

$noticeText = '';
if ($notice === 'student_pending') {
    $noticeText = 'Your student account was created and is awaiting college activation. You will be able to sign in once an administrator activates it.';
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$googleStudent = '/auth/google/start.php?intent=student';
$googleParent = '/auth/google/start.php?intent=parent' . ($invite !== '' ? ('&invite=' . rawurlencode($invite)) : '');
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Student &amp; Parent Portal · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <link href="/assets/css/student-auth.css" rel="stylesheet">
    <?php
    require_once __DIR__ . '/../includes/ui_feedback.php';
    ui_feedback_css();
    ?>
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    <style>
        .portal-role-tabs { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:18px 0 8px; }
        .portal-role-tabs a {
            display:flex; align-items:center; justify-content:center; gap:8px;
            padding:12px 10px; border-radius:14px; font-weight:800; text-decoration:none;
            border:1px solid var(--panel-border,#e5e7eb); color:var(--text,#0f172a);
            background:var(--surface-soft,rgba(0,0,0,.03));
        }
        .portal-role-tabs a.is-active {
            background:var(--primary-soft,rgba(81,97,206,.14));
            border-color:rgba(81,97,206,.35);
            color:var(--primary,#5161ce);
        }
        .portal-panel { margin-top:8px; }
        .portal-panel[hidden] { display:none !important; }
        .google-btn { background:#fff; color:#3c4043; border:1px solid #dadce0; font-weight:700; }
        .google-btn:hover { background:#f8f9fa; color:#202124; border-color:#dadce0; }
        .portal-note { font-size:.9rem; color:var(--muted,#64748b); line-height:1.45; margin-top:12px; }
        .auth-links { margin-top:16px; display:grid; gap:6px; }
    </style>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-icon"><i class="bi bi-shield-lock"></i></div>
        <div class="eyebrow">Edexcel College</div>
        <h1>Student &amp; Parent Portal</h1>
        <p class="intro">Sign in with your Google account. Phone and SMS/OTP login are no longer used for students and parents.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= $h($error) ?></div>
        <?php endif; ?>
        <?php if ($noticeText !== ''): ?>
            <div class="alert alert-info"><?= $h($noticeText) ?></div>
        <?php endif; ?>
        <?php if (!$googleReady): ?>
            <div class="alert alert-warning">Google sign-in is temporarily unavailable. Please contact the college office.</div>
        <?php endif; ?>

        <div class="portal-role-tabs" role="tablist">
            <a href="?role=student<?= $invite !== '' ? '&invite=' . $h(rawurlencode($invite)) : '' ?>"
               class="<?= $role === 'student' ? 'is-active' : '' ?>">Student</a>
            <a href="?role=parent<?= $invite !== '' ? '&invite=' . $h(rawurlencode($invite)) : '' ?>"
               class="<?= $role === 'parent' ? 'is-active' : '' ?>">Parent</a>
        </div>

        <div class="portal-panel" data-role-panel="student" <?= $role === 'student' ? '' : 'hidden' ?>>
            <button type="button" id="btnStudentFaceLogin" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm mb-2" style="font-weight:600;">
                <i class="bi bi-person-bounding-box fs-5"></i>
                <span>Sign in with Face ID</span>
            </button>
            <?php if ($googleReady): ?>
                <a class="btn google-btn btn-lg w-100" href="<?= $h($googleStudent) ?>">
                    <i class="bi bi-google"></i> Continue with Google
                </a>
                <p class="portal-note">New students can create an account by signing in with Google. The college may still need to activate class access.</p>
            <?php endif; ?>
        </div>

        <div class="portal-panel" data-role-panel="parent" <?= $role === 'parent' ? '' : 'hidden' ?>>
            <?php if ($googleReady): ?>
                <a class="btn google-btn btn-lg w-100" href="<?= $h($googleParent) ?>">
                    <i class="bi bi-google"></i> Continue with Google
                </a>
                <p class="portal-note">Parent access to student information still requires college verification after Google sign-in.</p>
            <?php endif; ?>
        </div>

        <div class="auth-links" style="margin-top:20px">
            <a href="/login.php">Staff login</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<?php ui_feedback_js(); ?>
<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="/assets/vendor/face-api/face-api.min.js"></script>
<script src="/assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-biometrics.js') ?>"></script>
<script src="/assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-face-ui.js') ?>"></script>
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
