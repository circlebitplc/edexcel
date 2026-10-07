<?php
declare(strict_types=1);

/**
 * Admin Teacher SMS Permissions & Quota Management
 *
 * Controls teacher SMS broadcast access, monthly quotas, group restrictions,
 * and enforces strict server-side iPromo lockdown.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\TeacherSmsService;
use Edexcel\Services\PhoneContactService;
use Edexcel\Services\SmsService;

require_admin();

TeacherSmsService::ensureSchema($pdo);
PhoneContactService::ensureSchema($pdo);

$page_title = 'Teacher SMS Permissions';
$current_page = 'sms_teacher_permissions.php';

$teachers = TeacherSmsService::getAllTeachersWithPermissions($pdo);
$filterOptions = PhoneContactService::getDistinctFilterOptions($pdo);
$allowedLocations = PhoneContactService::getAllowedLocations($pdo);
$isGlobalTeacherSmsEnabled = TeacherSmsService::isTeacherSmsGloballyEnabled($pdo);
$switchAuditLogs = TeacherSmsService::getSwitchAuditLogs($pdo, 10);

// Master iPromo status check
$ipromoActive = function_exists('ipromo_enabled') && ipromo_enabled($pdo);
$ipromoSetup = function_exists('ipromo_configured') && ipromo_configured($pdo);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 sms-teacher-perms-shell">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/bulk_sms.php">Communication</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Teacher SMS Permissions</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-shield-lock text-primary me-2"></i>Teacher SMS Access &amp; Quotas</h1>
            <p class="text-muted small mb-0">Control teacher broadcast privileges, monthly quotas, and academic group restrictions. Enforced server-side.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>admin/phone_contacts.php" class="btn btn-outline-secondary btn-sm">
                ← Phone Contacts
            </a>
            <a href="<?= BASE_URL ?>admin/settings.php?tab=ipromo" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-gear me-1"></i> iPromo Gateway Setup
            </a>
        </div>
    </div>

    <!-- Global Emergency SMS Broadcast Kill-Switch -->
    <div class="card border-0 <?= $isGlobalTeacherSmsEnabled ? 'bg-light' : 'bg-danger bg-opacity-10 border border-danger' ?> p-3 rounded shadow-sm mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle <?= $isGlobalTeacherSmsEnabled ? 'bg-success bg-opacity-10 text-success' : 'bg-danger text-white' ?> p-3">
                    <i class="bi <?= $isGlobalTeacherSmsEnabled ? 'bi-shield-check' : 'bi-shield-slash' ?> fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 <?= $isGlobalTeacherSmsEnabled ? 'text-dark' : 'text-danger' ?>">
                        <?= $isGlobalTeacherSmsEnabled ? 'Global Teacher SMS Status: ENABLED' : 'EMERGENCY LOCKDOWN ACTIVE: ALL TEACHER SMS DISABLED' ?>
                    </h5>
                    <p class="text-muted small mb-0">
                        <?= $isGlobalTeacherSmsEnabled 
                            ? 'Authorized teachers with permissions can dispatch bulk SMS within their monthly limits.' 
                            : 'All teacher SMS operations are strictly blocked server-side by system administrators. Existing campaigns and sending interfaces are locked.' ?>
                    </p>
                </div>
            </div>
            <div>
                <button type="button" class="btn <?= $isGlobalTeacherSmsEnabled ? 'btn-outline-danger' : 'btn-success' ?> btn-sm px-3" id="btnToggleGlobalTeacherSms" data-enabled="<?= $isGlobalTeacherSmsEnabled ? '1' : '0' ?>">
                    <i class="bi <?= $isGlobalTeacherSmsEnabled ? 'bi-slash-circle' : 'bi-play-circle' ?> me-1"></i>
                    <?= $isGlobalTeacherSmsEnabled ? 'Emergency Lockdown (Disable All)' : 'Lift Lockdown (Enable All)' ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Emergency Switch Audit History -->
    <?php if ($switchAuditLogs !== []): ?>
    <div class="card border-0 bg-white p-3 rounded shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-bold mb-0 text-muted small"><i class="bi bi-clock-history me-1"></i>Emergency Switch Audit Log</h6>
            <span class="badge bg-light text-muted border"><?= count($switchAuditLogs) ?> Recent Entries</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Reason / Audit Note</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($switchAuditLogs as $log): ?>
                        <tr>
                            <td><?= e($log['created_at']) ?></td>
                            <td><strong><?= e($log['admin_name'] ?: $log['admin_username'] ?: 'Admin #' . $log['admin_user_id']) ?></strong></td>
                            <td>
                                <?php if (!empty($log['new_status'])): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger">Disabled (Lockdown)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($log['reason'] ?: '—') ?></td>
                            <td><span class="font-monospace text-muted"><?= e($log['ip_address'] ?: '—') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gateway Notice Card -->
    <div class="card border-0 bg-light p-3 rounded shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 text-info p-3">
                    <i class="bi bi-broadcast-pin fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">Teacher Gateway Policy: Strictly iPromo Marketing Only</h6>
                    <p class="text-muted small mb-0">
                        Teachers with SMS permission may <strong>ONLY</strong> dispatch via the <strong>iPromo Marketing Gateway</strong>.
                        SMS-Gate (Android hardware) is strictly reserved for administrators.
                    </p>
                </div>
            </div>
            <div>
                <?php if ($ipromoActive && $ipromoSetup): ?>
                    <span class="badge bg-success bg-opacity-25 text-success border border-success p-2">
                        <i class="bi bi-check-circle me-1"></i> iPromo Active
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger p-2">
                        <i class="bi bi-exclamation-triangle me-1"></i> iPromo Inactive / Incomplete
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Teachers Table Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="card-title mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Teacher Accounts &amp; SMS Controls</h6>
            <span class="badge bg-light text-dark border"><?= count($teachers) ?> teachers</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Teacher Name</th>
                            <th>Username / Contact</th>
                            <th class="text-center">SMS Access</th>
                            <th>Gateway</th>
                            <th class="text-end">Monthly Quota</th>
                            <th class="text-end">Used this Month</th>
                            <th class="text-end">Remaining</th>
                            <th>Privileges</th>
                            <th>Allowed Scope</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-2 d-block mb-2 text-muted"></i>
                                No teacher accounts found in the system.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($teachers as $t): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= e($t['teacher_name'] ?: $t['username']) ?></strong>
                                </td>
                                <td>
                                    <div class="small text-muted font-monospace"><?= e($t['username']) ?></div>
                                    <?php if (!empty($t['teacher_phone'])): ?>
                                        <div class="small text-muted"><?= e($t['teacher_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($t['sms_access'])): ?>
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="bi bi-check-lg"></i> ON</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-25 text-secondary border">OFF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark font-monospace">iPromo (Locked)</span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= number_format((int)$t['monthly_limit']) ?>
                                </td>
                                <td class="text-end text-primary fw-semibold">
                                    <?= number_format((int)$t['used_this_month']) ?>
                                </td>
                                <td class="text-end">
                                    <?php if ((int)$t['remaining_this_month'] <= 0 && (int)$t['monthly_limit'] > 0): ?>
                                        <span class="badge bg-danger">0 (Limit Exceeded)</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-success"><?= number_format((int)$t['remaining_this_month']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="<?= !empty($t['can_send_sms']) ? 'text-success fw-semibold' : 'text-muted' ?>">
                                            <i class="bi <?= !empty($t['can_send_sms']) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i> Send SMS
                                        </span>
                                        <span class="<?= !empty($t['can_view_contacts']) ? 'text-success fw-semibold' : 'text-muted' ?>">
                                            <i class="bi <?= !empty($t['can_view_contacts']) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i> View Contacts
                                        </span>
                                        <span class="<?= !empty($t['can_select_contacts']) ? 'text-success fw-semibold' : 'text-muted' ?>">
                                            <i class="bi <?= !empty($t['can_select_contacts']) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i> Select Contacts
                                        </span>
                                    </div>
                                </td>
                                <td class="small">
                                    <?php
                                    $hasRestrictions = !empty($t['allowed_exam_years']) || !empty($t['allowed_exam_types']) || !empty($t['allowed_locations']) || !empty($t['allowed_whatsapp_groups']);
                                    ?>
                                    <?php if (!$hasRestrictions): ?>
                                        <span class="badge bg-light text-muted border">All Groups</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-1">
                                            <?php if (!empty($t['allowed_whatsapp_groups'])): ?>
                                                <div><span class="text-muted">WhatsApp Groups:</span> <span class="badge bg-secondary text-white"><?= e($t['allowed_whatsapp_groups']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($t['allowed_exam_years'])): ?>
                                                <div><span class="text-muted">Years:</span> <span class="badge bg-light text-dark border"><?= e($t['allowed_exam_years']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($t['allowed_exam_types'])): ?>
                                                <div><span class="text-muted">Exams:</span> <span class="badge bg-info text-dark"><?= e($t['allowed_exam_types']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($t['allowed_locations'])): ?>
                                                <div><span class="text-muted">Locs:</span> <span class="badge bg-light text-dark border"><?= e($t['allowed_locations']) ?></span></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-primary btn-sm py-1 px-3 btn-edit-teacher-perm"
                                        data-userid="<?= (int)$t['user_id'] ?>"
                                        data-name="<?= e($t['teacher_name'] ?: $t['username']) ?>"
                                        data-access="<?= (int)$t['sms_access'] ?>"
                                        data-limit="<?= (int)$t['monthly_limit'] ?>"
                                        data-cansend="<?= (int)$t['can_send_sms'] ?>"
                                        data-canview="<?= (int)$t['can_view_contacts'] ?>"
                                        data-canselect="<?= (int)$t['can_select_contacts'] ?>"
                                        data-groups="<?= e($t['allowed_whatsapp_groups'] ?? '') ?>"
                                        data-years="<?= e($t['allowed_exam_years'] ?? '') ?>"
                                        data-types="<?= e($t['allowed_exam_types'] ?? '') ?>"
                                        data-locations="<?= e($t['allowed_locations'] ?? '') ?>">
                                        <i class="bi bi-sliders"></i> Configure
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- CONFIGURE TEACHER PERMISSIONS MODAL                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="teacherPermModal" tabindex="-1" aria-labelledby="teacherPermModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="teacherPermModalLabel">
                    <i class="bi bi-shield-lock text-primary me-2"></i>Configure Teacher SMS Access: <span id="modalTeacherName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTeacherPerm">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_user_id" id="permTeacherUserId">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card p-3 border rounded h-100 bg-light">
                                <h6 class="small fw-bold mb-3 text-dark">Master Switches</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="sms_access" value="1" id="swSmsAccess">
                                    <label class="form-check-label fw-semibold small" for="swSmsAccess">SMS Access (Master ON/OFF)</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="can_send_sms" value="1" id="swCanSend">
                                    <label class="form-check-label fw-semibold small" for="swCanSend">Can Send SMS</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="can_view_contacts" value="1" id="swCanView">
                                    <label class="form-check-label fw-semibold small" for="swCanView">Can View Contacts</label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="can_select_contacts" value="1" id="swCanSelect">
                                    <label class="form-check-label fw-semibold small" for="swCanSelect">Can Select Contacts</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3 border rounded h-100 bg-light">
                                <h6 class="small fw-bold mb-3 text-dark">Quota &amp; Gateway</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Monthly SMS Limit (Units)</label>
                                    <input type="number" class="form-control" name="monthly_limit" id="permMonthlyLimit" min="0" step="50" placeholder="e.g. 500">
                                    <div class="form-text small">Counts SMS segments/units. Campaigns exceeding this hard limit are rejected.</div>
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold text-muted">Gateway Assignment</label>
                                    <input type="text" class="form-control form-control-sm bg-white" value="iPromo Marketing (Locked)" readonly>
                                    <div class="form-text small text-info">Teachers are strictly locked to iPromo server-side.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Group Restrictions Card -->
                    <div class="card p-3 border rounded bg-light mb-3">
                        <h6 class="small fw-bold mb-2 text-dark">Contact Group Restrictions (Optional)</h6>
                        <p class="text-muted small mb-3">Leave blank to allow all groups, or specify comma-separated allowed values.</p>
                        <div class="row g-2">
                            <div class="col-12 mb-2">
                                <label class="form-label small fw-semibold">Allowed WhatsApp Groups</label>
                                <input type="text" class="form-control form-control-sm" name="allowed_whatsapp_groups" id="permAllowedGroups" placeholder="e.g. 2026 IGCSE Kandy, 2027 IAL Online">
                                <div class="form-text small" style="font-size:0.75rem;">Comma-separated list of WhatsApp groups the teacher is authorized to contact. Leave blank to allow all.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Allowed Exam Years</label>
                                <input type="text" class="form-control form-control-sm" name="allowed_exam_years" id="permAllowedYears" placeholder="e.g. 2026, 2027">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Allowed Exam Types</label>
                                <input type="text" class="form-control form-control-sm" name="allowed_exam_types" id="permAllowedTypes" placeholder="e.g. IGCSE, IAL">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Allowed Locations</label>
                                <input type="text" class="form-control form-control-sm" name="allowed_locations" id="permAllowedLocations" placeholder="e.g. Kandy">
                            </div>
                        </div>
                    </div>

                    <div id="modalTeacherPermAlert" class="alert d-none small mb-0"></div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnSaveTeacherPerm">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    const modalEl = document.getElementById('teacherPermModal');
    const form = document.getElementById('formTeacherPerm');
    const alertEl = document.getElementById('modalTeacherPermAlert');

    document.querySelectorAll('.btn-edit-teacher-perm').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('permTeacherUserId').value = this.dataset.userid;
            document.getElementById('modalTeacherName').textContent = this.dataset.name;
            document.getElementById('swSmsAccess').checked = this.dataset.access === '1';
            document.getElementById('swCanSend').checked = this.dataset.cansend === '1';
            document.getElementById('swCanView').checked = this.dataset.canview === '1';
            document.getElementById('swCanSelect').checked = this.dataset.canselect === '1';
            document.getElementById('permMonthlyLimit').value = this.dataset.limit || '0';
            document.getElementById('permAllowedGroups').value = this.dataset.groups || '';
            document.getElementById('permAllowedYears').value = this.dataset.years || '';
            document.getElementById('permAllowedTypes').value = this.dataset.types || '';
            document.getElementById('permAllowedLocations').value = this.dataset.locations || '';
            alertEl.classList.add('d-none');
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    });

    const btnToggleGlobal = document.getElementById('btnToggleGlobalTeacherSms');
    if (btnToggleGlobal) {
        btnToggleGlobal.addEventListener('click', async function() {
            const current = this.getAttribute('data-enabled') === '1';
            const actionText = current ? 'DISABLE and lock all teacher SMS broadcasts' : 'ENABLE teacher SMS broadcasts';
            const reason = prompt(`Are you sure you want to ${actionText}?\n\nPlease enter the reason / audit note:`, current ? 'Emergency maintenance lockdown' : 'Broadcast authorization approved');
            if (reason === null) {
                return; // Cancelled
            }
            btnToggleGlobal.disabled = true;
            const fd = new FormData();
            fd.append('action', 'toggle_global_teacher_sms');
            fd.append('enabled', current ? '0' : '1');
            fd.append('reason', reason);
            fd.append('csrf_token', <?= json_encode(csrf_token()) ?>);
            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    location.reload();
                } else {
                    btnToggleGlobal.disabled = false;
                    alert(data.error || 'Failed to toggle global status.');
                }
            } catch (err) {
                btnToggleGlobal.disabled = false;
                alert('Network error.');
            }
        });
    }

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveTeacherPerm');
            btn.disabled = true;

            const fd = new FormData(form);
            fd.append('action', 'save_teacher_permissions');

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                btn.disabled = false;
                if (data.success) {
                    location.reload();
                } else {
                    alertEl.className = 'alert alert-danger small mb-0';
                    alertEl.textContent = data.error || 'Failed to update teacher permissions.';
                    alertEl.classList.remove('d-none');
                }
            } catch (err) {
                btn.disabled = false;
                alertEl.className = 'alert alert-danger small mb-0';
                alertEl.textContent = 'Network error.';
                alertEl.classList.remove('d-none');
            }
        });
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
