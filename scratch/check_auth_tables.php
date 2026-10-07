<?php
require __DIR__ . '/../config/database.php';
global $pdo;

echo "=== LOGIN_ATTEMPTS ===\n";
$stmt = $pdo->query("DESCRIBE login_attempts");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== STAFF_PERMISSIONS ===\n";
$stmt = $pdo->query("DESCRIBE staff_permissions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT * FROM staff_permissions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
