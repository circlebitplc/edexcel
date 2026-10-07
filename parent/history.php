<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\ParentAuthService;
use Edexcel\Services\ParentCommunicationTimelineService;

if (!ParentAuthService::isLoggedIn()) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'parent/login.php');
    exit;
}

$parentId = (int)($_SESSION['parent_id'] ?? 0);
$auth = new ParentAuthService($pdo);
$children = $auth->children($parentId);
$childIds = array_map(static fn($c) => (int)$c['id'], $children);
$filterStudent = (int)($_GET['student'] ?? 0);
if ($filterStudent > 0 && !in_array($filterStudent, $childIds, true)) {
    $filterStudent = 0;
}

$timeline = (new ParentCommunicationTimelineService($pdo))->timeline($parentId, $childIds, 80);
if ($filterStudent > 0) {
    $timeline = array_values(array_filter($timeline, static function (array $e) use ($filterStudent): bool {
        return empty($e['student_id']) || (int)$e['student_id'] === $filterStudent;
    }));
}

$nameMap = [];
foreach ($children as $c) {
    $nameMap[(int)$c['id']] = (string)($c['student_name'] ?? $c['name'] ?? $c['full_name'] ?? ('#'.$c['id']));
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:820px">
    <h1 class="h3">Communication history</h1>
    <p class="text-muted">Timeline for your linked children only.
        <a href="communications.php">Messages</a> ·
        <a href="notice_board.php">Notice board</a> ·
        <a href="preferences.php">Preferences</a>
    </p>
    <?php if ($children !== []): ?>
        <div class="mb-3">
            <a class="btn btn-sm <?= $filterStudent===0?'btn-primary':'btn-outline-primary' ?>" href="?">All</a>
            <?php foreach ($children as $c): ?>
                <a class="btn btn-sm <?= $filterStudent===(int)$c['id']?'btn-primary':'btn-outline-primary' ?>" href="?student=<?= (int)$c['id'] ?>"><?= e($nameMap[(int)$c['id']]) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($timeline === []): ?>
        <div class="alert alert-light border">No communication history yet.</div>
    <?php endif; ?>
    <div class="list-group shadow-sm">
        <?php foreach ($timeline as $e):
            $kind = (string)$e['kind'];
            $badge = match ($kind) {
                'announcement' => 'warning',
                'thread' => 'primary',
                'delivery' => 'secondary',
                default => 'info',
            };
            ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <div>
                        <span class="badge text-bg-<?= e($badge) ?>"><?= e($kind) ?></span>
                        <?php if (!empty($e['student_id']) && isset($nameMap[(int)$e['student_id']])): ?>
                            <span class="badge text-bg-light text-dark"><?= e($nameMap[(int)$e['student_id']]) ?></span>
                        <?php endif; ?>
                        <strong class="ms-1"><?= e($e['title']) ?></strong>
                    </div>
                    <div class="small text-muted"><?= e((string)$e['when']) ?></div>
                </div>
                <div class="small mt-1"><?= nl2br(e((string)$e['body'])) ?></div>
                <?php if (!empty($e['meta']['status']) || !empty($e['meta']['channel'])): ?>
                    <div class="small text-muted mt-1">
                        <?= e((string)($e['meta']['channel'] ?? '')) ?>
                        <?= !empty($e['meta']['status']) ? ' · '.e((string)$e['meta']['status']) : '' ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($e['link'])): ?>
                    <a class="small" href="<?= e((string)$e['link']) ?>">Open</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
