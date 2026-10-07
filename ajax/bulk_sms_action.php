<?php
declare(strict_types=1);

/**
 * AJAX Controller for Bulk SMS operations
 *
 * Handles file analysis, campaign initialization, batch processing,
 * progress polling, and failed recipient retries.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$fail = static function (string $error, int $http = 400): never {
    http_response_code($http);
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $fail('POST request required.', 405);
}

// Admin authorization check
require_admin();

// CSRF verification
$csrfToken = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrfToken)) {
    $fail('Invalid security token. Please refresh the page and try again.', 403);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$adminId = (int)($_SESSION['user_id'] ?? 0);

try {
    switch ($action) {
        case 'analyze':
            if (empty($_FILES['recipient_file']['tmp_name'])) {
                $fail('Please select a CSV or TXT file to upload.');
            }

            $file = $_FILES['recipient_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $fail('File upload error code: ' . $file['error']);
            }

            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                $fail('Uploaded file exceeds maximum allowed size of 10MB.');
            }

            $origName = (string)$file['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $fail('Unsupported file format. Please upload a .csv or .txt file.');
            }

            $message = (string)($_POST['message'] ?? '');

            // Process analysis
            $analysis = BulkSmsService::analyzeFile($file['tmp_name'], $origName, $pdo, $message);

            // Store analysis in session for campaign creation and validation CSV export
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['bulk_sms_last_analysis'] = [
                'filename'        => $origName,
                'records'         => $analysis['records'],
                'message'         => $message,
                'created_at'      => time(),
            ];

            // Strip the full records list from JSON response to keep payload light
            unset($analysis['records']);

            echo json_encode([
                'success'  => true,
                'analysis' => $analysis,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'create_campaign':
            if (empty($_SESSION['bulk_sms_last_analysis']['records'])) {
                $fail('No active file analysis found. Please upload and analyze a file first.');
            }

            $message = trim((string)($_POST['message'] ?? $_SESSION['bulk_sms_last_analysis']['message'] ?? ''));
            if ($message === '') {
                $fail('Message content cannot be empty.');
            }

            $excludePrev = isset($_POST['exclude_previously_sent']) && (string)$_POST['exclude_previously_sent'] === '1';
            $campaignName = trim((string)($_POST['campaign_name'] ?? ''));
            $filename = (string)($_SESSION['bulk_sms_last_analysis']['filename'] ?? 'uploaded_recipients.csv');
            $records = $_SESSION['bulk_sms_last_analysis']['records'];

            // Gateway selection & anti-tampering check
            $rawGateway = (string)($_POST['gateway'] ?? 'sms_gate_android');
            $gateway = SmsService::normalizeGatewayIdentifier($rawGateway);
            if ($gateway === '') {
                $gateway = 'sms_gate_android';
            }

            if ($gateway === 'ipromo') {
                $ipromoOn = function_exists('ipromo_enabled') && $pdo instanceof PDO ? ipromo_enabled($pdo) : false;
                if (!$ipromoOn) {
                    $fail('iPromo SMS Gateway is currently disabled in system settings. Sending rejected.', 403);
                }
            }

            $result = BulkSmsService::createCampaign(
                $pdo,
                $adminId,
                $filename,
                $message,
                $records,
                $excludePrev,
                $campaignName,
                $gateway
            );

            echo json_encode([
                'success'  => true,
                'campaign' => $result,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'process_batch':
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if ($campaignId < 1) {
                $fail('Invalid campaign ID.');
            }

            $batchSize = max(10, min(100, (int)($_POST['batch_size'] ?? 50)));
            $result = BulkSmsService::sendBatch($pdo, $campaignId, $batchSize, $adminId);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;

        case 'get_progress':
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if ($campaignId < 1) {
                $fail('Invalid campaign ID.');
            }

            $details = BulkSmsService::getCampaignDetails($pdo, $campaignId);
            if (!$details) {
                $fail('Campaign not found.');
            }

            $camp = $details['campaign'];
            $total = (int)$camp['recipient_count'];
            $sent = (int)$camp['sent_count'];
            $failed = (int)$camp['failed_count'];
            $skipped = (int)$camp['skipped_count'];
            $processed = $sent + $failed;
            $remaining = max(0, $total - $processed);
            $pct = $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 100;

            echo json_encode([
                'success'      => true,
                'campaign_id'  => $campaignId,
                'status'       => $camp['status'],
                'total'        => $total,
                'sent'         => $sent,
                'failed'       => $failed,
                'skipped'      => $skipped,
                'processed'    => $processed,
                'remaining'    => $remaining,
                'percent'      => $pct,
                'is_completed' => in_array($camp['status'], ['COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED', 'CANCELLED'], true),
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'retry_failed':
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if ($campaignId < 1) {
                $fail('Invalid campaign ID.');
            }

            $res = BulkSmsService::retryFailed($pdo, $campaignId, $adminId);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            exit;

        case 'search_recipients':
            $query = trim((string)($_POST['query'] ?? ''));
            if ($query === '') {
                $fail('Please enter a phone number or name to search.');
            }

            $results = BulkSmsService::searchRecipientHistory($pdo, $query, 50);
            echo json_encode([
                'success' => true,
                'results' => $results,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'get_failed_recipients':
            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if ($campaignId < 1) {
                $fail('Invalid campaign ID.');
            }

            $failed = BulkSmsService::getFailedRecipients($pdo, $campaignId, 100);
            echo json_encode([
                'success'           => true,
                'failed_recipients' => $failed,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'get_gateways':
            $gateways = SmsService::getAvailableGateways($pdo);
            echo json_encode([
                'success'  => true,
                'gateways' => $gateways,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            $fail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('ajax/bulk_sms_action.php error: ' . $e->getMessage());
    $fail($e->getMessage(), 500);
}
