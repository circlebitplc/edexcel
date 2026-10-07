<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../student/device_helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\EmergencyDeviceAccessService;
use Edexcel\Services\SecurityEventService;

require_admin();

$devices = student_devices($pdo);
$security = new SecurityEventService($pdo);
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($action === 'revoke_session' && $devices && $studentId > 0 && $devices->revokeActiveSession($studentId)) {
            if (function_exists('log_audit')) {
                log_audit($pdo, 'SESSION_REVOKED', 'student_active_sessions', $studentId);
            }
            $success = 'That student was signed out. Their registered devices were kept.';
        } elseif ($action === 'revoke_device' && $devices && $studentId > 0) {
            if ($devices->adminSetStatus($studentId, (int)($_POST['device_id'] ?? 0), 'revoke')) {
                $success = 'The device was revoked and its session was ended.';
            } else {
                $error = 'That device could not be revoked.';
            }
        } else {
            $error = 'That action could not be completed.';
        }
    }
}

$counts = $security->counts();
$live = $security->liveStudents();
$boards = $security->deviceBoards();
$events = $security->recent('', 60);
$requests = [];
try {
    $requests = (new EmergencyDeviceAccessService($pdo))->history('', 40);
} catch (Throwable $e) {
    $requests = [];
}

require_once __DIR__ . '/../includes/header.php';

$eventLabel = static function (string $code): string {
    return match ($code) {
        'LOGIN_SUCCESS' => 'Login success',
        'LOGIN_FAILED' => 'Failed login',
        'DEVICE_REGISTERED' => 'Device registered',
        'DEVICE_REPLACED' => 'Device replaced',
        'DEVICE_BLOCKED' => 'Device blocked',
        'DEVICE_UNBLOCKED' => 'Device unblocked',
        'SESSION_CREATED' => 'Session created',
        'SESSION_REVOKED' => 'Session revoked',
        'LIVE_CLASS_ACCESS_GRANTED' => 'Live class granted',
        'LIVE_CLASS_ACCESS_DENIED' => 'Live class denied',
        'LIVEKIT_TOKEN_ISSUED' => 'LiveKit token issued',
        'EMERGENCY_DEVICE_REQUESTED' => 'Emergency requested',
        'EMERGENCY_DEVICE_APPROVED' => 'Emergency approved',
        'EMERGENCY_DEVICE_DENIED' => 'Emergency denied',
        'EMERGENCY_DEVICE_EXPIRED' => 'Emergency expired',
        'SUSPICIOUS_ACTIVITY' => 'Suspicious activity',
        'RATE_LIMIT' => 'Rate limit',
        default => $code,
    };
};
?>
<div class="container-fluid py-3">
    <h1 class="h3">Security Center</h1>
    <p class="text-muted">Student access, devices, emergency requests, and security events. History is kept. Flood limits and temporary IP blocks are on <a href="/admin/protection.php">Protection</a>.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Live students</div><div class="h3 mb-0"><?= (int)$counts['online'] ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Active devices</div><div class="h3 mb-0"><?= (int)$counts['active_devices'] ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Pending requests</div><div class="h3 mb-0"><?= (int)$counts['pending_requests'] ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Suspicious, 24h</div><div class="h3 mb-0"><?= (int)$counts['suspicious'] ?></div></div></div></div>
    </div>

    <h2 class="h5">Live students</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead><tr><th>Student</th><th>Class</th><th>Teacher</th><th>Device</th><th>Login</th><th>Last activity</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($live as $row): ?>
                <tr>
                    <td><?= e((string)$row['student_name']) ?></td>
                    <td><?= e((string)$row['class_label']) ?></td>
                    <td><?= e((string)$row['teacher_name']) ?></td>
                    <td><?= e((string)($row['device_label'] ?? '')) ?></td>
                    <td class="small"><?= e((string)($row['last_login_at'] ?? '')) ?></td>
                    <td class="small"><?= e((string)($row['last_activity'] ?? '')) ?><div class="text-muted"><?= e(SecurityEventService::maskIp((string)($row['ip_address'] ?? ''))) ?></div></td>
                    <td>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" value="<?= (int)$row['user_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="action" value="revoke_session" type="submit">Sign out</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($live === []): ?><tr><td colspan="7">No students have been active in the last 15 minutes.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h5">Devices</h2>
    <div class="row g-3 mb-4">
        <?php
        $deviceSections = [
            'Active' => $boards['active'],
            'Newly registered or changed' => $boards['recent'],
            'Blocked or replaced' => $boards['blocked'],
            'Approaching block end' => $boards['expiring'],
        ];
        foreach ($deviceSections as $title => $rows):
        ?>
        <div class="col-12 col-xl-6">
            <div class="card h-100"><div class="card-header"><?= e($title) ?></div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Student</th><th>Device</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e((string)($row['student_name'] ?? '')) ?></td>
                            <td><?= e((string)($row['label'] ?? '')) ?></td>
                            <td class="small"><?= e((string)($row['status'] ?? '')) ?><?php if (!empty($row['blocked_until'])): ?><div class="text-muted"><?= e((string)$row['blocked_until']) ?></div><?php endif; ?></td>
                            <td>
                                <?php if ((string)($row['status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="student_id" value="<?= (int)$row['user_id'] ?>">
                                    <input type="hidden" name="device_id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="revoke_device" type="submit">Revoke</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="4">None</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h2 class="h5">Access requests</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead><tr><th>Student</th><th>Class</th><th>Device</th><th>Requested</th><th>Decision</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e((string)($request['student_name'] ?? '')) ?></td>
                    <td><?= e((string)($request['class_label'] ?? '')) ?></td>
                    <td><?= e((string)($request['device_label'] ?? '')) ?></td>
                    <td class="small"><?= e((string)($request['requested_at'] ?? '')) ?></td>
                    <td><?= e(ucfirst((string)($request['status'] ?? ''))) ?></td>
                    <td><?= e(trim((string)($request['decided_by_role'] ?? '') . ' ' . (string)($request['decided_by_name'] ?? ''))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requests === []): ?><tr><td colspan="6">No emergency device requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h5">Security events</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead><tr><th>When</th><th>Event</th><th>Student</th><th>Result</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td class="small"><?= e((string)($event['created_at'] ?? '')) ?></td>
                    <td><?= e($eventLabel((string)($event['event_code'] ?? ''))) ?></td>
                    <td><?= (int)($event['user_id'] ?? 0) > 0 ? (int)$event['user_id'] : '—' ?></td>
                    <td><?= e((string)($event['result'] ?? '')) ?></td>
                    <td class="small"><?= e((string)($event['message'] ?? '')) ?></td>
                    <td class="small"><?= e(SecurityEventService::maskIp((string)($event['ip_address'] ?? ''))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($events === []): ?><tr><td colspan="6">No security events yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
