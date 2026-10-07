<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/otp_helpers.php';

require_student();

$pdoDb = ($pdo instanceof PDO) ? $pdo : null;
$svc = student_devices($pdoDb);
$returnTo = student_presence_safe_return((string)($_GET['return'] ?? $_POST['return'] ?? ''));
$returnUrl = rtrim((string)BASE_URL, '/') . $returnTo;
$error = '';
$success = '';
$studentId = (int)($_SESSION['user_id'] ?? 0);

if ($svc && ($svc->presenceValid() || $svc->grantPresenceIfKnownDevice($studentId))) {
    header('Location: ' . $returnUrl);
    exit;
}

if (!$svc || $studentId < 1) {
    $error = 'Unable to confirm this device right now. Open the student portal again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif (isset($_POST['presence_resend'])) {
        $result = $svc->startPresenceOtp($studentId);
        if (($result['status'] ?? '') === 'ok') {
            header('Location: ' . $returnUrl);
            exit;
        }
        if (($result['status'] ?? '') === 'otp') {
            $success = (string)($result['message'] ?? '');
        } else {
            $error = (string)($result['message'] ?? 'Could not send the confirmation code.');
        }
    } else {
        $result = $svc->verifyPresenceOtp($studentId, (string)($_POST['otp'] ?? ''));
        if (($result['status'] ?? '') === 'ok') {
            header('Location: ' . $returnUrl);
            exit;
        }
        $error = (string)($result['message'] ?? 'Could not verify the code.');
    }
} else {
    $result = $svc->startPresenceOtp($studentId);
    if (($result['status'] ?? '') === 'ok') {
        header('Location: ' . $returnUrl);
        exit;
    }
    if (($result['status'] ?? '') === 'otp') {
        $success = (string)($result['message'] ?? '');
    } else {
        $error = (string)($result['message'] ?? 'Could not send the confirmation code.');
    }
}

$phone = $svc ? $svc->phoneForUser($studentId) : '';
$phoneShown = $phone !== '' && function_exists('campus_display_phone')
    ? campus_display_phone($phone)
    : $phone;
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Confirm it is you - Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/student-auth.css">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-icon">📱</div>
        <div class="eyebrow">Student check</div>
        <h1>Confirm it is you</h1>
        <p class="intro">
            Live class and recordings need an SMS code on the registered student number.
            This stops someone else using a shared password.
        </p>
        <?php if ($phoneShown !== ''): ?>
            <p class="small text-muted">Code sent to <strong><?= e($phoneShown) ?></strong></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <label for="otp">SMS confirmation code</label>
            <input class="form-control otp-input js-otp-auto" type="text" id="otp" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="------" required autofocus>
            <button class="btn btn-primary w-100" type="submit">Confirm and continue</button>
        </form>
        <form method="POST" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <button class="btn btn-outline-secondary w-100" type="submit" name="presence_resend" value="1">Resend SMS code</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(rtrim((string)BASE_URL, '/') . '/student/dashboard.php') ?>">Back to dashboard</a>
        </div>
    </section>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<?php if (function_exists('student_session_guard_script')) { student_session_guard_script(); } ?>
</body>
</html>
