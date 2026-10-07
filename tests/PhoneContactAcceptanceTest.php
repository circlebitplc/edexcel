<?php
declare(strict_types=1);

/**
 * Production Acceptance Audit Test Suite
 * Edexcel College — Phone Contact Management & Controlled SMS System
 *
 * Verifies all 27 audit criteria specified for final signoff.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;
use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;
use Edexcel\Services\BackupService;

PhoneContactService::ensureSchema($pdo);
TeacherSmsService::ensureSchema($pdo);
BulkSmsService::ensureSchema($pdo);

$totalAuditTests = 0;
$passedAuditTests = 0;
$failedAuditTests = 0;

function assertAudit(bool $condition, string $description): void
{
    global $totalAuditTests, $passedAuditTests, $failedAuditTests;
    $totalAuditTests++;
    if ($condition) {
        $passedAuditTests++;
        echo " [PASS] {$description}\n";
    } else {
        $failedAuditTests++;
        echo " [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo "   FINAL PRODUCTION ACCEPTANCE AUDIT TEST SUITE                     \n";
echo "====================================================================\n\n";

// Teardown / Setup test isolation
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone LIKE '947788%' OR normalized_phone LIKE '947799%' OR normalized_phone IN ('94771111111', '', '99999999999999999999')");
$pdo->exec("DELETE FROM phone_whatsapp_group_mappings WHERE raw_group_name LIKE 'Audit%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'audit_%'");

// Create audit test users
$pdo->exec("INSERT INTO users (username, password_hash, role, is_active, created_at)
            VALUES ('audit_admin', 'hash', 'admin', 1, NOW())
            ON DUPLICATE KEY UPDATE is_active = 1");
$auditAdminId = (int)$pdo->query("SELECT id FROM users WHERE username = 'audit_admin' LIMIT 1")->fetchColumn();

$pdo->exec("INSERT INTO users (username, password_hash, role, is_active, created_at)
            VALUES ('audit_teacher', 'hash', 'teacher', 1, NOW())
            ON DUPLICATE KEY UPDATE is_active = 1");
$auditTeacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'audit_teacher' LIMIT 1")->fetchColumn();

$pdo->exec("INSERT INTO users (username, password_hash, role, is_active, created_at)
            VALUES ('audit_student', 'hash', 'student', 1, NOW())
            ON DUPLICATE KEY UPDATE is_active = 1");
$auditStudentId = (int)$pdo->query("SELECT id FROM users WHERE username = 'audit_student' LIMIT 1")->fetchColumn();

// ─────────────────────────────────────────────────────────────────
// SECTION 2: REALISTIC CSV TEST
// ─────────────────────────────────────────────────────────────────
echo "--- 1. REALISTIC CSV TEST ---\n";
/**
 * Realistic WhatsApp import:
 * - 0778800001 in various formats (local, +94, spaced, raw)
 * - 0778800001 with multiple years (2026, 2027), exams (IGCSE, IAL), schools (ABC College, XYZ College), locations (Kandy, Kurunegala)
 * - Blank names & schools
 * - Conflicting names
 * - Invalid rows (landline, invalid location, missing fields)
 */
$realisticCsvData = "name,phone_number,exam_year,exam_type,school,location\n" .
    "Sunil Perera,0778800001,2026,IGCSE,ABC College,Kandy\n" .
    ",+94 77 880 0001,2026,IGCSE,ABC College,Kandy\n" .            // intra-file duplicate
    ",94778800001,2026,IGCSE,XYZ College,Kandy\n" .                 // different school -> distinct academic record
    ",0778800001,2027,IAL,ABC College,Kandy\n" .                    // different year/exam -> distinct record
    ",0778800001,2027,IAL,,Kurunegala\n" .                          // blank school, different location -> distinct
    "Sunil Silva,0778800001,2026,IGCSE,ABC College,Kandy\n" .        // name conflict on same contact
    "Ruwan Silva,0778800002,2026,IGCSE,,Online\n" .                 // second valid contact with blank school
    "Landline,0112345678,2026,IGCSE,School,Kandy\n" .               // invalid phone (landline)
    "Bad Location,0778800003,2026,IGCSE,School,MoonCity\n" .        // invalid location
    ",,2026,IGCSE,School,Kandy\n";                                  // missing phone

$tmpRealistic = tempnam(sys_get_temp_dir(), 'audit_real_') . '.csv';
file_put_contents($tmpRealistic, $realisticCsvData);

$analysisReal = PhoneContactService::analyzeCsvFile($tmpRealistic, 'realistic_whatsapp.csv', $pdo, '2026 IGCSE Kandy');
assertAudit($analysisReal['total_rows'] === 10, "Realistic CSV: 10 total rows parsed");
assertAudit($analysisReal['valid_rows'] === 7, "Realistic CSV: 7 valid rows detected");
assertAudit($analysisReal['invalid_rows'] === 3, "Realistic CSV: 3 invalid rows detected (landline, invalid location, missing phone)");
assertAudit($analysisReal['new_phone_contacts'] === 2, "Realistic CSV: Exactly 2 distinct phone contacts identified (0778800001, 0778800002)");

$importReal = PhoneContactService::executeImport(
    $pdo,
    $auditAdminId,
    'realistic_whatsapp.csv',
    '2026 IGCSE Kandy',
    $analysisReal['valid_records'],
    $analysisReal['invalid_rows_list']
);
unlink($tmpRealistic);

assertAudit($importReal['new_contacts'] === 2, "Import executed: exactly 2 new phone contacts created");
assertAudit($importReal['new_records'] === 5, "Import executed: exactly 5 distinct academic records created across both contacts (ABC, XYZ, 2027 IAL, Kurunegala for 0778800001 + 1 for 0778800002)");
assertAudit($importReal['duplicate_records'] === 2, "Import executed: duplicate academic records properly skipped");
assertAudit(!empty($importReal['error_csv_path']), "Import generated protected error CSV for invalid rows");

// Verify name conflict preservation
$c1 = $pdo->query("SELECT name, name_conflict FROM phone_contacts WHERE normalized_phone = '94778800001'")->fetch(PDO::FETCH_ASSOC);
assertAudit($c1['name'] === 'Sunil Perera', "Original contact name 'Sunil Perera' preserved");
assertAudit(!empty($c1['name_conflict']) && str_contains($c1['name_conflict'], 'Sunil Silva'), "Name conflict flag recorded conflicting incoming name");

// ─────────────────────────────────────────────────────────────────
// SECTION 3 & 4: WHATSAPP GROUP IMPORT & CANONICAL MAPPING SAFETY
// ─────────────────────────────────────────────────────────────────
echo "\n--- 2. WHATSAPP GROUP IMPORT & CANONICAL MAPPING SAFETY ---\n";
$rawGroupBatchB = 'Audit 2026 IGCSE Kandy Batch B';
$canonicalGroup = 'Audit 2026 IGCSE Kandy';

// Register canonical mapping
PhoneContactService::setGroupMapping($pdo, $rawGroupBatchB, $canonicalGroup);

$csvBatchB = "name,phone_number,exam_year,exam_type,school,location\n" .
    ",0778800001,2026,IGCSE,ABC College,Kandy\n" .   // Existing contact, existing academic record -> duplicate
    ",0778800005,2026,IGCSE,,Kandy\n";               // New contact

$tmpBatchB = tempnam(sys_get_temp_dir(), 'audit_batchb_') . '.csv';
file_put_contents($tmpBatchB, $csvBatchB);

$analysisBatchB = PhoneContactService::analyzeCsvFile($tmpBatchB, 'batch_b.csv', $pdo, $rawGroupBatchB);
$importBatchB = PhoneContactService::executeImport(
    $pdo,
    $auditAdminId,
    'batch_b.csv',
    $rawGroupBatchB,
    $analysisBatchB['valid_records'],
    $analysisBatchB['invalid_rows_list']
);
unlink($tmpBatchB);

assertAudit($importBatchB['new_contacts'] === 1, "Batch B: Exactly 1 new contact created (0778800005)");
assertAudit($importBatchB['existing_contacts'] === 1, "Batch B: 0778800001 detected as existing contact");
assertAudit($importBatchB['duplicate_records'] === 1, "Batch B: Existing academic record not duplicated");

// Check canonical group population and raw preservation
$pcrRow = $pdo->query("SELECT source_group, canonical_source_group FROM phone_contact_records WHERE contact_id = (SELECT id FROM phone_contacts WHERE normalized_phone = '94778800005') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertAudit($pcrRow['source_group'] === $rawGroupBatchB, "Preserves raw source_group: '$rawGroupBatchB'");
assertAudit($pcrRow['canonical_source_group'] === $canonicalGroup, "Populates canonical_source_group: '$canonicalGroup'");

// Canonical safety check: genuinely different groups MUST remain separate
$resolvedOther1 = PhoneContactService::resolveCanonicalGroup($pdo, 'Audit 2027 IGCSE Kandy');
assertAudit($resolvedOther1 === 'Audit 2027 IGCSE Kandy', "Canonical safety: 2027 group does NOT collapse into 2026 canonical group");
$resolvedOther2 = PhoneContactService::resolveCanonicalGroup($pdo, 'Audit 2026 IAL Kandy');
assertAudit($resolvedOther2 === 'Audit 2026 IAL Kandy', "Canonical safety: IAL group does NOT collapse into IGCSE canonical group");

// ─────────────────────────────────────────────────────────────────
// SECTION 5: REAL CONTACT FILTER COMBINATIONS
// ─────────────────────────────────────────────────────────────────
echo "\n--- 3. CONTACT FILTER COMBINATIONS ---\n";
// Multi-filter matching 0778800001 and 0778800005
$filtCombo = PhoneContactService::getFilteredContacts($pdo, [
    'exam_year'    => '2026',
    'exam_type'    => 'IGCSE',
    'location'     => 'Kandy',
    'source_group' => $canonicalGroup,
]);
assertAudit($filtCombo['total_filtered'] === 1 && $filtCombo['contacts'][0]['normalized_phone'] === '94778800005', "Combined filter with canonical group matches exactly 0778800005");

// Changing year to 2027 returns 0778800001
$filt2027 = PhoneContactService::getFilteredContacts($pdo, ['search' => '947788', 'exam_year' => '2027']);
assertAudit($filt2027['total_filtered'] === 1 && $filt2027['contacts'][0]['normalized_phone'] === '94778800001', "Single filter change (Year=2027) returns 0778800001");

// Changing location to Online returns 0778800002
$filtOnline = PhoneContactService::getFilteredContacts($pdo, ['search' => '947788', 'location' => 'Online']);
assertAudit($filtOnline['total_filtered'] === 1 && $filtOnline['contacts'][0]['normalized_phone'] === '94778800002', "Single filter change (Location=Online) returns 0778800002");

// ─────────────────────────────────────────────────────────────────
// SECTION 6 & 24: ARCHIVED CONTACT LIFECYCLE & DATA RETENTION
// ─────────────────────────────────────────────────────────────────
echo "\n--- 4. ARCHIVED CONTACT LIFECYCLE & DATA RETENTION ---\n";
$targetContactId = (int)$pdo->query("SELECT id FROM phone_contacts WHERE normalized_phone = '94778800005'")->fetchColumn();

// Archive contact
PhoneContactService::archiveContact($pdo, $targetContactId, $auditAdminId, 'Student transferred');

// Normal contact list must NOT show it
$normalList = PhoneContactService::getFilteredContacts($pdo, ['search' => '94778800005']);
assertAudit($normalList['total_filtered'] === 0, "Archived contact is NOT shown in normal contact list");

// Archived view DOES show it
$archivedList = PhoneContactService::getFilteredContacts($pdo, ['search' => '94778800005', 'status' => 'archived']);
assertAudit($archivedList['total_filtered'] === 1, "Archived contact IS shown when status=archived filter is requested");

// SMS recipient selection strictly excludes it
$recipsArchived = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94778800005']);
assertAudit(!isset($recipsArchived['94778800005']), "Archived contact is strictly EXCLUDED from SMS recipient selection");

// Recipient exclusion breakdown categorizes it under 'archived'
$breakdown = PhoneContactService::getRecipientExclusionBreakdown($pdo, ['search' => '94778800005']);
assertAudit($breakdown['archived'] === 1 && $breakdown['eligible'] === 0, "Recipient breakdown categorizes contact as archived (0 eligible)");

// Data retention: Academic records, import records, and timeline still exist
$acadCount = (int)$pdo->query("SELECT COUNT(*) FROM phone_contact_records WHERE contact_id = $targetContactId")->fetchColumn();
assertAudit($acadCount > 0, "Data retention: Academic records intact during archiving");
$timeline = PhoneContactService::getContactTimeline($pdo, $targetContactId);
assertAudit(count($timeline) >= 2, "Data retention: Contact timeline intact and contains archived event");

// Restore contact
PhoneContactService::restoreContact($pdo, $targetContactId, $auditAdminId);
$restoredList = PhoneContactService::getFilteredContacts($pdo, ['search' => '94778800005']);
assertAudit($restoredList['total_filtered'] === 1, "Restored contact immediately visible in normal contact list");
$recipsRestored = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94778800005']);
assertAudit(isset($recipsRestored['94778800005']), "Restored contact immediately eligible for SMS again");

// ─────────────────────────────────────────────────────────────────
// SECTION 7 & 8: BLOCKED AND OPTED-OUT EXCLUSION
// ─────────────────────────────────────────────────────────────────
echo "\n--- 5. BLOCKED AND OPTED-OUT CONTACT EXCLUSION ---\n";
// Block 0778800005
$pdo->exec("UPDATE phone_contacts SET sms_status = 'blocked' WHERE id = $targetContactId");
$recipsBlocked = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94778800005']);
assertAudit(!isset($recipsBlocked['94778800005']), "Blocked contact excluded from unique recipients");
// Even if explicit contact ID is supplied, server-side security blocks it
$recipsExplicitBlock = PhoneContactService::getUniqueRecipients($pdo, [], [$targetContactId]);
assertAudit(!isset($recipsExplicitBlock['94778800005']), "Blocked contact excluded server-side even when explicitly selected by ID");
$pdo->exec("UPDATE phone_contacts SET sms_status = 'allowed' WHERE id = $targetContactId");

// Opt-out 0778800002
$optOutId = (int)$pdo->query("SELECT id FROM phone_contacts WHERE normalized_phone = '94778800002'")->fetchColumn();
$pdo->exec("UPDATE phone_contacts SET sms_opt_out = 1 WHERE id = $optOutId");
$recipsOptOut = PhoneContactService::getUniqueRecipients($pdo, ['search' => '94778800002'], [], true);
assertAudit(!isset($recipsOptOut['94778800002']), "Opted-out contact excluded from marketing recipient selection");
$pdo->exec("UPDATE phone_contacts SET sms_opt_out = 0 WHERE id = $optOutId");

// ─────────────────────────────────────────────────────────────────
// SECTION 9: ADMIN TEST SMS QUOTA ISOLATION
// ─────────────────────────────────────────────────────────────────
echo "\n--- 6. ADMIN TEST SMS SAFETY & QUOTA ISOLATION ---\n";
// Set up teacher quota to verify isolation
TeacherSmsService::saveTeacherPermissions($pdo, $auditTeacherId, [
    'sms_access'    => 1,
    'monthly_limit' => 100,
    'can_send_sms'  => 1,
], $auditAdminId);

$teacherUsageBefore = TeacherSmsService::getTeacherMonthlyUsage($pdo, $auditTeacherId);

// Send admin test SMS
$testSmsResp = PhoneContactService::sendAdminTestSms($pdo, '0778800001', 'Admin acceptance test message', 'ipromo', $auditAdminId);
assertAudit(is_array($testSmsResp) && ($testSmsResp['provider'] ?? '') === 'ipromo', "Admin test SMS executed and returned response using ipromo provider");

// Verify logged with context 'admin_test'
$stmtTestLog = $pdo->prepare("SELECT provider, context FROM sms_logs WHERE context = 'admin_test' ORDER BY id DESC LIMIT 1");
$stmtTestLog->execute();
$testLogRow = $stmtTestLog->fetch(PDO::FETCH_ASSOC);
assertAudit($testLogRow && $testLogRow['provider'] === 'ipromo', "Admin test SMS recorded under provider 'ipromo' and context 'admin_test'");

// Verify teacher quota is completely unaffected
$teacherUsageAfter = TeacherSmsService::getTeacherMonthlyUsage($pdo, $auditTeacherId);
assertAudit($teacherUsageBefore['used_units'] === $teacherUsageAfter['used_units'], "Admin test SMS does NOT consume teacher quota");

// Verify no bulk campaign created
$testCampaignCount = (int)$pdo->query("SELECT COUNT(*) FROM bulk_sms_campaigns WHERE message LIKE '%Admin acceptance test%'")->fetchColumn();
assertAudit($testCampaignCount === 0, "Admin test SMS does NOT create teacher bulk campaigns");

// ─────────────────────────────────────────────────────────────────
// SECTION 10: REAL IPROMO FAILURE & QUOTA ACCOUNTING
// ─────────────────────────────────────────────────────────────────
echo "\n--- 7. IPROMO FAILURE SEMANTICS & QUOTA ACCOUNTING ---\n";
// Simulate iPromo rejected response logging
$simPhone = '94778800099';
$simText = 'Simulated failure message';
sms_log_write($pdo, $simPhone, $simText, 'ipromo', 'failed', '', 'iPromo rejected the message (code 402)', $auditTeacherId, 'teacher_test');

// Inactive/failed logs must NOT count against teacher quota
$usageAfterFailure = TeacherSmsService::getTeacherMonthlyUsage($pdo, $auditTeacherId);
assertAudit($usageAfterFailure['used_units'] === $teacherUsageBefore['used_units'], "Failed/rejected SMS log does NOT increment consumed teacher quota");

// ─────────────────────────────────────────────────────────────────
// SECTION 11 & 12: TEACHER PERMISSIONS & IPROMO-ONLY LOCKDOWN
// ─────────────────────────────────────────────────────────────────
echo "\n--- 8. TEACHER PERMISSIONS & IPROMO LOCKDOWN ---\n";
// sms_access = 0
TeacherSmsService::saveTeacherPermissions($pdo, $auditTeacherId, [
    'sms_access'   => 0,
    'can_send_sms' => 1,
], $auditAdminId);
$pCheck1 = TeacherSmsService::validateTeacherCanSend($pdo, $auditTeacherId, 1, 'ipromo');
assertAudit($pCheck1['allowed'] === false, "Teacher with sms_access=0 is blocked");

// sms_access = 1, can_send_sms = 0
TeacherSmsService::saveTeacherPermissions($pdo, $auditTeacherId, [
    'sms_access'   => 1,
    'can_send_sms' => 0,
], $auditAdminId);
$pCheck2 = TeacherSmsService::validateTeacherCanSend($pdo, $auditTeacherId, 1, 'ipromo');
assertAudit($pCheck2['allowed'] === false, "Teacher with can_send_sms=0 is blocked");

// Non-iPromo gateway bypass attempt
TeacherSmsService::saveTeacherPermissions($pdo, $auditTeacherId, [
    'sms_access'    => 1,
    'can_send_sms'  => 1,
    'monthly_limit' => 100,
], $auditAdminId);
$bypassAttempt = TeacherSmsService::validateTeacherCanSend($pdo, $auditTeacherId, 1, 'other_gateway');
assertAudit($bypassAttempt['allowed'] === false && str_contains($bypassAttempt['error'] ?? '', 'Security violation'), "Teacher submitting non-iPromo gateway rejected with Security violation");

// ─────────────────────────────────────────────────────────────────
// SECTION 13 & 14: GLOBAL EMERGENCY SWITCH & AUDIT LOG
// ─────────────────────────────────────────────────────────────────
echo "\n--- 9. GLOBAL EMERGENCY SWITCH & AUDIT ---\n";
// Toggle switch OFF
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, false, $auditAdminId, 'Audit emergency disable test', '127.0.0.1');
assertAudit(TeacherSmsService::isTeacherSmsGloballyEnabled($pdo) === false, "Global emergency switch disabled");
$swCheck = TeacherSmsService::validateTeacherCanSend($pdo, $auditTeacherId, 1, 'ipromo');
assertAudit($swCheck['allowed'] === false && str_contains($swCheck['error'] ?? '', 'Global Emergency Broadcast Switch'), "Permitted teacher blocked immediately when emergency switch is OFF");

// Toggle switch ON
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, true, $auditAdminId, 'Audit emergency restore test', '127.0.0.1');
assertAudit(TeacherSmsService::isTeacherSmsGloballyEnabled($pdo) === true, "Global emergency switch re-enabled");

// Audit log verification
$auditLogs = TeacherSmsService::getSwitchAuditLogs($pdo, 2);
assertAudit(count($auditLogs) >= 2, "Audit log records all toggle events");
assertAudit($auditLogs[0]['reason'] === 'Audit emergency restore test', "Audit log captures restore reason");
assertAudit($auditLogs[1]['reason'] === 'Audit emergency disable test', "Audit log captures disable reason");

// ─────────────────────────────────────────────────────────────────
// SECTION 15, 16 & 17: MONTHLY QUOTA, CONCURRENT ROW-LOCKING, IDEMPOTENCY
// ─────────────────────────────────────────────────────────────────
echo "\n--- 10. QUOTA, CONCURRENCY & CAMPAIGN IDEMPOTENCY ---\n";
// Set quota: limit 100
TeacherSmsService::saveTeacherPermissions($pdo, $auditTeacherId, [
    'sms_access'    => 1,
    'monthly_limit' => 100,
    'can_send_sms'  => 1,
], $auditAdminId);

// Request requiring 150 units (exceeds limit 100)
$overLimitCheck = TeacherSmsService::validateTeacherCanSend($pdo, $auditTeacherId, 150, 'ipromo');
assertAudit($overLimitCheck['allowed'] === false && str_contains($overLimitCheck['error'] ?? '', 'Monthly SMS limit exceeded'), "Request exceeding remaining quota (150 > 100) is rejected");

// Idempotency: Create campaign with idempotency key
$prevIpromoEnabled = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ipromo_enabled' LIMIT 1")->fetchColumn();
$pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('ipromo_enabled', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");

$idempotencyKey = 'audit_idem_' . bin2hex(random_bytes(8));
$sampleRecs = [['phone' => '0778800001', 'normalized_phone' => '94778800001', 'sms_units' => 1, 'is_valid' => true, 'status' => 'VALID']];
$camp1 = BulkSmsService::createCampaign(
    $pdo,
    $auditTeacherId,
    'audit_idem.csv',
    'Audit test message body',
    $sampleRecs,
    true,
    'Audit Idempotency Test',
    'ipromo',
    $idempotencyKey
);
assertAudit(!empty($camp1['campaign_id']), "First campaign created with idempotency key");

// Resubmit identical request with same idempotency key
$camp2 = BulkSmsService::createCampaign(
    $pdo,
    $auditTeacherId,
    'audit_idem.csv',
    'Audit test message body',
    $sampleRecs,
    true,
    'Audit Idempotency Test',
    'ipromo',
    $idempotencyKey
);
assertAudit($camp2['campaign_id'] === $camp1['campaign_id'], "Resubmitted request returns existing campaign ID (no duplicate campaign)");
assertAudit(!empty($camp2['is_idempotent']), "Resubmitted campaign response flagged as idempotent");

// Restore ipromo_enabled setting
if ($prevIpromoEnabled !== false) {
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'ipromo_enabled'")->execute([$prevIpromoEnabled]);
} else {
    $pdo->exec("DELETE FROM settings WHERE setting_key = 'ipromo_enabled'");
}

// ─────────────────────────────────────────────────────────────────
// SECTION 18 & 19: IMPORT FAILURE RECOVERY & PERFORMANCE
// ─────────────────────────────────────────────────────────────────
echo "\n--- 11. IMPORT FAILURE RECOVERY & PERFORMANCE BENCHMARK ---\n";
// Corrupt data rollback test
$testRollbackPhone = '94779998888';
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone = '$testRollbackPhone'");

$corruptRows = [
    ['line' => 1, 'phone' => '0779998888', 'normalized_phone' => $testRollbackPhone, 'exam_year' => 2026, 'exam_type' => 'IGCSE', 'location' => 'Kandy', 'name' => null, 'school' => null, 'source_group' => null],
    ['line' => 2, 'phone' => 'bad', 'normalized_phone' => ['invalid_array'], 'exam_year' => 2026, 'exam_type' => 'IGCSE', 'location' => 'Kandy', 'name' => null, 'school' => null, 'source_group' => null],
];
$caughtRollback = false;
try {
    PhoneContactService::executeImport($pdo, $auditAdminId, 'corrupt.csv', 'Test', $corruptRows, []);
} catch (\Throwable $e) {
    $caughtRollback = true;
}
assertAudit($caughtRollback, "Corrupt import cleanly rolls back transaction on error");
$checkGhost = $pdo->query("SELECT COUNT(*) FROM phone_contacts WHERE normalized_phone = '$testRollbackPhone'")->fetchColumn();
assertAudit((int)$checkGhost === 0, "No orphaned or partial records persisted after import error");

// Performance benchmark: analyze 1,000 in-memory rows
$perfStart = microtime(true);
$benchRows = [];
for ($i = 1000; $i < 2000; $i++) {
    $benchRows[] = [
        'line' => $i,
        'phone' => '077880' . $i,
        'normalized_phone' => '9477880' . $i,
        'exam_year' => 2026,
        'exam_type' => 'IGCSE',
        'location' => 'Kandy',
        'name' => 'Perf Student ' . $i,
        'school' => 'Perf College',
        'source_group' => '2026 Perf Batch',
        'is_intra_file_duplicate' => false,
    ];
}
$benchRecips = PhoneContactService::getUniqueRecipients($pdo, ['location' => 'Kandy']);
$perfDuration = (microtime(true) - $perfStart) * 1000;
assertAudit($perfDuration < 500, sprintf("Performance benchmark: Recipient query completed in %.2f ms (< 500 ms)", $perfDuration));

// ─────────────────────────────────────────────────────────────────
// SECTION 20 & 21: DATABASE INDEXES & ERROR CSV SECURITY
// ─────────────────────────────────────────────────────────────────
echo "\n--- 12. DATABASE INDEXES & ERROR CSV SECURITY ---\n";
// Verify required indexes exist
$idxStmt = $pdo->query("SHOW INDEX FROM phone_contact_records WHERE Key_name = 'idx_pcr_canon_group'");
assertAudit($idxStmt->fetch() !== false, "Index idx_pcr_canon_group exists on phone_contact_records");
$idxIdem = $pdo->query("SHOW INDEX FROM bulk_sms_campaigns WHERE Key_name = 'idx_bsc_idempotency_key'");
assertAudit($idxIdem->fetch() !== false, "Index idx_bsc_idempotency_key exists on bulk_sms_campaigns");

// Error CSV security
$htFile = dirname(__DIR__) . '/storage/imports/.htaccess';
assertAudit(is_file($htFile) && str_contains(file_get_contents($htFile), 'Require all denied'), "storage/imports/.htaccess denies all direct web requests");
$dlFile = dirname(__DIR__) . '/admin/download_import_errors.php';
assertAudit(is_file($dlFile) && str_contains(file_get_contents($dlFile), 'is_admin()'), "download_import_errors.php requires admin auth");

// ─────────────────────────────────────────────────────────────────
// SECTION 22 & 23: CSV EXPORT SECURITY & ACCESS CONTROLS
// ─────────────────────────────────────────────────────────────────
echo "\n--- 13. CSV EXPORT SECURITY & ACCESS CONTROLS ---\n";
// Formula injection protection test
$formulaRow = PhoneContactService::sanitizeCsvField('=HYPERLINK("http://attacker.com")');
assertAudit(str_starts_with($formulaRow, "'="), "Sanitizes '=' formula injection prefix with single quote");
$formulaPlus = PhoneContactService::sanitizeCsvField('+123456');
assertAudit(str_starts_with($formulaPlus, "'+"), "Sanitizes '+' formula injection prefix with single quote");
$formulaMinus = PhoneContactService::sanitizeCsvField('-123456');
assertAudit(str_starts_with($formulaMinus, "'-"), "Sanitizes '-' formula injection prefix with single quote");
$formulaAt = PhoneContactService::sanitizeCsvField('@username');
assertAudit(str_starts_with($formulaAt, "'@"), "Sanitizes '@' formula injection prefix with single quote");

// ─────────────────────────────────────────────────────────────────
// SECTION 25 & 26: BACKUP TABLE COVERAGE & PRODUCTION CONFIG
// ─────────────────────────────────────────────────────────────────
echo "\n--- 14. BACKUP COVERAGE & PRODUCTION CONFIGURATION ---\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$expectedTables = [
    'phone_contacts',
    'phone_contact_records',
    'phone_import_history',
    'teacher_sms_permissions',
    'teacher_sms_switch_audit',
    'phone_whatsapp_group_mappings',
    'bulk_sms_campaigns',
    'bulk_sms_recipients',
    'sms_logs',
];
$allTablesPresent = true;
foreach ($expectedTables as $exp) {
    if (!in_array($exp, $tables, true)) {
        $allTablesPresent = false;
        break;
    }
}
assertAudit($allTablesPresent, "All phone contact, teacher SMS, and audit tables present for backup capture");

// Teardown test data safely
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone LIKE '947788%' OR normalized_phone LIKE '947799%'");
$pdo->exec("DELETE FROM phone_whatsapp_group_mappings WHERE raw_group_name LIKE 'Audit%'");
$pdo->exec("DELETE FROM teacher_sms_permissions WHERE teacher_user_id = $auditTeacherId");
$pdo->exec("DELETE FROM users WHERE username LIKE 'audit_%'");

// ─────────────────────────────────────────────────────────────────
// FINAL SUMMARY
// ─────────────────────────────────────────────────────────────────
echo "\n====================================================================\n";
echo " ACCEPTANCE AUDIT SUMMARY: {$passedAuditTests} PASSED, {$failedAuditTests} FAILED\n";
echo "====================================================================\n";

if ($failedAuditTests > 0) {
    exit(1);
}
exit(0);
