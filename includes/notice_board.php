<?php
declare(strict_types=1);

/**
 * Shared digital notice board partial.
 * Expects: $announcements (list), $audienceLabel (string), optional $extraNotices (list of ['title','body','when']).
 */
$announcements = $announcements ?? [];
$extraNotices = $extraNotices ?? [];
$audienceLabel = $audienceLabel ?? 'College';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h1 class="h4 mb-1"><i class="bi bi-clipboard2-pulse"></i> Notice board</h1>
        <p class="text-muted small mb-0">Relevant notices for <?= e($audienceLabel) ?>. Urgent items appear first.</p>
    </div>
</div>
<?php if ($announcements === [] && $extraNotices === []): ?>
    <div class="alert alert-light border">No published notices right now.</div>
<?php endif; ?>
<?php foreach ($announcements as $a): ?>
    <?php
    $pri = (string)($a['priority'] ?? 'normal');
    $badge = $pri === 'urgent' ? 'danger' : (in_array($pri, ['high', 'important'], true) ? 'warning' : 'secondary');
    ?>
    <article class="card border-0 shadow-sm mb-3 <?= $pri === 'urgent' ? 'border-start border-4 border-danger' : '' ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
                <h2 class="h5 mb-1"><?= e((string)$a['title']) ?></h2>
                <span class="badge text-bg-<?= e($badge) ?>"><?= e(ucfirst($pri === 'high' ? 'important' : $pri)) ?></span>
            </div>
            <div class="small text-muted mb-2"><?= e((string)($a['published_at'] ?? $a['publish_at'] ?? $a['created_at'] ?? '')) ?></div>
            <div><?= nl2br(e((string)$a['content'])) ?></div>
        </div>
    </article>
<?php endforeach; ?>
<?php foreach ($extraNotices as $n): ?>
    <article class="card border-0 shadow-sm mb-2">
        <div class="card-body py-3">
            <h2 class="h6 mb-1"><?= e((string)($n['title'] ?? 'Notice')) ?></h2>
            <?php if (!empty($n['when'])): ?><div class="small text-muted"><?= e((string)$n['when']) ?></div><?php endif; ?>
            <div class="small"><?= nl2br(e((string)($n['body'] ?? ''))) ?></div>
        </div>
    </article>
<?php endforeach; ?>
