<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$role = (string)($_SESSION['role'] ?? '');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

$target = ($role === 'student')
    ? student_login_url()
    : (BASE_URL . 'index.php');

header('Location: ' . $target);
exit;
