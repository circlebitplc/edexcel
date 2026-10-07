<?php
declare(strict_types=1);

/**
 * Admin Phone Contacts CSV Exporter
 *
 * Streams filtered phone contacts as CSV with formula injection prevention.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;

require_admin();

PhoneContactService::ensureSchema($pdo);

$filters = [
    'search'       => trim((string)($_GET['search'] ?? '')),
    'exam_year'    => trim((string)($_GET['exam_year'] ?? '')),
    'exam_type'    => trim((string)($_GET['exam_type'] ?? '')),
    'location'     => trim((string)($_GET['location'] ?? '')),
    'school'       => trim((string)($_GET['school'] ?? '')),
    'source_group' => trim((string)($_GET['source_group'] ?? '')),
    'sms_opt_out'  => trim((string)($_GET['sms_opt_out'] ?? '')),
    'status'       => trim((string)($_GET['status'] ?? '')),
];

$selectedIds = [];
if (!empty($_GET['ids'])) {
    $selectedIds = array_map('intval', explode(',', (string)$_GET['ids']));
}

$csv = PhoneContactService::exportContactsCsv($pdo, $filters, $selectedIds);

$filename = 'phone_contacts_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";
echo $csv;
exit;
