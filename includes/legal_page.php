<?php
declare(strict_types=1);

if (!defined('DB_ALLOW_FAILURE')) {
    define('DB_ALLOW_FAILURE', true);
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/college_contact.php';
require_once __DIR__ . '/seo.php';

function legal_page_contact(): array
{
    global $pdo;
    return college_contact($pdo instanceof PDO ? $pdo : null);
}

function legal_page_start(string $title, string $description, string $canonicalPath): void
{
    $contact = legal_page_contact();
    $canonical = seo_absolute_url($canonicalPath);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: index, follow');
    $schemas = [
        seo_organization_schema(($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null),
        seo_breadcrumb_schema([
            ['name' => 'Home', 'url' => seo_absolute_url('/')],
            ['name' => $title, 'url' => $canonical],
        ]),
    ];
    $base = seo_base_url();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php seo_render_meta([
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'robots' => 'index, follow',
    ]); ?>
    <style>
        :root { color-scheme: light; }
        body { font-family: Georgia, serif; max-width: 44rem; margin: 2rem auto; padding: 0 1.25rem 3rem; line-height: 1.55; color: #222; }
        h1, h2, h3 { font-family: system-ui, sans-serif; }
        h1 { font-size: 1.85rem; margin: 0 0 .75rem; }
        h2 { font-size: 1.15rem; margin: 1.6rem 0 .5rem; }
        p, li { font-size: 1.02rem; }
        a { color: #0b57d0; }
        nav { font-family: system-ui, sans-serif; font-size: .9rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: .35rem .75rem; }
        .legal-contact { font-family: system-ui, sans-serif; background: #f4f6fb; border: 1px solid #dbe3f0; border-radius: 12px; padding: 1rem 1.1rem; margin: 1.5rem 0; }
        .legal-contact h2 { margin-top: 0; }
        .legal-contact p { margin: .35rem 0; }
        address { font-style: normal; white-space: pre-line; }
        .updated { color: #555; font-size: .92rem; }
    </style>
    <?php seo_print_jsonld($schemas); ?>
</head>
<body>
    <nav>
        <a href="<?= e($base) ?>/">Home</a>
        <a href="<?= e($base) ?>/about">About</a>
        <a href="<?= e($base) ?>/edexcel-classes">Edexcel Classes</a>
        <a href="<?= e($base) ?>/contact">Contact</a>
        <a href="<?= e($base) ?>/terms">Terms</a>
        <a href="<?= e($base) ?>/privacy-policy">Privacy</a>
        <a href="<?= e($base) ?>/refund-policy">Refund</a>
    </nav>
    <?php
}

function legal_page_contact_block(): void
{
    $c = legal_page_contact();
    $base = seo_base_url();
    ?>
    <section class="legal-contact" id="contact">
        <h2>Contact details</h2>
        <p><strong><?= e($c['name']) ?></strong></p>
        <address><?= e($c['address']) ?></address>
        <p>Email: <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></p>
        <p>Telephone: <a href="tel:<?= e($c['phone_tel']) ?>"><?= e($c['phone']) ?></a></p>
        <p>WhatsApp: <a href="https://wa.me/<?= e($c['whatsapp']) ?>" rel="noopener">+<?= e($c['whatsapp']) ?></a></p>
        <p>Office hours: <?= e($c['hours']) ?></p>
        <p>Website: <a href="<?= e($base) ?>/"><?= e(preg_replace('#^https?://#', '', $base) ?? 'edexcel.college') ?></a></p>
    </section>
    <?php
}

function legal_page_end(string $updated = '11 September 2026', bool $contactBlock = true): void
{
    if ($contactBlock) {
        legal_page_contact_block();
    }
    ?>
    <p class="updated">Last updated: <?= e($updated) ?>.</p>
</body>
</html>
    <?php
}
