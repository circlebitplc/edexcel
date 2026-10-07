<?php
require __DIR__ . '/../config/database.php';
global $pdo;

$sql = file_get_contents(__DIR__ . '/../database/migrations/056_admin_biometrics.sql');
$pdo->exec($sql);
$stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
$stmt->execute(['056_admin_biometrics.sql']);
echo "056_admin_biometrics.sql applied successfully.\n";

$tables = ['admin_passkeys', 'admin_face_credentials', 'authentication_audit', 'admin_biometric_challenges'];
foreach ($tables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount();
    echo "Table $t: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}
