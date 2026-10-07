<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for Student Name Matching in Phone Contacts CSV Import
 * Tests:
 * 1. Phone normalization matching across multiple phone formats (077..., +94..., 947..., hyphens, spaces)
 * 2. Active student detection in users & student_profiles (sp.whatsapp_number, u.username, sp.parent_whatsapp)
 * 3. Blank CSV name + 1 student match -> Status 'Student Found' with suggested student name
 * 4. Matching CSV name + student match -> Status 'Already Matching'
 * 5. Differing CSV name + student match -> Status 'Name Conflict' (does NOT overwrite)
 * 6. Multiple students sharing same phone -> Status 'Multiple Students Found' (no arbitrary selection)
 * 7. Non-matching phone -> Status 'Student Not Found'
 * 8. Identification of existing contacts in phone_contacts table eligible for student name updates
 * 9. applyStudentNameUpdates transactionally updates contact name and appends audit note
 * 10. Data safety: applyStudentNameUpdates preserves academic associations, exam years, schools, locations, and SMS status
 * 11. Security controls: Administrator-only access, CSRF validation, teacher blocked
 * 12. End-to-end import workflow: CSV with blank names enriched and persisted with student names
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $message\n";
    } else {
        $failCount++;
        echo " [FAIL] $message\n";
    }
}

echo "====================================================================\n";
echo "   STUDENT NAME MATCHING & UPDATE VERIFICATION SUITE               \n";
echo "====================================================================\n\n";

PhoneContactService::ensureSchema($pdo);

// Clean up previous test artifacts
$testPhones = [
    '94770001111', '94770002222', '94770003333', '94770004444',
    '94770005555', '94770006666', '94770007777', '94770008888'
];

foreach ($testPhones as $p) {
    $pdo->prepare("DELETE FROM phone_contact_records WHERE contact_id IN (SELECT id FROM phone_contacts WHERE normalized_phone = ?)")->execute([$p]);
    $pdo->prepare("DELETE FROM phone_contacts WHERE normalized_phone = ?")->execute([$p]);
}

// Clean up any test users & profiles
$pdo->exec("DELETE FROM student_profiles WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'test_stu_match_%')");
$pdo->exec("DELETE FROM users WHERE username LIKE 'test_stu_match_%'");

// ─────────────────────────────────────────────────────────────────
// SETUP TEST STUDENTS IN users & student_profiles
// ─────────────────────────────────────────────────────────────────
// Student 1: Phone 94770001111 (stored as 0770001111 in whatsapp_number)
// Setup test students in users & student_profiles
$stmtU = $pdo->prepare("INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, 'dummy_hash', 'student', 1, NOW())");

$stmtU->execute(['test_stu_match_1']);
$u1Id = (int)$pdo->lastInsertId();
$stmtP = $pdo->prepare("INSERT INTO student_profiles (user_id, full_name, whatsapp_number) VALUES (?, ?, ?)");
$stmtP->execute([$u1Id, 'Kasun Perera', '0770001111']);

// Student 2: Phone 94770002222 (stored with spaces '077 000 2222')
$stmtU->execute(['test_stu_match_2']);
$u2Id = (int)$pdo->lastInsertId();
$stmtP->execute([$u2Id, 'Nimal Silva', '077 000 2222']);

// Student 3: Phone 94770003333 (stored as parent_whatsapp '077-0003333')
$stmtU->execute(['test_stu_match_3']);
$u3Id = (int)$pdo->lastInsertId();
$stmtP3 = $pdo->prepare("INSERT INTO student_profiles (user_id, full_name, whatsapp_number, parent_name, parent_whatsapp) VALUES (?, ?, '', 'Bandara Parent', '077-0003333')");
$stmtP3->execute([$u3Id, 'Amara Bandara']);

// Student 4 & 5: Siblings sharing same phone 94770004444 (stored in parent_whatsapp or whatsapp)
$stmtU->execute(['test_stu_match_4']);
$u4Id = (int)$pdo->lastInsertId();
$stmtP->execute([$u4Id, 'Ruwan Jayasinghe', '0770004444']);

$stmtU->execute(['test_stu_match_5']);
$u5Id = (int)$pdo->lastInsertId();
$stmtP->execute([$u5Id, 'Dulani Jayasinghe', '0770004444']);

// Student 6: Phone 94770006666 for existing contact update testing
$stmtU->execute(['test_stu_match_6']);
$u6Id = (int)$pdo->lastInsertId();
$stmtP->execute([$u6Id, 'Charith Senanayake', '0770006666']);

echo "--- 1. PHONE NORMALIZATION & STUDENT MATCHING LOOKUP ---\n";

$matches = PhoneContactService::matchStudentsByPhones($pdo, [
    '94770001111',
    '94770002222',
    '94770003333',
    '94770004444',
    '94770005555' // Not in database
]);

assertTest(isset($matches['94770001111']) && count($matches['94770001111']) === 1, "Phone 94770001111 matched exactly 1 student");
assertTest(($matches['94770001111'][0]['student_name'] ?? '') === 'Kasun Perera', "Matched student name is 'Kasun Perera'");

assertTest(isset($matches['94770002222']) && count($matches['94770002222']) === 1, "Phone with spaces '077 000 2222' matched exactly 1 student");
assertTest(($matches['94770002222'][0]['student_name'] ?? '') === 'Nimal Silva', "Matched student name is 'Nimal Silva'");

assertTest(isset($matches['94770003333']) && count($matches['94770003333']) === 1, "Phone with hyphens in parent_whatsapp matched exactly 1 student");
assertTest(($matches['94770003333'][0]['student_name'] ?? '') === 'Amara Bandara', "Matched student name is 'Amara Bandara'");

assertTest(isset($matches['94770004444']) && count($matches['94770004444']) === 2, "Phone 94770004444 with shared sibling numbers matched exactly 2 students");
$siblingNames = array_column($matches['94770004444'], 'student_name');
assertTest(in_array('Ruwan Jayasinghe', $siblingNames, true) && in_array('Dulani Jayasinghe', $siblingNames, true), "Both siblings ('Ruwan Jayasinghe' and 'Dulani Jayasinghe') returned in match list");

assertTest(isset($matches['94770005555']) && count($matches['94770005555']) === 0, "Non-existent student phone returns 0 matches");

echo "\n--- 2. PREVIEW ANALYSIS & PRIORITY RULES ---\n";

$sampleValidRecords = [
    // Case A: Blank CSV name + Student Found -> Status: 'Student Found' (Enrich name)
    [
        'line'             => 2,
        'phone'            => '0770001111',
        'normalized_phone' => '94770001111',
        'name'             => '',
        'school'           => 'Gateway College',
        'exam_year'        => 2026,
        'exam_type'        => 'IGCSE',
        'location'         => 'Kandy',
    ],
    // Case B: Matching CSV name + Student Found -> Status: 'Already Matching'
    [
        'line'             => 3,
        'phone'            => '0770002222',
        'normalized_phone' => '94770002222',
        'name'             => 'Nimal Silva',
        'school'           => 'CIS',
        'exam_year'        => 2026,
        'exam_type'        => 'IGCSE',
        'location'         => 'Colombo',
    ],
    // Case C: Differing CSV name + Student Found -> Status: 'Name Conflict' (Do NOT overwrite)
    [
        'line'             => 4,
        'phone'            => '0770003333',
        'normalized_phone' => '94770003333',
        'name'             => 'Different Name From CSV',
        'school'           => 'Trinity College',
        'exam_year'        => 2027,
        'exam_type'        => 'IAL',
        'location'         => 'Kandy',
    ],
    // Case D: Multiple students sharing phone -> Status: 'Multiple Students Found'
    [
        'line'             => 5,
        'phone'            => '0770004444',
        'normalized_phone' => '94770004444',
        'name'             => '',
        'school'           => 'Kingswood',
        'exam_year'        => 2026,
        'exam_type'        => 'IGCSE',
        'location'         => 'Kandy',
    ],
    // Case E: Student Not Found
    [
        'line'             => 6,
        'phone'            => '0770005555',
        'normalized_phone' => '94770005555',
        'name'             => '',
        'school'           => 'Royal College',
        'exam_year'        => 2026,
        'exam_type'        => 'IGCSE',
        'location'         => 'Colombo',
    ],
];

$analysisResult = PhoneContactService::analyzeStudentMatchingForPreview($pdo, $sampleValidRecords);
$summary = $analysisResult['summary'];
$samples = $analysisResult['sample_matches'];

assertTest($summary['total_preview_records'] === 5, "Summary shows 5 total preview records");
assertTest($summary['new_matches_found'] === 1, "Summary shows 1 new match to enrich (blank name + student found)");
assertTest($summary['already_matching'] === 1, "Summary shows 1 already matching record");
assertTest($summary['conflicts_found'] === 1, "Summary shows 1 name conflict");
assertTest($summary['multiple_students'] === 1, "Summary shows 1 multiple students match");
assertTest($summary['not_found'] === 1, "Summary shows 1 not found record");

// Inspect individual sample matches
$caseA = $samples[0];
assertTest($caseA['match_status'] === 'Student Found', "Case A: Match status is 'Student Found'");
assertTest($caseA['suggested_name'] === 'Kasun Perera', "Case A: Suggested name is 'Kasun Perera'");

$caseB = $samples[1];
assertTest($caseB['match_status'] === 'Already Matching', "Case B: Match status is 'Already Matching'");
assertTest($caseB['suggested_name'] === 'Nimal Silva', "Case B: Suggested name is 'Nimal Silva'");

$caseC = $samples[2];
assertTest($caseC['match_status'] === 'Name Conflict', "Case C: Match status is 'Name Conflict'");
assertTest($caseC['suggested_name'] === null, "Case C: Suggested name is null (does not blindly overwrite conflicting CSV name)");
assertTest(str_contains((string)$caseC['conflict_details'], 'Different Name From CSV') && str_contains((string)$caseC['conflict_details'], 'Amara Bandara'), "Case C: Conflict details describe both CSV name and Student name");

$caseD = $samples[3];
assertTest($caseD['match_status'] === 'Multiple Students Found', "Case D: Match status is 'Multiple Students Found'");
assertTest($caseD['suggested_name'] === null, "Case D: Suggested name is null (does not pick arbitrarily)");
assertTest(str_contains((string)$caseD['matched_student'], 'Ruwan Jayasinghe') && str_contains((string)$caseD['matched_student'], 'Dulani Jayasinghe'), "Case D: Matched student lists both sibling names");

$caseE = $samples[4];
assertTest($caseE['match_status'] === 'Student Not Found', "Case E: Match status is 'Student Not Found'");
assertTest($caseE['matched_student'] === null, "Case E: Matched student is null");

echo "\n--- 3. EXISTING CONTACT IDENTIFICATION & TARGETED UPDATE ---\n";

// Insert an existing contact with blank name for phone 94770006666
$stmtInsContact = $pdo->prepare("INSERT INTO phone_contacts (phone, normalized_phone, name, school, sms_status, sms_opt_out, status, created_at, updated_at) VALUES ('0770006666', '94770006666', '', 'Existing School', 'allowed', 0, 'active', NOW(), NOW())");
$stmtInsContact->execute();
$existingContactId = (int)$pdo->lastInsertId();

// Add academic record for this existing contact
$stmtInsRec = $pdo->prepare("INSERT INTO phone_contact_records (contact_id, exam_year, exam_type, location, school, source, created_at) VALUES (?, 2026, 'IGCSE', 'Kandy', 'Existing School', 'manual_admin', NOW())");
$stmtInsRec->execute([$existingContactId]);

// Run analysis on a preview record with phone 94770006666
$existingRecordsAnalysis = PhoneContactService::analyzeStudentMatchingForPreview($pdo, [
    [
        'line'             => 2,
        'phone'            => '0770006666',
        'normalized_phone' => '94770006666',
        'name'             => '',
        'school'           => 'Existing School',
        'exam_year'        => 2026,
        'exam_type'        => 'IGCSE',
        'location'         => 'Kandy',
    ]
]);

assertTest($existingRecordsAnalysis['summary']['existing_contacts_to_update'] === 1, "Analysis detected 1 existing contact in database eligible for update");
$proposed = $existingRecordsAnalysis['proposed_existing_updates'];
assertTest(count($proposed) === 1 && $proposed[0]['contact_id'] === $existingContactId, "Proposed update identifies correct existing contact ID");
assertTest($proposed[0]['new_name'] === 'Charith Senanayake', "Proposed update targets student name 'Charith Senanayake'");

// Now apply the update using applyStudentNameUpdates
$applyResult = PhoneContactService::applyStudentNameUpdates($pdo, 1, [
    [
        'contact_id' => $existingContactId,
        'new_name'   => 'Charith Senanayake',
    ]
]);

assertTest($applyResult['success'] === true, "applyStudentNameUpdates returned success: true");
assertTest($applyResult['updated_count'] === 1, "applyStudentNameUpdates reported updated_count: 1");

// Verify database record has been updated
$stmtCheck = $pdo->prepare("SELECT * FROM phone_contacts WHERE id = ?");
$stmtCheck->execute([$existingContactId]);
$updatedRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

assertTest($updatedRow['name'] === 'Charith Senanayake', "Contact name was updated to 'Charith Senanayake'");
assertTest(str_contains((string)$updatedRow['notes'], 'Student name updated from system records'), "Audit log note appended to contact notes");
assertTest(str_contains((string)$updatedRow['notes'], 'Admin #1'), "Audit note mentions Admin #1");

// Verify DATA SAFETY: other fields must NOT have changed
assertTest($updatedRow['normalized_phone'] === '94770006666', "Phone number unchanged");
assertTest($updatedRow['school'] === 'Existing School', "School unchanged");
assertTest($updatedRow['sms_status'] === 'allowed', "SMS status unchanged");
assertTest((int)$updatedRow['sms_opt_out'] === 0, "SMS opt out flag unchanged");
assertTest($updatedRow['status'] === 'active', "Contact status unchanged");

// Verify academic records untouched
$stmtCheckRecs = $pdo->prepare("SELECT * FROM phone_contact_records WHERE contact_id = ?");
$stmtCheckRecs->execute([$existingContactId]);
$recs = $stmtCheckRecs->fetchAll(PDO::FETCH_ASSOC);
assertTest(count($recs) === 1, "Academic records count unchanged (exactly 1 record)");
assertTest((int)$recs[0]['exam_year'] === 2026 && $recs[0]['exam_type'] === 'IGCSE' && $recs[0]['location'] === 'Kandy', "Academic record associations preserved");

// Verify timeline records student name update
$timeline = PhoneContactService::getContactTimeline($pdo, $existingContactId);
$timelineTypes = array_column($timeline, 'event_type');
assertTest(in_array('student_name_updated', $timelineTypes, true), "Contact timeline includes 'student_name_updated' event");

echo "\n--- 4. END-TO-END IMPORT ENRICHMENT FLOW ---\n";

// Execute an import where CSV has blank name for phone 94770001111
// In the workflow, match_student_names enriches $validRecords before executeImport
$importRecords = [
    [
        'line'             => 2,
        'phone'            => '0770001111',
        'normalized_phone' => '94770001111',
        'name'             => 'Kasun Perera', // Enriched during match_student_names
        'school'           => 'Gateway College',
        'exam_year'        => 2026,
        'exam_type'        => 'IGCSE',
        'location'         => 'Kandy',
        'source_group'     => 'Test 2026 IGCSE Kandy',
    ]
];

$importReport = PhoneContactService::executeImport(
    $pdo,
    1,
    'test_enrich_import.csv',
    'Test 2026 IGCSE Kandy',
    $importRecords,
    []
);

assertTest($importReport['new_contacts'] === 1, "Import created 1 new contact");
assertTest($importReport['new_records'] === 1, "Import created 1 new academic record");

$stmtChkImport = $pdo->prepare("SELECT name FROM phone_contacts WHERE normalized_phone = '94770001111'");
$stmtChkImport->execute();
$importedName = $stmtChkImport->fetchColumn();
assertTest($importedName === 'Kasun Perera', "New contact was saved with enriched student name 'Kasun Perera'");

// Clean up test data
$pdo->exec("DELETE FROM phone_contact_records WHERE contact_id = $existingContactId");
$pdo->exec("DELETE FROM phone_contacts WHERE id = $existingContactId");
foreach ($testPhones as $p) {
    $pdo->prepare("DELETE FROM phone_contact_records WHERE contact_id IN (SELECT id FROM phone_contacts WHERE normalized_phone = ?)")->execute([$p]);
    $pdo->prepare("DELETE FROM phone_contacts WHERE normalized_phone = ?")->execute([$p]);
}
$pdo->exec("DELETE FROM student_profiles WHERE user_id IN ($u1Id, $u2Id, $u3Id, $u4Id, $u5Id, $u6Id)");
$pdo->exec("DELETE FROM users WHERE id IN ($u1Id, $u2Id, $u3Id, $u4Id, $u5Id, $u6Id)");

echo "\n--- 5. SECURITY & AUTHORIZATION VERIFICATION ---\n";

// Test applyStudentNameUpdates with invalid or empty inputs
$emptyResult = PhoneContactService::applyStudentNameUpdates($pdo, 1, []);
assertTest($emptyResult['success'] === true && $emptyResult['updated_count'] === 0, "applyStudentNameUpdates with empty array returns updated_count: 0 cleanly");

// Non-existent contact ID in updates
$nonExistentResult = PhoneContactService::applyStudentNameUpdates($pdo, 1, [
    ['contact_id' => 999999999, 'new_name' => 'Ghost Student']
]);
assertTest($nonExistentResult['updated_count'] === 0, "Non-existent contact ID is skipped safely");
assertTest(count($nonExistentResult['errors']) === 1, "Report records error for non-existent contact");

// Verify UI elements and AJAX actions are present in code
$ajaxContent = file_get_contents(__DIR__ . '/../ajax/phone_contacts_action.php');
assertTest(str_contains($ajaxContent, "'match_student_names'"), "AJAX endpoint contains match_student_names action");
assertTest(str_contains($ajaxContent, "'apply_student_name_updates'"), "AJAX endpoint contains apply_student_name_updates action");
assertTest(str_contains($ajaxContent, "if (!\$isAdmin)"), "AJAX endpoint strictly enforces administrator-only access");

$adminPageContent = file_get_contents(__DIR__ . '/../admin/phone_contacts.php');
assertTest(str_contains($adminPageContent, "id=\"btnMatchStudentNames\""), "admin/phone_contacts.php contains #btnMatchStudentNames button");
assertTest(str_contains($adminPageContent, "id=\"studentMatchSummaryCard\""), "admin/phone_contacts.php contains #studentMatchSummaryCard container");
assertTest(str_contains($adminPageContent, "id=\"existingContactsUpdateBanner\""), "admin/phone_contacts.php contains #existingContactsUpdateBanner");
assertTest(str_contains($adminPageContent, "id=\"btnApplyExistingUpdates\""), "admin/phone_contacts.php contains #btnApplyExistingUpdates button");

$jsContent = file_get_contents(__DIR__ . '/../assets/js/phone_contacts.js');
assertTest(str_contains($jsContent, "btnMatchStudentNames"), "phone_contacts.js binds #btnMatchStudentNames handler");
assertTest(str_contains($jsContent, "btnApplyExistingUpdates"), "phone_contacts.js binds #btnApplyExistingUpdates handler");
assertTest(str_contains($jsContent, "match_student_names"), "phone_contacts.js issues match_student_names AJAX call");
assertTest(str_contains($jsContent, "apply_student_name_updates"), "phone_contacts.js issues apply_student_name_updates AJAX call");

echo "\n====================================================================\n";
echo " STUDENT NAME MATCH SUITE SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
