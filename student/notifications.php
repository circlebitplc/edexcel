<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\NotificationCenterService;
use Edexcel\Services\ParentAuthService;

$nc = new NotificationCenterService($pdo);
$error = '';
$success = '';

$audience = '';
$userId = null;
$parentId = null;

if (function_exists('is_admin') && is_admin()) {
    $audience = 'admin';
    $userId = (int)($_SESSION['user_id'] ?? 0);
} elseif (function_exists('is_teacher') && is_teacher()) {
    $audience = 'teacher';
    $userId = (int)($_SESSION['user_id'] ?? 0);
} elseif (function_exists('is_student') && is_student()) {
    $audience = 'student';
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
    $nc->syncFromStudentNotifications($userId);
} elseif (ParentAuthService::isLoggedIn()) {
    $audience = 'parent';
    $parentId = (int)($_SESSION['parent_id'] ?? 0);
} else {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'mark_read') {
            $nc->markRead((int)($_POST['id'] ?? 0), $audience, $userId, $parentId);
            $success = 'Marked as read.';
        } elseif ($action === 'mark_all') {
            $n = $nc->markAllRead($audience, $userId, $parentId);
            $success = $n > 0 ? "Marked {$n} as read." : 'Nothing unread.';
        }
    }
}

$category = trim((string)($_GET['category'] ?? ''));
$unreadOnly = isset($_GET['unread']);
$items = $nc->listFor($audience, $userId, $parentId, [
    'category' => $category,
    'unread_only' => $unreadOnly,
    'limit' => 80,
]);
$unread = $nc->unreadCount($audience, $userId, $parentId);

$isParent = $audience === 'parent';
if ($isParent) {
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications · Parent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/system.css">
    <link rel="stylesheet" href="/assets/css/a11y-mobile-v2.css">
</head>
<body>
<main class="py-4 px-3" style="max-width:720px;margin:0 auto">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h4">Notifications</h1>
        <a class="btn btn-sm btn-outline-secondary" href="/parent/dashboard.php">Dashboard</a>
    </div>
<?php
} else {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-4" style="max-width:820px">';
}
?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <?php if (!$isParent): ?>
            <div>
                <h1 class="h3 mb-0"><i class="bi bi-bell"></i> Notification centre</h1>
                <p class="text-muted small mb-0">Role: <?= e($audience) ?> · Unread: <?= (int)$unread ?></p>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0">Unread: <?= (int)$unread ?></p>
        <?php endif; ?>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Mark all read</button>
        </form>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="mb-3 d-flex flex-wrap gap-1">
        <a class="btn btn-sm <?= $category === '' && !$unreadOnly ? 'btn-primary' : 'btn-outline-primary' ?>"
           href="?">All</a>
        <a class="btn btn-sm <?= $unreadOnly ? 'btn-primary' : 'btn-outline-primary' ?>" href="?unread=1">Unread</a>
        <?php foreach (NotificationCenterService::CATEGORIES as $cat): ?>
            <a class="btn btn-sm <?= $category === $cat ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="?category=<?= e(urlencode($cat)) ?>"><?= e($cat) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($items === []): ?>
        <p class="text-muted">No notifications.</p>
    <?php endif; ?>

    <?php foreach ($items as $n): ?>
        <div class="border rounded-4 p-3 mb-2 <?= empty($n['is_read']) ? 'border-primary' : 'opacity-75' ?>">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
                <div>
                    <div class="fw-semibold"><?= e((string)$n['title']) ?></div>
                    <?php if (!empty($n['body'])): ?><div class="small"><?= e((string)$n['body']) ?></div><?php endif; ?>
                    <div class="small text-muted">
                        <?= e((string)($n['category'] ?? '')) ?>
                        · <?= e((string)($n['priority'] ?? 'normal')) ?>
                        · <?= e((string)($n['created_at'] ?? '')) ?>
                    </div>
                    <?php if (!empty($n['link_url'])): ?>
                        <a class="small" href="<?= e((string)$n['link_url']) ?>">Open link</a>
                    <?php endif; ?>
                </div>
                <?php if (empty($n['is_read'])): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Mark read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php
if ($isParent) {
    echo '</main></body></html>';
} else {
    echo '</div>';
    include __DIR__ . '/../includes/footer.php';
}
