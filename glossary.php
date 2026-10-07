<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

$base = seo_base_url();
$path = 'glossary';
$url = seo_absolute_url($path);
$terms = seo_glossary_terms();
$reviewed = '11 September 2026';
$published = '2026-09-11';

seo_page_start([
    'title' => 'Pearson Edexcel Glossary',
    'description' => 'Plain-language definitions of Pearson, Edexcel, IGCSE, A Level, examination centres, private candidates, results, and related exam terms.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Resources', 'url' => $base . '/resources'],
        ['name' => 'Glossary', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'Pearson Edexcel glossary',
            'description' => 'Educational glossary for families preparing for Pearson Edexcel international exams.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>Pearson Edexcel glossary</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <p class="seo-lead">Short definitions for terms students and parents meet when preparing for Pearson Edexcel international exams.</p>
    <?php seo_render_disclaimer_box(); ?>

    <dl class="seo-glossary">
        <?php foreach ($terms as $term): ?>
            <dt id="<?= e((string)$term['slug']) ?>"><?= e((string)$term['term']) ?></dt>
            <dd>
                <?= e((string)$term['definition']) ?>
                <?php if (!empty($term['see'])): ?>
                    <?php
                    $see = (string)$term['see'];
                    $href = str_starts_with($see, 'http') ? $see : ($base . $see);
                    $ext = str_starts_with($see, 'http');
                    ?>
                    <br><a href="<?= e($href) ?>"<?= $ext ? ' rel="noopener noreferrer" target="_blank"' : '' ?>>Related reading</a>
                <?php endif; ?>
            </dd>
        <?php endforeach; ?>
    </dl>

    <h2>Related resources</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/faq">FAQ knowledge base</a>
        <a href="<?= e($base) ?>/guides/how-pearson-edexcel-exams-work">How exams work</a>
        <a href="<?= e($base) ?>/pearson-exams">Pearson exam services</a>
    </div>
    <?php seo_page_cta('Ask admissions about your pathway'); ?>
<?php
seo_page_end();
