<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\CommunicationPreferenceService;
use Edexcel\Services\ParentAuthService;

$categories = ['attendance', 'fees', 'exams', 'homework', 'announcements', 'classes', 'payments', 'system'];
$error = '';
$success = '';
$audience = '';
$userId = null;
$parentId = null;

if (ParentAuthService::isLoggedIn()) {
    $audience = 'parent';
    $parentId = (int)($_SESSION['parent_id'] ?? 0);
} elseif (function_exists('is_student') && is_student()) {
    $audience = 'student';
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
} elseif (function_exists('is_teacher') && is_teacher()) {
    $audience = 'teacher';
    $userId = (int)($_SESSION['user_id'] ?? 0);
} else {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'login.php');
    exit;
}

$svc = new CommunicationPreferenceService($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    foreach ($categories as $cat) {
        $svc->save($audience, $userId, $parentId, $cat, [
            'in_app' => !empty($_POST[$cat . '_in_app']),
            'whatsapp' => !empty($_POST[$cat . '_whatsapp']),
            'sms' => !empty($_POST[$cat . '_sms']),
        ]);
    }
    $success = 'Preferences saved. Mandatory categories (payments, system, admissions) stay on for in-app.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:720px">
    <h1 class="h3">Notification preferences</h1>
    <p class="text-muted">Choose channels for optional categories. Operationally required notices cannot be fully disabled.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?>
        <table class="table align-middle">
            <thead><tr><th>Category</th><th>In-app</th><th>WhatsApp</th><th>SMS</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $cat):
                $pref = $svc->get($audience, $userId, $parentId, $cat);
                $mandatory = in_array($cat, CommunicationPreferenceService::MANDATORY, true);
                ?>
                <tr>
                    <td><?= e(ucfirst($cat)) ?><?php if ($mandatory): ?> <span class="badge text-bg-secondary">Required in-app</span><?php endif; ?></td>
                    <td><input type="checkbox" name="<?= e($cat) ?>_in_app" <?= !empty($pref['in_app']) || $mandatory ? 'checked' : '' ?> <?= $mandatory ? 'disabled' : '' ?>><?php if ($mandatory): ?><input type="hidden" name="<?= e($cat) ?>_in_app" value="1"><?php endif; ?></td>
                    <td><input type="checkbox" name="<?= e($cat) ?>_whatsapp" <?= !empty($pref['whatsapp']) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="<?= e($cat) ?>_sms" <?= !empty($pref['sms']) ? 'checked' : '' ?>></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button class="btn btn-primary">Save preferences</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
