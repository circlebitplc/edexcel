<?php
declare(strict_types=1);

/**
 * Save / read the current user's theme preference.
 * Guests receive ok without a database write (localStorage + cookie on the client).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/theme.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
if ($pdo instanceof PDO) {
    app_theme_ensure_schema($pdo);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'ok' => true,
        'theme' => app_theme_current($pdo),
        'logged_in' => app_theme_logged_in_user_id() > 0,
        'allowed' => app_theme_ids(),
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($body)) {
    $body = [];
    if (is_string($raw) && $raw !== '' && str_contains($raw, '=')) {
        parse_str($raw, $parsed);
        if (is_array($parsed)) {
            $body = $parsed;
        }
    }
}

$token = app_theme_request_csrf($body);
$userId = app_theme_logged_in_user_id();
if ($userId > 0 && function_exists('verify_csrf_token') && !verify_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$rawTheme = strtolower(trim((string) ($body['theme'] ?? $_POST['theme'] ?? '')));
if (!in_array($rawTheme, app_theme_ids(), true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown theme']);
    exit;
}
$theme = app_theme_normalize($rawTheme);
app_theme_remember($theme);

$saved = false;
if ($userId > 0 && $pdo instanceof PDO) {
    $saved = app_theme_save_user($pdo, $userId, $theme);
}

echo json_encode([
    'ok' => true,
    'theme' => $theme,
    'saved' => $saved,
    'logged_in' => $userId > 0,
]);
