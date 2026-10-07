<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

$base = seo_base_url();
$url = seo_absolute_url('online-classes');

seo_page_start([
    'title' => 'Online Edexcel Classes Worldwide',
    'description' => 'Live online Pearson Edexcel IGCSE and International A Level classes for students worldwide. Around 90% of Edexcel College classes are online. Physical classes are at the Kandy campus when scheduled.',
    'canonical_path' => 'online-classes',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Online classes', 'url' => $url],
    ],
    'schemas' => [
        seo_faq_schema([
            ['question' => 'Does Edexcel College offer online classes?', 'answer' => 'Yes. Around 90% of classes are delivered live online for students worldwide.'],
            ['question' => 'Can students outside Sri Lanka join?', 'answer' => 'Yes, subject to the course, the published timetable, admission, and a reliable connection.'],
            ['question' => 'Are the classes live?', 'answer' => 'Yes. Teaching is a live class. Some lessons also have a recording afterwards.'],
        ]) ?? [],
    ],
]);
?>
    <h1>Online Edexcel classes worldwide</h1>
    <p class="seo-lead">Learn Pearson Edexcel IGCSE and International A Level subjects through live online classes from anywhere in the world. Physical classes are also available at the Kandy campus in Sri Lanka when a subject and teacher are scheduled there.</p>
    <?php seo_render_answer(
        'In short:',
        'Around 90% of Edexcel College classes are live online. The college is not a Pearson exam centre. Official exam entry stays with an authorised centre in the student’s own country.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>

    <h2>What you can study</h2>
    <ul>
        <li><a href="<?= e($base) ?>/online-classes/igcse/">IGCSE online classes</a> for secondary students</li>
        <li><a href="<?= e($base) ?>/online-classes/ial/">IAL online classes</a> for post-16 students</li>
        <li><a href="<?= e($base) ?>/subjects">Subjects</a> currently named on public teacher profiles</li>
        <li><a href="<?= e($base) ?>/teachers/">Teachers</a></li>
    </ul>

    <h2>How a live class works</h2>
    <ol>
        <li>Choose IGCSE or IAL and the subjects you need.</li>
        <li><a href="<?= e($base) ?>/admissions/enquire.php">Enquire</a> or <a href="<?= e($base) ?>/student/register.php">register</a>.</li>
        <li>Join the lesson at the time on the college timetable. Times are Asia/Colombo (UTC+5:30). The college does not move a class into every local time zone.</li>
        <li>Sign in to the student portal. The classroom opens after the server checks enrolment and any required lesson payment.</li>
        <li>Use a current browser and a stable connection. Camera and microphone are part of the live room when the teacher enables them.</li>
    </ol>
    <p>Some lessons are available later as recordings in the portal. A recording does not replace the timetable.</p>

    <h2>Payment</h2>
    <p>Online lesson fees can be paid by card through OnePay or by bank transfer with a slip. Paying in person is at the Kandy campus. The lesson is marked paid only after the college confirms the payment. The amount is the class fee on the timetable, not a separate price invented on this page.</p>

    <h2>Physical classes</h2>
    <p>The only campus is in <a href="<?= e($base) ?>/locations/kandy">Kandy</a>. There is no campus in Colombo, Kurunegala, or outside Sri Lanka. Students in those places use these online classes, or travel to Kandy when a class is on site.</p>

    <h2>Questions</h2>
    <div class="seo-faq">
        <details>
            <summary>Does Edexcel College offer online classes?</summary>
            <p>Yes. Around 90% of classes are delivered live online for students worldwide.</p>
        </details>
        <details>
            <summary>Can students outside Sri Lanka join?</summary>
            <p>Yes, subject to the course, the published timetable, admission, and a reliable connection.</p>
        </details>
        <details>
            <summary>Are the classes live?</summary>
            <p>Yes. Teaching is a live class. Some lessons also have a recording afterwards.</p>
        </details>
    </div>

    <?php seo_page_cta('Enquire about an online class'); ?>
<?php
seo_page_end();
