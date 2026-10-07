<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/sms_gateway.php';
require_once dirname(__DIR__) . '/config/otp_support_log.php';
require_once __DIR__ . '/device_helpers.php';

function valid_lk_phone(string $phone): bool
{
    return (bool)preg_match('/^94(?:7\d{8})$/', $phone);
}

function otp_send_via_whatsapp(string $phone, string $message): bool
{
    try {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        require_once dirname(__DIR__) . '/config/whatsapp_gateway.php';
        global $pdo;
        $db = $pdo instanceof PDO ? $pdo : null;
        whatsapp_sender($db)->sendText($phone, $message);
        return true;
    } catch (Throwable $e) {
        error_log('OTP WhatsApp send failed: ' . $e->getMessage());
        return false;
    }
}

function otp_deliver_student(string $phone, string $message): bool
{
    require_once dirname(__DIR__) . '/config/sms_gateway.php';
    global $pdo;
    $db = $pdo instanceof PDO ? $pdo : null;
    if (student_otp_channel($db) === 'sms') {
        return sms_send($db, $phone, $message);
    }
    return otp_send_via_whatsapp($phone, $message);
}

function otp_send_whatsapp(string $phone, string $message): bool
{
    return otp_deliver_student($phone, $message);
}

function remember_student_reg_phone(string $phone): void
{
    $_SESSION['student_registration_phone'] = $phone;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    setcookie('student_reg_phone', $phone, [
        'expires' => time() + 12 * 60,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_student_reg_phone(): string
{
    $fromSession = trim((string)($_SESSION['student_registration_phone'] ?? ''));
    if ($fromSession !== '' && preg_match('/^94\d{9}$/', $fromSession)) {
        return $fromSession;
    }
    $fromCookie = trim((string)($_COOKIE['student_reg_phone'] ?? ''));
    if ($fromCookie !== '' && preg_match('/^94\d{9}$/', $fromCookie)) {
        $_SESSION['student_registration_phone'] = $fromCookie;
        return $fromCookie;
    }
    return '';
}

function send_whatsapp_otp(string $phone, string $otp): bool
{
    require_once dirname(__DIR__) . '/config/sms_gateway.php';
    global $pdo;
    $db = $pdo instanceof PDO ? $pdo : null;
    $viaSms = student_otp_channel($db) === 'sms';
    $message = $viaSms
        ? "Edexcel College code: {$otp}"
        : (
            "Edexcel College Student Registration\n\n" .
            "Your verification code is: *{$otp}*\n\n" .
            "This code expires in 10 minutes.\n" .
            "Do not share this code with anyone."
        );
    return otp_deliver_student($phone, $message);
}

function student_otp_pending(PDO $pdo, string $phone): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM student_registration_otps
        WHERE phone = ?
          AND verified_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function student_otp_create_and_send(PDO $pdo, string $phone, string $fullName, ?string $email, string $passwordHash): array
{
    return student_registration_otp_issue($pdo, $phone, $fullName, $email, $passwordHash);
}

function student_otp_resend_wait_seconds(PDO $pdo, string $phone): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, created_at, NOW()))
            FROM student_registration_otps
            WHERE phone = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$phone]);
        $wait = $stmt->fetchColumn();
        return $wait === false ? 0 : max(0, (int)$wait);
    } catch (Throwable $e) {
        return 0;
    }
}

function student_registration_otp_issue(
    PDO $pdo,
    string $phone,
    string $fullName,
    ?string $email,
    string $passwordHash
): array {
    $wait = student_otp_resend_wait_seconds($pdo, $phone);
    if ($wait > 0) {
        return [
            'ok' => false,
            'sent' => false,
            'wait' => $wait,
            'message' => "Please wait {$wait} seconds before requesting another OTP.",
        ];
    }

    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO student_registration_otps
            (phone, full_name, email, password_hash, otp_hash, expires_at)
        VALUES
            (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ");
    $stmt->execute([$phone, $fullName, $email, $passwordHash, $otpHash]);
    $id = (int)$pdo->lastInsertId();
    $sent = send_whatsapp_otp($phone, $otp);
    otp_support_log_record($pdo, [
        'purpose' => 'student_registration',
        'phone' => $phone,
        'otp' => $otp,
        'channel' => otp_support_current_channel($pdo),
        'sent' => $sent,
    ]);
    if (!$sent) {
        if ($id > 0) {
            try {
                $pdo->prepare('DELETE FROM student_registration_otps WHERE id = ?')->execute([$id]);
            } catch (Throwable $e) {
                // Keep going so the student can retry immediately.
            }
        }
        return [
            'ok' => false,
            'sent' => false,
            'wait' => 0,
            'message' => 'We could not send the verification code. Please try again.',
        ];
    }

    remember_student_reg_phone($phone);
    return ['ok' => true, 'sent' => true, 'wait' => 60, 'message' => ''];
}

function student_registration_otp_resend(PDO $pdo, string $phone): array
{
    $wait = student_otp_resend_wait_seconds($pdo, $phone);
    if ($wait > 0) {
        return [
            'ok' => false,
            'sent' => false,
            'wait' => $wait,
            'message' => "Please wait {$wait} seconds before requesting another OTP.",
        ];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM student_registration_otps
        WHERE phone = ?
          AND verified_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pending) {
        return [
            'ok' => false,
            'sent' => false,
            'wait' => 0,
            'message' => 'No pending registration was found. Please register again.',
        ];
    }

    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("
            UPDATE student_registration_otps
            SET otp_hash = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                attempts = 0,
                created_at = NOW()
            WHERE id = ?
        ")->execute([$otpHash, (int)$pending['id']]);
    } catch (Throwable $e) {
        $pdo->prepare("
            UPDATE student_registration_otps
            SET otp_hash = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                attempts = 0
            WHERE id = ?
        ")->execute([$otpHash, (int)$pending['id']]);
    }

    $sent = send_whatsapp_otp($phone, $otp);
    otp_support_log_record($pdo, [
        'purpose' => 'student_registration',
        'phone' => $phone,
        'otp' => $otp,
        'channel' => otp_support_current_channel($pdo),
        'sent' => $sent,
    ]);
    if (!$sent) {
        return [
            'ok' => true,
            'sent' => false,
            'wait' => 60,
            'message' => 'A new code is ready. If WhatsApp is delayed, wait 60 seconds and tap resend.',
        ];
    }

    remember_student_reg_phone($phone);
    return [
        'ok' => true,
        'sent' => true,
        'wait' => 60,
        'message' => 'A new code was sent. You can request another after 60 seconds.',
    ];
}

function phone_match_variants(string $phone): array
{
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    $variants = $phone === '' ? [] : [$phone];
    if (str_starts_with($phone, '94') && strlen($phone) === 11) {
        $variants[] = '0' . substr($phone, 2);
    }
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $variants[] = '94' . substr($phone, 1);
    }
    return array_values(array_unique($variants));
}

function ensure_staff_login_otp_table(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_login_otps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_staff_login_otps_phone (phone, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function find_staff_user_by_phone(PDO $pdo, string $phone): ?array
{
    $variants = phone_match_variants($phone);
    if ($variants === []) {
        return null;
    }
    $in = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM users u
        LEFT JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
        WHERE u.deleted_at IS NULL
          AND u.role IN ('teacher', 'admin')
          AND (
            u.username IN ($in)
            OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(t.phone, ''), '+', ''), ' ', ''), '-', ''), '.', '') IN ($in)
          )
        ORDER BY CASE WHEN u.role = 'teacher' THEN 0 ELSE 1 END, u.id
        LIMIT 1
    ");
    $stmt->execute(array_merge($variants, $variants));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function remember_staff_login_phone(string $phone): void
{
    $_SESSION['staff_login_otp_phone'] = $phone;
}

function current_staff_login_otp_phone(): string
{
    $phone = trim((string)($_SESSION['staff_login_otp_phone'] ?? ''));
    return preg_match('/^94\d{9}$/', $phone) ? $phone : '';
}

function send_staff_login_otp(PDO $pdo, string $phone): array
{
    ensure_staff_login_otp_table($pdo);
    require_once dirname(__DIR__) . '/config/sms_gateway.php';
    $user = find_staff_user_by_phone($pdo, $phone);
    if (!$user) {
        return ['ok' => false, 'message' => 'No teacher account was found for that mobile number.', 'show_otp' => false];
    }
    if (array_key_exists('is_active', $user) && (int)$user['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'This account is currently inactive.', 'show_otp' => false];
    }

    $stmt = $pdo->prepare("SELECT created_at FROM staff_login_otps WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$phone]);
    $last = $stmt->fetchColumn();
    if ($last && strtotime((string)$last) > time() - 60) {
        remember_staff_login_phone($phone);
        return ['ok' => true, 'message' => 'A code was already sent. Enter it below, or wait 60 seconds to resend.', 'show_otp' => true];
    }

    $otp = (string)random_int(100000, 999999);
    $sent = sms_send($pdo, $phone, "Edexcel College staff login code: {$otp}");
    otp_support_log_record($pdo, [
        'purpose' => 'staff_login',
        'phone' => $phone,
        'otp' => $otp,
        'channel' => 'sms',
        'user_id' => (int)$user['id'],
        'sent' => $sent,
    ]);
    if (!$sent) {
        $detail = trim(sms_send_last_error());
        return [
            'ok' => false,
            'show_otp' => false,
            'message' => $detail !== ''
                ? ('Could not send SMS OTP. ' . $detail)
                : 'Could not send SMS OTP. Check the SMS gateway in Admin → Settings.',
        ];
    }

    $pdo->prepare("
        INSERT INTO staff_login_otps (user_id, phone, otp_hash, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ")->execute([(int)$user['id'], $phone, password_hash($otp, PASSWORD_DEFAULT)]);
    remember_staff_login_phone($phone);
    return [
        'ok' => true,
        'show_otp' => true,
        'message' => 'We sent a 6-digit login code by SMS. Enter it below.',
    ];
}

function verify_staff_login_otp(PDO $pdo, string $phone, string $otp, string $newPassword = ''): array
{
    ensure_staff_login_otp_table($pdo);
    $otp = preg_replace('/\D+/', '', $otp) ?? '';
    if (!preg_match('/^\d{6}$/', $otp)) {
        return ['ok' => false, 'message' => 'Enter the 6-digit verification code.'];
    }
    $variants = phone_match_variants($phone);
    $in = implode(',', array_fill(0, max(1, count($variants)), '?'));
    $stmt = $pdo->prepare("
        SELECT *
        FROM staff_login_otps
        WHERE phone IN ($in)
          AND verified_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute($variants !== [] ? $variants : [$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'No login code was found. Request a new OTP.'];
    }
    if ((int)$row['attempts'] >= 5) {
        return ['ok' => false, 'message' => 'Too many incorrect attempts. Request a new OTP.'];
    }
    if (!password_verify($otp, (string)$row['otp_hash'])) {
        $pdo->prepare("UPDATE staff_login_otps SET attempts = attempts + 1 WHERE id = ?")->execute([(int)$row['id']]);
        return ['ok' => false, 'message' => 'Incorrect code. Check the SMS and try again.'];
    }

    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $userStmt->execute([(int)$row['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: find_staff_user_by_phone($pdo, $phone);
    if (!$user) {
        return ['ok' => false, 'message' => 'No teacher account was found for that mobile number.'];
    }
    if (array_key_exists('is_active', $user) && (int)$user['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'This account is currently inactive.'];
    }

    $pdo->prepare("UPDATE staff_login_otps SET verified_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);

    $newPassword = trim($newPassword);
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
        }
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            (int)$user['id'],
        ]);
    }

    return ['ok' => true, 'user' => $user, 'message' => 'Signed in.'];
}

function staff_whatsapp_for_user(PDO $pdo, array $user): string
{
    $candidates = [(string)($user['username'] ?? '')];
    if (!empty($user['teacher_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT phone FROM teachers WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(int)$user['teacher_id']]);
            $candidates[] = (string)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }
    }
    foreach ($candidates as $raw) {
        $phone = function_exists('normalize_phone') ? normalize_phone($raw) : preg_replace('/\D+/', '', $raw);
        if (is_string($phone) && valid_lk_phone($phone)) {
            return $phone;
        }
    }
    return '';
}

function complete_staff_portal_login(array $user): void
{
    $role = strtolower(trim((string)($user['role'] ?? 'teacher')));
    if (!in_array($role, ['teacher', 'admin'], true)) {
        $role = 'teacher';
    }
    if (function_exists('regenerate_session')) {
        regenerate_session();
    }
    if (function_exists('clear_cross_portal_session')) {
        clear_cross_portal_session('staff');
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $role;
    if ($role === 'admin') {
        // Administrator who also performs teaching duties
        $_SESSION['teacher_id'] = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : null;
        $_SESSION['can_teach'] = true;
        $_SESSION['has_teacher_role'] = true;
        $_SESSION['teacher_access'] = true;
    } else {
        $_SESSION['teacher_id'] = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : null;
    }
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    unset($_SESSION['staff_login_otp_phone'], $_SESSION['staff_pending_2fa_user_id'], $_SESSION['staff_pending_totp_user_id'], $_SESSION['staff_pending_totp_username']);
    if (function_exists('app_theme_on_login')) {
        global $pdo;
        app_theme_on_login(($pdo instanceof PDO) ? $pdo : null, $user);
    } elseif (function_exists('app_theme_remember_user')) {
        app_theme_remember_user($user);
    }
}

function ensure_student_login_otp_table(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_login_otps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_student_login_otps_phone (phone, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function find_student_user_by_phone(PDO $pdo, string $phone): ?array
{
    $variants = phone_match_variants($phone);
    if ($variants === []) {
        return null;
    }
    $in = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.deleted_at IS NULL
          AND u.role = 'student'
          AND (
            u.username IN ($in)
            OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(sp.whatsapp_number, ''), '+', ''), ' ', ''), '-', ''), '.', '') IN ($in)
          )
        ORDER BY u.id
        LIMIT 1
    ");
    $stmt->execute(array_merge($variants, $variants));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function remember_student_login_phone(string $phone): void
{
    $_SESSION['student_login_otp_phone'] = $phone;
}

function current_student_login_otp_phone(): string
{
    $phone = trim((string)($_SESSION['student_login_otp_phone'] ?? ''));
    return preg_match('/^94\d{9}$/', $phone) ? $phone : '';
}

function send_student_login_otp(PDO $pdo, string $phone): array
{
    ensure_student_login_otp_table($pdo);
    $user = find_student_user_by_phone($pdo, $phone);
    if (!$user) {
        return ['ok' => false, 'message' => 'No student account was found for that number.', 'show_otp' => false];
    }
    if (array_key_exists('is_active', $user) && (int)$user['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'This account is currently inactive.', 'show_otp' => false];
    }

    $wait = 0;
    try {
        $waitStmt = $pdo->prepare("
            SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, created_at, NOW()))
            FROM student_login_otps
            WHERE phone = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $waitStmt->execute([$phone]);
        $wait = (int)($waitStmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $wait = 0;
    }
    if ($wait > 0) {
        remember_student_login_phone($phone);
        return ['ok' => true, 'message' => "A code was already sent. Enter it below, or wait {$wait} seconds to resend.", 'show_otp' => true];
    }

    $otp = (string)random_int(100000, 999999);
    $pdo->prepare("
        INSERT INTO student_login_otps (user_id, phone, otp_hash, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ")->execute([(int)$user['id'], $phone, password_hash($otp, PASSWORD_DEFAULT)]);
    remember_student_login_phone($phone);
    require_once dirname(__DIR__) . '/config/sms_gateway.php';
    $channelName = student_otp_channel_name($pdo);
    $viaSms = student_otp_channel($pdo) === 'sms';
    $sent = otp_deliver_student(
        $phone,
        $viaSms
            ? "Edexcel College login code: {$otp}"
            : "Edexcel College student login\n\nYour sign-in code is: *{$otp}*\n\nThis code expires in 10 minutes. Do not share this code."
    );
    otp_support_log_record($pdo, [
        'purpose' => 'student_login',
        'phone' => $phone,
        'otp' => $otp,
        'channel' => $viaSms ? 'sms' : 'whatsapp',
        'user_id' => (int)$user['id'],
        'sent' => $sent,
    ]);
    return [
        'ok' => true,
        'show_otp' => true,
        'message' => $sent
            ? "We sent a 6-digit login code by {$channelName}. Enter it below."
            : "Your login code is ready. If {$channelName} is delayed, tap Resend in a minute.",
    ];
}

function verify_student_login_otp(PDO $pdo, string $phone, string $otp, string $newPassword = ''): array
{
    ensure_student_login_otp_table($pdo);
    $otp = preg_replace('/\D+/', '', $otp) ?? '';
    if (!preg_match('/^\d{6}$/', $otp)) {
        return ['ok' => false, 'message' => 'Enter the 6-digit verification code.'];
    }
    $variants = phone_match_variants($phone);
    $in = implode(',', array_fill(0, max(1, count($variants)), '?'));
    $stmt = $pdo->prepare("
        SELECT *
        FROM student_login_otps
        WHERE phone IN ($in)
          AND verified_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute($variants !== [] ? $variants : [$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'No login code was found. Request a new OTP.'];
    }
    if ((int)$row['attempts'] >= 5) {
        return ['ok' => false, 'message' => 'Too many incorrect attempts. Request a new OTP.'];
    }
    if (!password_verify($otp, (string)$row['otp_hash'])) {
        $pdo->prepare("UPDATE student_login_otps SET attempts = attempts + 1 WHERE id = ?")->execute([(int)$row['id']]);
        return ['ok' => false, 'message' => 'Incorrect code. Check the message and try again.'];
    }

    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND role = 'student' LIMIT 1");
    $userStmt->execute([(int)$row['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: find_student_user_by_phone($pdo, $phone);
    if (!$user) {
        return ['ok' => false, 'message' => 'No student account was found for that number.'];
    }

    $pdo->prepare("UPDATE student_login_otps SET verified_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
    mark_student_whatsapp_otp_verified($pdo, (int)$user['id']);

    $newPassword = trim($newPassword);
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
        }
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            (int)$user['id'],
        ]);
    }

    return ['ok' => true, 'user' => $user, 'message' => 'Signed in.'];
}

function student_last_login_is_set(mixed $value): bool
{
    if ($value instanceof DateTimeInterface) {
        return true;
    }
    $text = strtolower(trim((string)($value ?? '')));
    return $text !== '' && $text !== 'null' && $text !== '0000-00-00 00:00:00';
}

function ensure_users_last_login_column(?PDO $pdo): void
{
    static $done = false;
    if ($done || !($pdo instanceof PDO) || $pdo->inTransaction()) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
        if (!$col || !$col->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL');
        }
    } catch (Throwable $e) {
        error_log('ensure_users_last_login_column: ' . $e->getMessage());
    }

    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'student_login_count'");
        if (!$col || !$col->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN student_login_count INT NOT NULL DEFAULT 0');
        }
    } catch (Throwable $e) {
        error_log('ensure_users_student_login_count: ' . $e->getMessage());
    }

    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_review_prompt_shown_at'");
        if (!$col || !$col->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN google_review_prompt_shown_at DATETIME NULL');
        }
    } catch (Throwable $e) {
        error_log('ensure_users_google_review_prompt: ' . $e->getMessage());
    }

    try {
        ensure_student_login_otp_table($pdo);
        $pdo->exec("
            UPDATE users u
            INNER JOIN (
                SELECT user_id, MIN(verified_at) AS first_seen
                FROM student_login_otps
                WHERE verified_at IS NOT NULL
                GROUP BY user_id
            ) o ON o.user_id = u.id
            SET u.last_login_at = COALESCE(u.last_login_at, o.first_seen),
                u.student_login_count = GREATEST(IFNULL(u.student_login_count, 0), 1)
            WHERE u.role = 'student'
        ");
    } catch (Throwable $e) {
        error_log('ensure_users_last_login_column otp repair: ' . $e->getMessage());
    }

    try {
        $pdo->exec("
            UPDATE users
            SET student_login_count = GREATEST(IFNULL(student_login_count, 0), 1)
            WHERE role = 'student'
              AND last_login_at IS NOT NULL
              AND IFNULL(student_login_count, 0) = 0
        ");
    } catch (Throwable $e) {
        error_log('ensure_users_last_login_column count repair: ' . $e->getMessage());
    }

    try {
        $flag = '0';
        $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'student_last_login_backfill_v3' LIMIT 1");
        $st->execute();
        $flag = (string)($st->fetchColumn() ?: '0');
        if ($flag !== '1') {
            $pdo->exec("
                UPDATE users
                SET last_login_at = COALESCE(last_login_at, updated_at, created_at, NOW()),
                    student_login_count = GREATEST(IFNULL(student_login_count, 0), 1)
                WHERE role = 'student'
                  AND created_at < CURDATE()
                  AND IFNULL(student_login_count, 0) = 0
                  AND last_login_at IS NULL
            ");
            $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES ('student_last_login_backfill_v3', '1')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute();
        }
    } catch (Throwable $e) {
        error_log('ensure_users_last_login_column backfill: ' . $e->getMessage());
    }
}

/**
 * Number already proved by WhatsApp or SMS OTP — keep the password field.
 */
function student_whatsapp_otp_is_verified(PDO $pdo, array $user): bool
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) {
        return false;
    }

    try {
        $st = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1');
        $st->execute([$userId]);
        $profile = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $via = strtolower(trim((string)($profile['phone_verified_via'] ?? '')));
        if ($via === 'whatsapp' || $via === 'sms') {
            return true;
        }
        if (student_last_login_is_set($profile['whatsapp_verified_at'] ?? null)) {
            return true;
        }
    } catch (Throwable $e) {
        error_log('student_whatsapp_otp_is_verified profile: ' . $e->getMessage());
    }

    try {
        ensure_student_login_otp_table($pdo);
        $st = $pdo->prepare('
            SELECT 1
            FROM student_login_otps
            WHERE user_id = ? AND verified_at IS NOT NULL
            LIMIT 1
        ');
        $st->execute([$userId]);
        if ($st->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
        error_log('student_whatsapp_otp_is_verified login otp: ' . $e->getMessage());
    }

    $phone = (string)($user['username'] ?? '');
    if (function_exists('normalize_phone')) {
        $phone = normalize_phone($phone);
    } else {
        $phone = student_normalize_lk_phone($phone);
    }
    if ($phone !== '' && function_exists('phone_match_variants')) {
        try {
            $variants = phone_match_variants($phone);
            if ($variants !== []) {
                $in = implode(',', array_fill(0, count($variants), '?'));
                $st = $pdo->prepare("
                    SELECT 1
                    FROM student_registration_otps
                    WHERE phone IN ($in) AND verified_at IS NOT NULL
                    LIMIT 1
                ");
                $st->execute($variants);
                if ($st->fetchColumn()) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            error_log('student_whatsapp_otp_is_verified registration: ' . $e->getMessage());
        }
    }

    return false;
}

function mark_student_whatsapp_otp_verified(PDO $pdo, int $userId): void
{
    if ($userId < 1) {
        return;
    }
    $viaSms = function_exists('student_otp_channel') && student_otp_channel($pdo) === 'sms';
    try {
        $hasVia = false;
        $col = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'phone_verified_via'");
        $hasVia = $col && $col->fetch(PDO::FETCH_ASSOC);
        if ($viaSms && $hasVia) {
            $pdo->prepare("
                UPDATE student_profiles
                SET phone_verified_via = CASE
                        WHEN phone_verified_via IS NULL OR phone_verified_via = '' THEN 'sms'
                        ELSE phone_verified_via
                    END
                WHERE user_id = ?
            ")->execute([$userId]);
        } elseif ($hasVia) {
            $pdo->prepare("
                UPDATE student_profiles
                SET whatsapp_verified_at = COALESCE(whatsapp_verified_at, NOW()),
                    phone_verified_via = CASE
                        WHEN phone_verified_via IS NULL OR phone_verified_via = '' THEN 'whatsapp'
                        ELSE phone_verified_via
                    END
                WHERE user_id = ?
            ")->execute([$userId]);
        } elseif (!$viaSms) {
            $pdo->prepare("
                UPDATE student_profiles
                SET whatsapp_verified_at = COALESCE(whatsapp_verified_at, NOW())
                WHERE user_id = ?
            ")->execute([$userId]);
        }
    } catch (Throwable $e) {
        error_log('mark_student_whatsapp_otp_verified: ' . $e->getMessage());
    }
}

/**
 * True only when this student has never completed a student-portal sign-in
 * and the number has not been verified by WhatsApp or SMS OTP.
 */
function student_is_first_login(array $user, ?PDO $pdo = null): bool
{
    if ($pdo instanceof PDO && student_whatsapp_otp_is_verified($pdo, $user)) {
        return false;
    }
    if ((int)($user['student_login_count'] ?? 0) > 0) {
        return false;
    }
    if (student_last_login_is_set($user['last_login_at'] ?? null)) {
        return false;
    }

    $userId = (int)($user['id'] ?? 0);
    if ($pdo instanceof PDO && $userId > 0) {
        try {
            ensure_student_login_otp_table($pdo);
            $st = $pdo->prepare('
                SELECT MIN(verified_at)
                FROM student_login_otps
                WHERE user_id = ? AND verified_at IS NOT NULL
            ');
            $st->execute([$userId]);
            $verified = $st->fetchColumn();
            if (student_last_login_is_set($verified)) {
                mark_student_last_login($pdo, $userId);
                return false;
            }
        } catch (Throwable $e) {
            error_log('student_is_first_login: ' . $e->getMessage());
        }
    }

    if (!array_key_exists('last_login_at', $user) && !array_key_exists('student_login_count', $user)) {
        return false;
    }

    return true;
}

function mark_student_last_login(?PDO $pdo, int $userId): void
{
    if (!($pdo instanceof PDO) || $userId < 1) {
        return;
    }
    ensure_users_last_login_column($pdo);
    try {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
    } catch (Throwable $e) {
        error_log('mark_student_last_login time: ' . $e->getMessage());
    }
    // Repair / first-login metadata only — do not increment (see record_student_portal_login).
    try {
        $pdo->prepare('
            UPDATE users
            SET student_login_count = GREATEST(IFNULL(student_login_count, 0), 1)
            WHERE id = ?
        ')->execute([$userId]);
    } catch (Throwable $e) {
        error_log('mark_student_last_login count: ' . $e->getMessage());
    }
}

/**
 * Count a completed student portal sign-in (password / OTP / Google / device).
 * Triggers the Google review prompt exactly on the 3rd login.
 */
function record_student_portal_login(?PDO $pdo, int $userId): int
{
    if (!($pdo instanceof PDO) || $userId < 1) {
        return 0;
    }
    ensure_users_last_login_column($pdo);
    try {
        $pdo->prepare("
            UPDATE users
            SET last_login_at = NOW(),
                student_login_count = IFNULL(student_login_count, 0) + 1
            WHERE id = ? AND role = 'student'
        ")->execute([$userId]);
        $st = $pdo->prepare('SELECT student_login_count, google_review_prompt_shown_at FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $count = (int)($row['student_login_count'] ?? 0);
        if ($count === 3 && empty($row['google_review_prompt_shown_at'])) {
            $_SESSION['show_google_review_prompt'] = 1;
        }
        return $count;
    } catch (Throwable $e) {
        error_log('record_student_portal_login: ' . $e->getMessage());
        return 0;
    }
}

function student_google_review_url(): string
{
    return 'https://g.page/r/CSFab2Hr_d_qEAI/review';
}

function student_should_show_google_review_prompt(?PDO $pdo, int $userId): bool
{
    if (!empty($_SESSION['show_google_review_prompt'])) {
        return true;
    }
    if (!($pdo instanceof PDO) || $userId < 1) {
        return false;
    }
    ensure_users_last_login_column($pdo);
    try {
        $st = $pdo->prepare('
            SELECT student_login_count, google_review_prompt_shown_at
            FROM users
            WHERE id = ? AND role = \'student\'
            LIMIT 1
        ');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int)($row['student_login_count'] ?? 0) >= 3
            && empty($row['google_review_prompt_shown_at']);
    } catch (Throwable $e) {
        return false;
    }
}

function student_mark_google_review_prompt_shown(?PDO $pdo, int $userId): void
{
    unset($_SESSION['show_google_review_prompt']);
    if (!($pdo instanceof PDO) || $userId < 1) {
        return;
    }
    ensure_users_last_login_column($pdo);
    try {
        $pdo->prepare("
            UPDATE users
            SET google_review_prompt_shown_at = COALESCE(google_review_prompt_shown_at, NOW())
            WHERE id = ? AND role = 'student'
        ")->execute([$userId]);
    } catch (Throwable $e) {
        error_log('student_mark_google_review_prompt_shown: ' . $e->getMessage());
    }
}

/**
 * Password sign-in, or first-time OTP if the student has never signed in.
 *
 * @return array{ok:bool,user?:array,otp?:bool,show_otp?:bool,message?:string}
 */
function attempt_student_password_login(PDO $pdo, string $username, string $password): array
{
    ensure_users_last_login_column($pdo);
    $username = trim($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Please enter your mobile number.'];
    }

    $normalized = student_normalize_lk_phone($username);
    if (function_exists('normalize_phone')) {
        $normalized = normalize_phone($username);
    }
    $loginUsername = preg_match('/^94[0-9]{9}$/', $normalized) ? $normalized : $username;

    if (function_exists('login_is_locked') && login_is_locked($pdo, $loginUsername)) {
        return ['ok' => false, 'message' => 'Too many failed sign-in attempts. Wait 15 minutes and try again.'];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE username = ? AND role = 'student' AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$loginUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) {
        $user = find_student_user_by_phone($pdo, $normalized !== '' ? $normalized : $username);
    }

    if ($user && student_is_first_login($user, $pdo)) {
        if (!valid_lk_phone($normalized)) {
            return [
                'ok' => false,
                'otp' => true,
                'show_otp' => false,
                'message' => 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.',
            ];
        }
        $result = send_student_login_otp($pdo, $normalized);
        return [
            'ok' => !empty($result['ok']),
            'otp' => true,
            'show_otp' => !empty($result['show_otp']),
            'message' => (string)($result['message'] ?? ''),
        ];
    }

    if ($password === '') {
        return ['ok' => false, 'message' => 'Please enter your username and password.'];
    }

    if (!$user || empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
        if (function_exists('login_record_failure')) {
            login_record_failure($pdo, $loginUsername);
        }
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }
    if (array_key_exists('is_active', $user) && (int)$user['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'This account is currently inactive.'];
    }
    if (function_exists('login_clear_failures')) {
        login_clear_failures($pdo, $loginUsername);
    }
    return ['ok' => true, 'user' => $user];
}

function complete_student_portal_login(array $user, ?PDO $db = null): void
{
    regenerate_session();
    if (function_exists('clear_cross_portal_session')) {
        clear_cross_portal_session('student');
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['student_id'] = (int)$user['id'];
    $_SESSION['role'] = 'student';
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['student_login_otp_phone'], $_SESSION['teacher_id']);

    if (!($db instanceof PDO)) {
        global $pdo;
        $db = ($pdo instanceof PDO) ? $pdo : null;
    }

    $fullName = '';
    try {
        if ($db instanceof PDO) {
            record_student_portal_login($db, (int)$user['id']);
            $st = $db->prepare('SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1');
            $st->execute([(int)$user['id']]);
            $fullName = trim((string)($st->fetchColumn() ?: ''));
        }
    } catch (Throwable $e) {
        $fullName = '';
    }
    if ($fullName !== '') {
        $_SESSION['student_full_name'] = $fullName;
    } else {
        unset($_SESSION['student_full_name']);
    }
    if (function_exists('app_theme_on_login')) {
        app_theme_on_login($db instanceof PDO ? $db : null, $user);
    } elseif (function_exists('app_theme_remember_user')) {
        app_theme_remember_user($user);
    }
}

function student_post_login_url(): string
{
    $fallback = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/student/dashboard.php';
    $ret = trim((string)($_SESSION['classroom_sms_return'] ?? ''));
    unset($_SESSION['classroom_sms_return']);
    if ($ret === '') {
        return $fallback;
    }
    $path = (string)(parse_url($ret, PHP_URL_PATH) ?: '');
    if (!str_contains(str_replace('\\', '/', $path), '/classroom/join.php')) {
        return $fallback;
    }
    return $ret;
}

function student_normalize_lk_phone(string $phone): string
{
    if (function_exists('campus_normalize_phone')) {
        return campus_normalize_phone($phone);
    }
    if (function_exists('normalize_phone')) {
        return normalize_phone($phone);
    }
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '94' . substr($phone, 1);
    }
    return $phone;
}

function ensure_student_phone_change_otp_table(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_phone_change_otps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            new_phone VARCHAR(20) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_student_phone_change_user (user_id, expires_at),
            KEY idx_student_phone_change_phone (new_phone, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function student_phone_taken_by_other(PDO $pdo, string $phone, int $exceptUserId): bool
{
    $variants = phone_match_variants($phone);
    if ($variants === []) {
        return false;
    }
    $in = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.deleted_at IS NULL
          AND u.id <> ?
          AND (
            u.username IN ($in)
            OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(sp.whatsapp_number, ''), '+', ''), ' ', ''), '-', ''), '.', '') IN ($in)
          )
        LIMIT 1
    ");
    $stmt->execute(array_merge([$exceptUserId], $variants, $variants));
    if ($stmt->fetchColumn()) {
        return true;
    }

    try {
        $pending = $pdo->prepare("
            SELECT id
            FROM student_registration_otps
            WHERE phone IN ($in)
              AND verified_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");
        $pending->execute($variants);
        return (bool)$pending->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function student_phone_change_resend_wait_seconds(PDO $pdo, int $userId, string $phone = ''): int
{
    ensure_student_phone_change_otp_table($pdo);
    try {
        if ($phone !== '') {
            $stmt = $pdo->prepare("
                SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, created_at, NOW()))
                FROM student_phone_change_otps
                WHERE user_id = ? AND new_phone = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $phone]);
        } else {
            $stmt = $pdo->prepare("
                SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, created_at, NOW()))
                FROM student_phone_change_otps
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
        }
        $wait = $stmt->fetchColumn();
        return $wait === false ? 0 : max(0, (int)$wait);
    } catch (Throwable $e) {
        return 0;
    }
}

function send_student_phone_change_otp_message(string $phone, string $otp): bool
{
    require_once dirname(__DIR__) . '/config/sms_gateway.php';
    global $pdo;
    $db = $pdo instanceof PDO ? $pdo : null;
    $viaSms = student_otp_channel($db) === 'sms';
    $message = $viaSms
        ? "Edexcel College code: {$otp}"
        : (
            "Edexcel College\n\n" .
            "Your code to change the student mobile number is: *{$otp}*\n\n" .
            "This code expires in 10 minutes.\n" .
            "Do not share this code with anyone."
        );
    return otp_deliver_student($phone, $message);
}

function student_phone_change_request(PDO $pdo, int $userId, string $newPhone): array
{
    ensure_student_phone_change_otp_table($pdo);
    $newPhone = student_normalize_lk_phone($newPhone);
    if (!valid_lk_phone($newPhone)) {
        return ['ok' => false, 'sent' => false, 'wait' => 0, 'message' => 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.'];
    }
    if (student_phone_taken_by_other($pdo, $newPhone, $userId)) {
        return ['ok' => false, 'sent' => false, 'wait' => 0, 'message' => 'That mobile number is already registered to another account.'];
    }

    $wait = student_phone_change_resend_wait_seconds($pdo, $userId, $newPhone);
    if ($wait > 0) {
        $_SESSION['student_phone_change_pending'] = $newPhone;
        return [
            'ok' => true,
            'sent' => false,
            'wait' => $wait,
            'message' => "A code was already sent. Enter it below, or wait {$wait} seconds to resend.",
        ];
    }

    $otp = (string)random_int(100000, 999999);
    $stmt = $pdo->prepare("
        INSERT INTO student_phone_change_otps (user_id, new_phone, otp_hash, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ");
    $stmt->execute([$userId, $newPhone, password_hash($otp, PASSWORD_DEFAULT)]);
    $id = (int)$pdo->lastInsertId();
    $sent = send_student_phone_change_otp_message($newPhone, $otp);
    otp_support_log_record($pdo, [
        'purpose' => 'student_phone_change',
        'phone' => $newPhone,
        'otp' => $otp,
        'channel' => otp_support_current_channel($pdo),
        'user_id' => $userId,
        'sent' => $sent,
    ]);
    if (!$sent) {
        if ($id > 0) {
            try {
                $pdo->prepare('DELETE FROM student_phone_change_otps WHERE id = ?')->execute([$id]);
            } catch (Throwable $e) {
            }
        }
        return [
            'ok' => false,
            'sent' => false,
            'wait' => 0,
            'message' => 'We could not send the verification code. Please try again.',
        ];
    }

    $_SESSION['student_phone_change_pending'] = $newPhone;
    $channel = student_otp_channel_name($pdo);
    return [
        'ok' => true,
        'sent' => true,
        'wait' => 60,
        'message' => "We sent a 6-digit code by {$channel} to the new number. Enter it below to confirm the change.",
    ];
}

function student_phone_change_resend(PDO $pdo, int $userId, string $newPhone): array
{
    ensure_student_phone_change_otp_table($pdo);
    $newPhone = student_normalize_lk_phone($newPhone);
    if (!valid_lk_phone($newPhone)) {
        return ['ok' => false, 'sent' => false, 'wait' => 0, 'message' => 'Enter a valid Sri Lankan mobile number.'];
    }

    $wait = student_phone_change_resend_wait_seconds($pdo, $userId, $newPhone);
    if ($wait > 0) {
        $_SESSION['student_phone_change_pending'] = $newPhone;
        return [
            'ok' => false,
            'sent' => false,
            'wait' => $wait,
            'message' => "Please wait {$wait} seconds before requesting another OTP.",
        ];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM student_phone_change_otps
        WHERE user_id = ?
          AND new_phone = ?
          AND verified_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $newPhone]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pending) {
        return student_phone_change_request($pdo, $userId, $newPhone);
    }

    $otp = (string)random_int(100000, 999999);
    $sent = send_student_phone_change_otp_message($newPhone, $otp);
    otp_support_log_record($pdo, [
        'purpose' => 'student_phone_change',
        'phone' => $newPhone,
        'otp' => $otp,
        'channel' => otp_support_current_channel($pdo),
        'user_id' => $userId,
        'sent' => $sent,
    ]);
    if (!$sent) {
        return [
            'ok' => false,
            'sent' => false,
            'wait' => 0,
            'message' => 'We could not send a new code. Please try again.',
        ];
    }

    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("
            UPDATE student_phone_change_otps
            SET otp_hash = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                attempts = 0,
                created_at = NOW()
            WHERE id = ?
        ")->execute([$otpHash, (int)$pending['id']]);
    } catch (Throwable $e) {
        $pdo->prepare("
            UPDATE student_phone_change_otps
            SET otp_hash = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                attempts = 0
            WHERE id = ?
        ")->execute([$otpHash, (int)$pending['id']]);
    }

    $_SESSION['student_phone_change_pending'] = $newPhone;
    $channel = student_otp_channel_name($pdo);
    return [
        'ok' => true,
        'sent' => true,
        'wait' => 60,
        'message' => "A new code was sent by {$channel}. You can request another after 60 seconds.",
    ];
}

function student_phone_change_verify(PDO $pdo, int $userId, string $newPhone, string $otp): array
{
    ensure_student_phone_change_otp_table($pdo);
    $newPhone = student_normalize_lk_phone($newPhone);
    $otp = preg_replace('/\D+/', '', $otp) ?? '';
    if (!preg_match('/^\d{6}$/', $otp)) {
        return ['ok' => false, 'message' => 'Enter the 6-digit verification code.'];
    }
    if (!valid_lk_phone($newPhone)) {
        return ['ok' => false, 'message' => 'Enter a valid Sri Lankan mobile number.'];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM student_phone_change_otps
        WHERE user_id = ?
          AND new_phone = ?
          AND verified_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $newPhone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'No verification code was found. Request a new OTP.'];
    }
    if ((int)$row['attempts'] >= 5) {
        return ['ok' => false, 'message' => 'Too many incorrect attempts. Request a new OTP.'];
    }
    if (!password_verify($otp, (string)$row['otp_hash'])) {
        $pdo->prepare('UPDATE student_phone_change_otps SET attempts = attempts + 1 WHERE id = ?')
            ->execute([(int)$row['id']]);
        return ['ok' => false, 'message' => 'Incorrect code. Check the message and try again.'];
    }
    if (student_phone_taken_by_other($pdo, $newPhone, $userId)) {
        return ['ok' => false, 'message' => 'That mobile number is already registered to another account.'];
    }

    $pdo->prepare('UPDATE student_phone_change_otps SET verified_at = NOW() WHERE id = ?')
        ->execute([(int)$row['id']]);

    return ['ok' => true, 'new_phone' => $newPhone, 'message' => 'Number verified.'];
}

function apply_student_phone_change(PDO $pdo, int $userId, string $newPhone): void
{
    $newPhone = student_normalize_lk_phone($newPhone);
    if (!valid_lk_phone($newPhone)) {
        throw new RuntimeException('Enter a valid Sri Lankan mobile number.');
    }
    if (student_phone_taken_by_other($pdo, $newPhone, $userId)) {
        throw new RuntimeException('That mobile number is already registered to another account.');
    }

    $viaSms = student_otp_channel($pdo) === 'sms';
    $hasViaCol = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'phone_verified_via'");
        $hasViaCol = $colCheck && $colCheck->fetch() !== false;
    } catch (Throwable $e) {
        $hasViaCol = false;
    }

    $oldUsername = '';
    $displayName = '';
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'student' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$userId]);
    $oldUsername = student_normalize_lk_phone((string)($stmt->fetchColumn() ?: ''));

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ? AND role = 'student' AND deleted_at IS NULL");
        $upd->execute([$newPhone, $userId]);
        if ($upd->rowCount() < 1) {
            $check = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'student' AND deleted_at IS NULL LIMIT 1");
            $check->execute([$userId]);
            $current = student_normalize_lk_phone((string)($check->fetchColumn() ?: ''));
            if ($current !== $newPhone) {
                throw new RuntimeException('Unable to update your login number.');
            }
        }

        $exists = $pdo->prepare('SELECT user_id FROM student_profiles WHERE user_id = ? LIMIT 1');
        $exists->execute([$userId]);
        if ($exists->fetchColumn()) {
            if ($hasViaCol) {
                $pdo->prepare("
                    UPDATE student_profiles
                    SET whatsapp_number = ?, whatsapp_verified_at = NOW(), phone_verified_via = 'phone'
                    WHERE user_id = ?
                ")->execute([$newPhone, $userId]);
            } else {
                $pdo->prepare("
                    UPDATE student_profiles
                    SET whatsapp_number = ?, whatsapp_verified_at = NOW()
                    WHERE user_id = ?
                ")->execute([$newPhone, $userId]);
            }
        } else {
            $uStmt = $pdo->prepare("SELECT google_email, username FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $email = trim((string)($uRow['google_email'] ?? ''));
            $displayName = $email !== '' ? (string)strtok($email, '@') : (string)($uRow['username'] ?? 'Student');

            if ($hasViaCol) {
                $pdo->prepare("
                    INSERT INTO student_profiles (user_id, full_name, email, whatsapp_number, whatsapp_verified_at, phone_verified_via)
                    VALUES (?, ?, ?, ?, NOW(), 'phone')
                ")->execute([$userId, $displayName, $email !== '' ? $email : null, $newPhone]);
            } else {
                $pdo->prepare("
                    INSERT INTO student_profiles (user_id, full_name, email, whatsapp_number, whatsapp_verified_at)
                    VALUES (?, ?, ?, ?, NOW())
                ")->execute([$userId, $displayName, $email !== '' ? $email : null, $newPhone]);
            }
        }

        try {
            $pdo->prepare('UPDATE whatsapp_bot_contacts SET student_id = NULL WHERE student_id = ? AND phone <> ?')
                ->execute([$userId, $newPhone]);
        } catch (Throwable $e) {
        }

        $botVerifiedSql = $viaSms ? 'NULL' : 'NOW()';
        try {
            $pdo->prepare("
                INSERT INTO whatsapp_bot_contacts (phone, student_id, active, verified_at, last_seen_at)
                VALUES (?, ?, 1, {$botVerifiedSql}, NOW())
                ON DUPLICATE KEY UPDATE
                    student_id = VALUES(student_id),
                    active = 1,
                    verified_at = COALESCE(VALUES(verified_at), whatsapp_bot_contacts.verified_at),
                    last_seen_at = NOW()
            ")->execute([$newPhone, $userId]);
        } catch (Throwable $e) {
            error_log('Student phone change bot contact: ' . $e->getMessage());
        }

        $pdo->commit();

        // Verify that the record was actually stored in the database
        $verifyCheck = $pdo->prepare("SELECT whatsapp_number FROM student_profiles WHERE user_id = ? LIMIT 1");
        $verifyCheck->execute([$userId]);
        $storedNum = (string)$verifyCheck->fetchColumn();
        if ($storedNum !== $newPhone) {
            throw new RuntimeException('Verification check failed: phone number was not stored in database.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
            throw new RuntimeException('That mobile number is already registered to another account.');
        }
        throw $e;
    }

    $_SESSION['username'] = $newPhone;
    unset($_SESSION['student_phone_change_pending'], $_SESSION['dismiss_phone_modal']);
    if (empty($_SESSION['student_full_name']) && !empty($displayName)) {
        $_SESSION['student_full_name'] = $displayName;
    }

    if (function_exists('log_audit')) {
        log_audit($pdo, 'student_phone_change', 'users', $userId, ['username' => $oldUsername], ['username' => $newPhone]);
    }
}
