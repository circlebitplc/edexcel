<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/recurring.php';
require_login();

generate_future_entries($pdo, 14);

$is_admin = is_admin();
$teacher_id = $_SESSION['teacher_id'] ?? 0;

if ($is_admin && isset($_GET['teacher_id'])) {
    $teacher_id = (int)$_GET['teacher_id'];
}

/*
 * Admins may open Teacher Schedule without selecting a teacher.
 * Show a proper teacher selector instead of passing teacher_id=0.
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

    <div class="container-fluid py-4">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <i class="bi bi-calendar-week fs-2 text-primary"></i>
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
                        <div class="col-md-6 col-lg-5">
                            <label for="teacher_id" class="form-label fw-semibold">
                                Select Teacher
                            </label>

                            <select
                                name="teacher_id"
                                id="teacher_id"
                                class="form-select"
                                required
                            >
                                <option value="">Choose a teacher...</option>

                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>">
                                        <?= htmlspecialchars($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
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

$stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher not found.');
}
$teacher_name = htmlspecialchars($teacher['name']);

// Week start (Monday)
$week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($week_start)));

// Generate Mon–Sun (7 days)
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime($week_start . " +$i days"));
}

// Time slots 8:00–18:00 (hourly)
$time_slots = [];
for ($h = 8; $h < 18; $h++) {
    $time_slots[] = sprintf('%02d:00', $h);
}

$sql = "SELECT t.*, 
               s.name as subject_name, 
               c.name as class_name, 
               r.name as room_name 
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.id
        JOIN student_classes c ON t.class_id = c.id
        JOIN rooms r ON t.room_id = r.id
        WHERE t.teacher_id = ? 
        AND t.date BETWEEN ? AND ?
        ORDER BY t.date, t.start_time";

$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id, $days[0], $days[6]]);
$entries = $stmt->fetchAll();

// Group by date and start time
$day_entries = [];
foreach ($entries as $e) {
    $date = $e['date'];
    $start = substr($e['start_time'], 0, 5);
    $day_entries[$date][$start] = $e;
}
foreach ($day_entries as $date => &$slots) {
    ksort($slots);
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* Google Calendar style */
.week-grid {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    overflow: auto;
    font-family: 'Segoe UI', Roboto, Arial, sans-serif;
}
.week-grid .header {
    display: flex;
    background: #f1f3f4;
    border-bottom: 1px solid #dadce0;
    font-weight: 500;
    min-width: 700px;
}
.week-grid .header .day-label {
    flex: 1;
    text-align: center;
    padding: 12px 0;
    font-size: 0.9rem;
    color: #3c4043;
    min-width: 80px;
}
.week-grid .header .day-label.today {
    background: #e8f0fe;
    color: #1a73e8;
    border-radius: 4px;
}
.week-grid .body {
    display: flex;
    min-width: 700px;
}
.week-grid .time-col {
    width: 60px;
    flex-shrink: 0;
    background: #f8f9fa;
    border-right: 1px solid #dadce0;
    position: relative;
}
.week-grid .time-col .time-slot {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: #5f6368;
    border-bottom: 1px solid #e8eaed;
}
.week-grid .days {
    display: flex;
    flex: 1;
}
.week-grid .day-col {
    flex: 1;
    border-right: 1px solid #dadce0;
    position: relative;
    min-height: 540px; /* 9 hours * 60px */
}
.week-grid .day-col:last-child {
    border-right: none;
}
.week-grid .day-col .hour-slot {
    height: 60px;
    border-bottom: 1px solid #e8eaed;
    position: relative;
}
.week-grid .day-col .hour-slot:last-child {
    border-bottom: none;
}
.week-grid .class-block {
    position: absolute;
    left: 4px;
    right: 4px;
    background: #e8f0fe;
    border-left: 4px solid #1a73e8;
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 0.75rem;
    line-height: 1.3;
    color: #1a0dab;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.week-grid .class-block .time {
    font-weight: 500;
    font-size: 0.65rem;
    color: #1a73e8;
}
.week-grid .class-block .subject {
    font-weight: 600;
    font-size: 0.8rem;
    color: #202124;
}
.week-grid .class-block .details {
    font-size: 0.65rem;
    color: #3c4043;
}
.week-grid .free-text {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 60px;
    color: #dadce0;
    font-size: 0.75rem;
    font-style: italic;
}

/* Navigation */
.nav-week {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.nav-week .week-range {
    font-weight: 500;
    color: #3c4043;
}
.nav-week .btn-outline-secondary {
    border-color: #dadce0;
    color: #3c4043;
}
.nav-week .btn-outline-secondary:hover {
    background: #f1f3f4;
}

.teacher-selector {
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .week-grid .time-col {
        width: 40px;
    }
    .week-grid .time-col .time-slot {
        font-size: 0.6rem;
        height: 50px;
    }
    .week-grid .day-col .hour-slot {
        height: 50px;
    }
    .week-grid .class-block {
        font-size: 0.65rem;
        padding: 2px 4px;
    }
    .week-grid .class-block .subject {
        font-size: 0.7rem;
    }
    .week-grid .header .day-label {
        font-size: 0.7rem;
        padding: 8px 0;
    }
}
</style>

<div class="nav-week">
    <div>
        <a href="?teacher_id=<?= $teacher_id ?>&week_start=<?= date('Y-m-d', strtotime($week_start . ' -7 days')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> Previous
        </a>
        <a href="?teacher_id=<?= $teacher_id ?>&week_start=<?= date('Y-m-d', strtotime('monday this week')) ?>" class="btn btn-outline-secondary btn-sm">
            Today
        </a>
        <a href="?teacher_id=<?= $teacher_id ?>&week_start=<?= date('Y-m-d', strtotime($week_start . ' +7 days')) ?>" class="btn btn-outline-secondary btn-sm">
            Next <i class="bi bi-chevron-right"></i>
        </a>
    </div>
    <span class="week-range">
        <?= date('M d', strtotime($days[0])) ?> – <?= date('M d, Y', strtotime($days[6])) ?>
    </span>
</div>

<?php if ($is_admin): ?>
<div class="teacher-selector">
    <form method="GET" class="row g-2">
        <div class="col-auto">
            <label for="teacher_id" class="col-form-label">Teacher:</label>
        </div>
        <div class="col-auto">
            <select name="teacher_id" id="teacher_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Select...</option>
                <?php
                $teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();
                foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($teacher_id == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="week_start" value="<?= $week_start ?>">
    </form>
</div>
<?php endif; ?>

<h2 class="h4 mb-3"><?= $teacher_name ?> – Weekly Schedule</h2>

<div class="week-grid">
    <!-- Header -->
    <div class="header">
        <div style="width:60px; flex-shrink:0;"></div>
        <?php foreach ($days as $d): 
            $is_today = ($d == date('Y-m-d'));
        ?>
            <div class="day-label <?= $is_today ? 'today' : '' ?>">
                <?= date('D', strtotime($d)) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Body -->
    <div class="body">
        <div class="time-col">
            <?php foreach ($time_slots as $slot): ?>
                <div class="time-slot"><?= date('g A', strtotime($slot)) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="days">
            <?php foreach ($days as $d): ?>
                <div class="day-col" data-date="<?= $d ?>">
                    <?php
                    // Empty hour slots for positioning
                    for ($i = 0; $i < count($time_slots); $i++) {
                        echo '<div class="hour-slot"></div>';
                    }
                    // Overlay classes
                    $day_entries_for_date = $day_entries[$d] ?? [];
                    foreach ($day_entries_for_date as $start_slot => $entry) {
                        $start_ts = strtotime($entry['start_time']);
                        $end_ts = strtotime($entry['end_time']);
                        $duration = ($end_ts - $start_ts) / 3600;
                        $start_idx = array_search(substr($entry['start_time'], 0, 5), $time_slots);
                        if ($start_idx === false) continue;
                        $start_minutes = (int)substr($entry['start_time'], 3, 2);
                        $top_offset = ($start_minutes / 60) * 60;
                        $height = $duration * 60;
                        $max_height = (count($time_slots) - $start_idx) * 60 - $top_offset;
                        if ($height > $max_height) $height = $max_height;

                        echo '<div class="class-block" style="top:' . ($start_idx * 60 + $top_offset) . 'px; height:' . $height . 'px;">';
                        echo '<div class="time">' . date('g:i A', $start_ts) . ' – ' . date('g:i A', $end_ts) . '</div>';
                        echo '<div class="subject">' . htmlspecialchars($entry['subject_name']) . '</div>';
                        echo '<div class="details">' . htmlspecialchars($entry['class_name']) . ' · ' . htmlspecialchars($entry['room_name']) . '</div>';
                        echo '<div class="details">👥 ' . $entry['student_count'] . '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>