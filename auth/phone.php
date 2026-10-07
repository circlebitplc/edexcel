<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../student/otp_helpers.php';

use Edexcel\Services\ParentAuthService;
use Edexcel\Services\PortalPhoneLinkService;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: /portal/login.php');
    exit;
}

$phoneSvc = new PortalPhoneLinkService($pdo);

// Outside Sri Lanka: Google login does not require SMS phone linking.
if (!$phoneSvc->smsPhoneLinkRequired()) {
    unset($_SESSION['needs_phone_link'], $_SESSION['phone_link_type'], $_SESSION['phone_link_pending']);
    if (ParentAuthService::isLoggedIn()) {
        header('Location: /parent/home.php');
        exit;
    }
    if (function_exists('is_logged_in') && is_logged_in() && (($_SESSION['role'] ?? '') === 'student')) {
        $sid = (int)($_SESSION['user_id'] ?? 0);
        if ($sid > 0) {
            $phoneSvc->activateStudentWithoutPhone($sid);
        }
        header('Location: /student/dashboard.php');
        exit;
    }
    header('Location: /portal/login.php');
    exit;
}

$type = strtolower(trim((string)($_SESSION['phone_link_type'] ?? '')));
if ($type === '' && ParentAuthService::isLoggedIn()) {
    $type = 'parent';
}
if ($type === '' && function_exists('is_logged_in') && is_logged_in() && (($_SESSION['role'] ?? '') === 'student')) {
    $type = 'student';
}

$error = '';
$notice = '';
$showOtp = false;
$phone = trim((string)($_POST['phone'] ?? $_SESSION['phone_link_pending'] ?? ''));

$studentId = (int)($_SESSION['user_id'] ?? 0);
$parentId = (int)($_SESSION['parent_id'] ?? 0);

if ($type === 'student') {
    if ($studentId < 1 || (($_SESSION['role'] ?? '') !== 'student')) {
        header('Location: /portal/login.php?role=student');
        exit;
    }
    if (!$phoneSvc->studentNeedsPhone($studentId) && empty($_SESSION['needs_phone_link'])) {
        header('Location: /student/dashboard.php');
        exit;
    }
} elseif ($type === 'parent') {
    if ($parentId < 1) {
        header('Location: /portal/login.php?role=parent');
        exit;
    }
    if (!$phoneSvc->parentNeedsPhone($parentId) && empty($_SESSION['needs_phone_link'])) {
        header('Location: /parent/home.php');
        exit;
    }
} else {
    header('Location: /portal/login.php');
    exit;
}

$_SESSION['needs_phone_link'] = 1;
$_SESSION['phone_link_type'] = $type;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your session expired. Try again.');
        }
        if ($type === 'student' && isset($_POST['skip_phone'])) {
            $phoneSvc->activateStudentWithoutPhone($studentId);
            $_SESSION['phone_link_skipped'] = 1;
            header('Location: /student/dashboard.php');
            exit;
        }
        $phone = (string)($_POST['phone'] ?? '');
        if (isset($_POST['send']) || isset($_POST['resend'])) {
            $result = $type === 'student'
                ? $phoneSvc->sendStudentSms($studentId, $phone)
                : $phoneSvc->sendParentSms($parentId, $phone);
            $showOtp = !empty($result['show_otp']);
            if (!empty($result['ok'])) {
                $notice = (string)$result['message'];
                $_SESSION['phone_link_pending'] = function_exists('student_normalize_lk_phone')
                    ? student_normalize_lk_phone($phone)
                    : $phone;
            } else {
                $error = (string)($result['message'] ?? 'Could not send SMS.');
            }
        } else {
            $phone = (string)($_POST['phone'] ?? $_SESSION['phone_link_pending'] ?? '');
            $result = $type === 'student'
                ? $phoneSvc->verifyStudentSms($studentId, $phone, (string)($_POST['otp'] ?? ''))
                : $phoneSvc->verifyParentSms($parentId, $phone, (string)($_POST['otp'] ?? ''));
            if (!empty($result['ok'])) {
                unset($_SESSION['phone_link_pending'], $_SESSION['needs_phone_link'], $_SESSION['phone_link_type']);
                if ($type === 'student') {
                    header('Location: /student/dashboard.php');
                } else {
                    header('Location: /parent/verify.php');
                }
                exit;
            }
            $error = (string)($result['message'] ?? 'Verification failed.');
            $showOtp = true;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$displayPhone = $phone !== '' ? $phone : (string)($_SESSION['phone_link_pending'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Verify mobile number · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <link href="/assets/css/student-auth.css" rel="stylesheet">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-icon"><i class="bi bi-phone"></i></div>
        <div class="eyebrow">Google sign-in</div>
        <h1>Add your mobile number</h1>
        <p class="intro">
            <?php if ($type === 'student'): ?>
                A mobile number is optional. Add one to receive SMS codes, or continue and join online classes without it.
            <?php else: ?>
                Enter a Sri Lankan mobile number. We will send an <strong>SMS</strong> one-time code to verify it
                and save it on your parent account.
            <?php endif; ?>
        </p>

        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
        <?php if ($notice !== ''): ?><div class="alert alert-success"><?= $h($notice) ?></div><?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <label for="phone">Mobile number</label>
            <input class="form-control" id="phone" name="phone" inputmode="tel" placeholder="077XXXXXXX"
                   value="<?= $h($displayPhone) ?>" <?= $type === 'student' ? '' : 'required' ?> <?= $showOtp ? 'readonly' : '' ?>>

            <?php if ($showOtp): ?>
                <label for="otp" class="mt-3">SMS code</label>
                <input class="form-control" id="otp" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus>
                <button class="btn btn-primary w-100 mt-3" type="submit" name="verify" value="1">Verify &amp; continue</button>
                <button class="btn btn-outline-secondary w-100 mt-2" type="submit" name="resend" value="1">Resend SMS</button>
            <?php else: ?>
                <button class="btn btn-primary w-100 mt-3" type="submit" name="send" value="1">Send SMS code</button>
            <?php endif; ?>
        </form>

        <?php if ($type === 'student'): ?>
            <form method="post" class="mt-2">
                <?= csrf_field() ?>
                <button class="btn btn-link w-100" type="submit" name="skip_phone" value="1">Continue without a mobile number</button>
            </form>
        <?php endif; ?>

        <div class="auth-links mt-3">
            <a href="<?= $type === 'parent' ? '/parent/logout.php' : '/logout.php' ?>">Sign out</a>
        </div>
    </section>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>
