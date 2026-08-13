<?php
/**
 * Public timetable – external CSS/JS, uses class_teacher_whatsapp for group links.
 * MAIN GROUP LINK: https://chat.whatsapp.com/HG3Vqyn5CCACDEuN8UlGaZ
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php'; // defines BASE_URL

// 2. Get filters
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$teacherFilter = isset($_GET['teacher']) ? trim($_GET['teacher']) : '';
$weekOffset    = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;
$isAjax        = isset($_GET['ajax']) || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($selectedClass < 0) $selectedClass = 0;

// 3. Calculate displayed week
$weekStart = date('Y-m-d', strtotime("monday this week + $weekOffset weeks"));
$weekEnd   = date('Y-m-d', strtotime("sunday this week + $weekOffset weeks"));
$today     = date('Y-m-d');
$currentTime = date('H:i:s');

// 4. Fetch classes
$classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

// 5. Build class ID list
$classIds = [];
if ($selectedClass > 0) {
    $classIds = [$selectedClass];
} else {
    foreach ($classes as $c) $classIds[] = (int)$c['id'];
}

$entries = [];
$teachersList = [];

if (!empty($classIds)) {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $params = $classIds;
    $params[] = $weekStart;
    $params[] = $weekEnd;

    $sql = "SELECT t.date, t.start_time, t.end_time,
                   tc.name AS teacher_name,
                   tc.phone AS teacher_phone,
                   s.name AS subject_name,
                   r.name AS room_name,
                   c.name AS class_name,
                   t.class_id,
                   t.teacher_id,
                   t.subject_id
            FROM timetable t
            JOIN teachers tc ON t.teacher_id = tc.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN student_classes c ON t.class_id = c.id
            JOIN rooms r ON t.room_id = r.id
            WHERE t.class_id IN ($placeholders)
              AND t.deleted_at IS NULL
              AND t.date BETWEEN ? AND ?";

    if (!empty($teacherFilter)) {
        $sql .= " AND tc.name = ?";
        $params[] = $teacherFilter;
    }
    $sql .= " ORDER BY t.date, t.start_time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    $teacherNames = array_unique(array_column($entries, 'teacher_name'));
    sort($teacherNames);
    $teachersList = $teacherNames;
}

// 6. Fetch WhatsApp links – treat NULL subject_id as wildcard
$whatsappLinkMap = [];
$stmt = $pdo->query("SELECT class_id, teacher_id, subject_id, whatsapp_link FROM class_teacher_whatsapp WHERE whatsapp_link IS NOT NULL AND whatsapp_link != ''");
$links = $stmt->fetchAll();
foreach ($links as $link) {
    $classId = $link['class_id'];
    $teacherId = $link['teacher_id'];
    $subjectId = $link['subject_id'] ?? 0; // NULL becomes 0
    $key = $classId . '|' . $teacherId . '|' . $subjectId;
    $whatsappLinkMap[$key] = $link['whatsapp_link'];
}

// 7. Group by day
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$grouped = [];
foreach ($daysOfWeek as $day) $grouped[$day] = [];
foreach ($entries as $e) {
    $day = date('l', strtotime($e['date']));
    if (isset($grouped[$day])) $grouped[$day][] = $e;
}

// 8. Build colour map
$colorPalette = ['#4A6CF7', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
$subjectColorMap = [];
$colorIndex = 0;
foreach ($entries as $e) {
    $key = $e['class_name'];
    if (!isset($subjectColorMap[$key])) {
        $subjectColorMap[$key] = $colorPalette[$colorIndex % count($colorPalette)];
        $colorIndex++;
    }
}

// 9. Helper functions
function getDayLabel(string $date): string {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if ($date === $today) return 'Today';
    if ($date === $tomorrow) return 'Tomorrow';
    return date('D', strtotime($date));
}

function getSubjectIcon(string $name): string {
    $lower = strtolower($name);
    if (strpos($lower, 'math') !== false) return 'bi-calculator';
    if (strpos($lower, 'physics') !== false) return 'bi-lightning';
    if (strpos($lower, 'chemistry') !== false) return 'bi-flask';
    if (strpos($lower, 'biology') !== false) return 'bi-tree';
    if (strpos($lower, 'ict') !== false || strpos($lower, 'computer') !== false) return 'bi-laptop';
    if (strpos($lower, 'english') !== false) return 'bi-book';
    if (strpos($lower, 'economic') !== false) return 'bi-bar-chart';
    if (strpos($lower, 'business') !== false) return 'bi-briefcase';
    if (strpos($lower, 'accounting') !== false) return 'bi-cash-coin';
    return 'bi-mortarboard';
}

function teacherWhatsAppUrl(string $phone, string $teacherName, string $className, string $subject): string {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    $msg = "Hi%20" . urlencode($teacherName) . "%2C%20I%20would%20like%20to%20join%20your%20" . urlencode($className) . "%20class%20(" . urlencode($subject) . ").%20Please%20give%20more%20details.";
    return !empty($phone) ? "https://wa.me/" . $phone . "?text=" . $msg : "https://wa.me/?text=" . $msg;
}

// 10. App name, hotline, and main WhatsApp group link
$appName = getenv('APP_NAME') ?: 'Edexcel College';
$hotlineNumber = getenv('HOTLINE_NUMBER') ?: '+94785858585';
$mainWhatsAppGroupLink = 'https://chat.whatsapp.com/HG3Vqyn5CCACDEuN8UlGaZ'; // change as needed
$hotlineMessage = "Hello%20" . urlencode($appName) . "%2C%20I%20would%20like%20to%20inquire%20about%20the%20class%20timetable.";
$hotlineUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $hotlineNumber) . "?text=" . $hotlineMessage;

// 11. AJAX response
if ($isAjax) {
    ob_start();
    renderContent($grouped, $classes, $teachersList, $subjectColorMap, $daysOfWeek, $selectedClass, $teacherFilter, $weekOffset, $weekStart, $weekEnd, $currentTime, $today, $whatsappLinkMap);
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['html' => $html]);
    exit;
}

// 12. Render function (with wildcard WhatsApp link lookup)
function renderContent($grouped, $classes, $teachersList, $subjectColorMap, $daysOfWeek, $selectedClass, $teacherFilter, $weekOffset, $weekStart, $weekEnd, $currentTime, $today, $whatsappLinkMap) {
    ?>
    <!-- Week Navigation -->
    <div class="week-nav">
        <button class="btn btn-outline-secondary btn-sm" id="prevWeek" data-offset="<?= $weekOffset - 1 ?>">
            <i class="bi bi-chevron-left"></i> Previous
        </button>
        <span class="week-label">
            <?= date('M d', strtotime($weekStart)) ?> – <?= date('M d, Y', strtotime($weekEnd)) ?>
        </span>
        <button class="btn btn-outline-secondary btn-sm" id="nextWeek" data-offset="<?= $weekOffset + 1 ?>">
            Next <i class="bi bi-chevron-right"></i>
        </button>
        <button class="btn btn-outline-secondary btn-sm" id="todayWeek" data-offset="0">
            <i class="bi bi-calendar-week"></i> This week
        </button>
    </div>

    <!-- Timetable -->
    <?php if (empty($grouped) || empty(array_filter($grouped))): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x big-icon"></i>
            <h4>No classes this week</h4>
            <p class="text-muted">Check back later or adjust your filters.</p>
            <a href="?reset=1" class="btn btn-outline-primary btn-sm mt-2">View all</a>
        </div>
    <?php else: ?>
        <?php foreach ($daysOfWeek as $day): ?>
            <?php if (!empty($grouped[$day])): ?>
                <section class="day-section">
                    <div class="day-header">
                        <h3><?= $day ?></h3>
                        <?php
                        $dayDate = date('Y-m-d', strtotime($day . ' this week', strtotime($weekStart)));
                        $label = getDayLabel($dayDate);
                        $badgeClass = $label === 'Today' ? 'today' : ($label === 'Tomorrow' ? 'tomorrow' : '');
                        ?>
                        <span class="day-badge <?= $badgeClass ?>">
                            <?= $label === 'Today' ? '🔵 Today' : ($label === 'Tomorrow' ? '🟡 Tomorrow' : date('d M', strtotime($dayDate))) ?>
                        </span>
                        <span class="count-badge"><?= count($grouped[$day]) ?> class<?= count($grouped[$day]) > 1 ? 'es' : '' ?></span>
                    </div>

                    <?php foreach ($grouped[$day] as $e):
                        $icon = getSubjectIcon($e['subject_name'] ?? $e['class_name'] ?? '');
                        $color = $subjectColorMap[$e['class_name']] ?? '#4A6CF7';
                        $startTs = strtotime($e['start_time']);
                        $endTs   = strtotime($e['end_time']);
                        $duration = ($endTs - $startTs) / 60;
                        $maxDuration = 180;
                        $barWidth = min(($duration / $maxDuration) * 100, 100);

                        $phone = !empty($e['teacher_phone']) ? $e['teacher_phone'] : '';
                        $waUrl = teacherWhatsAppUrl($phone, $e['teacher_name'], $e['class_name'], $e['subject_name']);

                        // Look up WhatsApp group link – first exact match, then wildcard (subject_id = 0)
                        $subjectId = $e['subject_id'] ?? 0;
                        $linkKey = $e['class_id'] . '|' . $e['teacher_id'] . '|' . $subjectId;
                        $groupLink = $whatsappLinkMap[$linkKey] ?? '';

                        if (empty($groupLink)) {
                            $wildcardKey = $e['class_id'] . '|' . $e['teacher_id'] . '|0';
                            $groupLink = $whatsappLinkMap[$wildcardKey] ?? '';
                        }

                        $isLive = false;
                        if ($e['date'] === $today) {
                            $currentTimestamp = strtotime($currentTime);
                            if ($currentTimestamp >= $startTs && $currentTimestamp <= $endTs) {
                                $isLive = true;
                            }
                        }
                    ?>
                    <article class="class-card">
                        <divclass="accent-bar inline-css-67f6ca11af"></div>
                        <div class="card-content">
                            <div class="time-section">
                                <span class="start-time"><?= date('g:i A', $startTs) ?></span>
                                <span class="end-time">– <?= date('g:i A', $endTs) ?></span>
                                <div class="duration-bar">
                                    <divclass="fill inline-css-942f7d52e1"></div>
                                </div>
                            </div>
                            <div class="subject-section">
                                <div class="class-name">
                                    <i class="bi <?= $icon ?>"></i>
                                    <?= htmlspecialchars($e['class_name']) ?>
                                </div>
                                <div class="subject-name">
                                    <i class="bi bi-book"></i> <?= htmlspecialchars($e['subject_name']) ?>
                                </div>
                            </div>
                            <div class="meta-section">
                                <a href="<?= $waUrl ?>" class="teacher-pill" target="_blank">
                                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($e['teacher_name']) ?>
                                </a>
                                <span class="pill"><i class="bi bi-door-open"></i> <?= htmlspecialchars($e['room_name']) ?></span>
                                <?php if ($e['date'] === $today): ?>
                                    <span class="today-tag"><i class="bi bi-dot"></i> Today</span>
                                <?php endif; ?>
                                <?php if ($isLive): ?>
                                    <span class="live-indicator"><i class="bi bi-record-circle"></i> Live</span>
                                <?php endif; ?>
                                <?php if (!empty($groupLink)): ?>
                                    <a href="<?= htmlspecialchars($groupLink) ?>" target="_blank" class="whatsapp-community-btn" title="Join WhatsApp Group">
                                        <i class="bi bi-whatsapp"></i> Join Group
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
}

// 13. Start HTML
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> – Timetable</title>
    <!-- Bootstrap & Icons (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
</head>
<body>

<!-- ===== NAVIGATION BAR ===== -->
<nav class="glass-nav">
    <div class="navbar-brand">
        <i class="bi bi-calendar3"></i> <?= htmlspecialchars($appName) ?>
    </div>
    <div class="nav-links">
        <a href="<?= $hotlineUrl ?>" class="hotline-link" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($hotlineNumber) ?>
        </a>
        <a href="<?= htmlspecialchars($mainWhatsAppGroupLink) ?>" class="whatsapp-group-link" target="_blank" rel="noopener">
            <i class="bi bi-people"></i> Join Group
        </a>
        <button class="dark-toggle-nav" id="darkModeToggle">
            <i class="bi bi-moon-fill" id="darkIcon"></i>
            <span id="darkLabel">Dark</span>
        </button>
        <a href="#"><i class="bi bi-box-arrow-in-right"></i> Login</a>
        <a href="#"><i class="bi bi-person-plus"></i> Register</a>
    </div>
</nav>

<div class="container" id="app-container">

    <!-- ===== FILTER BAR ===== -->
    <div class="filter-bar sticky-filter" id="filterBar">
        <form method="GET" id="filterForm" class="d-flex">
            <input type="hidden" name="week_offset" value="<?= $weekOffset ?>">

            <div class="flex-grow-1">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?= ($selectedClass == 0) ? 'selected' : '' ?>>All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($selectedClass == (int)$c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-grow-1">
                <label class="form-label">Teacher</label>
                <select name="teacher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachersList as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= ($teacherFilter == $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <a href="?reset=1" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- ===== TIMETABLE CONTENT ===== -->
    <div id="timetable-content">
        <?php renderContent($grouped, $classes, $teachersList, $subjectColorMap, $daysOfWeek, $selectedClass, $teacherFilter, $weekOffset, $weekStart, $weekEnd, $currentTime, $today, $whatsappLinkMap); ?>
    </div>

</div>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <divclass="modal-content inline-css-e03e6b68ed">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Class Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== FLOATING WHATSAPP ===== -->
<div class="whatsapp-float">
    <a href="<?= $hotlineUrl ?>" class="btn-whatsapp" target="_blank" rel="noopener">
        <i class="bi bi-whatsapp"></i> Chat
    </a>
</div>

<!-- ===== SHARE BUTTON ===== -->
<div class="share-float">
    <button class="btn" id="shareBtn" title="Copy shareable link">
        <i class="bi bi-share-fill"></i>
    </button>
</div>

<!-- ===== JAVASCRIPT ===== -->
<!-- External JS -->
<script src="<?= BASE_URL ?>assets/js/dashboard.js" defer></script>
<!-- Bootstrap JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>