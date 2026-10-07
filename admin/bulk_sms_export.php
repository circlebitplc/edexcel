<?php
declare(strict_types=1);

/**
 * Secure CSV export handler for Bulk SMS campaigns and validation reports.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BulkSmsService;

require_admin();

$type = strtolower(trim((string)($_GET['export'] ?? '')));

if ($type === 'validation') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $analysis = $_SESSION['bulk_sms_last_analysis'] ?? null;
    if (empty($analysis['records'])) {
        http_response_code(400);
        die('No validation report available to export.');
    }

    BulkSmsService::exportValidationCsv($analysis['records'], (string)($analysis['filename'] ?? 'recipients'));
    exit;
}

if ($type === 'campaign') {
    $campaignId = (int)($_GET['id'] ?? 0);
    if ($campaignId < 1) {
        http_response_code(400);
        die('Invalid campaign ID.');
    }

    BulkSmsService::exportCampaignCsv($pdo, $campaignId);
    exit;
}

http_response_code(400);
die('Invalid export request.');
