<?php
declare(strict_types=1);

define('DB_ALLOW_FAILURE', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\GoogleOAuthService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

$googleReady = false;
try {
    $googleReady = GoogleOAuthService::isPortalEnabled(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
} catch (Throwable $e) {
    $googleReady = false;
}

// Phone/OTP registration removed — Google only.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . rtrim((string)BASE_URL, '/') . '/auth/google/start.php?intent=student');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
<title>Student Registration | Edexcel College</title>
<meta name="description" content="Create your Edexcel College student account using Google Sign-In to access class timetables, recordings, and your parent portal.">
<link rel="canonical" href="https://edexcel.college/student/register.php">
<meta name="robots" content="noindex, follow">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/student-auth.css" rel="stylesheet">
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
        <h1>Create Student Account</h1>
        <p class="intro">Create your student account by signing in with Google. Phone OTP registration is no longer used.</p>

        <?php if ($googleReady): ?>
            <a class="btn google-btn btn-lg w-100" href="/auth/google/start.php?intent=student">
                Continue with Google
            </a>
            <p class="small text-muted text-center mt-3 mb-0">The college may still need to activate class access after your first Google sign-in.</p>
        <?php else: ?>
            <div class="alert alert-warning">Google sign-in is temporarily unavailable. Please contact the college office.</div>
        <?php endif; ?>

        <div class="auth-links">
            <a href="/portal/login.php?role=student">Already have an account? Student Login</a>
        </div>
    </section>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<?php ui_feedback_js(); ?>
</body>
</html>
