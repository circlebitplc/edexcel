<?php
// ajax/get_subjects.php (with debug)
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_staff();

$teacher_id = (int)($_GET['teacher_id'] ?? 0);
if (!$teacher_id) {
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare("SELECT s.id, s.name FROM subjects s
                       JOIN teacher_subjects ts ON s.id = ts.subject_id
                       WHERE ts.teacher_id = ? AND s.deleted_at IS NULL
                       ORDER BY s.name");
$stmt->execute([$teacher_id]);
$subjects = $stmt->fetchAll();

// Debug: log to error log
error_log('get_subjects.php - teacher_id: ' . $teacher_id . ', count: ' . count($subjects));

header('Content-Type: application/json');
echo json_encode($subjects);
