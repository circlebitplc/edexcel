<?php
/**
 * Edexcel College Kandy
 * Public Homepage + Inline Teacher & Student Login
 */

define('DB_ALLOW_FAILURE', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';


// ============================================================
// HELPER FUNCTIONS
// ============================================================

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function teacherInitials(string $name): string
{
    $parts = preg_split(
        '/\s+/',
        trim($name)
    );

    $initials = '';

    foreach ($parts as $part) {

        if ($part !== '') {

            $initials .= strtoupper(
                substr($part, 0, 1)
            );
        }
    }

    return substr(
        $initials ?: 'TE',
        0,
        2
    );
}


function teacherAvatarColor(string $name): string
{
    $colors = [
        '#03bfcb',
        '#f8b400',
        '#4a90d9',
        '#27ae60',
        '#e67e22',
        '#8e44ad',
        '#1abc9c',
        '#e74c3c'
    ];

    return $colors[
        abs(crc32($name)) % count($colors)
    ];
}


function teacherPhotoPath(int $id): ?string
{
    /*
     * Keep the existing project photo location.
     */
    foreach (
        ['jpg', 'jpeg', 'png', 'webp']
        as $ext
    ) {

        $filePath =
            __DIR__ .
            "/uploads/teachers/{$id}.{$ext}";

        if (file_exists($filePath)) {

            return
                "uploads/teachers/{$id}.{$ext}";
        }
    }

    return null;
}


function teacherSubjects($subjects): array
{
    if (is_array($subjects)) {

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    $subjects
                )
            )
        );
    }

    return array_values(
        array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    (string) $subjects
                )
            )
        )
    );
}


function whatsappUrl(?string $phone): ?string
{
    $digits = preg_replace(
        '/[^0-9]/',
        '',
        (string) $phone
    );

    if ($digits === '') {
        return null;
    }

    return "https://wa.me/{$digits}";
}


// ============================================================
// LOGIN STATE
// ============================================================

$activeSection = 'home';

$teacherLoginError = '';

$studentLoginError = '';


// ============================================================
// INLINE TEACHER LOGIN
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['teacher_login'])
) {

    /*
     * Keep the user on the teacher-login section
     * if authentication fails.
     */
    $activeSection = 'teacher-login';

    $teacherLoginError = '';

    // --------------------------------------------------------
    // CSRF CHECK
    // --------------------------------------------------------

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $teacherLoginError =
            'Your session expired. Please refresh the page and try again.';

    } else {

        // ----------------------------------------------------
        // GET LOGIN DETAILS
        // ----------------------------------------------------

        $username = trim(
            $_POST['teacher_username'] ?? ''
        );

        $password =
            $_POST['teacher_password'] ?? '';


        // ----------------------------------------------------
        // VALIDATION
        // ----------------------------------------------------

        if (
            $username === '' ||
            $password === ''
        ) {

            $teacherLoginError =
                'Please enter your username and password.';

        } elseif (!($pdo instanceof PDO)) {

            $teacherLoginError =
                'Unable to connect to the database. Please try again later.';

        } else {

            try {

                // ------------------------------------------------
                // FIND USER
                //
                // IMPORTANT:
                // This matches login.php authentication.
                // ------------------------------------------------

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        username,
                        password_hash,
                        role,
                        teacher_id,
                        deleted_at,
                        is_active
                    FROM users
                    WHERE username = ?
                      AND deleted_at IS NULL
                    LIMIT 1
                ");

                $stmt->execute([
                    $username
                ]);

                $user = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                // ------------------------------------------------
                // VERIFY PASSWORD
                // ------------------------------------------------

                if (
                    !$user ||
                    empty($user['password_hash']) ||
                    !password_verify(
                        $password,
                        $user['password_hash']
                    )
                ) {

                    $teacherLoginError =
                        'Invalid username or password.';

                } else {

                    // ------------------------------------------------
                    // CHECK USER ROLE
                    //
                    // The public "Teacher Login" form is allowed to
                    // authenticate BOTH teacher and admin accounts.
                    //
                    // Teachers continue to receive their teacher_id.
                    // Admin accounts receive the admin role so the
                    // dashboard/admin permissions work normally.
                    // ------------------------------------------------

                    $loginRole =
                        strtolower(
                            trim(
                                (string)($user['role'] ?? '')
                            )
                        );

                    if (
                        !in_array(
                            $loginRole,
                            ['teacher', 'admin'],
                            true
                        )
                    ) {

                        $teacherLoginError =
                            'This account is not registered as a teacher or administrator account.';

                    } elseif (
                        isset($user['is_active']) &&
                        (int)$user['is_active'] !== 1
                    ) {

                        $teacherLoginError =
                            $loginRole === 'admin'
                                ? 'This administrator account is currently inactive.'
                                : 'This teacher account is currently inactive.';

                    } else {

                        // ------------------------------------------------
                        // LOGIN SUCCESS
                        // ------------------------------------------------

                        regenerate_session();


                        // Store authenticated user
                        $_SESSION['user_id'] =
                            (int)$user['id'];


                        /*
                         * IMPORTANT:
                         * Keep the real database role.
                         *
                         * Do NOT force admins to "teacher".
                         * auth.php can therefore correctly recognise
                         * this session as an administrator.
                         */
                        $_SESSION['role'] =
                            $loginRole;


                        /*
                         * Teacher accounts need teacher_id.
                         * Admin accounts normally do not.
                         */
                        $_SESSION['teacher_id'] =
                            $loginRole === 'teacher' &&
                            !empty($user['teacher_id'])
                                ? (int)$user['teacher_id']
                                : null;


                        $_SESSION['username'] =
                            $user['username'];


                        // ------------------------------------------------
                        // GO TO DASHBOARD
                        // ------------------------------------------------
                        //
                        // Both teacher and admin accounts use the same
                        // dashboard entry point. The dashboard/auth
                        // system will display the appropriate content
                        // according to $_SESSION['role'].
                        // ------------------------------------------------

                        header(
                            'Location: /dashboard.php'
                        );

                        exit;
                    }
                }

            } catch (PDOException $exception) {

                error_log(
                    'Inline teacher login error: ' .
                    $exception->getMessage()
                );

                $teacherLoginError =
                    'Unable to process your login. Please try again.';
            }
        }
    }
}


// ============================================================
// INLINE STUDENT LOGIN
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['student_login'])
) {

    /*
     * If anything goes wrong, remain inside
     * index.php#student-login.
     */
    $activeSection = 'student-login';


    // --------------------------------------------------------
    // CSRF
    // --------------------------------------------------------

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $studentLoginError =
            'Your session expired. Please try again.';

    } else {

        $username =
            trim(
                $_POST['student_username'] ?? ''
            );

        $password =
            $_POST['student_password'] ?? '';


        // ----------------------------------------------------
        // Validation
        // ----------------------------------------------------

        if (
            $username === '' ||
            $password === ''
        ) {

            $studentLoginError =
                'Please enter your username and password.';

        } elseif (!($pdo instanceof PDO)) {

            $studentLoginError =
                'Unable to connect to the server. Please try again later.';

        } else {

            try {

                // ------------------------------------------------
                // Find student account
                // ------------------------------------------------

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM users
                    WHERE username = ?
                      AND role = 'student'
                      AND deleted_at IS NULL
                    LIMIT 1
                ");

                $stmt->execute([
                    $username
                ]);

                $user =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                // ------------------------------------------------
                // Verify password
                // ------------------------------------------------

                if (
                    $user &&
                    password_verify(
                        $password,
                        $user['password_hash']
                    )
                ) {

                    /*
                     * Regenerate session.
                     */
                    regenerate_session();


                    // --------------------------------------------
                    // Store student session
                    // --------------------------------------------

                    $_SESSION['user_id'] =
                        $user['id'];

                    $_SESSION['role'] =
                        'student';

                    $_SESSION['username'] =
                        $user['username'];

                    $_SESSION['student_id'] =
                        $user['id'];


                    /*
                     * Student successfully logged in.
                     */
                    header(
                        'Location: /student/dashboard.php'
                    );

                    exit;

                } else {

                    $studentLoginError =
                        'Invalid username or password.';
                }

            } catch (PDOException $exception) {

                error_log(
                    'Student login error: ' .
                    $exception->getMessage()
                );

                $studentLoginError =
                    'Unable to process your login. Please try again.';
            }
        }
    }
}


// ============================================================
// HOMEPAGE DATABASE DATA
// ============================================================

$teacherProfiles = [];

$teacherCount = 0;

$studentCount = 0;

$classCount = 0;

$todayClassCount = 0;

$subjectCount = 0;


if ($pdo instanceof PDO) {

    try {

        // --------------------------------------------------------
        // TEACHER COUNT
        // --------------------------------------------------------

        $teacherCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM teachers
            WHERE deleted_at IS NULL
              AND LOWER(name) <> 'default teacher'
        ");

        $teacherCount =
            (int) $teacherCountStmt->fetchColumn();


        // --------------------------------------------------------
        // STUDENT COUNT
        // --------------------------------------------------------

        $studentCountStmt = $pdo->query("
            SELECT COUNT(DISTINCT id)
            FROM users WHERE role='student'
        ");

        $studentCount =
            (int) $studentCountStmt->fetchColumn();


        // --------------------------------------------------------
        // ACTIVE CLASS COUNT
        // --------------------------------------------------------

        $classCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM student_classes
            WHERE deleted_at IS NULL
        ");

        $classCount =
            (int) $classCountStmt->fetchColumn();


        // --------------------------------------------------------
        // TODAY'S CLASS COUNT
        // --------------------------------------------------------

        $todayClassCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM timetable
            WHERE deleted_at IS NULL
              AND date = CURDATE()
        ");

        $todayClassCount =
            (int) $todayClassCountStmt->fetchColumn();


        // --------------------------------------------------------
        // SUBJECT COUNT
        // --------------------------------------------------------

        $subjectCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM subjects
            WHERE deleted_at IS NULL
        ");

        $subjectCount =
            (int) $subjectCountStmt->fetchColumn();


        // --------------------------------------------------------
        // TEACHER DIRECTORY
        // --------------------------------------------------------

        $teacherStmt = $pdo->query("
            SELECT
                t.id,
                t.name,
                t.email,
                t.phone,

                GROUP_CONCAT(
                    DISTINCT subject_pool.name
                    ORDER BY subject_pool.name
                    SEPARATOR ', '
                ) AS subjects

            FROM teachers t

            LEFT JOIN (

                /*
                 * Direct teacher-subject assignments
                 */
                SELECT
                    ts.teacher_id,
                    s.name

                FROM teacher_subjects ts

                INNER JOIN subjects s
                    ON s.id = ts.subject_id
                   AND s.deleted_at IS NULL


                UNION


                /*
                 * Subjects found in timetable
                 */
                SELECT
                    tt2.teacher_id,
                    s2.name

                FROM timetable tt2

                INNER JOIN subjects s2
                    ON s2.id = tt2.subject_id
                   AND s2.deleted_at IS NULL

                WHERE tt2.deleted_at IS NULL

            ) subject_pool

                ON subject_pool.teacher_id = t.id

            WHERE t.deleted_at IS NULL
              AND LOWER(t.name) <> 'default teacher'

            GROUP BY
                t.id,
                t.name,
                t.email,
                t.phone

            ORDER BY t.name ASC
        ");

        $teacherProfiles =
            $teacherStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $exception) {

        /*
         * Keep the public homepage usable if the
         * database temporarily fails.
         */
        $teacherProfiles = [];

        $teacherCount = 0;

        $studentCount = 0;

        $classCount = 0;

        $todayClassCount = 0;

        $subjectCount = 0;
    }
}
// ============================================================
// WEEKLY PUBLIC TIMETABLE
// ============================================================

$weeklyTimetable = [];

$weekStart = null;
$weekEnd = null;

if ($pdo instanceof PDO) {

    try {

        /*
         * Optional week offset.
         *
         * 0  = current week
         * -1 = previous week
         * 1  = next week
         */
        $weekOffset =
            filter_input(
                INPUT_GET,
                'timetable_week',
                FILTER_VALIDATE_INT
            );

        if ($weekOffset === false || $weekOffset === null) {
            $weekOffset = 0;
        }

        /*
         * Prevent somebody from requesting
         * an unnecessarily large date range.
         */
        $weekOffset =
            max(
                -52,
                min(
                    52,
                    (int) $weekOffset
                )
            );


        /*
         * Find Monday of the requested week.
         *
         * MySQL DAYOFWEEK:
         * Sunday = 1
         * Monday = 2
         * ...
         * Saturday = 7
         */
        $weekStartStmt =
            $pdo->prepare("
                SELECT DATE_SUB(
                    CURDATE(),
                    INTERVAL (
                        DAYOFWEEK(CURDATE()) - 2
                    ) DAY
                )
            ");

        $weekStartStmt->execute();

        $weekStart =
            $weekStartStmt->fetchColumn();


        /*
         * Apply requested week offset.
         */
        if ($weekOffset !== 0) {

            $weekStart =
                date(
                    'Y-m-d',
                    strtotime(
                        ($weekOffset > 0 ? '+' : '') .
                        ($weekOffset * 7) .
                        ' days',
                        strtotime($weekStart)
                    )
                );
        }


        /*
         * Sunday = Monday + 6 days.
         */
        $weekEnd =
            date(
                'Y-m-d',
                strtotime(
                    '+6 days',
                    strtotime($weekStart)
                )
            );


        /*
         * Fetch timetable.
         *
         * The WhatsApp lookup first tries a
         * class + teacher + subject match.
         *
         * If that doesn't exist, it falls back
         * to class + teacher.
         */
        $weeklyStmt =
            $pdo->prepare("
                SELECT

                    tt.id,

                    tt.date,

                    tt.start_time,

                    tt.end_time,

                    tt.class_id,

                    tt.teacher_id,

                    tt.subject_id,

                    tt.room_id,

                    sc.name AS class_name,

                    s.name AS subject_name,

                    t.name AS teacher_name,

                    r.name AS room_name,

                    COALESCE(

                        (
                            SELECT cw.whatsapp_link

                            FROM class_teacher_whatsapp cw

                            WHERE cw.class_id =
                                tt.class_id

                              AND cw.teacher_id =
                                tt.teacher_id

                              AND cw.subject_id =
                                tt.subject_id

                              AND cw.whatsapp_link IS NOT NULL

                              AND cw.whatsapp_link <> ''

                            ORDER BY cw.id DESC

                            LIMIT 1
                        ),

                        (
                            SELECT cw2.whatsapp_link

                            FROM class_teacher_whatsapp cw2

                            WHERE cw2.class_id =
                                tt.class_id

                              AND cw2.teacher_id =
                                tt.teacher_id

                              AND (
                                  cw2.subject_id IS NULL
                                  OR cw2.subject_id = 0
                              )

                              AND cw2.whatsapp_link IS NOT NULL

                              AND cw2.whatsapp_link <> ''

                            ORDER BY cw2.id DESC

                            LIMIT 1
                        )

                    ) AS whatsapp_link


                FROM timetable tt


                INNER JOIN student_classes sc
                    ON sc.id =
                        tt.class_id

                   AND sc.deleted_at IS NULL


                INNER JOIN subjects s
                    ON s.id =
                        tt.subject_id

                   AND s.deleted_at IS NULL


                INNER JOIN teachers t
                    ON t.id =
                        tt.teacher_id

                   AND t.deleted_at IS NULL

                   AND LOWER(t.name) <>
                        'default teacher'


                INNER JOIN rooms r
                    ON r.id =
                        tt.room_id

                   AND r.deleted_at IS NULL


                WHERE tt.deleted_at IS NULL

                  AND tt.date BETWEEN
                        :week_start
                    AND :week_end


                ORDER BY

                    tt.date ASC,

                    tt.start_time ASC,

                    tt.end_time ASC,

                    sc.name ASC
            ");


        $weeklyStmt->execute(
            [
                ':week_start' =>
                    $weekStart,

                ':week_end' =>
                    $weekEnd
            ]
        );


        $weeklyRows =
            $weeklyStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * Create Monday → Sunday structure.
         */
        $weeklyTimetable = [];


        for (
            $day = 0;
            $day < 7;
            $day++
        ) {

            $date =
                date(
                    'Y-m-d',
                    strtotime(
                        '+' . $day . ' days',
                        strtotime($weekStart)
                    )
                );


            $weeklyTimetable[$date] = [];
        }


        /*
         * Put timetable records into
         * their correct day.
         */
        foreach (
            $weeklyRows as $row
        ) {

            $date =
                $row['date'];


            if (
                isset(
                    $weeklyTimetable[$date]
                )
            ) {

                $weeklyTimetable[$date][] =
                    $row;
            }
        }


    } catch (PDOException $exception) {

        error_log(
            'Weekly timetable error: ' .
            $exception->getMessage()
        );


        $weeklyTimetable = [];

        $weekStart = null;

        $weekEnd = null;
    }
}

?>
<!DOCTYPE html>

<html
    lang="en"
    data-theme="dark"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="color-scheme"
        content="light dark"
    >

    <title>
        Kandy Edexcel College
    </title>


    <!-- ========================================================
         FONT / ICONS
    ========================================================= -->

    <link
        rel="preconnect"
        href="https://cdnjs.cloudflare.com"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >


    <!-- ========================================================
         HOMEPAGE CSS
    ========================================================= -->

    <link
        rel="stylesheet"
        href="assets/css/home.css"
    >

</head>


<body>

<!-- ============================================================
     STANDALONE THEME TOGGLE
============================================================= -->

<button
    type="button"
    class="standalone-theme-toggle"
    id="standaloneThemeToggle"
    aria-label="Switch to light mode"
    title="Switch theme"
>
    <i class="fas fa-moon"></i>
</button>

<!-- ============================================================
     MAIN PAGE
============================================================= -->

<main class="page-shell">


    <!-- ========================================================
         HOME
    ========================================================= -->

    <section
        class="app-section <?= $activeSection === 'home' ? 'active-section' : '' ?>"
        id="home"
        aria-labelledby="homeTitle"
        <?= $activeSection !== 'home' ? 'hidden' : '' ?>
    >

        <div class="section-header home-intro">

            <span class="eyebrow">
                 Edexcel College - Kandy
            </span> 

        </div>


        <!-- METRICS -->

        <div
            class="metric-strip"
            aria-label="College overview"
        >

            <article class="metric-card">

                <span>
                    Enrolled Students
                </span>

                <strong>
                    <?= e($studentCount) ?>
                </strong>

            </article>


            <article class="metric-card">

                <span>
                    Teachers
                </span>

                <strong>
                    <?= e($teacherCount) ?>
                </strong>

            </article>


            <article class="metric-card">

                <span>
                    Classes Today
                </span>

                <strong>
                    <?= e($todayClassCount) ?>
                </strong>

            </article>

        </div>


        <!-- ====================================================
             WHY CHOOSE US
        ===================================================== -->

        <section
            class="why-choose-section"
            aria-labelledby="whyChooseTitle"
        >

            <div class="section-header why-intro">

                <span class="eyebrow">
                    Why Choose Us?
                </span>
 
            </div>


            <div
                class="why-slider"
                aria-label="Why choose Edexcel College"
            >

                <button
                    type="button"
                    class="why-slider-arrow why-slider-prev"
                    aria-label="Previous reason"
                >

                    <i class="fas fa-chevron-left"></i>

                </button>


                <div class="why-slider-viewport">

                    <div class="why-slider-track">


                        <article class="why-slide">

                            <div class="why-slide-icon">
                                🏆
                            </div>

                            <h3>
                                Award-Winning Teachers
                            </h3>

                            <p>
                                Learn from experienced teachers
                                with strong subject knowledge
                                and proven teaching experience.
                            </p>

                        </article>


                        <article class="why-slide">

                            <div class="why-slide-icon">
                                📚
                            </div>

                            <h3>
                                Edexcel-Focused Teaching
                            </h3>

                            <p>
                                Lessons aligned with the Pearson
                                Edexcel syllabus and examination
                                requirements.
                            </p>

                        </article>


                        <article class="why-slide">

                            <div class="why-slide-icon">
                                🎯
                            </div>

                            <h3>
                                Exam-Focused Preparation
                            </h3>

                            <p>
                                Build understanding while learning
                                how to score marks effectively
                                in examinations.
                            </p>

                        </article>


                        <article class="why-slide">

                            <div class="why-slide-icon">
                                📝
                            </div>

                            <h3>
                                Past Paper Practice
                            </h3>

                            <p>
                                Regular practice with past papers
                                and exam-style questions.
                            </p>

                        </article>


                        <article class="why-slide">

                            <div class="why-slide-icon">
                                👨‍🏫
                            </div>

                            <h3>
                                Individual Attention
                            </h3>

                            <p>
                                Students get opportunities to ask
                                questions and receive personal
                                guidance.
                            </p>

                        </article>


                        <article class="why-slide">

                            <div class="why-slide-icon">
                                📈
                            </div>

                            <h3>
                                Progress Monitoring
                            </h3>

                            <p>
                                Track strengths, weaknesses and
                                improvement throughout the course.
                            </p>

                        </article>


                    </div>

                </div>


                <button
                    type="button"
                    class="why-slider-arrow why-slider-next"
                    aria-label="Next reason"
                >

                    <i class="fas fa-chevron-right"></i>

                </button>

            </div>


            <div
                class="why-slider-dots"
                role="tablist"
                aria-label="Why choose us slides"
            ></div>

        </section>

    </section>


    <!-- ========================================================
         TEACHERS
    ========================================================= -->

    <section
        class="app-section <?= $activeSection === 'teachers' ? 'active-section' : '' ?>"
        id="teachers"
        aria-labelledby="teachersTitle"
        <?= $activeSection !== 'teachers' ? 'hidden' : '' ?>
    >

        <div class="section-header teachers-intro">

            <span class="eyebrow">
                Academic Team
            </span>
            <div
                class="teacher-directory-count"
                aria-label="Teacher count"
            >

                <i class="fas fa-users"></i>

                <strong>
                    <?= e($teacherCount) ?>
                </strong>

                <span>
                    <?= $teacherCount === 1
                        ? 'Teacher'
                        : 'Teachers'
                    ?>
                </span>

            </div>

        </div>


        <?php if (!empty($teacherProfiles)): ?>

            <div
                class="teacher-profile-grid"
                aria-label="Teacher profile cards"
            >

                <?php foreach ($teacherProfiles as $teacher): ?>

                    <?php

                    $teacherId =
                        (int) (
                            $teacher['id'] ?? 0
                        );

                    $teacherName =
                        (string) (
                            $teacher['name']
                            ?? 'Teacher'
                        );

                    $subjects =
                        teacherSubjects(
                            $teacher['subjects']
                            ?? ''
                        );

                    $subjectLabel =
                        !empty($subjects)
                            ? implode(
                                ' • ',
                                $subjects
                            )
                            : 'Edexcel Teacher';

                    $avatar =
                        teacherPhotoPath(
                            $teacherId
                        );

                    $whatsapp =
                        whatsappUrl(
                            $teacher['phone']
                            ?? ''
                        );

                    ?>

                    <article
                        class="teacher-profile-card"
                    >

                        <div
                            class="teacher-avatar-ring"
                            style="--avatar-accent: <?= e(
                                teacherAvatarColor(
                                    $teacherName
                                )
                            ) ?>;"
                        >

                            <?php if ($avatar): ?>

                                <img
                                    src="<?= e($avatar) ?>"
                                    alt="<?= e($teacherName) ?>"
                                    class="teacher-avatar-image"
                                    loading="lazy"
                                    decoding="async"
                                >

                            <?php else: ?>

                                <span
                                    class="teacher-avatar-initials"
                                    aria-hidden="true"
                                >
                                    <?= e(
                                        teacherInitials(
                                            $teacherName
                                        )
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <h2>
                            <?= e($teacherName) ?>
                        </h2>


                        <p class="teacher-subject">
                            <?= e($subjectLabel) ?>
                        </p>


                        <div
                            class="teacher-profile-actions"
                        >

                            <a
                                class="teacher-primary-action"
                                href="/teachers/teacher_profile.php?id=<?= $teacherId ?>"
                                aria-label="View <?= e($teacherName) ?> profile"
                            >

                                <i class="fas fa-user"></i>

                                <span>
                                    Profile
                                </span>

                            </a>


                            <?php if ($whatsapp): ?>

                                <a
                                    class="teacher-ghost-action"
                                    href="<?= e($whatsapp) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="WhatsApp <?= e($teacherName) ?>"
                                >

                                    <i class="fab fa-whatsapp"></i>

                                    <span>
                                        WhatsApp
                                    </span>

                                </a>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="teacher-empty-state">

                <i class="fas fa-user-slash"></i>

                <h2>
                    No teacher records available
                </h2>

                <p>
                    Teacher information could not be
                    loaded from the database.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- ========================================================
         CLASSES
    ========================================================= -->
<!-- ========================================================
     CLASSES
========================================================= -->

<section
    class="app-section <?= $activeSection === 'classes' ? 'active-section' : '' ?>"
    id="classes"
    aria-labelledby="classesTitle"
    <?= $activeSection !== 'classes' ? 'hidden' : '' ?>
>   
 
<div class="section-card-grid">
                <article class="info-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h2>Active Classes</h2>
                    <strong class="info-card-number"><?= e($classCount) ?></strong>
                    <p>Classes currently available in the student class database.</p>
                </article>

                <article class="info-card">
                    <i class="fas fa-calendar-day"></i>
                    <h2>Classes Today</h2>
                    <strong class="info-card-number"><?= e($todayClassCount) ?></strong>
                    <p>Timetable entries scheduled for today.</p>
                </article>

                <article class="info-card">
                    <i class="fas fa-book-open"></i>
                    <h2>Subjects</h2>
                    <strong class="info-card-number"><?= e($subjectCount) ?></strong>
                    <p>Active subjects available in the subject database.</p>
                </article>
            </div>
   
    <!-- ====================================================
         TIMETABLE HEADER
    ===================================================== -->

    <div class="weekly-timetable-header">

        <div>

            <!-- <span class="eyebrow">
                Weekly Timetable
            </span> -->

            <h1 id="classesTitle">
                Class Schedule
            </h1>

            <?php if ($weekStart && $weekEnd): ?>

                <p>

                    <?= e(
                        date(
                            'd M',
                            strtotime($weekStart)
                        )
                    ) ?>

                    –

                    <?= e(
                        date(
                            'd M Y',
                            strtotime($weekEnd)
                        )
                    ) ?>

                </p>

            <?php endif; ?>

        </div>


        <!-- WEEK NAVIGATION -->

        <div
            class="weekly-timetable-controls"
            aria-label="Timetable week navigation"
        >

            <a
                href="?timetable_week=<?= (int) ($weekOffset - 1) ?>#classes"
                class="weekly-week-button"
                aria-label="Previous week"
                title="Previous week"
            >

                <i class="fas fa-chevron-left"></i>

            </a>


            <a
                href="?timetable_week=0#classes"
                class="weekly-today-button"
            >
                This Week
            </a>


            <a
                href="?timetable_week=<?= (int) ($weekOffset + 1) ?>#classes"
                class="weekly-week-button"
                aria-label="Next week"
                title="Next week"
            >

                <i class="fas fa-chevron-right"></i>

            </a>

        </div>

    </div>


    <!-- ====================================================
         MONDAY → SUNDAY
    ===================================================== -->

    <div
        class="weekly-timetable"
        aria-label="Weekly class timetable"
    >

        <?php

        $dayNumber = 0;

        foreach (
            $weeklyTimetable as
            $date => $classes
        ):

            $dayNumber++;


            $timestamp =
                strtotime($date);


            $dayName =
                date(
                    'l',
                    $timestamp
                );


            $dayDate =
                date(
                    'd M',
                    $timestamp
                );


            $isToday =
                $date ===
                date('Y-m-d');

        ?>

            <section
                class="
                    timetable-day
                    <?= $isToday
                        ? 'is-today'
                        : ''
                    ?>
                "
                aria-labelledby="timetable-day-<?= e($date) ?>"
            >

                <!-- DAY HEADER -->

                <div class="timetable-day-header">

                    <div>

                        <h2
                            id="timetable-day-<?= e($date) ?>"
                        >

                            <?= e($dayName) ?>

                            <span>
                                <?= e($dayDate) ?>
                            </span>

                            <?php if ($isToday): ?>

                                <small>
                                    Today
                                </small>

                            <?php endif; ?>

                        </h2>

                    </div>


                    <div
                        class="timetable-day-count"
                    >

                        <?= count($classes) ?>

                        <?= count($classes) === 1
                            ? 'class'
                            : 'classes'
                        ?>

                    </div>

                </div>


                <!-- DAY CLASSES -->

                <?php if (!empty($classes)): ?>

                    <div
                        class="timetable-class-list"
                    >

                        <?php foreach (
                            $classes as $class
                        ): ?>

                            <?php

                            /*
                             * Format times.
                             */
                            $startTimestamp =
                                strtotime(
                                    $class['start_time']
                                );


                            $endTimestamp =
                                strtotime(
                                    $class['end_time']
                                );


                            $startTime =
                                date(
                                    'g:i A',
                                    $startTimestamp
                                );


                            $endTime =
                                date(
                                    'g:i A',
                                    $endTimestamp
                                );


                            /*
                             * Build a deterministic
                             * accent class from the
                             * timetable ID.
                             */
                            $accentIndex =
                                ((int) $class['id']) % 5;


                            /*
                             * Clean WhatsApp URL.
                             */
                            $whatsapp =
                                trim(
                                    (string) (
                                        $class['whatsapp_link']
                                        ?? ''
                                    )
                                );

                            ?>

                            <article
                                class="
                                    timetable-class-card
                                    timetable-accent-<?= $accentIndex ?>
                                "
                            >

                                <!-- TIME -->

                                <div
                                    class="timetable-class-time"
                                >

                                    <strong>
                                        <?= e(
                                            $startTime
                                        ) ?>
                                    </strong>

                                    <span>
                                        – <?= e(
                                            $endTime
                                        ) ?>
                                    </span>


                                    <div
                                        class="timetable-time-line"
                                    ></div>

                                </div>


                                <!-- MAIN INFORMATION -->

                                <div
                                    class="timetable-class-main"
                                >

                                    <h3>
                                        <?= e(
                                            $class['class_name']
                                        ) ?>
                                    </h3>


                                    <p
                                        class="timetable-subject"
                                    >

                                        <i
                                            class="fas fa-book-open"
                                        ></i>

                                        <?= e(
                                            $class['subject_name']
                                        ) ?>

                                    </p>


                                    <p
                                        class="timetable-teacher-mobile"
                                    >

                                        <i
                                            class="fas fa-user-tie"
                                        ></i>

                                        <?= e(
                                            $class['teacher_name']
                                        ) ?>

                                    </p>

                                </div>


                                <!-- ACTIONS -->

                                <div
                                    class="timetable-class-actions"
                                >

                                    <!-- TEACHER -->

                                    <span
                                        class="timetable-teacher"
                                        title="Teacher"
                                    >

                                        <i
                                            class="fab fa-whatsapp"
                                        ></i>

                                        <?= e(
                                            $class['teacher_name']
                                        ) ?>

                                    </span>


                                    <!-- ROOM -->

                                    <span
                                        class="timetable-room"
                                    >

                                        <i
                                            class="fas fa-door-open"
                                        ></i>

                                        <?= e(
                                            $class['room_name']
                                        ) ?>

                                    </span>


                                    <!-- WHATSAPP -->

                                    <?php if ($whatsapp !== ''): ?>

                                        <a
                                            href="<?= e($whatsapp) ?>"
                                            class="timetable-join"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >

                                            <i
                                                class="fab fa-whatsapp"
                                            ></i>

                                            <span>
                                                Join Group
                                            </span>

                                        </a>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <!-- NO CLASSES -->

                    <div
                        class="timetable-empty-day"
                    >

                        <i
                            class="fas fa-calendar-check"
                        ></i>

                        <span>
                            No classes scheduled
                        </span>

                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    </div>

</section>


    <!-- ========================================================
         TEACHER LOGIN
    ========================================================= -->

    <section
        class="app-section <?= $activeSection === 'teacher-login' ? 'active-section' : '' ?>"
        id="teacher-login"
        aria-labelledby="teacherLoginTitle"
        <?= $activeSection !== 'teacher-login' ? 'hidden' : '' ?>
    >

        <div class="section-header">

            <span class="eyebrow">
                Staff Access
            </span>
 
        </div>


        <div
            class="login-panel">

            <div
                style="
                    width: 64px;
                    height: 64px;
                    margin: 0 auto 20px;
                    border-radius: 18px;
                    display: grid;
                    place-items: center;
                    background: linear-gradient(
                        135deg,
                        var(--primary),
                        var(--primary-dark)
                    );
                    color: #fff;
                    font-size: 1.5rem;
                "
            >

                <i class="fas fa-chalkboard-user"></i>

            </div>


            <h2
                style="
                    text-align: center;
                    margin-bottom: 8px;
                "
            >
                Teacher Portal
            </h2>


            <p
                style="
                    text-align: center;
                    color: var(--muted);
                    margin-bottom: 24px;
                "
            >
                Enter your teacher username and password.
            </p>


            <?php if ($teacherLoginError): ?>

                <div
                    role="alert"
                    style="
                        margin-bottom: 20px;
                        padding: 12px 14px;
                        border-radius: 12px;
                        background: rgba(231,76,60,.10);
                        border: 1px solid rgba(231,76,60,.25);
                        color: #e74c3c;
                        display: flex;
                        align-items: flex-start;
                        gap: 10px;
                    "
                >

                    <i class="fas fa-circle-exclamation"></i>

                    <span>
                        <?= e($teacherLoginError) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
    method="POST"
    action="index.php#teacher-login"
    autocomplete="on"
>

    <?= csrf_field() ?>

    <input
        type="hidden"
        name="teacher_login"
        value="1"
    >

    <label for="teacher_username">
        Username
    </label>

    <input
        type="text"
        id="teacher_username"
        name="teacher_username"
        value="<?= e($_POST['teacher_username'] ?? '') ?>"
        autocomplete="username"
        placeholder="Enter your username"
        required
    >

    <label for="teacher_password">
        Password
    </label>

    <input
        type="password"
        id="teacher_password"
        name="teacher_password"
        autocomplete="current-password"
        placeholder="Enter your password"
        required
    >

    <button type="submit">
        <i class="fas fa-right-to-bracket"></i>
        <span>Login to Teacher Portal</span>
    </button>

</form>

        </div>

    </section>


    <!-- ========================================================
         STUDENT LOGIN
    ========================================================= -->

    <section
        class="app-section <?= $activeSection === 'student-login' ? 'active-section' : '' ?>"
        id="student-login"
        aria-labelledby="studentLoginTitle"
        <?= $activeSection !== 'student-login' ? 'hidden' : '' ?>
    >

        <div class="section-header">

            <span class="eyebrow">
                Student Access
            </span>

        </div>


        <div
            class="login-panel" >

            <div
                style="
                    width: 64px;
                    height: 64px;
                    margin: 0 auto 20px;
                    border-radius: 18px;
                    display: grid;
                    place-items: center;
                    background: linear-gradient(
                        135deg,
                        var(--primary),
                        var(--primary-dark)
                    );
                    color: #fff;
                    font-size: 1.5rem;
                "
            >

                <i class="fas fa-user-graduate"></i>

            </div>


            <h2
                style="
                    text-align: center;
                    margin-bottom: 8px;
                "
            >
                Student Portal
            </h2>


            <p
                style="
                    text-align: center;
                    color: var(--muted);
                    margin-bottom: 24px;
                "
            >
                Enter your student username and password.
            </p>


            <?php if ($studentLoginError): ?>

                <div
                    role="alert"
                    style="
                        margin-bottom: 20px;
                        padding: 12px 14px;
                        border-radius: 12px;
                        background: rgba(231,76,60,.10);
                        border: 1px solid rgba(231,76,60,.25);
                        color: #e74c3c;
                        display: flex;
                        align-items: flex-start;
                        gap: 10px;
                    "
                >

                    <i class="fas fa-circle-exclamation"></i>

                    <span>
                        <?= e($studentLoginError) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="index.php#student-login"
                autocomplete="on"
            >

                <?= csrf_field() ?>


                <input
                    type="hidden"
                    name="student_login"
                    value="1"
                >


                <!-- USERNAME -->

                <label
                    for="student_username"
                >
                    Username
                </label>


                <div
                    style="
                        position: relative;
                        margin-bottom: 18px;
                    "
                >

                    <i
                        class="fas fa-user"
                        style="
                            position: absolute;
                            left: 15px;
                            top: 50%;
                            transform: translateY(-50%);
                            color: var(--muted);
                        "
                    ></i>


                    <input
                        type="text"
                        id="student_username"
                        name="student_username"
                        value="<?= e(
                            $_POST['student_username']
                            ?? ''
                        ) ?>"
                        autocomplete="username"
                        placeholder="Enter your username"
                        required
                        autofocus
                        style="
                            padding-left: 44px;
                            width: 100%;
                            box-sizing: border-box;
                        "
                    >

                </div>


                <!-- PASSWORD -->

                <label
                    for="student_password"
                >
                    Password
                </label>


                <div
                    style="
                        position: relative;
                        margin-bottom: 22px;
                    "
                >

                    <i
                        class="fas fa-lock"
                        style="
                            position: absolute;
                            left: 15px;
                            top: 50%;
                            transform: translateY(-50%);
                            color: var(--muted);
                        "
                    ></i>


                    <input
                        type="password"
                        id="student_password"
                        name="student_password"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        required
                        style="
                            padding-left: 44px;
                            padding-right: 48px;
                            width: 100%;
                            box-sizing: border-box;
                        "
                    >


                    <button
                        type="button"
                        id="toggleStudentPassword"
                        aria-label="Show password"
                        style="
                            position: absolute;
                            right: 8px;
                            top: 50%;
                            transform: translateY(-50%);
                            width: 36px;
                            height: 36px;
                            border: 0;
                            background: transparent;
                            color: var(--muted);
                            cursor: pointer;
                        "
                    >

                        <i class="fas fa-eye"></i>

                    </button>

                </div>


                <button
                    type="submit"
                    style="width: 100%;"
                >

                    <i class="fas fa-right-to-bracket"></i>

                    <span>
                        Login to Student Portal
                    </span>

                </button>

            </form>

        </div>

    </section>

</main>


<!-- ============================================================
     BOTTOM NAVIGATION
============================================================= -->

<nav
    class="bottom-navbar"
    aria-label="Primary bottom navigation"
>

    <a
        class="bottom-navbar-brand"
        href="#home"
        data-target="home"
        aria-label="Go to home"
    >

        <i class="fas fa-graduation-cap"></i>

        <span>
            Edexcel
        </span>

    </a>


    <div
        class="bottom-navbar-menu"
        id="bottomNavbarMenu"
    >

        <span
            class="nav-selector"
            aria-hidden="true"
        >

            <span
                class="selector-corner selector-corner-left"
            ></span>

            <span
                class="selector-corner selector-corner-right"
            ></span>

        </span>


        <ul class="bottom-nav-list">


            <!-- HOME -->

            <li class="bottom-nav-item">

                <a
                    class="bottom-nav-link"
                    href="#home"
                    data-target="home"
                >

                    <i class="fas fa-house"></i>

                    <span>
                        Home
                    </span>

                </a>

            </li>


            <!-- TEACHERS -->

            <li class="bottom-nav-item">

                <a
                    class="bottom-nav-link"
                    href="#teachers"
                    data-target="teachers"
                >

                    <i class="fas fa-chalkboard-user"></i>

                    <span>
                        Teachers
                    </span>

                </a>

            </li>


            <!-- CLASSES -->

            <li class="bottom-nav-item">

                <a
                    class="bottom-nav-link"
                    href="#classes"
                    data-target="classes"
                >

                    <i class="fas fa-book-open"></i>

                    <span>
                        Classes
                    </span>

                </a>

            </li>


            <!-- TEACHER LOGIN -->

            <li class="bottom-nav-item">

                <a
                    class="bottom-nav-link"
                    href="#teacher-login"
                    data-target="teacher-login"
                    aria-label="Teachers Login"
                >

                    <i class="fas fa-user-tie"></i>

                    <span>
                        Teachers Login
                    </span>

                </a>

            </li>


            <!-- STUDENT LOGIN -->

            <li class="bottom-nav-item">

                <a
                    class="bottom-nav-link"
                    href="#student-login"
                    data-target="student-login"
                    aria-label="Student Login"
                >

                    <i class="fas fa-user-graduate"></i>

                    <span>
                        Student Login
                    </span>

                </a>

            </li>


        </ul>

    </div>

</nav>


<!-- ============================================================
     HOMEPAGE JAVASCRIPT
============================================================= -->

<script
    src="assets/js/home.js"
    defer
></script>


<!-- ============================================================
     INLINE LOGIN JAVASCRIPT
============================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
         * ========================================================
         * ACTIVE LOGIN SECTION
         *
         * If PHP returned a login error, make sure the
         * browser URL and visible section remain correct.
         * ========================================================
         */

        const activeSection =
            <?= json_encode($activeSection) ?>;


        if (
            activeSection === 'teacher-login' ||
            activeSection === 'student-login'
        ) {

            setTimeout(
                function () {

                    const expectedHash =
                        '#' + activeSection;


                    if (
                        window.location.hash !==
                        expectedHash
                    ) {

                        history.replaceState(
                            null,
                            '',
                            expectedHash
                        );

                        window.dispatchEvent(
                            new HashChangeEvent(
                                'hashchange'
                            )
                        );
                    }

                },
                100
            );
        }


        /*
         * ========================================================
         * TEACHER PASSWORD SHOW / HIDE
         * ========================================================
         */

        const teacherPassword =
            document.getElementById(
                'teacher_password'
            );

        const toggleTeacherPassword =
            document.getElementById(
                'toggleTeacherPassword'
            );


        if (
            teacherPassword &&
            toggleTeacherPassword
        ) {

            toggleTeacherPassword.addEventListener(
                'click',
                function () {

                    const icon =
                        toggleTeacherPassword.querySelector(
                            'i'
                        );


                    if (
                        teacherPassword.type ===
                        'password'
                    ) {

                        teacherPassword.type =
                            'text';

                        icon.classList.remove(
                            'fa-eye'
                        );

                        icon.classList.add(
                            'fa-eye-slash'
                        );

                        toggleTeacherPassword.setAttribute(
                            'aria-label',
                            'Hide password'
                        );

                    } else {

                        teacherPassword.type =
                            'password';

                        icon.classList.remove(
                            'fa-eye-slash'
                        );

                        icon.classList.add(
                            'fa-eye'
                        );

                        toggleTeacherPassword.setAttribute(
                            'aria-label',
                            'Show password'
                        );
                    }

                }
            );
        }


        /*
         * ========================================================
         * STUDENT PASSWORD SHOW / HIDE
         * ========================================================
         */

        const studentPassword =
            document.getElementById(
                'student_password'
            );

        const toggleStudentPassword =
            document.getElementById(
                'toggleStudentPassword'
            );


        if (
            studentPassword &&
            toggleStudentPassword
        ) {

            toggleStudentPassword.addEventListener(
                'click',
                function () {

                    const icon =
                        toggleStudentPassword.querySelector(
                            'i'
                        );


                    if (
                        studentPassword.type ===
                        'password'
                    ) {

                        studentPassword.type =
                            'text';

                        icon.classList.remove(
                            'fa-eye'
                        );

                        icon.classList.add(
                            'fa-eye-slash'
                        );

                        toggleStudentPassword.setAttribute(
                            'aria-label',
                            'Hide password'
                        );

                    } else {

                        studentPassword.type =
                            'password';

                        icon.classList.remove(
                            'fa-eye-slash'
                        );

                        icon.classList.add(
                            'fa-eye'
                        );

                        toggleStudentPassword.setAttribute(
                            'aria-label',
                            'Show password'
                        );
                    }

                }
            );
        }


        /*
         * ========================================================
         * TEACHER LOGIN DOUBLE-SUBMIT PROTECTION
         * ========================================================
         */

        const teacherForm =
            document.querySelector(
                '#teacher-login form'
            );


        if (teacherForm) {

            teacherForm.addEventListener(
                'submit',
                function () {

                    const button =
                        teacherForm.querySelector(
                            'button[type="submit"]'
                        );


                    if (button) {

                        button.disabled =
                            true;

                        button.innerHTML =
                            '<i class="fas fa-spinner fa-spin"></i>' +
                            '&nbsp; Signing in...';
                    }

                }
            );
        }


        /*
         * ========================================================
         * STUDENT LOGIN DOUBLE-SUBMIT PROTECTION
         * ========================================================
         */

        const studentForm =
            document.querySelector(
                '#student-login form'
            );


        if (studentForm) {

            studentForm.addEventListener(
                'submit',
                function () {

                    const button =
                        studentForm.querySelector(
                            'button[type="submit"]'
                        );


                    if (button) {

                        button.disabled =
                            true;

                        button.innerHTML =
                            '<i class="fas fa-spinner fa-spin"></i>' +
                            '&nbsp; Signing in...';
                    }

                }
            );
        }

    }
);

</script>


</body>

</html>