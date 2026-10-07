<?php
require __DIR__ . '/../config/database.php';
global $pdo;

$migrationDir = __DIR__ . '/../database/migrations';
$files = glob($migrationDir . '/*.sql');
foreach ($files as $file) {
    $fn = basename($file);
    if ($fn < '056_admin_biometrics.sql') {
        $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)')->execute([$fn]);
    }
}
echo "Populated schema_migrations for legacy files.\n";
