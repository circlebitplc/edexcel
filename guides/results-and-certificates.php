<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$path = 'guides/results-and-certificates';
$url = seo_absolute_url($path);
$published = '2026-09-11';
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'Pearson Edexcel Results and Certificates',
    'description' => 'How Pearson Edexcel results and certificates are issued through examination centres, and what an independent tuition college can and cannot do.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Resources', 'url' => $base . '/resources'],
        ['name' => 'Results and certificates', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'Pearson Edexcel results and certificates',
            'description' => 'How results and certificates work for international candidates.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>Results and certificates</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Direct answer:',
        'Pearson Edexcel results and certificates are issued through the examination centre that entered you — not through an independent tuition college. Check your centre’s results instructions for each series.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>
    <p><em><?= e(seo_freshness_notice($reviewed)) ?></em></p>

    <h2>What usually happens</h2>
    <ol>
        <li>You sit papers at an authorised centre.</li>
        <li>After the series, the centre publishes access details for results (portal, letter, or collection — depends on the centre).</li>
        <li>Certificates follow later according to Pearson/centre processes.</li>
    </ol>

    <h2>What we cannot do</h2>
    <ul>
        <li>Publish official board results</li>
        <li>Reprint or certify Pearson certificates</li>
        <li>Override a centre’s results or remark procedures</li>
    </ul>

    <h2>How we still help</h2>
    <p>After results day, families often ask about next units, resits, or A Level progression. Admissions can discuss tuition options once you share which papers you entered.</p>

    <h2>Authoritative sources</h2>
    <ul>
        <li><a href="https://qualifications.pearson.com/" rel="noopener noreferrer" target="_blank">Pearson qualifications</a></li>
        <li><a href="https://www.britishcouncil.lk/exam/school-exams" rel="noopener noreferrer" target="_blank">British Council Sri Lanka — school exams</a> (for candidates entered there)</li>
        <li><a href="<?= e($base) ?>/glossary#results">Glossary: results</a></li>
    </ul>

    <?php seo_page_cta('Plan your next units with us'); ?>
<?php
seo_page_end();
