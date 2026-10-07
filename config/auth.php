<?php
// config/auth.php
require_once __DIR__ . '/error_handler.php'; // Load error handler first
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ops.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_teacher() {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
        return true;
    }
    // Administrator who also performs teaching duties preserves teacher access
    if (is_admin() && (!empty($_SESSION['can_teach']) || !empty($_SESSION['teacher_id']) || !empty($_SESSION['has_teacher_role']) || !empty($_SESSION['teacher_access']))) {
        return true;
    }
    return false;
}

function student_login_url(): string {
    return rtrim((string)BASE_URL, '/') . '/portal/login.php?role=student';
}

function destroy_app_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function session_user_is_valid($pdo): bool
{
    if (!is_logged_in()) {
        return false;
    }
    if (!($pdo instanceof PDO)) {
        return true;
    }
    $user = get_logged_in_user($pdo);
    if (!$user) {
        return false;
    }
    $accountStatus = strtolower(trim((string)($user['account_status'] ?? '')));
    if (in_array($accountStatus, ['pending', 'suspended', 'disabled'], true)) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $phoneLinkOk = $accountStatus === 'pending'
            && !empty($_SESSION['needs_phone_link'])
            && str_contains($script, '/auth/phone.php');
        if (!$phoneLinkOk) {
            return false;
        }
    } elseif (array_key_exists('is_active', $user) && (int)$user['is_active'] !== 1) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $phoneLinkOk = !empty($_SESSION['needs_phone_link'])
            && str_contains($script, '/auth/phone.php');
        if (!$phoneLinkOk) {
            return false;
        }
    }
    $role = strtolower(trim((string)($user['role'] ?? '')));
    if ($role !== '' && (string)($_SESSION['role'] ?? '') !== $role) {
        $_SESSION['role'] = $role;
    }
    if ($role === 'teacher') {
        $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : null;
        if (($_SESSION['teacher_id'] ?? null) !== $teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    } elseif ($role === 'admin') {
        // Administrator who also performs teaching duties
        $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : ($_SESSION['teacher_id'] ?? null);
        if ($teacherId !== null) {
            $_SESSION['teacher_id'] = (int)$teacherId;
        }
        $_SESSION['can_teach'] = true;
        $_SESSION['has_teacher_role'] = true;

        // Invalidate admin session if all sessions were revoked globally
        if (function_exists('ops_setting')) {
            $revokedAt = (int)ops_setting($pdo, 'admin_sessions_revoked_at', '0');
            $sessionCreated = (int)($_SESSION['login_time'] ?? 0);
            if ($revokedAt > 0 && $sessionCreated > 0 && $sessionCreated < $revokedAt) {
                $_SESSION['_kick_reason'] = 'admin_session_revoked';
                return false;
            }
        }
    } elseif (isset($_SESSION['teacher_id'])) {
        unset($_SESSION['teacher_id']);
    }
    if ($role === 'student') {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $skipDevice = !empty($_SESSION['needs_phone_link']) && str_contains($script, '/auth/phone.php');
        if (!$skipDevice) {
            if (!function_exists('student_device_session_valid')) {
                require_once dirname(__DIR__) . '/student/device_helpers.php';
            }
            if (!student_device_session_valid($pdo, (int)$user['id'])) {
                $_SESSION['_kick_reason'] = 'other_device';
                return false;
            }
        }
    }
    if (empty($_SESSION['theme_preference']) && function_exists('app_theme_load_user_preference')) {
        $savedTheme = app_theme_load_user_preference($pdo, (int) ($user['id'] ?? 0));
        if ($savedTheme) {
            $_SESSION['theme_preference'] = $savedTheme;
        }
    }
    return true;
}

function enforce_active_session($pdo): void
{
    if (!is_logged_in() || !($pdo instanceof PDO)) {
        return;
    }
    if (session_user_is_valid($pdo)) {
        return;
    }
    $kickReason = (string)($_SESSION['_kick_reason'] ?? '');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $wasStudent = current_role() === 'student' || $kickReason === 'other_device';
    $goStudent = str_contains($script, '/student/') || $wasStudent;
    destroy_app_session();
    if ($wasStudent && $kickReason === 'other_device' && function_exists('student_login_notice_set')) {
        student_login_notice_set('other_device');
    } elseif ($wasStudent && $kickReason === 'other_device') {
        require_once dirname(__DIR__) . '/student/device_helpers.php';
        student_login_notice_set('other_device');
    }
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $redirect = $goStudent ? student_login_url() : (BASE_URL . 'login.php');
    if (is_ajax_request()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => $kickReason === 'other_device'
                ? 'Signed in on another device.'
                : 'Session expired.',
            'reason' => $kickReason !== '' ? $kickReason : 'expired',
            'redirect' => $redirect,
        ]);
        exit();
    }
    header('Location: ' . $redirect);
    exit();
}

function require_login() {
    if (!is_logged_in()) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_contains($script, '/classroom/room.php')) {
            if (!function_exists('student_device_remember_classroom_return')) {
                $helper = dirname(__DIR__) . '/student/device_helpers.php';
                if (is_file($helper)) {
                    require_once $helper;
                }
            }
            if (function_exists('student_device_remember_classroom_return')) {
                student_device_remember_classroom_return();
            }
        }
        if (is_ajax_request()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Authentication required. Please sign in.',
                'redirect' => (BASE_URL . 'login.php')
            ]);
            exit();
        }
        $goStudent = str_contains($script, '/student/');
        header('Location: ' . ($goStudent ? student_login_url() : (BASE_URL . 'login.php')));
        exit();
    }
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        enforce_active_session($pdo);
        if (function_exists('app_theme_ensure_schema')) {
            app_theme_ensure_schema($pdo);
        }
    }
}

function current_role(): string {
    return strtolower(trim((string)($_SESSION['role'] ?? '')));
}

function is_ajax_request(): bool {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($script, '/ajax/') || str_contains($script, '/api/')) {
        return true;
    }
    $requested = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($requested === 'xmlhttprequest' || $requested === 'fetch') {
        return true;
    }
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
}

function deny_wrong_role(string $message, string $redirect = ''): void {
    if (is_ajax_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit();
    }
    if ($redirect !== '') {
        header('Location: ' . $redirect);
        exit();
    }
    http_response_code(403);
    exit($message);
}

function require_student(): void {
    require_login();
    if (current_role() !== 'student') {
        $redirect = is_admin() || is_teacher()
            ? (BASE_URL . 'dashboard.php')
            : student_login_url();
        deny_wrong_role('Access denied. Students only.', $redirect);
    }
    global $pdo;
    if ($pdo instanceof PDO) {
        try {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            if (!function_exists('student_joining_online_class')) {
                $helpers = dirname(__DIR__) . '/student/device_helpers.php';
                if (is_file($helpers)) {
                    require_once $helpers;
                }
            }
            $phoneSvc = new \Edexcel\Services\PortalPhoneLinkService($pdo);
            $joiningClass = function_exists('student_joining_online_class') && student_joining_online_class();
            if (
                empty($_SESSION['phone_link_skipped'])
                && !$joiningClass
                && $phoneSvc->studentNeedsPhone((int)($_SESSION['user_id'] ?? 0))
            ) {
                $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
                if (!str_contains($script, '/auth/phone.php')) {
                    $_SESSION['needs_phone_link'] = 1;
                    $_SESSION['phone_link_type'] = 'student';
                    if (is_ajax_request()) {
                        http_response_code(403);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => false, 'error' => 'Verify your mobile number.', 'redirect' => '/auth/phone.php']);
                        exit();
                    }
                    header('Location: /auth/phone.php');
                    exit();
                }
            }
        } catch (Throwable $e) {
            // ignore gate failures
        }
    }
    if (!function_exists('student_enforce_parent_phone')) {
        $helpers = dirname(__DIR__) . '/student/device_helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }
    if (function_exists('student_enforce_parent_phone')) {
        student_enforce_parent_phone($pdo instanceof PDO ? $pdo : null);
    }
}

function require_staff(): void {
    require_login();
    $role = current_role();
    if ($role === 'student') {
        deny_wrong_role(
            'Access denied.',
            rtrim((string)BASE_URL, '/') . '/student/dashboard.php'
        );
    }
    if (!is_admin() && !is_teacher()) {
        deny_wrong_role('Access denied.');
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $redirect = current_role() === 'student'
            ? rtrim((string)BASE_URL, '/') . '/student/dashboard.php'
            : (BASE_URL . 'dashboard.php');
        deny_wrong_role('Access denied. Admin only.', $redirect);
    }
}

function require_teacher() {
    require_login();
    if (!is_teacher()) {
        $redirect = current_role() === 'student'
            ? rtrim((string)BASE_URL, '/') . '/student/dashboard.php'
            : (BASE_URL . 'dashboard.php');
        deny_wrong_role('Access denied. Teachers only.', $redirect);
    }
}

/**
 * Parent portal guard: session parent_id + account not suspended/disabled.
 * Verified child access is enforced separately via ParentAuthService::ownsStudent().
 */
function require_parent(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $parentId = (int)($_SESSION['parent_id'] ?? 0);
    if ($parentId < 1) {
        if (is_ajax_request()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Parent authentication required.']);
            exit();
        }
        header('Location: /portal/login.php');
        exit();
    }
    global $pdo;
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT status FROM parent_accounts WHERE id = ? LIMIT 1');
            $stmt->execute([$parentId]);
            $status = strtolower((string)($stmt->fetchColumn() ?: 'active'));
            $_SESSION['parent_status'] = $status;
            if (in_array($status, ['suspended', 'disabled'], true)) {
                unset(
                    $_SESSION['parent_id'],
                    $_SESSION['parent_phone'],
                    $_SESSION['parent_email'],
                    $_SESSION['parent_name'],
                    $_SESSION['parent_status'],
                    $_SESSION['parent_student_id']
                );
                deny_wrong_role('Parent account is not allowed to access the portal.', '/portal/login.php');
            }
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            $phoneSvc = new \Edexcel\Services\PortalPhoneLinkService($pdo);
            if ($phoneSvc->parentNeedsPhone($parentId)) {
                $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
                if (!str_contains($script, '/auth/phone.php')) {
                    $_SESSION['needs_phone_link'] = 1;
                    $_SESSION['phone_link_type'] = 'parent';
                    header('Location: /auth/phone.php');
                    exit();
                }
            }
        } catch (Throwable $e) {
            // If status column missing, allow legacy parents through.
        }
    }
}

/**
 * Ensure the logged-in parent may access a specific student (IDOR protection).
 */
function require_parent_owns_student(int $studentId): void
{
    require_parent();
    global $pdo;
    $parentId = (int)($_SESSION['parent_id'] ?? 0);
    if (!($pdo instanceof PDO) || $parentId < 1 || $studentId < 1) {
        deny_wrong_role('Forbidden.', '/parent/home.php');
    }
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $ok = (new \Edexcel\Services\ParentAuthService($pdo))->ownsStudent($parentId, $studentId);
    if (!$ok) {
        if (function_exists('log_audit')) {
            log_audit($pdo, 'parent_unauthorized_student', 'parent_students', $studentId, null, [
                'parent_id' => $parentId,
                'student_id' => $studentId,
            ]);
        }
        deny_wrong_role('Forbidden.', '/parent/home.php');
    }
}

function get_logged_in_user($pdo) {
    if (!is_logged_in()) return null;
    static $cached = false;
    static $cachedId = 0;
    $id = (int)$_SESSION['user_id'];
    if ($cached !== false && $cachedId === $id) {
        return $cached;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $cached = $row ?: null;
    $cachedId = $id;
    return $cached;
}

function regenerate_session() {
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Keep one portal's session keys. Shared browsers must not carry teacher/parent/student together.
 */
function clear_cross_portal_session(string $keep): void
{
    $keep = strtolower(trim($keep));
    if ($keep !== 'student') {
        unset(
            $_SESSION['student_id'],
            $_SESSION['student_full_name'],
            $_SESSION['student_login_otp_phone'],
            $_SESSION['student_session_token'],
            $_SESSION['student_device_id'],
            $_SESSION['student_pending_login_user_id'],
            $_SESSION['student_presence_until'],
            $_SESSION['student_pending_device']
        );
    }
    if ($keep !== 'parent') {
        unset(
            $_SESSION['parent_id'],
            $_SESSION['parent_phone'],
            $_SESSION['parent_email'],
            $_SESSION['parent_name'],
            $_SESSION['parent_status'],
            $_SESSION['parent_student_id']
        );
    }
    if ($keep !== 'staff') {
        unset(
            $_SESSION['teacher_id'],
            $_SESSION['staff_login_otp_phone'],
            $_SESSION['staff_pending_2fa_user_id']
        );
    }
    if ($keep === 'student') {
        unset($_SESSION['teacher_id']);
    }
}