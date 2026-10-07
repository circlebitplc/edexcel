<?php
declare(strict_types=1);

/**
 * Admin Bulk SMS History & Recipient Audit Log
 *
 * View all past bulk campaigns, filter by status or date, inspect per-campaign
 * recipient logs, retry failed messages, download CSV reports, and search recipient history.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BulkSmsService;

require_admin();

BulkSmsService::ensureSchema($pdo);

$page_title = 'Bulk SMS History';
$current_page = 'bulk_sms_history.php';

// Active view tab: 'campaigns' or 'recipients'
$activeTab = (string)($_GET['tab'] ?? 'campaigns');
if (!in_array($activeTab, ['campaigns', 'recipients'], true)) {
    $activeTab = 'campaigns';
}

// ── Campaign Filters ──────────────────────────────────────────────────────────
$filterStatus   = trim((string)($_GET['status']    ?? ''));
$filterGateway  = trim((string)($_GET['gateway']   ?? ''));
$filterSearch   = trim((string)($_GET['search']    ?? ''));
$filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
$filterDateTo   = trim((string)($_GET['date_to']   ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$campaignData = BulkSmsService::getCampaigns($pdo, [
    'status'    => $filterStatus,
    'gateway'   => $filterGateway,
    'search'    => $filterSearch,
    'date_from' => $filterDateFrom,
    'date_to'   => $filterDateTo,
], $perPage, $offset);

$totalCampaigns = $campaignData['total'];
$campaigns = $campaignData['campaigns'];
$pagination = paginate($totalCampaigns, $perPage, $page);

// ── Recipient Search ──────────────────────────────────────────────────────────
$recipSearchQuery = trim((string)($_GET['recip_q'] ?? ''));
$recipResults = [];
if ($activeTab === 'recipients' && $recipSearchQuery !== '') {
    $recipResults = BulkSmsService::searchRecipientHistory($pdo, $recipSearchQuery, 100);
}

// Selected campaign for detailed modal view
$viewCampaignId = (int)($_GET['view_campaign'] ?? 0);
$campaignDetail = null;
if ($viewCampaignId > 0) {
    $recipStatusFilter = trim((string)($_GET['recip_status'] ?? ''));
    $campaignDetail = BulkSmsService::getCampaignDetails($pdo, $viewCampaignId, 200, 0, $recipStatusFilter);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 bulk-sms-history-shell">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bulk SMS History</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Bulk SMS Campaign History</h1>
            <p class="text-muted small mb-0">Track all bulk SMS campaigns, inspect recipient delivery records, and export reports.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>admin/bulk_sms.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Broadcast
            </a>
            <a href="<?= BASE_URL ?>admin/sms_logs.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-columns me-1"></i> Realtime Gateway Logs
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'campaigns' ? 'active fw-bold' : '' ?>" href="?tab=campaigns">
                <i class="bi bi-broadcast-pin me-1"></i> Campaigns (<?= number_format($totalCampaigns) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'recipients' ? 'active fw-bold' : '' ?>" href="?tab=recipients">
                <i class="bi bi-person-lines-fill me-1"></i> Recipient SMS Search
            </a>
        </li>
    </ul>

    <!-- ── TAB 1: CAMPAIGNS ────────────────────────────────────────────────── -->
    <?php if ($activeTab === 'campaigns'): ?>
        <!-- Filter Form -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="campaigns">
                    <div class="col-md-3">
                        <label for="search" class="form-label small fw-semibold">Search Campaign / Message</label>
                        <input type="text" id="search" name="search" class="form-control form-control-sm" placeholder="Code, name, message..." value="<?= htmlspecialchars($filterSearch) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="gateway" class="form-label small fw-semibold">Gateway</label>
                        <select id="gateway" name="gateway" class="form-select form-select-sm">
                            <option value="">All Gateways</option>
                            <option value="sms_gate_android" <?= $filterGateway === 'sms_gate_android' ? 'selected' : '' ?>>SMS-Gate (Android)</option>
                            <option value="ipromo" <?= $filterGateway === 'ipromo' ? 'selected' : '' ?>>iPromo Marketing</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label small fw-semibold">Status</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <?php foreach (['DRAFT', 'READY', 'SENDING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED', 'CANCELLED'] as $st): ?>
                                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label small fw-semibold">From Date</label>
                        <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateFrom) ?>">
                    </div>
                    <div class="col-md-1">
                        <label for="date_to" class="form-label small fw-semibold">To Date</label>
                        <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateTo) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter"></i> Filter</button>
                        <a href="?tab=campaigns" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Campaigns Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th>Gateway</th>
                                <th>Date &amp; Creator</th>
                                <th>Source File</th>
                                <th class="text-center">Recipients</th>
                                <th class="text-center text-success">Sent</th>
                                <th class="text-center text-danger">Failed</th>
                                <th class="text-center text-secondary">Skipped</th>
                                <th class="text-center">SMS Units</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($campaigns === []): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-1"></i> No campaigns found matching the criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($campaigns as $c): ?>
                                    <?php
                                    $st = (string)$c['status'];
                                    $badge = match ($st) {
                                        'COMPLETED'             => 'bg-success',
                                        'COMPLETED_WITH_ERRORS' => 'bg-warning text-dark',
                                        'SENDING'               => 'bg-primary',
                                        'READY'                 => 'bg-info text-dark',
                                        'FAILED'                => 'bg-danger',
                                        default                 => 'bg-secondary',
                                    };
                                    $gw = (string)($c['gateway'] ?? 'sms_gate_android');
                                    $gwLabel = $c['gateway_label'] ?? ($gw === 'ipromo' ? 'iPromo Marketing' : 'SMS-Gate (Android)');
                                    $gwBadgeClass = ($gw === 'ipromo') ? 'bg-info text-dark' : 'bg-secondary';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold font-monospace text-primary"><?= htmlspecialchars($c['campaign_code']) ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($c['campaign_name']) ?>">
                                                <?= htmlspecialchars($c['campaign_name'] ?: '—') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $gwBadgeClass ?>"><?= htmlspecialchars($gwLabel) ?></span>
                                        </td>
                                        <td class="small">
                                            <div><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$c['created_at']))) ?></div>
                                            <div class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($c['creator_username'] ?? 'System') ?></div>
                                        </td>
                                        <td class="small text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($c['source_filename']) ?>">
                                            <code><?= htmlspecialchars($c['source_filename']) ?></code>
                                        </td>
                                        <td class="text-center fw-bold"><?= number_format((int)$c['recipient_count']) ?></td>
                                        <td class="text-center text-success fw-bold"><?= number_format((int)$c['sent_count']) ?></td>
                                        <td class="text-center text-danger fw-bold"><?= number_format((int)$c['failed_count']) ?></td>
                                        <td class="text-center text-secondary"><?= number_format((int)$c['skipped_count']) ?></td>
                                        <td class="text-center small"><?= number_format((int)$c['total_sms_units']) ?></td>
                                        <td>
                                            <span class="badge <?= $badge ?>"><?= $st ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="?tab=campaigns&view_campaign=<?= $c['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>admin/bulk_sms_export.php?export=campaign&id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Export CSV">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <?php if ((int)$c['failed_count'] > 0): ?>
                                                    <button type="button" class="btn btn-outline-warning retry-btn" data-id="<?= $c['id'] ?>" title="Retry Failed">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
                    <span class="small text-muted">Showing <?= count($campaigns) ?> of <?= number_format($totalCampaigns) ?> entries</span>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=campaigns&page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&search=<?= urlencode($filterSearch) ?>&date_from=<?= urlencode($filterDateFrom) ?>&date_to=<?= urlencode($filterDateTo) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

    <!-- ── TAB 2: RECIPIENT SMS SEARCH ──────────────────────────────────────── -->
    <?php elseif ($activeTab === 'recipients'): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-2 mb-4">
                    <input type="hidden" name="tab" value="recipients">
                    <div class="col-md-9">
                        <label for="recip_q" class="form-label fw-bold">Search Recipient History</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="recip_q" name="recip_q" class="form-control" placeholder="Enter full or partial phone number (e.g. 0771234567, 9477...) or recipient name" value="<?= htmlspecialchars($recipSearchQuery) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i> Search Recipient
                        </button>
                    </div>
                </form>

                <?php if ($recipSearchQuery !== ''): ?>
                    <h6 class="fw-bold mb-3">Results for "<?= htmlspecialchars($recipSearchQuery) ?>" (<?= count($recipResults) ?> found)</h6>
                    <?php if ($recipResults === []): ?>
                        <div class="alert alert-info">No previous bulk SMS messages found for this recipient.</div>
                    <?php else: ?>
                        <div class="table-responsive border rounded">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date &amp; Time</th>
                                        <th>Recipient</th>
                                        <th>Gateway</th>
                                        <th>Campaign</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Gateway ID</th>
                                        <th>Details / Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recipResults as $r): ?>
                                        <?php
                                        $st = (string)$r['status'];
                                        $badge = match ($st) {
                                            'SENT', 'DELIVERED' => 'bg-success',
                                            'FAILED'            => 'bg-danger',
                                            'SKIPPED'           => 'bg-warning text-dark',
                                            default             => 'bg-secondary',
                                        };
                                        $rGw = (string)($r['gateway'] ?? 'sms_gate_android');
                                        $rGwLabel = ($rGw === 'ipromo') ? 'iPromo Marketing' : 'SMS-Gate (Android)';
                                        $rGwBadge = ($rGw === 'ipromo') ? 'bg-info text-dark' : 'bg-secondary';
                                        ?>
                                        <tr>
                                            <td class="small text-nowrap"><?= htmlspecialchars((string)($r['sent_at'] ?: $r['created_at'])) ?></td>
                                            <td>
                                                <div class="fw-bold font-monospace"><?= htmlspecialchars((string)$r['phone_number']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars((string)$r['name'] ?: '—') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $rGwBadge ?> small"><?= htmlspecialchars($rGwLabel) ?></span>
                                            </td>
                                            <td class="small">
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars((string)$r['campaign_code']) ?></span>
                                            </td>
                                            <td class="small" style="max-width: 320px;">
                                                <div class="text-truncate" title="<?= htmlspecialchars((string)$r['message']) ?>">
                                                    <?= htmlspecialchars((string)$r['message']) ?>
                                                </div>
                                            </td>
                                            <td><span class="badge <?= $badge ?>"><?= $st ?></span></td>
                                            <td class="small font-monospace text-muted"><?= htmlspecialchars((string)($r['gateway_message_id'] ?: '—')) ?></td>
                                            <td class="small text-danger"><?= htmlspecialchars((string)($r['error_message'] ?: '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-telephone-inbound fs-1 d-block mb-2"></i>
                        Enter a Sri Lankan mobile number above to look up all messages sent to that recipient.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── CAMPAIGN DETAIL MODAL / VIEW ────────────────────────────────────── -->
    <?php if ($campaignDetail): ?>
        <?php 
        $camp = $campaignDetail['campaign'];
        $modalGw = (string)($camp['gateway'] ?? 'sms_gate_android');
        $modalGwLabel = $camp['gateway_label'] ?? (($modalGw === 'ipromo') ? 'iPromo Marketing' : 'SMS-Gate (Android)');
        $modalGwBadge = ($modalGw === 'ipromo') ? 'bg-info text-dark' : 'bg-secondary';
        ?>
        <div class="modal fade show d-block" id="campaignDetailModal" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content shadow">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-broadcast me-2 text-primary"></i>Campaign Details: <?= htmlspecialchars($camp['campaign_code']) ?>
                            <span class="badge <?= $modalGwBadge ?> ms-2 fs-6"><?= htmlspecialchars($modalGwLabel) ?></span>
                        </h5>
                        <a href="?tab=campaigns" class="btn-close"></a>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Stats Grid -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center">
                                    <span class="small text-muted">Gateway Used</span>
                                    <div class="mt-1">
                                        <span class="badge <?= $modalGwBadge ?> fs-6"><?= htmlspecialchars($modalGwLabel) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center">
                                    <span class="small text-muted">Target Recipients</span>
                                    <h4 class="fw-bold mb-0"><?= number_format((int)$camp['recipient_count']) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-success bg-opacity-10 rounded border border-success text-center">
                                    <span class="small text-success fw-bold">Successfully Sent</span>
                                    <h4 class="fw-bold text-success mb-0"><?= number_format((int)$camp['sent_count']) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-danger bg-opacity-10 rounded border border-danger text-center">
                                    <span class="small text-danger fw-bold">Failed</span>
                                    <h4 class="fw-bold text-danger mb-0"><?= number_format((int)$camp['failed_count']) ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Message Content Box -->
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <div class="fw-bold small text-muted mb-1">Message Content:</div>
                                <div class="font-monospace small bg-white p-3 border rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($camp['message']) ?></div>
                            </div>
                        </div>

                        <!-- Filter Status for Recipients in this campaign -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Recipient Breakdown (Showing <?= count($campaignDetail['recipients']) ?> of <?= number_format($campaignDetail['total_recipients']) ?>)</h6>
                            <a href="<?= BASE_URL ?>admin/bulk_sms_export.php?export=campaign&id=<?= $camp['id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1"></i> Export Complete CSV
                            </a>
                        </div>

                        <div class="table-responsive border rounded" style="max-height: 340px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Recipient Phone</th>
                                        <th>Name</th>
                                        <th>Gateway</th>
                                        <th>Status</th>
                                        <th>Gateway ID</th>
                                        <th>Sent Timestamp</th>
                                        <th>Details / Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($campaignDetail['recipients'] === []): ?>
                                        <tr><td colspan="7" class="text-center py-3 text-muted">No recipients found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($campaignDetail['recipients'] as $r): ?>
                                            <?php
                                            $rg = (string)($r['gateway'] ?? $camp['gateway'] ?? 'sms_gate_android');
                                            $rgLabel = ($rg === 'ipromo') ? 'iPromo' : 'SMS-Gate';
                                            $rgBadge = ($rg === 'ipromo') ? 'bg-info text-dark' : 'bg-secondary';
                                            ?>
                                            <tr>
                                                <td class="font-monospace fw-bold"><?= htmlspecialchars($r['phone_number']) ?></td>
                                                <td><?= htmlspecialchars($r['name'] ?: '—') ?></td>
                                                <td><span class="badge <?= $rgBadge ?> small"><?= htmlspecialchars($rgLabel) ?></span></td>
                                                <td>
                                                    <span class="badge <?= $r['status'] === 'SENT' ? 'bg-success' : ($r['status'] === 'FAILED' ? 'bg-danger' : 'bg-secondary') ?>">
                                                        <?= htmlspecialchars($r['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="font-monospace small text-muted"><?= htmlspecialchars((string)($r['gateway_message_id'] ?: '—')) ?></td>
                                                <td class="small text-muted"><?= htmlspecialchars((string)($r['sent_at'] ?: '—')) ?></td>
                                                <td class="small text-danger"><?= htmlspecialchars((string)($r['error_message'] ?: '—')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <a href="?tab=campaigns" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.retry-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        if (!confirm('Are you sure you want to retry all failed messages for this campaign?')) return;

        this.disabled = true;
        const fd = new FormData();
        fd.append('action', 'retry_failed');
        fd.append('csrf_token', '<?= verify_csrf_token('') ? '' : (string)($_SESSION['csrf_token'] ?? '') ?>');
        fd.append('campaign_id', id);

        fetch('<?= BASE_URL ?>ajax/bulk_sms_action.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Successfully queued ' + res.retried_count + ' failed messages for retry. Redirecting to execution...');
                window.location.href = '<?= BASE_URL ?>admin/bulk_sms.php';
            } else {
                alert('Retry error: ' + (res.error || 'Failed'));
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
