<?php
declare(strict_types=1);
/**
 * student/views/tabs/classes.php
 * Tab 3: Enrolled Classes Grid & Teacher Details
 */
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-collection me-2 text-primary"></i>My Enrolled Classes</h3>
        <p class="text-muted mb-0">You are currently registered in <strong><?= count($enrolledClasses) ?></strong> class<?= count($enrolledClasses) === 1 ? '' : 'es' ?>.</p>
    </div>
    <a href="?tab=join" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i> Find a class</a>
</div>

<?php if ($enrolledClasses): ?>
<div data-live-scope>
    <div class="input-group mb-3 shadow-sm rounded-4 overflow-hidden">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="search" class="form-control border-start-0" placeholder="Filter enrolled classes by name, subject, or teacher..." data-live-search autocomplete="off">
    </div>
    <div class="row g-4">
        <?php foreach ($enrolledClasses as $c):
            $searchBlob = strtolower(trim(
                ($c['class_name'] ?? $c['name'] ?? '') . ' ' .
                ($c['teacher_name'] ?? '') . ' ' .
                ($c['subject_name'] ?? '')
            ));
        ?>
            <div class="col-md-6 col-lg-4" data-live-item data-search="<?= student_e($searchBlob) ?>">
                <div class="card h-100 border shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle d-grid place-items-center text-white fw-bold overflow-hidden" style="width:48px;height:48px;background:<?= student_e(teacherAvatarColor($c['teacher_name'] ?? 'Teacher')) ?>;flex-shrink:0;">
                                <?php if (!empty($c['teacher_photo_path'])): ?>
                                    <img src="<?= student_e(teacherPhotoSrc($c['teacher_photo_path'] ?? null)) ?>" alt="<?= student_e($c['teacher_name'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <span><?= student_e(teacherInitials($c['teacher_name'] ?? 'TE')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark"><?= student_e($c['class_name'] ?? $c['name'] ?? 'Class') ?></h5>
                                <div class="small text-muted"><i class="bi bi-person"></i>
                                    <?php if (!empty($c['teacher_id'])): ?>
                                        <a href="?tab=teachers&amp;id=<?= (int)$c['teacher_id'] ?>"><?= student_e($c['teacher_name'] ?? 'Assigned Teacher') ?></a>
                                    <?php else: ?>
                                        <?= student_e($c['teacher_name'] ?? 'Assigned Teacher') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($c['subject_name'])): ?>
                            <span class="badge bg-primary-subtle text-primary mb-3"><?= student_e($c['subject_name']) ?></span>
                        <?php endif; ?>

                        <div class="pt-3 border-top d-flex flex-wrap gap-2">
                            <?php if (!empty($c['whatsapp_link'])): ?>
                                <a href="<?= student_e($c['whatsapp_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm flex-grow-1">
                                    <i class="bi bi-whatsapp me-1"></i> WhatsApp Group
                                </a>
                            <?php endif; ?>
                            <form method="POST" action="leave_class.php" class="flex-grow-1 js-leave-class">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="class_id" value="<?= (int)($c['id'] ?? $c['class_id'] ?? 0) ?>">
                                <input type="hidden" name="return_tab" value="classes">
                                <input type="hidden" name="reason" value="">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="bi bi-box-arrow-left me-1"></i> Unenroll
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center py-5 bg-light rounded-4 border d-none" data-live-empty>
        <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
        <h4 class="fw-bold">No matching classes</h4>
        <p class="text-muted mb-0">Try another search term.</p>
    </div>
</div>
<?php else: ?>
    <div class="text-center py-5 bg-light rounded-4 border">
        <i class="bi bi-collection fs-1 text-muted d-block mb-3"></i>
        <h4 class="fw-bold">No Enrolled Classes</h4>
        <p class="text-muted mb-4">You haven't joined any classes yet. Browse available classes to get started.</p>
        <a href="?tab=join" class="btn btn-primary"><i class="bi bi-search me-1"></i> Browse & Join Classes</a>
    </div>
<?php endif; ?>
