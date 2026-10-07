<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\TeacherMigrationService;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$inviteToken = trim((string)($_GET['invite'] ?? ''));
$googleReady = GoogleOAuthService::isPortalEnabled(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$error = '';
$teacher = null;
$inviteData = null;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $error = 'Database service is currently unavailable. Please try again later.';
} elseif ($inviteToken === '') {
    $error = 'No invitation token was provided. Please use the exact link sent by your administrator.';
} else {
    $migration = new TeacherMigrationService($pdo);
    $check = $migration->validateInvite($inviteToken);
    if (!$check['ok']) {
        $error = $check['message'] ?? 'This invitation link is invalid or expired.';
    } else {
        $teacher = $check['teacher'];
        $inviteData = $check['invite'];
    }
}

$startUrl = '/auth/google/start.php?intent=link_teacher&invite=' . rawurlencode($inviteToken);
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Link Teacher Google Account · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <link href="/assets/css/student-auth.css" rel="stylesheet">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    <style>
        .auth-card { max-width: 460px; margin: 40px auto; padding: 32px; }
        .teacher-badge-box {
            background: var(--surface-soft, rgba(0,0,0,.04));
            border: 1px solid var(--panel-border, #e5e7eb);
            border-radius: 12px;
            padding: 16px;
            margin: 18px 0;
            text-align: left;
        }
        .google-btn {
            background: #fff;
            color: #3c4043;
            border: 1px solid #dadce0;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            width: 100%;
            border-radius: 8px;
            text-decoration: none;
            transition: background .2s, border-color .2s;
        }
        .google-btn:hover { background: #f8f9fa; color: #202124; border-color: #c6c9cc; }
        .google-icon { width: 18px; height: 18px; }
        .info-note { font-size: .88rem; color: var(--muted, #64748b); margin-top: 14px; line-height: 1.45; }
    </style>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-icon" style="background:rgba(81,97,206,.15); color:#5161ce;"><i class="bi bi-person-badge"></i></div>
        <div class="eyebrow">Edexcel College · Faculty Portal</div>
        <h1>Link Google Account</h1>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger mt-3 mb-4 text-start">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $h($error) ?>
            </div>
            <p class="text-muted small">If your link has expired, please request a fresh invitation link from the college administration office.</p>
            <div class="mt-4">
                <a href="/login.php" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-left me-2"></i>Back to Staff Sign-In</a>
            </div>
        <?php elseif ($teacher): ?>
            <p class="intro">Connect your official Google account to your Edexcel College teacher profile for secure, one-click access.</p>

            <div class="teacher-badge-box">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($teacher['photo']) && is_file(__DIR__ . '/../../' . ltrim($teacher['photo'], '/'))): ?>
                        <img src="/<?= $h(ltrim($teacher['photo'], '/')) ?>" alt="" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width:48px;height:48px;font-size:1.2rem;">
                            <?= $h(strtoupper(substr($teacher['name'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-bold text-body"><?= $h($teacher['name']) ?></div>
                        <div class="small text-muted">Username: <span class="badge bg-secondary"><?= $h($teacher['username']) ?></span></div>
                        <?php if (!empty($teacher['email'])): ?>
                            <div class="small text-muted mt-1"><i class="bi bi-envelope me-1"></i><?= $h($teacher['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($inviteData['expected_email'])): ?>
                    <div class="alert alert-info py-2 px-3 mt-3 mb-0 small">
                        <i class="bi bi-shield-check me-1"></i><strong>Expected Account:</strong> <?= $h($inviteData['expected_email']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$googleReady): ?>
                <div class="alert alert-warning small">
                    Google OAuth is currently being configured on the server. Please check back shortly or contact the office.
                </div>
            <?php else: ?>
                <div class="mt-4">
                    <a href="<?= $h($startUrl) ?>" class="google-btn shadow-sm">
                        <svg class="google-icon" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        Link and Sign In with Google
                    </a>
                </div>

                <div class="info-note">
                    <i class="bi bi-info-circle me-1"></i>
                    Your classes, timetable, attendance, payments, and historical records remain 100% untouched. Linking simply connects your Google identity to this account for future logins.
                </div>
            <?php endif; ?>

            <div class="mt-4 pt-3 border-top text-center">
                <a href="/login.php" class="small text-decoration-none text-muted"><i class="bi bi-key me-1"></i>Sign in with username/password instead</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

