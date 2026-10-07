<?php
// config/database.php
require_once __DIR__ . '/load_env.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    try {
        $pdo->exec("SET time_zone = '+05:30'");
    } catch (Throwable $e) {
    }
} catch (PDOException $e) {
    if (defined('DB_ALLOW_FAILURE') && DB_ALLOW_FAILURE) {
        $pdo = null;
        $database_error = $e->getMessage();
    } else {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Database connection failed. Please contact the administrator.');
    }
}
