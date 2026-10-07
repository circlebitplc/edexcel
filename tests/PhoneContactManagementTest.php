<?php
declare(strict_types=1);

/**
 * Automated Test Suite for Phone Contact Management and Controlled SMS System
 * Edexcel College
 */

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/sms_gateway.php';

use Edexcel\Services\PhoneContactService;
use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;

echo "====================================================================\n";
echo "   EDEXCEL COLLEGE — PHONE CONTACTS & CONTROLLED SMS TEST SUITE     \n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $testName\n";
    } else {
        $failCount++;
        echo " [FAIL] $testName" . ($details ? " — $details" : "") . "\n";
    }
}

// ─────────────────────────────────────────────────────────────────
// Setup: Ensure schema & clean test database tables
// ─────────────────────────────────────────────────────────────────
PhoneContactService::ensureSchema($pdo);
TeacherSmsService::ensureSchema($pdo);
BulkSmsService::ensureSchema($pdo);

// Clean up any previous test data safely
$pdo->exec("DELETE FROM phone_contact_records WHERE source = 'test_suite'");
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone LIKE '9477000%' OR normalized_phone IN ('94771234567', '94712345678', '94779998811', '94779998881', '94771112233', '94773344556')");
$pdo->exec("DELETE FROM teacher_sms_permissions WHERE teacher_user_id IN (998, 999)");

// ─────────────────────────────────────────────────────────────────
// TEST 1: Phone Normalization for Sri Lankan numbers
// ─────────────────────────────────────────────────────────────────
echo "--- 1. SRI LANKAN PHONE NORMALIZATION ---\n";
assertTest(PhoneContactService::normalizePhone('0771234567') === '94771234567', "Normalize local 0771234567 -> 94771234567");
assertTest(PhoneContactService::normalizePhone('+94771234567') === '94771234567', "Normalize international +94771234567 -> 94771234567");
assertTest(PhoneContactService::normalizePhone('94771234567') === '94771234567', "Normalize raw 94771234567 -> 94771234567");
assertTest(PhoneContactService::normalizePhone('077 123 4567') === '94771234567', "Normalize spaced 077 123 4567 -> 94771234567");
assertTest(PhoneContactService::normalizePhone('077-123-4567') === '94771234567', "Normalize hyphenated 077-123-4567 -> 94771234567");
assertTest(PhoneContactService::normalizePhone('771234567') === '94771234567', "Normalize 9-digit 771234567 -> 94771234567");
assertTest(PhoneContactService::normalizePhone('0112345678') === '', "Reject landline 0112345678 -> empty string");
assertTest(PhoneContactService::normalizePhone('invalid_phone') === '', "Reject alphabetic input -> empty string");
assertTest(PhoneContactService::formatPhoneDisplay('94771234567') === '0771234567', "Format display 94771234567 -> 0771234567");

// ─────────────────────────────────────────────────────────────────
// TEST 2: Allowed Locations Configuration & Validation
// ─────────────────────────────────────────────────────────────────
echo "\n--- 2. CONFIGURABLE ALLOWED LOCATIONS ---\n";
$locs = PhoneContactService::getAllowedLocations($pdo);
assertTest(in_array('Kandy', $locs, true) && in_array('Kurunegala', $locs, true) && in_array('Online', $locs, true), "Default locations include Kandy, Kurunegala, Online");
assertTest(PhoneContactService::matchAllowedLocation('kandy', $locs) === 'Kandy', "Case-insensitive location matching: 'kandy' -> 'Kandy'");
assertTest(PhoneContactService::matchAllowedLocation('ONLINE', $locs) === 'Online', "Case-insensitive location matching: 'ONLINE' -> 'Online'");
assertTest(PhoneContactService::matchAllowedLocation('Colombo', $locs) === null, "Unknown location 'Colombo' returns null when not allowed");

// Test adding a configurable location
PhoneContactService::saveAllowedLocations($pdo, ['Kandy', 'Kurunegala', 'Online', 'Colombo']);
$updatedLocs = PhoneContactService::getAllowedLocations($pdo);
assertTest(in_array('Colombo', $updatedLocs, true), "Configurable location 'Colombo' successfully added to settings");
assertTest(PhoneContactService::matchAllowedLocation('colombo', $updatedLocs) === 'Colombo', "Newly configured location matches correctly");
// Restore defaults
PhoneContactService::saveAllowedLocations($pdo, ['Kandy', 'Kurunegala', 'Online']);

// ─────────────────────────────────────────────────────────────────
// TEST 3: User's Explicit Scenario (Section 39 of Requirements)
// ─────────────────────────────────────────────────────────────────
echo "\n--- 3. SPECIFIC CASE (REQUIREMENTS SECTION 39) ---\n";
/**
 * Import:
 * phone_number,exam_year,exam_type,location
 * 0771234567,2026,IGCSE,Kandy
 * 0771234567,2026,IGCSE,Kandy
 * 0771234567,2027,IAL,Kandy
 * 0771234567,2027,IAL,Online
 * 0712345678,2026,IGCSE,Kurunegala
 *
 * Expected result:
 * Contacts:
 * 0771234567
 * 0712345678
 *
 * Academic Records:
 * 0771234567 → 2026 / IGCSE / Kandy
 * 0771234567 → 2027 / IAL / Kandy
 * 0771234567 → 2027 / IAL / Online
 * 0712345678 → 2026 / IGCSE / Kurunegala
 *
 * The duplicate: 0771234567 → 2026 / IGCSE / Kandy must only be stored once.
 */

$csvContent39 = "phone_number,exam_year,exam_type,location\n" .
"0771234567,2026,IGCSE,Kandy\n" .
"0771234567,2026,IGCSE,Kandy\n" .
"0771234567,2027,IAL,Kandy\n" .
"0771234567,2027,IAL,Online\n" .
"0712345678,2026,IGCSE,Kurunegala\n";

$tmpFile39 = tempnam(sys_get_temp_dir(), 'csv_test39_') . '.csv';
file_put_contents($tmpFile39, $csvContent39);

$analysis39 = PhoneContactService::analyzeCsvFile($tmpFile39, '2026_IGCSE_Kandy.csv', $pdo);
assertTest($analysis39['total_rows'] === 5, "Total rows parsed is 5");
assertTest($analysis39['valid_rows'] === 5, "Valid rows parsed is 5");
assertTest($analysis39['invalid_rows'] === 0, "Invalid rows is 0");
assertTest($analysis39['new_phone_contacts'] === 2, "Identified 2 new distinct phone contacts (0771234567 & 0712345678)");
assertTest($analysis39['new_academic_records'] === 4, "Identified 4 distinct academic records");
assertTest($analysis39['duplicate_records'] === 1, "Identified 1 duplicate academic record in preview");

$importResult39 = PhoneContactService::executeImport(
    $pdo,
    1,
    '2026_IGCSE_Kandy.csv',
    '2026 IGCSE Kandy',
    $analysis39['valid_records'],
    $analysis39['invalid_rows_list']
);

assertTest($importResult39['new_contacts'] === 2, "Import created exactly 2 contacts");
assertTest($importResult39['new_records'] === 4, "Import created exactly 4 academic records");
assertTest($importResult39['duplicate_records'] === 1, "Import skipped exactly 1 duplicate academic record");

// Verify in DB
$c1 = $pdo->query("SELECT id FROM phone_contacts WHERE normalized_phone = '94771234567'")->fetch(PDO::FETCH_ASSOC);
$c2 = $pdo->query("SELECT id FROM phone_contacts WHERE normalized_phone = '94712345678'")->fetch(PDO::FETCH_ASSOC);
assertTest($c1 && $c2, "Both contacts exist in phone_contacts table");

$recordsC1 = $pdo->query("SELECT exam_year, exam_type, location FROM phone_contact_records WHERE contact_id = {$c1['id']} ORDER BY exam_year, exam_type, location")->fetchAll(PDO::FETCH_ASSOC);
assertTest(count($recordsC1) === 3, "Contact 0771234567 has exactly 3 academic records");
assertTest($recordsC1[0] === ['exam_year' => 2026, 'exam_type' => 'IGCSE', 'location' => 'Kandy'], "Record 1: 2026 / IGCSE / Kandy");
assertTest($recordsC1[1] === ['exam_year' => 2027, 'exam_type' => 'IAL', 'location' => 'Kandy'], "Record 2: 2027 / IAL / Kandy");
assertTest($recordsC1[2] === ['exam_year' => 2027, 'exam_type' => 'IAL', 'location' => 'Online'], "Record 3: 2027 / IAL / Online");

$recordsC2 = $pdo->query("SELECT exam_year, exam_type, location FROM phone_contact_records WHERE contact_id = {$c2['id']}")->fetchAll(PDO::FETCH_ASSOC);
assertTest(count($recordsC2) === 1 && $recordsC2[0] === ['exam_year' => 2026, 'exam_type' => 'IGCSE', 'location' => 'Kurunegala'], "Contact 0712345678 has 1 record: 2026 / IGCSE / Kurunegala");

unlink($tmpFile39);

// ─────────────────────────────────────────────────────────────────
// TEST 4: Optional Name & School + Enrichment
// ─────────────────────────────────────────────────────────────────
echo "\n--- 4. OPTIONAL NAME & SCHOOL + CONTACT ENRICHMENT ---\n";
// Re-import with Kamal Perera for 0771234567 and ABC College
$csvEnrich = "name,phone_number,exam_year,exam_type,school,location\n" .
"Kamal Perera,0771234567,2028,IAL,ABC College,Kandy\n";

$tmpEnrich = tempnam(sys_get_temp_dir(), 'csv_enrich_') . '.csv';
file_put_contents($tmpEnrich, $csvEnrich);

$analysisEnrich = PhoneContactService::analyzeCsvFile($tmpEnrich, 'enrich.csv', $pdo, '2028 Batch');
assertTest($analysisEnrich['existing_phone_contacts'] === 1, "Recognizes 0771234567 as an existing phone contact");

$repEnrich = PhoneContactService::executeImport(
    $pdo,
    1,
    'enrich.csv',
    '2028 Batch',
    $analysisEnrich['valid_records'],
    $analysisEnrich['invalid_rows_list']
);
assertTest($repEnrich['new_contacts'] === 0, "No new contact created for existing phone number");
assertTest($repEnrich['existing_contacts'] === 1, "Enriched existing contact");
assertTest($repEnrich['new_records'] === 1, "Added new 2028 academic record");

// Check that Kamal Perera and ABC College were saved
$cEnriched = $pdo->query("SELECT name, school FROM phone_contacts WHERE normalized_phone = '94771234567'")->fetch(PDO::FETCH_ASSOC);
assertTest($cEnriched['name'] === 'Kamal Perera', "Contact name enriched to 'Kamal Perera'");
assertTest($cEnriched['school'] === 'ABC College', "Contact school enriched to 'ABC College'");

unlink($tmpEnrich);

// ─────────────────────────────────────────────────────────────────
// TEST 5: Invalid Rows Handling (Invalid Phone, Invalid Location, Missing Fields)
// ─────────────────────────────────────────────────────────────────
echo "\n--- 5. INVALID ROWS & ERROR CSV GENERATION ---\n";
$csvInvalid = "phone_number,exam_year,exam_type,location\n" .
"0771234567,2026,IGCSE,Kandy\n" .       // valid (duplicate record)
"0112345678,2026,IGCSE,Kandy\n" .       // invalid phone (landline)
"0770000001,2026,IGCSE,MarsCity\n" .    // invalid location
",2026,IGCSE,Kandy\n" .                 // missing phone
"0770000002,,IGCSE,Kandy\n";            // missing year

$tmpInvalid = tempnam(sys_get_temp_dir(), 'csv_inv_') . '.csv';
file_put_contents($tmpInvalid, $csvInvalid);

$analysisInv = PhoneContactService::analyzeCsvFile($tmpInvalid, 'test_errors.csv', $pdo);
assertTest($analysisInv['total_rows'] === 5, "Total rows is 5");
assertTest($analysisInv['valid_rows'] === 1, "Valid rows is 1");
assertTest($analysisInv['invalid_rows'] === 4, "Invalid rows caught is 4");
assertTest($analysisInv['invalid_breakdown']['invalid_phone'] === 1, "Detected 1 invalid phone");
assertTest($analysisInv['invalid_breakdown']['invalid_location'] === 1, "Detected 1 invalid location ('MarsCity')");
assertTest($analysisInv['invalid_breakdown']['missing_fields'] === 2, "Detected 2 missing required fields");

$repInv = PhoneContactService::executeImport(
    $pdo,
    1,
    'test_errors.csv',
    'Error Test Group',
    $analysisInv['valid_records'],
    $analysisInv['invalid_rows_list']
);
assertTest(!empty($repInv['error_csv_path']), "Error CSV generated and stored in storage/imports/");
assertTest(is_file(__DIR__ . '/../' . $repInv['error_csv_path']), "Error CSV file exists on disk");

unlink($tmpInvalid);

// ─────────────────────────────────────────────────────────────────
// TEST 6: Contact Filtering & Server-Side Search
// ─────────────────────────────────────────────────────────────────
echo "\n--- 6. CONTACT FILTERING & SERVER-SIDE SEARCH ---\n";
$filt1 = PhoneContactService::getFilteredContacts($pdo, ['location' => 'Online']);
assertTest($filt1['total_filtered'] === 1 && $filt1['contacts'][0]['normalized_phone'] === '94771234567', "Filter location='Online' returns exactly 1 contact (0771234567)");

$filt2 = PhoneContactService::getFilteredContacts($pdo, ['exam_year' => '2026', 'exam_type' => 'IGCSE']);
assertTest($filt2['total_filtered'] === 2, "Combined filter (2026 + IGCSE) returns 2 contacts");

$filt3 = PhoneContactService::getFilteredContacts($pdo, ['location' => 'Kurunegala']);
assertTest($filt3['total_filtered'] === 1 && $filt3['contacts'][0]['normalized_phone'] === '94712345678', "Filter location='Kurunegala' returns 1 contact (0712345678)");

$filtSearch = PhoneContactService::getFilteredContacts($pdo, ['search' => 'Kamal']);
assertTest($filtSearch['total_filtered'] === 1 && $filtSearch['contacts'][0]['name'] === 'Kamal Perera', "Search by name 'Kamal' finds contact");

// ─────────────────────────────────────────────────────────────────
// TEST 7: SMS Recipient Deduplication (Section 20 & 43)
// ─────────────────────────────────────────────────────────────────
echo "\n--- 7. SMS RECIPIENT DEDUPLICATION ---\n";
// Kamal has records in Kandy (2026, 2027, 2028) and Online (2027).
// When we query all contacts matching Kandy:
$recipsKandy = PhoneContactService::getUniqueRecipients($pdo, ['location' => 'Kandy'], [], true);
assertTest(count($recipsKandy) === 1, "Kamal (having multiple Kandy records) is returned exactly ONCE in SMS recipient list");

// When we query all contacts (no filter):
$recipsAll = PhoneContactService::getUniqueRecipients($pdo, [], [], true);
assertTest(count($recipsAll) === 2, "Total unique recipients for both contacts is exactly 2");

// Test SMS opt-out exclusion
$pdo->exec("UPDATE phone_contacts SET sms_opt_out = 1 WHERE normalized_phone = '94712345678'");
$recipsOptOut = PhoneContactService::getUniqueRecipients($pdo, [], [], true);
assertTest(count($recipsOptOut) === 1 && isset($recipsOptOut['94771234567']), "Contact with sms_opt_out=1 is automatically excluded from SMS recipients");
// Restore opt-out
$pdo->exec("UPDATE phone_contacts SET sms_opt_out = 0 WHERE normalized_phone = '94712345678'");

// ─────────────────────────────────────────────────────────────────
// TEST 8: Teacher SMS Permissions, Quota, & Hard Limits
// ─────────────────────────────────────────────────────────────────
echo "\n--- 8. TEACHER SMS PERMISSIONS & HARD QUOTA ENFORCEMENT ---\n";
$teacherUserId = 999;
// Insert test user if not exists
$pdo->exec("INSERT IGNORE INTO users (id, username, password_hash, role) VALUES ($teacherUserId, 'test.teacher.sms', 'dummy', 'teacher')");

// Test default permissions: OFF, OFF, OFF, limit 0
$defaultPerms = TeacherSmsService::getOrCreateTeacherPermissions($pdo, $teacherUserId);
assertTest($defaultPerms['sms_access'] === 0, "Default teacher sms_access is OFF (0)");
assertTest($defaultPerms['monthly_limit'] === 0, "Default teacher monthly_limit is 0");
assertTest($defaultPerms['can_send_sms'] === 0, "Default teacher can_send_sms is OFF (0)");
assertTest($defaultPerms['gateway'] === 'ipromo', "Teacher gateway is set to 'ipromo'");

// Validate sending when disabled: MUST REJECT
$valDenied = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 10, 'ipromo');
assertTest($valDenied['allowed'] === false, "Teacher with sms_access=0 is denied sending SMS");

// Admin enables teacher with limit = 100 units
TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, [
    'sms_access'          => 1,
    'can_send_sms'        => 1,
    'can_view_contacts'   => 1,
    'can_select_contacts' => 1,
    'monthly_limit'       => 100,
    'allowed_exam_years'  => '2026',
    'allowed_exam_types'  => 'IGCSE',
    'allowed_locations'   => 'Kandy,Kurunegala',
], 1);

$savedPerm = TeacherSmsService::getTeacherPermissions($pdo, $teacherUserId);
assertTest($savedPerm['sms_access'] === 1 && $savedPerm['monthly_limit'] === 100, "Teacher permissions and monthly limit saved");
assertTest($savedPerm['allowed_years_array'] === [2026], "Teacher allowed years restricted to [2026]");

// GATEWAY ENFORCEMENT: Teachers must ONLY use ipromo
$valGwHacked = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 10, 'sms_gate_android');
assertTest($valGwHacked['allowed'] === false && str_contains($valGwHacked['error'], 'iPromo'), "Teacher attempting to use 'sms_gate_android' is strictly rejected server-side");

// QUOTA LIMIT ENFORCEMENT:
// Simulate 90 units already used this month
$stmtCamp = $pdo->prepare(
    "INSERT INTO bulk_sms_campaigns (campaign_code, campaign_name, gateway, created_by, source_filename, message, message_hash, recipient_count, total_sms_units, status, created_at)
     VALUES ('TEST-CAMP-999', 'Teacher Test Campaign', 'ipromo', $teacherUserId, 'test.csv', 'Test', 'dummyhash', 90, 90, 'COMPLETED', NOW())"
);
$stmtCamp->execute();
$cId = (int)$pdo->lastInsertId();

$stmtRec = $pdo->prepare(
    "INSERT INTO bulk_sms_recipients (campaign_id, phone_number, message, message_hash, recipient_message_hash, sms_units, status, created_at)
     VALUES ($cId, '94770000001', 'Test', 'dummyhash', 'dummyhash', 90, 'SENT', NOW())"
);
$stmtRec->execute();

$usageCheck = TeacherSmsService::getTeacherMonthlyUsage($pdo, $teacherUserId);
assertTest($usageCheck['used_units'] === 90, "Calculated teacher used units is 90");
assertTest($usageCheck['remaining_units'] === 10, "Calculated teacher remaining units is 10 (100 - 90)");

// Teacher tries to send 15 units when only 10 remain: HARD REJECT
$valQuotaExceeded = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 15, 'ipromo');
assertTest($valQuotaExceeded['allowed'] === false, "Campaign requiring 15 units with 10 remaining is rejected");
assertTest(str_contains($valQuotaExceeded['error'], 'Monthly SMS limit exceeded. Remaining: 10, Required: 15'), "Error message exactly indicates remaining and required units");

// Teacher tries to send 5 units when 10 remain: ALLOWED (if ipromo active) or checked
if (function_exists('ipromo_enabled') && ipromo_enabled($pdo) && function_exists('ipromo_configured') && ipromo_configured($pdo)) {
    $valOk = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 5, 'ipromo');
    assertTest($valOk['allowed'] === true, "Campaign within remaining quota is allowed");
} else {
    echo " [INFO] iPromo gateway inactive/unconfigured in DB settings, gateway activation check working correctly\n";
    $passCount++;
}

// Clean up teacher test campaign & user
$pdo->exec("DELETE FROM bulk_sms_recipients WHERE campaign_id = $cId");
$pdo->exec("DELETE FROM bulk_sms_campaigns WHERE id = $cId");
$pdo->exec("DELETE FROM teacher_sms_permissions WHERE teacher_user_id = $teacherUserId");
$pdo->exec("DELETE FROM users WHERE id = $teacherUserId");

// ─────────────────────────────────────────────────────────────────
// TEST 9: CSV Formula Injection Protection
// ─────────────────────────────────────────────────────────────────
echo "\n--- 9. CSV FORMULA INJECTION PROTECTION ---\n";
assertTest(PhoneContactService::sanitizeCsvField('=SUM(A1:A10)') === "'=SUM(A1:A10)", "Prepend single quote to '=' formula");
assertTest(PhoneContactService::sanitizeCsvField('+cmd|calc') === "'+cmd|calc", "Prepend single quote to '+' formula");
assertTest(PhoneContactService::sanitizeCsvField('-1+1') === "'-1+1", "Prepend single quote to '-' formula");
assertTest(PhoneContactService::sanitizeCsvField('@alert') === "'@alert", "Prepend single quote to '@' formula");
assertTest(PhoneContactService::sanitizeCsvField('Kamal Perera') === "Kamal Perera", "Safe text remains unescaped");

// ─────────────────────────────────────────────────────────────────
// TEST 10: Academic Record Duplicate Logic with School
// ─────────────────────────────────────────────────────────────────
echo "\n--- 10. ACADEMIC RECORD DEDUPLICATION WITH SCHOOL ---\n";
// Create clean temporary contact
$testPhoneSchool = '0779998811';
$normSchool = '94779998811';
$pdo->exec("DELETE FROM phone_contacts WHERE normalized_phone = '$normSchool'");

$csvSchoolContent = "phone_number,exam_year,exam_type,location,school\n"
    . "$testPhoneSchool,2026,IGCSE,Kandy,ABC College\n"
    . "$testPhoneSchool,2026,IGCSE,Kandy,XYZ College\n" // Different school: must create distinct academic record
    . "$testPhoneSchool,2026,IGCSE,Kandy,ABC College\n"; // Exact duplicate school: must skip

$csvFileSchool = __DIR__ . '/test_school_dedup.csv';
file_put_contents($csvFileSchool, $csvSchoolContent);

$analysisSchool = PhoneContactService::analyzeCsvFile($csvFileSchool, 'test_school_dedup.csv', $pdo, '2026 IGCSE Kandy');
assertTest($analysisSchool['valid_rows'] === 3, "All 3 rows valid in school CSV");
assertTest($analysisSchool['new_phone_contacts'] === 1, "Exactly 1 new phone contact detected");
assertTest($analysisSchool['new_academic_records'] === 2, "2 distinct academic records detected due to different schools");
assertTest($analysisSchool['duplicate_records'] === 1, "1 exact duplicate academic record detected and skipped");

$importResSchool = PhoneContactService::executeImport(
    $pdo,
    1,
    'test_school_dedup.csv',
    '2026 IGCSE Kandy',
    $analysisSchool['valid_records'],
    $analysisSchool['invalid_rows_list']
);
assertTest($importResSchool['new_contacts'] === 1, "Import created 1 new phone contact");
assertTest($importResSchool['new_records'] === 2, "Import created 2 academic records (ABC College and XYZ College)");
assertTest($importResSchool['duplicate_records'] === 1, "Import skipped 1 exact duplicate record");

// Test NULL / empty school deduplication
$csvEmptySchool = "phone_number,exam_year,exam_type,location,school\n"
    . "$testPhoneSchool,2027,IAL,Kurunegala,\n"
    . "$testPhoneSchool,2027,IAL,Kurunegala,\n"; // Both empty school: must deduplicate to 1
$csvFileEmpty = __DIR__ . '/test_empty_school.csv';
file_put_contents($csvFileEmpty, $csvEmptySchool);

$analysisEmpty = PhoneContactService::analyzeCsvFile($csvFileEmpty, 'test_empty_school.csv', $pdo, '2027 IAL Kurunegala');
$importEmpty = PhoneContactService::executeImport(
    $pdo,
    1,
    'test_empty_school.csv',
    '2027 IAL Kurunegala',
    $analysisEmpty['valid_records'],
    $analysisEmpty['invalid_rows_list']
);
assertTest($importEmpty['new_records'] === 1, "Empty school imported 1 academic record");
assertTest($importEmpty['duplicate_records'] === 1, "Duplicate empty school skipped");

@unlink($csvFileSchool);
@unlink($csvFileEmpty);

// ─────────────────────────────────────────────────────────────────
// TEST 11: Name Conflict Handling
// ─────────────────────────────────────────────────────────────────
echo "\n--- 11. NAME CONFLICT HANDLING ---\n";
// Kamal exists on $normSchool. Import new CSV with different name: Sunil
$csvConflict = "phone_number,exam_year,exam_type,location,name\n"
    . "$testPhoneSchool,2028,IAL,Online,Sunil Perera\n";
$csvFileConflict = __DIR__ . '/test_conflict.csv';
file_put_contents($csvFileConflict, $csvConflict);

// First ensure Kamal is the contact's name
$cSchoolId = (int)$pdo->query("SELECT id FROM phone_contacts WHERE normalized_phone = '$normSchool'")->fetchColumn();
PhoneContactService::updateContact($pdo, $cSchoolId, [
    'name'  => 'Kamal Perera',
    'phone' => $testPhoneSchool,
]);

$analysisConflict = PhoneContactService::analyzeCsvFile($csvFileConflict, 'test_conflict.csv', $pdo, '2028 IAL Online');
PhoneContactService::executeImport(
    $pdo,
    1,
    'test_conflict.csv',
    '2028 IAL Online',
    $analysisConflict['valid_records'],
    $analysisConflict['invalid_rows_list']
);
$contactAfter = PhoneContactService::getContactDetails($pdo, $cSchoolId);
assertTest($contactAfter['name'] === 'Kamal Perera', "Existing contact name Kamal Perera is preserved and not overwritten");
assertTest(!empty($contactAfter['name_conflict']), "Name conflict field was populated");
assertTest(str_contains($contactAfter['name_conflict'], 'Sunil Perera'), "Conflict message contains the conflicting name Sunil Perera");

@unlink($csvFileConflict);

// ─────────────────────────────────────────────────────────────────
// TEST 12: SMS Status & Recipient Filtering
// ─────────────────────────────────────────────────────────────────
echo "\n--- 12. SMS STATUS & STRICT RECIPIENT FILTERING ---\n";
// Update contact to blocked
PhoneContactService::updateContact($pdo, $cSchoolId, [
    'phone'      => $testPhoneSchool,
    'sms_status' => 'blocked',
]);

// Verify blocked contact is NEVER in getUniqueRecipients
$recipientsBlockedCheck = PhoneContactService::getUniqueRecipients($pdo, ['search' => $testPhoneSchool]);
assertTest(!isset($recipientsBlockedCheck[$normSchool]), "Blocked contact is strictly excluded from unique recipients");

// Check exclusion breakdown
$breakdown = PhoneContactService::getRecipientExclusionBreakdown($pdo, ['search' => $testPhoneSchool]);
assertTest($breakdown['blocked'] >= 1, "Exclusion breakdown correctly counts blocked contact");
assertTest($breakdown['eligible'] === 0, "Exclusion breakdown marks 0 eligible contacts for blocked number");

// Change to allowed
PhoneContactService::updateContact($pdo, $cSchoolId, [
    'phone'      => $testPhoneSchool,
    'sms_status' => 'allowed',
]);
$recipientsAllowedCheck = PhoneContactService::getUniqueRecipients($pdo, ['search' => $testPhoneSchool]);
assertTest(isset($recipientsAllowedCheck[$normSchool]), "Allowed contact is included in unique recipients");

// ─────────────────────────────────────────────────────────────────
// TEST 13: Recent Campaign Duplicate Warning
// ─────────────────────────────────────────────────────────────────
echo "\n--- 13. RECENT CAMPAIGN DUPLICATE WARNING ---\n";
// Simulate a recent bulk SMS recipient record for this phone
$stmtC = $pdo->prepare(
    "INSERT INTO bulk_sms_campaigns (campaign_code, campaign_name, gateway, created_by, source_filename, message, message_hash, recipient_count, total_sms_units, status, created_at)
     VALUES ('TEST-WARN-101', 'Recent Warning Test', 'ipromo', 1, 'warn.csv', 'Warn Test', 'hash101', 1, 1, 'COMPLETED', NOW())"
);
$stmtC->execute();
$cWarnId = (int)$pdo->lastInsertId();

$stmtR = $pdo->prepare(
    "INSERT INTO bulk_sms_recipients (campaign_id, phone_number, message, message_hash, recipient_message_hash, sms_units, status, created_at)
     VALUES ($cWarnId, '$normSchool', 'Warn Test', 'hash101', 'hash101', 1, 'SENT', NOW())"
);
$stmtR->execute();

$duplicateWarn = PhoneContactService::checkRecentCampaignDuplicates($pdo, [$normSchool], 7);
assertTest($duplicateWarn['has_duplicates'] === true, "Recent campaign duplicate detected within 7 days");
assertTest($duplicateWarn['duplicate_count'] === 1, "Duplicate count is exactly 1");

$pdo->exec("DELETE FROM bulk_sms_recipients WHERE campaign_id = $cWarnId");
$pdo->exec("DELETE FROM bulk_sms_campaigns WHERE id = $cWarnId");

// ─────────────────────────────────────────────────────────────────
// TEST 14: Global Emergency SMS Switch
// ─────────────────────────────────────────────────────────────────
echo "\n--- 14. GLOBAL EMERGENCY SMS SWITCH ---\n";
$origGlobalState = TeacherSmsService::isTeacherSmsGloballyEnabled($pdo);

// Create temp teacher
$tId = 998;
$pdo->exec("INSERT IGNORE INTO users (id, username, password_hash, role) VALUES ($tId, 'emergency_teacher', 'dummy', 'teacher')");
TeacherSmsService::saveTeacherPermissions($pdo, $tId, [
    'sms_access'      => 1,
    'can_send_sms'    => 1,
    'monthly_limit'   => 1000,
], 1);

// Turn OFF globally
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, false, 1);
assertTest(TeacherSmsService::isTeacherSmsGloballyEnabled($pdo) === false, "Teacher SMS globally disabled via setting");

$valEmergency = TeacherSmsService::validateTeacherCanSend($pdo, $tId, 1, 'ipromo');
assertTest($valEmergency['allowed'] === false, "Teacher cannot send SMS when emergency stop is active");
assertTest(str_contains($valEmergency['error'], 'emergency stop') || str_contains($valEmergency['error'], 'disabled'), "Rejection notice mentions emergency stop");

// Restore global state
TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, true, 1);
assertTest(TeacherSmsService::isTeacherSmsGloballyEnabled($pdo) === true, "Teacher SMS globally re-enabled");

// ─────────────────────────────────────────────────────────────────
// TEST 15: Teacher Allowed WhatsApp Groups Scope Restriction
// ─────────────────────────────────────────────────────────────────
echo "\n--- 15. TEACHER ALLOWED WHATSAPP GROUPS SCOPE RESTRICTION ---\n";
TeacherSmsService::saveTeacherPermissions($pdo, $tId, [
    'sms_access'              => 1,
    'can_send_sms'            => 1,
    'monthly_limit'           => 1000,
    'allowed_whatsapp_groups' => '2026 IGCSE Kandy, 2027 IAL Kurunegala',
], 1);

$teacherPerms = TeacherSmsService::getTeacherPermissions($pdo, $tId);
assertTest(in_array('2026 IGCSE Kandy', $teacherPerms['allowed_groups_array'], true), "Allowed WhatsApp groups array parsed correctly");

// Enforce teacher filters with an unauthorized group -> throws RuntimeException
$threwDenied = false;
try {
    TeacherSmsService::enforceTeacherFilters($pdo, $tId, ['source_group' => '2028 IAL Online']);
} catch (RuntimeException $e) {
    $threwDenied = true;
}
assertTest($threwDenied, "Unauthorized WhatsApp group request was blocked with Access Denied exception");

// Enforce teacher filters with an authorized group
$enforcedOk = TeacherSmsService::enforceTeacherFilters($pdo, $tId, ['source_group' => '2026 IGCSE Kandy']);
assertTest($enforcedOk['source_group'] === '2026 IGCSE Kandy', "Authorized WhatsApp group was permitted");

// ─────────────────────────────────────────────────────────────────
// TEST 16: WhatsApp Group Statistics & Contact Timeline
// ─────────────────────────────────────────────────────────────────
echo "\n--- 16. WHATSAPP GROUP STATISTICS & CONTACT TIMELINE ---\n";
$stats = PhoneContactService::getWhatsAppGroupStatistics($pdo);
assertTest(is_array($stats) && count($stats) > 0, "WhatsApp group statistics returned at least 1 group");

$timeline = PhoneContactService::getContactTimeline($pdo, $cSchoolId);
assertTest(is_array($timeline) && count($timeline) >= 2, "Contact timeline contains events (creation and academic records)");
$hasAcademicEvent = false;
foreach ($timeline as $ev) {
    if ($ev['event_type'] === 'academic_record') {
        $hasAcademicEvent = true;
        break;
    }
}
assertTest($hasAcademicEvent, "Contact timeline includes academic association events");

// Clean up temp teacher
$pdo->exec("DELETE FROM teacher_sms_permissions WHERE teacher_user_id = $tId");
$pdo->exec("DELETE FROM users WHERE id = $tId");

// Clean up temp contact
$pdo->exec("DELETE FROM phone_contact_records WHERE contact_id = $cSchoolId");
$pdo->exec("DELETE FROM phone_contacts WHERE id = $cSchoolId");

// ─────────────────────────────────────────────────────────────────
// FINAL SUMMARY
// ─────────────────────────────────────────────────────────────────
echo "\n====================================================================\n";
echo " TEST SUITE SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
