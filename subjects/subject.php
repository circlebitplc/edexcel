<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$pages = seo_public_subject_pages();
$page = $pages[$slug] ?? null;
if ($page === null) {
    http_response_code(404);
    require dirname(__DIR__) . '/error_page.php';
    exit;
}

$base = seo_base_url();
$path = 'subjects/' . $page['slug'] . '/';
$url = seo_absolute_url($path);
$teacherLinks = [];
foreach ($page['teachers'] as $teacher) {
    $teacherLinks[] = '<a href="' . e(seo_teacher_profile_url((int)$teacher['id'])) . '">' . e((string)$teacher['name']) . '</a>';
}
$related = [];
foreach ($pages as $other) {
    if ($other['slug'] === $page['slug']) {
        continue;
    }
    $related[] = '<a href="' . e($base . '/subjects/' . $other['slug'] . '/') . '">' . e($other['name']) . '</a>';
}

seo_page_start([
    'title' => $page['name'] . ' — Edexcel Classes Online',
    'description' => $page['name'] . ' at Edexcel College is taught live online for students worldwide. A physical class is at the Kandy campus only when the teacher is scheduled there. Confirm IGCSE or IAL on the timetable.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Subjects', 'url' => $base . '/subjects'],
        ['name' => $page['name'], 'url' => $url],
    ],
    'schemas' => [
        seo_course_schema([
            'name' => $page['name'],
            'description' => $page['name'] . ' is taught live online worldwide. Physical classes are at the Kandy campus when scheduled. The level, IGCSE or IAL, follows the current timetable.',
            'url' => $url,
            'about' => 'Pearson Edexcel',
        ]),
    ],
]);
?>
    <h1><?= e($page['name']) ?></h1>
    <p class="seo-lead"><?= e($page['name']) ?> is taught at Edexcel College for Pearson Edexcel students. Classes are live online worldwide. Physical classes are at the Kandy campus in Sri Lanka when this subject is scheduled on site.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>Examination board</h2>
    <p>Pearson Edexcel. The college prepares students. It does not enter them for the exam.</p>

    <h2>Level</h2>
    <p>This subject may appear on an <a href="<?= e($base) ?>/online-classes/igcse/">IGCSE</a> timetable, an <a href="<?= e($base) ?>/online-classes/ial/">IAL</a> timetable, or both, depending on the current classes. Ask admissions which level is open. This page does not add a level that is not on the timetable.</p>

    <h2>Teachers</h2>
    <p><?= implode(', ', $teacherLinks) ?></p>

    <h2>Where students attend</h2>
    <ul>
        <li><a href="<?= e($base) ?>/online-classes">Online</a> — worldwide, at the Asia/Colombo class time</li>
        <li><a href="<?= e($base) ?>/locations/kandy">Physical</a> — Kandy campus only, when scheduled</li>
    </ul>

    <h2>Admission</h2>
    <p><a href="<?= e($base) ?>/admissions/enquire.php">Enquire</a> with this subject name and the level you need.</p>

    <?php if ($related !== []): ?>
    <h2>Other published subjects</h2>
    <p><?= implode(' · ', $related) ?></p>
    <?php endif; ?>
<?php
seo_page_end();
