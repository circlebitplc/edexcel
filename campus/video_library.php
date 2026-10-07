<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\TeacherVideoLibraryService;

require_staff();
ensure_recordings_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$error = '';
$success = '';
$library = new TeacherVideoLibraryService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token.';
    } elseif ($library->archive((int)($_POST['video_id'] ?? 0), $teacherId, $isAdmin)) {
        $success = 'Video removed from your library.';
    } else {
        $error = 'You cannot remove that video.';
    }
}

$videos = $library->listForTeacher($teacherId, $isAdmin);
$classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$bunny = bunny_config($pdo);
$teacherLibraryReady = $teacherId > 0 && bunny_teacher_is_configured($pdo, $teacherId);

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-collection-play me-2"></i>My video library</h1>
        <p class="text-muted mb-0">Reusable videos that belong to you. These are separate from lesson class recordings.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL . 'campus/recordings.php') ?>">Class recordings</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (empty($bunny['enabled'])): ?>
    <div class="alert alert-warning">Bunny Stream is disabled. An administrator must enable it under Settings → Bunny.net.</div>
<?php elseif (!$isAdmin && !$teacherLibraryReady): ?>
    <div class="alert alert-warning">You do not have a Bunny Stream library yet. Ask an administrator to assign a unique library ID on Teachers → Edit.</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Upload video</h2>
        <form id="libraryUploadForm">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input class="form-control" name="title" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Visibility</label>
                    <select class="form-select" name="visibility" id="libraryVisibility">
                        <option value="private">Private (only you)</option>
                        <option value="class_students">Students in a class</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subject (optional)</label>
                    <select class="form-select" name="subject_id">
                        <option value="">—</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class (required for class students)</label>
                    <select class="form-select" name="class_id" id="libraryClass">
                        <option value="">—</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tags</label>
                    <input class="form-control" name="tags" placeholder="IAL, revision">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Video file</label>
                    <input class="form-control" type="file" id="libraryFile" accept="video/*">
                </div>
            </div>
            <div class="progress mt-3" style="height:8px;"><div class="progress-bar" id="libraryProgress" style="width:0%"></div></div>
            <p class="small text-muted mt-2" id="libraryStatus">Default visibility is private. Public sharing is not offered.</p>
            <button class="btn btn-primary mt-2" type="submit">Upload to my library</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Title</th><th>Class</th><th>Visibility</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($videos as $v): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars((string)$v['title']) ?>
                        <?php if (!empty($v['subject_name'])): ?><div class="small text-muted"><?= htmlspecialchars((string)$v['subject_name']) ?></div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)($v['class_name'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string)$v['visibility']) ?></td>
                    <td><span class="badge text-bg-<?= $v['status'] === 'ready' ? 'success' : ($v['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= htmlspecialchars((string)$v['status']) ?></span></td>
                    <td class="text-end">
                        <?php if ((string)$v['status'] === 'ready'): ?>
                            <a class="btn btn-sm btn-success" href="<?= htmlspecialchars(BASE_URL . 'campus/watch_library.php?id=' . (int)$v['id']) ?>">Watch</a>
                        <?php endif; ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this video from your library?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="video_id" value="<?= (int)$v['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($videos === []): ?>
                <tr><td colspan="5" class="text-muted p-4">No library videos yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tus-js-client@4.3.1/dist/tus.min.js"></script>
<script src="<?= htmlspecialchars(BASE_URL . 'assets/js/bunny-tus-upload.js') ?>"></script>
<script>
edexcelBunnyUpload({
    form: document.getElementById('libraryUploadForm'),
    fileInput: document.getElementById('libraryFile'),
    progressEl: document.getElementById('libraryProgress'),
    statusEl: document.getElementById('libraryStatus'),
    endpoint: <?= json_encode(BASE_URL . 'ajax/bunny_upload.php') ?>,
    completeEndpoint: <?= json_encode(BASE_URL . 'ajax/bunny_upload_complete.php') ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    extra: {
        kind: 'library',
        visibility: function () { return document.getElementById('libraryVisibility').value; },
        class_id: function () { return document.getElementById('libraryClass').value; },
        subject_id: function () { var s = document.querySelector('[name="subject_id"]'); return s ? s.value : ''; },
        tags: function () { var t = document.querySelector('[name="tags"]'); return t ? t.value : ''; }
    },
    onDone: function () { setTimeout(function () { location.reload(); }, 1200); }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
