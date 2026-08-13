<?php
// config/security.php
// CSRF, validation, sanitization, audit logging

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
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
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
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