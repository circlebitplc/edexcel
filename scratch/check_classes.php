<?php
require __DIR__ . '/../config/database.php';
global $pdo;

echo "=== TIMETABLE COLUMNS & SAMPLE ===\n";
try {
    $rows = $pdo->query("SELECT * FROM timetable LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}

echo "=== SUBJECT_CLASSES COLUMNS & SAMPLE ===\n";
try {
    $rows = $pdo->query("SELECT * FROM subject_classes LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
