<?php
declare(strict_types=1);

/**
 * Application appearance / theme system.
 *
 * Active theme is applied with data-theme on <html>. Bootstrap still uses
 * data-bs-theme="dark"|"light" so existing components keep working.
 *
 * Load priority:
 *   1. Logged-in user's users.theme_preference
 *   2. eck_theme cookie / localStorage (localStorage is client-only)
 *   3. Legacy dark_mode cookie
 *   4. Default: dark
 */

const APP_THEME_DEFAULT = 'dark';
const APP_THEME_COOKIE = 'eck_theme';
const APP_THEME_STORAGE_KEY = 'eck_theme';

/**
 * @return array<string, array{
 *   name: string,
 *   blurb: string,
 *   family: string,
 *   swatches: array<int, string>,
 *   color: string
 * }>
 */
function app_themes(): array
{
    return [
        'dark' => [
            'name' => 'Dark',
            'blurb' => 'Default modern dark interface',
            'family' => 'dark',
            'swatches' => ['#10131b', '#191e2b', '#6878e3', '#f8b400'],
            'color' => '#10131b',
        ],
        'light' => [
            'name' => 'Light',
            'blurb' => 'Clean and minimal bright interface',
            'family' => 'light',
            'swatches' => ['#f7f8fd', '#ffffff', '#5161ce', '#f8b400'],
            'color' => '#f7f8fd',
        ],
        'midnight' => [
            'name' => 'Midnight Blue',
            'blurb' => 'Premium dark navy interface',
            'family' => 'dark',
            'swatches' => ['#070d1c', '#121a36', '#5b8def', '#7dd3fc'],
            'color' => '#0a1228',
        ],
        'ocean' => [
            'name' => 'Ocean',
            'blurb' => 'Modern blue and cyan palette',
            'family' => 'dark',
            'swatches' => ['#06161d', '#0d2530', '#22d3ee', '#2dd4bf'],
            'color' => '#071a22',
        ],
        'purple' => [
            'name' => 'Purple',
            'blurb' => 'Modern purple and violet interface',
            'family' => 'dark',
            'swatches' => ['#140e24', '#1d1533', '#a78bfa', '#e879f9'],
            'color' => '#140e24',
        ],
        'emerald' => [
            'name' => 'Emerald',
            'blurb' => 'Clean green and emerald interface',
            'family' => 'dark',
            'swatches' => ['#071612', '#0e241c', '#34d399', '#6ee7b7'],
            'color' => '#071612',
        ],
        'sunset' => [
            'name' => 'Sunset',
            'blurb' => 'Warm orange and red interface',
            'family' => 'dark',
            'swatches' => ['#1a0f0c', '#271712', '#fb923c', '#f43f5e'],
            'color' => '#1a0f0c',
        ],
        'rose' => [
            'name' => 'Rose',
            'blurb' => 'Premium pink and rose interface',
            'family' => 'dark',
            'swatches' => ['#1a0f14', '#2a1520', '#fb7185', '#f472b6'],
            'color' => '#1a0f14',
        ],
        'cyber' => [
            'name' => 'Cyber Neon',
            'blurb' => 'Futuristic dark with neon accents',
            'family' => 'dark',
            'swatches' => ['#05060a', '#0c1018', '#22f0ff', '#d946ef'],
            'color' => '#05060a',
        ],
        'glass' => [
            'name' => 'Glassmorphism',
            'blurb' => 'Frosted glass cards on a deep backdrop',
            'family' => 'dark',
            'swatches' => ['#0b1220', 'rgba(255,255,255,.12)', '#8b9cff', '#67e8f9'],
            'color' => '#0b1220',
        ],
    ];
}

/** @return list<string> */
function app_theme_ids(): array
{
    return array_keys(app_themes());
}

function app_theme_normalize(?string $theme): string
{
    $theme = strtolower(trim((string) $theme));
    if ($theme === 'true' || $theme === '1' || $theme === 'dark_mode') {
        return 'dark';
    }
    if ($theme === 'false' || $theme === '0' || $theme === 'light_mode') {
        return 'light';
    }
    return in_array($theme, app_theme_ids(), true) ? $theme : APP_THEME_DEFAULT;
}

function app_theme_family(string $theme): string
{
    $themes = app_themes();
    $id = app_theme_normalize($theme);
    return $themes[$id]['family'] ?? 'dark';
}

function app_theme_meta(string $theme): array
{
    $id = app_theme_normalize($theme);
    return app_themes()[$id];
}

function app_theme_ensure_schema(?PDO $pdo): void
{
    if (!$pdo instanceof PDO) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'theme_preference'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec("
                ALTER TABLE users
                ADD COLUMN theme_preference VARCHAR(50) NOT NULL DEFAULT 'dark'
            ");
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('theme schema: ' . $e->getMessage());
    }
}

function app_theme_set_cookie(string $theme): void
{
    $theme = app_theme_normalize($theme);
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $opts = [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ];
    setcookie(APP_THEME_COOKIE, $theme, $opts);
    $_COOKIE[APP_THEME_COOKIE] = $theme;
    // Keep the legacy toggle cookie in sync for older scripts.
    setcookie('dark_mode', app_theme_family($theme) === 'dark' ? 'true' : 'false', $opts);
}

function app_theme_clear_dirty_cookie(): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    setcookie('eck_theme_dirty', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['eck_theme_dirty']);
}

function app_theme_from_cookie(): ?string
{
    if (!empty($_COOKIE[APP_THEME_COOKIE])) {
        $value = app_theme_normalize((string) $_COOKIE[APP_THEME_COOKIE]);
        return $value;
    }
    if (isset($_COOKIE['dark_mode'])) {
        return $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light';
    }
    return null;
}

function app_theme_pdo(): ?PDO
{
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }
    return null;
}

function app_theme_load_user_preference(?PDO $pdo, int $userId): ?string
{
    if (!$pdo instanceof PDO || $userId < 1) {
        return null;
    }
    app_theme_ensure_schema($pdo);
    try {
        $stmt = $pdo->prepare('SELECT theme_preference FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || trim((string) $value) === '') {
            return null;
        }
        return app_theme_normalize((string) $value);
    } catch (Throwable $e) {
        return null;
    }
}

function app_theme_save_user(?PDO $pdo, int $userId, string $theme): bool
{
    $theme = app_theme_normalize($theme);
    if (!$pdo instanceof PDO || $userId < 1) {
        return false;
    }
    app_theme_ensure_schema($pdo);
    try {
        $stmt = $pdo->prepare('UPDATE users SET theme_preference = ? WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$theme, $userId]);
        return true;
    } catch (Throwable $e) {
        error_log('theme save: ' . $e->getMessage());
        return false;
    }
}

/**
 * Store the preference on the session + cookie after login or a saved change.
 */
function app_theme_remember(?string $theme): string
{
    $theme = app_theme_normalize($theme);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['theme_preference'] = $theme;
    }
    app_theme_set_cookie($theme);
    return $theme;
}

function app_theme_remember_user(array $user): string
{
    $pdo = app_theme_pdo();
    return app_theme_on_login($pdo, $user);
}

function app_theme_dirty_for_user(int $userId): bool
{
    if ($userId < 1) {
        return false;
    }
    $raw = (string) ($_COOKIE['eck_theme_dirty'] ?? '');
    if ($raw === '' || $raw === '0') {
        return false;
    }
    return (int) $raw === $userId;
}

/**
 * Restore the account theme at login, then keep session + cookie in sync.
 *
 * The database value is the source of truth for the account.
 * If this browser just changed the theme and the API save had not finished,
 * the dirty cookie (scoped to this user id) is written to the account first.
 */
function app_theme_on_login(?PDO $pdo, array $user): string
{
    $userId = (int) ($user['id'] ?? 0);
    if ($pdo instanceof PDO && $userId > 0) {
        app_theme_ensure_schema($pdo);
    }

    $dbTheme = ($pdo instanceof PDO && $userId > 0)
        ? app_theme_load_user_preference($pdo, $userId)
        : null;
    $rowTheme = isset($user['theme_preference']) && trim((string) $user['theme_preference']) !== ''
        ? app_theme_normalize((string) $user['theme_preference'])
        : null;
    $cookieTheme = app_theme_from_cookie();

    if (app_theme_dirty_for_user($userId) && $cookieTheme) {
        $theme = $cookieTheme;
    } else {
        $theme = $dbTheme ?: ($rowTheme ?: ($cookieTheme ?: APP_THEME_DEFAULT));
    }

    if ($pdo instanceof PDO && $userId > 0) {
        app_theme_save_user($pdo, $userId, $theme);
    }
    app_theme_clear_dirty_cookie();

    return app_theme_remember($theme);
}

function app_theme_logged_in_user_id(): int
{
    if (function_exists('is_logged_in') && is_logged_in()) {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
    return (int) ($_SESSION['user_id'] ?? 0);
}

function app_theme_request_csrf(array $body = []): string
{
    $header = (string) (
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_SERVER['HTTP_X_CSRFTOKEN']
        ?? $_SERVER['REDIRECT_HTTP_X_CSRF_TOKEN']
        ?? ''
    );
    $candidates = [
        (string) ($_POST['csrf_token'] ?? ''),
        (string) ($body['csrf_token'] ?? ''),
        $header,
    ];
    foreach ($candidates as $token) {
        if ($token !== '') {
            return $token;
        }
    }
    return '';
}

/**
 * Resolve the theme for this request.
 * Logged-in users always follow the database value so a new session
 * shows the same theme they saved last time.
 */
function app_theme_current(?PDO $pdo = null): string
{
    $pdo = $pdo instanceof PDO ? $pdo : app_theme_pdo();
    $userId = app_theme_logged_in_user_id();

    if ($userId > 0 && $pdo instanceof PDO) {
        $cookieTheme = app_theme_from_cookie();
        $dirty = app_theme_dirty_for_user($userId)
            || ((string) ($_COOKIE['eck_theme_dirty'] ?? '') === '1');
        if ($dirty && $cookieTheme) {
            app_theme_save_user($pdo, $userId, $cookieTheme);
            app_theme_clear_dirty_cookie();
            return app_theme_remember($cookieTheme);
        }
        $dbTheme = app_theme_load_user_preference($pdo, $userId);
        if ($dbTheme) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['theme_preference'] = $dbTheme;
            }
            if (app_theme_from_cookie() !== $dbTheme) {
                app_theme_set_cookie($dbTheme);
            }
            return $dbTheme;
        }
        $theme = $cookieTheme ?: APP_THEME_DEFAULT;
        app_theme_save_user($pdo, $userId, $theme);
        return app_theme_remember($theme);
    }

    $cookie = app_theme_from_cookie();
    if ($cookie) {
        return $cookie;
    }

    return APP_THEME_DEFAULT;
}

function app_theme_html_attrs(?PDO $pdo = null): string
{
    $theme = app_theme_current($pdo);
    $family = app_theme_family($theme);
    return 'data-theme="' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')
        . '" data-bs-theme="' . htmlspecialchars($family, ENT_QUOTES, 'UTF-8') . '"';
}

function app_theme_color(?string $theme = null): string
{
    $meta = app_theme_meta($theme ?? app_theme_current());
    return (string) $meta['color'];
}

function app_theme_base_url(): string
{
    return defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/';
}

function app_theme_asset_version(string $relativePath): string
{
    $full = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    return is_file($full) ? (string) filemtime($full) : '1';
}

/**
 * Inline boot script: apply localStorage for guests before first paint.
 * Logged-in users keep the server-rendered data-theme (no flash).
 */
function app_theme_boot_script(): void
{
    $current = app_theme_current();
    $userId = app_theme_logged_in_user_id();
    $loggedIn = $userId > 0;
    $ids = app_theme_ids();
    $base = app_theme_base_url();
    $csrf = ($loggedIn && function_exists('generate_csrf_token')) ? generate_csrf_token() : '';
    ?>
<script>
(function () {
    var ALLOWED = <?= json_encode($ids, JSON_UNESCAPED_SLASHES) ?>;
    var DEFAULT = <?= json_encode(APP_THEME_DEFAULT) ?>;
    var KEY = <?= json_encode(APP_THEME_STORAGE_KEY) ?>;
    var server = <?= json_encode($current) ?>;
    var loggedIn = <?= $loggedIn ? 'true' : 'false' ?>;
    var html = document.documentElement;
    var theme = server;
    if (!loggedIn) {
        try {
            var stored = localStorage.getItem(KEY) || localStorage.getItem('edexcel-theme');
            if (stored && ALLOWED.indexOf(stored) !== -1) {
                theme = stored;
            }
        } catch (e) {}
    }
    if (ALLOWED.indexOf(theme) === -1) {
        theme = DEFAULT;
    }
    var family = theme === 'light' ? 'light' : 'dark';
    html.setAttribute('data-theme', theme);
    html.setAttribute('data-bs-theme', family);
    html.style.colorScheme = family;
    window.ECK_THEME_STATE = {
        current: theme,
        loggedIn: loggedIn,
        userId: <?= (int) $userId ?>,
        csrfToken: <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>,
        saveUrl: <?= json_encode($base . 'api/theme.php', JSON_UNESCAPED_SLASHES) ?>,
        storageKey: KEY,
        allowed: ALLOWED,
        catalog: <?= json_encode(app_themes(), JSON_UNESCAPED_SLASHES) ?>
    };
})();
</script>
    <?php
}

function app_theme_css_link(): void
{
    $href = htmlspecialchars(app_theme_base_url() . 'assets/css/themes.css?v=' . app_theme_asset_version('assets/css/themes.css'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="' . $href . '">' . "\n";
}

function app_theme_js_link(): void
{
    $src = htmlspecialchars(app_theme_base_url() . 'assets/js/theme.js?v=' . app_theme_asset_version('assets/js/theme.js'), ENT_QUOTES, 'UTF-8');
    echo '<script src="' . $src . '" defer></script>' . "\n";
}

function app_theme_render_cards(string $layout = 'grid'): void
{
    $current = app_theme_current();
    $layout = $layout === 'compact' ? 'compact' : 'grid';
    echo '<div class="eck-theme-grid eck-theme-grid--' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '" role="list">';
    foreach (app_themes() as $id => $meta) {
        $active = $id === $current;
        ?>
        <button
            type="button"
            class="eck-theme-card<?= $active ? ' is-active' : '' ?>"
            data-eck-theme="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
            role="listitem"
            aria-pressed="<?= $active ? 'true' : 'false' ?>"
            aria-label="<?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?> theme"
        >
            <span class="eck-theme-preview" aria-hidden="true" data-preview-theme="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                <span class="eck-theme-preview-bar"></span>
                <span class="eck-theme-preview-body">
                    <span class="eck-theme-preview-sidebar"></span>
                    <span class="eck-theme-preview-main">
                        <span></span>
                        <span></span>
                    </span>
                </span>
                <span class="eck-theme-swatches">
                    <?php foreach ($meta['swatches'] as $swatch): ?>
                        <i style="background:<?= htmlspecialchars((string) $swatch, ENT_QUOTES, 'UTF-8') ?>"></i>
                    <?php endforeach; ?>
                </span>
            </span>
            <span class="eck-theme-card-meta">
                <strong><?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($meta['blurb'], ENT_QUOTES, 'UTF-8') ?></small>
            </span>
            <span class="eck-theme-check" aria-hidden="true"></span>
        </button>
        <?php
    }
    echo '</div>';
}

/**
 * Compact picker used in the navbar / homepage.
 *
 * @param 'nav'|'floating'|'header' $placement
 */
function app_theme_render_picker(string $placement = 'nav'): void
{
    $current = app_theme_current();
    $meta = app_theme_meta($current);
    $placement = in_array($placement, ['nav', 'floating', 'header'], true) ? $placement : 'nav';
    ?>
    <div class="eck-theme-picker eck-theme-picker--<?= htmlspecialchars($placement, ENT_QUOTES, 'UTF-8') ?>" id="eckThemePicker">
        <button
            type="button"
            class="eck-theme-toggle-btn"
            id="eckThemeToggle"
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-controls="eckThemePanel"
            title="Choose theme"
            aria-label="Choose theme, current <?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?>"
        >
            <svg class="eck-theme-toggle-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 3a9 9 0 1 0 0 18c.4 0 .7-.3.7-.7 0-.3-.1-.5-.3-.7a2.5 2.5 0 0 1 1.8-4.3h.3A5.5 5.5 0 0 0 21 10.2 9 9 0 0 0 12 3Zm-5.2 9.2a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Zm2-4.4a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Zm4.4 0a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Zm2.4 3.2a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Z"/>
            </svg>
        </button>
        <div class="eck-theme-panel" id="eckThemePanel" role="dialog" aria-label="Theme settings" hidden>
            <div class="eck-theme-panel-head">
                <div>
                    <strong>Appearance</strong>
                    <span>Choose a theme. It is saved for your next visit.</span>
                </div>
                <button type="button" class="eck-theme-panel-close" data-eck-theme-close aria-label="Close theme settings">&times;</button>
            </div>
            <?php app_theme_render_cards('compact'); ?>
        </div>
    </div>
    <?php
}

function app_theme_render_settings_section(): void
{
    ?>
    <section class="eck-theme-settings" id="themeSettings" aria-labelledby="themeSettingsTitle">
        <div class="eck-theme-settings-head">
            <h2 id="themeSettingsTitle"><i class="bi bi-palette2 me-2"></i>Theme</h2>
            <p>Pick the look of the portal. Dark is the default. Your choice is saved to this account.</p>
            <p class="mb-3">
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/admin/homepage-layouts.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-layout-wtf me-1"></i> Homepage layouts
                </a>
                <span class="small text-muted ms-2">Change the public website design (separate from colour theme).</span>
            </p>
        </div>
        <?php app_theme_render_cards('grid'); ?>
    </section>
    <?php
}
