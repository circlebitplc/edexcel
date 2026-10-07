<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\RecordingService;

require_staff();
ensure_recordings_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$error = '';
$success = '';
$recordings = new RecordingService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['action'] ?? ''), ['remove', 'refresh'], true)) {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token.';
    } else {
        $rid = (int)($_POST['recording_id'] ?? 0);
        $action = (string)$_POST['action'];
        if ($action === 'remove') {
            if ($recordings->archive($rid, $teacherId, $isAdmin)) {
                log_audit($pdo, 'recording_archive', 'class_recordings', $rid);
                $success = 'Recording archived.';
            } else {
                $error = 'You cannot remove that recording.';
            }
        } elseif ($action === 'refresh') {
            $row = $recordings->find($rid);
            if (!$row) {
                $error = 'You cannot refresh that recording.';
            } elseif (!\Edexcel\Services\RecordingAccessService::teacherCanManageLesson(
                $teacherId,
                (int)$row['lesson_teacher_id'],
                $isAdmin,
                (int)($row['substitute_teacher_id'] ?? 0)
            )) {
                $error = 'You cannot refresh that recording.';
            } elseif ($recordings->syncFromBunny($rid)) {
                $success = 'Recording status refreshed from Bunny.';
            } else {
                $error = 'Could not read this video from Bunny. Check that this teacher’s library ID and AccessKey are saved.';
            }
        }
    }
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : date('Y-m-d', strtotime('-21 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : date('Y-m-d');
$lessons = $recordings->teacherLessons($teacherId, $isAdmin, $from, $to);

$autoSynced = 0;
foreach ($lessons as $lesson) {
    $st = (string)($lesson['recording_status'] ?? '');
    if (!in_array($st, ['processing', 'uploading', 'draft'], true) || empty($lesson['recording_id'])) {
        continue;
    }
    if ($recordings->syncFromBunny((int)$lesson['recording_id'])) {
        $autoSynced++;
    }
    if ($autoSynced >= 2) {
        break;
    }
}
if ($autoSynced > 0) {
    $lessons = $recordings->teacherLessons($teacherId, $isAdmin, $from, $to);
    if ($success === '') {
        $success = 'Recording status was updated from Bunny.';
    }
}

$bunny = bunny_config($pdo);
$uploadId = (int)($_GET['lesson'] ?? 0);
$teacherLibraryReady = $teacherId > 0 && bunny_teacher_is_configured($pdo, $teacherId);

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-camera-reels me-2"></i>Class recordings</h1>
        <p class="text-muted mb-0">Upload the recording for a specific lesson. Use <strong>Watch</strong> to preview it. Students can watch after you mark their class fee, they pay online, or the monthly wallet covers it. Attendance does not block access.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL . 'campus/video_library.php') ?>">My video library</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (empty($bunny['enabled'])): ?>
    <div class="alert alert-warning">Bunny Stream is disabled. An administrator must enable it under Settings → Bunny.net.</div>
<?php elseif (!$isAdmin && !$teacherLibraryReady): ?>
    <div class="alert alert-warning">You do not have a Bunny Stream library yet. Ask an administrator to assign a unique library ID on Teachers → Edit before you can upload videos.</div>
<?php endif; ?>

<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-auto">
        <label class="form-label mb-0">From</label>
        <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">To</label>
        <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary" type="submit">Show lessons</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lesson</th>
                    <th>Class</th>
                    <th>Recording</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <?php
                    $status = (string)($lesson['recording_status'] ?? '');
                    $badge = match ($status) {
                        'ready' => 'success',
                        'processing', 'uploading' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    };
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(date('D, d M Y', strtotime((string)$lesson['date']))) ?><br>
                        <span class="small text-muted"><?= htmlspecialchars(date('g:i A', strtotime((string)$lesson['start_time'])) . ' – ' . date('g:i A', strtotime((string)$lesson['end_time']))) ?></span>
                    </td>
                    <td>
                        <?= htmlspecialchars((string)$lesson['subject_name']) ?>
                        <?php if ($isAdmin): ?><div class="small text-muted"><?= htmlspecialchars((string)$lesson['teacher_name']) ?></div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)$lesson['class_name']) ?></td>
                    <td>
                        <?php if ($status !== ''): ?>
                            <?php if ($status === 'ready' && !empty($lesson['recording_id'])): ?>
                                <a href="<?= htmlspecialchars(BASE_URL . 'campus/watch_recording.php?id=' . (int)$lesson['recording_id']) ?>" class="badge text-bg-success text-decoration-none">ready</a>
                            <?php else: ?>
                                <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($lesson['duration_seconds'])): ?>
                                <div class="small text-muted"><?= (int)floor(((int)$lesson['duration_seconds']) / 60) ?> min</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($status === 'ready' && !empty($lesson['recording_id'])): ?>
                            <a class="btn btn-sm btn-success" href="<?= htmlspecialchars(BASE_URL . 'campus/watch_recording.php?id=' . (int)$lesson['recording_id']) ?>">Watch</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars(BASE_URL . 'campus/lesson_fees.php?date=' . urlencode((string)$lesson['date']) . '&lesson=' . (int)$lesson['id']) ?>">Mark fees</a>
                        <a class="btn btn-sm btn-primary" href="?from=<?= urlencode($from) ?>&amp;to=<?= urlencode($to) ?>&amp;lesson=<?= (int)$lesson['id'] ?>#upload">Upload</a>
                        <?php if (\Edexcel\Services\OnlineLessonService::supportsDeliveryMode((string)($lesson['delivery_mode'] ?? 'physical'))): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(campus_online_lesson_url((int)$lesson['id'])) ?>">Build lesson</a>
                        <?php endif; ?>
                        <?php if (!empty($lesson['recording_id'])): ?>
                            <?php if (in_array($status, ['processing', 'uploading', 'draft'], true)): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="refresh">
                                    <input type="hidden" name="recording_id" value="<?= (int)$lesson['recording_id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Refresh</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Archive this recording?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="recording_id" value="<?= (int)$lesson['recording_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($lessons === []): ?>
                <tr><td colspan="5" class="text-muted p-4">No lessons in this date range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$uploadLesson = null;
if ($uploadId > 0) {
    $uploadLesson = $recordings->lessonForStaff($uploadId, $teacherId, $isAdmin);
}
if ($uploadLesson):
    $existing = $recordings->activeForLesson((int)$uploadLesson['id']);
    $lessonTeacherId = (int)($uploadLesson['teacher_id'] ?? 0);
    $lessonLibraryReady = $lessonTeacherId > 0 && bunny_teacher_is_configured($pdo, $lessonTeacherId);
?>
<div class="card border-0 shadow-sm mt-4" id="upload">
    <div class="card-body">
        <h2 class="h5">Upload recording</h2>
        <p class="text-muted">
            <?= htmlspecialchars((string)$uploadLesson['subject_name']) ?> ·
            <?= htmlspecialchars((string)$uploadLesson['class_name']) ?> ·
            <?= htmlspecialchars(date('d M Y', strtotime((string)$uploadLesson['date']))) ?>
            <?= htmlspecialchars(date('g:i A', strtotime((string)$uploadLesson['start_time']))) ?>
        </p>
        <?php if (empty($bunny['enabled'])): ?>
            <div class="alert alert-warning">Bunny Stream is disabled in settings.</div>
        <?php elseif (!$lessonLibraryReady): ?>
            <div class="alert alert-warning">This lesson’s teacher does not have a Bunny Stream library. Assign a unique library ID on Teachers → Edit before uploading.</div>
        <?php endif; ?>
        <form id="recordingUploadForm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input class="form-control" name="title" value="<?= htmlspecialchars($existing['title'] ?? ($uploadLesson['subject_name'] . ' — ' . date('d M Y', strtotime((string)$uploadLesson['date'])))) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description (optional)</label>
                <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars((string)($existing['description'] ?? '')) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Video file</label>
                <input class="form-control" type="file" id="recordingFile" accept="video/*">
            </div>
            <div class="progress mb-2" style="height:8px;"><div class="progress-bar" id="recordingProgress" style="width:0%"></div></div>
            <p class="small text-muted" id="recordingStatus">The file uploads directly to Bunny Stream. The college server never stores the video.</p>
            <button class="btn btn-primary" type="submit">Start upload</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.3.1/dist/tus.min.js"></script>
<script src="<?= htmlspecialchars(BASE_URL . 'assets/js/bunny-tus-upload.js') ?>"></script>
<script>
edexcelBunnyUpload({
    form: document.getElementById('recordingUploadForm'),
    fileInput: document.getElementById('recordingFile'),
    progressEl: document.getElementById('recordingProgress'),
    statusEl: document.getElementById('recordingStatus'),
    endpoint: <?= json_encode(BASE_URL . 'ajax/bunny_upload.php') ?>,
    completeEndpoint: <?= json_encode(BASE_URL . 'ajax/bunny_upload_complete.php') ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    extra: { kind: 'recording', timetable_id: <?= (int)$uploadLesson['id'] ?> },
    onDone: function () { setTimeout(function () { location.reload(); }, 1200); }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
