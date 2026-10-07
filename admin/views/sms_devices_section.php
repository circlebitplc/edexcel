<?php
declare(strict_types=1);

/**
 * Admin Settings: SMS-Gate Android Devices & Incoming SMS Management Section
 */

require_once __DIR__ . '/../../src/Services/IncomingSmsService.php';

use Edexcel\Services\IncomingSmsService;

IncomingSmsService::ensureSchema($pdo);

// If no devices exist yet, create a default device from existing gateway settings
$existingDevices = IncomingSmsService::getDevices($pdo);
if ($existingDevices === []) {
    $existingDevId = trim((string)($settings['sms_gateway_device_id'] ?? ''));
    if ($existingDevId === '') {
        $existingDevId = 'android-gateway-01';
    }
    try {
        IncomingSmsService::createDevice($pdo, 'Edexcel SMS Phone', $existingDevId, '07XXXXXXXX');
        $existingDevices = IncomingSmsService::getDevices($pdo);
    } catch (Throwable) {
        // pass
    }
}

$webhookEndpoint = rtrim($publicAppUrl, '/') . '/api/sms-gateway/incoming.php';
?>

<article class="settings-card settings-card-advanced mt-4" id="sms-devices-section">
    <div class="settings-card-head d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3><i class="bi bi-phone-vibrate"></i> SMS-Gate Android Devices & Incoming SMS</h3>
            <p>Manage Android phones running SMS Gateway for sending and synchronizing incoming SMS messages.</p>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                <i class="bi bi-plus-circle"></i> Add Device
            </button>
            <a href="<?= BASE_URL ?>admin/incoming_sms.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-inbox"></i> View SMS Inbox
            </a>
        </div>
    </div>

    <div class="settings-card-body">
        <div id="deviceSectionAlert" class="alert alert-dismissible fade show d-none" role="alert">
            <span id="deviceSectionAlertText"></span>
            <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('deviceSectionAlert').classList.add('d-none')"></button>
        </div>

        <!-- Devices List -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="smsDevicesTable">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th>Device Name</th>
                        <th>Device ID</th>
                        <th>Phone Number</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        <th>Last SMS Sync</th>
                        <th>Incoming SMS</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="devicesTableBody">
                    <?php if ($existingDevices === []): ?>
                        <tr id="noDevicesRow">
                            <td colspan="8" class="text-center py-4 text-muted">
                                No SMS Gateway devices registered yet. Click <strong>Add Device</strong> to register an Android phone.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($existingDevices as $dev): ?>
                            <?php
                            $isEnabled = (int)$dev['is_enabled'] === 1;
                            $statusClass = $dev['status_badge'] ?? 'bg-secondary';
                            $statusLabel = $dev['status_label'] ?? ($isEnabled ? 'Connected' : 'Disabled');
                            ?>
                            <tr id="deviceRow-<?= (int)$dev['id'] ?>" data-id="<?= (int)$dev['id'] ?>">
                                <td>
                                    <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-phone text-primary"></i>
                                        <span class="device-name-text"><?= htmlspecialchars($dev['device_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                        Token: <?= htmlspecialchars($dev['api_token_prefix'] ?: '••••••••••••••••', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td>
                                    <code class="text-dark bg-light px-2 py-1 rounded small device-id-text"><?= htmlspecialchars($dev['device_id'], ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                                <td>
                                    <span class="device-phone-text font-monospace small"><?= htmlspecialchars($dev['phone_number'] ?: '-', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>" id="devStatusBadge-<?= (int)$dev['id'] ?>">
                                        <i class="bi bi-circle-fill small"></i> <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="small text-muted" id="devLastSeen-<?= (int)$dev['id'] ?>">
                                    <?= htmlspecialchars($dev['last_seen_human'] ?? 'Never', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="small text-muted" id="devLastSms-<?= (int)$dev['id'] ?>">
                                    <?= htmlspecialchars($dev['last_sms_human'] ?? 'Never', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input dev-toggle-switch" type="checkbox" role="switch"
                                               data-id="<?= (int)$dev['id'] ?>" <?= $isEnabled ? 'checked' : '' ?>
                                               title="Enable / Disable Device">
                                        <label class="form-check-label small text-muted"><?= $isEnabled ? 'Enabled' : 'Disabled' ?></label>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-edit-dev"
                                                data-id="<?= (int)$dev['id'] ?>"
                                                data-name="<?= htmlspecialchars($dev['device_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-phone="<?= htmlspecialchars($dev['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                title="Rename / Edit Details">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-regen-token"
                                                data-id="<?= (int)$dev['id'] ?>"
                                                data-name="<?= htmlspecialchars($dev['device_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Regenerate API Token">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-delete-dev"
                                                data-id="<?= (int)$dev['id'] ?>"
                                                data-name="<?= htmlspecialchars($dev['device_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Delete Device">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Android App Webhook Setup Instructions -->
        <div class="mt-4 p-3 bg-light rounded border">
            <h5 class="fw-bold text-dark fs-6 d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-gear-wide-connected text-primary"></i> Android Phone Webhook Configuration
            </h5>
            <p class="small text-muted mb-2">
                To receive incoming SMS messages automatically, configure the <strong>SMS Gateway for Android</strong> app on your phone with this webhook:
            </p>
            <div class="row g-2 small font-monospace">
                <div class="col-md-6">
                    <div class="p-2 bg-white rounded border">
                        <span class="text-muted d-block small font-sans-serif">Webhook URL:</span>
                        <strong class="text-primary"><?= htmlspecialchars($webhookEndpoint, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 bg-white rounded border">
                        <span class="text-muted d-block small font-sans-serif">Event:</span>
                        <strong class="text-dark">sms:received</strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 bg-white rounded border">
                        <span class="text-muted d-block small font-sans-serif">Method:</span>
                        <strong class="text-dark">POST (JSON)</strong>
                    </div>
                </div>
            </div>
            <div class="small text-muted mt-2">
                <i class="bi bi-shield-check text-success"></i> <strong>Authentication:</strong> In your webhook settings, pass header <code>X-Device-ID: &lt;Device ID&gt;</code> and <code>X-API-Token: &lt;Token&gt;</code> (or <code>Authorization: Bearer &lt;Token&gt;</code>).
                <br>
                <i class="bi bi-info-circle text-info"></i> <strong>Important:</strong> In Android settings, grant the app the <code>RECEIVE_SMS</code> permission and disable RCS in Google Messages so SMS webhooks trigger reliably.
            </div>
        </div>
    </div>
</article>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- Add Device Modal -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addDeviceModalLabel">
                    <i class="bi bi-phone text-primary"></i> Add SMS Gateway Device
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addDeviceForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="newDevName" class="form-label small fw-semibold">Device Name</label>
                        <input type="text" class="form-control" id="newDevName" placeholder="e.g. Edexcel Honor X9c" required>
                        <div class="form-text">A friendly label to identify this phone in logs and inbox.</div>
                    </div>

                    <div class="mb-3">
                        <label for="newDevId" class="form-label small fw-semibold">Device ID</label>
                        <input type="text" class="form-control font-monospace" id="newDevId" placeholder="e.g. android-phone-01" required>
                        <div class="form-text">Unique hardware or alphanumeric ID matching the Android app.</div>
                    </div>

                    <div class="mb-3">
                        <label for="newDevPhone" class="form-label small fw-semibold">Phone Number (Optional)</label>
                        <input type="text" class="form-control font-monospace" id="newDevPhone" placeholder="077XXXXXXX">
                        <div class="form-text">The SIM phone number in this Android device.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitAddDevice">
                        <i class="bi bi-plus-circle"></i> Save & Generate Token
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- Edit Device Modal -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="editDeviceModalLabel">
                    <i class="bi bi-pencil text-primary"></i> Edit SMS Gateway Device
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editDeviceForm">
                <input type="hidden" id="editDevIdVal" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editDevName" class="form-label small fw-semibold">Device Name</label>
                        <input type="text" class="form-control" id="editDevName" required>
                    </div>

                    <div class="mb-3">
                        <label for="editDevPhone" class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" class="form-control font-monospace" id="editDevPhone">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitEditDevice">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- Token Display Modal (Shown only once after creation or regeneration) -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="tokenDisplayModal" tabindex="-1" aria-labelledby="tokenDisplayModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-success" id="tokenDisplayModalLabel">
                    <i class="bi bi-key-fill"></i> SMS Device API Token Generated
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Copy this token now!</strong> For security reasons, the complete API token will never be displayed again.
                </div>

                <label class="form-label small fw-bold text-uppercase text-muted">API Token</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace bg-light" id="generatedTokenInput" readonly>
                    <button class="btn btn-primary" type="button" id="btnCopyGeneratedToken">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>

                <div class="small text-muted">
                    Configure this token in your Android SMS-Gate webhook header:
                    <div class="bg-light p-2 rounded mt-1 font-monospace">
                        X-API-Token: <span id="tokenHeaderSnippet">...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-success btn-sm" data-bs-dismiss="modal">I Have Saved the Token</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const getCsrf = () => document.querySelector('input[name="csrf_token"]')?.value
        || document.getElementById('csrfToken')?.value
        || '';

    const addModalEl = document.getElementById('addDeviceModal');
    const addModal = addModalEl ? new bootstrap.Modal(addModalEl) : null;

    const editModalEl = document.getElementById('editDeviceModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    const tokenModalEl = document.getElementById('tokenDisplayModal');
    const tokenModal = tokenModalEl ? new bootstrap.Modal(tokenModalEl) : null;

    function showDeviceAlert(msg, isSuccess = true) {
        const alertEl = document.getElementById('deviceSectionAlert');
        const alertText = document.getElementById('deviceSectionAlertText');
        if (!alertEl || !alertText) return;
        alertText.textContent = msg;
        alertEl.className = 'alert alert-dismissible fade show ' + (isSuccess ? 'alert-success' : 'alert-danger');
        alertEl.classList.remove('d-none');
        setTimeout(() => alertEl.classList.add('d-none'), 5000);
    }

    function showTokenModal(token) {
        const tokenInput = document.getElementById('generatedTokenInput');
        const snippet = document.getElementById('tokenHeaderSnippet');
        if (tokenInput) tokenInput.value = token;
        if (snippet) snippet.textContent = token;
        if (tokenModal) tokenModal.show();
    }

    // Copy Token button
    const btnCopyToken = document.getElementById('btnCopyGeneratedToken');
    if (btnCopyToken) {
        btnCopyToken.addEventListener('click', function () {
            const token = document.getElementById('generatedTokenInput')?.value || '';
            navigator.clipboard.writeText(token).then(() => {
                btnCopyToken.innerHTML = '<i class="bi bi-check2"></i> Copied!';
                setTimeout(() => {
                    btnCopyToken.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
                }, 2000);
            });
        });
    }

    // Add Device Form Submit
    const addForm = document.getElementById('addDeviceForm');
    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const name = document.getElementById('newDevName')?.value.trim();
            const devId = document.getElementById('newDevId')?.value.trim();
            const phone = document.getElementById('newDevPhone')?.value.trim();

            if (!name || !devId) {
                alert('Device Name and Device ID are required.');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'add_device');
            fd.append('device_name', name);
            fd.append('device_id', devId);
            fd.append('phone_number', phone);
            fd.append('csrf_token', getCsrf());

            const btn = document.getElementById('btnSubmitAddDevice');
            if (btn) btn.disabled = true;

            fetch('<?= BASE_URL ?>ajax/sms_devices_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (btn) btn.disabled = false;
                if (!data.ok) {
                    alert(data.error || 'Failed to add device.');
                    return;
                }
                if (addModal) addModal.hide();
                addForm.reset();
                showDeviceAlert('Device added successfully.');
                showTokenModal(data.token);
                setTimeout(() => window.location.reload(), 2000);
            })
            .catch(err => {
                if (btn) btn.disabled = false;
                alert('Network error: ' + err.message);
            });
        });
    }

    // Edit Device Click
    document.querySelectorAll('.btn-edit-dev').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name') || '';
            const phone = this.getAttribute('data-phone') || '';

            document.getElementById('editDevIdVal').value = id;
            document.getElementById('editDevName').value = name;
            document.getElementById('editDevPhone').value = phone;

            if (editModal) editModal.show();
        });
    });

    // Edit Device Form Submit
    const editForm = document.getElementById('editDeviceForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = document.getElementById('editDevIdVal')?.value;
            const name = document.getElementById('editDevName')?.value.trim();
            const phone = document.getElementById('editDevPhone')?.value.trim();

            const fd = new FormData();
            fd.append('action', 'rename_device');
            fd.append('id', id);
            fd.append('device_name', name);
            fd.append('phone_number', phone);
            fd.append('csrf_token', getCsrf());

            fetch('<?= BASE_URL ?>ajax/sms_devices_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    alert(data.error || 'Failed to update device.');
                    return;
                }
                if (editModal) editModal.hide();
                showDeviceAlert('Device updated.');
                setTimeout(() => window.location.reload(), 1000);
            });
        });
    }

    // Toggle Enable/Disable Switch
    document.querySelectorAll('.dev-toggle-switch').forEach(sw => {
        sw.addEventListener('change', function () {
            const id = this.getAttribute('data-id');
            const isEnabled = this.checked ? '1' : '0';

            const fd = new FormData();
            fd.append('action', 'toggle_device');
            fd.append('id', id);
            fd.append('is_enabled', isEnabled);
            fd.append('csrf_token', getCsrf());

            fetch('<?= BASE_URL ?>ajax/sms_devices_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    sw.checked = !sw.checked;
                    alert(data.error || 'Failed to toggle device state.');
                    return;
                }
                showDeviceAlert(data.message);
                const badge = document.getElementById('devStatusBadge-' + id);
                if (badge) {
                    badge.className = isEnabled === '1' ? 'badge bg-success' : 'badge bg-secondary';
                    badge.innerHTML = '<i class="bi bi-circle-fill small"></i> ' + (isEnabled === '1' ? 'Connected' : 'Disabled');
                }
            });
        });
    });

    // Regenerate Token Click
    document.querySelectorAll('.btn-regen-token').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            if (!confirm(`Are you sure you want to regenerate the API token for "${name}"? Existing webhooks using the old token will stop working immediately.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'regenerate_token');
            fd.append('id', id);
            fd.append('csrf_token', getCsrf());

            fetch('<?= BASE_URL ?>ajax/sms_devices_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    alert(data.error || 'Failed to regenerate token.');
                    return;
                }
                showDeviceAlert('New API token generated.');
                showTokenModal(data.token);
            });
        });
    });

    // Delete Device Click
    document.querySelectorAll('.btn-delete-dev').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            if (!confirm(`Permanently delete "${name}"? This action cannot be undone.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'delete_device');
            fd.append('id', id);
            fd.append('csrf_token', getCsrf());

            fetch('<?= BASE_URL ?>ajax/sms_devices_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    alert(data.error || 'Failed to delete device.');
                    return;
                }
                const row = document.getElementById('deviceRow-' + id);
                if (row) row.remove();
                showDeviceAlert('Device deleted.');
            });
        });
    });
});
</script>
