<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$canonicalPath = 'edexcel-classes';
$url = seo_absolute_url($canonicalPath);

$courseSchema = seo_course_schema([
    'name' => 'Edexcel Classes — IGCSE & International A Level',
    'description' => 'Pearson Edexcel IGCSE and International A Level classes, live online for students worldwide, with physical classes at the Kandy campus when scheduled.',
    'url' => $url,
    'educationalLevel' => 'Secondary and post-16',
    'about' => 'Pearson Edexcel',
], $pdoSafe);

seo_page_start([
    'title' => 'Edexcel IGCSE & IAL Classes — Online Worldwide',
    'description' => 'Pearson Edexcel IGCSE and International A Level classes, live online for students worldwide, with physical classes at the Kandy campus when scheduled.',
    'canonical_path' => $canonicalPath,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Edexcel Classes', 'url' => $url],
    ],
    'schemas' => [$courseSchema],
]);
?>
    <h1>Edexcel IGCSE and IAL classes — online worldwide</h1>
    <p class="seo-lead">Pearson Edexcel IGCSE and International A Level classes. Around 90% are live online for students worldwide. Physical classes are at the Kandy campus in Sri Lanka when a subject and teacher are scheduled there.</p>
    <?php seo_render_answer(
        'Where can you join?',
        'Most students join live online, including students outside Sri Lanka and students in Colombo, Kurunegala, and the rest of the country. The only physical campus is in Kandy. There is no Colombo or Kurunegala campus. On-site attendance means travelling to Kandy for a scheduled class.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>

    <p>Families choose Edexcel College for syllabus-aligned lessons, past-paper practice, and a clear path from teaching to exam day. Enrolled students get portal access for timetables, recordings, and parent updates. For country-level exam guidance, see <a href="<?= e($base) ?>/pearson-exams/sri-lanka">Pearson exams in Sri Lanka</a> or the wider <a href="<?= e($base) ?>/pearson-exams">Pearson exams hub</a>.</p>

    <h2>How delivery works by location</h2>

    <h3>Worldwide — live online classes</h3>
    <p>This is how most students attend. Lessons are live, at the time published on the college timetable (Asia/Colombo). Students need a browser and a stable connection. Enrolment and any required lesson payment are checked before the classroom opens. Some lessons are available later as recordings in the student portal. Country notes are on the <a href="<?= e($base) ?>/pearson-exams">Pearson exams pages</a>.</p>

    <h3>Sri Lanka — physical classes in Kandy only</h3>
    <p>The only campus is in Kandy. On-site classes run when a subject and teacher are scheduled there. See the <a href="<?= e($base) ?>/locations/kandy">Kandy campus page</a> for the address. Students elsewhere in Sri Lanka use the online classes above.</p>

    <h3>Kurunegala and Colombo — online, not a local campus</h3>
    <p>There is no Edexcel College campus in Kurunegala or Colombo. Students there join the same live online classes as students elsewhere. A physical class means a scheduled lesson at the Kandy campus. Confirm the current timetable with admissions before travelling.</p>

    <h2>Who these classes are for</h2>
    <ul>
        <li>Secondary students preparing for Pearson Edexcel IGCSE</li>
        <li>Post-16 students studying Pearson Edexcel International A Level (IAL)</li>
        <li>Families in Kandy, Kurunegala, Colombo, and beyond who want structured Edexcel tuition and progress visibility</li>
        <li>Students who will attend live online, or at the Kandy campus when that class is on site</li>
    </ul>

    <h2>What students learn</h2>
    <p>Lessons follow Pearson Edexcel syllabus requirements. Students build subject knowledge, exam technique, and confidence through taught classes, homework, and past-paper practice. Subject offerings follow the live college timetable and programme list.</p>

    <h2>Qualifications we support</h2>
    <ul>
        <li><a href="<?= e($base) ?>/edexcel-o-level">Edexcel IGCSE / O Level pathway</a> — international GCSE-equivalent preparation</li>
        <li><a href="<?= e($base) ?>/edexcel-a-level">Edexcel A Level / IAL pathway</a> — International Advanced Level units</li>
        <li><a href="<?= e($base) ?>/exam-preparation">Exam preparation</a> — paper practice and exam planner support</li>
    </ul>

    <h2>Benefits of studying with Edexcel College</h2>
    <ul>
        <li>Edexcel-focused teachers and exam-oriented lessons</li>
        <li>Choose Edexcel College (Kandy), online, or hybrid to fit your city and schedule</li>
        <li>Live timetable, attendance, and parent portal access</li>
        <li>Clear admissions routes for Kandy, Kurunegala, Colombo, and online enquiries</li>
    </ul>

    <?php seo_render_featured_teachers_section('Specialist subject teachers'); ?>

    <h2>Before you enquire</h2>
    <ul>
        <li>Which subjects / unit codes you need</li>
        <li>Whether you prefer Kandy on-site, online, or hybrid</li>
        <li>Target exam series (if known)</li>
        <li>Best contact number or WhatsApp for a callback</li>
    </ul>
    <p>Class fees are confirmed by the office for your subjects — we do not publish unverified fee tables here. Exam-centre entry fees are paid to your centre, not to us as Pearson.</p>

    <?php seo_page_cta('Join Edexcel classes — Kandy, Kurunegala, Colombo or online'); ?>
    <?php seo_page_related(['classes']); ?>
<?php
seo_page_end();
