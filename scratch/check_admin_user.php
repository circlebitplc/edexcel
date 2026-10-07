<?php
require __DIR__ . '/../config/database.php';
global $pdo;

echo "=== USER TABLE STRUCTURE ===\n";
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== ADMIN USERS ===\n";
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' OR username LIKE '%admin%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
