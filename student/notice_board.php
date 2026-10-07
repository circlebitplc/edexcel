<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\AnnouncementService;
use Edexcel\Services\ParentAuthService;

if (function_exists('is_student') && is_student()) {
    $audience = 'students';
    $label = 'students';
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
    $classId = null;
    try {
        $s = $pdo->prepare('SELECT class_id FROM student_enrollments WHERE student_id=? ORDER BY enrolled_at DESC LIMIT 1');
        $s->execute([$userId]);
        $classId = (int)($s->fetchColumn() ?: 0) ?: null;
    } catch (Throwable $e) {
    }
} elseif (ParentAuthService::isLoggedIn()) {
    $audience = 'parents';
    $label = 'parents';
    $classId = null;
} elseif (function_exists('is_teacher') && is_teacher()) {
    $audience = 'teachers';
    $label = 'teachers';
    $classId = null;
} elseif (function_exists('is_admin') && is_admin()) {
    $audience = 'everyone';
    $label = 'administrators';
    $classId = null;
} else {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'login.php');
    exit;
}

$announcements = (new AnnouncementService($pdo))->visible($audience === 'everyone' ? 'students' : $audience, null, $classId);
// Also show college-wide for role-specific pages
if ($audience !== 'everyone') {
    $college = (new AnnouncementService($pdo))->visible('everyone');
    $seen = array_column($announcements, 'id');
    foreach ($college as $row) {
        if (!in_array($row['id'], $seen, true)) {
            $announcements[] = $row;
        }
    }
}
usort($announcements, static function (array $a, array $b): int {
    $rank = static fn($p) => match ((string)$p) { 'urgent' => 0, 'high', 'important' => 1, default => 2 };
    return $rank($a['priority'] ?? '') <=> $rank($b['priority'] ?? '');
});
$audienceLabel = $label;
$extraNotices = [];
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
    <?php include __DIR__ . '/../includes/notice_board.php'; ?>
    <p class="small text-muted"><a href="<?= e(BASE_URL) ?>student/notifications.php">Open notification inbox</a> · <a href="<?= e(BASE_URL) ?>student/history.php">Message history</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
