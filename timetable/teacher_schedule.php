<?php
/**
 * timetable/teacher_schedule.php
 *
 * Teacher-focused weekly schedule.
 *
 * - Teachers can only see their own schedule.
 * - Admins can select any teacher.
 * - 30-minute time intervals.
 * - Class blocks use their real start/end time and fill the
 *   complete duration instead of only one hourly cell.
 * - Deleted timetable records are excluded.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/recurring.php';

require_staff();

generate_future_entries($pdo, 14);

$is_admin = is_admin();

/*
 * Teachers always use their session teacher_id.
 * Only administrators may request another teacher by URL.
 */
$teacher_id = isset($_SESSION['teacher_id'])
    ? (int)$_SESSION['teacher_id']
    : 0;

if ($is_admin && isset($_GET['teacher_id'])) {
    $teacher_id = (int)$_GET['teacher_id'];
}

if (!$is_admin && $teacher_id <= 0) {
    http_response_code(403);
    exit('Your teacher account is not linked to an active teacher profile. Ask an administrator to link users.teacher_id.');
}

/*
 * Admins may open this page without a teacher selected.
 */
if (!$teacher_id) {

    $teachers = $pdo->query("
        SELECT id, name
        FROM teachers
        WHERE deleted_at IS NULL
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

    include __DIR__ . '/../includes/header.php';
    ?>

    <style>
        .teacher-select-card {
            max-width: 760px;
            margin: 30px auto;
            border: 0;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }

        .teacher-select-icon {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: #e8f0fe;
            color: #1a73e8;
            font-size: 28px;
        }
    </style>

    <div class="container-fluid py-4">

        <div class="card teacher-select-card">
            <div class="card-body p-4 p-md-5">

                <div class="d-flex align-items-center gap-3 mb-4">

                    <div class="teacher-select-icon">
                        <i class="bi bi-calendar-week"></i>
                    </div>

                    <div>
                        <h1 class="h3 mb-1">Teacher Schedule</h1>
                        <p class="text-muted mb-0">
                            Select a teacher to view their weekly timetable.
                        </p>
                    </div>

                </div>

                <?php if (!$teachers): ?>

                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No active teachers are available.
                    </div>

                <?php else: ?>

                    <form method="GET" class="row g-3 align-items-end">

                        <div class="col-md-8">

                            <label
                                for="teacher_id"
                                class="form-label fw-semibold"
                            >
                                Select Teacher
                            </label>

                            <select
                                name="teacher_id"
                                id="teacher_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    Choose a teacher...
                                </option>

                                <?php foreach ($teachers as $t): ?>

                                    <option value="<?= (int)$t['id'] ?>">
                                        <?= htmlspecialchars(
                                            $t['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-md-4">

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-calendar-week me-1"></i>
                                View Schedule
                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </div>
        </div>

    </div>

    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}


/* ============================================================
   TEACHER
============================================================ */

$stmt = $pdo->prepare("
    SELECT id, name
    FROM teachers
    WHERE id = ?
      AND deleted_at IS NULL
    LIMIT 1
");

$stmt->execute([$teacher_id]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    http_response_code($is_admin ? 404 : 403);
    die($is_admin
        ? 'Teacher not found.'
        : 'Your linked teacher profile is inactive. Ask an administrator to review users.teacher_id.');
}

$teacher_name = htmlspecialchars(
    $teacher['name'],
    ENT_QUOTES,
    'UTF-8'
);


/* ============================================================
   HELPERS
============================================================ */

if (!function_exists('timetable_minutes_from_midnight')) {
    function timetable_minutes_from_midnight(
        string $time
    ): int {
        $parts = explode(':', $time);
        $hour = (int)($parts[0] ?? 0);
        $minute = (int)($parts[1] ?? 0);
        return ($hour * 60) + $minute;
    }
}

if (!function_exists('timetable_format_time')) {
    function timetable_format_time(
        string $time
    ): string {
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return htmlspecialchars(
                $time,
                ENT_QUOTES,
                'UTF-8'
            );
        }
        return date(
            'g:i A',
            $timestamp
        );
    }
}


/* ============================================================
   WEEK
============================================================ */

$requested_week = $_GET['week_start'] ?? $_GET['date'] ?? '';

if (!empty($requested_week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$requested_week)) {
    $week_start = date(
        'Y-m-d',
        strtotime(
            'monday this week',
            strtotime((string)$requested_week)
        )
    );
} else {
    $this_week_monday = date('Y-m-d', strtotime('monday this week'));
    $this_week_sunday = date('Y-m-d', strtotime('+6 days', strtotime($this_week_monday)));

    // Check if teacher has classes scheduled in the current week
    $cur_stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM timetable
        WHERE (teacher_id = ? OR substitute_teacher_id = ?)
          AND deleted_at IS NULL
          AND date BETWEEN ? AND ?
    ");
    $cur_stmt->execute([$teacher_id, $teacher_id, $this_week_monday, $this_week_sunday]);
    $classes_this_week = (int)$cur_stmt->fetchColumn();

    if ($classes_this_week > 0) {
        $week_start = $this_week_monday;
    } else {
        // If current week has no classes, check for nearest upcoming scheduled class
        $today = date('Y-m-d');
        $next_stmt = $pdo->prepare("
            SELECT date
            FROM timetable
            WHERE (teacher_id = ? OR substitute_teacher_id = ?)
              AND deleted_at IS NULL
              AND date >= ?
            ORDER BY date ASC, start_time ASC
            LIMIT 1
        ");
        $next_stmt->execute([$teacher_id, $teacher_id, $today]);
        $next_class_date = $next_stmt->fetchColumn();

        if ($next_class_date) {
            $week_start = date(
                'Y-m-d',
                strtotime(
                    'monday this week',
                    strtotime((string)$next_class_date)
                )
            );
        } else {
            $week_start = $this_week_monday;
        }
    }
}

$days = [];

for ($i = 0; $i < 7; $i++) {
    $days[] = date(
        'Y-m-d',
        strtotime(
            $week_start . " +{$i} days"
        )
    );
}


/* ============================================================
   LOAD TIMETABLE
============================================================ */

$sql = "
    SELECT
        t.id,
        t.date,
        t.start_time,
        t.end_time,
        t.student_count,

        s.name AS subject_name,
        c.name AS class_name,
        r.name AS room_name,
        st.name AS substitute_name

    FROM timetable t

    LEFT JOIN subjects s
        ON t.subject_id = s.id
       AND s.deleted_at IS NULL

    LEFT JOIN student_classes c
        ON t.class_id = c.id
       AND c.deleted_at IS NULL

    LEFT JOIN rooms r
        ON t.room_id = r.id
       AND r.deleted_at IS NULL

    LEFT JOIN teachers st
        ON t.substitute_teacher_id = st.id
       AND st.deleted_at IS NULL

    WHERE (t.teacher_id = ? OR t.substitute_teacher_id = ?)
      AND t.deleted_at IS NULL
      AND t.date BETWEEN ? AND ?

    ORDER BY
        t.date ASC,
        t.start_time ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $teacher_id,
    $teacher_id,
    $days[0],
    $days[6]
]);

$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Group classes by date.
 */
$day_entries = [];

foreach ($entries as $entry) {
    $date = $entry['date'];

    if (!isset($day_entries[$date])) {
        $day_entries[$date] = [];
    }

    $day_entries[$date][] = $entry;
}

foreach ($day_entries as &$date_entries) {
    usort(
        $date_entries,
        static function ($a, $b) {
            return strcmp(
                $a['start_time'],
                $b['start_time']
            );
        }
    );
}

unset($date_entries);


/* ============================================================
   30-MINUTE GRID (DYNAMIC HOURS)
============================================================ */

$grid_start_minutes = 8 * 60;   // Default 08:00 (8:00 AM)
$grid_end_minutes   = 20 * 60;  // Default 20:00 (8:00 PM)
$slot_minutes       = 30;
$slot_height        = 40;       // px per 30 minutes

// Automatically expand grid to accommodate early morning or late evening lessons
foreach ($entries as $entry) {
    $s = timetable_minutes_from_midnight((string)$entry['start_time']);
    $e = timetable_minutes_from_midnight((string)$entry['end_time']);

    if ($e <= $s) {
        $e = $s + 60;
    }

    if ($s < $grid_start_minutes) {
        $grid_start_minutes = (int)(floor($s / 60) * 60);
    }

    if ($e > $grid_end_minutes) {
        $grid_end_minutes = (int)(ceil($e / 60) * 60);
    }
}

$grid_start_minutes = max(0, min($grid_start_minutes, 8 * 60));
$grid_end_minutes   = min(24 * 60, max($grid_end_minutes, 20 * 60));

$time_slots = [];

for (
    $minutes = $grid_start_minutes;
    $minutes < $grid_end_minutes;
    $minutes += $slot_minutes
) {
    $hours = intdiv($minutes, 60);
    $mins  = $minutes % 60;

    $time_slots[] = sprintf(
        '%02d:%02d',
        $hours,
        $mins
    );
}

$grid_height =
    (
        ($grid_end_minutes - $grid_start_minutes)
        / $slot_minutes
    ) * $slot_height;

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   TEACHER WEEKLY SCHEDULE
============================================================ */

.teacher-schedule-page {
    width: 100%;
}

.teacher-schedule-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.teacher-schedule-title {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
}

.teacher-schedule-subtitle {
    margin: 3px 0 0;
    color: #6b7280;
    font-size: .9rem;
}

.week-navigation {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.week-range {
    font-weight: 600;
    color: #374151;
    margin-left: 8px;
}


/* ============================================================
   GRID
============================================================ */

.teacher-week-grid {
    width: 100%;
    overflow-x: auto;
    background: #fff;
    border: 1px solid #dfe3e8;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

.teacher-week-inner {
    min-width: 850px;
}


/* Header */

.teacher-week-header {
    display: grid;
    grid-template-columns: 72px repeat(7, minmax(112px, 1fr));
    background: #f5f7fa;
    border-bottom: 1px solid #dfe3e8;
}

.teacher-week-header .time-header {
    border-right: 1px solid #dfe3e8;
}

.teacher-day-header {
    min-height: 62px;
    padding: 10px 5px;
    text-align: center;
    border-right: 1px solid #dfe3e8;
    color: #374151;
}

.teacher-day-header:last-child {
    border-right: 0;
}

.teacher-day-header .day-name {
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
}

.teacher-day-header .day-date {
    margin-top: 3px;
    font-size: .95rem;
    font-weight: 700;
}

.teacher-day-header.today {
    background: #e8f0fe;
    color: #1967d2;
}


/* Body */

.teacher-week-body {
    display: grid;
    grid-template-columns: 72px 1fr;
}

.teacher-time-column {
    position: relative;
    background: #fafbfc;
    border-right: 1px solid #dfe3e8;
}

.teacher-time-label {
    height: <?= $slot_height ?>px;
    box-sizing: border-box;
    padding: 3px 5px 0;
    text-align: right;
    border-bottom: 1px solid #edf0f3;
    color: #68707d;
    font-size: .68rem;
    font-weight: 600;
}


/* Days */

.teacher-days {
    display: grid;
    grid-template-columns: repeat(7, minmax(112px, 1fr));
}

.teacher-day-column {
    position: relative;
    height: <?= $grid_height ?>px;
    border-right: 1px solid #dfe3e8;
    background:
        repeating-linear-gradient(
            to bottom,
            transparent 0,
            transparent <?= ($slot_height - 1) ?>px,
            #edf0f3 <?= ($slot_height - 1) ?>px,
            #edf0f3 <?= $slot_height ?>px
        );
}

.teacher-day-column:last-child {
    border-right: 0;
}


/*
 * Every 30-minute line is visible.
 * The alternating background makes hour boundaries
 * easier to see without adding another column.
 */
.teacher-day-column::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        repeating-linear-gradient(
            to bottom,
            transparent 0,
            transparent <?= ($slot_height * 2 - 1) ?>px,
            rgba(120,130,145,.12) <?= ($slot_height * 2 - 1) ?>px,
            rgba(120,130,145,.12) <?= ($slot_height * 2) ?>px
        );
}


/* ============================================================
   CLASS BLOCK
============================================================ */

.teacher-class-block {
    position: absolute;
    z-index: 2;

    left: 5px;
    right: 5px;

    min-height: 22px;

    box-sizing: border-box;

    padding: 7px 8px;

    background: #12bfe3;
    border: 1px solid rgba(0,0,0,.08);
    border-left: 4px solid #0799b9;

    border-radius: 7px;

    color: #fff;

    overflow: hidden;

    box-shadow:
        0 2px 5px rgba(0,0,0,.14);

    transition:
        transform .15s ease,
        box-shadow .15s ease;
}

a.teacher-class-block {
    cursor: pointer;
}

.teacher-class-block:hover {
    z-index: 5;
    transform: translateY(-1px);
    box-shadow:
        0 5px 12px rgba(0,0,0,.18);
}

.teacher-class-time {
    font-size: .68rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 3px;
}

.teacher-class-subject {
    font-size: .82rem;
    font-weight: 800;
    line-height: 1.25;
}

.teacher-class-name {
    margin-top: 3px;
    font-size: .72rem;
    font-weight: 600;
    line-height: 1.25;
}

.teacher-class-room {
    margin-top: 2px;
    font-size: .69rem;
    line-height: 1.25;
    opacity: .95;
}

.teacher-class-students {
    margin-top: 4px;
    font-size: .7rem;
    font-weight: 700;
}

.teacher-class-block a {
    color: inherit;
    text-decoration: none;
}


/* Empty day */

.teacher-empty-day {
    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #d2d7de;
    font-size: .75rem;
    font-style: italic;

    pointer-events: none;
}


/* Legend */

.teacher-schedule-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    color: #6b7280;
    font-size: .78rem;
}

.teacher-schedule-legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    background: #12bfe3;
}


/* Mobile */

@media (max-width: 768px) {

    .teacher-schedule-toolbar {
        align-items: flex-start;
    }

    .teacher-schedule-title {
        font-size: 1.2rem;
    }

    .teacher-week-inner {
        min-width: 760px;
    }

    .teacher-week-header {
        grid-template-columns:
            58px repeat(7, minmax(100px, 1fr));
    }

    .teacher-week-body {
        grid-template-columns: 58px 1fr;
    }

    .teacher-days {
        grid-template-columns:
            repeat(7, minmax(100px, 1fr));
    }

    .teacher-time-label {
        font-size: .61rem;
        padding-right: 4px;
    }

    .teacher-class-block {
        left: 3px;
        right: 3px;
        padding: 5px 6px;
        border-left-width: 3px;
    }

    .teacher-class-subject {
        font-size: .72rem;
    }

    .teacher-class-name,
    .teacher-class-room,
    .teacher-class-students {
        font-size: .63rem;
    }
}
</style>


<div class="container-fluid py-3 teacher-schedule-page">

    <!-- ========================================================
         TOOLBAR
    ========================================================= -->

    <div class="teacher-schedule-toolbar">

        <div>

            <h1 class="teacher-schedule-title">
                <?= $teacher_name ?> – Weekly Schedule
            </h1>

            <p class="teacher-schedule-subtitle">
                30-minute intervals · <?= count($entries) ?> class(es)
                this week
            </p>

        </div>


        <div class="week-navigation">

            <a
                href="add.php"
                class="btn btn-primary btn-sm"
            >
                <i class="bi bi-plus-lg"></i>
                Add lesson
            </a>

            <a
                href="?teacher_id=<?= $teacher_id ?>&week_start=<?= htmlspecialchars(
                    date(
                        'Y-m-d',
                        strtotime($week_start . ' -7 days')
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-chevron-left"></i>
                Previous
            </a>

            <a
                href="?teacher_id=<?= $teacher_id ?>&week_start=<?= date(
                    'Y-m-d',
                    strtotime('monday this week')
                ) ?>"
                class="btn btn-outline-secondary btn-sm"
            >
                Today
            </a>

            <a
                href="?teacher_id=<?= $teacher_id ?>&week_start=<?= htmlspecialchars(
                    date(
                        'Y-m-d',
                        strtotime($week_start . ' +7 days')
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="btn btn-outline-secondary btn-sm"
            >
                Next
                <i class="bi bi-chevron-right"></i>
            </a>

            <span class="week-range">
                <?= date('d M', strtotime($days[0])) ?>
                –
                <?= date('d M Y', strtotime($days[6])) ?>
            </span>

        </div>

    </div>


    <!-- ========================================================
         ADMIN TEACHER SELECTOR
    ========================================================= -->

    <?php if ($is_admin): ?>

        <div class="card border-0 shadow-sm mb-3">

            <div class="card-body py-3">

                <form method="GET" class="row g-2 align-items-end">

                    <div class="col-md-5 col-lg-4">

                        <label
                            for="teacher_id"
                            class="form-label small fw-semibold mb-1"
                        >
                            Teacher
                        </label>

                        <select
                            name="teacher_id"
                            id="teacher_id"
                            class="form-select form-select-sm"
                            onchange="this.form.submit()"
                        >

                            <?php
                            $teachers = $pdo->query("
                                SELECT id, name
                                FROM teachers
                                WHERE deleted_at IS NULL
                                ORDER BY name
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <?php foreach ($teachers as $t): ?>

                                <option
                                    value="<?= (int)$t['id'] ?>"
                                    <?= (
                                        $teacher_id ==
                                        (int)$t['id']
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $t['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <input
                        type="hidden"
                        name="week_start"
                        value="<?= htmlspecialchars(
                            $week_start,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </form>

            </div>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         WEEK GRID
    ========================================================= -->

    <div class="teacher-week-grid">

        <div class="teacher-week-inner">

            <!-- HEADER -->

            <div class="teacher-week-header">

                <div class="time-header"></div>

                <?php foreach ($days as $day): ?>

                    <?php
                    $is_today =
                        $day === date('Y-m-d');
                    ?>

                    <div
                        class="
                            teacher-day-header
                            <?= $is_today
                                ? 'today'
                                : '' ?>
                        "
                    >

                        <div class="day-name">
                            <?= date(
                                'D',
                                strtotime($day)
                            ) ?>
                        </div>

                        <div class="day-date">
                            <?= date(
                                'd M',
                                strtotime($day)
                            ) ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- BODY -->

            <div class="teacher-week-body">

                <!-- TIME COLUMN -->

                <div class="teacher-time-column">

                    <?php foreach ($time_slots as $slot): ?>

                        <div class="teacher-time-label">
                            <?= date(
                                'g:i A',
                                strtotime($slot)
                            ) ?>
                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- DAY COLUMNS -->

                <div class="teacher-days">

                    <?php foreach ($days as $day): ?>

                        <div
                            class="teacher-day-column"
                            data-date="<?= htmlspecialchars(
                                $day,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <?php
                            $classes_today =
                                $day_entries[$day] ?? [];
                            ?>

                            <?php if (!$classes_today): ?>

                                <div class="teacher-empty-day">
                                    Free
                                </div>

                            <?php endif; ?>


                            <?php foreach ($classes_today as $entry): ?>

                                <?php
                                /*
                                 * Convert actual class times into
                                 * minutes from midnight.
                                 */
                                $start_minutes =
                                    timetable_minutes_from_midnight(
                                        (string)$entry['start_time']
                                    );

                                $end_minutes =
                                    timetable_minutes_from_midnight(
                                        (string)$entry['end_time']
                                    );

                                /*
                                 * Ignore invalid records.
                                 */
                                if (
                                    $end_minutes <=
                                    $start_minutes
                                ) {
                                    continue;
                                }

                                /*
                                 * Clamp the visible part to the
                                 * 08:00–20:00 grid.
                                 */
                                $visible_start =
                                    max(
                                        $start_minutes,
                                        $grid_start_minutes
                                    );

                                $visible_end =
                                    min(
                                        $end_minutes,
                                        $grid_end_minutes
                                    );

                                /*
                                 * If the class is completely
                                 * outside the visible grid,
                                 * don't render it.
                                 */
                                if (
                                    $visible_end <=
                                    $visible_start
                                ) {
                                    continue;
                                }

                                /*
                                 * 40px per 30 minutes.
                                 *
                                 * Example:
                                 * 08:00–10:00 = 160px
                                 * 15:00–17:00 = 160px
                                 * 09:00–12:00 = 240px
                                 */
                                $top_px =
                                    (
                                        (
                                            $visible_start -
                                            $grid_start_minutes
                                        )
                                        /
                                        $slot_minutes
                                    )
                                    *
                                    $slot_height;

                                $height_px =
                                    (
                                        (
                                            $visible_end -
                                            $visible_start
                                        )
                                        /
                                        $slot_minutes
                                    )
                                    *
                                    $slot_height;

                                /*
                                 * Ensure the block remains visible
                                 * even for a very short class.
                                 */
                                $height_px =
                                    max(
                                        24,
                                        $height_px
                                    );
                                ?>


                                <a
                                    href="edit.php?id=<?= (int)$entry['id'] ?>"
                                    class="teacher-class-block"
                                    style="
                                        top: <?= number_format(
                                            $top_px,
                                            2,
                                            '.',
                                            ''
                                        ) ?>px;
                                        height: <?= number_format(
                                            $height_px,
                                            2,
                                            '.',
                                            ''
                                        ) ?>px;
                                        text-decoration: none;
                                        color: inherit;
                                    "
                                    title="<?= htmlspecialchars(
                                        $entry['subject_name'] .
                                        ' — ' .
                                        $entry['class_name'] .
                                        ' — ' .
                                        timetable_format_time(
                                            $entry['start_time']
                                        ) .
                                        '–' .
                                        timetable_format_time(
                                            $entry['end_time']
                                        ) .
                                        ' · Click to edit',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                    <div class="teacher-class-time">
                                        <?= timetable_format_time(
                                            $entry['start_time']
                                        ) ?>
                                        –
                                        <?= timetable_format_time(
                                            $entry['end_time']
                                        ) ?>
                                    </div>

                                    <div class="teacher-class-subject">
                                        <?= htmlspecialchars(
                                            $entry['subject_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div class="teacher-class-name">
                                        <?= htmlspecialchars(
                                            $entry['class_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div class="teacher-class-room">
                                        <i class="bi bi-door-open"></i>
                                        <?= htmlspecialchars(
                                            $entry['room_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div class="teacher-class-students">
                                        <i class="bi bi-people-fill"></i>
                                        <?= (int)(
                                            $entry['student_count'] ?? 0
                                        ) ?>
                                    </div>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    </div>


    <div class="teacher-schedule-legend">

        <span class="teacher-schedule-legend-dot"></span>

        <span>
            Scheduled class · tap a lesson to edit · each row represents 30 minutes
        </span>

    </div>

</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>