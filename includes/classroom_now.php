<?php
declare(strict_types=1);

if (!function_exists('e')) {
    $helpers = __DIR__ . '/helpers.php';
    if (is_file($helpers)) {
        require_once $helpers;
    }
}
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$live = $classroomNow['live'] ?? null;
$next = $classroomNow['next'] ?? null;
$role = $classroomNow['role'] ?? '';
$liveCount = (int)($classroomNow['live_count'] ?? 0);
if (!$live && !$next && $liveCount < 1) {
    return;
}

$label = static function (array $row): string {
    return trim((string)($row['subject_name'] ?? $row['class_name'] ?? 'Class'));
};
$when = static function (array $row): string {
    $start = !empty($row['start_time']) ? date('g:i A', strtotime((string)$row['start_time'])) : '';
    $end = !empty($row['end_time']) ? date('g:i A', strtotime((string)$row['end_time'])) : '';
    if ($start !== '' && $end !== '') {
        return $start . ' – ' . $end;
    }
    return $start;
};
$href = static function (array $row): string {
    $id = (int)($row['id'] ?? $row['timetable_id'] ?? 0);
    $pub = (string)($row['public_id'] ?? '');
    return classroom_room_url($id, $pub);
};
?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/classroom.css?v=<?= is_file(__DIR__ . '/../assets/css/classroom.css') ? filemtime(__DIR__ . '/../assets/css/classroom.css') : '1' ?>">

<?php if ($live): ?>
    <?php
    $joinLateMinutes = (int)(classroom_settings($pdo ?? null)['join_late_minutes'] ?? 15);
    $liveJoinClosed = $role === 'student' && classroom_join_window_closed($live, $joinLateMinutes);
    ?>
    <section class="ck-now-card mb-3" aria-live="polite">
        <div class="ck-live-badge"><span class="ck-dot"></span> Live now</div>
        <h2><?= e($label($live)) ?></h2>
        <p>
            <?= e((string)($live['teacher_name'] ?? '')) ?>
            <?php if (!empty($live['start_time'])): ?> · <?= e($when($live)) ?><?php endif; ?>
            <?php if (!empty($live['enrolled'])): ?> · <?= (int)$live['enrolled'] ?> students<?php endif; ?>
            <?php if ($role === 'admin' && $liveCount > 1): ?> · <?= $liveCount ?> classes live<?php endif; ?>
        </p>
        <div class="ck-now-actions">
            <?php if ($role === 'student'): ?>
                <?php if ($liveJoinClosed): ?>
                    <button type="button" class="btn btn-secondary" disabled>Join closed</button>
                <?php else: ?>
                    <a class="btn btn-danger" href="<?= e($href($live)) ?>"><i class="bi bi-broadcast"></i> Join class</a>
                <?php endif; ?>
            <?php elseif ($role === 'teacher'): ?>
                <a class="btn btn-danger" href="<?= e($href($live)) ?>"><i class="bi bi-broadcast"></i> Open live class</a>
            <?php else: ?>
                <a class="btn btn-danger" href="<?= e(BASE_URL) ?>admin/classroom.php">View live classes</a>
                <a class="btn btn-outline-danger" href="<?= e($href($live)) ?>">Open</a>
            <?php endif; ?>
        </div>
    </section>
<?php elseif ($next && in_array(classroom_normalize_delivery_mode((string)($next['delivery_mode'] ?? 'physical')), ['online', 'hybrid'], true)): ?>
    <?php
    $state = (string)($next['display_state'] ?? classroom_display_state($next, ['status' => $next['meeting_status'] ?? '']));
    $soon = $state === 'starting_soon';
    ?>
    <section class="ck-now-card is-next mb-3">
        <div class="ck-live-badge" style="color:#5b7cfa"><span class="ck-dot" style="background:#5b7cfa"></span> <?= $soon ? 'Starting soon' : 'Next class' ?></div>
        <h2><?= e($label($next)) ?></h2>
        <p>
            <?= e((string)($next['teacher_name'] ?? '')) ?>
            · <?= e($when($next)) ?>
            <?php if (!empty($next['enrolled'])): ?> · <?= (int)$next['enrolled'] ?> students<?php endif; ?>
        </p>
        <div class="ck-now-actions">
            <?php if ($role === 'teacher'): ?>
                <a class="btn btn-primary" href="<?= e($href($next)) ?>"><i class="bi bi-play-fill"></i> Start class</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e($href($next)) ?>">View class</a>
            <?php endif; ?>
        </div>
    </section>
<?php elseif ($next && $role === 'teacher'): ?>
    <section class="ck-now-card is-next mb-3">
        <div class="ck-live-badge" style="color:#5b7cfa"><span class="ck-dot" style="background:#5b7cfa"></span> Next class</div>
        <h2><?= e($label($next)) ?></h2>
        <p><?= e($when($next)) ?> · <?= e(classroom_delivery_label((string)($next['delivery_mode'] ?? 'physical'))) ?></p>
        <div class="ck-now-actions">
            <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>campus/today.php">Today’s classes</a>
        </div>
    </section>
<?php endif; ?>
