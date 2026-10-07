<?php
require __DIR__ . '/../config/database.php';
global $pdo;

echo "=== TEACHERS TABLE STRUCTURE ===\n";
$stmt = $pdo->query("DESCRIBE teachers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== TEACHERS ROWS ===\n";
$stmt = $pdo->query("SELECT * FROM teachers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
