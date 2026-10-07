<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$path = 'guides/private-candidates';
$url = seo_absolute_url($path);
$published = '2026-09-11';
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'Pearson Edexcel Private Candidates Explained',
    'description' => 'What it means to sit Pearson Edexcel exams as a private candidate, how centres work, and how tuition at Edexcel College fits in.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Resources', 'url' => $base . '/resources'],
        ['name' => 'Private candidates', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'Pearson Edexcel private candidates',
            'description' => 'Plain explanation of private candidacy for Pearson Edexcel international exams.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>Private candidates explained</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Direct answer:',
        'A private candidate is entered for Pearson Edexcel exams through a centre that accepts private entries (often the British Council or another authorised centre), rather than through a day school. Tuition colleges prepare you; they do not automatically become your exam centre.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>
    <p><em><?= e(seo_freshness_notice($reviewed)) ?></em></p>

    <h2>Who this is for</h2>
    <p>Students who study independently, switch boards, retake units, or attend a tuition college without school-centre entry often need a private-candidate route.</p>

    <h2>Typical steps</h2>
    <ol>
        <li>Confirm subjects and specifications you need.</li>
        <li>Find a centre that accepts private candidates for those papers in your country.</li>
        <li>Register and pay the centre’s exam fees within their windows.</li>
        <li>Keep your statement of entry and follow venue instructions.</li>
        <li>Align weekly tuition with the exact papers you entered.</li>
    </ol>

    <h2>Sri Lanka note</h2>
    <p>Many private candidates in Sri Lanka use British Council school-exam registration for Pearson Edexcel. Always verify current windows, venues, and fees on the British Council Sri Lanka site or with another Pearson-authorised centre — we do not republish fee tables here.</p>

    <h2>Our role</h2>
    <p>Edexcel College provides Pearson Edexcel–focused teaching (campus in Kandy; online/hybrid where offered). We help you prepare for the papers you register elsewhere.</p>

    <h2>Useful links</h2>
    <ul>
        <li><a href="<?= e($base) ?>/guides/how-to-register-for-pearson-exams">How to register for a Pearson exam</a></li>
        <li><a href="https://www.britishcouncil.lk/exam/school-exams/register/private-edexcel" rel="noopener noreferrer" target="_blank">British Council Sri Lanka — private Edexcel registration</a></li>
        <li><a href="https://qualifications.pearson.com/" rel="noopener noreferrer" target="_blank">Pearson qualifications</a></li>
        <li><a href="<?= e($base) ?>/glossary#private-candidate">Glossary: private candidate</a></li>
    </ul>

    <?php seo_page_cta('Match tuition to your entry plan'); ?>
<?php
seo_page_end();
