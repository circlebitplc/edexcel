<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\ParentAuthService;

header('Cache-Control: no-store');
$lang = eck_lang();
$error = trim((string)($_GET['error'] ?? ''));

if (ParentAuthService::isLoggedIn()) {
    header('Location: /parent/home.php');
    exit;
}

// Legacy WhatsApp OTP login removed — Google only.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: /portal/login.php?role=parent&error=' . rawurlencode('Please sign in with Google.'));
    exit;
}

$googleReady = false;
try {
    $googleReady = GoogleOAuthService::isPortalEnabled(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
} catch (Throwable $e) {
    $googleReady = false;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'si' ? 'si' : 'en' ?>" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= $h(eck_t('parent.login')) ?> · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <?php
    require_once __DIR__ . '/../includes/ui_feedback.php';
    ui_feedback_css();
    if (function_exists('app_theme_css_link')) { app_theme_css_link(); }
    ?>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="parent-page" style="min-height:100vh;padding:24px 16px">
    <div style="width:min(100%,420px);margin:40px auto">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <?= eck_lang_toggle() ?>
            <h1 class="h4 fw-bold"><?= $h(eck_t('parent.login')) ?></h1>
            <p class="text-muted">Sign in with your Google account. WhatsApp OTP login is no longer used.</p>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= $h($error) ?></div>
            <?php endif; ?>
            <?php if ($googleReady): ?>
                <a class="btn btn-outline-dark w-100 mb-3" href="/auth/google/start.php?intent=parent">
                    <i class="bi bi-google"></i> Continue with Google
                </a>
                <p class="small text-muted mb-0">Parent access to student information still requires college verification after sign-in.</p>
            <?php else: ?>
                <div class="alert alert-warning mb-0">Google sign-in is temporarily unavailable. Please contact the college office.</div>
            <?php endif; ?>
            <div class="mt-3 small">
                <a href="/portal/login.php?role=student">Student portal</a>
            </div>
        </div>
    </div>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<?php ui_feedback_js(); ?>
</body>
</html>
