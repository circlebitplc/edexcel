<?php
// ============================================================
// dashboard.php
// ============================================================

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/payment.php';
require_once 'config/config.php';
require_once 'includes/notifications.php';

require_login();


/* ============================================================
   USER / ROLE
   ============================================================ */

$user = get_logged_in_user($pdo);

$role =
    $_SESSION['role'] ?? '';

$is_admin =
    is_admin();

$is_teacher =
    ($role === 'teacher');


$username =
    htmlspecialchars(
        $_SESSION['username'] ?? 'User',
        ENT_QUOTES,
        'UTF-8'
    );


/*
 * Get teacher ID from the session first.
 * If it is not available, use the logged-in user record.
 */
$teacher_id =
    $_SESSION['teacher_id'] ??
    ($user['teacher_id'] ?? null);


/*
 * Normalise teacher ID.
 */
if (
    $teacher_id !== null &&
    $teacher_id !== ''
) {

    $teacher_id =
        (int)$teacher_id;

} else {

    $teacher_id =
        null;
}


/*
 * IMPORTANT SECURITY CHECK
 *
 * A teacher without a valid teacher_id must NEVER
 * receive institute-wide statistics.
 */
$teacher_has_valid_id =
    $is_teacher &&
    $teacher_id !== null &&
    $teacher_id > 0;


/* ============================================================
   CURRENT WEEK
   ============================================================
 *
 * Monday -> Sunday
 *
 * Example:
 *
 * Monday 10 Aug
 * through
 * Sunday 16 Aug
 *
 * This is used ONLY for the teacher's lesson count.
 */

$current_week_start =
    date(
        'Y-m-d',
        strtotime('monday this week')
    );

$current_week_end =
    date(
        'Y-m-d',
        strtotime('sunday this week')
    );


/* ============================================================
   STAT DEFAULTS
   ============================================================ */

$stats = [

    'total_lessons' =>
        0,

    'total_revenue' =>
        0,

    'pending_count' =>
        0,

    'pending_amount' =>
        0,

    'upcoming_classes' =>
        0,

    'total_teachers' =>
        0,

    'total_classes' =>
        0
];


/* ============================================================
   TOTAL LESSONS
   ============================================================
 *
 * ADMIN:
 *   All lessons.
 *
 * TEACHER:
 *   Only this teacher's lessons
 *   during the current Monday-Sunday week.
 */

if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)
                FROM timetable
                WHERE deleted_at IS NULL
                  AND teacher_id = ?
                  AND date BETWEEN ? AND ?
            ");

        $stmt->execute([
            $teacher_id,
            $current_week_start,
            $current_week_end
        ]);

        $stats['total_lessons'] =
            (int)$stmt->fetchColumn();

    } else {

        /*
         * No valid teacher ID.
         *
         * Never fall back to all lessons.
         */
        $stats['total_lessons'] =
            0;
    }

} else {

    /*
     * Admin:
     * Show all lessons.
     */

    $stmt =
        $pdo->query("
            SELECT COUNT(*)
            FROM timetable
            WHERE deleted_at IS NULL
        ");

    $stats['total_lessons'] =
        (int)$stmt->fetchColumn();
}


/* ============================================================
   TOTAL REVENUE
   ============================================================
 *
 * ADMIN:
 *   Entire system revenue.
 *
 * TEACHER:
 *   Entire/all-time revenue belonging
 *   to that teacher.
 *
 * IMPORTANT:
 *
 * Teacher revenue is NOT restricted
 * to the current week.
 */

if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $stmt =
            $pdo->prepare("
                SELECT
                    COALESCE(
                        SUM(
                            student_count * ?
                        ),
                        0
                    )
                FROM timetable
                WHERE deleted_at IS NULL
                  AND teacher_id = ?
            ");

        $stmt->execute([
            $FEE_PER_STUDENT_LIVE,
            $teacher_id
        ]);

        $stats['total_revenue'] =
            (float)$stmt->fetchColumn();

    } else {

        /*
         * No teacher ID:
         * do not expose revenue.
         */
        $stats['total_revenue'] =
            0;
    }

} else {

    /*
     * ADMIN:
     * Entire system revenue.
     */

    $stmt =
        $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(
                        student_count * ?
                    ),
                    0
                )
            FROM timetable
            WHERE deleted_at IS NULL
        ");

    $stmt->execute([
        $FEE_PER_STUDENT_LIVE
    ]);

    $stats['total_revenue'] =
        (float)$stmt->fetchColumn();
}


/* ============================================================
   PENDING PAYMENTS
   ============================================================
 *
 * ADMIN:
 *   All pending payments.
 *
 * TEACHER:
 *   Only this teacher's pending payments.
 */

$pending_sql = "
    SELECT
        COUNT(*) AS count,

        COALESCE(
            SUM(
                student_count * ?
            ),
            0
        ) AS amount

    FROM timetable

    WHERE payment_status = 'pending'
      AND deleted_at IS NULL
";

$pending_params = [
    $FEE_PER_STUDENT_LIVE
];


if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $pending_sql .= "
            AND teacher_id = ?
        ";

        $pending_params[] =
            $teacher_id;

    } else {

        /*
         * Invalid teacher account:
         * force empty result.
         */
        $pending_sql .= "
            AND 1 = 0
        ";
    }
}


$stmt =
    $pdo->prepare(
        $pending_sql
    );

$stmt->execute(
    $pending_params
);


$pending =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


$stats['pending_count'] =
    (int)(
        $pending['count'] ??
        0
    );


$stats['pending_amount'] =
    (float)(
        $pending['amount'] ??
        0
    );


/* ============================================================
   UPCOMING CLASSES - NEXT 24 HOURS
   ============================================================
 *
 * ADMIN:
 *   All upcoming classes.
 *
 * TEACHER:
 *   Only that teacher's upcoming classes.
 */

$upcoming_sql = "
    SELECT COUNT(*)
    FROM timetable

    WHERE CONCAT(
        date,
        ' ',
        start_time
    ) >= NOW()

      AND CONCAT(
        date,
        ' ',
        start_time
    ) < DATE_ADD(
        NOW(),
        INTERVAL 24 HOUR
    )

      AND deleted_at IS NULL
";

$upcoming_params = [];


if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $upcoming_sql .= "
            AND teacher_id = ?
        ";

        $upcoming_params[] =
            $teacher_id;

    } else {

        /*
         * Never expose other teachers'
         * upcoming classes.
         */
        $upcoming_sql .= "
            AND 1 = 0
        ";
    }
}


$stmt =
    $pdo->prepare(
        $upcoming_sql
    );

$stmt->execute(
    $upcoming_params
);


$stats['upcoming_classes'] =
    (int)$stmt->fetchColumn();


/* ============================================================
   ADMIN-ONLY SYSTEM STATISTICS
   ============================================================ */

if ($is_admin) {


    /* --------------------------------------------------------
       TOTAL TEACHERS
    -------------------------------------------------------- */

    $stmt =
        $pdo->query("
            SELECT COUNT(*)
            FROM teachers
            WHERE deleted_at IS NULL
        ");

    $stats['total_teachers'] =
        (int)$stmt->fetchColumn();


    /* --------------------------------------------------------
       TOTAL STUDENT GROUPS
    -------------------------------------------------------- */

    $stmt =
        $pdo->query("
            SELECT COUNT(*)
            FROM student_classes
            WHERE deleted_at IS NULL
        ");

    $stats['total_classes'] =
        (int)$stmt->fetchColumn();
}


/* ============================================================
   UPCOMING LESSON NOTIFICATIONS
   ============================================================ */

if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $upcoming =
            get_upcoming_lessons(
                $pdo,
                $teacher_id
            );

    } else {

        /*
         * Invalid teacher account:
         * no upcoming lesson data.
         */
        $upcoming = [];
    }

} else {

    /*
     * Admin receives all upcoming lessons.
     */
    $upcoming =
        get_upcoming_lessons(
            $pdo,
            null
        );
}


/* ============================================================
   CONFLICTS
   ============================================================ */

$conflicts = [];


if ($is_admin) {

    $conflicts =
        get_conflicts_next_week(
            $pdo
        );
}


$conflict_count =
    count($conflicts);

?>


<?php include 'includes/header.php'; ?>


<!-- ============================================================
     DASHBOARD
============================================================= -->

<div class="admin-dashboard">


    <!-- ========================================================
         WELCOME SECTION
    ========================================================= -->

    <section class="welcome-section">

        <div
            class="
                d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-2
            "
        >


            <!-- WELCOME TEXT -->

            <div>

                <h1>
                    👋 Welcome, <?= $username ?>!
                </h1>


                <div class="subtitle">


                    <span class="badge-role">

                        <?= ucfirst(
                            htmlspecialchars(
                                $role,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>

                    </span>


                    <?php if ($is_teacher): ?>

                        Here's an overview of your
                        teaching activity.

                    <?php else: ?>

                        Here's an overview of your
                        timetable system.

                    <?php endif; ?>


                </div>

            </div>


            <!-- DATE -->

            <div class="dashboard-date text-end">

                <div>

                    <i class="bi bi-calendar3"></i>

                    <?= date('l, d M Y') ?>

                </div>


                <?php if ($is_teacher): ?>

                    <div class="dashboard-week">

                        Week:

                        <?= date(
                            'd M',
                            strtotime(
                                $current_week_start
                            )
                        ) ?>

                        –

                        <?= date(
                            'd M Y',
                            strtotime(
                                $current_week_end
                            )
                        ) ?>

                    </div>

                <?php endif; ?>

            </div>


        </div>

    </section>


    <!-- ========================================================
         STATISTICS
    ========================================================= -->

    <section
        class="
            dashboard-stats
            row
            g-3
            mb-4
        "
        aria-label="Dashboard statistics"
    >


        <!-- ====================================================
             TOTAL LESSONS
        ===================================================== -->

        <div class="col-md-3 col-6">

            <article class="stat-card">

                <div class="stat-icon">
                    📚
                </div>


                <div class="stat-number">

                    <?= number_format(
                        $stats['total_lessons']
                    ) ?>

                </div>


                <div class="stat-label">

                    <?php if ($is_teacher): ?>

                        Lessons This Week

                    <?php else: ?>

                        Total Lessons

                    <?php endif; ?>

                </div>


                <?php if ($is_teacher): ?>

                    <span class="stat-trend up">

                        <?= date(
                            'd M',
                            strtotime(
                                $current_week_start
                            )
                        ) ?>

                        –

                        <?= date(
                            'd M',
                            strtotime(
                                $current_week_end
                            )
                        ) ?>

                    </span>

                <?php endif; ?>


            </article>

        </div>


        <!-- ====================================================
             TOTAL REVENUE
        ===================================================== -->

        <div class="col-md-3 col-6">

            <article class="stat-card">

                <div class="stat-icon">
                    💰
                </div>


                <div class="stat-number">

                    Rs
                    <?= number_format(
                        $stats['total_revenue']
                    ) ?>

                </div>


                <div class="stat-label">

                    <?php if ($is_teacher): ?>

                        My Total Revenue

                    <?php else: ?>

                        Total Revenue

                    <?php endif; ?>

                </div>


                <?php if ($is_teacher): ?>

                    <span class="stat-trend up">

                        All Time

                    </span>

                <?php endif; ?>


            </article>

        </div>


        <!-- ====================================================
             PENDING PAYMENTS
        ===================================================== -->

        <div class="col-md-3 col-6">

            <article class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>


                <div class="stat-number">

                    Rs
                    <?= number_format(
                        $stats['pending_amount']
                    ) ?>

                </div>


                <div class="stat-label">

                    Pending Payments

                </div>


                <span
                    class="
                        stat-trend
                        <?= (
                            $stats['pending_count'] > 0
                        )
                            ? 'down'
                            : 'up' ?>
                >

                    <?= number_format(
                        $stats['pending_count']
                    ) ?>

                    classes pending

                </span>


            </article>

        </div>


        <!-- ====================================================
             UPCOMING 24 HOURS
        ===================================================== -->

        <div class="col-md-3 col-6">

            <article class="stat-card">

                <div class="stat-icon">
                    📅
                </div>


                <div class="stat-number">

                    <?= number_format(
                        $stats['upcoming_classes']
                    ) ?>

                </div>


                <div class="stat-label">

                    Upcoming (24h)

                </div>


            </article>

        </div>


    </section>


    <!-- ========================================================
         NOTIFICATIONS
    ========================================================= -->

    <section
        class="
            dashboard-notifications
            row
            g-3
            mb-4
        "
        aria-label="Notifications"
    >


        <!-- ====================================================
             PENDING PAYMENTS
        ===================================================== -->

        <?php if (
            $stats['pending_count'] > 0
        ): ?>

            <div class="col-md-6">

                <article
                    class="
                        notif-card
                        notif-warning
                    "
                >

                    <div
                        class="
                            d-flex
                            align-items-start
                        "
                    >

                        <span class="notif-icon">
                            💳
                        </span>


                        <div>

                            <div class="notif-title">

                                Pending Payments

                            </div>


                            <div class="notif-text">

                                <strong>

                                    <?= number_format(
                                        $stats['pending_count']
                                    ) ?>

                                </strong>

                                class(es) with total amount

                                <strong>

                                    Rs
                                    <?= number_format(
                                        $stats['pending_amount']
                                    ) ?>

                                </strong>

                                are pending.

                            </div>


                            <a
                                href="<?= BASE_URL ?>timetable/payments.php?payment_status=pending"
                                class="
                                    btn
                                    btn-sm
                                    btn-warning
                                    mt-2
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-arrow-right
                                    "
                                ></i>

                                View Pending

                            </a>


                        </div>

                    </div>

                </article>

            </div>

        <?php endif; ?>


        <!-- ====================================================
             UPCOMING LESSONS
        ===================================================== -->

        <?php if (
            count($upcoming) > 0
        ): ?>

            <div class="col-md-6">

                <article
                    class="
                        notif-card
                        notif-primary
                    "
                >

                    <div
                        class="
                            d-flex
                            align-items-start
                        "
                    >

                        <span class="notif-icon">
                            🔔
                        </span>


                        <div>

                            <div class="notif-title">

                                Upcoming Lessons

                            </div>


                            <div class="notif-text">

                                Classes starting in
                                the next 24 hours:

                            </div>


                            <ul
                                class="upcoming-list"
                            >


                                <?php foreach (
                                    array_slice(
                                        $upcoming,
                                        0,
                                        3
                                    )
                                    as $lesson
                                ): ?>


                                    <li>


                                        <span>

                                            <?= date(
                                                'd M H:i',
                                                strtotime(
                                                    $lesson['date'] .
                                                    ' ' .
                                                    $lesson['start_time']
                                                )
                                            ) ?>

                                        </span>


                                        <span>

                                            –

                                        </span>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $lesson[
                                                    'subject_name'
                                                ] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <?php if (
                                            !$is_teacher
                                        ): ?>

                                            <span
                                                class="
                                                    text-muted
                                                "
                                            >

                                                (
                                                <?= htmlspecialchars(
                                                    $lesson[
                                                        'teacher_name'
                                                    ] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                                )

                                            </span>

                                        <?php endif; ?>


                                    </li>


                                <?php endforeach; ?>


                                <?php if (
                                    count($upcoming) > 3
                                ): ?>

                                    <li class="more-item">

                                        +

                                        <?= number_format(
                                            count($upcoming) - 3
                                        ) ?>

                                        more

                                    </li>

                                <?php endif; ?>


                            </ul>


                            <a
                                href="<?= BASE_URL ?>timetable/index.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                class="
                                    btn
                                    btn-sm
                                    btn-primary
                                    mt-2
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-arrow-right
                                    "
                                ></i>

                                View All

                            </a>


                        </div>

                    </div>

                </article>

            </div>

        <?php endif; ?>


        <!-- ====================================================
             CONFLICTS - ADMIN ONLY
        ===================================================== -->

        <?php if (
            $is_admin &&
            $conflict_count > 0
        ): ?>

            <div class="col-12">

                <article
                    class="
                        notif-card
                        notif-danger
                    "
                >

                    <div
                        class="
                            d-flex
                            align-items-start
                        "
                    >

                        <span class="notif-icon">
                            ⚠️
                        </span>


                        <div class="notif-content">


                            <div
                                class="
                                    d-flex
                                    justify-content-between
                                    align-items-center
                                    flex-wrap
                                    gap-2
                                "
                            >

                                <div class="notif-title">

                                    Potential Conflicts

                                </div>


                                <span
                                    class="
                                        badge
                                        bg-danger
                                    "
                                >

                                    <?= number_format(
                                        $conflict_count
                                    ) ?>

                                    conflicts found

                                </span>

                            </div>


                            <div
                                class="
                                    notif-text
                                    mt-1
                                "
                            >

                                The following scheduling
                                conflicts were detected
                                in the next 7 days:

                            </div>


                            <ul
                                class="
                                    conflict-list
                                    mt-2
                                "
                            >


                                <?php foreach (
                                    array_slice(
                                        $conflicts,
                                        0,
                                        5
                                    )
                                    as $conflict
                                ): ?>


                                    <li>

                                        <i
                                            class="
                                                bi
                                                bi-exclamation-triangle
                                                text-danger
                                            "
                                        ></i>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $conflict[
                                                    'teacher1'
                                                ] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <span>
                                            vs
                                        </span>


                                        <strong>

                                            <?= htmlspecialchars(
                                                $conflict[
                                                    'teacher2'
                                                ] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <span
                                            class="
                                                text-muted
                                            "
                                        >

                                            on

                                            <?= date(
                                                'd M Y',
                                                strtotime(
                                                    $conflict[
                                                        'date'
                                                    ]
                                                )
                                            ) ?>

                                        </span>


                                    </li>


                                <?php endforeach; ?>


                                <?php if (
                                    $conflict_count > 5
                                ): ?>

                                    <li
                                        class="
                                            more-item
                                            text-muted
                                        "
                                    >

                                        +

                                        <?= number_format(
                                            $conflict_count - 5
                                        ) ?>

                                        more conflicts

                                    </li>

                                <?php endif; ?>


                            </ul>


                            <a
                                href="<?= BASE_URL ?>timetable/index.php"
                                class="
                                    btn
                                    btn-sm
                                    btn-danger
                                    mt-1
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-arrow-right
                                    "
                                ></i>

                                Resolve Conflicts

                            </a>


                        </div>

                    </div>

                </article>

            </div>

        <?php endif; ?>


        <!-- ====================================================
             ALL CLEAR
        ===================================================== -->

        <?php if (
            $stats['pending_count'] == 0 &&
            count($upcoming) == 0 &&
            $conflict_count == 0
        ): ?>

            <div class="col-12">

                <article
                    class="
                        notif-card
                        notif-success
                    "
                >

                    <div
                        class="
                            d-flex
                            align-items-start
                        "
                    >

                        <span class="notif-icon">
                            ✅
                        </span>


                        <div>

                            <div class="notif-title">

                                All Clear!

                            </div>


                            <div class="notif-text">

                                <?php if ($is_teacher): ?>

                                    No pending payments or
                                    upcoming lessons to report.

                                    Everything is running
                                    smoothly.

                                <?php else: ?>

                                    No pending payments,
                                    upcoming lessons, or
                                    conflicts to report.

                                    Everything is running
                                    smoothly.

                                <?php endif; ?>

                            </div>


                        </div>

                    </div>

                </article>

            </div>

        <?php endif; ?>


    </section>


    <!-- ========================================================
         QUICK ACTIONS
    ========================================================= -->

    <section
        class="dashboard-quick-actions"
        aria-labelledby="quickActionsTitle"
    >


        <h5
            id="quickActionsTitle"
            class="mb-3"
        >

            <i
                class="
                    bi
                    bi-lightning-fill
                    text-warning
                "
            ></i>

            Quick Actions

        </h5>


        <div
            class="
                row
                g-3
                mb-4
            "
        >


            <!-- =================================================
                 ADD LESSON
            ================================================== -->

            <div class="col-md-3 col-6">

                <a
                    href="<?= BASE_URL ?>timetable/add.php"
                    class="action-card"
                >

                    <span class="action-icon">
                        ➕
                    </span>


                    <div class="action-title">

                        Add Lesson

                    </div>


                    <div class="action-desc">

                        Schedule a new class

                    </div>


                </a>

            </div>


            <!-- =================================================
                 MANAGE TIMETABLE
            ================================================== -->

            <div class="col-md-3 col-6">

                <a
                    href="<?= BASE_URL ?>timetable/index.php"
                    class="action-card"
                >

                    <span class="action-icon">
                        📋
                    </span>


                    <div class="action-title">

                        Manage Timetable

                    </div>


                    <div class="action-desc">

                        View / edit existing entries

                    </div>


                </a>

            </div>


            <!-- =================================================
                 ADMIN ACTIONS
            ================================================== -->

            <?php if ($is_admin): ?>


                <!-- TEACHERS -->

                <div class="col-md-3 col-6">

                    <a
                        href="<?= BASE_URL ?>teachers/index.php"
                        class="action-card"
                    >

                        <span class="action-icon">
                            👨‍🏫
                        </span>


                        <div class="action-title">

                            Manage Teachers

                        </div>


                        <div class="action-desc">

                            Add or edit teachers

                        </div>


                    </a>

                </div>


                <!-- PAYMENTS -->

                <div class="col-md-3 col-6">

                    <a
                        href="<?= BASE_URL ?>timetable/payments.php"
                        class="action-card"
                    >

                        <span class="action-icon">
                            💳
                        </span>


                        <div class="action-title">

                            Payments

                        </div>


                        <div class="action-desc">

                            Track pending payments

                        </div>


                    </a>

                </div>


                <!-- WEEKLY -->

                <div class="col-md-3 col-6">

                    <a
                        href="<?= BASE_URL ?>timetable/weekly.php?type=teacher&id=0"
                        class="action-card"
                    >

                        <span class="action-icon">
                            📅
                        </span>


                        <div class="action-title">

                            Weekly View

                        </div>


                        <div class="action-desc">

                            See the full week schedule

                        </div>


                    </a>

                </div>


                <!-- REVENUE -->

                <div class="col-md-3 col-6">

                    <a
                        href="<?= BASE_URL ?>reports/revenue.php"
                        class="action-card"
                    >

                        <span class="action-icon">
                            📊
                        </span>


                        <div class="action-title">

                            Revenue Reports

                        </div>


                        <div class="action-desc">

                            View income statistics

                        </div>


                    </a>

                </div>


                <!-- AUDIT -->

                <div class="col-md-3 col-6">

                    <a
                        href="<?= BASE_URL ?>admin/audit_log.php"
                        class="action-card"
                    >

                        <span class="action-icon">
                            📜
                        </span>


                        <div class="action-title">

                            Audit Log

                        </div>


                        <div class="action-desc">

                            Track all system changes

                        </div>


                    </a>

                </div>


                <!-- SETTINGS -->

                <div class="col-md-3 col-6">

                    <a
                        href="<?= BASE_URL ?>admin/settings.php"
                        class="action-card"
                    >

                        <span class="action-icon">
                            ⚙️
                        </span>


                        <div class="action-title">

                            Settings

                        </div>


                        <div class="action-desc">

                            Configure system

                        </div>


                    </a>

                </div>


            <?php endif; ?>


        </div>

    </section>


    <!-- ========================================================
         SYSTEM INFORMATION
         ADMIN ONLY
    ========================================================= -->

    <?php if ($is_admin): ?>


        <section
            class="
                dashboard-system-info
                row
                g-3
            "
            aria-label="System information"
        >


            <!-- =================================================
                 SYSTEM OVERVIEW
            ================================================== -->

            <div class="col-md-6">

                <article
                    class="
                        card
                        border-0
                        shadow-sm
                    "
                >

                    <div class="card-body">

                        <h6 class="card-title">

                            <i
                                class="
                                    bi
                                    bi-database
                                "
                            ></i>

                            System Overview

                        </h6>


                        <hr>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Teachers

                            </span>


                            <span class="fw-semibold">

                                <?= number_format(
                                    $stats['total_teachers']
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Student Groups

                            </span>


                            <span class="fw-semibold">

                                <?= number_format(
                                    $stats['total_classes']
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Total Lessons

                            </span>


                            <span class="fw-semibold">

                                <?= number_format(
                                    $stats['total_lessons']
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Total Revenue

                            </span>


                            <span class="fw-semibold">

                                Rs
                                <?= number_format(
                                    $stats['total_revenue']
                                ) ?>

                            </span>

                        </div>


                    </div>

                </article>

            </div>


            <!-- =================================================
                 QUICK STATS
            ================================================== -->

            <div class="col-md-6">

                <article
                    class="
                        card
                        border-0
                        shadow-sm
                    "
                >

                    <div class="card-body">

                        <h6 class="card-title">

                            <i
                                class="
                                    bi
                                    bi-info-circle
                                "
                            ></i>

                            Quick Stats

                        </h6>


                        <hr>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Pending Payments

                            </span>


                            <span
                                class="
                                    fw-semibold
                                    <?= (
                                        $stats[
                                            'pending_count'
                                        ] > 0
                                    )
                                        ? 'text-danger'
                                        : '' ?>
                                "
                            >

                                <?= number_format(
                                    $stats[
                                        'pending_count'
                                    ]
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Upcoming Classes (24h)

                            </span>


                            <span class="fw-semibold">

                                <?= number_format(
                                    $stats[
                                        'upcoming_classes'
                                    ]
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Conflicts Found

                            </span>


                            <span
                                class="
                                    fw-semibold
                                    <?= (
                                        $conflict_count > 0
                                    )
                                        ? 'text-danger'
                                        : 'text-success' ?>
                                "
                            >

                                <?= number_format(
                                    $conflict_count
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="
                                d-flex
                                justify-content-between
                                py-1
                            "
                        >

                            <span class="text-muted">

                                Last Login

                            </span>


                            <span class="fw-semibold">

                                <?= date(
                                    'd M Y H:i',
                                    strtotime(
                                        $_SESSION[
                                            'last_activity'
                                        ] ?? 'now'
                                    )
                                ) ?>

                            </span>

                        </div>


                    </div>

                </article>

            </div>


        </section>


    <?php endif; ?>


</div>


<?php include 'includes/footer.php'; ?>