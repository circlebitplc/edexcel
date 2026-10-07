<?php
declare(strict_types=1);
/**
 * student/views/tabs/teachers.php
 * Staff directory and teacher profile (classes + upcoming lessons).
 */
$directory = $teacherDirectory ?? [];
$profile = $teacherProfile ?? null;
$profileClasses = $teacherClasses ?? [];
$upcoming = $teacherUpcoming ?? [];
$openId = (int)($selectedTeacherId ?? 0);
?>

<?php if ($profile): ?>
    <div class="mb-3">
        <a href="?tab=teachers" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> All teachers
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap gap-4 align-items-start">
                <div class="rounded-circle d-grid place-items-center text-white fw-bold overflow-hidden" style="width:88px;height:88px;background:<?= student_e($profile['avatar_color'] ?? teacherAvatarColor((string)$profile['name'])) ?>;flex-shrink:0;">
                    <?php if (!empty($profile['photo_path'])): ?>
                        <img src="<?= student_e(teacherPhotoSrc($profile['photo_path'] ?? null)) ?>" alt="<?= student_e($profile['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span class="fs-3"><?= student_e($profile['initials'] ?? teacherInitials((string)$profile['name'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-1"><?= student_e($profile['name']) ?></h3>
                    <?php if (!empty($profile['subjects'])): ?>
                        <div class="text-muted mb-2"><i class="bi bi-book me-1"></i><?= student_e($profile['subjects']) ?></div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php if (!empty($profile['qualifications'])): ?>
                            <span class="badge bg-primary-subtle text-primary"><?= student_e($profile['qualifications']) ?></span>
                        <?php endif; ?>
                        <?php if ($profile['experience_years'] !== null && $profile['experience_years'] !== ''): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= (int)$profile['experience_years'] ?> years’ experience</span>
                        <?php endif; ?>
                    </div>
                    <?php
                    $links = [];
                    foreach (['website' => 'bi-globe', 'facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'youtube' => 'bi-youtube'] as $key => $icon) {
                        $url = trim((string)($profile[$key] ?? ''));
                        if ($url !== '' && preg_match('#^https?://#i', $url)) {
                            $links[] = ['url' => $url, 'icon' => $icon];
                        }
                    }
                    ?>
                    <?php if ($links): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($links as $link): ?>
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= student_e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi <?= student_e($link['icon']) ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($profile['bio'])): ?>
                <p class="mt-3 mb-0"><?= nl2br(student_e($profile['bio'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($profile['achievements'])): ?>
                <div class="mt-3 small">
                    <div class="fw-semibold mb-1">Highlights</div>
                    <div class="text-muted"><?= nl2br(student_e($profile['achievements'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h4 class="h6 fw-bold mb-3"><i class="bi bi-collection me-2 text-primary"></i>Classes</h4>
                    <?php if (!$profileClasses): ?>
                        <p class="text-muted mb-0">No classes listed for this teacher yet.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($profileClasses as $class): ?>
                                <?php $enrolled = (int)($class['is_enrolled'] ?? 0) > 0; ?>
                                <div class="border rounded-4 p-3">
                                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                                        <div>
                                            <div class="fw-semibold"><?= student_e($class['name'] ?? 'Class') ?></div>
                                            <?php if (!empty($class['subjects'])): ?>
                                                <div class="small text-muted"><?= student_e($class['subjects']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($class['description'])): ?>
                                                <div class="small mt-1"><?= student_e($class['description']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($enrolled): ?>
                                                <span class="badge bg-success-subtle text-success">You’re enrolled</span>
                                            <?php else: ?>
                                                <form method="post" action="join_class.php">
                                                    <input type="hidden" name="csrf_token" value="<?= student_e(csrf_token()) ?>">
                                                    <input type="hidden" name="class_id" value="<?= (int)$class['id'] ?>">
                                                    <input type="hidden" name="return_tab" value="teachers">
                                                    <input type="hidden" name="teacher_id" value="<?= (int)$profile['id'] ?>">
                                                    <button class="btn btn-primary btn-sm rounded-pill">Join class</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h4 class="h6 fw-bold mb-3"><i class="bi bi-calendar3 me-2 text-primary"></i>Upcoming lessons</h4>
                    <?php if (!$upcoming): ?>
                        <p class="text-muted mb-0">No upcoming lessons on the timetable.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($upcoming as $lesson): ?>
                                <li class="border-bottom py-2">
                                    <div class="fw-semibold"><?= student_e($lesson['class_name'] ?? '') ?></div>
                                    <div class="small text-muted">
                                        <?= student_e(!empty($lesson['date']) ? date('D, d M Y', strtotime((string)$lesson['date'])) : '') ?>
                                        <?php if (!empty($lesson['start_time'])): ?>
                                            · <?= student_e(date('g:i A', strtotime((string)$lesson['start_time']))) ?>
                                            <?php if (!empty($lesson['end_time'])): ?>– <?= student_e(date('g:i A', strtotime((string)$lesson['end_time']))) ?><?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= student_e($lesson['subject_name'] ?? '') ?>
                                        <?php if (!empty($lesson['room_name'])): ?> · <?= student_e($lesson['room_name']) ?><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-person-badge me-2 text-primary"></i>Teachers</h3>
            <p class="text-muted mb-0">Find a teacher, read their profile, and see the classes they teach.</p>
        </div>
    </div>

    <?php if ($openId > 0 && !$profile): ?>
        <div class="alert alert-warning">That teacher was not found.</div>
    <?php endif; ?>

    <div data-live-scope>
        <div class="input-group mb-3 shadow-sm rounded-4 overflow-hidden">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control border-start-0" placeholder="Search by teacher name or subject..." data-live-search autocomplete="off">
        </div>
        <?php if ($directory): ?>
            <div class="row g-4">
                <?php foreach ($directory as $t):
                    $blob = strtolower(trim(($t['name'] ?? '') . ' ' . ($t['subjects'] ?? '') . ' ' . ($t['qualifications'] ?? '')));
                    $bio = trim((string)($t['bio'] ?? ''));
                    if (strlen($bio) > 140) {
                        $bio = substr($bio, 0, 137) . '…';
                    }
                ?>
                    <div class="col-md-6 col-lg-4" data-live-item data-search="<?= student_e($blob) ?>">
                        <a href="?tab=teachers&amp;id=<?= (int)$t['id'] ?>" class="text-decoration-none text-reset">
                            <div class="card h-100 border shadow-sm rounded-4">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="rounded-circle d-grid place-items-center text-white fw-bold overflow-hidden" style="width:56px;height:56px;background:<?= student_e($t['avatar_color'] ?? '#4a90d9') ?>;flex-shrink:0;">
                                            <?php if (!empty($t['photo_path'])): ?>
                                                <img src="<?= student_e(teacherPhotoSrc($t['photo_path'] ?? null)) ?>" alt="<?= student_e($t['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                            <?php else: ?>
                                                <span><?= student_e($t['initials'] ?? '') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold mb-0"><?= student_e($t['name']) ?></h5>
                                            <div class="small text-muted"><?= (int)($t['class_count'] ?? 0) ?> class<?= (int)($t['class_count'] ?? 0) === 1 ? '' : 'es' ?></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($t['subjects'])): ?>
                                        <div class="small text-muted mb-2"><i class="bi bi-book me-1"></i><?= student_e($t['subjects']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($t['qualifications'])): ?>
                                        <span class="badge bg-primary-subtle text-primary mb-2"><?= student_e($t['qualifications']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($bio !== ''): ?>
                                        <p class="small text-muted mb-0"><?= student_e($bio) ?></p>
                                    <?php endif; ?>
                                    <div class="mt-3 small fw-semibold text-primary">View profile &amp; classes <i class="bi bi-arrow-right"></i></div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center py-5 bg-light rounded-4 border d-none" data-live-empty>
                <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
                <h4 class="fw-bold">No matching teachers</h4>
                <p class="text-muted mb-0">Try another name or subject.</p>
            </div>
        <?php else: ?>
            <div class="text-center py-5 bg-light rounded-4 border">
                <i class="bi bi-person-badge fs-1 text-muted d-block mb-3"></i>
                <h4 class="fw-bold">No teachers listed yet</h4>
                <p class="text-muted mb-0">Teacher profiles will appear here once they are added.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
