<?php
/**
 * PhoneContactImportModalTest.php
 * Automated verification for CSV Import Modal workflow and endpoints.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;
use Edexcel\Services\TeacherSmsService;

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        echo " [FAIL] {$message}\n";
        exit(1);
    }
    echo " [PASS] {$message}\n";
}

echo "====================================================================\n";
echo "   CSV IMPORT WORKFLOW & ENDPOINTS TEST SUITE                      \n";
echo "====================================================================\n";

PhoneContactService::ensureSchema($pdo);

// 1. ANALYZE CSV VALIDATION
$csvContent = "phone_number,exam_year,exam_type,location,name,school\n"
    . "0771122334,2026,IGCSE,Kandy,Alice Fernando,High School\n"
    . "+94 71 223 3445,2027,IAL,Kurunegala,Bob Silva,Royal College\n"
    . "0112345678,2026,IGCSE,Kandy,Landline Invalid,Test School\n"; // invalid landline

$tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
file_put_contents($tmpFile, $csvContent);

$analysis = PhoneContactService::analyzeCsvFile($tmpFile, 'test_batch.csv', $pdo, '2026_IGCSE_Modal_Test');

assertTest($analysis['total_rows'] === 3, "Analyze CSV: 3 total rows detected");
assertTest($analysis['valid_rows'] === 2, "Analyze CSV: 2 valid rows detected");
assertTest($analysis['invalid_rows'] === 1, "Analyze CSV: 1 invalid row detected (landline)");
assertTest(count($analysis['sample_preview']) === 2, "Analyze CSV: Sample preview contains valid rows");
assertTest($analysis['source_group'] === '2026_IGCSE_Modal_Test', "Analyze CSV: Source group captured");

// 2. EXECUTE IMPORT
$importResult = PhoneContactService::executeImport(
    $pdo,
    1,
    'test_batch.csv',
    '2026_IGCSE_Modal_Test',
    $analysis['valid_records'],
    $analysis['invalid_rows_list']
);

assertTest($importResult['new_contacts'] >= 1, "Execute Import: Created new contacts");
assertTest($importResult['new_records'] >= 1, "Execute Import: Created new academic records");
assertTest($importResult['invalid_rows'] === 1, "Execute Import: Recorded invalid rows count");
assertTest(!empty($importResult['error_csv_path']), "Execute Import: Generated protected error CSV path");

// 3. RE-IMPORT DUPLICATE HANDLING (RE-UPLOAD / SECOND RUN)
$importResult2 = PhoneContactService::executeImport(
    $pdo,
    1,
    'test_batch.csv',
    '2026_IGCSE_Modal_Test',
    $analysis['valid_records'],
    []
);
assertTest($importResult2['duplicate_records'] >= 2, "Execute Import (Second run): Skipped duplicates cleanly");
assertTest($importResult2['new_contacts'] === 0, "Execute Import (Second run): 0 duplicate contacts created");

// 4. CLEANUP TEST DATA
@unlink($tmpFile);
$pdo->exec("DELETE FROM phone_contact_records WHERE source_group = '2026_IGCSE_Modal_Test'");
$pdo->exec("DELETE FROM phone_contacts WHERE phone IN ('0771122334', '0712233445')");
$pdo->exec("DELETE FROM phone_import_history WHERE source_group = '2026_IGCSE_Modal_Test'");

echo "====================================================================\n";
echo " IMPORT MODAL ENDPOINTS TEST SUITE: ALL PASSED                      \n";
echo "====================================================================\n";
