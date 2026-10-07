<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/config.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit('Document not found.');
}

$stmt = $pdo->prepare('SELECT id, student_id, title, file_name, file_url FROM student_documents WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

$studentId = (int)$doc['student_id'];
$role = current_role();
$userId = (int)($_SESSION['user_id'] ?? 0);
$allowed = false;
if ($role === 'student' && $userId === $studentId) {
    $allowed = true;
} elseif ($role === 'admin' || $role === 'teacher') {
    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
    $allowed = function_exists('campus_staff_can_access_student')
        && campus_staff_can_access_student($pdo, $studentId, is_admin(), $teacherId);
}

if (!$allowed) {
    http_response_code(403);
    exit('You cannot open this document.');
}

$stored = basename((string)($doc['file_url'] ?? ''));
if ($stored === '' || str_contains($stored, '..') || str_contains($stored, '/') || str_contains($stored, '\\')) {
    http_response_code(404);
    exit('Document not found.');
}

$path = __DIR__ . '/files/documents/' . $stored;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('File missing.');
}

$downloadName = basename((string)($doc['file_name'] ?? $stored));
if ($downloadName === '' || $downloadName === '.' || $downloadName === '..') {
    $downloadName = $stored;
}

$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $downloadName) . '"');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
