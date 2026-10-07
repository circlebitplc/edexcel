<?php
declare(strict_types=1);
/**
 * student/views/tabs/join.php
 * Tab 4: Searchable Class Catalog & Enrollment
 */
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-search me-2 text-primary"></i>Find a class</h3>
        <p class="text-muted mb-0">Choose the subject <strong>and</strong> the teacher. If more than one teacher takes the same subject, each teacher is shown on a separate card.</p>
    </div>
</div>

<div data-live-scope>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="input-group shadow-sm rounded-4 overflow-hidden flex-grow-1" style="min-width:220px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control border-start-0 ps-0" placeholder="Type to filter by class, subject, or teacher..." value="<?= student_e($joinSearch ?? '') ?>" data-live-search autocomplete="off">
            <button type="button" class="btn btn-outline-secondary" data-live-clear title="Clear"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="btn-group shadow-sm">
            <button type="button" class="btn btn-outline-primary btn-sm active" data-live-chip data-live-key="join-filter" data-live-value="all">All</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-live-chip data-live-key="join-filter" data-live-value="open">Open</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-live-chip data-live-key="join-filter" data-live-value="enrolled">Already enrolled</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-live-chip data-live-key="join-filter" data-live-value="waitlist">Waitlist</button>
        </div>
        <span class="small text-muted"><span data-live-count><?= count($availableClasses) ?></span> shown</span>
    </div>

<?php if ($availableClasses): ?>
    <div class="row g-4">
        <?php foreach ($availableClasses as $c):
            $isEnrolled = !empty($c['is_enrolled']) || in_array((int)$c['id'], $enrolledIds, true);
            $onWaitlist = !empty($c['on_waitlist']);
            $teacherId = (int)($c['teacher_id'] ?? $c['class_teacher_id'] ?? 0);
            $leadTeacher = trim((string)($c['teacher_name'] ?? $c['class_teacher_name'] ?? ''));
            if ($leadTeacher === '') {
                $leadTeacher = 'Teacher to be assigned';
            }
            $searchBlob = strtolower(trim(
                ($c['name'] ?? $c['class_name'] ?? '') . ' ' .
                $leadTeacher . ' ' .
                ($c['subject_names'] ?? $c['subject_name'] ?? '') . ' ' .
                ($c['description'] ?? '') . ' ' .
                ($c['next_lesson'] ?? '')
            ));
            $joinFilter = $isEnrolled ? 'enrolled' : ($onWaitlist ? 'waitlist' : 'open');
        ?>
            <div class="col-md-6 col-lg-4" data-live-item data-search="<?= student_e($searchBlob) ?>" data-join-filter="<?= $joinFilter ?>" data-enrolled="<?= $isEnrolled ? '1' : '0' ?>" data-waitlist="<?= $onWaitlist ? '1' : '0' ?>">
                <div class="card h-100 border shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle d-grid place-items-center text-white fw-bold overflow-hidden" style="width:48px;height:48px;background:<?= student_e(teacherAvatarColor($leadTeacher)) ?>;flex-shrink:0;">
                                <?php
                                $photoPath = $c['teacher_photo_path'] ?? teacherPhotoPath($teacherId, $c['teacher_photo'] ?? null);
                                $photoSrc = teacherPhotoSrc($photoPath);
                                ?>
                                <?php if ($photoSrc !== ''): ?>
                                    <img src="<?= student_e($photoSrc) ?>" alt="<?= student_e($leadTeacher) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <span><?= student_e(teacherInitials($leadTeacher)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark"><?= student_e((string)($c['name'] ?? $c['class_name'] ?? 'Class')) ?></h5>
                                <?php if ($teacherId > 0): ?>
                                    <div class="small text-muted">
                                        <a href="?tab=teachers&amp;id=<?= $teacherId ?>"><i class="bi bi-person"></i> <?= student_e($leadTeacher) ?></a>
                                    </div>
                                <?php else: ?>
                                    <div class="small text-muted"><i class="bi bi-person"></i> <?= student_e($leadTeacher) ?></div>
                                <?php endif; ?>
                                <?php
                                $subjectLabel = trim((string)($c['subject_name'] ?? $c['subject_names'] ?? ''));
                                $classLabel = trim((string)($c['name'] ?? $c['class_name'] ?? ''));
                                if ($subjectLabel !== '' && strcasecmp($subjectLabel, $classLabel) !== 0):
                                ?>
                                    <div class="small text-muted"><?= student_e($subjectLabel) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($c['next_lesson'])): ?>
                            <div class="small text-muted mb-3"><i class="bi bi-clock me-1"></i>Next lesson <?= student_e($c['next_lesson']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($c['description'])): ?>
                            <p class="text-muted small mb-3 flex-grow-1"><?= student_e($c['description']) ?></p>
                        <?php else: ?>
                            <div class="flex-grow-1"></div>
                        <?php endif; ?>

                        <div class="pt-3 border-top mt-auto">
                            <?php
                            $capacity = isset($c['capacity']) && $c['capacity'] !== null && $c['capacity'] !== '' ? (int)$c['capacity'] : 0;
                            $studentCount = (int)($c['student_count'] ?? 0);
                            $onWaitlist = !empty($c['on_waitlist']);
                            $isFull = $capacity > 0 && $studentCount >= $capacity;
                            $seatsLeft = $capacity > 0 ? max(0, $capacity - $studentCount) : null;
                            ?>
                            <?php if ($capacity > 0): ?>
                                <div class="small text-muted mb-2">
                                    <?= $studentCount ?> / <?= $capacity ?> enrolled
                                    <?php if ($isFull): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Full</span>
                                    <?php else: ?>
                                        · <?= $seatsLeft ?> seat<?= $seatsLeft === 1 ? '' : 's' ?> left
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($isEnrolled): ?>
                                <div class="d-grid gap-2">
                                    <div class="btn btn-outline-success btn-sm py-2 fw-semibold disabled">
                                        <i class="bi bi-check2-circle me-1"></i> Already Enrolled
                                    </div>
                                    <form method="POST" action="leave_class.php" class="js-leave-class">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="return_tab" value="join">
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 py-2">
                                            <i class="bi bi-box-arrow-left me-1"></i> Unenroll
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($onWaitlist && $isFull): ?>
                                <div class="d-grid gap-2">
                                    <div class="btn btn-outline-warning btn-sm py-2 fw-semibold disabled">
                                        <i class="bi bi-hourglass-split me-1"></i> On waitlist
                                    </div>
                                    <form method="POST" action="join_class.php">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="join_action" value="leave_waitlist">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100 py-2">
                                            Leave waitlist
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="join_class.php">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                                    <?php if ($teacherId > 0): ?>
                                        <input type="hidden" name="teacher_id" value="<?= $teacherId ?>">
                                    <?php endif; ?>
                                    <?php if ($isFull): ?>
                                        <button type="submit" class="btn btn-warning btn-sm w-100 py-2 fw-bold">
                                            <i class="bi bi-hourglass-split me-1"></i> Join waitlist
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-bold">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> <?= $onWaitlist ? 'A seat is free — enrol now' : ('Enroll with ' . student_e($leadTeacher === 'Teacher to be assigned' ? 'this class' : $leadTeacher)) ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center py-5 bg-light rounded-4 border d-none" data-live-empty>
        <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
        <h4 class="fw-bold">No Classes Found</h4>
        <p class="text-muted mb-0">No classes matched your search. Try different words or clear the filters.</p>
    </div>
<?php else: ?>
    <div class="text-center py-5 bg-light rounded-4 border">
        <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
        <h4 class="fw-bold">No Classes Found</h4>
        <p class="text-muted mb-0">There are no classes available to join right now.</p>
    </div>
<?php endif; ?>
</div>
