<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$path = 'guides/what-to-bring-to-a-pearson-exam';
$url = seo_absolute_url($path);
$published = '2026-09-11';
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'What to Bring to a Pearson Exam',
    'description' => 'Practical exam-day checklist for Pearson Edexcel candidates: ID, stationery, timing, and centre rules. Independent guidance from Edexcel College.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Guides', 'url' => $base . '/pearson-exams'],
        ['name' => 'Exam day checklist', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'What to bring to a Pearson exam',
            'description' => 'Exam-day checklist for Pearson Edexcel candidates.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>What to bring to a Pearson exam</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Direct answer:',
        'Bring valid photo ID accepted by your centre, your statement of entry if issued, and only approved stationery. Leave phones and notes outside. Always follow your centre’s published rules.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>

    <h2>Usually required</h2>
    <ul>
        <li>Valid photo ID accepted by the centre</li>
        <li>Statement of entry / candidate details if provided</li>
        <li>Transparent pencil case with approved stationery</li>
        <li>Calculator only if the paper allows it (check the specification)</li>
    </ul>

    <h2>Usually restricted</h2>
    <ul>
        <li>Mobile phones, smartwatches, and notes</li>
        <li>Unauthorised calculators or formula sheets</li>
        <li>Food and drink rules vary — ask the centre</li>
    </ul>

    <h2>Timing and conduct</h2>
    <p>Arrive early. Know the paper code and seat instructions. Invigilators’ directions override informal advice.</p>

    <h2>Sources</h2>
    <ul>
        <li>Your examination centre’s candidate instructions</li>
        <li><a href="https://qualifications.pearson.com/" rel="noopener noreferrer" target="_blank">Pearson qualifications (official)</a></li>
    </ul>

    <?php seo_page_cta('Book exam-focused tuition'); ?>
<?php
seo_page_end();
