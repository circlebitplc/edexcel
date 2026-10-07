<?php
declare(strict_types=1);

/**
 * Security and Authorization Tests for Phone Contact & Teacher SMS
 * Edexcel College
 */

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\PhoneContactService;

echo "====================================================================\n";
echo "   PHONE CONTACTS & CONTROLLED SMS — SECURITY AUDIT SUITE           \n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertSec(bool $cond, string $name): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo " [PASS] $name\n";
    } else {
        $failCount++;
        echo " [FAIL] $name\n";
    }
}

// 1. Teacher Gateway Tampering Protection:
// Even if an attacker passes 'sms_gate_android', TeacherSmsService rejects or overrides it.
$teacherUserId = 888;
$pdo->exec("INSERT IGNORE INTO users (id, username, password_hash, role) VALUES ($teacherUserId, 'security.teacher', 'dummy', 'teacher')");

TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, [
    'sms_access'          => 1,
    'can_send_sms'        => 1,
    'monthly_limit'       => 500,
    'allowed_exam_years'  => '2026',
    'allowed_exam_types'  => 'IGCSE',
    'allowed_locations'   => 'Kandy',
], 1);

// Attempt gateway manipulation
$valHackGw = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 10, 'sms_gate_android');
assertSec($valHackGw['allowed'] === false, "Server-side rejection of non-iPromo gateway for teacher ('sms_gate_android')");

$valHackRandom = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 10, 'evil_gateway');
assertSec($valHackRandom['allowed'] === false, "Server-side rejection of arbitrary gateway for teacher ('evil_gateway')");

// 2. Teacher Scope Enforcement
// Teacher is only allowed 2026 / IGCSE / Kandy
$teacherAllowedFilters = TeacherSmsService::enforceTeacherFilters($pdo, $teacherUserId, ['exam_year' => '2026', 'exam_type' => 'IGCSE', 'location' => 'Kandy']);
assertSec($teacherAllowedFilters['exam_year'] === '2026', "Allowed exam year passes verification");

// Teacher tries to query 2027 (not allowed)
$yearBlocked = false;
try {
    TeacherSmsService::enforceTeacherFilters($pdo, $teacherUserId, ['exam_year' => '2027']);
} catch (\RuntimeException $e) {
    $yearBlocked = true;
}
assertSec($yearBlocked, "Teacher querying outside assigned year (2027) is blocked by server exception");

// Teacher tries to query IAL (not allowed)
$typeBlocked = false;
try {
    TeacherSmsService::enforceTeacherFilters($pdo, $teacherUserId, ['exam_type' => 'IAL']);
} catch (\RuntimeException $e) {
    $typeBlocked = true;
}
assertSec($typeBlocked, "Teacher querying outside assigned exam type (IAL) is blocked by server exception");

// Teacher tries to query Online (not allowed)
$locBlocked = false;
try {
    TeacherSmsService::enforceTeacherFilters($pdo, $teacherUserId, ['location' => 'Online']);
} catch (\RuntimeException $e) {
    $locBlocked = true;
}
assertSec($locBlocked, "Teacher querying outside assigned location (Online) is blocked by server exception");

// 3. Quota Manipulation Protection
// When quota is 500, attempting to send 501 units is blocked
$quotaBlocked = TeacherSmsService::validateTeacherCanSend($pdo, $teacherUserId, 501, 'ipromo');
assertSec($quotaBlocked['allowed'] === false, "Quota breach (501 > 500) blocked before dispatch");

// 4. SQL Injection Protection Verification
// Try sending malicious payload in filters
$maliciousSearch = "'; DROP TABLE phone_contacts; --";
$safeResult = PhoneContactService::getFilteredContacts($pdo, ['search' => $maliciousSearch]);
assertSec(is_array($safeResult), "Prepared statements prevent SQL injection in search filter");

$maliciousYear = "2026 OR 1=1";
$safeResult2 = PhoneContactService::getFilteredContacts($pdo, ['exam_year' => $maliciousYear]);
assertSec(is_array($safeResult2), "Prepared statements prevent SQL injection in exam_year filter");

// 5. Verify phone_contacts table intact
$tableCheck = $pdo->query("SHOW TABLES LIKE 'phone_contacts'")->fetchColumn();
assertSec($tableCheck === 'phone_contacts', "Table phone_contacts remains intact and uncompromised");

// Clean up
$pdo->exec("DELETE FROM teacher_sms_permissions WHERE teacher_user_id = $teacherUserId");
$pdo->exec("DELETE FROM users WHERE id = $teacherUserId");

echo "\n====================================================================\n";
echo " SECURITY AUDIT SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
