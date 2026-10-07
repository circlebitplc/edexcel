<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seo_page.php';

$slug = strtolower(trim((string)($_GET['country'] ?? '')));
$slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? '';
if ($slug === '' || !isset(seo_country_pages()[$slug])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Country page not found.\n";
    exit;
}
seo_render_country_page($slug);
