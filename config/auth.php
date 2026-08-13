<?php
// config/auth.php
require_once __DIR__ . '/error_handler.php'; // Load error handler first
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

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
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        die('Access denied. Admin only.');
    }
}

function require_teacher() {
    require_login();
    if (!is_teacher()) {
        die('Access denied. Teachers only.');
    }
}

function get_logged_in_user($pdo) {
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function regenerate_session() {
    session_regenerate_id(true);
}