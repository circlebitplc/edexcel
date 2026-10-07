<?php
declare(strict_types=1);

/**
 * Shared homepage chrome: head, foot, preview banner, login panels.
 */

function homepage_render_head(string $layoutKey): void
{
    if (!function_exists('seo_render_meta')) {
        require_once __DIR__ . '/seo.php';
    }
    $meta = homepage_layout_meta($layoutKey);
    $fonts = (string)($meta['fonts'] ?? 'Outfit:wght@400;600;700');
    $hp = is_array($GLOBALS['hp'] ?? null) ? $GLOBALS['hp'] : [];
    $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    $institute = trim((string)($hp['institute'] ?? 'Edexcel College'));
    $city = trim((string)($hp['city'] ?? 'Kandy, Sri Lanka'));
    $isPreview = !empty($GLOBALS['homepagePreviewMode']);
    $description = function_exists('seo_public_positioning_short')
        ? seo_public_positioning_short()
        : $institute . ' provides live online Pearson Edexcel IGCSE and International A Level classes for students worldwide, with physical classes at the Kandy campus.';
    $pageTitle = $institute . ' | Edexcel IGCSE & IAL Classes Online Worldwide';
    $canon = function_exists('seo_absolute_url') ? seo_absolute_url('/') : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '');
    $robots = $isPreview ? 'noindex, nofollow' : 'index, follow';
    ?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <?php
    seo_render_meta([
        'title' => $pageTitle,
        'description' => $description,
        'canonical' => $canon,
        'robots' => $robots,
        'image_alt' => $institute . ' campus and students',
    ]);
    ?>
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="facebook-domain-verification" content="lj58obmi3y9ebe39pmyhlflalh0qe6">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= e(function_exists('generate_csrf_token') ? generate_csrf_token() : '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=<?= e($fonts) ?>&amp;display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= e($fonts) ?>&amp;display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= e($fonts) ?>&amp;display=swap"></noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" referrerpolicy="no-referrer" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" referrerpolicy="no-referrer"></noscript>
    <link rel="stylesheet" href="<?= e(homepage_asset('assets/css/homepage-shared.css')) ?>">
    <link rel="stylesheet" href="<?= e(homepage_asset('assets/css/' . homepage_sanitize_layout($layoutKey) . '.css')) ?>">
    <?php if (homepage_sanitize_layout($layoutKey) === 'layout3'): ?>
    <link rel="stylesheet" href="<?= e(homepage_asset('assets/css/home.css')) ?>">
    <?php endif; ?>
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    <link rel="stylesheet" href="<?= e(homepage_asset('assets/css/homepage-theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(homepage_asset('assets/css/bottom-nav.css')) ?>">
    <?php
    require_once __DIR__ . '/ui_feedback.php';
    ui_feedback_css();
    ?>
    <?php
    if (!$isPreview) {
        $faqs = seo_homepage_faqs($pdo);
        $graph = [
            seo_organization_schema($pdo),
            seo_website_schema($pdo),
            seo_breadcrumb_schema([
                ['name' => 'Home', 'url' => $canon],
            ]),
        ];
        $faqSchema = seo_faq_schema($faqs);
        if ($faqSchema !== null) {
            $graph[] = $faqSchema;
        }
        seo_print_jsonld($graph);
    }
    ?>
</head>
    <?php
}

function homepage_render_preview_banner(): void
{
    // Always offer a keyboard skip target before layout chrome (all layouts call this).
    ?>
    <a class="hp-skip-link" href="#main-content">Skip to main content</a>
    <?php
    $layoutNow = function_exists('homepage_sanitize_layout')
        ? homepage_sanitize_layout((string)($GLOBALS['activeLayout'] ?? 'layout1'))
        : 'layout1';
    if ($layoutNow !== 'layout1') {
        homepage_render_account_actions();
    }
    if (empty($GLOBALS['homepagePreviewMode'])) {
        // Invisible landmark so skip-link always has a target even if a layout forgot id="main-content".
        echo '<span id="main-content" tabindex="-1" class="hp-main-anchor" aria-hidden="true"></span>';
        return;
    }
    $meta = homepage_layout_meta((string)($GLOBALS['activeLayout'] ?? 'layout1'));
    ?>
    <div class="hp-preview-banner" role="status">
        <strong>Preview:</strong>
        Layout <?= (int)$meta['number'] ?> — <?= e($meta['name']) ?>
        <span>This is not the live homepage.</span>
        <a href="<?= e(rtrim((string)BASE_URL, '/') . '/admin/homepage-layouts.php') ?>">Back to admin</a>
    </div>
    <span id="main-content" tabindex="-1" class="hp-main-anchor" aria-hidden="true"></span>
    <?php
}

function homepage_account_urls(): array
{
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
    return [
        'login' => $base . '/portal/login.php',
        'signup' => $base . '/student/register.php',
    ];
}

function homepage_render_account_actions(): void
{
    $urls = homepage_account_urls();
    ?>
    <nav class="hp-account-actions" aria-label="Account">
        <a class="hp-account-login" href="<?= e($urls['login']) ?>">Login</a>
        <a class="hp-account-signup" href="<?= e($urls['signup']) ?>">Sign Up</a>
    </nav>
    <?php
}

function homepage_render_foot(string $layoutKey): void
{
    $root = defined('HOMEPAGE_ROOT') ? HOMEPAGE_ROOT : dirname(__DIR__);
    if (function_exists('app_theme_render_picker')) {
        app_theme_render_picker('floating');
    }
    include $root . '/includes/public_ai_widget.php';
    if (function_exists('app_theme_js_link')) {
        app_theme_js_link();
    }
    $otpJs = $root . '/assets/js/login-otp.js';
    require_once __DIR__ . '/ui_feedback.php';
    ui_feedback_js();
    ?>
    <script src="<?= e(homepage_asset('assets/js/homepage-shared.js')) ?>" defer></script>
    <script src="<?= e(homepage_asset('assets/js/homepage-auth.js')) ?>" defer></script>
    <?php if (homepage_sanitize_layout($layoutKey) === 'layout3' && is_file($root . '/assets/js/home.js')): ?>
    <script src="<?= e(homepage_asset('assets/js/home.js')) ?>" defer></script>
    <?php endif; ?>
    <?php if (is_file($otpJs)): ?>
    <script src="<?= e(homepage_asset('assets/js/login-otp.js')) ?>"></script>
    <?php endif; ?>
    <script src="<?= e(homepage_asset('assets/js/' . homepage_sanitize_layout($layoutKey) . '.js')) ?>" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        window.homepageBoot && window.homepageBoot(<?= json_encode([
            'activeSection' => $GLOBALS['activeSection'] ?? 'home',
            'preview' => !empty($GLOBALS['homepagePreviewMode']),
            'layout' => homepage_sanitize_layout($layoutKey),
        ], JSON_UNESCAPED_SLASHES) ?>);
    });
    </script>
    <?php
    if (is_file($root . '/includes/visitor_tracking.php')) {
        require_once $root . '/includes/visitor_tracking.php';
        visitor_tracking_tag();
    }
    ?>
</body>
</html>
    <?php
}

function homepage_render_auth_role_tabs(string $activeRole): void
{
    $tabs = [
        'student' => ['hash' => 'student-login', 'label' => 'Student', 'icon' => 'fa-user-graduate'],
        'teacher' => ['hash' => 'teacher-login', 'label' => 'Staff', 'icon' => 'fa-user-tie'],
        'parent' => ['hash' => 'parent-login', 'label' => 'Parent', 'icon' => 'fa-users'],
    ];
    ?>
    <nav class="hp-auth-roles" aria-label="Choose portal">
        <?php foreach ($tabs as $key => $tab): ?>
        <button type="button"
                class="hp-auth-role<?= $activeRole === $key ? ' is-active' : '' ?>"
                data-auth-role="<?= e($key) ?>"
                data-auth-target="<?= e($tab['hash']) ?>"
                aria-pressed="<?= $activeRole === $key ? 'true' : 'false' ?>">
            <i class="fas <?= e($tab['icon']) ?>" aria-hidden="true"></i>
            <span><?= e($tab['label']) ?></span>
        </button>
        <?php endforeach; ?>
    </nav>
    <?php
}

function homepage_google_oauth_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(\Edexcel\Services\GoogleOAuthService::class)) {
            return false;
        }
        $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
        // Hide only when explicitly disabled; otherwise show so phone + Google are both offered.
        $raw = '';
        if (function_exists('recordings_env_or_setting')) {
            $raw = trim(recordings_env_or_setting($pdo, 'GOOGLE_OAUTH_ENABLED', 'google_oauth_enabled', ''));
        } elseif ($pdo instanceof PDO) {
            try {
                $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                $st->execute(['google_oauth_enabled']);
                $raw = trim((string)($st->fetchColumn() ?: ''));
            } catch (Throwable $e) {
                $raw = '';
            }
        }
        if ($raw === '0' || strtolower($raw) === 'false') {
            $ready = false;
        } else {
            $ready = true;
        }
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * Google sign-in button for student/parent homepage auth panels.
 */
function homepage_render_google_sign_in(string $intent, string $note = ''): void
{
    $intent = strtolower(trim($intent));
    if (!in_array($intent, ['student', 'parent'], true) || !homepage_google_oauth_ready()) {
        return;
    }
    $href = rtrim((string)BASE_URL, '/') . '/auth/google/start.php?intent=' . rawurlencode($intent);
    ?>
    <a class="hp-google-btn" href="<?= e($href) ?>">
        <i class="fab fa-google" aria-hidden="true"></i>
        <span>Continue with Google</span>
    </a>
    <?php if ($note !== ''): ?>
        <p class="hp-google-note"><?= e($note) ?></p>
    <?php endif; ?>
    <?php
}

function homepage_render_auth(string $variant = 'modal'): void
{
    $variant = $variant === 'inline' ? 'inline' : 'modal';
    $section = (string)($GLOBALS['activeSection'] ?? '');
    $openTeacher = ($section === 'teacher-login');
    $openStudent = ($section === 'student-login');
    $openParent = ($section === 'parent-login');
    $openAny = $openTeacher || $openStudent || $openParent;
    ?>
    <div class="hp-auth hp-auth-<?= e($variant) ?>" data-hp-auth="<?= e($variant) ?>"<?= $openAny && $variant === 'modal' ? ' data-open="1"' : '' ?>>
        <?php if ($variant === 'modal'): ?>
        <div class="hp-auth-backdrop" data-close-auth hidden></div>
        <?php endif; ?>
        <?php homepage_render_student_login($variant, $openStudent); ?>
        <?php homepage_render_teacher_login($variant, $openTeacher); ?>
        <?php homepage_render_parent_login($variant, $openParent); ?>
    </div>
    <?php
}

function homepage_render_teacher_login(string $variant, bool $open): void
{
    global $teacherLoginError, $teacherOtpNotice, $teacherOtpStep, $pdo;
    $hidden = ($variant === 'modal' && !$open) || ($variant === 'inline' && !$open && (($GLOBALS['activeSection'] ?? 'home') !== 'teacher-login'));
    $staffOtpPhoneShown = function_exists('current_staff_login_otp_phone')
        ? current_staff_login_otp_phone()
        : trim((string)($_SESSION['staff_login_otp_phone'] ?? ''));
    $staffVerifyingOtp = !empty($teacherOtpStep) && $staffOtpPhoneShown !== '';
    $teacherForgotMode = !$staffVerifyingOtp && (isset($_POST['teacher_otp_send']) || isset($_POST['teacher_otp_resend']));
    ?>
    <section class="hp-auth-panel<?= $variant === 'inline' ? ' app-section login-panel' : '' ?>" id="teacher-login" <?= $hidden && $variant === 'inline' ? 'hidden' : '' ?> <?= $variant === 'modal' && !$open ? 'hidden' : '' ?>>
        <?php if ($variant === 'modal'): ?>
        <button type="button" class="hp-auth-close" data-close-auth aria-label="Close">&times;</button>
        <?php endif; ?>
        <?php homepage_render_auth_role_tabs('teacher'); ?>
        <div class="hp-auth-icon"><i class="fas fa-user-tie"></i></div>
        <h2>Staff Portal</h2>
        <p>Teachers and administrators sign in here.</p>
        <?php if (!empty($teacherLoginError)): ?>
            <div class="hp-auth-alert" role="alert"><?= e($teacherLoginError) ?></div>
        <?php endif; ?>
        <?php if (!empty($teacherOtpNotice)): ?>
            <div class="hp-auth-ok" role="status"><?= e($teacherOtpNotice) ?></div>
        <?php endif; ?>
        <?php if ($staffVerifyingOtp): ?>
            <p>Enter the SMS code sent to <strong><?= e($staffOtpPhoneShown) ?></strong></p>
            <form method="POST" action="<?= e(homepage_form_action('teacher-login')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_username" value="<?= e($staffOtpPhoneShown) ?>">
                <input type="hidden" name="teacher_otp_verify" value="1">
                <label for="teacher_otp">SMS login code</label>
                <input class="otp-input js-otp-auto" type="text" id="teacher_otp" name="teacher_otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="------" required autofocus>
                <button type="submit" name="teacher_otp_verify" value="1">Verify OTP &amp; Sign In</button>
            </form>
            <form method="POST" action="<?= e(homepage_form_action('teacher-login')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_username" value="<?= e($staffOtpPhoneShown) ?>">
                <button type="submit" name="teacher_otp_resend" value="1" class="hp-btn-ghost">Resend SMS code</button>
            </form>
            <form method="POST" action="<?= e(homepage_form_action('teacher-login')) ?>">
                <?= csrf_field() ?>
                <button type="submit" name="teacher_otp_cancel" value="1" class="hp-btn-ghost">Back to password login</button>
            </form>
        <?php else: ?>
            <form method="POST" action="<?= e(homepage_form_action('teacher-login')) ?>" autocomplete="on" class="js-login-form" data-otp-mode="<?= $teacherForgotMode ? '1' : '0' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_login" value="1" class="js-password-flag"<?= $teacherForgotMode ? ' disabled' : '' ?>>
                <input type="hidden" name="teacher_otp_send" value="1" class="js-otp-flag"<?= $teacherForgotMode ? '' : ' disabled' ?>>
                <label for="teacher_username">Username</label>
                <input class="js-user-input" type="text" id="teacher_username" name="teacher_username" value="<?= e($_POST['teacher_username'] ?? '') ?>" autocomplete="username" placeholder="Enter your username" required>
                <div class="js-pass-wrap">
                    <label for="teacher_password">Password</label>
                    <input class="js-pass-input" type="password" id="teacher_password" name="teacher_password" autocomplete="current-password" placeholder="Enter your password"<?= $teacherForgotMode ? ' disabled' : ' required' ?>>
                </div>
                <div class="js-phone-wrap"<?= $teacherForgotMode ? '' : ' hidden' ?>>
                    <label for="teacher_phone">Mobile number</label>
                    <input class="js-phone-input" type="text" id="teacher_phone" name="teacher_phone" value="<?= e($_POST['teacher_phone'] ?? $_POST['teacher_username'] ?? '') ?>" inputmode="tel" placeholder="077XXXXXXX"<?= $teacherForgotMode ? ' required' : ' disabled' ?>>
                </div>
                <button type="submit" class="js-login-submit" data-login-label="Sign in as staff" data-otp-label="Send SMS OTP">
                    <?= $teacherForgotMode ? 'Send SMS OTP' : 'Sign in as staff' ?>
                </button>
                <p class="hp-auth-links">
                    <a href="#teacher-login" class="js-forgot-link"<?= $teacherForgotMode ? ' hidden' : '' ?>>Forgot password? Use SMS OTP</a>
                    <a href="#teacher-login" class="js-back-password"<?= $teacherForgotMode ? '' : ' hidden' ?>>Back to password login</a>
                </p>
            </form>
        <?php endif; ?>
    </section>
    <?php
}

function homepage_render_student_login(string $variant, bool $open): void
{
    global $studentLoginError, $studentLoginNotice;
    $hidden = ($variant === 'modal' && !$open) || ($variant === 'inline' && !$open && (($GLOBALS['activeSection'] ?? 'home') !== 'student-login'));
    ?>
    <section class="hp-auth-panel<?= $variant === 'inline' ? ' app-section login-panel' : '' ?>" id="student-login" <?= $hidden && $variant === 'inline' ? 'hidden' : '' ?> <?= $variant === 'modal' && !$open ? 'hidden' : '' ?>>
        <?php if ($variant === 'modal'): ?>
        <button type="button" class="hp-auth-close" data-close-auth aria-label="Close">&times;</button>
        <?php endif; ?>
        <?php homepage_render_auth_role_tabs('student'); ?>
        <div class="hp-auth-icon hp-auth-icon-student"><i class="fas fa-user-graduate"></i></div>
        <h2>Student Portal</h2>
        <p>Sign in with your Google account. Phone and SMS/OTP login are no longer used.</p>
        <?php if (!empty($studentLoginNotice)): ?>
            <div class="hp-auth-alert" role="status"><?= e($studentLoginNotice) ?></div>
        <?php endif; ?>
        <?php if (!empty($studentLoginError)): ?>
            <div class="hp-auth-alert" role="alert"><?= e($studentLoginError) ?></div>
        <?php endif; ?>
        <?php if (homepage_google_oauth_ready()): ?>
            <?php homepage_render_google_sign_in('student'); ?>
        <?php else: ?>
            <div class="hp-auth-alert" role="alert">Google sign-in is temporarily unavailable. Please contact the college office.</div>
        <?php endif; ?>
        <p class="hp-auth-links">
            <a href="<?= e(rtrim((string)BASE_URL, '/') . '/portal/login.php?role=student') ?>">Open student portal</a>
        </p>
    </section>
    <?php
}

function homepage_render_parent_login(string $variant, bool $open): void
{
    global $parentLoginError, $parentLoginNotice;
    $hidden = ($variant === 'modal' && !$open) || ($variant === 'inline' && !$open && (($GLOBALS['activeSection'] ?? 'home') !== 'parent-login'));
    ?>
    <section class="hp-auth-panel<?= $variant === 'inline' ? ' app-section login-panel' : '' ?>" id="parent-login" <?= $hidden && $variant === 'inline' ? 'hidden' : '' ?> <?= $variant === 'modal' && !$open ? 'hidden' : '' ?>>
        <?php if ($variant === 'modal'): ?>
        <button type="button" class="hp-auth-close" data-close-auth aria-label="Close">&times;</button>
        <?php endif; ?>
        <?php homepage_render_auth_role_tabs('parent'); ?>
        <div class="hp-auth-icon hp-auth-icon-parent"><i class="fas fa-users"></i></div>
        <h2>Parent Portal</h2>
        <p>Sign in with your Google account. WhatsApp OTP login is no longer used.</p>
        <?php if (!empty($parentLoginError)): ?>
            <div class="hp-auth-alert" role="alert"><?= e($parentLoginError) ?></div>
        <?php endif; ?>
        <?php if (!empty($parentLoginNotice)): ?>
            <div class="hp-auth-ok" role="status"><?= e($parentLoginNotice) ?></div>
        <?php endif; ?>
        <?php if (homepage_google_oauth_ready()): ?>
            <?php homepage_render_google_sign_in(
                'parent',
                'Parent access to student information still requires college verification after Google sign-in.'
            ); ?>
        <?php else: ?>
            <div class="hp-auth-alert" role="alert">Google sign-in is temporarily unavailable. Please contact the college office.</div>
        <?php endif; ?>
        <p class="hp-auth-links">
            <a href="<?= e(rtrim((string)BASE_URL, '/') . '/portal/login.php?role=parent') ?>">Open parent portal</a>
        </p>
    </section>
    <?php
}

function homepage_render_site_footer(array $hp): void
{
    $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    $contact = function_exists('college_contact') ? college_contact($pdo) : [
        'name' => (string)($hp['institute'] ?? 'Edexcel College'),
        'email' => 'info@edexcel.college',
        'phone' => '+94 78 585 8585',
        'phone_tel' => '+94785858585',
        'whatsapp' => '94785858585',
        'address' => "No 83 Katugatota Road, Kandy\nSri Lanka",
        'maps_url' => 'https://maps.app.goo.gl/1FJE2mQ5eR1HijsbA',
        'maps_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.45!2d80.6346098!3d7.3050896!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae367d64dd20f49%3A0x3539c01e3bc33f01!2s83%20Katugastota%20Rd%2C%20Kandy!5e0!3m2!1sen!2slk!4v1720000000000!5m2!1sen!2slk',
        'hours' => 'Monday–Friday 8:00–18:00 · Saturday 8:00–14:00',
    ];
    $name = e((string)($hp['institute'] ?? $contact['name']));
    $base = rtrim((string)BASE_URL, '/');
    $mapsUrl = (string)($contact['maps_url'] ?? 'https://maps.app.goo.gl/1FJE2mQ5eR1HijsbA');
    $mapsEmbed = (string)($contact['maps_embed_url'] ?? 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.45!2d80.6346098!3d7.3050896!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae367d64dd20f49%3A0x3539c01e3bc33f01!2s83%20Katugastota%20Rd%2C%20Kandy!5e0!3m2!1sen!2slk!4v1720000000000!5m2!1sen!2slk');
    ?>
    <footer class="hp-site-footer" id="site-contact">
        <div class="hp-site-footer-inner hp-footer-grid">
            <div>
                <strong><?= $name ?></strong>
                <p>Pearson Edexcel IGCSE and International A Level classes. Around 90% are live online for students worldwide. Physical classes are at the Kandy campus in Sri Lanka. Not affiliated with Pearson Education Ltd.</p>
                <p class="hp-footer-address"><a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer"><?= nl2br(e($contact['address'])) ?></a></p>
                <p><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></p>
                <p><a href="tel:<?= e($contact['phone_tel']) ?>"><?= e($contact['phone']) ?></a>
                    · <a href="https://wa.me/<?= e($contact['whatsapp']) ?>" rel="noopener">WhatsApp</a></p>
                <p><?= e($contact['hours']) ?></p>
            </div>
            <div class="hp-footer-map">
                <iframe
                    title="Edexcel College location on Google Maps"
                    src="<?= e($mapsEmbed) ?>"
                    width="600"
                    height="350"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
            <nav aria-label="Footer">
                <a href="<?= e($base . '/about') ?>">About</a>
                <a href="<?= e($base . '/pearson-exams') ?>">Pearson Exams</a>
                <a href="<?= e($base . '/edexcel-classes') ?>">Edexcel Classes</a>
                <a href="<?= e($base . '/edexcel-o-level') ?>">IGCSE / O Level</a>
                <a href="<?= e($base . '/edexcel-a-level') ?>">A Level / IAL</a>
                <a href="<?= e($base . '/exam-preparation') ?>">Exam Preparation</a>
                <a href="<?= e($base . '/resources') ?>">Resources</a>
                <a href="<?= e($base . '/glossary') ?>">Glossary</a>
                <a href="<?= e($base . '/locations/kandy') ?>">Kandy Campus</a>
                <a href="<?= e($base . '/faq') ?>">FAQ</a>
                <a href="<?= e($base . '/contact') ?>">Contact</a>
                <a href="<?= e($base . '/teachers/') ?>">Teachers</a>
                <a href="<?= e($base . '/student/register.php') ?>">Admissions</a>
                <a href="<?= e($base . '/terms') ?>">Terms and Conditions</a>
                <a href="<?= e($base . '/privacy-policy') ?>">Privacy Policy</a>
                <a href="<?= e($base . '/refund-policy') ?>">Refund Policy</a>
            </nav>
        </div>
    </footer>
    <?php
}

function homepage_teacher_avatar(array $teacher, string $class = ''): void
{
    $url = homepage_photo_url($teacher);
    $name = (string)($teacher['name'] ?? 'Teacher');
    $alt = trim((string)($teacher['photo_alt'] ?? ''));
    if ($alt === '') {
        $alt = $name;
    }
    $color = (string)($teacher['avatar_color'] ?? '#5161ce');
    $initials = e((string)($teacher['initials'] ?? homepage_initials($name)));
    if ($url !== '') {
        echo '<img class="' . e($class) . '" src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy">';
        return;
    }
    echo '<span class="' . e($class) . ' hp-avatar-fallback" style="background:' . e($color) . '">' . $initials . '</span>';
}

function homepage_render_teacher_rank(array $teacher): void
{
    $n = (int)($teacher['student_count'] ?? 0);
    $pct = (int)($teacher['profile_percent'] ?? 0);
    $students = $n === 1 ? '1 student' : $n . ' students';
    echo '<span class="hp-teacher-rank">' . e($students) . ' · Profile ' . $pct . '%</span>';
}

/**
 * Render the 2026 SaaS-style floating bottom navigation bar.
 * Matches the reference design with dark charcoal container and lighter gray active pill.
 */
function homepage_render_bottom_nav(string $activeItem = 'home', ?string $context = null): void
{
    $isSignedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['role']);
    $userRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');

    // Resolve profile/account destination based on auth state
    $profileUrl = match($userRole) {
        'admin' => $base . '/dashboard.php',
        'teacher' => $base . '/teachers/index.php',
        'student' => $base . '/student/dashboard.php',
        'parent' => $base . '/parent/home.php',
        default => $base . '/login.php',
    };

    $profileTitle = $isSignedIn ? 'My Account' : 'Sign in / Portal';
    $profileLabel = $isSignedIn ? 'Account' : 'Profile';
    $profileAction = $isSignedIn ? 'href="' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '"' : 'type="button" data-open-auth="login"';

    $items = [
        [
            'key' => 'home',
            'tag' => 'a',
            'href' => '#home',
            'icon' => 'fas fa-house',
            'label' => 'Home',
            'title' => 'Home',
        ],
        [
            'key' => 'intro',
            'tag' => 'a',
            'href' => '#intro',
            'icon' => 'fas fa-chart-line',
            'label' => 'Analytics',
            'title' => 'Analytics & Statistics',
        ],
        [
            'key' => 'programmes',
            'tag' => 'a',
            'href' => '#programmes',
            'icon' => 'fas fa-book-open',
            'label' => 'Courses',
            'title' => 'Programmes & Courses',
        ],
        [
            'key' => 'teachers',
            'tag' => 'a',
            'href' => '#teachers',
            'icon' => 'fas fa-chalkboard-user',
            'label' => 'Classes',
            'title' => 'Classes & Teachers',
        ],
        [
            'key' => 'faq',
            'tag' => 'a',
            'href' => '#faq',
            'icon' => 'fas fa-circle-question',
            'label' => 'Help',
            'title' => 'Help & FAQ',
        ],
        [
            'key' => 'login',
            'tag' => $isSignedIn ? 'a' : 'button',
            'attrs' => $profileAction,
            'icon' => 'fas fa-user',
            'label' => $profileLabel,
            'title' => $profileTitle,
        ],
    ];
    ?>
    <nav class="app-bottom-nav l1-capsule" aria-label="Quick navigation" data-bottom-nav data-l1-capsule>
        <?php foreach ($items as $item): ?>
            <?php
                $isActive = ($activeItem === $item['key']);
                $tag = $item['tag'];
                $attrs = $item['attrs'] ?? ('href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '"');
                $activeClass = $isActive ? ' is-active' : '';
                $ariaCurrent = ($isActive && $tag === 'a') ? ' aria-current="page"' : '';
            ?>
            <<?= $tag ?> class="app-bottom-nav-item l1-capsule-item<?= $activeClass ?>"
                      data-cap="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>"
                      data-l1-cap="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>"
                      title="<?= $item['title'] ?>"
                      <?= $attrs ?><?= $ariaCurrent ?>>
                <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <span class="app-bottom-nav-label l1-capsule-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </nav>
    <?php
}
