<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';

use Edexcel\Services\ParentLinkService;

require_admin();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$links = new ParentLinkService($pdo);
$message = '';
$error = '';
$adminId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Session expired. Try again.');
        }
        $requestId = (int)($_POST['request_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'approve') {
            $result = $links->approve($requestId, $adminId);
        } elseif ($action === 'reject') {
            $result = $links->reject($requestId, $adminId, (string)($_POST['rejection_reason'] ?? ''));
        } elseif ($action === 'activate_student') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $result = activate_pending_google_student($pdo, $studentId, $adminId);
        } else {
            $result = ['ok' => false, 'message' => 'Unknown action.'];
        }
        if (!empty($result['ok'])) {
            $message = (string)$result['message'];
        } else {
            $error = (string)($result['message'] ?? 'Action failed.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$requests = $links->listOpenRequests(200);
$pendingStudents = list_pending_google_students($pdo);

function list_pending_google_students(PDO $pdo): array
{
    try {
        $sql = "
            SELECT u.id, u.username, u.google_email, u.created_at,
                   COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS full_name
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.role = 'student'
              AND u.deleted_at IS NULL
              AND (
                    u.account_status = 'pending'
                 OR (u.account_status IS NULL AND u.is_active = 0 AND u.google_id IS NOT NULL AND u.google_id <> '')
              )
            ORDER BY u.created_at DESC
            LIMIT 100
        ";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{ok:bool,message:string}
 */
function activate_pending_google_student(PDO $pdo, int $studentId, int $adminId): array
{
    if ($studentId < 1) {
        return ['ok' => false, 'message' => 'Invalid student.'];
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['ok' => false, 'message' => 'Student not found.'];
        }
        $sets = ['is_active = 1'];
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[(string)$c['Field']] = true;
        }
        if (isset($cols['account_status'])) {
            $sets[] = "account_status = 'active'";
        }
        $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute([$studentId]);
        if (function_exists('log_audit')) {
            log_audit($pdo, 'student_activate', 'users', $studentId, null, ['by' => $adminId, 'via' => 'google_pending']);
        }
        return ['ok' => true, 'message' => 'Student account activated.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not activate student.'];
    }
}

$page_title = 'Parent verification';
$current_page = 'parent_requests.php';
require_once __DIR__ . '/../includes/header.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid py-4" style="max-width:1100px">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Parent verification</h1>
            <p class="text-muted mb-0">Approve or reject parent↔student access requests. Google parents cannot see student data until approved.</p>
        </div>
        <a class="btn btn-outline-primary" href="/admin/parent_invitations.php">Invite parent</a>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= $h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Open parent access requests</h2>
            <?php if ($requests === []): ?>
                <p class="text-muted mb-0">No pending requests.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Parent</th>
                            <th>Email / phone</th>
                            <th>Requested student</th>
                            <th>Student ID</th>
                            <th>Requested</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($requests as $row): ?>
                            <tr>
                                <td><?= $h($row['parent_name'] ?: '—') ?></td>
                                <td>
                                    <?= $h($row['parent_email'] ?: '—') ?><br>
                                    <small class="text-muted"><?= $h($row['parent_phone'] ?: '') ?></small>
                                </td>
                                <td><?= $h($row['student_name'] ?? '') ?></td>
                                <td><?= (int)$row['student_id'] ?></td>
                                <td><?= $h($row['requested_at'] ?? '') ?></td>
                                <td class="text-nowrap">
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <button class="btn btn-sm btn-success" name="action" value="approve">Approve</button>
                                    </form>
                                    <form method="post" class="d-inline-flex gap-1 align-items-center mt-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <input class="form-control form-control-sm" name="rejection_reason" placeholder="Reason (optional)" style="width:140px">
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5">Pending Google student accounts</h2>
            <p class="text-muted small">New Google-registered students stay pending until activated.</p>
            <?php if ($pendingStudents === []): ?>
                <p class="text-muted mb-0">No pending Google students.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingStudents as $s): ?>
                            <tr>
                                <td><?= $h($s['full_name'] ?? '') ?></td>
                                <td><?= $h($s['google_email'] ?? '') ?></td>
                                <td><?= $h($s['username'] ?? '') ?></td>
                                <td><?= $h($s['created_at'] ?? '') ?></td>
                                <td>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                                        <button class="btn btn-sm btn-primary" name="action" value="activate_student">Activate</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
