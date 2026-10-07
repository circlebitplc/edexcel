<?php
declare(strict_types=1);

/**
 * Public homepage teacher + student login POST handlers.
 * Extracted from the legacy index.php so every layout can share them.
 */

$activeSection = $activeSection ?? 'home';
$teacherLoginError = $teacherLoginError ?? '';
$teacherOtpNotice = $teacherOtpNotice ?? '';
$teacherOtpStep = $teacherOtpStep ?? false;
$studentLoginError = $studentLoginError ?? '';
$studentOtpNotice = $studentOtpNotice ?? '';
$studentOtpStep = $studentOtpStep ?? false;
$studentDeviceOtpStep = $studentDeviceOtpStep ?? student_device_otp_pending();
$studentLoginNotice = $studentLoginNotice ?? student_login_notice_message();
$parentLoginError = $parentLoginError ?? '';
$parentLoginNotice = $parentLoginNotice ?? '';
$parentOtpStep = $parentOtpStep ?? false;

$homepageRoot = defined('HOMEPAGE_ROOT') ? HOMEPAGE_ROOT : dirname(__DIR__);
$parentAutoload = $homepageRoot . '/vendor/autoload.php';
if (is_file($parentAutoload)) {
    require_once $parentAutoload;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' &&
    isset($_POST['teacher_login'])
) {
    $activeSection = 'teacher-login';
    $teacherLoginError = '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $teacherLoginError = 'Your session expired. Please refresh the page and try again.';
    } else {
        $username = trim((string)($_POST['teacher_username'] ?? ''));
        $password = (string)($_POST['teacher_password'] ?? '');

        if ($username === '' || $password === '') {
            $teacherLoginError = 'Please enter your username and password.';
        } elseif (!($pdo instanceof PDO)) {
            $teacherLoginError = 'Unable to connect to the database. Please try again later.';
        } elseif (login_is_locked($pdo, $username)) {
            $teacherLoginError = 'Too many failed sign-in attempts. Wait 15 minutes and try again.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, username, password_hash, role, teacher_id, deleted_at, is_active
                    FROM users
                    WHERE username = ? AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$user ||
                    empty($user['password_hash']) ||
                    !password_verify($password, $user['password_hash'])
                ) {
                    $teacherLoginError = 'Invalid username or password.';
                    login_record_failure($pdo, $username);
                } else {
                    $loginRole = strtolower(trim((string)($user['role'] ?? '')));
                    if (!in_array($loginRole, ['teacher', 'admin'], true)) {
                        $teacherLoginError = 'This account is not registered as a teacher or administrator account.';
                    } elseif (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
                        $teacherLoginError = $loginRole === 'admin'
                            ? 'This administrator account is currently inactive.'
                            : 'This teacher account is currently inactive.';
                    } else {
                        login_clear_failures($pdo, $username);
                        $requireOtp = $loginRole === 'admin';
                        try {
                            require_once $homepageRoot . '/config/ops.php';
                            require_once $homepageRoot . '/includes/helpers.php';
                            $requireOtp = $requireOtp && ops_setting($pdo, 'admin_password_requires_otp', '1') === '1';
                        } catch (Throwable $e) {
                            $requireOtp = $loginRole === 'admin';
                        }
                        $otpPhoneForUser = staff_whatsapp_for_user($pdo, $user);
                        if ($requireOtp && $otpPhoneForUser === '') {
                            $teacherLoginError = 'This administrator account needs a mobile number before password login. Use SMS OTP login, or ask another admin to add a number.';
                        } elseif ($requireOtp && $otpPhoneForUser !== '') {
                            $otpResult = send_staff_login_otp($pdo, $otpPhoneForUser);
                            if (!empty($otpResult['ok'])) {
                                $_SESSION['staff_pending_2fa_user_id'] = (int)$user['id'];
                                $teacherOtpStep = true;
                                $teacherOtpNotice = 'Enter the SMS code to finish signing in as admin.';
                            } else {
                                $teacherLoginError = (string)($otpResult['message'] ?? 'Could not send a verification code. Try SMS OTP login.');
                            }
                        } else {
                            complete_staff_portal_login($user);
                            header('Location: ' . BASE_URL . 'dashboard.php');
                            exit;
                        }
                    }
                }
            } catch (PDOException $exception) {
                error_log('Inline teacher login error: ' . $exception->getMessage());
                $teacherLoginError = 'Unable to process your login. Please try again.';
            }
        }
    }
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (
        isset($_POST['teacher_otp_send'])
        || isset($_POST['teacher_otp_verify'])
        || isset($_POST['teacher_otp_resend'])
        || isset($_POST['teacher_otp_cancel'])
        || (isset($_POST['teacher_otp']) && $_POST['teacher_otp'] !== '')
    )
) {
    $activeSection = 'teacher-login';
    require_once $homepageRoot . '/includes/helpers.php';
    require_once $homepageRoot . '/student/otp_helpers.php';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $teacherLoginError = 'Your session expired. Please try again.';
    } elseif (isset($_POST['teacher_otp_cancel'])) {
        unset($_SESSION['staff_login_otp_phone'], $_SESSION['staff_pending_2fa_user_id']);
        $teacherOtpStep = false;
    } elseif (!($pdo instanceof PDO)) {
        $teacherLoginError = 'Unable to connect to the server. Please try again later.';
    } elseif (isset($_POST['teacher_otp_verify']) || (isset($_POST['teacher_otp']) && $_POST['teacher_otp'] !== '')) {
        $posted = normalize_phone((string)($_POST['teacher_username'] ?? current_staff_login_otp_phone()));
        $otp = (string)($_POST['teacher_otp'] ?? '');
        $newPassword = (string)($_POST['teacher_new_password'] ?? '');
        $newConfirm = (string)($_POST['teacher_new_password_confirm'] ?? '');
        $teacherOtpStep = true;
        if ($newPassword !== '' && $newPassword !== $newConfirm) {
            $teacherLoginError = 'New passwords do not match.';
        } elseif (!valid_lk_phone($posted)) {
            $teacherLoginError = 'Enter a valid Sri Lankan mobile number.';
        } else {
            try {
                $result = verify_staff_login_otp($pdo, $posted, $otp, $newPassword);
            } catch (Throwable $e) {
                error_log('Staff OTP verify error: ' . $e->getMessage());
                $result = ['ok' => false, 'message' => 'Unable to verify the code. Please try again.'];
            }
            if (!empty($result['ok']) && !empty($result['user'])) {
                $pending = (int)($_SESSION['staff_pending_2fa_user_id'] ?? 0);
                if ($pending > 0 && (int)$result['user']['id'] !== $pending) {
                    $teacherLoginError = 'Sign in with the same admin account that requested the code.';
                } else {
                    unset($_SESSION['staff_pending_2fa_user_id']);
                    complete_staff_portal_login($result['user']);
                    header('Location: ' . BASE_URL . 'dashboard.php');
                    exit;
                }
            }
            $teacherLoginError = (string)($result['message'] ?? 'Could not verify the code.');
        }
    } else {
        $posted = normalize_phone((string)($_POST['teacher_phone'] ?? $_POST['teacher_username'] ?? ''));
        if (!valid_lk_phone($posted)) {
            $teacherLoginError = 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.';
        } else {
            $result = send_staff_login_otp($pdo, $posted);
            $teacherOtpStep = !empty($result['show_otp']);
            if ($result['ok']) {
                $teacherOtpNotice = $result['message'];
            } else {
                $teacherLoginError = $result['message'];
            }
        }
    }
}

if (!empty($_SESSION['staff_login_otp_phone']) || !empty($_SESSION['staff_pending_2fa_user_id'])) {
    $teacherOtpStep = true;
}

// Student / parent phone + OTP login removed — Google only.
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (
        isset($_POST['student_login'])
        || isset($_POST['student_otp_send'])
        || isset($_POST['student_otp_verify'])
        || isset($_POST['student_otp_resend'])
        || isset($_POST['student_otp_cancel'])
        || isset($_POST['student_device_otp_verify'])
        || isset($_POST['student_device_otp_resend'])
        || isset($_POST['student_device_otp_cancel'])
        || (isset($_POST['student_otp']) && $_POST['student_otp'] !== '')
        || (isset($_POST['student_device_otp']) && $_POST['student_device_otp'] !== '')
        || isset($_POST['parent_otp_send'])
        || isset($_POST['parent_otp_resend'])
        || isset($_POST['parent_otp_verify'])
        || isset($_POST['parent_otp_cancel'])
    )
) {
    $isParent = isset($_POST['parent_otp_send'])
        || isset($_POST['parent_otp_resend'])
        || isset($_POST['parent_otp_verify'])
        || isset($_POST['parent_otp_cancel']);
    $activeSection = $isParent ? 'parent-login' : 'student-login';
    $msg = 'Phone and SMS/OTP login are no longer available. Please sign in with Google.';
    if ($isParent) {
        $parentLoginError = $msg;
        unset($_SESSION['parent_otp_phone']);
        $parentOtpStep = false;
    } else {
        $studentLoginError = $msg;
        unset(
            $_SESSION['student_login_otp_phone'],
            $_SESSION['student_pending_login_user_id'],
            $_SESSION['student_device_otp'],
            $_SESSION['student_device_replace_label']
        );
        $studentOtpStep = false;
        $studentDeviceOtpStep = false;
    }
}
