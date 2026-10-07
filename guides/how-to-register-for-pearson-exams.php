<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$path = 'guides/how-to-register-for-pearson-exams';
$url = seo_absolute_url($path);
$published = '2026-09-11';
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'How to Register for a Pearson Exam',
    'description' => 'Typical steps to register for Pearson Edexcel international exams through an authorised centre, and how tuition at Edexcel College supports preparation.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Guides', 'url' => $base . '/pearson-exams'],
        ['name' => 'How to register', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'How to register for a Pearson exam',
            'description' => 'Checklist for registering Pearson Edexcel international exams via authorised centres.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>How to register for a Pearson exam</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Direct answer:',
        'Register through Pearson and an authorised examination centre (or school centre). Confirm the exact specification, meet entry deadlines, and align tuition with the papers you entered. Edexcel College prepares you academically; we do not process Pearson entries ourselves.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>
    <p><em><?= e(seo_freshness_notice($reviewed)) ?></em></p>

    <h2>Step-by-step</h2>
    <ol>
        <li><strong>Confirm the specification</strong> — IGCSE or IAL units, and subject codes your centre offers.</li>
        <li><strong>Choose an authorised centre</strong> — school or private centre that accepts your candidature type.</li>
        <li><strong>Note entry deadlines</strong> — late entries are costly or closed.</li>
        <li><strong>Align tuition</strong> — share confirmed subjects with admissions so teaching matches the papers.</li>
        <li><strong>Prepare documents</strong> — ID and centre forms; see the <a href="<?= e($base) ?>/guides/what-to-bring-to-a-pearson-exam">exam-day checklist</a>.</li>
    </ol>

    <h2>What we cannot do</h2>
    <p>We cannot enter you as Pearson, issue statements of results, or guarantee a specific centre place.</p>

    <h2>Sources</h2>
    <ul>
        <li><a href="https://qualifications.pearson.com/" rel="noopener noreferrer" target="_blank">Pearson qualifications (official)</a></li>
        <li><a href="<?= e($base) ?>/contact">Contact Edexcel College</a></li>
    </ul>

    <?php seo_page_cta('Get help planning your subjects'); ?>
<?php
seo_page_end();
