<?php
declare(strict_types=1);

use Edexcel\Services\StudentDeviceService;

function student_device_autoload(): void
{
    if (class_exists(StudentDeviceService::class, false)) {
        return;
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (!class_exists(StudentDeviceService::class)) {
        require_once dirname(__DIR__) . '/src/Services/StudentDeviceService.php';
    }
}

function student_devices(?PDO $pdo = null): ?StudentDeviceService
{
    student_device_autoload();
    if (!($pdo instanceof PDO)) {
        global $pdo;
    }
    if (!($pdo instanceof PDO)) {
        return null;
    }
    static $services = [];
    $key = spl_object_id($pdo);
    if (!isset($services[$key])) {
        $services[$key] = new StudentDeviceService($pdo);
    }
    return $services[$key];
}

function student_device_issue_cookie(?PDO $pdo = null): void
{
    $svc = student_devices($pdo);
    if ($svc) {
        $svc->issueCookie();
        return;
    }
    student_device_autoload();
    $existing = trim((string)($_COOKIE[StudentDeviceService::COOKIE] ?? ''));
    if (preg_match('/^[a-f0-9]{64}$/', $existing)) {
        return;
    }
    $token = bin2hex(random_bytes(32));
    $_COOKIE[StudentDeviceService::COOKIE] = $token;
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        setcookie(StudentDeviceService::COOKIE, $token, [
            'expires' => time() + 400 * 86400,
            'path' => '/',
            'secure' => StudentDeviceService::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function student_login_notice_set(string $code): void
{
    student_device_autoload();
    if (PHP_SAPI === 'cli' || headers_sent()) {
        $_COOKIE[StudentDeviceService::NOTICE_COOKIE] = $code;
        return;
    }
    setcookie(StudentDeviceService::NOTICE_COOKIE, $code, [
        'expires' => time() + 600,
        'path' => '/',
        'secure' => StudentDeviceService::cookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function student_login_notice_take(): string
{
    student_device_autoload();
    $code = trim((string)($_COOKIE[StudentDeviceService::NOTICE_COOKIE] ?? ''));
    if ($code === '') {
        return '';
    }
    unset($_COOKIE[StudentDeviceService::NOTICE_COOKIE]);
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        setcookie(StudentDeviceService::NOTICE_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => StudentDeviceService::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $code;
}

function student_login_notice_message(string $code = ''): string
{
    if ($code === '') {
        $code = student_login_notice_take();
    }
    return match ($code) {
        'other_device' => 'You were signed out because this account was used on another device. Only one device can stay signed in at a time.',
        'device_removed' => 'That device was removed. Sign in again from a registered device.',
        default => '',
    };
}

function student_device_otp_pending(): bool
{
    return !empty($_SESSION['student_device_otp']) && (int)($_SESSION['student_pending_login_user_id'] ?? 0) > 0;
}

/**
 * Finish a successful password/OTP check: register or challenge this device, then open one live session.
 *
 * @return array{status:string,message:string,show_otp?:bool,replace_label?:string,channel?:string}
 */
function student_portal_sign_in(array $user, PDO $pdo, string $source = 'password'): array
{
    $svc = student_devices($pdo);
    if (!$svc) {
        complete_student_portal_login($user, $pdo);
        return ['status' => 'ok', 'message' => ''];
    }
    $userId = (int)($user['id'] ?? 0);
    $gate = $svc->beginLogin($userId, $source);
    if (in_array(($gate['status'] ?? ''), ['device_limit', 'device_blocked'], true)) {
        complete_student_portal_login($user, $pdo);
        $_SESSION['student_device_choice'] = (string)$gate['status'];
        $_SESSION['student_device_blocked_until'] = (string)($gate['blocked_until'] ?? '');
        return $gate;
    }
    if (($gate['status'] ?? '') === 'otp') {
        return $gate;
    }
    if (($gate['status'] ?? '') !== 'ok') {
        return $gate;
    }
    complete_student_portal_login($user, $pdo);
    $svc->activateSession($userId, (int)$gate['device_id']);
    $svc->clearPending();
    $svc->onSignedIn($userId, (int)$gate['device_id'], $source, !empty($gate['new_device']));
    return ['status' => 'ok', 'message' => '', 'device_id' => (int)$gate['device_id']];
}

function student_device_complete_pending(PDO $pdo, string $otp): array
{
    $userId = (int)($_SESSION['student_pending_login_user_id'] ?? 0);
    if ($userId < 1) {
        return ['status' => 'error', 'message' => 'Sign in again, then enter the device code.'];
    }
    $svc = student_devices($pdo);
    if (!$svc) {
        return ['status' => 'error', 'message' => 'Unable to verify this device right now.'];
    }
    $verified = $svc->verifyDeviceOtp($userId, $otp);
    if (($verified['status'] ?? '') !== 'ok') {
        return $verified;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['status' => 'error', 'message' => 'This student account is no longer available.'];
    }
    complete_student_portal_login($user, $pdo);
    $svc->activateSession($userId, (int)$verified['device_id']);
    $svc->onSignedIn($userId, (int)$verified['device_id'], 'device_otp', true);
    return ['status' => 'ok', 'message' => '', 'user' => $user];
}

function student_device_session_valid(PDO $pdo, int $userId): bool
{
    if (!empty($_SESSION['student_device_choice'])) {
        return true;
    }
    $svc = student_devices($pdo);
    if (!$svc || $userId < 1) {
        return true;
    }
    return $svc->enforceExistingSession($userId);
}

function student_device_enforce_gate(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $choice = (string)($_SESSION['student_device_choice'] ?? '');
    if ($choice === '' || (function_exists('current_role') && current_role() !== 'student')) {
        return;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (
        str_contains($script, '/student/device_gate.php')
        || str_contains($script, '/logout.php')
        || str_contains($script, '/auth/')
        || str_contains($script, '/api/')
    ) {
        return;
    }
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        return;
    }
    if (str_contains($script, '/classroom/room.php')) {
        student_device_remember_classroom_return();
        global $pdo;
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $lessonId = $pdo instanceof PDO ? student_device_return_lesson_id($pdo) : 0;
        if ($lessonId > 0 && $pdo instanceof PDO && student_device_emergency_allows($pdo, $userId, $lessonId)) {
            return;
        }
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';
    header('Location: ' . $base . 'student/device_gate.php');
    exit;
}

function student_device_remember_classroom_return(): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || $uri === '' || !str_contains($uri, '/classroom/room.php')) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $_SESSION['student_device_return'] = ($https ? 'https' : 'http') . '://' . $host . $uri;
}

function student_device_return_lesson_id(PDO $pdo): int
{
    $return = (string)($_SESSION['student_device_return'] ?? '');
    if ($return === '') {
        return 0;
    }
    $query = (string)(parse_url($return, PHP_URL_QUERY) ?: '');
    parse_str($query, $params);
    if (!is_array($params)) {
        return 0;
    }
    $lessonId = (int)($params['lesson'] ?? $params['timetable_id'] ?? 0);
    if ($lessonId > 0) {
        return $lessonId;
    }
    $publicId = preg_replace('/[^a-f0-9]/i', '', (string)($params['m'] ?? '')) ?? '';
    if ($publicId === '') {
        return 0;
    }
    try {
        $stmt = $pdo->prepare('SELECT timetable_id FROM online_meetings WHERE public_id = ? LIMIT 1');
        $stmt->execute([$publicId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function student_device_safe_return(string $return): string
{
    $return = trim($return);
    if ($return === '') {
        return '';
    }
    $path = str_replace('\\', '/', (string)(parse_url($return, PHP_URL_PATH) ?: ''));
    if (!str_contains($path, '/classroom/room.php')) {
        return '';
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
    if ($base !== '' && $base !== '/' && str_starts_with($return, $base . '/')) {
        return $return;
    }
    $host = strtolower((string)(parse_url($return, PHP_URL_HOST) ?: ''));
    $siteHost = strtolower((string)(parse_url($base !== '' ? $base : '', PHP_URL_HOST) ?: ''));
    if ($host !== '' && $siteHost !== '' && $host === $siteHost) {
        return $return;
    }
    return '';
}

function student_device_emergency_allows(PDO $pdo, int $userId, int $timetableId): bool
{
    if ($userId < 1 || $timetableId < 1) {
        return false;
    }
    student_device_autoload();
    $svc = student_devices($pdo);
    if (!$svc) {
        return false;
    }
    if (!class_exists(\Edexcel\Services\EmergencyDeviceAccessService::class)) {
        require_once dirname(__DIR__) . '/src/Services/EmergencyDeviceAccessService.php';
    }
    try {
        $emergency = new \Edexcel\Services\EmergencyDeviceAccessService($pdo);
        return $emergency->allows($userId, $timetableId, $svc->currentDeviceKey());
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return array{user_id:int,role:string,is_admin:bool,is_teacher:bool,teacher_id:int,name:string}
 */
function emergency_device_actor(PDO $pdo): array
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $name = trim((string)($_SESSION['username'] ?? ''));
    if ($name === '' && $userId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $name = trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            $name = '';
        }
    }
    return [
        'user_id' => $userId,
        'role' => function_exists('current_role') ? current_role() : '',
        'is_admin' => function_exists('is_admin') && is_admin(),
        'is_teacher' => function_exists('is_teacher') && is_teacher(),
        'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0),
        'name' => $name !== '' ? $name : 'Staff',
    ];
}

function student_device_emergency_popup_script(string $mode = 'staff', int $lessonId = 0, string $returnUrl = ''): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $role = function_exists('current_role') ? current_role() : '';
    if ($mode === 'staff' && !in_array($role, ['teacher', 'admin'], true)) {
        return;
    }
    if ($mode === 'student' && $role !== 'student') {
        return;
    }
    $js = dirname(__DIR__) . '/assets/js/device-emergency.js';
    if (!is_file($js)) {
        return;
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';
    $config = [
        'endpoint' => $base . 'api/classroom/device_emergency.php',
        'csrf' => function_exists('csrf_token') ? csrf_token() : '',
        'role' => $role,
        'mode' => $mode,
        'lessonId' => $lessonId,
        'returnUrl' => $returnUrl,
    ];
    $version = (string)filemtime($js);
    echo '<script>window.ECK_DEVICE_EMERGENCY = ' . json_encode($config, JSON_UNESCAPED_SLASHES) . ';</script>';
    echo '<script src="' . htmlspecialchars($base . 'assets/js/device-emergency.js?v=' . $version, ENT_QUOTES, 'UTF-8') . '"></script>';
}

function student_session_guard_script(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if ($role !== 'student') {
        return;
    }
    $js = dirname(__DIR__) . '/assets/js/student-session.js';
    if (!is_file($js)) {
        return;
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';
    echo '<script src="' . htmlspecialchars($base . 'assets/js/student-session.js?v=' . filemtime($js), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-student-session="1"'
        . ' data-ping-url="' . htmlspecialchars($base . 'ajax/student_session_ping.php', ENT_QUOTES, 'UTF-8') . '"'
        . ' data-login-url="' . htmlspecialchars(function_exists('student_login_url') ? student_login_url() : ($base . 'index.php#student-login'), ENT_QUOTES, 'UTF-8') . '"'
        . ' defer></script>' . "\n";
}

function student_presence_safe_return(string $url = ''): string
{
    if ($url === '') {
        $url = (string)($_GET['return'] ?? $_SERVER['REQUEST_URI'] ?? '');
    }
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
    $query = (string)(parse_url($url, PHP_URL_QUERY) ?: '');
    if (!preg_match('#^/(student|classroom)/#', $path) || str_contains($path, 'confirm_phone.php')) {
        return '/student/dashboard.php';
    }
    return $path . ($query !== '' ? '?' . $query : '');
}

function student_require_presence(?PDO $pdo = null, string $returnTo = ''): void
{
    if (function_exists('current_role') && current_role() !== 'student') {
        return;
    }
    $svc = student_devices($pdo);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($svc && ($svc->presenceValid() || $svc->grantPresenceIfKnownDevice($userId))) {
        return;
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';
    $return = student_presence_safe_return($returnTo !== '' ? $returnTo : (string)($_SERVER['REQUEST_URI'] ?? ''));
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'need_presence' => true,
            'error' => 'Confirm it is you with the SMS code sent to your registered number.',
            'redirect' => $base . 'student/confirm_phone.php?return=' . rawurlencode($return),
        ]);
        exit;
    }
    header('Location: ' . $base . 'student/confirm_phone.php?return=' . rawurlencode($return));
    exit;
}

/**
 * Return country list with ISO code, name, dial code, flag emoji, and phone placeholder.
 *
 * @return array<string, array{name:string, dial:string, flag:string, sample:string, popular?:bool}>
 */
function student_country_dial_database(): array
{
    return [
        'LK' => ['name' => 'Sri Lanka', 'dial' => '+94', 'flag' => '🇱🇰', 'sample' => '077 123 4567', 'popular' => true],
        'GB' => ['name' => 'United Kingdom', 'dial' => '+44', 'flag' => '🇬🇧', 'sample' => '07911 123456', 'popular' => true],
        'AE' => ['name' => 'United Arab Emirates', 'dial' => '+971', 'flag' => '🇦🇪', 'sample' => '050 123 4567', 'popular' => true],
        'QA' => ['name' => 'Qatar', 'dial' => '+974', 'flag' => '🇶🇦', 'sample' => '3312 3456', 'popular' => true],
        'OM' => ['name' => 'Oman', 'dial' => '+968', 'flag' => '🇴🇲', 'sample' => '9123 4567', 'popular' => true],
        'SA' => ['name' => 'Saudi Arabia', 'dial' => '+966', 'flag' => '🇸🇦', 'sample' => '050 123 4567', 'popular' => true],
        'AU' => ['name' => 'Australia', 'dial' => '+61', 'flag' => '🇦🇺', 'sample' => '0412 345 678', 'popular' => true],
        'IN' => ['name' => 'India', 'dial' => '+91', 'flag' => '🇮🇳', 'sample' => '98765 43210', 'popular' => true],
        'US' => ['name' => 'United States', 'dial' => '+1', 'flag' => '🇺🇸', 'sample' => '(555) 123-4567', 'popular' => true],
        'CA' => ['name' => 'Canada', 'dial' => '+1', 'flag' => '🇨🇦', 'sample' => '(555) 123-4567', 'popular' => true],
        'SG' => ['name' => 'Singapore', 'dial' => '+65', 'flag' => '🇸🇬', 'sample' => '8123 4567', 'popular' => true],
        'MY' => ['name' => 'Malaysia', 'dial' => '+60', 'flag' => '🇲🇾', 'sample' => '012-345 6789', 'popular' => true],
        'MV' => ['name' => 'Maldives', 'dial' => '+960', 'flag' => '🇲🇻', 'sample' => '712 3456', 'popular' => true],
        'KW' => ['name' => 'Kuwait', 'dial' => '+965', 'flag' => '🇰🇼', 'sample' => '5123 4567', 'popular' => true],
        'BH' => ['name' => 'Bahrain', 'dial' => '+973', 'flag' => '🇧🇭', 'sample' => '3612 3456', 'popular' => true],
        'NZ' => ['name' => 'New Zealand', 'dial' => '+64', 'flag' => '🇳🇿', 'sample' => '021 123 4567', 'popular' => true],
        'IT' => ['name' => 'Italy', 'dial' => '+39', 'flag' => '🇮🇹', 'sample' => '312 345 6789', 'popular' => true],
        'DE' => ['name' => 'Germany', 'dial' => '+49', 'flag' => '🇩🇪', 'sample' => '0151 1234567', 'popular' => true],
        'FR' => ['name' => 'France', 'dial' => '+33', 'flag' => '🇫🇷', 'sample' => '06 12 34 56 78', 'popular' => true],
        'IE' => ['name' => 'Ireland', 'dial' => '+353', 'flag' => '🇮🇪', 'sample' => '085 123 4567', 'popular' => true],
        'PK' => ['name' => 'Pakistan', 'dial' => '+92', 'flag' => '🇵🇰', 'sample' => '0300 1234567', 'popular' => true],
        'BD' => ['name' => 'Bangladesh', 'dial' => '+880', 'flag' => '🇧🇩', 'sample' => '01712 345678', 'popular' => true],
        'NP' => ['name' => 'Nepal', 'dial' => '+977', 'flag' => '🇳🇵', 'sample' => '984 1234567'],
        'HK' => ['name' => 'Hong Kong', 'dial' => '+852', 'flag' => '🇭🇰', 'sample' => '9123 4567'],
        'JP' => ['name' => 'Japan', 'dial' => '+81', 'flag' => '🇯🇵', 'sample' => '090 1234 5678'],
        'KR' => ['name' => 'South Korea', 'dial' => '+82', 'flag' => '🇰🇷', 'sample' => '010 1234 5678'],
        'CN' => ['name' => 'China', 'dial' => '+86', 'flag' => '🇨🇳', 'sample' => '138 1234 5678'],
        'ID' => ['name' => 'Indonesia', 'dial' => '+62', 'flag' => '🇮🇩', 'sample' => '0812 3456 789'],
        'TH' => ['name' => 'Thailand', 'dial' => '+66', 'flag' => '🇹🇭', 'sample' => '081 234 5678'],
        'PH' => ['name' => 'Philippines', 'dial' => '+63', 'flag' => '🇵🇭', 'sample' => '0917 123 4567'],
        'VN' => ['name' => 'Vietnam', 'dial' => '+84', 'flag' => '🇻🇳', 'sample' => '091 234 5678'],
        'ZA' => ['name' => 'South Africa', 'dial' => '+27', 'flag' => '🇿🇦', 'sample' => '071 123 4567'],
        'EG' => ['name' => 'Egypt', 'dial' => '+20', 'flag' => '🇪🇬', 'sample' => '010 1234 5678'],
        'NG' => ['name' => 'Nigeria', 'dial' => '+234', 'flag' => '🇳🇬', 'sample' => '0802 123 4567'],
        'KE' => ['name' => 'Kenya', 'dial' => '+254', 'flag' => '🇰🇪', 'sample' => '0712 345 678'],
        'TR' => ['name' => 'Turkey', 'dial' => '+90', 'flag' => '🇹🇷', 'sample' => '0532 123 4567'],
        'NL' => ['name' => 'Netherlands', 'dial' => '+31', 'flag' => '🇳🇱', 'sample' => '06 12345678'],
        'CH' => ['name' => 'Switzerland', 'dial' => '+41', 'flag' => '🇨🇭', 'sample' => '078 123 45 67'],
        'SE' => ['name' => 'Sweden', 'dial' => '+46', 'flag' => '🇸🇪', 'sample' => '070 123 45 67'],
        'NO' => ['name' => 'Norway', 'dial' => '+47', 'flag' => '🇳🇴', 'sample' => '412 34 567'],
        'DK' => ['name' => 'Denmark', 'dial' => '+45', 'flag' => '🇩🇰', 'sample' => '20 12 34 56'],
        'FI' => ['name' => 'Finland', 'dial' => '+358', 'flag' => '🇫🇮', 'sample' => '040 1234567'],
        'ES' => ['name' => 'Spain', 'dial' => '+34', 'flag' => '🇪🇸', 'sample' => '612 34 56 78'],
        'PT' => ['name' => 'Portugal', 'dial' => '+351', 'flag' => '🇵🇹', 'sample' => '912 345 678'],
        'GR' => ['name' => 'Greece', 'dial' => '+30', 'flag' => '🇬🇷', 'sample' => '691 234 5678'],
        'CY' => ['name' => 'Cyprus', 'dial' => '+357', 'flag' => '🇨🇾', 'sample' => '99 123456'],
        'JO' => ['name' => 'Jordan', 'dial' => '+962', 'flag' => '🇯🇴', 'sample' => '07 9123 4567'],
        'LB' => ['name' => 'Lebanon', 'dial' => '+961', 'flag' => '🇱🇧', 'sample' => '03 123 456'],
        'MU' => ['name' => 'Mauritius', 'dial' => '+230', 'flag' => '🇲🇺', 'sample' => '5123 4567'],
        'SC' => ['name' => 'Seychelles', 'dial' => '+248', 'flag' => '🇸🇨', 'sample' => '251 2345'],
        'BR' => ['name' => 'Brazil', 'dial' => '+55', 'flag' => '🇧🇷', 'sample' => '(11) 91234-5678'],
        'MX' => ['name' => 'Mexico', 'dial' => '+52', 'flag' => '🇲🇽', 'sample' => '55 1234 5678'],
        'RU' => ['name' => 'Russia', 'dial' => '+7', 'flag' => '🇷🇺', 'sample' => '912 345-67-89'],
        'KZ' => ['name' => 'Kazakhstan', 'dial' => '+7', 'flag' => '🇰🇿', 'sample' => '701 123 4567'],
        'BE' => ['name' => 'Belgium', 'dial' => '+32', 'flag' => '🇧🇪', 'sample' => '0470 12 34 56'],
        'AT' => ['name' => 'Austria', 'dial' => '+43', 'flag' => '🇦🇹', 'sample' => '0664 1234567'],
        'PL' => ['name' => 'Poland', 'dial' => '+48', 'flag' => '🇵🇱', 'sample' => '512 345 678'],
        'CZ' => ['name' => 'Czech Republic', 'dial' => '+420', 'flag' => '🇨🇿', 'sample' => '601 123 456'],
    ];
}

function student_detect_country_iso(): string
{
    $code = '';
    try {
        if (class_exists('Edexcel\\Services\\GeoIpService')) {
            $code = \Edexcel\Services\GeoIpService::countryCode();
        } else {
            $file = dirname(__DIR__) . '/src/Services/GeoIpService.php';
            if (is_file($file)) {
                require_once $file;
                if (class_exists('Edexcel\\Services\\GeoIpService')) {
                    $code = \Edexcel\Services\GeoIpService::countryCode();
                }
            }
        }
    } catch (\Throwable $e) {
        $code = '';
    }

    if ($code === '' && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $cf = strtoupper(trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']));
        if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
            $code = $cf;
        }
    }

    $db = student_country_dial_database();
    return array_key_exists($code, $db) ? $code : 'LK';
}

function student_parse_e164_country(string $e164): array
{
    $digits = preg_replace('/\D+/', '', $e164) ?? '';
    if ($digits === '') {
        return ['iso' => 'LK', 'dial' => '+94', 'local' => ''];
    }

    $db = student_country_dial_database();
    $candidates = [];
    foreach ($db as $iso => $info) {
        $code = preg_replace('/\D+/', '', $info['dial']) ?? '';
        $candidates[$code] = $iso;
    }
    krsort($candidates);

    foreach ($candidates as $code => $iso) {
        $strCode = (string)$code;
        if (str_starts_with($digits, $strCode)) {
            $local = substr($digits, strlen($strCode));
            if ($iso === 'LK') {
                $local = '0' . $local;
            }
            return [
                'iso' => $iso,
                'dial' => $db[$iso]['dial'],
                'local' => $local,
            ];
        }
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return ['iso' => 'LK', 'dial' => '+94', 'local' => $digits];
    }
    return ['iso' => 'LK', 'dial' => '+94', 'local' => $digits];
}

function student_parent_phone_normalize(string $raw, string $dialCode = '', string $countryIso = ''): string
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return '';
    }

    $hasPlus = str_starts_with($trimmed, '+');
    $has00 = str_starts_with($trimmed, '00');
    $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
    if ($has00 && strlen($digits) > 2) {
        $digits = substr($digits, 2);
        $hasPlus = true;
    }

    $cleanDial = preg_replace('/\D+/', '', $dialCode) ?? '';

    // If user explicitly typed a leading '+' (or '00'), digits already include the country calling code
    if ($hasPlus && strlen($digits) >= 8 && strlen($digits) <= 15) {
        return $digits;
    }

    if ($cleanDial !== '') {
        // If input starts with leading 0 (e.g. 0771234567 or 07911123456), strip 0 and prepend dial code
        if (str_starts_with($digits, '0') && strlen($digits) > 1) {
            return $cleanDial . substr($digits, 1);
        }
        // If input already starts with the dial code digits
        if (str_starts_with($digits, $cleanDial)) {
            return $digits;
        }
        return $cleanDial . $digits;
    }

    // Default fallback (Sri Lanka convention: 07X XXX XXXX -> 947XXXXXXXX)
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '94' . substr($digits, 1);
    }
    if (preg_match('/^7\d{8}$/', $digits)) {
        return '94' . $digits;
    }

    return $digits;
}

function student_parent_phone_valid(string $normalized): bool
{
    if ($normalized === '') {
        return false;
    }

    // International E.164: 8 to 15 digits, cannot start with 0
    if (!preg_match('/^[1-9]\d{7,14}$/', $normalized)) {
        return false;
    }

    // Sri Lanka: 947XXXXXXXX (11 digits)
    if (str_starts_with($normalized, '94')) {
        return (bool)preg_match('/^947\d{8}$/', $normalized);
    }

    // UK: 447XXXXXXXXX (12 digits)
    if (str_starts_with($normalized, '44')) {
        return (bool)preg_match('/^44(?:7\d{9})$/', $normalized);
    }

    // UAE: 9715XXXXXXXX (12 digits)
    if (str_starts_with($normalized, '971')) {
        return (bool)preg_match('/^9715\d{8}$/', $normalized);
    }

    // India: 91[6-9]XXXXXXXXX (12 digits)
    if (str_starts_with($normalized, '91')) {
        return (bool)preg_match('/^91[6-9]\d{9}$/', $normalized);
    }

    // US/Canada: 1[2-9]XXXXXXXXX (11 digits)
    if (str_starts_with($normalized, '1') && strlen($normalized) === 11) {
        return (bool)preg_match('/^1[2-9]\d{9}$/', $normalized);
    }

    // Australia: 614XXXXXXXX (11 digits)
    if (str_starts_with($normalized, '61')) {
        return (bool)preg_match('/^614\d{8}$/', $normalized);
    }

    return true;
}

function student_parent_phone_forget(int $userId = -1): void
{
    student_parent_phone_for_user(null, $userId, true);
    student_has_parent_phone(null, $userId, true);
}

function student_parent_phone_for_user(?PDO $pdo, int $userId = -1, bool $forget = false): string
{
    static $cache = [];
    if ($forget) {
        if ($userId !== -1) {
            unset($cache[$userId]);
        } else {
            $cache = [];
        }
        return '';
    }
    if ($userId === -1) {
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        } elseif (isset($_SESSION['student_id'])) {
            $userId = (int)$_SESSION['student_id'];
        } else {
            return '';
        }
    }
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    if (!($pdo instanceof PDO)) {
        global $pdo;
    }
    if (!($pdo instanceof PDO)) {
        $cache[$userId] = '';
        return '';
    }
    $phone = '';
    try {
        $stmt = $pdo->prepare('SELECT parent_whatsapp FROM student_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $phone = student_parent_phone_normalize((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        $phone = '';
    }
    if (!student_parent_phone_valid($phone)) {
        $phone = '';
    }
    $cache[$userId] = $phone;
    return $phone;
}

function student_own_login_phone(?PDO $pdo, int $userId): string
{
    if ($userId < 1 || !($pdo instanceof PDO)) {
        return '';
    }
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, sp.whatsapp_number
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.id = ? AND u.role = 'student'
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $fromProfile = student_parent_phone_normalize((string)($row['whatsapp_number'] ?? ''));
        if (student_parent_phone_valid($fromProfile)) {
            return $fromProfile;
        }
        $fromUser = student_parent_phone_normalize((string)($row['username'] ?? ''));
        return student_parent_phone_valid($fromUser) ? $fromUser : '';
    } catch (Throwable $e) {
        return '';
    }
}

function student_has_parent_phone(?PDO $pdo = null, int $userId = -1, bool $forget = false): bool
{
    static $cache = [];
    if ($forget) {
        if ($userId !== -1) {
            unset($cache[$userId]);
        } else {
            $cache = [];
        }
        return false;
    }
    if ($userId === -1) {
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        } elseif (isset($_SESSION['student_id'])) {
            $userId = (int)$_SESSION['student_id'];
        } else {
            return false;
        }
    }
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    if (!($pdo instanceof PDO)) {
        global $pdo;
    }
    if (!($pdo instanceof PDO)) {
        $cache[$userId] = false;
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT parent_whatsapp, parent_name FROM student_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $phone = student_parent_phone_normalize((string)($row['parent_whatsapp'] ?? ''));
            $name = trim((string)($row['parent_name'] ?? ''));
            $hasBoth = student_parent_phone_valid($phone) && $name !== '';
            $cache[$userId] = $hasBoth;
            return $hasBoth;
        }
    } catch (Throwable $e) {
    }
    $cache[$userId] = false;
    return false;
}

/**
 * @return array{ok:bool,message:string,phone?:string,parent_name?:string}
 */
function student_save_parent_phone(
    PDO $pdo,
    int $userId,
    string $rawPhone,
    string $parentName = '',
    string $dialCode = '',
    string $countryIso = ''
): array {
    $phone = student_parent_phone_normalize($rawPhone, $dialCode, $countryIso);
    if (!student_parent_phone_valid($phone)) {
        return ['ok' => false, 'message' => 'Enter a valid parent WhatsApp number.'];
    }
    $own = student_own_login_phone($pdo, $userId);
    if ($own !== '' && $phone === $own) {
        return ['ok' => false, 'message' => 'Use your parent or guardian’s number, not your own mobile number.'];
    }
    $name = trim($parentName);
    if ($name === '') {
        return ['ok' => false, 'message' => 'Please enter your parent or guardian’s name.'];
    }
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 120);
    } elseif (strlen($name) > 120) {
        $name = substr($name, 0, 120);
    }
    try {
        $stmt = $pdo->prepare('SELECT user_id FROM student_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() !== false) {
            $pdo->prepare('
                UPDATE student_profiles
                SET parent_whatsapp = ?,
                    parent_name = ?
                WHERE user_id = ?
            ')->execute([$phone, $name, $userId]);
        } else {
            $pdo->prepare('
                INSERT INTO student_profiles (user_id, parent_name, parent_whatsapp)
                VALUES (?, ?, ?)
            ')->execute([$userId, $name, $phone]);
        }
    } catch (Throwable $e) {
        error_log('student_save_parent_phone: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Unable to save the parent number right now. Try again or contact the college office.'];
    }
    unset($_SESSION['parent_wa_pending']);
    student_parent_phone_forget($userId);
    return [
        'ok' => true,
        'message' => 'Parent WhatsApp number saved.',
        'phone' => $phone,
        'parent_name' => $name,
    ];
}

function student_parent_phone_script(): string
{
    $file = (string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    return strtolower(basename(str_replace('\\', '/', $file)));
}

function student_parent_phone_dashboard_url(): string
{
    return rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/student/dashboard.php';
}

function student_joining_online_class(): bool
{
    $script = str_replace('\\', '/', strtolower((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (str_contains($script, '/classroom/') || str_contains($script, '/api/classroom/')) {
        return true;
    }
    return str_contains($script, '/student/class.php')
        || str_contains($script, '/student/lesson.php')
        || str_contains($script, '/student/join_class.php')
        || str_contains($script, '/student/pay_lesson.php');
}

function student_parent_phone_request_exempt(): bool
{
    if (!empty($_SESSION['parent_phone_deferred']) || student_joining_online_class()) {
        return true;
    }
    $script = student_parent_phone_script();
    if ($script === 'save_parent_phone.php') {
        return true;
    }
    if ($script !== 'settings.php') {
        return false;
    }
    if (isset($_GET['dismiss_phone_modal'])) {
        return true;
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return false;
    }
    $form = (string)($_POST['form'] ?? '');
    return in_array($form, [
        'parent_required',
        'parent_wa_verify',
        'parent_wa_cancel',
        'phone_request',
        'phone_resend',
        'phone_verify',
        'phone_cancel',
        'dismiss_phone_modal',
        'skip_parent_phone',
    ], true);
}

function student_enforce_parent_phone(?PDO $pdo = null, bool $allowDashboard = true): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (function_exists('current_role') && current_role() !== 'student') {
        return;
    }
    if (student_parent_phone_request_exempt()) {
        return;
    }
    if ($allowDashboard && student_parent_phone_script() === 'dashboard.php') {
        return;
    }
    if (student_has_parent_phone($pdo)) {
        return;
    }
    $url = student_parent_phone_dashboard_url();
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'need_parent_phone' => true,
            'error' => 'Enter a parent WhatsApp number before you can continue.',
            'redirect' => $url,
        ]);
        exit;
    }
    header('Location: ' . $url);
    exit;
}

function student_require_parent_phone(?PDO $pdo = null): void
{
    student_enforce_parent_phone($pdo, false);
}

function student_parent_phone_gate_render(): void
{
    static $rendered = false;
    if ($rendered) {
        return; // Guard: only render once per page load (prevents duplicate modal that blocks input)
    }
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (function_exists('current_role') && current_role() !== 'student') {
        return;
    }
    if (!empty($_SESSION['parent_phone_deferred']) || student_joining_online_class()) {
        return;
    }
    global $pdo;
    $db = $pdo instanceof PDO ? $pdo : null;
    if (student_has_parent_phone($db)) {
        return;
    }
    $rendered = true; // Mark as rendered before output to prevent any re-entrant double render
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
    $own = $db && $userId > 0 ? student_own_login_phone($db, $userId) : '';
    $pending = $_SESSION['parent_wa_pending'] ?? null;
    if ($db && $userId > 0) {
        try {
            $stmt = $db->prepare('SELECT parent_whatsapp, parent_name FROM student_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (empty($pending['phone'])) {
                    $pendingPhone = student_parent_phone_normalize((string)($row['parent_whatsapp'] ?? ''));
                }
                if (empty($pending['name'])) {
                    $pendingName = trim((string)($row['parent_name'] ?? ''));
                }
            }
        } catch (Throwable $e) {}
    }
    if (is_array($pending) && !empty($pending['phone'])) {
        $pendingPhone = student_parent_phone_normalize((string)$pending['phone']);
    }
    if (is_array($pending) && !empty($pending['name'])) {
        $pendingName = trim((string)$pending['name']);
    }
    $fullName = trim((string)($_SESSION['student_full_name'] ?? ''));
    $firstName = $fullName !== '' ? (preg_split('/\s+/', $fullName)[0] ?? '') : '';
    $hello = $firstName !== '' ? 'Hi, ' . $firstName : 'One more step';
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';
    $saveUrl = $base . 'student/settings.php';
    $logoutUrl = $base . 'logout.php';
    $css = dirname(__DIR__) . '/assets/css/parent-phone-gate.css';
    $js = dirname(__DIR__) . '/assets/js/parent-phone-gate.js';
    $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $csrf = function_exists('csrf_field') ? csrf_field() : '';
    $countryDb = student_country_dial_database();
    $defaultIso = student_detect_country_iso();
    $selectedIso = $defaultIso;
    $phoneValue = '';

    if ($pendingPhone !== '') {
        $parsed = student_parse_e164_country($pendingPhone);
        $selectedIso = $parsed['iso'];
        $phoneValue = $parsed['local'];
    }

    $country = $countryDb[$selectedIso] ?? $countryDb['LK'];
    $countryFlag = $country['flag'];
    $countryDial = $country['dial'];
    $countrySample = $country['sample'];
    $countryName = $country['name'];
    ?>
<link rel="stylesheet" href="<?= $esc($base . 'assets/css/parent-phone-gate.css') ?>?v=<?= is_file($css) ? filemtime($css) : '1' ?>">
<div id="parentPhoneGate" class="parent-phone-gate" role="dialog" aria-modal="true" aria-labelledby="parentPhoneGateTitle"
     data-own-phone="<?= $esc($own) ?>" data-detected-country="<?= $esc($selectedIso) ?>">
    <div class="parent-phone-gate-card">
        <aside class="parent-phone-gate-aside">
            <div class="parent-phone-gate-brand">Edexcel College</div>
            <div class="parent-phone-gate-visual" aria-hidden="true">
                <span class="parent-phone-gate-ring"></span>
                <span class="parent-phone-gate-ring is-2"></span>
                <span class="parent-phone-gate-phone"><i class="bi bi-whatsapp"></i></span>
            </div>
            <h2>Keep a parent in the loop</h2>
            <p>This number gets college updates. It also gets a notice if someone signs in on a new device.</p>
            <ul class="parent-phone-gate-points">
                <li><i class="bi bi-check-lg"></i> Evening notes on classes, fees, and attendance</li>
                <li><i class="bi bi-check-lg"></i> Alert when a new phone or computer is registered</li>
                <li><i class="bi bi-check-lg"></i> Parent view without a separate login</li>
            </ul>
        </aside>
        <div class="parent-phone-gate-main">
            <div class="parent-phone-gate-kicker">Optional</div>
            <h3 id="parentPhoneGateTitle"><?= $esc($hello) ?></h3>
            <p class="parent-phone-gate-lead">A parent WhatsApp number is optional. You can join online classes with or without it.</p>
            <form method="POST" action="<?= $esc($saveUrl) ?>" id="parentPhoneGateForm" novalidate>
                <?= $csrf ?>
                <input type="hidden" name="form" value="parent_required">
                <input type="hidden" name="ajax" value="0" id="parentPhoneGateAjax">
                <input type="hidden" name="country_iso" id="parentPhoneGateCountryIso" value="<?= $esc($selectedIso) ?>">
                <input type="hidden" name="dial_code" id="parentPhoneGateDialCodeInput" value="<?= $esc($countryDial) ?>">
                <label class="form-label" for="parentPhoneGateName">Parent or guardian name</label>
                <input class="form-control" id="parentPhoneGateName" name="parent_name" maxlength="120" autocomplete="name" value="<?= $esc($pendingName) ?>" placeholder="e.g. Nimal Perera" required autofocus>
                <label class="form-label mt-3" for="parentPhoneGateWhatsapp">Parent WhatsApp number</label>
                <div class="parent-phone-gate-field-group">
                    <div class="parent-phone-gate-phonewrap" id="parentPhoneGateWrap">
                        <button type="button" class="parent-phone-gate-country-btn" id="parentPhoneGateCountryBtn" aria-haspopup="listbox" aria-expanded="false" title="Change country code" aria-controls="parentPhoneGateCountryDropdown">
                            <span class="parent-phone-gate-flag" id="parentPhoneGateFlag"><?= $esc($countryFlag) ?></span>
                            <span class="parent-phone-gate-dial" id="parentPhoneGateDialCode"><?= $esc($countryDial) ?></span>
                            <i class="bi bi-chevron-down parent-phone-gate-arrow" aria-hidden="true"></i>
                        </button>
                        <input class="form-control" id="parentPhoneGateWhatsapp" name="parent_whatsapp" inputmode="tel" autocomplete="tel" required
                               placeholder="<?= $esc($countrySample) ?>" value="<?= $esc($phoneValue) ?>"
                               data-own-phone="<?= $esc($own) ?>" data-initial-country="<?= $esc($selectedIso) ?>">
                        <span class="parent-phone-gate-status" id="parentPhoneGateStatus" aria-hidden="true"><i class="bi bi-phone"></i></span>
                    </div>
                    <div class="parent-phone-gate-country-dropdown" id="parentPhoneGateCountryDropdown" role="listbox" aria-label="Select country" hidden>
                        <div class="parent-phone-gate-country-search-wrap">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="text" class="parent-phone-gate-country-search" id="parentPhoneGateCountrySearch" placeholder="Search country or code…" autocomplete="off" spellcheck="false">
                            <button type="button" class="parent-phone-gate-country-clear" id="parentPhoneGateCountryClear" aria-label="Clear search" hidden><i class="bi bi-x"></i></button>
                        </div>
                        <div class="parent-phone-gate-country-list" id="parentPhoneGateCountryList" tabindex="-1">
                            <?php foreach ($countryDb as $iso => $item): ?>
                                <button type="button" class="parent-phone-gate-country-item<?= $iso === $selectedIso ? ' is-selected' : '' ?>"
                                        data-iso="<?= $esc($iso) ?>"
                                        data-dial="<?= $esc($item['dial']) ?>"
                                        data-flag="<?= $esc($item['flag']) ?>"
                                        data-sample="<?= $esc($item['sample']) ?>"
                                        data-name="<?= $esc($item['name']) ?>"
                                        role="option"
                                        aria-selected="<?= $iso === $selectedIso ? 'true' : 'false' ?>">
                                    <span class="parent-phone-gate-country-item-main">
                                        <span class="parent-phone-gate-country-item-flag"><?= $esc($item['flag']) ?></span>
                                        <span class="parent-phone-gate-country-item-name"><?= $esc($item['name']) ?></span>
                                    </span>
                                    <span class="parent-phone-gate-country-item-dial"><?= $esc($item['dial']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <p class="parent-phone-gate-hint" id="parentPhoneGateHint"><?= $selectedIso === 'LK' ? 'Use 077 123 4567 — not your own mobile number.' : 'Enter parent WhatsApp number without country code.' ?></p>
                <div class="parent-phone-gate-error is-empty" id="parentPhoneGateError" role="alert">Enter a valid number</div>
                <button class="btn btn-primary w-100 parent-phone-gate-submit" type="submit" id="parentPhoneGateSubmit">Save and continue</button>
            </form>
            <form method="POST" action="<?= $esc($saveUrl) ?>">
                <?= $csrf ?>
                <input type="hidden" name="form" value="skip_parent_phone">
                <button class="parent-phone-gate-logout" type="submit">Continue without WhatsApp</button>
            </form>
            <a class="parent-phone-gate-logout" href="<?= $esc($logoutUrl) ?>">Not you? Sign out</a>
        </div>
        <div class="parent-phone-gate-success" aria-live="polite">
            <i class="bi bi-check-lg"></i>
            <h3>Parent number saved</h3>
            <p>Opening your portal…</p>
        </div>
    </div>
</div>
<script>
/* Inline gate bootstrap — runs synchronously at parse time, no external dependency.
   1. Removes any duplicate #parentPhoneGate elements.
   2. Cleans up any conflicting Bootstrap modals or modal backdrops.
   3. Captures and stops focusin propagation so no background focus trap can hijack focus.
   4. Ensures inputs are enabled, focusable, and receive auto-focus. */
(function () {
  var gates = document.querySelectorAll('[id="parentPhoneGate"]');
  for (var i = 1; i < gates.length; i++) {
    gates[i].parentNode && gates[i].parentNode.removeChild(gates[i]);
  }
  var gate = document.getElementById('parentPhoneGate');
  if (gate) {
    // Stop any document-level focus trap (such as Bootstrap FocusTrap) from stealing focus
    gate.addEventListener('focusin', function (e) {
      e.stopImmediatePropagation();
    }, true);

    // Dismiss any active Bootstrap modals and remove stale backdrops
    if (window.bootstrap) {
      try {
        var openModals = document.querySelectorAll('.modal.show, .modal');
        for (var k = 0; k < openModals.length; k++) {
          var inst = bootstrap.Modal.getInstance(openModals[k]);
          if (inst) { inst.hide(); inst.dispose(); }
        }
      } catch (e) {}
    }
    var backdrops = document.querySelectorAll('.modal-backdrop');
    for (var b = 0; b < backdrops.length; b++) {
      backdrops[b].parentNode && backdrops[b].parentNode.removeChild(backdrops[b]);
    }
    document.body.classList.remove('modal-open');

    // Make absolutely sure inputs and card are interactive
    gate.style.pointerEvents = 'auto';
    var card = gate.querySelector('.parent-phone-gate-card');
    if (card) card.style.pointerEvents = 'auto';

    var inputs = gate.querySelectorAll('input, button, select, textarea');
    for (var j = 0; j < inputs.length; j++) {
      inputs[j].style.pointerEvents = 'auto';
      inputs[j].removeAttribute('disabled');
      inputs[j].removeAttribute('inert');
    }

    // Auto-focus first empty field
    setTimeout(function () {
      var nameIn = document.getElementById('parentPhoneGateName');
      var phoneIn = document.getElementById('parentPhoneGateWhatsapp');
      if (nameIn && !nameIn.value.trim()) {
        nameIn.focus();
      } else if (phoneIn && !phoneIn.value.trim()) {
        phoneIn.focus();
      }
    }, 60);
  }
})();
</script>
<script src="<?= $esc($base . 'assets/js/parent-phone-gate.js') ?>?v=<?= is_file($js) ? filemtime($js) : '1' ?>"></script>
    <?php
}
