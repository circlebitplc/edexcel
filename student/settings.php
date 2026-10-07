<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/otp_helpers.php';

require_student();
ensure_campus_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId <= 0 && !empty($_SESSION['username'])) {
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'student' AND deleted_at IS NULL LIMIT 1");
    $uStmt->execute([$_SESSION['username']]);
    $studentId = (int)$uStmt->fetchColumn();
}
if ($studentId <= 0 && !empty($_SESSION['google_email'])) {
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE google_email = ? AND role = 'student' AND deleted_at IS NULL LIMIT 1");
    $uStmt->execute([$_SESSION['google_email']]);
    $studentId = (int)$uStmt->fetchColumn();
}

function student_current_login_phone(PDO $pdo, int $studentId): string
{
    $stmt = $pdo->prepare("
        SELECT u.username, sp.whatsapp_number
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ? AND u.role = 'student' AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $fromProfile = student_normalize_lk_phone((string)($row['whatsapp_number'] ?? ''));
    if (valid_lk_phone($fromProfile)) {
        return $fromProfile;
    }
    return student_normalize_lk_phone((string)($row['username'] ?? ''));
}

function student_profile_column_values(PDO $pdo, array $data): array
{
    $allowed = ['full_name', 'email', 'parent_name', 'parent_whatsapp', 'website', 'facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
    $out = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $data)) {
            continue;
        }
        if (!campus_column_exists($pdo, 'student_profiles', $col)) {
            continue;
        }
        $out[$col] = $data[$col];
    }
    return $out;
}

function student_upsert_profile(PDO $pdo, int $studentId, array $data): void
{
    $cols = student_profile_column_values($pdo, $data);
    if ($cols === []) {
        throw new RuntimeException('Unable to save your profile right now.');
    }

    $stmt = $pdo->prepare('SELECT user_id FROM student_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    if ($stmt->fetchColumn() !== false) {
        $sets = [];
        $vals = [];
        foreach ($cols as $col => $value) {
            $sets[] = '`' . $col . '` = ?';
            $vals[] = $value;
        }
        $vals[] = $studentId;
        $pdo->prepare('UPDATE student_profiles SET ' . implode(', ', $sets) . ' WHERE user_id = ?')
            ->execute($vals);
        return;
    }

    $names = array_keys($cols);
    $placeholders = implode(', ', array_fill(0, count($names), '?'));
    $quoted = implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', $names));
    $pdo->prepare('INSERT INTO student_profiles (user_id, ' . $quoted . ') VALUES (?, ' . $placeholders . ')')
        ->execute(array_merge([$studentId], array_values($cols)));
}

function student_collect_profile_post(): array
{
    global $pdo;
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    if ($fullName === '' || mb_strlen($fullName) < 2) {
        throw new RuntimeException('Please enter your full name.');
    }

    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
    }

    $parentName = trim((string)($_POST['parent_name'] ?? ''));
    $parentRaw = trim((string)($_POST['parent_whatsapp'] ?? ''));
    $parentPhone = function_exists('student_parent_phone_normalize')
        ? student_parent_phone_normalize($parentRaw)
        : ($parentRaw === '' ? '' : campus_normalize_phone($parentRaw));
    $parentOk = function_exists('student_parent_phone_valid')
        ? student_parent_phone_valid($parentPhone)
        : (function_exists('valid_lk_phone') && valid_lk_phone($parentPhone));
    if (!$parentOk) {
        throw new RuntimeException('Enter a valid parent WhatsApp number, e.g. 0771234567.');
    }
    $ownPhone = '';
    if ($pdo instanceof PDO) {
        $ownPhone = function_exists('student_own_login_phone')
            ? student_own_login_phone($pdo, (int)($_SESSION['user_id'] ?? 0))
            : student_current_login_phone($pdo, (int)($_SESSION['user_id'] ?? 0));
    }
    if ($ownPhone !== '' && $parentPhone === $ownPhone) {
        throw new RuntimeException('Use your parent or guardian’s number, not your own mobile number.');
    }

    $data = [
        'full_name' => $fullName,
        'email' => $email !== '' ? $email : null,
        'parent_name' => $parentName !== '' ? $parentName : null,
        'parent_whatsapp' => $parentPhone !== '' ? $parentPhone : null,
    ];
    foreach (student_social_networks() as $key => $_meta) {
        $url = student_normalize_social_url((string)($_POST[$key] ?? ''), $key);
        $data[$key] = $url !== '' ? $url : null;
    }
    return $data;
}

function student_settings_wants_json(): bool
{
    if (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1') {
        return true;
    }
    $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xrw === 'xmlhttprequest') {
        return true;
    }
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json');
}

function student_settings_json(bool $ok, string $message, array $extra = [], int $code = 0): void
{
    if ($code === 0) {
        $code = $ok ? 200 : 400;
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

function student_profile_client_payload(array $data): array
{
    $profile = [];
    foreach ($data as $key => $value) {
        $profile[$key] = $value === null ? '' : (string)$value;
    }
    $name = trim((string)($profile['full_name'] ?? ''));
    $initials = 'S';
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        } else {
            $initials = strtoupper(substr($name, 0, 1));
        }
    }
    return [
        'profile' => $profile,
        'display_name' => $name,
        'initials' => $initials,
        'social' => function_exists('student_social_icon_links') ? student_social_icon_links($profile) : [],
    ];
}

$wantsJson = student_settings_wants_json();

if (isset($_POST['form']) && (string)$_POST['form'] === 'skip_parent_phone') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['student_error'] = 'Your security token expired. Please refresh the page and try again.';
    } else {
        $_SESSION['parent_phone_deferred'] = 1;
    }
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['dismiss_phone_modal']) || (isset($_POST['form']) && (string)$_POST['form'] === 'dismiss_phone_modal')) {
    $_SESSION['dismiss_phone_modal'] = true;
    if ($wantsJson || (function_exists('is_ajax_request') && is_ajax_request())) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    $target = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your security token expired. Please refresh the page and try again.');
        }

        $form = (string)($_POST['form'] ?? 'password');

        if ($form === 'parent_required') {
            $result = student_save_parent_phone(
                $pdo,
                $studentId,
                (string)($_POST['parent_whatsapp'] ?? ''),
                (string)($_POST['parent_name'] ?? ''),
                (string)($_POST['dial_code'] ?? ''),
                (string)($_POST['country_iso'] ?? '')
            );
            if (empty($result['ok'])) {
                throw new RuntimeException((string)($result['message'] ?? 'Could not save the parent number.'));
            }
            if ($wantsJson) {
                student_settings_json(true, (string)$result['message'], [
                    'parent_whatsapp' => (string)($result['phone'] ?? ''),
                    'parent_name' => (string)($result['parent_name'] ?? ''),
                ]);
            }
            $_SESSION['student_success'] = (string)$result['message'];
            header('Location: dashboard.php');
            exit;
        }

        if ($form === 'rotate_parent_link') {
            $token = classroom_parent_rotate_token($pdo, $studentId);
            if ($token === '') {
                throw new RuntimeException('Could not create a new parent link.');
            }
            $_SESSION['student_success'] = 'The old parent link no longer works. Copy the new one below.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'parent_wa_verify') {
            $pending = $_SESSION['parent_wa_pending'] ?? null;
            $otp = preg_replace('/\D+/', '', (string)($_POST['otp'] ?? '')) ?? '';
            if (!is_array($pending) || empty($pending['phone']) || empty($pending['hash'])) {
                throw new RuntimeException('Request a new code for the parent WhatsApp number.');
            }
            if ((int)($pending['expires'] ?? 0) < time()) {
                unset($_SESSION['parent_wa_pending']);
                throw new RuntimeException('That code expired. Save the parent number again.');
            }
            if ((int)($pending['attempts'] ?? 0) >= 5) {
                unset($_SESSION['parent_wa_pending']);
                throw new RuntimeException('Too many incorrect codes. Save the parent number again.');
            }
            if (!password_verify($otp, (string)$pending['hash'])) {
                $_SESSION['parent_wa_pending']['attempts'] = (int)($pending['attempts'] ?? 0) + 1;
                throw new RuntimeException('Incorrect code. Check the WhatsApp message.');
            }
            student_upsert_profile($pdo, $studentId, ['parent_whatsapp' => (string)$pending['phone']]);
            unset($_SESSION['parent_wa_pending']);
            $_SESSION['student_success'] = 'Parent WhatsApp number confirmed.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'parent_wa_cancel') {
            unset($_SESSION['parent_wa_pending']);
            $_SESSION['student_success'] = 'Parent WhatsApp change cancelled.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'parent' || $form === 'profile') {
            $data = student_collect_profile_post();
            $curStmt = $pdo->prepare('SELECT parent_whatsapp FROM student_profiles WHERE user_id = ? LIMIT 1');
            $curStmt->execute([$studentId]);
            $currentParent = campus_normalize_phone((string)$curStmt->fetchColumn());
            $newParent = campus_normalize_phone((string)($data['parent_whatsapp'] ?? ''));
            if ($newParent !== '' && $newParent !== $currentParent) {
                unset($data['parent_whatsapp']);
                student_upsert_profile($pdo, $studentId, $data);
                $_SESSION['student_full_name'] = (string)$data['full_name'];
                $otp = (string)random_int(100000, 999999);
                $_SESSION['parent_wa_pending'] = [
                    'phone' => $newParent,
                    'hash' => password_hash($otp, PASSWORD_DEFAULT),
                    'expires' => time() + 600,
                    'attempts' => 0,
                ];
                $sent = function_exists('otp_send_via_whatsapp')
                    ? otp_send_via_whatsapp(
                        $newParent,
                        "Edexcel College\n\nConfirm this parent WhatsApp number with code: *{$otp}*\n\nThis code expires in 10 minutes."
                    )
                    : false;
                otp_support_log_record($pdo, [
                    'purpose' => 'parent_whatsapp_change',
                    'phone' => $newParent,
                    'otp' => $otp,
                    'channel' => 'whatsapp',
                    'user_id' => $studentId,
                    'sent' => $sent,
                ]);
                $msg = $sent
                    ? 'We sent a code to the new parent WhatsApp. Enter it below to confirm.'
                    : 'Enter the code we prepared for the new parent WhatsApp. If the message is delayed, save the number again.';
                if ($wantsJson) {
                    student_settings_json(true, $msg, array_merge(student_profile_client_payload($data), [
                        'need_parent_otp' => true,
                        'parent_otp_phone' => $newParent,
                    ]));
                }
                $_SESSION['student_success'] = $msg;
                header('Location: dashboard.php?tab=settings');
                exit;
            }
            student_upsert_profile($pdo, $studentId, $data);
            $_SESSION['student_full_name'] = (string)$data['full_name'];
            if ($wantsJson) {
                student_settings_json(true, 'Saved', student_profile_client_payload($data));
            }
            $_SESSION['student_success'] = 'Your profile has been saved.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'logout_others') {
            $svc = student_devices($pdo);
            if (!$svc) {
                throw new RuntimeException('Devices could not be updated.');
            }
            $count = $svc->logoutOtherDevices($studentId);
            $_SESSION['student_success'] = $count > 0
                ? 'Other devices were signed out. This device stays signed in.'
                : 'No other devices were signed in.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'revoke_device') {
            $deviceId = (int)($_POST['device_id'] ?? 0);
            $svc = student_devices($pdo);
            if (!$svc || $deviceId < 1) {
                throw new RuntimeException('That device could not be removed.');
            }
            $currentId = (int)($_SESSION['student_device_id'] ?? 0);
            $removed = $svc->revokeDevice($studentId, $deviceId, true);
            if (!$removed) {
                throw new RuntimeException('That device is already removed.');
            }
            if ($currentId === $deviceId) {
                student_login_notice_set('device_removed');
                destroy_app_session();
                header('Location: ' . student_login_url());
                exit;
            }
            $_SESSION['student_success'] = 'Device removed. You can register another device when you sign in.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'phone_cancel') {
            unset($_SESSION['student_phone_change_pending']);
            $_SESSION['student_success'] = 'Mobile number change cancelled.';
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        if ($form === 'phone_request' || $form === 'phone_resend' || $form === 'phone_verify') {
            $newPhone = student_normalize_lk_phone((string)($_POST['new_whatsapp'] ?? $_SESSION['student_phone_change_pending'] ?? ''));
            $currentPhone = student_current_login_phone($pdo, $studentId);

            if ($form === 'phone_verify') {
                $otp = (string)($_POST['otp'] ?? '');
                $result = student_phone_change_verify($pdo, $studentId, $newPhone, $otp);
                if (empty($result['ok'])) {
                    throw new RuntimeException((string)($result['message'] ?? 'Could not verify the code.'));
                }
                apply_student_phone_change($pdo, $studentId, (string)$result['new_phone']);
                $_SESSION['student_success'] = 'Your mobile / WhatsApp number has been updated. Use this number the next time you sign in.';
                header('Location: dashboard.php?tab=settings');
                exit;
            }

            if (!valid_lk_phone($newPhone)) {
                throw new RuntimeException('Enter a valid Sri Lankan mobile number, e.g. 0771234567.');
            }
            if ($currentPhone !== '' && $newPhone === $currentPhone) {
                throw new RuntimeException('This is already your registered mobile number.');
            }

            $phoneAction = (string)($_POST['phone_action'] ?? 'send_otp');

            if ($form === 'phone_request' && $phoneAction === 'save_direct') {
                apply_student_phone_change($pdo, $studentId, $newPhone);
                $_SESSION['student_success'] = 'Your mobile / WhatsApp number has been successfully saved.';
                unset($_SESSION['dismiss_phone_modal']);
                if ($wantsJson) {
                    student_settings_json(true, 'Your mobile / WhatsApp number has been successfully saved.', [
                        'phone' => $newPhone,
                        'display_phone' => function_exists('campus_display_phone') ? campus_display_phone($newPhone) : ('+' . $newPhone),
                    ]);
                }
                $redirectTo = 'dashboard.php?tab=settings';
                if (!empty($_POST['redirect_to'])) {
                    $cand = (string)$_POST['redirect_to'];
                    if (str_starts_with($cand, 'dashboard.php') || str_starts_with($cand, '/student/dashboard.php')) {
                        $redirectTo = $cand;
                    }
                }
                header('Location: ' . $redirectTo);
                exit;
            }

            $result = $form === 'phone_resend'
                ? student_phone_change_resend($pdo, $studentId, $newPhone)
                : student_phone_change_request($pdo, $studentId, $newPhone);

            if (!empty($result['ok'])) {
                $_SESSION['student_success'] = (string)$result['message'];
            } else {
                throw new RuntimeException((string)($result['message'] ?? 'Unable to send the verification code.'));
            }
            header('Location: dashboard.php?tab=settings');
            exit;
        }

        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            throw new RuntimeException('Please complete all password fields.');
        }
        if (strlen($new) < 8) {
            throw new RuntimeException('The new password must contain at least 8 characters.');
        }
        if ($new !== $confirm) {
            throw new RuntimeException('The new passwords do not match.');
        }

        $stmt = $pdo->prepare("
            SELECT id, password_hash
            FROM users
            WHERE id = ?
              AND role = 'student'
              AND deleted_at IS NULL
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current, (string)$user['password_hash'])) {
            throw new RuntimeException('Current password is incorrect.');
        }
        if (password_verify($new, (string)$user['password_hash'])) {
            throw new RuntimeException('The new password must be different from the current password.');
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'student'");
        $stmt->execute([$hash, $studentId]);

        $_SESSION['student_success'] = 'Your password has been changed successfully.';
    } catch (Throwable $e) {
        error_log('Student settings error: ' . $e->getMessage());
        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update your settings right now.';
        if ($wantsJson) {
            student_settings_json(false, $message);
        }
        $_SESSION['student_error'] = $message;
        if (!empty($_POST['redirect_to'])) {
            $cand = (string)$_POST['redirect_to'];
            if (str_starts_with($cand, 'dashboard.php') || str_starts_with($cand, '/student/dashboard.php')) {
                header('Location: ' . $cand);
                exit;
            }
        }
    }
}

if ($wantsJson) {
    student_settings_json(false, 'Nothing to save.');
}

header('Location: dashboard.php?tab=settings');
exit;
