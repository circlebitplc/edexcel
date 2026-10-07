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
$createdToken = '';
$inviteUrl = '';
$adminId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Session expired. Try again.');
        }
        $result = $links->createInvitation(
            (string)($_POST['parent_email'] ?? ''),
            (int)($_POST['student_id'] ?? 0),
            $adminId,
            (int)($_POST['ttl_days'] ?? 14)
        );
        if (!empty($result['ok'])) {
            $message = (string)$result['message'];
            $createdToken = (string)($result['token'] ?? '');
            $base = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/');
            $inviteUrl = $base . '/portal/login.php?invite=' . rawurlencode($createdToken);
        } else {
            $error = (string)($result['message'] ?? 'Could not create invitation.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$students = [];
try {
    $students = $pdo->query("
        SELECT u.id, COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS name, u.username
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.role = 'student' AND u.deleted_at IS NULL AND u.is_active = 1
        ORDER BY name
        LIMIT 2000
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $students = [];
}

$page_title = 'Invite parent';
$current_page = 'parent_invitations.php';
require_once __DIR__ . '/../includes/header.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid py-4" style="max-width:720px">
    <h1 class="h3 mb-2">Invite parent</h1>
    <p class="text-muted">Create a single-use, expiring invitation for a specific parent email and student. The parent must sign in with Google using that email.</p>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= $h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

    <?php if ($createdToken !== ''): ?>
        <div class="alert alert-info">
            <div class="fw-bold mb-1">Invitation link (copy now — token is shown once)</div>
            <code class="user-select-all"><?= $h($inviteUrl) ?></code>
            <div class="small mt-2 text-muted">Expires: <?= $h($_POST['ttl_days'] ?? '14') ?> day(s) from creation.</div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label">Parent email</label>
                <input class="form-control mb-3" type="email" name="parent_email" required value="<?= $h($_POST['parent_email'] ?? '') ?>">

                <label class="form-label">Student</label>
                <select class="form-select mb-3" name="student_id" required>
                    <option value="">Select student…</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ((int)($_POST['student_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                            <?= $h($s['name']) ?> — ID <?= (int)$s['id'] ?> (<?= $h($s['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label">Expires in (days)</label>
                <input class="form-control mb-3" type="number" name="ttl_days" min="1" max="60" value="<?= $h((string)($_POST['ttl_days'] ?? '14')) ?>">

                <button class="btn btn-primary" type="submit">Create invitation</button>
                <a class="btn btn-link" href="/admin/parent_requests.php">Back to requests</a>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
