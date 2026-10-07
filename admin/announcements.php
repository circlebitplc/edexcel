<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AnnouncementService;
use Edexcel\Services\CommunicationAuth;
try { $auth = CommunicationAuth::require($pdo, 'communication.send'); } catch (Throwable $e) {
    require_admin();
    $auth = ['user_id'=>(int)$_SESSION['user_id'],'role'=>'admin'];
}
$svc = new AnnouncementService($pdo);
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? 'create');
        if ($action === 'create') { $svc->create($_POST, $auth['user_id']); $success = 'Announcement saved.'; }
        elseif ($action === 'publish') { $svc->publish((int)$_POST['id'], $auth['user_id']); $success = 'Published and fan-out queued to notification centre.'; }
        elseif ($action === 'archive') { $svc->archive((int)$_POST['id'], $auth['user_id']); $success = 'Announcement archived/expired.'; }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$rows = $svc->recent();
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <a href="communications.php">← Communication centre</a>
    <h1 class="h3">Announcements</h1>
    <p class="text-muted">Urgent is limited to 3/day. Publishing fans out to the notification centre for the target audience.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?><input type="hidden" name="action" value="create">
                <input class="form-control mb-2" name="title" placeholder="Title" required>
                <textarea class="form-control mb-2" name="content" rows="5" placeholder="Content" required></textarea>
                <div class="row g-2">
                    <div class="col"><select class="form-select" name="priority"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></select></div>
                    <div class="col"><select class="form-select" name="target_type"><option value="everyone">Everyone</option><option value="students">Students</option><option value="parents">Parents</option><option value="teachers">Teachers</option><option value="class">Class</option><option value="subject">Subject</option></select></div>
                </div>
                <input class="form-control my-2" name="target_id" type="number" placeholder="Target ID if class/subject">
                <div class="row g-2"><div class="col"><label class="form-label small">Publish at</label><input class="form-control" type="datetime-local" name="publish_at"></div>
                    <div class="col"><label class="form-label small">Expires</label><input class="form-control" type="datetime-local" name="expires_at"></div></div>
                <button class="btn btn-primary mt-3">Save draft / schedule</button>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table">
                <thead><tr><th>Title</th><th>Priority</th><th>Target</th><th>Status</th><th></th></tr></thead>
                <tbody><?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['title']) ?><?php if (($r['priority']??'')==='urgent'): ?> <span class="badge text-bg-danger">Urgent</span><?php elseif (in_array($r['priority']??'',['high','important'],true)): ?> <span class="badge text-bg-warning">Important</span><?php endif; ?></td>
                        <td><?= e((string)$r['priority']) ?></td>
                        <td><?= e($r['target_type']) ?></td>
                        <td><?= e($r['status']) ?></td>
                        <td class="text-nowrap">
                            <?php if (in_array($r['status'],['draft','scheduled'],true)): ?>
                                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-success">Publish</button></form>
                            <?php endif; ?>
                            <?php if ($r['status']==='published'): ?>
                                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Archive</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
