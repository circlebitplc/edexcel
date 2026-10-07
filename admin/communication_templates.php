<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\MessageTemplateService;
try { $auth = CommunicationAuth::require($pdo, 'communication.templates'); } catch (Throwable $e) {
    if (!function_exists('is_admin') || !is_admin()) { http_response_code(403); echo 'Access denied.'; exit; }
    $auth = ['user_id' => (int)$_SESSION['user_id'], 'role' => 'admin'];
}
$svc = new MessageTemplateService($pdo);
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        if (($_POST['action'] ?? '') === 'seed') { $success = $svc->seedDefaults($auth['user_id']).' templates seeded.'; }
        else { $id = $svc->save($_POST, $auth['user_id']); $success = 'Template #'.$id.' saved.'; }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$rows = $svc->list(false);
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:900px">
    <a href="communications.php">← Communication centre</a>
    <h1 class="h3">Message templates</h1>
    <p class="text-muted">Variables: <?= e(implode(', ', array_map(static fn($v) => '{{'.$v.'}}', MessageTemplateService::VARIABLES))) ?></p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?>
        <input class="form-control mb-2" name="name" placeholder="Name" required>
        <select class="form-select mb-2" name="channel"><?php foreach (['in_app','whatsapp','sms'] as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
        <input class="form-control mb-2" name="subject" placeholder="Subject">
        <textarea class="form-control mb-2" name="body" rows="4" required placeholder="Body with {{variables}}"></textarea>
        <button class="btn btn-primary">Save template</button>
    </form>
    <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="seed"><button class="btn btn-outline-secondary btn-sm">Seed defaults</button></form>
    <ul><?php foreach ($rows as $r): ?><li><strong><?= e($r['name']) ?></strong> · <?= e($r['channel']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['body'],0,120,'…')) ?></div></li><?php endforeach; ?></ul>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
