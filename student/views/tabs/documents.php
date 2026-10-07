<?php
declare(strict_types=1);
$docs = $studentDocuments ?? [];
?>
<div class="mb-4">
    <h3 class="fw-bold mb-1">My documents</h3>
    <p class="text-muted">Files the college uploaded for you (ID copies, letters, certificates).</p>
</div>
<div class="card border shadow-sm rounded-4">
    <div class="card-body p-0">
        <?php if ($docs): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($docs as $d): ?>
                    <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?= student_e($d['title']) ?></h6>
                            <div class="small text-muted"><?= !empty($d['created_at']) ? student_e(date('d M Y', strtotime((string)$d['created_at']))) : '' ?></div>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="<?= student_e(rtrim((string)BASE_URL, '/') . '/download_document.php?id=' . (int)($d['id'] ?? 0)) ?>" target="_blank">Open</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">No personal documents have been uploaded yet.</div>
        <?php endif; ?>
    </div>
</div>
