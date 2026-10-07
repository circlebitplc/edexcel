<?php
declare(strict_types=1);

/**
 * Admin Contact Details, Academic Records, and SMS History
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;

require_admin();

PhoneContactService::ensureSchema($pdo);

$contactId = (int)($_GET['id'] ?? 0);
if ($contactId < 1) {
    header('Location: ' . BASE_URL . 'admin/phone_contacts.php');
    exit;
}

$contact = PhoneContactService::getContactDetails($pdo, $contactId);
if (!$contact) {
    header('Location: ' . BASE_URL . 'admin/phone_contacts.php?error=not_found');
    exit;
}

$allowedLocations = PhoneContactService::getAllowedLocations($pdo);
$timeline = PhoneContactService::getContactTimeline($pdo, $contactId);

// Distinct WhatsApp groups / sources
$sources = [];
foreach ($contact['academic_records'] as $r) {
    if (!empty($r['source_group'])) {
        $sources[] = trim((string)$r['source_group']);
    }
}
$sources = array_values(array_unique(array_filter($sources)));

$page_title = 'Contact Details - ' . ($contact['name'] ?: $contact['display_phone']);
$current_page = 'phone_contacts.php';

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 phone-contact-view-shell" style="max-width: 1200px;">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/phone_contacts.php">Phone Contacts</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Contact #<?= (int)$contact['id'] ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="bi bi-person-badge text-primary me-2"></i><?= e($contact['name'] ?: 'Unknown Student') ?>
                <?php if (empty($contact['name'])): ?>
                    <span class="badge bg-secondary bg-opacity-75 fs-6 fw-normal">Name Not Available</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted small mb-0">Phone: <strong><?= e($contact['display_phone']) ?></strong> (+<?= e($contact['normalized_phone']) ?>)</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>admin/phone_contacts.php" class="btn btn-outline-secondary btn-sm">
                ← Back to Contacts
            </a>
            <?php if (($contact['status'] ?? 'active') === 'archived'): ?>
                <button type="button" class="btn btn-outline-success btn-sm px-3" id="btnRestoreContact" data-id="<?= (int)$contact['id'] ?>">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Contact
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnArchiveContact" data-id="<?= (int)$contact['id'] ?>">
                    <i class="bi bi-archive me-1"></i> Archive Contact
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#editContactModal">
                <i class="bi bi-pencil-square me-1"></i> Edit Contact Info
            </button>
            <button type="button" class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                <i class="bi bi-plus-circle me-1"></i> Add Academic Record
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnDeleteContact" data-id="<?= (int)$contact['id'] ?>" data-name="<?= e($contact['name'] ?: $contact['display_phone']) ?>" onclick="window.deleteCurrentContact(event)">
                <i class="bi bi-trash me-1"></i> Delete Contact
            </button>
        </div>
    </div>

    <!-- Name Conflict Alert (if CSV imported different non-blank name) -->
    <?php if (!empty($contact['name_conflict'])): ?>
    <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
            <div>
                <strong>Name Conflict Detected during CSV Import</strong>
                <div class="small text-dark mt-1"><?= e($contact['name_conflict']) ?></div>
                <div class="small text-muted mt-1">Existing contact name was preserved to prevent accidental overwrites. You may edit the name manually if desired.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feedback Alert -->
    <div id="contactViewAlert" class="alert d-none mb-4" role="alert"></div>

    <div class="row g-4">
        <!-- LEFT COLUMN: Contact Identity Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Contact Information</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted small">Name:</dt>
                        <dd class="col-sm-7 fw-semibold"><?= e($contact['name'] ?: 'Not Available') ?></dd>

                        <dt class="col-sm-5 text-muted small">Normalized Phone:</dt>
                        <dd class="col-sm-7 font-monospace">+<?= e($contact['normalized_phone']) ?></dd>

                        <dt class="col-sm-5 text-muted small">Local Phone:</dt>
                        <dd class="col-sm-7 font-monospace"><?= e($contact['display_phone']) ?></dd>

                        <dt class="col-sm-5 text-muted small">School:</dt>
                        <dd class="col-sm-7"><?= e($contact['school'] ?: 'Not Available') ?></dd>

                        <dt class="col-sm-5 text-muted small">SMS Status:</dt>
                        <dd class="col-sm-7">
                            <?php
                            $st = $contact['sms_status'] ?? 'allowed';
                            if ($st === 'blocked') {
                                echo '<span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i> Blocked (Never Send)</span>';
                            } elseif ($st === 'opted_out' || !empty($contact['sms_opt_out'])) {
                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-bell-slash me-1"></i> Opted Out</span>';
                            } else {
                                echo '<span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="bi bi-check-circle me-1"></i> Allowed</span>';
                            }
                            ?>
                        </dd>

                        <dt class="col-sm-5 text-muted small">WhatsApp Sources:</dt>
                        <dd class="col-sm-7">
                            <?php if ($sources !== []): ?>
                                <ul class="list-unstyled mb-0 small">
                                    <?php foreach ($sources as $src): ?>
                                        <li><i class="bi bi-chat-dots text-success me-1"></i> <?= e($src) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="text-muted small">None recorded</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5 text-muted small">First Imported:</dt>
                        <dd class="col-sm-7 small text-muted"><?= e($contact['first_imported_at'] ?: $contact['created_at']) ?></dd>

                        <dt class="col-sm-5 text-muted small">Last Imported:</dt>
                        <dd class="col-sm-7 small text-muted"><?= e($contact['last_imported_at'] ?: '—') ?></dd>

                        <dt class="col-sm-5 text-muted small">Status:</dt>
                        <dd class="col-sm-7"><span class="badge bg-light text-dark border"><?= ucfirst(e($contact['status'])) ?></span></dd>

                        <?php if (!empty($contact['notes'])): ?>
                        <dt class="col-sm-5 text-muted small">Notes:</dt>
                        <dd class="col-sm-7 small"><?= nl2br(e($contact['notes'])) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Academic / Group Records & SMS History -->
        <div class="col-lg-8">
            <!-- Academic Records Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Exam / WhatsApp Group Records</h6>
                    <button type="button" class="btn btn-outline-primary btn-sm py-0" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Exam Year</th>
                                    <th>Exam Type</th>
                                    <th>Location</th>
                                    <th>School</th>
                                    <th>WhatsApp Group / Source</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contact['academic_records'])): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No academic records associated with this contact yet.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($contact['academic_records'] as $rec): ?>
                                    <tr>
                                        <td><strong><?= (int)$rec['exam_year'] ?></strong></td>
                                        <td><span class="badge bg-info text-dark"><?= e($rec['exam_type']) ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($rec['location']) ?></span></td>
                                        <td><small class="text-muted"><?= e($rec['school'] ?: '—') ?></small></td>
                                        <td><small class="text-muted"><?= e($rec['source_group'] ?: '—') ?></small></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 btn-edit-record"
                                                data-id="<?= (int)$rec['id'] ?>"
                                                data-year="<?= (int)$rec['exam_year'] ?>"
                                                data-type="<?= e($rec['exam_type']) ?>"
                                                data-location="<?= e($rec['location']) ?>"
                                                data-school="<?= e($rec['school'] ?? '') ?>"
                                                data-group="<?= e($rec['source_group'] ?? '') ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-delete-record"
                                                data-id="<?= (int)$rec['id'] ?>"
                                                data-summary="<?= (int)$rec['exam_year'] ?> <?= e($rec['exam_type']) ?> in <?= e($rec['location']) ?>">
                                                <i class="bi bi-trash"></i>
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

            <!-- SMS History Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-chat-left-text me-2 text-primary"></i>SMS Campaign History</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Campaign / Context</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Message Preview</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contact['sms_history'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No SMS history recorded for this phone number.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($contact['sms_history'] as $sms): ?>
                                    <tr>
                                        <td class="small text-muted"><?= e($sms['date']) ?></td>
                                        <td><strong><?= e($sms['reference']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= e($sms['gateway']) ?></span></td>
                                        <td>
                                            <?php
                                            $st = strtoupper((string)$sms['status']);
                                            $bClass = in_array($st, ['SENT', 'DELIVERED'], true) ? 'bg-success' : (in_array($st, ['FAILED', 'INVALID'], true) ? 'bg-danger' : 'bg-warning text-dark');
                                            ?>
                                            <span class="badge <?= $bClass ?>"><?= e($sms['status']) ?></span>
                                        </td>
                                        <td><small class="text-muted" title="<?= e($sms['message']) ?>"><?= e(mb_substr((string)$sms['message'], 0, 50)) ?><?= mb_strlen((string)$sms['message']) > 50 ? '...' : '' ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Contact Timeline Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Contact Lifecycle Timeline</h6>
                    <span class="badge bg-light text-dark border"><?= count($timeline) ?> Events</span>
                </div>
                <div class="card-body">
                    <?php if (empty($timeline)): ?>
                        <div class="text-center py-4 text-muted small">No lifecycle events recorded for this contact.</div>
                    <?php else: ?>
                        <div class="timeline position-relative ps-3">
                            <?php foreach ($timeline as $tIdx => $evt): ?>
                                <div class="timeline-item mb-3 position-relative pb-2" style="border-left: 2px solid #dee2e6; padding-left: 20px;">
                                    <div class="timeline-dot position-absolute" style="left: -7px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background-color: #0d6efd; border: 2px solid #fff;"></div>
                                    <div class="d-flex align-items-baseline justify-content-between mb-1">
                                        <strong class="small text-dark"><?= e($evt['title']) ?></strong>
                                        <span class="text-muted small" style="font-size: 0.75rem;"><?= e($evt['timestamp']) ?></span>
                                    </div>
                                    <p class="text-muted small mb-0"><?= e($evt['description']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- EDIT CONTACT MODAL                                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editContactModal" tabindex="-1" aria-labelledby="editContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editContactModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Contact Info
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditContact">
                <?= csrf_field() ?>
                <input type="hidden" name="contact_id" value="<?= (int)$contact['id'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contact Name</label>
                        <input type="text" class="form-control" name="name" value="<?= e($contact['name'] ?? '') ?>" placeholder="Leave blank if unknown">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" class="form-control" name="phone" value="<?= e($contact['phone']) ?>" required>
                        <div class="form-text small">Sri Lankan mobile (e.g. 0771234567). Canonical: 94771234567.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">School</label>
                        <input type="text" class="form-control" name="school" value="<?= e($contact['school'] ?? '') ?>" placeholder="School or college">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">SMS Status</label>
                        <select class="form-select" name="sms_status">
                            <option value="allowed" <?= ($contact['sms_status'] ?? 'allowed') === 'allowed' ? 'selected' : '' ?>>Allowed (Can receive SMS)</option>
                            <option value="opted_out" <?= ($contact['sms_status'] ?? '') === 'opted_out' ? 'selected' : '' ?>>Opted Out</option>
                            <option value="blocked" <?= ($contact['sms_status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked (Never Send)</option>
                        </select>
                        <div class="form-text small">Contacts marked as Blocked or Opted Out are strictly excluded server-side from broadcasts.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= $contact['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $contact['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="archived" <?= $contact['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="sms_opt_out" value="1" id="switchOptOut" <?= !empty($contact['sms_opt_out']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="switchOptOut">SMS Opt-Out Flag</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"><?= e($contact['notes'] ?? '') ?></textarea>
                    </div>
                    <div id="modalEditAlert" class="alert d-none small mb-0"></div>
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.deleteCurrentContact(event)">
                        <i class="bi bi-trash me-1"></i> Delete Contact
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnSaveContact">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- ADD ACADEMIC RECORD MODAL                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addRecordModalLabel">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Add Academic Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAddRecord">
                <?= csrf_field() ?>
                <input type="hidden" name="contact_id" value="<?= (int)$contact['id'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Year</label>
                        <input type="number" class="form-control" name="exam_year" min="2020" max="2035" value="<?= date('Y') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Type</label>
                        <select class="form-select" name="exam_type" required>
                            <option value="IGCSE">IGCSE</option>
                            <option value="IAL">IAL</option>
                            <option value="OL">OL</option>
                            <option value="AL">AL</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Location</label>
                        <select class="form-select" name="location" required>
                            <?php foreach ($allowedLocations as $loc): ?>
                                <option value="<?= e($loc) ?>"><?= e($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">School (Optional)</label>
                        <input type="text" class="form-control" name="school" placeholder="Leave blank if unknown">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">WhatsApp Group / Source (Optional)</label>
                        <input type="text" class="form-control" name="source_group" placeholder="e.g. 2026 IGCSE Kandy">
                    </div>
                    <div id="modalAddRecordAlert" class="alert d-none small mb-0"></div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" id="btnAddRecordSubmit">Add Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- EDIT ACADEMIC RECORD MODAL                                             -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editRecordModalLabel">
                    <i class="bi bi-pencil text-primary me-2"></i>Edit Academic Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditRecord">
                <?= csrf_field() ?>
                <input type="hidden" name="record_id" id="editRecordId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Year</label>
                        <input type="number" class="form-control" name="exam_year" id="editRecordYear" min="2020" max="2035" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Type</label>
                        <select class="form-select" name="exam_type" id="editRecordType" required>
                            <option value="IGCSE">IGCSE</option>
                            <option value="IAL">IAL</option>
                            <option value="OL">OL</option>
                            <option value="AL">AL</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Location</label>
                        <select class="form-select" name="location" id="editRecordLocation" required>
                            <?php foreach ($allowedLocations as $loc): ?>
                                <option value="<?= e($loc) ?>"><?= e($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">School (Optional)</label>
                        <input type="text" class="form-control" name="school" id="editRecordSchool">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">WhatsApp Group / Source (Optional)</label>
                        <input type="text" class="form-control" name="source_group" id="editRecordGroup">
                    </div>
                    <div id="modalEditRecordAlert" class="alert d-none small mb-0"></div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnEditRecordSubmit">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    const csrfToken = <?= json_encode(csrf_token()) ?>;

    // Edit Contact Info Form
    const formEdit = document.getElementById('formEditContact');
    if (formEdit) {
        formEdit.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveContact');
            const alertEl = document.getElementById('modalEditAlert');
            btn.disabled = true;

            const fd = new FormData(formEdit);
            fd.append('action', 'update_contact');

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                btn.disabled = false;
                if (data.success) {
                    location.reload();
                } else {
                    alertEl.className = 'alert alert-danger small mb-0';
                    alertEl.textContent = data.error || 'Failed to update contact.';
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

    // Add Academic Record Form
    const formAddRecord = document.getElementById('formAddRecord');
    if (formAddRecord) {
        formAddRecord.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnAddRecordSubmit');
            const alertEl = document.getElementById('modalAddRecordAlert');
            btn.disabled = true;

            const fd = new FormData(formAddRecord);
            fd.append('action', 'add_academic_record');

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                btn.disabled = false;
                if (data.success) {
                    location.reload();
                } else {
                    alertEl.className = 'alert alert-danger small mb-0';
                    alertEl.textContent = data.error || 'Failed to add record.';
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

    // Edit Record Buttons
    const editRecordModalEl = document.getElementById('editRecordModal');
    document.querySelectorAll('.btn-edit-record').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editRecordId').value = this.dataset.id;
            document.getElementById('editRecordYear').value = this.dataset.year;
            document.getElementById('editRecordType').value = this.dataset.type;
            document.getElementById('editRecordLocation').value = this.dataset.location;
            document.getElementById('editRecordSchool').value = this.dataset.school;
            document.getElementById('editRecordGroup').value = this.dataset.group;
            document.getElementById('modalEditRecordAlert').classList.add('d-none');
            if (editRecordModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(editRecordModalEl) : new bootstrap.Modal(editRecordModalEl);
                modal.show();
            }
        });
    });

    // Edit Academic Record Form
    const formEditRecord = document.getElementById('formEditRecord');
    if (formEditRecord) {
        formEditRecord.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnEditRecordSubmit');
            const alertEl = document.getElementById('modalEditRecordAlert');
            btn.disabled = true;

            const fd = new FormData(formEditRecord);
            fd.append('action', 'update_academic_record');

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                btn.disabled = false;
                if (data.success) {
                    location.reload();
                } else {
                    alertEl.className = 'alert alert-danger small mb-0';
                    alertEl.textContent = data.error || 'Failed to update record.';
                    alertEl.classList.remove('d-none');
                }
            } catch (err) {
                btn.disabled = false;
                alertEl.className = 'alert alert-danger small mb-0';
                alertEl.textContent = 'Network error.';
                alertEl.classList.remove('d-none');
            }
        });
    });

    // Delete Record Buttons (with confirmation per Section 23)
    document.querySelectorAll('.btn-delete-record').forEach(btn => {
        btn.addEventListener('click', async function() {
            const summary = this.dataset.summary;
            if (!confirm(`Are you sure you want to delete the academic record: ${summary}?\nThis action cannot be undone.`)) {
                return;
            }
            const recordId = this.dataset.id;
            const fd = new FormData();
            fd.append('action', 'delete_academic_record');
            fd.append('csrf_token', csrfToken);
            fd.append('record_id', recordId);

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to delete record.');
                }
            } catch (err) {
                alert('Network error.');
            }
        });
    });

    // Archive Contact Button
    const btnArchive = document.getElementById('btnArchiveContact');
    if (btnArchive) {
        btnArchive.addEventListener('click', async function() {
            const reason = prompt('Are you sure you want to archive this contact?\nArchived contacts are excluded from bulk SMS messages.\n\nReason / Note (optional):', 'Archived by administrator');
            if (reason === null) return;
            btnArchive.disabled = true;
            const fd = new FormData();
            fd.append('action', 'archive_contact');
            fd.append('contact_id', btnArchive.dataset.id);
            fd.append('reason', reason);
            fd.append('csrf_token', csrfToken);
            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    location.reload();
                } else {
                    btnArchive.disabled = false;
                    alert(data.error || 'Failed to archive contact.');
                }
            } catch (err) {
                btnArchive.disabled = false;
                alert('Network error.');
            }
        });
    }

    // Restore Contact Button
    const btnRestore = document.getElementById('btnRestoreContact');
    if (btnRestore) {
        btnRestore.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to restore this contact to active status?')) return;
            btnRestore.disabled = true;
            const fd = new FormData();
            fd.append('action', 'restore_contact');
            fd.append('contact_id', btnRestore.dataset.id);
            fd.append('csrf_token', csrfToken);
            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    location.reload();
                } else {
                    btnRestore.disabled = false;
                    alert(data.error || 'Failed to restore contact.');
                }
            } catch (err) {
                btnRestore.disabled = false;
                alert('Network error.');
            }
        });
    }

    // Delete Contact Function (exposed on window for direct clicks and modal buttons)
    window.deleteCurrentContact = async function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const btn = document.getElementById('btnDeleteContact');
        const name = btn ? (btn.getAttribute('data-name') || 'this contact') : 'this contact';
        let confirmed = false;
        try {
            confirmed = window.confirm(`Are you sure you want to permanently delete ${name}?\n\nThis will permanently delete the contact and all associated academic records.\nThis action CANNOT be undone.`);
        } catch (err) {
            confirmed = true;
        }
        if (!confirmed) return;

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        }

        const fd = new FormData();
        fd.append('action', 'delete_contact');
        fd.append('contact_id', '<?= (int)$contact['id'] ?>');
        fd.append('csrf_token', csrfToken);

        try {
            const resp = await fetch('<?= BASE_URL ?>ajax/phone_contacts_action.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                window.location.href = '<?= BASE_URL ?>admin/phone_contacts.php?deleted=1';
            } else {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-trash me-1"></i> Delete Contact';
                }
                alert(data.error || 'Failed to delete contact.');
            }
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash me-1"></i> Delete Contact';
            }
            alert('Network error while deleting contact.');
        }
    };

    // Delete Contact Button
    const btnDeleteContact = document.getElementById('btnDeleteContact');
    if (btnDeleteContact) {
        btnDeleteContact.addEventListener('click', function(e) {
            window.deleteCurrentContact(e);
        });
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
