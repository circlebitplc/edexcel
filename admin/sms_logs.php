<?php
declare(strict_types=1);

/**
 * Admin SMS Logs page.
 * Shows all outgoing SMS attempts from both SMS-Gate and iPromo.
 * Accessible only to admins. Phone numbers are masked in the UI.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_admin();

ensure_sms_log_schema($pdo);

// ─── Filters ──────────────────────────────────────────────────────────────────
$filterStatus   = trim((string)($_GET['status']    ?? ''));
$filterProvider = trim((string)($_GET['provider']  ?? ''));
$filterDate     = trim((string)($_GET['date']      ?? ''));
$filterRecip    = trim((string)($_GET['recipient'] ?? ''));
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 50;
$offset         = ($page - 1) * $perPage;

$allowedStatuses   = ['', 'sent', 'failed', 'skipped', 'pending'];
$allowedProviders  = ['', 'ipromo', 'sms-gate'];
if (!in_array($filterStatus,   $allowedStatuses,  true)) { $filterStatus   = ''; }
if (!in_array($filterProvider, $allowedProviders, true)) { $filterProvider = ''; }
if ($filterDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) { $filterDate = ''; }

// ─── Query ────────────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($filterStatus !== '') {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterProvider !== '') {
    $where[]  = 'provider = ?';
    $params[] = $filterProvider;
}
if ($filterDate !== '') {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filterDate;
}
if ($filterRecip !== '') {
    $digits = preg_replace('/\D+/', '', $filterRecip) ?? '';
    if ($digits !== '') {
        $where[]  = 'recipient LIKE ?';
        $params[] = '%' . $digits . '%';
    }
}

$whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sms_logs $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $rowStmt = $pdo->prepare(
        "SELECT id, recipient, message, provider, status,
                provider_message_id, error_message, sent_by, context, created_at
         FROM sms_logs $whereClause
         ORDER BY id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $rowStmt->execute($params);
    $rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('admin/sms_logs.php query: ' . $e->getMessage());
    $total = 0;
    $rows  = [];
}

$pages   = $total > 0 ? (int)ceil($total / $perPage) : 1;
$baseUrl = htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8');

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function pagerUrl(int $p, string $status, string $provider, string $date, string $recip): string
{
    $q = http_build_query(array_filter([
        'page'      => $p > 1 ? $p : null,
        'status'    => $status,
        'provider'  => $provider,
        'date'      => $date,
        'recipient' => $recip,
    ]));
    return '?' . $q;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="flex-grow-1">
            <h1 class="h4 mb-0"><i class="bi bi-chat-dots"></i> SMS Logs</h1>
            <p class="text-muted small mb-0">Outgoing SMS via SMS-Gate and iPromo. Phone numbers are partially masked.</p>
        </div>
        <a href="<?= $baseUrl ?>admin/settings.php?tab=otp" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-gear"></i> SMS Settings
        </a>
    </div>

    <?php if ($total === 0 && $whereClause === ''): ?>
        <div class="alert alert-info">No SMS messages have been sent yet through this system.</div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach (['sent', 'failed', 'skipped', 'pending'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1">Provider</label>
            <select name="provider" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="ipromo"  <?= $filterProvider === 'ipromo'   ? 'selected' : '' ?>>iPromo</option>
                <option value="sms-gate" <?= $filterProvider === 'sms-gate' ? 'selected' : '' ?>>SMS-Gate</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1">Recipient (digits)</label>
            <input type="text" name="recipient" class="form-control form-control-sm" value="<?= e($filterRecip) ?>" placeholder="e.g. 0771">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="small text-muted mb-2"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></div>

    <?php if ($rows): ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date/Time</th>
                    <th>Recipient</th>
                    <th>Message</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>Context</th>
                    <th>Provider ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-nowrap small"><?= e((string)($row['created_at'] ?? '')) ?></td>
                    <td class="text-nowrap small font-monospace"><?= e(sms_mask_phone((string)($row['recipient'] ?? ''))) ?></td>
                    <td class="small" style="max-width:320px;white-space:normal;">
                        <?= e(mb_substr((string)($row['message'] ?? ''), 0, 80)) ?>
                        <?php if (mb_strlen((string)($row['message'] ?? '')) > 80): ?><span class="text-muted">…</span><?php endif; ?>
                        <?php if (!empty($row['error_message'])): ?>
                            <div class="text-danger small mt-1"><?= e((string)$row['error_message']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($row['provider'] === 'ipromo'): ?>
                            <span class="badge bg-primary">iPromo</span>
                        <?php elseif ($row['provider'] === 'sms-gate'): ?>
                            <span class="badge bg-secondary">SMS-Gate</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark"><?= e((string)$row['provider']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $st = strtolower((string)($row['status'] ?? '')); ?>
                        <?php if ($st === 'sent'): ?>
                            <span class="badge bg-success">Sent</span>
                        <?php elseif ($st === 'failed'): ?>
                            <span class="badge bg-danger">Failed</span>
                        <?php elseif ($st === 'skipped'): ?>
                            <span class="badge bg-warning text-dark">Skipped</span>
                        <?php elseif ($st === 'pending'): ?>
                            <span class="badge bg-info text-dark">Pending</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark"><?= e($st) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e((string)($row['context'] ?? '')) ?></td>
                    <td class="small font-monospace text-muted">
                        <?= $row['provider_message_id'] ? e(mb_substr((string)$row['provider_message_id'], 0, 20)) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= pagerUrl($p, $filterStatus, $filterProvider, $filterDate, $filterRecip) ?>">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php elseif ($whereClause !== ''): ?>
        <div class="alert alert-warning">No SMS logs match the selected filters.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
