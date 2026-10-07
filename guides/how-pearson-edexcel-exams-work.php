<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$path = 'guides/how-pearson-edexcel-exams-work';
$url = seo_absolute_url($path);
$published = '2026-09-11';
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'How Pearson Edexcel Exams Work',
    'description' => 'A practical overview of Pearson Edexcel International GCSE and International A Level exams: syllabuses, papers, series, and how tuition fits alongside official centres.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Guides', 'url' => $base . '/pearson-exams'],
        ['name' => 'How exams work', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'How Pearson Edexcel exams work',
            'description' => 'Overview of IGCSE and International A Level exam structure and how independent tuition supports preparation.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>How Pearson Edexcel exams work</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Direct answer:',
        'Pearson Edexcel international exams follow published subject specifications. Students prepare through teaching and past papers, then sit papers at an authorised examination centre. Tuition colleges prepare candidates; Pearson and the centre handle official entry and results.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>

    <h2>Qualifications in scope</h2>
    <p>Pearson Edexcel publishes international specifications used worldwide. Two common pathways our students prepare for are:</p>
    <ul>
        <li><strong>International GCSE (IGCSE)</strong> — often searched as “Edexcel O Level”</li>
        <li><strong>International A Level (IAL)</strong> — modular A Level-style units for post-16 study</li>
    </ul>

    <h2>Syllabus → teaching → exam papers</h2>
    <p>Each subject has a published specification (topics, assessment objectives, and paper structure). Good tuition maps weekly lessons to that specification, then builds exam technique with past papers and mark schemes. Official documents and updates live on Pearson’s qualifications site.</p>

    <h2>Exam series and centres</h2>
    <p>Candidates sit papers in scheduled series through schools or authorised private centres. Entry deadlines, fees, and available subjects depend on the centre. Tuition colleges like ours prepare learners academically; we do not replace the centre’s registration desk.</p>

    <h2>Where Edexcel College fits</h2>
    <p>We teach and coach. After enrolment you get timetable and portal tools. For registration mechanics, see our <a href="<?= e($base) ?>/guides/how-to-register-for-pearson-exams">registration guide</a>.</p>

    <h2>Sources</h2>
    <ul>
        <li><a href="https://qualifications.pearson.com/" rel="noopener noreferrer" target="_blank">Pearson qualifications (official)</a></li>
        <li><a href="<?= e($base) ?>/faq">Edexcel College FAQ</a></li>
    </ul>

    <div class="seo-related">
        <a href="<?= e($base) ?>/edexcel-o-level">IGCSE pathway</a>
        <a href="<?= e($base) ?>/edexcel-a-level">IAL pathway</a>
        <a href="<?= e($base) ?>/exam-preparation">Exam preparation</a>
    </div>
    <?php seo_page_cta(); ?>
<?php
seo_page_end();
