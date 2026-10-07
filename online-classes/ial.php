<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$url = seo_absolute_url('online-classes/ial/');

seo_page_start([
    'title' => 'IAL Online Classes Worldwide',
    'description' => 'Live online Pearson Edexcel International A Level classes for post-16 students worldwide. Physical IAL classes are at the Kandy campus when scheduled.',
    'canonical_path' => 'online-classes/ial/',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Online classes', 'url' => $base . '/online-classes'],
        ['name' => 'IAL', 'url' => $url],
    ],
    'schemas' => [
        seo_faq_schema([
            ['question' => 'Can an international student take IAL online?', 'answer' => 'Yes. IAL classes are part of the live online timetable. A place on that subject and a connection that can carry the live class are still required.'],
            ['question' => 'Where is the physical IAL class?', 'answer' => 'Only at the Kandy campus, and only for classes marked on site. There is no other campus.'],
        ]) ?? [],
    ],
]);
?>
    <h1>IAL online classes</h1>
    <p class="seo-lead">Pearson Edexcel International A Level classes for post-16 students. Most students attend live online. A campus seat is in Kandy only when that class is scheduled there.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>Qualification</h2>
    <p>International A Level (IAL) is the Pearson Edexcel post-16 pathway. The programme overview is on the <a href="<?= e($base) ?>/edexcel-a-level">IAL classes page</a>. Students usually enter after IGCSE or an equivalent, but admissions confirms the right starting point.</p>

    <h2>Delivery</h2>
    <ul>
        <li>Online worldwide, at the published Asia/Colombo time</li>
        <li>Physical classes at the <a href="<?= e($base) ?>/locations/kandy">Kandy campus</a> when the teacher and subject are on site</li>
    </ul>
    <p>Subject names published with teachers are listed on the <a href="<?= e($base) ?>/subjects">subjects page</a>. Confirm that the subject is offered as IAL this term. Not every named subject is automatically an IAL class.</p>

    <h2>Admission</h2>
    <p><a href="<?= e($base) ?>/admissions/enquire.php">Enquire</a> with your IAL subjects, or <a href="<?= e($base) ?>/online-classes">read how online classes work</a> first. Payment for a lesson is confirmed by the college before the portal treats that lesson as paid.</p>

    <h2>Questions</h2>
    <div class="seo-faq">
        <details>
            <summary>Can an international student take IAL online?</summary>
            <p>Yes. IAL classes are part of the live online timetable. You still need a place on that subject and a connection that can carry the live class.</p>
        </details>
        <details>
            <summary>Where is the physical IAL class?</summary>
            <p>Only at the Kandy campus, and only for classes marked on site. There is no other campus.</p>
        </details>
    </div>
    <p><a href="<?= e($base) ?>/online-classes/igcse/">IGCSE online classes</a> · <a href="<?= e($base) ?>/teachers/">Teachers</a></p>
    <?php seo_page_cta('Enquire about IAL'); ?>
<?php
seo_page_end();
