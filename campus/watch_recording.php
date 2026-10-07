<?php
declare(strict_types=1);

/**
 * Staff preview of a class recording. No student payment check.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;

require_staff();
ensure_recordings_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$recordingId = (int)($_GET['id'] ?? 0);
$recordings = new RecordingService($pdo);
$recording = $recordings->find($recordingId);
$sub = (int)($recording['substitute_teacher_id'] ?? 0);

if (
    !$recording
    || $recording['deleted_at'] !== null
    || (string)$recording['status'] === 'deleted'
    || !RecordingAccessService::teacherCanManageLesson(
        $teacherId,
        (int)$recording['lesson_teacher_id'],
        $isAdmin,
        $sub
    )
) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">You cannot watch this recording.</div>';
    echo '<p><a href="' . htmlspecialchars(BASE_URL . 'campus/recordings.php') . '">&larr; Class recordings</a></p>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

try {
    $recordings->syncFromBunny($recordingId);
    $recording = $recordings->find($recordingId) ?: $recording;
} catch (Throwable $e) {
}

$embedUrls = [];
$error = '';
try {
    $embedUrls = $recordings->playableEmbedUrls($recordingId, true);
} catch (Throwable $e) {
    $error = 'The player could not be prepared. Check this teacher’s Bunny library credentials.';
}

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= htmlspecialchars(BASE_URL . 'campus/recordings.php') ?>">&larr; Class recordings</a></p>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
        <div>
            <h1 class="h4 mb-1"><?= htmlspecialchars((string)$recording['title']) ?></h1>
            <p class="text-muted mb-0">
                <?= htmlspecialchars((string)$recording['subject_name']) ?>
                · <?= htmlspecialchars((string)$recording['class_name']) ?>
                · <?= htmlspecialchars(date('d F Y', strtotime((string)$recording['date']))) ?>
                · <?= htmlspecialchars(date('g:i A', strtotime((string)$recording['start_time'])) . ' – ' . date('g:i A', strtotime((string)$recording['end_time']))) ?>
            </p>
        </div>
        <span class="badge text-bg-info">Teacher preview</span>
    </div>
    <p class="small text-muted">Students do not see this until the lesson fee is paid or waived (teacher Class fees, OnePay, or monthly wallet). You can watch it now to check the upload.</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($embedUrls === []): ?>
        <div class="alert alert-warning">This recording is not ready to play yet. If Bunny has finished processing, click Refresh on the recordings list.</div>
    <?php else: ?>
        <?php foreach ($embedUrls as $url): ?>
            <div class="bunny-player ratio ratio-16x9 mb-3 rounded-4 overflow-hidden bg-dark">
                <iframe src="<?= htmlspecialchars($url) ?>" title="Class recording" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
