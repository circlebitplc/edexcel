<?php
// ============================================================
// includes/header.php
// ============================================================

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   PAGE INFORMATION
   ============================================================ */

$current_page =
    basename($_SERVER['PHP_SELF']);

$current_dir =
    basename(dirname($_SERVER['PHP_SELF']));


/* ============================================================
   USER ROLES
   ============================================================ */

$is_admin =
    is_admin();

$is_teacher =
    is_teacher();

$is_student =
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'student';


/* ============================================================
   USER DISPLAY / ROLE LABEL
============================================================ */

$user_display_name = trim((string)($_SESSION['username'] ?? 'User'));
if ($is_student) {
    $studentName = trim((string)($_SESSION['student_full_name'] ?? ''));
    if ($studentName === '' && isset($pdo) && $pdo instanceof PDO) {
        try {
            $nameStmt = $pdo->prepare('SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1');
            $nameStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
            $studentName = trim((string)($nameStmt->fetchColumn() ?: ''));
            if ($studentName !== '') {
                $_SESSION['student_full_name'] = $studentName;
            }
        } catch (Throwable $e) {
            $studentName = '';
        }
    }
    if ($studentName !== '') {
        $user_display_name = $studentName;
    }
}

$user_role_label = $is_admin
    ? 'Administrator'
    : ($is_teacher ? 'Teacher' : ($is_student ? 'Student' : 'User'));

$user_role_icon = $is_admin
    ? 'bi-shield-check'
    : ($is_teacher ? 'bi-person-workspace' : ($is_student ? 'bi-mortarboard' : 'bi-person'));

$headerUnreadSms = 0;
if ($is_admin) {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        @require_once __DIR__ . '/../config/database.php';
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmtUnreadSms = $pdo->query('SELECT COUNT(*) FROM incoming_sms WHERE is_read = 0');
            $headerUnreadSms = (int)($stmtUnreadSms->fetchColumn() ?: 0);
        } catch (Throwable) {
            $headerUnreadSms = 0;
        }
    }
}


/* ============================================================
   ACTIVE DROPDOWN HELPER
   ============================================================ */

if (!function_exists('is_active_dropdown')) {

    function is_active_dropdown($pages)
    {
        global $current_page;

        foreach ($pages as $page) {

            if (
                strpos(
                    $current_page,
                    $page
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }
}


/* ============================================================
   CURRENT APPEARANCE THEME
   ============================================================ */

$app_theme = function_exists('app_theme_current')
    ? app_theme_current(isset($pdo) && $pdo instanceof PDO ? $pdo : null)
    : 'dark';
$dark_mode_enabled = function_exists('app_theme_family')
    ? app_theme_family($app_theme) === 'dark'
    : $app_theme !== 'light';


/* ============================================================
   ASSET PATHS
   ============================================================ */

$dashboard_css =
    __DIR__ .
    '/../assets/css/dashboard.css';

$dashboard_js =
    __DIR__ .
    '/../assets/js/dashboard.js';


/* ============================================================
   PAGE THEME
   ============================================================ */

$html_theme = $dark_mode_enabled ? 'dark' : 'light';

?>
<!DOCTYPE html>

<html
    lang="en"
    <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs(isset($pdo) && $pdo instanceof PDO ? $pdo : null) : 'data-theme="dark" data-bs-theme="dark"' ?>
>

<head>

    <meta charset="UTF-8">
    <meta name="facebook-domain-verification" content="lj58obmi3y9ebe39pmyhlflalh0qe6">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>

    <meta
        name="csrf-token"
        content="<?= htmlspecialchars(
            generate_csrf_token(),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <?php
    $seoPageTitle = trim((string)($page_title ?? ''));
    if ($seoPageTitle === '') {
        $seoPageTitle = 'Edexcel College Portal';
    }
    $seoPageDescription = trim((string)($meta_description ?? $page_description ?? ''));
    if ($seoPageDescription === '') {
        $seoPageDescription = 'Edexcel College timetable and campus portal for students, parents, and staff.';
    }
    $seoPageRobots = trim((string)($meta_robots ?? $page_robots ?? 'noindex, nofollow'));
    $seoCanonical = trim((string)($canonical_url ?? ''));
    ?>
    <title><?= htmlspecialchars($seoPageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <meta
        name="theme-color"
        content="<?= htmlspecialchars(function_exists('app_theme_color') ? app_theme_color($app_theme) : '#10131b', ENT_QUOTES, 'UTF-8') ?>"
    >
    <meta name="color-scheme" content="dark light">

    <meta
        name="description"
        content="<?= htmlspecialchars($seoPageDescription, ENT_QUOTES, 'UTF-8') ?>"
    >
    <meta name="robots" content="<?= htmlspecialchars($seoPageRobots, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($seoCanonical !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php
    // Optional Open Graph / Twitter for public indexable portal pages (e.g. teacher profiles).
    $seoSocialEnabled = !empty($seo_social);
    if ($seoSocialEnabled) {
        $seoOgType = trim((string)($og_type ?? 'profile'));
        if ($seoOgType === '') {
            $seoOgType = 'profile';
        }
        $seoOgImage = trim((string)($og_image ?? ''));
        if ($seoOgImage === '' && function_exists('seo_default_image_url')) {
            $seoOgImage = seo_default_image_url();
        } elseif ($seoOgImage === '') {
            $seoOgImage = rtrim((string)BASE_URL, '/') . '/assets/icons/icon-512.png';
        }
        $seoOgImageAlt = trim((string)($og_image_alt ?? $seoPageTitle));
        $seoOgUrl = $seoCanonical !== '' ? $seoCanonical : rtrim((string)BASE_URL, '/') . '/';
        ?>
    <meta property="og:site_name" content="Edexcel College">
    <meta property="og:locale" content="en_LK">
    <meta property="og:type" content="<?= htmlspecialchars($seoOgType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoPageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoPageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seoOgUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seoOgImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($seoOgImageAlt, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoPageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoPageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($seoOgImage, ENT_QUOTES, 'UTF-8') ?>">
        <?php
    }
    if (!empty($head_json_ld) && is_array($head_json_ld) && function_exists('seo_print_jsonld')) {
        seo_print_jsonld($head_json_ld);
    }
    ?>


    <!-- ========================================================
         BOOTSTRAP
    ========================================================= -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- ========================================================
         CONSOLIDATED PORTAL CSS
    ========================================================= -->

    <?php if (file_exists($dashboard_css)): ?>

        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/dashboard.css?v=<?= filemtime($dashboard_css) ?>"
        >
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/payments.css?v=<?= is_file(__DIR__ . '/../assets/css/payments.css') ? filemtime(__DIR__ . '/../assets/css/payments.css') : '1' ?>"
        >
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/theme-fix.css?v=<?= is_file(__DIR__ . '/../assets/css/theme-fix.css') ? filemtime(__DIR__ . '/../assets/css/theme-fix.css') : '1' ?>"
        >
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/app-rail.css?v=<?= is_file(__DIR__ . '/../assets/css/app-rail.css') ? filemtime(__DIR__ . '/../assets/css/app-rail.css') : '1' ?>"
        >
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/app-dialog.css?v=<?= is_file(__DIR__ . '/../assets/css/app-dialog.css') ? filemtime(__DIR__ . '/../assets/css/app-dialog.css') : '1' ?>"
        >
        <?php
        require_once __DIR__ . '/ui_feedback.php';
        ui_feedback_css();
        ?>
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/responsive.css?v=<?= is_file(__DIR__ . '/../assets/css/responsive.css') ? filemtime(__DIR__ . '/../assets/css/responsive.css') : '1' ?>"
        >
        <?php
        $student_portal_css = __DIR__ . '/../assets/css/student-portal.css';
        $student_features_css = __DIR__ . '/../assets/css/student-dashboard-features.css';
        if ($is_student && is_file($student_portal_css)):
        ?>
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/student-portal.css?v=<?= filemtime($student_portal_css) ?>"
        >
        <?php endif; ?>
        <?php if ($is_student && is_file($student_features_css)): ?>
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/student-dashboard-features.css?v=<?= filemtime($student_features_css) ?>"
        >
        <?php endif; ?>
        <?php
        $courso_css = __DIR__ . '/../assets/css/courso.css';
        if ($is_student && is_file($courso_css)):
        ?>
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/courso.css?v=<?= filemtime($courso_css) ?>"
        >
        <?php endif; ?>
        <?php
        $online_lesson_css = __DIR__ . '/../assets/css/online-lesson.css';
        if (in_array($current_page, ['lesson.php', 'online_lesson.php', 'lesson_preview.php'], true) && is_file($online_lesson_css)):
        ?>
        <link
            rel="stylesheet"
            href="<?= BASE_URL ?>assets/css/online-lesson.css?v=<?= filemtime($online_lesson_css) ?>"
        >
        <?php endif; ?>

    <?php endif; ?>

    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>


 
<style>
/* ============================================================
   HEADER / NAVIGATION UX IMPROVEMENTS
============================================================ */

.portal-navbar {
    position: sticky;
    top: 0;
    z-index: 1030;
    box-shadow: 0 2px 14px rgba(0,0,0,.12);
}

.portal-navbar .navbar-brand {
    letter-spacing: -.02em;
}

.portal-navbar .brand-mark {
    font-size: 1.25rem;
    line-height: 1;
}

.portal-navbar .nav-link {
    border-radius: 9px;
    margin: 2px 3px;
    padding: .55rem .72rem;
    transition: background-color .15s ease, color .15s ease;
}

.portal-navbar .nav-link:hover,
.portal-navbar .nav-link:focus {
    background: rgba(255,255,255,.08);
}

.portal-navbar .nav-link.active {
    background: rgba(255,255,255,.14);
    font-weight: 700;
}

.portal-navbar .dropdown-menu {
    border: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 12px 30px rgba(0,0,0,.14);
    border-radius: 12px;
    padding: .45rem;
}

.portal-navbar .dropdown-item {
    border-radius: 8px;
    padding: .55rem .7rem;
}

.portal-navbar .dropdown-item:hover,
.portal-navbar .dropdown-item:focus {
    background: var(--bs-tertiary-bg);
}

.portal-navbar .dark-toggle .form-check {
    margin-bottom: 0;
}

.portal-navbar .dark-toggle .form-check-input {
    cursor: pointer;
}

.portal-navbar .dark-toggle .form-check-label {
    cursor: pointer;
}

/* Sidebar hierarchy */
.sidebar {
    position: sticky;
    top: 70px;
    height: calc(100vh - 70px);
    overflow-y: auto;
    scrollbar-width: thin;
}

.sidebar .nav-link {
    border-radius: 9px;
    margin-bottom: 3px;
    padding: .58rem .72rem;
    transition: background-color .15s ease, transform .15s ease;
}

.sidebar .nav-link:hover {
    transform: translateX(2px);
}

.sidebar .nav-link.active {
    font-weight: 700;
    box-shadow: inset 3px 0 0 var(--bs-primary);
}

.sidebar .nav-link i {
    width: 1.35rem;
    text-align: center;
    margin-right: .3rem;
}

.sidebar .nav .nav {
    border-left: 1px solid var(--bs-border-color);
    margin-left: .7rem !important;
    padding-left: .35rem;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(127,127,127,.3);
    border-radius: 99px;
}

/* ============================================================
   STUDENT PORTAL TABS
============================================================ */













@media (max-width: 767.98px) {
    

    

    
}

/* Breadcrumb */
.breadcrumb-nav {
    margin-bottom: 1rem;
}

.breadcrumb {
    padding: .55rem .75rem;
    margin-bottom: 0;
    border-radius: 10px;
    background: var(--bs-tertiary-bg);
    font-size: .84rem;
}

/* Mobile */
@media (max-width: 991.98px) {
    .portal-navbar .navbar-collapse {
        max-height: calc(100vh - 70px);
        overflow-y: auto;
        padding-bottom: .75rem;
    }

    .portal-navbar .navbar-nav .nav-link {
        margin: 2px 0;
    }
}
</style>


<style id="student-portal-layout-fix">
.student-sidebar-hidden{display:none!important}
.student-main-fullwidth{
    flex:0 0 100%!important;
    width:100%!important;
    max-width:100%!important;
    margin-left:0!important;
}


.student-tab{
    flex:0 0 auto;
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:10px 14px;
    border-radius:11px;
    text-decoration:none;
    font-weight:600;
    color:var(--bs-secondary-color,#6c757d);
    white-space:nowrap
}
.student-tab:hover{
    background:var(--bs-tertiary-bg,#f1f3f5);
    color:var(--bs-emphasis-color,#212529)
}
.student-tab.active{
    color:#fff;
    background:#0d6efd;
    box-shadow:0 4px 12px rgba(13,110,253,.22)
}
@media(max-width:700px){
    .student-tab{padding:9px 11px;font-size:13px}
    .student-main-fullwidth{
        padding-left:12px!important;
        padding-right:12px!important;
    }
}
</style>


<style id="global-theme-toggle-css">
.edexcel-theme-item {
    display: flex;
    align-items: center;
}

.edexcel-theme-toggle {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 0 !important;
    background: transparent !important;
    cursor: pointer;
    white-space: nowrap;
}

.edexcel-theme-toggle:hover {
    opacity: .8;
}

.edexcel-theme-toggle i {
    font-size: 1rem;
}

@media (max-width: 768px) {
    #globalThemeLabel {
        display: none;
    }

    .edexcel-theme-toggle {
        padding-left: 8px !important;
        padding-right: 8px !important;
    }
}
</style>




</head>


<body class="has-app-rail<?= !empty($is_student) ? ' student-user' : '' ?>">


<!-- ============================================================
     NO GLOBAL PAGE LOADER
     ============================================================

     The previous system contained:

     <div class="loader-overlay" id="loaderOverlay">

     This has intentionally been removed because it was causing
     the permanent buffering circle visible on the dashboard.
============================================================= -->


<!-- ============================================================
     TOAST CONTAINER
============================================================= -->

<div
    class="toast-container"
    id="toastContainer"
    aria-live="polite"
    aria-atomic="true"
></div>


<!-- ============================================================
     MAIN NAVBAR
============================================================= -->

<nav
    class="
        navbar
        navbar-expand-lg
        navbar-dark
        bg-dark
        portal-navbar
    "
>

    <div class="container-fluid">


        <!-- BRAND -->

        <a
            class="navbar-brand d-flex align-items-center gap-2"
            href="<?= $is_student
                ? BASE_URL . 'student/dashboard.php'
                : (($is_admin || $is_teacher)
                    ? BASE_URL . 'dashboard.php'
                    : BASE_URL) ?>"
            aria-label="Edexcel College"
        >

            <span class="brand-mark" aria-hidden="true">📚</span>

            <span>
                <strong>Edexcel</strong>
                <small class="d-none d-sm-inline text-white-50 ms-1">College</small>
            </span>

        </a>


        <div class="navbar-actions">
            <?php if (!empty($is_admin)): ?>
                <a
                    href="<?= BASE_URL ?>admin/phone_contacts.php"
                    class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-1 me-1 <?= in_array($current_page, ['phone_contacts.php', 'phone_contact_view.php', 'phone_import_history.php'], true) ? 'active bg-white text-dark fw-semibold' : '' ?>"
                    id="navPhoneContactsBtn"
                    title="Phone Contacts & SMS"
                    style="min-height: 38px; border-radius: 10px; font-weight: 500; font-size: 0.85rem;"
                >
                    <i class="bi bi-telephone-fill text-warning"></i>
                    <span class="d-none d-sm-inline">Phone Contacts</span>
                </a>
            <?php endif; ?>
            <?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('nav'); } ?>
            <button
                class="app-rail-toggle"
                id="appRailToggle"
                type="button"
                aria-controls="appRail"
                aria-expanded="false"
                aria-label="Expand menu"
            >
                <span class="app-rail-toggle-grid" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </span>
            </button>
        </div>

        <!-- Deprecated legacy registry: inert and permanently hidden while old page markup is retired. -->
        <div class="collapse navbar-collapse d-none" id="navbarNav" aria-hidden="true" inert>


            <!-- ==================================================
                 LEFT NAVIGATION
            =================================================== -->

            <ul class="navbar-nav me-auto">


                <?php if (
                    $is_admin ||
                    $is_teacher
                ): ?>


                    <!-- DASHBOARD -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    $current_page ===
                                    'dashboard.php'
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>dashboard.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-speedometer2
                                "
                            ></i>

                            Dashboard

                        </a>

                    </li>


                    <?php if ($is_admin): ?>


                        <!-- ==================================================
                             ADMIN NAVIGATION
                        =================================================== -->


                        <!-- TIMETABLE -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'timetable'
                                        ) !== false &&
                                        $current_dir !==
                                        'reports'
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>timetable/index.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-calendar-event
                                    "
                                ></i>

                                Timetable

                            </a>

                        </li>


                        <!-- WEEKLY VIEW -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'weekly'
                                        ) !== false
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>timetable/weekly.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-calendar-week
                                    "
                                ></i>

                                Weekly View

                            </a>

                        </li>


                        <!-- EXAM / MOCK TIMETABLE -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        $current_page ===
                                        'exams.php'
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>campus/exams.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-journal-text
                                    "
                                ></i>

                                Exams

                            </a>

                        </li>


                        <!-- MANAGE -->

                        <li class="nav-item dropdown">

                            <a
                                class="
                                    nav-link
                                    dropdown-toggle
                                    <?= is_active_dropdown([
                                        'teachers',
                                        'subjects',
                                        'classes',
                                        'rooms'
                                    ])
                                        ? 'active'
                                        : '' ?>
                                "
                                href="#"
                                id="manageDropdown"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >

                                <i
                                    class="
                                        bi
                                        bi-grid-3x3-gap-fill
                                    "
                                ></i>

                                Manage

                            </a>


                            <ul
                                class="
                                    dropdown-menu
                                "
                                aria-labelledby="manageDropdown"
                            >

                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>teachers/index.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-people
                                            "
                                        ></i>

                                        Teachers

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>subjects/index.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-book
                                            "
                                        ></i>

                                        Subjects

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>classes/index.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-layers
                                            "
                                        ></i>

                                        Classes

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>rooms/index.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-door-open
                                            "
                                        ></i>

                                        Rooms

                                    </a>

                                </li>


                            </ul>

                        </li>


                        <!-- PAYMENTS -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'payments'
                                        ) !== false ||
                                        $current_page ===
                                        'payment_ledger.php'
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>timetable/payments.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-credit-card
                                    "
                                ></i>

                                Payments

                            </a>

                        </li>


                        <!-- REPORTS -->

                        <li class="nav-item dropdown">

                            <a
                                class="
                                    nav-link
                                    dropdown-toggle
                                    <?= is_active_dropdown([
                                        'revenue',
                                        'monthly',
                                        'yearly',
                                        'payment_ledger'
                                    ])
                                        ? 'active'
                                        : '' ?>
                                "
                                href="#"
                                id="reportsDropdown"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >

                                <i
                                    class="
                                        bi
                                        bi-graph-up-arrow
                                    "
                                ></i>

                                Reports

                            </a>


                            <ul
                                class="
                                    dropdown-menu
                                "
                                aria-labelledby="reportsDropdown"
                            >

                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>reports/revenue.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-currency-dollar
                                            "
                                        ></i>

                                        Revenue

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>reports/monthly.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-calendar-month
                                            "
                                        ></i>

                                        Monthly

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>reports/yearly.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-calendar-year
                                            "
                                        ></i>

                                        Yearly

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>timetable/payment_ledger.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-journal-text
                                            "
                                        ></i>

                                        Teacher Payment Ledger

                                    </a>

                                </li>


                            </ul>

                        </li>


                        <!-- MANAGEMENT -->

                        <li class="nav-item dropdown">

                            <a
                                class="
                                    nav-link
                                    dropdown-toggle
                                    <?= is_active_dropdown([
                                        'holidays',
                                        'recurring_manage',
                                        'teacher_schedule'
                                    ])
                                        ? 'active'
                                        : '' ?>
                                "
                                href="#"
                                id="mgmtDropdown"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >

                                <i
                                    class="
                                        bi
                                        bi-tools
                                    "
                                ></i>

                                Management

                            </a>


                            <ul
                                class="
                                    dropdown-menu
                                "
                                aria-labelledby="mgmtDropdown"
                            >

                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>timetable/holidays.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-calendar2-x
                                            "
                                        ></i>

                                        Holidays

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>timetable/recurring_manage.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-arrow-repeat
                                            "
                                        ></i>

                                        Recurring

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>timetable/teacher_schedule.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-person-video
                                            "
                                        ></i>

                                        Teacher Schedule

                                    </a>

                                </li>

                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/today.php">
                                        <i class="bi bi-sun"></i> Today’s classes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/waitlist.php">
                                        <i class="bi bi-hourglass-split"></i> Class waitlist
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/exams.php">
                                        <i class="bi bi-clipboard2-pulse"></i> Exam & mock timetable
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/official_exams.php">
                                        <i class="bi bi-calendar2-week"></i> Official exam timetable
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/official_exams.php">
                                        <i class="bi bi-calendar2-week"></i> Official exam timetable
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/attendance.php">
                                        <i class="bi bi-check2-circle"></i> Attendance
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/lesson_fees.php">
                                        <i class="bi bi-cash-coin"></i> Class fees
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/progress.php">
                                        <i class="bi bi-graph-up-arrow"></i> Marks
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/homework.php">
                                        <i class="bi bi-file-earmark-pdf"></i> Homework & papers
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/class_status.php">
                                        <i class="bi bi-exclamation-octagon"></i> Cancel / substitute
                                    </a>
                                </li>
                                <?php if (!empty($is_admin)): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>campus/fees.php">
                                        <i class="bi bi-wallet2"></i> Student fees
                                    </a>
                                </li>
                                <?php endif; ?>


                            </ul>

                        </li>


                        <!-- ADMIN -->

                        <li class="nav-item dropdown">

                            <a
                                class="
                                    nav-link
                                    dropdown-toggle
                                    <?= is_active_dropdown([
                                        'audit',
                                        'settings',
                                        'users',
                                        'students',
                                        'backup',
                                        'whatsapp_bot',
                                        'bulk_sms',
                                        'bulk_sms_history',
                                        'incoming_sms',
                                        'sms_logs',
                                        'phone_contacts',
                                        'phone_import_history',
                                        'sms_teacher_permissions'
                                    ])
                                        ? 'active'
                                        : '' ?>
                                "
                                href="#"
                                id="adminDropdown"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >

                                <i
                                    class="
                                        bi
                                        bi-shield-lock
                                    "
                                ></i>

                                Admin
                                <?php if (!empty($headerUnreadSms)): ?>
                                    <span class="badge bg-danger rounded-pill small ms-1 sms-unread-badge" style="font-size: 0.7rem;"><?= $headerUnreadSms ?></span>
                                <?php endif; ?>

                            </a>


                            <ul
                                class="
                                    dropdown-menu
                                "
                                aria-labelledby="adminDropdown"
                            >

                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/audit_log.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-clock-history
                                            "
                                        ></i>

                                        Audit Log

                                    </a>

                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/settings.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-gear
                                            "
                                        ></i>

                                        Settings

                                    </a>

                                </li>

                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/online_payments.php">
                                        <i class="bi bi-cash-stack"></i>
                                        Online payments
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/teacher_banks.php">
                                        <i class="bi bi-bank"></i>
                                        Teacher bank details
                                    </a>
                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/users.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-people
                                            "
                                        ></i>

                                        Users

                                    </a>

                                </li>

                                <li>
                                    <a
                                        class="dropdown-item <?= ($current_page === 'teacher_oauth_migration.php') ? 'active' : '' ?>"
                                        href="<?= BASE_URL ?>admin/teacher_oauth_migration.php"
                                    >
                                        <i class="bi bi-google text-danger"></i>
                                        Teacher Google Migration
                                    </a>
                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/students.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-person-video
                                            "
                                        ></i>

                                        Students

                                    </a>

                                </li>

                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/student_devices.php">
                                        <i class="bi bi-phone"></i>
                                        Devices
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/student_devices.php#emergency-requests">
                                        <i class="bi bi-bell"></i>
                                        Emergency devices
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/security_center.php">
                                        <i class="bi bi-shield-lock"></i>
                                        Security Center
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/protection.php">
                                        <i class="bi bi-shield-check"></i>
                                        Protection
                                    </a>
                                </li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/whatsapp_bot.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-whatsapp
                                            "
                                        ></i>

                                        WhatsApp Bot

                                    </a>

                                </li>

                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header text-uppercase small text-muted"><i class="bi bi-chat-square-dots"></i> SMS Management</h6></li>
                                <li>
                                    <a
                                        class="dropdown-item <?= $current_page === 'incoming_sms.php' ? 'active' : '' ?> d-flex justify-content-between align-items-center"
                                        href="<?= BASE_URL ?>admin/incoming_sms.php"
                                    >
                                        <span><i class="bi bi-inbox"></i> Incoming SMS</span>
                                        <?php if (!empty($headerUnreadSms)): ?>
                                            <span class="badge bg-danger rounded-pill sms-unread-badge"><?= $headerUnreadSms ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li>
                                    <a
                                        class="dropdown-item <?= in_array($current_page, ['phone_contacts.php', 'phone_contact_view.php']) ? 'active' : '' ?>"
                                        href="<?= BASE_URL ?>admin/phone_contacts.php"
                                    >
                                        <i class="bi bi-person-lines-fill"></i> Phone Contacts
                                    </a>
                                </li>
                                <li>
                                    <a
                                        class="dropdown-item <?= $current_page === 'phone_import_history.php' ? 'active' : '' ?>"
                                        href="<?= BASE_URL ?>admin/phone_import_history.php"
                                    >
                                        <i class="bi bi-clock-history"></i> Contact Import History
                                    </a>
                                </li>
                                <li>
                                    <a
                                        class="dropdown-item <?= $current_page === 'sms_teacher_permissions.php' ? 'active' : '' ?>"
                                        href="<?= BASE_URL ?>admin/sms_teacher_permissions.php"
                                    >
                                        <i class="bi bi-shield-lock"></i> Teacher SMS Permissions
                                    </a>
                                </li>
                                <li>
                                    <a
                                        class="dropdown-item <?= in_array($current_page, ['bulk_sms.php', 'bulk_sms_history.php']) ? 'active' : '' ?>"
                                        href="<?= BASE_URL ?>admin/bulk_sms.php"
                                    >
                                        <i class="bi bi-broadcast"></i> Bulk SMS
                                    </a>
                                </li>
                                <li>
                                    <a
                                        class="dropdown-item <?= $current_page === 'sms_logs.php' ? 'active' : '' ?>"
                                        href="<?= BASE_URL ?>admin/sms_logs.php"
                                    >
                                        <i class="bi bi-card-list"></i> SMS Logs
                                    </a>
                                </li>
                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/settings.php?tab=otp"
                                    >
                                        <i class="bi bi-gear"></i> Gateway Settings
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>


                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="<?= BASE_URL ?>admin/backup.php"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-database
                                            "
                                        ></i>

                                        Backup

                                    </a>

                                </li>


                            </ul>

                        </li>


                    <?php else: ?>


                        <!-- ==================================================
                             TEACHER NAVIGATION
                             Teachers get one timetable page only:
                             My Schedule.
                        =================================================== -->


                        <!-- MY PROFILE -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'teachers/profile.php'
                                        ) !== false
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>teachers/profile.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-person-badge
                                    "
                                ></i>

                                My Profile

                            </a>

                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= strpos($current_page, 'bank_details.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>teachers/bank_details.php">
                                <i class="bi bi-bank"></i>
                                Bank Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($current_page, 'online_earnings.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>teachers/online_earnings.php">
                                <i class="bi bi-wallet2"></i>
                                Teacher payments
                            </a>
                        </li>


                        <!-- MY SCHEDULE -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'teacher_schedule'
                                        ) !== false
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>timetable/teacher_schedule.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-calendar-week
                                    "
                                ></i>

                                My Schedule

                            </a>

                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/today.php"><i class="bi bi-sun"></i> Today</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/waitlist.php"><i class="bi bi-hourglass-split"></i> Waitlist</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/exams.php"><i class="bi bi-clipboard2-pulse"></i> Exams</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/attendance.php"><i class="bi bi-check2-circle"></i> Attendance</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/lesson_fees.php"><i class="bi bi-cash-coin"></i> Class fees</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/progress.php"><i class="bi bi-graph-up-arrow"></i> Marks</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/homework.php"><i class="bi bi-file-earmark-pdf"></i> Papers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>campus/class_status.php"><i class="bi bi-exclamation-octagon"></i> Cancel class</a>
                        </li>


                        <!-- MY PAYMENTS -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'payments'
                                        ) !== false ||
                                        $current_page ===
                                        'payment_ledger.php'
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>timetable/payments.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-credit-card
                                    "
                                ></i>

                                My Payments

                            </a>

                        </li>


                        <!-- SETTINGS -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        $current_page ===
                                        'settings.php'
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>settings.php"
                            >

                                <i class="bi bi-gear"></i>

                                Settings

                            </a>

                        </li>


                    <?php endif; ?>


                <?php elseif ($is_student): ?>


                    <!-- ==================================================
                         STUDENT NAVIGATION
                    =================================================== -->

                    <!-- OVERVIEW -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'dashboard.php'
                                && $current_dir === 'student'
                                && (
                                    empty($_GET['tab'])
                                    || ($_GET['tab'] ?? '') === 'overview'
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=overview"
                        >
                            <i class="bi bi-house-door"></i>
                            Overview
                        </a>

                    </li>


                    <!-- TIMETABLE -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'dashboard.php'
                                && $current_dir === 'student'
                                && (
                                    ($_GET['tab'] ?? '') === 'timetable'
                                    || (
                                        isset($_SERVER['REQUEST_URI'])
                                        && strpos(
                                            $_SERVER['REQUEST_URI'],
                                            '#student-timetable'
                                        ) !== false
                                    )
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=timetable"
                        >
                            <i class="bi bi-calendar3"></i>
                            Timetable
                        </a>

                    </li>


                    <!-- EXAMS -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'dashboard.php'
                                && $current_dir === 'student'
                                && (
                                    ($_GET['tab'] ?? '') === 'exams'
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=exams"
                        >
                            <i class="bi bi-journal-text"></i>
                            Exams
                        </a>

                    </li>


                    <!-- MY CLASSES -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'dashboard.php'
                                && $current_dir === 'student'
                                && (
                                    ($_GET['tab'] ?? '') === 'classes'
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=classes"
                        >
                            <i class="bi bi-collection"></i>
                            My Classes
                        </a>

                    </li>


                    <!-- TEACHERS -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'dashboard.php'
                                && $current_dir === 'student'
                                && (($_GET['tab'] ?? '') === 'teachers')
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=teachers"
                        >
                            <i class="bi bi-person-badge"></i>
                            Teachers
                        </a>

                    </li>


                    <!-- JOIN CLASS -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'join_class.php'
                                || (
                                    $current_page === 'dashboard.php'
                                    && $current_dir === 'student'
                                    && ($_GET['tab'] ?? '') === 'join'
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=join"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Join Class
                        </a>

                    </li>


                    <!-- SERVICES -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'services.php'
                                || (
                                    $current_page === 'dashboard.php'
                                    && $current_dir === 'student'
                                    && ($_GET['tab'] ?? '') === 'services'
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=services"
                        >
                            <i class="bi bi-grid"></i>
                            Services
                        </a>

                    </li>


                    <!-- SETTINGS -->

                    <li class="nav-item">

                        <a
                            class="nav-link <?= (
                                $current_page === 'settings.php'
                                || (
                                    $current_page === 'dashboard.php'
                                    && $current_dir === 'student'
                                    && ($_GET['tab'] ?? '') === 'settings'
                                )
                            ) ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=settings"
                        >
                            <i class="bi bi-gear"></i>
                            Settings
                        </a>

                    </li>


                    <?php endif; ?>


                        <li>

                            <hr
                                class="
                                    dropdown-divider
                                "
                            >

                        </li>


                        <li>

                            <a
                                class="dropdown-item"
                                href="<?= BASE_URL ?>logout.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-box-arrow-right
                                    "
                                ></i>

                                Logout

                            </a>

                        </li>


                    </ul>

                </li>


</ul>


        </div>

    </div>


</nav>

<?php include __DIR__ . '/app_menu.php'; ?>

<!-- ============================================================
     MAIN PAGE CONTAINER
============================================================= -->

<div class="container-fluid portal-container">

    <div class="row">


        <!-- ====================================================
             SIDEBAR
        ===================================================== -->

        <nav
            class="
                col-md-2
                d-none
                d-md-block
                sidebar
                p-3
                <?= $is_student ? "student-sidebar-hidden" : "" ?>
            "
            aria-label="Main navigation"
        >

            <ul
                class="
                    nav
                    flex-column
                "
            >


                <?php if ($is_admin): ?>


                    <!-- DASHBOARD -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    $current_page ===
                                    'dashboard.php'
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>dashboard.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-speedometer2
                                "
                            ></i>

                            Dashboard

                        </a>

                    </li>


                    <!-- TIMETABLE -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'timetable'
                                    ) !== false &&
                                    $current_dir !==
                                    'reports'
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/index.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar-event
                                "
                            ></i>

                            Timetable

                        </a>

                    </li>


                    <!-- WEEKLY -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'weekly'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/weekly.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar-week
                                "
                            ></i>

                            Weekly View

                        </a>

                    </li>


                    <!-- EXAMS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    $current_page ===
                                    'exams.php'
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>campus/exams.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-journal-text
                                "
                            ></i>

                            Exams

                        </a>

                    </li>


                    <!-- TEACHERS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'teachers'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>teachers/index.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-people
                                "
                            ></i>

                            Teachers

                        </a>

                    </li>


                    <!-- SUBJECTS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'subjects'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>subjects/index.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-book
                                "
                            ></i>

                            Subjects

                        </a>

                    </li>


                    <!-- CLASSES -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'classes'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>classes/index.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-layers
                                "
                            ></i>

                            Classes

                        </a>

                    </li>


                    <!-- ROOMS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'rooms'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>rooms/index.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-door-open
                                "
                            ></i>

                            Rooms

                        </a>

                    </li>


                    <!-- PAYMENTS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'payments'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/payments.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-credit-card
                                "
                            ></i>

                            Payments

                        </a>

                    </li>


                    <!-- REPORTS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'reports'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="#"
                        >

                            <i
                                class="
                                    bi
                                    bi-graph-up-arrow
                                "
                            ></i>

                            Reports

                        </a>


                        <ul
                            class="
                                nav
                                flex-column
                                ms-3
                            "
                        >

                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                        <?= (
                                            $current_page ===
                                            'revenue.php'
                                        )
                                            ? 'active'
                                            : '' ?>
                                    "
                                    href="<?= BASE_URL ?>reports/revenue.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-currency-dollar
                                        "
                                    ></i>

                                    Revenue

                                </a>

                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                        <?= (
                                            $current_page ===
                                            'monthly.php'
                                        )
                                            ? 'active'
                                            : '' ?>
                                    "
                                    href="<?= BASE_URL ?>reports/monthly.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-calendar-month
                                        "
                                    ></i>

                                    Monthly

                                </a>

                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                        <?= (
                                            $current_page ===
                                            'yearly.php'
                                        )
                                            ? 'active'
                                            : '' ?>
                                    "
                                    href="<?= BASE_URL ?>reports/yearly.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-calendar-year
                                        "
                                    ></i>

                                    Yearly

                                </a>

                            </li>


                        </ul>

                    </li>


                    <!-- HOLIDAYS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'holidays'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/holidays.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar2-x
                                "
                            ></i>

                            Holidays

                        </a>

                    </li>


                    <!-- RECURRING -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'recurring_manage'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/recurring_manage.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-arrow-repeat
                                "
                            ></i>

                            Recurring

                        </a>

                    </li>


                    <!-- ADMIN -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'admin'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="#"
                        >

                            <i
                                class="
                                    bi
                                    bi-tools
                                "
                            ></i>

                            Admin

                        </a>


                        <ul
                            class="
                                nav
                                flex-column
                                ms-3
                            "
                        >

                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                    "
                                    href="<?= BASE_URL ?>admin/audit_log.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-clock-history
                                        "
                                    ></i>

                                    Audit Log

                                </a>

                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                    "
                                    href="<?= BASE_URL ?>admin/settings.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-gear
                                        "
                                    ></i>

                                    Settings

                                </a>

                            </li>

                            <li class="nav-item">
                                <a class="nav-link py-1" href="<?= BASE_URL ?>admin/online_payments.php">
                                    <i class="bi bi-cash-stack"></i>
                                    Online payments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-1" href="<?= BASE_URL ?>admin/teacher_banks.php">
                                    <i class="bi bi-bank"></i>
                                    Teacher bank details
                                </a>
                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                    "
                                    href="<?= BASE_URL ?>admin/users.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-people
                                        "
                                    ></i>

                                    Users

                                </a>

                            </li>

                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= ($current_page === 'teacher_oauth_migration.php') ? 'active' : '' ?>"
                                    href="<?= BASE_URL ?>admin/teacher_oauth_migration.php"
                                >
                                    <i class="bi bi-google text-danger"></i>
                                    Teacher Migration
                                </a>
                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                    "
                                    href="<?= BASE_URL ?>admin/students.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-person-video
                                        "
                                    ></i>

                                    Students

                                </a>

                            </li>

                            <li class="nav-item">
                                <a class="nav-link py-1" href="<?= BASE_URL ?>admin/student_devices.php">
                                    <i class="bi bi-phone"></i>
                                    Devices
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-1" href="<?= BASE_URL ?>admin/student_devices.php#emergency-requests">
                                    <i class="bi bi-bell"></i>
                                    Emergency devices
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-1" href="<?= BASE_URL ?>admin/security_center.php">
                                    <i class="bi bi-shield-lock"></i>
                                    Security Center
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-1" href="<?= BASE_URL ?>admin/protection.php">
                                    <i class="bi bi-shield-check"></i>
                                    Protection
                                </a>
                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                    "
                                    href="<?= BASE_URL ?>admin/whatsapp_bot.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-whatsapp
                                        "
                                    ></i>

                                    WhatsApp Bot

                                </a>

                            </li>

                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= $current_page === 'incoming_sms.php' ? 'active' : '' ?> d-flex justify-content-between align-items-center"
                                    href="<?= BASE_URL ?>admin/incoming_sms.php"
                                >
                                    <span><i class="bi bi-inbox"></i> Incoming SMS</span>
                                    <?php if (!empty($headerUnreadSms)): ?>
                                        <span class="badge bg-danger rounded-pill sms-unread-badge"><?= $headerUnreadSms ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= in_array($current_page, ['phone_contacts.php', 'phone_contact_view.php']) ? 'active' : '' ?>"
                                    href="<?= BASE_URL ?>admin/phone_contacts.php"
                                >
                                    <i class="bi bi-person-lines-fill"></i> Phone Contacts
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= $current_page === 'phone_import_history.php' ? 'active' : '' ?>"
                                    href="<?= BASE_URL ?>admin/phone_import_history.php"
                                >
                                    <i class="bi bi-clock-history"></i> Contact Import History
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= $current_page === 'sms_teacher_permissions.php' ? 'active' : '' ?>"
                                    href="<?= BASE_URL ?>admin/sms_teacher_permissions.php"
                                >
                                    <i class="bi bi-shield-lock"></i> Teacher SMS Permissions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= in_array($current_page, ['bulk_sms.php', 'bulk_sms_history.php']) ? 'active' : '' ?>"
                                    href="<?= BASE_URL ?>admin/bulk_sms.php"
                                >
                                    <i class="bi bi-broadcast"></i> Bulk SMS
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link py-1 <?= $current_page === 'sms_logs.php' ? 'active' : '' ?>"
                                    href="<?= BASE_URL ?>admin/sms_logs.php"
                                >
                                    <i class="bi bi-card-list"></i> SMS Logs
                                </a>
                            </li>


                            <li class="nav-item">

                                <a
                                    class="
                                        nav-link
                                        py-1
                                    "
                                    href="<?= BASE_URL ?>admin/backup.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-database
                                        "
                                    ></i>

                                    Backup

                                </a>

                            </li>


                        </ul>

                    </li>


                    <!-- TEACHER SCHEDULE -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'teacher_schedule'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/teacher_schedule.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-person-video
                                "
                            ></i>

                            Teacher Schedule

                        </a>

                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/today.php"><i class="bi bi-sun"></i> Today</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/waitlist.php"><i class="bi bi-hourglass-split"></i> Waitlist</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/exams.php"><i class="bi bi-clipboard2-pulse"></i> Exams</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/attendance.php"><i class="bi bi-check2-circle"></i> Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/lesson_fees.php"><i class="bi bi-cash-coin"></i> Class fees</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/progress.php"><i class="bi bi-graph-up-arrow"></i> Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/homework.php"><i class="bi bi-file-earmark-pdf"></i> Papers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/class_status.php"><i class="bi bi-exclamation-octagon"></i> Cancel class</a>
                    </li>
                    <?php if (!empty($is_admin)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/fees.php"><i class="bi bi-wallet2"></i> Student fees</a>
                    </li>
                    <?php endif; ?>


                <?php elseif ($is_teacher): ?>


                    <!-- ==================================================
                         TEACHER SIDEBAR
                         One timetable page only: My Schedule.
                    =================================================== -->


                    <!-- DASHBOARD -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    $current_page ===
                                    'dashboard.php'
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>dashboard.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-speedometer2
                                "
                            ></i>

                            Dashboard

                        </a>

                    </li>


                    <!-- MY SCHEDULE -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'teacher_schedule'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/teacher_schedule.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar-week
                                "
                            ></i>

                            My Schedule

                        </a>

                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/today.php"><i class="bi bi-sun"></i> Today</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/waitlist.php"><i class="bi bi-hourglass-split"></i> Waitlist</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/exams.php"><i class="bi bi-clipboard2-pulse"></i> Exams</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/attendance.php"><i class="bi bi-check2-circle"></i> Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/lesson_fees.php"><i class="bi bi-cash-coin"></i> Class fees</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/progress.php"><i class="bi bi-graph-up-arrow"></i> Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/homework.php"><i class="bi bi-file-earmark-pdf"></i> Papers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>campus/class_status.php"><i class="bi bi-exclamation-octagon"></i> Cancel class</a>
                    </li>


                    <!-- MY PAYMENTS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'payments'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/payments.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-credit-card
                                "
                            ></i>

                            My Payments

                        </a>

                    </li>

                    <!-- TEACHER SMS BROADCAST -->
                    <li class="nav-item">
                        <a
                            class="nav-link <?= ($current_page === 'send_sms.php') ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>teachers/send_sms.php"
                        >
                            <i class="bi bi-chat-left-dots"></i>
                            Send SMS
                        </a>
                    </li>


                    <!-- SETTINGS -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    $current_page ===
                                    'settings.php'
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>settings.php"
                        >

                            <i class="bi bi-gear"></i>

                            Settings

                        </a>

                    </li>


                <?php elseif ($is_student): ?>


                    <!-- ==================================================
                         STUDENT SIDEBAR
                    =================================================== -->


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php"
                        >
                            <i class="bi bi-house-door"></i>
                            Overview
                        </a>
                    </li>


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'dashboard.php' && (($_GET['tab'] ?? '') === 'timetable') ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=timetable"
                        >
                            <i class="bi bi-calendar3"></i>
                            Timetable
                        </a>
                    </li>


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'dashboard.php' && (($_GET['tab'] ?? '') === 'exams') ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=exams"
                        >
                            <i class="bi bi-journal-text"></i>
                            Exams
                        </a>
                    </li>


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'all_classes.php' ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/all_classes.php"
                        >
                            <i class="bi bi-collection"></i>
                            My Classes / All Classes
                        </a>
                    </li>


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'join_class.php' ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/join_class.php"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Join Class
                        </a>
                    </li>


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'services.php' ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/dashboard.php?tab=services"
                        >
                            <i class="bi bi-grid"></i>
                            Services
                        </a>
                    </li>


                    <li class="nav-item">
                        <a
                            class="nav-link <?= $current_page === 'settings.php' && $current_dir === 'student' ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>student/settings.php"
                        >
                            <i class="bi bi-gear"></i>
                            Settings
                        </a>
                    </li>


                <?php endif; ?>


            </ul>

        </nav>


        <!-- ====================================================
             MAIN CONTENT
        ===================================================== -->

        <main
            class="
                col-md-10
                ms-sm-auto
                main-content
                <?= $is_student ? "student-main-fullwidth" : "staff-main-with-rail" ?>
            "
        >


            <?php if (!$is_student): ?>


                <!-- ==================================================
                     BREADCRUMB
                =================================================== -->

                <nav
                    aria-label="breadcrumb"
                    class="breadcrumb-nav"
                >

                    <ol class="breadcrumb">


                        <li class="breadcrumb-item">

                            <a
                                href="<?= BASE_URL ?>dashboard.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-house
                                    "
                                ></i>

                                Home

                            </a>

                        </li>


                        <?php

                        $crumbs = [];

                        $path =
                            $_SERVER['SCRIPT_NAME'];

                        $parts =
                            explode(
                                '/',
                                trim(
                                    $path,
                                    '/'
                                )
                            );

                        $base =
                            BASE_URL;

                        $current = '';


                        foreach (
                            $parts
                            as $part
                        ) {

                            if (
                                $part ===
                                'index.php' ||
                                $part ===
                                ''
                            ) {
                                continue;
                            }


                            $current .=
                                $part . '/';


                            $label =
                                ucfirst(
                                    str_replace(
                                        '.php',
                                        '',
                                        $part
                                    )
                                );


                            switch ($part) {

                                case 'timetable':

                                    $label =
                                        'Timetable';

                                    break;


                                case 'exams':

                                    $label =
                                        'Exams';

                                    break;


                                case 'teachers':

                                    $label =
                                        'Teachers';

                                    break;


                                case 'subjects':

                                    $label =
                                        'Subjects';

                                    break;


                                case 'classes':

                                    $label =
                                        'Classes';

                                    break;


                                case 'rooms':

                                    $label =
                                        'Rooms';

                                    break;


                                case 'payments':

                                    $label =
                                        'Payments';

                                    break;


                                case 'reports':

                                    $label =
                                        'Reports';

                                    break;


                                case 'admin':

                                    $label =
                                        'Admin';

                                    break;

                                case 'admissions':

                                    $label =
                                        'Admissions';

                                    break;
                            }


                            $crumbUrl =
                                $base .
                                $current;

                            // Intermediate directory crumbs must point at a real page.
                            if (
                                $part ===
                                'admissions'
                            ) {
                                $crumbUrl =
                                    $base .
                                    'admissions/enquire.php';
                            }


                            $crumbs[] = [

                                'label' =>
                                    $label,

                                'url' =>
                                    $crumbUrl

                            ];
                        }


                        foreach (
                            $crumbs
                            as $index =>
                            $crumb
                        ):

                        ?>


                            <?php if (
                                $index ===
                                count(
                                    $crumbs
                                ) - 1
                            ): ?>

                                <li
                                    class="
                                        breadcrumb-item
                                        active
                                    "
                                    aria-current="page"
                                >

                                    <?= htmlspecialchars(
                                        $crumb['label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </li>


                            <?php else: ?>

                                <li
                                    class="
                                        breadcrumb-item
                                    "
                                >

                                    <a
                                        href="<?= htmlspecialchars(
                                            $crumb['url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $crumb['label'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </a>

                                </li>

                            <?php endif; ?>


                        <?php endforeach; ?>


                    </ol>

                </nav>


            <?php endif; ?>
