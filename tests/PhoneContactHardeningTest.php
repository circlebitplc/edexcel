<?php
declare(strict_types=1);

/**
 * Production Hardening and Reliability Verification Suite
 *
 * Covers:
 * 1. Gateway Standardization ('ipromo')
 * 2. Teacher Non-iPromo Gateway Rejection
 * 3. Admin Test SMS Safety & Quota Isolation
 * 4. Global Teacher SMS Emergency Switch Audit Logging
 * 5. Teacher Authorization Chain (Order of Enforcement)
 * 6. Atomic Quota Protection & Campaign Idempotency Key
 * 7. Soft Delete / Archive & Restore
 * 8. Private Error CSV Protection & Access Controls
 * 9. Canonical WhatsApp Group Normalization & Filtering
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;
use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;

PhoneContactService::ensureSchema($pdo);
TeacherSmsService::ensureSchema($pdo);
BulkSmsService::ensureSchema($pdo);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertHardening(bool $condition, string $description): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo " [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo "   PHONE CONTACTS & SMS — PRODUCTION HARDENING TEST SUITE          \n";
echo "====================================================================\n\n";

// Setup Test Users and cleanup previous runs
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone IN ('94779998881', '94771112233', '94773344556')");
$pdo->exec("DELETE FROM phone_whatsapp_group_mappings WHERE raw_group_name = '2026 IGCSE Kandy (Batch A)'");

$pdo->exec("INSERT INTO users (username, password_hash, role, is_active, created_at)
            VALUES ('harden_admin', 'hash', 'admin', 1, NOW())
            ON DUPLICATE KEY UPDATE is_active = 1");
$adminUserId = (int)$pdo->query("SELECT id FROM users WHERE username = 'harden_admin' LIMIT 1")->fetchColumn();

$pdo->exec("INSERT INTO users (username, password_hash, role, is_active, created_at)
            VALUES ('harden_teacher', 'hash', 'teacher', 1, NOW())
            ON DUPLICATE KEY UPDATE is_active = 1");
$teacherUserId = (int)$pdo->query("SELECT id FROM users WHERE username = 'harden_teacher' LIMIT 1")->fetchColumn();

$pdo->exec("INSERT INTO users (username, password_hash, role, is_active, created_at)
            VALUES ('harden_inactive_teacher', 'hash', 'teacher', 0, NOW())
            ON DUPLICATE KEY UPDATE is_active = 0");
$inactiveTeacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'harden_inactive_teacher' LIMIT 1")->fetchColumn();

// ─────────────────────────────────────────────────────────────────
// TEST 1: Gateway Identifier Standardization
// ─────────────────────────────────────────────────────────────────
echo "--- 1. GATEWAY IDENTIFIER STANDARDIZATION ---\n";
assertHardening(SmsService::normalizeGatewayIdentifier('ipromo') === 'ipromo', "Gateway 'ipromo' normalized to 'ipromo'");
assertHardening(SmsService::normalizeGatewayIdentifier('IPROMO') === 'ipromo', "Gateway 'IPROMO' normalized to 'ipromo'");
assertHardening(SmsService::normalizeGatewayIdentifier('iPromo') === 'ipromo', "Gateway 'iPromo' normalized to 'ipromo'");
assertHardening(SmsService::normalizeGatewayIdentifier('sms_gate_android') === 'sms_gate_android', "Gateway 'sms_gate_android' normalized to 'sms_gate_android'");
assertHardening(SmsService::normalizeGatewayIdentifier('') === '', "Empty gateway normalizes to empty string");

// ─────────────────────────────────────────────────────────────────
// TEST 2: Teacher Non-iPromo Server-Side Rejection
// ─────────────────────────────────────────────────────────────────
echo "\n--- 2. TEACHER NON-IPROMO REJECTION ---\n";
TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, [
    'sms_access'    => 1,
    'monthly_limit' => 100,
    'can_send_sms'  => 1,
], $adminUserId);

$gwCheck1 = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 1, 'sms_gate_android');
assertHardening($gwCheck1['allowed'] === false && str_contains($gwCheck1['error'] ?? '', 'Security violation'), "Teacher request using 'sms_gate_android' is rejected with Security violation");

$gwCheck2 = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 1, 'external_gateway');
assertHardening($gwCheck2['allowed'] === false && str_contains($gwCheck2['error'] ?? '', 'Security violation'), "Teacher request using arbitrary gateway is rejected with Security violation");

// ─────────────────────────────────────────────────────────────────
// TEST 3: Admin Test SMS Safety & Quota Isolation
// ─────────────────────────────────────────────────────────────────
echo "\n--- 3. ADMIN TEST SMS SAFETY & QUOTA ISOLATION ---\n";
// Set teacher permissions with limit 100
TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, [
    'sms_access'    => 1,
    'monthly_limit' => 100,
    'can_send_sms'  => 1,
], $adminUserId);

$usageBefore = TeacherSmsService::getTeacherMonthlyUsage($pdo, $teacherUserId);

// Invalid phone number rejection
$invalidPhoneCaught = false;
try {
    PhoneContactService::sendAdminTestSms($pdo, 'invalid_phone', 'Test Message', 'ipromo', $adminUserId);
} catch (\Throwable $e) {
    $invalidPhoneCaught = true;
}
assertHardening($invalidPhoneCaught, "sendAdminTestSms rejects invalid phone number");

// Empty message rejection
$emptyMsgCaught = false;
try {
    PhoneContactService::sendAdminTestSms($pdo, '0771234567', '   ', 'ipromo', $adminUserId);
} catch (\Throwable $e) {
    $emptyMsgCaught = true;
}
assertHardening($emptyMsgCaught, "sendAdminTestSms rejects empty message");

// Execute valid admin test SMS (calls SmsService::send with context 'admin_test')
$adminTestResult = PhoneContactService::sendAdminTestSms($pdo, '0771234567', 'Test SMS via iPromo', 'ipromo', $adminUserId);
assertHardening(is_array($adminTestResult), "sendAdminTestSms returns structured response array");

// Verify teacher quota was NEVER affected
$usageAfter = TeacherSmsService::getTeacherMonthlyUsage($pdo, $teacherUserId);
assertHardening($usageBefore['used_units'] === $usageAfter['used_units'], "Admin Test SMS does NOT decrement or affect teacher used units");
assertHardening($usageBefore['remaining_units'] === $usageAfter['remaining_units'], "Admin Test SMS does NOT decrement or affect teacher remaining units");

// Check that sms_logs recorded context = 'admin_test'
$stmtLog = $pdo->prepare("SELECT context FROM sms_logs WHERE recipient = '94771234567' AND sent_by = ? ORDER BY id DESC LIMIT 1");
$stmtLog->execute([$adminUserId]);
$logCtx = $stmtLog->fetchColumn();
assertHardening($logCtx === 'admin_test', "Admin Test SMS logged with context = 'admin_test'");

// ─────────────────────────────────────────────────────────────────
// TEST 4: Global Teacher SMS Emergency Switch Audit Logging
// ─────────────────────────────────────────────────────────────────
echo "\n--- 4. EMERGENCY SWITCH AUDIT LOGGING ---\n";
$auditReasonDisable = "Emergency maintenance lockdown test";
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, false, $adminUserId, $auditReasonDisable, '127.0.0.1');

$logs = TeacherSmsService::getSwitchAuditLogs($pdo, 5);
assertHardening(count($logs) >= 1, "Switch audit logs recorded in database");
$latest = $logs[0];
assertHardening((int)$latest['admin_user_id'] === $adminUserId, "Audit log records correct admin_user_id");
assertHardening((int)$latest['new_status'] === 0, "Audit log records new_status = 0 (disabled)");
assertHardening($latest['reason'] === $auditReasonDisable, "Audit log records exact supplied reason");
assertHardening($latest['ip_address'] === '127.0.0.1', "Audit log records client IP address");

// Re-enable with audit note
$auditReasonEnable = "Resuming teacher broadcasts after verification";
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, true, $adminUserId, $auditReasonEnable, '127.0.0.1');
$logs2 = TeacherSmsService::getSwitchAuditLogs($pdo, 5);
assertHardening((int)$logs2[0]['new_status'] === 1 && $logs2[0]['reason'] === $auditReasonEnable, "Audit log records enable event with reason");

// ─────────────────────────────────────────────────────────────────
// TEST 5: Complete Teacher Authorization Chain
// ─────────────────────────────────────────────────────────────────
echo "\n--- 5. TEACHER AUTHORIZATION CHAIN ---\n";
// 1. Inactive teacher user
$inactCheck = TeacherSmsService::validateTeacherCanSend($pdo, $inactiveTeacherId, 1, 'ipromo');
assertHardening($inactCheck['allowed'] === false && str_contains($inactCheck['error'] ?? '', 'inactive'), "Step 0: Inactive teacher account is rejected");

// 2. Global switch disabled
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, false, $adminUserId, 'Lockdown', '127.0.0.1');
$switchCheck = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 1, 'ipromo');
assertHardening($switchCheck['allowed'] === false && str_contains($switchCheck['error'] ?? '', 'Global Emergency Broadcast Switch'), "Step 1: Emergency switch disabled blocks teacher");
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, true, $adminUserId, 'Restored', '127.0.0.1');

// 3. sms_access = 0
TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, ['sms_access' => 0, 'can_send_sms' => 0], $adminUserId);
$accessCheck = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 1, 'ipromo');
assertHardening($accessCheck['allowed'] === false && str_contains($accessCheck['error'] ?? '', 'do not have permission'), "Step 2: sms_access=0 blocks teacher");

// 4. Monthly limit = 0
TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, ['sms_access' => 1, 'can_send_sms' => 1, 'monthly_limit' => 0], $adminUserId);
$limitCheck = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 1, 'ipromo');
assertHardening($limitCheck['allowed'] === false && str_contains($limitCheck['error'] ?? '', 'monthly SMS quota is 0'), "Step 3: monthly_limit=0 blocks teacher");

// 5. Quota exceeded
TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, ['sms_access' => 1, 'can_send_sms' => 1, 'monthly_limit' => 5], $adminUserId);
$quotaCheck = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 10, 'ipromo');
assertHardening($quotaCheck['allowed'] === false && str_contains($quotaCheck['error'] ?? '', 'Monthly SMS limit exceeded'), "Step 4: Insufficient remaining quota blocks teacher");

// ─────────────────────────────────────────────────────────────────
// TEST 6: Atomic Quota Protection & Campaign Idempotency Key
// ─────────────────────────────────────────────────────────────────
echo "\n--- 6. ATOMIC QUOTA & CAMPAIGN IDEMPOTENCY KEY ---\n";
// Create a campaign with an idempotency key
$idempKey = 'idemp_test_' . bin2hex(random_bytes(8));
$testRecords = [
    [
        'normalized_phone' => '94770000001',
        'raw_phone'        => '0770000001',
        'name'             => 'Idemp Student 1',
        'is_valid'         => true,
        'is_duplicate'     => false,
    ],
];

$camp1 = BulkSmsService::createCampaign(
    $pdo,
    $adminUserId,
    'idemp_test.csv',
    'Idempotency message test',
    $testRecords,
    false,
    'Idempotency Campaign 1',
    'sms_gate_android',
    $idempKey
);
assertHardening(!empty($camp1['campaign_id']), "First campaign creation succeeds with idempotency key");

// Re-submitting identical request with the same idempotency key must return the existing campaign
$camp2 = BulkSmsService::createCampaign(
    $pdo,
    $adminUserId,
    'idemp_test.csv',
    'Idempotency message test',
    $testRecords,
    false,
    'Idempotency Campaign 1',
    'sms_gate_android',
    $idempKey
);
assertHardening((int)$camp2['campaign_id'] === (int)$camp1['campaign_id'], "Second call with identical idempotency key returns existing campaign (no duplicate)");
assertHardening(!empty($camp2['is_idempotent']), "Campaign response marks is_idempotent = true");

// ─────────────────────────────────────────────────────────────────
// TEST 7: Soft Delete / Archive & Restore
// ─────────────────────────────────────────────────────────────────
echo "\n--- 7. SOFT DELETE / ARCHIVE & RESTORE ---\n";
// Create a dedicated contact for archive test
$pdo->exec("INSERT INTO phone_contacts (normalized_phone, phone, name, sms_opt_out, sms_status, status, created_at)
            VALUES ('94779998881', '0779998881', 'Archive Test Contact', 0, 'allowed', 'active', NOW())
            ON DUPLICATE KEY UPDATE status = 'active', sms_status = 'allowed', sms_opt_out = 0");
$archContactId = (int)$pdo->query("SELECT id FROM phone_contacts WHERE normalized_phone = '94779998881' LIMIT 1")->fetchColumn();

// Add academic record
$pdo->exec("INSERT IGNORE INTO phone_contact_records (contact_id, exam_year, exam_type, location, school, source, source_group, created_at)
            VALUES ($archContactId, 2026, 'IGCSE', 'Kandy', 'Archive College', 'test', 'Archive Group', NOW())");

// Check that contact is initially eligible
$recipsBefore = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94779998881']);
assertHardening(isset($recipsBefore['94779998881']), "Active contact is included in SMS recipients");

// Archive contact
$archResult = PhoneContactService::archiveContact($pdo, $archContactId, $adminUserId, "Testing soft delete archive");
assertHardening($archResult === true, "archiveContact returns true");

$statusInDb = $pdo->query("SELECT status FROM phone_contacts WHERE id = $archContactId")->fetchColumn();
assertHardening($statusInDb === 'archived', "Contact status updated to 'archived' in database");

// Archived contact MUST be excluded from SMS recipients
$recipsAfter = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94779998881']);
assertHardening(!isset($recipsAfter['94779998881']), "Archived contact is strictly excluded from unique SMS recipients");

// Breakdown reports archived
$breakdown = PhoneContactService::getRecipientExclusionBreakdown($pdo, ['search' => '94779998881']);
assertHardening($breakdown['archived'] === 1 && $breakdown['eligible'] === 0, "Exclusion breakdown counts archived contact under 'archived' (0 eligible)");

// Timeline includes archive milestone
$timeline = PhoneContactService::getContactTimeline($pdo, $archContactId);
$hasArchiveTimeline = false;
foreach ($timeline as $ev) {
    if ($ev['event_type'] === 'contact_archived') {
        $hasArchiveTimeline = true;
        break;
    }
}
assertHardening($hasArchiveTimeline, "Contact lifecycle timeline records 'contact_archived' event");

// Restore contact
$restoreResult = PhoneContactService::restoreContact($pdo, $archContactId, $adminUserId);
assertHardening($restoreResult === true, "restoreContact returns true");
$statusRestored = $pdo->query("SELECT status FROM phone_contacts WHERE id = $archContactId")->fetchColumn();
assertHardening($statusRestored === 'active', "Restored contact status is 'active'");

// Restored contact is eligible again
$recipsRestored = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94779998881']);
assertHardening(isset($recipsRestored['94779998881']), "Restored contact is immediately eligible for SMS again");

// ─────────────────────────────────────────────────────────────────
// TEST 8: Private Error CSV Security & Access Controls
// ─────────────────────────────────────────────────────────────────
echo "\n--- 8. PRIVATE ERROR CSV SECURITY ---\n";
// Create a test import with an invalid row to trigger error CSV generation
$csvContent = "phone_number,exam_year,exam_type,location\n0771112233,2026,IGCSE,Kandy\n999invalid,2026,IGCSE,Kandy\n";
$tmpCsv = tempnam(sys_get_temp_dir(), 'test_err_');
file_put_contents($tmpCsv, $csvContent);

$analysis = PhoneContactService::analyzeCsvFile($tmpCsv, 'hardening_test_errors.csv', $pdo, '2026 Error Test Group');
$importRep = PhoneContactService::executeImport(
    $pdo,
    $adminUserId,
    'hardening_test_errors.csv',
    '2026 Error Test Group',
    $analysis['valid_records'],
    $analysis['invalid_rows_list']
);
unlink($tmpCsv);

assertHardening(!empty($importRep['error_csv_path']), "Import generated error CSV path");
// Check unguessable filename: must contain at least 32 hex chars / random token
$errBasename = basename((string)$importRep['error_csv_path']);
assertHardening(preg_match('/errors_import_\d+_[a-f0-9]{32}\.csv/', $errBasename) === 1, "Error CSV filename contains cryptographically secure random token (errors_import_X_[32-hex].csv)");

// Check .htaccess in storage/imports/
$htFile = dirname(__DIR__) . '/storage/imports/.htaccess';
assertHardening(file_exists($htFile) && str_contains(file_get_contents($htFile), 'Require all denied'), "storage/imports/.htaccess denies all direct web access");

// Check download_import_errors.php exists
$dlScript = dirname(__DIR__) . '/admin/download_import_errors.php';
assertHardening(file_exists($dlScript), "admin/download_import_errors.php download controller exists");

// ─────────────────────────────────────────────────────────────────
// TEST 9: Canonical WhatsApp Group Normalization & Filtering
// ─────────────────────────────────────────────────────────────────
echo "\n--- 9. CANONICAL WHATSAPP GROUP NORMALIZATION ---\n";
// Map raw group to canonical group
$rawGroup = '2026 IGCSE Kandy (Batch A)';
$canonicalGroup = '2026 IGCSE Kandy';

$setMap = PhoneContactService::setGroupMapping($pdo, $rawGroup, $canonicalGroup);
assertHardening($setMap === true, "setGroupMapping saves raw to canonical mapping");

$resolved = PhoneContactService::resolveCanonicalGroup($pdo, $rawGroup);
assertHardening($resolved === $canonicalGroup, "resolveCanonicalGroup resolves raw alias to canonical group");

$allMappings = PhoneContactService::getAllGroupMappings($pdo);
assertHardening(count($allMappings) >= 1, "getAllGroupMappings lists registered group mappings");

// Import contact with this raw group and verify canonical_source_group is populated
$rawImportContent = "phone_number,exam_year,exam_type,location\n0773344556,2026,IGCSE,Kandy\n";
$tmpRawCsv = tempnam(sys_get_temp_dir(), 'test_canon_');
file_put_contents($tmpRawCsv, $rawImportContent);

$analysisRaw = PhoneContactService::analyzeCsvFile($tmpRawCsv, 'canon_test.csv', $pdo, $rawGroup);
$importRaw = PhoneContactService::executeImport(
    $pdo,
    $adminUserId,
    'canon_test.csv',
    $rawGroup,
    $analysisRaw['valid_records'],
    $analysisRaw['invalid_rows_list']
);
unlink($tmpRawCsv);

$stmtCanonCheck = $pdo->prepare(
    "SELECT pcr.source_group, pcr.canonical_source_group 
     FROM phone_contact_records pcr
     JOIN phone_contacts pc ON pc.id = pcr.contact_id
     WHERE pc.normalized_phone = '94773344556'
     ORDER BY pcr.id DESC LIMIT 1"
);
$stmtCanonCheck->execute();
$canonRow = $stmtCanonCheck->fetch(PDO::FETCH_ASSOC);

assertHardening($canonRow['source_group'] === $rawGroup, "Preserves original raw source_group");
assertHardening($canonRow['canonical_source_group'] === $canonicalGroup, "Populates canonical_source_group with resolved canonical name");

// Filtering by canonical group matches the record
$filtCanon = PhoneContactService::getFilteredContacts($pdo, ['source_group' => $canonicalGroup]);
$foundByCanonical = false;
foreach ($filtCanon['contacts'] as $c) {
    if ($c['normalized_phone'] === '94773344556') {
        $foundByCanonical = true;
        break;
    }
}
assertHardening($foundByCanonical, "Filtering by canonical group successfully matches contact imported with raw group alias");

// Teardown Test Data
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone IN ('94779998881', '94771112233', '94773344556')");
$pdo->exec("DELETE FROM phone_whatsapp_group_mappings WHERE raw_group_name = '2026 IGCSE Kandy (Batch A)'");
$pdo->exec("DELETE FROM teacher_sms_permissions WHERE teacher_user_id IN ($teacherUserId, $inactiveTeacherId)");
$pdo->exec("DELETE FROM users WHERE username IN ('harden_admin', 'harden_teacher', 'harden_inactive_teacher')");

// ─────────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────────
echo "\n====================================================================\n";
echo " HARDENING TEST SUITE SUMMARY: {$passedTests} PASSED, {$failedTests} FAILED\n";
echo "====================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
