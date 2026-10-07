<?php
declare(strict_types=1);

/**
 * Secure Authenticated Download Controller for Phone Import Errors
 *
 * Restricts access to authenticated administrators only.
 * Validates import ID, path traversal prevention, and streams CSV.
 */
require_once __DIR__ . '/../config/bootstrap.php';

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo 'Access denied: Administrator access required.';
    exit;
}

$importId = (int)($_GET['import_id'] ?? 0);
if ($importId <= 0) {
    http_response_code(400);
    echo 'Invalid or missing import ID.';
    exit;
}

$stmt = $pdo->prepare("SELECT id, filename, error_csv_path FROM phone_import_history WHERE id = ? LIMIT 1");
$stmt->execute([$importId]);
$hist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hist || empty($hist['error_csv_path'])) {
    http_response_code(404);
    echo 'No error CSV file found for this import record.';
    exit;
}

$relPath = ltrim((string)$hist['error_csv_path'], '/\\');
$fullPath = dirname(__DIR__) . '/' . $relPath;

// Strictly prevent path traversal: file must reside within storage/imports/
$storageBase = realpath(dirname(__DIR__) . '/storage/imports');
$realFile = realpath($fullPath);

if ($realFile === false || !$storageBase || !str_starts_with($realFile, $storageBase) || !is_file($realFile)) {
    http_response_code(404);
    echo 'Error file not found on disk or invalid storage location.';
    exit;
}

// Download stream with security headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="import_errors_' . $importId . '.csv"');
header('Content-Length: ' . (string)filesize($realFile));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

readfile($realFile);
exit;
