<?php
// ============================================================
// dashboard.php
// ============================================================

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/payment.php';
require_once 'config/config.php';
require_once 'config/campus.php';
require_once 'config/classroom.php';
require_once 'includes/helpers.php';
require_once 'includes/notifications.php';
require_once 'vendor/autoload.php';
if (isset($pdo) && $pdo instanceof PDO) {
    \Edexcel\Services\ClassSessionFeeCalculator::ensureSchema($pdo);
}

require_login();
if (function_exists('ensure_classroom_schema')) {
    ensure_classroom_schema($pdo);
}

/*
 * Students have a dedicated portal at /student/dashboard.php.
 * Do not expose the admin/teacher dashboard statistics to students.
 */
if (($_SESSION['role'] ?? '') === 'student') {
    header('Location: student/dashboard.php');
    exit;
}


/* ============================================================
   USER / ROLE
   ============================================================ */

$user = get_logged_in_user($pdo);
if (!$user) {
    destroy_app_session();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$role =
    $_SESSION['role'] ?? '';

$is_admin =
    is_admin();

$has_teacher_role =
    is_teacher();

$active_view = strtolower(trim((string)($_GET['view'] ?? $_SESSION['active_dashboard_view'] ?? 'admin')));
if ($is_admin && $has_teacher_role) {
    if ($active_view === 'teacher') {
        $_SESSION['active_dashboard_view'] = 'teacher';
        $is_teacher = true;
    } else {
        $_SESSION['active_dashboard_view'] = 'admin';
        $is_teacher = false;
    }
} else {
    $is_teacher =
        ($role === 'teacher');
}


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
   CURRENT TEACHER PHOTO
   ============================================================ */

$teacher_photo_url = '';

if (
    $is_teacher &&
    $teacher_has_valid_id
) {

    $teacher_photo_dir =
        __DIR__ .
        '/assets/images/teachers';

    foreach (
        [
            'png',
            'jpg',
            'jpeg',
            'webp',
            'gif'
        ] as $photo_extension
    ) {

        $candidate =
            $teacher_photo_dir .
            '/' .
            $teacher_id .
            '.' .
            $photo_extension;

        if (
            is_file(
                $candidate
            )
        ) {

            $teacher_photo_url =
                BASE_URL .
                'assets/images/teachers/' .
                $teacher_id .
                '.' .
                $photo_extension .
                '?v=' .
                filemtime(
                    $candidate
                );

            break;
        }
    }
}


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

    'institute_fee_due' =>
        0,

    'institute_fee_paid' =>
        0,

    'institute_fee_pending' =>
        0,

    'teacher_net_revenue' =>
        0,

    'transaction_handling_due' =>
        0,

    'online_student_fees' =>
        0,

    'online_institute_fees' =>
        0,

    'online_handling_fees' =>
        0,

    'online_teacher_net' =>
        0,

    'online_lessons' =>
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
   FINANCIAL SUMMARY
   ============================================================

   STUDENT REVENUE
   ----------------
   Actual amount charged by the teacher:

       student_count × class_fee_per_student

   INSTITUTE FEE
   -------------
   Existing duration-based institute fee:

       0–2.5 hours = Rs.500/student
       3–3.5 hours = Rs.700/student
       4 hours     = Rs.900/student
       5+ hours    = Rs.1100/student

   TEACHER NET
   -----------
       Student Revenue - Institute Fee Due
*/


$financial_rows = [];


/*
 * Admin:
 *     all timetable sessions
 *
 * Teacher:
 *     only that teacher's sessions
 */
$financial_sql = "
    SELECT
        student_count,
        class_fee_per_student,
        start_time,
        end_time,
        payment_status,
        delivery_mode,
        fee_rule,
        institute_online_fee,
        transaction_handling_fee,
        teacher_net_amount
    FROM timetable
    WHERE deleted_at IS NULL
";

$financial_params = [];


if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $financial_sql .= "
            AND teacher_id = ?
        ";

        $financial_params[] =
            $teacher_id;

    } else {

        /*
         * Invalid teacher account:
         * return no financial information.
         */
        $financial_sql .= "
            AND 1 = 0
        ";
    }
}


$financial_stmt =
    $pdo->prepare(
        $financial_sql
    );

$financial_stmt->execute(
    $financial_params
);


$financial_rows =
    $financial_stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


foreach (
    $financial_rows as $financial_row
) {

    $settlement = \Edexcel\Services\ClassSessionFeeCalculator::settlement($financial_row);
    $students = (int)$settlement['students'];
    $student_revenue = (float)$settlement['totals']['gross_class_fee'];
    $institute_fee = (float)$settlement['totals']['institute_fee'];
    $handling_fee = (float)$settlement['totals']['transaction_handling_fee'];
    if (!empty($settlement['uses_online_rule'])) {
        $stats['online_lessons']++;
        $stats['online_student_fees'] += (float)$settlement['totals']['gross_class_fee'];
        $stats['online_institute_fees'] += (float)$settlement['totals']['institute_online_fee'];
        $stats['online_handling_fees'] += $handling_fee;
        $stats['online_teacher_net'] += (float)$settlement['totals']['teacher_net_amount'];
    }


    /*
     * Add to totals.
     */
    $stats['total_revenue'] +=
        $student_revenue;


    $stats['institute_fee_due'] +=
        $institute_fee;

    $stats['transaction_handling_due'] +=
        $handling_fee;


    if (
        ($financial_row['payment_status'] ?? '') ===
        'paid'
    ) {

        $stats['institute_fee_paid'] +=
            $institute_fee;

    } elseif (
        ($financial_row['payment_status'] ?? '') ===
        'pending'
    ) {

        $stats['institute_fee_pending'] +=
            $institute_fee;
    }
}


/*
 * Teacher's actual net earnings after institute fees.
 */
$stats['teacher_net_revenue'] =
    $stats['total_revenue'] -
    $stats['institute_fee_due'] -
    $stats['transaction_handling_due'];


/*
 * Keep the existing pending session count,
 * but make pending_amount represent the actual
 * institute fee still owed by the teacher.
 */
$stats['pending_amount'] =
    $stats['institute_fee_pending'];


/* ============================================================
   PENDING SESSION COUNT
   ============================================================ */

$pending_count_sql = "
    SELECT COUNT(*)
    FROM timetable
    WHERE payment_status = 'pending'
      AND deleted_at IS NULL
";

$pending_count_params = [];


if ($is_teacher) {

    if ($teacher_has_valid_id) {

        $pending_count_sql .= "
            AND teacher_id = ?
        ";

        $pending_count_params[] =
            $teacher_id;

    } else {

        $pending_count_sql .= "
            AND 1 = 0
        ";
    }
}


$pending_count_stmt =
    $pdo->prepare(
        $pending_count_sql
    );

$pending_count_stmt->execute(
    $pending_count_params
);


$stats['pending_count'] =
    (int)$pending_count_stmt->fetchColumn();



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


/* ============================================================
   TEACHER DASHBOARD NOTIFICATIONS
   ============================================================ */

$teacher_notifications = [];
$teacher_notification_unread = 0;
$admin_notifications = [];
$admin_notification_unread = 0;

if (isset($pdo) && $pdo instanceof PDO) {
    ensure_campus_schema($pdo);
}

if ($is_teacher && $teacher_has_valid_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, teacher_id, title, message, type, link, is_read, created_at
            FROM teacher_notifications
            WHERE teacher_id = ?
            ORDER BY is_read ASC, created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$teacher_id]);
        $teacher_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($teacher_notifications as $n) {
            if ((int)$n['is_read'] === 0) {
                $teacher_notification_unread++;
            }
        }
    } catch (Throwable $e) {
        error_log('Teacher notification load failed: ' . $e->getMessage());
    }
}

if ($is_admin) {
    try {
        $adminUserId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT id, user_id, title, message, type, link, is_read, created_at
            FROM admin_notifications
            WHERE user_id = ?
            ORDER BY is_read ASC, created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$adminUserId]);
        $admin_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($admin_notifications as $n) {
            if ((int)$n['is_read'] === 0) {
                $admin_notification_unread++;
            }
        }
    } catch (Throwable $e) {
        error_log('Admin notification load failed: ' . $e->getMessage());
    }
}

$onepay_payment_alerts = [];
$onepay_title_match = static function (string $title): bool {
    $t = strtolower($title);
    return str_contains($t, 'student paid ·')
        || str_contains($t, 'online payment')
        || str_contains($t, 'onepay')
        || str_contains($t, 'class fee paid');
};
foreach ($teacher_notifications as $n) {
    if ($onepay_title_match((string)($n['title'] ?? '')) && (int)($n['is_read'] ?? 1) === 0) {
        $onepay_payment_alerts[] = ['audience' => 'teacher', 'row' => $n];
    }
}
foreach ($admin_notifications as $n) {
    if ($onepay_title_match((string)($n['title'] ?? '')) && (int)($n['is_read'] ?? 1) === 0) {
        $onepay_payment_alerts[] = ['audience' => 'admin', 'row' => $n];
    }
}

$dashboard_notif_href = static function (string $link): string {
    $link = trim($link);
    if ($link === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $link) || str_starts_with($link, '/')) {
        return $link;
    }
    return rtrim((string)BASE_URL, '/') . '/' . ltrim($link, '/');
};

/* ============================================================
   CLASS FEE PAYMENTS BY DATE (teacher / admin)
   ============================================================ */

$today_payment_date = trim((string)($_GET['pay_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today_payment_date)) {
    $today_payment_date = date('Y-m-d');
}
$is_payment_date_today = ($today_payment_date === date('Y-m-d'));
$today_payments_by_lesson = [];
$today_payments_total = 0.0;
$today_payments_count = 0;

if (
    isset($pdo) &&
    $pdo instanceof PDO &&
    (
        ($is_teacher && $teacher_has_valid_id) ||
        $is_admin
    )
) {
    try {
        if (!class_exists(\Edexcel\Services\StudentLessonFeeService::class)) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
        $paySql = "
            SELECT
                p.id,
                p.student_id,
                p.timetable_id,
                p.amount,
                p.gateway,
                p.gateway_response,
                p.paid_at,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username, 'Student') AS student_name,
                COALESCE(
                    NULLIF(TRIM(sp.whatsapp_number), ''),
                    NULLIF(TRIM(u.username), '')
                ) AS phone,
                s.name AS subject_name,
                c.name AS class_name,
                tt.date,
                tt.start_time,
                tt.end_time,
                tt.delivery_mode,
                tt.fee_rule,
                p.gross_class_fee,
                p.institute_online_fee,
                p.transaction_handling_fee,
                p.teacher_net_amount
            FROM payment_transactions p
            JOIN timetable tt ON tt.id = p.timetable_id AND tt.deleted_at IS NULL
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN users u ON u.id = p.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE p.status = 'paid'
              AND tt.date = ?
        ";
        $payParams = [$today_payment_date];
        if (!$is_admin && $teacher_has_valid_id) {
            $paySql .= ' AND (tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
            $payParams[] = $teacher_id;
            $payParams[] = $teacher_id;
        }
        $paySql .= ' ORDER BY tt.start_time ASC, c.name ASC, student_name ASC, p.id DESC';
        $payStmt = $pdo->prepare($paySql);
        $payStmt->execute($payParams);
        $seenTxn = [];
        foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $payRow) {
            $txnId = (int)($payRow['id'] ?? 0);
            if ($txnId > 0 && isset($seenTxn[$txnId])) {
                continue;
            }
            $seenTxn[$txnId] = true;
            $lessonId = (int)($payRow['timetable_id'] ?? 0);
            if ($lessonId < 1) {
                continue;
            }
            if (!isset($today_payments_by_lesson[$lessonId])) {
                $start = !empty($payRow['start_time'])
                    ? date('g:i A', strtotime((string)$payRow['start_time']))
                    : '';
                $today_payments_by_lesson[$lessonId] = [
                    'timetable_id' => $lessonId,
                    'subject_name' => (string)($payRow['subject_name'] ?? 'Class'),
                    'class_name' => (string)($payRow['class_name'] ?? ''),
                    'start_time' => $start,
                    'date' => (string)($payRow['date'] ?? $today_payment_date),
                    'fee_rule' => (string)($payRow['fee_rule'] ?? ''),
                    'delivery_mode' => (string)($payRow['delivery_mode'] ?? ''),
                    'payments' => [],
                    'total' => 0.0,
                ];
            }
            $amount = (float)($payRow['amount'] ?? 0);
            $gateway = \Edexcel\Services\StudentLessonFeeService::displayMethod($payRow);
            if ($gateway === '') {
                $gateway = 'onepay';
            }
            $phoneRaw = (string)($payRow['phone'] ?? '');
            $phoneDisplay = function_exists('campus_display_phone')
                ? campus_display_phone($phoneRaw)
                : $phoneRaw;
            $today_payments_by_lesson[$lessonId]['payments'][] = [
                'student_name' => (string)($payRow['student_name'] ?? 'Student'),
                'phone' => $phoneDisplay !== '' ? $phoneDisplay : '—',
                'amount' => $amount,
                'gateway' => $gateway,
                'method' => \Edexcel\Services\StudentLessonFeeService::gatewayLabel($gateway),
                'paid_at' => (string)($payRow['paid_at'] ?? ''),
                'gross_class_fee' => $payRow['gross_class_fee'] ?? null,
                'institute_online_fee' => $payRow['institute_online_fee'] ?? null,
                'transaction_handling_fee' => $payRow['transaction_handling_fee'] ?? null,
                'teacher_net_amount' => $payRow['teacher_net_amount'] ?? null,
            ];
            $today_payments_by_lesson[$lessonId]['total'] += $amount;
            $today_payments_total += $amount;
            $today_payments_count++;
        }
    } catch (Throwable $e) {
        error_log('Dashboard today payments: ' . $e->getMessage());
        $today_payments_by_lesson = [];
        $today_payments_total = 0.0;
        $today_payments_count = 0;
    }
}

?>

<?php include 'includes/header.php'; ?>

<?php
$base = htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8');
$fmtInt = static fn($n): string => number_format((float)$n);
$fmtMoney = static fn($n): string => 'Rs ' . number_format((float)$n);
$hasAttention = ($stats['pending_count'] > 0)
    || (count($upcoming) > 0)
    || ($is_admin && $conflict_count > 0)
    || !empty($onepay_payment_alerts);
?>

<div class="admin-dashboard dash-home">

    <?php if ($is_teacher && (($user['teacher_oauth_status'] ?? 'not_linked') !== 'linked')): ?>
        <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between flex-wrap gap-3 shadow-sm mb-4" role="alert" style="border-radius:12px; padding:16px 20px;">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2 text-warning"><i class="bi bi-shield-exclamation"></i></div>
                <div>
                    <strong class="d-block" style="font-size:1.05rem;">Action Required: Link your Google Account</strong>
                    <div class="text-muted small">Edexcel College is migrating teacher accounts to Google OAuth. Please link your Google account to ensure uninterrupted access. Your classes, timetable, and records will not be changed.</div>
                </div>
            </div>
            <a href="/auth/google/start.php?intent=link_teacher" class="btn btn-warning fw-bold text-dark px-3 py-2">
                <i class="bi bi-google me-1"></i>Link Google Account
            </a>
        </div>
    <?php endif; ?>

    <!-- ========== Welcome ========== -->
    <section class="welcome-section dash-welcome">
        <div class="dash-welcome-main">
            <div class="teacher-welcome-content">
                <?php if ($is_teacher && $teacher_has_valid_id): ?>
                    <div class="dashboard-teacher-avatar">
                        <?php if ($teacher_photo_url): ?>
                            <img src="<?= htmlspecialchars($teacher_photo_url, ENT_QUOTES, 'UTF-8') ?>" alt="Your profile photo">
                        <?php else: ?>
                            <i class="bi bi-person"></i>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h1>Welcome, <?= $username ?></h1>
                    <div class="subtitle">
                        <span class="badge-role"><?= ucfirst(htmlspecialchars($role, ENT_QUOTES, 'UTF-8')) ?></span>
                        <?php if ($is_teacher): ?>
                            Your teaching overview for this week.
                        <?php else: ?>
                            College operations at a glance — start with today, then jump to the tools you need.
                        <?php endif; ?>
                    </div>
                    <?php if ($is_admin && $has_teacher_role): ?>
                        <div class="mt-2 d-inline-flex gap-2">
                            <a href="dashboard.php?view=admin" class="btn btn-sm <?= !$is_teacher ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                <i class="bi bi-shield-check me-1"></i> Admin Dashboard
                            </a>
                            <a href="dashboard.php?view=teacher" class="btn btn-sm <?= $is_teacher ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                <i class="bi bi-person-workspace me-1"></i> Teacher Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dash-welcome-aside">
                <div class="dashboard-date text-end">
                    <div><i class="bi bi-calendar3"></i> <?= date('l, d M Y') ?></div>
                    <?php if ($is_teacher): ?>
                        <div class="dashboard-week">
                            Week: <?= date('d M', strtotime($current_week_start)) ?> – <?= date('d M Y', strtotime($current_week_end)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($is_teacher || $is_admin): ?>
                    <div class="dash-welcome-actions">
                        <a class="btn btn-primary btn-sm" href="<?= $base ?>campus/today.php">
                            <i class="bi bi-sun"></i> Today’s classes
                        </a>
                        <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>campus/lesson_fees.php?date=<?= urlencode(date('Y-m-d')) ?>">
                            <i class="bi bi-cash-coin"></i> Class fees
                        </a>
                        <?php if ($is_teacher && !$is_admin && isset($pdo) && $pdo instanceof PDO && teacher_manual_payment_enabled($pdo)): ?>
                            <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>campus/lesson_fees.php?date=<?= urlencode(date('Y-m-d')) ?>">
                                <i class="bi bi-pencil-square"></i> Online class payment
                            </a>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>admin/command_center.php">
                                <i class="bi bi-speedometer2"></i> Command center
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========== At a glance ========== -->
    <section class="dash-section" aria-labelledby="dashStatsTitle">
        <div class="dash-section-head">
            <h2 id="dashStatsTitle" class="dash-section-title">At a glance</h2>
        </div>
        <div class="dashboard-stats row g-3 mb-0" aria-label="Dashboard statistics">
            <div class="col-md-3 col-6">
                <article class="stat-card">
                    <div class="stat-icon"><i class="bi bi-journal-bookmark"></i></div>
                    <div class="stat-number"><?= $fmtInt($stats['total_lessons']) ?></div>
                    <div class="stat-label"><?= $is_teacher ? 'Lessons this week' : 'Total lessons' ?></div>
                    <?php if ($is_teacher): ?>
                        <span class="stat-trend up"><?= date('d M', strtotime($current_week_start)) ?> – <?= date('d M', strtotime($current_week_end)) ?></span>
                    <?php endif; ?>
                </article>
            </div>
            <div class="col-md-3 col-6">
                <article class="stat-card">
                    <div class="stat-icon"><i class="bi bi-currency-exchange"></i></div>
                    <div class="stat-number"><?= $fmtMoney($stats['total_revenue']) ?></div>
                    <div class="stat-label"><?= $is_teacher ? 'My total revenue' : 'Total revenue' ?></div>
                    <?php if ($is_teacher): ?>
                        <span class="stat-trend up">All time</span>
                    <?php endif; ?>
                </article>
            </div>
            <div class="col-md-3 col-6">
                <article class="stat-card<?= $stats['pending_count'] > 0 ? ' is-alert' : '' ?>">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?= $fmtMoney($stats['pending_amount']) ?></div>
                    <div class="stat-label">Pending payments</div>
                    <span class="stat-trend <?= $stats['pending_count'] > 0 ? 'down' : 'up' ?>">
                        <?= $fmtInt($stats['pending_count']) ?> classes pending
                    </span>
                </article>
            </div>
            <div class="col-md-3 col-6">
                <article class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                    <div class="stat-number"><?= $fmtInt($stats['upcoming_classes']) ?></div>
                    <div class="stat-label">Upcoming (24h)</div>
                </article>
            </div>
            <?php if ($is_admin): ?>
                <div class="col-md-3 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
                        <div class="stat-number"><?= $fmtInt($stats['total_teachers']) ?></div>
                        <div class="stat-label">Teachers</div>
                    </article>
                </div>
                <div class="col-md-3 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-collection"></i></div>
                        <div class="stat-number"><?= $fmtInt($stats['total_classes']) ?></div>
                        <div class="stat-label">Student groups</div>
                    </article>
                </div>
                <div class="col-md-3 col-6">
                    <article class="stat-card<?= $conflict_count > 0 ? ' is-alert' : '' ?>">
                        <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-number"><?= $fmtInt($conflict_count) ?></div>
                        <div class="stat-label">Conflicts (7 days)</div>
                    </article>
                </div>
                <div class="col-md-3 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-bell"></i></div>
                        <div class="stat-number"><?= $fmtInt($admin_notification_unread) ?></div>
                        <div class="stat-label">Unread alerts</div>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($is_teacher): ?>
        <section class="dash-section" aria-labelledby="dashFinanceTitle">
            <div class="dash-section-head">
                <h2 id="dashFinanceTitle" class="dash-section-title">My finances</h2>
            </div>
            <div class="row g-3">
                <div class="col-md-4 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-number"><?= $fmtMoney($stats['total_revenue']) ?></div>
                        <div class="stat-label">Student revenue</div>
                        <span class="stat-trend up">Amount charged to students</span>
                    </article>
                </div>
                <div class="col-md-4 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-building"></i></div>
                        <div class="stat-number"><?= $fmtMoney($stats['institute_fee_due']) ?></div>
                        <div class="stat-label">Institute fee due</div>
                    </article>
                </div>
                <div class="col-md-4 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                        <div class="stat-number"><?= $fmtMoney($stats['institute_fee_paid']) ?></div>
                        <div class="stat-label">Institute fee paid</div>
                        <span class="stat-trend up">Paid to institute</span>
                    </article>
                </div>
                <div class="col-md-4 col-6">
                    <article class="stat-card<?= $stats['institute_fee_pending'] > 0 ? ' is-alert' : '' ?>">
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-number"><?= $fmtMoney($stats['institute_fee_pending']) ?></div>
                        <div class="stat-label">Institute fee pending</div>
                        <span class="stat-trend <?= $stats['institute_fee_pending'] > 0 ? 'down' : 'up' ?>">
                            <?= $stats['institute_fee_pending'] > 0 ? 'Payment required' : 'All paid' ?>
                        </span>
                    </article>
                </div>
                <div class="col-md-4 col-6">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="stat-number"><?= $fmtMoney($stats['teacher_net_revenue']) ?></div>
                        <div class="stat-label">My net revenue</div>
                        <span class="stat-trend up"><?= $stats['transaction_handling_due'] > 0 ? 'After institute and online handling fees' : 'After institute fees' ?></span>
                    </article>
                </div>
                <?php if ((int)$stats['online_lessons'] > 0): ?>
                    <div class="col-12">
                        <article class="stat-card">
                            <div class="stat-label">Online classes</div>
                            <div class="small mt-2">
                                Student fees <?= $fmtMoney($stats['online_student_fees']) ?>
                                · Institute fee <?= $fmtMoney($stats['online_institute_fees']) ?>
                                · Transaction &amp; handling <?= $fmtMoney($stats['online_handling_fees']) ?>
                                · Teacher net <?= $fmtMoney($stats['online_teacher_net']) ?>
                            </div>
                        </article>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $classroomNow = ['live' => null, 'next' => null, 'role' => $role, 'live_count' => 0];
    try {
        $classroomNow = classroom_dashboard_cards($pdo, [
            'role' => $role,
            'teacher_id' => $teacher_id,
            'user_id' => $_SESSION['user_id'] ?? 0,
        ]);
    } catch (Throwable $e) {
        error_log('Dashboard classroom cards: ' . $e->getMessage());
    }
    include __DIR__ . '/includes/classroom_now.php';
    ?>

    <?php if ($is_teacher || $is_admin): ?>
        <!-- ========== Today’s operations ========== -->
        <section class="dash-section" aria-labelledby="dashOpsTitle">
            <div class="dash-section-head">
                <h2 id="dashOpsTitle" class="dash-section-title">Today’s operations</h2>
                <p class="dash-section-sub">Payments collected and items that need a quick look.</p>
            </div>

            <div class="dash-ops-grid">
                <div class="dash-ops-main">
                    <?php if (($is_teacher && $teacher_has_valid_id) || $is_admin): ?>
                        <div class="today-payments-board" id="todayPaymentsBoard">
                            <div class="today-payments-board-head">
                                <div>
                                    <strong>
                                        <i class="bi bi-wallet2"></i>
                                        <?= $is_payment_date_today ? 'Today’s payments' : 'Payments' ?>
                                    </strong>
                                    <?php if ($today_payments_count > 0): ?>
                                        <span class="today-payments-board-count"><?= (int)$today_payments_count ?></span>
                                    <?php endif; ?>
                                    <div class="today-payments-board-sub">
                                        <?= htmlspecialchars(date('d M Y', strtotime($today_payment_date)), ENT_QUOTES, 'UTF-8') ?>
                                        · grouped by class lesson
                                        <?php if ($today_payments_count > 0): ?>
                                            · Rs <?= htmlspecialchars(number_format($today_payments_total, 2), ENT_QUOTES, 'UTF-8') ?> total
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="today-payments-board-actions">
                                    <form class="today-payments-date-form" method="get" action="<?= htmlspecialchars(BASE_URL . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>#todayPaymentsBoard">
                                        <label class="visually-hidden" for="payDatePicker">Payment date</label>
                                        <input
                                            type="date"
                                            class="form-control form-control-sm"
                                            id="payDatePicker"
                                            name="pay_date"
                                            value="<?= htmlspecialchars($today_payment_date, ENT_QUOTES, 'UTF-8') ?>"
                                            max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                        <button class="btn btn-sm btn-primary" type="submit">Go</button>
                                        <?php if (!$is_payment_date_today): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL . 'dashboard.php?pay_date=' . urlencode(date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>#todayPaymentsBoard">Today</a>
                                        <?php endif; ?>
                                    </form>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(BASE_URL . 'campus/lesson_fees.php?date=' . urlencode($today_payment_date), ENT_QUOTES, 'UTF-8') ?>">
                                        Class fees
                                    </a>
                                    <?php if ($is_teacher && !$is_admin && isset($pdo) && $pdo instanceof PDO && teacher_manual_payment_enabled($pdo)): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(BASE_URL . 'campus/lesson_fees.php?date=' . urlencode($today_payment_date), ENT_QUOTES, 'UTF-8') ?>">
                                            Online class payment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!$today_payments_by_lesson): ?>
                                <div class="today-payments-empty">No class fee payments recorded for this day’s lessons.</div>
                            <?php else: ?>
                                <div class="today-payments-groups">
                                    <?php foreach ($today_payments_by_lesson as $lessonPay): ?>
                                        <?php
                                        $lessonHref = BASE_URL . 'campus/lesson_fees.php?date='
                                            . rawurlencode((string)$lessonPay['date'])
                                            . '&lesson=' . (int)$lessonPay['timetable_id'];
                                        $lessonTitle = trim(
                                            (string)$lessonPay['subject_name']
                                            . ($lessonPay['class_name'] !== '' ? ' · ' . $lessonPay['class_name'] : '')
                                        );
                                        if ($lessonPay['start_time'] !== '') {
                                            $lessonTitle .= ' · ' . $lessonPay['start_time'];
                                        }
                                        ?>
                                        <section class="today-payments-group">
                                            <div class="today-payments-group-head">
                                                <a href="<?= htmlspecialchars($lessonHref, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($lessonTitle, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                                <?php if (($lessonPay['fee_rule'] ?? '') === 'online_v1' && isset($lessonPay['payments'][0]['teacher_net_amount']) && $lessonPay['payments'][0]['teacher_net_amount'] !== null): ?>
                                                    <?php $sample = $lessonPay['payments'][0]; ?>
                                                    <div class="small text-body-secondary">
                                                        Online class · student fee Rs <?= number_format((float)$sample['gross_class_fee'], 2) ?>
                                                        · institute Rs <?= number_format((float)$sample['institute_online_fee'], 2) ?>
                                                        · handling Rs <?= number_format((float)$sample['transaction_handling_fee'], 2) ?>
                                                        · teacher net Rs <?= number_format((float)$sample['teacher_net_amount'], 2) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="today-payments-group-total">
                                                    Rs <?= htmlspecialchars(number_format((float)$lessonPay['total'], 2), ENT_QUOTES, 'UTF-8') ?>
                                                    · <?= count($lessonPay['payments']) ?> paid
                                                </span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm today-payments-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Student</th>
                                                            <th>Phone</th>
                                                            <th>Method</th>
                                                            <th class="text-end">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($lessonPay['payments'] as $pay): ?>
                                                            <?php
                                                            $methodClass = match ((string)$pay['gateway']) {
                                                                'onepay' => 'text-bg-info',
                                                                'bank' => 'text-bg-primary',
                                                                'cash' => 'text-bg-success',
                                                                default => 'text-bg-secondary',
                                                            };
                                                            ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars((string)$pay['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="today-payments-phone"><?= htmlspecialchars((string)$pay['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td>
                                                                    <span class="badge <?= htmlspecialchars($methodClass, ENT_QUOTES, 'UTF-8') ?>">
                                                                        <?= htmlspecialchars((string)$pay['method'], ENT_QUOTES, 'UTF-8') ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-end fw-semibold">
                                                                    Rs <?= htmlspecialchars(number_format((float)$pay['amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="dash-ops-side" aria-label="Needs attention">
                    <?php if ($onepay_payment_alerts): ?>
                        <div class="onepay-payment-alerts" id="onepayPaymentAlerts">
                            <div class="onepay-payment-alerts-head">
                                <div>
                                    <strong><i class="bi bi-credit-card-2-front"></i> Fee payment alerts</strong>
                                    <span class="onepay-payment-alerts-count"><?= count($onepay_payment_alerts) ?></span>
                                    <div class="onepay-payment-alerts-sub">Online, bank, or cash class fees to review.</div>
                                </div>
                            </div>
                            <div class="onepay-payment-alerts-list">
                                <?php foreach ($onepay_payment_alerts as $alert):
                                    $n = $alert['row'];
                                    $audience = $alert['audience'];
                                    $notificationId = (int)$n['id'];
                                    $link = $dashboard_notif_href((string)($n['link'] ?? ''));
                                    $itemClass = $audience === 'admin' ? 'admin-notification-item' : 'teacher-notification-item';
                                    $readClass = $audience === 'admin' ? 'admin-notification-read' : 'teacher-notification-read';
                                    $linkClass = $audience === 'admin' ? 'admin-notification-link' : 'teacher-notification-link';
                                ?>
                                    <div
                                        class="<?= htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') ?> unread onepay-payment-alert-item"
                                        data-notification-id="<?= $notificationId ?>"
                                        data-audience="<?= htmlspecialchars($audience, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <span class="teacher-notification-dot"></span>
                                        <div class="teacher-notification-body">
                                            <div class="teacher-notification-top">
                                                <strong><?= htmlspecialchars((string)$n['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <small><?= htmlspecialchars(date('d M Y · h:i A', strtotime((string)$n['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                                            </div>
                                            <?php if (!empty($n['message'])): ?>
                                                <div class="teacher-notification-message">
                                                    <?= nl2br(htmlspecialchars((string)$n['message'], ENT_QUOTES, 'UTF-8')) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="teacher-notification-actions">
                                                <?php if ($link !== ''): ?>
                                                    <a
                                                        href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
                                                        class="btn btn-sm btn-primary <?= htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-notification-id="<?= $notificationId ?>"
                                                    >
                                                        <i class="bi bi-arrow-right"></i> View fees
                                                    </a>
                                                <?php endif; ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-light <?= htmlspecialchars($readClass, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-notification-id="<?= $notificationId ?>"
                                                >
                                                    <i class="bi bi-check2"></i> Mark read
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($stats['pending_count'] > 0): ?>
                        <article class="notif-card notif-warning dash-attention-card">
                            <div class="d-flex align-items-start gap-2">
                                <span class="notif-icon"><i class="bi bi-credit-card"></i></span>
                                <div>
                                    <div class="notif-title">Pending payments</div>
                                    <div class="notif-text">
                                        <strong><?= $fmtInt($stats['pending_count']) ?></strong> class(es) ·
                                        <strong><?= $fmtMoney($stats['pending_amount']) ?></strong>
                                    </div>
                                    <a href="<?= $base ?>timetable/payments.php?payment_status=pending" class="btn btn-sm btn-warning mt-2">
                                        <i class="bi bi-arrow-right"></i> View pending
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <?php if (count($upcoming) > 0): ?>
                        <article class="notif-card notif-primary dash-attention-card">
                            <div class="d-flex align-items-start gap-2">
                                <span class="notif-icon"><i class="bi bi-bell"></i></span>
                                <div class="w-100">
                                    <div class="notif-title">Upcoming lessons</div>
                                    <div class="notif-text">Starting in the next 24 hours:</div>
                                    <ul class="upcoming-list">
                                        <?php foreach (array_slice($upcoming, 0, 3) as $lesson): ?>
                                            <li>
                                                <span><?= date('d M H:i', strtotime($lesson['date'] . ' ' . $lesson['start_time'])) ?></span>
                                                <span>–</span>
                                                <strong><?= htmlspecialchars($lesson['subject_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!$is_teacher): ?>
                                                    <span class="text-muted">(<?= htmlspecialchars($lesson['teacher_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>)</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($upcoming) > 3): ?>
                                            <li class="more-item">+ <?= $fmtInt(count($upcoming) - 3) ?> more</li>
                                        <?php endif; ?>
                                    </ul>
                                    <a href="<?= $base ?>timetable/index.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d', strtotime('+1 day')) ?>" class="btn btn-sm btn-primary mt-2">
                                        <i class="bi bi-arrow-right"></i> View all
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <?php if ($is_admin && $conflict_count > 0): ?>
                        <article class="notif-card notif-danger dash-attention-card">
                            <div class="d-flex align-items-start gap-2">
                                <span class="notif-icon"><i class="bi bi-exclamation-triangle"></i></span>
                                <div class="notif-content w-100">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="notif-title">Schedule conflicts</div>
                                        <span class="badge bg-danger"><?= $fmtInt($conflict_count) ?> found</span>
                                    </div>
                                    <div class="notif-text mt-1">Detected in the next 7 days:</div>
                                    <ul class="conflict-list mt-2">
                                        <?php foreach (array_slice($conflicts, 0, 5) as $conflict): ?>
                                            <li>
                                                <i class="bi bi-exclamation-triangle text-danger"></i>
                                                <strong><?= htmlspecialchars($conflict['teacher1'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span>vs</span>
                                                <strong><?= htmlspecialchars($conflict['teacher2'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="text-muted">on <?= date('d M Y', strtotime($conflict['date'])) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if ($conflict_count > 5): ?>
                                            <li class="more-item text-muted">+ <?= $fmtInt($conflict_count - 5) ?> more</li>
                                        <?php endif; ?>
                                    </ul>
                                    <a href="<?= $base ?>timetable/index.php" class="btn btn-sm btn-danger mt-1">
                                        <i class="bi bi-arrow-right"></i> Resolve
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <?php if (!$hasAttention): ?>
                        <article class="notif-card notif-success dash-attention-card">
                            <div class="d-flex align-items-start gap-2">
                                <span class="notif-icon"><i class="bi bi-check-circle"></i></span>
                                <div>
                                    <div class="notif-title">All clear</div>
                                    <div class="notif-text">
                                        <?php if ($is_teacher): ?>
                                            No pending payments or upcoming lessons to flag right now.
                                        <?php else: ?>
                                            No pending payments, upcoming lessons, or conflicts to report.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========== Inbox ========== -->
    <?php if ($is_teacher && $teacher_has_valid_id): ?>
        <section class="dash-section" aria-labelledby="dashTeacherInboxTitle">
            <div class="dash-section-head">
                <h2 id="dashTeacherInboxTitle" class="dash-section-title">Inbox</h2>
            </div>
            <article class="notif-card teacher-dashboard-notifications" id="teacherDashboardNotifications">
                <div class="teacher-notifications-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="notif-icon"><i class="bi bi-bell"></i></span>
                        <div>
                            <div class="notif-title">
                                Teacher notifications
                                <?php if ($teacher_notification_unread > 0): ?>
                                    <span class="teacher-notification-count"><?= (int)$teacher_notification_unread ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-text">Updates and messages from the institute.</div>
                        </div>
                    </div>
                    <?php if ($teacher_notification_unread > 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="markTeacherNotificationsRead">
                            <i class="bi bi-check2-all"></i> Mark all as read
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($teacher_notifications): ?>
                    <div class="teacher-notification-list">
                        <?php foreach ($teacher_notifications as $n):
                            $notificationId = (int)$n['id'];
                            $unread = (int)$n['is_read'] === 0;
                            $link = trim((string)($n['link'] ?? ''));
                        ?>
                            <div class="teacher-notification-item <?= $unread ? 'unread' : 'read' ?>" data-notification-id="<?= $notificationId ?>">
                                <span class="teacher-notification-dot"></span>
                                <div class="teacher-notification-body">
                                    <div class="teacher-notification-top">
                                        <strong><?= htmlspecialchars((string)$n['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars(date('d M Y · h:i A', strtotime((string)$n['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                                    </div>
                                    <?php if (!empty($n['message'])): ?>
                                        <div class="teacher-notification-message"><?= nl2br(htmlspecialchars((string)$n['message'], ENT_QUOTES, 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                    <div class="teacher-notification-actions">
                                        <?php if ($link !== ''): ?>
                                            <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary teacher-notification-link" data-notification-id="<?= $notificationId ?>">
                                                <i class="bi bi-arrow-right"></i> View
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($unread): ?>
                                            <button type="button" class="btn btn-sm btn-light teacher-notification-read" data-notification-id="<?= $notificationId ?>">
                                                <i class="bi bi-check2"></i> Mark read
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="teacher-notifications-empty">
                        <i class="bi bi-check-circle"></i> You're all caught up.
                    </div>
                <?php endif; ?>
            </article>
        </section>
    <?php endif; ?>

    <?php if ($is_admin): ?>
        <section class="dash-section" aria-labelledby="dashAdminInboxTitle">
            <div class="dash-section-head">
                <h2 id="dashAdminInboxTitle" class="dash-section-title">Office inbox</h2>
                <p class="dash-section-sub">Registrations, class joins, and other office alerts.</p>
            </div>
            <article class="notif-card teacher-dashboard-notifications" id="adminDashboardNotifications">
                <div class="teacher-notifications-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="notif-icon"><i class="bi bi-inbox"></i></span>
                        <div>
                            <div class="notif-title">
                                Admin notifications
                                <?php if ($admin_notification_unread > 0): ?>
                                    <span class="teacher-notification-count" data-admin-badge><?= (int)$admin_notification_unread ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($admin_notification_unread > 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="markAdminNotificationsRead">
                            <i class="bi bi-check2-all"></i> Mark all as read
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($admin_notifications): ?>
                    <div class="teacher-notification-list">
                        <?php foreach ($admin_notifications as $n):
                            $notificationId = (int)$n['id'];
                            $unread = (int)$n['is_read'] === 0;
                            $link = trim((string)($n['link'] ?? ''));
                        ?>
                            <div class="admin-notification-item <?= $unread ? 'unread' : 'read' ?>" data-notification-id="<?= $notificationId ?>">
                                <span class="teacher-notification-dot"></span>
                                <div class="teacher-notification-body">
                                    <div class="teacher-notification-top">
                                        <strong><?= htmlspecialchars((string)$n['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars(date('d M Y · h:i A', strtotime((string)$n['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                                    </div>
                                    <?php if (!empty($n['message'])): ?>
                                        <div class="teacher-notification-message"><?= nl2br(htmlspecialchars((string)$n['message'], ENT_QUOTES, 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                    <div class="teacher-notification-actions">
                                        <?php if ($link !== ''): ?>
                                            <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary admin-notification-link" data-notification-id="<?= $notificationId ?>">
                                                <i class="bi bi-arrow-right"></i> View
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($unread): ?>
                                            <button type="button" class="btn btn-sm btn-light admin-notification-read" data-notification-id="<?= $notificationId ?>">
                                                <i class="bi bi-check2"></i> Mark read
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="teacher-notifications-empty">
                        <i class="bi bi-check-circle"></i> No admin notifications yet.
                    </div>
                <?php endif; ?>
            </article>
        </section>
    <?php endif; ?>

    <!-- ========== Quick actions (grouped) ========== -->
    <section class="dashboard-quick-actions dash-section" aria-labelledby="quickActionsTitle">
        <div class="dash-section-head">
            <h2 id="quickActionsTitle" class="dash-section-title">
                <i class="bi bi-lightning-charge"></i> Quick actions
            </h2>
            <p class="dash-section-sub">Jump to the tools you use most, grouped by task.</p>
        </div>

        <?php if ($is_teacher): ?>
            <?php
            $teacherActionGroups = [
                'Teaching' => [
                    ['href' => 'timetable/index.php', 'icon' => 'bi-calendar-event', 'title' => 'My timetable', 'desc' => 'View scheduled classes'],
                    ['href' => 'timetable/teacher_schedule.php?teacher_id=' . (int)$teacher_id, 'icon' => 'bi-calendar-week', 'title' => 'My schedule', 'desc' => 'Weekly schedule'],
                    ['href' => 'campus/exams.php', 'icon' => 'bi-journal-text', 'title' => 'Exam timetable', 'desc' => 'Publish exam and mock slots'],
                    ['href' => 'campus/progress.php', 'icon' => 'bi-graph-up-arrow', 'title' => 'Marks', 'desc' => 'Enter mock and exam scores'],
                    ['href' => 'campus/attendance.php', 'icon' => 'bi-check2-circle', 'title' => 'Attendance', 'desc' => 'Mark today’s register'],
                    ['href' => 'campus/homework.php', 'icon' => 'bi-file-earmark-pdf', 'title' => 'Papers', 'desc' => 'Homework and worksheets'],
                ],
                'Finance & students' => array_values(array_filter([
                    ['href' => 'timetable/payments.php', 'icon' => 'bi-wallet2', 'title' => 'My payments', 'desc' => 'Lesson payment history'],
                    ['href' => 'campus/lesson_fees.php', 'icon' => 'bi-cash-coin', 'title' => 'Class fees', 'desc' => 'Collect and check fees'],
                    (isset($pdo) && $pdo instanceof PDO && teacher_manual_payment_enabled($pdo))
                        ? ['href' => 'campus/lesson_fees.php', 'icon' => 'bi-pencil-square', 'title' => 'Online class payment', 'desc' => 'Record a payment for an online class']
                        : null,
                    ['href' => 'campus/students.php', 'icon' => 'bi-people', 'title' => 'My students', 'desc' => 'Class lists'],
                    ['href' => 'campus/cash.php', 'icon' => 'bi-cash-stack', 'title' => 'Cash handover', 'desc' => 'Hand over collected cash'],
                ])),
            ];
            ?>
            <?php foreach ($teacherActionGroups as $groupLabel => $actions): ?>
                <div class="dash-action-group">
                    <h3 class="dash-action-group-title"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="row g-3 mb-3">
                        <?php foreach ($actions as $a): ?>
                            <div class="col-md-4 col-6">
                                <a href="<?= $base . htmlspecialchars($a['href'], ENT_QUOTES, 'UTF-8') ?>" class="action-card">
                                    <span class="action-icon"><i class="bi <?= htmlspecialchars($a['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                    <div class="action-title"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="action-desc"><?= htmlspecialchars($a['desc'], ENT_QUOTES, 'UTF-8') ?></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php
            $adminActionGroups = [
                'Schedule' => [
                    ['href' => 'timetable/add.php', 'icon' => 'bi-plus-lg', 'title' => 'Add lesson', 'desc' => 'Schedule a new class'],
                    ['href' => 'timetable/index.php', 'icon' => 'bi-calendar-event', 'title' => 'Manage timetable', 'desc' => 'View and edit entries'],
                    ['href' => 'timetable/weekly.php?type=teacher&id=0', 'icon' => 'bi-calendar-week', 'title' => 'Weekly view', 'desc' => 'Full week schedule'],
                    ['href' => 'campus/exams.php', 'icon' => 'bi-journal-text', 'title' => 'Exam / mock', 'desc' => 'Publish exam timetable'],
                ],
                'People & classroom' => [
                    ['href' => 'admin/students.php', 'icon' => 'bi-people', 'title' => 'Students', 'desc' => 'Student records'],
                    ['href' => 'teachers/index.php', 'icon' => 'bi-person-workspace', 'title' => 'Teachers', 'desc' => 'Add or edit teachers'],
                    ['href' => 'campus/progress.php', 'icon' => 'bi-graph-up-arrow', 'title' => 'Marks', 'desc' => 'Enter student results'],
                    ['href' => 'campus/attendance.php', 'icon' => 'bi-check2-circle', 'title' => 'Attendance', 'desc' => 'Registers and reports'],
                ],
                'Finance' => [
                    ['href' => 'timetable/payments.php', 'icon' => 'bi-wallet2', 'title' => 'Payments', 'desc' => 'Track pending payments'],
                    ['href' => 'campus/lesson_fees.php', 'icon' => 'bi-cash-coin', 'title' => 'Class fees', 'desc' => 'Lesson fee board'],
                    ['href' => 'campus/bank_slips.php', 'icon' => 'bi-bank', 'title' => 'Bank slips', 'desc' => 'Verify transfers'],
                    ['href' => 'reports/revenue.php', 'icon' => 'bi-bar-chart', 'title' => 'Revenue reports', 'desc' => 'Income statistics'],
                    ['href' => 'admin/finance.php', 'icon' => 'bi-cash-stack', 'title' => 'Finance hub', 'desc' => 'College finance overview'],
                ],
                'Office & system' => [
                    ['href' => 'admin/admissions_control.php', 'icon' => 'bi-person-plus', 'title' => 'Admissions', 'desc' => 'Leads and applications'],
                    ['href' => 'admin/communications.php', 'icon' => 'bi-megaphone', 'title' => 'Communications', 'desc' => 'Announcements and messages'],
                    ['href' => 'admin/bulk_sms.php', 'icon' => 'bi-chat-left-dots', 'title' => 'Bulk SMS', 'desc' => 'Broadcast SMS campaigns'],
                    ['href' => 'admin/command_center.php', 'icon' => 'bi-speedometer2', 'title' => 'Command center', 'desc' => 'Ops snapshot'],
                    ['href' => 'admin/audit_log.php', 'icon' => 'bi-journal-text', 'title' => 'Audit log', 'desc' => 'System change history'],
                    ['href' => 'admin/settings.php', 'icon' => 'bi-gear', 'title' => 'Settings', 'desc' => 'Configure the system'],
                ],
            ];
            ?>
            <?php foreach ($adminActionGroups as $groupLabel => $actions): ?>
                <div class="dash-action-group">
                    <h3 class="dash-action-group-title"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="row g-3 mb-3">
                        <?php foreach ($actions as $a): ?>
                            <div class="col-lg-3 col-md-4 col-6">
                                <a href="<?= $base . htmlspecialchars($a['href'], ENT_QUOTES, 'UTF-8') ?>" class="action-card">
                                    <span class="action-icon"><i class="bi <?= htmlspecialchars($a['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                    <div class="action-title"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="action-desc"><?= htmlspecialchars($a['desc'], ENT_QUOTES, 'UTF-8') ?></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($is_admin): ?>
        <section class="dash-section dash-snapshot" aria-label="College snapshot">
            <div class="dash-section-head">
                <h2 class="dash-section-title">College snapshot</h2>
            </div>
            <div class="dash-snapshot-grid">
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Teachers</span>
                    <span class="dash-snapshot-value"><?= $fmtInt($stats['total_teachers']) ?></span>
                </div>
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Student groups</span>
                    <span class="dash-snapshot-value"><?= $fmtInt($stats['total_classes']) ?></span>
                </div>
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Total lessons</span>
                    <span class="dash-snapshot-value"><?= $fmtInt($stats['total_lessons']) ?></span>
                </div>
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Total revenue</span>
                    <span class="dash-snapshot-value"><?= $fmtMoney($stats['total_revenue']) ?></span>
                </div>
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Pending classes</span>
                    <span class="dash-snapshot-value<?= $stats['pending_count'] > 0 ? ' text-danger' : '' ?>"><?= $fmtInt($stats['pending_count']) ?></span>
                </div>
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Conflicts</span>
                    <span class="dash-snapshot-value<?= $conflict_count > 0 ? ' text-danger' : ' text-success' ?>"><?= $fmtInt($conflict_count) ?></span>
                </div>
                <div class="dash-snapshot-item">
                    <span class="dash-snapshot-label">Session</span>
                    <span class="dash-snapshot-value dash-snapshot-session"><?= date('d M Y H:i', strtotime($_SESSION['last_activity'] ?? 'now')) ?></span>
                </div>
            </div>
        </section>
    <?php endif; ?>

</div>

<style>
.dash-home{display:flex;flex-direction:column;gap:1.35rem}
.dash-welcome-main{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem}
.dash-welcome-aside{display:flex;flex-direction:column;align-items:flex-end;gap:.65rem}
.dash-welcome-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.4rem}
.dash-section{margin:0}
.dash-section-head{margin-bottom:.85rem}
.dash-section-title{margin:0;font-size:1.05rem;font-weight:800;letter-spacing:-.02em;color:var(--text,inherit);display:flex;align-items:center;gap:.4rem}
.dash-section-sub{margin:.25rem 0 0;font-size:.82rem;color:var(--muted,#6c757d)}
.dash-ops-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(260px,.9fr);gap:1rem;align-items:start}
.dash-ops-side{display:grid;gap:.75rem}
.dash-attention-card{margin:0}
.dash-action-group{margin-bottom:.35rem}
.dash-action-group-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted,#6c757d);margin:0 0 .65rem}
.dash-snapshot{padding:1rem 1.1rem;border:1px solid var(--panel-border,rgba(0,0,0,.08));border-radius:14px;background:var(--surface,#fff)}
.dash-snapshot-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem 1rem}
.dash-snapshot-item{display:flex;flex-direction:column;gap:.15rem}
.dash-snapshot-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted,#6c757d);font-weight:700}
.dash-snapshot-value{font-size:1.05rem;font-weight:800;font-variant-numeric:tabular-nums}
.dash-snapshot-session{font-size:.88rem;font-weight:600}
.admin-dashboard .stat-card.is-alert{border-color:rgba(220,53,69,.35);background:rgba(220,53,69,.04)}
.admin-dashboard .stat-icon i{font-size:1.35rem;opacity:.9}
.action-card .action-icon i{font-size:1.35rem}
.teacher-dashboard-notifications{position:relative}
.teacher-notifications-header{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:14px}
.teacher-notification-count{display:inline-flex;align-items:center;justify-content:center;min-width:23px;height:23px;margin-left:7px;padding:0 7px;border-radius:999px;background:#dc3545;color:#fff;font-size:.72rem;font-weight:800}
.teacher-notification-list{display:grid;gap:9px}
.teacher-notification-item,.admin-notification-item{display:flex;gap:12px;padding:13px 14px;border:1px solid var(--bs-border-color,#dee2e6);border-radius:13px;background:var(--bs-body-bg,#fff);transition:.2s}
.teacher-notification-item.unread,.admin-notification-item.unread{background:rgba(13,110,253,.045);border-color:rgba(13,110,253,.20)}
.teacher-notification-item.read,.admin-notification-item.read{opacity:.72}
.teacher-notification-dot{flex:0 0 9px;width:9px;height:9px;margin-top:6px;border-radius:50%;background:#adb5bd}
.teacher-notification-item.unread .teacher-notification-dot,.admin-notification-item.unread .teacher-notification-dot{background:#0d6efd;box-shadow:0 0 0 4px rgba(13,110,253,.10)}
.teacher-notification-body{min-width:0;flex:1}
.teacher-notification-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.teacher-notification-top small{color:var(--bs-secondary-color,#6c757d);font-size:.7rem}
.teacher-notification-message{margin-top:4px;color:var(--bs-secondary-color,#6c757d);font-size:.8rem;line-height:1.5}
.teacher-notification-actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}
.teacher-notifications-empty{text-align:center;padding:18px 12px 4px;color:var(--bs-secondary-color,#6c757d);font-size:.82rem}
.onepay-payment-alerts{padding:14px 16px;border:1px solid rgba(25,135,84,.35);border-radius:14px;background:rgba(25,135,84,.10)}
.onepay-payment-alerts-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
.onepay-payment-alerts-head strong{font-size:.95rem}
.onepay-payment-alerts-sub{margin-top:2px;color:var(--bs-secondary-color,#6c757d);font-size:.78rem}
.onepay-payment-alerts-count{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;margin-left:6px;padding:0 7px;border-radius:999px;background:#198754;color:#fff;font-size:.72rem;font-weight:800;vertical-align:middle}
.onepay-payment-alerts-list{display:grid;gap:9px}
.onepay-payment-alert-item{background:rgba(255,255,255,.04)!important}
.today-payments-board{padding:14px 16px;border:1px solid rgba(13,110,253,.28);border-radius:14px;background:rgba(13,110,253,.08)}
.today-payments-board-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap}
.today-payments-board-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.today-payments-date-form{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.today-payments-date-form .form-control{width:auto;min-width:9.5rem}
.today-payments-board-head strong{font-size:.95rem}
.today-payments-board-sub{margin-top:2px;color:var(--bs-secondary-color,#6c757d);font-size:.78rem}
.today-payments-board-count{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;margin-left:6px;padding:0 7px;border-radius:999px;background:#0d6efd;color:#fff;font-size:.72rem;font-weight:800;vertical-align:middle}
.today-payments-empty{padding:10px 2px 2px;color:var(--bs-secondary-color,#6c757d);font-size:.84rem}
.today-payments-groups{display:grid;gap:12px}
.today-payments-group{padding:12px;border:1px solid var(--bs-border-color,rgba(255,255,255,.12));border-radius:12px;background:rgba(0,0,0,.12)}
.today-payments-group-head{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:8px}
.today-payments-group-head a{font-weight:700;text-decoration:none;color:inherit}
.today-payments-group-head a:hover{color:var(--bs-primary,#0d6efd)}
.today-payments-group-total{color:var(--bs-secondary-color,#6c757d);font-size:.78rem;font-weight:600}
.today-payments-table{--bs-table-bg:transparent;font-size:.86rem}
.today-payments-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:var(--bs-secondary-color,#6c757d);border-bottom-width:1px}
.today-payments-phone{white-space:nowrap;font-variant-numeric:tabular-nums}
@media(max-width:991px){
    .dash-ops-grid{grid-template-columns:1fr}
}
@media(max-width:576px){
    .dash-welcome-aside{align-items:stretch}
    .dash-welcome-actions{
        width:100%;
        flex-wrap:nowrap;
        justify-content:flex-start;
        overflow-x:auto;
        overscroll-behavior-inline:contain;
        scroll-snap-type:x proximity;
        padding:.15rem 0 .4rem;
    }
    .dash-welcome-actions .btn{
        flex:0 0 auto;
        min-height:44px;
        justify-content:center;
        scroll-snap-align:start;
    }
    .teacher-notifications-header{align-items:flex-start;flex-direction:column}
    .teacher-notifications-header>button{width:100%}
    .teacher-notification-top{flex-direction:column;gap:3px}
    .today-payments-board-head{flex-direction:column}
}
</style>

<script>
(function(){
    const csrf = <?= json_encode(generate_csrf_token()) ?>;

    function bindNotifications(root, endpoint, itemClass, readClass, linkClass, markAllId, badgeSel){
        if(!root) return;

        function markRead(ids){
            const fd = new FormData();
            fd.append('action','mark_read');
            fd.append('csrf_token', csrf);
            ids.forEach(id => fd.append('notification_ids[]',id));
            return fetch(endpoint,{
                method:'POST',
                body:fd,
                credentials:'same-origin',
                headers:{'X-Requested-With':'XMLHttpRequest'}
            }).then(r => r.json()).then(d => !!d.success).catch(() => false);
        }

        function updateItem(item){
            if(!item) return;
            const id = item.dataset.notificationId;
            const nodes = id
                ? document.querySelectorAll('.' + itemClass + '[data-notification-id="' + id + '"]')
                : [item];
            nodes.forEach(function(node){
                node.classList.remove('unread');
                node.classList.add('read');
                const btn = node.querySelector('.' + readClass);
                if (btn) btn.remove();
            });
            const topPanel = document.getElementById('onepayPaymentAlerts');
            if (topPanel) {
                const remaining = topPanel.querySelectorAll('.onepay-payment-alert-item.unread');
                if (!remaining.length) topPanel.remove();
                else {
                    const badge = topPanel.querySelector('.onepay-payment-alerts-count');
                    if (badge) badge.textContent = String(remaining.length);
                }
            }
        }

        function refresh(){
            const unread=root.querySelectorAll('.'+itemClass+'.unread');
            const badge=root.querySelector(badgeSel);
            const all=root.querySelector('#'+markAllId);
            if(badge){
                badge.textContent=unread.length;
                if(!unread.length) badge.remove();
            }
            if(all && !unread.length) all.remove();
        }

        root.addEventListener('click',function(e){
            const read=e.target.closest('.'+readClass);
            if(read){
                const item=read.closest('.'+itemClass);
                read.disabled=true;
                markRead([read.dataset.notificationId]).then(ok=>{
                    if(ok){ updateItem(item); refresh(); }
                    else read.disabled=false;
                });
                return;
            }
            if(markAllId){
                const all=e.target.closest('#'+markAllId);
                if(all){
                    const items=[...root.querySelectorAll('.'+itemClass+'.unread')];
                    const ids=items.map(x=>x.dataset.notificationId).filter(Boolean);
                    all.disabled=true;
                    markRead(ids).then(ok=>{
                        if(ok){ items.forEach(updateItem); refresh(); }
                        else all.disabled=false;
                    });
                    return;
                }
            }
            const link=e.target.closest('.'+linkClass);
            if(link){
                const item=link.closest('.'+itemClass);
                if(item && item.classList.contains('unread')){
                    markRead([link.dataset.notificationId]);
                }
            }
        });
    }

    bindNotifications(
        document.getElementById('teacherDashboardNotifications'),
        <?= json_encode(BASE_URL . 'teachers/notification_read.php') ?>,
        'teacher-notification-item',
        'teacher-notification-read',
        'teacher-notification-link',
        'markTeacherNotificationsRead',
        '.teacher-notification-count'
    );
    bindNotifications(
        document.getElementById('adminDashboardNotifications'),
        <?= json_encode(BASE_URL . 'admin/notification_read.php') ?>,
        'admin-notification-item',
        'admin-notification-read',
        'admin-notification-link',
        'markAdminNotificationsRead',
        '[data-admin-badge]'
    );
    bindNotifications(
        document.getElementById('onepayPaymentAlerts'),
        <?= json_encode(BASE_URL . 'teachers/notification_read.php') ?>,
        'teacher-notification-item',
        'teacher-notification-read',
        'teacher-notification-link',
        '',
        '.onepay-payment-alerts-count'
    );
    bindNotifications(
        document.getElementById('onepayPaymentAlerts'),
        <?= json_encode(BASE_URL . 'admin/notification_read.php') ?>,
        'admin-notification-item',
        'admin-notification-read',
        'admin-notification-link',
        '',
        '.onepay-payment-alerts-count'
    );
})();
</script>

<?php include 'includes/footer.php'; ?>
