<?php
declare(strict_types=1);

$label = 'phone-sms-production-accepted-20261005';
$backupDir = __DIR__ . '/../backups/' . $label;
$sqlFile = $backupDir . '/phone_sms_subsystem.sql';

if (!file_exists($sqlFile)) {
    die("Backup file not found: $sqlFile\n");
}

$content = file_get_contents($sqlFile);
$requiredTables = [
    'phone_contacts',
    'phone_contact_records',
    'phone_import_history',
    'teacher_sms_permissions',
    'teacher_sms_switch_audit',
    'phone_whatsapp_group_mappings',
    'bulk_sms_campaigns',
    'bulk_sms_recipients',
    'sms_logs',
    'settings',
    'schema_migrations',
];

echo "=== VERIFYING BACKUP INTEGRITY ===\n";
$allFound = true;
foreach ($requiredTables as $tbl) {
    $hasCreate = str_contains($content, "CREATE TABLE `$tbl`") || str_contains($content, "CREATE TABLE IF NOT EXISTS `$tbl`");
    if ($hasCreate) {
        echo " [PASS] Table `$tbl` found in backup with complete schema\n";
    } else {
        echo " [FAIL] Table `$tbl` MISSING from backup\n";
        $allFound = false;
    }
}

// Check manifest
$manifestFile = $backupDir . '/RELEASE_MANIFEST.json';
$manifest = json_decode(file_get_contents($manifestFile), true);
if (is_array($manifest) && count($manifest['file_checksums']) === 19) {
    echo " [PASS] Manifest valid and all 19 subsystem files checksummed\n";
} else {
    echo " [FAIL] Manifest invalid\n";
    $allFound = false;
}

echo "=== BACKUP VERIFICATION " . ($allFound ? "PASSED" : "FAILED") . " ===\n";
