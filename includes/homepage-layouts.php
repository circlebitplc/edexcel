<?php
declare(strict_types=1);

/**
 * Homepage layout catalog, whitelist, and active-layout persistence.
 * Uses the existing `settings` table (same shape as the suggested site_settings).
 */

const HOMEPAGE_LAYOUT_SETTING_KEY = 'active_homepage_layout';
const HOMEPAGE_LAYOUT_DEFAULT = 'layout1';

/**
 * @return list<string>
 */
function homepage_allowed_layouts(): array
{
    return [
        'layout1',
        'layout2',
        'layout3',
        'layout4',
        'layout5',
        'layout6',
        'layout7',
        'layout8',
        'layout9',
        'layout10',
    ];
}

function homepage_is_allowed_layout(?string $key): bool
{
    return is_string($key) && in_array($key, homepage_allowed_layouts(), true);
}

function homepage_sanitize_layout(?string $key): string
{
    $key = strtolower(trim((string)$key));
    $key = preg_replace('/[^a-z0-9]/', '', $key) ?? '';
    return homepage_is_allowed_layout($key) ? $key : HOMEPAGE_LAYOUT_DEFAULT;
}

/**
 * @return array<string, array{
 *   id:string,
 *   number:int,
 *   name:string,
 *   tagline:string,
 *   description:string,
 *   fonts:string,
 *   accent:string
 * }>
 */
function homepage_layout_catalog(): array
{
    return [
        'layout1' => [
            'id' => 'layout1',
            'number' => 1,
            'name' => 'Modern Education Hero',
            'tagline' => 'Premium Academic Institution',
            'description' => 'Flagship institutional homepage with Pearson Edexcel curriculum highlights, academic journey, specialist faculty showcase, and high-trust conversion experience.',
            'fonts' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800',
            'accent' => '#0b2a5b',
        ],
        'layout2' => [
            'id' => 'layout2',
            'number' => 2,
            'name' => 'Dashboard Style',
            'tagline' => 'SaaS / student dashboard',
            'description' => 'Student dashboard with sidebar, search, KPI bars, calendar, today\'s classes and quick links.',
            'fonts' => 'DM+Sans:wght@400;500;600;700',
            'accent' => '#2563eb',
        ],
        'layout3' => [
            'id' => 'layout3',
            'number' => 3,
            'name' => 'Classic Homepage',
            'tagline' => 'Original public portal',
            'description' => 'The original index.php homepage: metrics, Why Choose slider, teacher directory, weekly timetable, and bottom navigation.',
            'fonts' => 'Outfit:wght@400;500;600;700',
            'accent' => '#5161ce',
        ],
        'layout4' => [
            'id' => 'layout4',
            'number' => 4,
            'name' => 'Bento Grid',
            'tagline' => 'Apple / Notion cards',
            'description' => 'Colourful mosaic cards for programmes, teachers, timetable, portal, admissions, news and contact.',
            'fonts' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800',
            'accent' => '#111827',
        ],
        'layout5' => [
            'id' => 'layout5',
            'number' => 5,
            'name' => 'Digital Campus',
            'tagline' => 'Interactive campus map',
            'description' => 'Interactive isometric campus map with Explore markers for IGCSE, IAL, teachers, timetable and admissions.',
            'fonts' => 'Nunito:wght@400;600;700;800',
            'accent' => '#0f766e',
        ],
        'layout6' => [
            'id' => 'layout6',
            'number' => 6,
            'name' => 'Mobile App Style',
            'tagline' => 'App-first college home',
            'description' => 'Phone-style college app with a greeting, colourful shortcut grid, today\'s classes and a four-item bottom bar.',
            'fonts' => 'Figtree:wght@400;500;600;700;800',
            'accent' => '#5161ce',
        ],
        'layout7' => [
            'id' => 'layout7',
            'number' => 7,
            'name' => 'Achievement Focused',
            'tagline' => 'Building Future Achievers',
            'description' => 'Dark prestige homepage with a gold trophy, achievement tiles, teacher cards and a student testimonial.',
            'fonts' => 'Cormorant+Garamond:wght@500;600;700&family=Outfit:wght@400;500;600;700',
            'accent' => '#b45309',
        ],
        'layout8' => [
            'id' => 'layout8',
            'number' => 8,
            'name' => 'Cinematic Layout',
            'tagline' => 'Premium international film',
            'description' => 'Full-width campus film or photograph, play control, then a clean Why Choose strip and statistics.',
            'fonts' => 'Bebas+Neue&family=Manrope:wght@400;500;600;700;800',
            'accent' => '#eab308',
        ],
        'layout9' => [
            'id' => 'layout9',
            'number' => 9,
            'name' => 'Magazine / News Portal',
            'tagline' => 'Editorial education site',
            'description' => 'Gazette layout with a featured photo story, latest news, event date badges and a teacher column.',
            'fonts' => 'Newsreader:wght@500;600;700&family=Public+Sans:wght@400;500;600;700',
            'accent' => '#9f1239',
        ],
        'layout10' => [
            'id' => 'layout10',
            'number' => 10,
            'name' => 'Futuristic Interactive',
            'tagline' => 'Tech-forward campus',
            'description' => 'Neon subject network around a Student hub, glass stats, and a dock for timetable, teachers and portals.',
            'fonts' => 'Orbitron:wght@500;600;700&family=Space+Grotesk:wght@400;500;600;700',
            'accent' => '#22d3ee',
        ],
    ];
}

function homepage_layout_meta(string $key): array
{
    $catalog = homepage_layout_catalog();
    $key = homepage_sanitize_layout($key);
    return $catalog[$key];
}

function homepage_layout_file(string $key): string
{
    $key = homepage_sanitize_layout($key);
    $root = defined('HOMEPAGE_ROOT') ? HOMEPAGE_ROOT : dirname(__DIR__);
    return $root . '/layouts/' . $key . '.php';
}

function homepage_ensure_layout_setting(?PDO $pdo): void
{
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([HOMEPAGE_LAYOUT_SETTING_KEY]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            $ins = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $ins->execute([HOMEPAGE_LAYOUT_SETTING_KEY, HOMEPAGE_LAYOUT_DEFAULT]);
        }
    } catch (Throwable $e) {
        error_log('homepage_ensure_layout_setting: ' . $e->getMessage());
    }
}

function getActiveHomepageLayout(?PDO $pdo): string
{
    if (!($pdo instanceof PDO)) {
        return HOMEPAGE_LAYOUT_DEFAULT;
    }
    homepage_ensure_layout_setting($pdo);
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([HOMEPAGE_LAYOUT_SETTING_KEY]);
        $value = $stmt->fetchColumn();
        return homepage_sanitize_layout(is_string($value) ? $value : HOMEPAGE_LAYOUT_DEFAULT);
    } catch (Throwable $e) {
        error_log('getActiveHomepageLayout: ' . $e->getMessage());
        return HOMEPAGE_LAYOUT_DEFAULT;
    }
}

function setActiveHomepageLayout(PDO $pdo, string $key): string
{
    $key = homepage_sanitize_layout($key);
    homepage_ensure_layout_setting($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([HOMEPAGE_LAYOUT_SETTING_KEY, $key]);
    return $key;
}

function homepage_require_layout(string $key): void
{
    $file = homepage_layout_file($key);
    if (!is_file($file)) {
        $key = HOMEPAGE_LAYOUT_DEFAULT;
        $file = homepage_layout_file($key);
    }
    if (!is_file($file)) {
        http_response_code(500);
        echo 'Homepage layout is unavailable. Please contact the administrator.';
        exit;
    }

    $hp = $GLOBALS['hp'] ?? [];
    $activeLayout = homepage_sanitize_layout($key);
    $pdo = $GLOBALS['pdo'] ?? null;
    $homepagePreviewMode = !empty($GLOBALS['homepagePreviewMode']);
    $homepageFormAction = $GLOBALS['homepageFormAction'] ?? 'index.php';
    $activeSection = $GLOBALS['activeSection'] ?? 'home';
    $teacherLoginError = $GLOBALS['teacherLoginError'] ?? '';
    $teacherOtpNotice = $GLOBALS['teacherOtpNotice'] ?? '';
    $teacherOtpStep = !empty($GLOBALS['teacherOtpStep']);
    $studentLoginError = $GLOBALS['studentLoginError'] ?? '';
    $studentOtpNotice = $GLOBALS['studentOtpNotice'] ?? '';
    $studentOtpStep = !empty($GLOBALS['studentOtpStep']);
    $parentLoginError = $GLOBALS['parentLoginError'] ?? '';
    $parentLoginNotice = $GLOBALS['parentLoginNotice'] ?? '';
    $parentOtpStep = !empty($GLOBALS['parentOtpStep']);

    require $file;
}
