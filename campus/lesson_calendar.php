<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LessonAuthoringService;
use Edexcel\Services\LessonPlannerService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$planner = new LessonPlannerService($pdo);
$lessons = new OnlineLessonService($pdo);
$authoring = new LessonAuthoringService($pdo, $lessons);

$view = in_array((string)($_GET['view'] ?? ''), LessonPlannerService::VIEWS, true) ? (string)$_GET['view'] : 'week';
$anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? (string)$_GET['date'] : date('Y-m-d');
$classes = campus_staff_classes($pdo, $isAdmin, $teacherId);
$allowedClassIds = array_map(static fn (array $c): int => (int)$c['id'], $classes);
$classId = (int)($_GET['class'] ?? 0);
if ($classId > 0 && !$isAdmin && !in_array($classId, $allowedClassIds, true)) {
    $classId = 0;
}
$error = '';
$success = '';
$skipped = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token.');
        }
        if ((string)($_POST['action'] ?? '') === 'bulk_drafts') {
            $ids = array_map('intval', (array)($_POST['timetable_ids'] ?? []));
            $result = $planner->bulkCreateDrafts($ids, $teacherId, $isAdmin, $userId, (int)($_POST['template_id'] ?? 0));
            foreach ($result['created'] as $lessonId) {
                log_audit($pdo, 'lesson_bulk_draft_created', 'online_lessons', $lessonId, null, ['template_id' => (int)($_POST['template_id'] ?? 0)]);
            }
            $success = count($result['created']) . ' draft lesson' . (count($result['created']) === 1 ? '' : 's') . ' created. Nothing was published and students were not notified.';
            $skipped = $result['skipped'];
        }
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not create the drafts.';
        if (!$e instanceof RuntimeException) {
            error_log('lesson_calendar: ' . $e->getMessage());
        }
    }
}

$range = LessonPlannerService::range($view, $anchor);
$slots = $planner->slots($teacherId, $isAdmin, $range['from'], $range['to'], $classId);
$counts = ['none' => 0, 'draft' => 0, 'scheduled' => 0, 'published' => 0, 'closed' => 0, 'archived' => 0, 'over' => 0];
$byDate = [];
foreach ($slots as $slot) {
    $counts[$slot['state']] = ($counts[$slot['state']] ?? 0) + 1;
    if (($slot['duration']['state'] ?? '') === 'over') {
        $counts['over']++;
    }
    $byDate[$slot['date']][] = $slot;
}
$templates = $authoring->templatesFor($userId, $isAdmin);

$timeline = null;
$timelineSlot = null;
$timelineTt = (int)($_GET['timeline'] ?? 0);
if ($timelineTt > 0) {
    $timelineSlot = (new RecordingService($pdo))->lessonForStaff($timelineTt, $teacherId, $isAdmin);
    $timelineLesson = $timelineSlot ? $lessons->findByTimetable($timelineTt) : null;
    if ($timelineLesson) {
        $timeline = LessonPlannerService::timeline(
            $lessons->items((int)$timelineLesson['id']),
            $authoring->sections((int)$timelineLesson['id']),
            LessonPlannerService::plannedMinutes($timelineLesson, $timelineSlot)
        );
        $timeline['title'] = (string)$timelineLesson['title'];
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$stateBadge = [
    'none' => ['No lesson', 'text-bg-light border'],
    'draft' => ['Draft', 'text-bg-secondary'],
    'scheduled' => ['Scheduled', 'text-bg-info'],
    'published' => ['Published', 'text-bg-success'],
    'closed' => ['Closed', 'text-bg-warning'],
    'archived' => ['Archived', 'text-bg-dark'],
];
$qs = static fn (array $over) => BASE_URL . 'campus/lesson_calendar.php?' . http_build_query(array_merge(['view' => $view, 'date' => $anchor, 'class' => $classId ?: null], $over));

$pageTitle = 'Lesson planner';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-calendar3 me-2"></i>Lesson planner</h1>
        <p class="text-muted mb-0">Your timetabled classes with their learning module status. Durations use each lesson’s planned time, or the class length when none is set.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= $h(BASE_URL . 'campus/online_lesson.php') ?>">All learning modules</a>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if ($skipped !== []): ?>
    <div class="alert alert-warning"><strong>Some slots were skipped</strong>
        <ul class="mb-0"><?php foreach ($skipped as $row): ?><li>Class slot #<?= (int)$row['timetable_id'] ?>: <?= $h($row['reason']) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($timelineTt > 0): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="timeline">
        <?php if (!$timelineSlot): ?>
            <p class="text-muted mb-0">That class was not found or you cannot manage it.</p>
        <?php elseif ($timeline === null): ?>
            <p class="text-muted mb-0">This class has no learning module yet.</p>
        <?php else: ?>
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <h2 class="h5 mb-0">Timeline: <?= $h($timeline['title']) ?></h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="<?= $h(campus_online_lesson_url($timelineTt)) ?>">Edit lesson</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $h($qs([])) ?>">Close</a>
                </div>
            </div>
            <p class="small text-muted">
                Estimated <?= (int)$timeline['total'] ?> min<?= $timeline['planned'] !== null ? ' of ' . (int)$timeline['planned'] . ' min planned' : '' ?>.
                <?php if ($timeline['unestimated'] > 0): ?><?= (int)$timeline['unestimated'] ?> part<?= $timeline['unestimated'] === 1 ? ' has' : 's have' ?> no estimate.<?php endif; ?>
            </p>
            <?php if ($timeline['over'] > 0): ?>
                <div class="alert alert-warning py-2">Estimated duration exceeds the planned duration by <?= (int)$timeline['over'] ?> minutes.</div>
            <?php endif; ?>
            <?php if ($timeline['rows'] === []): ?>
                <p class="text-muted mb-0">No items yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Start</th><th>Part</th><th>Minutes</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($timeline['rows'] as $row): ?>
                            <tr class="<?= $row['over'] ? 'table-warning' : '' ?>">
                                <td class="text-nowrap"><?= (int)$row['start'] ?>–<?= (int)$row['end'] ?> min</td>
                                <td><?= $h($row['title'] !== '' ? $row['title'] : ucfirst($row['type'])) ?> <span class="small text-muted"><?= $h(str_replace('_', ' ', $row['type'])) ?></span></td>
                                <td><?= $row['minutes'] === null ? '<span class="text-muted">—</span>' : (int)$row['minutes'] ?></td>
                                <td class="small"><?= $row['over'] ? 'Past planned time' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
    <div class="btn-group" role="group" aria-label="Calendar view">
        <?php foreach (LessonPlannerService::VIEWS as $v): ?>
            <a class="btn btn-sm <?= $view === $v ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= $h(BASE_URL . 'campus/lesson_calendar.php?' . http_build_query(['view' => $v, 'date' => $anchor, 'class' => $classId ?: null])) ?>"><?= $v === 'term' ? 'Term (' . LessonPlannerService::TERM_WEEKS . ' weeks)' : ucfirst($v) ?></a>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="view" value="<?= $h($view) ?>">
    <div>
        <label class="form-label small mb-0" for="cal-date">From</label>
        <input class="form-control form-control-sm" type="date" id="cal-date" name="date" value="<?= $h($anchor) ?>">
    </div>
    <div>
        <label class="form-label small mb-0" for="cal-class">Class</label>
        <select class="form-select form-select-sm" id="cal-class" name="class">
            <option value="0">All my classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-sm btn-outline-secondary" type="submit">Show</button>
    <div class="ms-auto d-flex gap-1 align-items-center">
        <a class="btn btn-sm btn-outline-secondary" href="<?= $h($qs(['date' => $range['prev']])) ?>" aria-label="Previous">‹</a>
        <span class="small fw-semibold px-2"><?= $h($range['label']) ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $h($qs(['date' => $range['next']])) ?>" aria-label="Next">›</a>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3 small">
    <?php foreach ($stateBadge as $key => [$label, $cls]): ?>
        <span class="badge <?= $cls ?>"><?= $h($label) ?>: <?= (int)($counts[$key] ?? 0) ?></span>
    <?php endforeach; ?>
    <?php if ($counts['over'] > 0): ?><span class="badge text-bg-warning">Over planned time: <?= (int)$counts['over'] ?></span><?php endif; ?>
</div>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_drafts">
    <?php if ($byDate === []): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 text-muted">No online, in-college or hybrid classes in this period.</div>
    <?php endif; ?>
    <?php foreach ($byDate as $date => $daySlots): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-2">
            <div class="card-header bg-transparent fw-semibold <?= $date === date('Y-m-d') ? 'text-primary' : '' ?>"><?= $h(date('l, d M Y', strtotime($date))) ?></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($daySlots as $slot): [$label, $cls] = $stateBadge[$slot['state']] ?? $stateBadge['draft']; ?>
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                        <?php if ($slot['state'] === 'none'): ?>
                            <input class="form-check-input mt-0" type="checkbox" name="timetable_ids[]" value="<?= (int)$slot['timetable_id'] ?>" id="slot-<?= (int)$slot['timetable_id'] ?>" aria-label="Create a draft for this class">
                        <?php else: ?>
                            <span style="width: 1em;"></span>
                        <?php endif; ?>
                        <span class="small text-muted text-nowrap"><?= $h(date('g:i A', strtotime($slot['start_time']))) ?>–<?= $h(date('g:i A', strtotime($slot['end_time']))) ?></span>
                        <label class="flex-grow-1 mb-0" for="slot-<?= (int)$slot['timetable_id'] ?>">
                            <span class="fw-semibold"><?= $h($slot['subject_name']) ?></span>
                            <span class="text-muted">· <?= $h($slot['class_name']) ?><?= $isAdmin ? ' · ' . $h($slot['teacher_name']) : '' ?> · <?= $h(ucfirst($slot['delivery_mode'])) ?></span>
                            <?php if ($slot['title'] !== ''): ?><div class="small"><?= $h($slot['title']) ?> · <?= (int)$slot['item_count'] ?> item<?= $slot['item_count'] === 1 ? '' : 's' ?></div><?php endif; ?>
                        </label>
                        <span class="badge <?= $cls ?>"><?= $h($slot['state'] === 'scheduled' && $slot['publish_at'] !== '' ? 'Scheduled ' . date('d M g:i A', strtotime($slot['publish_at'])) : $label) ?></span>
                        <?php if (is_array($slot['duration']) && $slot['duration']['estimated'] !== null): ?>
                            <span class="badge <?= $slot['duration']['state'] === 'over' ? 'text-bg-warning' : 'text-bg-light border' ?>" title="<?= $h($slot['duration']['message']) ?>">
                                <?= (int)$slot['duration']['estimated'] ?><?= $slot['duration']['planned'] !== null ? '/' . (int)$slot['duration']['planned'] : '' ?> min
                            </span>
                        <?php endif; ?>
                        <?php if ($slot['lesson_id'] > 0): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $h($qs(['timeline' => $slot['timetable_id']])) ?>#timeline">Timeline</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= $h(campus_online_lesson_url($slot['timetable_id'])) ?>"><?= $slot['lesson_id'] > 0 ? 'Open' : 'Plan' ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
    <?php if ($counts['none'] > 0): ?>
        <div class="card border-0 shadow-sm rounded-4 p-3 mt-3">
            <div class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label small mb-0" for="bulk-template">Start each draft from</label>
                    <select class="form-select form-select-sm" name="template_id" id="bulk-template">
                        <option value="0">An empty lesson</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= $h($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Create drafts for ticked classes</button>
                <span class="small text-muted">Up to <?= LessonPlannerService::BULK_LIMIT ?> at a time. Drafts stay hidden from students until you publish each one.</span>
            </div>
        </div>
    <?php endif; ?>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
