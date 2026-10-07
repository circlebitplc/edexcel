<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonResultBuilder;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$filters = marking_queue_filters($_GET);
$modules = new LearningModuleService($pdo);

if (!empty($_GET['next'])) {
    $next = $modules->nextUnmarked($teacherId, $isAdmin, $filters);
    if ($next) {
        header('Location: ' . marking_queue_item_url($next, $filters));
        exit;
    }
    $allDone = true;
}

$rows = $modules->markingQueue($teacherId, $isAdmin, $page, $filters);
$classes = campus_staff_classes($pdo, $isAdmin, $teacherId);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$filterQuery = http_build_query(array_filter($filters, static fn ($v) => $v !== '' && $v !== 0));

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Marking queue</h1>
        <p class="text-muted mb-0">Written answers and submissions that are still open. Each one is marked by you; nothing is marked automatically.</p>
    </div>
    <div class="d-flex gap-2 align-self-start">
        <a class="btn btn-primary" href="?<?= $h($filterQuery . ($filterQuery !== '' ? '&' : '') . 'next=1') ?>">Next unmarked</a>
        <a class="btn btn-outline-secondary" href="<?= $h(BASE_URL . 'campus/learning_modules.php') ?>">Learning modules</a>
    </div>
</div>
<?php if (!empty($allDone)): ?>
    <div class="alert alert-success">Nothing left to mark for this filter.</div>
<?php endif; ?>
<form method="get" class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <label class="form-label small" for="mq-class">Class</label>
        <select class="form-select form-select-sm" id="mq-class" name="class_id">
            <option value="0">All</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $filters['class_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="mq-kind">Type</label>
        <select class="form-select form-select-sm" id="mq-kind" name="kind">
            <option value="">All</option>
            <option value="written" <?= $filters['kind'] === 'written' ? 'selected' : '' ?>>Written answers</option>
            <option value="submission" <?= $filters['kind'] === 'submission' ? 'selected' : '' ?>>Assignments</option>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="mq-status">Status</label>
        <select class="form-select form-select-sm" id="mq-status" name="status">
            <option value="">All open</option>
            <option value="waiting" <?= $filters['status'] === 'waiting' ? 'selected' : '' ?>>Waiting for me</option>
            <option value="resubmit" <?= $filters['status'] === 'resubmit' ? 'selected' : '' ?>>Resubmission requested</option>
        </select>
    </div>
    <div class="col-6 col-md-2"><label class="form-label small" for="mq-from">Lesson from</label><input class="form-control form-control-sm" id="mq-from" type="date" name="from" value="<?= $h($filters['from']) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="mq-to">Lesson to</label><input class="form-control form-control-sm" id="mq-to" type="date" name="to" value="<?= $h($filters['to']) ?>"></div>
    <div class="col-6 col-md-1">
        <label class="form-label small" for="mq-order">Order</label>
        <select class="form-select form-select-sm" id="mq-order" name="order">
            <option value="oldest">Oldest</option>
            <option value="newest" <?= $filters['order'] === 'newest' ? 'selected' : '' ?>>Newest</option>
        </select>
    </div>
    <?php if ($filters['lesson'] > 0): ?><input type="hidden" name="lesson" value="<?= (int)$filters['lesson'] ?>"><?php endif; ?>
    <div class="col-12 col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Filter</button></div>
</form>
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr><th>Student</th><th>Class</th><th>Lesson</th><th>Activity</th><th>Submitted</th><th>Maximum marks</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $h((string)$row['student_name']) ?></td>
                <td><?= $h((string)$row['class_name']) ?></td>
                <td><a href="?<?= $h(http_build_query(array_merge(array_filter($filters), ['lesson' => (int)$row['timetable_id']]))) ?>" title="Show only this lesson"><?= $h((string)$row['lesson_title']) ?></a></td>
                <td><?= $h((string)$row['activity_title']) ?> <span class="small text-muted"><?= (string)$row['kind'] === 'written' ? 'written answer' : 'assignment' ?></span></td>
                <td><?= $row['submitted_at'] ? $h(date('d M Y, g:i A', strtotime((string)$row['submitted_at']))) : '—' ?></td>
                <td><?= $row['max_marks'] === null ? '—' : $h(LessonResultBuilder::formatMark((float)$row['max_marks'])) ?></td>
                <td><?= (string)$row['status'] === 'pending' ? 'Pending' : $h(LearningModuleService::submissionLabel((string)$row['status'])) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= $h(marking_queue_item_url($row, $filters)) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="8" class="text-muted p-4">Nothing is waiting for a mark.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<div class="d-flex gap-2">
    <?php if ($page > 1): ?>
        <a class="btn btn-outline-secondary" href="?<?= $h(http_build_query(array_merge(array_filter($filters), ['page' => $page - 1]))) ?>">Previous</a>
    <?php endif; ?>
    <?php if (count($rows) === 30): ?>
        <a class="btn btn-outline-secondary" href="?<?= $h(http_build_query(array_merge(array_filter($filters), ['page' => $page + 1]))) ?>">Next page</a>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
