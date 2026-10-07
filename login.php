<?php
// login.php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/student/otp_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

use Edexcel\Services\AdminTotpService;
use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\SecurityEventService;

require_once __DIR__ . '/config/ops.php';

$error = trim((string)($_GET['error'] ?? ''));
$otpNotice = trim((string)($_GET['notice'] ?? ''));
$otpPhone = current_staff_login_otp_phone();
$showOtp = $otpPhone !== '';
$showTotp = !empty($_SESSION['staff_pending_totp_user_id']);
$security = ($pdo instanceof PDO) ? new SecurityEventService($pdo) : null;
$googleReady = ($pdo instanceof PDO) && GoogleOAuthService::isPortalEnabled($pdo);
$legacyLoginDisabled = ($pdo instanceof PDO) && (ops_setting($pdo, 'staff_legacy_login_disabled', ops_setting($pdo, 'teacher_legacy_login_disabled', '0')) === '1');

$bioService = ($pdo instanceof PDO) ? new \Edexcel\Services\AdminBiometricService($pdo) : null;
$passkeyService = ($pdo instanceof PDO && $bioService) ? new \Edexcel\Services\AdminPasskeyService($pdo, $bioService) : null;
$faceService = ($pdo instanceof PDO && $bioService) ? new \Edexcel\Services\AdminFaceService($pdo, $bioService) : null;
$adminUser = null;
$adminPasskeysCount = 0;
$adminFaceEnrolled = false;
if ($bioService) {
    try {
        $adminUser = $bioService->getAdminAccount();
        if ($passkeyService) {
            $adminPasskeysCount = count($passkeyService->listPasskeys((int)$adminUser['id']));
        }
        if ($faceService) {
            $adminFaceEnrolled = $faceService->isEnrolled((int)$adminUser['id']);
        }
    } catch (Throwable $e) {
    }
}

/**
 * Finish staff login after password (+ optional WhatsApp OTP / TOTP).
 *
 * @param array<string,mixed> $user
 */
$finishStaffLogin = static function (array $user) use ($pdo, $security): void {
    if (function_exists('complete_staff_portal_login')) {
        complete_staff_portal_login($user);
    } else {
        regenerate_session();
        if (function_exists('clear_cross_portal_session')) {
            clear_cross_portal_session('staff');
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['teacher_id'] = $user['teacher_id'] ?? null;
        $_SESSION['username'] = $user['username'];
        $_SESSION['last_activity'] = time();
    }
    if ($security) {
        $security->record(
            'admin_login_success',
            'Staff signed in',
            'info',
            (int)$user['id'],
            (string)$user['username'],
            (string)$user['role'],
            'auth'
        );
    }
    header('Location: dashboard.php');
    exit();
};

/**
 * After password (and optional SMS OTP), require authenticator TOTP when enabled.
 *
 * @param array<string,mixed> $user
 */
$maybeRequireTotp = static function (array $user) use ($pdo, $finishStaffLogin): bool {
    if (!($pdo instanceof PDO)) {
        return false;
    }
    $role = strtolower(trim((string)($user['role'] ?? '')));
    if ($role !== 'admin') {
        return false;
    }
    $totp = new AdminTotpService($pdo);
    if (!$totp->isEnabledForUser((int)$user['id'])) {
        if ($totp->isRequiredGlobally()) {
            // Required but not enrolled — allow login and push setup via security page.
            $_SESSION['admin_totp_enrollment_required'] = 1;
        }
        return false;
    }
    if ($totp->isTrustedDevice((int)$user['id'])) {
        return false;
    }
    $_SESSION['staff_pending_totp_user_id'] = (int)$user['id'];
    $_SESSION['staff_pending_totp_username'] = (string)$user['username'];
    return true;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
        if ($security) {
            $security->record('csrf_failure', 'Login CSRF failure', 'warning', null, null, null, 'auth');
        }
    } elseif ($legacyLoginDisabled) {
        $error = 'Staff password login has been disabled by the administrator. Please click "Continue with Google".';
    } elseif (isset($_POST['staff_otp_cancel']) || isset($_POST['staff_totp_cancel'])) {
        unset(
            $_SESSION['staff_login_otp_phone'],
            $_SESSION['staff_pending_2fa_user_id'],
            $_SESSION['staff_pending_totp_user_id'],
            $_SESSION['staff_pending_totp_username']
        );
        $otpPhone = '';
        $showOtp = false;
        $showTotp = false;
    } elseif (isset($_POST['staff_totp_verify'])) {
        $pendingId = (int)($_SESSION['staff_pending_totp_user_id'] ?? 0);
        $code = trim((string)($_POST['totp_code'] ?? ''));
        $showTotp = true;
        if ($pendingId < 1 || !($pdo instanceof PDO)) {
            $error = 'Authenticator session expired. Sign in again.';
            $showTotp = false;
        } else {
            $totp = new AdminTotpService($pdo);
            if ($totp->verifyCode($pendingId, $code, true)) {
                if (!empty($_POST['trust_device'])) {
                    $totp->trustDevice($pendingId, 30, 'Login trusted device');
                }
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$pendingId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                unset($_SESSION['staff_pending_totp_user_id'], $_SESSION['staff_pending_totp_username']);
                if ($user) {
                    $finishStaffLogin($user);
                }
                $error = 'Account not found.';
            } else {
                $error = 'Invalid authenticator or recovery code.';
                if ($security) {
                    $security->record('totp_failure', 'Admin TOTP failed', 'warning', $pendingId, (string)($_SESSION['staff_pending_totp_username'] ?? ''), 'admin', 'auth');
                }
            }
        }
    } elseif (isset($_POST['staff_otp_send']) || isset($_POST['staff_otp_resend'])) {
        $posted = normalize_phone((string)($_POST['phone'] ?? $_POST['username'] ?? ''));
        if (!valid_lk_phone($posted)) {
            $error = 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.';
        } elseif (!($pdo instanceof PDO)) {
            $error = 'Unable to connect to the database.';
        } else {
            $result = send_staff_login_otp($pdo, $posted);
            $showOtp = !empty($result['show_otp']);
            $otpPhone = current_staff_login_otp_phone();
            if ($result['ok']) {
                $otpNotice = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['staff_otp_verify']) || (isset($_POST['otp']) && trim((string)$_POST['otp']) !== '')) {
        $posted = normalize_phone((string)($_POST['username'] ?? $otpPhone));
        $otp = (string)($_POST['otp'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $newConfirm = (string)($_POST['new_password_confirm'] ?? '');
        $showOtp = true;
        if ($newPassword !== '' && $newPassword !== $newConfirm) {
            $error = 'New passwords do not match.';
        } elseif (!valid_lk_phone($posted)) {
            $error = 'Enter a valid Sri Lankan mobile number.';
        } elseif (!($pdo instanceof PDO)) {
            $error = 'Unable to connect to the database.';
        } else {
            $result = verify_staff_login_otp($pdo, $posted, $otp, $newPassword);
            if (!empty($result['ok']) && !empty($result['user'])) {
                $pending = (int)($_SESSION['staff_pending_2fa_user_id'] ?? 0);
                if ($pending > 0 && (int)$result['user']['id'] !== $pending) {
                    $error = 'Sign in with the same admin account that requested the code.';
                } else {
                    unset($_SESSION['staff_pending_2fa_user_id']);
                    if ($maybeRequireTotp($result['user'])) {
                        $showOtp = false;
                        $showTotp = true;
                        $otpNotice = 'Enter the code from your authenticator app.';
                    } else {
                        $finishStaffLogin($result['user']);
                    }
                }
            } else {
                $error = (string)($result['message'] ?? 'Could not verify the code.');
                if ($security) {
                    $security->record('otp_failure', 'Staff OTP verify failed', 'warning', null, $posted, 'staff', 'auth');
                }
            }
            $otpPhone = $posted;
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            if (login_is_locked($pdo, $username)) {
                $error = 'Too many failed sign-in attempts. Wait 15 minutes and try again.';
            } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND deleted_at IS NULL");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $role = strtolower(trim((string)($user['role'] ?? '')));

                if ($role === 'student') {
                    $error = 'Students sign in from the homepage student portal.';
                } elseif (!in_array($role, ['admin', 'teacher'], true)) {
                    login_record_failure($pdo, $username);
                    $error = 'Invalid username or password.';
                } elseif (array_key_exists('is_active', $user) && (int)$user['is_active'] !== 1) {
                    login_record_failure($pdo, $username);
                    $error = 'Invalid username or password.';
                } else {
                    login_clear_failures($pdo, $username);
                    if ($role === 'teacher') {
                        try {
                            require_once __DIR__ . '/config/ops.php';
                            if (ops_setting($pdo, 'teacher_legacy_login_disabled', '0') === '1') {
                                $error = 'Teacher password login has been disabled. Please use the "Sign in with Google" button below.';
                            } else {
                                $finishStaffLogin($user);
                            }
                        } catch (Throwable $e) {
                            $finishStaffLogin($user);
                        }
                    } else {
                        $requireOtp = $role === 'admin';
                        try {
                            require_once __DIR__ . '/config/ops.php';
                            $requireOtp = $requireOtp && ops_setting($pdo, 'admin_password_requires_otp', '1') === '1';
                        } catch (Throwable $e) {
                        }
                        $otpPhoneForUser = staff_whatsapp_for_user($pdo, $user);
                        if ($requireOtp && $otpPhoneForUser === '') {
                            $error = 'This administrator account needs a mobile number before password login. Use SMS OTP login, or ask another admin to add a number.';
                        } elseif ($requireOtp && $otpPhoneForUser !== '') {
                            $otpResult = send_staff_login_otp($pdo, $otpPhoneForUser);
                            if (!empty($otpResult['ok'])) {
                                $_SESSION['staff_pending_2fa_user_id'] = (int)$user['id'];
                                $showOtp = true;
                                $otpPhone = $otpPhoneForUser;
                                $otpNotice = 'Enter the SMS code to finish signing in as admin.';
                            } else {
                                $error = (string)($otpResult['message'] ?? 'Could not send a verification code. Try SMS OTP login.');
                            }
                        } else {
                            if ($maybeRequireTotp($user)) {
                                $showTotp = true;
                                $otpNotice = 'Enter the code from your authenticator app to finish signing in.';
                            } else {
                                $finishStaffLogin($user);
                            }
                        }
                    }
                }
            } else {
                login_record_failure($pdo, $username);
                if ($security) {
                    $security->record('failed_login', 'Invalid staff credentials', 'warning', null, $username, null, 'auth');
                }
                $error = 'Invalid username or password.';
            }
            }
        } else {
            $error = 'Please fill in both fields.';
        }
    }
}

if (!empty($_SESSION['staff_pending_totp_user_id'])) {
    $showTotp = true;
}
if (!empty($_SESSION['staff_login_otp_phone']) || !empty($_SESSION['staff_pending_2fa_user_id'])) {
    $showOtp = !$showTotp;
    $otpPhone = current_staff_login_otp_phone();
}
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= htmlspecialchars(function_exists('generate_csrf_token') ? generate_csrf_token() : '', ENT_QUOTES, 'UTF-8') ?>">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Login - Edexcel College</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/system.css?v=<?= filemtime(__DIR__ . '/assets/css/system.css') ?>">
    <?php
    require_once __DIR__ . '/includes/ui_feedback.php';
    ui_feedback_css();
    ?>
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    
    <style>
        .auth-page { min-height: 100vh; display:grid; place-items:center; padding:24px; }
        .auth-shell { width:min(100%, 440px); }
        .auth-card { background:var(--surface); border:1px solid var(--panel-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-md); padding:34px; }
        .auth-brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; }
        .auth-brand-icon { width:48px; height:48px; display:grid; place-items:center; border-radius:14px; color:#fff; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); box-shadow:0 10px 24px rgba(81,97,206,.22); font-size:1.35rem; }
        .auth-title { margin:0; font-size:1.7rem; font-weight:800; color:var(--text); }
        .auth-subtitle { margin:4px 0 0; color:var(--muted); font-size:.9rem; }
        .auth-form .form-control { min-height:48px; border-radius:12px; }
        .auth-submit { min-height:48px; border-radius:12px; font-weight:700; }
        .auth-footer { margin-top:20px; text-align:center; color:var(--muted); font-size:.8rem; }
        @media (max-width:576px){ .auth-page{padding:16px;} .auth-card{padding:24px 20px;} .auth-title{font-size:1.45rem;} }
        .staff-google-btn {
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.05rem;
            background: var(--surface);
            color: var(--text);
            border: 1.5px solid var(--panel-border, #cbd5e1);
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .staff-google-btn:hover {
            background: var(--surface-soft, rgba(0,0,0,0.03)) !important;
            border-color: var(--primary, #5161ce) !important;
            color: var(--text) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
        }
        .staff-google-btn.disabled {
            opacity: 0.75;
            pointer-events: none;
            cursor: not-allowed;
        }
        details summary::-webkit-details-marker { display:none; }
        details[open] .legacy-chevron { transform: rotate(180deg); }
        .legacy-chevron { transition: transform 0.2s ease; }
    </style>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="auth-page">
    <div class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand">
                <div class="auth-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <h1 class="auth-title">Edexcel College</h1>
                    <p class="auth-subtitle">Staff Portal &bull; Timetable Management</p>
                </div>
            </div>

            <div class="mb-4 text-center">
                <h2 class="h5 fw-bold mb-1">Staff Login</h2>
                <p class="text-muted small mb-0">Sign in with your authorized college Google account.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                    <div class="flex-grow-1">
                        <div><?= htmlspecialchars($error) ?></div>
                        <?php if (str_contains(strtolower($error), 'not linked') || str_contains(strtolower($error), 'no staff account')): ?>
                            <div class="small mt-2 pt-2 border-top border-danger-subtle text-danger-emphasis">
                                <i class="bi bi-info-circle-fill me-1"></i><strong>Need access?</strong> If you are a teacher or administrator, your Google account must be linked by the college administrator. Please contact college support.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($otpNotice): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
                    <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                    <div><?= htmlspecialchars($otpNotice) ?></div>
                </div>
            <?php endif; ?>

            <!-- Admin Biometric Authentication Options -->
            <div class="mb-3 d-flex flex-column gap-2" id="adminBiometricOptions">
                <button type="button" id="btnPasskeyLogin" class="btn btn-dark w-100 py-3 d-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm border border-secondary" style="font-weight:600; font-size:1.02rem;">
                    <i class="bi bi-passkey-fill fs-5 text-info"></i>
                    <span class="btn-text">Continue with Passkey</span>
                </button>
                <button type="button" id="btnFaceLogin" class="btn btn-outline-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm" style="font-weight:600; font-size:1.02rem;">
                    <i class="bi bi-person-bounding-box fs-5"></i>
                    <span class="btn-text">Login with Face</span>
                </button>
            </div>

            <div class="position-relative my-3 text-center">
                <hr class="border-secondary-subtle">
                <span class="position-absolute top-50 start-50 translate-middle px-3 text-muted small" style="background:var(--surface);">OR</span>
            </div>

            <?php if ($googleReady): ?>
                <div class="mb-3">
                    <a href="/auth/google/start.php?intent=staff" id="googleStaffBtn" class="btn w-100 d-flex align-items-center justify-content-center gap-3 py-3 shadow-sm staff-google-btn">
                        <svg style="width:22px;height:22px;" viewBox="0 0 24 24" class="flex-shrink-0">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span class="btn-text">Continue with Google</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>Google sign-in is temporarily unconfigured. Please use the legacy login below.
                </div>
            <?php endif; ?>

            <?php
            $staffForgotMode = !$showOtp && (
                isset($_POST['staff_otp_send']) || isset($_POST['staff_otp_resend'])
            );
            $legacyOpen = ($error !== '' && !empty($_POST)) || $showOtp || $showTotp || $staffForgotMode || !$googleReady;
            ?>

            <?php if ($legacyLoginDisabled): ?>
                <div class="text-center mt-3 pt-3 border-top">
                    <span class="badge bg-secondary-subtle text-secondary small py-1 px-2">
                        <i class="bi bi-shield-lock me-1"></i>Legacy password login is disabled by administrator
                    </span>
                </div>
            <?php else: ?>
                <div class="mt-4 pt-3 border-top">
                    <details class="legacy-login-details" <?= $legacyOpen ? 'open' : '' ?>>
                        <summary class="text-center text-muted small user-select-none mb-3" style="cursor:pointer; list-style:none;">
                            <span class="d-inline-flex align-items-center gap-1">
                                <i class="bi bi-key"></i>
                                <span>Legacy Sign-In Fallback</span>
                                <i class="bi bi-chevron-down legacy-chevron small"></i>
                            </span>
                        </summary>

                        <div class="p-3 mb-2 rounded-3" style="background: var(--surface-soft, rgba(0,0,0,0.02)); border: 1px dashed var(--panel-border, #cbd5e1);">
                            <div class="badge bg-warning-subtle text-warning-emphasis d-block mb-3 py-2 text-wrap text-start">
                                <i class="bi bi-exclamation-circle me-1"></i><strong>Temporary Fallback:</strong> This username/password login is temporary and will be retired once all staff have linked their Google accounts.
                            </div>

                            <?php if ($showTotp): ?>
                                <p class="text-muted small mb-3">Enter the 6-digit code from your authenticator app (or a recovery code).</p>
                                <form method="POST" class="auth-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="staff_totp_verify" value="1">
                                    <div class="mb-3">
                                        <label for="totp_code" class="form-label fw-semibold">Authenticator code</label>
                                        <input class="form-control text-center" type="text" id="totp_code" name="totp_code" inputmode="numeric" autocomplete="one-time-code" placeholder="------" required autofocus>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="trust_device" value="1" id="trust_device">
                                        <label class="form-check-label" for="trust_device">Trust this device for 30 days</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 auth-submit">Verify &amp; Sign In</button>
                                </form>
                                <form method="POST" class="mt-2">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="staff_totp_cancel" value="1" class="btn btn-link w-100">Back to password login</button>
                                </form>
                            <?php elseif ($showOtp && $otpPhone !== ''): ?>
                                <p class="text-muted small mb-3">Enter the SMS code sent to <strong><?= htmlspecialchars($otpPhone) ?></strong></p>
                                <form method="POST" class="auth-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($otpPhone) ?>">
                                    <input type="hidden" name="staff_otp_verify" value="1">
                                    <div class="mb-3">
                                        <label for="otp" class="form-label fw-semibold">SMS login code</label>
                                        <input class="form-control text-center js-otp-auto" type="text" id="otp" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="------" required autofocus>
                                    </div>
                                    <button type="submit" name="staff_otp_verify" value="1" class="btn btn-primary w-100 auth-submit">Verify OTP &amp; Sign In</button>
                                </form>
                                <form method="POST" class="mt-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($otpPhone) ?>">
                                    <button type="submit" name="staff_otp_resend" value="1" class="btn btn-outline-secondary w-100">Resend SMS code</button>
                                </form>
                                <form method="POST" class="mt-2">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="staff_otp_cancel" value="1" class="btn btn-link w-100">Back to password login</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="" class="auth-form js-login-form" data-otp-mode="<?= $staffForgotMode ? '1' : '0' ?>" novalidate>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="staff_otp_send" value="1" class="js-otp-flag"<?= $staffForgotMode ? '' : ' disabled' ?>>
                                    <div class="mb-3">
                                        <label for="username" class="form-label fw-semibold">Username</label>
                                        <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input type="text" class="form-control js-user-input" id="username" name="username" autocomplete="username" required></div>
                                    </div>
                                    <div class="mb-4 js-pass-wrap"<?= $staffForgotMode ? ' hidden' : '' ?>>
                                        <label for="password" class="form-label fw-semibold">Password</label>
                                        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input type="password" class="form-control js-pass-input" id="password" name="password" autocomplete="current-password"<?= $staffForgotMode ? ' disabled' : ' required' ?>></div>
                                    </div>
                                    <div class="mb-4 js-phone-wrap"<?= $staffForgotMode ? '' : ' hidden' ?>>
                                        <label for="staff_phone" class="form-label fw-semibold">Mobile number</label>
                                        <input type="text" class="form-control js-phone-input" id="staff_phone" name="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? $_POST['username'] ?? '')) ?>" inputmode="tel" placeholder="077XXXXXXX"<?= $staffForgotMode ? ' required' : ' disabled' ?>>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 auth-submit js-login-submit" data-login-label="Sign in" data-otp-label="Send SMS OTP"><?= $staffForgotMode ? 'Send SMS OTP' : 'Sign in' ?></button>

                                    <p class="text-center mt-3 mb-0">
                                        <a href="#" class="js-forgot-link"<?= $staffForgotMode ? ' hidden' : '' ?>>Forgot password? Use SMS OTP</a>
                                        <a href="#" class="js-back-password"<?= $staffForgotMode ? '' : ' hidden' ?>>Back to password login</a>
                                    </p>
                                </form>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

            <div class="auth-footer mt-4 pt-3 border-top">
                <div class="text-muted small mb-2">Staff portal for teachers and administrators only.</div>
                <div class="d-flex justify-content-center align-items-center gap-3 small">
                    <a href="index.php#student-login" class="text-decoration-none"><i class="bi bi-mortarboard me-1"></i>Student Login</a>
                    <span class="text-muted opacity-50">&bull;</span>
                    <a href="/parent/login.php" class="text-decoration-none"><i class="bi bi-people me-1"></i>Parent Portal</a>
                </div>
            </div>
        </section>
    </div>
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
<script src="<?= BASE_URL ?>assets/vendor/face-api/face-api.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-biometrics.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/assets/js/admin-face-ui.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/login-otp.js?v=<?= filemtime(__DIR__ . '/assets/js/login-otp.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var googleBtn = document.getElementById('googleStaffBtn');
    if (googleBtn) {
        googleBtn.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            this.classList.add('disabled');
            var textSpan = this.querySelector('.btn-text');
            if (textSpan) {
                textSpan.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Connecting to Google...';
            }
        });
    }

    var btnPasskey = document.getElementById('btnPasskeyLogin');
    if (btnPasskey) {
        btnPasskey.addEventListener('click', async function() {
            var origContent = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Connecting authenticator...';

            try {
                var res = await AdminBiometrics.loginWithPasskey();
                this.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Verified!</span>';
                window.location.href = res.redirect || '/dashboard.php';
            } catch (err) {
                alert(err.message || 'Passkey login failed. Please try again or use another sign-in method.');
                this.disabled = false;
                this.innerHTML = origContent;
            }
        });
    }

    var btnFace = document.getElementById('btnFaceLogin');
    if (btnFace) {
        btnFace.addEventListener('click', function() {
            var userVal = (document.getElementById('username')?.value || '').trim();
            AdminFaceUI.startLoginModal(false, userVal);
        });
    }
});
</script>
</body>
</html>
