<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Http\AbuseGuard;
use Edexcel\Http\EdgeProtectionStatus;

require_admin();

$error = '';
$success = '';
$actor = (int)($_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'block') {
                $ip = trim((string)($_POST['ip'] ?? ''));
                $minutes = (int)($_POST['minutes'] ?? 30);
                $reason = trim((string)($_POST['reason'] ?? ''));
                if (!AbuseGuard::block($ip, $minutes * 60, $reason !== '' ? $reason : 'Blocked by an administrator', 'manual', $actor)) {
                    throw new RuntimeException('Enter a valid IP address. Blocks last at most 24 hours.');
                }
                if (function_exists('log_audit')) {
                    log_audit($pdo, 'ABUSE_BLOCK', 'abuse_controls', null, null, ['ip' => $ip, 'minutes' => $minutes]);
                }
                $success = 'That address is temporarily blocked.';
            } elseif ($action === 'unblock' || $action === 'clear') {
                $ip = trim((string)($_POST['ip'] ?? ''));
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new RuntimeException('Enter a valid IP address.');
                }
                AbuseGuard::clearIp($ip);
                if (function_exists('log_audit')) {
                    log_audit($pdo, 'ABUSE_UNBLOCK', 'abuse_controls', null, null, ['ip' => $ip]);
                }
                $success = 'Rate limits and temporary blocks for that address were cleared.';
            } elseif ($action === 'thresholds') {
                $incoming = [];
                $posted = $_POST['limit'] ?? [];
                if (is_array($posted)) {
                    foreach (AbuseGuard::defaults() as $name => $rule) {
                        $row = is_array($posted[$name] ?? null) ? $posted[$name] : [];
                        $incoming[$name] = [
                            'max' => (int)($row['max'] ?? $rule['max']),
                            'window' => (int)($row['window'] ?? $rule['window']),
                            'block' => (int)($row['block'] ?? $rule['block']),
                        ];
                    }
                }
                AbuseGuard::saveThresholds($incoming);
                if (function_exists('log_audit')) {
                    log_audit($pdo, 'ABUSE_THRESHOLDS', 'abuse_controls', null, null, ['updated' => true]);
                }
                $success = 'Rate-limit thresholds were saved. Minimums are enforced so the site cannot be locked by a too-low value.';
            } else {
                $error = 'That action could not be completed.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$metrics = AbuseGuard::metrics();
$limits = AbuseGuard::activeLimits();
$events = AbuseGuard::recentEvents(60);
$tops = AbuseGuard::topBuckets();
$thresholds = AbuseGuard::thresholds();
$labels = [
    'global_anon' => 'Anonymous pages, per IP',
    'global_auth_ip' => 'Signed-in traffic, per IP (shared networks)',
    'global_auth_user' => 'Signed-in traffic, per account',
    'login' => 'Login attempts, per IP',
    'oauth' => 'Google sign-in, per IP',
    'register' => 'Registration and admissions, per IP',
    'payment_create' => 'Payment creation',
    'payment_webhook' => 'Payment callbacks',
    'webhook' => 'Other webhooks',
    'sms' => 'SMS code sends, per number',
    'sms_ip' => 'SMS code sends, per IP',
    'visitor' => 'Public visitor beacons, per IP',
    'api_public' => 'Public catalogue API, per IP',
    'classroom_user' => 'Live classroom, per account',
    'admin_user' => 'Admin actions, per account',
];

$dbConnections = null;
try {
    if ($pdo instanceof PDO) {
        $dbConnections = (int)$pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetchColumn(1);
    }
} catch (Throwable $e) {
    $dbConnections = null;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3">
    <h1 class="h3">Protection</h1>
    <p class="text-muted">Temporary limits for floods, brute force, and abusive clients. A limit expires on its own. This page does not permanently ban anyone. Large volumetric attacks still have to be absorbed by Cloudflare, not by this server.</p>

    <h2 class="h5">Protection status</h2>
    <p class="small text-muted">Each value is measured. A running firewall is not shown as protecting a port unless a readable firewalld rule limits that port. Students and teachers do not see this page.</p>
    <?php
    $statusItems = EdgeProtectionStatus::items();
    $statusGroups = [];
    foreach ($statusItems as $item) {
        $statusGroups[(string)$item['group']][] = $item;
    }
    ?>
    <?php foreach ($statusGroups as $groupName => $groupItems): ?>
        <h3 class="h6 text-uppercase text-muted mt-3"><?= e((string)$groupName) ?></h3>
        <div class="row g-3 mb-2">
            <?php foreach ($groupItems as $item): ?>
                <?php
                $state = (string)$item['state'];
                $badge = match ($state) {
                    'YES', 'OK', 'ACTIVE', 'ENABLED', 'PROTECTED', 'RESTRICTED', 'LOCAL', 'VERIFIED', 'CONFIRMED' => 'text-bg-success',
                    'DISABLED', 'INACTIVE', 'NO', 'CLOSED' => 'text-bg-secondary',
                    default => 'text-bg-warning',
                };
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold"><?= e((string)$item['label']) ?></div>
                                <span class="badge <?= e($badge) ?>"><?= e($state) ?></span>
                            </div>
                            <p class="small text-muted mb-0 mt-2"><?= e((string)$item['detail']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <p class="small text-muted">How to tell blocks apart: Cloudflare blocks never reach PHP. An origin firewall drop also never reaches PHP. Application limits are stored with layer <code>application-rate-limit</code>. The application origin lock uses <code>origin-lock</code>. Oversized bodies use <code>request-size</code>. Failed sign-ins stay in Security Center as failed login events. Denied classroom access stays as an authorization denial. Passwords, tokens, and session IDs are not written in these rows.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php foreach ($metrics['alerts'] as $alert): ?><div class="alert alert-warning"><?= e((string)$alert) ?></div><?php endforeach; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">PHP requests this minute</div><div class="h3 mb-0"><?= (int)$metrics['minute']['total'] ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">429 this minute</div><div class="h3 mb-0"><?= (int)$metrics['minute']['s429'] ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">4xx / 5xx this minute</div><div class="h3 mb-0"><?= (int)$metrics['minute']['s4'] ?> / <?= (int)$metrics['minute']['s5'] ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Load / DB connections</div><div class="h3 mb-0"><?= $metrics['load'] === null ? 'n/a' : e((string)$metrics['load']) ?> / <?= $dbConnections === null ? 'n/a' : (int)$dbConnections ?></div></div></div></div>
    </div>
    <p class="small text-muted">Counts are PHP requests that reached the application, measured in UTC minutes. Static files are not included. Last five minutes: <?= (int)$metrics['recent']['total'] ?> requests, <?= (int)$metrics['recent']['s429'] ?> limited, <?= (int)$metrics['recent']['s5'] ?> server errors.</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <h2 class="h5">Temporarily limited addresses</h2>
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Address</th><th>Scope</th><th>Reason</th><th>Until</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($limits === []): ?>
                        <tr><td colspan="5" class="text-muted">No temporary blocks right now.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($limits as $row): ?>
                        <tr>
                            <td><?= e((string)($row['ip'] ?? '')) ?></td>
                            <td><?= e((string)($row['bucket'] ?? '')) ?></td>
                            <td><?= e((string)($row['reason'] ?? '')) ?></td>
                            <td><?= e(date('Y-m-d H:i', (int)($row['until'] ?? 0))) ?></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="clear">
                                    <input type="hidden" name="ip" value="<?= e((string)($row['ip'] ?? '')) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Clear</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="h5">Recent protection events</h2>
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <thead><tr><th>When</th><th>Address</th><th>Layer</th><th>Endpoint group</th><th>Event</th></tr></thead>
                    <tbody>
                    <?php if ($events === []): ?>
                        <tr><td colspan="5" class="text-muted">No protection events yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $layer = (string)($event['layer'] ?? '');
                        if ($layer === '') {
                            $bucketName = (string)($event['bucket'] ?? '');
                            $layer = match ($bucketName) {
                                'origin' => 'origin-lock',
                                'body' => 'request-size',
                                default => 'application-rate-limit',
                            };
                        }
                        ?>
                        <tr>
                            <td><?= e(date('Y-m-d H:i:s', (int)($event['t'] ?? 0))) ?></td>
                            <td><?= e((string)($event['ip'] ?? '')) ?></td>
                            <td><?= e($layer) ?></td>
                            <td><?= e((string)($event['bucket'] ?? '')) ?></td>
                            <td><?= e((string)($event['message'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-5">
            <h2 class="h5">Block or clear an address</h2>
            <form method="post" class="card card-body mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="block">
                <label class="form-label" for="block-ip">IP address</label>
                <input class="form-control mb-2" id="block-ip" name="ip" required inputmode="decimal" autocomplete="off">
                <label class="form-label" for="block-minutes">Minutes (1–1440)</label>
                <input class="form-control mb-2" id="block-minutes" name="minutes" type="number" min="1" max="1440" value="30" required>
                <label class="form-label" for="block-reason">Reason</label>
                <input class="form-control mb-3" id="block-reason" name="reason" maxlength="160" placeholder="Payment flood">
                <button class="btn btn-danger" type="submit">Temporarily block</button>
            </form>
            <form method="post" class="card card-body mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unblock">
                <label class="form-label" for="clear-ip">Clear a rate limit or block</label>
                <input class="form-control mb-3" id="clear-ip" name="ip" required autocomplete="off">
                <button class="btn btn-outline-primary" type="submit">Unblock and clear limits</button>
            </form>

            <h2 class="h5">Top limited groups</h2>
            <ul class="list-group mb-4">
                <?php if ($tops === []): ?><li class="list-group-item text-muted">No limited groups in the recent event log.</li><?php endif; ?>
                <?php foreach ($tops as $top): ?>
                    <li class="list-group-item d-flex justify-content-between"><span><?= e((string)$top['bucket']) ?></span><span><?= (int)$top['count'] ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <h2 class="h5">Rate-limit thresholds</h2>
    <p class="small text-muted">Max is how many requests are allowed in the window. Block is how many seconds a tripped limit lasts. Values below the safety floor are raised automatically.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="thresholds">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Group</th><th>Max</th><th>Window seconds</th><th>Block seconds</th></tr></thead>
                <tbody>
                <?php foreach ($thresholds as $name => $rule): ?>
                    <tr>
                        <td><?= e($labels[$name] ?? $name) ?></td>
                        <td><input class="form-control form-control-sm" type="number" name="limit[<?= e($name) ?>][max]" value="<?= (int)$rule['max'] ?>" min="1"></td>
                        <td><input class="form-control form-control-sm" type="number" name="limit[<?= e($name) ?>][window]" value="<?= (int)$rule['window'] ?>" min="10"></td>
                        <td><input class="form-control form-control-sm" type="number" name="limit[<?= e($name) ?>][block]" value="<?= (int)$rule['block'] ?>" min="0"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary" type="submit">Save thresholds</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
