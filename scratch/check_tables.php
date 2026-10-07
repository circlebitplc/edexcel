<?php
require __DIR__ . '/../config/database.php';
global $pdo;
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    if (preg_match('/class|schedul|subject|timetabl|batch|whatsapp/i', $t)) {
        echo "$t\n";
    }
}
