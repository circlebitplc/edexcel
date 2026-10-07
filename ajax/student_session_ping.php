<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../student/device_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!is_logged_in() || current_role() !== 'student') {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'reason' => 'expired',
        'redirect' => student_login_url(),
    ]);
    exit;
}

echo json_encode(['ok' => true]);
