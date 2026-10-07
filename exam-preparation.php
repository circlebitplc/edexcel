<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$canonicalPath = 'exam-preparation';
$url = seo_absolute_url($canonicalPath);

$courseSchema = seo_course_schema([
    'name' => 'Edexcel Exam Preparation',
    'description' => 'Pearson Edexcel IGCSE and IAL exam preparation through live online classes worldwide, plus Kandy campus classes when scheduled. Past-paper practice is part of teaching. The college is not an exam centre.',
    'url' => $url,
    'educationalLevel' => 'Secondary and post-16',
    'about' => 'Pearson Edexcel examination preparation',
], $pdoSafe);

seo_page_start([
    'title' => 'Edexcel Exam Preparation — Online Worldwide',
    'description' => 'Exam preparation for Pearson Edexcel IGCSE and International A Level. Classes are mostly live online for students worldwide. Physical preparation classes are at the Kandy campus when scheduled.',
    'canonical_path' => $canonicalPath,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Exam Preparation', 'url' => $url],
    ],
    'schemas' => [$courseSchema],
]);
?>
    <h1>Edexcel Exam Preparation</h1>
    <p class="seo-lead">Exam preparation at Edexcel College is teaching: syllabus topics, past papers, and exam technique inside live classes. Around 90% of those classes are online for students worldwide. Physical classes are at the Kandy campus when scheduled. Official exam entry stays with Pearson or an authorised centre.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>What exam preparation includes</h2>
    <p>Our teaching is built around Pearson paper structures. Students revise syllabus topics, practise past papers, and strengthen exam technique for International GCSE and International A Level assessments.</p>

    <h2>Who it is for</h2>
    <ul>
        <li>IGCSE students approaching Pearson exam series</li>
        <li>IAL / A Level students preparing for unit papers</li>
        <li>Learners who need structured revision alongside regular classes</li>
    </ul>

    <h2>How we support examination readiness</h2>
    <ul>
        <li>Exam-focused classroom teaching</li>
        <li>Past-paper practice and portal learning tools</li>
        <li>Official Pearson exam planner for paper selection and series awareness</li>
        <li>Experienced Edexcel teachers familiar with mark schemes and common pitfalls</li>
    </ul>

    <?php seo_render_featured_teachers_section('Learn with subject specialists'); ?>

    <h2>Related pathways</h2>
    <ul>
        <li><a href="<?= e($base) ?>/edexcel-o-level">Edexcel IGCSE / O Level classes</a></li>
        <li><a href="<?= e($base) ?>/edexcel-a-level">Edexcel A Level / IAL classes</a></li>
        <li><a href="<?= e($base) ?>/edexcel-classes">All Edexcel classes</a></li>
    </ul>

    <h2>How to start</h2>
    <p>Tell us which qualification and subjects you need help with. Admissions will guide you to the right class group and revision support.</p>

    <?php seo_page_cta('Start exam preparation'); ?>
    <?php seo_page_related(['exam']); ?>
<?php
seo_page_end();
