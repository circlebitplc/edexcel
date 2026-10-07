<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$label = 'pre-biometrics-' . date('Ymd_His');
$backupDir = __DIR__ . '/../backups/' . $label;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

echo "=== CREATING PRE-BIOMETRICS RESTORE POINT: $label ===\n";

$fullSqlPath = $backupDir . '/database_full.sql';
$fhFull = fopen($fullSqlPath, 'wb');
if ($fhFull === false) {
    throw new RuntimeException("Cannot open $fullSqlPath for writing.");
}

fwrite($fhFull, "-- Edexcel College Pre-Biometrics Restore Point\n");
fwrite($fhFull, "-- Release Label: $label\n");
fwrite($fhFull, "-- Created At: " . date('Y-m-d H:i:s') . "\n");
fwrite($fhFull, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
foreach ($tables as $table) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        continue;
    }
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $createSql = $create['Create Table'] ?? '';
    if ($createSql !== '') {
        fwrite($fhFull, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($fhFull, $createSql . ";\n\n");
    }

    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $escapedCols = array_map(fn($c) => "`$c`", $cols);
        $colList = implode(', ', $escapedCols);

        $chunkSize = 100;
        $chunks = array_chunk($rows, $chunkSize);
        foreach ($chunks as $chunk) {
            $valRows = [];
            foreach ($chunk as $row) {
                $vals = [];
                foreach ($cols as $col) {
                    $v = $row[$col];
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string)$v);
                    }
                }
                $valRows[] = '(' . implode(', ', $vals) . ')';
            }
            fwrite($fhFull, "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $valRows) . ";\n\n");
        }
    }
}
fwrite($fhFull, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fhFull);

echo "Backup created at $backupDir\n";
