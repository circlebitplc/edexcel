<?php
declare(strict_types=1);

/**
 * Lightweight public marketing page chrome for SEO landings.
 */

if (!defined('DB_ALLOW_FAILURE')) {
    define('DB_ALLOW_FAILURE', true);
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/college_contact.php';
require_once __DIR__ . '/seo.php';

/**
 * @param array{
 *   title:string,
 *   description:string,
 *   canonical_path:string,
 *   breadcrumbs?:list<array{name:string,url:string}>,
 *   schemas?:list<array<string,mixed>>,
 *   robots?:string,
 *   geo_region?:string,
 *   geo_placename?:string,
 *   og_locale?:string
 * } $opts
 */
function seo_page_start(array $opts): void
{
    global $pdo;
    $contact = college_contact($pdo instanceof PDO ? $pdo : null);
    $canonicalPath = (string)$opts['canonical_path'];
    $canonical = seo_absolute_url($canonicalPath);
    $title = (string)$opts['title'];
    $description = (string)$opts['description'];
    $robots = (string)($opts['robots'] ?? 'index, follow');

    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: ' . $robots);

    $schemas = $opts['schemas'] ?? [];
    $breadcrumbs = $opts['breadcrumbs'] ?? [
        ['name' => 'Home', 'url' => seo_absolute_url('/')],
        ['name' => $title, 'url' => $canonical],
    ];
    $schemas[] = seo_organization_schema($pdo instanceof PDO ? $pdo : null);
    $schemas[] = seo_website_schema($pdo instanceof PDO ? $pdo : null);
    $schemas[] = seo_breadcrumb_schema($breadcrumbs);

    $base = seo_base_url();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php seo_render_meta([
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'robots' => $robots,
        'geo_region' => (string)($opts['geo_region'] ?? ''),
        'geo_placename' => (string)($opts['geo_placename'] ?? ''),
        'og_locale' => (string)($opts['og_locale'] ?? 'en_LK'),
    ]); ?>
    <style>
        :root {
            --seo-bg: #f7f4ef;
            --seo-ink: #1c2430;
            --seo-muted: #5b6573;
            --seo-accent: #0b4f8a;
            --seo-line: #d9e0ea;
            --seo-card: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            color: var(--seo-ink);
            background:
                radial-gradient(1200px 500px at 10% -10%, #dceaf8 0%, transparent 55%),
                radial-gradient(900px 400px at 100% 0%, #f3e7d4 0%, transparent 50%),
                var(--seo-bg);
            line-height: 1.6;
        }
        a { color: var(--seo-accent); }
        .seo-wrap { max-width: 46rem; margin: 0 auto; padding: 1.25rem 1.25rem 3rem; }
        .seo-nav {
            display: flex; flex-wrap: wrap; gap: .5rem .9rem;
            font-size: .92rem; margin-bottom: 1.25rem;
        }
        .seo-nav a { text-decoration: none; font-weight: 600; }
        .seo-nav a:hover { text-decoration: underline; }
        .seo-crumbs { font-size: .85rem; color: var(--seo-muted); margin: 0 0 1rem; }
        .seo-crumbs a { color: inherit; text-decoration: none; }
        .seo-crumbs a:hover { text-decoration: underline; }
        h1 { font-size: clamp(1.7rem, 3vw, 2.15rem); line-height: 1.2; margin: 0 0 .75rem; }
        h2 { font-size: 1.2rem; margin: 1.75rem 0 .55rem; }
        h3 { font-size: 1.05rem; margin: 1.2rem 0 .4rem; }
        p, li { color: var(--seo-ink); }
        .seo-lead { font-size: 1.08rem; color: var(--seo-muted); margin: 0 0 1.25rem; }
        .seo-card {
            background: var(--seo-card);
            border: 1px solid var(--seo-line);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            margin: 1rem 0;
        }
        .seo-cta {
            display: flex; flex-wrap: wrap; gap: .65rem;
            margin: 1.35rem 0 0;
        }
        .seo-btn {
            display: inline-block; text-decoration: none; font-weight: 700;
            padding: .7rem 1rem; border-radius: 10px; border: 1px solid transparent;
        }
        .seo-btn-primary { background: var(--seo-accent); color: #fff; }
        .seo-btn-ghost { background: transparent; color: var(--seo-accent); border-color: #9db8d4; }
        .seo-related { display: grid; gap: .55rem; margin: .75rem 0 0; }
        .seo-related a { font-weight: 600; text-decoration: none; }
        .seo-glossary { margin: 1rem 0 0; }
        .seo-glossary dt { font-weight: 700; margin: 1rem 0 .25rem; }
        .seo-glossary dd { margin: 0 0 .35rem; color: var(--seo-muted); }
        .seo-table {
            width: 100%; border-collapse: collapse; font-size: .92rem;
            margin: .75rem 0 1.25rem; background: var(--seo-card);
        }
        .seo-table th, .seo-table td {
            border: 1px solid var(--seo-line); padding: .55rem .65rem; text-align: left; vertical-align: top;
        }
        .seo-table th { background: #eef3f8; }
        .seo-related a:hover { text-decoration: underline; }
        .seo-note {
            background: #eef5fb;
            border: 1px solid #c5d8ec;
            border-radius: 12px;
            padding: .9rem 1rem;
            margin: 1rem 0 1.25rem;
            font-size: .95rem;
            color: var(--seo-ink);
        }
        .seo-faq details {
            background: var(--seo-card);
            border: 1px solid var(--seo-line);
            border-radius: 12px;
            padding: .75rem 1rem;
            margin: .55rem 0;
        }
        .seo-faq summary { cursor: pointer; font-weight: 700; }
        .seo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
            gap: .65rem;
            margin: .75rem 0 1rem;
        }
        .seo-grid a {
            display: block;
            text-decoration: none;
            font-weight: 600;
            background: var(--seo-card);
            border: 1px solid var(--seo-line);
            border-radius: 12px;
            padding: .85rem 1rem;
        }
        .seo-grid a:hover { border-color: #9db8d4; }
        .seo-byline { font-size: .9rem; color: var(--seo-muted); margin: 0 0 1rem; }
        .seo-answer {
            background: #f3faf3;
            border-left: 4px solid #2f7d32;
            padding: .75rem 1rem;
            margin: 0 0 1.1rem;
            border-radius: 0 10px 10px 0;
        }
        .seo-skip {
            position: absolute; left: -999px; top: 0;
            background: #000; color: #fff; padding: .5rem .75rem; z-index: 99;
        }
        .seo-skip:focus { left: .5rem; top: .5rem; }
        a:focus-visible, button:focus-visible, summary:focus-visible {
            outline: 3px solid #f8b400; outline-offset: 2px;
        }
        footer.seo-foot {
            margin-top: 2.25rem; padding-top: 1rem;
            border-top: 1px solid var(--seo-line);
            font-size: .92rem; color: var(--seo-muted);
        }
        address { font-style: normal; white-space: pre-line; }
        @media (max-width: 640px) {
            .seo-wrap { padding: 1rem 1rem 2.5rem; }
        }
    </style>
    <?php seo_print_jsonld($schemas); ?>
</head>
<body>
    <a class="seo-skip" href="#seo-main">Skip to main content</a>
    <div class="seo-wrap">
        <nav class="seo-nav" aria-label="Primary">
            <a href="<?= e($base) ?>/">Home</a>
            <a href="<?= e($base) ?>/about">About</a>
            <a href="<?= e($base) ?>/pearson-exams">Pearson Exams</a>
            <a href="<?= e($base) ?>/online-classes">Online classes</a>
            <a href="<?= e($base) ?>/subjects">Subjects</a>
            <a href="<?= e($base) ?>/edexcel-classes">Classes</a>
            <a href="<?= e($base) ?>/edexcel-o-level">IGCSE</a>
            <a href="<?= e($base) ?>/teachers/">Teachers</a>
            <a href="<?= e($base) ?>/edexcel-a-level">A Level</a>
            <a href="<?= e($base) ?>/resources">Resources</a>
            <a href="<?= e($base) ?>/faq">FAQ</a>
            <a href="<?= e($base) ?>/locations/kandy">Kandy Campus</a>
            <a href="<?= e($base) ?>/contact">Contact</a>
            <a href="<?= e($base) ?>/admissions/enquire.php">Enquire</a>
        </nav>
        <p class="seo-crumbs" aria-label="Breadcrumb">
            <?php
            $trail = [];
            foreach ($breadcrumbs as $crumb) {
                $trail[] = '<a href="' . e((string)$crumb['url']) . '">' . e((string)$crumb['name']) . '</a>';
            }
            echo implode(' · ', $trail);
            ?>
        </p>
        <main id="seo-main">
    <?php
    $GLOBALS['seo_page_contact'] = $contact;
}

function seo_page_end(): void
{
    $c = $GLOBALS['seo_page_contact'] ?? college_contact();
    $base = seo_base_url();
    ?>
        </main>
        <footer class="seo-foot">
            <p><strong><?= e((string)$c['name']) ?></strong></p>
            <address><a href="<?= e((string)$c['maps_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$c['address']) ?></a></address>
            <p>
                <a href="mailto:<?= e((string)$c['email']) ?>"><?= e((string)$c['email']) ?></a>
                · <a href="tel:<?= e((string)$c['phone_tel']) ?>"><?= e((string)$c['phone']) ?></a>
                · <a href="https://wa.me/<?= e((string)$c['whatsapp']) ?>" rel="noopener">WhatsApp</a>
            </p>
            <p><?= e((string)$c['hours']) ?></p>
            <p>
                <a href="<?= e($base) ?>/">Home</a> ·
                <a href="<?= e($base) ?>/about">About</a> ·
                <a href="<?= e($base) ?>/pearson-exams">Pearson exams</a> ·
                <a href="<?= e($base) ?>/resources">Resources</a> ·
                <a href="<?= e($base) ?>/glossary">Glossary</a> ·
                <a href="<?= e($base) ?>/faq">FAQ</a> ·
                <a href="<?= e($base) ?>/locations/kandy">Kandy</a> ·
                <a href="<?= e($base) ?>/teachers/">Teachers</a> ·
                <a href="<?= e($base) ?>/student/register.php">Admissions</a> ·
                <a href="<?= e($base) ?>/terms">Terms</a> ·
                <a href="<?= e($base) ?>/privacy-policy">Privacy</a>
            </p>
        </footer>
    </div>
    <?php
    if (is_file(__DIR__ . '/visitor_tracking.php')) {
        require_once __DIR__ . '/visitor_tracking.php';
        visitor_tracking_tag();
    }
    ?>
</body>
</html>
    <?php
}

function seo_page_cta(string $heading = 'Ready to enrol?'): void
{
    $base = seo_base_url();
    ?>
    <div class="seo-card">
        <h2><?= e($heading) ?></h2>
        <p>Speak with admissions, send a course enquiry, or create a student account online.</p>
        <div class="seo-cta">
            <a class="seo-btn seo-btn-primary" href="<?= e($base) ?>/admissions/enquire.php">Course enquiry</a>
            <a class="seo-btn seo-btn-ghost" href="<?= e($base) ?>/student/register.php">Student registration</a>
            <a class="seo-btn seo-btn-ghost" href="<?= e($base) ?>/contact">Contact the college</a>
        </div>
    </div>
    <?php
}

/**
 * @param list<string> $exclude
 */
function seo_page_related(array $exclude = []): void
{
    $base = seo_base_url();
    $links = [
        'hub' => ['href' => $base . '/pearson-exams', 'label' => 'Pearson / Edexcel exam services'],
        'resources' => ['href' => $base . '/resources', 'label' => 'Student resources hub'],
        'classes' => ['href' => $base . '/edexcel-classes', 'label' => 'Edexcel classes overview'],
        'o-level' => ['href' => $base . '/edexcel-o-level', 'label' => 'IGCSE / O Level pathway'],
        'a-level' => ['href' => $base . '/edexcel-a-level', 'label' => 'A Level / IAL pathway'],
        'exam' => ['href' => $base . '/exam-preparation', 'label' => 'Exam preparation'],
        'kandy' => ['href' => $base . '/locations/kandy', 'label' => 'Edexcel College'],
        'glossary' => ['href' => $base . '/glossary', 'label' => 'Glossary'],
        'faq' => ['href' => $base . '/faq', 'label' => 'Frequently asked questions'],
        'teachers' => ['href' => $base . '/teachers/', 'label' => 'Meet our teachers'],
        'sri-lanka' => ['href' => $base . '/pearson-exams/sri-lanka', 'label' => 'Pearson exams in Sri Lanka'],
    ];
    ?>
    <h2>Related pages</h2>
    <div class="seo-related">
        <?php foreach ($links as $key => $link): ?>
            <?php if (in_array($key, $exclude, true)) { continue; } ?>
            <a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render a country landing from seo_country_pages().
 */
function seo_render_country_page(string $slug): void
{
    $pages = seo_country_pages();
    if (!isset($pages[$slug])) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    $page = $pages[$slug];
    global $pdo;
    $pdoSafe = $pdo instanceof PDO ? $pdo : null;
    $base = seo_base_url();
    $path = 'pearson-exams/' . $slug;
    $url = seo_absolute_url($path);
    $reviewed = (string)($page['reviewed'] ?? '11 September 2026');
    $name = (string)$page['name'];

    $schemas = [
        seo_service_schema([
            'name' => (string)$page['title'],
            'description' => (string)$page['description'],
            'url' => $url,
            'area' => $name,
        ], $pdoSafe),
    ];
    $countryFaqs = $page['faqs'] ?? [];
    if (is_array($countryFaqs) && $countryFaqs !== []) {
        $faqSchema = seo_faq_schema($countryFaqs);
        if ($faqSchema !== null) {
            $schemas[] = $faqSchema;
        }
    }

    seo_page_start([
        'title' => (string)$page['title'],
        'description' => (string)$page['description'],
        'canonical_path' => $path,
        'geo_region' => (string)($page['geo_region'] ?? 'LK'),
        'geo_placename' => (string)($page['geo_placename'] ?? $page['name']),
        'og_locale' => 'en_' . ((string)($page['geo_region'] ?? 'LK')),
        'breadcrumbs' => [
            ['name' => 'Home', 'url' => $base . '/'],
            ['name' => 'Pearson exams', 'url' => $base . '/pearson-exams'],
            ['name' => $name, 'url' => $url],
        ],
        'schemas' => $schemas,
    ]);

    echo '<h1>' . e((string)$page['h1']) . '</h1>';
    seo_render_byline($reviewed, $reviewed);
    echo '<p class="seo-lead">' . e((string)$page['lead']) . '</p>';

    if (!empty($page['answer'])) {
        seo_render_answer('Direct answer:', (string)$page['answer']);
    } elseif ($slug === 'sri-lanka') {
        seo_render_answer(
            'Campus vs overview:',
            'This page explains Pearson Edexcel options for students in Sri Lanka. On-site classes run at our Kandy campus; Kurunegala and Colombo families often join online or travel to Kandy. See Edexcel tuition classes to enrol.'
        );
        echo '<p><a href="' . e($base) . '/edexcel-classes">Go to Edexcel class programmes</a> · <a href="' . e($base) . '/locations/kandy">Kandy campus</a>.</p>';
    }

    seo_render_disclaimer_box();
    echo '<p><em>' . e(seo_freshness_notice($reviewed)) . '</em></p>';

    if (($page['mode'] ?? '') === 'campus') {
        echo '<p><strong>Delivery:</strong> Around 90% of classes are live online for students worldwide. Physical classes are at the Kandy campus when a subject and teacher are scheduled there.</p>';
    } else {
        echo '<p><strong>Delivery:</strong> Online / hybrid tuition support for students in '
            . e($name)
            . '. Our only physical campus is in Kandy, Sri Lanka — we do not claim a branch office in '
            . e($name) . '.</p>';
    }

    echo '<h2>Services available</h2>';
    echo '<table class="seo-table"><thead><tr><th>Service</th><th>Available for ' . e($name) . '?</th><th>Notes</th></tr></thead><tbody>';
    $services = $page['services'] ?? [
        ['IGCSE / O Level tuition', 'Yes (enquire)', 'Pearson Edexcel International GCSE pathway'],
        ['International A Level (IAL) tuition', 'Yes (enquire)', 'Unit teaching and paper practice'],
        ['Exam preparation / past papers', 'Yes (enquire)', 'Aligned to papers you enter elsewhere'],
        ['Official Pearson exam entry', 'No — use a centre', 'We are not a Pearson examination centre'],
        ['Results / certificates', 'No — via your centre', 'Issued through Pearson and your centre'],
    ];
    foreach ($services as $row) {
        echo '<tr><td>' . e((string)$row[0]) . '</td><td>' . e((string)$row[1]) . '</td><td>' . e((string)$row[2]) . '</td></tr>';
    }
    echo '</tbody></table>';

    foreach ($page['sections'] as $section) {
        echo '<h2>' . e((string)$section['h']) . '</h2>';
        echo '<p>' . e((string)$section['p']) . '</p>';
    }

    echo '<h2>How the process works</h2><ol>';
    $steps = $page['process'] ?? [
        'Confirm which Pearson Edexcel specifications and subjects you need (IGCSE and/or IAL units).',
        'Enquire with admissions about online or campus tuition availability for your time zone.',
        'Register for exams separately with an authorised centre in your country (or school centre).',
        'Share confirmed papers with your teachers so tuition matches what you entered.',
        'Sit exams at the centre; collect results and certificates through that centre.',
    ];
    foreach ($steps as $step) {
        echo '<li>' . e((string)$step) . '</li>';
    }
    echo '</ol>';

    echo '<h2>Who can use this service</h2><ul>';
    $who = $page['who'] ?? [
        'Secondary students preparing for Pearson Edexcel International GCSE',
        'Post-16 students taking International A Level units',
        'Private candidates who need structured tuition alongside centre registration',
        'Parents who want clear weekly teaching and progress visibility',
    ];
    foreach ($who as $item) {
        echo '<li>' . e((string)$item) . '</li>';
    }
    echo '</ul>';

    if (is_array($countryFaqs) && $countryFaqs !== []) {
        echo '<h2>FAQs for ' . e($name) . '</h2><div class="seo-faq">';
        foreach ($countryFaqs as $faq) {
            echo '<details><summary>' . e((string)$faq['question']) . '</summary><p>'
                . e((string)$faq['answer']) . '</p></details>';
        }
        echo '</div>';
    }

    echo '<h2>Official and supporting sources</h2><ul>';
    $links = $page['official'] ?? [
        ['Pearson qualifications (official)', 'https://qualifications.pearson.com/'],
        ['Private candidates (Pearson)', 'https://qualifications.pearson.com/en/support/support-topics/registrations-and-entries/academic-registrations-and-entries/private-candidates.html'],
    ];
    foreach ($links as $link) {
        $label = (string)$link[0];
        $href = (string)$link[1];
        $ext = str_starts_with($href, 'http');
        $full = $ext ? $href : ($base . $href);
        echo '<li><a href="' . e($full) . '"'
            . ($ext ? ' rel="noopener noreferrer" target="_blank"' : '')
            . '>' . e($label) . '</a></li>';
    }
    echo '</ul>';

    echo '<h2>Programmes and topic cluster</h2>';
    echo '<div class="seo-related">';
    echo '<a href="' . e($base) . '/edexcel-o-level">IGCSE / O Level pathway</a>';
    echo '<a href="' . e($base) . '/edexcel-a-level">A Level / IAL pathway</a>';
    echo '<a href="' . e($base) . '/exam-preparation">Exam preparation</a>';
    echo '<a href="' . e($base) . '/guides/how-to-register-for-pearson-exams">How to register</a>';
    echo '<a href="' . e($base) . '/guides/private-candidates">Private candidates</a>';
    echo '<a href="' . e($base) . '/guides/results-and-certificates">Results and certificates</a>';
    echo '<a href="' . e($base) . '/glossary">Glossary</a>';
    echo '<a href="' . e($base) . '/faq">FAQ knowledge base</a>';
    echo '<a href="' . e($base) . '/resources">Resources hub</a>';
    if ($slug === 'sri-lanka') {
        echo '<a href="' . e($base) . '/locations/kandy">Kandy campus</a>';
        echo '<a href="' . e($base) . '/edexcel-classes">Classes (Kandy / online)</a>';
    }
    echo '</div>';

    echo '<h2>Enquire about tuition for ' . e($name) . '</h2>';
    echo '<p>Tell admissions your subjects, target exam series, and preferred times (include your city/time zone). We reply with realistic options — we will not invent local offices or guarantee centre places.</p>';
    seo_page_cta('Start a course enquiry');
    seo_page_related(['sri-lanka', 'hub']);
    seo_page_end();
}
