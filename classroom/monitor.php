<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\OnlineMeetingService;

require_login();
ensure_classroom_schema($pdo);

$svc = new OnlineMeetingService($pdo);
$lessonId = (int)($_GET['lesson'] ?? 0);
$publicId = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['m'] ?? '')) ?? '';
if ($lessonId < 1 && $publicId !== '') {
    $found = $svc->findByPublicId($publicId);
    $lessonId = (int)($found['timetable_id'] ?? 0);
}
if ($lessonId < 1) {
    http_response_code(404);
    echo 'Class not found.';
    exit;
}

$lesson = $svc->lesson($lessonId);
if (!$lesson) {
    http_response_code(404);
    echo 'Class not found.';
    exit;
}

$meeting = $svc->findByLesson($lessonId);
$access = classroom_access_context($pdo, $lesson, $meeting);

// Enforce teacher/host authorization. Unauthorized students or users cannot view monitoring windows.
if (empty($access['is_host'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access Denied</title><style>body{background:#0e1218;color:#f4f6fb;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center;"><h2>Access Denied</h2><p>Only the teacher or host can access the monitoring window.</p></div></body></html>';
    exit;
}

$mode = (string)($_GET['mode'] ?? 'monitor');
if (!in_array($mode, ['camera', 'chat', 'monitor'], true)) {
    $mode = 'monitor';
}

$settings = classroom_settings($pdo);
$state = classroom_display_state($lesson, $meeting, (int)$settings['join_early_minutes']);
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = current_role();
$displayName = classroom_display_name($pdo, $userId, 'teacher');
$csrf = csrf_token();
$apiBase = rtrim((string)BASE_URL, '/') . '/api/classroom/';
$classroomUrl = rtrim((string)BASE_URL, '/') . '/classroom/room.php?lesson=' . $lessonId;

$pageTitles = [
    'camera' => 'Student Cameras',
    'chat' => 'Class Chat',
    'monitor' => 'Teaching Monitor (Secondary Screen)',
];

$config = [
    'lessonId' => $lessonId,
    'publicId' => (string)($meeting['public_id'] ?? ''),
    'mode' => $mode,
    'csrf' => $csrf,
    'apiBase' => $apiBase,
    'classroomUrl' => $classroomUrl,
    'isHost' => true,
    'role' => 'teacher',
    'userId' => $userId,
    'hostUserId' => is_array($meeting) ? (int)($meeting['host_user_id'] ?? 0) : 0,
    'hostIdentity' => (is_array($meeting) && (int)($meeting['host_user_id'] ?? 0) > 0)
        ? classroom_identity((int)$meeting['host_user_id'])
        : '',
    'displayName' => $displayName,
    'subject' => (string)($lesson['subject_name'] ?? 'Class'),
    'className' => (string)($lesson['class_name'] ?? ''),
    'teacher' => (string)(($lesson['substitute_name'] ?: $lesson['teacher_name']) ?? ''),
    'startTime' => date('g:i A', strtotime((string)$lesson['start_time'])),
    'endTime' => date('g:i A', strtotime((string)$lesson['end_time'])),
    'chatEnabled' => !empty($settings['chat_enabled']),
    'state' => $state,
];
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= e($pageTitles[$mode] ?? 'Teaching Monitor') ?> · <?= e($config['subject']) ?> · Edexcel College</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/classroom.css?v=<?= is_file(__DIR__ . '/../assets/css/classroom.css') ? filemtime(__DIR__ . '/../assets/css/classroom.css') : '1' ?>">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body class="ck-body ck-is-host ck-monitor-window ck-monitor-<?= e($mode) ?>" data-monitor-mode="<?= e($mode) ?>">
    <header class="ck-top ck-monitor-top">
        <div class="ck-brand">Edexcel College · <?= e($pageTitles[$mode] ?? 'Monitor') ?></div>
        <div class="ck-title">
            <strong><?= e($config['subject']) ?></strong>
            <span><?= e($config['className']) ?> · <?= e($config['teacher']) ?></span>
        </div>
        <div class="ck-status" id="ckMonitorStatus">
            <span class="ck-audio-indicator" id="ckMonitorAudioPill" title="Audio stays in the main classroom window to eliminate echo and feedback">
                <i class="bi bi-volume-mute-fill"></i>
                <span>Audio: Main Window</span>
            </span>
            <span class="ck-sync-badge is-connected" id="ckSyncBadge" title="Live synchronization with classroom active">
                <i class="bi bi-link-45deg"></i>
                <span id="ckSyncStatusText">Synchronized</span>
            </span>
            <button type="button" class="ck-btn ck-btn-sm" id="ckFocusOpenerBtn" title="Bring main classroom window to front">
                <i class="bi bi-window-stack"></i> Main Window
            </button>
            <button type="button" class="ck-btn ck-btn-sm ck-btn-danger" id="ckClosePopoutBtn" title="Close this window and restore feeds to main classroom">
                <i class="bi bi-x-circle"></i> Dock Back
            </button>
        </div>
    </header>

    <div class="ck-monitor-offline-banner" id="ckMonitorOfflineBanner" hidden>
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>Main classroom window disconnected or closed. Feeds may be paused.</span>
        <button type="button" class="ck-btn ck-btn-sm ck-btn-primary" id="ckReconnectOpenerBtn">Reconnect</button>
        <a class="ck-btn ck-btn-sm" href="<?= e($classroomUrl) ?>" target="_blank" rel="noopener">Open Classroom</a>
    </div>

    <main class="ck-monitor-main" id="ckMonitorMain">
        <?php if ($mode === 'camera' || $mode === 'monitor'): ?>
        <section class="ck-monitor-cam-section" id="ckMonitorCamSection">
            <div class="ck-monitor-section-head">
                <div class="ck-monitor-head-title">
                    <h2><i class="bi bi-people-video"></i> Student Cameras (<span id="ckStudentCamCount">0</span>)</h2>
                </div>
                <div class="ck-monitor-head-actions">
                    <div class="ck-btn-group" role="group" aria-label="Camera view mode">
                        <button type="button" class="ck-btn ck-btn-sm is-on" id="ckCamViewGrid" title="Grid View">
                            <i class="bi bi-grid-fill"></i> Grid
                        </button>
                        <button type="button" class="ck-btn ck-btn-sm" id="ckCamViewSpeaker" title="Speaker View (enlarges active speaker)">
                            <i class="bi bi-person-video"></i> Speaker
                        </button>
                    </div>
                    <label class="ck-monitor-sort-wrap" title="Sort student cameras">
                        <span>Sort:</span>
                        <select id="ckCamSortSelect" class="ck-monitor-sort-select">
                            <option value="activity">Speaking / Activity</option>
                            <option value="name">Student Name (A–Z)</option>
                            <option value="cam_first">Active Cameras First</option>
                        </select>
                    </label>
                    <button type="button" class="ck-btn ck-btn-sm" id="ckUnpinAllBtn" hidden title="Reset pinned student">
                        <i class="bi bi-pin-angle"></i> Unpin
                    </button>
                </div>
            </div>

            <div class="ck-monitor-cam-stage" id="ckMonitorCamStage">
                <div class="ck-monitor-spotlight" id="ckMonitorSpotlight" hidden>
                    <!-- Spotlighted or speaker-focused student video tile lives here -->
                </div>
                <div class="ck-monitor-grid" id="ckMonitorGrid">
                    <div class="ck-monitor-empty" id="ckMonitorEmpty">
                        <i class="bi bi-camera-video-off"></i>
                        <p>No students connected yet</p>
                        <span>Student camera feeds will appear here automatically when students join.</span>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($mode === 'chat' || $mode === 'monitor'): ?>
        <aside class="ck-monitor-chat-section" id="ckMonitorChatSection">
            <div class="ck-monitor-section-head">
                <div class="ck-monitor-head-title">
                    <h2><i class="bi bi-chat-dots-fill"></i> Class Chat</h2>
                </div>
                <div class="ck-tabs ck-chat-tabs" id="ckMonitorChatTabs">
                    <button type="button" id="ckMonitorTabClass" data-chat="class" class="is-on">Class</button>
                    <button type="button" id="ckMonitorTabPrivate" data-chat="private">Private</button>
                </div>
            </div>

            <?php if ($mode === 'monitor'): ?>
            <div class="ck-monitor-hands-panel" id="ckMonitorHandsPanel">
                <div class="ck-hands-head">
                    <h3><i class="bi bi-hand-index-thumb-fill"></i> Raised Hands (<span id="ckMonitorHandCount">0</span>)</h3>
                </div>
                <ol class="ck-hands-list" id="ckMonitorHandsList"></ol>
                <p class="ck-hands-empty" id="ckMonitorHandsEmpty">No hands raised</p>
            </div>
            <?php endif; ?>

            <div class="ck-monitor-chat-body" id="ckMonitorChatBody">
                <div class="ck-private-bar" id="ckMonitorPrivateBar" hidden>
                    <label class="visually-hidden" for="ckMonitorPrivateSelect">Select student</label>
                    <select id="ckMonitorPrivateSelect">
                        <option value="">Choose a student</option>
                    </select>
                </div>
                <div class="ck-chat-log" id="ckMonitorChatLog"></div>
                <div class="ck-chat-log" id="ckMonitorPrivateLog" hidden></div>

                <form class="ck-announce-form" id="ckMonitorAnnounceForm">
                    <label class="visually-hidden" for="ckMonitorAnnounceInput">Announcement</label>
                    <input id="ckMonitorAnnounceInput" type="text" maxlength="500" placeholder="Announce to the class" autocomplete="off">
                    <button type="submit" class="ck-btn ck-btn-primary">Announce</button>
                </form>

                <form class="ck-chat-form" id="ckMonitorChatForm">
                    <label class="visually-hidden" for="ckMonitorChatInput">Message</label>
                    <input id="ckMonitorChatInput" type="text" maxlength="500" placeholder="Message the class" autocomplete="off">
                    <button type="submit" class="ck-icon-btn" aria-label="Send"><i class="bi bi-send"></i></button>
                </form>
            </div>
        </aside>
        <?php endif; ?>
    </main>

    <div class="ck-toast" id="ckMonitorToast" hidden></div>

    <script>window.CK_MONITOR_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom-monitor.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom-monitor.js') ? filemtime(__DIR__ . '/../assets/js/classroom-monitor.js') : '1' ?>"></script>
    <?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>

