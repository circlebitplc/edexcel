<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;

require_staff();
ensure_recordings_schema($pdo);
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$timetableId = (int)($_GET['lesson'] ?? 0);
$itemId = (int)($_GET['item'] ?? 0);

$recordings = new RecordingService($pdo);
$lessons = new OnlineLessonService($pdo);
$staffLesson = $timetableId > 0 ? $recordings->lessonForStaff($timetableId, $teacherId, $isAdmin) : null;
$onlineLesson = null;
$recording = null;
$error = '';

if (!$staffLesson || !OnlineLessonService::supportsDeliveryMode((string)($staffLesson['delivery_mode'] ?? 'physical'))) {
    $error = 'That video lesson was not found.';
    $staffLesson = null;
} else {
    try {
        $recording = $recordings->activeForLesson($timetableId);
        $onlineLesson = $lessons->getOrCreateForTimetable($staffLesson, $recording, (int)($_SESSION['user_id'] ?? 0));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$items = $onlineLesson ? $lessons->items((int)$onlineLesson['id']) : [];
if ($itemId < 1 && $items !== []) {
    $itemId = (int)$items[0]['id'];
}
$current = $itemId > 0 ? $lessons->item($itemId) : null;
if ($current && $onlineLesson && (int)$current['lesson_id'] !== (int)$onlineLesson['id']) {
    $current = null;
}
$payload = ($current && $onlineLesson)
    ? $lessons->previewPayload($onlineLesson, $current)
    : null;
$neighbors = $current ? OnlineLessonService::neighbors($items, (int)$current['id']) : ['prev' => 0, 'next' => 0];
$builderUrl = campus_online_lesson_url($timetableId);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3">
    <a href="<?= $h($builderUrl) ?>">&larr; Back to builder</a>
</p>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= $h($error) ?></div>
<?php elseif ($payload): ?>
<div class="alert alert-info">Preview only — answers and watch progress are not saved. Students still need to pay and watch clips.</div>
<div class="ol-shell">
    <button type="button" class="btn btn-outline-secondary ol-toc-toggle mb-2" data-ol-toc-toggle>
        <i class="bi bi-list-ol me-1"></i> Lesson contents
    </button>
    <aside class="ol-sidebar" data-ol-sidebar>
        <h2>Lesson contents</h2>
        <p class="ol-kicker mb-2"><?= $h((string)($staffLesson['subject_name'] ?? 'Class')) ?></p>
        <ol class="ol-toc">
            <?php foreach ($items as $i => $row):
                $rid = (int)$row['id'];
                $active = $rid === (int)$payload['id'];
                $type = OnlineLessonService::normalizeItemType((string)$row['item_type']);
                $kind = match ($type) {
                    'activity' => 'Question activity',
                    'page' => 'Reading',
                    'external_link' => 'External link',
                    default => 'Video clip',
                };
            ?>
            <li class="<?= $active ? 'is-active' : '' ?>">
                <a href="<?= $h(campus_online_lesson_preview_url($timetableId, $rid)) ?>">
                    <span class="ol-num"><?= $i + 1 ?></span>
                    <span class="ol-toc-label">
                        <?= $h((string)$row['title']) ?>
                        <span class="ol-toc-kind"><?= $h($kind) ?></span>
                    </span>
                    <i class="bi <?= OnlineLessonService::itemIcon($type) ?> ol-state text-muted"></i>
                </a>
            </li>
            <?php endforeach; ?>
        </ol>
    </aside>
    <section class="ol-main">
        <div class="ol-main-head">
            <div>
                <p class="ol-kicker mb-1"><?= $h((string)$onlineLesson['title']) ?></p>
                <h1><?= $h((string)$payload['title']) ?></h1>
            </div>
            <span class="badge text-bg-secondary">Preview</span>
        </div>
        <div class="ol-stage">
            <?php if ($payload['type'] === 'video'): ?>
                <?php if (!empty($payload['embed_url'])): ?>
                    <div class="ol-player ratio ratio-16x9">
                        <iframe src="<?= $h((string)$payload['embed_url']) ?>" title="<?= $h((string)$payload['title']) ?>" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">This clip is still processing.</div>
                <?php endif; ?>
            <?php elseif ($payload['type'] === 'page'): ?>
                <div class="ol-page-body"><?= nl2br($h((string)$payload['body'])) ?></div>
            <?php elseif ($payload['type'] === 'external_link'): ?>
                <div class="ol-link-card">
                    <div class="ol-link-icon" aria-hidden="true"><i class="bi bi-box-arrow-up-right"></i></div>
                    <h2 class="h4 mb-1"><?= $h((string)$payload['title']) ?></h2>
                    <p class="text-muted mb-3">External learning material</p>
                    <?php if (!empty($payload['url'])): ?>
                        <a class="btn btn-primary ol-link-open" href="<?= $h((string)$payload['url']) ?>" <?= !empty($payload['open_new_tab']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>Open Learning Material →</a>
                    <?php endif; ?>
                </div>
            <?php elseif ($payload['type'] === 'resource'): ?>
                <div class="ol-link-card">
                    <h2 class="h4 mb-1"><?= $h((string)$payload['title']) ?></h2>
                    <p class="text-muted mb-0">Students open this resource from the lesson. Preview does not record progress.</p>
                </div>
            <?php elseif (OnlineLessonService::isSubmissionActivity((string)($current['activity_type'] ?? ''))): ?>
                <p class="text-muted"><?= nl2br($h((string)($current['activity_instructions'] ?? ''))) ?></p>
                <?php if (!empty($current['due_at'])): ?><p>Due <?= $h(date('d M Y, g:i A', strtotime((string)$current['due_at']))) ?></p><?php endif; ?>
                <p class="mb-0">Maximum marks: <?= $h((string)($current['max_marks'] ?? '')) ?>. Students submit text or a file. This preview is not saved.</p>
            <?php else:
                $activity = $payload['activity'] ?? [];
                $questions = $activity['questions'] ?? [];
            ?>
                <?php if (!empty($activity['instructions'])): ?>
                    <p class="text-muted"><?= nl2br($h((string)$activity['instructions'])) ?></p>
                <?php endif; ?>
                <?php foreach ($questions as $qi => $question): ?>
                    <fieldset class="ol-question">
                        <div class="ol-q-title"><?= ($qi + 1) ?>. <?= nl2br($h((string)$question['prompt'])) ?>
                            <span class="small text-muted">(<?= $h((string)$question['marks']) ?>)</span>
                        </div>
                        <?php if (in_array((string)$question['type'], ['essay', 'short', 'exam'], true)): ?>
                            <?php if ((string)$question['type'] === 'exam' && trim((string)($question['exam_ref'] ?? '')) !== ''): ?>
                                <p class="small text-muted">Exam reference: <?= $h((string)$question['exam_ref']) ?></p>
                            <?php endif; ?>
                            <textarea class="form-control" rows="5" readonly placeholder="Students type their answer here. This preview is not saved."></textarea>
                        <?php else: ?>
                            <?php foreach (($question['choices'] ?? []) as $ci => $choice):
                                $cls = 'ol-choice';
                                if (isset($question['correct_index']) && (int)$question['correct_index'] === (int)$ci) {
                                    $cls .= ' is-correct';
                                }
                            ?>
                                <label class="<?= $cls ?>">
                                    <input type="radio" disabled <?= isset($question['correct_index']) && (int)$question['correct_index'] === (int)$ci ? 'checked' : '' ?>>
                                    <span><?= $h((string)$choice) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (!empty($question['explanation'])): ?>
                                <p class="small text-muted mt-2 mb-0"><?= nl2br($h((string)$question['explanation'])) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <nav class="ol-pager">
            <?php if ($neighbors['prev'] > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= $h(campus_online_lesson_preview_url($timetableId, $neighbors['prev'])) ?>">Previous</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($neighbors['next'] > 0): ?>
                <a class="btn btn-primary" href="<?= $h(campus_online_lesson_preview_url($timetableId, $neighbors['next'])) ?>">Next</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= $h($builderUrl) ?>">Back to builder</a>
            <?php endif; ?>
        </nav>
    </section>
</div>
<script src="<?= $h(BASE_URL . 'assets/js/online-lesson.js?v=' . filemtime(__DIR__ . '/../assets/js/online-lesson.js')) ?>"></script>
<?php else: ?>
    <div class="alert alert-warning">This lesson has no clips or questions yet.</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
