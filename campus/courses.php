<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CourseService;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$service = new CourseService($pdo);
$courseId = (int)($_GET['course'] ?? $_POST['course_id'] ?? 0);
$classId = (int)($_GET['class'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token.');
        }
        if ($action === 'save_course') {
            $courseId = $service->saveCourse(
                $courseId,
                (string)($_POST['title'] ?? ''),
                (int)($_POST['subject_id'] ?? 0),
                (string)($_POST['description'] ?? ''),
                (int)($_POST['coverage_min_percent'] ?? 0),
                $userId,
                $isAdmin
            );
            $success = 'Course saved.';
        } elseif ($action === 'archive_course' || $action === 'restore_course') {
            $service->setArchived($courseId, $action === 'archive_course', $userId, $isAdmin);
            $success = $action === 'archive_course' ? 'Course archived. Lessons keep their topic link.' : 'Course restored.';
        } elseif ($action === 'add_unit') {
            $service->addUnit($courseId, (string)($_POST['title'] ?? ''), $userId, $isAdmin);
            $success = 'Unit added.';
        } elseif ($action === 'rename_unit') {
            $service->renameUnit((int)($_POST['unit_id'] ?? 0), (string)($_POST['title'] ?? ''), $userId, $isAdmin);
            $success = 'Unit renamed.';
        } elseif ($action === 'delete_unit') {
            $service->deleteUnit((int)($_POST['unit_id'] ?? 0), $userId, $isAdmin);
            $success = 'Unit removed. Its lessons are no longer linked to a topic; the lessons themselves were not changed.';
        } elseif ($action === 'save_topic') {
            $service->saveTopic(
                (int)($_POST['topic_id'] ?? 0),
                (int)($_POST['unit_id'] ?? 0),
                (string)($_POST['title'] ?? ''),
                (string)($_POST['objectives'] ?? ''),
                (int)($_POST['planned_lessons'] ?? 1),
                $userId,
                $isAdmin
            );
            $success = 'Topic saved.';
        } elseif ($action === 'delete_topic') {
            $service->deleteTopic((int)($_POST['topic_id'] ?? 0), $userId, $isAdmin);
            $success = 'Topic removed. Its lessons are no longer linked; the lessons themselves were not changed.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
        log_audit($pdo, 'course_' . $action, 'lm_courses', $courseId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$classes = function_exists('campus_staff_classes') ? campus_staff_classes($pdo, $isAdmin, $teacherId) : [];
$allowedClassIds = array_map(static fn (array $c): int => (int)$c['id'], $classes);
if ($classId > 0 && !in_array($classId, $allowedClassIds, true)) {
    $classId = 0;
}
$course = $courseId > 0 ? $service->course($courseId) : null;
$canEdit = $course ? $service->canEdit($course, $userId, $isAdmin) : true;
$coverage = null;
if ($course) {
    $scope = $classId > 0 ? [$classId] : ($isAdmin ? null : $allowedClassIds);
    $coverage = $service->coverage($courseId, $scope);
}
$courseList = $service->courses(true);

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$statusBadge = [
    'covered' => ['Covered', 'text-bg-success'],
    'in_progress' => ['In progress', 'text-bg-warning'],
    'not_started' => ['Not started', 'text-bg-secondary'],
];

$pageTitle = 'Courses';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-diagram-3 me-2"></i>Courses and syllabus coverage</h1>
        <p class="text-muted mb-0">Optional structure: Course → Unit → Topic. Lessons stay with their timetable class and are linked to a topic from the lesson manager.</p>
    </div>
</div>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <h2 class="h6">Courses</h2>
            <div class="list-group list-group-flush">
                <?php foreach ($courseList as $c): ?>
                    <a class="list-group-item list-group-item-action <?= (int)$c['id'] === $courseId ? 'active' : '' ?>" href="?course=<?= (int)$c['id'] ?>">
                        <?= $h((string)$c['title']) ?>
                        <span class="small <?= (int)$c['id'] === $courseId ? '' : 'text-muted' ?>">· <?= $h((string)($c['subject_name'] ?? 'Any subject')) ?> · <?= (int)$c['topic_count'] ?> topics<?= (int)$c['archived'] === 1 ? ' · archived' : '' ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if ($courseList === []): ?><p class="text-muted small mb-0">No courses yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h2 class="h6"><?= $course ? 'Course details' : 'New course' ?></h2>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_course">
                <input type="hidden" name="course_id" value="<?= $course && $canEdit ? $courseId : 0 ?>">
                <div class="col-12"><label class="form-label small" for="c_title">Title</label><input class="form-control" id="c_title" name="title" maxlength="200" required value="<?= $h($course && $canEdit ? (string)$course['title'] : '') ?>"></div>
                <div class="col-12">
                    <label class="form-label small" for="c_subject">Subject</label>
                    <select class="form-select" id="c_subject" name="subject_id">
                        <option value="0">Any subject</option>
                        <?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $course && $canEdit && (int)($course['subject_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= $h((string)$s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><label class="form-label small" for="c_desc">Description</label><textarea class="form-control" id="c_desc" name="description" rows="2"><?= $h($course && $canEdit ? (string)($course['description'] ?? '') : '') ?></textarea></div>
                <div class="col-12">
                    <label class="form-label small" for="c_cov">A lesson counts toward coverage when class completion reaches</label>
                    <div class="input-group">
                        <input class="form-control" type="number" id="c_cov" name="coverage_min_percent" min="0" max="100" value="<?= $course && $canEdit ? (int)$course['coverage_min_percent'] : 0 ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">0 = the lesson counts once it is published and the class date has passed.</div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" type="submit"><?= $course && $canEdit ? 'Save course' : 'Create course' ?></button>
                    <?php if ($course && $canEdit): ?><a class="btn btn-outline-secondary btn-sm" href="?">New course</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (!$course): ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 text-muted">Choose a course, or create one.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
                    <div>
                        <h2 class="h5 mb-1"><?= $h((string)$course['title']) ?></h2>
                        <p class="mb-0">Syllabus coverage: <strong><?= (int)$coverage['percent'] ?>%</strong> (<?= (int)$coverage['covered'] ?> of <?= (int)$coverage['topics'] ?> topics)</p>
                        <p class="small text-muted mb-0">A topic is covered when its planned number of lessons were published and taught<?= (int)$course['coverage_min_percent'] > 0 ? ', with class completion of at least ' . (int)$course['coverage_min_percent'] . '%' : '' ?>.</p>
                    </div>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="course" value="<?= $courseId ?>">
                        <select class="form-select form-select-sm" name="class" aria-label="Class" onchange="this.form.submit()">
                            <option value="0"><?= $isAdmin ? 'All classes' : 'All my classes' ?></option>
                            <?php foreach ($classes as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= $classId === (int)$cl['id'] ? 'selected' : '' ?>><?= $h((string)$cl['name']) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php if ($canEdit): ?>
                    <form method="post" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="action" value="<?= (int)$course['archived'] === 1 ? 'restore_course' : 'archive_course' ?>">
                        <button class="btn btn-link btn-sm p-0" type="submit"><?= (int)$course['archived'] === 1 ? 'Restore course' : 'Archive course' ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <?php foreach ($coverage['units'] as $unit): ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <?php if ($canEdit): ?>
                            <form method="post" class="d-flex gap-2 flex-grow-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                <input type="hidden" name="action" value="rename_unit">
                                <input type="hidden" name="unit_id" value="<?= (int)$unit['id'] ?>">
                                <input class="form-control form-control-sm fw-semibold" name="title" value="<?= $h((string)$unit['title']) ?>" aria-label="Unit title">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Rename</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Remove this unit and its topics? Lessons are kept.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                <input type="hidden" name="action" value="delete_unit">
                                <input type="hidden" name="unit_id" value="<?= (int)$unit['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Remove unit</button>
                            </form>
                        <?php else: ?>
                            <h3 class="h6 mb-0"><?= $h((string)$unit['title']) ?></h3>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Topic</th><th>Status</th><th>Lessons</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php foreach ($unit['topics'] as $topic): $cov = $topic['coverage']; ?>
                                <tr>
                                    <td>
                                        <?= $h((string)$topic['title']) ?>
                                        <?php if (!empty($topic['objectives'])): ?><div class="small text-muted" style="white-space: pre-wrap;"><?= $h((string)$topic['objectives']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><span class="badge <?= $statusBadge[$cov['status']][1] ?>"><?= $statusBadge[$cov['status']][0] ?></span>
                                        <div class="small text-muted"><?= (int)$cov['qualifying'] ?> of <?= (int)$cov['planned'] ?> planned</div></td>
                                    <td class="small">
                                        <?php foreach ($topic['lessons'] as $l): ?>
                                            <div>
                                                <a href="<?= $h(BASE_URL . 'campus/lesson_manage.php?lesson=' . (int)$l['timetable_id']) ?>"><?= $h(date('d M', strtotime($l['date'])) . ' · ' . $l['title']) ?></a>
                                                <span class="text-muted">· <?= $h($l['class_name']) ?> · <?= $l['released'] ? (int)$l['avg_completion'] . '% complete' : 'not published' ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($topic['lessons'] === []): ?><span class="text-muted">No lessons linked</span><?php endif; ?>
                                    </td>
                                    <?php if ($canEdit): ?>
                                        <td class="text-nowrap">
                                            <details>
                                                <summary class="small">Edit</summary>
                                                <form method="post" class="mt-2" style="min-width: 240px;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                                    <input type="hidden" name="action" value="save_topic">
                                                    <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                                                    <input class="form-control form-control-sm mb-1" name="title" value="<?= $h((string)$topic['title']) ?>" aria-label="Topic title">
                                                    <textarea class="form-control form-control-sm mb-1" name="objectives" rows="2" aria-label="Objectives"><?= $h((string)($topic['objectives'] ?? '')) ?></textarea>
                                                    <input class="form-control form-control-sm mb-1" type="number" name="planned_lessons" min="1" max="50" value="<?= (int)$topic['planned_lessons'] ?>" aria-label="Planned lessons">
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                                </form>
                                                <form method="post" class="mt-1" onsubmit="return confirm('Remove this topic? Lessons are kept.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                                    <input type="hidden" name="action" value="delete_topic">
                                                    <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit">Remove topic</button>
                                                </form>
                                            </details>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($canEdit): ?>
                        <form method="post" class="row g-2 mt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="course_id" value="<?= $courseId ?>">
                            <input type="hidden" name="action" value="save_topic">
                            <input type="hidden" name="unit_id" value="<?= (int)$unit['id'] ?>">
                            <div class="col-md-5"><input class="form-control form-control-sm" name="title" placeholder="New topic" required aria-label="New topic"></div>
                            <div class="col-md-4"><input class="form-control form-control-sm" name="objectives" placeholder="Objectives (optional)" aria-label="Objectives"></div>
                            <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="planned_lessons" min="1" max="50" value="1" title="Planned lessons" aria-label="Planned lessons"></div>
                            <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Add</button></div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($canEdit): ?>
                <form method="post" class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="action" value="add_unit">
                    <input class="form-control" name="title" placeholder="New unit title" required aria-label="New unit title">
                    <button class="btn btn-outline-primary" type="submit">Add unit</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
