<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$isAdminDirectory = is_admin();

if ($isAdminDirectory) {
    require_once __DIR__ . '/../config/campus.php';
    if ($pdo instanceof PDO) {
        ensure_recordings_schema($pdo);
    }
}

$directoryWhere = 't.deleted_at IS NULL';
if (!$isAdminDirectory) {
    $directoryWhere .= " AND LOWER(t.name) <> 'default teacher'";
}

$stmt = $pdo->query("SELECT
    t.*,
    tp.bio AS profile_bio,
    tp.qualifications AS profile_qualifications,
    tp.experience_years AS profile_experience,
    tp.achievements AS profile_achievements,
    tp.profile_background AS profile_background,
    tp.website AS profile_website,
    tp.facebook AS profile_facebook,
    tp.instagram AS profile_instagram,
    tp.youtube AS profile_youtube,
    GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS subjects
    FROM teachers t
    LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
    LEFT JOIN subjects s ON ts.subject_id = s.id AND s.deleted_at IS NULL
    LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
    WHERE {$directoryWhere}
    GROUP BY t.id
    ORDER BY t.name");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalTeachers = count($teachers);
$assignedTeachers = 0;
$totalSubjectLinks = 0;

/*
 * Profile completion summary.
 *
 * The same five core fields used by the teacher cards:
 * - Photo
 * - Biography
 * - Qualifications
 * - Experience
 * - Achievements
 */
$completeProfiles = 0;
$incompleteProfiles = 0;
$totalCompletionPoints = 0;
$totalCompletionItems = 0;

foreach ($teachers as $teacherSummary) {

    $teacherPhoto = trim(
        (string)($teacherSummary['photo'] ?? '')
    );

    $hasSummaryPhoto = $teacherPhoto !== '';

    if (!$hasSummaryPhoto) {
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {

            $candidate = __DIR__ .
                '/../assets/images/teachers/' .
                (int)$teacherSummary['id'] .
                '.' .
                $ext;

            if (is_file($candidate)) {
                $hasSummaryPhoto = true;
                break;
            }
        }
    }

    $summaryFields = [
        $hasSummaryPhoto,
        !empty(trim((string)($teacherSummary['profile_bio'] ?? ''))),
        !empty(trim((string)($teacherSummary['profile_qualifications'] ?? ''))),
        $teacherSummary['profile_experience'] !== null &&
            $teacherSummary['profile_experience'] !== '',
        !empty(trim((string)($teacherSummary['profile_achievements'] ?? '')))
    ];

    $summaryPercent = (int)round(
        count(array_filter($summaryFields)) /
        count($summaryFields) *
        100
    );

    $totalCompletionPoints += count(
        array_filter($summaryFields)
    );

    $totalCompletionItems += count($summaryFields);

    if ($summaryPercent === 100) {
        $completeProfiles++;
    } else {
        $incompleteProfiles++;
    }
}

$overallCompletionPercent = $totalCompletionItems > 0
    ? (int)round(
        ($totalCompletionPoints / $totalCompletionItems) * 100
    )
    : 0;
foreach ($teachers as $teacher) {
    if (!empty($teacher['subjects'])) $assignedTeachers++;
    if (!empty($teacher['subjects'])) $totalSubjectLinks += count(array_filter(array_map('trim', explode(',', $teacher['subjects']))));
}
?>
<?php
$page_title = $isAdminDirectory
    ? 'Manage Teachers | Edexcel College Portal'
    : 'Edexcel Teachers — Online Classes Worldwide';
$meta_description = $isAdminDirectory
    ? 'Manage teacher profiles, subjects, and accounts for Edexcel College.'
    : 'Edexcel College teachers for Pearson IGCSE and IAL. Classes are live online for students worldwide. Physical classes are at the Kandy campus when that teacher is scheduled there.';
$meta_robots = $isAdminDirectory ? 'noindex, nofollow' : 'index, follow';
if (!function_exists('seo_absolute_url')) {
    require_once __DIR__ . '/../includes/seo.php';
}
$canonical_url = $isAdminDirectory ? '' : seo_absolute_url('teachers/');
$seo_social = !$isAdminDirectory;
$og_type = 'website';
$og_image_alt = 'Edexcel College teachers';
$head_json_ld = [];
if (!$isAdminDirectory) {
    $directoryListItems = [];
    foreach ($teachers as $directoryTeacher) {
        $directoryListItems[] = [
            'name' => (string)($directoryTeacher['name'] ?? ''),
            'url' => seo_teacher_profile_url((int)($directoryTeacher['id'] ?? 0)),
        ];
    }
    $head_json_ld = [
        seo_organization_schema($pdo instanceof PDO ? $pdo : null),
        seo_breadcrumb_schema([
            ['name' => 'Home', 'url' => seo_absolute_url('/')],
            ['name' => 'Teachers', 'url' => seo_absolute_url('teachers/')],
        ]),
        seo_teacher_itemlist_schema($directoryListItems),
    ];
}
include __DIR__ . '/../includes/header.php';
?>

<div class="teacher-page-header">
    <div>
        <h1 class="teacher-page-title"><i class="bi bi-people-fill"></i> <?= $isAdminDirectory ? 'Teachers' : 'Edexcel Teachers' ?></h1>
        <p class="teacher-page-subtitle">
            <?php if ($isAdminDirectory): ?>
                Manage teacher profiles, subjects and accounts. The directory automatically scales as your teaching team grows.
            <?php else: ?>
                Meet specialist Pearson Edexcel IGCSE and International A Level teachers at Edexcel College in Kandy. Open a profile for subjects, qualifications, and this week’s timetable — including ICT &amp; Computer Science, Mathematics, Chemistry, Physics, Economics, and Business.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($isAdminDirectory): ?>
        <a href="create.php" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Add Teacher</a>
    <?php endif; ?>
</div>

<?php if ($isAdminDirectory): ?>
<!-- ============================================================
     PROFILE COMPLETION DASHBOARD
     ============================================================ -->

<div class="teacher-profile-dashboard">

    <div class="teacher-profile-dashboard-main">

        <div class="teacher-profile-dashboard-title">
            <div class="teacher-profile-dashboard-icon">
                <i class="bi bi-person-vcard-fill"></i>
            </div>

            <div>
                <h2>Teacher Profile Completion</h2>
                <p>
                    Keep teacher public profiles complete and ready for students.
                </p>
            </div>
        </div>

        <div class="teacher-profile-progress">

            <div class="teacher-profile-progress-bar">
                <span
                    style="width: <?= $overallCompletionPercent ?>%;"
                ></span>
            </div>

            <strong>
                <?= $overallCompletionPercent ?>%
            </strong>

        </div>

    </div>

    <div class="teacher-profile-dashboard-stats">

        <div class="teacher-dashboard-stat">
            <strong><?= $totalTeachers ?></strong>
            <span>Total Teachers</span>
        </div>

        <div class="teacher-dashboard-stat complete">
            <strong><?= $completeProfiles ?></strong>
            <span>Complete</span>
        </div>

        <div class="teacher-dashboard-stat incomplete">
            <strong><?= $incompleteProfiles ?></strong>
            <span>Need Information</span>
        </div>

    </div>

</div>
<?php endif; ?>

<!-- ============================================================
     PROFILE COMPLETION SUMMARY
     ============================================================ -->

<div data-live-scope>

<?php if ($isAdminDirectory): ?>
<div class="teacher-profile-summary">

    <button
        type="button"
        class="teacher-profile-filter active"
        data-live-chip
        data-live-key="profile-status"
        data-live-value="all"
    >
        <strong><?= $totalTeachers ?></strong>
        <span>All Teachers</span>
    </button>

    <button
        type="button"
        class="teacher-profile-filter"
        data-live-chip
        data-live-key="profile-status"
        data-live-value="complete"
    >
        <strong><?= $completeProfiles ?></strong>
        <span>Complete</span>
    </button>

    <button
        type="button"
        class="teacher-profile-filter"
        data-live-chip
        data-live-key="profile-status"
        data-live-value="incomplete"
    >
        <strong><?= $incompleteProfiles ?></strong>
        <span>Needs Information</span>
    </button>

</div>
<?php endif; ?>

<?php if ($isAdminDirectory && isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if ($isAdminDirectory && isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="teacher-stats">
    <div class="teacher-stat"><div class="teacher-stat-icon"><i class="bi bi-people"></i></div><div><strong><?= $totalTeachers ?></strong><span>Total teachers</span></div></div>
    <div class="teacher-stat"><div class="teacher-stat-icon"><i class="bi bi-book"></i></div><div><strong><?= $assignedTeachers ?></strong><span>With subjects assigned</span></div></div>
    <div class="teacher-stat"><div class="teacher-stat-icon"><i class="bi bi-diagram-3"></i></div><div><strong><?= $totalSubjectLinks ?></strong><span>Subject assignments</span></div></div>
</div>

<div class="teacher-toolbar">
    <div class="teacher-toolbar-grid">
        <div class="teacher-search">
            <i class="bi bi-search"></i>
            <input type="search" id="teacherSearch" class="form-control" placeholder="<?= $isAdminDirectory ? 'Search teacher, email or subject…' : 'Search teacher or subject…' ?>" autocomplete="off" data-live-search>
        </div>
        <select id="subjectFilter" class="form-select" data-live-select data-live-key="subjects">
            <option value="">All subjects</option>
            <?php
            $filterSubjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filterSubjects as $subject):
            ?>
                <option value="<?= htmlspecialchars(strtolower($subject['name'])) ?>"><?= htmlspecialchars($subject['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-secondary" id="clearTeacherFilters" data-live-clear><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
    </div>
</div>

<div class="teacher-grid" id="teacherGrid">
    <?php foreach ($teachers as $t):
        $name = trim($t['name']);
        $parts = preg_split('/\s+/', $name);
        $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
        $subjectNames = array_values(array_filter(array_map('trim', explode(',', $t['subjects'] ?? ''))));
        /*
         * Teacher photo
         *
         * First use the photo path stored in teachers.photo.
         * If that is unavailable or the file does not exist,
         * fall back to the existing ID-based photo convention.
         */

        $photoFile = '';
        $photoPath = '';
        $photoUrl = '';

        $storedPhoto = trim((string)($t['photo'] ?? ''));

        if ($storedPhoto !== '') {

            $storedRelative = ltrim($storedPhoto, '/');
            $storedPath = __DIR__ . '/../' . $storedRelative;

            if (is_file($storedPath)) {

                $photoFile = basename($storedRelative);
                $photoPath = $storedPath;
                $photoUrl = BASE_URL . $storedRelative;
            }
        }

        /*
         * Backward-compatible fallback for existing photos.
         */
        if (!$photoUrl) {

            foreach (
                ['png','jpg','jpeg','webp','gif']
                as $ext
            ) {

                $candidate =
                    __DIR__ .
                    '/../assets/images/teachers/' .
                    $t['id'] .
                    '.' .
                    $ext;

                if (is_file($candidate)) {

                    $photoFile =
                        $t['id'] . '.' . $ext;

                    $photoPath =
                        $candidate;

                    $photoUrl =
                        BASE_URL .
                        'assets/images/teachers/' .
                        $photoFile;

                    break;
                }
            }
        }

        $hasPhoto = $photoUrl !== '';
        $searchData = $isAdminDirectory
            ? strtolower($name . ' ' . ($t['email'] ?? '') . ' ' . implode(' ', $subjectNames))
            : strtolower($name . ' ' . implode(' ', $subjectNames));
        $teacherPhotoAlt = function_exists('seo_teacher_photo_alt')
            ? seo_teacher_photo_alt($name, $subjectNames)
            : ($name . ($subjectNames ? (', ' . implode(' and ', array_slice($subjectNames, 0, 2)) . ' teacher') : ''));

        /*
         * Public profile completion.
         *
         * Core profile items:
         * - Teacher photo
         * - Biography
         * - Qualifications
         * - Teaching experience
         * - Achievements
         *
         * Optional enhancements:
         * - Background photo
         * - Website
         * - Facebook
         * - Instagram
         * - YouTube
         */
        $profileFields = [
            $hasPhoto,
            !empty(trim((string)($t['profile_bio'] ?? ''))),
            !empty(trim((string)($t['profile_qualifications'] ?? ''))),
            $t['profile_experience'] !== null &&
                $t['profile_experience'] !== '',
            !empty(trim((string)($t['profile_achievements'] ?? '')))
        ];

        $profileCompleted =
            count(array_filter($profileFields));

        $profileTotal =
            count($profileFields);

        $profilePercent =
            (int)round(
                ($profileCompleted / $profileTotal) * 100
            );

        $profileComplete =
            $profilePercent === 100;
    ?>
        <article
            class="teacher-card"
            data-live-item
            data-search="<?= htmlspecialchars($searchData) ?>"
            data-subjects="<?= htmlspecialchars(strtolower(implode('|', $subjectNames))) ?>"
            data-profile-status="<?= $profileComplete ? 'complete' : 'incomplete' ?>"
        >
            <div class="teacher-card-top">
                <div class="teacher-avatar">
                    <?php if ($hasPhoto): ?><img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($teacherPhotoAlt) ?>" loading="lazy">
                    <?php else: ?><span class="teacher-avatar-initials"><?= htmlspecialchars($initials ?: '?') ?></span><?php endif; ?>
                </div>
                <div class="min-w-0">
                    <h2 class="teacher-card-name"><?= htmlspecialchars($name) ?></h2>
                    <?php if ($isAdminDirectory): ?>
                    <div class="teacher-card-email" title="<?= htmlspecialchars($t['email'] ?? '') ?>"><?= htmlspecialchars($t['email'] ?? 'No email') ?></div>
                    <?php if (!empty($t['bunny_library_id'])): ?>
                        <div class="small text-muted">Bunny library <?= htmlspecialchars((string)$t['bunny_library_id']) ?></div>
                    <?php else: ?>
                        <div class="small text-warning">No Bunny library</div>
                    <?php endif; ?>
                    <?php elseif (!empty($subjectNames)): ?>
                    <div class="teacher-card-email"><?= htmlspecialchars(function_exists('seo_teacher_subject_label') ? seo_teacher_subject_label($subjectNames) : implode(', ', $subjectNames)) ?> · Edexcel IGCSE / IAL</div>
                    <?php elseif (!empty($t['profile_qualifications'])): ?>
                    <div class="teacher-card-email"><?= htmlspecialchars((string)$t['profile_qualifications']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isAdminDirectory): ?>
            <div class="teacher-profile-status <?= $profileComplete ? 'is-complete' : 'is-incomplete' ?>">

                <?php if ($profileComplete): ?>

                    <i class="bi bi-check-circle-fill"></i>
                    Profile complete

                <?php else: ?>

                    <i class="bi bi-exclamation-circle-fill"></i>
                    Profile <?= $profilePercent ?>% complete

                <?php endif; ?>

            </div>

            <?php if (!$profileComplete): ?>

                <div class="teacher-profile-missing">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Complete the public profile information
                    </span>
                </div>

            <?php endif; ?>
            <?php endif; ?>

            <div class="teacher-subjects">
                <?php if ($subjectNames): foreach (array_slice($subjectNames, 0, 3) as $subject): ?>
                    <span class="teacher-subject"><?= htmlspecialchars($subject) ?></span>
                <?php endforeach; if (count($subjectNames) > 3): ?><span class="teacher-subject more">+<?= count($subjectNames)-3 ?> more</span><?php endif; ?>
                <?php else: ?><span class="teacher-subject more">No subjects assigned</span><?php endif; ?>
            </div>
            <div class="teacher-card-actions">
                <a href="teacher_profile.php?id=<?= (int)$t['id'] ?>" class="btn <?= $isAdminDirectory ? 'btn-secondary' : 'btn-primary' ?>"><i class="bi bi-person-vcard"></i> <?= $isAdminDirectory ? 'View Profile' : 'View profile &amp; timetable' ?></a>
                <?php if ($isAdminDirectory): ?>
                <a href="profile_admin.php?id=<?= (int)$t['id'] ?>" class="btn btn-primary"><i class="bi bi-person-vcard"></i> Manage Profile</a>
                <a href="edit.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this teacher from the active directory?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <div class="teacher-empty" id="teacherEmpty" data-live-empty <?= $teachers ? 'style="display:none"' : '' ?>><i class="bi bi-person-x fs-2 d-block mb-2"></i><strong>No teachers found</strong><div class="small mt-1"><?= $isAdminDirectory ? 'Try changing your search or add a new teacher.' : 'Try changing your search.' ?></div></div>
</div>

</div>


<style>

/* ============================================================
   TEACHER PROFILE COMPLETION DASHBOARD
   ============================================================ */

.teacher-profile-dashboard {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px;
    align-items: center;
    margin: 0 0 18px;
    padding: 20px;
    border: 1px solid var(--panel-border, rgba(0,0,0,.10));
    border-radius: 18px;
    background: var(--surface, #fff);
    box-shadow: var(--shadow-sm, 0 4px 16px rgba(0,0,0,.06));
}

.teacher-profile-dashboard-main {
    min-width: 0;
}

.teacher-profile-dashboard-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.teacher-profile-dashboard-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: rgba(81,97,206,.10);
    color: #5161ce;
    font-size: 1.2rem;
}

.teacher-profile-dashboard-title h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
}

.teacher-profile-dashboard-title p {
    margin: 3px 0 0;
    color: var(--muted, #6c757d);
    font-size: .76rem;
}

.teacher-profile-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
}

.teacher-profile-progress-bar {
    height: 9px;
    flex: 1;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(81,97,206,.10);
}

.teacher-profile-progress-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #5161ce;
}

.teacher-profile-progress strong {
    min-width: 42px;
    text-align: right;
    font-size: .82rem;
}

.teacher-profile-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(75px, 1fr));
    gap: 8px;
}

.teacher-dashboard-stat {
    min-width: 82px;
    padding: 10px 12px;
    text-align: center;
    border-radius: 12px;
    background: var(--surface-soft, #f8f9fa);
}

.teacher-dashboard-stat strong {
    display: block;
    font-size: 1.25rem;
    line-height: 1.1;
}

.teacher-dashboard-stat span {
    display: block;
    margin-top: 3px;
    font-size: .65rem;
    color: var(--muted, #6c757d);
    font-weight: 700;
}

.teacher-dashboard-stat.complete strong {
    color: #198754;
}

.teacher-dashboard-stat.incomplete strong {
    color: #b58105;
}

.teacher-profile-missing {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: calc(100% - 24px);
    margin: 7px auto 0;
    padding: 7px 9px;
    border-radius: 9px;
    background: rgba(255,193,7,.08);
    color: #9a6700;
    font-size: .68rem;
    font-weight: 700;
}

.teacher-profile-missing i {
    flex: 0 0 auto;
}

@media (max-width: 760px) {

    .teacher-profile-dashboard {
        grid-template-columns: 1fr;
    }

    .teacher-profile-dashboard-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 480px) {

    .teacher-profile-dashboard {
        padding: 15px;
    }

    .teacher-profile-dashboard-stats {
        grid-template-columns: 1fr;
    }

    .teacher-dashboard-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
    }

    .teacher-dashboard-stat span {
        margin-top: 0;
    }
}

/* ============================================================
   TEACHER PROFILE FILTER SUMMARY
   ============================================================ */

.teacher-profile-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin: 0 0 18px;
}

.teacher-profile-filter {
    appearance: none;
    border: 1px solid var(--panel-border, rgba(0,0,0,.10));
    background: var(--surface, #fff);
    color: var(--text, #212529);
    border-radius: 14px;
    padding: 14px 16px;
    text-align: left;
    cursor: pointer;
    transition: .18s ease;
}

.teacher-profile-filter strong {
    display: block;
    font-size: 1.35rem;
    line-height: 1.1;
    margin-bottom: 3px;
}

.teacher-profile-filter span {
    display: block;
    font-size: .76rem;
    color: var(--muted, #6c757d);
    font-weight: 700;
}

.teacher-profile-filter:hover {
    transform: translateY(-1px);
    border-color: rgba(81,97,206,.35);
}

.teacher-profile-filter.active {
    border-color: #5161ce;
    box-shadow: 0 0 0 2px rgba(81,97,206,.10);
}

.teacher-card.profile-filter-hidden {
    display: none !important;
}

@media (max-width: 640px) {
    .teacher-profile-summary {
        grid-template-columns: 1fr;
    }

    .teacher-profile-filter {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .teacher-profile-filter strong {
        margin: 0;
    }
}

.teacher-profile-status {


    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
    width: fit-content;
}

.teacher-profile-status.is-complete {
    background: rgba(25, 135, 84, .10);
    color: #198754;
    border: 1px solid rgba(25, 135, 84, .18);
}

.teacher-profile-status.is-incomplete {
    background: rgba(255, 193, 7, .12);
    color: #9a6700;
    border: 1px solid rgba(255, 193, 7, .22);
}

[data-bs-theme="dark"] .teacher-profile-status.is-complete {
    background: rgba(25, 135, 84, .18);
    color: #75d6a5;
}

[data-bs-theme="dark"] .teacher-profile-status.is-incomplete {
    background: rgba(255, 193, 7, .16);
    color: #ffd76a;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
