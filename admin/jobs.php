<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CashHandoverService;
use Edexcel\Services\FeeStatementService;
use Edexcel\Services\SystemHealthService;

require_admin();
ensure_ops_schema($pdo);

$error = '';
$success = '';
$health = new SystemHealthService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $otp = isset($_POST['admin_password_requires_otp']) ? '1' : '0';
        ops_save_setting($pdo, 'admin_password_requires_otp', $otp);
        $success = 'Saved.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$snap = $health->snapshot();
$otpOn = ops_setting($pdo, 'admin_password_requires_otp', '1') === '1';
$day = (new FeeStatementService($pdo))->dayEnd(date('Y-m-d'));

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-1"><i class="bi bi-heart-pulse text-danger"></i> System health</h1>
    <p class="text-muted">Cron last-run, WhatsApp token, Bunny, OnePay, and today’s cash. Point crontab at the <code>tools/</code> copies if <code>cron/</code> cannot be written.</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php $wa = $snap['whatsapp']; ?>
    <?php if (!empty($wa['warning']) || ($wa['valid'] === false)): ?>
        <div class="alert alert-warning">
            WhatsApp Cloud API token:
            <?= e((string)$wa['message']) ?>
            <?php if (!empty($wa['expires_at'])): ?>
                Expires <?= e($wa['expires_at']) ?>
                (<?= (int)$wa['days_left'] ?> days).
            <?php endif; ?>
            Paste a new API Setup token on <a href="<?= e(BASE_URL) ?>admin/whatsapp_connect.php">Connect WhatsApp</a>.
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">Cash today</div>
                <div class="fs-4 fw-bold">Rs <?= number_format((float)$day['cash']) ?></div>
                <div class="small">Not handed: Rs <?= number_format((float)$day['outstanding_cash']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">OnePay today</div>
                <div class="fs-4 fw-bold">Rs <?= number_format((float)$day['onepay']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">Wallet marked paid</div>
                <div class="fs-4 fw-bold">Rs <?= number_format((float)$day['wallet']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">WhatsApp outbox failed (24h)</div>
                <div class="fs-4 fw-bold"><?= (int)($snap['outbox']['failed_today'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h2 class="h5">Jobs</h2>
                    <p class="small text-muted mb-3">Install on the server (Asia/Colombo):<br>
                        <code>*/5 * * * * php /home/edexcel.college/public_html/tools/classroom_reminders.php</code><br>
                        <code>*/10 * * * * php /home/edexcel.college/public_html/tools/bunny_sync.php</code><br>
                        <code>*/5 * * * * php /home/edexcel.college/public_html/tools/ops_jobs.php</code><br>
                        <code>*/5 * * * * php /home/edexcel.college/public_html/tools/whatsapp_outbox.php</code><br>
                        <code>*/5 * * * * php /home/edexcel.college/public_html/tools/communication_queue.php</code><br>
                        <code>15 2 * * * php /home/edexcel.college/public_html/tools/automated_backup.php</code><br>
                        <code>30 8 * * * php /home/edexcel.college/public_html/tools/admissions_followup_worker.php</code>
                    </p>
                    <p class="small mb-3"><a href="<?= e(BASE_URL) ?>admin/system_health.php">Open full System Health Center</a>
                        · <a href="<?= e(BASE_URL) ?>admin/backup.php">Backup &amp; DR</a>
                        · <a href="<?= e(BASE_URL) ?>admin/security.php">Security</a>
                        · <a href="<?= e(BASE_URL) ?>admin/command_center.php">Command center</a></p>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Job</th><th>Last finished</th><th>OK</th><th>Note</th></tr></thead>
                            <tbody>
                            <?php foreach ($snap['jobs'] as $job): ?>
                                <tr>
                                    <td><?= e($job['job_name']) ?></td>
                                    <td><?= e($job['last_finished_at'] ?: '—') ?></td>
                                    <td><?= ((int)$job['last_ok'] === 1) ? 'Yes' : 'No' ?></td>
                                    <td class="small"><?= e($job['last_message'] ?: '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$snap['jobs']): ?>
                                <tr><td colspan="4" class="text-muted">No job has reported yet. After crontab runs, rows appear here.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-3 mb-0">
                        Bunny stuck processing: <?= (int)$snap['bunny']['stuck_processing'] ?>
                        · pending delete: <?= (int)$snap['bunny']['pending_delete'] ?>
                        · OnePay open checkouts: <?= (int)$snap['onepay']['open_checkouts'] ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body">
                    <h2 class="h5">Admin sign-in</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="admin_password_requires_otp" id="admin_otp" <?= $otpOn ? 'checked' : '' ?>>
                            <label class="form-check-label" for="admin_otp">After password, require WhatsApp OTP for admin (skipped if the account has no WhatsApp number)</label>
                        </div>
                        <button class="btn btn-primary rounded-pill mt-3">Save</button>
                    </form>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h2 class="h5">Office links</h2>
                    <ul class="mb-0">
                        <li><a href="<?= e(BASE_URL) ?>campus/cash.php">Teacher cash handover</a></li>
                        <li><a href="<?= e(BASE_URL) ?>campus/fees.php">Student fees + day end</a></li>
                        <li><a href="<?= e(BASE_URL) ?>admin/backup.php">Backup (secrets redacted)</a></li>
                        <li><a href="<?= e(BASE_URL) ?>admin/whatsapp_connect.php">Connect WhatsApp</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
