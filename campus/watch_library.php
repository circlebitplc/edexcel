<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\TeacherVideoLibraryService;

require_staff();
ensure_recordings_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$library = new TeacherVideoLibraryService($pdo);
$video = $library->find($id);

if (
    !$video
    || $video['deleted_at'] !== null
    || !RecordingAccessService::teacherOwnsLibraryItem($teacherId, (int)$video['teacher_id'], $isAdmin)
) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">You cannot watch this video.</div>';
    echo '<p><a href="' . htmlspecialchars(BASE_URL . 'campus/video_library.php') . '">&larr; My video library</a></p>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$embed = '';
$error = '';
try {
    $libraryId = trim((string)($video['bunny_library_id'] ?? ''));
    $bunny = $libraryId !== ''
        ? BunnyVideoService::forLibraryId($pdo, $libraryId)
        : BunnyVideoService::tryForTeacher($pdo, (int)$video['teacher_id']);
    if ($bunny !== null) {
        $embed = $bunny->signedEmbedUrl((string)$video['bunny_video_id']);
    } else {
        $error = 'This teacher’s Bunny library is not configured.';
    }
} catch (Throwable $e) {
    $error = 'The player could not be prepared.';
}

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= htmlspecialchars(BASE_URL . 'campus/video_library.php') ?>">&larr; My video library</a></p>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <h1 class="h4 mb-1"><?= htmlspecialchars((string)$video['title']) ?></h1>
    <p class="text-muted"><?= htmlspecialchars((string)($video['teacher_name'] ?? '')) ?> · <?= htmlspecialchars((string)($video['subject_name'] ?? '')) ?></p>
    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($embed === ''): ?>
        <div class="alert alert-warning">This video is not available yet.</div>
    <?php else: ?>
        <div class="bunny-player ratio ratio-16x9 rounded-4 overflow-hidden bg-dark">
            <iframe src="<?= htmlspecialchars($embed) ?>" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
