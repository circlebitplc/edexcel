<?php
require __DIR__ . '/../config/database.php';
global $pdo;

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES (" . count($tables) . "):\n";
foreach ($tables as $t) {
    echo "- $t\n";
}
