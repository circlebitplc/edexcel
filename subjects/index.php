<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$url = seo_absolute_url('subjects');
$pages = seo_public_subject_pages();

seo_page_start([
    'title' => 'Edexcel Subjects — Online Worldwide',
    'description' => 'Subjects named on Edexcel College public teacher profiles. Classes are live online worldwide. Physical classes are at the Kandy campus when scheduled. Confirm IGCSE or IAL with admissions.',
    'canonical_path' => 'subjects',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Subjects', 'url' => $url],
    ],
]);
?>
    <h1>Subjects</h1>
    <p class="seo-lead">These are the subjects named on the college’s public teacher profiles. A subject page does not by itself mean every level is running this week. Admissions confirms IGCSE or IAL against the timetable.</p>
    <?php seo_render_disclaimer_box(); ?>
    <ul>
        <?php foreach ($pages as $page): ?>
            <li>
                <a href="<?= e($base . '/subjects/' . $page['slug'] . '/') ?>"><?= e($page['name']) ?></a>
                <?php
                $names = array_map(static fn(array $t): string => (string)$t['name'], $page['teachers']);
                echo ' — ' . e(implode(', ', $names));
                ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p>Delivery for each subject: <a href="<?= e($base) ?>/online-classes">online worldwide</a>, and <a href="<?= e($base) ?>/locations/kandy">physical classes in Kandy</a> when scheduled. Programmes: <a href="<?= e($base) ?>/edexcel-o-level">IGCSE</a> and <a href="<?= e($base) ?>/edexcel-a-level">IAL</a>.</p>
<?php
seo_page_end();
