<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CourseService;

require_student();
ensure_online_lesson_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$path = ['groups' => [], 'next' => null, 'progress' => 0];
try {
    $path = (new CourseService($pdo))->studentPath($studentId);
} catch (Throwable $e) {
    error_log('learning path: ' . $e->getMessage());
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$icons = [
    'completed' => ['✓', 'text-success', 'Completed'],
    'in_progress' => ['◐', 'text-primary', 'In progress'],
    'available' => ['○', 'text-secondary', 'Ready to start'],
    'locked' => ['🔒', 'text-muted', 'Locked'],
    'payment' => ['💳', 'text-warning', 'Payment required'],
];

$pageTitle = 'Learning path';
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3" style="max-width: 960px;">
    <h1 class="h4 mb-1">Learning path</h1>
    <p class="text-muted">Your published lessons in order. Progress counts the parts of each lesson you have completed; marks are shown separately on each lesson’s result page.</p>

    <?php if ($path['next']): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="small text-muted">Continue learning</div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= $h($path['next']['title']) ?></div>
                    <div class="small text-muted"><?= $h($path['next']['subject']) ?> · <?= (int)$path['next']['percent'] ?>% complete</div>
                </div>
                <a class="btn btn-primary" href="<?= $h(student_online_lesson_url((int)$path['next']['timetable_id'], 0, 'overview')) ?>"><?= (int)$path['next']['percent'] > 0 ? 'Continue' : 'Start' ?></a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($path['groups'] === []): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 text-muted">No published lessons yet.</div>
    <?php endif; ?>

    <?php foreach ($path['groups'] as $group): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0"><?= $h($group['title']) ?><?= $group['is_course'] ? ' <span class="badge text-bg-light border">Course</span>' : '' ?></h2>
                <span class="small text-muted"><?= (int)$group['progress'] ?>% complete</span>
            </div>
            <div class="progress mb-3" style="height: 6px;" role="progressbar" aria-label="<?= $h($group['title']) ?> progress" aria-valuenow="<?= (int)$group['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= (int)$group['progress'] ?>%"></div>
            </div>
            <ol class="list-unstyled mb-0">
                <?php $lastUnit = null; foreach ($group['lessons'] as $lesson): [$icon, $cls, $label] = $icons[$lesson['state']]; ?>
                    <?php if ($group['is_course'] && $lesson['unit'] !== '' && $lesson['unit'] !== $lastUnit): $lastUnit = $lesson['unit']; ?>
                        <li class="small text-uppercase text-muted mt-3 mb-1"><?= $h($lesson['unit']) ?></li>
                    <?php endif; ?>
                    <li class="d-flex align-items-start gap-3 py-2 border-bottom">
                        <span class="<?= $cls ?> fs-5" aria-hidden="true" style="width: 1.5rem;"><?= $icon ?></span>
                        <div class="flex-grow-1">
                            <?php if ($lesson['state'] === 'locked'): ?>
                                <span class="text-muted"><?= $h($lesson['title']) ?></span>
                            <?php else: ?>
                                <a href="<?= $h(student_online_lesson_url($lesson['timetable_id'], 0, 'overview')) ?>"><?= $h($lesson['title']) ?></a>
                            <?php endif; ?>
                            <div class="small text-muted">
                                <?= $h(date('d M Y', strtotime($lesson['date']))) ?><?= $lesson['topic'] !== '' ? ' · ' . $h($lesson['topic']) : '' ?>
                                · <span class="visually-hidden"><?= $h($label) ?>, </span><?= in_array($lesson['state'], ['locked', 'payment'], true) ? $h($lesson['reason']) : (int)$lesson['percent'] . '%' ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
