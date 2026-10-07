<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\CommunicationThreadService;
use Edexcel\Services\ParentAuthService;

if (!ParentAuthService::isLoggedIn()) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'parent/login.php');
    exit;
}

$parentId = (int)($_SESSION['parent_id'] ?? 0);
$userId = $parentId;
$svc = new CommunicationThreadService($pdo);
$error = '';
$success = '';
$id = (int)($_GET['id'] ?? $_POST['thread_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        if (($_POST['action'] ?? '') === 'reply') {
            $svc->reply($id, 'parent', $userId, (string)($_POST['body'] ?? ''));
            $success = 'Reply sent.';
        } elseif (($_POST['action'] ?? '') === 'open') {
            $childId = (int)($_POST['related_student_id'] ?? 0);
            $auth = new ParentAuthService($pdo);
            $kids = array_map(static fn($c) => (int)$c['id'], $auth->children($parentId));
            if ($childId < 1 || !in_array($childId, $kids, true)) {
                throw new RuntimeException('Select one of your linked children.');
            }
            $id = $svc->open([
                'subject' => $_POST['subject'] ?? '',
                'body' => $_POST['body'] ?? '',
                'related_student_id' => $childId,
            ], 'parent', $userId);
            $success = 'Message sent to college staff.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

$inbox = $svc->inbox('parent', $userId);
$thread = $id ? $svc->get($id) : null;
$messages = ($thread && $svc->canAccess($id, 'parent', $userId)) ? $svc->messages($id) : [];
if ($thread && $messages) {
    $svc->markRead($id, 'parent', $userId);
}
$children = (new ParentAuthService($pdo))->children($parentId);

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:960px">
    <h1 class="h3">Parent communications</h1>
    <p class="text-muted">Messages about your linked children only.
        <a href="history.php">History</a> ·
        <a href="notice_board.php">Notice board</a> ·
        <a href="preferences.php">Preferences</a>
    </p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-4">
            <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?>
                <input type="hidden" name="action" value="open">
                <h2 class="h6">Contact college</h2>
                <select class="form-select mb-2" name="related_student_id" required>
                    <option value="">Child…</option>
                    <?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e((string)($c['name'] ?? $c['full_name'] ?? ('#'.$c['id']))) ?></option><?php endforeach; ?>
                </select>
                <input class="form-control mb-2" name="subject" required placeholder="Subject">
                <textarea class="form-control mb-2" name="body" rows="3" required></textarea>
                <button class="btn btn-primary btn-sm">Send</button>
            </form>
            <div class="list-group"><?php foreach ($inbox as $t): ?>
                <a class="list-group-item list-group-item-action <?= $id===(int)$t['id']?'active':'' ?>" href="?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
            <?php endforeach; ?></div>
        </div>
        <div class="col-md-8">
            <?php if ($thread && $messages): ?>
                <div class="card border-0 shadow-sm p-3">
                    <h2 class="h5"><?= e($thread['subject']) ?></h2>
                    <?php foreach ($messages as $m): ?>
                        <div class="mb-2"><span class="badge text-bg-secondary"><?= e($m['sender_role']) ?></span>
                            <span class="small text-muted"><?= e((string)$m['created_at']) ?></span>
                            <div><?= nl2br(e($m['body'])) ?></div></div>
                    <?php endforeach; ?>
                    <form method="post" class="mt-3"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply"><input type="hidden" name="thread_id" value="<?= $id ?>">
                        <textarea class="form-control mb-2" name="body" rows="3" required></textarea>
                        <button class="btn btn-primary">Reply</button>
                    </form>
                </div>
            <?php else: ?><div class="alert alert-light">Select a conversation.</div><?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
