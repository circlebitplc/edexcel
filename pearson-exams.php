<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$url = seo_absolute_url('pearson-exams');

$service = seo_service_schema([
    'name' => 'Pearson Edexcel examination tuition and preparation',
    'description' => 'How Pearson Edexcel exams relate to Edexcel College classes. Teaching is mostly live online worldwide. The college is not an exam centre. The campus is in Kandy.',
    'url' => $url,
    'area' => 'International',
], $pdoSafe);

seo_page_start([
    'title' => 'Pearson Edexcel Classes — Online Worldwide',
    'description' => 'How Pearson Edexcel exams relate to Edexcel College classes. Teaching is mostly live online worldwide. The college is not an exam centre. The campus is in Kandy.',
    'canonical_path' => 'pearson-exams',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Pearson exams', 'url' => $url],
    ],
    'schemas' => [$service],
]);
?>
    <h1>Pearson Edexcel exam services</h1>
    <p class="seo-lead">Academic tuition and exam preparation for Pearson Edexcel International GCSE (IGCSE) and International A Level (IAL) — delivered by an independent college, not by Pearson.</p>
    <?php seo_render_disclaimer_box(); ?>

    <h2>What we offer</h2>
    <ul>
        <li>Syllabus-aligned teaching for Pearson Edexcel IGCSE and IAL</li>
        <li>Exam technique and past-paper practice</li>
        <li>On-campus classes in Kandy, Sri Lanka</li>
        <li>Online / hybrid options for international students where timetable capacity allows</li>
        <li>Student and parent portal tools after enrolment</li>
    </ul>

    <h2>Qualifications</h2>
    <div class="seo-grid">
        <a href="<?= e($base) ?>/edexcel-o-level">IGCSE / O Level pathway</a>
        <a href="<?= e($base) ?>/edexcel-a-level">A Level / IAL pathway</a>
        <a href="<?= e($base) ?>/exam-preparation">Exam preparation</a>
        <a href="<?= e($base) ?>/edexcel-classes">All Edexcel classes</a>
    </div>

    <h2>Country guides</h2>
    <p>These pages explain how students in each market typically use our tuition. Only Sri Lanka has our physical campus; other pages describe online support and local exam-centre realities — not branch offices.</p>
    <div class="seo-grid">
        <?php foreach (seo_country_pages() as $slug => $page): ?>
            <a href="<?= e($base) ?>/pearson-exams/<?= e($slug) ?>"><?= e((string)$page['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <p>We also hear from families across the wider Gulf. See the <a href="<?= e($base) ?>/pearson-exams/gulf">Saudi Arabia, Bahrain &amp; Kuwait guide</a>, or <a href="<?= e($base) ?>/admissions/enquire.php">send a course enquiry</a> with your subjects and time zone.</p>

    <h2>Campus city</h2>
    <p>Our physical campus is in <a href="<?= e($base) ?>/locations/kandy">Kandy, Sri Lanka</a>.</p>

    <h2>Guides &amp; knowledge base</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/resources">Student resources hub</a>
        <a href="<?= e($base) ?>/guides/how-pearson-edexcel-exams-work">How Pearson Edexcel exams work</a>
        <a href="<?= e($base) ?>/guides/how-to-register-for-pearson-exams">How to register for a Pearson exam</a>
        <a href="<?= e($base) ?>/guides/what-to-bring-to-a-pearson-exam">What to bring on exam day</a>
        <a href="<?= e($base) ?>/guides/private-candidates">Private candidates explained</a>
        <a href="<?= e($base) ?>/guides/results-and-certificates">Results and certificates</a>
        <a href="<?= e($base) ?>/glossary">Glossary</a>
        <a href="<?= e($base) ?>/faq">FAQ knowledge base</a>
    </div>

    <h2>How families find us</h2>
    <p>Families typically search for Pearson Edexcel tuition, IGCSE classes, or International A Level preparation before finding this page. We match that intent with clear programme pages and honest country guidance — not doorway spam.</p>

    <?php seo_page_cta('Talk to admissions'); ?>
    <?php seo_page_related(['hub']); ?>
<?php
seo_page_end();
