<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AutomationService;
use Edexcel\Services\CommunicationRulePackService;
require_admin();
$svc = new AutomationService($pdo);
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired.';
    } else {
        try {
            if (($_POST['action'] ?? '') === 'seed_communication') {
                $n = (new CommunicationRulePackService($pdo))->seedDefaults((int)$_SESSION['user_id']);
                $success = $n > 0 ? "Seeded {$n} communication automation rules." : 'Communication rule pack already present.';
            } else {
                $svc->saveRule($_POST, (int)$_SESSION['user_id']);
                $success = 'Automation rule saved.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$rules = $svc->rules();
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3"><i class="bi bi-diagram-3"></i> Automation rules</h1>
    <p class="text-muted">Rules create auditable in-app / queued actions. External channels follow notification preferences and the communication queue.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="seed_communication">
        <button class="btn btn-outline-secondary btn-sm">Seed communication rule pack</button>
    </form>
    <div class="row g-4">
        <div class="col-lg-5">
            <form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?>
                <input class="form-control mb-2" name="name" placeholder="Rule name" required>
                <input class="form-control mb-2" name="event_name" placeholder="student_success_changed" required>
                <textarea class="form-control mb-2" name="conditions_json" rows="3" placeholder='[{"field":"success_level","operator":"equals","value":"AT_RISK"}]' required></textarea>
                <textarea class="form-control mb-2" name="actions_json" rows="4" placeholder='[{"type":"communication","channel":"in_app","audience_type":"individual","category":"system","title":"Notice","body":"Follow-up required"}]' required></textarea>
                <input class="form-control mb-2" type="number" name="cooldown_seconds" value="86400">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="enabled" checked> Enabled</label>
                <button class="btn btn-primary mt-3">Save rule</button>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table">
                <thead><tr><th>Name</th><th>Event</th><th>Cooldown</th><th>Enabled</th></tr></thead>
                <tbody><?php foreach ($rules as $r): ?>
                    <tr><td><?= e($r['name']) ?></td><td><?= e($r['event_name']) ?></td><td><?= e((string)$r['cooldown_seconds']) ?>s</td><td><?= ((int)$r['enabled']) ? 'Yes' : 'No' ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
