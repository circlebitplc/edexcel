<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_login();

if ($_SESSION['role'] !== 'student') {
    die('Access denied. Students only.');
}

$student_id = $_SESSION['user_id'];

// Get student's enrolled classes
$stmt = $pdo->prepare("SELECT c.id, c.name FROM student_classes c
                       JOIN student_enrollments se ON c.id = se.class_id
                       WHERE se.student_id = ? AND c.deleted_at IS NULL");
$stmt->execute([$student_id]);
$classes = $stmt->fetchAll();

// ---- GET FILTERS ----
$selected_class = (int)($_GET['class_id'] ?? 0);
$teacher_filter = $_GET['teacher'] ?? '';

// Current week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$today = date('Y-m-d');

// ---- BUILD CLASS ID LIST ----
$class_ids = [];
if ($selected_class > 0) {
    $class_ids = [$selected_class];
} else {
    foreach ($classes as $c) {
        $class_ids[] = $c['id'];
    }
}

// ---- FETCH ENTRIES ----
$entries = [];
$teachers_list = [];
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

    // Build list of teachers for this class
    $teacher_names = array_unique(array_column($entries, 'teacher_name'));
    sort($teacher_names);
    $teachers_list = $teacher_names;
}

// ---- APPLY TEACHER FILTER ----
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

// Color palette for classes
$color_palette = ['#4A6CF7', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
$subject_color_map = [];
$color_index = 0;
foreach ($entries as $e) {
    if (!isset($subject_color_map[$e['class_name']])) {
        $subject_color_map[$e['class_name']] = $color_palette[$color_index % count($color_palette)];
        $color_index++;
    }
}

include '../includes/header.php';
?>



<div class="all-classes-page">

    <!-- ===== Page Header ===== -->
    <div class="page-header">
        <h2><i class="bi bi-list-ul"></i> All Classes</h2>
        <span class="badge-count"><?= count($entries) ?> classes this week</span>
    </div>

    <!-- ===== Class Selector ===== -->
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="classSelect" class="form-label fw-semibold">Select Class</label>
            <select id="classSelect" class="form-select" onchange="window.location.href='?class_id='+this.value<?= ($teacher_filter ? '&teacher='.urlencode($teacher_filter) : '') ?>">
                <option value="0" <?= ($selected_class == 0) ? 'selected' : '' ?>>-- All Classes --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($selected_class == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-center">
            <span class="text-muted">
                <i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($week_start)) ?> - <?= date('d M Y', strtotime($week_end)) ?>
            </span>
        </div>
    </div>

    <!-- ===== Filter Section ===== -->
    <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="class_id" value="<?= $selected_class ?>">
            <div class="col-md-4">
                <label for="teacher" class="form-label"><i class="bi bi-person"></i> Filter by Teacher</label>
                <select id="teacher" name="teacher" class="form-select" onchange="this.form.submit()">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= ($teacher_filter == $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <a href="?class_id=<?= $selected_class ?>" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </a>
            </div>
            <?php if ($teacher_filter): ?>
                <div class="col-md-auto">
                    <span class="badge bg-info text-dark">
                        <i class="bi bi-funnel"></i> Filtered: <?= htmlspecialchars($teacher_filter) ?>
                    </span>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($class_ids) || count($entries) === 0): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h5>No classes this week</h5>
            <p class="text-muted">Enjoy your break! ??</p>
        </div>
    <?php else: ?>

        <?php foreach ($days_of_week as $day): ?>
            <?php if (!empty($grouped[$day])): ?>
                <div class="day-section">
                    <div class="day-header">
                        <h4><?= $day ?></h4>
                        <?php if ($day === date('l')): ?>
                            <span class="day-badge today">Today</span>
                        <?php else: ?>
                            <span class="day-badge"><?= date('d M', strtotime($day . ' this week')) ?></span>
                        <?php endif; ?>
                        <span class="class-count"><?= count($grouped[$day]) ?> class<?= count($grouped[$day]) > 1 ? 'es' : '' ?></span>
                    </div>

                    <?php foreach ($grouped[$day] as $e): 
                        $icon_key = $e['class_name'] ?? $e['subject_name'];
                        $subject_icon = '<i class="bi bi-mortarboard"></i>';
                        $icon_key_lower = strtolower($icon_key);
                        if (strpos($icon_key_lower, 'math') !== false) $subject_icon = '<i class="bi bi-calculator"></i>';
                        elseif (strpos($icon_key_lower, 'physics') !== false) $subject_icon = '<i class="bi bi-lightning"></i>';
                        elseif (strpos($icon_key_lower, 'chemistry') !== false) $subject_icon = '<i class="bi bi-flask"></i>';
                        elseif (strpos($icon_key_lower, 'biology') !== false) $subject_icon = '<i class="bi bi-tree"></i>';
                        elseif (strpos($icon_key_lower, 'ict') !== false || strpos($icon_key_lower, 'computer') !== false) $subject_icon = '<i class="bi bi-laptop"></i>';
                        elseif (strpos($icon_key_lower, 'english') !== false) $subject_icon = '<i class="bi bi-book"></i>';
                        elseif (strpos($icon_key_lower, 'economic') !== false) $subject_icon = '<i class="bi bi-bar-chart"></i>';
                        elseif (strpos($icon_key_lower, 'business') !== false) $subject_icon = '<i class="bi bi-briefcase"></i>';
                        elseif (strpos($icon_key_lower, 'accounting') !== false) $subject_icon = '<i class="bi bi-cash-coin"></i>';
                    ?>
                    <div class="class-card">
                        <divclass="accent-bar inline-css-cca6df4d00"></div>
                        
                        <div class="card-content">
                            <div class="subject-block">
                                <divclass="class-name-main inline-css-1c6d542037">
                                    <span class="subject-icon"><?= $subject_icon ?></span>
                                    <?= htmlspecialchars($e['class_name']) ?>
                                </div>
                                <div class="subject-sub">
                                    <i class="bi bi-book"></i> <?= htmlspecialchars($e['subject_name']) ?>
                                </div>
                            </div>

                            <div class="info-block">
                                <?php 
                                $phone = !empty($e['teacher_phone']) ? preg_replace('/[^0-9+]/', '', $e['teacher_phone']) : '';
                                $message = "Hi%20" . urlencode($e['teacher_name']) . "%2C%20I%20would%20like%20to%20join%20your%20" . urlencode($e['class_name']) . "%20class%20(" . urlencode($e['subject_name']) . ").%20Please%20give%20more%20details.";
                                $whatsapp_url = !empty($phone) ? "https://wa.me/" . $phone . "?text=" . $message : "https://wa.me/?text=" . $message;
                                ?>
                                <a href="<?= $whatsapp_url ?>" class="teacher-chip" target="_blank" title="Send WhatsApp message to <?= htmlspecialchars($e['teacher_name']) ?>">
                                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($e['teacher_name']) ?>
                                </a>
                                <span class="info-chip">
                                    <i class="bi bi-door-open"></i> <?= htmlspecialchars($e['room_name']) ?>
                                </span>
                                <!-- Time chip with clock icon -->
                                <span class="info-chip time-chip">
                                    <i class="bi bi-clock"></i> <?= date('g:i A', strtotime($e['start_time'])) ?> - <?= date('g:i A', strtotime($e['end_time'])) ?>
                                </span>
                            </div>

                            <?php if ($e['date'] === $today): ?>
                                <span class="today-tag"><i class="bi bi-lightning-fill"></i> Today</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>