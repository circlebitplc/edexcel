<?php
declare(strict_types=1);
/**
 * views/home/teacher_cards.php
 * Public Homepage Faculty Directory Cards
 */
?>
<section id="faculty-section" class="mb-5 pt-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-people me-2 text-primary"></i>Faculty Directory</h2>
            <p class="text-muted mb-0">Learn from experienced educators dedicated to academic excellence.</p>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($teachers as $t): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white hover-shadow transition-all">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle d-grid place-items-center text-white fw-bold overflow-hidden" style="width:64px;height:64px;background:<?= e($t['avatar_color']) ?>;flex-shrink:0;">
                                <?php if (!empty($t['photo_path'])): ?>
                                    <img src="<?= e(BASE_URL . $t['photo_path']) ?>" alt="<?= e((string)($t['photo_alt'] ?? $t['name'])) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <span class="fs-4"><?= e($t['initials']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark"><?= e($t['name']) ?></h5>
                                <?php if (!empty($t['profile_qualifications'])): ?>
                                    <div class="small text-muted mb-0"><?= e($t['profile_qualifications']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Subjects -->
                        <?php if (!empty($t['subject_list'])): ?>
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <?php foreach ($t['subject_list'] as $s): ?>
                                    <span class="badge bg-primary-subtle text-primary small px-2 py-1"><?= e($s) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Bio summary -->
                        <?php if (!empty($t['profile_bio'])): ?>
                            <p class="text-muted small mb-4 flex-grow-1">
                                <?= e(mb_strimwidth($t['profile_bio'], 0, 120, '...')) ?>
                            </p>
                        <?php else: ?>
                            <div class="flex-grow-1"></div>
                        <?php endif; ?>

                        <!-- Profile Link -->
                        <div class="pt-3 border-top mt-auto">
                            <a href="<?= BASE_URL ?>teachers/teacher_profile.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline-primary btn-sm w-100 py-2 rounded-pill fw-semibold">
                                View Profile & Schedule <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
