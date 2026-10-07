<?php
declare(strict_types=1);

/**
 * Admin Bulk SMS Broadcast Management
 *
 * Provides a multi-step workflow for uploading recipient files (.csv/.txt),
 * composing messages, live GSM/Unicode segment analysis, duplicate prevention,
 * batch sending, live progress, and comprehensive reporting.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;
use Edexcel\Services\IncomingSmsService;

require_admin();

BulkSmsService::ensureSchema($pdo);
IncomingSmsService::ensureSchema($pdo);

$page_title = 'Bulk SMS Broadcast';
$current_page = 'bulk_sms.php';

// Gateway status inspection
$availableGateways = SmsService::getAvailableGateways($pdo);
$smsGate = $availableGateways['sms_gate_android'] ?? [];
$ipromo = $availableGateways['ipromo'] ?? [];
$hasAnyActiveGateway = !empty($smsGate['available']) || !empty($ipromo['available']);

// Real-time device connection status for SMS-Gate (Android)
$smsGateStats = IncomingSmsService::getStats($pdo);
$isSmsGateOnline = ($smsGateStats['online_devices'] ?? 0) > 0;

// Summary metrics
$summary = BulkSmsService::getDashboardSummary($pdo);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 bulk-sms-shell">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/bulk_sms_history.php">Communication</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bulk SMS</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-chat-left-dots text-primary me-2"></i>Bulk SMS Broadcast</h1>
            <p class="text-muted small mb-0">Upload recipient lists, inspect duplicates, and broadcast SMS with full delivery auditing.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>admin/bulk_sms_history.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock-history me-1"></i> Campaign History
            </a>
            <a href="<?= BASE_URL ?>admin/sms_logs.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-columns me-1"></i> SMS Logs
            </a>
            <a href="<?= BASE_URL ?>admin/settings.php?tab=ipromo" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Gateway Settings
            </a>
        </div>
    </div>

    <!-- Alert only if NO gateway is available -->
    <?php if (!$hasAnyActiveGateway): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
        <div>
            <strong>No SMS Gateway is currently operational.</strong> Bulk SMS broadcasting is unavailable.
            Please configure SMS-Gate (Android) or enable iPromo in <a href="<?= BASE_URL ?>admin/settings.php?tab=ipromo" class="alert-link">Admin Settings</a>.
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Metrics Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 text-primary">
                        <i class="bi bi-broadcast-pin fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Today's Campaigns</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($summary['today_campaigns']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 text-success">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">SMS Sent Today</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($summary['today_sent']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3 text-danger">
                        <i class="bi bi-x-octagon fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Failed Today</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($summary['today_failed']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3 text-info">
                        <i class="bi bi-calendar2-check fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">This Month's Volume</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($summary['month_sent']) ?> <span class="fs-6 fw-normal text-muted">(<?= $summary['month_campaigns'] ?> camps)</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Step Container -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <span class="badge bg-primary me-2" id="stepIndicator">Step 1</span>
                <span id="stepTitle">Upload Recipients &amp; Compose Message</span>
            </h5>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Gateway:</span>
                <span class="badge bg-secondary" id="headerSelectedGatewayBadge">SMS-Gate (Android)</span>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Global Feedback Alert -->
            <div id="actionAlert" class="alert d-none mb-4" role="alert"></div>

            <!-- ── STEP 1: Upload & Compose Form ───────────────────────────── -->
            <div id="step1Section">
                <form id="analyzeForm" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <!-- SMS Gateway Selection Cards -->
                    <div class="card border-0 bg-light p-3 mb-4 rounded-3 shadow-none">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <label class="form-label fw-bold mb-0 text-dark">
                                <i class="bi bi-hdd-network text-primary me-2"></i>Select SMS Gateway
                            </label>
                            <span class="small text-muted">Choose the delivery provider for this broadcast</span>
                        </div>
                        <div class="row g-3">
                            <!-- Option 1: SMS-Gate (Android) -->
                            <div class="col-md-6">
                                <div class="gateway-option-card card h-100 p-3 border-2 border-primary bg-white shadow-sm position-relative cursor-pointer" id="gwCard_sms_gate_android" style="cursor: pointer;">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input gateway-radio" type="radio" name="sms_gateway" id="gwRadio_sms_gate_android" value="sms_gate_android" checked>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-check-label fw-bold text-dark fs-6 cursor-pointer" for="gwRadio_sms_gate_android">
                                                    SMS-Gate (Android)
                                                </label>
                                                <?php if ($isSmsGateOnline): ?>
                                                    <span class="badge bg-success"><i class="bi bi-circle-fill small"></i> Connected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-circle-fill small"></i> Offline</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                <i class="bi bi-phone me-1"></i><?= htmlspecialchars($smsGate['device_info'] ?? 'Android Gateway') ?>
                                            </div>
                                            <div class="small text-secondary">
                                                <?= htmlspecialchars($smsGate['notes'] ?? 'Direct cellular carrier dispatch via connected Android hardware') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Option 2: iPromo Marketing -->
                            <div class="col-md-6">
                                <div class="gateway-option-card card h-100 p-3 border bg-white shadow-sm position-relative <?= !empty($ipromo['enabled']) ? '' : 'opacity-75 bg-light' ?>" id="gwCard_ipromo" style="cursor: <?= !empty($ipromo['enabled']) ? 'pointer' : 'not-allowed' ?>;">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input gateway-radio" type="radio" name="sms_gateway" id="gwRadio_ipromo" value="ipromo" <?= empty($ipromo['enabled']) ? 'disabled' : '' ?>>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-check-label fw-bold text-dark fs-6 <?= !empty($ipromo['enabled']) ? 'cursor-pointer' : '' ?>" for="gwRadio_ipromo">
                                                    iPromo Marketing
                                                </label>
                                                <span class="badge <?= !empty($ipromo['enabled']) ? 'bg-info text-dark' : 'bg-danger text-white' ?>">
                                                    <?= htmlspecialchars($ipromo['status_text'] ?? 'Disabled') ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                <i class="bi bi-tag me-1"></i><?= htmlspecialchars($ipromo['device_info'] ?? 'Sender ID: Not configured') ?>
                                            </div>
                                            <div class="small <?= !empty($ipromo['enabled']) ? 'text-secondary' : 'text-danger' ?>">
                                                <?= htmlspecialchars($ipromo['notes'] ?? '') ?>
                                                <?php if (empty($ipromo['enabled'])): ?>
                                                    <a href="<?= BASE_URL ?>admin/settings.php?tab=ipromo" class="d-block small text-primary mt-1 text-decoration-underline">
                                                        Enable in Admin Settings &rarr;
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Recipient File Upload Card -->
                        <div class="col-lg-6">
                            <label class="form-label fw-bold"><i class="bi bi-file-earmark-arrow-up me-1"></i> Recipient File (.csv or .txt)</label>
                            <div class="border border-2 border-dashed rounded-3 p-4 text-center bg-light upload-dropzone position-relative" id="dropzoneBox">
                                <i class="bi bi-cloud-upload fs-1 text-primary mb-2 d-block"></i>
                                <div class="fw-semibold text-dark mb-1">Click to select or drag and drop file here</div>
                                <div class="small text-muted mb-3">Accepts .CSV or .TXT (Maximum file size: 10MB)</div>
                                <input type="file" id="recipientFile" name="recipient_file" class="form-control" accept=".csv,.txt" required>
                            </div>

                            <div class="card bg-light border-0 mt-3">
                                <div class="card-body p-3 small text-muted">
                                    <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1"></i> Accepted formats:</div>
                                    <ul class="mb-0 ps-3">
                                        <li><strong>CSV with headers:</strong> <code>name,phone</code> or <code>student_id,name,mobile</code></li>
                                        <li><strong>Raw CSV / Numbers:</strong> <code>0771234567</code> (one per row)</li>
                                        <li><strong>TXT file:</strong> One Sri Lankan mobile number per line.</li>
                                        <li>Supports local (<code>077...</code>), 9-digit (<code>77...</code>), and international (<code>+947...</code>).</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Message Composer Card -->
                        <div class="col-lg-6">
                            <label for="messageBody" class="form-label fw-bold d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-pencil-square me-1"></i> SMS Message Content</span>
                                <span class="badge bg-secondary" id="encodingBadge">GSM-7</span>
                            </label>
                            <textarea id="messageBody" name="message" class="form-control" rows="8" placeholder="Type your SMS message here..." required></textarea>

                            <!-- Live Segment & Character Metrics -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 mt-2 bg-light border rounded small">
                                <div>
                                    <span class="text-muted">Characters:</span> <strong id="charCount">0</strong>
                                    <span class="text-muted ms-2">| Chars left in part:</span> <strong id="charsLeft">160</strong>
                                </div>
                                <div>
                                    <span class="text-muted">Segments per recipient:</span> <span class="badge bg-primary" id="segmentCount">0</span>
                                </div>
                            </div>

                            <!-- Fast Template Samples -->
                            <div class="mt-3">
                                <span class="small text-muted fw-semibold me-2">Sample templates:</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 me-1 insert-template" data-tpl="Edexcel College: Dear Student, your class will be held this Sunday at 8:00 AM at Kandy Campus. Thank you.">Class Reminder</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 insert-template" data-tpl="Edexcel College: Dear Student, please note your official examination timetable is now available on the portal.">Exam Notice</button>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-4 text-end">
                        <button type="submit" id="analyzeBtn" class="btn btn-primary btn-lg px-4" <?= !$hasAnyActiveGateway ? 'disabled' : '' ?>>
                            <i class="bi bi-search me-1"></i> Analyze Recipients &amp; Preview
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── STEP 2: Upload Analysis & Validation Breakdown ──────────── -->
            <div id="step2Section" class="d-none">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-clipboard2-data me-2 text-primary"></i>Upload Analysis Results</h5>
                        <span class="badge bg-secondary" id="step2GatewayBadge">SMS-Gate (Android)</span>
                    </div>
                    <div>
                        <a href="<?= BASE_URL ?>admin/bulk_sms_export.php?export=validation" id="downloadValCsvBtn" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="bi bi-download me-1"></i> Download Validation Report (CSV)
                        </a>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="reuploadBtn">
                            <i class="bi bi-arrow-left me-1"></i> Upload Different File
                        </button>
                    </div>
                </div>

                <!-- Analysis Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-md-4 col-xl-2">
                        <div class="card border bg-light text-center p-2">
                            <span class="small text-muted">Total Rows</span>
                            <h4 class="fw-bold mb-0" id="statTotalRows">0</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-2">
                        <div class="card border border-success bg-success bg-opacity-10 text-center p-2">
                            <span class="small text-success fw-semibold">Valid Numbers</span>
                            <h4 class="fw-bold text-success mb-0" id="statValid">0</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-2">
                        <div class="card border border-warning bg-warning bg-opacity-10 text-center p-2">
                            <span class="small text-warning-emphasis fw-semibold">Prev. Contacted</span>
                            <h4 class="fw-bold text-warning-emphasis mb-0" id="statPrevSent">0</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-2">
                        <div class="card border border-secondary bg-light text-center p-2">
                            <span class="small text-secondary fw-semibold">Duplicates in File</span>
                            <h4 class="fw-bold text-secondary mb-0" id="statDuplicates">0</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-2">
                        <div class="card border border-danger bg-danger bg-opacity-10 text-center p-2">
                            <span class="small text-danger fw-semibold">Invalid Numbers</span>
                            <h4 class="fw-bold text-danger mb-0" id="statInvalid">0</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-2">
                        <div class="card border border-primary bg-primary bg-opacity-10 text-center p-2">
                            <span class="small text-primary fw-bold">Effective Recipients</span>
                            <h4 class="fw-bold text-primary mb-0" id="statNewRecips">0</h4>
                        </div>
                    </div>
                </div>

                <!-- Options & Campaign Configuration -->
                <div class="card bg-light border-0 p-3 mb-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label for="campaignNameInput" class="form-label small fw-bold">Campaign Name / Reference</label>
                            <input type="text" class="form-control" id="campaignNameInput" placeholder="e.g. ICT Sunday Class Reminder">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-md-4">
                                <input class="form-check-input" type="checkbox" id="excludePrevSentCheck" checked>
                                <label class="form-check-label fw-bold" for="excludePrevSentCheck">
                                    Exclude previously sent recipients
                                </label>
                                <div class="form-text small">Recommended: prevents accidental double-contacting with the exact same message.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sample Preview Table -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-2">Recipients Preview (First 50 Rows)</h6>
                    <div class="table-responsive border rounded" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Row</th>
                                    <th>Original Phone</th>
                                    <th>Normalized Phone</th>
                                    <th>Recipient Name</th>
                                    <th>Matched Contact</th>
                                    <th>Validation Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="sampleRecordsTbody">
                                <tr><td colspan="7" class="text-center text-muted py-3">No records loaded.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 2 Action Buttons -->
                <div class="border-top pt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="backToStep1Btn">
                        <i class="bi bi-pencil me-1"></i> Edit Message or File
                    </button>
                    <button type="button" class="btn btn-success btn-lg px-4" id="toConfirmationBtn" <?= !$hasAnyActiveGateway ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-right-circle me-1"></i> Review &amp; Confirm Broadcast
                    </button>
                </div>
            </div>

            <!-- ── STEP 3: Final Confirmation Screen ───────────────────────── -->
            <div id="step3Section" class="d-none">
                <div class="alert alert-warning border-warning">
                    <h5 class="fw-bold alert-heading"><i class="bi bi-shield-check me-2"></i>Final Confirmation Before Sending</h5>
                    <p class="mb-0">Please review the campaign details below. Once confirmed, messages will be safely queued and broadcasted in automated batches.</p>
                </div>

                <div class="row g-4 my-2">
                    <div class="col-md-6">
                        <div class="card border h-100 p-3">
                            <h6 class="fw-bold border-bottom pb-2 mb-3">Broadcast Summary</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">SMS Gateway:</td>
                                    <td class="fw-bold" id="confirmGatewayName">SMS-Gate (Android)</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Campaign Name:</td>
                                    <td class="fw-bold" id="confirmCampName">—</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Target Recipients:</td>
                                    <td class="fw-bold text-primary" id="confirmRecipCount">0</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">SMS Segments:</td>
                                    <td class="fw-bold" id="confirmSegments">1</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Estimated SMS Units:</td>
                                    <td class="fw-bold text-dark" id="confirmTotalUnits">0</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Duplicate Protection:</td>
                                    <td class="fw-bold text-success" id="confirmDupProtection">Active (SHA-256 fingerprinting)</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border h-100 p-3 bg-light">
                            <h6 class="fw-bold border-bottom pb-2 mb-2">Message Body Preview</h6>
                            <div class="p-3 bg-white border rounded font-monospace small" id="confirmMessageText" style="white-space: pre-wrap; min-height: 120px;"></div>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3 mt-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="backToStep2Btn">
                        <i class="bi bi-arrow-left me-1"></i> Back to Analysis
                    </button>
                    <button type="button" class="btn btn-danger btn-lg px-4" id="confirmSendBtn">
                        <i class="bi bi-send-fill me-2"></i> Start Broadcast Now
                    </button>
                </div>
            </div>

            <!-- ── CONFIRMATION MODAL BEFORE BROADCAST ─────────────────────── -->
            <div class="modal fade" id="confirmBroadcastModal" tabindex="-1" aria-labelledby="confirmBroadcastModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow border-0">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="confirmBroadcastModalLabel">
                                <i class="bi bi-shield-exclamation text-warning me-2"></i>Confirm Bulk SMS Broadcast
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="mb-3 text-muted">You are about to launch a live SMS broadcast. Please confirm the details:</p>

                            <div class="card bg-light border p-3 mb-3">
                                <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                    <span class="text-muted small">Selected Gateway:</span>
                                    <span class="fw-bold text-dark" id="modalGatewayLabel">SMS-Gate (Android)</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                    <span class="text-muted small">Target Recipients:</span>
                                    <span class="fw-bold text-primary" id="modalRecipCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Estimated SMS Units:</span>
                                    <span class="fw-bold text-dark" id="modalTotalUnits">0</span>
                                </div>
                            </div>

                            <div class="alert alert-warning small mb-0 d-flex align-items-center">
                                <i class="bi bi-shield-lock-fill me-2 fs-4 text-warning"></i>
                                <div>
                                    <strong>Strict Gateway Isolation:</strong> Messages will be dispatched strictly through the selected gateway. Automatic fallback is disabled to prevent duplicate sending and unexpected carrier fees.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger px-4" id="modalExecuteSendBtn">
                                <i class="bi bi-send-fill me-1"></i> Confirm &amp; Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STEP 4: Live Progress Screen ────────────────────────────── -->
            <div id="step4Section" class="d-none">
                <div class="text-center py-4">
                    <h4 class="fw-bold mb-1" id="progressStatusTitle">Sending Bulk SMS...</h4>
                    <p class="text-muted small mb-4" id="progressStatusSub">
                        Broadcasting via <span class="fw-bold text-primary" id="progressGatewayLabel">SMS-Gate (Android)</span>. Please keep this browser tab open while batches are processed.
                    </p>

                    <!-- Large Progress Bar -->
                    <div class="progress mb-3" style="height: 28px;">
                        <div id="broadcastProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary fw-bold" role="progressbar" style="width: 0%;">
                            0%
                        </div>
                    </div>

                    <!-- Progress Stats Grid -->
                    <div class="row g-3 justify-content-center text-center mt-2">
                        <div class="col-auto">
                            <div class="p-3 bg-light rounded border">
                                <span class="small text-muted d-block">Processed</span>
                                <strong class="fs-5" id="progProcessed">0 / 0</strong>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="p-3 bg-success bg-opacity-10 text-success rounded border border-success">
                                <span class="small d-block">Successfully Sent</span>
                                <strong class="fs-5" id="progSent">0</strong>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="p-3 bg-danger bg-opacity-10 text-danger rounded border border-danger">
                                <span class="small d-block">Failed</span>
                                <strong class="fs-5" id="progFailed">0</strong>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="p-3 bg-secondary bg-opacity-10 text-secondary rounded border">
                                <span class="small d-block">Skipped</span>
                                <strong class="fs-5" id="progSkipped">0</strong>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="p-3 bg-light rounded border">
                                <span class="small text-muted d-block">Remaining</span>
                                <strong class="fs-5" id="progRemaining">0</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STEP 5: Final Report Screen ─────────────────────────────── -->
            <div id="step5Section" class="d-none">
                <div class="text-center py-3">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-check-lg fs-1"></i>
                    </div>
                    <h3 class="fw-bold" id="reportHeadline">Broadcast Completed!</h3>
                    <p class="text-muted" id="reportSub">All recipient batches have been executed.</p>
                </div>

                <!-- Final Stats Grid -->
                <div class="card bg-light border-0 p-4 mb-4">
                    <div class="row g-3 text-center align-items-center">
                        <div class="col-md-2">
                            <span class="text-muted small">Campaign Code</span>
                            <h6 class="fw-bold font-monospace text-primary mb-0" id="finalCode">—</h6>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Gateway Used</span>
                            <div class="mt-1" id="finalGatewayContainer">
                                <span class="badge bg-secondary fs-6" id="finalGatewayBadge">SMS-Gate (Android)</span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <span class="text-muted small">Total Recipients</span>
                            <h5 class="fw-bold text-dark mb-0" id="finalTotal">0</h5>
                        </div>
                        <div class="col-md-2">
                            <span class="text-muted small">Successfully Sent</span>
                            <h5 class="fw-bold text-success mb-0" id="finalSent">0</h5>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Success Rate</span>
                            <h5 class="fw-bold text-dark mb-0" id="finalRate">0%</h5>
                        </div>
                    </div>
                </div>

                <!-- Failed Messages Breakdown Table (shown if failed > 0) -->
                <div id="failedRecipientsSection" class="d-none text-start mb-4">
                    <div class="card border border-danger border-opacity-25 shadow-sm">
                        <div class="card-header bg-danger bg-opacity-10 py-2 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Failed Messages Breakdown</span>
                            <span class="badge bg-danger" id="failedTableCountBadge">0 failed</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Recipient Phone</th>
                                            <th>Recipient Name</th>
                                            <th>Failure Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody id="failedRecipientsTbody">
                                        <tr><td colspan="3" class="text-center text-muted py-2">Loading failed details...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Toolbar -->
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                    <a href="#" id="downloadFinalCsvBtn" class="btn btn-outline-primary">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Delivery CSV Report
                    </a>
                    <button type="button" id="retryFailedBtn" class="btn btn-warning d-none">
                        <i class="bi bi-arrow-clockwise me-1"></i> Retry Failed Messages (<span id="failedCountBadge">0</span>)
                    </button>
                    <a href="<?= BASE_URL ?>admin/bulk_sms.php" class="btn btn-secondary">
                        <i class="bi bi-plus-circle me-1"></i> Start New Broadcast
                    </a>
                    <a href="<?= BASE_URL ?>admin/bulk_sms_history.php" class="btn btn-outline-secondary">
                        <i class="bi bi-clock-history me-1"></i> View All Campaigns
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Bulk SMS Client Logic -->
<script>
(function() {
    'use strict';

    let currentAnalysis = null;
    let activeCampaignId = null;
    let isProcessing = false;
    let selectedGateway = 'sms_gate_android';
    let selectedGatewayLabel = 'SMS-Gate (Android)';

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Elements
    const messageInput = document.getElementById('messageBody');
    const charCountEl = document.getElementById('charCount');
    const charsLeftEl = document.getElementById('charsLeft');
    const segmentCountEl = document.getElementById('segmentCount');
    const encodingBadge = document.getElementById('encodingBadge');

    const stepIndicator = document.getElementById('stepIndicator');
    const stepTitle = document.getElementById('stepTitle');
    const actionAlert = document.getElementById('actionAlert');

    const step1 = document.getElementById('step1Section');
    const step2 = document.getElementById('step2Section');
    const step3 = document.getElementById('step3Section');
    const step4 = document.getElementById('step4Section');
    const step5 = document.getElementById('step5Section');

    function showAlert(msg, isSuccess = false) {
        actionAlert.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        actionAlert.textContent = msg;
        actionAlert.classList.remove('d-none');
    }

    function hideAlert() {
        actionAlert.classList.add('d-none');
    }

    // ── Gateway Selection Handling ──────────────────────────────────────────
    function updateGatewaySelection(gwId, gwLabel) {
        selectedGateway = gwId;
        selectedGatewayLabel = gwLabel;

        // Visual cards styling
        document.querySelectorAll('.gateway-option-card').forEach(card => {
            card.classList.remove('border-2', 'border-primary');
            card.classList.add('border');
        });
        const activeCard = document.getElementById('gwCard_' + gwId);
        if (activeCard) {
            activeCard.classList.add('border-2', 'border-primary');
            activeCard.classList.remove('border');
        }

        // Header badge
        const hBadge = document.getElementById('headerSelectedGatewayBadge');
        if (hBadge) {
            hBadge.textContent = gwLabel;
            hBadge.className = 'badge ' + (gwId === 'ipromo' ? 'bg-info text-dark' : 'bg-secondary');
        }

        // Step 2 badge
        const s2Badge = document.getElementById('step2GatewayBadge');
        if (s2Badge) {
            s2Badge.textContent = gwLabel;
            s2Badge.className = 'badge ' + (gwId === 'ipromo' ? 'bg-info text-dark' : 'bg-secondary');
        }

        // Step 3 summary
        const s3Name = document.getElementById('confirmGatewayName');
        if (s3Name) s3Name.textContent = gwLabel;

        // Modal summary
        const modalGw = document.getElementById('modalGatewayLabel');
        if (modalGw) modalGw.textContent = gwLabel;
    }

    document.querySelectorAll('.gateway-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const label = this.value === 'ipromo' ? 'iPromo Marketing' : 'SMS-Gate (Android)';
                updateGatewaySelection(this.value, label);
            }
        });
    });

    document.querySelectorAll('.gateway-option-card').forEach(card => {
        card.addEventListener('click', function(e) {
            const radio = this.querySelector('.gateway-radio');
            if (radio && !radio.disabled && radio !== e.target) {
                radio.checked = true;
                const label = radio.value === 'ipromo' ? 'iPromo Marketing' : 'SMS-Gate (Android)';
                updateGatewaySelection(radio.value, label);
            }
        });
    });

    // ── Live SMS Character and Segment Calculation ──────────────────────────
    function calculateUnits(text) {
        if (!text || text.length === 0) {
            return { encoding: 'GSM-7', length: 0, segments: 0, left: 160 };
        }

        const gsmBasic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        const gsmExt = "^{}\\[~]|€";

        let isGsm = true;
        let gsmLen = 0;

        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (gsmBasic.indexOf(ch) !== -1) {
                gsmLen += 1;
            } else if (gsmExt.indexOf(ch) !== -1) {
                gsmLen += 2;
            } else {
                isGsm = false;
                break;
            }
        }

        if (isGsm) {
            const segs = gsmLen <= 160 ? 1 : Math.ceil(gsmLen / 153);
            const left = segs === 1 ? (160 - gsmLen) : ((segs * 153) - gsmLen);
            return { encoding: 'GSM-7', length: gsmLen, segments: segs, left: Math.max(0, left) };
        } else {
            const len = text.length;
            const segs = len <= 70 ? 1 : Math.ceil(len / 67);
            const left = segs === 1 ? (70 - len) : ((segs * 67) - len);
            return { encoding: 'Unicode', length: len, segments: segs, left: Math.max(0, left) };
        }
    }

    function updateLiveStats() {
        const text = messageInput.value;
        const res = calculateUnits(text);
        charCountEl.textContent = res.length;
        charsLeftEl.textContent = res.left;
        segmentCountEl.textContent = res.segments;
        encodingBadge.textContent = res.encoding;
        encodingBadge.className = 'badge ' + (res.encoding === 'GSM-7' ? 'bg-secondary' : 'bg-warning text-dark');
    }

    messageInput.addEventListener('input', updateLiveStats);

    // Sample template injection
    document.querySelectorAll('.insert-template').forEach(btn => {
        btn.addEventListener('click', function() {
            messageInput.value = this.dataset.tpl;
            updateLiveStats();
        });
    });

    // ── STEP 1: Upload & Analyze ────────────────────────────────────────────
    document.getElementById('analyzeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        hideAlert();

        const fileInput = document.getElementById('recipientFile');
        if (!fileInput.files || fileInput.files.length === 0) {
            showAlert('Please select a .csv or .txt file to upload.');
            return;
        }

        const msg = messageInput.value.trim();
        if (msg === '') {
            showAlert('Please enter an SMS message body before analyzing.');
            return;
        }

        const btn = document.getElementById('analyzeBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Analyzing...';

        const fd = new FormData();
        fd.append('action', 'analyze');
        fd.append('csrf_token', csrfToken);
        fd.append('recipient_file', fileInput.files[0]);
        fd.append('message', msg);

        fetch('<?= BASE_URL ?>ajax/bulk_sms_action.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Analyze Recipients &amp; Preview';

            if (!data.success) {
                showAlert(data.error || 'Failed to analyze file.');
                return;
            }

            currentAnalysis = data.analysis;
            renderAnalysis(data.analysis);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Analyze Recipients &amp; Preview';
            showAlert('Network or server error: ' + err.message);
        });
    });

    function renderAnalysis(an) {
        document.getElementById('statTotalRows').textContent = Number(an.total_records).toLocaleString();
        document.getElementById('statValid').textContent = Number(an.valid_records).toLocaleString();
        document.getElementById('statPrevSent').textContent = Number(an.previously_sent_records).toLocaleString();
        document.getElementById('statDuplicates').textContent = Number(an.duplicate_records).toLocaleString();
        document.getElementById('statInvalid').textContent = Number(an.invalid_records).toLocaleString();
        document.getElementById('statNewRecips').textContent = Number(an.new_recipients).toLocaleString();

        const tbody = document.getElementById('sampleRecordsTbody');
        tbody.innerHTML = '';

        if (!an.sample_records || an.sample_records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No valid records found in file.</td></tr>';
        } else {
            an.sample_records.forEach(r => {
                let badgeClass = 'bg-secondary';
                if (r.status === 'VALID') badgeClass = 'bg-success';
                else if (r.status === 'PREVIOUSLY_SENT') badgeClass = 'bg-warning text-dark';
                else if (r.status === 'DUPLICATE') badgeClass = 'bg-secondary';
                else if (r.status === 'INVALID') badgeClass = 'bg-danger';

                let matchLabel = '—';
                if (r.match_info) {
                    matchLabel = `<span class="badge bg-light text-dark border">${r.match_info.role}</span> ${r.match_info.name}`;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${r.row_number}</td>
                    <td><code>${escapeHtml(r.original_phone)}</code></td>
                    <td><strong>${escapeHtml(r.normalized_phone || '—')}</strong></td>
                    <td>${escapeHtml(r.name || '—')}</td>
                    <td>${matchLabel}</td>
                    <td><span class="badge ${badgeClass}">${r.status}</span></td>
                    <td class="small text-muted">${escapeHtml(r.reason || '')}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Switch to Step 2
        step1.classList.add('d-none');
        step2.classList.remove('d-none');
        stepIndicator.textContent = 'Step 2';
        stepTitle.textContent = 'Review Validation & Configure Broadcast';
    }

    // Navigation between steps
    document.getElementById('backToStep1Btn').addEventListener('click', () => {
        step2.classList.add('d-none');
        step1.classList.remove('d-none');
        stepIndicator.textContent = 'Step 1';
        stepTitle.textContent = 'Upload Recipients & Compose Message';
    });

    document.getElementById('reuploadBtn').addEventListener('click', () => {
        document.getElementById('recipientFile').value = '';
        step2.classList.add('d-none');
        step1.classList.remove('d-none');
        stepIndicator.textContent = 'Step 1';
        stepTitle.textContent = 'Upload Recipients & Compose Message';
    });

    // ── STEP 2 -> STEP 3: To Confirmation ───────────────────────────────────
    document.getElementById('toConfirmationBtn').addEventListener('click', function() {
        if (!currentAnalysis) return;

        const excludePrev = document.getElementById('excludePrevSentCheck').checked;
        const effectiveCount = excludePrev ? currentAnalysis.new_recipients : currentAnalysis.valid_records;

        if (effectiveCount <= 0) {
            showAlert('No eligible recipients to send. Please check your file or exclusion settings.');
            return;
        }

        const msg = messageInput.value.trim();
        const units = calculateUnits(msg);

        const campName = document.getElementById('campaignNameInput').value.trim() || 'Broadcast Campaign';
        document.getElementById('confirmCampName').textContent = campName;
        document.getElementById('confirmGatewayName').textContent = selectedGatewayLabel;
        document.getElementById('confirmRecipCount').textContent = Number(effectiveCount).toLocaleString();
        document.getElementById('confirmSegments').textContent = units.segments;
        document.getElementById('confirmTotalUnits').textContent = Number(effectiveCount * units.segments).toLocaleString();
        document.getElementById('confirmMessageText').textContent = msg;

        // Also update modal fields
        document.getElementById('modalGatewayLabel').textContent = selectedGatewayLabel;
        document.getElementById('modalRecipCount').textContent = Number(effectiveCount).toLocaleString();
        document.getElementById('modalTotalUnits').textContent = Number(effectiveCount * units.segments).toLocaleString();

        step2.classList.add('d-none');
        step3.classList.remove('d-none');
        stepIndicator.textContent = 'Step 3';
        stepTitle.textContent = 'Final Confirmation';
    });

    document.getElementById('backToStep2Btn').addEventListener('click', () => {
        step3.classList.add('d-none');
        step2.classList.remove('d-none');
        stepIndicator.textContent = 'Step 2';
        stepTitle.textContent = 'Review Validation & Configure Broadcast';
    });

    // ── STEP 3 -> MODAL TRIGGER -> STEP 4: Start Sending ────────────────────
    document.getElementById('confirmSendBtn').addEventListener('click', function() {
        hideAlert();
        const modalEl = document.getElementById('confirmBroadcastModal');
        if (window.bootstrap && bootstrap.Modal) {
            const modalObj = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalObj.show();
        } else {
            executeBroadcast();
        }
    });

    document.getElementById('modalExecuteSendBtn').addEventListener('click', function() {
        const modalEl = document.getElementById('confirmBroadcastModal');
        if (window.bootstrap && bootstrap.Modal) {
            const modalObj = bootstrap.Modal.getInstance(modalEl);
            if (modalObj) modalObj.hide();
        }
        executeBroadcast();
    });

    function executeBroadcast() {
        hideAlert();
        const sendBtn = document.getElementById('confirmSendBtn');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Creating Campaign...';

        const campName = document.getElementById('campaignNameInput').value.trim();
        const excludePrev = document.getElementById('excludePrevSentCheck').checked ? '1' : '0';
        const msg = messageInput.value.trim();

        const fd = new FormData();
        fd.append('action', 'create_campaign');
        fd.append('csrf_token', csrfToken);
        fd.append('message', msg);
        fd.append('campaign_name', campName);
        fd.append('exclude_previously_sent', excludePrev);
        fd.append('gateway', selectedGateway);

        fetch('<?= BASE_URL ?>ajax/bulk_sms_action.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Start Broadcast Now';
                showAlert(data.error || 'Failed to create campaign.');
                return;
            }

            activeCampaignId = data.campaign.campaign_id;
            step3.classList.add('d-none');
            step4.classList.remove('d-none');
            stepIndicator.textContent = 'Step 4';
            stepTitle.textContent = 'Broadcasting Messages';

            const activeGwLabel = data.campaign.gateway_label || selectedGatewayLabel;
            const pGw = document.getElementById('progressGatewayLabel');
            if (pGw) pGw.textContent = activeGwLabel;

            startBatchProcessor(activeCampaignId);
        })
        .catch(err => {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Start Broadcast Now';
            showAlert('Network error: ' + err.message);
        });
    }

    // ── Batch Processing Loop via AJAX ──────────────────────────────────────
    function startBatchProcessor(campId) {
        if (isProcessing) return;
        isProcessing = true;

        function runNextBatch() {
            const fd = new FormData();
            fd.append('action', 'process_batch');
            fd.append('csrf_token', csrfToken);
            fd.append('campaign_id', campId);
            fd.append('batch_size', 50);

            fetch('<?= BASE_URL ?>ajax/bulk_sms_action.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showAlert('Batch processing paused: ' + (data.error || 'Unknown error'));
                    isProcessing = false;
                    return;
                }

                // Update UI progress
                const pct = Math.min(100, Math.max(0, data.percent));
                const bar = document.getElementById('broadcastProgressBar');
                bar.style.width = pct + '%';
                bar.textContent = pct + '%';

                document.getElementById('progProcessed').textContent = Number(data.processed).toLocaleString() + ' / ' + Number(data.total).toLocaleString();
                document.getElementById('progSent').textContent = Number(data.sent).toLocaleString();
                document.getElementById('progFailed').textContent = Number(data.failed).toLocaleString();
                document.getElementById('progSkipped').textContent = Number(data.skipped).toLocaleString();
                document.getElementById('progRemaining').textContent = Number(data.remaining).toLocaleString();

                if (data.is_completed) {
                    isProcessing = false;
                    showCompletionReport(data);
                } else {
                    // Small delay between batches to respect rate limits
                    setTimeout(runNextBatch, 500);
                }
            })
            .catch(err => {
                showAlert('Temporary network issue during batch. Retrying in 3 seconds...');
                setTimeout(runNextBatch, 3000);
            });
        }

        runNextBatch();
    }

    // ── STEP 5: Final Report Screen ─────────────────────────────────────────
    function showCompletionReport(data) {
        step4.classList.add('d-none');
        step5.classList.remove('d-none');
        stepIndicator.textContent = 'Step 5';
        stepTitle.textContent = 'Broadcast Summary Report';

        document.getElementById('finalCode').textContent = data.campaign_code;
        document.getElementById('finalTotal').textContent = Number(data.total).toLocaleString();
        document.getElementById('finalSent').textContent = Number(data.sent).toLocaleString();

        const usedGw = data.gateway || selectedGateway;
        const usedGwLabel = data.gateway_label || selectedGatewayLabel;
        const gwBadge = document.getElementById('finalGatewayBadge');
        if (gwBadge) {
            gwBadge.textContent = usedGwLabel;
            gwBadge.className = 'badge ' + (usedGw === 'ipromo' ? 'bg-info text-dark' : 'bg-secondary');
        }

        const rate = data.total > 0 ? ((data.sent / data.total) * 100).toFixed(1) : 0;
        document.getElementById('finalRate').textContent = rate + '%';

        const downloadBtn = document.getElementById('downloadFinalCsvBtn');
        downloadBtn.href = `<?= BASE_URL ?>admin/bulk_sms_export.php?export=campaign&id=${data.campaign_id}`;

        if (data.failed > 0) {
            const retryBtn = document.getElementById('retryFailedBtn');
            retryBtn.classList.remove('d-none');
            document.getElementById('failedCountBadge').textContent = data.failed;
            document.getElementById('failedTableCountBadge').textContent = `${data.failed} failed`;

            // Load failed recipient breakdown
            loadFailedRecipients(data.campaign_id);

            retryBtn.onclick = function() {
                retryBtn.disabled = true;
                retryBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing retry...';

                const fd = new FormData();
                fd.append('action', 'retry_failed');
                fd.append('csrf_token', csrfToken);
                fd.append('campaign_id', data.campaign_id);

                fetch('<?= BASE_URL ?>ajax/bulk_sms_action.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        step5.classList.add('d-none');
                        step4.classList.remove('d-none');
                        startBatchProcessor(data.campaign_id);
                    } else {
                        retryBtn.disabled = false;
                        showAlert(res.error || 'Failed to retry.');
                    }
                });
            };
        } else {
            const failSec = document.getElementById('failedRecipientsSection');
            if (failSec) failSec.classList.add('d-none');
        }
    }

    function loadFailedRecipients(campId) {
        const failSec = document.getElementById('failedRecipientsSection');
        const tbody = document.getElementById('failedRecipientsTbody');
        if (!failSec || !tbody) return;

        failSec.classList.remove('d-none');
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm me-2"></span>Loading failed recipient log...</td></tr>';

        const fd = new FormData();
        fd.append('action', 'get_failed_recipients');
        fd.append('csrf_token', csrfToken);
        fd.append('campaign_id', campId);

        fetch('<?= BASE_URL ?>ajax/bulk_sms_action.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            tbody.innerHTML = '';
            if (!res.success || !res.failed_recipients || res.failed_recipients.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">No failed recipient entries found.</td></tr>';
                return;
            }

            res.failed_recipients.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-monospace fw-bold">${escapeHtml(r.phone_number)}</td>
                    <td>${escapeHtml(r.name || '—')}</td>
                    <td class="small text-danger">${escapeHtml(r.error_message || 'Gateway rejected message')}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-2">Failed to load error details: ${escapeHtml(err.message)}</td></tr>`;
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
</script>

<style>
.bulk-sms-shell .upload-dropzone {
    background: #fbfcfe;
    border-color: #cbd5e1 !important;
    transition: all 0.2s ease-in-out;
}
.bulk-sms-shell .upload-dropzone:hover {
    border-color: #3b82f6 !important;
    background: #eff6ff;
}
.bulk-sms-shell .progress-bar {
    transition: width 0.3s ease;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
