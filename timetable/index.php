<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/recurring.php';
require_once __DIR__ . '/../includes/holidays.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../config/cache.php';
require_once __DIR__ . '/../config/notifications.php'; // <-- Added
require_login();

$is_admin = is_admin();
$teacher_id = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : 0;
$is_teacher = !$is_admin && $teacher_id > 0;

// ========================================================
// HANDLE POST REQUESTS (LOCK, UNLOCK, MARK PAID)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
        $services = TimetableServiceFactory::services($pdo);

        if (isset($_POST['toggle_lock']) && isset($_POST['id'])) {
            try {
                $result = $services['lock']->toggle(
                    (int)$_POST['id'],
                    static function(array $entry): void {
                        if (!is_admin()) {
                            throw new RuntimeException('Only administrators can toggle locks.');
                        }
                    }
                );
                $_SESSION['success'] =
                    $result['status'] === 'locked'
                        ? 'Entry locked successfully.'
                        : 'Entry unlocked successfully.';
            } catch (Throwable $e) {
                $_SESSION['error'] = $e->getMessage();
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        if (isset($_POST['mark_paid']) && isset($_POST['entry_id'])) {
            try {
                $result = $services['payment']->markPaid((int)$_POST['entry_id'], static function(array $entry): void {
                    if (!is_admin()) throw new RuntimeException('Only an administrator can mark a lesson as paid.');
                });
                try {
                    $sent = notify_payment(
                        $pdo,
                        (int)$result['teacher_id'],
                        (float)$result['amount']
                    );
                    $_SESSION[$sent ? 'success' : 'error'] =
                        $sent
                            ? 'Payment marked as paid and WhatsApp notification sent to teacher.'
                            : 'Payment marked as paid, but WhatsApp notification failed.';
                } catch (Throwable $notificationError) {
                    error_log('Payment notification failed: '.$notificationError->getMessage());
                    $_SESSION['error'] = 'Payment marked as paid, but WhatsApp notification failed.';
                }
            } catch (Throwable $e) {
                $_SESSION['error'] = $e->getMessage();
            }

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
// ========================================================

// --- Generate future entries ---
generate_future_entries($pdo, 7);

// --- Get current filters (with defaults) --- 

$today = date('Y-m-d');

$filters = [
    'teacher' => (int)($_GET['teacher'] ?? 0),
    'room' => (int)($_GET['room'] ?? 0),
    'class' => (int)($_GET['class'] ?? 0),
    'subject' => (int)($_GET['subject'] ?? 0),
    'day' => $_GET['day'] ?? '',

    // Default From date = Today
    'date_from' => (
        isset($_GET['date_from']) &&
        $_GET['date_from'] !== ''
    )
        ? $_GET['date_from']
        : $today,

    // Default To date = Today
    'date_to' => (
        isset($_GET['date_to']) &&
        $_GET['date_to'] !== ''
    )
        ? $_GET['date_to']
        : $today,

    'payment_status' => $_GET['payment_status'] ?? 'all',

    'view' => (
        ($_GET['view'] ?? 'list') === 'week'
    )
        ? 'week'
        : 'list',

    'compact' => isset($_GET['compact'])
        ? (bool)$_GET['compact']
        : false,
];

/*
 * ============================================================
 * TEACHER SECURITY
 * ============================================================
 *
 * A teacher can never choose another teacher through the URL.
 * The logged-in session is always authoritative.
 */
if ($is_teacher) {
    $filters['teacher'] = $teacher_id;
}

// --- Build WHERE conditions ---
$where = ["t.deleted_at IS NULL"];
$params = [];

if ($filters['teacher']) {
    $where[] = "t.teacher_id = ?";
    $params[] = $filters['teacher'];
}
if ($filters['room']) {
    $where[] = "t.room_id = ?";
    $params[] = $filters['room'];
}
if ($filters['class']) {
    $where[] = "t.class_id = ?";
    $params[] = $filters['class'];
}
if ($filters['subject']) {
    $where[] = "t.subject_id = ?";
    $params[] = $filters['subject'];
}
if ($filters['day']) {
    $where[] = "DAYNAME(t.date) = ?";
    $params[] = $filters['day'];
}
if ($filters['date_from']) {
    $where[] = "t.date >= ?";
    $params[] = $filters['date_from'];
}
if ($filters['date_to']) {
    $where[] = "t.date <= ?";
    $params[] = $filters['date_to'];
}
if ($filters['payment_status'] !== 'all') {
    $where[] = "t.payment_status = ?";
    $params[] = $filters['payment_status'];
}

/*
 * Week range is calculated before the timetable query so the week view
 * can load the COMPLETE Monday-Sunday set rather than the paginated list.
 */
$week_start = $filters['date_from']
    ? date('Y-m-d', strtotime($filters['date_from']))
    : date('Y-m-d', strtotime('monday this week'));

$week_end = $filters['date_to']
    ? date('Y-m-d', strtotime($filters['date_to']))
    : date('Y-m-d', strtotime($week_start . ' +6 days'));

if (strtotime($week_end) < strtotime($week_start)) {
    $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
}

if ((strtotime($week_end) - strtotime($week_start)) > 6 * 86400) {
    $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
}

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime($week_start . " +$i days"));
}

/*
 * For week view, force the selected week into the data query.
 * Date filters remain respected, while pagination is bypassed.
 */
$week_where = $where;
$week_params = $params;

$week_where[] = "t.date BETWEEN ? AND ?";
$week_params[] = $week_start;
$week_params[] = $week_end;

/* --- Pagination for list view --- */
$items_per_page = 50;
$page = (int)($_GET['page'] ?? 1);
$page = max(1, $page);
$offset = ($page - 1) * $items_per_page;

/* --- Count total --- */
$count_sql = "SELECT COUNT(*)
              FROM timetable t
              LEFT JOIN teachers tc
                ON t.teacher_id = tc.id
               AND tc.deleted_at IS NULL
              LEFT JOIN subjects s
                ON t.subject_id = s.id
               AND s.deleted_at IS NULL
              LEFT JOIN student_classes c
                ON t.class_id = c.id
               AND c.deleted_at IS NULL
              LEFT JOIN rooms r
                ON t.room_id = r.id
               AND r.deleted_at IS NULL
              WHERE " . implode(" AND ", $where);

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = (int)$count_stmt->fetchColumn();
$pagination = paginate($total_items, $items_per_page, $page);

/*
 * Shared SELECT.
 * LEFT JOINs prevent a timetable entry from disappearing simply because
 * a related teacher/subject/class/room has been soft-deleted.
 *
 * WhatsApp links come from class_teacher_whatsapp:
 *   1. exact class + teacher + subject
 *   2. wildcard subject_id = 0
 */
$select_sql = "SELECT
                t.*,
                COALESCE(tc.name, 'Teacher') AS teacher_name,
                COALESCE(s.name, 'Subject') AS subject_name,
                COALESCE(c.name, 'Class') AS class_name,
                COALESCE(r.name, 'Room') AS room_name,
                c.id AS class_id,
                COALESCE(
                    (
                        SELECT ctw.whatsapp_link
                        FROM class_teacher_whatsapp ctw
                        WHERE ctw.class_id = t.class_id
                          AND ctw.teacher_id = t.teacher_id
                          AND ctw.subject_id = t.subject_id
                          AND ctw.whatsapp_link IS NOT NULL
                          AND ctw.whatsapp_link <> ''
                        ORDER BY ctw.id DESC
                        LIMIT 1
                    ),
                    (
                        SELECT ctw.whatsapp_link
                        FROM class_teacher_whatsapp ctw
                        WHERE ctw.class_id = t.class_id
                          AND ctw.teacher_id = t.teacher_id
                          AND ctw.subject_id = 0
                          AND ctw.whatsapp_link IS NOT NULL
                          AND ctw.whatsapp_link <> ''
                        ORDER BY ctw.id DESC
                        LIMIT 1
                    )
                ) AS whatsapp_group_link
            FROM timetable t
            LEFT JOIN teachers tc
                ON t.teacher_id = tc.id
               AND tc.deleted_at IS NULL
            LEFT JOIN subjects s
                ON t.subject_id = s.id
               AND s.deleted_at IS NULL
            LEFT JOIN student_classes c
                ON t.class_id = c.id
               AND c.deleted_at IS NULL
            LEFT JOIN rooms r
                ON t.room_id = r.id
               AND r.deleted_at IS NULL
            WHERE ";

if ($filters['view'] === 'week') {
    $week_sql = $select_sql
        . implode(" AND ", $week_where)
        . " ORDER BY t.date ASC, t.start_time ASC, t.id ASC";

    $stmt = $pdo->prepare($week_sql);

    $idx = 1;
    foreach ($week_params as $param) {
        $stmt->bindValue(
            $idx++,
            $param,
            is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    $sql = $select_sql
        . implode(" AND ", $where)
        . " ORDER BY t.date DESC, t.start_time ASC, t.id ASC
           LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);

    $idx = 1;
    foreach ($params as $param) {
        $stmt->bindValue(
            $idx++,
            $param,
            is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        $idx++,
        $items_per_page,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        $idx++,
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
 * FILTER OPTIONS
 * ============================================================ */

$dropdowns_cache_key = 'dropdowns_all';
$dropdowns = $cache->get($dropdowns_cache_key);

if ($dropdowns === null) {

    $teachers = $pdo->query(
        "SELECT id, name
         FROM teachers
         WHERE deleted_at IS NULL
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $rooms = $pdo->query(
        "SELECT id, name
         FROM rooms
         WHERE deleted_at IS NULL
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $classes = $pdo->query(
        "SELECT id, name
         FROM student_classes
         WHERE deleted_at IS NULL
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $subjects = $pdo->query(
        "SELECT id, name
         FROM subjects
         WHERE deleted_at IS NULL
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $dropdowns = [
        'teachers' => $teachers,
        'rooms' => $rooms,
        'classes' => $classes,
        'subjects' => $subjects
    ];

    $cache->set(
        $dropdowns_cache_key,
        $dropdowns,
        3600
    );

} else {

    $teachers = $dropdowns['teachers'] ?? [];
    $rooms = $dropdowns['rooms'] ?? [];
    $classes = $dropdowns['classes'] ?? [];
    $subjects = $dropdowns['subjects'] ?? [];
}


/* ============================================================
 * TEACHER-SPECIFIC FILTER OPTIONS
 * ============================================================
 *
 * Teachers must only see:
 *   - their own timetable
 *   - classes they actually teach
 *   - subjects they actually teach
 *
 * The Teacher filter itself is hidden in the HTML below.
 */

if ($is_teacher) {

    /* No Teacher dropdown for teachers. */
    $teachers = [];

    /* --------------------------------------------------------
     * Classes belonging to this teacher
     * -------------------------------------------------------- */

    $stmt = $pdo->prepare(
        "SELECT DISTINCT
                c.id,
                c.name
         FROM timetable t
         INNER JOIN student_classes c
             ON c.id = t.class_id
            AND c.deleted_at IS NULL
         WHERE t.deleted_at IS NULL
           AND t.teacher_id = ?
         ORDER BY c.name"
    );

    $stmt->execute([
        $teacher_id
    ]);

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* --------------------------------------------------------
     * Subjects belonging to this teacher
     * -------------------------------------------------------- */

    $stmt = $pdo->prepare(
        "SELECT DISTINCT
                s.id,
                s.name
         FROM timetable t
         INNER JOIN subjects s
             ON s.id = t.subject_id
            AND s.deleted_at IS NULL
         WHERE t.deleted_at IS NULL
           AND t.teacher_id = ?
         ORDER BY s.name"
    );

    $stmt->execute([
        $teacher_id
    ]);

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* --- Subject colour mapping --- */
$color_palette = [
    '#4A6CF7',
    '#10B981',
    '#F59E0B',
    '#EF4444',
    '#8B5CF6',
    '#EC4899',
    '#14B8A6',
    '#F97316',
    '#6366F1',
    '#84CC16'
];

$subject_color_map = [];
$color_index = 0;

foreach ($entries as $e) {
    $subject_name = $e['subject_name'] ?? 'Subject';

    if (!isset($subject_color_map[$subject_name])) {
        $subject_color_map[$subject_name] =
            $color_palette[
                $color_index % count($color_palette)
            ];

        $color_index++;
    }
}

/* ============================================================
 * SUMMARY STATISTICS
 * ============================================================
 *
 * Pending means:
 *   - payment_status = pending
 *   - lesson date is today or earlier
 *
 * Future unpaid lessons are NOT counted.
 *
 * For teachers, only their own lessons are counted.
 * Admin sees all pending lessons.
 */

$total_revenue = 0;
$paid_count = 0;

foreach ($entries as $e) {
    $rev = (int)$e['student_count'] * $FEE_PER_STUDENT_LIVE;
    $total_revenue += $rev;

    if ($e['payment_status'] === 'paid') {
        $paid_count++;
    }
}


$pending_sql = "
    SELECT COUNT(*)
    FROM timetable
    WHERE deleted_at IS NULL
      AND payment_status = 'pending'
      AND date <= CURDATE()
";

$pending_params = [];

if ($is_teacher) {
    $pending_sql .= " AND teacher_id = ?";
    $pending_params[] = $teacher_id;
}

$pending_stmt = $pdo->prepare($pending_sql);
$pending_stmt->execute($pending_params);

$pending_count = (int)$pending_stmt->fetchColumn();


// --- Include header ---
include __DIR__ . '/../includes/header.php';
?>

<div class="timetable-page <?= $filters['compact'] ? 'compact' : '' ?>">
    <div class="tt-page-heading">
        <div class="heading-copy">
            <h1><i class="bi bi-calendar3"></i> Timetable</h1>
            <p>Manage lessons, rooms, teachers, student counts and payments from one place.</p>
        </div>
        <div class="heading-actions">
            <?php if ($is_admin || $teacher_id): ?>
                <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Lesson</a>
            <?php endif; ?>
            <a href="index.php?view=week" class="btn btn-outline-secondary"><i class="bi bi-calendar-week"></i> Weekly View</a>
        </div>
    </div>
    <div id="timetableRefreshArea">
    <!-- ===== SUMMARY STATS ===== -->
    <?php
    // Summary values are calculated above.
    ?>
    <div class="summary-stats">
        <div class="summary-stat">
            <div class="number"><?= count($entries) ?></div>
            <div class="label">Classes</div>
        </div>
        <div class="summary-stat">
            <div class="number">Rs <?= number_format($total_revenue) ?></div>
            <div class="label">Total Revenue</div>
        </div>
        <div class="summary-stat">
            <div class="number"><?= $pending_count ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="summary-stat">
            <div class="number"><?= $paid_count ?></div>
            <div class="label">Paid</div>
        </div>
        <div class="summary-stat">
            <div class="number"><?= $total_items ?></div>
            <div class="label">Total Entries</div>
        </div>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <div class="filter-bar">
        <form method="GET" id="filterForm" class="filter-group">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="view" value="<?= $filters['view'] ?>">
            <input type="hidden" name="compact" value="<?= $filters['compact'] ? 1 : 0 ?>">

            <?php if ($is_admin): ?>
            <div class="filter-item">
                <label class="form-label">Teacher</label>
                <select name="teacher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= ($filters['teacher'] == $t['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-item" id="room-filter-container">
                <label class="form-label">Room</label>
                <select name="room" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($filters['room'] == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label class="form-label">Class</label>
                <select name="class" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filters['class'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label class="form-label">Subject</label>
                <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($filters['subject'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label class="form-label">Day</label>
                <select name="day" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="Monday" <?= ($filters['day']=='Monday')?'selected':'' ?>>Monday</option>
                    <option value="Tuesday" <?= ($filters['day']=='Tuesday')?'selected':'' ?>>Tuesday</option>
                    <option value="Wednesday" <?= ($filters['day']=='Wednesday')?'selected':'' ?>>Wednesday</option>
                    <option value="Thursday" <?= ($filters['day']=='Thursday')?'selected':'' ?>>Thursday</option>
                    <option value="Friday" <?= ($filters['day']=='Friday')?'selected':'' ?>>Friday</option>
                    <option value="Saturday" <?= ($filters['day']=='Saturday')?'selected':'' ?>>Saturday</option>
                    <option value="Sunday" <?= ($filters['day']=='Sunday')?'selected':'' ?>>Sunday</option>
                </select>
            </div>
            <div class="filter-item" style="min-width:120px;">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $filters['date_from'] ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-item" style="min-width:120px;">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $filters['date_to'] ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-item">
                <label class="form-label">Payment</label>
                <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($filters['payment_status']=='all')?'selected':'' ?>>All</option>
                    <option value="pending" <?= ($filters['payment_status']=='pending')?'selected':'' ?>>Pending</option>
                    <option value="paid" <?= ($filters['payment_status']=='paid')?'selected':'' ?>>Paid</option>
                </select>
            </div>
            <div class="filter-actions">
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                <div class="toggle-view">
                    <button type="button" class="btn <?= ($filters['view']=='list')?'active':'' ?>" onclick="setView('list')"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="btn <?= ($filters['view']=='week')?'active':'' ?>" onclick="setView('week')"><i class="bi bi-calendar-week"></i></button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleCompact()"><i class="bi <?= $filters['compact'] ? 'bi-arrows-expand' : 'bi-arrows-collapse' ?>"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportCSV()"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </form>
    </div>

    <div class="tt-toolbar">
        <div class="result-count"><i class="bi bi-list-check"></i> Showing <strong><?= count($entries) ?></strong> of <?= (int)$total_items ?> timetable entries</div>
        <div class="toolbar-actions">
            <a href="payments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-credit-card"></i> Payments</a>
            <a href="export_csv.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
        </div>
    </div>

    <!-- ===== BULK ACTIONS BAR (Admin only) ===== -->
    <?php if ($is_admin && count($entries) > 0): ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Bulk Action</label>
            <select id="bulkAction" class="form-select form-select-sm">
                <option value="">-- Select --</option>
                <option value="mark_paid">Mark as Paid</option>
                <option value="delete">Delete</option>
                <option value="lock">Lock</option>
                <option value="unlock">Unlock</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="button" class="btn btn-primary btn-sm" onclick="bulkAction()"><i class="bi bi-play-fill"></i> Apply</button>
        </div>
        <div class="col-md-7 text-end d-flex align-items-end justify-content-end gap-3">
            <div class="form-check mb-0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="selectAllEntries"
                    onchange="toggleSelectAllEntries(this)"
                >
                <label class="form-check-label" for="selectAllEntries">
                    Select all
                </label>
            </div>
            <span class="text-muted"><?= count($entries) ?> entries shown</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== CONTENT ===== -->
    <?php if (count($entries) === 0): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-calendar-x" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
            <h5>No entries found</h5>
            <p class="text-muted">Try adjusting your filters or add a new class.</p>
        </div>
    <?php else: ?>
        <?php if ($filters['view'] == 'list'): ?>
            <!-- ===== LIST VIEW (Card-based) ===== -->
            <div class="timetable-cards" id="timetableList">
                <?php foreach ($entries as $e):
                    $rev = $e['student_count'] * $FEE_PER_STUDENT_LIVE;
                    $can_edit = ($is_admin || ($teacher_id && $e['teacher_id'] == $teacher_id)) && !$e['is_locked'];
                    $conflict = false;
                    $color = $subject_color_map[$e['subject_name']] ?? '#4A6CF7';
                    $card_class = $conflict ? 'card-entry conflict-highlight' : 'card-entry';
                    $is_today = ($e['date'] == date('Y-m-d'));
                ?>
                 <div class="<?= $card_class ?>" data-id="<?= $e['id'] ?>">

    <!-- SUBJECT COLOUR -->
    <div
        class="accent-bar"
        style="background: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;"
    ></div>

    <div class="card-content">
                    <!-- BULK SELECT -->
        <div class="bulk-select-control deletebtn">
            <input
                type="checkbox"
                class="form-check-input timetable-select"
                name="selected_ids[]"
                value="<?= (int)$e['id'] ?>"
                aria-label="Select lesson <?= (int)$e['id'] ?>"
                onchange="updateBulkSelectionState()"
            >
        </div>

        <!-- ==================================================
             TOP ROW
        =================================================== -->

        <div class="card-top">

            <div class="day-date">
                <span class="day-name <?= $is_today ? 'today' : '' ?>">
                    <?= date('D', strtotime($e['date'])) ?>
                </span>

                <span class="date-small">
                    <?= date('j M', strtotime($e['date'])) ?>
                </span>
                
       
            </div>
                     

            <div class="time-range">

                <span class="start-time">
                    <?= date('g:i A', strtotime($e['start_time'])) ?>
                </span>

                <span class="end-time">
                    – <?= date('g:i A', strtotime($e['end_time'])) ?>
                </span>

            </div>

        </div>


        <!-- ==================================================
             MAIN LESSON INFORMATION
        =================================================== -->

        <div class="lesson-main">

            <div
                class="class-name"
                style="--lesson-color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;"
            >
                <i class="bi bi-book"></i>
                <?= htmlspecialchars($e['subject_name']) ?>
            </div>

            <div class="subject-name">

                <i class="bi bi-layers"></i>

                <span class="lesson-class">
                    <?= htmlspecialchars($e['class_name']) ?>
                </span>

            </div>

            <div class="lesson-teacher">

                <i class="bi bi-person"></i>

                <span>
                    <?= htmlspecialchars($e['teacher_name']) ?>
                </span>

            </div>

        </div>


        <!-- ==================================================
             INFORMATION PILLS
        =================================================== -->

        <div class="meta-section">

            <span class="pill room-pill">
                <i class="bi bi-door-open"></i>
                <span><?= htmlspecialchars($e['room_name']) ?></span>
            </span>

            <span class="pill students-pill">
                <i class="bi bi-people"></i>
                <span><?= (int)$e['student_count'] ?> Students</span>
            </span>

            <span class="pill revenue-pill">
                <span>Rs <?= number_format($rev) ?></span>
            </span>

            <span class="status-badge <?= htmlspecialchars($e['payment_status']) ?>">
                <?= ucfirst(htmlspecialchars($e['payment_status'])) ?>
            </span>

            <?php if ($e['is_locked']): ?>

                <span class="pill locked-pill">
                    <i class="bi bi-lock"></i>
                    <span>Locked</span>
                </span>

            <?php endif; ?>

            <?php if (strtotime($e['date']) < time()): ?>

                <span class="pill past-pill">
                    Past
                </span>

            <?php endif; ?>

        </div>


        <!-- ==================================================
             ACTIONS
        =================================================== -->

        <div class="actions-section">

            <div class="action-buttons">

                <?php if ($can_edit): ?>

                    <button
                        type="button"
                        class="card-action edit-action"
                        title="Edit lesson"
                        onclick="openEditModal(<?= (int)$e['id'] ?>)"
                    >
                        <i class="bi bi-pencil"></i>
                        <span>Edit</span>
                    </button>

                    <button
                        type="button"
                        class="card-action"
                        onclick="quickEdit(
                            <?= $e['id'] ?>,
                            <?= $e['student_count'] ?>,
                            this
                        )"
                        title="Update student count"
                    >
                        <i class="bi bi-person-plus"></i>
                        <span>Students</span>
                    </button>

                    <button
                        type="button"
                        class="card-action"
                        onclick="cloneEntry(<?= $e['id'] ?>)"
                        title="Clone lesson"
                    >
                        <i class="bi bi-copy"></i>
                        <span>Clone</span>
                    </button>

                <?php endif; ?>


                <?php if ($is_admin): ?>

                    <?php if (!$e['is_locked']): ?>

                        <form method="POST" class="card-action-form">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $e['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="toggle_lock"
                                value="1"
                            >

<button
    type="button"
    class="card-action"
    onclick="toggleLockAjax(
        <?= (int)$e['id'] ?>,
        this
    )"
    title="Lock"
>
    <i class="bi bi-lock"></i>
    <span>Lock</span>
</button>

                        </form>

                    <?php else: ?>

                        <form method="POST" class="card-action-form">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $e['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="toggle_lock"
                                value="1"
                            >

<button
    type="button"
    class="card-action"
    onclick="toggleLockAjax(
        <?= (int)$e['id'] ?>,
        this
    )"
    title="Unlock"
>
    <i class="bi bi-unlock"></i>
    <span>Unlock</span>
</button>

                        </form>

                    <?php endif; ?>


                    <?php if ($e['payment_status'] === 'pending'): ?>

                        <button
                            type="button"
                            class="card-action paid-action"
                            title="Mark as paid"
                            onclick="markPaidAjax(<?= (int)$e['id'] ?>, this)"
                        >
                            <i class="bi bi-check-circle"></i>
                            <span>Paid</span>
                        </button>

                    <?php endif; ?>


                    <?php if ($can_edit): ?>

                        <button
                            type="button"
                            class="card-action delete-action"
                            title="Delete"
                            onclick="deleteTimetableEntry(<?= (int)$e['id'] ?>, this)"
                        >
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </button>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- ===== WEEK VIEW ===== -->
            <div class="timetable-week-list">

                <?php
                $week_entries = [];

                foreach ($entries as $entry) {
                    $entry_date = $entry['date'];

                    if (!isset($week_entries[$entry_date])) {
                        $week_entries[$entry_date] = [];
                    }

                    $week_entries[$entry_date][] = $entry;
                }
                ?>

                <?php foreach ($days as $day_date): ?>

                    <?php
                    $day_entries = $week_entries[$day_date] ?? [];

                    usort(
                        $day_entries,
                        static function ($a, $b) {
                            $timeCompare = strcmp(
                                (string)$a['start_time'],
                                (string)$b['start_time']
                            );

                            if ($timeCompare !== 0) {
                                return $timeCompare;
                            }

                            return ((int)$a['id']) <=> ((int)$b['id']);
                        }
                    );

                    $day_class_count = count($day_entries);
                    $is_today = $day_date === date('Y-m-d');
                    ?>

                    <section
                        class="timetable-day-section <?= $is_today ? 'is-today' : '' ?>"
                        data-date="<?= htmlspecialchars($day_date, ENT_QUOTES, 'UTF-8') ?>"
                    >

                        <div class="timetable-day-header">

                            <div class="timetable-day-title">

                                <span class="day-name">
                                    <?= htmlspecialchars(
                                        date('l', strtotime($day_date)),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <span class="day-date">
                                    <?= htmlspecialchars(
                                        date('d M', strtotime($day_date)),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <?php if ($is_today): ?>
                                    <span class="today-badge">Today</span>
                                <?php endif; ?>

                            </div>

                            <div class="day-class-count">
                                <?= $day_class_count ?>
                                <?= $day_class_count === 1 ? 'class' : 'classes' ?>
                            </div>

                        </div>

                        <?php if ($day_class_count > 0): ?>

                            <div class="timetable-day-lessons">

                                <?php foreach ($day_entries as $e): ?>

                                    <?php
                                    $subject_name = $e['subject_name'] ?: 'Subject';
                                    $teacher_name = $e['teacher_name'] ?: 'Teacher';
                                    $room_name = $e['room_name'] ?: 'Room';
                                    $class_name = $e['class_name'] ?: '';

                                    $color =
                                        $subject_color_map[$subject_name]
                                        ?? '#4A6CF7';

                                    $start_timestamp = strtotime($e['start_time']);
                                    $end_timestamp = strtotime($e['end_time']);

                                    $start_time = date('g:i A', $start_timestamp);
                                    $end_time = date('g:i A', $end_timestamp);

                                    $group_link = trim(
                                        (string)($e['whatsapp_group_link'] ?? '')
                                    );

                                    $can_edit =
                                        (
                                            $is_admin ||
                                            (
                                                $teacher_id &&
                                                (int)$e['teacher_id'] === (int)$teacher_id
                                            )
                                        ) &&
                                        !$e['is_locked'];
                                    ?>

                                    <article
                                        class="week-lesson-card"
                                        data-id="<?= (int)$e['id'] ?>"
                                    >

                                        <!-- BULK SELECT -->
                                        <div class="week-bulk-select">
                                            <input
                                                type="checkbox"
                                                class="form-check-input timetable-select"
                                                name="selected_ids[]"
                                                value="<?= (int)$e['id'] ?>"
                                                aria-label="Select lesson <?= (int)$e['id'] ?>"
                                                onchange="updateBulkSelectionState()"
                                            >
                                        </div>

                                        <span
                                            class="week-lesson-accent"
                                            style="background: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;"
                                        ></span>

                                        <div class="week-lesson-time">

                                            <strong>
                                                <?= htmlspecialchars($start_time, ENT_QUOTES, 'UTF-8') ?>
                                            </strong>

                                            <span>
                                                – <?= htmlspecialchars($end_time, ENT_QUOTES, 'UTF-8') ?>
                                            </span>

                                            <div
                                                class="week-lesson-line"
                                                style="background: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;"
                                            ></div>

                                        </div>

                                        <div class="week-lesson-info">

                                            <div
                                                class="week-lesson-subject"
                                                style="color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;"
                                            >
                                                <?= htmlspecialchars($class_name ?: $subject_name, ENT_QUOTES, 'UTF-8') ?>
                                            </div>

                                            <?php if ($class_name): ?>
                                                <div class="week-lesson-title">
                                                    <?= htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="week-lesson-meta">
                                                <i class="bi bi-book"></i>
                                                <?= htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8') ?>
                                            </div>

                                        </div>

                                        <div class="week-lesson-actions">

                                            <span class="teacher-pill">
                                                <i class="bi bi-whatsapp"></i>
                                                <?= htmlspecialchars($teacher_name, ENT_QUOTES, 'UTF-8') ?>
                                            </span>

                                            <span class="room-pill">
                                                <i class="bi bi-door-open"></i>
                                                <?= htmlspecialchars($room_name, ENT_QUOTES, 'UTF-8') ?>
                                            </span>

                                            <?php if ($group_link): ?>
                                                <a
                                                    href="<?= htmlspecialchars($group_link, ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="join-group-btn"
                                                >
                                                    <i class="bi bi-whatsapp"></i>
                                                    Join Group
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($can_edit): ?>
                                                <button
                                                    type="button"
                                                    class="week-lesson-action-btn"
                                                    title="Edit lesson"
                                                    onclick="openEditModal(<?= (int)$e['id'] ?>)"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($is_admin): ?>

                                                <?php if (!$e['is_locked']): ?>
                                                    <form method="POST" class="week-inline-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                                        <input type="hidden" name="toggle_lock" value="1">
                                                        <button
                                                            type="button"
                                                            class="week-lesson-action-btn"
                                                            title="Lock lesson"
                                                            onclick="confirmAction(this.form, 'Lock this lesson?')"
                                                        >
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="week-inline-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                                        <input type="hidden" name="toggle_lock" value="1">
                                                        <button
                                                            type="button"
                                                            class="week-lesson-action-btn"
                                                            title="Unlock lesson"
                                                            onclick="confirmAction(this.form, 'Unlock this lesson?')"
                                                        >
                                                            <i class="bi bi-unlock"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </div>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="timetable-day-empty">
                                <i class="bi bi-calendar-x"></i>
                                <span>No classes scheduled</span>
                            </div>

                        <?php endif; ?>

                    </section>

                <?php endforeach; ?>

            </div>

            <div class="week-range-note">
                <i class="bi bi-calendar-week"></i>
                Showing Monday <?= date('d M Y', strtotime($days[0])) ?>
                to Sunday <?= date('d M Y', strtotime($days[6])) ?>
            </div>
        <?php endif; ?>

        <?php if ($filters['view'] === 'list'): ?>
            <!-- ===== PAGINATION ===== -->
            <?= render_pagination($pagination, $_GET) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</div>


<!-- ===== AJAX EDIT LESSON MODAL ===== -->
<div class="modal fade" id="ajaxEditModal" tabindex="-1" aria-labelledby="ajaxEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ajaxEditModalLabel"><i class="bi bi-pencil-square"></i> Edit Lesson</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="ajaxEditForm">
                <div class="modal-body">
                    <div id="ajaxEditError" class="alert alert-danger d-none"></div>
                    <div id="ajaxEditLoading" class="text-center py-4">
                        <div class="spinner-border" role="status"></div>
                        <div class="mt-2 text-muted">Loading lesson...</div>
                    </div>
                    <div id="ajaxEditFields" class="d-none">
                        <input type="hidden" name="id" id="ajaxEditId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Teacher</label>
                                <select class="form-select" name="teacher_id" id="ajaxEditTeacher" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <select class="form-select" name="subject_id" id="ajaxEditSubject" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <select class="form-select" name="class_id" id="ajaxEditClass" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Room</label>
                                <select class="form-select" name="room_id" id="ajaxEditRoom" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" id="ajaxEditDate" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" id="ajaxEditStart" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="ajaxEditEnd" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Students</label>
                                <input type="number" min="0" class="form-control" name="student_count" id="ajaxEditStudents" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Status</label>
                                <select class="form-select" name="payment_status" id="ajaxEditPayment">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="repeat_weekly" value="1" id="ajaxEditRepeat">
                                    <label class="form-check-label" for="ajaxEditRepeat">Repeat weekly</label>
                                </div>
                            </div>
                            <div class="col-md-6" id="ajaxEditRepeatUntilWrap" style="display:none;">
                                <label class="form-label">Repeat Until</label>
                                <input type="date" class="form-control" name="repeat_until" id="ajaxEditRepeatUntil">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ajaxEditSaveBtn"><i class="bi bi-check-lg"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== QUICK EDIT STUDENT COUNT MODAL ===== -->
<div class="modal fade" id="quickEditModal" tabindex="-1" aria-labelledby="quickEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickEditModalLabel">Update Student Count</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="quickEditInput" class="form-label fw-bold">Enter new student count:</label>
                    <input type="number" id="quickEditInput" class="form-control form-control-lg" placeholder="0" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quickEditSaveBtn"><i class="bi bi-check-circle"></i> Update</button>
            </div>
        </div>
    </div>
</div>

<style>
    .bulk-select-control {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 5;
    }

    .card-entry {
        position: relative;
    }

    .bulk-select-control .timetable-select,
    .week-bulk-select .timetable-select {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .week-lesson-card {
        position: relative;
    }

    .week-bulk-select {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 5;
    }

    .week-bulk-select + .week-lesson-accent {
        /* keep the existing accent bar layout intact */
    }

    .bulk-select-control ~ .card-top,
    .bulk-select-control ~ .lesson-main {
        /* selection control is positioned and does not alter the card layout */
    }
</style>

<script>
    async function toggleLockAjax(id, button) {

    const action =
        button
            .querySelector('i')
            ?.classList
            .contains('bi-lock');

    const message = action
        ? 'Lock this lesson?'
        : 'Unlock this lesson?';

    if (!confirm(message)) {
        return;
    }

    const csrf =
        document.querySelector(
            'input[name="csrf_token"]'
        )?.value || '';

    const originalHTML =
        button.innerHTML;

    button.disabled = true;

    button.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span>';


    try {

        const formData =
            new FormData();

        formData.append(
            'entry_id',
            id
        );

        formData.append(
            'csrf_token',
            csrf
        );


        const response =
            await fetch(
                '../ajax/toggle_lock.php',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }
            );


        const data =
            await response.json();


        if (!data.success) {

            throw new Error(
                data.error ||
                'Unable to update lock status.'
            );
        }


        /*
         * Refresh only timetable content.
         *
         * Filters and current URL remain unchanged.
         */
        if (
            typeof refreshTimetable ===
            'function'
        ) {

            await refreshTimetable();

        } else {

            /*
             * Fallback:
             * restore button if the timetable
             * refresh function is unavailable.
             */
            button.innerHTML =
                originalHTML;
        }


    } catch (error) {

        alert(
            error.message ||
            'Unable to update lock status.'
        );

        button.innerHTML =
            originalHTML;

    } finally {

        button.disabled = false;
    }
}
// ===== Helper to get CSRF token from meta tag =====
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// ===== Custom Confirm Action =====
function confirmAction(form, message) {
    if (typeof customConfirm === 'function') {
        customConfirm('Confirm', message, function(confirmed) {
            if (confirmed) { form.submit(); }
        });
    } else {
        if (confirm(message)) { form.submit(); }
    }
}

// ===== Toggle view =====
function setView(view) {
    const form = document.getElementById('filterForm');
    const input = form.querySelector('input[name="view"]');
    input.value = view;
    form.submit();
}

// ===== Toggle compact =====
function toggleCompact() {
    const form = document.getElementById('filterForm');
    const input = form.querySelector('input[name="compact"]');
    input.value = input.value == '1' ? '0' : '1';
    form.submit();
}

// ===== Quick edit (student count) - MODAL VERSION =====
let quickEditTargetId = null;
let quickEditTargetButton = null;

function quickEdit(id, currentCount, btn) {
    quickEditTargetId = id;
    quickEditTargetButton = btn;
    const input = document.getElementById('quickEditInput');
    input.value = currentCount;
    input.focus();
    input.select();
    const modal = new bootstrap.Modal(document.getElementById('quickEditModal'));
    modal.show();
}

function saveQuickEdit() {
    const id = quickEditTargetId;
    const btn = quickEditTargetButton;
    const input = document.getElementById('quickEditInput');
    const newCount = input.value.trim();
    if (newCount === '' || isNaN(newCount) || parseInt(newCount) < 0) {
        alert('Please enter a valid positive number.');
        return;
    }
    const modalEl = document.getElementById('quickEditModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
    const csrf = getCsrfToken();
    if (!csrf) {
        alert('CSRF token not found. Please refresh the page.');
        return;
    }
    fetch('../ajax/update_students.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&student_count=' + newCount + '&csrf_token=' + encodeURIComponent(csrf)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const card = btn.closest('.card-entry');
            const countPill = card.querySelector('.pill i.bi-people')?.closest('.pill');
            if (countPill) {
                countPill.innerHTML = '<i class="bi bi-people"></i> ' + newCount;
            }
            refreshTimetable();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => alert('Request failed: ' + err.message));
}

document.addEventListener('DOMContentLoaded', function() {
    const quickEditInput = document.getElementById('quickEditInput');
    const quickEditSaveBtn = document.getElementById('quickEditSaveBtn');
    if (quickEditInput && quickEditSaveBtn) {
        quickEditInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                quickEditSaveBtn.click();
            }
        });
        quickEditSaveBtn.addEventListener('click', saveQuickEdit);
    }
});

// ===== AJAX timetable refresh =====
let ajaxEditModal = null;

function showAjaxError(message) {
    const box = document.getElementById('ajaxEditError');
    if (!box) return;
    box.textContent = message || 'Something went wrong.';
    box.classList.remove('d-none');
}

function clearAjaxError() {
    const box = document.getElementById('ajaxEditError');
    if (!box) return;
    box.textContent = '';
    box.classList.add('d-none');
}

function setSelectOptions(selectId, rows, selected) {
    const select = document.getElementById(selectId);
    if (!select) return;
    select.innerHTML = '';
    rows.forEach(function(row) {
        const option = document.createElement('option');
        option.value = row.id;
        option.textContent = row.name;
        if (String(row.id) === String(selected)) option.selected = true;
        select.appendChild(option);
    });
}

function openEditModal(id) {
    const modalEl = document.getElementById('ajaxEditModal');
    if (!modalEl) return;

    if (!ajaxEditModal) ajaxEditModal = new bootstrap.Modal(modalEl);

    clearAjaxError();
    document.getElementById('ajaxEditLoading').classList.remove('d-none');
    document.getElementById('ajaxEditFields').classList.add('d-none');
    document.getElementById('ajaxEditSaveBtn').disabled = true;
    ajaxEditModal.show();

    fetch('../ajax/get_entry.php?id=' + encodeURIComponent(id), {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.error || 'Unable to load lesson.');

        const e = data.entry;

        if (Number(e.is_locked) === 1) {
            throw new Error(
                'This lesson is locked and cannot be edited.'
            );
        }

        document.getElementById('ajaxEditId').value = e.id;
        setSelectOptions('ajaxEditTeacher', data.teachers || [], e.teacher_id);
        setSelectOptions('ajaxEditSubject', data.subjects || [], e.subject_id);
        setSelectOptions('ajaxEditClass', data.classes || [], e.class_id);
        setSelectOptions('ajaxEditRoom', data.rooms || [], e.room_id);
        document.getElementById('ajaxEditDate').value = e.date;
        document.getElementById('ajaxEditStart').value = String(e.start_time).slice(0,5);
        document.getElementById('ajaxEditEnd').value = String(e.end_time).slice(0,5);
        document.getElementById('ajaxEditStudents').value = e.student_count;
        document.getElementById('ajaxEditPayment').value = e.payment_status || 'pending';
        document.getElementById('ajaxEditPayment').disabled = !data.is_admin;
        document.getElementById('ajaxEditTeacher').disabled = !data.is_admin;
        document.getElementById('ajaxEditRepeat').checked = !!data.has_recurring;

        const repeatUntil =
            document.getElementById('ajaxEditRepeatUntil');

        const editDate =
            document.getElementById('ajaxEditDate');

        repeatUntil.value = data.recurring_end_date || '';
        repeatUntil.min = editDate.value || '';
        repeatUntil.required = !!data.has_recurring;

        document.getElementById('ajaxEditRepeatUntilWrap').style.display =
            data.has_recurring ? '' : 'none';

        document.getElementById('ajaxEditLoading').classList.add('d-none');
        document.getElementById('ajaxEditFields').classList.remove('d-none');
        document.getElementById('ajaxEditSaveBtn').disabled = false;
    })
    .catch(err => {
        document.getElementById('ajaxEditLoading').classList.add('d-none');
        showAjaxError(err.message);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const repeat = document.getElementById('ajaxEditRepeat');
    if (repeat) {
        repeat.addEventListener('change', function() {
            const wrap = document.getElementById('ajaxEditRepeatUntilWrap');
            const until = document.getElementById('ajaxEditRepeatUntil');
            const date = document.getElementById('ajaxEditDate');

            wrap.style.display = this.checked ? '' : 'none';

            if (this.checked) {
                until.required = true;

                if (date.value) {
                    until.min = date.value;
                }
            } else {
                until.required = false;
            }
        });
    }

    const editDate = document.getElementById('ajaxEditDate');
    const editRepeatUntil = document.getElementById('ajaxEditRepeatUntil');

    if (editDate && editRepeatUntil) {
        editDate.addEventListener('change', function() {
            editRepeatUntil.min = this.value;

            if (
                editRepeatUntil.value &&
                this.value &&
                editRepeatUntil.value < this.value
            ) {
                editRepeatUntil.value = this.value;
            }
        });
    }

    const form = document.getElementById('ajaxEditForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            clearAjaxError();

            const repeatCheckbox =
                document.getElementById('ajaxEditRepeat');

            const date =
                document.getElementById('ajaxEditDate');

            const repeatUntil =
                document.getElementById('ajaxEditRepeatUntil');

            if (
                repeatCheckbox &&
                repeatCheckbox.checked
            ) {
                if (!repeatUntil.value) {
                    showAjaxError(
                        'Please select a Repeat Until date.'
                    );
                    repeatUntil.focus();
                    return;
                }

                if (
                    date.value &&
                    repeatUntil.value < date.value
                ) {
                    showAjaxError(
                        'Repeat Until cannot be before the lesson date.'
                    );
                    repeatUntil.focus();
                    return;
                }
            }

            const btn = document.getElementById('ajaxEditSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

            const fd = new FormData(form);
            fd.append('csrf_token', getCsrfToken());

            fetch('../ajax/update_entry.php', {
                method: 'POST',
                body: fd,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Unable to update lesson.');
                if (ajaxEditModal) ajaxEditModal.hide();
                refreshTimetable();
            })
            .catch(err => showAjaxError(err.message))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Save Changes';
            });
        });
    }
});

function markPaidAjax(id, button) {
    const proceed = function(confirmed) {
        if (!confirmed) return;

        const csrf = getCsrfToken();

        if (!csrf) {
            alert('CSRF token not found. Please refresh the page.');
            return;
        }

        if (button) {
            button.disabled = true;
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML =
                '<span class="spinner-border spinner-border-sm"></span><span>Saving...</span>';
        }

        const body = new URLSearchParams();
        body.set('entry_id', id);
        body.set('csrf_token', csrf);

        fetch('../ajax/mark_paid.php', {
            method: 'POST',
            body: body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            let data;

            try {
                data = await response.json();
            } catch (e) {
                throw new Error('Invalid response from server.');
            }

            if (!response.ok || !data.success) {
                throw new Error(
                    data.error || 'Unable to mark payment as paid.'
                );
            }

            return data;
        })
        .then(data => {
            /*
             * Refresh only the timetable content.
             * The current URL, including all filters, is preserved.
             */
            return refreshTimetable().then(() => {
                if (data.notification_sent === false) {
                    console.warn(
                        data.notification_message ||
                        'Payment was updated, but the WhatsApp notification was not sent.'
                    );
                }
            });
        })
        .catch(err => {
            if (button) {
                button.disabled = false;
                button.innerHTML =
                    button.dataset.originalHtml ||
                    '<i class="bi bi-check-circle"></i><span>Paid</span>';
            }

            alert(err.message || 'Unable to mark payment as paid.');
        });
    };

    if (typeof customConfirm === 'function') {
        customConfirm(
            'Mark Payment as Paid',
            'Mark this lesson payment as paid?',
            proceed
        );
    } else {
        proceed(
            confirm('Mark this lesson payment as paid?')
        );
    }
}


function deleteTimetableEntry(id, button) {
    const proceed = function(confirmed) {
        if (!confirmed) return;
        const csrf = getCsrfToken();
        if (!csrf) return alert('CSRF token not found. Please refresh the page.');

        if (button) {
            button.disabled = true;
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        const body = new URLSearchParams();
        body.set('id', id);
        body.set('csrf_token', csrf);

        fetch('../ajax/delete_entry.php', {
            method: 'POST',
            body: body,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Delete failed.');
            const card = document.querySelector('.card-entry[data-id="' + id + '"]');
            if (card) {
                card.style.transition = 'opacity .2s ease, transform .2s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(.98)';
            }
            setTimeout(refreshTimetable, 220);
        })
        .catch(err => {
            if (button) {
                button.disabled = false;
                button.innerHTML = button.dataset.originalHtml || '<i class="bi bi-trash"></i><span>Delete</span>';
            }
            alert(err.message);
        });
    };

    if (typeof customConfirm === 'function') {
        customConfirm('Delete Lesson', 'Delete this lesson? This will soft-delete the timetable entry.', proceed);
    } else {
        proceed(confirm('Delete this lesson? This will soft-delete the timetable entry.'));
    }
}

async function refreshTimetable() {
    const area = document.getElementById('timetableRefreshArea');
    if (!area) return window.location.reload();

    area.style.opacity = '.65';
    area.style.pointerEvents = 'none';

    try {
        const response = await fetch(window.location.href, {
            method: 'GET',
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}
        });
        if (!response.ok) throw new Error('Unable to refresh timetable.');
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const fresh = doc.getElementById('timetableRefreshArea');
        if (!fresh) throw new Error('Timetable content was not found.');
        area.replaceWith(fresh);
        window.scrollTo({top: window.scrollY, behavior: 'instant'});
    } catch (err) {
        console.error(err);
        alert(err.message || 'Unable to refresh timetable.');
    } finally {
        const freshArea = document.getElementById('timetableRefreshArea');
        if (freshArea) {
            freshArea.style.opacity = '';
            freshArea.style.pointerEvents = '';
        }
    }
}

// ===== Clone entry =====
function cloneEntry(id) {
    if (!confirm('Clone this entry?')) return;
    const csrf = getCsrfToken();
    if (!csrf) {
        alert('CSRF token not found.');
        return;
    }
    fetch('../ajax/clone_entry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&csrf_token=' + encodeURIComponent(csrf)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Entry cloned successfully!');
            refreshTimetable();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => alert('Request failed: ' + err));
}

// ===== Bulk selection =====
function updateBulkSelectionState() {
    const checkboxes = Array.from(
        document.querySelectorAll('input[name="selected_ids[]"]')
    );

    const checked = checkboxes.filter(cb => cb.checked);
    const selectAll = document.getElementById('selectAllEntries');

    if (selectAll) {
        selectAll.checked =
            checkboxes.length > 0 &&
            checked.length === checkboxes.length;

        selectAll.indeterminate =
            checked.length > 0 &&
            checked.length < checkboxes.length;
    }
}

function toggleSelectAllEntries(master) {
    const checkboxes = document.querySelectorAll(
        'input[name="selected_ids[]"]'
    );

    checkboxes.forEach(function (checkbox) {
        checkbox.checked = master.checked;
    });

    updateBulkSelectionState();
}

// ===== Bulk actions =====
function bulkAction() {
    const actionElement = document.getElementById('bulkAction');
    const action = actionElement ? actionElement.value : '';

    if (!action) {
        alert('Select an action.');
        return;
    }

    const selected = Array.from(
        document.querySelectorAll(
            'input[name="selected_ids[]"]:checked'
        )
    );

    if (selected.length === 0) {
        alert('Select at least one entry.');
        return;
    }

    const ids = selected.map(function (checkbox) {
        return checkbox.value;
    });

    const actionLabels = {
        mark_paid: 'Mark as Paid',
        delete: 'Delete',
        lock: 'Lock',
        unlock: 'Unlock'
    };

    const actionLabel =
        actionLabels[action] || action;

    const proceed = function (confirmed) {
        if (!confirmed) {
            return;
        }

        const csrf = getCsrfToken();

        if (!csrf) {
            alert(
                'CSRF token not found. Please refresh the page.'
            );
            return;
        }

        const applyButton =
            document.querySelector(
                '[onclick="bulkAction()"]'
            );

        if (applyButton) {
            applyButton.disabled = true;
            applyButton.dataset.originalHtml =
                applyButton.innerHTML;

            applyButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span> Applying...';
        }

        const body = new URLSearchParams();

        body.set('action', action);
        body.set('ids', JSON.stringify(ids));
        body.set('csrf_token', csrf);

        fetch('../ajax/bulk_action.php', {
            method: 'POST',
            body: body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async function (response) {

            let data;

            try {
                data = await response.json();
            } catch (error) {
                throw new Error(
                    'Invalid response from server.'
                );
            }

            if (!response.ok || !data.success) {
                throw new Error(
                    data.error ||
                    'Bulk action failed.'
                );
            }

            return data;
        })
        .then(function (data) {

            if (data.message) {
                alert(data.message);
            }

            /*
             * Refresh only the timetable content.
             * The current filters and URL remain unchanged.
             */
            return refreshTimetable();

        })
        .catch(function (error) {

            alert(
                error.message ||
                'Bulk action failed.'
            );

        })
        .finally(function () {

            const applyButton =
                document.querySelector(
                    '[onclick="bulkAction()"]'
                );

            if (applyButton) {
                applyButton.disabled = false;

                applyButton.innerHTML =
                    applyButton.dataset.originalHtml ||
                    '<i class="bi bi-play-fill"></i> Apply';
            }
        });
    };

    const confirmMessage =
        'Apply "' +
        actionLabel +
        '" to ' +
        ids.length +
        ' selected ' +
        (ids.length === 1 ? 'entry' : 'entries') +
        '?';

    if (typeof customConfirm === 'function') {

        customConfirm(
            'Bulk Action',
            confirmMessage,
            proceed
        );

    } else {

        proceed(
            confirm(confirmMessage)
        );
    }
}

// ===== Export CSV =====
function exportCSV() {
    const url = window.location.href + '&export=csv';
    window.open(url, '_blank');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>