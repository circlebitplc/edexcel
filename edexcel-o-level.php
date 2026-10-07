<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$canonicalPath = 'edexcel-o-level';
$url = seo_absolute_url($canonicalPath);

$courseSchema = seo_course_schema([
    'name' => 'Edexcel IGCSE / O Level Classes',
    'description' => 'Live online Pearson Edexcel IGCSE classes for students worldwide. Physical classes are at the Kandy campus in Sri Lanka when a subject and teacher are scheduled there.',
    'url' => $url,
    'educationalLevel' => 'Secondary / IGCSE',
    'about' => 'Pearson Edexcel IGCSE',
], $pdoSafe);

seo_page_start([
    'title' => 'Edexcel IGCSE Classes Online Worldwide',
    'description' => 'Pearson Edexcel IGCSE classes, live online for students worldwide. Around 90% of Edexcel College classes are online. Physical IGCSE classes are at the Kandy campus when scheduled.',
    'canonical_path' => $canonicalPath,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Edexcel Classes', 'url' => $base . '/edexcel-classes'],
        ['name' => 'IGCSE / O Level', 'url' => $url],
    ],
    'schemas' => [$courseSchema],
]);
?>
    <h1>Edexcel O Level &amp; IGCSE Classes</h1>
    <p class="seo-lead">Pearson Edexcel International GCSE classes at Edexcel College. Most students attend live online from anywhere in the world. A physical class is at the Kandy campus only when that subject and teacher are on the campus timetable. Families who search for “O Level” usually mean this IGCSE pathway.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>What is this course?</h2>
    <p>Pearson Edexcel IGCSE is an internationally recognised secondary qualification. At Edexcel College, IGCSE teaching covers subject content and examination requirements so students are ready for official Pearson papers.</p>

    <h2>Who it is for</h2>
    <ul>
        <li>Secondary students aiming for Pearson Edexcel IGCSE results</li>
        <li>Learners who need O Level tuition with an international curriculum focus</li>
        <li>Students outside Sri Lanka, and students in Sri Lanka, who will join live online</li>
        <li>Students who want a Kandy campus seat when that IGCSE class is scheduled on site</li>
    </ul>

    <h2>What students learn</h2>
    <p>Students study Pearson syllabus topics for their chosen IGCSE subjects, practise past papers, and receive guidance on exam technique. Class groups and subjects follow the college’s live programme list and timetable.</p>

    <h2>Examination information</h2>
    <p>Assessments follow Pearson Edexcel IGCSE paper structures. The college portal includes official exam planning tools so students and parents can track paper selections for relevant exam series.</p>

    <h2>Benefits</h2>
    <ul>
        <li>Specialist Edexcel teachers for secondary subjects</li>
        <li>Exam-focused lessons and past-paper practice</li>
        <li>Clear next step into <a href="<?= e($base) ?>/edexcel-a-level">Edexcel A Level / IAL</a> study</li>
    </ul>

    <?php seo_render_featured_teachers_section('IGCSE subject specialists'); ?>

    <h2>How to enrol</h2>
    <p>Send a course enquiry or register for a student account. Admissions matches subjects to the live timetable. Read <a href="<?= e($base) ?>/online-classes/igcse/">how IGCSE online classes work</a>, browse <a href="<?= e($base) ?>/subjects/">subjects</a>, or see the <a href="<?= e($base) ?>/locations/kandy">Kandy campus</a> if you need a physical class.</p>

    <?php seo_page_cta('Enquire about IGCSE / O Level classes'); ?>
    <?php seo_page_related(['o-level']); ?>
<?php
seo_page_end();
