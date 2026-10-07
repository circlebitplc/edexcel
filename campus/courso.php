<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CoursoCommunityService;

require_staff();
ensure_courso_schema($pdo);

$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$posts = (new CoursoCommunityService($pdo))->staffFeed($teacherId ?: null, is_admin());

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-2"><i class="bi bi-stars"></i> Talk with AI — class discussions</h1>
    <p class="text-muted">Students ask each other questions inside Talk with AI. You can see recent threads for your classes<?= is_admin() ? ' (all classes for admin)' : '' ?>.</p>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="list-group list-group-flush">
            <?php if ($posts === []): ?>
                <div class="list-group-item text-muted">No student questions yet.</div>
            <?php endif; ?>
            <?php foreach ($posts as $p): ?>
                <div class="list-group-item py-3">
                    <div class="small text-muted mb-1">
                        <?= e((string)($p['class_name'] ?? '')) ?>
                        · <?= e((string)($p['author'] ?? 'Student')) ?>
                        · <?= e((string)($p['created_at'] ?? '')) ?>
                    </div>
                    <div><?= e((string)($p['body'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
