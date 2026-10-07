<?php
declare(strict_types=1);

/**
 * AJAX Controller for Phone Contact Management and Controlled SMS
 *
 * Handles CSV upload analysis, transactional import, live filtering,
 * contact & academic record modifications, recipient deduplication,
 * SMS campaign dispatching, and teacher permissions.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;
use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$fail = static function (string $error, int $http = 400, array $extra = []): never {
    http_response_code($http);
    echo json_encode(array_merge(['success' => false, 'error' => $error], $extra), JSON_UNESCAPED_UNICODE);
    exit;
};

// Check basic authentication
if (!is_logged_in()) {
    $fail('Authentication required. Please log in.', 401);
}

$isAdmin = is_admin();
$isTeacher = is_teacher();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$isTeacher) {
    $fail('Access denied.', 403);
}

// CSRF verification for POST requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($csrfToken)) {
        $fail('Invalid or expired security token. Please refresh the page and try again.', 403);
    }
}

$action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? $_REQUEST['action'] ?? '')));

PhoneContactService::ensureSchema($pdo);
TeacherSmsService::ensureSchema($pdo);
BulkSmsService::ensureSchema($pdo);

try {
    switch ($action) {
        // ─────────────────────────────────────────────────────────────
        // 1. ANALYZE CSV FILE (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'analyze_csv':
            if (!$isAdmin) {
                $fail('Administrator access required to import contacts.', 403);
            }

            if (empty($_FILES['csv_file']['tmp_name'])) {
                $fail('Please select a CSV file to upload.');
            }

            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $fail('File upload error code: ' . $file['error']);
            }

            $maxSize = 15 * 1024 * 1024; // 15MB
            if ($file['size'] > $maxSize) {
                $fail('Uploaded file exceeds maximum allowed size of 15MB.');
            }

            $origName = (string)$file['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $fail('Unsupported file format. Please upload a .csv file.');
            }

            $sourceGroup = trim((string)($_POST['source_group'] ?? ''));

            $analysis = PhoneContactService::analyzeCsvFile($file['tmp_name'], $origName, $pdo, $sourceGroup);

            // Store analysis in session for confirmation
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['phone_contact_last_analysis'] = [
                'filename'          => $analysis['filename'],
                'source_group'      => $analysis['source_group'],
                'valid_records'     => $analysis['valid_records'],
                'invalid_rows_list' => $analysis['invalid_rows_list'],
                'created_at'        => time(),
            ];

            // Exclude large arrays from client response
            $clientAnalysis = $analysis;
            unset($clientAnalysis['valid_records']);

            echo json_encode([
                'success'  => true,
                'analysis' => $clientAnalysis,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 2. EXECUTE IMPORT (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'execute_import':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            if (empty($_SESSION['phone_contact_last_analysis']['valid_records'])) {
                if (!empty($_FILES['csv_file']['tmp_name']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['csv_file'];
                    $origName = (string)$file['name'];
                    $sourceGroup = trim((string)($_POST['source_group'] ?? ''));
                    $analysis = PhoneContactService::analyzeCsvFile($file['tmp_name'], $origName, $pdo, $sourceGroup);
                    $filename = (string)$analysis['filename'];
                    $sourceGroup = (string)$analysis['source_group'];
                    $validRecords = (array)$analysis['valid_records'];
                    $invalidRows = (array)$analysis['invalid_rows_list'];
                } else {
                    $fail('No active CSV analysis found. Please upload and inspect a CSV first.');
                }
            } else {
                $cached = $_SESSION['phone_contact_last_analysis'];
                $filename = (string)$cached['filename'];
                $sourceGroup = trim((string)($_POST['source_group'] ?? $cached['source_group'] ?? ''));
                $validRecords = (array)$cached['valid_records'];
                $invalidRows = (array)$cached['invalid_rows_list'];
            }

            $importResult = PhoneContactService::executeImport(
                $pdo,
                $currentUserId,
                $filename,
                $sourceGroup,
                $validRecords,
                $invalidRows
            );

            // Clear session analysis
            unset($_SESSION['phone_contact_last_analysis']);

            echo json_encode([
                'success' => true,
                'report'  => $importResult,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 3. CANCEL IMPORT
        // ─────────────────────────────────────────────────────────────
        case 'cancel_import':
            unset($_SESSION['phone_contact_last_analysis']);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 3b. MATCH STUDENT NAMES (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'match_student_names':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            if (empty($_SESSION['phone_contact_last_analysis']['valid_records'])) {
                $fail('No active CSV preview session found. Please upload and inspect a CSV first.');
            }

            $cached = &$_SESSION['phone_contact_last_analysis'];
            $validRecords = (array)$cached['valid_records'];

            $matchingAnalysis = PhoneContactService::analyzeStudentMatchingForPreview($pdo, $validRecords);
            $summary = $matchingAnalysis['summary'];
            $proposedUpdates = $matchingAnalysis['proposed_existing_updates'];

            // Fast single student lookup to enrich blank CSV names in session valid_records
            $singleStudentMap = [];
            $allNorms = array_values(array_unique(array_filter(array_column($validRecords, 'normalized_phone'))));
            $rawMatches = PhoneContactService::matchStudentsByPhones($pdo, $allNorms);
            foreach ($rawMatches as $norm => $mList) {
                if (count($mList) === 1) {
                    $singleStudentMap[$norm] = $mList[0]['student_name'];
                }
            }

            $enrichedNewCount = 0;
            foreach ($cached['valid_records'] as &$vRec) {
                $norm = (string)($vRec['normalized_phone'] ?? '');
                $currName = trim((string)($vRec['name'] ?? ''));
                if ($currName === '' && isset($singleStudentMap[$norm])) {
                    $vRec['name'] = $singleStudentMap[$norm];
                    $vRec['student_matched'] = true;
                    $enrichedNewCount++;
                }
            }
            unset($vRec);

            $cached['proposed_student_updates'] = $proposedUpdates;

            echo json_encode([
                'success'                   => true,
                'summary'                   => $summary,
                'sample_matches'            => $matchingAnalysis['sample_matches'],
                'proposed_existing_updates' => $proposedUpdates,
                'enriched_in_csv'           => $enrichedNewCount,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 3c. APPLY STUDENT NAME UPDATES (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'apply_student_name_updates':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $updates = [];
            if (!empty($_POST['updates']) && is_string($_POST['updates'])) {
                $decoded = json_decode((string)$_POST['updates'], true);
                if (is_array($decoded)) {
                    $updates = $decoded;
                }
            } elseif (!empty($_SESSION['phone_contact_last_analysis']['proposed_student_updates'])) {
                $updates = (array)$_SESSION['phone_contact_last_analysis']['proposed_student_updates'];
            }

            if ($updates === []) {
                $fail('No pending student name updates found.');
            }

            $updateResult = PhoneContactService::applyStudentNameUpdates($pdo, $currentUserId, $updates);

            if (isset($_SESSION['phone_contact_last_analysis']['proposed_student_updates'])) {
                unset($_SESSION['phone_contact_last_analysis']['proposed_student_updates']);
            }

            echo json_encode([
                'success'        => true,
                'updated_count'  => $updateResult['updated_count'],
                'errors'         => $updateResult['errors'],
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 4. LOAD FILTERED CONTACTS (SERVER-SIDE)
        // ─────────────────────────────────────────────────────────────
        case 'load_contacts':
            // Teacher permission check: can_view_contacts must be 1 if teacher
            if ($isTeacher) {
                $perm = TeacherSmsService::getTeacherPermissions($pdo, $currentUserId);
                if (!$perm || empty($perm['can_view_contacts'])) {
                    $fail('Access denied: You do not have permission to view contact lists.', 403);
                }
            }

            $page = max(1, (int)($_REQUEST['page'] ?? 1));
            $perPage = max(10, min(200, (int)($_REQUEST['per_page'] ?? 50)));

            $filters = [
                'search'       => trim((string)($_REQUEST['search'] ?? '')),
                'exam_year'    => trim((string)($_REQUEST['exam_year'] ?? '')),
                'exam_type'    => trim((string)($_REQUEST['exam_type'] ?? '')),
                'location'     => trim((string)($_REQUEST['location'] ?? '')),
                'school'       => trim((string)($_REQUEST['school'] ?? '')),
                'source_group' => trim((string)($_REQUEST['source_group'] ?? '')),
                'sms_opt_out'  => trim((string)($_REQUEST['sms_opt_out'] ?? '')),
                'status'       => trim((string)($_REQUEST['status'] ?? '')),
            ];

            // Teacher scope restrictions
            if ($isTeacher) {
                $filters = TeacherSmsService::enforceTeacherFilters($pdo, $currentUserId, $filters);
            }

            $data = PhoneContactService::getFilteredContacts($pdo, $filters, $page, $perPage);

            echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 5. GET CONTACT DETAILS (VIEW / EDIT MODAL)
        // ─────────────────────────────────────────────────────────────
        case 'get_contact':
            $contactId = (int)($_REQUEST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            if ($isTeacher) {
                $perm = TeacherSmsService::getTeacherPermissions($pdo, $currentUserId);
                if (!$perm || empty($perm['can_view_contacts'])) {
                    $fail('Access denied.', 403);
                }
            }

            $details = PhoneContactService::getContactDetails($pdo, $contactId);
            if (!$details) {
                $fail('Contact not found.', 404);
            }

            echo json_encode([
                'success' => true,
                'contact' => $details,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 6. UPDATE MAIN CONTACT
        // ─────────────────────────────────────────────────────────────
        case 'update_contact':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $contactId = (int)($_POST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            $res = PhoneContactService::updateContact($pdo, $contactId, [
                'name'        => $_POST['name'] ?? null,
                'phone'       => $_POST['phone'] ?? '',
                'school'      => $_POST['school'] ?? null,
                'sms_opt_out' => isset($_POST['sms_opt_out']) && (string)$_POST['sms_opt_out'] === '1' ? 1 : 0,
                'sms_status'  => $_POST['sms_status'] ?? 'allowed',
                'status'      => $_POST['status'] ?? 'active',
                'notes'       => $_POST['notes'] ?? null,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Contact updated successfully.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 7. ADD ACADEMIC RECORD
        // ─────────────────────────────────────────────────────────────
        case 'add_academic_record':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $contactId = (int)($_POST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            $res = PhoneContactService::addAcademicRecord($pdo, $contactId, [
                'exam_year'    => (int)($_POST['exam_year'] ?? 0),
                'exam_type'    => (string)($_POST['exam_type'] ?? ''),
                'location'     => (string)($_POST['location'] ?? ''),
                'school'       => (string)($_POST['school'] ?? ''),
                'source_group' => (string)($_POST['source_group'] ?? ''),
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Academic record added.',
                'record_id' => $res['id'],
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 8. UPDATE ACADEMIC RECORD
        // ─────────────────────────────────────────────────────────────
        case 'update_academic_record':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $recordId = (int)($_POST['record_id'] ?? 0);
            if ($recordId < 1) {
                $fail('Invalid record ID.');
            }

            PhoneContactService::updateAcademicRecord($pdo, $recordId, [
                'exam_year'    => (int)($_POST['exam_year'] ?? 0),
                'exam_type'    => (string)($_POST['exam_type'] ?? ''),
                'location'     => (string)($_POST['location'] ?? ''),
                'school'       => (string)($_POST['school'] ?? ''),
                'source_group' => (string)($_POST['source_group'] ?? ''),
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Academic record updated.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 9. DELETE ACADEMIC RECORD
        // ─────────────────────────────────────────────────────────────
        case 'delete_academic_record':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $recordId = (int)($_POST['record_id'] ?? 0);
            if ($recordId < 1) {
                $fail('Invalid record ID.');
            }

            PhoneContactService::deleteAcademicRecord($pdo, $recordId);

            echo json_encode([
                'success' => true,
                'message' => 'Academic record deleted.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 10. CALCULATE SMS RECIPIENTS & UNITS (PREVIEW)
        // ─────────────────────────────────────────────────────────────
        case 'calculate_sms':
            $message = trim((string)($_POST['message'] ?? ''));
            $filtersJson = (string)($_POST['filters'] ?? '{}');
            $filters = json_decode($filtersJson, true) ?: [];
            $selectedIds = !empty($_POST['selected_ids']) ? (array)$_POST['selected_ids'] : [];

            // Teachers check
            if ($isTeacher) {
                $perm = TeacherSmsService::getTeacherPermissions($pdo, $currentUserId);
                if (!$perm || empty($perm['sms_access']) || empty($perm['can_send_sms'])) {
                    $fail('You do not have SMS sending privileges.', 403);
                }
                $filters = TeacherSmsService::enforceTeacherFilters($pdo, $currentUserId, $filters);
            }

            $recipients = PhoneContactService::getUniqueRecipients($pdo, $filters, $selectedIds, true);
            $uniqueCount = count($recipients);

            $unitsCalc = BulkSmsService::calculateSmsUnits($message);
            $segments = $unitsCalc['segments'];
            $totalUnits = $uniqueCount * $segments;

            // Exclusion breakdown (total, eligible, blocked, opted_out)
            $breakdown = PhoneContactService::getRecipientExclusionBreakdown($pdo, $filters, $selectedIds);

            // Recent campaign duplicate warning (configurable days)
            $warnDays = 7;
            try {
                $stmtWD = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'campaign_duplicate_warning_days' LIMIT 1");
                $valWD = $stmtWD->fetchColumn();
                if ($valWD !== false && is_numeric($valWD)) {
                    $warnDays = max(1, (int)$valWD);
                }
            } catch (Throwable) {}

            $duplicateWarning = PhoneContactService::checkRecentCampaignDuplicates($pdo, array_keys($recipients), $warnDays);

            $teacherQuota = null;
            if ($isTeacher) {
                $usage = TeacherSmsService::getTeacherMonthlyUsage($pdo, $currentUserId);
                $teacherQuota = [
                    'limit'       => $usage['monthly_limit'],
                    'used'        => $usage['used_units'],
                    'remaining'   => $usage['remaining_units'],
                    'is_exceeded' => $totalUnits > $usage['remaining_units'],
                ];
            }

            echo json_encode([
                'success'             => true,
                'unique_recipients'   => $uniqueCount,
                'characters'          => $unitsCalc['length'],
                'encoding'            => $unitsCalc['encoding'],
                'segments'            => $segments,
                'total_units'         => $totalUnits,
                'exclusion_breakdown' => $breakdown,
                'duplicate_warning'   => $duplicateWarning,
                'teacher_quota'       => $teacherQuota,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 11. SEND CONTROLLED SMS CAMPAIGN
        // ─────────────────────────────────────────────────────────────
        case 'send_sms_campaign':
            $message = trim((string)($_POST['message'] ?? ''));
            if ($message === '') {
                $fail('SMS message content cannot be empty.');
            }

            $requestedGateway = trim((string)($_POST['gateway'] ?? ''));
            $campaignName = trim((string)($_POST['campaign_name'] ?? ''));
            $filtersJson = (string)($_POST['filters'] ?? '{}');
            $filters = json_decode($filtersJson, true) ?: [];
            $selectedIds = !empty($_POST['selected_ids']) ? (array)$_POST['selected_ids'] : [];

            // TEACHER SECURITY CHECKS (CRITICAL)
            if ($isTeacher) {
                // Strict rejection if teacher submitted non-ipromo gateway
                $rawTeacherGw = strtolower(trim((string)($_POST['gateway'] ?? '')));
                if ($rawTeacherGw !== '' && $rawTeacherGw !== 'ipromo') {
                    $fail('Security violation: Teachers are strictly restricted to the ipromo gateway only.', 403);
                }
                $requestedGateway = 'ipromo';

                // Enforce scope
                $filters = TeacherSmsService::enforceTeacherFilters($pdo, $currentUserId, $filters);
            }

            // Deduplicate recipients
            $recipients = PhoneContactService::getUniqueRecipients($pdo, $filters, $selectedIds, true);
            $uniqueCount = count($recipients);

            if ($uniqueCount === 0) {
                $fail('No active recipients match the selected criteria (or all selected contacts have opted out / been archived).');
            }

            $unitsCalc = BulkSmsService::calculateSmsUnits($message);
            $segments = $unitsCalc['segments'];
            $totalUnits = $uniqueCount * $segments;

            // Optional idempotency key from header or POST
            $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));

            // Transform into analyzedRecords format expected by BulkSmsService
            $analyzedRecords = [];
            foreach ($recipients as $norm => $item) {
                $analyzedRecords[] = [
                    'phone'        => $norm,
                    'name'         => $item['name'] !== 'Student' ? $item['name'] : '',
                    'raw_phone'    => $item['display_phone'],
                    'is_valid'     => true,
                    'is_duplicate' => false,
                    'errors'       => [],
                ];
            }

            if ($campaignName === '') {
                $prefix = $isTeacher ? 'Teacher Campaign ' : 'Contact Campaign ';
                $campaignName = $prefix . date('Y-m-d H:i');
            }

            // Teacher Quota & Permission Verification (with Atomic Row-Level Locking)
            if ($isTeacher) {
                $pdo->beginTransaction();
                try {
                    // Row lock on permissions table to eliminate concurrent race conditions
                    $stmtLock = $pdo->prepare("SELECT id, monthly_limit, sms_access, can_send_sms FROM teacher_sms_permissions WHERE teacher_user_id = ? FOR UPDATE");
                    $stmtLock->execute([$currentUserId]);
                    $lockRow = $stmtLock->fetch(PDO::FETCH_ASSOC);

                    if (!$lockRow || empty($lockRow['sms_access']) || empty($lockRow['can_send_sms'])) {
                        $pdo->rollBack();
                        $fail('You do not have permission to send SMS broadcasts.', 403);
                    }

                    $val = TeacherSmsService::validateTeacherCanSend($pdo, $currentUserId, $totalUnits, $requestedGateway);
                    if (!$val['allowed']) {
                        $pdo->rollBack();
                        $fail($val['error'] ?? 'Teacher SMS validation failed.', 403, [
                            'remaining' => $val['remaining_units'] ?? 0,
                            'required'  => $totalUnits,
                        ]);
                    }

                    // Create Campaign atomically under the transaction
                    $campaign = BulkSmsService::createCampaign(
                        $pdo,
                        $currentUserId,
                        'phone_contacts_filter',
                        $message,
                        $analyzedRecords,
                        false, // Send to all matching selected contacts
                        $campaignName,
                        $requestedGateway,
                        $idempotencyKey
                    );

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            } else {
                // Admin gateway check
                $normalizedGw = SmsService::normalizeGatewayIdentifier($requestedGateway);
                if ($normalizedGw === '') {
                    $requestedGateway = 'sms_gate_android';
                } else {
                    $requestedGateway = $normalizedGw;
                }
                if ($requestedGateway === 'ipromo') {
                    if (function_exists('ipromo_enabled') && !ipromo_enabled($pdo)) {
                        $fail('iPromo SMS Gateway is currently disabled in system settings.', 403);
                    }
                }

                $campaign = BulkSmsService::createCampaign(
                    $pdo,
                    $currentUserId,
                    'phone_contacts_filter',
                    $message,
                    $analyzedRecords,
                    false, // Send to all matching selected contacts
                    $campaignName,
                    $requestedGateway,
                    $idempotencyKey
                );
            }

            echo json_encode([
                'success'      => true,
                'campaign'     => $campaign,
                'campaign_id'  => $campaign['campaign_id'] ?? 0,
                'recipients'   => $uniqueCount,
                'total_units'  => $totalUnits,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 12. SAVE CONFIGURED LOCATIONS (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'save_locations':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $raw = (string)($_POST['locations'] ?? '');
            $locList = array_map('trim', explode(',', $raw));
            PhoneContactService::saveAllowedLocations($pdo, $locList);

            echo json_encode([
                'success'   => true,
                'locations' => PhoneContactService::getAllowedLocations($pdo),
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 13. SAVE TEACHER PERMISSIONS (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'save_teacher_permissions':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $teacherUserId = (int)($_POST['teacher_user_id'] ?? 0);
            if ($teacherUserId < 1) {
                $fail('Invalid teacher user ID.');
            }

            $permData = [
                'sms_access'          => isset($_POST['sms_access']) && (string)$_POST['sms_access'] === '1' ? 1 : 0,
                'monthly_limit'       => (int)($_POST['monthly_limit'] ?? 0),
                'can_send_sms'        => isset($_POST['can_send_sms']) && (string)$_POST['can_send_sms'] === '1' ? 1 : 0,
                'can_view_contacts'   => isset($_POST['can_view_contacts']) && (string)$_POST['can_view_contacts'] === '1' ? 1 : 0,
                'can_select_contacts' => isset($_POST['can_select_contacts']) && (string)$_POST['can_select_contacts'] === '1' ? 1 : 0,
                'allowed_exam_years'      => $_POST['allowed_exam_years'] ?? '',
                'allowed_exam_types'      => $_POST['allowed_exam_types'] ?? '',
                'allowed_locations'       => $_POST['allowed_locations'] ?? '',
                'allowed_whatsapp_groups' => $_POST['allowed_whatsapp_groups'] ?? '',
            ];

            TeacherSmsService::saveTeacherPermissions($pdo, $teacherUserId, $permData, $currentUserId);

            echo json_encode([
                'success' => true,
                'message' => 'Teacher SMS permissions and quota updated successfully.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 14. GET WHATSAPP GROUP STATISTICS
        // ─────────────────────────────────────────────────────────────
        case 'get_group_stats':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $stats = PhoneContactService::getWhatsAppGroupStatistics($pdo);
            echo json_encode([
                'success' => true,
                'stats'   => $stats,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 15. GET CONTACT TIMELINE
        // ─────────────────────────────────────────────────────────────
        case 'get_contact_timeline':
            $contactId = (int)($_REQUEST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            if ($isTeacher) {
                $perm = TeacherSmsService::getTeacherPermissions($pdo, $currentUserId);
                if (!$perm || empty($perm['can_view_contacts'])) {
                    $fail('Access denied.', 403);
                }
            }

            $timeline = PhoneContactService::getContactTimeline($pdo, $contactId);
            echo json_encode([
                'success'  => true,
                'timeline' => $timeline,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 16. SEND ADMIN TEST SMS
        // ─────────────────────────────────────────────────────────────
        case 'send_test_sms':
            if (!$isAdmin) {
                $fail('Administrator access required for test SMS.', 403);
            }

            $phone = trim((string)($_POST['phone_number'] ?? ''));
            $testMsg = trim((string)($_POST['message'] ?? ''));
            $gateway = trim((string)($_POST['gateway'] ?? 'ipromo'));

            if ($phone === '') {
                $fail('Phone number is required.');
            }
            if ($testMsg === '') {
                $fail('Test message content cannot be empty.');
            }

            $testResult = PhoneContactService::sendAdminTestSms($pdo, $phone, $testMsg, $gateway, $currentUserId);
            echo json_encode([
                'success' => true,
                'message' => 'Test SMS sent successfully.',
                'result'  => $testResult,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 17. TOGGLE GLOBAL TEACHER SMS EMERGENCY SWITCH (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'toggle_global_teacher_sms':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $enabled = isset($_POST['enabled']) && ((string)$_POST['enabled'] === '1' || (string)$_POST['enabled'] === 'true');
            $reason = trim((string)($_POST['reason'] ?? ''));
            TeacherSmsService::setTeacherSmsGloballyEnabled($pdo, $enabled, $currentUserId, $reason, $_SERVER['REMOTE_ADDR'] ?? null);

            echo json_encode([
                'success' => true,
                'enabled' => $enabled,
                'message' => $enabled ? 'Global teacher SMS broadcasts enabled.' : 'EMERGENCY: Global teacher SMS broadcasts blocked.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 18. GET GLOBAL TEACHER SMS STATUS
        // ─────────────────────────────────────────────────────────────
        case 'get_global_teacher_sms':
            $enabled = TeacherSmsService::isTeacherSmsGloballyEnabled($pdo);
            echo json_encode([
                'success' => true,
                'enabled' => $enabled,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 19. GET EMERGENCY SWITCH AUDIT LOGS (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'get_switch_audit_logs':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $limit = max(1, min(100, (int)($_REQUEST['limit'] ?? 50)));
            $logs = TeacherSmsService::getSwitchAuditLogs($pdo, $limit);
            echo json_encode([
                'success' => true,
                'logs'    => $logs,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 20. ARCHIVE CONTACT (ADMIN ONLY - SOFT DELETE)
        // ─────────────────────────────────────────────────────────────
        case 'archive_contact':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $contactId = (int)($_POST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            $reason = trim((string)($_POST['reason'] ?? ''));
            $res = PhoneContactService::archiveContact($pdo, $contactId, $currentUserId, $reason);
            echo json_encode([
                'success' => $res,
                'message' => 'Contact archived successfully. Archived contacts are excluded from SMS broadcasts.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 21. RESTORE CONTACT (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'restore_contact':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $contactId = (int)($_POST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            $res = PhoneContactService::restoreContact($pdo, $contactId, $currentUserId);
            echo json_encode([
                'success' => $res,
                'message' => 'Contact restored to active status.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 21b. DELETE SINGLE CONTACT (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'delete_contact':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $contactId = (int)($_POST['contact_id'] ?? $_REQUEST['contact_id'] ?? 0);
            if ($contactId < 1) {
                $fail('Invalid contact ID.');
            }

            $res = PhoneContactService::deleteContact($pdo, $contactId, $currentUserId);
            if (!$res) {
                $fail('Contact not found or already deleted.', 404);
            }
            echo json_encode([
                'success' => true,
                'message' => 'Contact and associated academic records deleted successfully.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 21c. DELETE CONTACTS IN BULK (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'delete_contacts_bulk':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $allFiltered = !empty($_POST['all_filtered']) && ((string)$_POST['all_filtered'] === '1' || (string)$_POST['all_filtered'] === 'true');
            $selectedIds = [];
            if (!empty($_POST['selected_ids'])) {
                if (is_array($_POST['selected_ids'])) {
                    $selectedIds = $_POST['selected_ids'];
                } elseif (is_string($_POST['selected_ids'])) {
                    $decoded = json_decode($_POST['selected_ids'], true);
                    $selectedIds = is_array($decoded) ? $decoded : explode(',', $_POST['selected_ids']);
                }
            }

            $filters = [];
            if (!empty($_POST['filters'])) {
                if (is_array($_POST['filters'])) {
                    $filters = $_POST['filters'];
                } elseif (is_string($_POST['filters'])) {
                    $decoded = json_decode($_POST['filters'], true);
                    if (is_array($decoded)) {
                        $filters = $decoded;
                    }
                }
            }

            if (!$allFiltered && $selectedIds === []) {
                $fail('No contacts selected for deletion.');
            }

            $delResult = PhoneContactService::deleteContactsBulk($pdo, $selectedIds, $filters, $allFiltered, $currentUserId);
            echo json_encode([
                'success'       => true,
                'deleted_count' => $delResult['deleted_count'],
                'message'       => "Successfully deleted {$delResult['deleted_count']} contact(s) and their associated records.",
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 22. GET WHATSAPP GROUP CANONICAL MAPPINGS (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'get_group_mappings':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $mappings = PhoneContactService::getAllGroupMappings($pdo);
            echo json_encode([
                'success'  => true,
                'mappings' => $mappings,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ─────────────────────────────────────────────────────────────
        // 23. SAVE WHATSAPP GROUP CANONICAL MAPPING (ADMIN ONLY)
        // ─────────────────────────────────────────────────────────────
        case 'save_group_mapping':
            if (!$isAdmin) {
                $fail('Administrator access required.', 403);
            }

            $rawGroup = trim((string)($_POST['raw_group'] ?? ''));
            $canonGroup = trim((string)($_POST['canonical_group'] ?? ''));
            if ($rawGroup === '' || $canonGroup === '') {
                $fail('Both raw WhatsApp group and canonical name are required.');
            }

            $res = PhoneContactService::setGroupMapping($pdo, $rawGroup, $canonGroup);
            echo json_encode([
                'success' => $res,
                'message' => 'WhatsApp group mapping saved successfully.',
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            $fail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('ajax/phone_contacts_action.php error: ' . $e->getMessage());
    $fail($e->getMessage(), 500);
}
