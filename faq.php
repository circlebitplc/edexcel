<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$url = seo_absolute_url('faq');
$categories = seo_faq_categories($pdoSafe);
$faqs = seo_public_faqs($pdoSafe);
$faqSchema = seo_faq_schema($faqs);
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'Edexcel College FAQ — Online Classes Worldwide',
    'description' => 'Answers about Edexcel College online IGCSE and IAL classes for students worldwide, the Kandy campus, joining from outside Sri Lanka, and how live lessons work.',
    'canonical_path' => 'faq',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Resources', 'url' => $base . '/resources'],
        ['name' => 'FAQ', 'url' => $url],
    ],
    'schemas' => array_values(array_filter([$faqSchema])),
]);
?>
    <h1>Frequently asked questions</h1>
    <p class="seo-lead">A structured knowledge base for families researching Pearson Edexcel tuition, registration, and exam logistics.</p>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Most asked:',
        seo_public_positioning() . ' Official exam registration is with Pearson and authorised centres — not with us as Pearson.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>
    <p><em><?= e(seo_freshness_notice($reviewed)) ?></em></p>

    <p class="seo-related" aria-label="Jump to FAQ topics">
        <?php foreach ($categories as $cat): ?>
            <a href="#faq-<?= e((string)$cat['id']) ?>"><?= e((string)$cat['label']) ?></a>
        <?php endforeach; ?>
    </p>

    <?php foreach ($categories as $cat): ?>
        <h2 id="faq-<?= e((string)$cat['id']) ?>"><?= e((string)$cat['label']) ?></h2>
        <div class="seo-faq">
            <?php foreach ($cat['items'] as $faq): ?>
                <details>
                    <summary><?= e((string)$faq['question']) ?></summary>
                    <p><?= e((string)$faq['answer']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <h2>Next steps</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/glossary">Glossary</a>
        <a href="<?= e($base) ?>/resources">Resources hub</a>
        <a href="<?= e($base) ?>/guides/how-to-register-for-pearson-exams">Registration guide</a>
        <a href="<?= e($base) ?>/contact">Contact the college</a>
    </div>
    <?php seo_page_cta('Still have a question?'); ?>
<?php
seo_page_end();
