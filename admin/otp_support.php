<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/otp_support_log.php';

require_admin();

$phone = trim((string)($_GET['phone'] ?? ''));
$rows = otp_support_log_list($pdo, $phone, 100);

function otp_support_format_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (preg_match('/^94(7\d{8})$/', $digits, $m)) {
        return '0' . $m[1] . ' · ' . $digits;
    }
    return $digits !== '' ? $digits : $phone;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-shield-lock"></i> OTP support desk</h1>
            <p class="text-muted mb-0">
                When SMS or WhatsApp OTP delivery fails (signal / Outbox), look up the number and read the code to the caller.
                Codes are kept for 24 hours only.
            </p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>admin/settings.php#panel-otp">OTP / SMS settings</a>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-5 col-lg-4">
            <label class="form-label" for="otp-phone">Phone number</label>
            <input
                class="form-control"
                id="otp-phone"
                name="phone"
                value="<?= e($phone) ?>"
                placeholder="0771234567 or 94771234567"
                autocomplete="off"
                autofocus
            >
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Find OTP</button>
            <?php if ($phone !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/otp_support.php">Clear</a>
            <?php endif; ?>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary" type="button" onclick="location.reload()">Refresh</button>
        </div>
    </form>

    <div class="alert alert-warning py-2">
        Only share a code after confirming the caller’s phone number. Prefer the newest <strong>active</strong> row for that number.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Sent</th>
                        <th>Phone</th>
                        <th>OTP</th>
                        <th>Type</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="7" class="text-muted py-4 text-center">
                            <?= $phone !== '' ? 'No OTPs found for that number in the last 24 hours.' : 'No OTPs recorded in the last 24 hours yet.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $active = ($r['validity'] ?? '') === 'active';
                        $code = (string)($r['otp_code'] ?? '');
                        ?>
                        <tr class="<?= $active ? '' : 'table-secondary' ?>">
                            <td class="text-nowrap small"><?= e((string)$r['created_at']) ?></td>
                            <td class="text-nowrap">
                                <span class="fw-semibold"><?= e(otp_support_format_phone((string)$r['phone'])) ?></span>
                                <?php if (!empty($r['user_id'])): ?>
                                    <div class="small text-muted">User #<?= (int)$r['user_id'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="fs-5 user-select-all"><?= e($code) ?></code>
                            </td>
                            <td><?= e(otp_support_purpose_label((string)$r['purpose'])) ?></td>
                            <td class="text-uppercase small"><?= e((string)$r['channel']) ?></td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Expired</span>
                                <?php endif; ?>
                                <?php if (!(int)($r['sent'] ?? 0)): ?>
                                    <span class="badge text-bg-warning text-dark">Send failed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap small"><?= e((string)$r['expires_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
