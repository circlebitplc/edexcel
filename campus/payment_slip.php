<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BankTransferService;
use Edexcel\Services\PaymentTransactionService;

require_staff();
ensure_recordings_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$payments = new PaymentTransactionService($pdo);
$txn = $id > 0 ? $payments->findById($id) : null;
if (!$txn || strtolower((string)($txn['gateway'] ?? '')) !== 'bank') {
    http_response_code(404);
    exit('Slip not found.');
}

$bank = new BankTransferService($pdo);
if (!$bank->staffMayViewTxn((int)($_SESSION['user_id'] ?? 0), $txn)) {
    http_response_code(403);
    exit('You cannot open this slip.');
}

$stored = basename((string)($txn['slip_path'] ?? ''));
if ($stored === '' || str_contains($stored, '..') || str_contains($stored, '/') || str_contains($stored, '\\')) {
    http_response_code(404);
    exit('Slip not found.');
}

$path = BankTransferService::storageDir() . '/' . $stored;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('File missing.');
}

$downloadName = basename((string)($txn['slip_original_name'] ?? $stored));
if ($downloadName === '' || $downloadName === '.' || $downloadName === '..') {
    $downloadName = $stored;
}
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$mime = $types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $downloadName) . '"');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
