<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonAccessService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;
use Edexcel\Services\StudentStudyService;

require_student();
ensure_recordings_schema($pdo);
ensure_online_lesson_schema($pdo);
if (function_exists('student_require_presence')) {
    student_require_presence($pdo);
}

$studentId = (int)($_SESSION['user_id'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? $_GET['id'] ?? 0);
$itemId = (int)($_GET['item'] ?? 0);
$from = strtolower(trim((string)($_GET['from'] ?? 'recordings')));
if (!in_array($from, ['timetable', 'overview', 'fees', 'recordings', 'classroom', 'class'], true)) {
    $from = 'recordings';
}

$backHref = BASE_URL . 'student/class.php?lesson=' . $lessonId . '&from=' . rawurlencode($from === 'class' ? 'timetable' : $from);
$backLabel = 'Class';
if ($from === 'recordings') {
    $backHref = BASE_URL . 'student/dashboard.php?tab=recordings';
    $backLabel = 'Class recordings';
}

if ($lessonId < 1) {
    header('Location: ' . BASE_URL . 'student/dashboard.php?tab=recordings');
    exit;
}

$recordings = new RecordingService($pdo);
$fees = new StudentLessonFeeService($pdo);
$lessons = new OnlineLessonService($pdo);
$timetable = $recordings->findLesson($lessonId);

if (
    !$timetable
    || !empty($timetable['deleted_at'])
    || !$fees->isEnrolled($studentId, $timetable)
    || !OnlineLessonService::supportsDeliveryMode((string)($timetable['delivery_mode'] ?? 'physical'))
) {
    $_SESSION['student_error'] = 'That video lesson was not found, or you are not enrolled.';
    header('Location: ' . $backHref);
    exit;
}

$onlineLesson = $lessons->findPublishedByTimetable($lessonId);
$recording = $recordings->activeForLesson($lessonId);
if (!$onlineLesson) {
    header('Location: ' . ($recording
        ? BASE_URL . 'student/recording.php?id=' . (int)$recording['id'] . '&from=' . rawurlencode($from)
        : student_class_page_url($lessonId, $from)));
    exit;
}

$fee = $fees->resolve($studentId, $timetable, $recording ? (int)$recording['id'] : null);
$state = OnlineLessonService::feeUnlockState($fee);
$availability = OnlineLessonService::availabilityWindow($onlineLesson, $timetable);
$unmetPrerequisites = [];
try {
    $unmetPrerequisites = (new LessonAccessService($pdo))->unmetFor($studentId, (int)$onlineLesson['id']);
} catch (Throwable $e) {
    error_log('online lesson prerequisites: ' . $e->getMessage());
}
if ($unmetPrerequisites !== [] && !empty($availability['open'])) {
    $names = array_map(static fn (array $row): string => (string)$row['title'], $unmetPrerequisites);
    $availability['open'] = false;
    $availability['message'] = 'Complete ' . implode(', ', $names) . ' before starting this lesson.';
}
$study = new StudentStudyService($pdo);

$items = $lessons->items((int)$onlineLesson['id']);
$completedIds = $lessons->completedItemIds($studentId, (int)$onlineLesson['id']);
$sequential = (int)$onlineLesson['sequential'] === 1;
$resumeId = OnlineLessonService::resumeItemId($items, $completedIds);
if ($itemId < 1) {
    $itemId = $resumeId;
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }
        if ($state !== RecordingAccessService::ACCESS_GRANTED) {
            throw new RuntimeException('Pay for this class to continue the lesson.');
        }
        if (empty($availability['open'])) {
            throw new RuntimeException((string)($availability['message'] ?: 'This lesson is not open yet.'));
        }
        $action = (string)($_POST['action'] ?? '');
        $postItemId = (int)($_POST['item_id'] ?? 0);
        if ($postItemId < 1 || !OnlineLessonService::canOpenItem($items, $completedIds, $postItemId, $sequential)) {
            throw new RuntimeException('Finish the previous part of the lesson first.');
        }
        $postItem = $lessons->item($postItemId);
        if (!$postItem || (int)$postItem['lesson_id'] !== (int)$onlineLesson['id']) {
            throw new RuntimeException('That lesson item was not found.');
        }
        $neighbors = OnlineLessonService::neighbors($items, $postItemId);

        if ($action === 'start_attempt') {
            $lessons->startAttempt($studentId, $postItemId);
            header('Location: ' . student_online_lesson_url($lessonId, $postItemId, $from));
            exit;
        }

        if ($action === 'toggle_bookmark') {
            $saved = $study->toggleBookmark($studentId, (int)$onlineLesson['id'], $postItemId, max(0, (int)($_POST['question_id'] ?? 0)));
            $successMessage = $saved ? 'Bookmarked. Find it under My bookmarks.' : 'Bookmark removed.';
            $itemId = $postItemId;
        }

        if ($action === 'save_note') {
            $scope = (string)($_POST['note_scope'] ?? 'item');
            $study->saveNote(
                $studentId,
                (int)$onlineLesson['id'],
                $scope === 'section' ? (int)($postItem['section_id'] ?? 0) : 0,
                $scope === 'item' ? $postItemId : 0,
                (string)($_POST['body'] ?? '')
            );
            $successMessage = 'Note saved. Only you can see it.';
            $itemId = $postItemId;
        }

        if ($action === 'complete_item') {
            $lessons->completeNavigableItem($studentId, $onlineLesson, $postItem);
            $next = $neighbors['next'];
            header('Location: ' . student_online_lesson_url($lessonId, $next > 0 ? $next : 0, $from));
            exit;
        }

        if ($action === 'submit_activity') {
            $answers = [];
            foreach (($_POST['q'] ?? []) as $qid => $payload) {
                $qid = (int)$qid;
                if ($qid < 1 || !is_array($payload)) {
                    continue;
                }
                $answers[$qid] = [
                    'choice' => array_key_exists('choice', $payload) ? (int)$payload['choice'] : null,
                    'essay' => (string)($payload['essay'] ?? ''),
                ];
            }
            $result = $lessons->submitActivity($studentId, $postItemId, $answers);
            if (!empty($result['passed'])) {
                $successMessage = 'Answers submitted. You can continue.';
                $completedIds = $lessons->completedItemIds($studentId, (int)$onlineLesson['id']);
            } elseif (!empty($result['awaiting_mark'])) {
                $successMessage = 'Submitted. Your teacher will mark the written answers.';
            } else {
                $errorMessage = 'Some answers were incorrect. Try again to continue.';
            }
            $itemId = $postItemId;
        }

        if ($action === 'submit_work') {
            if (!OnlineLessonService::isSubmissionActivity((string)($postItem['activity_type'] ?? ''))) {
                throw new RuntimeException('That is not an assignment.');
            }
            $modules = new LearningModuleService($pdo);
            $modules->submitWork($studentId, $postItemId, $postItem, (string)($_POST['body_text'] ?? ''), $_FILES['work_file'] ?? null);
            $lessons->markItemComplete($studentId, $postItemId);
            $successMessage = 'Work submitted.';
            $completedIds = $lessons->completedItemIds($studentId, (int)$onlineLesson['id']);
            $itemId = $postItemId;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $itemId = (int)($_POST['item_id'] ?? $itemId);
    }
    $items = $lessons->items((int)$onlineLesson['id']);
    $completedIds = $lessons->completedItemIds($studentId, (int)$onlineLesson['id']);
}

if ($itemId > 0 && !OnlineLessonService::canOpenItem($items, $completedIds, $itemId, $sequential)) {
    $itemId = $resumeId;
}

$current = $itemId > 0 ? $lessons->item($itemId) : null;
if ($current && (int)$current['lesson_id'] !== (int)$onlineLesson['id']) {
    $current = null;
    $itemId = $resumeId;
    $current = $itemId > 0 ? $lessons->item($itemId) : null;
}

$allDone = $items !== [] && count($completedIds) >= count($items);
if ($current) {
    $lessons->touchProgress($studentId, (int)$onlineLesson['id'], (int)$current['id']);
    if ($state === RecordingAccessService::ACCESS_GRANTED && !empty($availability['open'])) {
        try {
            $lessons->noteItemOpen($studentId, (int)$current['id']);
        } catch (Throwable $e) {
            error_log('online lesson open: ' . $e->getMessage());
        }
    }
}

$payload = $current
    && $state === RecordingAccessService::ACCESS_GRANTED
    && !empty($availability['open'])
    ? $lessons->playerPayload($studentId, $onlineLesson, $current, false)
    : null;
$neighbors = $current ? OnlineLessonService::neighbors($items, (int)$current['id']) : ['prev' => 0, 'next' => 0];
$percent = OnlineLessonService::percentComplete(count($completedIds), count($items));
$bookmarkKeys = [];
$studentNotes = [];
if ($payload) {
    try {
        $bookmarkKeys = $study->bookmarkKeys($studentId, (int)$onlineLesson['id']);
        $studentNotes = $study->notesForLesson($studentId, (int)$onlineLesson['id']);
    } catch (Throwable $e) {
        error_log('online lesson study tools: ' . $e->getMessage());
    }
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= $h($backHref) ?>">&larr; <?= $h($backLabel) ?></a></p>

<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?= $h($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?= $h($errorMessage) ?></div>
<?php endif; ?>

<?php if ($state === RecordingAccessService::NOT_AUTHORIZED): ?>
    <div class="alert alert-danger">You are not authorised to open this lesson.</div>
<?php elseif ($state === RecordingAccessService::RECORDING_PROCESSING): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4"><?= $h((string)$onlineLesson['title']) ?></h1>
        <p class="text-muted mb-0">The class video is still being processed. Please check again shortly.</p>
    </div>
<?php elseif ($state === RecordingAccessService::RECORDING_UNAVAILABLE): ?>
    <div class="alert alert-warning">This lesson is currently unavailable. Please contact the college.</div>
<?php elseif ($state === RecordingAccessService::PAYMENT_PENDING): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4"><?= $h((string)$onlineLesson['title']) ?></h1>
        <p><?= $h(\Edexcel\Services\StudentLessonFeeService::pendingPaywallMessage()) ?></p>
        <a class="btn btn-outline-primary" href="<?= $h(BASE_URL . 'student/payment_result.php') ?>">Check payment</a>
    </div>
<?php elseif ($state === RecordingAccessService::PAYMENT_REQUIRED): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4 mb-1">Payment required</h1>
        <p class="text-muted"><?= $h((string)($timetable['subject_name'] ?? '')) ?>  <?= $h(date('d F Y', strtotime((string)$timetable['date']))) ?></p>
        <p><?= $h(\Edexcel\Services\StudentLessonFeeService::paywallMessage()) ?></p>
        <?php if (classroom_normalize_delivery_mode((string)($timetable['delivery_mode'] ?? '')) === 'online'): ?>
            <p class="mb-1">Online class</p>
            <p class="text-muted small mb-1">This is the class fee for this session.</p>
        <?php endif; ?>
        <p class="fs-3 fw-bold">Rs <?= number_format((float)$fee['amount_due'], 2) ?></p>
        <?php if ($recording): ?>
            <?php student_pay_now_form([
                'timetable_id' => $lessonId,
                'recording_id' => (int)$recording['id'],
                'return' => 'class',
            ]); ?>
            <p class="mt-3 mb-0"><a href="<?= $h(student_class_page_url($lessonId, $from)) ?>#pay-bank">Pay by bank transfer instead</a></p>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= $h(student_class_page_url($lessonId, $from)) ?>#pay">Pay for this lesson</a>
        <?php endif; ?>
    </div>
<?php elseif (empty($availability['open'])): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h1 class="h4"><?= $h((string)$onlineLesson['title']) ?></h1>
        <p class="mb-0"><?= $h((string)($availability['message'] ?: 'This lesson is not open yet.')) ?></p>
        <?php if ($unmetPrerequisites !== []): ?>
            <ul class="mt-3 mb-0">
                <?php foreach ($unmetPrerequisites as $need): ?>
                    <li>
                        <a href="<?= $h(student_online_lesson_url((int)$need['timetable_id'], 0, $from)) ?>"><?= $h((string)$need['title']) ?></a>
                        · you have completed <?= (int)$need['percent'] ?>%, <?= (int)$need['min_percent'] ?>% needed
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="ol-shell"
     data-ol-time
     data-item-id="<?= (int)($current['id'] ?? 0) ?>"
     data-endpoint="<?= $h(BASE_URL . 'ajax/online_lesson_time.php') ?>"
     data-csrf="<?= $h(csrf_token()) ?>">
    <button type="button" class="btn btn-outline-secondary ol-toc-toggle mb-2" data-ol-toc-toggle>
        <i class="bi bi-list-ol me-1"></i> Lesson contents
    </button>
    <aside class="ol-sidebar" data-ol-sidebar>
        <h2>Lesson contents</h2>
        <p class="ol-kicker mb-2"><?= $h((string)($timetable['subject_name'] ?? 'Class')) ?></p>
        <div class="ol-progress">
            <div class="ol-progress-top">
                <span><?= count($completedIds) ?> / <?= count($items) ?> complete</span>
                <span><?= (int)$percent ?>%</span>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="<?= (int)$percent ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= (int)$percent ?>%"></div>
            </div>
        </div>
        <ol class="ol-toc">
            <?php foreach ($items as $i => $row):
                $rid = (int)$row['id'];
                $open = OnlineLessonService::canOpenItem($items, $completedIds, $rid, $sequential);
                $done = in_array($rid, $completedIds, true);
                $active = $current && $rid === (int)$current['id'];
                $type = OnlineLessonService::normalizeItemType((string)$row['item_type']);
                $kind = match ($type) {
                    'activity' => 'Question activity',
                    'page' => 'Reading',
                    'external_link' => 'External link',
                    default => 'Video clip',
                };
                $href = $open ? student_online_lesson_url($lessonId, $rid, $from) : '';
            ?>
            <li class="<?= $active ? 'is-active' : '' ?> <?= $done ? 'is-done' : '' ?> <?= $open ? '' : 'is-locked' ?>">
                <?php if ($href !== ''): ?>
                    <a href="<?= $h($href) ?>">
                <?php else: ?>
                    <span class="ol-toc-row">
                <?php endif; ?>
                    <span class="ol-num"><?= $i + 1 ?></span>
                    <span class="ol-toc-label">
                        <?= $h((string)$row['title']) ?>
                        <span class="ol-toc-kind"><?= $h($kind) ?></span>
                    </span>
                    <?php if ($done): ?>
                        <i class="bi bi-check-circle-fill ol-state"></i>
                    <?php elseif (!$open): ?>
                        <i class="bi bi-lock-fill ol-state text-muted"></i>
                    <?php else: ?>
                        <i class="bi <?= OnlineLessonService::itemIcon($type) ?> ol-state text-muted"></i>
                    <?php endif; ?>
                <?php if ($href !== ''): ?>
                    </a>
                <?php else: ?>
                    </span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <p class="small mt-3 mb-0"><a href="<?= $h(BASE_URL . 'student/bookmarks.php') ?>"><i class="bi bi-bookmark me-1"></i>My bookmarks and notes</a></p>
    </aside>

    <section class="ol-main">
        <?php if ($allDone && (!$current || in_array((int)$current['id'], $completedIds, true)) && (int)($_GET['item'] ?? 0) < 1): ?>
            <div class="ol-complete border-0 shadow-none p-4">
                <div class="display-6 mb-2">?</div>
                <h1 class="h3">Lesson complete</h1>
                <p class="text-muted">You have finished every clip and question in this class.</p>
                <a class="btn btn-primary" href="<?= $h($backHref) ?>">Back to class</a>
            </div>
        <?php elseif ($payload): ?>
            <?php
                $doneCount = count($completedIds);
                $itemCount = count($items);
                $progressPercent = OnlineLessonService::percentComplete($doneCount, $itemCount);
                $remainMinutes = OnlineLessonService::remainingMinutes($items, $completedIds);
                $sectionName = '';
                if ($current && (int)($current['section_id'] ?? 0) > 0) {
                    $sectionStmt = $pdo->prepare('SELECT title FROM online_lesson_sections WHERE id = ? AND lesson_id = ? LIMIT 1');
                    $sectionStmt->execute([(int)$current['section_id'], (int)$onlineLesson['id']]);
                    $sectionName = (string)$sectionStmt->fetchColumn();
                }
            ?>
            <div class="ol-main-head">
                <div>
                    <p class="ol-kicker mb-1"><?= $h((string)$onlineLesson['title']) ?><?= $sectionName !== '' ? ' · ' . $h($sectionName) : '' ?></p>
                    <h1><?= $h((string)$payload['title']) ?></h1>
                    <p class="small text-muted mb-0">Progress <?= $progressPercent ?>% · <?= $doneCount ?> / <?= $itemCount ?> activities<?= $remainMinutes > 0 ? ' · about ' . $remainMinutes . ' minutes left' : '' ?></p>
                </div>
                <div class="d-flex flex-column align-items-end gap-2">
                    <?php if (!empty($payload['completed'])): ?>
                        <span class="badge text-bg-success">Completed</span>
                    <?php endif; ?>
                    <?php $itemBookmarked = isset($bookmarkKeys[(int)$payload['id'] . ':0']); ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_bookmark">
                        <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                        <button class="btn btn-sm <?= $itemBookmarked ? 'btn-warning' : 'btn-outline-secondary' ?>" type="submit">
                            <i class="bi <?= $itemBookmarked ? 'bi-bookmark-fill' : 'bi-bookmark' ?> me-1"></i><?= $itemBookmarked ? 'Remove bookmark' : 'Bookmark' ?>
                        </button>
                    </form>
                </div>
            </div>
            <div class="ol-stage">
                <?php if ($payload['type'] === 'video'): ?>
                    <?php
                        $watch = $payload['watch'] ?? ['required' => false, 'enough' => true, 'min_percent' => 0, 'percent' => 0, 'seconds' => 0, 'duration' => 0];
                    ?>
                    <?php if (!empty($payload['embed_url'])): ?>
                        <div class="ol-player ratio ratio-16x9"
                             data-ol-watch
                             data-item-id="<?= (int)$payload['id'] ?>"
                             data-required="<?= !empty($watch['required']) ? '1' : '0' ?>"
                             data-enough="<?= !empty($watch['enough']) ? '1' : '0' ?>"
                             data-min="<?= (int)($watch['min_percent'] ?? 0) ?>"
                             data-seconds="<?= (int)($watch['seconds'] ?? 0) ?>"
                             data-duration="<?= (int)($watch['duration'] ?? 0) ?>"
                             data-endpoint="<?= $h(BASE_URL . 'ajax/online_lesson_watch.php') ?>"
                             data-csrf="<?= $h(csrf_token()) ?>">
                            <iframe src="<?= $h((string)$payload['embed_url']) ?>" title="<?= $h((string)$payload['title']) ?>" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        </div>
                        <?php if (!empty($watch['required']) && empty($watch['enough'])): ?>
                            <div class="ol-watch mt-2" data-ol-watch-ui>
                                <div class="ol-progress-top">
                                    <span data-ol-watch-label>Watch <?= (int)$watch['min_percent'] ?>% of this clip to continue</span>
                                    <span data-ol-watch-pct><?= (int)$watch['percent'] ?>%</span>
                                </div>
                                <div class="progress" role="progressbar">
                                    <div class="progress-bar" data-ol-watch-bar style="width: <?= (int)$watch['percent'] ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">This clip is still processing. You can continue and return when it is ready.</div>
                    <?php endif; ?>
                <?php elseif ($payload['type'] === 'page'): ?>
                    <div class="ol-page-body">
                        <?= nl2br($h((string)$payload['body'])) ?>
                    </div>
                <?php elseif ($payload['type'] === 'external_link'): ?>
                    <div class="ol-link-card">
                        <div class="ol-link-icon" aria-hidden="true"><i class="bi bi-box-arrow-up-right"></i></div>
                        <h2 class="h4 mb-1"><?= $h((string)$payload['title']) ?></h2>
                        <p class="text-muted mb-3">External learning material</p>
                        <?php if (!empty($payload['url'])): ?>
                            <a class="btn btn-primary ol-link-open"
                               href="<?= $h((string)$payload['url']) ?>"
                               <?= !empty($payload['open_new_tab']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                               data-ol-external
                               data-item-id="<?= (int)$payload['id'] ?>"
                               data-endpoint="<?= $h(BASE_URL . 'ajax/online_lesson_link.php') ?>"
                               data-csrf="<?= $h(csrf_token()) ?>">Open Learning Material →</a>
                        <?php else: ?>
                            <p class="text-muted mb-0">This link is not available yet.</p>
                        <?php endif; ?>
                        <?php if (!empty($payload['required']) && empty($payload['completed'])): ?>
                            <p class="small text-muted mt-3 mb-0" data-ol-link-status>Open the material to continue.</p>
                        <?php elseif (!empty($payload['completed'])): ?>
                            <p class="small text-success mt-3 mb-0" data-ol-link-status>Opened</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($payload['type'] === 'resource'): ?>
                    <div class="ol-link-card">
                        <h2 class="h4 mb-1"><?= $h((string)$payload['title']) ?></h2>
                        <p class="text-muted mb-3">Lesson resource</p>
                        <a class="btn btn-primary" href="<?= $h(BASE_URL . 'student/lesson_file.php?lesson=' . $lessonId . '&item=' . (int)$payload['id'] . '&from=' . rawurlencode($from)) ?>">Open resource</a>
                    </div>
                <?php elseif (OnlineLessonService::isSubmissionActivity((string)($current['activity_type'] ?? ''))): ?>
                    <?php
                        $work = (new LearningModuleService($pdo))->submission($studentId, (int)$payload['id']);
                        $workStatus = (string)($work['status'] ?? '');
                        $canSubmit = !$work || $workStatus === 'resubmit';
                    ?>
                    <p class="text-muted"><?= nl2br($h((string)($current['activity_instructions'] ?? ''))) ?></p>
                    <?php if (!empty($current['due_at'])): ?>
                        <p>Due <?= $h(date('d M Y, g:i A', strtotime((string)$current['due_at']))) ?></p>
                    <?php endif; ?>
                    <p>Status: <?= $h(LearningModuleService::submissionLabel($workStatus)) ?></p>
                    <?php if ($work && $work['marks_awarded'] !== null && $work['marks_awarded'] !== ''): ?>
                        <p>Marked: <?= $h((string)$work['marks_awarded']) ?> / <?= $h((string)($current['max_marks'] ?? '')) ?></p>
                    <?php elseif ($work && $workStatus === 'submitted'): ?>
                        <p>Pending marking</p>
                    <?php endif; ?>
                    <?php if ($work && trim((string)($work['teacher_comment'] ?? '')) !== ''): ?>
                        <p><strong>Feedback:</strong> <?= nl2br($h((string)$work['teacher_comment'])) ?></p>
                    <?php endif; ?>
                    <?php if ($canSubmit): ?>
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit_work">
                            <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                            <?php if ((int)($current['allow_text'] ?? 1) === 1): ?>
                                <div class="col-12">
                                    <label class="form-label">Your answer</label>
                                    <textarea class="form-control" name="body_text" rows="6"></textarea>
                                </div>
                            <?php endif; ?>
                            <?php if ((int)($current['allow_file'] ?? 1) === 1): ?>
                                <div class="col-12">
                                    <label class="form-label">File</label>
                                    <input class="form-control" type="file" name="work_file">
                                </div>
                            <?php endif; ?>
                            <div class="col-12"><button class="btn btn-primary" type="submit">Submit</button></div>
                        </form>
                    <?php endif; ?>
                <?php else:
                    $activity = $payload['activity'] ?? [];
                    $attempt = $payload['attempt'] ?? null;
                    $questions = $activity['questions'] ?? [];
                    $locked = !empty($payload['completed']) || !empty($activity['finished']);
                ?>
                    <?php if (!empty($activity['instructions'])): ?>
                        <p class="text-muted"><?= nl2br($h((string)$activity['instructions'])) ?></p>
                    <?php endif; ?>
                    <?php
                        $showScore = !array_key_exists('show_score', $activity) || !empty($activity['show_score']);
                        $timeInfo = $activity['time'] ?? ['limit' => 0, 'remaining' => 0, 'expired' => false];
                        $timed = (int)($timeInfo['limit'] ?? 0) > 0 && !$locked && empty($activity['finished']);
                        $writtenInAttempt = false;
                        foreach (($attempt['questions'] ?? []) as $attemptQuestion) {
                            if (in_array((string)($attemptQuestion['type'] ?? ''), ['essay', 'short', 'exam'], true)) {
                                $writtenInAttempt = true;
                                break;
                            }
                        }
                    ?>
                    <?php if ($attempt && isset($attempt['passed'])): ?>
                        <?php if ($attempt['passed']): ?>
                            <div class="alert alert-success">
                                Submitted<?= $showScore && $attempt['score'] !== null ? ': ' . $h((string)$attempt['score']) . ' / ' . $h((string)$attempt['max_score']) : '.' ?>
                            </div>
                        <?php elseif ((string)($attempt['status'] ?? '') === 'submitted' && $writtenInAttempt): ?>
                            <div class="alert alert-info">Submitted. Written answers stay pending until your teacher marks them.</div>
                        <?php elseif (!empty($activity['finished'])): ?>
                            <div class="alert alert-secondary">
                                <?= $showScore ? 'Score ' . $h((string)$attempt['score']) . ' / ' . $h((string)$attempt['max_score']) . '. ' : '' ?>No attempts are left.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <?= $showScore ? 'Score ' . $h((string)$attempt['score']) . ' / ' . $h((string)$attempt['max_score']) . '. ' : '' ?>Try again to continue.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ((int)($activity['draw_count'] ?? 0) > 0 && (int)$activity['draw_count'] < (int)($activity['pool_size'] ?? 0) && !$locked): ?>
                        <p class="small text-muted">Each attempt gives you <?= (int)$activity['draw_count'] ?> questions chosen from a pool of <?= (int)$activity['pool_size'] ?>.</p>
                    <?php endif; ?>
                    <?php if ((int)($activity['max_attempts'] ?? 0) > 0 && !$locked): ?>
                        <p class="small text-muted">Attempts used: <?= (int)($activity['attempts_used'] ?? 0) ?> of <?= (int)$activity['max_attempts'] ?>.</p>
                    <?php endif; ?>

                    <?php if (!empty($activity['needs_start'])): ?>
                        <div class="card border-0 bg-body-tertiary rounded-4 p-4 mb-3">
                            <h2 class="h5">Timed activity</h2>
                            <p class="mb-3">You have <?= (int)($activity['time_limit_minutes'] ?? 0) ?> minutes once you start. Answers are submitted automatically when the time runs out.</p>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="start_attempt">
                                <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                                <button class="btn btn-primary" type="submit">Start<?= (int)($activity['attempts_used'] ?? 0) > 0 ? ' attempt ' . ((int)$activity['attempts_used'] + 1) : '' ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <?php if ($timed): ?>
                        <div class="alert alert-secondary d-flex justify-content-between align-items-center" data-ol-timer data-remaining="<?= (int)($timeInfo['remaining'] ?? 0) ?>">
                            <span>Time left</span>
                            <strong data-ol-timer-label><?= sprintf('%d:%02d', intdiv((int)($timeInfo['remaining'] ?? 0), 60), (int)($timeInfo['remaining'] ?? 0) % 60) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($questions as $question): ?>
                        <form method="post" id="bm-q-<?= (int)$question['id'] ?>" class="d-none">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_bookmark">
                            <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                            <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                        </form>
                    <?php endforeach; ?>
                    <?php if (empty($activity['needs_start'])): ?>
                    <form method="post" class="ol-quiz" data-ol-quiz<?= $timed ? ' data-ol-timed novalidate' : '' ?><?php if (!$locked && $questions !== []): ?> data-ol-live="<?= $h(BASE_URL . 'ajax/mcq_live.php') ?>" data-item-id="<?= (int)$payload['id'] ?>" data-csrf="<?= $h(csrf_token()) ?>"<?php endif; ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit_activity">
                        <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                        <?php foreach ($questions as $qi => $question): ?>
                            <?php $questionBookmarked = isset($bookmarkKeys[(int)$payload['id'] . ':' . (int)$question['id']]); ?>
                            <fieldset class="ol-question" data-ol-qid="<?= (int)$question['id'] ?>" <?= (string)$question['type'] === 'mcq' ? 'data-ol-required-group' : '' ?>>
                                <div class="ol-q-title"><?= ($qi + 1) ?>. <?= nl2br($h((string)$question['prompt'])) ?>
                                    <span class="small text-muted">(<?= $h((string)$question['marks']) ?> mark<?= (float)$question['marks'] === 1.0 ? '' : 's' ?>)</span>
                                    <button class="btn btn-link btn-sm p-0 ms-2 align-baseline" type="submit" form="bm-q-<?= (int)$question['id'] ?>" title="<?= $questionBookmarked ? 'Remove bookmark' : 'Bookmark this question' ?>">
                                        <i class="bi <?= $questionBookmarked ? 'bi-bookmark-fill text-warning' : 'bi-bookmark' ?>"></i><span class="visually-hidden"><?= $questionBookmarked ? 'Remove bookmark' : 'Bookmark' ?></span>
                                    </button>
                                </div>
                                <?php if (in_array((string)$question['type'], ['essay', 'short', 'exam'], true)): ?>
                                    <?php if ((string)$question['type'] === 'exam' && trim((string)($question['exam_ref'] ?? '')) !== ''): ?>
                                        <p class="small text-muted">Exam reference: <?= $h((string)$question['exam_ref']) ?></p>
                                    <?php endif; ?>
                                    <textarea class="form-control" name="q[<?= (int)$question['id'] ?>][essay]" rows="7" <?= $locked ? 'readonly' : 'data-ol-required-essay required' ?>><?= $h((string)($question['student_essay'] ?? '')) ?></textarea>
                                    <?php if (!empty($question['teacher_comment'])): ?>
                                        <p class="small text-success mt-2 mb-0"><strong>Teacher:</strong> <?= nl2br($h((string)$question['teacher_comment'])) ?></p>
                                    <?php endif; ?>
                                    <?php if (($question['marks_awarded'] ?? null) !== null): ?>
                                        <p class="small text-muted mt-1 mb-0">Marked: <?= $h((string)$question['marks_awarded']) ?> / <?= $h((string)$question['marks']) ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php foreach (($question['choices'] ?? []) as $ci => $choice):
                                        $picked = isset($question['student_choice']) && (int)$question['student_choice'] === (int)$ci;
                                        $cls = 'ol-choice';
                                        if ($locked && array_key_exists('correct_index', $question)) {
                                            if ((int)$question['correct_index'] === (int)$ci) {
                                                $cls .= ' is-correct';
                                            } elseif ($picked) {
                                                $cls .= ' is-wrong';
                                            }
                                        }
                                    ?>
                                        <label class="<?= $cls ?>">
                                            <input type="radio" name="q[<?= (int)$question['id'] ?>][choice]" value="<?= (int)$ci ?>" <?= $picked ? 'checked' : '' ?> <?= $locked ? 'disabled' : 'required' ?>>
                                            <span><?= $h((string)$choice) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <?php if ($locked && !empty($question['explanation'])): ?>
                                        <p class="small text-muted mt-2 mb-0"><?= nl2br($h((string)$question['explanation'])) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <?php if (!$locked && $questions !== []): ?>
                            <button class="btn btn-primary" type="submit">Submit answers</button>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php
                    $sectionIdForNote = (int)($current['section_id'] ?? 0);
                    $noteItem = $studentNotes['0:' . (int)$payload['id']] ?? '';
                    $noteSection = $sectionIdForNote > 0 ? ($studentNotes[$sectionIdForNote . ':0'] ?? '') : '';
                    $noteLesson = $studentNotes['0:0'] ?? '';
                ?>
                <details class="ol-notes mt-4" <?= ($noteItem . $noteSection . $noteLesson) !== '' ? 'open' : '' ?>>
                    <summary class="small"><i class="bi bi-journal-text me-1"></i>My private notes <span class="text-muted">(only you can see these)</span></summary>
                    <?php foreach (array_filter([
                        'item' => ['This activity', $noteItem],
                        'section' => $sectionIdForNote > 0 ? ['This section', $noteSection] : null,
                        'lesson' => ['Whole lesson', $noteLesson],
                    ]) as $scope => [$scopeLabel, $scopeBody]): ?>
                        <form method="post" class="mt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_note">
                            <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                            <input type="hidden" name="note_scope" value="<?= $h($scope) ?>">
                            <label class="form-label small mb-1" for="note-<?= $h($scope) ?>"><?= $h($scopeLabel) ?></label>
                            <textarea class="form-control form-control-sm" id="note-<?= $h($scope) ?>" name="body" rows="2" maxlength="<?= StudentStudyService::NOTE_LIMIT ?>" placeholder="e.g. Review this before the exam."><?= $h($scopeBody) ?></textarea>
                            <button class="btn btn-sm btn-outline-secondary mt-1" type="submit">Save note</button>
                        </form>
                    <?php endforeach; ?>
                </details>
            </div>
            <nav class="ol-pager">
                <?php if ($neighbors['prev'] > 0): ?>
                    <a class="btn btn-outline-secondary" href="<?= $h(student_online_lesson_url($lessonId, $neighbors['prev'], $from)) ?>">Previous</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <?php
                    $watch = $payload['watch'] ?? null;
                    $watchBlocks = $payload['type'] === 'video'
                        && !empty($watch['required'])
                        && empty($watch['enough'])
                        && empty($payload['completed']);
                    $linkBlocks = $payload['type'] === 'external_link'
                        && !empty($payload['required'])
                        && empty($payload['completed']);
                    $canNext = !empty($payload['completed']) || ($payload['type'] !== 'activity' && !$watchBlocks && !$linkBlocks);
                    $nextId = $neighbors['next'];
                    $nextLabel = $nextId > 0 ? 'Next' : 'Finish lesson';
                ?>
                <?php if ($payload['type'] === 'video'): ?>
                    <form method="post" data-ol-complete<?= $watchBlocks ? ' data-ol-watch-gate' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete_item">
                        <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                        <button class="btn btn-primary" type="submit" <?= $watchBlocks ? 'disabled' : '' ?> data-ol-next-btn data-ol-next-label="<?= $h($nextLabel) ?>">
                            <?= $watchBlocks ? 'Watch the clip to continue' : $h($nextLabel) ?>
                        </button>
                    </form>
                <?php elseif ($payload['type'] === 'external_link'): ?>
                    <form method="post" data-ol-complete<?= $linkBlocks ? ' data-ol-link-gate' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete_item">
                        <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                        <button class="btn btn-primary" type="submit" <?= $linkBlocks ? 'disabled' : '' ?> data-ol-next-btn data-ol-next-label="<?= $h($nextLabel) ?>">
                            <?= $linkBlocks ? 'Open the learning material to continue' : $h($nextLabel) ?>
                        </button>
                    </form>
                <?php elseif ($canNext): ?>
                    <form method="post" data-ol-complete>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete_item">
                        <input type="hidden" name="item_id" value="<?= (int)$payload['id'] ?>">
                        <button class="btn btn-primary" type="submit"><?= $h($nextLabel) ?></button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-primary" type="button" disabled>Submit answers to continue</button>
                <?php endif; ?>
            </nav>
        <?php else: ?>
            <div class="p-4 text-muted">This lesson has no clips or questions yet.</div>
        <?php endif; ?>
    </section>
</div>
<script src="<?= $h(BASE_URL . 'assets/js/online-lesson.js?v=' . filemtime(__DIR__ . '/../assets/js/online-lesson.js')) ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
