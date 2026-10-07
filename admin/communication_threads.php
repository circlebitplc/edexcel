<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\CommunicationAiAssistant;
use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationThreadService;
try { $auth = CommunicationAuth::require($pdo, 'communication.view'); } catch (Throwable $e) {
    if (!function_exists('is_admin') || !is_admin()) { http_response_code(403); echo 'Access denied.'; exit; }
    $auth = ['user_id'=>(int)$_SESSION['user_id'],'role'=>'admin','teacher_id'=>0];
}
$svc = new CommunicationThreadService($pdo);
$id = (int)($_GET['id'] ?? $_POST['thread_id'] ?? 0);
$error = ''; $success = ''; $summary = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? 'reply');
        if ($action === 'open') {
            $id = $svc->open($_POST, $auth['role'], $auth['user_id']);
            $success = 'Thread opened.';
        } elseif ($action === 'reply') {
            $svc->reply($id, $auth['role'], $auth['user_id'], (string)($_POST['body'] ?? ''));
            $success = 'Reply saved.';
        } elseif ($action === 'summarize') {
            $summary = (new CommunicationAiAssistant($pdo))->summarizeThread($svc->messages($id));
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$thread = $id ? $svc->get($id) : null;
$messages = ($thread && $svc->canAccess($id, $auth['role'], $auth['user_id'])) ? $svc->messages($id) : [];
if ($thread && $messages) { $svc->markRead($id, $auth['role'], $auth['user_id']); }
$inbox = $svc->inbox($auth['role'] === 'admin' ? 'admin' : $auth['role'], $auth['user_id']);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <a href="communications.php">← Communication centre</a>
    <h1 class="h3">Communication threads</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="open">
                <h2 class="h6">New thread</h2>
                <input class="form-control mb-2" name="subject" required placeholder="Subject">
                <input class="form-control mb-2" name="related_student_id" type="number" placeholder="Related student ID (optional)">
                <input class="form-control mb-2" name="related_class_id" type="number" placeholder="Related class ID (optional)">
                <textarea class="form-control mb-2" name="body" rows="3" placeholder="First message"></textarea>
                <button class="btn btn-primary btn-sm">Open</button>
            </form>
            <div class="list-group"><?php foreach ($inbox as $t): ?>
                <a class="list-group-item list-group-item-action <?= $id===(int)$t['id']?'active':'' ?>" href="?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?><div class="small"><?= e((string)($t['last_message_at'] ?? $t['created_at'])) ?></div></a>
            <?php endforeach; ?></div>
        </div>
        <div class="col-lg-8">
            <?php if (!$thread): ?><div class="alert alert-light">Select or open a thread.</div><?php elseif (!$svc->canAccess($id, $auth['role'], $auth['user_id'])): ?>
                <div class="alert alert-warning">You do not have access to this thread.</div>
            <?php else: ?>
                <div class="card border-0 shadow-sm p-3">
                    <h2 class="h5"><?= e($thread['subject']) ?></h2>
                    <div class="border rounded p-2 mb-3" style="max-height:420px;overflow:auto">
                        <?php foreach ($messages as $m): ?>
                            <div class="mb-2"><span class="badge text-bg-secondary"><?= e($m['sender_role']) ?></span>
                                <span class="small text-muted"><?= e((string)$m['created_at']) ?></span>
                                <div><?= nl2br(e($m['body'])) ?></div></div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="thread_id" value="<?= $id ?>">
                        <textarea class="form-control mb-2" name="body" rows="3" required></textarea>
                        <button class="btn btn-primary">Reply</button>
                    </form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="summarize"><input type="hidden" name="thread_id" value="<?= $id ?>">
                        <button class="btn btn-outline-secondary btn-sm">AI summarize (advisory)</button>
                    </form>
                    <?php if ($summary): ?><div class="mt-2 small p-2 bg-light rounded"><?= nl2br(e($summary['summary'])) ?><div class="text-muted"><?= e($summary['disclaimer']) ?></div></div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
