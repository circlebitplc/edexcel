<?php
declare(strict_types=1);

/**
 * Admin Phone Contact Management
 *
 * WhatsApp Group Contact Collection, Academic Associations,
 * Deduplication, Filtering, Controlled Bulk SMS, and CSV Export.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;
use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\BulkSmsService;
use Edexcel\Services\SmsService;

require_admin();

PhoneContactService::ensureSchema($pdo);
TeacherSmsService::ensureSchema($pdo);
BulkSmsService::ensureSchema($pdo);

$page_title = 'Phone Contacts';
$current_page = 'phone_contacts.php';

// Fetch distinct filter options
$filterOptions = PhoneContactService::getDistinctFilterOptions($pdo);
$allowedLocations = PhoneContactService::getAllowedLocations($pdo);

// Available SMS gateways
$availableGateways = SmsService::getAvailableGateways($pdo);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 phone-contacts-shell">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/bulk_sms.php">Communication</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Phone Contacts</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-person-lines-fill text-primary me-2"></i>Phone Contacts Management</h1>
            <p class="text-muted small mb-0">Collect, normalize, and manage WhatsApp group student contacts and academic records with deduplication.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnOpenGroupStatsModal" data-bs-toggle="modal" data-bs-target="#groupStatsModal">
                <i class="bi bi-bar-chart-line me-1"></i> WhatsApp Group Stats
            </button>
            <button type="button" class="btn btn-outline-warning btn-sm" id="btnOpenTestSmsModal" data-bs-toggle="modal" data-bs-target="#testSmsModal">
                <i class="bi bi-send-check me-1"></i> Test SMS
            </button>
            <a href="<?= BASE_URL ?>admin/phone_import_history.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock-history me-1"></i> Import History
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#locationsModal">
                <i class="bi bi-geo-alt me-1"></i> Locations
            </button>
            <a href="<?= BASE_URL ?>admin/sms_teacher_permissions.php" class="btn btn-outline-info btn-sm">
                <i class="bi bi-shield-lock me-1"></i> Teacher SMS Permissions
            </a>
        </div>
    </div>

    <!-- Alert Container -->
    <?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Contact and its associated academic records were permanently deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <div id="pageAlert" class="alert d-none mb-4" role="alert"></div>

    <!-- Counters Banner -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 text-primary">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Unique Contacts</div>
                        <h4 class="mb-0 fw-bold" id="statTotalAll">—</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 text-success">
                        <i class="bi bi-funnel fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Filtered Contacts</div>
                        <h4 class="mb-0 fw-bold" id="statTotalFiltered">—</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3 text-info">
                        <i class="bi bi-check2-square fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Selected Contacts</div>
                        <h4 class="mb-0 fw-bold" id="statSelectedCount">0</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="card-title mb-0 fw-bold text-dark"><i class="bi bi-sliders me-2 text-primary"></i>Contact Filters</h6>
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnResetFilters">
                <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
            </button>
        </div>
        <div class="card-body p-3">
            <form id="filterForm" class="row g-2 align-items-end">
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="filterSearch" placeholder="Name / Phone / School / Group...">
                    </div>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">WhatsApp Group</label>
                    <select class="form-select form-select-sm" id="filterSourceGroup">
                        <option value="">All Groups</option>
                        <?php foreach ($filterOptions['groups'] as $grp): ?>
                            <option value="<?= e($grp) ?>"><?= e($grp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Exam Year</label>
                    <select class="form-select form-select-sm" id="filterExamYear">
                        <option value="">All Years</option>
                        <?php foreach ($filterOptions['years'] as $yr): ?>
                            <option value="<?= (int)$yr ?>"><?= (int)$yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Exam Type</label>
                    <select class="form-select form-select-sm" id="filterExamType">
                        <option value="">All Exams</option>
                        <?php foreach ($filterOptions['types'] as $tp): ?>
                            <option value="<?= e($tp) ?>"><?= e($tp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Location</label>
                    <select class="form-select form-select-sm" id="filterLocation">
                        <option value="">All Locations</option>
                        <?php foreach ($filterOptions['locations'] as $loc): ?>
                            <option value="<?= e($loc) ?>"><?= e($loc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">SMS Status</label>
                    <select class="form-select form-select-sm" id="filterSmsStatus">
                        <option value="">All</option>
                        <option value="allowed">Allowed</option>
                        <option value="opted_out">Opt-out</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">Active</option>
                        <option value="archived">Archived</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Actions & Selection Toolbar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 bg-white p-2 rounded border shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <div class="form-check ms-2 mb-0">
                <input class="form-check-input" type="checkbox" id="checkSelectPage">
                <label class="form-check-label small" for="checkSelectPage">Select Page</label>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectAllFiltered">
                <i class="bi bi-check-all"></i> Select All Filtered (<span id="btnSelectAllFilteredCount">0</span>)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearSelection" style="display:none;">
                <i class="bi bi-x"></i> Clear Selection
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="btnBulkDelete" title="Select one or more contacts using checkboxes to delete">
                <i class="bi bi-trash me-1"></i> Delete Selected (<span id="btnBulkDeleteCount">0</span>)
            </button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" id="btnExportCsv">
                <i class="bi bi-download me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-success btn-sm px-3" id="btnOpenSendSmsModal">
                <i class="bi bi-chat-left-dots-fill me-1"></i> Send SMS Broadcast
            </button>
        </div>
    </div>

    <!-- Contacts Table Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="contactsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <span class="visually-hidden">Select</span>
                        </th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Exam Types</th>
                        <th>Years</th>
                        <th>Locations</th>
                        <th>School</th>
                        <th>WhatsApp Group / Source</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="contactsTableBody">
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div> Loading contacts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2" id="paginationBar">
            <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 contacts</div>
            <ul class="pagination pagination-sm mb-0" id="paginationControls"></ul>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- CSV IMPORT MODAL (MULTI-STEP)                                         -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="importCsvModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="importCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="importCsvModalLabel">
                    <i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Import Contacts from WhatsApp Group CSV
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- STEP 1: Upload & Group Input -->
                <div id="importStep1">
                    <p class="text-muted small mb-3">
                        Upload contact phone lists exported from WhatsApp groups. <strong>Name and School are optional</strong>.
                        Phone number, Exam Year, Exam Type, and Location are required.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">WhatsApp Group / Source (Optional)</label>
                        <input type="text" class="form-control" id="importSourceGroup" placeholder="e.g. 2026 IGCSE Kandy (or leave blank to infer from filename)">
                        <div class="form-text small">If left blank, the system will infer the group from the CSV filename.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Select CSV File</label>
                        <input type="file" class="form-control" id="importFileInput" accept=".csv,.txt">
                        <div class="form-text small">Accepts comma-separated .csv files. Maximum size 15MB.</div>
                    </div>

                    <div class="card bg-light border-0 p-3 rounded mb-3">
                        <h6 class="small fw-bold mb-2">Supported CSV Columns:</h6>
                        <code class="small text-dark">phone_number, exam_year, exam_type, location, [name], [school]</code>
                        <div class="small text-muted mt-2">
                            Allowed Locations: <span class="badge bg-secondary"><?= implode('</span> <span class="badge bg-secondary">', $allowedLocations) ?></span>
                        </div>
                    </div>

                    <div id="importUploadAlert" class="alert d-none small mb-0"></div>
                </div>

                <!-- STEP 2: Preview Summary & Validation -->
                <div id="importStep2" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <div>
                            <h6 class="fw-bold mb-0" id="previewFileName">File Preview</h6>
                            <span class="small text-muted">WhatsApp Group: <strong id="previewSourceGroup">—</strong></span>
                        </div>
                        <span class="badge bg-primary fs-6" id="previewTotalRowsBadge">0 Total Rows</span>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Valid Rows</div>
                                <div class="fw-bold fs-5 text-success" id="prevValidRows">0</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Invalid Rows</div>
                                <div class="fw-bold fs-5 text-danger" id="prevInvalidRows">0</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">New Phone Contacts</div>
                                <div class="fw-bold fs-5 text-primary" id="prevNewContacts">0</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Existing Contacts</div>
                                <div class="fw-bold fs-5 text-secondary" id="prevExistingContacts">0</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">New Academic Records</div>
                                <div class="fw-bold fs-5 text-info" id="prevNewRecords">0</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Duplicate Records</div>
                                <div class="fw-bold fs-5 text-warning" id="prevDuplicateRecords">0</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Names Available / Missing</div>
                                <div class="small fw-semibold mt-1">
                                    <span class="text-success" id="prevNamesAvail">0</span> / <span class="text-muted" id="prevNamesMiss">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Schools Available / Missing</div>
                                <div class="small fw-semibold mt-1">
                                    <span class="text-success" id="prevSchoolsAvail">0</span> / <span class="text-muted" id="prevSchoolsMiss">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invalid Breakdown Alert -->
                    <div id="previewInvalidAlert" class="alert alert-warning small mb-3 d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="previewInvalidSummary">Some rows contain validation issues.</span>
                    </div>

                    <!-- Student Matching Summary Card (Shown after "Update Student Names") -->
                    <div id="studentMatchSummaryCard" class="card border-primary border-opacity-25 bg-primary bg-opacity-10 mb-3 p-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-person-check-fill me-1"></i> Student Matching Analysis
                            </h6>
                            <span class="badge bg-primary" id="badgeMatchTotal">0 Total Matches</span>
                        </div>
                        <div class="row g-2 text-center small">
                            <div class="col-4 col-md-2">
                                <div class="bg-white rounded p-1 border">
                                    <div class="text-muted" style="font-size:0.7rem;">Can Enrich</div>
                                    <div class="fw-bold text-success" id="statMatchEnrich">0</div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="bg-white rounded p-1 border">
                                    <div class="text-muted" style="font-size:0.7rem;">Matching</div>
                                    <div class="fw-bold text-info" id="statMatchAlready">0</div>
                                </div>
                            </div>
                            <div class="col-4 col-md-2">
                                <div class="bg-white rounded p-1 border">
                                    <div class="text-muted" style="font-size:0.7rem;">Conflicts</div>
                                    <div class="fw-bold text-warning" id="statMatchConflict">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white rounded p-1 border">
                                    <div class="text-muted" style="font-size:0.7rem;">Multiple Students</div>
                                    <div class="fw-bold text-danger" id="statMatchMultiple">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white rounded p-1 border">
                                    <div class="text-muted" style="font-size:0.7rem;">Not Found</div>
                                    <div class="fw-bold text-secondary" id="statMatchNotFound">0</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Contacts In Database Update Banner -->
                    <div id="existingContactsUpdateBanner" class="alert alert-info py-2 px-3 small mb-3 d-none d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <span id="existingContactsUpdateText">Found 0 existing phone contacts in database that can have their names updated with student records.</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm py-0 px-2" id="btnApplyExistingUpdates">
                            <i class="bi bi-check2-all me-1"></i> Apply Student Name Updates
                        </button>
                    </div>
                    <div id="existingContactsUpdateResult" class="alert d-none py-2 px-3 small mb-3"></div>

                    <!-- Sample Preview Table -->
                    <h6 class="small fw-bold mb-2">Sample Preview (First Rows):</h6>
                    <div class="table-responsive border rounded mb-3" style="max-height: 200px;">
                        <table class="table table-sm table-striped mb-0 small">
                            <thead class="table-light">
                                <tr id="previewTableHeaderRow">
                                    <th>Line</th>
                                    <th>Phone</th>
                                    <th>Year</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Name</th>
                                    <th>School</th>
                                    <th id="thPreviewStudentName" class="d-none">Student Match</th>
                                    <th id="thPreviewMatchStatus" class="d-none">Match Status</th>
                                </tr>
                            </thead>
                            <tbody id="previewSampleBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- STEP 3: Final Report -->
                <div id="importStep3" style="display:none;">
                    <div class="text-center py-3">
                        <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex p-3 mb-2">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                        </div>
                        <h5 class="fw-bold">Import Completed Successfully</h5>
                        <p class="text-muted small" id="reportSummaryText">All valid contacts and academic associations have been recorded.</p>
                    </div>

                    <div class="card bg-light border-0 p-3 rounded mb-3">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="small text-muted">New Contacts</div>
                                <div class="fw-bold fs-5 text-primary" id="repNewContacts">0</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Academic Records Added</div>
                                <div class="fw-bold fs-5 text-success" id="repNewRecords">0</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Duplicates Skipped</div>
                                <div class="fw-bold fs-5 text-secondary" id="repDuplicates">0</div>
                            </div>
                        </div>
                    </div>

                    <div id="repErrorDownloadContainer" class="d-none text-center mb-3">
                        <a href="#" id="repErrorDownloadLink" class="btn btn-outline-danger btn-sm" download>
                            <i class="bi bi-file-earmark-x me-1"></i> Download Error Rows CSV (<span id="repErrorCount">0</span> errors)
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <!-- Footer 1 -->
                <div id="importFooter1" class="w-100 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="btnCancelImportModal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" id="btnAnalyzeCsv">
                        <i class="bi bi-search me-1"></i> Inspect &amp; Preview
                    </button>
                </div>
                <!-- Footer 2 -->
                <div id="importFooter2" class="w-100 d-none justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBackToStep1">← Re-upload</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnMatchStudentNames">
                            <i class="bi bi-person-check me-1"></i> Update Student Names
                        </button>
                        <button type="button" class="btn btn-success btn-sm px-4" id="btnConfirmImport">
                            <i class="bi bi-check2-circle me-1"></i> Import Contacts
                        </button>
                    </div>
                </div>
                <!-- Footer 3 -->
                <div id="importFooter3" class="w-100 d-none justify-content-end">
                    <button type="button" class="btn btn-primary btn-sm px-4" data-bs-dismiss="modal" id="btnCloseImportModal">Done</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- SEND CONTROLLED SMS MODAL                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="sendSmsModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="sendSmsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="sendSmsModalLabel">
                    <i class="bi bi-chat-left-dots-fill text-success me-2"></i>Send SMS Broadcast
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Sending Progress Section (Active during dispatch) -->
                <div id="smsSendingProgress" style="display:none;" class="mb-4">
                    <div class="card border-0 bg-light p-3 rounded">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-bold" id="smsProgressStatus">Sending SMS campaign...</span>
                            <span class="badge bg-primary" id="smsProgressPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="smsProgressBar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-2">
                            <span>Sent: <strong id="smsProgressSent">0</strong> / <span id="smsProgressTotal">0</span></span>
                            <span>Failed: <strong id="smsProgressFailed" class="text-danger">0</strong></span>
                        </div>
                    </div>
                </div>

                <div id="smsComposeForm">
                    <!-- Target Recipients Summary Card -->
                    <div class="card bg-light border-0 p-3 rounded mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="small text-muted fw-semibold">Target Recipients (Strictly Deduplicated)</div>
                                <h4 class="mb-0 fw-bold text-dark" id="smsModalUniqueCount">Calculating...</h4>
                            </div>
                            <span class="badge bg-success bg-opacity-25 text-success border border-success p-2 small">
                                <i class="bi bi-shield-check me-1"></i> 1 SMS per phone number
                            </span>
                        </div>
                        <div class="row g-2 text-center mt-2 small pt-2 border-top" id="smsExclusionBreakdown">
                            <div class="col-3 border-end">
                                <div class="text-muted" style="font-size:0.75rem;">Selected</div>
                                <strong id="smsModalTotalCount">0</strong>
                            </div>
                            <div class="col-3 border-end">
                                <div class="text-success" style="font-size:0.75rem;">Eligible</div>
                                <strong id="smsModalEligibleCount" class="text-success">0</strong>
                            </div>
                            <div class="col-3 border-end">
                                <div class="text-danger" style="font-size:0.75rem;">Blocked</div>
                                <strong id="smsModalBlockedCount" class="text-danger">0</strong>
                            </div>
                            <div class="col-3">
                                <div class="text-secondary" style="font-size:0.75rem;">Opted Out</div>
                                <strong id="smsModalOptedOutCount" class="text-secondary">0</strong>
                            </div>
                        </div>
                        <div class="small text-muted mt-2" style="font-size:0.75rem;">
                            Blocked contacts and opted-out contacts are strictly excluded on the server.
                        </div>
                    </div>

                    <!-- Recent Duplicate Campaign Warning Banner -->
                    <div id="smsDuplicateWarningBanner" class="alert alert-warning small mb-3 d-none">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Previous Campaign Duplicate Warning</div>
                                <div id="smsDuplicateWarningText">Some recipients received a campaign recently.</div>
                                <div class="mt-2 d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelDuplicateSend" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-sm btn-warning" id="btnProceedDuplicateSend">Proceed Anyway</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campaign Name -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Campaign Name</label>
                        <input type="text" class="form-control form-control-sm" id="smsCampaignName" placeholder="e.g. 2026 IGCSE Kandy Revision Class Notice">
                    </div>

                    <!-- Gateway Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select SMS Gateway</label>
                        <div class="row g-2">
                            <?php foreach ($availableGateways as $gwKey => $gwInfo): ?>
                            <div class="col-sm-6">
                                <label class="card h-100 p-3 border rounded cursor-pointer gateway-select-card" for="gw_<?= e($gwKey) ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1" type="radio" name="sms_gateway" id="gw_<?= e($gwKey) ?>" value="<?= e($gwKey) ?>" <?= $gwKey === 'ipromo' || empty($availableGateways['sms_gate_android']['available']) ? 'checked' : '' ?>>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <strong class="small"><?= e($gwInfo['name']) ?></strong>
                                                <span class="badge <?= e($gwInfo['badge_class']) ?> small"><?= e($gwInfo['status_text']) ?></span>
                                            </div>
                                            <div class="text-muted small mt-1" style="font-size: 0.75rem;"><?= e($gwInfo['device_info']) ?></div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Message Text</label>
                        <textarea class="form-control" id="smsMessageText" rows="4" placeholder="Type your SMS message here..."></textarea>
                    </div>

                    <!-- Live Calculation Bar -->
                    <div class="card border-0 bg-light p-3 rounded mb-3">
                        <div class="row g-2 text-center">
                            <div class="col-3 border-end">
                                <div class="small text-muted">Characters</div>
                                <div class="fw-bold" id="smsCharCount">0</div>
                            </div>
                            <div class="col-3 border-end">
                                <div class="small text-muted">Encoding</div>
                                <div class="fw-bold" id="smsEncoding">GSM-7</div>
                            </div>
                            <div class="col-3 border-end">
                                <div class="small text-muted">SMS Parts</div>
                                <div class="fw-bold" id="smsPartsCount">1</div>
                            </div>
                            <div class="col-3">
                                <div class="small text-muted">Total Units</div>
                                <div class="fw-bold text-primary" id="smsTotalUnits">0</div>
                            </div>
                        </div>
                    </div>

                    <div id="smsModalAlert" class="alert d-none small mb-0"></div>
                </div>
            </div>
            <div class="modal-footer bg-light" id="smsModalFooter">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm px-4" id="btnConfirmSendSms">
                    <i class="bi bi-send-fill me-1"></i> Send Campaign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- MANAGE LOCATIONS MODAL                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="locationsModal" tabindex="-1" aria-labelledby="locationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="locationsModalLabel">
                    <i class="bi bi-geo-alt text-primary me-2"></i>Configured Allowed Locations
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Locations accepted during CSV imports. If a CSV row specifies an unknown location, it will be flagged as <code>Invalid Location</code>.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Current Locations</label>
                    <div id="locationsBadgesContainer" class="d-flex flex-wrap gap-2 p-2 border rounded bg-light mb-2"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Add New Location</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="newLocationInput" placeholder="e.g. Colombo, Negombo, Galle...">
                        <button class="btn btn-outline-primary" type="button" id="btnAddLocation">Add</button>
                    </div>
                </div>
                <div id="locationsModalAlert" class="alert d-none small mb-0"></div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btnSaveLocations">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- QUICK CONTACT EDIT MODAL                                               -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="contactEditModal" tabindex="-1" aria-labelledby="contactEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="contactEditModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Contact
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="contactEditForm">
                    <input type="hidden" id="editContactId">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Name</label>
                        <input type="text" class="form-control form-control-sm" id="editContactName" placeholder="Student name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" class="form-control form-control-sm" id="editContactPhone" required>
                        <div class="form-text small">Standard Sri Lankan mobile number (e.g. 0771234567).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">School</label>
                        <input type="text" class="form-control form-control-sm" id="editContactSchool" placeholder="School or college">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select form-select-sm" id="editContactStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">SMS Status</label>
                        <select class="form-select form-select-sm" id="editContactSmsStatus">
                            <option value="allowed">Allowed (Normal SMS Delivery)</option>
                            <option value="opted_out">Opted Out (Excluded from Broadcasts)</option>
                            <option value="blocked">Blocked (Server-side Lockdown, Never SMS)</option>
                        </select>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="editContactOptOut">
                        <label class="form-check-label small" for="editContactOptOut">SMS Opt-Out (Do not send marketing/broadcast SMS)</label>
                    </div>
                    <div id="contactEditAlert" class="alert d-none small mb-0"></div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btnSaveContactEdit">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- WHATSAPP GROUP STATS MODAL                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="groupStatsModal" tabindex="-1" aria-labelledby="groupStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="groupStatsModalLabel">
                    <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>WhatsApp Group Statistics &amp; Coverage
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Overview of all WhatsApp groups, contacts count, academic records, exam years, and latest import timestamps.
                </p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0" id="groupStatsTable">
                        <thead class="table-light">
                            <tr>
                                <th>WhatsApp Group / Source</th>
                                <th class="text-center">Contacts</th>
                                <th class="text-center">Records</th>
                                <th>Exam Types</th>
                                <th>Years</th>
                                <th>Locations</th>
                                <th>First Seen</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody id="groupStatsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading group stats...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- ADMIN TEST SMS MODAL                                                    -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="testSmsModal" tabindex="-1" aria-labelledby="testSmsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="testSmsModalLabel">
                    <i class="bi bi-send-check text-warning me-2"></i>Admin Test SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Send an immediate single test SMS to verify gateway connectivity, balance, and message delivery without affecting contact campaign records or quotas.
                </p>
                <div id="testSmsAlert" class="alert d-none small mb-3"></div>
                <form id="testSmsForm">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Destination Phone Number</label>
                        <input type="text" class="form-control form-control-sm" id="testSmsPhone" placeholder="0771234567" required>
                        <div class="form-text small">Any valid Sri Lankan mobile number.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">SMS Gateway</label>
                        <select class="form-select form-select-sm" id="testSmsGateway">
                            <option value="ipromo">iPromo SMS Gateway (Primary)</option>
                            <option value="sms_gate_android">SMS Gate Android (Local SIM)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Test Message Text</label>
                        <textarea class="form-control form-control-sm" id="testSmsMessage" rows="3" placeholder="Test SMS from Edexcel College Admin Panel..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm px-3" id="btnSendTestSms">
                    <i class="bi bi-send me-1"></i> Send Test SMS
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
window.INITIAL_LOCATIONS = <?= json_encode($allowedLocations) ?>;
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<script src="<?= BASE_URL ?>assets/js/phone_contacts.js?v=<?= filemtime(__DIR__ . '/../assets/js/phone_contacts.js') ?>" defer></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
