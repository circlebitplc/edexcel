<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\SystemHealthService;

require_admin();
ensure_ops_schema($pdo);

$health = new SystemHealthService($pdo);
$snap = $health->snapshot();
$indicators = $snap['indicators'] ?? [];
$counts = $snap['counts'] ?? [];

$statusColor = static function (string $status): string {
    return match (strtolower($status)) {
        'green', 'ok', 'healthy' => 'success',
        'yellow', 'warn', 'warning', 'degraded' => 'warning',
        'red', 'error', 'critical', 'down' => 'danger',
        default => 'secondary',
    };
};

$statusLabel = static function (array $block): string {
    $s = strtolower((string)($block['status'] ?? $block['level'] ?? 'grey'));
    if ($s === '') {
        $s = 'grey';
    }
    return $s;
};

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-heart-pulse text-danger"></i> System health</h1>
            <p class="text-muted mb-0">Live snapshot of database, cron, integrations, backups, and payment queues.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>admin/jobs.php"><i class="bi bi-list-task"></i> Jobs &amp; cash</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>admin/backup.php"><i class="bi bi-cloud-arrow-up"></i> Backups</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>admin/command_center.php"><i class="bi bi-speedometer2"></i> Command center</a>
            <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>admin/system_health.php"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <?php
        $countCards = [
            'Failed jobs' => (int)($counts['failed_jobs'] ?? 0),
            'Failed payments' => (int)($counts['failed_payments'] ?? 0),
            'Pending bank slips' => (int)($counts['pending_bank_slips'] ?? 0),
            'Stuck payments' => (int)($counts['stuck_payments'] ?? 0),
            'Stuck recordings' => (int)($counts['stuck_recordings'] ?? 0),
            'WA failed today' => (int)($counts['failed_whatsapp'] ?? 0),
            'Outbox pending' => (int)($counts['pending_outbox'] ?? 0),
        ];
        foreach ($countCards as $label => $n):
            $tone = $n > 0 ? 'warning' : 'success';
        ?>
            <div class="col-6 col-md-3 col-xl">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                    <div class="small text-muted"><?= e($label) ?></div>
                    <div class="fs-4 fw-bold text-<?= e($tone) ?>"><?= $n ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="h5 mb-3">Service indicators</h2>
    <div class="row g-3 mb-4">
        <?php
        $blocks = [
            'Database' => $snap['database'] ?? [],
            'PHP' => $snap['php'] ?? [],
            'Disk' => $snap['disk'] ?? [],
            'SSL' => $snap['ssl'] ?? [],
            'Cron' => $snap['cron'] ?? [],
            'Cache' => $snap['cache'] ?? [],
            'Uploads' => $snap['uploads'] ?? [],
            'WhatsApp' => $snap['whatsapp'] ?? [],
            'OnePay' => $snap['onepay'] ?? [],
            'Bunny' => $snap['bunny'] ?? [],
            'LiveKit' => $snap['livekit'] ?? [],
            'AI' => $snap['ai'] ?? [],
            'SMS' => $snap['sms'] ?? [],
            'Backup' => $snap['backup'] ?? [],
            'Payments' => $snap['payments'] ?? [],
            'Outbox' => $snap['outbox'] ?? [],
            'Migrations' => $snap['migrations'] ?? [],
        ];
        if (is_array($indicators) && $indicators !== []) {
            // Prefer compact indicators list when present
            foreach ($indicators as $name => $ind) {
                if (is_string($name) && is_array($ind)) {
                    $blocks[$name] = array_merge($blocks[$name] ?? [], $ind);
                }
            }
        }
        foreach ($blocks as $name => $block):
            if (!is_array($block)) {
                continue;
            }
            $st = $statusLabel($block);
            $color = $statusColor($st);
            $msg = (string)($block['message'] ?? $block['detail'] ?? $block['label'] ?? '');
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex gap-3">
                        <div class="rounded-circle bg-<?= e($color) ?> bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:42px;height:42px" title="<?= e($st) ?>">
                            <span class="rounded-circle bg-<?= e($color) ?>" style="width:14px;height:14px;display:inline-block"></span>
                        </div>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= e((string)$name) ?></div>
                            <div class="small text-muted text-uppercase"><?= e($st) ?></div>
                            <?php if ($msg !== ''): ?>
                                <div class="small mt-1 text-break"><?= e($msg) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($block['used_percent'])): ?>
                                <div class="small">Disk used: <?= e((string)$block['used_percent']) ?>%</div>
                            <?php endif; ?>
                            <?php if (!empty($block['last_run'])): ?>
                                <div class="small">Last run: <?= e((string)$block['last_run']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($block['pending'])): ?>
                                <div class="small">Pending: <?= (int)$block['pending'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $jobs = $snap['jobs'] ?? [];
    if (is_array($jobs) && $jobs !== []):
    ?>
        <h2 class="h5 mb-3">Cron / job last runs</h2>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Last run</th>
                            <th>Status</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <?php
                            if (!is_array($job)) {
                                continue;
                            }
                            $jst = strtolower((string)($job['status'] ?? 'grey'));
                            ?>
                            <tr>
                                <td><?= e((string)($job['name'] ?? $job['job'] ?? '—')) ?></td>
                                <td><?= e((string)($job['last_run'] ?? $job['ran_at'] ?? '—')) ?></td>
                                <td><span class="badge text-bg-<?= e($statusColor($jst)) ?>"><?= e($jst) ?></span></td>
                                <td class="small text-muted"><?= e((string)($job['message'] ?? $job['detail'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-3">
        <div class="small text-muted">App</div>
        <div>
            Version <?= e((string)($snap['app']['version'] ?? '—')) ?>
            · PHP <?= e((string)($snap['php']['version'] ?? PHP_VERSION)) ?>
        </div>
        <div class="mt-2">
            <a href="<?= e(BASE_URL) ?>admin/jobs.php">Open jobs dashboard</a>
            ·
            <a href="<?= e(BASE_URL) ?>admin/backup.php">Open backups</a>
            ·
            <a href="<?= e(BASE_URL) ?>admin/security.php">Security events</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
