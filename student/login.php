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
        <?php if (!$googleReady): ?>
            <div class="alert alert-warning">Google sign-in is temporarily unavailable. Please contact the college office.</div>
        <?php else: ?>
            <a class="btn google-btn btn-lg w-100" href="<?= e(rtrim((string)BASE_URL, '/') . '/auth/google/start.php?intent=student') ?>">
                Continue with Google
            </a>
        <?php endif; ?>

        <div class="auth-links">
            <a href="<?= BASE_URL ?>portal/login.php?role=parent">Parent portal</a><br>
            <a href="<?= BASE_URL ?>index.php">Back to homepage</a>
        </div>
    </section>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<?php ui_feedback_js(); ?>
</body>
</html>
