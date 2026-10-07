<?php
// config/security.php
// CSRF, validation, sanitization, audit logging

require_once __DIR__ . '/client_ip.php';

if (!headers_sent() && PHP_SAPI !== 'cli') {
    $httpsOn = eck_request_is_https();
    if (!$httpsOn) {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && !preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host)) {
            header('Location: https://' . $host . (string)($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $secure = eck_request_is_https();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    if ($secure) {
        ini_set('session.cookie_secure', '1');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE && (!empty($_SESSION['user_id']) || !empty($_SESSION['parent_id']))) {
    $timeout = defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : 3600;
    if ($timeout > 0) {
        $last = (int)($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $timeout) {
            $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
            $goParent = str_contains($script, '/parent/');
            $goStudent = str_contains($script, '/student/');
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
            }
            session_destroy();
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                if ($goParent) {
                    header('Location: /parent/login.php');
                } else {
                    header('Location: ' . ($goStudent ? '/index.php#student-login' : '/login.php'));
                }
                exit;
            }
        } elseif ($last === 0 || (time() - $last) >= 60) {
            // Rewrite the session at most once a minute. The timeout is still enforced.
            $_SESSION['last_activity'] = time();
        }
    }
}

// --- CSRF ---
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return generate_csrf_token();
    }
}

// --- Input validation ---
function validate_input($data, $type = 'string') {
    $data = trim($data);
    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'date':
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : false;
        case 'time':
            return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data) ? $data : false;
        case 'string':
        default:
            return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
    }
}

function sanitize_array($array, $rules) {
    $sanitized = [];
    foreach ($rules as $key => $type) {
        if (isset($array[$key])) {
            $sanitized[$key] = validate_input($array[$key], $type);
        } else {
            $sanitized[$key] = null;
        }
    }
    return $sanitized;
}

// --- Audit Logging (silent fail) ---
function log_audit($pdo, $action, $table_name, $record_id = null, $old_values = null, $new_values = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0
    ? (int)$_SESSION['user_id']
    : null;
    $username = $_SESSION['username'] ?? 'system';
    $ip = function_exists('eck_client_ip') ? eck_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs 
                               (user_id, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $username,
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $ip,
            $user_agent
        ]);
    } catch (Exception $e) {
        // Silently fail – do NOT break the main operation
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

function login_throttle_key(string $username): string
{
    $ip = function_exists('eck_client_ip') ? eck_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    if ($ip === '') {
        $ip = '0';
    }
    return hash('sha256', $ip . '|' . strtolower(trim($username)));
}

function login_is_locked($pdo, string $username): bool
{
    if (!($pdo instanceof PDO)) {
        return false;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                throttle_key CHAR(64) NOT NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_login_attempts_key (throttle_key, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE throttle_key = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([login_throttle_key($username)]);
        return (int)$stmt->fetchColumn() >= 8;
    } catch (Throwable $e) {
        return false;
    }
}

function login_record_failure($pdo, string $username): void
{
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $pdo->prepare("INSERT INTO login_attempts (throttle_key) VALUES (?)")
            ->execute([login_throttle_key($username)]);
        if (class_exists(\Edexcel\Services\SecurityEventService::class)) {
            (new \Edexcel\Services\SecurityEventService($pdo))->record('LOGIN_FAILED', [
                'result' => 'denied',
                'message' => 'Sign-in was refused',
            ]);
        }
    } catch (Throwable $e) {
        error_log('login_record_failure: ' . $e->getMessage());
    }
}

function login_clear_failures($pdo, string $username): void
{
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE throttle_key = ?")
            ->execute([login_throttle_key($username)]);
    } catch (Throwable $e) {
    }
}

require_once __DIR__ . '/abuse.php';
require_once __DIR__ . '/response_security.php';
