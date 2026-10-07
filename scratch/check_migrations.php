<?php
require __DIR__ . '/../config/database.php';
global $pdo;

$stmt = $pdo->query("SELECT * FROM schema_migrations ORDER BY id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
