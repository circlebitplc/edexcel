<?php
declare(strict_types=1);

/**
 * Admin Incoming SMS Inbox
 *
 * Real-time inbox for SMS messages synchronized from SMS-Gate (Android) devices.
 * Features automated deduplication, registered student/teacher/parent resolution,
 * detailed audit viewing, status filtering, and device status management.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../src/Services/IncomingSmsService.php';

use Edexcel\Services\IncomingSmsService;

require_admin();

IncomingSmsService::ensureSchema($pdo);

$page_title = 'Incoming SMS Inbox';
$current_page = 'incoming_sms.php';

// ─── Filter parameters ────────────────────────────────────────────────────────
$search   = trim((string)($_GET['search'] ?? ''));
$status   = trim((string)($_GET['status'] ?? ''));
$deviceId = trim((string)($_GET['device_id'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$filters = [
    'search'    => $search,
    'status'    => in_array($status, ['unread', 'read'], true) ? $status : '',
    'device_id' => $deviceId,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
];

// Query messages
$data = IncomingSmsService::getMessages($pdo, $filters, $page, $perPage);
$messages = $data['messages'];
$total = $data['total'];
$pagination = paginate($total, $perPage, $page);

// Summary stats
$stats = IncomingSmsService::getStats($pdo);

// Available devices for filter dropdown
$devices = IncomingSmsService::getDevices($pdo);

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 incoming-sms-shell">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Incoming SMS</li>
                </ol>
            </nav>
            <h1 class="h3 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-inbox-fill text-primary"></i> Incoming SMS Inbox
                <?php if ($stats['unread'] > 0): ?>
                    <span class="badge bg-danger rounded-pill fs-6" id="topUnreadBadge"><?= number_format($stats['unread']) ?> New</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted small mb-0">Incoming SMS synchronized from Android phone via SMS-Gate API. Automatically deduplicated and matched to registered users.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= BASE_URL ?>admin/bulk_sms.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-broadcast"></i> Bulk SMS
            </a>
            <a href="<?= BASE_URL ?>admin/sms_logs.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-card-list"></i> Outgoing Logs
            </a>
            <a href="<?= BASE_URL ?>admin/settings.php?tab=otp" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-phone"></i> Gateway Devices
            </a>
            <?php if ($stats['unread'] > 0): ?>
                <button type="button" class="btn btn-outline-success btn-sm" id="btnMarkAllRead">
                    <i class="bi bi-check2-all"></i> Mark All as Read
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert banner -->
    <div id="actionAlert" class="alert alert-dismissible fade show d-none" role="alert">
        <span id="actionAlertText"></span>
        <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('actionAlert').classList.add('d-none')"></button>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Unread SMS</span>
                        <h3 class="fw-bold mb-0 text-danger" id="statUnreadCount"><?= number_format($stats['unread']) ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded p-3">
                        <i class="bi bi-envelope-exclamation fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Received Today</span>
                        <h3 class="fw-bold mb-0 text-primary" id="statTodayCount"><?= number_format($stats['today']) ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded p-3">
                        <i class="bi bi-calendar-event fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Received</span>
                        <h3 class="fw-bold mb-0 text-dark" id="statTotalCount"><?= number_format($stats['total']) ?></h3>
                    </div>
                    <div class="bg-dark bg-opacity-10 text-dark rounded p-3">
                        <i class="bi bi-chat-left-text fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">SMS-Gate Phone</span>
                        <h4 class="fw-bold mb-0 fs-5 d-flex align-items-center gap-2">
                            <?php if ($stats['online_devices'] > 0): ?>
                                <span class="badge bg-success"><i class="bi bi-circle-fill small"></i> Connected</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-circle-fill small"></i> Offline</span>
                            <?php endif; ?>
                        </h4>
                        <span class="small text-muted"><?= $stats['total_devices'] ?> device(s) registered</span>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded p-3">
                        <i class="bi bi-phone fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Sender, phone, message..." value="<?= e($search) ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>● Unread only</option>
                        <option value="read"   <?= $status === 'read'   ? 'selected' : '' ?>>Read</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Device</label>
                    <select name="device_id" class="form-select form-select-sm">
                        <option value="">All Devices</option>
                        <?php foreach ($devices as $dev): ?>
                            <option value="<?= e($dev['device_id']) ?>" <?= $deviceId === $dev['device_id'] ? 'selected' : '' ?>>
                                <?= e($dev['device_name']) ?> (<?= e($dev['device_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="incoming_sms.php" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Inbox Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark fs-6">
                <i class="bi bi-chat-left-quote"></i> Received Messages
                <span class="text-muted fw-normal ms-1">(<?= number_format($total) ?> found)</span>
            </h5>
            <div class="small text-muted">
                Auto-refreshes inbox in background
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="incomingTable">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th style="width: 70px;">Status</th>
                        <th style="width: 220px;">Sender</th>
                        <th>Message</th>
                        <th style="width: 160px;">Device</th>
                        <th style="width: 140px;">Received</th>
                        <th style="width: 120px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($messages === []): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <?php if ($search !== '' || $status !== '' || $deviceId !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                                    No incoming SMS messages matched your filter criteria.<br>
                                    <a href="incoming_sms.php" class="btn btn-sm btn-link mt-2">Clear all filters</a>
                                <?php else: ?>
                                    No incoming SMS messages received yet.<br>
                                    <span class="small">Ensure your Android phone running SMS-Gate is registered in <a href="<?= BASE_URL ?>admin/settings.php?tab=otp">Settings</a> and webhooks are active.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php
                            $isUnread = ((int)($msg['is_read'] ?? 0)) === 0;
                            $rowClass = $isUnread ? 'table-light fw-semibold text-dark' : 'text-secondary';
                            $userType = $msg['matched_user_type'] ?? '';
                            $userName = $msg['matched_user_name'] ?? '';
                            $senderPhone = $msg['sender'] ?? '';
                            ?>
                            <tr class="<?= $rowClass ?>" id="msgRow-<?= (int)$msg['id'] ?>" data-id="<?= (int)$msg['id'] ?>">
                                <td>
                                    <?php if ($isUnread): ?>
                                        <span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-circle-fill small"></i> New</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-2 py-1">Read</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($userName !== ''): ?>
                                        <div class="text-truncate" style="max-width: 210px;">
                                            <span class="text-dark fw-bold"><?= e($userName) ?></span>
                                            <?php if ($userType === 'student'): ?>
                                                <span class="badge bg-info text-dark small" style="font-size: 0.65rem;">Student</span>
                                            <?php elseif ($userType === 'teacher'): ?>
                                                <span class="badge bg-success small" style="font-size: 0.65rem;">Teacher</span>
                                            <?php elseif ($userType === 'parent'): ?>
                                                <span class="badge bg-warning text-dark small" style="font-size: 0.65rem;">Parent</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted font-monospace"><?= e($senderPhone) ?></div>
                                    <?php else: ?>
                                        <div class="text-truncate text-muted small" style="max-width: 210px;">Unknown Sender</div>
                                        <div class="font-monospace text-dark"><?= e($senderPhone) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="text-truncate" style="max-width: 500px;" title="<?= e($msg['message']) ?>">
                                        <?= e($msg['message']) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        <i class="bi bi-phone"></i> <?= e($msg['device_name'] ?: $msg['device_id']) ?>
                                    </span>
                                    <?php if (!empty($msg['sim_number'])): ?>
                                        <span class="badge bg-light text-muted border small">SIM <?= (int)$msg['sim_number'] ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="small">
                                    <div title="<?= e((string)$msg['received_at']) ?>"><?= e((string)($msg['received_human'] ?? '')) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime((string)$msg['received_at'])) ?></div>
                                </td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-view-msg" data-id="<?= (int)$msg['id'] ?>" title="View Message">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($isUnread): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-mark-read" data-id="<?= (int)$msg['id'] ?>" title="Mark as Read">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary btn-mark-unread" data-id="<?= (int)$msg['id'] ?>" title="Mark as Unread">
                                                <i class="bi bi-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-danger btn-delete-msg" data-id="<?= (int)$msg['id'] ?>" title="Delete">
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

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <?= render_pagination($pagination, [
                    'search'    => $search,
                    'status'    => $status,
                    'device_id' => $deviceId,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- Message Details Modal -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="messageDetailsModal" tabindex="-1" aria-labelledby="messageDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="messageDetailsModalLabel">
                    <i class="bi bi-chat-square-text text-primary"></i> Incoming SMS Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Sender Info Banner -->
                <div class="d-flex flex-wrap align-items-center justify-content-between p-3 bg-light rounded mb-3 border">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="fs-5 fw-bold text-dark" id="modalSenderName">Unknown Sender</span>
                            <span class="badge bg-info text-dark" id="modalSenderRoleBadge" style="display:none;">Student</span>
                        </div>
                        <div class="font-monospace text-primary fw-semibold" id="modalSenderPhone">+94 77 XXX XXXX</div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary" id="modalDeviceBadge">Edexcel SMS Phone</span>
                        <div class="small text-muted mt-1" id="modalReceivedTime">Received: Just now</div>
                    </div>
                </div>

                <!-- Message Body -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-uppercase text-muted mb-0">SMS Message Content</label>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnCopyMessage">
                            <i class="bi bi-clipboard"></i> Copy text
                        </button>
                    </div>
                    <div class="p-3 bg-white border rounded font-monospace fs-6" style="white-space: pre-wrap; word-break: break-word; min-height: 100px; background-color: #fafbfc !important;" id="modalMessageContent">
                        Loading...
                    </div>
                </div>

                <!-- Technical Details Accordion -->
                <div class="accordion" id="modalTechAccordion">
                    <div class="accordion-item border rounded">
                        <h2 class="accordion-header" id="headingTech">
                            <button class="accordion-button collapsed py-2 small fw-semibold bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTech" aria-expanded="false" aria-controls="collapseTech">
                                <i class="bi bi-info-circle me-2"></i> Technical Audit Details
                            </button>
                        </h2>
                        <div id="collapseTech" class="accordion-collapse collapse" aria-labelledby="headingTech" data-bs-parent="#modalTechAccordion">
                            <div class="accordion-body small p-3 bg-light font-monospace">
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <span class="text-muted">Message ID:</span> <span id="modalMsgId" class="text-dark">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted">Device ID:</span> <span id="modalDeviceId" class="text-dark">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted">Received at Phone:</span> <span id="modalPhoneTime" class="text-dark">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted">Received at Server:</span> <span id="modalServerTime" class="text-dark">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted">SIM Number:</span> <span id="modalSimNumber" class="text-dark">-</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted">Deduplication Hash:</span> <span id="modalFingerprint" class="text-dark text-truncate d-inline-block" style="max-width: 180px;">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token -->
<input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const csrfToken = document.getElementById('csrfToken')?.value || '';
    const detailsModalEl = document.getElementById('messageDetailsModal');
    const detailsModal = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;

    function showAlert(msg, isSuccess = true) {
        const alertEl = document.getElementById('actionAlert');
        const alertText = document.getElementById('actionAlertText');
        if (!alertEl || !alertText) return;
        alertText.textContent = msg;
        alertEl.className = 'alert alert-dismissible fade show ' + (isSuccess ? 'alert-success' : 'alert-danger');
        alertEl.classList.remove('d-none');
        setTimeout(() => alertEl.classList.add('d-none'), 5000);
    }

    function updateUnreadCounts(newCount) {
        const topBadge = document.getElementById('topUnreadBadge');
        const statUnread = document.getElementById('statUnreadCount');
        const navBadges = document.querySelectorAll('.sms-unread-badge');

        if (statUnread) {
            statUnread.textContent = newCount.toLocaleString();
        }
        if (topBadge) {
            if (newCount > 0) {
                topBadge.textContent = newCount.toLocaleString() + ' New';
                topBadge.style.display = '';
            } else {
                topBadge.style.display = 'none';
            }
        }
        navBadges.forEach(b => {
            if (newCount > 0) {
                b.textContent = newCount.toString();
                b.style.display = '';
            } else {
                b.style.display = 'none';
            }
        });
    }

    // View Message Click
    document.querySelectorAll('.btn-view-msg').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            if (!id) return;

            const fd = new FormData();
            fd.append('action', 'get_message');
            fd.append('id', id);
            fd.append('csrf_token', csrfToken);

            fetch('<?= BASE_URL ?>ajax/incoming_sms_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.message) {
                    alert(data.error || 'Failed to load message.');
                    return;
                }
                const m = data.message;

                // Populate modal
                document.getElementById('modalSenderName').textContent = m.matched_user_name || 'Unknown Sender';
                const roleBadge = document.getElementById('modalSenderRoleBadge');
                if (m.matched_user_type) {
                    roleBadge.textContent = m.matched_user_type.charAt(0).toUpperCase() + m.matched_user_type.slice(1);
                    roleBadge.style.display = '';
                } else {
                    roleBadge.style.display = 'none';
                }

                document.getElementById('modalSenderPhone').textContent = m.sender || m.raw_sender || '';
                document.getElementById('modalDeviceBadge').textContent = m.device_name || m.device_id || 'Device';
                document.getElementById('modalReceivedTime').textContent = 'Received: ' + (m.received_human || m.received_at);
                document.getElementById('modalMessageContent').textContent = m.message || '';

                document.getElementById('modalMsgId').textContent = m.message_id || 'N/A';
                document.getElementById('modalDeviceId').textContent = m.device_id || 'N/A';
                document.getElementById('modalPhoneTime').textContent = m.received_at || 'N/A';
                document.getElementById('modalServerTime').textContent = m.received_at_server || 'N/A';
                document.getElementById('modalSimNumber').textContent = m.sim_number ? ('SIM ' + m.sim_number) : 'Default';
                document.getElementById('modalFingerprint').textContent = m.fingerprint || 'N/A';
                document.getElementById('modalFingerprint').title = m.fingerprint || '';

                // Update row styling if it was unread
                if (data.was_unread) {
                    const row = document.getElementById('msgRow-' + id);
                    if (row) {
                        row.className = 'text-secondary';
                        const statusCell = row.cells[0];
                        if (statusCell) {
                            statusCell.innerHTML = '<span class="badge bg-light text-muted border px-2 py-1">Read</span>';
                        }
                    }
                    if (typeof data.unread_count === 'number') {
                        updateUnreadCounts(data.unread_count);
                    }
                }

                if (detailsModal) {
                    detailsModal.show();
                }
            })
            .catch(err => {
                alert('Network error: ' + err.message);
            });
        });
    });

    // Copy message button
    const copyBtn = document.getElementById('btnCopyMessage');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const text = document.getElementById('modalMessageContent')?.textContent || '';
            navigator.clipboard.writeText(text).then(() => {
                copyBtn.innerHTML = '<i class="bi bi-check2 text-success"></i> Copied!';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy text';
                }, 2000);
            });
        });
    }

    // Mark as Read click
    document.querySelectorAll('.btn-mark-read').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('id', id);
            fd.append('csrf_token', csrfToken);

            fetch('<?= BASE_URL ?>ajax/incoming_sms_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    const row = document.getElementById('msgRow-' + id);
                    if (row) {
                        row.className = 'text-secondary';
                        row.cells[0].innerHTML = '<span class="badge bg-light text-muted border px-2 py-1">Read</span>';
                        btn.outerHTML = '<button type="button" class="btn btn-outline-secondary btn-mark-unread" data-id="' + id + '" title="Mark as Unread"><i class="bi bi-envelope"></i></button>';
                    }
                    if (typeof data.unread_count === 'number') {
                        updateUnreadCounts(data.unread_count);
                    }
                }
            });
        });
    });

    // Mark as Unread click
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-mark-unread');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        const fd = new FormData();
        fd.append('action', 'mark_unread');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);

        fetch('<?= BASE_URL ?>ajax/incoming_sms_action.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                const row = document.getElementById('msgRow-' + id);
                if (row) {
                    row.className = 'table-light fw-semibold text-dark';
                    row.cells[0].innerHTML = '<span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-circle-fill small"></i> New</span>';
                    btn.outerHTML = '<button type="button" class="btn btn-outline-secondary btn-mark-read" data-id="' + id + '" title="Mark as Read"><i class="bi bi-check2"></i></button>';
                }
                if (typeof data.unread_count === 'number') {
                    updateUnreadCounts(data.unread_count);
                }
            }
        });
    });

    // Delete message click
    document.querySelectorAll('.btn-delete-msg').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to permanently delete this SMS record?')) {
                return;
            }
            const id = this.getAttribute('data-id');
            const fd = new FormData();
            fd.append('action', 'delete_message');
            fd.append('id', id);
            fd.append('csrf_token', csrfToken);

            fetch('<?= BASE_URL ?>ajax/incoming_sms_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    const row = document.getElementById('msgRow-' + id);
                    if (row) {
                        row.remove();
                    }
                    if (typeof data.unread_count === 'number') {
                        updateUnreadCounts(data.unread_count);
                    }
                    showAlert('Message deleted.');
                }
            });
        });
    });

    // Mark all as read button
    const btnMarkAll = document.getElementById('btnMarkAllRead');
    if (btnMarkAll) {
        btnMarkAll.addEventListener('click', function () {
            if (!confirm('Mark all incoming messages as read?')) {
                return;
            }
            const fd = new FormData();
            fd.append('action', 'mark_all_read');
            fd.append('csrf_token', csrfToken);

            fetch('<?= BASE_URL ?>ajax/incoming_sms_action.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    updateUnreadCounts(0);
                    btnMarkAll.remove();
                    window.location.reload();
                }
            });
        });
    }

    // Periodic gentle refresh of unread count (every 30 seconds)
    setInterval(() => {
        fetch('<?= BASE_URL ?>ajax/incoming_sms_action.php?action=get_unread_count', {
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok && typeof data.unread_count === 'number') {
                updateUnreadCounts(data.unread_count);
            }
        })
        .catch(() => {});
    }, 30000);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
