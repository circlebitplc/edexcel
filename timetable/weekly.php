<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/recurring.php';
require_once __DIR__ . '/../config/cache.php';
require_login();

// Only generate 7 days ahead for better performance
generate_future_entries($pdo, 7);

$is_admin = is_admin();
$is_teacher = is_teacher();
$session_teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$type = $_GET['type'] ?? 'teacher';
$id = (int)($_GET['id'] ?? 0);

/*
 * Teachers can only view their own timetable.
 * Admins can select a teacher/room/class.
 */
if ($is_teacher) {
    if (!$session_teacher_id) {
        http_response_code(403);
        exit('Your teacher account is not linked to a teacher profile.');
    }

    $type = 'teacher';
    $id = $session_teacher_id;
} elseif (!$is_admin) {
    http_response_code(403);
    exit('Access denied.');
} elseif ($id <= 0 && $type === 'teacher') {
    // Give admins a useful default instead of ending on "Invalid selection".
    $defaultTeacher = $pdo->query(
        "SELECT id FROM teachers WHERE deleted_at IS NULL ORDER BY name LIMIT 1"
    )->fetchColumn();

    if ($defaultTeacher) {
        $id = (int)$defaultTeacher;
    }
}

$week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

// Get the week days (Mon-Sun)
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime($week_start . " +$i days"));
}

// Time slots 8:00–18:00 hourly
$time_slots = [];
for ($h = 8; $h < 18; $h++) {
    $time_slots[] = sprintf('%02d:00', $h);
}

$where = [];
$params = [];
$title = '';

if ($type === 'teacher' && $id) {
    $where[] = "t.teacher_id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    $title = "Teacher: " . ($entity ? htmlspecialchars($entity['name']) : 'Unknown');
} elseif ($type === 'room' && $id) {
    $where[] = "t.room_id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    $title = "Room: " . ($entity ? htmlspecialchars($entity['name']) : 'Unknown');
} elseif ($type === 'class' && $id) {
    $where[] = "t.class_id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare("SELECT name FROM student_classes WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    $title = "Class: " . ($entity ? htmlspecialchars($entity['name']) : 'Unknown');
} else {
    die('Invalid selection.');
}

$where[] = "t.date >= ? AND t.date <= ? AND t.deleted_at IS NULL";
$params[] = $days[0];
$params[] = $days[6];

// Cache key for this specific query
$cache_key = 'weekly_' . md5($type . '_' . $id . '_' . implode('_', $days));
$entries = $cache->get($cache_key);

if ($entries === null) {
    $sql = "SELECT t.*, 
                   tc.name as teacher_name, 
                   s.name as subject_name, 
                   c.name as class_name, 
                   r.name as room_name 
            FROM timetable t
            LEFT JOIN teachers tc ON t.teacher_id = tc.id AND tc.deleted_at IS NULL
            LEFT JOIN subjects s ON t.subject_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN student_classes c ON t.class_id = c.id AND c.deleted_at IS NULL
            LEFT JOIN rooms r ON t.room_id = r.id AND r.deleted_at IS NULL
            WHERE " . implode(" AND ", $where) . "
            ORDER BY t.date, t.start_time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();
    // Cache for 1 hour
    $cache->set($cache_key, $entries, 60);
}

// Build grid: [date][start_time] = entry
$day_entries = [];
foreach ($entries as $e) {
    $date = $e['date'];
    $start = substr($e['start_time'], 0, 5);
    $day_entries[$date][$start] = $e;
}
// Sort start times for each day
foreach ($day_entries as $date => &$slots) {
    ksort($slots);
}

// Dropdown data (cached)
$dropdown_cache_key = 'weekly_dropdowns';
$dropdowns = $cache->get($dropdown_cache_key);
if ($dropdowns === null) {
    $teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    $rooms = $pdo->query("SELECT id, name FROM rooms WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    $classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    $dropdowns = ['teachers' => $teachers, 'rooms' => $rooms, 'classes' => $classes];
    $cache->set($dropdown_cache_key, $dropdowns, 3600);
} else {
    $teachers = $dropdowns['teachers'];
    $rooms = $dropdowns['rooms'];
    $classes = $dropdowns['classes'];
}

$selected_teacher = ($type === 'teacher') ? $id : 0;
$selected_room = ($type === 'room') ? $id : 0;
$selected_class = ($type === 'class') ? $id : 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="tt-page-heading">
    <div class="heading-copy">
        <h1><i class="bi bi-calendar-week"></i> Weekly Timetable</h1>
        <p>See lessons by teacher, room or class across the selected week.</p>
    </div>
</div>

<div class="filter-bar"><form method="GET" class="row g-3 mb-0" id="filterForm">
    <div class="col-md-3">
        <label for="type" class="form-label">View by</label>
        <select id="type" name="type" class="form-select" onchange="document.getElementById('filterForm').submit()">
            <option value="teacher" <?= $type=='teacher'?'selected':'' ?>>Teacher</option>
            <option value="room" <?= $type=='room'?'selected':'' ?>>Room</option>
            <option value="class" <?= $type=='class'?'selected':'' ?>>Class</option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="id" class="form-label">Select</label>
        <select id="id" name="id" class="form-select" onchange="document.getElementById('filterForm').submit()">
            <option value="">-- Choose --</option>
            <?php if ($type === 'teacher'): ?>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($selected_teacher == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            <?php elseif ($type === 'room'): ?>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= ($selected_room == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            <?php elseif ($type === 'class'): ?>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($selected_class == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label for="week_start" class="form-label">Week Start</label>
        <input type="date" id="week_start" name="week_start" class="form-control" value="<?= $week_start ?>" onchange="document.getElementById('filterForm').submit()">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <a href="export_pdf.php?type=<?= $type ?>&id=<?= $id ?>&week_start=<?= $week_start ?>" class="btn btn-danger w-100" target="_blank"><i class="bi bi-file-pdf"></i> Export PDF</a>
    </div>
</form></div>

<?php if ($id): ?>
    <h3><?= $title ?> (Week of <?= date('d M Y', strtotime($week_start)) ?>)</h3>
    
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th style="min-width:80px;">Time</th>
                    <?php foreach ($days as $d): ?>
                        <th><?= date('D d M', strtotime($d)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                for ($i = 0; $i < count($time_slots); $i++) {
                    $slot = $time_slots[$i];
                    $next_slot = ($i + 1 < count($time_slots)) ? $time_slots[$i + 1] : '18:00';
                    echo '<tr>';
                    echo '<td style="white-space:nowrap; font-weight:bold;">' . date('g:i A', strtotime($slot)) . ' – ' . date('g:i A', strtotime($next_slot)) . '</td>';
                    
                    foreach ($days as $d) {
                        if (isset($day_entries[$d][$slot])) {
                            $entry = $day_entries[$d][$slot];
                            $start_ts = strtotime($entry['start_time']);
                            $end_ts   = strtotime($entry['end_time']);
                            $duration = ($end_ts - $start_ts) / 3600;
                            $rowspan  = max(1, ceil($duration));
                            $remaining = count($time_slots) - $i;
                            if ($rowspan > $remaining) $rowspan = $remaining;
                            $time_display = date('g:i A', $start_ts) . ' – ' . date('g:i A', $end_ts);
                            echo '<td rowspan="' . $rowspan . '" style="vertical-align:middle; min-height:80px;">';
                            echo '<div class="badge bg-info text-dark p-2 w-100" style="font-size:0.85rem; white-space:normal; text-align:left; line-height:1.6;">';
                            echo '<div><strong>' . $time_display . '</strong></div>';
                            echo '<div><strong>' . htmlspecialchars($entry['subject_name']) . '</strong></div>';
                            echo '<div><small>' . htmlspecialchars($entry['teacher_name']) . '</small></div>';
                            echo '<div><small>' . htmlspecialchars($entry['room_name']) . '</small></div>';
                            echo '<div><small>' . htmlspecialchars($entry['class_name']) . '</small></div>';
                            echo '<div><small>👥 ' . $entry['student_count'] . '</small></div>';
                            echo '</div>';
                            echo '</td>';
                        } else {
                            $covered = false;
                            for ($prev = $i - 1; $prev >= 0; $prev--) {
                                $prev_slot = $time_slots[$prev];
                                if (isset($day_entries[$d][$prev_slot])) {
                                    $prev_entry = $day_entries[$d][$prev_slot];
                                    $start_ts = strtotime($prev_entry['start_time']);
                                    $end_ts   = strtotime($prev_entry['end_time']);
                                    $slot_ts  = strtotime($slot);
                                    if ($slot_ts >= $start_ts && $slot_ts < $end_ts) {
                                        $covered = true;
                                        break;
                                    }
                                }
                            }
                            if (!$covered) {
                                echo '<td class="text-center text-muted">FREE</td>';
                            }
                        }
                    }
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-3">
        <p><span class="badge bg-info text-dark">Colored block</span> = Lesson scheduled (shows time inside)</p>
        <p><span class="text-muted">FREE</span> = Available slot</p>
    </div>
<?php else: ?>
    <div class="alert alert-warning">Please select a teacher, room, or class to view.</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>