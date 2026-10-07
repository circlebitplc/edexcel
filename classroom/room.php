<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\ClassroomAccessService;
use Edexcel\Services\OnlineMeetingService;

require_login();
ensure_classroom_schema($pdo);
if (!function_exists('student_require_parent_phone')) {
    require_once __DIR__ . '/../student/device_helpers.php';
}
if (function_exists('student_require_parent_phone')) {
    student_require_parent_phone($pdo);
}
if (function_exists('student_require_presence')) {
    student_require_presence($pdo);
}

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

$mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
if (in_array($mode, ['online', 'hybrid'], true)) {
    $svc->ensureForLesson($lessonId);
}
$meeting = $svc->findByLesson($lessonId);
$access = classroom_access_context($pdo, $lesson, $meeting);
if (classroom_join_forbidden($access)) {
    classroom_render_join_denied();
}
$settings = classroom_settings($pdo);
$state = classroom_display_state($lesson, $meeting, (int)$settings['join_early_minutes']);
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = current_role();
$displayName = classroom_display_name($pdo, $userId, $access['is_host'] ? 'teacher' : $role);
$csrf = csrf_token();
$apiBase = rtrim((string)BASE_URL, '/') . '/api/classroom/';
$homeUrl = $role === 'student'
    ? rtrim((string)BASE_URL, '/') . '/student/dashboard.php'
    : rtrim((string)BASE_URL, '/') . '/dashboard.php';

$materials = classroom_lesson_material_items($pdo, (int)$lesson['class_id']);

$config = [
    'lessonId' => $lessonId,
    'publicId' => (string)($meeting['public_id'] ?? ''),
    'csrf' => $csrf,
    'apiBase' => $apiBase,
    'homeUrl' => $homeUrl,
    'payUrl' => rtrim((string)BASE_URL, '/') . '/student/pay_lesson.php',
    'isHost' => $access['is_host'],
    'role' => $access['is_host'] ? 'teacher' : $role,
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
    'state' => $state,
    'accessCode' => $access['code'],
    'accessMessage' => $access['message'],
    'amountDue' => (float)($access['amount_due'] ?? 0),
    'configured' => livekit_ready($pdo) && !empty($settings['enabled']),
    'chatEnabled' => !empty($settings['chat_enabled']),
    'whiteboardEnabled' => !empty($settings['whiteboard_enabled']),
    'studentMic' => !empty($settings['student_mic']),
    'studentCamera' => !empty($settings['student_camera']),
    'pdfWhiteboardEnabled' => !empty($settings['pdf_whiteboard_enabled']),
    'pdfMaxMb' => (int)($settings['pdf_max_mb'] ?? 30),
    'studentPdfDownload' => !empty($settings['student_pdf_download']),
    'activePdf' => is_array($meeting) ? classroom_pdf_active_state($pdo, (int)$meeting['id'], $userId) : null,
    'autoRecord' => !empty($settings['auto_record']),
    'recording' => is_array($meeting) && trim((string)($meeting['egress_id'] ?? '')) !== '',
    'studentsCanDraw' => is_array($meeting) && !empty($meeting['students_can_draw']),
    'studentShare' => !empty($settings['student_screenshare']),
    'locked' => is_array($meeting) && !empty($meeting['locked']),
    'startedAt' => ($state === 'live' && is_array($meeting))
        ? classroom_started_at_iso((string)($meeting['started_at'] ?? ''))
        : null,
];
$isHost = !empty($config['isHost']);
$waitingOn = classroom_meeting_waiting_room($meeting, $settings);
if (
    !$access['is_host']
    && $access['code'] === ClassroomAccessService::WAIT
    && ($access['wait_kind'] ?? '') !== 'pay_pending'
    && is_array($meeting)
    && ($meeting['status'] ?? '') === 'live'
    && $waitingOn
    && !empty($access['enrolled'])
) {
    $svc->markWaiting((int)$meeting['id'], $userId, classroom_identity($userId));
}
$config['waitingRoom'] = $waitingOn;
$config['waitKind'] = $access['wait_kind'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= e($config['subject']) ?> · Live class</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/classroom.css?v=<?= is_file(__DIR__ . '/../assets/css/classroom.css') ? filemtime(__DIR__ . '/../assets/css/classroom.css') : '1' ?>">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body class="ck-body<?= $isHost ? ' ck-is-host' : ' ck-is-student' ?>" data-role="<?= e($config['role']) ?>">
    <header class="ck-top" id="ckTopHeader">
        <div class="ck-brand-wrap">
            <div class="ck-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="ck-brand-text">
                <span class="ck-brand-name">Edexcel College</span>
                <span class="ck-brand-sub">Live Classroom</span>
            </div>
        </div>
        <div class="ck-class-meta">
            <strong class="ck-meta-subject"><?= e($config['subject'] ?: 'Live Class') ?></strong>
            <span class="ck-meta-teacher">Teacher: <?= e($config['teacher'] ?: 'Class Instructor') ?></span>
        </div>
        <div class="ck-top-center">
            <div class="ck-status-pill" id="ckStatus" data-state="<?= e($state) ?>">
                <span class="ck-rec-dot"></span>
                <span class="ck-status-live-text">Live</span>
                <span class="ck-elapsed" id="ckElapsed">00:42:15</span>
                <span class="ck-rec" id="ckRec" <?= empty($config['recording']) ? 'hidden' : '' ?>>REC</span>
                <span id="ckStatusLabel" class="visually-hidden"><?= e(classroom_status_label($state)) ?></span>
                <span class="ck-qos" id="ckQos" hidden></span>
            </div>
        </div>
        <?php if (!$isHost): ?>
        <div class="ck-top-actions" id="ckTopActions">
            <?php if (!empty($config['studentMic'])): ?>
            <button type="button" id="ckMic" class="ck-header-btn ck-ctrl" aria-pressed="false" title="Microphone">
                <i class="bi bi-mic-mute"></i>
                <span>Mic</span>
            </button>
            <?php endif; ?>
            <?php if (!empty($config['chatEnabled'])): ?>
            <button type="button" id="ckChatToggle" class="ck-header-btn ck-ctrl is-on" title="Chat">
                <i class="bi bi-chat-dots"></i>
                <span class="ck-count-badge ck-badge-red" id="ckHeaderChatUnread" hidden>0</span>
                <span>Chat</span>
            </button>
            <?php endif; ?>
            <div class="ck-more-dropdown-wrap" id="ckTopMoreWrap">
                <button type="button" id="ckTopMoreBtn" class="ck-header-btn" title="More options" aria-label="More options">
                    <i class="bi bi-three-dots"></i>
                    <span>More</span>
                </button>
                <div class="ck-more-dropdown" id="ckTopMoreMenu" hidden>
                    <button type="button" class="ck-dropdown-item" id="ckSettings">
                        <i class="bi bi-gear"></i> Device Settings
                    </button>
                    <button type="button" class="ck-dropdown-item" id="ckHand">
                        <i class="bi bi-hand-index"></i> Raise Hand
                    </button>
                    <div class="ck-dropdown-divider"></div>
                    <a class="ck-dropdown-item" href="<?= e($homeUrl) ?>">
                        <i class="bi bi-arrow-left"></i> Exit to Dashboard
                    </a>
                </div>
            </div>
            <a href="<?= e($homeUrl) ?>" id="ckLeave" class="ck-btn-leave" title="Leave class">
                <i class="bi bi-chevron-left"></i>
                <span>Leave</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isHost): ?>
        <!-- Teacher mobile header shortcuts -->
        <div class="ck-mobile-header-actions" id="ckMobileHeaderActions">
            <button type="button" class="ck-mobile-header-btn" id="ckMobileHeaderMenuBtn" title="Classroom Menu" aria-label="Classroom Menu">
                <i class="bi bi-grid-fill"></i>
            </button>
            <a href="<?= e($homeUrl) ?>" class="ck-mobile-header-btn ck-btn-leave-compact" id="ckMobileHeaderLeaveBtn" title="Exit" aria-label="Exit">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </header>

    <main class="ck-main ck-people-off ck-side-off" id="ckMain">
        <!-- Left Edge Activation Zone & Floating Handle -->
        <div class="ck-edge-trigger ck-edge-trigger-bottom-left" id="ckPeopleTriggerZone" title="People">
            <button type="button" class="ck-edge-handle ck-chat-corner-handle" id="ckPeopleEdgeHandle" aria-label="Toggle People Panel" title="People">
                <i class="bi bi-people-fill"></i>
                <span>People</span>
                <span class="ck-edge-badge" id="ckPeopleEdgeBadge" hidden>0</span>
            </button>
        </div>

        <aside class="ck-people-rail" id="ckPeopleRail" hidden<?= $isHost ? '' : ' aria-hidden="true"' ?>>
            <div class="ck-rail-head">
                <h2>People</h2>
                <div class="ck-rail-head-actions">
                    <button type="button" class="ck-icon-btn ck-rail-pin" id="ckPeoplePinBtn" title="Pin panel" aria-label="Pin panel" aria-pressed="false"><i class="bi bi-pin-angle"></i></button>
                    <button type="button" class="ck-icon-btn ck-rail-close" id="ckPeopleClose" aria-label="Close people"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <section class="ck-hands" id="ckHandsWrap">
                <h3>Raised hands</h3>
                <ol class="ck-hands-list" id="ckHands"></ol>
                <p class="ck-hands-empty" id="ckHandsEmpty">No hands raised</p>
            </section>
            <?php if ($isHost): ?>
            <section class="ck-hands ck-waiting" id="ckWaitingWrap" <?= empty($config['waitingRoom']) ? 'hidden' : '' ?>>
                <h3>Waiting</h3>
                <ol class="ck-hands-list" id="ckWaiting"></ol>
                <p class="ck-hands-empty" id="ckWaitingEmpty">No one is waiting</p>
            </section>
            <?php endif; ?>
            <div class="ck-panel is-on" id="ckPeoplePanel">
                <ul class="ck-people" id="ckPeople"></ul>
            </div>
            <?php if ($isHost): ?>
            <div class="ck-host-panel" id="ckHostPanel">
                <button type="button" class="ck-btn" id="ckLock" aria-pressed="<?= !empty($config['locked']) ? 'true' : 'false' ?>">
                    <?= !empty($config['locked']) ? 'Unlock class' : 'Lock class' ?>
                </button>
                <button type="button" class="ck-btn" id="ckWaitingRoom" aria-pressed="<?= !empty($config['waitingRoom']) ? 'true' : 'false' ?>">
                    <?= !empty($config['waitingRoom']) ? 'Turn off waiting room' : 'Turn on waiting room' ?>
                </button>
                <button type="button" class="ck-btn" id="ckMuteAll">Mute all</button>
                <button type="button" class="ck-btn" id="ckClearChat">Clear chat</button>
            </div>
            <?php endif; ?>
            <div class="ck-tabs ck-rail-tabs">
                <button type="button" data-panel="board">Board</button>
                <button type="button" data-panel="materials">Materials</button>
                <button type="button" data-panel="info">This class</button>
            </div>
            <div class="ck-panel" id="ckBoardPanel"></div>
            <div class="ck-panel" id="ckMaterialsPanel">
                <?php if ($materials !== []): ?>
                    <ul class="ck-list" id="ckMaterials">
                        <?php foreach ($materials as $item): ?>
                            <li>
                                <strong><?= e((string)$item['title']) ?></strong>
                                <?php if (!empty($item['due'])): ?>
                                    <span>Due <?= e(date('d M', strtotime((string)$item['due']))) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['url'])): ?>
                                    <a href="<?= e((string)$item['url']) ?>" target="_blank" rel="noopener noreferrer">Open</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ck-mat-empty">No materials for this class yet.</p>
                <?php endif; ?>
            </div>
            <div class="ck-panel" id="ckInfoPanel">
                <dl class="ck-info">
                    <dt>Subject</dt><dd><?= e($config['subject']) ?></dd>
                    <dt>Class</dt><dd><?= e($config['className']) ?></dd>
                    <dt>Teacher</dt><dd><?= e($config['teacher']) ?></dd>
                    <dt>Time</dt><dd><?= e($config['startTime']) ?> – <?= e($config['endTime']) ?></dd>
                </dl>
            </div>
        </aside>

        <button type="button" class="ck-drawer-mask" id="ckDrawerMask" hidden aria-label="Close panel"></button>

        <section class="ck-stage" id="ckStage">
            <div class="ck-hero" id="ckHero"></div>
            <div class="ck-stage-board" id="ckStageBoard" hidden>
                <div class="ck-board-stage" id="ckBoardStage">
                    <!-- Left: Sleek Compact Page Navigation Sidebar -->
                    <aside class="ck-page-nav-sidebar is-collapsed" id="ckPageNavSidebar" aria-label="Page navigation">
                        <div class="ck-pns-bar" id="ckPnsBar">
                            <button type="button" class="ck-pns-head-btn" id="ckPnsToggleBtn" title="Toggle page thumbnails" aria-expanded="false">
                                <i class="bi bi-file-earmark-text"></i>
                                <span class="ck-pns-title">Pages</span>
                                <span class="ck-pns-cur-badge" id="ckPnsCurBadge">1/1</span>
                                <i class="bi bi-chevron-down ck-pns-caret" id="ckPnsCaret"></i>
                            </button>
                            <div class="ck-pns-quick-nav">
                                <button type="button" class="ck-pns-step-btn" id="ckPnsPrevBtn" title="Previous page" aria-label="Previous page" disabled>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <button type="button" class="ck-pns-step-btn" id="ckPnsNextBtn" title="Next page" aria-label="Next page" disabled>
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                                <?php if ($isHost): ?>
                                <button type="button" class="ck-pns-step-btn ck-pns-add-icon-btn" id="ckPnsQuickAddBtn" title="Add page" aria-label="Add page">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ck-pns-drawer" id="ckPnsDrawer" hidden>
                            <div class="ck-pns-list" id="ckPageThumbnails">
                                <div class="ck-pns-item is-active" data-page="1">
                                    <div class="ck-pns-thumb">
                                        <canvas class="ck-thumb-canvas" width="72" height="45"></canvas>
                                    </div>
                                    <span class="ck-pns-num is-active">1</span>
                                </div>
                            </div>
                            <div class="ck-pns-bottom">
                                <?php if ($isHost): ?>
                                <button type="button" class="ck-btn-add-page" id="ckPageAddBtn" title="Add Page">
                                    <i class="bi bi-plus-lg"></i>
                                    <span>Add page</span>
                                </button>
                                <?php endif; ?>
                                <div hidden>
                                    <button type="button" id="ckPageRotateBtn"></button>
                                    <button type="button" id="ckPageFitBtn"></button>
                                    <button type="button" id="ckPageZoomInBtn"></button>
                                    <button type="button" id="ckPageZoomOutBtn"></button>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Center: Whiteboard Main Column -->
                    <div class="ck-board-main-col" id="ckBoardMainCol">
                        <!-- Top Horizontal White Toolbar (Teacher Only) -->
                        <?php if ($isHost): ?>
                        <div class="ck-board-top-toolbar" id="ckBoardTools" role="toolbar" aria-label="Whiteboard tools">
                            <div class="ck-wb-group ck-wb-drawing-tools" data-group="tools">
                                <button type="button" class="ck-wb-tool is-on" data-tool="select" title="Select (V)"><i class="bi bi-cursor-fill"></i><span>Select</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="pen" title="Pen (P)"><i class="bi bi-pen-fill"></i><span>Pen</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="highlight" title="Highlighter (H)"><i class="bi bi-highlighter"></i><span>Highlighter</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="erase" title="Eraser (E)"><i class="bi bi-eraser-fill"></i><span>Eraser</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="text" title="Text (T)"><i class="bi bi-type"></i><span>Text</span></button>
                                <button type="button" class="ck-wb-tool ck-wb-dropdown-btn" data-tool="rect" id="ckShapeQuickBtn" title="Shape"><i class="bi bi-square"></i><span>Shape <i class="bi bi-chevron-down ck-caret"></i></span></button>
                                <button type="button" class="ck-wb-tool" data-tool="arrow" title="Arrow (A)"><i class="bi bi-arrow-up-right"></i><span>Arrow</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="line" title="Line"><i class="bi bi-slash-lg"></i><span>Line</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="rect" title="Rectangle (R)"><i class="bi bi-square"></i><span>Rectangle</span></button>
                                <button type="button" class="ck-wb-tool" data-tool="ellipse" title="Circle (O)"><i class="bi bi-circle"></i><span>Circle</span></button>
                                
                                <button type="button" class="ck-wb-tool ck-wb-extra" data-tool="sticky" title="Sticky note (N)"><i class="bi bi-sticky-fill text-warning"></i><span>Sticky note</span></button>
                                <button type="button" class="ck-wb-tool ck-wb-extra" data-tool="laser" title="Laser pointer (L)"><i class="bi bi-record-circle-fill text-danger"></i><span>Laser</span></button>
                                <button type="button" class="ck-wb-tool ck-wb-extra" data-tool="pan" title="Hand / Pan (M, or hold Space)"><i class="bi bi-hand-index-thumb"></i><span>Hand</span></button>
                                <button type="button" class="ck-wb-tool ck-wb-extra" data-tool="h2t" title="Convert handwriting to text (W)" hidden><i class="bi bi-textarea-t"></i><span>H2T</span></button>
                            </div>

                            <div class="ck-wb-sep"></div>

                            <div class="ck-wb-group ck-wb-history" data-group="edit">
                                <button type="button" class="ck-wb-tool" id="ckBoardUndo" title="Undo (Ctrl+Z)" disabled><i class="bi bi-arrow-counterclockwise"></i><span>Undo</span></button>
                                <button type="button" class="ck-wb-tool" id="ckBoardRedo" title="Redo (Ctrl+Y)" disabled><i class="bi bi-arrow-clockwise"></i><span>Redo</span></button>
                                <button type="button" class="ck-wb-tool" id="ckBoardClear" title="Clear board (Delete)"><i class="bi bi-trash3"></i><span>Clear</span></button>
                                <button type="button" id="ckBoardDup" hidden disabled></button>
                                <button type="button" id="ckBoardDelete" hidden disabled></button>
                            </div>

                            <div class="ck-wb-sep"></div>
                            <div class="ck-wb-group ck-wb-permissions" data-group="permissions">
                                <label class="ck-allow-draw-toggle" id="ckAllowDrawWrap" title="Allow or prevent students from drawing on the whiteboard">
                                    <input type="checkbox" id="ckAllowDraw" <?= !empty($config['studentsCanDraw']) ? 'checked' : '' ?>>
                                    <span class="ck-toggle-track"><span class="ck-toggle-thumb"></span></span>
                                    <span class="ck-toggle-text">Students may draw</span>
                                </label>
                            </div>

                            <button type="button" class="ck-wb-tool ck-wb-props-tool-btn" id="ckMobilePropsBtn" title="Tool Properties" aria-label="Tool Properties"><i class="bi bi-palette-fill"></i><span>Style</span></button>
                            <button type="button" class="ck-wb-tool ck-wb-overflow-btn" id="ckBoardOverflowBtn" title="More tools" aria-label="More tools"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        <?php endif; ?>

                        <p class="ck-board-hint" id="ckBoardLocked" hidden>The teacher has locked the board. You can still watch.</p>

                        <!-- Viewport with Canvases & Floating Panels -->
                        <div class="ck-board-viewport" id="ckBoardViewport">
                            <div class="ck-board-world" id="ckBoardWorld">
                                <canvas id="ckPdfCanvas" class="ck-pdf-canvas" width="900" height="560"></canvas>
                                <canvas id="ckBoard" width="900" height="560"></canvas>
                                <div class="ck-board-overlay" id="ckBoardOverlay" aria-hidden="true"></div>
                                <div class="ck-board-lasers" id="ckBoardLasers" aria-hidden="true"></div>
                            </div>

                            <!-- Tool Properties Panel (Teacher Only) -->
                            <?php if ($isHost): ?>
                            <!-- Right-Edge Hover Trigger Zone -->
                            <div class="ck-props-trigger-zone" id="ckPropsTriggerZone" aria-hidden="true"></div>

                            <!-- Right-Edge Touch/Click Handle for Mobile & Trackpad fallback -->
                            <button type="button" class="ck-props-open-handle" id="ckPropsOpenHandle" title="Tool Properties" aria-label="Tool Properties">
                                <i class="bi bi-palette-fill"></i>
                            </button>

                            <!-- Slide-Out Floating Tool Properties Panel (Hidden by default, slides from right) -->
                            <div class="ck-tool-props-panel ck-props-hidden" id="ckToolPropsPanel" role="dialog" aria-label="Tool Properties">
                                <div class="ck-tpp-head">
                                    <span class="ck-tpp-title">Tool Properties</span>
                                    <button type="button" class="ck-tpp-close-btn" id="ckTppCloseBtn" title="Close properties" aria-label="Close properties"><i class="bi bi-chevron-down"></i></button>
                                </div>

                                <div class="ck-tpp-tool-badge" id="ckTppActiveToolBadge">
                                    <div class="ck-tpp-tool-info">
                                        <i class="bi bi-pencil-fill" id="ckTppToolIcon"></i>
                                        <span id="ckTppToolName">Pen</span>
                                    </div>
                                    <i class="bi bi-chevron-down"></i>
                                </div>

                                <div class="ck-tpp-section ck-tpp-color" id="ckTppColorSection">
                                    <span class="ck-tpp-label">Colour</span>
                                    <div class="ck-tpp-swatches" id="ckTppSwatches">
                                        <button type="button" class="ck-color-swatch" data-color="#000000" style="background:#000000;" title="Black"></button>
                                        <button type="button" class="ck-color-swatch" data-color="#ef4444" style="background:#ef4444;" title="Red"></button>
                                        <button type="button" class="ck-color-swatch is-active" data-color="#2563eb" style="background:#2563eb;" title="Blue"></button>
                                        <button type="button" class="ck-color-swatch" data-color="#10b981" style="background:#10b981;" title="Green"></button>
                                        <button type="button" class="ck-color-swatch" data-color="#facc15" style="background:#facc15;" title="Yellow"></button>
                                        <button type="button" class="ck-color-swatch" data-color="#8b5cf6" style="background:#8b5cf6;" title="Purple"></button>
                                        <button type="button" class="ck-color-swatch" data-color="#ec4899" style="background:#ec4899;" title="Pink"></button>
                                        <label class="ck-custom-color-picker" title="Custom color">
                                            <input type="color" id="ckBoardColor" value="#2563eb" aria-label="Pen color">
                                        </label>
                                    </div>
                                </div>

                                <div class="ck-tpp-section ck-tpp-stroke" id="ckTppStrokeSection">
                                    <div class="ck-tpp-row">
                                        <span class="ck-tpp-label">Stroke size</span>
                                        <span class="ck-tpp-val" id="ckBoardSizeVal">4 px</span>
                                    </div>
                                    <div class="ck-slider-wrap">
                                        <input type="range" id="ckBoardSize" min="1" max="48" value="4" aria-label="Stroke size">
                                    </div>
                                </div>

                                <div class="ck-tpp-section ck-tpp-opacity" id="ckBoardOpacityWrap">
                                    <div class="ck-tpp-row">
                                        <span class="ck-tpp-label">Opacity</span>
                                        <span class="ck-tpp-val" id="ckBoardOpacityVal">100%</span>
                                    </div>
                                    <div class="ck-slider-wrap">
                                        <input type="range" id="ckBoardOpacity" min="10" max="100" value="100" aria-label="Opacity">
                                    </div>
                                </div>

                                <!-- Advanced Settings Section with Toggles -->
                                <div class="ck-tpp-advanced" id="ckTppAdvancedSection">
                                    <div class="ck-tpp-adv-header" id="ckTppAdvToggle">
                                        <span class="ck-tpp-adv-title"><i class="bi bi-gear-wide-connected"></i> Advanced</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </div>
                                    <div class="ck-tpp-adv-body" id="ckTppAdvBody">
                                        <label class="ck-switch-row">
                                            <span>Smooth lines</span>
                                            <input type="checkbox" id="ckTppSmoothLines" checked class="ck-switch-input">
                                            <span class="ck-switch-slider"></span>
                                        </label>
                                        <label class="ck-switch-row">
                                            <span>Pressure sensitivity</span>
                                            <input type="checkbox" id="ckTppPressure" checked class="ck-switch-input">
                                            <span class="ck-switch-slider"></span>
                                        </label>
                                        <label class="ck-switch-row">
                                            <span>Fill shape</span>
                                            <input type="checkbox" id="ckTppFillShape" class="ck-switch-input">
                                            <span class="ck-switch-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Contextual Font Section for Text Tool -->
                                <div class="ck-tpp-section ck-tpp-font" id="ckTppFontSection" hidden>
                                    <span class="ck-tpp-label">Typography</span>
                                    <div class="ck-tpp-font-row">
                                        <select id="ckBoardFont" aria-label="Font family">
                                            <option value="sans" selected>Inter</option>
                                            <option value="arial">Arial</option>
                                            <option value="serif">Georgia</option>
                                            <option value="mono">Mono</option>
                                        </select>
                                        <select id="ckBoardFontSize" aria-label="Font size">
                                            <option value="14">14 px</option>
                                            <option value="18">18 px</option>
                                            <option value="24" selected>24 px</option>
                                            <option value="32">32 px</option>
                                            <option value="48">48 px</option>
                                        </select>
                                    </div>
                                    <div class="ck-tpp-fmt-row">
                                        <div class="ck-fmt-btn-group">
                                            <button type="button" class="ck-fmt-btn" id="ckFmtBold" title="Bold"><b>B</b></button>
                                            <button type="button" class="ck-fmt-btn" id="ckFmtItalic" title="Italic"><i>I</i></button>
                                            <button type="button" class="ck-fmt-btn" id="ckFmtUnderline" title="Underline"><u>U</u></button>
                                        </div>
                                        <div class="ck-fmt-btn-group">
                                            <button type="button" class="ck-align-btn is-active" data-align="left" title="Align left"><i class="bi bi-text-left"></i></button>
                                            <button type="button" class="ck-align-btn" data-align="center" title="Align center"><i class="bi bi-text-center"></i></button>
                                            <button type="button" class="ck-align-btn" data-align="right" title="Align right"><i class="bi bi-text-right"></i></button>
                                        </div>
                                    </div>
                                    <select id="ckBoardStyle" aria-label="Font style" hidden>
                                        <option value="normal" selected>Regular</option>
                                        <option value="bold">Bold</option>
                                        <option value="italic">Italic</option>
                                        <option value="bolditalic">Bold italic</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Bottom Center Floating Controls Pill (Matching Reference Image) -->
                            <div class="ck-board-bottom-pill" id="ckBoardBottomPill">
                                <button type="button" class="ck-pill-btn" id="ckBoardPrevPage" title="Previous page" aria-label="Previous page">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <span class="ck-pill-page-text" id="ckBoardPageIndicator">1 / 4</span>
                                <button type="button" class="ck-pill-btn" id="ckBoardNextPage" title="Next page" aria-label="Next page">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                                
                                <span class="ck-pill-extra">
                                    <span class="ck-pill-divider"></span>
                                    <button type="button" class="ck-pill-btn" id="ckBoardZoomOut" title="Zoom out" aria-label="Zoom out">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <button type="button" class="ck-pill-val-btn" id="ckBoardZoomReset" title="Reset zoom (100%)">
                                        100%
                                    </button>
                                    <button type="button" class="ck-pill-btn" id="ckBoardZoomIn" title="Zoom in" aria-label="Zoom in">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                    <span class="ck-pill-divider"></span>
                                    <div class="ck-pill-fit-select-wrap">
                                        <select id="ckBoardFitSelect" class="ck-pill-select" aria-label="Fit options">
                                            <option value="fit">Fit to screen</option>
                                            <option value="width">Fit to width</option>
                                            <option value="100">100%</option>
                                            <option value="150">150%</option>
                                            <option value="200">200%</option>
                                        </select>
                                    </div>
                                    <span class="ck-pill-divider"></span>
                                </span>

                                <?php if ($isHost): ?>
                                <button type="button" class="ck-pill-btn ck-pill-fs-btn" id="ckBoardFs" title="<?= $isHost ? 'Full screen (F)' : 'Full screen' ?>" aria-label="<?= $isHost ? 'Full screen (F)' : 'Full screen' ?>">
                                    <i class="bi bi-fullscreen"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="ck-pill-btn ck-pill-fs-btn ck-student-pill-fs" id="ckStudentPillFs" title="Full screen" aria-label="Full screen">
                                    <i class="bi bi-fullscreen"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="ck-pill-btn" id="ckPillDownloadPdf" title="Download PDF" aria-label="Download PDF" style="display:none;">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Bottom Action Dock Bar (Teacher Only) -->
                        <?php if ($isHost): ?>
                        <div class="ck-board-bottom-bar" id="ckBoardBottomBar">
                            <div class="ck-bbb-left">
                                <button type="button" class="ck-btn-pdf-download" id="ckBoardDownloadBtn" title="Download PDF / Export">
                                    <i class="bi bi-download"></i>
                                    <span>Download PDF</span>
                                </button>
                                <div class="ck-bbb-more-wrap" id="ckBoardMoreWrap">
                                    <button type="button" class="ck-btn-bbb-more" id="ckBoardMoreOptionsBtn" title="More options" aria-label="More options">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <div class="ck-bbb-more-menu" id="ckBoardMoreMenu" hidden>
                                        <button type="button" class="ck-bbb-menu-item" id="ckBoardExportPngBtn">
                                            <i class="bi bi-image"></i> Export PNG
                                        </button>
                                        <button type="button" class="ck-bbb-menu-item" id="ckBoardExportPdfBtn">
                                            <i class="bi bi-file-pdf"></i> Export PDF
                                        </button>
                                        <div class="ck-bbb-menu-sep"></div>
                                        <button type="button" class="ck-bbb-menu-item text-danger" id="ckBoardClearAllBtn">
                                            <i class="bi bi-trash3"></i> Clear Whiteboard
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="ck-bbb-center">
                                <div class="ck-mode-dock" id="ckBoardModeDock" role="toolbar" aria-label="Classroom modes">
                                    <button type="button" class="ck-mode-dock-btn" id="ckDockShare" title="Share screen">
                                        <i class="bi bi-display"></i>
                                        <span>Share screen</span>
                                    </button>
                                    <button type="button" class="ck-mode-dock-btn" id="ckDockRecord" title="Record class">
                                        <i class="bi bi-record-circle"></i>
                                        <span>Record</span>
                                    </button>
                                    <button type="button" class="ck-mode-dock-btn" id="ckDockSettings" title="Settings">
                                        <i class="bi bi-gear-wide-connected"></i>
                                        <span>Settings</span>
                                    </button>
                                </div>
                            </div>

                            <div class="ck-bbb-right">
                                <div class="ck-conn-status-pill" id="ckConnectionBadge" title="Network Connection: Excellent">
                                    <span class="ck-conn-dot"></span>
                                    <span class="ck-conn-label">Excellent</span>
                                </div>
                                <span class="ck-brand-tag">Edexcel College</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button type="button" class="ck-stage-exit" id="ckStageExit" hidden>Exit full view</button>
            <div class="ck-presenting" id="ckPresenting" hidden>
                <i class="bi bi-display"></i>
                <p>You are sharing your screen</p>
                <span>Students see this window. Your camera stays in the strip below.</span>
                <button type="button" class="ck-btn ck-btn-primary" id="ckStopShare">Stop sharing</button>
            </div>
            <div class="ck-grid" id="ckGrid"></div>
            <div class="ck-audio-sink" id="ckAudio"></div>
            <button type="button" class="ck-enable-sound" id="ckEnableSound" hidden>Click to enable sound</button>
            <div class="ck-lobby" id="ckLobby">
                <div class="ck-lobby-card">
                    <div class="ck-lobby-brand">
                        <span class="ck-lobby-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
                        <span class="ck-lobby-brand-name">Edexcel College</span>
                    </div>
                    <h1 class="ck-lobby-title" id="ckLobbyTitle"><?= e($config['subject'] ?: 'Live Classroom') ?></h1>
                    <p class="ck-lobby-teacher">Teacher: <strong><?= e($config['teacher'] ?: 'Class Instructor') ?></strong></p>
                    <p class="ck-kicker" id="ckKicker"><?= e($config['startTime']) ?> – <?= e($config['endTime']) ?></p>
                    <p class="ck-lead" id="ckLobbyMsg"><?= e($access['message']) ?></p>

                    <?php if ($access['code'] === ClassroomAccessService::SETUP): ?>
                        <?php if ($role === 'admin'): ?>
                            <a class="ck-btn ck-btn-primary" href="<?= e(BASE_URL) ?>admin/settings.php?tab=classroom">Open classroom settings</a>
                        <?php else: ?>
                            <p class="ck-hint">Ask the office to turn on live classes.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="ck-preview-wrap" id="ckPreviewWrap" <?= in_array($access['code'], [ClassroomAccessService::DENY, ClassroomAccessService::PAY], true) || ($access['wait_kind'] ?? '') === 'pay_pending' ? 'hidden' : '' ?>>
                            <video id="ckPreview" autoplay playsinline muted></video>
                        </div>
                        
                        <div class="ck-lobby-media-controls" id="ckLobbyMediaControls" <?= in_array($access['code'], [ClassroomAccessService::DENY, ClassroomAccessService::PAY], true) ? 'hidden' : '' ?>>
                            <button type="button" class="ck-btn ck-lobby-media-btn is-on" id="ckLobbyMicBtn" title="Toggle Microphone">
                                <i class="bi bi-mic-fill" id="ckLobbyMicIcon"></i>
                                <span id="ckLobbyMicLabel">Microphone</span>
                            </button>
                            <button type="button" class="ck-btn ck-lobby-media-btn is-on" id="ckLobbyCamBtn" title="Toggle Camera">
                                <i class="bi bi-camera-video-fill" id="ckLobbyCamIcon"></i>
                                <span id="ckLobbyCamLabel">Camera</span>
                            </button>
                        </div>

                        <div class="ck-lobby-actions" id="ckLobbyActions">
                            <?php if ($access['code'] === ClassroomAccessService::PAY): ?>
                                <?php student_pay_now_form([
                                    'timetable_id' => $lessonId,
                                    'return' => 'classroom',
                                    'button_class' => 'ck-btn ck-btn-primary',
                                ]); ?>
                                <a class="ck-btn" href="<?= e(student_class_page_url($lessonId, 'classroom')) ?>#pay-bank">Pay by bank transfer</a>
                            <?php elseif ($access['code'] === ClassroomAccessService::DENY): ?>
                                <a class="ck-btn" href="<?= e($homeUrl) ?>">Go to home</a>
                            <?php elseif ($access['code'] === ClassroomAccessService::ALLOW || $isHost || $state === 'live'): ?>
                                <button type="button" class="ck-btn ck-btn-primary" id="ckJoinBtnFallback" onclick="if(typeof window.connect==='function'){window.connect();}else if(typeof window.startClass==='function'&&<?= $isHost && $state !== 'live' ? 'true' : 'false' ?>){window.startClass();}else{window.location.reload();}">
                                    <?= $isHost ? ($state === 'live' ? 'Join class' : 'Start class') : 'Join class' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isHost): ?>
            <div class="ck-student-cam-panel" id="ckStudentCamPanel" hidden>
                <div class="ck-scp-head" id="ckStudentCamHead">
                    <div class="ck-scp-drag-title">
                        <i class="bi bi-grip-vertical"></i>
                        <strong>Student Cameras (<span id="ckScpCount">0</span>)</strong>
                    </div>
                    <div class="ck-scp-actions">
                        <div class="ck-btn-group" role="group" aria-label="Camera layout mode">
                            <button type="button" class="ck-btn ck-btn-xs is-on" id="ckScpViewGrid" title="Grid View"><i class="bi bi-grid-fill"></i></button>
                            <button type="button" class="ck-btn ck-btn-xs" id="ckScpViewSpeaker" title="Speaker View"><i class="bi bi-person-video"></i></button>
                        </div>
                        <button type="button" class="ck-icon-btn ck-scp-btn" id="ckScpPopoutBtn" title="Open Student Cameras in New Window (Secondary Monitor)" aria-label="Open in New Window">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </button>
                        <button type="button" class="ck-icon-btn ck-scp-btn" id="ckScpMinBtn" title="Minimize / Restore Panel" aria-label="Minimize">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                        <button type="button" class="ck-icon-btn ck-scp-btn" id="ckScpCloseBtn" title="Close Panel" aria-label="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="ck-scp-body" id="ckStudentCamBody">
                    <div class="ck-scp-popped-state" id="ckScpPoppedState" hidden>
                        <i class="bi bi-window-fullscreen"></i>
                        <p>Student cameras are open in a secondary window.</p>
                        <div class="ck-scp-popped-btns">
                            <button type="button" class="ck-btn ck-btn-sm" id="ckScpFocusPopoutBtn">Focus Window</button>
                            <button type="button" class="ck-btn ck-btn-sm ck-btn-primary" id="ckScpRestoreBtn">Restore to Classroom</button>
                        </div>
                    </div>
                    <div class="ck-scp-stage" id="ckScpStage">
                        <div class="ck-scp-spotlight" id="ckScpSpotlight" hidden></div>
                        <div class="ck-scp-grid" id="ckScpGrid">
                            <p class="ck-scp-empty" id="ckScpEmpty">No student cameras connected yet</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="ck-toast" id="ckToast" hidden></div>
        </section>

        <!-- Bottom Right Corner Chat Trigger Handle -->
        <div class="ck-edge-trigger ck-edge-trigger-right ck-edge-trigger-bottom-right" id="ckChatTriggerZone" title="Click or hover to open Chat">
            <button type="button" class="ck-edge-handle ck-chat-corner-handle" id="ckChatEdgeHandle" aria-label="Toggle Chat Panel" title="Class Chat & Students">
                <i class="bi bi-chat-dots-fill"></i>
                <span>Chat</span>
                <span class="ck-edge-badge" id="ckChatEdgeBadge" hidden>0</span>
            </button>
        </div>

        <!-- Right Sidebar: Dual Stacked Dark Cards (Matching Reference Image) -->
        <aside class="ck-side ck-chat-rail ck-right-sidebar ck-dual-card-sidebar" id="ckChatRail" hidden>
            <!-- Chat Rail Header with Pin & Close -->
            <div class="ck-rsb-header" id="ckRightSidebarHeader">
                <div class="ck-rsb-header-title">
                    <i class="bi bi-chat-left-text-fill"></i>
                    <span>Classroom Panel</span>
                </div>
                <div class="ck-rsb-header-actions">
                    <button type="button" class="ck-icon-btn ck-rsb-pin" id="ckChatPinBtn" title="Pin panel" aria-label="Pin panel" aria-pressed="false"><i class="bi bi-pin-angle"></i></button>
                    <button type="button" class="ck-icon-btn ck-rsb-close" id="ckChatClose" aria-label="Close sidebar" title="Slide down to close"><i class="bi bi-chevron-down"></i></button>
                </div>
            </div>

            <!-- Active speaker preview -->
            <div class="ck-student-hero-card" id="ckActiveSpeakerCard" hidden>
                <div class="ck-shc-video-wrap">
                    <video class="ck-shc-video" id="ckHeroSpeakerVideo" autoplay playsinline muted></video>
                    <div class="ck-shc-avatar" id="ckHeroSpeakerAvatar"><span id="ckHeroSpeakerInitials">T</span></div>
                </div>
                <div class="ck-shc-info">
                    <strong class="ck-shc-name" id="ckHeroSpeakerName"><?= e($config['teacher'] ?: 'Teacher') ?></strong>
                    <span class="ck-shc-mic is-speaking" id="ckHeroSpeakerMic"><i class="bi bi-mic-fill"></i></span>
                </div>
            </div>

            <!-- Card 1: Students Grid Card -->
            <section class="ck-side-card ck-students-card" id="ckCardStudents">
                <div class="ck-sc-head">
                    <div class="ck-sc-title-group">
                        <h3 class="ck-sc-title">Students</h3>
                    </div>
                    <div class="ck-sc-nav" id="ckStudentsNav">
                        <button type="button" class="ck-sc-nav-btn" id="ckStudentsPrevBtn" title="Previous page" aria-label="Previous page">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="ck-sc-page-text" id="ckStudentsPageIndicator">1 / 2</span>
                        <button type="button" class="ck-sc-nav-btn" id="ckStudentsNextBtn" title="Next page" aria-label="Next page">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="ck-sc-body" id="ckPanelStudents">
                    <div class="ck-students-subgrid ck-cols-3" id="ckStudentsSubgrid">
                        <p class="ck-students-empty" id="ckStudentsEmpty">No student cameras connected yet</p>
                    </div>
                    <div class="ck-students-more-badge" id="ckStudentsMoreBadge" hidden><span>+0 More students</span></div>
                </div>

                <div class="ck-sc-foot">
                    <button type="button" class="ck-btn-view-all" id="ckViewAllStudentsBtn" title="View all students">
                        <i class="bi bi-people-fill"></i>
                        <span>View all students</span>
                    </button>
                    <?php if ($isHost): ?>
                    <button type="button" class="ck-btn-popout-icon" id="ckSidebarPopoutBtn" title="Open Student Cameras in New Window (Secondary Monitor)" aria-label="Open in new window">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Card 2: Class Chat Card -->
            <section class="ck-side-card ck-chat-card" id="ckCardChat">
                <div class="ck-sc-head" id="ckChatHead">
                    <div class="ck-sc-title-group">
                        <h3 class="ck-sc-title">Class chat</h3>
                    </div>
                    <button type="button" class="ck-sc-collapse-btn" id="ckChatCollapseBtn" title="Toggle chat panel" aria-label="Toggle chat panel">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>

                <div class="ck-sc-body ck-chat-sc-body" id="ckPanelChat">
                    <div class="ck-chat-tabs" id="ckChatTabs" hidden>
                        <button type="button" id="ckChatTabClass" data-chat="class" class="is-on">Class</button>
                        <button type="button" id="ckChatTabPrivate" data-chat="private" <?= $isHost ? '' : 'hidden' ?>>Private</button>
                    </div>
                    <div class="ck-private-bar" id="ckPrivateBar" hidden>
                        <?php if ($isHost): ?>
                        <label class="visually-hidden" for="ckPrivatePeerSelect">Student</label>
                        <select id="ckPrivatePeerSelect"><option value="">Choose a student</option></select>
                        <?php endif; ?>
                        <p class="ck-private-with" id="ckPrivateWith" hidden></p>
                    </div>

                    <div class="ck-chat-log" id="ckChatLog"></div>
                    <div class="ck-chat-log" id="ckPrivateLog" hidden></div>
                </div>

                <div class="ck-sc-foot ck-chat-sc-foot">
                    <form class="ck-chat-form ck-modern-chat-form" id="ckChatForm">
                        <button type="button" class="ck-chat-emoji-btn" id="ckChatEmojiBtn" title="Insert emoji" aria-label="Insert emoji">
                            <i class="bi bi-emoji-smile"></i>
                        </button>
                        <label class="visually-hidden" for="ckChatInput">Message</label>
                        <input id="ckChatInput" type="text" maxlength="500" placeholder="Type a message..." autocomplete="off">
                        <button type="submit" class="ck-chat-send-btn" id="ckChatSendBtn" aria-label="Send message">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                    <?php if ($isHost): ?>
                    <button type="button" class="ck-btn-popout-icon" id="ckChatPopoutBtn" title="Open Chat in New Window (Secondary Monitor)" aria-label="Open chat in new window" hidden>
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </main>

    <div class="ck-popup-blocked-banner" id="ckPopupBlockedBanner" hidden>
        <div class="ck-pbb-inner">
            <i class="bi bi-shield-exclamation"></i>
            <div class="ck-pbb-text">
                <strong>Pop-up window blocked by your browser.</strong>
                <span>Please allow pop-ups for this site, or click the retry button below:</span>
            </div>
            <button type="button" class="ck-btn ck-btn-sm ck-btn-primary" id="ckPopupRetryBtn">Retry Opening Window</button>
            <button type="button" class="ck-icon-btn" id="ckPopupCloseBtn" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>

    <!-- Student Minimized Teacher Screen Dock (shown when student minimizes teacher screen) -->
    <?php if (!$isHost): ?>
    <div class="ck-screen-min-dock" id="ckScreenMinDock" role="region" aria-label="Teacher screen minimized" hidden>
        <div class="ck-smd-info">
            <span class="ck-smd-pulse" aria-hidden="true"></span>
            <i class="bi bi-display"></i>
            <div class="ck-smd-text">
                <strong class="ck-smd-title">Teacher Screen Active</strong>
                <span class="ck-smd-sub">Click to expand</span>
            </div>
        </div>
        <div class="ck-smd-actions">
            <button type="button" class="ck-smd-restore-btn" id="ckScreenRestoreBtn" title="Restore Teacher Screen" aria-label="Restore Teacher Screen">
                <i class="bi bi-arrows-angle-expand"></i>
                <span>Restore Screen</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <footer class="ck-dock" id="ckDock" hidden>
        <?php if ($isHost): ?>
        <div class="ck-host-dock" id="ckHostDock" role="toolbar" aria-label="Classroom controls">
            <div class="ck-host-group" aria-label="Media">
                <button type="button" id="ckMic" class="ck-header-btn ck-ctrl" aria-pressed="true" title="Microphone">
                    <i class="bi bi-mic"></i>
                    <span>Mic</span>
                </button>
                <button type="button" id="ckCam" class="ck-header-btn ck-ctrl is-on" aria-pressed="true" title="Camera (Alt+C)">
                    <i class="bi bi-camera-video"></i>
                    <span>Camera</span>
                </button>
            </div>
            <span class="ck-host-sep" aria-hidden="true"></span>
            <div class="ck-host-group" aria-label="Teaching">
                <button type="button" class="ck-header-btn ck-ctrl is-on" id="ckDockWhiteboard" title="Whiteboard">
                    <i class="bi bi-easel2-fill"></i>
                    <span>Board</span>
                </button>
                <button type="button" class="ck-header-btn ck-ctrl" id="ckPdfUploadBtn" title="Upload PDF">
                    <i class="bi bi-file-earmark-arrow-up-fill"></i>
                    <span>PDF</span>
                </button>
                <button type="button" id="ckShare" class="ck-header-btn ck-ctrl" title="Share screen (Alt+S)">
                    <i class="bi bi-display"></i>
                    <span>Share</span>
                </button>
                <button type="button" id="ckSharePause" class="ck-header-btn ck-ctrl" aria-pressed="false" title="Pause screen for students (Alt+P)" aria-label="Pause screen for students (Alt+P)" hidden>
                    <i class="bi bi-pause-circle"></i>
                    <span>Pause</span>
                </button>
            </div>
            <span class="ck-host-sep" aria-hidden="true"></span>
            <div class="ck-host-group" aria-label="Communication">
                <button type="button" id="ckPeopleToggle" class="ck-header-btn ck-ctrl" title="Participants">
                    <i class="bi bi-people"></i>
                    <span class="ck-count-badge ck-badge-green" id="ckHeaderParticipantsCount" hidden>0</span>
                    <span>People</span>
                </button>
                <?php if (!empty($config['chatEnabled'])): ?>
                <button type="button" id="ckChatToggle" class="ck-header-btn ck-ctrl is-on" title="Chat">
                    <i class="bi bi-chat-dots"></i>
                    <span class="ck-count-badge ck-badge-red" id="ckHeaderChatUnread" hidden>0</span>
                    <span>Chat</span>
                </button>
                <?php endif; ?>
            </div>
            <div class="ck-host-fold" id="ckHostFold">
                <button type="button" class="ck-header-btn ck-host-fold-btn" id="ckHostFoldBtn" title="More classroom controls" aria-expanded="false" aria-controls="ckHostFoldBody">
                    <i class="bi bi-three-dots"></i>
                    <span>More</span>
                </button>
                <div class="ck-host-fold-body" id="ckHostFoldBody">
                    <span class="ck-host-sep" aria-hidden="true"></span>
                    <div class="ck-host-group" aria-label="Classroom view">
                        <button type="button" id="ckViewPresentation" class="ck-header-btn ck-ctrl is-on" data-view="presentation" aria-pressed="true" title="Presentation view">
                            <i class="bi bi-easel"></i>
                            <span>Present</span>
                        </button>
                        <button type="button" id="ckViewSpeaker" class="ck-header-btn ck-ctrl" data-view="speaker" aria-pressed="false" title="Speaker view">
                            <i class="bi bi-person-video"></i>
                            <span>Speaker</span>
                        </button>
                        <button type="button" id="ckViewGallery" class="ck-header-btn ck-ctrl" data-view="gallery" aria-pressed="false" title="Gallery view">
                            <i class="bi bi-grid"></i>
                            <span>Gallery</span>
                        </button>
                    </div>
                    <span class="ck-host-sep" aria-hidden="true"></span>
                    <div class="ck-host-group" aria-label="Tools">
                        <button type="button" class="ck-header-btn ck-ctrl" id="ckTopMonitorBtn" title="Teaching Monitor">
                            <i class="bi bi-window-desktop"></i>
                            <span>Monitor</span>
                        </button>
                        <button type="button" class="ck-header-btn ck-ctrl" id="ckStudentCamsToggle" title="Floating Cameras">
                            <i class="bi bi-person-video3"></i>
                            <span>Cameras</span>
                        </button>
                        <button type="button" class="ck-header-btn ck-ctrl" id="ckRecStart" title="Start recording" <?= !empty($config['recording']) ? 'hidden' : '' ?>>
                            <i class="bi bi-record-circle"></i>
                            <span>Record</span>
                        </button>
                        <button type="button" class="ck-header-btn ck-ctrl" id="ckRecStop" title="Stop recording" <?= empty($config['recording']) ? 'hidden' : '' ?>>
                            <i class="bi bi-stop-circle-fill"></i>
                            <span>Stop</span>
                        </button>
                        <button type="button" class="ck-header-btn ck-ctrl" id="ckSettings" title="Device settings">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </button>
                        <a class="ck-header-btn ck-ctrl" href="<?= e($homeUrl) ?>" title="Exit to dashboard">
                            <i class="bi bi-arrow-left"></i>
                            <span>Exit</span>
                        </a>
                    </div>
                </div>
            </div>
            <span class="ck-host-sep" aria-hidden="true"></span>
            <div class="ck-host-group ck-host-group-session" aria-label="Session">
                <button type="button" id="ckEnd" class="ck-btn-end" title="End class for everyone">
                    <span>End class</span>
                </button>
                <a href="<?= e($homeUrl) ?>" id="ckLeave" class="ck-btn-leave" title="Leave class">
                    <span>Leave</span>
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- Desktop Student Display Modes -->
        <div class="ck-student-display-modes ck-desktop-only-views" id="ckStudentDisplayModes" role="group" aria-label="Display View Mode">
            <button type="button" class="ck-sd-btn is-active" id="ckSdModeBoard" data-sd-mode="board" title="Feature Whiteboard" aria-pressed="true">
                <i class="bi bi-easel2-fill"></i>
                <span>Board</span>
            </button>
            <button type="button" class="ck-sd-btn" id="ckSdModeTeacher" data-sd-mode="teacher" title="Feature Teacher Video" aria-pressed="false">
                <i class="bi bi-person-video"></i>
                <span>Teacher</span>
            </button>
            <button type="button" class="ck-sd-btn" id="ckSdModeShare" data-sd-mode="share" title="Feature Shared Screen" aria-pressed="false">
                <i class="bi bi-display"></i>
                <span>Shared Screen</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Dedicated Mobile Bottom Navigation Bar (Visible on screens <= 860px) -->
        <?php if ($isHost): ?>
        <div class="ck-mobile-dock-bar ck-host-mobile-bar" id="ckMobileDockBar" role="toolbar" aria-label="Teacher mobile controls">
            <button type="button" class="ck-mob-dock-btn" id="ckMobileMic" title="Microphone" aria-label="Microphone">
                <i class="bi bi-mic"></i>
                <span>Mic</span>
            </button>

            <button type="button" class="ck-mob-dock-btn is-on" id="ckMobileCam" title="Camera" aria-label="Camera">
                <i class="bi bi-camera-video"></i>
                <span>Camera</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobileShare" title="Share Screen" aria-label="Share Screen">
                <i class="bi bi-display"></i>
                <span>Share</span>
            </button>

            <button type="button" class="ck-mob-dock-btn is-active" id="ckMobileBoardToggle" title="Whiteboard" aria-label="Whiteboard">
                <i class="bi bi-easel2-fill"></i>
                <span>Board</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobilePeople" title="Participants" aria-label="Participants">
                <div class="ck-mob-btn-icon-wrap">
                    <i class="bi bi-people-fill"></i>
                    <span class="ck-mob-badge ck-badge-green" id="ckMobilePeopleBadge">0</span>
                </div>
                <span>People</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobileChat" title="Chat" aria-label="Chat">
                <div class="ck-mob-btn-icon-wrap">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span class="ck-mob-badge ck-badge-red" id="ckMobileChatBadge" hidden>0</span>
                </div>
                <span>Chat</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobileMoreBtn" title="More Options" aria-label="More Options">
                <i class="bi bi-grid-fill"></i>
                <span>More</span>
            </button>
        </div>
        <?php else: ?>
        <!-- Student mobile navigation: Board | Screen | Teacher | Raise Hand | Audio | More -->
        <div class="ck-mobile-dock-bar ck-student-mobile-bar" id="ckMobileDockBar" role="toolbar" aria-label="Student classroom controls">
            <button type="button" class="ck-mob-dock-btn is-active" id="ckMobBtnBoard" data-sd-mode="board" title="Whiteboard" aria-pressed="true">
                <i class="bi bi-easel2-fill"></i>
                <span>Board</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobBtnShare" data-sd-mode="share" title="Shared Screen" aria-pressed="false">
                <i class="bi bi-display"></i>
                <span>Screen</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobBtnTeacher" data-sd-mode="teacher" title="Teacher camera" aria-pressed="false">
                <i class="bi bi-person-video"></i>
                <span>Teacher</span>
            </button>

            <button type="button" class="ck-mob-dock-btn" id="ckMobBtnHand" title="Raise Hand" aria-pressed="false">
                <i class="bi bi-hand-index-thumb"></i>
                <span id="ckMobHandLabel">Raise Hand</span>
            </button>

            <?php if (!empty($config['studentMic'])): ?>
            <button type="button" class="ck-mob-dock-btn" id="ckMobBtnAudio" title="Microphone" aria-label="Toggle microphone" aria-pressed="false">
                <i class="bi bi-mic-mute-fill"></i>
                <span>Audio</span>
            </button>
            <?php endif; ?>

            <button type="button" class="ck-mob-dock-btn" id="ckMobBtnMore" title="More Options" aria-label="More Options">
                <i class="bi bi-three-dots"></i>
                <span>More</span>
                <?php if (!empty($config['chatEnabled'])): ?>
                <span class="ck-mob-badge ck-badge-red" id="ckMobileChatBadge" hidden>0</span>
                <?php endif; ?>
            </button>
        </div>
        <?php endif; ?>
    </footer>

    <!-- Mobile More Menu Bottom Sheet Modal -->
    <div class="ck-mobile-sheet-backdrop" id="ckMobileMoreBackdrop" hidden>
        <div class="ck-mobile-sheet" id="ckMobileMoreSheet" role="dialog" aria-modal="true" aria-labelledby="ckMobileMoreTitle">
            <div class="ck-sheet-handle-bar"><span class="ck-sheet-handle"></span></div>
            <div class="ck-sheet-head">
                <h3 class="ck-sheet-title" id="ckMobileMoreTitle"><i class="bi bi-grid-fill"></i> Classroom Menu</h3>
                <button type="button" class="ck-icon-btn ck-sheet-close" id="ckMobileMoreCloseBtn" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="ck-sheet-body">
                <?php if ($isHost): ?>
                <!-- Teacher Mobile Options -->
                <div class="ck-sheet-section">
                    <span class="ck-sheet-sec-title">Teaching & Whiteboard</span>
                    <div class="ck-sheet-grid">
                        <label class="ck-sheet-toggle-item" id="ckMobileDrawWrap">
                            <div class="ck-sheet-item-icon text-success"><i class="bi bi-pencil-square"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Students May Draw</strong>
                                <span>Allow student drawing on board</span>
                            </div>
                            <input type="checkbox" id="ckMobileDrawToggle" class="ck-switch-input" <?= !empty($config['studentsCanDraw']) ? 'checked' : '' ?>>
                            <span class="ck-switch-slider"></span>
                        </label>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileRecBtn">
                            <div class="ck-sheet-item-icon text-danger"><i class="bi bi-record-circle-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong id="ckMobileRecLabel"><?= !empty($config['recording']) ? 'Stop Recording' : 'Start Recording' ?></strong>
                                <span>Record classroom session</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileLockBtn">
                            <div class="ck-sheet-item-icon"><i class="bi bi-lock-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong id="ckMobileLockLabel"><?= !empty($config['locked']) ? 'Unlock Class' : 'Lock Class' ?></strong>
                                <span>Control student entry</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileWaitBtn">
                            <div class="ck-sheet-item-icon"><i class="bi bi-person-check-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong id="ckMobileWaitLabel"><?= !empty($config['waitingRoom']) ? 'Turn Off Waiting Room' : 'Turn On Waiting Room' ?></strong>
                                <span>Screen students before joining</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileMuteAllBtn">
                            <div class="ck-sheet-item-icon text-warning"><i class="bi bi-mic-mute-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Mute All Students</strong>
                                <span>Silence all connected mics</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileClearBoardBtn">
                            <div class="ck-sheet-item-icon text-danger"><i class="bi bi-trash3-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Clear Whiteboard</strong>
                                <span>Erase all strokes on current page</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileExportPngBtn">
                            <div class="ck-sheet-item-icon text-primary"><i class="bi bi-image"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Export PNG</strong>
                                <span>Save board image to device</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileExportPdfBtn">
                            <div class="ck-sheet-item-icon text-primary"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Export PDF</strong>
                                <span>Download full whiteboard document</span>
                            </div>
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <!-- Student Mobile Options -->
                <div class="ck-sheet-section">
                    <span class="ck-sheet-sec-title">Classroom Actions</span>
                    <div class="ck-sheet-grid">
                        <?php if (!empty($config['chatEnabled'])): ?>
                        <button type="button" class="ck-sheet-action-item" id="ckMobBtnChat">
                            <div class="ck-sheet-item-icon text-info"><i class="bi bi-chat-dots-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Chat</strong>
                                <span>Open class chat</span>
                            </div>
                        </button>
                        <?php endif; ?>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileDownloadPdfBtn" <?= empty($config['activePdf']) ? 'style="display:none;"' : '' ?>>
                            <div class="ck-sheet-item-icon text-info"><i class="bi bi-download"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Download Lesson PDF</strong>
                                <span>Download lesson document</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobilePagesSheetBtn">
                            <div class="ck-sheet-item-icon text-primary"><i class="bi bi-file-earmark-text-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Page Selector</strong>
                                <span>Browse and jump to lesson pages</span>
                            </div>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Common Settings & Navigation -->
                <div class="ck-sheet-section">
                    <span class="ck-sheet-sec-title">Preferences & Room</span>
                    <div class="ck-sheet-grid">
                        <button type="button" class="ck-sheet-action-item" id="ckMobileFsBtn">
                            <div class="ck-sheet-item-icon text-primary"><i class="bi bi-fullscreen"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong id="ckMobileFsLabel">Fullscreen</strong>
                                <span>Maximize classroom display</span>
                            </div>
                        </button>

                        <button type="button" class="ck-sheet-action-item" id="ckMobileSettingsBtn">
                            <div class="ck-sheet-item-icon"><i class="bi bi-gear-fill"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>Device Settings</strong>
                                <span>Select microphone, camera, speaker</span>
                            </div>
                        </button>

                        <?php if ($isHost): ?>
                        <button type="button" class="ck-sheet-action-item text-danger" id="ckMobileEndClassBtn">
                            <div class="ck-sheet-item-icon"><i class="bi bi-power"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong>End Class For Everyone</strong>
                                <span>Conclude session and save logs</span>
                            </div>
                        </button>
                        <?php endif; ?>

                        <a class="ck-sheet-action-item text-danger" id="ckMobileLeaveClassBtn" href="<?= e($homeUrl) ?>">
                            <div class="ck-sheet-item-icon"><i class="bi bi-box-arrow-left"></i></div>
                            <div class="ck-sheet-item-text">
                                <strong><?= $isHost ? 'Exit to Dashboard' : 'Leave Class' ?></strong>
                                <span>Return to dashboard</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="ck-bottom-footer" id="ckBottomFooter">
        <span class="ck-bf-left">Teaching Today for a Brighter Tomorrow</span>
        <span class="ck-bf-center">A modern, clean and intuitive whiteboard experience for online education</span>
        <span class="ck-bf-right">Edexcel College</span>
    </footer>

    <div class="ck-modal" id="ckSettingsModal" hidden>
        <div class="ck-modal-card" role="dialog" aria-labelledby="ckSettingsTitle" aria-modal="true">
            <h2 id="ckSettingsTitle">Device settings</h2>
            <video id="ckSettingsPreview" autoplay playsinline muted></video>
            <label class="ck-field">Microphone
                <select id="ckMicSelect"></select>
            </label>
            <label class="ck-field">Camera
                <select id="ckCamSelect"></select>
            </label>
            <label class="ck-field">Speakers
                <select id="ckSpeakerSelect"></select>
            </label>
            <button type="button" class="ck-btn ck-btn-primary" id="ckSettingsClose">Done</button>
        </div>
    </div>

    <script>window.CK_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/livekit-client@2.15.4/dist/livekit-client.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom-board.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom-board.js') ? filemtime(__DIR__ . '/../assets/js/classroom-board.js') : '1' ?>"></script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom-board-text.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom-board-text.js') ? filemtime(__DIR__ . '/../assets/js/classroom-board-text.js') : '1' ?>"></script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom-board-edu.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom-board-edu.js') ? filemtime(__DIR__ . '/../assets/js/classroom-board-edu.js') : '1' ?>"></script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom-board-h2t.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom-board-h2t.js') ? filemtime(__DIR__ . '/../assets/js/classroom-board-h2t.js') : '1' ?>"></script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom-board-pdf.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom-board-pdf.js') ? filemtime(__DIR__ . '/../assets/js/classroom-board-pdf.js') : '1' ?>"></script>
    <script src="<?= e(BASE_URL) ?>assets/js/classroom.js?v=<?= is_file(__DIR__ . '/../assets/js/classroom.js') ? filemtime(__DIR__ . '/../assets/js/classroom.js') : '1' ?>"></script>
    <?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
    <?php
    if (!function_exists('student_session_guard_script')) {
        require_once __DIR__ . '/../student/device_helpers.php';
    }
    student_session_guard_script();
    if ($isHost && function_exists('student_device_emergency_popup_script')) {
        student_device_emergency_popup_script('staff');
    }
    ?>
</body>
</html>
