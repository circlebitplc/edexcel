<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$base = seo_base_url();
$url = seo_absolute_url('about');
$c = college_contact($pdoSafe);

seo_page_start([
    'title' => 'About Edexcel College — Online IGCSE & IAL Worldwide',
    'description' => 'Edexcel College teaches Pearson Edexcel IGCSE and International A Level. Around 90% of classes are live online worldwide. The physical campus is in Kandy, Sri Lanka.',
    'canonical_path' => 'about',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'About', 'url' => $url],
    ],
    'schemas' => [],
]);
?>
    <h1>About <?= e((string)$c['name']) ?></h1>
    <?php seo_render_answer(
        'In short:',
        seo_public_positioning() . ' We are not Pearson Education Ltd.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>

    <h2>Who we are</h2>
    <p><?= e((string)$c['name']) ?> teaches Pearson Edexcel IGCSE and International A Level. Around 90% of classes are live online, so students join from outside Sri Lanka as well as from inside it. The physical campus is at <?= e(preg_replace('/\s+/', ' ', (string)$c['address']) ?? '') ?>. Enrolled students and parents use the portal for timetables, live classes, and lesson records.</p>

    <h2>What we offer</h2>
    <ul>
        <li>Pearson Edexcel IGCSE (often searched as O Level) tuition</li>
        <li>Pearson Edexcel International A Level (IAL) tuition</li>
        <li>Exam preparation and past-paper practice</li>
        <li>Live online classes as the main way to attend, for students worldwide</li>
        <li>Physical classes at the Kandy campus when a subject and teacher are scheduled there</li>
    </ul>

    <h2>What makes our service different</h2>
    <ul>
        <li>Clear separation between <strong>tuition</strong> (us) and <strong>official exam entry</strong> (Pearson / authorised centres)</li>
        <li>Online worldwide as the primary model, with one physical campus in Kandy rather than branches in every city</li>
        <li>Exam-oriented teaching with past-paper practice rather than vague “exam booking” claims</li>
        <li>Published guides, FAQ, and glossary so families can self-serve before enquiring</li>
    </ul>

    <h2>Where we serve students</h2>
    <p>Online classes are open to students worldwide, including Sri Lankan students who prefer to study from home. The only physical campus is in <a href="<?= e($base) ?>/locations/kandy">Kandy</a>. There is no campus in Colombo, Kurunegala, or outside Sri Lanka. Students in those places join online, or travel to Kandy when a class is on site. Class times are published in Asia/Colombo.</p>
    <p><a href="<?= e($base) ?>/pearson-exams">See Pearson exam services by country</a> · <a href="<?= e($base) ?>/resources">Student resources</a>.</p>

    <h2>How to contact us</h2>
    <ul>
        <li>Email: <a href="mailto:<?= e((string)$c['email']) ?>"><?= e((string)$c['email']) ?></a></li>
        <li>Phone: <a href="tel:<?= e((string)$c['phone_tel']) ?>"><?= e((string)$c['phone']) ?></a></li>
        <li>WhatsApp: <a href="https://wa.me/<?= e((string)$c['whatsapp']) ?>" rel="noopener">+<?= e((string)$c['whatsapp']) ?></a></li>
        <li>Hours: <?= e((string)$c['hours']) ?></li>
        <li>Website: <a href="<?= e($base) ?>/">edexcel.college</a></li>
    </ul>

    <h2>Policies</h2>
    <p>
        <a href="<?= e($base) ?>/terms">Terms</a> ·
        <a href="<?= e($base) ?>/privacy-policy">Privacy</a> ·
        <a href="<?= e($base) ?>/refund-policy">Refunds</a> ·
        <a href="<?= e($base) ?>/faq">FAQ</a>
    </p>

    <?php seo_page_cta('Enquire or enrol'); ?>
<?php
seo_page_end();
