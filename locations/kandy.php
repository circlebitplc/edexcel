<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/seo_page.php';

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$contact = seo_contact($pdoSafe);
$base = seo_base_url();
$path = 'locations/kandy';
$url = seo_absolute_url($path);
$reviewed = '11 September 2026';
$published = '2026-09-11';

seo_page_start([
    'title' => 'Physical Edexcel Classes in Kandy',
    'description' => 'The Edexcel College physical campus is at No 83 Katugatota Road, Kandy. Most classes are still live online worldwide. On-site classes depend on the subject, teacher, and timetable.',
    'canonical_path' => $path,
    'geo_region' => 'LK',
    'geo_placename' => 'Kandy',
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => $base . '/'],
        ['name' => 'Pearson Exams', 'url' => $base . '/pearson-exams'],
        ['name' => 'Sri Lanka', 'url' => $base . '/pearson-exams/sri-lanka'],
        ['name' => 'Kandy', 'url' => $url],
    ],
    'schemas' => [
        seo_article_schema([
            'headline' => 'Edexcel tuition in Kandy',
            'description' => 'Campus Pearson Edexcel tuition at Edexcel College.',
            'url' => $url,
            'datePublished' => $published,
            'dateModified' => $published,
        ]),
    ],
]);
?>
    <h1>Pearson Edexcel tuition in Kandy</h1>
    <?php seo_render_byline($reviewed, $reviewed); ?>
    <?php seo_render_answer(
        'Direct answer:',
        'The physical campus is at ' . (string)$contact['address'] . '. This is the only on-site location. Around 90% of Edexcel College classes are live online for students worldwide. A Kandy seat exists only when that subject and teacher are scheduled on campus. Official exam entries are made through authorised centres.'
    ); ?>
    <?php seo_render_disclaimer_box(); ?>
    <p><em><?= e(seo_freshness_notice($reviewed)) ?></em></p>

    <h2>Service availability in Kandy</h2>
    <p>Kandy is the only physical campus. Students in Colombo, Kurunegala, and other places do not have a local branch. They join online, or travel to this address when a class is on site. Confirm the timetable with admissions before visiting.</p>

    <h2>How the process works</h2>
    <ol>
        <li>Enquire about subjects and class times.</li>
        <li>Enrol and join the timetable that matches your Pearson Edexcel pathway.</li>
        <li>Register for exams separately with an authorised centre when you are ready.</li>
        <li>Use our teaching and past-paper practice to prepare for those papers.</li>
    </ol>

    <h2>Contact the campus</h2>
    <ul>
        <li><strong>Address:</strong> <a href="<?= e((string)$contact['maps_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$contact['address']) ?></a></li>
        <li><strong>Phone:</strong> <a href="tel:<?= e((string)$contact['phone_tel']) ?>"><?= e((string)$contact['phone']) ?></a></li>
        <li><strong>WhatsApp:</strong> <a href="https://wa.me/<?= e((string)$contact['whatsapp']) ?>" rel="noopener">Message us</a></li>
        <li><strong>Email:</strong> <a href="mailto:<?= e((string)$contact['email']) ?>"><?= e((string)$contact['email']) ?></a></li>
        <li><strong>Hours:</strong> <?= e((string)$contact['hours']) ?></li>
    </ul>

    <h2>Related pages</h2>
    <div class="seo-related">
        <a href="<?= e($base) ?>/edexcel-classes">Edexcel College classes</a>
        <a href="<?= e($base) ?>/pearson-exams/sri-lanka">Sri Lanka country guide</a>
        <a href="<?= e($base) ?>/faq">FAQ</a>
        <a href="<?= e($base) ?>/contact">Contact page</a>
    </div>
    <?php seo_page_cta('Book a conversation with admissions'); ?>
<?php
seo_page_end();
