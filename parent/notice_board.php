<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\AnnouncementService;
use Edexcel\Services\ParentAuthService;

if (!ParentAuthService::isLoggedIn()) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'parent/login.php');
    exit;
}

$announcements = (new AnnouncementService($pdo))->visible('parents');
$college = (new AnnouncementService($pdo))->visible('everyone');
$seen = array_column($announcements, 'id');
foreach ($college as $row) {
    if (!in_array($row['id'], $seen, true)) {
        $announcements[] = $row;
    }
}
$audienceLabel = 'parents';
$extraNotices = [];
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
    <?php include __DIR__ . '/../includes/notice_board.php'; ?>
    <p class="small text-muted">
        <a href="<?= e(BASE_URL) ?>student/notifications.php">Notifications</a> ·
        <a href="<?= e(BASE_URL) ?>parent/communications.php">Messages</a> ·
        <a href="<?= e(BASE_URL) ?>parent/history.php">History</a> ·
        <a href="<?= e(BASE_URL) ?>parent/preferences.php">Notification preferences</a>
    </p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
