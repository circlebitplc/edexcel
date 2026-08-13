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
   CURRENT DARK MODE
   ============================================================ */

$dark_mode_enabled =
    isset($_COOKIE['dark_mode']) &&
    $_COOKIE['dark_mode'] === 'true';


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

$html_theme =
    $dark_mode_enabled
        ? 'dark'
        : 'light';

?>
<!DOCTYPE html>

<html
    lang="en"
    data-bs-theme="<?= $html_theme ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="
            width=device-width,
            initial-scale=1.0,
            maximum-scale=1.0,
            user-scalable=yes
        "
    >

    <meta
        name="csrf-token"
        content="<?= htmlspecialchars(
            generate_csrf_token(),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <title>
        Edexcel Timetable
    </title>


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
            href="<?= BASE_URL ?>assets/css/payments.css?v=<?= filemtime($dashboard_css) ?>"
        >

    <?php endif; ?>


</head>


<body>


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
            class="navbar-brand"
            href="<?= BASE_URL ?>dashboard.php"
        >

            📚 Edexcel

        </a>


        <!-- MOBILE TOGGLE -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <!-- NAVIGATION -->

        <div
            class="collapse navbar-collapse"
            id="navbarNav"
        >


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
                                        'backup'
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
                        =================================================== -->


                        <!-- MY TIMETABLE -->

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
                                href="<?= BASE_URL ?>timetable/index.php?teacher=<?= (int)($_SESSION['teacher_id'] ?? 0) ?>"
                            >

                                <i
                                    class="
                                        bi
                                        bi-calendar-event
                                    "
                                ></i>

                                My Timetable

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
                                href="<?= BASE_URL ?>timetable/teacher_schedule.php?teacher_id=<?= (int)($_SESSION['teacher_id'] ?? 0) ?>"
                            >

                                <i
                                    class="
                                        bi
                                        bi-person-video
                                    "
                                ></i>

                                My Schedule

                            </a>

                        </li>


                        <!-- ADD LESSON -->

                        <li class="nav-item">

                            <a
                                class="
                                    nav-link
                                    <?= (
                                        strpos(
                                            $current_page,
                                            'add'
                                        ) !== false
                                    )
                                        ? 'active'
                                        : '' ?>
                                "
                                href="<?= BASE_URL ?>timetable/add.php"
                            >

                                <i
                                    class="
                                        bi
                                        bi-plus-circle
                                    "
                                ></i>

                                Add Lesson

                            </a>

                        </li>


                    <?php endif; ?>


                <?php elseif ($is_student): ?>


                    <!-- ==================================================
                         STUDENT NAVIGATION
                    =================================================== -->


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
                            href="<?= BASE_URL ?>student/dashboard.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar-event
                                "
                            ></i>

                            My Timetable

                        </a>

                    </li>


                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'all_classes'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>student/all_classes.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-list-ul
                                "
                            ></i>

                            All Classes

                        </a>

                    </li>


                <?php endif; ?>


            </ul>


            <!-- ==================================================
                 RIGHT NAVIGATION
            =================================================== -->

            <ul
                class="
                    navbar-nav
                    ms-auto
                    align-items-center
                "
            >


                <!-- LANGUAGE -->

                <li
                    class="
                        nav-item
                        dropdown
                        me-2
                    "
                >

                    <a
                        class="
                            nav-link
                            dropdown-toggle
                        "
                        href="#"
                        id="langDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >

                        <i
                            class="
                                bi
                                bi-globe
                            "
                        ></i>

                        EN

                    </a>


                    <ul
                        class="
                            dropdown-menu
                            dropdown-menu-end
                        "
                        aria-labelledby="langDropdown"
                    >

                        <li>

                            <a
                                class="dropdown-item"
                                href="?lang=en"
                            >

                                English

                            </a>

                        </li>


                        <li>

                            <a
                                class="dropdown-item"
                                href="?lang=si"
                            >

                                සිංහල

                            </a>

                        </li>


                        <li>

                            <a
                                class="dropdown-item"
                                href="?lang=ta"
                            >

                                தமிழ்

                            </a>

                        </li>


                    </ul>

                </li>


                <!-- DARK MODE -->

                <li
                    class="
                        nav-item
                        dark-toggle
                        me-2
                    "
                >

                    <div
                        class="
                            form-check
                            form-switch
                        "
                    >

                        <input
                            class="
                                form-check-input
                            "
                            type="checkbox"
                            id="darkModeToggle"
                            <?= $dark_mode_enabled
                                ? 'checked'
                                : '' ?>
                        >


                        <label
                            class="
                                form-check-label
                                text-light
                            "
                            for="darkModeToggle"
                            title="Toggle dark mode"
                        >

                            <i
                                class="
                                    bi
                                    <?= $dark_mode_enabled
                                        ? 'bi-sun-fill'
                                        : 'bi-moon-fill' ?>
                                "
                                id="darkModeIcon"
                            ></i>

                        </label>


                    </div>

                </li>


                <!-- USER -->

                <li
                    class="
                        nav-item
                        dropdown
                    "
                >

                    <a
                        class="
                            nav-link
                            dropdown-toggle
                        "
                        href="#"
                        id="userDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >

                        <i
                            class="
                                bi
                                bi-person-circle
                            "
                        ></i>

                        <?= htmlspecialchars(
                            $_SESSION['username'] ??
                            'User',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </a>


                    <ul
                        class="
                            dropdown-menu
                            dropdown-menu-end
                        "
                        aria-labelledby="userDropdown"
                    >


                        <?php if (!$is_student): ?>

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="<?= BASE_URL ?>profile.php"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-key
                                        "
                                    ></i>

                                    Change Password

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


                <?php elseif ($is_teacher): ?>


                    <!-- ==================================================
                         TEACHER SIDEBAR
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


                    <!-- MY TIMETABLE -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'timetable'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/index.php?teacher=<?= (int)($_SESSION['teacher_id'] ?? 0) ?>"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar-event
                                "
                            ></i>

                            My Timetable

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

                            My Payments

                        </a>

                    </li>


                    <!-- SCHEDULE -->

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
                            href="<?= BASE_URL ?>timetable/teacher_schedule.php?teacher_id=<?= (int)($_SESSION['teacher_id'] ?? 0) ?>"
                        >

                            <i
                                class="
                                    bi
                                    bi-person-video
                                "
                            ></i>

                            My Schedule

                        </a>

                    </li>


                    <!-- ADD LESSON -->

                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'add'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>timetable/add.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-plus-circle
                                "
                            ></i>

                            Add Lesson

                        </a>

                    </li>


                <?php elseif ($is_student): ?>


                    <!-- ==================================================
                         STUDENT SIDEBAR
                    =================================================== -->


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
                            href="<?= BASE_URL ?>student/dashboard.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-calendar-event
                                "
                            ></i>

                            My Timetable

                        </a>

                    </li>


                    <li class="nav-item">

                        <a
                            class="
                                nav-link
                                <?= (
                                    strpos(
                                        $current_page,
                                        'all_classes'
                                    ) !== false
                                )
                                    ? 'active'
                                    : '' ?>
                            "
                            href="<?= BASE_URL ?>student/all_classes.php"
                        >

                            <i
                                class="
                                    bi
                                    bi-list-ul
                                "
                            ></i>

                            All Classes

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
                            }


                            $crumbs[] = [

                                'label' =>
                                    $label,

                                'url' =>
                                    $base .
                                    $current

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