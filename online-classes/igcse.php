<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$url = seo_absolute_url('online-classes/igcse/');

seo_page_start([
    'title' => 'IGCSE Online Classes Worldwide',
    'description' => 'Live online Pearson Edexcel IGCSE classes for secondary students worldwide. Confirm subjects on the timetable. Physical IGCSE classes are at the Kandy campus when scheduled.',
    'canonical_path' => 'online-classes/igcse/',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Online classes', 'url' => $base . '/online-classes'],
        ['name' => 'IGCSE', 'url' => $url],
    ],
    'schemas' => [
        seo_faq_schema([
            ['question' => 'Is IGCSE available online?', 'answer' => 'Yes. IGCSE teaching at Edexcel College is part of the live online timetable. Around 90% of the college’s classes are online.'],
            ['question' => 'Can I sit the exam at Edexcel College?', 'answer' => 'No. The college prepares students. Official papers are sat at a Pearson-authorised centre.'],
        ]) ?? [],
    ],
]);
?>
    <h1>IGCSE online classes</h1>
    <p class="seo-lead">Pearson Edexcel International GCSE classes for secondary students. At Edexcel College these classes are normally live online, including for students outside Sri Lanka.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>Qualification</h2>
    <p>This is the Pearson Edexcel International GCSE pathway. Many families search for it as “O Level”. It is not a different examination board. The full programme note is on the <a href="<?= e($base) ?>/edexcel-o-level">IGCSE classes page</a>.</p>

    <h2>Delivery</h2>
    <ul>
        <li>Online: worldwide, at the Asia/Colombo time on the timetable</li>
        <li>Physical: Kandy campus only, and only when that subject and teacher are scheduled on site</li>
    </ul>
    <p>See <a href="<?= e($base) ?>/subjects">subjects</a> named on public teacher profiles, then ask admissions which of them are running as IGCSE this term. This page does not promise every subject at IGCSE.</p>

    <h2>Teachers and timetable</h2>
    <p><a href="<?= e($base) ?>/teachers/">Teacher profiles</a> list subjects. The homepage timetable shows upcoming lessons. Match the subject and the delivery mode before you enrol.</p>

    <h2>Admission</h2>
    <p><a href="<?= e($base) ?>/admissions/enquire.php">Send an enquiry</a> with the IGCSE subjects you need, or <a href="<?= e($base) ?>/student/register.php">register</a>. Exam entry with Pearson is separate from joining a class.</p>

    <h2>Questions</h2>
    <div class="seo-faq">
        <details>
            <summary>Is IGCSE available online?</summary>
            <p>Yes. IGCSE teaching at Edexcel College is part of the live online timetable. Around 90% of the college’s classes are online.</p>
        </details>
        <details>
            <summary>Can I sit the exam at Edexcel College?</summary>
            <p>No. The college prepares students. Official papers are sat at a Pearson-authorised centre.</p>
        </details>
    </div>
    <p><a href="<?= e($base) ?>/online-classes/">All online classes</a> · <a href="<?= e($base) ?>/online-classes/ial/">IAL online classes</a></p>
    <?php seo_page_cta('Enquire about IGCSE'); ?>
<?php
seo_page_end();
