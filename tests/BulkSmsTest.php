<?php
declare(strict_types=1);

require_once 'd:/all/server_new/edexcel.college/public_html/config/load_env.php';
require_once 'd:/all/server_new/edexcel.college/public_html/config/database.php';
require_once 'd:/all/server_new/edexcel.college/public_html/vendor/autoload.php';
require_once 'd:/all/server_new/edexcel.college/public_html/config/sms_gateway.php';

use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;

echo "========================================================\n";
echo "    EDEXCEL COLLEGE — BULK SMS AUTOMATED TEST SUITE    \n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $testName\n";
    } else {
        $failCount++;
        echo " [FAIL] $testName\n";
    }
}

// ─────────────────────────────────────────────────────────────
// 1. GSM & Unicode Segment Calculation Tests
// ─────────────────────────────────────────────────────────────
echo "--- 1. SMS SEGMENTS & ENCODING RULES ---\n";

$shortGsm = "Dear Student, your class is scheduled for tomorrow at Edexcel College.";
$calc1 = BulkSmsService::calculateSmsUnits($shortGsm);
assertTest($calc1['encoding'] === 'GSM-7' && $calc1['segments'] === 1, "Short English text detects GSM-7 with 1 segment");

$longGsm = str_repeat("Hello Edexcel ", 15); // ~210 chars
$calc2 = BulkSmsService::calculateSmsUnits($longGsm);
assertTest($calc2['encoding'] === 'GSM-7' && $calc2['segments'] === 2, "Long English text (210 chars) detects GSM-7 with 2 segments");

$unicodeMsg = "ආයුබෝවන් සිසුවා! Edexcel College"; // Sinhala
$calc3 = BulkSmsService::calculateSmsUnits($unicodeMsg);
assertTest($calc3['encoding'] === 'Unicode' && $calc3['segments'] === 1, "Sinhala text detects Unicode encoding");

$longUnicode = str_repeat("පරීක්ෂණය ", 12); // ~96 chars
$calc4 = BulkSmsService::calculateSmsUnits($longUnicode);
assertTest($calc4['encoding'] === 'Unicode' && $calc4['segments'] === 2, "Long Unicode text (>70 chars) calculates multi-part segments");

// ─────────────────────────────────────────────────────────────
// 2. CSV Formula Injection Sanitization Tests
// ─────────────────────────────────────────────────────────────
echo "\n--- 2. CSV FORMULA INJECTION PROTECTION ---\n";

$dangerous1 = "=1+1";
$dangerous2 = "+cmd|' /C calc'!A0";
$dangerous3 = "@SUM(A1:A10)";
$safeText = "John Doe";

assertTest(BulkSmsService::sanitizeCsvCell($dangerous1) === "'=1+1", "Cell starting with '=' is prepended with single quote");
assertTest(BulkSmsService::sanitizeCsvCell($dangerous2) === "'+cmd|' /C calc'!A0", "Cell starting with '+' is prepended with single quote");
assertTest(BulkSmsService::sanitizeCsvCell($dangerous3) === "'@SUM(A1:A10)", "Cell starting with '@' is prepended with single quote");
assertTest(BulkSmsService::sanitizeCsvCell($safeText) === "John Doe", "Normal text remains unescaped");

// ─────────────────────────────────────────────────────────────
// 3. File Processing Tests (CSV with headers, without, and TXT)
// ─────────────────────────────────────────────────────────────
echo "\n--- 3. FILE PARSING & RECIPIENT EXTRACTION ---\n";

$tempDir = 'C:/Users/Batta Sir/.gemini/antigravity/brain/14368695-36b2-4e3c-a8e4-490ae036e9b5/scratch';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// 3.1 CSV with standard headers
$csvContent1 = "name,phone,notes\nJohn,0771234567,Class A\nPeter,779876543,Class B\nKamal,+94712345678,Class C\nDuplicate,0771234567,Duplicate row\nInvalid,0112345678,Landline\nMalformed,not-a-number,Bad row\n";
$csvFile1 = $tempDir . '/test_recipients_1.csv';
file_put_contents($csvFile1, $csvContent1);

$analysis1 = BulkSmsService::analyzeFile($csvFile1, 'test_recipients_1.csv', $pdo, 'Test Message 1');
assertTest($analysis1['total_records'] === 7, "Total rows parsed correctly (7 rows including header)");
assertTest($analysis1['valid_records'] === 3, "Valid distinct numbers identified (3 valid: 0771234567, 779876543, +94712345678)");
assertTest($analysis1['duplicate_records'] === 1, "Duplicate number within file identified (1 duplicate)");
assertTest($analysis1['invalid_records'] === 2, "Invalid numbers caught (2 invalid: landline and letters)");

// 3.2 TXT File with 1 number per line
$txtContent = "0771234567\n0765551234\n0788889999\n";
$txtFile = $tempDir . '/test_recipients.txt';
file_put_contents($txtFile, $txtContent);

$analysis2 = BulkSmsService::analyzeFile($txtFile, 'test_recipients.txt', $pdo, 'Test Message 2');
assertTest($analysis2['valid_records'] === 3, "TXT file with 1 phone per line parsed successfully (3 valid)");

// ─────────────────────────────────────────────────────────────
// 4. Duplicate Prevention (Phone + Message Fingerprinting)
// ─────────────────────────────────────────────────────────────
echo "\n--- 4. DUPLICATE-SENDING PREVENTION ---\n";

$testMsg = "Important Notification: School starts at 8:00 AM on Monday.";
$normMsg = BulkSmsService::normalizeMessage($testMsg);

// Create a test campaign with phone 0771234567
$testRecords = [
    [
        'row_number'          => 1,
        'original_phone'      => '0771234567',
        'normalized_phone'    => '94771234567',
        'name'                => 'Student A',
        'status'              => 'VALID',
        'is_valid'            => true,
        'is_duplicate'        => false,
        'is_previously_sent'  => false,
    ],
    [
        'row_number'          => 2,
        'original_phone'      => '0719998888',
        'normalized_phone'    => '94719998888',
        'name'                => 'Student B',
        'status'              => 'VALID',
        'is_valid'            => true,
        'is_duplicate'        => false,
        'is_previously_sent'  => false,
    ],
];

$campResult = BulkSmsService::createCampaign($pdo, 1, 'test.csv', $testMsg, $testRecords, false, 'Test Campaign');
$campId = $campResult['campaign_id'];

assertTest($campId > 0, "Campaign created successfully (ID: $campId)");

// Mark 94771234567 as SENT in database
$pdo->prepare("UPDATE bulk_sms_recipients SET status = 'SENT', sent_at = NOW() WHERE campaign_id = ? AND phone_number = '94771234567'")
    ->execute([$campId]);

// Now test analysis with the SAME phone and SAME message: must be flagged as PREVIOUSLY_SENT
$csvRepeatSame = "phone\n0771234567\n";
$repeatFile = $tempDir . '/repeat_same.csv';
file_put_contents($repeatFile, $csvRepeatSame);

$analysisRepeat = BulkSmsService::analyzeFile($repeatFile, 'repeat_same.csv', $pdo, $testMsg);
assertTest($analysisRepeat['previously_sent_records'] === 1, "Same phone + SAME message detected as PREVIOUSLY_SENT (blocked)");
assertTest($analysisRepeat['new_recipients'] === 0, "New recipients is 0 when all recipients already received identical message");

// Now test analysis with the SAME phone and a DIFFERENT message: must be ALLOWED
$diffMsg = "Different Notification: Examination results are published.";
$analysisDiff = BulkSmsService::analyzeFile($repeatFile, 'repeat_same.csv', $pdo, $diffMsg);
assertTest($analysisDiff['previously_sent_records'] === 0, "Same phone + DIFFERENT message is ALLOWED (not blocked)");
assertTest($analysisDiff['new_recipients'] === 1, "New recipients is 1 for legitimate new message to same phone");

// Test DIFFERENT phone + SAME message: must be ALLOWED
$diffPhoneCsv = "phone\n0767778899\n";
$diffPhoneFile = $tempDir . '/diff_phone.csv';
file_put_contents($diffPhoneFile, $diffPhoneCsv);
$analysisDiffPhone = BulkSmsService::analyzeFile($diffPhoneFile, 'diff_phone.csv', $pdo, $testMsg);
assertTest($analysisDiffPhone['previously_sent_records'] === 0, "Different phone + SAME message is ALLOWED");

// ─────────────────────────────────────────────────────────────
// 5. Batch Processing & Resumability Tests
// ─────────────────────────────────────────────────────────────
echo "\n--- 5. BATCH SENDING & RETRY LOGIC ---\n";

// Batch processing test on the remaining PENDING recipient (Student B)
$batchResult = BulkSmsService::sendBatch($pdo, $campId, 10, 1);
assertTest($batchResult['success'] === true, "sendBatch executes successfully");
assertTest($batchResult['is_completed'] === true, "Campaign marks is_completed when no PENDING recipients remain");

// Test retry failed
$pdo->prepare("UPDATE bulk_sms_recipients SET status = 'FAILED', error_message = 'Simulated timeout' WHERE campaign_id = ? AND phone_number = '94719998888'")
    ->execute([$campId]);
$retryRes = BulkSmsService::retryFailed($pdo, $campId, 1);
assertTest($retryRes['success'] === true && $retryRes['retried_count'] === 1, "retryFailed resets FAILED recipients back to PENDING");

// ─────────────────────────────────────────────────────────────
// 6. Clean Up Test Data
// ─────────────────────────────────────────────────────────────
$pdo->prepare("DELETE FROM bulk_sms_campaigns WHERE id = ?")->execute([$campId]);

// Delete temp files
@unlink($csvFile1);
@unlink($txtFile);
@unlink($repeatFile);
@unlink($diffPhoneFile);

echo "\n========================================================\n";
echo " TEST SUITE SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "========================================================\n";
