<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\LeadService;
try { $auth = AdmissionAuth::require($pdo, 'admissions.view'); } catch (Throwable $e) { http_response_code(403); echo 'Access denied.'; exit; }
$svc = new LeadService($pdo);
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? 'create');
        if ($action === 'create') {
            if (!AdmissionAuth::can($pdo, $auth['user_id'], 'admissions.create')) {
                throw new RuntimeException('You do not have admissions.create.');
            }
            $id = $svc->create($_POST, $auth['user_id']);
            $success = 'Lead #'.$id.' saved. Possible matches are logged for review and never auto-merged.';
        } elseif ($action === 'status') {
            $svc->transition((int)$_POST['lead_id'], (string)$_POST['status'], $auth['user_id'], (string)($_POST['notes'] ?? ''));
            $success = 'Lead status updated.';
        } elseif ($action === 'followup') {
            $svc->scheduleFollowup($_POST, $auth['user_id']);
            $success = 'Follow-up scheduled.';
        } elseif ($action === 'done') {
            $svc->completeFollowup((int)$_POST['followup_id'], $auth['user_id'], (string)($_POST['notes'] ?? ''));
            $success = 'Follow-up completed.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$filters = ['status' => $_GET['status'] ?? '', 'source' => $_GET['source'] ?? '', 'assigned_to' => (int)($_GET['assigned_to'] ?? 0), 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? ''];
$rows = $svc->pipeline($filters);
$due = $svc->dueToday();
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3">Lead / enquiry pipeline</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="d-flex flex-wrap gap-1 mb-3"><?php foreach (LeadService::STATUSES as $st): ?><a class="badge text-bg-secondary text-decoration-none" href="?status=<?= e($st) ?>"><?= e($st) ?></a><?php endforeach; ?></div>
    <div class="row g-3">
        <div class="col-lg-4">
            <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="create">
                <h2 class="h6">New lead</h2>
                <input class="form-control mb-2" name="full_name" placeholder="Name" required>
                <input class="form-control mb-2" name="phone" placeholder="Phone">
                <input class="form-control mb-2" name="qualification_label" placeholder="Qualification">
                <select class="form-select mb-2" name="source"><?php foreach (LeadService::SOURCES as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
                <textarea class="form-control mb-2" name="notes" placeholder="Notes"></textarea>
                <button class="btn btn-primary">Save lead</button>
            </form>
            <div class="card border-0 shadow-sm p-3"><h2 class="h6">Follow-ups due</h2><?php foreach ($due as $f): ?>
                <form method="post" class="border-bottom py-2"><?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="followup_id" value="<?= (int)$f['id'] ?>">
                    <div class="small"><?= e((string)($f['full_name'] ?? '')) ?> · <?= e($f['reason']) ?></div>
                    <button class="btn btn-sm btn-outline-success">Done</button>
                </form>
            <?php endforeach; ?></div>
        </div>
        <div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table">
            <thead><tr><th>Lead</th><th>Source</th><th>Status</th><th></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?><tr>
                <td><?= e($r['full_name']) ?><div class="small text-muted"><?= e((string)$r['phone']) ?></div></td>
                <td><?= e($r['source']) ?></td>
                <td>
                    <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="lead_id" value="<?= (int)$r['id'] ?>">
                        <select class="form-select form-select-sm" name="status"><?php foreach (LeadService::STATUSES as $st): ?><option <?= $r['status']===$st?'selected':'' ?>><?= e($st) ?></option><?php endforeach; ?></select>
                        <button class="btn btn-sm btn-outline-primary">Set</button>
                    </form>
                </td>
                <td>
                    <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="followup"><input type="hidden" name="lead_id" value="<?= (int)$r['id'] ?>">
                        <input class="form-control form-control-sm" type="datetime-local" name="due_at" required>
                        <input class="form-control form-control-sm" name="reason" placeholder="Reason">
                        <button class="btn btn-sm btn-outline-secondary">Follow-up</button>
                    </form>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
