<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_staff();

$subject_id = (int)($_GET['subject_id'] ?? 0);
if (!$subject_id) {
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare("SELECT c.id, c.name FROM student_classes c
                       JOIN subject_classes sc ON c.id = sc.class_id
                       WHERE sc.subject_id = ? AND c.deleted_at IS NULL
                       ORDER BY c.name");
$stmt->execute([$subject_id]);
$classes = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($classes);
