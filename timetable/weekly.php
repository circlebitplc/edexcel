<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/recurring.php';
require_once __DIR__ . '/../config/cache.php';

require_staff();

date_default_timezone_set('Asia/Colombo');

/*
|--------------------------------------------------------------------------
| Weekly Timetable - upgraded dashboard
|--------------------------------------------------------------------------
| Features:
| - Teacher / Room / Class views
| - Previous / This Week / Next Week navigation
| - Current-day highlighting
| - 15-minute grid for accurate lesson positioning
| - Subject-based colours
| - Responsive mobile day cards
| - Dashboard summary cards
| - Current lesson status: Upcoming / NOW / Completed
| - WhatsApp group link status
| - Clickable lesson detail modal
| - PDF export
| - Admin/teacher access restrictions preserved
|--------------------------------------------------------------------------
*/

generate_future_entries($pdo, 7);

$is_admin   = is_admin();
$is_teacher = is_teacher();

$session_teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

if ($is_teacher) {
    if ($session_teacher_id <= 0) {
        http_response_code(403);
        exit('Your teacher account is not linked to a teacher profile.');
    }
    $type = 'teacher';
    $id   = $session_teacher_id;
} elseif (!$is_admin) {
    http_response_code(403);
    exit('Access denied.');
} else {
    $type = (string)($_GET['type'] ?? 'teacher');
    $id   = (int)($_GET['id'] ?? 0);
}

$allowed_types = ['teacher', 'room', 'class'];

if (!in_array($type, $allowed_types, true)) {
    $type = 'teacher';
}

/*
 * Week navigation:
 * timetable_week = -1 / 0 / 1 etc.
 * week_start is also accepted for compatibility with the old page.
 */
$weekOffset = filter_input(INPUT_GET, 'timetable_week', FILTER_VALIDATE_INT);
if ($weekOffset === false || $weekOffset === null) {
    $weekOffset = 0;
}
$weekOffset = max(-52, min(52, (int)$weekOffset));

if (!empty($_GET['week_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['week_start'])) {
    $requestedWeekStart = (string)$_GET['week_start'];
    $week_start = date('Y-m-d', strtotime('monday this week', strtotime($requestedWeekStart)));
} else {
    $this_week_monday = date('Y-m-d', strtotime('monday this week'));
    $this_week_sunday = date('Y-m-d', strtotime('+6 days', strtotime($this_week_monday)));

    if ($type === 'teacher' && $id > 0) {
        $cur_stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM timetable
            WHERE (teacher_id = ? OR substitute_teacher_id = ?)
              AND deleted_at IS NULL
              AND date BETWEEN ? AND ?
        ");
        $cur_stmt->execute([$id, $id, $this_week_monday, $this_week_sunday]);
        $classes_this_week = (int)$cur_stmt->fetchColumn();

        if ($classes_this_week > 0) {
            $week_start = $this_week_monday;
        } else {
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
            $next_stmt->execute([$id, $id, $today]);
            $next_class_date = $next_stmt->fetchColumn();

            if ($next_class_date) {
                $week_start = date('Y-m-d', strtotime('monday this week', strtotime((string)$next_class_date)));
            } else {
                $week_start = $this_week_monday;
            }
        }
    } else {
        $week_start = $this_week_monday;
    }
}

if ($weekOffset !== 0) {
    $week_start = date(
        'Y-m-d',
        strtotime(($weekOffset > 0 ? '+' : '') . ($weekOffset * 7) . ' days', strtotime($week_start))
    );
}

$week_end = date('Y-m-d', strtotime('+6 days', strtotime($week_start)));

$today = date('Y-m-d');
$now   = date('H:i:s');

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("+{$i} days", strtotime($week_start)));
}

/*
 * 30-minute grid defaults.
 * 08:00 -> 20:00 default; dynamically expanded after loading weekly entries.
 */
$grid_start_minutes = 8 * 60;
$grid_end_minutes   = 20 * 60;
$slot_minutes       = 30;

$time_slots = [];
for ($m = $grid_start_minutes; $m < $grid_end_minutes; $m += $slot_minutes) {
    $time_slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('minutes_from_time')) {
    function minutes_from_time(string $time): int
    {
        $parts = explode(':', $time);
        return ((int)($parts[0] ?? 0) * 60) + (int)($parts[1] ?? 0);
    }
}

if (!function_exists('time_label')) {
    function time_label(string $time): string
    {
        return date('g:i A', strtotime($time));
    }
}

if (!function_exists('day_label')) {
    function day_label(string $date): string
    {
        return date('D', strtotime($date));
    }
}

if (!function_exists('full_day_label')) {
    function full_day_label(string $date): string
    {
        return date('D d M', strtotime($date));
    }
}

if (!function_exists('subject_class')) {
    function subject_class(string $subject): string
    {
        $s = strtolower(trim($subject));

        if (str_contains($s, 'computer science')) return 'subject-cs';
        if (str_contains($s, 'ict') || str_contains($s, 'information technology')) return 'subject-ict';
        if (str_contains($s, 'mathemat')) return 'subject-maths';
        if (str_contains($s, 'physics')) return 'subject-physics';
        if (str_contains($s, 'chemistry')) return 'subject-chemistry';
        if (str_contains($s, 'biology')) return 'subject-biology';
        if (str_contains($s, 'account')) return 'subject-accounting';
        if (str_contains($s, 'business')) return 'subject-business';
        if (str_contains($s, 'econom')) return 'subject-economics';
        if (str_contains($s, 'english')) return 'subject-english';
        return 'subject-default';
    }
}

if (!function_exists('lesson_status')) {
    function lesson_status(array $entry, string $today, string $now): string
    {
        if (($entry['date'] ?? '') < $today) {
            return 'completed';
        }

        if (($entry['date'] ?? '') > $today) {
            return 'upcoming';
        }

        $start = (string)($entry['start_time'] ?? '00:00:00');
        $end   = (string)($entry['end_time'] ?? '00:00:00');

        if ($now >= $start && $now < $end) {
            return 'now';
        }

        if ($now < $start) {
            return 'upcoming';
        }

        return 'completed';
    }
}

if (!function_exists('whatsapp_link_for')) {
    function whatsapp_link_for(PDO $pdo, array $entry): string
    {
        /*
         * First prefer exact class + teacher + subject.
         * Then fall back to class + teacher where subject_id is NULL/0.
         */
        $stmt = $pdo->prepare("
            SELECT whatsapp_link
            FROM class_teacher_whatsapp
            WHERE class_id = ?
              AND teacher_id = ?
              AND subject_id = ?
              AND whatsapp_link IS NOT NULL
              AND whatsapp_link <> ''
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            (int)($entry['class_id'] ?? 0),
            (int)($entry['teacher_id'] ?? 0),
            (int)($entry['subject_id'] ?? 0),
        ]);
        $link = (string)$stmt->fetchColumn();

        if ($link !== '') {
            return $link;
        }

        $fallback = $pdo->prepare("
            SELECT whatsapp_link
            FROM class_teacher_whatsapp
            WHERE class_id = ?
              AND teacher_id = ?
              AND (subject_id IS NULL OR subject_id = 0)
              AND whatsapp_link IS NOT NULL
              AND whatsapp_link <> ''
            ORDER BY id DESC
            LIMIT 1
        ");
        $fallback->execute([
            (int)($entry['class_id'] ?? 0),
            (int)($entry['teacher_id'] ?? 0),
        ]);

        return (string)$fallback->fetchColumn();
    }
}

/*
|--------------------------------------------------------------------------
| Admin default teacher
|--------------------------------------------------------------------------
*/
if (!$is_teacher && $type === 'teacher' && $id <= 0) {
    $defaultTeacher = $pdo->query("
        SELECT id
        FROM teachers
        WHERE deleted_at IS NULL
        ORDER BY name
        LIMIT 1
    ")->fetchColumn();

    if ($defaultTeacher) {
        $id = (int)$defaultTeacher;
    }
}

/*
|--------------------------------------------------------------------------
| Selected entity
|--------------------------------------------------------------------------
*/
$title = '';
$entityName = '';

if ($type === 'teacher' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT name
        FROM teachers
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity) {
        if ($is_teacher) {
            http_response_code(403);
            exit('Your linked teacher profile is inactive. Ask an administrator to review users.teacher_id.');
        }
        $id = 0;
        $title = 'Teacher timetable';
    } else {
        $entityName = (string)$entity['name'];
        $title = $entityName . "'s Weekly Schedule";
    }
} elseif ($type === 'room' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT name
        FROM rooms
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity) {
        $id = 0;
        $title = 'Room timetable';
    } else {
        $entityName = (string)$entity['name'];
        $title = $entityName . "'s Weekly Schedule";
    }
} elseif ($type === 'class' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT name
        FROM student_classes
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity) {
        $id = 0;
        $title = 'Class timetable';
    } else {
        $entityName = (string)$entity['name'];
        $title = $entityName . "'s Weekly Schedule";
    }
}

/*
|--------------------------------------------------------------------------
| Fetch dropdown data
|--------------------------------------------------------------------------
*/
$dropdown_cache_key = 'weekly_dropdowns_v2';
$dropdowns = $cache->get($dropdown_cache_key);

if ($dropdowns === null) {
    $teachers = $pdo->query("
        SELECT id, name
        FROM teachers
        WHERE deleted_at IS NULL
          AND LOWER(name) <> 'default teacher'
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $rooms = $pdo->query("
        SELECT id, name
        FROM rooms
        WHERE deleted_at IS NULL
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $classes = $pdo->query("
        SELECT id, name
        FROM student_classes
        WHERE deleted_at IS NULL
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $dropdowns = [
        'teachers' => $teachers,
        'rooms'    => $rooms,
        'classes'  => $classes,
    ];

    $cache->set($dropdown_cache_key, $dropdowns, 3600);
} else {
    $teachers = $dropdowns['teachers'] ?? [];
    $rooms    = $dropdowns['rooms'] ?? [];
    $classes  = $dropdowns['classes'] ?? [];
}

/*
|--------------------------------------------------------------------------
| Fetch weekly timetable
|--------------------------------------------------------------------------
*/
$entries = [];

if ($id > 0) {
    $where = [
        "t.date BETWEEN ? AND ?",
        "t.deleted_at IS NULL"
    ];

    $params = [$week_start, $week_end];

    if ($type === 'teacher') {
        $where[] = "(t.teacher_id = ? OR t.substitute_teacher_id = ?)";
        $params[] = $id;
        $params[] = $id;
    } elseif ($type === 'room') {
        $where[] = "t.room_id = ?";
        $params[] = $id;
    } elseif ($type === 'class') {
        $where[] = "t.class_id = ?";
        $params[] = $id;
    }

    $cache_key = 'weekly_dashboard_' . md5(
        $type . '|' . $id . '|' . $week_start . '|' . $week_end
    );

    $entries = $cache->get($cache_key);

    if ($entries === null) {
        $sql = "
            SELECT
                t.id,
                t.date,
                t.start_time,
                t.end_time,
                t.teacher_id,
                t.substitute_teacher_id,
                t.subject_id,
                t.class_id,
                t.room_id,
                t.student_count,
                COALESCE(st.name, tc.name, 'Unassigned teacher') AS teacher_name,
                tc.name AS assigned_teacher_name,
                st.name AS substitute_teacher_name,
                s.name AS subject_name,
                c.name AS class_name,
                r.name AS room_name,

                COALESCE(
                    (
                        SELECT cw.whatsapp_link
                        FROM class_teacher_whatsapp cw
                        WHERE cw.class_id = t.class_id
                          AND cw.teacher_id = t.teacher_id
                          AND cw.subject_id = t.subject_id
                          AND cw.whatsapp_link IS NOT NULL
                          AND cw.whatsapp_link <> ''
                        ORDER BY cw.id DESC
                        LIMIT 1
                    ),
                    (
                        SELECT cw2.whatsapp_link
                        FROM class_teacher_whatsapp cw2
                        WHERE cw2.class_id = t.class_id
                          AND cw2.teacher_id = t.teacher_id
                          AND (cw2.subject_id IS NULL OR cw2.subject_id = 0)
                          AND cw2.whatsapp_link IS NOT NULL
                          AND cw2.whatsapp_link <> ''
                        ORDER BY cw2.id DESC
                        LIMIT 1
                    )
                ) AS whatsapp_link

            FROM timetable t

            LEFT JOIN teachers tc
                ON tc.id = t.teacher_id
               AND tc.deleted_at IS NULL

            LEFT JOIN teachers st
                ON st.id = t.substitute_teacher_id
               AND st.deleted_at IS NULL

            LEFT JOIN subjects s
                ON s.id = t.subject_id
               AND s.deleted_at IS NULL

            LEFT JOIN student_classes c
                ON c.id = t.class_id
               AND c.deleted_at IS NULL

            LEFT JOIN rooms r
                ON r.id = t.room_id
               AND r.deleted_at IS NULL

            WHERE " . implode(" AND ", $where) . "

            ORDER BY
                t.date ASC,
                t.start_time ASC,
                t.end_time ASC,
                c.name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache->set($cache_key, $entries, 60);
    }
}

/*
|--------------------------------------------------------------------------
| Enrich entries and build day structure
|--------------------------------------------------------------------------
*/
$day_entries = [];
foreach ($days as $d) {
    $day_entries[$d] = [];
}

$totalStudents = 0;
$totalHours = 0.0;
$subjectCounts = [];
$roomCounts = [];
$todayClasses = 0;
$nowEntryId = null;

foreach ($entries as &$entry) {
    $entry['student_count'] = (int)($entry['student_count'] ?? 0);
    $entry['whatsapp_link'] = trim((string)($entry['whatsapp_link'] ?? ''));
    $entry['status'] = lesson_status($entry, $today, $now);
    $entry['subject_css'] = subject_class((string)$entry['subject_name']);

    $startMinutes = minutes_from_time((string)$entry['start_time']);
    $endMinutes   = minutes_from_time((string)$entry['end_time']);

    if ($endMinutes <= $startMinutes) {
        $endMinutes = $startMinutes + 60;
    }

    $entry['duration_hours'] = round(($endMinutes - $startMinutes) / 60, 2);

    $totalStudents += $entry['student_count'];
    $totalHours += $entry['duration_hours'];

    $subjectKey = (string)$entry['subject_name'];
    $roomKey = (string)$entry['room_name'];

    $subjectCounts[$subjectKey] = ($subjectCounts[$subjectKey] ?? 0) + 1;
    $roomCounts[$roomKey] = ($roomCounts[$roomKey] ?? 0) + 1;

    if ($entry['date'] === $today) {
        $todayClasses++;

        if ($entry['status'] === 'now') {
            $nowEntryId = (int)$entry['id'];
        }
    }

    if (isset($day_entries[$entry['date']])) {
        $day_entries[$entry['date']][] = $entry;
    }
}
unset($entry);

$totalClasses = count($entries);
$totalRooms = count(array_filter(array_keys($roomCounts), static fn($x) => trim((string)$x) !== ''));

usort($subjectCounts, static fn($a, $b) => $b <=> $a);
$uniqueSubjects = count($subjectCounts);

/*
 * Dynamically adjust grid start and end hours to include all scheduled lessons for this week.
 * Default is 08:00 to 20:00 (8:00 AM to 8:00 PM).
 */
$grid_start_minutes = 8 * 60;
$grid_end_minutes   = 20 * 60;

foreach ($entries as $e_item) {
    $s_min = minutes_from_time((string)$e_item['start_time']);
    $e_min = minutes_from_time((string)$e_item['end_time']);

    if ($e_min <= $s_min) {
        $e_min = $s_min + 60;
    }

    if ($s_min < $grid_start_minutes) {
        $grid_start_minutes = (int)(floor($s_min / 60) * 60);
    }

    if ($e_min > $grid_end_minutes) {
        $grid_end_minutes = (int)(ceil($e_min / 60) * 60);
    }
}

$grid_start_minutes = max(0, min($grid_start_minutes, 8 * 60));
$grid_end_minutes   = min(24 * 60, max($grid_end_minutes, 20 * 60));

$time_slots = [];
for ($m = $grid_start_minutes; $m < $grid_end_minutes; $m += $slot_minutes) {
    $time_slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/*
|--------------------------------------------------------------------------
| Navigation URLs
|--------------------------------------------------------------------------
*/
if (!function_exists('weekly_url')) {
    function weekly_url(string $type, int $id, string $weekStart): string
    {
        return '?' . http_build_query([
            'type'      => $type,
            'id'        => $id,
            'week_start'=> $weekStart,
        ]);
    }
}

$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($week_start)));
$nextWeek = date('Y-m-d', strtotime('+7 days', strtotime($week_start)));
$thisWeek = date('Y-m-d', strtotime('monday this week'));

$prevUrl = weekly_url($type, $id, $prevWeek);
$nextUrl = weekly_url($type, $id, $nextWeek);
$thisUrl = weekly_url($type, $id, $thisWeek);

$exportUrl = 'export_pdf.php?' . http_build_query([
    'type'      => $type,
    'id'        => $id,
    'week_start'=> $week_start,
]);

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   WEEKLY TIMETABLE DASHBOARD
   ============================================================ */

:root {
    --tt-bg: #0d111b;
    --tt-panel: #151b28;
    --tt-panel-2: #1b2230;
    --tt-border: rgba(148, 163, 184, .18);
    --tt-text: #f8fafc;
    --tt-muted: #94a3b8;
    --tt-primary: #4f7cff;
    --tt-success: #16a34a;
    --tt-warning: #f59e0b;
    --tt-danger: #ef4444;
}

.tt-dashboard {
    padding: 18px 0 40px;
}

.tt-hero {
    background: linear-gradient(135deg, #101827 0%, #151d2d 55%, #111827 100%);
    border: 1px solid var(--tt-border);
    border-radius: 24px;
    padding: 28px;
    color: var(--tt-text);
    margin-bottom: 18px;
    box-shadow: 0 18px 50px rgba(0,0,0,.16);
}

.tt-hero-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
}

.tt-kicker {
    text-transform: uppercase;
    letter-spacing: .12em;
    font-size: .72rem;
    color: #9fb5ff;
    font-weight: 800;
    margin-bottom: 6px;
}

.tt-hero h1 {
    margin: 0;
    font-size: clamp(1.7rem, 3vw, 2.5rem);
    font-weight: 800;
}

.tt-hero p {
    margin: 7px 0 0;
    color: #b8c4d8;
}

.tt-week-pill {
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.05);
    border-radius: 14px;
    padding: 12px 16px;
    min-width: 190px;
    text-align: right;
}

.tt-week-pill strong {
    display: block;
    font-size: .95rem;
}

.tt-week-pill span {
    color: #9aa8bd;
    font-size: .78rem;
}

.tt-filter-card,
.tt-summary-card,
.tt-timetable-card {
    background: var(--tt-panel);
    border: 1px solid var(--tt-border);
    border-radius: 20px;
    box-shadow: 0 12px 35px rgba(0,0,0,.08);
}

.tt-filter-card {
    padding: 18px;
    margin-bottom: 18px;
}

.tt-filter-label {
    display: block;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--tt-muted);
    margin-bottom: 7px;
    letter-spacing: .04em;
}

.tt-filter-card .form-select,
.tt-filter-card .form-control {
    min-height: 46px;
    border-radius: 12px;
}

.tt-nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tt-nav .btn {
    min-height: 46px;
    border-radius: 12px;
    font-weight: 700;
}

.tt-summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(0,1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.tt-summary-card {
    padding: 18px;
}

.tt-summary-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: rgba(79,124,255,.12);
    color: #80a0ff;
    margin-bottom: 10px;
}

.tt-summary-number {
    font-size: 1.55rem;
    line-height: 1;
    font-weight: 800;
    color: var(--tt-text);
}

.tt-summary-label {
    margin-top: 6px;
    color: var(--tt-muted);
    font-size: .8rem;
}

.tt-timetable-card {
    overflow: hidden;
}

.tt-table-head {
    padding: 18px 20px;
    border-bottom: 1px solid var(--tt-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.tt-table-head h2 {
    margin: 0;
    font-size: 1.15rem;
    color: var(--tt-text);
}

.tt-table-head p {
    margin: 3px 0 0;
    color: var(--tt-muted);
    font-size: .82rem;
}

.tt-legend {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    color: var(--tt-muted);
    font-size: .75rem;
}

.tt-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.tt-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
}

.tt-dot-now { background: #ef4444; }
.tt-dot-wa { background: #22c55e; }
.tt-dot-free { background: #64748b; }

.tt-grid-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.tt-grid {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
    table-layout: fixed;
    color: var(--tt-text);
}

.tt-grid th,
.tt-grid td {
    border-right: 1px solid var(--tt-border);
    border-bottom: 1px solid var(--tt-border);
}

.tt-grid th:last-child,
.tt-grid td:last-child {
    border-right: 0;
}

.tt-grid thead th {
    height: 68px;
    background: #20283a;
    padding: 9px 10px;
    text-align: center;
    font-size: .76rem;
    color: #b8c7df;
    text-transform: uppercase;
    letter-spacing: .03em;
    position: sticky;
    top: 0;
    z-index: 5;
}

.tt-grid thead th:first-child {
    width: 105px;
    text-align: left;
}

.tt-grid thead th.tt-today {
    background: linear-gradient(180deg, #304a88, #26395f);
    color: #fff;
}

.tt-day-name {
    display: block;
    font-weight: 800;
}

.tt-day-date {
    display: block;
    margin-top: 3px;
    opacity: .75;
    font-size: .68rem;
}

.tt-today-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 6px;
    border-radius: 999px;
    background: #fff;
    color: #28417d;
    font-size: .58rem;
    font-weight: 900;
}

.tt-grid tbody tr {
    height: 58px;
}

.tt-grid tbody td {
    height: 58px;
}

.tt-time-cell {
    background: #151b28;
    color: #aebbd0;
    font-size: .69rem;
    font-weight: 800;
    padding: 4px 7px;
    vertical-align: top;
    white-space: nowrap;
}

.tt-free-cell {
    background: rgba(255,255,255,.012);
    color: #65748b;
    text-align: center;
    font-size: .62rem;
    min-height: 28px;
}

.tt-day-today-cell {
    background: rgba(79,124,255,.035);
}

.tt-lesson-cell {
    padding: 4px;
    vertical-align: top;
    background: rgba(255,255,255,.008);
    position: relative;
}

.tt-lesson {
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    width: 100%;
    height: 100%;
    min-height: 100%;
    border-radius: 10px;
    padding: 9px;
    cursor: pointer;
    color: #fff;
    text-align: left;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.16);
    flex: 1 1 auto;
    box-shadow: 0 7px 18px rgba(0,0,0,.16);
    transition: transform .16s ease, box-shadow .16s ease;
}

.tt-lesson:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(0,0,0,.25);
}

.tt-lesson.is-now {
    outline: 2px solid #ef4444;
    outline-offset: 1px;
}

.tt-lesson.is-completed {
    opacity: .7;
}

.tt-lesson-time {
    font-size: .69rem;
    font-weight: 900;
    margin-bottom: 4px;
}

.tt-lesson-subject {
    font-size: .82rem;
    line-height: 1.15;
    font-weight: 900;
}

.tt-lesson-teacher,
.tt-lesson-room,
.tt-lesson-class {
    font-size: .67rem;
    line-height: 1.35;
    margin-top: 3px;
    opacity: .94;
}

.tt-lesson-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
    margin-top: auto;
    padding-top: 6px;
    font-size: .65rem;
    font-weight: 800;
}

.tt-wa {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    border-radius: 999px;
    padding: 2px 6px;
    background: rgba(0,0,0,.22);
    color: #fff;
    text-decoration: none;
}

.tt-wa:hover {
    color: #fff;
    background: rgba(0,0,0,.36);
}

.tt-wa.missing {
    color: rgba(255,255,255,.7);
}

.tt-status {
    border-radius: 999px;
    padding: 2px 6px;
    background: rgba(0,0,0,.2);
    font-size: .58rem;
    white-space: nowrap;
}

.subject-ict { background: linear-gradient(135deg,#08b6d6,#159dd0); }
.subject-cs { background: linear-gradient(135deg,#7657df,#5641bd); }
.subject-maths { background: linear-gradient(135deg,#f59e0b,#dc7b06); }
.subject-physics { background: linear-gradient(135deg,#ef4444,#c62d39); }
.subject-chemistry { background: linear-gradient(135deg,#16a34a,#087f3d); }
.subject-biology { background: linear-gradient(135deg,#0f9f8d,#087f73); }
.subject-accounting { background: linear-gradient(135deg,#ca8a04,#a16207); }
.subject-business { background: linear-gradient(135deg,#db2777,#b91c5d); }
.subject-economics { background: linear-gradient(135deg,#0284c7,#0369a1); }
.subject-english { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
.subject-default { background: linear-gradient(135deg,#475569,#334155); }

.tt-mobile-days {
    display: none;
}

.tt-mobile-day {
    border-bottom: 1px solid var(--tt-border);
}

.tt-mobile-day:last-child {
    border-bottom: 0;
}

.tt-mobile-day-header {
    padding: 14px 16px;
    background: #20283a;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 2;
}

.tt-mobile-day.is-today .tt-mobile-day-header {
    background: #304a88;
}

.tt-mobile-day-header strong {
    font-size: .9rem;
}

.tt-mobile-day-header span {
    font-size: .72rem;
    color: #bdc9dc;
}

.tt-mobile-empty {
    padding: 22px 16px;
    text-align: center;
    color: #64748b;
}

.tt-mobile-lesson {
    margin: 12px 14px;
}

.tt-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tt-actions .btn {
    border-radius: 10px;
    font-weight: 700;
}

.tt-modal .modal-content {
    background: #151b28;
    color: #f8fafc;
    border: 1px solid var(--tt-border);
    border-radius: 18px;
}

.tt-modal .modal-header {
    border-bottom-color: var(--tt-border);
}

.tt-modal .modal-footer {
    border-top-color: var(--tt-border);
}

.tt-detail-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 10px;
}

.tt-detail-item {
    padding: 12px;
    background: #1b2230;
    border: 1px solid var(--tt-border);
    border-radius: 11px;
}

.tt-detail-item small {
    display: block;
    color: #94a3b8;
    font-size: .68rem;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: 3px;
}

.tt-detail-item strong {
    font-size: .88rem;
}

.tt-empty {
    padding: 50px 20px;
    text-align: center;
    color: var(--tt-muted);
}

.tt-empty i {
    font-size: 2.5rem;
    opacity: .55;
    display: block;
    margin-bottom: 10px;
}

@media (max-width: 1100px) {
    .tt-summary {
        grid-template-columns: repeat(3,minmax(0,1fr));
    }
}

@media (max-width: 767.98px) {
    .tt-dashboard {
        padding-top: 8px;
    }

    .tt-hero {
        border-radius: 16px;
        padding: 20px;
    }

    .tt-hero-top {
        display: block;
    }

    .tt-week-pill {
        margin-top: 14px;
        text-align: left;
    }

    .tt-summary {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }

    .tt-summary-card {
        padding: 14px;
    }

    .tt-filter-card {
        padding: 14px;
        border-radius: 16px;
    }

    .tt-nav {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .tt-nav .btn:last-child {
        grid-column: 1 / -1;
    }

    .tt-table-head {
        display: block;
    }

    .tt-legend {
        margin-top: 10px;
    }

    .tt-grid-wrap {
        display: none;
    }

    .tt-mobile-days {
        display: block;
    }

    .tt-detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 420px) {
    .tt-summary {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .tt-summary-number {
        font-size: 1.25rem;
    }
}
</style>

<div class="tt-dashboard">

    <section class="tt-hero">
        <div class="tt-hero-top">
            <div>
                <div class="tt-kicker">
                    <i class="bi bi-calendar-week"></i> Edexcel College
                </div>

                <h1>
                    <?= h($title ?: 'Weekly Timetable') ?>
                </h1>

                <p>
                    <?= h(date('d M Y', strtotime($week_start))) ?>
                    –
                    <?= h(date('d M Y', strtotime($week_end))) ?>
                    ·
                    <?= $totalClasses ?> scheduled class<?= $totalClasses === 1 ? '' : 'es' ?>
                </p>
            </div>

            <div class="tt-week-pill">
                <strong>
                    <?= $week_start === $thisWeek ? 'Current Week' : 'Selected Week' ?>
                </strong>
                <span>
                    <?= h(date('l, d M Y', strtotime($week_start))) ?>
                </span>
            </div>
        </div>
    </section>

    <section class="tt-filter-card">

        <form method="GET" id="filterForm">

            <div class="row g-3">

                <div class="col-lg-2 col-md-4">
                    <label class="tt-filter-label" for="type">
                        View by
                    </label>

                    <select
                        id="type"
                        name="type"
                        class="form-select"
                        <?= $is_teacher ? 'disabled' : '' ?>
                        onchange="document.getElementById('filterForm').submit()"
                    >
                        <option value="teacher" <?= $type === 'teacher' ? 'selected' : '' ?>>
                            Teacher
                        </option>

                        <option value="room" <?= $type === 'room' ? 'selected' : '' ?>>
                            Room
                        </option>

                        <option value="class" <?= $type === 'class' ? 'selected' : '' ?>>
                            Class
                        </option>
                    </select>

                    <?php if ($is_teacher): ?>
                        <input type="hidden" name="type" value="teacher">
                    <?php endif; ?>
                </div>

                <div class="col-lg-3 col-md-8">
                    <label class="tt-filter-label" for="id">
                        Select <?= h(ucfirst($type)) ?>
                    </label>

                    <select
                        id="id"
                        name="id"
                        class="form-select"
                        onchange="document.getElementById('filterForm').submit()"
                        <?= $is_teacher ? 'disabled' : '' ?>
                    >
                        <option value="">-- Choose --</option>

                        <?php if ($type === 'teacher'): ?>
                            <?php foreach ($teachers as $teacher): ?>
                                <option
                                    value="<?= (int)$teacher['id'] ?>"
                                    <?= $id === (int)$teacher['id'] ? 'selected' : '' ?>
                                >
                                    <?= h($teacher['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php elseif ($type === 'room'): ?>
                            <?php foreach ($rooms as $room): ?>
                                <option
                                    value="<?= (int)$room['id'] ?>"
                                    <?= $id === (int)$room['id'] ? 'selected' : '' ?>
                                >
                                    <?= h($room['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <option
                                    value="<?= (int)$class['id'] ?>"
                                    <?= $id === (int)$class['id'] ? 'selected' : '' ?>
                                >
                                    <?= h($class['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <?php if ($is_teacher): ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                    <?php endif; ?>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="tt-filter-label" for="week_start">
                        Week
                    </label>

                    <input
                        type="date"
                        id="week_start"
                        name="week_start"
                        class="form-control"
                        value="<?= h($week_start) ?>"
                    >
                </div>

                <div class="col-lg-4 col-md-6 d-flex align-items-end">
                    <div class="tt-actions w-100">

                        <button class="btn btn-primary flex-grow-1" type="submit">
                            <i class="bi bi-arrow-clockwise"></i>
                            Refresh
                        </button>

                        <a
                            href="<?= h($thisUrl) ?>"
                            class="btn btn-outline-primary"
                            title="Go to current week"
                        >
                            <i class="bi bi-calendar-check"></i>
                            Today
                        </a>

                        <a
                            href="<?= h($exportUrl) ?>"
                            class="btn btn-danger"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="bi bi-file-pdf"></i>
                            PDF
                        </a>

                    </div>
                </div>

            </div>

        </form>

        <div class="tt-nav mt-3">

            <a href="<?= h($prevUrl) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
                Previous Week
            </a>

            <a href="<?= h($thisUrl) ?>" class="btn btn-outline-primary">
                <i class="bi bi-calendar-week"></i>
                This Week
            </a>

            <a href="<?= h($nextUrl) ?>" class="btn btn-outline-secondary">
                Next Week
                <i class="bi bi-chevron-right"></i>
            </a>

        </div>

    </section>

    <?php if ($id > 0): ?>

        <section class="tt-summary">

            <div class="tt-summary-card">
                <div class="tt-summary-icon">
                    <i class="bi bi-calendar2-check"></i>
                </div>
                <div class="tt-summary-number"><?= $totalClasses ?></div>
                <div class="tt-summary-label">Classes this week</div>
            </div>

            <div class="tt-summary-card">
                <div class="tt-summary-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="tt-summary-number"><?= number_format($totalHours, 1) ?>h</div>
                <div class="tt-summary-label">Teaching hours</div>
            </div>

            <div class="tt-summary-card">
                <div class="tt-summary-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="tt-summary-number"><?= $totalStudents ?></div>
                <div class="tt-summary-label">Student places</div>
            </div>

            <div class="tt-summary-card">
                <div class="tt-summary-icon">
                    <i class="bi bi-door-open"></i>
                </div>
                <div class="tt-summary-number"><?= $totalRooms ?></div>
                <div class="tt-summary-label">Rooms used</div>
            </div>

            <div class="tt-summary-card">
                <div class="tt-summary-icon">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div class="tt-summary-number"><?= $todayClasses ?></div>
                <div class="tt-summary-label">Classes today</div>
            </div>

        </section>

        <section class="tt-timetable-card">

            <div class="tt-table-head">

                <div>
                    <h2>
                        <i class="bi bi-calendar3"></i>
                        <?= h(date('d M', strtotime($week_start))) ?>
                        –
                        <?= h(date('d M Y', strtotime($week_end))) ?>
                    </h2>

                    <p>
                        30-minute time grid · Click any class for details
                    </p>
                </div>

                <div class="tt-legend">

                    <span class="tt-legend-item">
                        <span class="tt-dot tt-dot-now"></span>
                        NOW
                    </span>

                    <span class="tt-legend-item">
                        <span class="tt-dot tt-dot-wa"></span>
                        WhatsApp
                    </span>

                    <span class="tt-legend-item">
                        <span class="tt-dot tt-dot-free"></span>
                        Free
                    </span>

                </div>

            </div>

            <?php if ($totalClasses > 0): ?>

                <!-- DESKTOP / TABLET GRID -->
                <div class="tt-grid-wrap">

                    <table class="tt-grid">

                        <thead>
                            <tr>
                                <th>Time</th>

                                <?php foreach ($days as $day): ?>
                                    <?php $isToday = $day === $today; ?>

                                    <th class="<?= $isToday ? 'tt-today' : '' ?>">

                                        <span class="tt-day-name">
                                            <?= h(day_label($day)) ?>
                                        </span>

                                        <span class="tt-day-date">
                                            <?= h(date('d M', strtotime($day))) ?>
                                        </span>

                                        <?php if ($isToday): ?>
                                            <span class="tt-today-badge">TODAY</span>
                                        <?php endif; ?>

                                    </th>
                                <?php endforeach; ?>

                            </tr>
                        </thead>

                        <tbody>

                        <?php
                        /*
                         * For every day we maintain occupied 30-minute slots.
                         * This prevents a second cell from being rendered inside
                         * a lesson's rowspan.
                         */
                        $occupied = [];

                        foreach ($days as $day) {
                            $occupied[$day] = [];
                        }

                        for ($slotIndex = 0; $slotIndex < count($time_slots); $slotIndex++):
                            $slot = $time_slots[$slotIndex];
                            $slotStartMinutes = minutes_from_time($slot);
                        ?>

                            <tr>

                                <td class="tt-time-cell">
                                    <?= h(time_label($slot)) ?>
                                </td>

                                <?php foreach ($days as $day): ?>

                                    <?php
                                    $isToday = $day === $today;

                                    if (!empty($occupied[$day][$slot])) {
                                        continue;
                                    }

                                    $lesson = null;

                                    foreach ($day_entries[$day] as $candidate) {
                                        $candidateStart = minutes_from_time((string)$candidate['start_time']);
                                        $candidateEnd   = minutes_from_time((string)$candidate['end_time']);

                                        if ($candidateEnd <= $candidateStart) {
                                            $candidateEnd = $candidateStart + 60;
                                        }

                                        /*
                                         * Match if lesson begins in this 30-minute interval
                                         * (or if it began before grid start and this is the first slot)
                                         */
                                        $startsInSlot = ($slotIndex === 0 && $candidateStart < $slotStartMinutes)
                                            || ($candidateStart >= $slotStartMinutes && $candidateStart < ($slotStartMinutes + $slot_minutes));

                                        if ($startsInSlot && $candidateEnd > $slotStartMinutes) {
                                            $lesson = $candidate;
                                            break;
                                        }
                                    }

                                    if (!$lesson):
                                    ?>

                                        <td class="tt-free-cell <?= $isToday ? 'tt-day-today-cell' : '' ?>">
                                            FREE
                                        </td>

                                    <?php else: ?>

                                        <?php
                                        $lessonStart = minutes_from_time((string)$lesson['start_time']);
                                        $lessonEnd   = minutes_from_time((string)$lesson['end_time']);

                                        if ($lessonEnd <= $lessonStart) {
                                            $lessonEnd = $lessonStart + 60;
                                        }

                                        $rowspan = max(
                                            1,
                                            (int)ceil(($lessonEnd - $slotStartMinutes) / $slot_minutes)
                                        );

                                        $remainingRows = count($time_slots) - $slotIndex;
                                        $rowspan = min($rowspan, $remainingRows);

                                        for ($r = 1; $r < $rowspan; $r++) {
                                            if (isset($time_slots[$slotIndex + $r])) {
                                                $occupied[$day][$time_slots[$slotIndex + $r]] = true;
                                            }
                                        }

                                        $status = (string)$lesson['status'];
                                        $statusLabel = match ($status) {
                                            'now' => 'NOW',
                                            'completed' => 'DONE',
                                            default => 'UPCOMING',
                                        };

                                        $wa = (string)$lesson['whatsapp_link'];

                                        $detailJson = htmlspecialchars(
                                            json_encode(
                                                [
                                                    'id' => (int)$lesson['id'],
                                                    'date' => date('l, d M Y', strtotime($lesson['date'])),
                                                    'time' => time_label((string)$lesson['start_time']) . ' – ' . time_label((string)$lesson['end_time']),
                                                    'subject' => (string)$lesson['subject_name'],
                                                    'teacher' => (string)$lesson['teacher_name'],
                                                    'class' => (string)$lesson['class_name'],
                                                    'room' => (string)$lesson['room_name'],
                                                    'students' => (int)$lesson['student_count'],
                                                    'duration' => number_format((float)$lesson['duration_hours'], 2) . ' hours',
                                                    'status' => strtoupper($statusLabel),
                                                    'whatsapp' => $wa,
                                                ],
                                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                        <td
                                            rowspan="<?= $rowspan ?>"
                                            class="tt-lesson-cell <?= $isToday ? 'tt-day-today-cell' : '' ?>"
                                        >

                                            <div
                                                class="tt-lesson <?= h($lesson['subject_css']) ?> <?= $status === 'now' ? 'is-now' : '' ?> <?= $status === 'completed' ? 'is-completed' : '' ?>"
                                                data-lesson='<?= $detailJson ?>'
                                                onclick="openLesson(this)"
                                                role="button"
                                                tabindex="0"
                                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openLesson(this)}"
                                            >

                                                <div class="tt-lesson-time">
                                                    <?= h(time_label((string)$lesson['start_time'])) ?>
                                                    –
                                                    <?= h(time_label((string)$lesson['end_time'])) ?>
                                                </div>

                                                <div class="tt-lesson-subject">
                                                    <?= h($lesson['subject_name']) ?>
                                                </div>

                                                <?php if ($type !== 'teacher'): ?>
                                                    <div class="tt-lesson-teacher">
                                                        <i class="bi bi-person"></i>
                                                        <?= h($lesson['teacher_name']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($type !== 'room'): ?>
                                                    <div class="tt-lesson-room">
                                                        <i class="bi bi-door-open"></i>
                                                        <?= h($lesson['room_name']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($type !== 'class'): ?>
                                                    <div class="tt-lesson-class">
                                                        <i class="bi bi-mortarboard"></i>
                                                        <?= h($lesson['class_name']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="tt-lesson-footer">

                                                    <span>
                                                        <i class="bi bi-people-fill"></i>
                                                        <?= (int)$lesson['student_count'] ?>
                                                    </span>

                                                    <span class="tt-status">
                                                        <?= h($statusLabel) ?>
                                                    </span>

                                                    <?php if ($wa !== ''): ?>
                                                        <a
                                                            class="tt-wa"
                                                            href="<?= h($wa) ?>"
                                                            target="_blank"
                                                            rel="noopener"
                                                            onclick="event.stopPropagation()"
                                                            title="Open WhatsApp group"
                                                        >
                                                            <i class="bi bi-whatsapp"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="tt-wa missing" title="No WhatsApp group link">
                                                            <i class="bi bi-whatsapp"></i>
                                                        </span>
                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </td>

                                    <?php endif; ?>

                                <?php endforeach; ?>

                            </tr>

                        <?php endfor; ?>

                        </tbody>

                    </table>

                </div>

                <!-- MOBILE DAY-CARD VIEW -->
                <div class="tt-mobile-days">

                    <?php foreach ($days as $day): ?>

                        <?php $dayLessons = $day_entries[$day]; ?>

                        <section class="tt-mobile-day <?= $day === $today ? 'is-today' : '' ?>">

                            <div class="tt-mobile-day-header">

                                <strong>
                                    <?= h(day_label($day)) ?>
                                    <?= h(date('d M', strtotime($day))) ?>

                                    <?php if ($day === $today): ?>
                                        <span class="tt-today-badge">TODAY</span>
                                    <?php endif; ?>
                                </strong>

                                <span>
                                    <?= count($dayLessons) ?>
                                    class<?= count($dayLessons) === 1 ? '' : 'es' ?>
                                </span>

                            </div>

                            <?php if (!$dayLessons): ?>

                                <div class="tt-mobile-empty">
                                    <i class="bi bi-calendar-x d-block mb-2"></i>
                                    No classes scheduled
                                </div>

                            <?php else: ?>

                                <?php foreach ($dayLessons as $lesson): ?>

                                    <?php
                                    $status = (string)$lesson['status'];
                                    $statusLabel = match ($status) {
                                        'now' => 'NOW',
                                        'completed' => 'DONE',
                                        default => 'UPCOMING',
                                    };

                                    $wa = (string)$lesson['whatsapp_link'];

                                    $detailJson = htmlspecialchars(
                                        json_encode(
                                            [
                                                'id' => (int)$lesson['id'],
                                                'date' => date('l, d M Y', strtotime($lesson['date'])),
                                                'time' => time_label((string)$lesson['start_time']) . ' – ' . time_label((string)$lesson['end_time']),
                                                'subject' => (string)$lesson['subject_name'],
                                                'teacher' => (string)$lesson['teacher_name'],
                                                'class' => (string)$lesson['class_name'],
                                                'room' => (string)$lesson['room_name'],
                                                'students' => (int)$lesson['student_count'],
                                                'duration' => number_format((float)$lesson['duration_hours'], 2) . ' hours',
                                                'status' => strtoupper($statusLabel),
                                                'whatsapp' => $wa,
                                            ],
                                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                    <div
                                        class="tt-lesson tt-mobile-lesson <?= h($lesson['subject_css']) ?> <?= $status === 'now' ? 'is-now' : '' ?> <?= $status === 'completed' ? 'is-completed' : '' ?>"
                                        data-lesson='<?= $detailJson ?>'
                                        onclick="openLesson(this)"
                                        role="button"
                                        tabindex="0"
                                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openLesson(this)}"
                                    >

                                        <div class="tt-lesson-time">
                                            <?= h(time_label((string)$lesson['start_time'])) ?>
                                            –
                                            <?= h(time_label((string)$lesson['end_time'])) ?>
                                        </div>

                                        <div class="tt-lesson-subject">
                                            <?= h($lesson['subject_name']) ?>
                                        </div>

                                        <div class="tt-lesson-teacher">
                                            <i class="bi bi-person"></i>
                                            <?= h($lesson['teacher_name']) ?>
                                        </div>

                                        <div class="tt-lesson-room">
                                            <i class="bi bi-door-open"></i>
                                            <?= h($lesson['room_name']) ?>
                                        </div>

                                        <div class="tt-lesson-class">
                                            <i class="bi bi-mortarboard"></i>
                                            <?= h($lesson['class_name']) ?>
                                        </div>

                                        <div class="tt-lesson-footer">

                                            <span>
                                                <i class="bi bi-people-fill"></i>
                                                <?= (int)$lesson['student_count'] ?>
                                            </span>

                                            <span class="tt-status">
                                                <?= h($statusLabel) ?>
                                            </span>

                                            <?php if ($wa !== ''): ?>
                                                <a
                                                    class="tt-wa"
                                                    href="<?= h($wa) ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                    onclick="event.stopPropagation()"
                                                >
                                                    <i class="bi bi-whatsapp"></i>
                                                    Group
                                                </a>
                                            <?php else: ?>
                                                <span class="tt-wa missing">
                                                    <i class="bi bi-whatsapp"></i>
                                                    No group
                                                </span>
                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </section>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="tt-empty">
                    <i class="bi bi-calendar-x"></i>
                    <strong>No classes found</strong>
                    <div class="mt-1">
                        There are no timetable entries for this selection and week.
                    </div>
                </div>

            <?php endif; ?>

        </section>

    <?php else: ?>

        <section class="tt-timetable-card">
            <div class="tt-empty">
                <i class="bi bi-person-workspace"></i>
                <strong>Please select a <?= h($type) ?></strong>
                <div class="mt-1">
                    Choose an item above to display its weekly timetable.
                </div>
            </div>
        </section>

    <?php endif; ?>

</div>

<!-- LESSON DETAILS MODAL -->
<div
    class="modal fade tt-modal"
    id="lessonModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title mb-1" id="lessonModalTitle">
                        Class Details
                    </h5>

                    <div class="small text-secondary" id="lessonModalSubtitle"></div>
                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>

            <div class="modal-body">

                <div class="tt-detail-grid">

                    <div class="tt-detail-item">
                        <small>Subject</small>
                        <strong id="detailSubject">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Class</small>
                        <strong id="detailClass">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Teacher</small>
                        <strong id="detailTeacher">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Room</small>
                        <strong id="detailRoom">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Date</small>
                        <strong id="detailDate">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Time</small>
                        <strong id="detailTime">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Duration</small>
                        <strong id="detailDuration">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Students</small>
                        <strong id="detailStudents">—</strong>
                    </div>

                    <div class="tt-detail-item">
                        <small>Status</small>
                        <strong id="detailStatus">—</strong>
                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <a
                    href="#"
                    target="_blank"
                    rel="noopener"
                    id="detailWhatsApp"
                    class="btn btn-success d-none"
                >
                    <i class="bi bi-whatsapp"></i>
                    Open WhatsApp Group
                </a>

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>

            </div>

        </div>
    </div>
</div>

<script>
(function () {

    window.openLesson = function (element) {

        let raw = element.getAttribute('data-lesson');

        if (!raw) {
            return;
        }

        let lesson;

        try {
            lesson = JSON.parse(raw);
        } catch (error) {
            console.error('Could not read lesson details:', error);
            return;
        }

        const setText = function (id, value) {
            const node = document.getElementById(id);
            if (node) {
                node.textContent = value || '—';
            }
        };

        setText('lessonModalTitle', lesson.subject || 'Class Details');
        setText('lessonModalSubtitle', lesson.date || '');

        setText('detailSubject', lesson.subject);
        setText('detailClass', lesson.class);
        setText('detailTeacher', lesson.teacher);
        setText('detailRoom', lesson.room);
        setText('detailDate', lesson.date);
        setText('detailTime', lesson.time);
        setText('detailDuration', lesson.duration);
        setText('detailStudents', String(lesson.students ?? 0));
        setText('detailStatus', lesson.status);

        const wa = document.getElementById('detailWhatsApp');

        if (wa) {
            if (lesson.whatsapp) {
                wa.href = lesson.whatsapp;
                wa.classList.remove('d-none');
            } else {
                wa.removeAttribute('href');
                wa.classList.add('d-none');
            }
        }

        const modalElement = document.getElementById('lessonModal');

        if (!modalElement) {
            return;
        }

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    };

    /*
     * Keyboard accessibility for lesson cards.
     */
    document.addEventListener('keydown', function (event) {

        if (
            event.target &&
            event.target.matches &&
            event.target.matches('.tt-lesson') &&
            (event.key === 'Enter' || event.key === ' ')
        ) {
            event.preventDefault();
            window.openLesson(event.target);
        }

    });

    /*
     * On mobile, automatically bring today's section into view.
     */
    document.addEventListener('DOMContentLoaded', function () {

        const today = document.querySelector('.tt-mobile-day.is-today');

        if (
            today &&
            window.innerWidth <= 767 &&
            <?= $week_start === $thisWeek ? 'true' : 'false' ?>
        ) {
            setTimeout(function () {
                today.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }, 350);
        }

    });

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>