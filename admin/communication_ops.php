<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationHubService;

try {
    $auth = CommunicationAuth::require($pdo, 'communication.manage');
} catch (Throwable $e) {
    try {
        $auth = CommunicationAuth::require($pdo, 'communication.analytics');
    } catch (Throwable $e2) {
        if (!function_exists('is_admin') || !is_admin()) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
        $auth = ['user_id' => (int)$_SESSION['user_id'], 'role' => 'admin'];
    }
}

$hub = new CommunicationHubService($pdo);
$error = '';
$success = '';

$filters = [
    'channel' => (string)($_GET['channel'] ?? $_POST['filter_channel'] ?? ''),
    'from' => (string)($_GET['from'] ?? $_POST['filter_from'] ?? ''),
    'to' => (string)($_GET['to'] ?? $_POST['filter_to'] ?? ''),
    'q' => (string)($_GET['q'] ?? $_POST['filter_q'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'retry_one') {
            $hub->retryFailed((int)$_POST['recipient_id'], (int)$auth['user_id']);
            $success = 'Recipient re-queued for background delivery.';
        } elseif ($action === 'retry_selected') {
            $ids = array_map('intval', (array)($_POST['ids'] ?? []));
            $result = $hub->retryFailedBatch($ids, (int)$auth['user_id']);
            $success = 'Retried '.$result['retried'].', skipped '.$result['skipped'].'.';
            if ($result['errors'] !== []) {
                $error = implode('; ', $result['errors']);
            }
        } elseif ($action === 'retry_filtered') {
            $result = $hub->retryFailedMatching([
                'channel' => (string)($_POST['filter_channel'] ?? ''),
                'from' => (string)($_POST['filter_from'] ?? ''),
                'to' => (string)($_POST['filter_to'] ?? ''),
                'q' => (string)($_POST['filter_q'] ?? ''),
            ], (int)$auth['user_id'], 50);
            $success = 'Filter retry: retried '.$result['retried'].', skipped '.$result['skipped'].' (cap 50).';
            if ($result['errors'] !== []) {
                $error = implode('; ', $result['errors']);
            }
            $filters = [
                'channel' => (string)($_POST['filter_channel'] ?? ''),
                'from' => (string)($_POST['filter_from'] ?? ''),
                'to' => (string)($_POST['filter_to'] ?? ''),
                'q' => (string)($_POST['filter_q'] ?? ''),
            ];
        } elseif ($action === 'process_now') {
            $stats = $hub->processQueue(25);
            $success = 'Processed '.$stats['processed'].' (sent '.$stats['sent'].', failed '.$stats['failed'].').';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

$snap = $hub->opsSnapshot();
$failed = $hub->listFailedRecipients(120, $filters);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <a href="communications.php">← Communication centre</a>
    <h1 class="h3 mt-2">Channel ops console</h1>
    <p class="text-muted">Failed deliveries with channel/date filters. Retries re-queue only. Max <?= (int)CommunicationHubService::MAX_RETRIES ?> retries each.
        <a href="communication_workbench.php">Student workbench</a>
    </p>
    <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="row g-2 mb-3">
        <?php foreach ([['Failed',$snap['failed']],['Queued',$snap['queued']],['Delivered',$snap['delivered']],['Read',$snap['read']],['Retries today',$snap['retries_today']],['Webhook events 24h',$snap['webhook_events_24h']]] as $c): ?>
            <div class="col"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-4 fw-bold"><?= e((string)$c[1]) ?></div></div></div>
        <?php endforeach; ?>
    </div>

    <form method="get" class="card border-0 shadow-sm p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Channel</label>
                <select class="form-select" name="channel">
                    <option value="">All</option>
                    <?php foreach (['in_app','whatsapp','sms'] as $ch): ?>
                        <option value="<?= e($ch) ?>" <?= $filters['channel']===$ch?'selected':'' ?>><?= e($ch) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small">From</label><input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
            <div class="col-md-2"><label class="form-label small">To</label><input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
            <div class="col-md-4"><label class="form-label small">Search</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Subject, phone, student/parent id"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </div>
    </form>

    <form method="post" class="d-inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="process_now">
        <button class="btn btn-outline-primary btn-sm">Process queue now (batch 25)</button>
    </form>
    <form method="post" class="d-inline ms-2" onsubmit="return confirm('Retry up to 50 failed rows matching the current filters?');"><?= csrf_field() ?>
        <input type="hidden" name="action" value="retry_filtered">
        <input type="hidden" name="filter_channel" value="<?= e($filters['channel']) ?>">
        <input type="hidden" name="filter_from" value="<?= e($filters['from']) ?>">
        <input type="hidden" name="filter_to" value="<?= e($filters['to']) ?>">
        <input type="hidden" name="filter_q" value="<?= e($filters['q']) ?>">
        <button class="btn btn-warning btn-sm" <?= $failed===[]?'disabled':'' ?>>Retry all matching (cap 50)</button>
    </form>

    <form method="post" class="card border-0 shadow-sm mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="retry_selected">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Failed recipients (<?= count($failed) ?> shown)</strong>
            <button class="btn btn-warning btn-sm" <?= $failed === [] ? 'disabled' : '' ?>>Retry selected</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th></th><th>ID</th><th>Channel</th><th>Audience</th><th>Subject</th><th>Detail</th><th>When</th><th>Retries</th></tr></thead>
                <tbody>
                <?php if ($failed === []): ?>
                    <tr><td colspan="8" class="text-muted p-3">No failed deliveries for these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($failed as $r): ?>
                    <tr>
                        <td><?php if ((int)($r['retry_count'] ?? 0) < CommunicationHubService::MAX_RETRIES): ?><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"><?php endif; ?></td>
                        <td>#<?= (int)$r['id'] ?></td>
                        <td><?= e((string)$r['channel']) ?></td>
                        <td><?= e((string)$r['audience']) ?><?= !empty($r['user_id']) ? ' u'.(int)$r['user_id'] : '' ?><?= !empty($r['parent_id']) ? ' p'.(int)$r['parent_id'] : '' ?>
                            <?php if (!empty($r['user_id'])): ?> <a class="small" href="student360.php?student=<?= (int)$r['user_id'] ?>&tab=communication">360</a><?php endif; ?>
                        </td>
                        <td><?= e(mb_strimwidth((string)($r['subject'] ?? ''), 0, 40, '…')) ?></td>
                        <td class="small text-muted"><?= e(mb_strimwidth((string)($r['exclusion_reason'] ?? ''), 0, 50, '…')) ?></td>
                        <td class="small"><?= e((string)($r['updated_at'] ?? $r['created_at'] ?? '')) ?></td>
                        <td><?= (int)($r['retry_count'] ?? 0) ?>/<?= (int)CommunicationHubService::MAX_RETRIES ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php if ($failed !== []): ?>
        <div class="card border-0 shadow-sm mt-3 p-3">
            <h2 class="h6">Single retry</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($failed as $r): ?>
                    <?php if ((int)($r['retry_count'] ?? 0) >= CommunicationHubService::MAX_RETRIES) continue; ?>
                    <form method="post" class="d-inline"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="retry_one">
                        <input type="hidden" name="recipient_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-outline-warning">#<?= (int)$r['id'] ?> <?= e((string)$r['channel']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
