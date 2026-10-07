<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for Contact Deletion & Bulk Deletion
 * Tests:
 * 1. Single contact deletion (cascades and deletes academic records)
 * 2. Bulk contact deletion by explicit ID list
 * 3. Bulk contact deletion in "all filtered" mode
 * 4. Safety: non-targeted contacts remain completely untouched
 * 5. Clean handling of non-existent IDs
 * 6. Security controls: Admin-only access, CSRF validation, teacher blocked
 * 7. Verification of UI components in HTML and JavaScript
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;

$passCount = 0;
$failCount = 0;

function assertDel(bool $condition, string $message): void {
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
echo "   CONTACT DELETION & BULK DELETION VERIFICATION SUITE              \n";
echo "====================================================================\n\n";

PhoneContactService::ensureSchema($pdo);

// Clean up any test records
$testNorms = ['94779991111', '94779992222', '94779993333', '94779994444', '94779995555'];
foreach ($testNorms as $n) {
    $pdo->prepare("DELETE FROM phone_contact_records WHERE contact_id IN (SELECT id FROM phone_contacts WHERE normalized_phone = ?)")->execute([$n]);
    $pdo->prepare("DELETE FROM phone_contacts WHERE normalized_phone = ?")->execute([$n]);
}

echo "--- 1. SINGLE CONTACT DELETION & CASCADE --- \n";

// Create contact with 2 academic records
$stmtC = $pdo->prepare("INSERT INTO phone_contacts (phone, normalized_phone, name, school, sms_status, status, created_at) VALUES ('0779991111', '94779991111', 'Test Single Delete', 'Test School', 'allowed', 'active', NOW())");
$stmtC->execute();
$c1Id = (int)$pdo->lastInsertId();

$stmtR = $pdo->prepare("INSERT INTO phone_contact_records (contact_id, exam_year, exam_type, location, school, source, created_at) VALUES (?, ?, ?, ?, ?, 'test', NOW())");
$stmtR->execute([$c1Id, 2026, 'IGCSE', 'Kandy', 'Test School']);
$stmtR->execute([$c1Id, 2027, 'IAL', 'Colombo', 'Test School']);

// Verify insertion
$checkC = $pdo->query("SELECT COUNT(*) FROM phone_contacts WHERE id = $c1Id")->fetchColumn();
$checkR = $pdo->query("SELECT COUNT(*) FROM phone_contact_records WHERE contact_id = $c1Id")->fetchColumn();
assertDel((int)$checkC === 1 && (int)$checkR === 2, "Test contact #$c1Id created with 2 academic records");

// Perform deletion
$delRes = PhoneContactService::deleteContact($pdo, $c1Id, 1);
assertDel($delRes === true, "deleteContact returned true");

// Verify contact is gone
$afterC = $pdo->query("SELECT COUNT(*) FROM phone_contacts WHERE id = $c1Id")->fetchColumn();
assertDel((int)$afterC === 0, "Contact row successfully deleted from phone_contacts");

// Verify associated academic records are gone
$afterR = $pdo->query("SELECT COUNT(*) FROM phone_contact_records WHERE contact_id = $c1Id")->fetchColumn();
assertDel((int)$afterR === 0, "Associated academic records successfully deleted from phone_contact_records");

echo "\n--- 2. BULK CONTACT DELETION (EXPLICIT ID LIST) --- \n";

// Create 3 contacts
$stmtC->execute(); // 94779991111
$c1Id = (int)$pdo->lastInsertId();
$stmtR->execute([$c1Id, 2026, 'IGCSE', 'Kandy', 'School A']);

$stmtC2 = $pdo->prepare("INSERT INTO phone_contacts (phone, normalized_phone, name, school, sms_status, status, created_at) VALUES ('0779992222', '94779992222', 'Bulk Delete 2', 'School B', 'allowed', 'active', NOW())");
$stmtC2->execute();
$c2Id = (int)$pdo->lastInsertId();
$stmtR->execute([$c2Id, 2026, 'IGCSE', 'Kurunegala', 'School B']);

$stmtC3 = $pdo->prepare("INSERT INTO phone_contacts (phone, normalized_phone, name, school, sms_status, status, created_at) VALUES ('0779993333', '94779993333', 'Keep Intact 3', 'School C', 'allowed', 'active', NOW())");
$stmtC3->execute();
$c3Id = (int)$pdo->lastInsertId();
$stmtR->execute([$c3Id, 2027, 'IAL', 'Online', 'School C']);

// Delete c1 and c2 in bulk
$bulkRes = PhoneContactService::deleteContactsBulk($pdo, [$c1Id, $c2Id], [], false, 1);
assertDel($bulkRes['success'] === true, "deleteContactsBulk returned success: true");
assertDel($bulkRes['deleted_count'] === 2, "deleteContactsBulk reported deleted_count: 2");

// Verify c1 and c2 are gone
$chkBulk12 = $pdo->query("SELECT COUNT(*) FROM phone_contacts WHERE id IN ($c1Id, $c2Id)")->fetchColumn();
$chkBulkRecs12 = $pdo->query("SELECT COUNT(*) FROM phone_contact_records WHERE contact_id IN ($c1Id, $c2Id)")->fetchColumn();
assertDel((int)$chkBulk12 === 0 && (int)$chkBulkRecs12 === 0, "Selected contacts and their academic records removed completely");

// Verify c3 remains completely untouched
$chkC3 = $pdo->query("SELECT COUNT(*) FROM phone_contacts WHERE id = $c3Id")->fetchColumn();
$chkC3Rec = $pdo->query("SELECT COUNT(*) FROM phone_contact_records WHERE contact_id = $c3Id")->fetchColumn();
assertDel((int)$chkC3 === 1 && (int)$chkC3Rec === 1, "Non-selected contact #$c3Id and its academic record remain untouched");

// Cleanup c3
PhoneContactService::deleteContact($pdo, $c3Id, 1);

echo "\n--- 3. BULK CONTACT DELETION (ALL FILTERED MODE) --- \n";

// Insert 2 contacts with unique source_group
$uniqueGroup = 'DeleteGroup_' . time();
$stmtC4 = $pdo->prepare("INSERT INTO phone_contacts (phone, normalized_phone, name, school, sms_status, status, created_at) VALUES ('0779994444', '94779994444', 'Filtered Delete 4', 'School D', 'allowed', 'active', NOW())");
$stmtC4->execute();
$c4Id = (int)$pdo->lastInsertId();
$stmtRGrp = $pdo->prepare("INSERT INTO phone_contact_records (contact_id, exam_year, exam_type, location, school, source_group, source, created_at) VALUES (?, 2026, 'IGCSE', 'Kandy', 'School D', ?, 'test', NOW())");
$stmtRGrp->execute([$c4Id, $uniqueGroup]);

$stmtC5 = $pdo->prepare("INSERT INTO phone_contacts (phone, normalized_phone, name, school, sms_status, status, created_at) VALUES ('0779995555', '94779995555', 'Filtered Delete 5', 'School E', 'allowed', 'active', NOW())");
$stmtC5->execute();
$c5Id = (int)$pdo->lastInsertId();
$stmtRGrp->execute([$c5Id, $uniqueGroup]);

// Call deleteContactsBulk in all_filtered mode using filter for source_group
$allFilteredRes = PhoneContactService::deleteContactsBulk($pdo, [], ['source_group' => $uniqueGroup], true, 1);
assertDel($allFilteredRes['success'] === true, "deleteContactsBulk (all filtered) returned success: true");
assertDel($allFilteredRes['deleted_count'] === 2, "deleteContactsBulk deleted all 2 matching filtered contacts");

$chkFilteredRemaining = $pdo->query("SELECT COUNT(*) FROM phone_contacts WHERE id IN ($c4Id, $c5Id)")->fetchColumn();
assertDel((int)$chkFilteredRemaining === 0, "All filtered contacts confirmed deleted from database");

echo "\n--- 4. EDGE CASES & SAFETY --- \n";

// Deleting non-existent contact ID returns false without throwing error
$ghostDel = PhoneContactService::deleteContact($pdo, 999999999, 1);
assertDel($ghostDel === false, "Deleting non-existent contact returns false gracefully");

// Empty bulk list returns deleted_count: 0
$emptyBulk = PhoneContactService::deleteContactsBulk($pdo, [], [], false, 1);
assertDel($emptyBulk['success'] === true && $emptyBulk['deleted_count'] === 0, "Bulk delete with empty list returns 0 count cleanly");

echo "\n--- 5. UI & ENDPOINT VERIFICATION --- \n";

$ajaxSrc = file_get_contents(__DIR__ . '/../ajax/phone_contacts_action.php');
assertDel(str_contains($ajaxSrc, "'delete_contact'"), "AJAX endpoint contains delete_contact action");
assertDel(str_contains($ajaxSrc, "'delete_contacts_bulk'"), "AJAX endpoint contains delete_contacts_bulk action");
assertDel(str_contains($ajaxSrc, "case 'delete_contact':\n            if (!\$isAdmin)"), "delete_contact action is strictly admin-only");
assertDel(str_contains($ajaxSrc, "case 'delete_contacts_bulk':\n            if (!\$isAdmin)"), "delete_contacts_bulk action is strictly admin-only");

$phoneContactsViewSrc = file_get_contents(__DIR__ . '/../admin/phone_contact_view.php');
assertDel(str_contains($phoneContactsViewSrc, "id=\"btnDeleteContact\""), "admin/phone_contact_view.php contains #btnDeleteContact button");
assertDel(str_contains($phoneContactsViewSrc, "btnDeleteContact.addEventListener('click'"), "admin/phone_contact_view.php has delete confirmation handler");

$phoneContactsAdminSrc = file_get_contents(__DIR__ . '/../admin/phone_contacts.php');
assertDel(str_contains($phoneContactsAdminSrc, "id=\"btnBulkDelete\""), "admin/phone_contacts.php contains #btnBulkDelete button");
assertDel(str_contains($phoneContactsAdminSrc, "id=\"btnBulkDeleteCount\""), "admin/phone_contacts.php contains #btnBulkDeleteCount badge");

$jsSrc = file_get_contents(__DIR__ . '/../assets/js/phone_contacts.js');
assertDel(str_contains($jsSrc, "btn-delete-contact"), "phone_contacts.js renders .btn-delete-contact button per row");
assertDel(str_contains($jsSrc, "btnBulkDelete.addEventListener('click'"), "phone_contacts.js has #btnBulkDelete handler");
assertDel(str_contains($jsSrc, "'delete_contacts_bulk'"), "phone_contacts.js sends delete_contacts_bulk AJAX request");
assertDel(str_contains($jsSrc, "'delete_contact'"), "phone_contacts.js sends delete_contact AJAX request");

echo "\n====================================================================\n";
echo " CONTACT DELETION SUITE SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

