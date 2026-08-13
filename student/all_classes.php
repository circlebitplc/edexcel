<?php
// student/all_classes.php – Public timetable with WhatsApp Community links
require_once __DIR__ . '/../config/database.php';

// ---- GET FILTERS ----
$selected_class = (int)($_GET['class_id'] ?? 0);
$teacher_filter = $_GET['teacher'] ?? '';

// Current week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$today = date('Y-m-d');

// ---- FETCH ALL CLASSES ----
$entries = [];
$teachers_list = [];

// Get all classes for the dropdown (including whatsapp_link)
$classes = $pdo->query("SELECT id, name, whatsapp_link FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

// Build class ID list
$class_ids = [];
if ($selected_class > 0) {
    $class_ids = [$selected_class];
} else {
    foreach ($classes as $c) {
        $class_ids[] = $c['id'];
    }
}

if (!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $params = array_merge($class_ids, [$week_start, $week_end]);

    $sql = "SELECT t.date, t.start_time, t.end_time,
                   tc.name as teacher_name,
                   tc.phone as teacher_phone,
                   s.name as subject_name,
                   r.name as room_name,
                   c.name as class_name,
                   t.class_id
            FROM timetable t
            JOIN teachers tc ON t.teacher_id = tc.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN student_classes c ON t.class_id = c.id
            JOIN rooms r ON t.room_id = r.id
            WHERE t.class_id IN ($placeholders)
            AND t.deleted_at IS NULL
            AND t.date BETWEEN ? AND ?
            ORDER BY t.date, t.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    $teacher_names = array_unique(array_column($entries, 'teacher_name'));
    sort($teacher_names);
    $teachers_list = $teacher_names;
}

// ---- APPLY FILTERS ----
if ($teacher_filter) {
    $entries = array_filter($entries, function($e) use ($teacher_filter) {
        return $e['teacher_name'] === $teacher_filter;
    });
}
$entries = array_values($entries);

// Group by day
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$grouped = [];
foreach ($days_of_week as $day) {
    $grouped[$day] = [];
}
foreach ($entries as $e) {
    $day = date('l', strtotime($e['date']));
    if (isset($grouped[$day])) {
        $grouped[$day][] = $e;
    }
}

// Colour palette for classes
$color_palette = ['#4A6CF7', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
$subject_color_map = [];
$color_index = 0;
foreach ($entries as $e) {
    if (!isset($subject_color_map[$e['class_name']])) {
        $subject_color_map[$e['class_name']] = $color_palette[$color_index % count($color_palette)];
        $color_index++;
    }
}

// Build a quick lookup for whatsapp_link by class_id
$whatsapp_links = [];
foreach ($classes as $c) {
    if (!empty($c['whatsapp_link'])) {
        $whatsapp_links[$c['id']] = $c['whatsapp_link'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Timetable – Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    
</head>
<body>
<div class="container">

    <!-- Header -->
    <div class="header-section">
        <div class="brand">
            <h1>📅 Timetable</h1>
            <span>Edexcel College · Public Schedule</span>
        </div>
        <div class="week-info">
            <i class="bi bi-calendar-week"></i>
            <?= date('d M', strtotime($week_start)) ?> – <?= date('d M Y', strtotime($week_end)) ?>
        </div>
    </div>

    <!-- Stats -->
    <?php
    $total_classes = count($entries);
    $today_classes = count(array_filter($entries, function($e) use ($today) { return $e['date'] === $today; }));
    $unique_teachers = count(array_unique(array_column($entries, 'teacher_name')));
    ?>
    <div class="stats-bar">
        <div class="stat-item"><div class="number"><?= $total_classes ?></div><div class="label">Total Classes</div></div>
        <div class="stat-item"><div class="number"><?= $today_classes ?></div><div class="label">Today</div></div>
        <div class="stat-item"><div class="number"><?= $unique_teachers ?></div><div class="label">Teachers</div></div>
        <div class="stat-item"><div class="number"><?= count($classes) ?></div><div class="label">Subjects</div></div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="w-100 d-flex flex-wrap gap-2 align-items-end">
            <divclass="flex-grow-1 inline-css-04c6a0a4e8">
                <label for="classSelect" class="form-label">Class</label>
                <select id="classSelect" name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?= ($selected_class == 0) ? 'selected' : '' ?>>All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($selected_class == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <divclass="flex-grow-1 inline-css-04c6a0a4e8">
                <label for="teacher" class="form-label">Teacher</label>
                <select id="teacher" name="teacher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= ($teacher_filter == $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i> Reset</a>
            </div>
            <div class="ms-auto text-muted small">
                <i class="bi bi-list-ul"></i> <?= count($entries) ?> classes
            </div>
        </form>
    </div>

    <!-- Content -->
    <?php if (empty($class_ids) || count($entries) === 0): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h4>No classes this week</h4>
            <p class="text-muted">Check back later or adjust your filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($days_of_week as $day): ?>
            <?php if (!empty($grouped[$day])): ?>
                <div class="day-section">
                    <div class="day-header">
                        <h3><?= $day ?></h3>
                        <?php
                        $day_date = date('Y-m-d', strtotime($day . ' this week'));
                        $label = $day_date === $today ? 'Today' : (($day_date === date('Y-m-d', strtotime('+1 day'))) ? 'Tomorrow' : date('d M', strtotime($day . ' this week')));
                        $badge_class = $label === 'Today' ? 'today' : ($label === 'Tomorrow' ? 'tomorrow' : '');
                        ?>
                        <span class="day-badge <?= $badge_class ?>"><?= $label === 'Today' ? '🔵 Today' : ($label === 'Tomorrow' ? '🟡 Tomorrow' : $label) ?></span>
                        <span class="count-badge"><?= count($grouped[$day]) ?> class<?= count($grouped[$day]) > 1 ? 'es' : '' ?></span>
                    </div>

                    <?php foreach ($grouped[$day] as $e):
                        $icon_key = $e['class_name'] ?? $e['subject_name'];
                        $icon = 'bi-mortarboard';
                        $lower = strtolower($icon_key);
                        if (strpos($lower, 'math') !== false) $icon = 'bi-calculator';
                        elseif (strpos($lower, 'physics') !== false) $icon = 'bi-lightning';
                        elseif (strpos($lower, 'chemistry') !== false) $icon = 'bi-flask';
                        elseif (strpos($lower, 'biology') !== false) $icon = 'bi-tree';
                        elseif (strpos($lower, 'ict') !== false || strpos($lower, 'computer') !== false) $icon = 'bi-laptop';
                        elseif (strpos($lower, 'english') !== false) $icon = 'bi-book';
                        elseif (strpos($lower, 'economic') !== false) $icon = 'bi-bar-chart';
                        elseif (strpos($lower, 'business') !== false) $icon = 'bi-briefcase';
                        elseif (strpos($lower, 'accounting') !== false) $icon = 'bi-cash-coin';

                        $phone = !empty($e['teacher_phone']) ? preg_replace('/[^0-9+]/', '', $e['teacher_phone']) : '';
                        $msg = "Hi%20" . urlencode($e['teacher_name']) . "%2C%20I%20would%20like%20to%20join%20your%20" . urlencode($e['class_name']) . "%20class%20(" . urlencode($e['subject_name']) . ").%20Please%20give%20more%20details.";
                        $wa_url = !empty($phone) ? "https://wa.me/" . $phone . "?text=" . $msg : "https://wa.me/?text=" . $msg;

                        $whatsapp_link = $whatsapp_links[$e['class_id']] ?? '';
                    ?>
                    <div class="class-card">
                        <divclass="accent-bar inline-css-0bdc38a1fd"></div>
                        <div class="card-content">
                            <div class="time-section">
                                <span class="start-time"><?= date('g:i A', strtotime($e['start_time'])) ?></span>
                                <span class="end-time">– <?= date('g:i A', strtotime($e['end_time'])) ?></span>
                            </div>
                            <div class="subject-section">
                                <div class="class-name"><i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($e['class_name']) ?></div>
                                <div class="subject-name"><i class="bi bi-book"></i> <?= htmlspecialchars($e['subject_name']) ?></div>
                            </div>
                            <div class="meta-section">
                                <a href="<?= $wa_url ?>" class="teacher-pill" target="_blank" title="WhatsApp"><i class="bi bi-whatsapp"></i> <?= htmlspecialchars($e['teacher_name']) ?></a>
                                <span class="pill"><i class="bi bi-door-open"></i> <?= htmlspecialchars($e['room_name']) ?></span>
                                <?php if ($e['date'] === $today): ?>
                                    <span class="today-tag"><i class="bi bi-dot"></i> Today</span>
                                <?php endif; ?>
                                <?php if (!empty($whatsapp_link)): ?>
                                    <a href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" class="whatsapp-btn" title="Join WhatsApp Community">
                                        <i class="bi bi-whatsapp"></i> Join Community
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>