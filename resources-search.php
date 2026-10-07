<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

$base = seo_base_url();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 120);
} else {
    $q = substr($q, 0, 120);
}
$needle = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
$results = [];

if ($needle !== '') {
    foreach (seo_resource_index() as $row) {
        $hayRaw = $row['title'] . ' ' . $row['blurb'] . ' ' . $row['keywords'];
        $hay = function_exists('mb_strtolower') ? mb_strtolower($hayRaw) : strtolower($hayRaw);
        $found = function_exists('mb_strpos') ? (mb_strpos($hay, $needle) !== false) : (strpos($hay, $needle) !== false);
        if ($found) {
            $results[] = $row;
        }
    }
}

seo_page_start([
    'title' => 'Resource search results',
    'description' => 'Internal search results for Edexcel College resources.',
    'canonical_path' => 'resources/search',
    'robots' => 'noindex,follow',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Resources', 'url' => $base . '/resources'],
        ['name' => 'Search', 'url' => $base . '/resources/search'],
    ],
]);
?>
    <h1>Search resources</h1>
    <form class="seo-card" method="get" action="<?= e($base) ?>/resources/search" role="search">
        <label for="q"><strong>Query</strong></label>
        <div class="seo-cta" style="align-items:center;margin-top:.5rem">
            <input id="q" name="q" type="search" maxlength="120" value="<?= e($q) ?>" required
                   style="flex:1;min-width:12rem;padding:.65rem .8rem;border:1px solid #ccc;border-radius:8px;font:inherit">
            <button class="seo-btn seo-btn-primary" type="submit">Search</button>
        </div>
    </form>

    <?php if ($q === ''): ?>
        <p>Enter a term such as <em>results</em>, <em>Italy</em>, or <em>IGCSE</em>.</p>
    <?php elseif ($results === []): ?>
        <p>No matches for “<?= e($q) ?>”. Try the <a href="<?= e($base) ?>/faq">FAQ</a> or <a href="<?= e($base) ?>/glossary">glossary</a>.</p>
    <?php else: ?>
        <p><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($q) ?>”.</p>
        <ul>
            <?php foreach ($results as $row): ?>
                <li style="margin:.75rem 0">
                    <a href="<?= e((string)$row['url']) ?>"><strong><?= e((string)$row['title']) ?></strong></a>
                    <br><?= e((string)$row['blurb']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="seo-related">
        <a href="<?= e($base) ?>/resources">Back to resources</a>
        <a href="<?= e($base) ?>/contact">Contact</a>
    </div>
<?php
seo_page_end();
