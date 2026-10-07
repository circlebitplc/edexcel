<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo; 
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$canonicalPath = 'edexcel-a-level';
$url = seo_absolute_url($canonicalPath);

$courseSchema = seo_course_schema([
    'name' => 'Edexcel A Level / International A Level (IAL) Classes',
    'description' => 'Live online Pearson Edexcel International A Level classes for students worldwide. Physical classes are at the Kandy campus in Sri Lanka when a subject and teacher are scheduled there.',
    'url' => $url,
    'educationalLevel' => 'Post-16 / International A Level',
    'about' => 'Pearson Edexcel International A Level',
], $pdoSafe);

seo_page_start([
    'title' => 'Edexcel IAL Classes Online Worldwide',
    'description' => 'Pearson Edexcel International A Level classes, live online for students worldwide. Around 90% of Edexcel College classes are online. Physical IAL classes are at the Kandy campus when scheduled.',
    'canonical_path' => $canonicalPath,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Edexcel Classes', 'url' => $base . '/edexcel-classes'],
        ['name' => 'A Level / IAL', 'url' => $url],
    ],
    'schemas' => [$courseSchema],
]);
?>
    <h1>Edexcel A Level &amp; IAL Classes</h1>
    <p class="seo-lead">Pearson Edexcel International A Level classes for post-16 students. Most classes are live online, so students can join from outside Sri Lanka. Physical classes are at the Kandy campus when that subject and teacher are scheduled there. “A Level” in our pages means this IAL pathway.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>What is this course?</h2>
    <p>Pearson Edexcel International A Level (IAL) is a post-16 qualification recognised for university progression. Our A Level classes support unitised AS/A2-style study with teaching aligned to Pearson examination requirements.</p>

    <h2>Who it is for</h2>
    <ul>
        <li>Students who have completed IGCSE / O Level or an equivalent pathway</li>
        <li>Post-16 learners preparing for Pearson Edexcel IAL units</li>
        <li>Students worldwide who will attend live online</li>
        <li>Students who need a physical class at the Kandy campus when it is on the timetable</li>
    </ul>

    <h2>What students learn</h2>
    <p>Students deepen subject mastery for their IAL units, practise official-style papers, and receive timetable-based teaching from specialist teachers. Available subjects follow the live college programme list.</p>

    <h2>Examination information</h2>
    <p>IAL assessments follow Pearson’s unit structure and published exam series. Students can use the college portal’s official exam planner to review and select papers for the relevant series.</p>

    <h2>Benefits</h2>
    <ul>
        <li>Experienced Edexcel A Level teachers</li>
        <li>Exam-focused preparation and past-paper practice</li>
        <li>A student and parent portal for the timetable, live class, and lesson records</li>
        <li>Clear pathway from <a href="<?= e($base) ?>/edexcel-o-level">IGCSE / O Level</a> into IAL</li>
    </ul>

    <?php seo_render_featured_teachers_section('A Level / IAL subject specialists'); ?>

    <h2>How to enrol</h2>
    <p>Submit an enquiry or register online. Admissions confirms whether a subject is on the IAL timetable. See <a href="<?= e($base) ?>/online-classes/ial/">IAL online classes</a>, <a href="<?= e($base) ?>/subjects/">subjects</a>, and the <a href="<?= e($base) ?>/locations/kandy">Kandy campus</a>.</p>

    <?php seo_page_cta('Enquire about A Level / IAL classes'); ?>
    <?php seo_page_related(['a-level']); ?>
<?php
seo_page_end();
