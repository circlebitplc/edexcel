<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\SupportTicketService;

require_admin();
$svc = new SupportTicketService($pdo);
$error = '';
$success = '';
$id = (int)($_GET['id'] ?? $_POST['ticket_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? 'status');
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($action === 'reply') {
            $svc->reply($ticketId, 'admin', (int)$_SESSION['user_id'], (string)($_POST['message'] ?? ''));
            $success = 'Reply saved and linked to the communication thread.';
            $id = $ticketId;
        } else {
            $svc->updateStatus($ticketId, (string)$_POST['status'], (int)$_SESSION['user_id']);
            $success = 'Ticket updated.';
            $id = $ticketId;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

$rows = $svc->listFor('admin', (int)$_SESSION['user_id'], true);
$ticket = $id > 0 ? $svc->get($id) : null;
$messages = $ticket ? $svc->messages($id, 'admin', (int)$_SESSION['user_id'], true) : [];
$threadId = $ticket ? $svc->linkedThreadId($id) : 0;

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3"><i class="bi bi-life-preserver"></i> Support tickets</h1>
    <p class="text-muted">Replies stay connected to the ticket and open a communication thread for audit history.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?= $id === (int)$r['id'] ? 'table-active' : '' ?>">
                        <td><a href="?id=<?= (int)$r['id'] ?>"><?= e($r['ticket_no']) ?></a></td>
                        <td><?= e($r['subject']) ?><div class="small text-muted"><?= e($r['requester_type']) ?> #<?= (int)$r['requester_id'] ?></div></td>
                        <td><?= e($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
        </div>
        <div class="col-lg-7">
            <?php if (!$ticket): ?>
                <div class="alert alert-light">Select a ticket to reply and update status.</div>
            <?php else: ?>
                <div class="card border-0 shadow-sm p-3">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <h2 class="h5 mb-1"><?= e($ticket['subject']) ?></h2>
                            <div class="small text-muted"><?= e($ticket['ticket_no']) ?> · <?= e($ticket['priority']) ?> · <?= e($ticket['status']) ?></div>
                        </div>
                        <?php if ($threadId > 0): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="communication_threads.php?id=<?= $threadId ?>">Open communication thread</a>
                        <?php endif; ?>
                    </div>
                    <div class="border rounded p-2 my-3" style="max-height:360px;overflow:auto">
                        <?php foreach ($messages as $m): ?>
                            <div class="mb-2">
                                <span class="badge text-bg-secondary"><?= e($m['author_type']) ?></span>
                                <span class="small text-muted"><?= e((string)$m['created_at']) ?></span>
                                <div><?= nl2br(e($m['message'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="mb-3"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="ticket_id" value="<?= $id ?>">
                        <textarea class="form-control mb-2" name="message" rows="3" required placeholder="Reply to requester"></textarea>
                        <button class="btn btn-primary">Send reply</button>
                    </form>
                    <form method="post" class="d-flex gap-2"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="ticket_id" value="<?= $id ?>">
                        <select name="status" class="form-select form-select-sm" style="max-width:200px">
                            <?php foreach (['open','assigned','in_progress','waiting_user','resolved','closed'] as $st): ?>
                                <option value="<?= e($st) ?>" <?= $ticket['status']===$st?'selected':'' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Update status</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
