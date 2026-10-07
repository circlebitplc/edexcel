<?php
/**
 * Public timetable – external CSS/JS, uses class_teacher_whatsapp for group links.
 * MAIN GROUP LINK: https://chat.whatsapp.com/HG3Vqyn5CCACDEuN8UlGaZ
 */
error_reporting(0);
ini_set('display_errors', '0');

// 1. Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php'; // defines BASE_URL

// 2. Get filters
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$teacherFilter = isset($_GET['teacher']) ? trim($_GET['teacher']) : '';
$weekOffset    = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;
$searchQuery   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$isAjax        = isset($_GET['ajax']) || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if (!empty($_GET['reset'])) {
    $selectedClass = 0;
    $teacherFilter = '';
    $searchQuery = '';
    $weekOffset = 0;
}

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
            LEFT JOIN rooms r ON t.room_id = r.id
            WHERE t.class_id IN ($placeholders)
              AND t.deleted_at IS NULL
              AND t.date BETWEEN ? AND ?
            ORDER BY t.date, t.start_time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    $teacherNames = array_unique(array_column($entries, 'teacher_name'));
    sort($teacherNames);
    $teachersList = $teacherNames;

    if ($teacherFilter !== '') {
        $entries = array_values(array_filter($entries, static function ($e) use ($teacherFilter) {
            return ($e['teacher_name'] ?? '') === $teacherFilter;
        }));
    }

    if ($searchQuery !== '') {
        $tokens = preg_split('/\s+/', strtolower($searchQuery)) ?: [];
        $entries = array_values(array_filter($entries, static function ($e) use ($tokens) {
            $hay = strtolower(
                ($e['teacher_name'] ?? '') . ' ' .
                ($e['subject_name'] ?? '') . ' ' .
                ($e['room_name'] ?? '') . ' ' .
                ($e['class_name'] ?? '')
            );
            foreach ($tokens as $token) {
                if ($token !== '' && strpos($hay, $token) === false) {
                    return false;
                }
            }
            return true;
        }));
    }
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
                        <div class="accent-bar"></div>
                        <div class="card-content">
                            <div class="time-section">
                                <span class="start-time"><?= date('g:i A', $startTs) ?></span>
                                <span class="end-time">– <?= date('g:i A', $endTs) ?></span>
                                <div class="duration-bar">
                                    <div class="fill"></div>
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
                                <span class="pill"><i class="bi bi-door-open"></i> <?= htmlspecialchars((string)($e['room_name'] ?? '')) ?></span>
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
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= htmlspecialchars($appName) ?> – Timetable</title>
    <!-- Bootstrap & Icons (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>

<style>
/* KANDY STUDENT TIMETABLE LAYOUT FIX v1 */

#app-container {
    width: min(1420px, calc(100% - 32px));
    margin: 0 auto;
    padding: 24px 0 70px;
}

/* Navigation */
.glass-nav {
    position: sticky;
    top: 0;
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    min-height: 68px;
    padding: 12px max(20px, calc((100vw - 1420px) / 2));
    background: rgba(255,255,255,.95);
    border-bottom: 1px solid rgba(23,32,51,.09);
    box-shadow: 0 8px 28px rgba(23,32,51,.08);
    backdrop-filter: blur(16px);
}

[data-bs-theme="dark"] .glass-nav,
[data-theme]:not([data-theme="light"]) .glass-nav {
    background: var(--nav-bg, rgba(25,30,43,.95));
    border-color: var(--panel-border, rgba(255,255,255,.08));
}

.glass-nav .navbar-brand {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 1.08rem;
    font-weight: 800;
    color: var(--text,#172033);
    white-space: nowrap;
}

.glass-nav .navbar-brand i {
    color: var(--primary,#5161ce);
}

.nav-links {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 7px;
}

.nav-links > a,
.dark-toggle-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid transparent;
    border-radius: 10px;
    background: transparent;
    color: var(--text,#172033);
    font-size: .86rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
}

.nav-links > a:hover,
.dark-toggle-nav:hover {
    background: var(--primary-soft,rgba(81,97,206,.1));
    color: var(--primary,#5161ce);
}

.dark-toggle-nav {
    cursor: pointer;
    border-color: var(--panel-border,#e7eaf3);
}

.hotline-link i,
.whatsapp-group-link i {
    color: #16a36a;
}

/* Filters */
.filter-bar {
    display: flex;
    width: 100%;
    padding: 18px;
    margin: 0 0 20px;
    border: 1px solid var(--panel-border,rgba(23,32,51,.08));
    border-radius: 20px;
    background: var(--surface,#fff);
    box-shadow: 0 12px 35px rgba(23,32,51,.08);
}

.filter-bar form {
    width: 100%;
    display: grid !important;
    grid-template-columns: minmax(0,1fr) minmax(0,1fr) auto;
    gap: 14px;
    align-items: end;
}

.filter-bar .form-label {
    display: block;
    margin: 0 0 6px;
    color: var(--muted,#65718a);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.filter-bar .form-select {
    width: 100%;
    min-height: 48px;
    border-radius: 12px;
}

.filter-bar .btn {
    min-height: 48px;
    padding-inline: 18px;
    border-radius: 12px;
}

/* Week navigation */
.week-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 6px 0 24px;
}

.week-nav .btn {
    min-height: 42px;
    border-radius: 11px;
}

.week-label {
    min-width: 220px;
    text-align: center;
    color: var(--text,#172033);
    font-size: 1rem;
    font-weight: 700;
}

/* Day sections */
.day-section {
    margin: 0 0 28px;
}

.day-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 12px;
    padding: 0 4px;
}

.day-title-wrap {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.day-header h3 {
    margin: 0;
    color: var(--text,#172033);
    font-size: 1.45rem;
    font-weight: 800;
}

.day-date {
    color: var(--muted,#65718a);
    font-size: .84rem;
    font-weight: 600;
}

.day-badge,
.count-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 30px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 800;
}

.day-badge {
    background: var(--primary-soft,rgba(81,97,206,.1));
    color: var(--primary,#5161ce);
}

.day-badge.today {
    background: rgba(22,163,106,.11);
    color: #138455;
}

.count-badge {
    margin-left: 6px;
    background: var(--surface-soft,#f3f5fb);
    color: var(--muted,#65718a);
}

/* Class cards */
.class-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.class-card {
    position: relative;
    display: block;
    overflow: hidden;
    border: 1px solid var(--panel-border,rgba(23,32,51,.08));
    border-radius: 18px;
    background: var(--surface,#fff);
    box-shadow: 0 9px 28px rgba(23,32,51,.065);
    transition: transform .2s ease, box-shadow .2s ease;
}

.class-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 38px rgba(23,32,51,.11);
}

.accent-bar {
    position: absolute;
    inset: 0 auto 0 0;
    width: 5px;
    background: var(--class-accent,#4A6CF7);
}

.card-content {
    display: grid;
    grid-template-columns: 190px minmax(220px,1.1fr) minmax(280px,1.5fr);
    align-items: center;
    gap: 22px;
    padding: 18px 22px 18px 26px;
}

.time-section {
    min-width: 0;
}

.start-time,
.time-main {
    color: var(--text,#172033);
    font-size: 1.12rem;
    font-weight: 800;
    white-space: nowrap;
}

.end-time,
.time-end {
    margin-top: 2px;
    color: var(--muted,#65718a);
    font-size: .78rem;
    font-weight: 600;
}

.duration-bar {
    width: 100%;
    max-width: 155px;
    height: 5px;
    margin-top: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--surface-soft,#f3f5fb);
}

.duration-bar .fill {
    height: 100%;
    border-radius: inherit;
    background: var(--class-accent,#4A6CF7);
}

.subject-section {
    min-width: 0;
}

.class-name {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text,#172033);
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.3;
}

.subject-name {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 7px;
    color: var(--muted,#65718a);
    font-size: .8rem;
    font-weight: 600;
}

.meta-section {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    flex-wrap: wrap;
}

.teacher-pill,
.pill,
.today-tag,
.live-indicator,
.whatsapp-community-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 7px 10px;
    border-radius: 999px;
    font-size: .73rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.teacher-pill {
    background: rgba(22,163,106,.1);
    color: #138455;
}

.pill {
    background: var(--surface-soft,#f3f5fb);
    color: var(--muted,#65718a);
}

.today-tag {
    background: rgba(81,97,206,.1);
    color: var(--primary,#5161ce);
}

.live-indicator {
    background: rgba(220,76,100,.1);
    color: #c83b53;
}

.whatsapp-community-btn {
    background: #16a36a;
    color: #fff;
}

.whatsapp-community-btn:hover {
    background: #128b59;
    color: #fff;
}

/* Empty state */
.empty-state {
    padding: 65px 20px;
    text-align: center;
    border: 1px dashed var(--panel-border-strong,rgba(23,32,51,.13));
    border-radius: 20px;
    background: var(--surface,#fff);
}

.empty-state .big-icon,
.empty-icon {
    display: block;
    margin-bottom: 14px;
    font-size: 2.2rem;
    color: var(--primary,#5161ce);
}

/* Tablet */
@media (max-width: 1000px) {
    .card-content {
        grid-template-columns: 150px minmax(190px,1fr);
        gap: 16px;
    }

    .meta-section {
        grid-column: 1 / -1;
        justify-content: flex-start;
        padding-top: 10px;
        border-top: 1px solid var(--panel-border);
    }
}

/* Mobile */
@media (max-width: 720px) {
    #app-container {
        width: calc(100% - 20px);
        padding-top: 14px;
    }

    .glass-nav {
        position: relative;
        padding: 10px;
        align-items: flex-start;
        flex-direction: column;
        gap: 7px;
    }

    .nav-links {
        width: 100%;
        justify-content: flex-start;
        overflow-x: auto;
        flex-wrap: nowrap;
    }

    .nav-links > a,
    .dark-toggle-nav {
        flex: 0 0 auto;
        min-height: 38px;
        padding: 7px 10px;
        font-size: .76rem;
    }

    .filter-bar {
        padding: 13px;
        border-radius: 16px;
    }

    .filter-bar form {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .filter-bar .btn {
        width: 100%;
    }

    .week-nav {
        justify-content: space-between;
        gap: 7px;
    }

    .week-nav .btn {
        flex: 1;
        min-width: 0;
        padding-inline: 9px;
        font-size: .75rem;
    }

    .week-label {
        order: -1;
        width: 100%;
        min-width: 0;
        font-size: .9rem;
    }

    .day-header {
        align-items: flex-start;
    }

    .day-header h3 {
        font-size: 1.2rem;
    }

    .card-content {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 16px 15px 16px 19px;
    }

    .time-section {
        display: grid;
        grid-template-columns: auto auto;
        align-items: baseline;
        column-gap: 8px;
    }

    .start-time {
        font-size: 1rem;
    }

    .duration-bar {
        grid-column: 1 / -1;
        max-width: none;
        margin-top: 7px;
    }

    .class-name {
        font-size: .94rem;
    }

    .meta-section {
        grid-column: auto;
        padding-top: 0;
        border-top: 0;
        justify-content: flex-start;
    }

    .teacher-pill,
    .pill,
    .today-tag,
    .live-indicator,
    .whatsapp-community-btn {
        white-space: normal;
    }
}

@media (max-width: 420px) {
    .day-header {
        flex-direction: column;
        gap: 7px;
    }

    .day-badges {
        justify-content: flex-start;
    }

    .meta-section {
        gap: 6px;
    }
}
</style>

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
        <?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('header'); } ?>
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
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Subject, teacher, room..." value="<?= htmlspecialchars($searchQuery) ?>" onchange="this.form.submit()">
            </div>
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
        <div class="modal-content">
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

<!-- Student timetable JavaScript -->

<script>
/* KANDY STUDENT THEME CONTROLLER v1 */
(function () {
    'use strict';

    function initStudentTheme() {
        if (window.EckTheme) {
            return;
        }
        const toggle = document.getElementById('darkModeToggle');
        const icon = document.getElementById('darkIcon');
        const label = document.getElementById('darkLabel');
        const root = document.documentElement;

        if (!toggle) {
            return;
        }

        function applyTheme(isDark) {
            const theme = isDark ? 'dark' : 'light';

            root.setAttribute('data-bs-theme', theme);

            // Keep compatibility with any existing theme code.
            root.classList.toggle('dark', isDark);

            // Remember the preference.
            try {
                localStorage.setItem('darkMode', isDark ? 'true' : 'false');
            } catch (e) {}

            // Keep the existing server-side theme cookie compatible.
            document.cookie =
                'dark_mode=' +
                (isDark ? 'true' : 'false') +
                ';path=/;max-age=31536000;SameSite=Lax';

            if (icon) {
                icon.className = isDark
                    ? 'bi bi-sun-fill'
                    : 'bi bi-moon-fill';
            }

            if (label) {
                label.textContent = isDark
                    ? 'Light'
                    : 'Dark';
            }

            toggle.setAttribute(
                'aria-label',
                isDark
                    ? 'Switch to light mode'
                    : 'Switch to dark mode'
            );

            toggle.setAttribute(
                'aria-pressed',
                isDark ? 'true' : 'false'
            );
        }

        function getSavedTheme() {
            try {
                const saved = localStorage.getItem('darkMode');

                if (saved === 'true') {
                    return true;
                }

                if (saved === 'false') {
                    return false;
                }
            } catch (e) {}

            const current =
                root.getAttribute('data-bs-theme');

            if (current === 'dark') {
                return true;
            }

            if (current === 'light') {
                return false;
            }

            return window.matchMedia &&
                window.matchMedia(
                    '(prefers-color-scheme: dark)'
                ).matches;
        }

        // Apply saved preference immediately.
        applyTheme(getSavedTheme());

        // The student page uses a BUTTON, so use click.
        toggle.addEventListener('click', function (event) {
            event.preventDefault();

            const currentlyDark =
                root.getAttribute('data-bs-theme') === 'dark';

            applyTheme(!currentlyDark);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initStudentTheme
        );
    } else {
        initStudentTheme();
    }
})();
</script>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>

</body>
</html>