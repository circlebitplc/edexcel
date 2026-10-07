<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

$base = seo_base_url();
$path = 'resources';
$url = seo_absolute_url($path);
$reviewed = '11 September 2026';

seo_page_start([
    'title' => 'Pearson Edexcel Student Resources',
    'description' => 'Guides, FAQ, glossary, country pages, and campus information for families preparing for Pearson Edexcel IGCSE and International A Level exams.',
    'canonical_path' => $path,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Resources', 'url' => $url],
    ],
]);
?>
    <h1>Student resources</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <p class="seo-lead">A topical hub for examinations, qualifications, countries we genuinely support, and practical student guides.</p>
    <?php seo_render_disclaimer_box(); ?>

    <form class="seo-card" method="get" action="<?= e($base) ?>/resources/search" role="search">
        <label for="q"><strong>Search resources</strong></label>
        <p style="margin:.35rem 0 .75rem;color:var(--seo-muted,#555)">Find exams, qualifications, countries, FAQs, and guides on this site.</p>
        <div class="seo-cta" style="align-items:center">
            <input id="q" name="q" type="search" maxlength="120" placeholder="e.g. private candidate, Kandy, results" required
                   style="flex:1;min-width:12rem;padding:.65rem .8rem;border:1px solid #ccc;border-radius:8px;font:inherit">
            <button class="seo-btn seo-btn-primary" type="submit">Search</button>
        </div>
    </form>

    <h2>Examinations</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/pearson-exams">Pearson exam services hub</a>
        <a href="<?= e($base) ?>/guides/how-pearson-edexcel-exams-work">How Pearson Edexcel exams work</a>
        <a href="<?= e($base) ?>/guides/how-to-register-for-pearson-exams">How to register</a>
        <a href="<?= e($base) ?>/guides/what-to-bring-to-a-pearson-exam">Exam day checklist</a>
        <a href="<?= e($base) ?>/guides/results-and-certificates">Results and certificates</a>
        <a href="<?= e($base) ?>/guides/private-candidates">Private candidates</a>
        <a href="<?= e($base) ?>/guides/igcse-vs-edexcel-o-level">IGCSE vs O Level naming</a>
        <a href="<?= e($base) ?>/exam-preparation">Exam preparation</a>
    </div>

    <h2>Qualifications</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/edexcel-o-level">International GCSE (IGCSE)</a>
        <a href="<?= e($base) ?>/edexcel-a-level">International A Level (IAL)</a>
        <a href="<?= e($base) ?>/edexcel-classes">Edexcel College classes</a>
        <a href="<?= e($base) ?>/glossary">Glossary of terms</a>
    </div>

    <h2>Countries we discuss</h2>
    <p>Only markets where we genuinely offer campus or online/hybrid tuition support. No fake branch pages.</p>
    <div class="seo-related">
        <?php foreach (seo_country_pages() as $slug => $page): ?>
            <a href="<?= e($base) ?>/pearson-exams/<?= e((string)$slug) ?>"><?= e((string)$page['name']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e($base) ?>/locations/kandy">Kandy (campus city)</a>
    </div>

    <h2>Help &amp; trust</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/faq">FAQ knowledge base</a>
        <a href="<?= e($base) ?>/about">About the college</a>
        <a href="<?= e($base) ?>/contact">Contact</a>
        <a href="<?= e($base) ?>/admissions/enquire.php">Course enquiry</a>
    </div>
    <?php seo_page_cta('Need a personal recommendation?'); ?>
<?php
seo_page_end();
