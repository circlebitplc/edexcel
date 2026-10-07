<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../student/device_helpers.php';

use Edexcel\Services\EmergencyDeviceAccessService;

require_admin();

$svc = student_devices($pdo);
$error = '';
$success = '';
$studentId = (int)($_GET['student'] ?? $_POST['student_id'] ?? 0);
$query = trim((string)($_GET['q'] ?? ''));
$emergencyFilter = (string)($_GET['emergency'] ?? 'pending');
if (!in_array($emergencyFilter, ['pending', 'approved', 'denied', 'expired', 'all'], true)) {
    $emergencyFilter = 'pending';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token.';
    } elseif (in_array((string)($_POST['action'] ?? ''), ['emergency_approve', 'emergency_deny'], true)) {
        $decision = (string)$_POST['action'] === 'emergency_approve' ? 'approved' : 'denied';
        $result = (new EmergencyDeviceAccessService($pdo))->decide((int)($_POST['request_id'] ?? 0), $decision, emergency_device_actor($pdo));
        if (!empty($result['ok'])) {
            if (function_exists('log_audit')) {
                log_audit($pdo, 'emergency_device_' . $result['status'], 'emergency_device_requests', (int)($_POST['request_id'] ?? 0));
            }
            $success = $result['status'] === 'approved' ? 'Temporary access approved for this class.' : 'The device request was denied.';
        } else {
            $error = (string)($result['error'] ?? 'That request could not be updated.');
        }
    } elseif ($svc && $studentId > 0) {
        $action = (string)($_POST['action'] ?? '');
        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($svc->adminSetStatus($studentId, $deviceId, $action)) {
            log_audit($pdo, 'admin_' . $action . '_student_device', 'student_devices', $deviceId);
            $success = 'Device updated.';
        } else {
            $error = 'That device could not be updated.';
        }
    }
}

$matches = [];
if ($query !== '' && $studentId < 1) {
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, sp.full_name
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.role = 'student' AND u.deleted_at IS NULL
          AND (CAST(u.id AS CHAR) = ? OR u.username LIKE ? OR sp.full_name LIKE ? OR u.email LIKE ?)
        ORDER BY u.id DESC
        LIMIT 20
    ");
    try {
        $stmt->execute([$query, $like, $like, $like]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, sp.full_name
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.role = 'student' AND u.deleted_at IS NULL
              AND (CAST(u.id AS CHAR) = ? OR u.username LIKE ? OR sp.full_name LIKE ?)
            ORDER BY u.id DESC
            LIMIT 20
        ");
        $stmt->execute([$query, $like, $like]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$student = null;
$devices = [];
$history = [];
$frequent = false;
if ($svc && $studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, sp.full_name
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ? AND u.role = 'student'
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($student) {
        $devices = $svc->listDevices($studentId, true);
        $history = $svc->replacementHistory($studentId, 30);
        $frequent = $svc->frequentDeviceChanges($studentId);
    }
}

$emergencyRows = [];
try {
    $emergencyRows = (new EmergencyDeviceAccessService($pdo))->history($emergencyFilter === 'all' ? '' : $emergencyFilter, 80);
} catch (Throwable $e) {
    $emergencyRows = [];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3">
    <h1 class="h3">Student devices</h1>
    <p class="text-muted">Each student can keep 2 active devices. Replaced devices stay blocked for 14 days. History is kept.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form class="row g-2 mb-4" method="get">
        <div class="col-auto">
            <input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Name, username, or student id">
        </div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Find</button></div>
    </form>
    <?php if ($matches): ?>
        <ul class="list-group mb-4">
            <?php foreach ($matches as $row): ?>
                <li class="list-group-item">
                    <a href="?student=<?= (int)$row['id'] ?>"><?= e((string)($row['full_name'] ?: $row['username'])) ?></a>
                    <span class="text-muted">#<?= (int)$row['id'] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($student): ?>
        <h2 class="h5"><?= e((string)($student['full_name'] ?: $student['username'])) ?> <span class="text-muted">#<?= (int)$student['id'] ?></span></h2>
        <?php if ($frequent): ?>
            <div class="alert alert-warning"><strong>Frequent device changes detected.</strong> Review the history below. This is a warning, not a ban.</div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Status</th>
                        <th>First used</th>
                        <th>Last used</th>
                        <th>Blocked until</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($devices as $device): ?>
                    <?php
                    $status = strtoupper((string)($device['status'] ?? ''));
                    if ($status === '') {
                        $status = empty($device['revoked_at']) ? 'ACTIVE' : 'REPLACED';
                    }
                    ?>
                    <tr>
                        <td><?= e(\Edexcel\Services\StudentDeviceService::publicLabel((string)($device['label'] ?? ''))) ?></td>
                        <td><?= e($status) ?></td>
                        <td><?= e(\Edexcel\Services\StudentDeviceService::formatLastUsed((string)($device['first_seen_at'] ?? ''))) ?></td>
                        <td><?= e(\Edexcel\Services\StudentDeviceService::formatLastUsed((string)($device['last_seen_at'] ?? ''))) ?></td>
                        <td><?= e(trim((string)($device['blocked_until'] ?? '')) !== '' ? \Edexcel\Services\StudentDeviceService::formatBlockedUntil((string)$device['blocked_until']) : '—') ?></td>
                        <td class="text-nowrap">
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
                                <input type="hidden" name="device_id" value="<?= (int)$device['id'] ?>">
                                <?php if ($status === 'ACTIVE'): ?>
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="revoke" type="submit">Revoke</button>
                                    <button class="btn btn-sm btn-outline-warning" name="action" value="block" type="submit">Block</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="unblock" type="submit">Unblock</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($devices === []): ?><tr><td colspan="6">No devices recorded.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <h3 class="h6 mt-4">Device change history</h3>
        <ul class="list-unstyled">
            <?php foreach ($history as $event): ?>
                <li>
                    <?= e(\Edexcel\Services\StudentDeviceService::formatLastUsed((string)($event['created_at'] ?? ''))) ?>
                    — <?= e((string)($event['event_name'] ?? '')) ?>
                    — <?= e((string)($event['device_label'] ?? '')) ?>
                </li>
            <?php endforeach; ?>
            <?php if ($history === []): ?><li class="text-muted">No replacements recorded.</li><?php endif; ?>
        </ul>
    <?php endif; ?>

    <h2 class="h5 mt-4" id="emergency-requests">Emergency Device Requests</h2>
    <p class="text-muted">A third device can be approved for one live class only. The student's limit stays at 2 devices. These records are kept.</p>
    <div class="mb-3">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'expired' => 'Expired', 'all' => 'All'] as $key => $label): ?>
            <a class="btn btn-sm <?= $emergencyFilter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?emergency=<?= e($key) ?>#emergency-requests"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Device</th>
                    <th>Requested</th>
                    <th>Decision</th>
                    <th>Approved by</th>
                    <th>Access until</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($emergencyRows as $request): ?>
                <?php
                $decision = (string)($request['status'] ?? '');
                $approver = trim((string)($request['decided_by_role'] ?? ''));
                if ($approver === 'teacher') {
                    $approver = 'Teacher';
                } elseif ($approver === 'admin') {
                    $approver = 'Admin';
                }
                $approverName = trim((string)($request['decided_by_name'] ?? ''));
                if ($approver !== '' && $approverName !== '') {
                    $approver .= ' (' . $approverName . ')';
                }
                $accessUntil = (string)($request['access_until'] ?? '');
                ?>
                <tr>
                    <td><?= e((string)($request['student_name'] ?? '')) ?></td>
                    <td><?= e((string)($request['class_label'] ?? '')) ?><div class="text-muted"><?= e((string)($request['teacher_name'] ?? '')) ?></div></td>
                    <td><?= e((string)($request['device_label'] ?? '')) ?></td>
                    <td><?= e((string)($request['requested_at'] ?? '')) ?></td>
                    <td><?= e(ucfirst($decision)) ?><?php if (!empty($request['reason'])): ?><div class="text-muted"><?= e((string)$request['reason']) ?></div><?php endif; ?></td>
                    <td><?= $approver !== '' ? e($approver) : '—' ?></td>
                    <td><?= $accessUntil !== '' ? e($accessUntil) : '—' ?></td>
                    <td>
                        <?php if ($decision === 'pending'): ?>
                            <form method="post" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                <button class="btn btn-sm btn-success" name="action" value="emergency_approve" type="submit">Allow</button>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="emergency_deny" type="submit">Deny</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($emergencyRows === []): ?><tr><td colspan="8">No emergency device requests in this view.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
