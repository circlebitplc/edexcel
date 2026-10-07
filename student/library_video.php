<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\TeacherVideoLibraryService;

require_student();
ensure_recordings_schema($pdo);
if (function_exists('student_require_presence')) {
    student_require_presence($pdo);
}

$studentId = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$library = new TeacherVideoLibraryService($pdo);
$video = $library->find($id);
if (!$video || !$library->studentCanWatch($studentId, $video)) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger">You are not authorised to watch this video.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$embed = '';
try {
    $libraryId = trim((string)($video['bunny_library_id'] ?? ''));
    $bunny = $libraryId !== ''
        ? BunnyVideoService::forLibraryId($pdo, $libraryId)
        : BunnyVideoService::tryForTeacher($pdo, (int)($video['teacher_id'] ?? 0));
    if ($bunny !== null) {
        $embed = $bunny->signedEmbedUrl((string)$video['bunny_video_id']);
    }
} catch (Throwable $e) {
    $embed = '';
}

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= htmlspecialchars(BASE_URL . 'student/dashboard.php?tab=recordings') ?>">&larr; Recordings</a></p>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <h1 class="h4"><?= htmlspecialchars((string)$video['title']) ?></h1>
    <p class="text-muted"><?= htmlspecialchars((string)($video['teacher_name'] ?? '')) ?> · <?= htmlspecialchars((string)($video['subject_name'] ?? '')) ?></p>
    <?php if ($embed !== ''): ?>
        <div class="bunny-player ratio ratio-16x9 rounded-4 overflow-hidden bg-dark">
            <iframe src="<?= htmlspecialchars($embed) ?>" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">This video is not available yet.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
