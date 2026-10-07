<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CourseService;
use Edexcel\Services\LessonAccessService;
use Edexcel\Services\LessonAiService;
use Edexcel\Services\LessonInsightService;
use Edexcel\Services\LessonVersionService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\QuestionPool;
use Edexcel\Services\RecordingService;

require_staff();
ensure_recordings_schema($pdo);
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$timetableId = (int)($_GET['lesson'] ?? $_POST['timetable_id'] ?? 0);
$tab = (string)($_GET['tab'] ?? 'access');
if (!in_array($tab, ['access', 'versions', 'ai', 'history'], true)) {
    $tab = 'access';
}

$recordings = new RecordingService($pdo);
$lessons = new OnlineLessonService($pdo);
$staffLesson = $timetableId > 0 ? $recordings->lessonForStaff($timetableId, $teacherId, $isAdmin) : null;
if (!$staffLesson) {
    $_SESSION['error'] = 'You cannot manage a lesson for that class.';
    header('Location: ' . BASE_URL . 'campus/online_lesson.php');
    exit;
}
$onlineLesson = $lessons->findByTimetable($timetableId);
if (!$onlineLesson) {
    header('Location: ' . campus_online_lesson_url($timetableId));
    exit;
}
$lessonPk = (int)$onlineLesson['id'];

$access = new LessonAccessService($pdo);
$versions = new LessonVersionService($pdo);
$courses = new CourseService($pdo);
$insightService = new LessonInsightService($pdo);
$ai = new LessonAiService($pdo);
$error = '';
$success = '';
$restoreKept = [];
$selfUrl = BASE_URL . 'campus/lesson_manage.php?lesson=' . $timetableId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token.');
        }
        $auditRefs = [];
        if ($action === 'save_schedule') {
            $publishAt = LessonAccessService::normalizeDateTime((string)($_POST['publish_at'] ?? ''));
            $unpublishAt = LessonAccessService::normalizeDateTime((string)($_POST['unpublish_at'] ?? ''));
            $publish = !empty($_POST['publish']);
            if ($publish) {
                $blocking = array_filter($insightService->qualityReport($lessonPk), static fn (array $i): bool => $i['severity'] === 'high');
                if ($blocking !== []) {
                    throw new RuntimeException('Fix these issues first: ' . implode(' ', array_column($blocking, 'message')));
                }
            }
            $access->saveSchedule($lessonPk, $publishAt, $unpublishAt, $publish);
            if ($publish) {
                $versions->createVersion($lessonPk, $userId, 'published');
            }
            $auditRefs = ['publish_at' => $publishAt, 'unpublish_at' => $unpublishAt, 'publish' => $publish ? 1 : 0];
            $fresh = $lessons->find($lessonPk) ?? $onlineLesson;
            $state = OnlineLessonService::publicationState($fresh);
            $success = $state === 'scheduled'
                ? 'Scheduled. Students can open the lesson from ' . date('d M Y, g:i A', strtotime((string)$fresh['publish_at'])) . '. Scheduled lessons do not send a notification.'
                : ($publish ? 'Published. Students can open the lesson now.' : 'Schedule saved.');
            if ($unpublishAt !== null) {
                $success .= ' It closes on ' . date('d M Y, g:i A', strtotime($unpublishAt)) . '; progress and results are kept.';
            }
        } elseif ($action === 'clear_schedule') {
            $pdo->prepare('UPDATE online_lessons SET publish_at = NULL, unpublish_at = NULL WHERE id = ?')->execute([$lessonPk]);
            $success = 'Schedule cleared.';
        } elseif ($action === 'add_prerequisite') {
            $requires = (int)($_POST['requires_lesson_id'] ?? 0);
            $access->addPrerequisite($lessonPk, $requires, (int)($_POST['min_percent'] ?? 100));
            $auditRefs = ['requires_lesson_id' => $requires];
            $success = 'Prerequisite saved.';
        } elseif ($action === 'remove_prerequisite') {
            $access->removePrerequisite($lessonPk, (int)($_POST['prerequisite_id'] ?? 0));
            $auditRefs = ['prerequisite_id' => (int)($_POST['prerequisite_id'] ?? 0)];
            $success = 'Prerequisite removed.';
        } elseif ($action === 'assign_topic') {
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $courses->assignLesson($lessonPk, $topicId);
            $auditRefs = ['topic_id' => $topicId];
            $success = $topicId > 0 ? 'Lesson linked to the course topic.' : 'Lesson removed from the course.';
        } elseif ($action === 'use_latest_resource') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $item = $lessons->item($itemId);
            if (!$item || (int)$item['lesson_id'] !== $lessonPk || (int)($item['resource_id'] ?? 0) < 1) {
                throw new RuntimeException('That resource item was not found.');
            }
            $latest = (new \Edexcel\Services\ResourceLibraryService($pdo))->latestVersionId((int)$item['resource_id']);
            $pdo->prepare('UPDATE online_lesson_items SET resource_id = ? WHERE id = ? AND lesson_id = ?')->execute([$latest, $itemId, $lessonPk]);
            $latestUrl = (string)($pdo->query('SELECT url FROM online_lesson_resources WHERE id = ' . (int)$latest)->fetchColumn() ?: '');
            if ((string)$item['item_type'] === 'external_link' && $latestUrl !== '') {
                $pdo->prepare('UPDATE online_lesson_items SET link_url = ? WHERE id = ? AND lesson_id = ?')
                    ->execute([OnlineLessonService::normalizeExternalUrl($latestUrl), $itemId, $lessonPk]);
            }
            $auditRefs = ['item_id' => $itemId, 'resource_id' => $latest];
            $success = 'The item now uses the latest version of the resource.';
        } elseif ($action === 'save_version') {
            $id = $versions->createVersion($lessonPk, $userId, 'saved');
            $auditRefs = ['version_id' => $id];
            $success = 'Version saved. If nothing changed since the last version, the last version is kept.';
            $tab = 'versions';
        } elseif ($action === 'restore_version') {
            $versionId = (int)($_POST['version_id'] ?? 0);
            $result = $versions->restore($lessonPk, $versionId, $userId);
            $restoreKept = $result['kept'];
            $auditRefs = ['restored_from' => $versionId, 'version_id' => $result['version_id']];
            $success = 'Version restored as a new version. History is kept. Student progress, attempts and marks were not changed.';
            $tab = 'versions';
        } elseif ($action === 'ai_plan') {
            $draftId = $ai->draftPlan($lessonPk, $userId, [
                'topic' => (string)($_POST['topic'] ?? ''),
                'objectives' => (string)($_POST['objectives'] ?? ''),
                'minutes' => (int)($_POST['minutes'] ?? 60),
                'level' => (string)($_POST['level'] ?? ''),
            ]);
            log_audit($pdo, 'online_lesson_ai_plan', 'online_lessons', $lessonPk, null, ['draft_id' => $draftId]);
            header('Location: ' . $selfUrl . '&tab=ai&draft=' . $draftId);
            exit;
        } elseif ($action === 'ai_questions') {
            $activityId = (int)($_POST['activity_id'] ?? 0);
            $activity = $lessons->activity($activityId);
            if (!$activity || (int)$activity['lesson_id'] !== $lessonPk) {
                throw new RuntimeException('Choose a question activity from this lesson.');
            }
            $draftId = $ai->draftQuestions($lessonPk, $activityId, $userId, [
                'topic' => (string)($_POST['topic'] ?? ''),
                'count' => (int)($_POST['count'] ?? 5),
                'difficulty' => (string)($_POST['difficulty'] ?? ''),
                'type' => (string)($_POST['type'] ?? 'mcq'),
            ]);
            log_audit($pdo, 'online_lesson_ai_questions', 'online_lessons', $lessonPk, null, ['draft_id' => $draftId, 'activity_id' => $activityId]);
            header('Location: ' . $selfUrl . '&tab=ai&draft=' . $draftId);
            exit;
        } elseif ($action === 'ai_apply_plan') {
            $draftId = (int)($_POST['draft_id'] ?? 0);
            $count = $ai->applyPlan($draftId, $lessonPk);
            $versions->createVersion($lessonPk, $userId, 'ai_applied');
            $auditRefs = ['draft_id' => $draftId];
            $success = 'AI plan added as a draft with ' . $count . ' items. Quiz questions are marked “AI generated — review required” and stay hidden from students until you save each one.';
            $tab = 'ai';
        } elseif ($action === 'ai_apply_questions') {
            $draftId = (int)($_POST['draft_id'] ?? 0);
            $picked = is_array($_POST['pick'] ?? null) ? array_map('intval', $_POST['pick']) : [];
            $count = $ai->applyQuestions($draftId, $lessonPk, $picked);
            $auditRefs = ['draft_id' => $draftId];
            $success = 'Added ' . $count . ' question' . ($count === 1 ? '' : 's') . ' marked “AI generated — review required”. Students will not see them until you open and save each one.';
            $tab = 'ai';
        } elseif ($action === 'ai_discard') {
            $ai->discard((int)($_POST['draft_id'] ?? 0), $lessonPk);
            $auditRefs = ['draft_id' => (int)($_POST['draft_id'] ?? 0)];
            $success = 'Draft discarded.';
            $tab = 'ai';
        } else {
            throw new RuntimeException('Unknown action.');
        }
        log_audit($pdo, 'online_lesson_' . $action, 'online_lessons', $lessonPk, null, array_filter($auditRefs, static fn ($v) => $v !== null) ?: null);
        $onlineLesson = $lessons->find($lessonPk) ?? $onlineLesson;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$state = OnlineLessonService::publicationState($onlineLesson);
$stateLabels = [
    'draft' => ['Draft', 'text-bg-secondary'],
    'scheduled' => ['Scheduled', 'text-bg-info'],
    'published' => ['Published', 'text-bg-success'],
    'closed' => ['Closed', 'text-bg-warning'],
    'archived' => ['Archived', 'text-bg-dark'],
];
$toLocal = static fn (?string $v): string => $v !== null && trim($v) !== '' ? date('Y-m-d\TH:i', strtotime($v)) : '';

$pageTitle = 'Manage lesson';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= $h((string)$onlineLesson['title']) ?></h1>
        <p class="text-muted mb-0">
            <?= $h((string)$staffLesson['subject_name']) ?> · <?= $h((string)$staffLesson['class_name']) ?>
            · <?= $h(date('D d M Y', strtotime((string)$staffLesson['date']))) ?>
            <span class="badge <?= $stateLabels[$state][1] ?> ms-2"><?= $stateLabels[$state][0] ?></span>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= $h(campus_online_lesson_url($timetableId)) ?>">Back to lesson builder</a>
</div>

<?php if ($success !== ''): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
<?php if ($restoreKept !== []): ?>
    <div class="alert alert-warning">
        <strong>Kept because students already used them:</strong>
        <ul class="mb-0"><?php foreach ($restoreKept as $kept): ?><li><?= $h($kept) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach (['access' => 'Schedule & access', 'versions' => 'Versions', 'ai' => 'AI assistant', 'history' => 'History'] as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= $h($selfUrl . '&tab=' . $key) ?>"><?= $h($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'access'): ?>
    <?php
        $prereqs = $access->prerequisites($lessonPk);
        $candidates = $access->candidates($lessonPk, (int)$staffLesson['class_id']);
        $topicOptions = $courses->topicOptions((int)$staffLesson['subject_id']);
        $quality = $insightService->qualityReport($lessonPk);
        $ready = LessonInsightService::isReady($quality);
        $insights = $insightService->insights($lessonPk);
        $severityClass = ['high' => 'text-bg-danger', 'medium' => 'text-bg-warning', 'low' => 'text-bg-secondary'];
        $severityLabel = ['high' => 'Blocks publishing', 'medium' => 'Should fix', 'low' => 'Suggestion'];
    ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5">Publishing schedule</h2>
                <p class="text-muted small">Leave “Publish at” empty to publish now. Scheduled lessons open automatically at that time; nothing needs to run in the background. “Available until” closes the lesson but keeps all progress and results.</p>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_schedule">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <div class="col-md-6">
                        <label class="form-label" for="publish_at">Publish at</label>
                        <input class="form-control" type="datetime-local" id="publish_at" name="publish_at" value="<?= $h($toLocal($onlineLesson['publish_at'] ?? null)) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="unpublish_at">Available until</label>
                        <input class="form-control" type="datetime-local" id="unpublish_at" name="unpublish_at" value="<?= $h($toLocal($onlineLesson['unpublish_at'] ?? null)) ?>">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                        <button class="btn btn-success" type="submit" name="publish" value="1"><?= (int)$onlineLesson['published'] === 1 ? 'Save schedule' : 'Publish or schedule' ?></button>
                        <?php if ((int)$onlineLesson['published'] !== 1): ?>
                            <button class="btn btn-outline-secondary" type="submit">Save dates only (stay draft)</button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if (!empty($onlineLesson['publish_at']) || !empty($onlineLesson['unpublish_at'])): ?>
                    <form method="post" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear_schedule">
                        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                        <button class="btn btn-link btn-sm p-0" type="submit">Clear both dates</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5">Prerequisites</h2>
                <p class="text-muted small">Students must complete these lessons from the same class first. They see “Complete [lesson] before starting this lesson.”</p>
                <?php if ($prereqs === []): ?>
                    <p class="text-muted">No prerequisites.</p>
                <?php else: ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($prereqs as $pre): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= $h((string)$pre['title']) ?> · at least <?= (int)$pre['min_percent'] ?>%
                                    <?php if (!OnlineLessonService::isReleased($pre)): ?><span class="badge text-bg-secondary ms-1">not published</span><?php endif; ?>
                                </span>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_prerequisite">
                                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                                    <input type="hidden" name="prerequisite_id" value="<?= (int)$pre['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($candidates !== []): ?>
                    <form method="post" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_prerequisite">
                        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                        <div class="col-md-7">
                            <label class="form-label" for="requires_lesson_id">Lesson</label>
                            <select class="form-select" id="requires_lesson_id" name="requires_lesson_id" required>
                                <option value="">Choose…</option>
                                <?php foreach ($candidates as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= $h(date('d M', strtotime((string)$c['date'])) . ' · ' . (string)$c['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="min_percent">Min %</label>
                            <input class="form-control" type="number" id="min_percent" name="min_percent" min="1" max="100" value="100">
                        </div>
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Add</button></div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5">Course topic</h2>
                <p class="text-muted small">Optional. Links this lesson to a topic for syllabus coverage and the student learning path. The lesson stays with this class.</p>
                <?php if ($topicOptions === []): ?>
                    <p class="mb-0">No course topics yet. <a href="<?= $h(BASE_URL . 'campus/courses.php') ?>">Create a course</a>.</p>
                <?php else: ?>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="assign_topic">
                        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                        <select class="form-select" name="topic_id" aria-label="Course topic">
                            <option value="0">Not linked to a course</option>
                            <?php foreach ($topicOptions as $opt): ?>
                                <option value="<?= $opt['id'] ?>" <?= (int)($onlineLesson['topic_id'] ?? 0) === $opt['id'] ? 'selected' : '' ?>><?= $h($opt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary" type="submit">Save</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100" id="insights">
                <h2 class="h5">What students did</h2>
                <?php if ($insights === []): ?>
                    <p class="text-muted mb-0">No patterns yet. Insights appear once at least <?= LessonInsightService::MIN_STUDENTS ?> students have used a part of the lesson.</p>
                <?php else: ?>
                    <ul class="mb-0">
                        <?php foreach ($insights as $insight): ?><li><?= $h($insight['message']) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4" id="quality">
                <h2 class="h5 d-flex align-items-center gap-2">Quality check
                    <span class="badge <?= $ready ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $ready ? 'LESSON READY' : 'ISSUES FOUND' ?></span>
                </h2>
                <?php if ($quality === []): ?>
                    <p class="text-muted mb-0">No issues found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Issue</th><th>Severity</th><th>How to fix</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($quality as $issue): ?>
                                <tr>
                                    <td><?= $h($issue['message']) ?></td>
                                    <td><span class="badge <?= $severityClass[$issue['severity']] ?>"><?= $severityLabel[$issue['severity']] ?></span></td>
                                    <td class="small"><?= $h($issue['fix']) ?></td>
                                    <td class="text-nowrap">
                                        <?php if (str_contains($issue['message'], 'older version') && $issue['item_id'] > 0): ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="use_latest_resource">
                                                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                                                <input type="hidden" name="item_id" value="<?= (int)$issue['item_id'] ?>">
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Use latest</button>
                                            </form>
                                        <?php elseif ($issue['activity_id'] > 0): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= $h(campus_online_lesson_url($timetableId, $issue['activity_id'])) ?>">Fix</a>
                                        <?php elseif ($issue['item_id'] > 0): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= $h(campus_online_lesson_url($timetableId) . '&page=' . $issue['item_id']) ?>">Fix</a>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= $h(campus_online_lesson_url($timetableId)) ?>#lesson-plan">Open</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'versions'): ?>
    <?php
        $list = $versions->versions($lessonPk);
        $fromId = (int)($_GET['from'] ?? 0);
        $toId = (int)($_GET['to'] ?? 0);
        $diff = null;
        $diffError = '';
        if ($fromId > 0) {
            try {
                $old = $versions->version($lessonPk, $fromId)['snapshot'];
                $new = $toId > 0 ? $versions->version($lessonPk, $toId)['snapshot'] : $versions->snapshot($lessonPk);
                $diff = LessonVersionService::compare($old, $new);
            } catch (Throwable $e) {
                $diffError = $e->getMessage();
            }
        }
        $versionLabel = static function (array $v): string {
            return 'Version ' . (int)$v['version_no'] . ' · ' . date('d M Y, g:i A', strtotime((string)$v['created_at']));
        };
    ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
            <div>
                <h2 class="h5">Version history</h2>
                <p class="text-muted small mb-0">A version stores the lesson design only: settings, plan, sections, objectives, activities, questions and mark schemes. It never contains student progress, attempts, marks or submissions. A version is saved automatically when you publish.</p>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_version">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <button class="btn btn-primary" type="submit">Save current version</button>
            </form>
        </div>
    </div>
    <?php if ($list !== []): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h2 class="h6">Compare</h2>
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="lesson" value="<?= $timetableId ?>">
                <input type="hidden" name="tab" value="versions">
                <div class="col-md-5">
                    <label class="form-label small" for="from">Older</label>
                    <select class="form-select" id="from" name="from">
                        <?php foreach ($list as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $fromId === (int)$v['id'] ? 'selected' : '' ?>><?= $h($versionLabel($v)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small" for="to">Newer</label>
                    <select class="form-select" id="to" name="to">
                        <option value="0">Current lesson</option>
                        <?php foreach ($list as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $toId === (int)$v['id'] ? 'selected' : '' ?>><?= $h($versionLabel($v)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Compare</button></div>
            </form>
            <?php if ($diffError !== ''): ?><div class="alert alert-danger mt-3 mb-0"><?= $h($diffError) ?></div><?php endif; ?>
            <?php if ($diff !== null): ?>
                <div class="mt-3">
                    <?php if (LessonVersionService::isEmptyDiff($diff)): ?>
                        <p class="mb-0">No differences.</p>
                    <?php else: ?>
                        <p class="small">Total marks: <?= $h((string)$diff['marks']['old']) ?> → <?= $h((string)$diff['marks']['new']) ?></p>
                        <?php
                            $lists = [
                                'items_added' => 'Activities added', 'items_removed' => 'Activities removed',
                                'questions_added' => 'Questions added', 'questions_removed' => 'Questions removed',
                                'objectives_added' => 'Objectives added', 'objectives_removed' => 'Objectives removed',
                                'sections_added' => 'Sections added', 'sections_removed' => 'Sections removed',
                            ];
                        ?>
                        <?php foreach ($lists as $key => $label): ?>
                            <?php if ($diff[$key] !== []): ?>
                                <h3 class="h6 mt-2"><?= $h($label) ?></h3>
                                <ul class="small"><?php foreach ($diff[$key] as $row): ?><li><?= $h((string)$row) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($diff['items_changed'] !== []): ?>
                            <h3 class="h6 mt-2">Activities changed</h3>
                            <ul class="small"><?php foreach ($diff['items_changed'] as $row): ?><li><?= $h($row['title']) ?>: <?= $h(str_replace('_', ' ', implode(', ', $row['fields']))) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                        <?php if ($diff['questions_changed'] !== []): ?>
                            <h3 class="h6 mt-2">Questions changed</h3>
                            <ul class="small"><?php foreach ($diff['questions_changed'] as $row): ?><li><?= $h($row['prompt']) ?>: <?= $h(str_replace('_', ' ', implode(', ', $row['fields']))) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                        <?php if ($diff['settings'] !== []): ?>
                            <h3 class="h6 mt-2">Settings and plan changed</h3>
                            <ul class="small"><?php foreach ($diff['settings'] as $row): ?><li><?= $h(str_replace(['plan_', '_'], ['plan ', ' '], $row['field'])) ?>: “<?= $h(mb_strimwidth($row['old'], 0, 60, '…')) ?>” → “<?= $h(mb_strimwidth($row['new'], 0, 60, '…')) ?>”</li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Version</th><th>Saved</th><th>By</th><th>Reason</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($list as $v): ?>
                    <tr>
                        <td><?= (int)$v['version_no'] ?></td>
                        <td><?= $h(date('d M Y, g:i A', strtotime((string)$v['created_at']))) ?></td>
                        <td><?= $h((string)($v['created_by_name'] ?? '')) ?></td>
                        <td>
                            <?= $h(LessonVersionService::REASONS[(string)$v['reason']] ?? (string)$v['reason']) ?>
                            <?php if ((int)($v['restored_from'] ?? 0) > 0): ?><span class="small text-muted">(from saved version #<?= (int)$v['restored_from'] ?>)</span><?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $h($selfUrl . '&tab=versions&from=' . (int)$v['id']) ?>">Compare with current</a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Restore version <?= (int)$v['version_no'] ?>? The current lesson is saved as a version first, and student work is not changed.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="restore_version">
                                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                                <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($list === []): ?>
                    <tr><td colspan="5" class="text-muted p-4">No versions yet. Save one now, or publish the lesson.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'ai'): ?>
    <?php
        $aiReady = $ai->available();
        $draftId = (int)($_GET['draft'] ?? 0);
        $draft = null;
        if ($draftId > 0) {
            try {
                $draft = $ai->draft($draftId, $lessonPk);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $activityOptions = [];
        foreach ($lessons->items($lessonPk) as $item) {
            if ((string)$item['item_type'] === 'activity' && !OnlineLessonService::isSubmissionActivity((string)($item['activity_type'] ?? ''))) {
                $activityOptions[] = $item;
            }
        }
        $drafts = $ai->drafts($lessonPk);
    ?>
    <div class="alert alert-info">
        AI output is always a <strong>draft</strong>. Nothing is published automatically. Questions you add are marked
        <strong>AI GENERATED — REVIEW REQUIRED</strong>, stay hidden from students, and block publishing until you open and save each one.
        AI questions are practice material written for this lesson; they are not official exam questions.
    </div>
    <?php if (!$aiReady): ?>
        <div class="alert alert-warning">The AI service is not configured on this server, so drafts cannot be generated.</div>
    <?php endif; ?>

    <?php if ($draft): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h2 class="h5">Draft #<?= (int)$draft['id'] ?> · <?= $draft['kind'] === 'plan' ? 'Lesson plan' : 'Questions' ?>
                <span class="badge text-bg-secondary"><?= $h((string)$draft['status']) ?></span></h2>
            <?php if ($draft['kind'] === 'plan'): ?>
                <?php $plan = LessonAiService::normalizePlan($draft['output']); ?>
                <?php if ($plan['objectives'] !== []): ?>
                    <h3 class="h6">Objectives</h3>
                    <ul><?php foreach ($plan['objectives'] as $o): ?><li><?= $h($o) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php foreach ($plan['sections'] as $section): ?>
                    <div class="border rounded-3 p-3 mb-2">
                        <strong><?= $h($section['title']) ?></strong><?= $section['minutes'] ? ' · ' . (int)$section['minutes'] . ' min' : '' ?>
                        <?php foreach ($section['items'] as $item): ?>
                            <div class="mt-2 ms-2">
                                <div class="fw-semibold small"><?= $item['type'] === 'mcq' ? 'Quiz: ' : 'Page: ' ?><?= $h($item['title']) ?></div>
                                <?php if ($item['type'] === 'page'): ?>
                                    <div class="small text-muted" style="white-space: pre-wrap;"><?= $h(mb_strimwidth($item['body'], 0, 600, '…')) ?></div>
                                <?php else: ?>
                                    <ol class="small mb-0"><?php foreach ($item['questions'] as $q): ?><li><?= $h($q['prompt']) ?> <span class="badge text-bg-warning">AI GENERATED — REVIEW REQUIRED</span></li><?php endforeach; ?></ol>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($draft['status'] === 'draft'): ?>
                    <div class="d-flex gap-2 mt-3">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="ai_apply_plan">
                            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                            <input type="hidden" name="draft_id" value="<?= (int)$draft['id'] ?>">
                            <button class="btn btn-primary" type="submit">Add to lesson as draft</button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="ai_discard">
                            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                            <input type="hidden" name="draft_id" value="<?= (int)$draft['id'] ?>">
                            <button class="btn btn-outline-secondary" type="submit">Discard</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php $draftQuestions = LessonAiService::normalizeQuestions($draft['output']['questions'] ?? [], (string)($draft['input']['type'] ?? 'mcq')); ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ai_apply_questions">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <input type="hidden" name="draft_id" value="<?= (int)$draft['id'] ?>">
                    <?php foreach ($draftQuestions as $i => $q): ?>
                        <div class="border rounded-3 p-3 mb-2">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="pick[]" value="<?= $i ?>" <?= $draft['status'] === 'draft' ? 'checked' : 'disabled' ?>>
                                <span class="form-check-label"><strong><?= $i + 1 ?>.</strong> <?= $h($q['prompt']) ?></span>
                            </label>
                            <span class="badge text-bg-warning">AI GENERATED — REVIEW REQUIRED</span>
                            <?php if (!empty($q['difficulty'])): ?><span class="badge text-bg-light border"><?= $h(QuestionPool::DIFFICULTIES[$q['difficulty']] ?? $q['difficulty']) ?></span><?php endif; ?>
                            <?php if (isset($q['choices'])): ?>
                                <ol type="A" class="small mt-2 mb-1">
                                    <?php foreach ($q['choices'] as $ci => $choice): ?><li><?= $h($choice) ?><?= $ci === $q['correct_index'] ? ' ✓' : '' ?></li><?php endforeach; ?>
                                </ol>
                                <?php if ($q['explanation'] !== ''): ?><div class="small text-muted">Explanation: <?= $h($q['explanation']) ?></div><?php endif; ?>
                            <?php else: ?>
                                <div class="small text-muted mt-1">Expected answer: <?= $h($q['expected_answer']) ?> · <?= $h((string)$q['marks']) ?> marks</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($draft['status'] === 'draft' && $draftQuestions !== []): ?>
                        <button class="btn btn-primary" type="submit">Add ticked questions (hidden until reviewed)</button>
                    <?php endif; ?>
                </form>
                <?php if ($draft['status'] === 'draft'): ?>
                    <form method="post" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="ai_discard">
                        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                        <input type="hidden" name="draft_id" value="<?= (int)$draft['id'] ?>">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Discard draft</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5">Plan this lesson with AI</h2>
                <p class="text-muted small">Creates a preview of objectives, sections, notes and short quizzes. You choose whether to add it. It can only be added to an unpublished lesson with no pages or quizzes yet.</p>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ai_plan">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <div class="col-12"><label class="form-label" for="ai_topic">Topic</label><input class="form-control" id="ai_topic" name="topic" maxlength="200" required value="<?= $h((string)($onlineLesson['plan_topics'] ?? '')) ?>"></div>
                    <div class="col-12"><label class="form-label" for="ai_obj">Your objectives (optional)</label><textarea class="form-control" id="ai_obj" name="objectives" rows="3"><?= $h((string)($onlineLesson['plan_objectives'] ?? '')) ?></textarea></div>
                    <div class="col-md-6"><label class="form-label" for="ai_min">Minutes</label><input class="form-control" type="number" id="ai_min" name="minutes" min="10" max="240" value="<?= (int)($onlineLesson['plan_minutes'] ?? 60) ?: 60 ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="ai_level">Level (optional)</label><input class="form-control" id="ai_level" name="level" maxlength="80" placeholder="e.g. Grade 10"></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit" <?= $aiReady ? '' : 'disabled' ?>>Create draft plan</button></div>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5">Draft questions with AI</h2>
                <?php if ($activityOptions === []): ?>
                    <p class="text-muted mb-0">Add a question activity to the lesson first.</p>
                <?php else: ?>
                    <form method="post" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="ai_questions">
                        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                        <div class="col-12">
                            <label class="form-label" for="ai_act">Add to activity</label>
                            <select class="form-select" id="ai_act" name="activity_id">
                                <?php foreach ($activityOptions as $opt): ?><option value="<?= (int)$opt['activity_id'] ?>"><?= $h((string)$opt['title']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label" for="ai_qtopic">What should the questions cover?</label><input class="form-control" id="ai_qtopic" name="topic" maxlength="200" required></div>
                        <div class="col-md-4"><label class="form-label" for="ai_count">How many</label><input class="form-control" type="number" id="ai_count" name="count" min="1" max="10" value="5"></div>
                        <div class="col-md-4">
                            <label class="form-label" for="ai_diff">Difficulty</label>
                            <select class="form-select" id="ai_diff" name="difficulty"><option value="">Mixed</option><?php foreach (QuestionPool::DIFFICULTIES as $k => $v): ?><option value="<?= $h($k) ?>"><?= $h($v) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ai_type">Type</label>
                            <select class="form-select" id="ai_type" name="type"><option value="mcq">Multiple choice</option><option value="short">Short answer</option></select>
                        </div>
                        <div class="col-12"><button class="btn btn-primary" type="submit" <?= $aiReady ? '' : 'disabled' ?>>Create draft questions</button></div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($drafts !== []): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mt-4">
            <h2 class="h6">Earlier drafts</h2>
            <ul class="mb-0">
                <?php foreach ($drafts as $d): $in = json_decode((string)$d['input_json'], true) ?: []; ?>
                    <li><a href="<?= $h($selfUrl . '&tab=ai&draft=' . (int)$d['id']) ?>">#<?= (int)$d['id'] ?> <?= $d['kind'] === 'plan' ? 'Plan' : 'Questions' ?> · <?= $h((string)($in['topic'] ?? '')) ?></a>
                        <span class="text-muted small">· <?= $h((string)$d['status']) ?> · <?= $h(date('d M Y, g:i A', strtotime((string)$d['created_at']))) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE table_name = 'online_lessons' AND record_id = ?");
        $count->execute([$lessonPk]);
        $total = (int)$count->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT id, username, action, new_values, created_at
            FROM audit_logs
            WHERE table_name = 'online_lessons' AND record_id = ?
            ORDER BY id DESC
            LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage));
        $stmt->execute([$lessonPk]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pages = max(1, (int)ceil($total / $perPage));
    ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Objects</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                        $refs = json_decode((string)($log['new_values'] ?? ''), true);
                        $refText = [];
                        if (is_array($refs)) {
                            foreach ($refs as $k => $v) {
                                if (is_scalar($v) && (preg_match('/_id$/', (string)$k) || in_array($k, ['publish_at', 'unpublish_at', 'publish', 'restored_from'], true))) {
                                    $refText[] = str_replace('_', ' ', (string)$k) . ' ' . $v;
                                }
                            }
                        }
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= $h(date('d M Y, g:i A', strtotime((string)$log['created_at']))) ?></td>
                        <td><?= $h((string)$log['username']) ?></td>
                        <td><?= $h(ucfirst(str_replace('_', ' ', preg_replace('/^online_lesson_/', '', (string)$log['action']) ?? ''))) ?></td>
                        <td class="small text-muted"><?= $h(implode(', ', $refText)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($logs === []): ?>
                    <tr><td colspan="4" class="text-muted p-4">No recorded changes yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="mt-3"><ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= min($pages, 30); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $h($selfUrl . '&tab=history&page=' . $p) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
