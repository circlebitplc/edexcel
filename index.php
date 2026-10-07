<?php
declare(strict_types=1);

/**
 * Public homepage — loads the administrator-selected layout.
 * Existing teacher/student login, timetable, teachers and events stay on the shared data layer.
 */

$homepageFatal = static function (Throwable $e): void {
    error_log('index.php homepage failure: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $base = '';
    if (defined('BASE_URL')) {
        $base = rtrim((string)BASE_URL, '/');
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . preg_replace('/[^a-zA-Z0-9.:\\-]/', '', (string)$_SERVER['HTTP_HOST']);
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $link = static function (string $path) use ($base, $esc): string {
        $href = $base !== '' ? $base . $path : $path;
        return $esc($href);
    };

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex">';
    echo '<title>Edexcel College</title>';
    echo '<style>
      :root{color-scheme:light dark}
      body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;line-height:1.5}
      main{max-width:36rem;margin:12vh auto;padding:1.5rem}
      h1{font-size:1.5rem;margin:0 0 .75rem}
      p{margin:0 0 1rem;color:#94a3b8}
      nav{display:flex;flex-wrap:wrap;gap:.75rem}
      a{color:#93c5fd;text-decoration:none;padding:.65rem .9rem;border:1px solid #334155;border-radius:.75rem}
      a:hover,a:focus-visible{background:#1e293b;outline:2px solid #93c5fd;outline-offset:2px}
    </style></head><body><main role="alert">';
    echo '<h1>We are refreshing the homepage</h1>';
    echo '<p>The page could not load right now. You can still open the main portals below, or try again in a moment.</p>';
    echo '<nav aria-label="Quick links">';
    echo '<a href="' . $link('/') . '">Try homepage again</a>';
    echo '<a href="' . $link('/login.php') . '">Staff login</a>';
    echo '<a href="' . $link('/student/login.php') . '">Student login</a>';
    echo '<a href="' . $link('/parent/login.php') . '">Parent login</a>';
    echo '<a href="' . $link('/teachers/index.php') . '">Teachers</a>';
    echo '<a href="' . $link('/admissions/enquire.php') . '">Admissions</a>';
    echo '<a href="' . $link('/contact.php') . '">Contact</a>';
    echo '</nav></main></body></html>';
};

try {
    require_once __DIR__ . '/includes/homepage-bootstrap.php';

    if (!isset($activeLayout) || !is_string($activeLayout) || $activeLayout === '') {
        $activeLayout = defined('HOMEPAGE_LAYOUT_DEFAULT') ? HOMEPAGE_LAYOUT_DEFAULT : 'layout1';
    }

    if (!function_exists('homepage_require_layout')) {
        throw new RuntimeException('Homepage layout loader is unavailable.');
    }

    homepage_require_layout($activeLayout);
} catch (Throwable $e) {
    $homepageFatal($e);
}
