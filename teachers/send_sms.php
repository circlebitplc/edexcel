<?php
declare(strict_types=1);

/**
 * Teacher SMS Broadcast Center
 *
 * Dedicated controlled SMS dispatch interface for teachers with
 * server-side quota checks, group scope restrictions, and iPromo-only enforcement.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\PhoneContactService;
use Edexcel\Services\BulkSmsService;

require_teacher();

TeacherSmsService::ensureSchema($pdo);
PhoneContactService::ensureSchema($pdo);

$teacherUserId = (int)($_SESSION['user_id'] ?? 0);
$perm = TeacherSmsService::getTeacherPermissions($pdo, $teacherUserId);
$usage = TeacherSmsService::getTeacherMonthlyUsage($pdo, $teacherUserId);
$isGloballyEnabled = TeacherSmsService::isTeacherSmsGloballyEnabled($pdo);

$hasSmsAccess = $perm && !empty($perm['sms_access']) && !empty($perm['can_send_sms']);
$canViewContacts = $perm && !empty($perm['can_view_contacts']);
$canSelectContacts = $perm && !empty($perm['can_select_contacts']);

$allowedGroups = $perm['allowed_groups_array'] ?? $perm['allowed_whatsapp_groups_array'] ?? [];
$allowedYears = $perm['allowed_years_array'] ?? [];
$allowedTypes = $perm['allowed_types_array'] ?? [];
$allowedLocations = $perm['allowed_locations_array'] ?? [];

$page_title = 'Send SMS Broadcast';
$current_page = 'send_sms.php';

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 teacher-sms-shell" style="max-width: 1100px;">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">SMS Broadcast</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-chat-left-dots text-primary me-2"></i>Send SMS Broadcast</h1>
            <p class="text-muted small mb-0">Broadcast class notices and announcements to students via iPromo SMS within your assigned monthly quota.</p>
        </div>
        <div>
            <span class="badge bg-info text-dark font-monospace p-2">
                <i class="bi bi-shield-check me-1"></i> Gateway: iPromo Marketing
            </span>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="teacherAlert" class="alert d-none mb-4" role="alert"></div>

    <?php if (!$hasSmsAccess): ?>
    <div class="card shadow-sm border-0 p-5 text-center my-4">
        <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex p-3 mx-auto mb-3">
            <i class="bi bi-shield-lock-fill fs-1"></i>
        </div>
        <h4 class="fw-bold">SMS Broadcast Privilege Not Active</h4>
        <p class="text-muted mx-auto" style="max-width: 500px;">
            Your teacher account currently does not have active SMS broadcast access.
            Please contact the Edexcel College administrator to enable SMS privileges and assign a monthly SMS quota.
        </p>
        <div>
            <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline-secondary btn-sm">
                ← Return to Dashboard
            </a>
        </div>
    </div>
    <?php else: ?>

    <?php if (!$isGloballyEnabled): ?>
    <div class="card shadow-sm border-0 border-start border-danger border-4 p-4 mb-4 bg-white">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-3">
                <i class="bi bi-shield-slash fs-2"></i>
            </div>
            <div>
                <h5 class="fw-bold text-danger mb-1">Teacher SMS Broadcasts Temporarily Suspended</h5>
                <p class="text-muted small mb-0">
                    The administration has placed teacher SMS broadcasts on temporary emergency lockdown.
                    You cannot dispatch SMS campaigns at this time. Please contact administrative staff for updates.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Monthly Quota Statistics Banner -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3 text-secondary">
                        <i class="bi bi-speedometer fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Monthly Limit</div>
                        <h4 class="mb-0 fw-bold"><?= number_format((int)$usage['monthly_limit']) ?> <small class="fs-6 text-muted">units</small></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 text-primary">
                        <i class="bi bi-send-check fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Used This Month</div>
                        <h4 class="mb-0 fw-bold text-primary"><?= number_format((int)$usage['used_units']) ?> <small class="fs-6 text-muted">units</small></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle <?= $usage['remaining_units'] > 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?> p-3 me-3">
                        <i class="bi <?= $usage['remaining_units'] > 0 ? 'bi-check2-circle' : 'bi-exclamation-octagon' ?> fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Remaining Quota</div>
                        <h4 class="mb-0 fw-bold <?= $usage['remaining_units'] > 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format((int)$usage['remaining_units']) ?> <small class="fs-6 text-muted">units</small>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Hard Limit Alert if 0 remaining -->
    <?php if ($usage['remaining_units'] <= 0): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
        <div>
            <strong>Monthly SMS limit reached.</strong>
            You have used all <?= number_format((int)$usage['monthly_limit']) ?> allocated SMS units for this calendar month.
            New broadcasts cannot be dispatched until the next month or until an administrator increases your quota.
        </div>
    </div>
    <?php endif; ?>

    <!-- Broadcast Composition Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Compose Broadcast</h5>
            <span class="badge bg-light text-dark border">iPromo Marketing</span>
        </div>
        <div class="card-body p-4">
            <!-- Progress Bar during Sending -->
            <div id="teacherSendingProgress" style="display:none;" class="mb-4">
                <div class="card border-0 bg-light p-3 rounded">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold" id="teacherProgressStatus">Sending SMS broadcast...</span>
                        <span class="badge bg-primary" id="teacherProgressPercent">0%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="teacherProgressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-2">
                        <span>Sent: <strong id="teacherProgressSent">0</strong> / <span id="teacherProgressTotal">0</span></span>
                        <span>Failed: <strong id="teacherProgressFailed" class="text-danger">0</strong></span>
                    </div>
                </div>
            </div>

            <form id="teacherSmsForm">
                <?= csrf_field() ?>

                <!-- Target Groups Selector -->
                <div class="card bg-light border-0 p-3 rounded mb-3">
                    <h6 class="small fw-bold text-dark mb-2">Target Student Groups</h6>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">WhatsApp Group</label>
                            <select class="form-select form-select-sm" id="targetSourceGroup">
                                <option value="">All Allowed Groups</option>
                                <?php
                                $groupsToShow = $allowedGroups !== [] ? $allowedGroups : PhoneContactService::getDistinctFilterOptions($pdo)['groups'];
                                foreach ($groupsToShow as $grp): ?>
                                    <option value="<?= e($grp) ?>"><?= e($grp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Exam Year</label>
                            <select class="form-select form-select-sm" id="targetExamYear">
                                <option value="">All Allowed Years</option>
                                <?php
                                $yearsToShow = $allowedYears !== [] ? $allowedYears : [2024, 2025, 2026, 2027, 2028];
                                foreach ($yearsToShow as $y): ?>
                                    <option value="<?= (int)$y ?>"><?= (int)$y ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Exam Type</label>
                            <select class="form-select form-select-sm" id="targetExamType">
                                <option value="">All Allowed Exams</option>
                                <?php
                                $typesToShow = $allowedTypes !== [] ? $allowedTypes : ['IGCSE', 'IAL'];
                                foreach ($typesToShow as $tp): ?>
                                    <option value="<?= e($tp) ?>"><?= e($tp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Location</label>
                            <select class="form-select form-select-sm" id="targetLocation">
                                <option value="">All Allowed Locations</option>
                                <?php
                                $locsToShow = $allowedLocations !== [] ? $allowedLocations : ['Kandy', 'Kurunegala', 'Online'];
                                foreach ($locsToShow as $l): ?>
                                    <option value="<?= e($l) ?>"><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <span class="small text-muted">
                            Unique Recipients (Deduplicated): <strong class="text-dark" id="teacherRecipientsCount">Calculating...</strong>
                        </span>
                        <span class="badge bg-success bg-opacity-25 text-success border border-success small">
                            <i class="bi bi-shield-check me-1"></i> Exact Duplicate Numbers Filtered
                        </span>
                    </div>
                </div>

                <!-- Campaign Label -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notice Subject / Campaign Name</label>
                    <input type="text" class="form-control form-control-sm" id="teacherCampaignName" placeholder="e.g. Special Physics Revision Session Notice">
                </div>

                <!-- Message Body -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">SMS Message</label>
                    <textarea class="form-control" id="teacherMessageText" rows="4" placeholder="Dear Students, please note that tomorrow's class will commence at..."></textarea>
                </div>

                <!-- Segment Calculation Metrics -->
                <div class="card border-0 bg-light p-3 rounded mb-4">
                    <div class="row g-2 text-center">
                        <div class="col-3 border-end">
                            <div class="small text-muted">Characters</div>
                            <div class="fw-bold" id="teacherCharCount">0</div>
                        </div>
                        <div class="col-3 border-end">
                            <div class="small text-muted">Encoding</div>
                            <div class="fw-bold" id="teacherEncoding">GSM-7</div>
                        </div>
                        <div class="col-3 border-end">
                            <div class="small text-muted">SMS Parts</div>
                            <div class="fw-bold" id="teacherPartsCount">1</div>
                        </div>
                        <div class="col-3">
                            <div class="small text-muted">Total Units Required</div>
                            <div class="fw-bold text-primary" id="teacherTotalUnits">0</div>
                        </div>
                    </div>
                </div>

                <!-- Limit Warning Box -->
                <div id="teacherQuotaWarning" class="alert alert-danger small d-none mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Monthly SMS Limit Exceeded.</strong>
                    Required units (<span id="warnRequired">0</span>) exceed your remaining monthly quota (<span id="warnRemaining"><?= (int)$usage['remaining_units'] ?></span>).
                    The campaign will be rejected.
                </div>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-success px-4" id="btnTeacherSendBroadcast" <?= ($usage['remaining_units'] <= 0 || !$isGloballyEnabled) ? 'disabled' : '' ?>>
                        <i class="bi bi-send-fill me-1"></i> Dispatch Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const remainingUnits = <?= (int)$usage['remaining_units'] ?>;

    const targetSourceGroup = document.getElementById('targetSourceGroup');
    const targetExamYear = document.getElementById('targetExamYear');
    const targetExamType = document.getElementById('targetExamType');
    const targetLocation = document.getElementById('targetLocation');
    const teacherMessageText = document.getElementById('teacherMessageText');
    const teacherCampaignName = document.getElementById('teacherCampaignName');
    const teacherRecipientsCount = document.getElementById('teacherRecipientsCount');
    const teacherCharCount = document.getElementById('teacherCharCount');
    const teacherEncoding = document.getElementById('teacherEncoding');
    const teacherPartsCount = document.getElementById('teacherPartsCount');
    const teacherTotalUnits = document.getElementById('teacherTotalUnits');
    const teacherQuotaWarning = document.getElementById('teacherQuotaWarning');
    const warnRequired = document.getElementById('warnRequired');
    const btnTeacherSendBroadcast = document.getElementById('btnTeacherSendBroadcast');
    const teacherAlert = document.getElementById('teacherAlert');

    let cachedUniqueCount = 0;
    let cachedTotalUnits = 0;

    function getFilters() {
        return {
            source_group: targetSourceGroup ? targetSourceGroup.value : '',
            exam_year: targetExamYear ? targetExamYear.value : '',
            exam_type: targetExamType ? targetExamType.value : '',
            location: targetLocation ? targetLocation.value : '',
            sms_opt_out: '0'
        };
    }

    async function calculateUnits() {
        const message = teacherMessageText ? teacherMessageText.value : '';
        const formData = new FormData();
        formData.append('action', 'calculate_sms');
        formData.append('csrf_token', csrfToken);
        formData.append('message', message);
        formData.append('filters', JSON.stringify(getFilters()));

        try {
            const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: formData });
            const data = await resp.json();

            if (data.success) {
                cachedUniqueCount = data.unique_recipients;
                cachedTotalUnits = data.total_units;

                if (teacherRecipientsCount) teacherRecipientsCount.textContent = `${data.unique_recipients.toLocaleString()} Contacts`;
                if (teacherCharCount) teacherCharCount.textContent = data.characters;
                if (teacherEncoding) teacherEncoding.textContent = data.encoding;
                if (teacherPartsCount) teacherPartsCount.textContent = data.segments;
                if (teacherTotalUnits) teacherTotalUnits.textContent = Number(data.total_units).toLocaleString();

                if (cachedTotalUnits > remainingUnits && remainingUnits > 0) {
                    if (warnRequired) warnRequired.textContent = cachedTotalUnits.toLocaleString();
                    if (teacherQuotaWarning) teacherQuotaWarning.classList.remove('d-none');
                    if (btnTeacherSendBroadcast) btnTeacherSendBroadcast.disabled = true;
                } else if (remainingUnits > 0) {
                    if (teacherQuotaWarning) teacherQuotaWarning.classList.add('d-none');
                    if (btnTeacherSendBroadcast) btnTeacherSendBroadcast.disabled = false;
                }
            }
        } catch (err) {
            console.error('calculateUnits error:', err);
        }
    }

    if (targetExamYear) targetExamYear.addEventListener('change', calculateUnits);
    if (targetExamType) targetExamType.addEventListener('change', calculateUnits);
    if (targetLocation) targetLocation.addEventListener('change', calculateUnits);

    if (teacherMessageText) {
        let timer;
        teacherMessageText.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(calculateUnits, 200);
        });
    }

    if (btnTeacherSendBroadcast) {
        btnTeacherSendBroadcast.addEventListener('click', async function() {
            const message = teacherMessageText ? teacherMessageText.value.trim() : '';
            if (!message) {
                alert('SMS message content cannot be empty.');
                return;
            }

            if (cachedUniqueCount <= 0) {
                alert('No active recipients match the selected group criteria.');
                return;
            }

            if (cachedTotalUnits > remainingUnits) {
                alert(`Monthly SMS limit exceeded.\nRemaining: ${remainingUnits}\nRequired: ${cachedTotalUnits}`);
                return;
            }

            if (!confirm(`Are you sure you want to broadcast this message to ${cachedUniqueCount.toLocaleString()} student contacts?\nTotal units required: ${cachedTotalUnits.toLocaleString()} SMS units.`)) {
                return;
            }

            btnTeacherSendBroadcast.disabled = true;
            btnTeacherSendBroadcast.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Initializing...';

            const formData = new FormData();
            formData.append('action', 'send_sms_campaign');
            formData.append('csrf_token', csrfToken);
            formData.append('message', message);
            formData.append('gateway', 'ipromo'); // Teachers locked to iPromo
            const idempKey = (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : 'idemp_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
            formData.append('idempotency_key', idempKey);
            formData.append('campaign_name', teacherCampaignName ? teacherCampaignName.value.trim() : '');
            formData.append('filters', JSON.stringify(getFilters()));

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: formData });
                const data = await resp.json();

                if (!data.success) {
                    btnTeacherSendBroadcast.disabled = false;
                    btnTeacherSendBroadcast.innerHTML = '<i class="bi bi-send-fill me-1"></i> Dispatch Broadcast';
                    alert(data.error || 'Campaign failed.');
                    return;
                }

                // Campaign created, process batches!
                const campaignId = data.campaign_id;
                document.getElementById('teacherSmsForm').style.display = 'none';
                document.getElementById('teacherSendingProgress').style.display = 'block';

                await processBatches(campaignId);
            } catch (err) {
                console.error(err);
                btnTeacherSendBroadcast.disabled = false;
                btnTeacherSendBroadcast.innerHTML = '<i class="bi bi-send-fill me-1"></i> Dispatch Broadcast';
                alert('Network error.');
            }
        });
    }

    async function processBatches(campaignId) {
        const progressBar = document.getElementById('teacherProgressBar');
        const progressPercent = document.getElementById('teacherProgressPercent');
        const progressStatus = document.getElementById('teacherProgressStatus');
        const progressSent = document.getElementById('teacherProgressSent');
        const progressTotal = document.getElementById('teacherProgressTotal');
        const progressFailed = document.getElementById('teacherProgressFailed');

        let isCompleted = false;

        while (!isCompleted) {
            const formData = new FormData();
            formData.append('action', 'process_batch');
            formData.append('csrf_token', csrfToken);
            formData.append('campaign_id', String(campaignId));
            formData.append('batch_size', '50');

            try {
                const resp = await fetch('/ajax/bulk_sms_action.php', { method: 'POST', body: formData });
                const data = await resp.json();

                if (!data.success) {
                    progressStatus.textContent = 'Batch paused: ' + (data.error || 'Unknown error');
                    break;
                }

                const total = data.total || 1;
                const processed = (data.sent || 0) + (data.failed || 0);
                const pct = Math.min(100, Math.round((processed / total) * 100));

                if (progressBar) progressBar.style.width = pct + '%';
                if (progressPercent) progressPercent.textContent = pct + '%';
                if (progressSent) progressSent.textContent = Number(data.sent || 0).toLocaleString();
                if (progressTotal) progressTotal.textContent = Number(total).toLocaleString();
                if (progressFailed) progressFailed.textContent = Number(data.failed || 0).toLocaleString();

                if (data.is_completed) {
                    isCompleted = true;
                    progressStatus.textContent = 'Broadcast completed successfully!';
                    if (progressBar) progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            } catch (err) {
                console.error('Batch error:', err);
                await new Promise(r => setTimeout(r, 2000));
            }
        }
    }

    document.addEventListener('DOMContentLoaded', calculateUnits);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
