<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../student/otp_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$phone = (string)($_GET['phone'] ?? $_GET['username'] ?? '');

$payload = [
    'ok' => true,
    'first_login' => false,
    'has_logged_in' => false,
];

if (!($pdo instanceof PDO)) {
    echo json_encode($payload);
    exit;
}

ensure_users_last_login_column($pdo);

$normalized = normalize_phone($phone);
if (!valid_lk_phone($normalized)) {
    echo json_encode($payload);
    exit;
}

try {
    if (function_exists('login_is_locked')) {
        login_is_locked($pdo, '__student_login_status_init__');
    }
    $throttleKey = login_throttle_key('__student_login_status__');
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE throttle_key = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $countStmt->execute([$throttleKey]);
    if ((int)$countStmt->fetchColumn() >= 40) {
        echo json_encode($payload);
        exit;
    }
    $pdo->prepare('INSERT INTO login_attempts (throttle_key) VALUES (?)')->execute([$throttleKey]);

    $user = find_student_user_by_phone($pdo, $normalized);
    if ($user) {
        $first = student_is_first_login($user, $pdo);
        $payload['first_login'] = $first;
        $payload['has_logged_in'] = !$first;
    }
} catch (Throwable $e) {
    error_log('student_login_status: ' . $e->getMessage());
}

echo json_encode($payload);
