<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\StaffCommunicationWorkbenchService;

if (!function_exists('is_student') || !is_student()) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'student/login.php');
    exit;
}

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId < 1) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'student/login.php');
    exit;
}

$kind = strtolower(trim((string)($_GET['kind'] ?? '')));
$wb = new StaffCommunicationWorkbenchService($pdo);
$timeline = $wb->studentTimeline($studentId, 80);
$stats = $wb->studentStats($studentId);

if ($kind !== '' && in_array($kind, ['notification', 'delivery', 'thread', 'parent_delivery'], true)) {
    $timeline = array_values(array_filter($timeline, static fn(array $e): bool => (string)($e['kind'] ?? '') === $kind));
}
// Students must not see parent_delivery rows about parents — strip if any leaked.
$timeline = array_values(array_filter($timeline, static fn(array $e): bool => (string)($e['kind'] ?? '') !== 'parent_delivery'));

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
    <h1 class="h3">My messages</h1>
    <p class="text-muted">Your college communication history (in-app notices, deliveries, and threads).
        <a href="notifications.php">Inbox</a> ·
        <a href="notice_board.php">Notice board</a> ·
        <a href="preferences.php">Preferences</a>
    </p>
    <div class="row g-2 mb-3">
        <?php foreach ([['Deliveries', $stats['deliveries']], ['Failed', $stats['failed']], ['Threads', $stats['threads']], ['Unread', $stats['unread_in_app']]] as $c): ?>
            <div class="col-6 col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-5 fw-bold"><?= (int)$c[1] ?></div></div></div>
        <?php endforeach; ?>
    </div>
    <div class="mb-3">
        <a class="btn btn-sm <?= $kind===''?'btn-primary':'btn-outline-primary' ?>" href="?">All</a>
        <a class="btn btn-sm <?= $kind==='notification'?'btn-primary':'btn-outline-primary' ?>" href="?kind=notification">Notices</a>
        <a class="btn btn-sm <?= $kind==='delivery'?'btn-primary':'btn-outline-primary' ?>" href="?kind=delivery">Deliveries</a>
        <a class="btn btn-sm <?= $kind==='thread'?'btn-primary':'btn-outline-primary' ?>" href="?kind=thread">Threads</a>
    </div>
    <?php if ($timeline === []): ?>
        <div class="alert alert-light border">No messages in this view yet.</div>
    <?php endif; ?>
    <div class="list-group shadow-sm">
        <?php foreach ($timeline as $e):
            $k = (string)$e['kind'];
            $badge = match ($k) {
                'delivery' => 'secondary',
                'thread' => 'primary',
                default => 'info',
            };
            $link = (string)($e['link'] ?? '');
            // Never expose admin ops links to students.
            if (str_contains($link, 'admin/')) {
                $link = '';
            }
            if ($k === 'thread' && $link === '') {
                $link = '';
            }
            ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <div>
                        <span class="badge text-bg-<?= e($badge) ?>"><?= e($k) ?></span>
                        <?php if (!empty($e['channel'])): ?><span class="badge text-bg-light text-dark"><?= e((string)$e['channel']) ?></span><?php endif; ?>
                        <?php if (!empty($e['status'])): ?><span class="badge text-bg-<?= ($e['status']==='failed')?'danger':(($e['status']==='delivered'||$e['status']==='read')?'success':'secondary') ?>"><?= e((string)$e['status']) ?></span><?php endif; ?>
                        <strong class="ms-1"><?= e((string)$e['title']) ?></strong>
                    </div>
                    <div class="small text-muted"><?= e((string)$e['when']) ?></div>
                </div>
                <div class="small mt-1"><?= nl2br(e((string)$e['body'])) ?></div>
                <?php if ($link !== '' && !str_contains($link, 'admin/')): ?>
                    <a class="small" href="<?= e($link) ?>">Open</a>
                <?php elseif ($k === 'notification'): ?>
                    <a class="small" href="notifications.php">Open inbox</a>
                <?php elseif ($k === 'thread'): ?>
                    <span class="small text-muted">Reply via college staff threads when invited</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
