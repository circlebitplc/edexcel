<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status' => (string)($_GET['status'] ?? ''),
    'subject' => (string)($_GET['subject'] ?? ''),
    'topic' => (string)($_GET['topic'] ?? ''),
    'from' => (string)($_GET['from'] ?? ''),
    'to' => (string)($_GET['to'] ?? ''),
];
$modules = new LearningModuleService($pdo);
$list = $modules->teacherModules($teacherId, $isAdmin, $filters, $page);
$activity = $modules->teacherActivity($teacherId, $isAdmin);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$stateLabels = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'closed' => 'Closed', 'archived' => 'Archived'];
$pages = max(1, (int)ceil($list['total'] / 20));

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Learning modules</h1>
        <p class="text-muted mb-0">Drafts, published lessons, and work that still needs a mark.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-self-start">
        <a class="btn btn-outline-primary" href="<?= $h(BASE_URL . 'campus/marking_queue.php') ?>">Marking queue</a>
        <a class="btn btn-outline-primary" href="<?= $h(BASE_URL . 'campus/lesson_calendar.php') ?>">Lesson planner</a>
        <a class="btn btn-outline-secondary" href="<?= $h(BASE_URL . 'campus/resource_library.php') ?>">Resource library</a>
    </div>
</div>
<div class="row g-3 mb-3">
    <?php foreach ([
        ['Draft', $list['drafts'], '?status=draft'],
        ['Scheduled', $list['scheduled'], '?status=scheduled'],
        ['Published', $list['published'], '?status=published'],
        ['Archived', $list['archived'], '?status=archived'],
    ] as [$label, $value, $href]): ?>
        <div class="col-6 col-md-3"><a class="card border-0 shadow-sm rounded-4 text-decoration-none text-body" href="<?= $h($href) ?>"><div class="card-body"><div class="text-muted small"><?= $h($label) ?></div><div class="h4 mb-0"><?= (int)$value ?></div></div></a></div>
    <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><a class="card border-0 shadow-sm rounded-4 text-decoration-none text-body" href="<?= $h(BASE_URL . 'campus/marking_queue.php?status=waiting') ?>"><div class="card-body"><div class="text-muted small">Needs marking</div><div class="h4 mb-0"><?= (int)$list['needs_marking'] ?></div></div></a></div>
    <div class="col-6 col-md-3"><a class="card border-0 shadow-sm rounded-4 text-decoration-none text-body" href="<?= $h(BASE_URL . 'campus/lesson_calendar.php?view=term') ?>"><div class="card-body"><div class="text-muted small">Next 14 days without a lesson</div><div class="h4 mb-0"><?= (int)$activity['unplanned'] ?> <span class="small text-muted fw-normal">of <?= (int)$activity['upcoming'] ?></span></div></div></a></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Activity attempts, last 7 days</div><div class="h4 mb-0"><?= (int)$activity['attempts_7d'] ?> <span class="small text-muted fw-normal"><?= (int)$activity['active_students_7d'] ?> student<?= $activity['active_students_7d'] === 1 ? '' : 's' ?></span></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Submissions, last 7 days</div><div class="h4 mb-0"><?= (int)$activity['submissions_7d'] ?></div></div></div></div>
</div>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-2">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="">Any</option>
            <?php foreach (['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" value="<?= $h($filters['subject']) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="topic">Topic</label><input class="form-control" id="topic" name="topic" value="<?= $h($filters['topic']) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="from">From</label><input class="form-control" id="from" type="date" name="from" value="<?= $h($filters['from']) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="to">To</label><input class="form-control" id="to" type="date" name="to" value="<?= $h($filters['to']) ?>"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
</form>
<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Lesson</th><th>Class</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list['rows'] as $row): ?>
            <tr>
                <td><?= $h((string)$row['title']) ?><div class="small text-muted"><?= $h((string)$row['subject_name']) ?></div></td>
                <td><?= $h((string)$row['class_name']) ?></td>
                <td><?= $h((string)$row['date']) ?></td>
                <?php $rowState = !empty($row['archived']) ? 'archived' : \Edexcel\Services\OnlineLessonService::publicationState($row); ?>
                <td><?= $h($stateLabels[$rowState] ?? 'Draft') ?><?= $rowState === 'scheduled' && !empty($row['publish_at']) ? '<div class="small text-muted">' . $h(date('d M Y, g:i A', strtotime((string)$row['publish_at']))) . '</div>' : '' ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= $h(campus_online_lesson_url((int)$row['timetable_id'])) ?>">Open</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $h(BASE_URL . 'campus/lesson_manage.php?lesson=' . (int)$row['timetable_id']) ?>">Manage</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($list['rows'] === []): ?>
            <tr><td colspan="5" class="text-muted p-4">No learning modules match this filter.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pages > 1): ?>
    <nav class="d-flex gap-2" aria-label="Pages">
        <?php for ($i = 1; $i <= $pages && $i <= 10; $i++): ?>
            <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?<?= $h(http_build_query(array_merge($filters, ['page' => $i]))) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
