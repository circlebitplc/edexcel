<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LiveKitRoomService;
use Edexcel\Services\OnlineMeetingService;

require_admin();
ensure_classroom_schema($pdo);

$svc = new OnlineMeetingService($pdo);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $lessonId = (int)($_POST['lesson'] ?? 0);
        try {
            if ($action === 'end' && $lessonId > 0) {
                $svc->end($lessonId, (int)($_SESSION['user_id'] ?? 0));
                $success = 'That class was ended.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$live = $svc->liveMeetings();
$cfg = livekit_config($pdo);
$settings = classroom_settings($pdo);
$health = ['ok' => false, 'message' => 'Live classroom is not connected yet.'];
if (livekit_ready($pdo)) {
    $health = LiveKitRoomService::fromConfig($cfg)->testConnection();
}

$recent = [];
try {
    $recent = $pdo->query("
        SELECT om.*, tt.date, tt.start_time, tt.end_time,
               s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
        FROM online_meetings om
        JOIN timetable tt ON tt.id = om.timetable_id
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        JOIN teachers t ON t.id = tt.teacher_id
        ORDER BY om.updated_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-broadcast text-danger"></i> Online classes</h1>
            <p class="text-muted mb-0">See what is live now, end a stuck class, and check the video service.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>admin/settings.php?tab=classroom">Classroom settings</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="alert <?= $health['ok'] ? 'alert-success' : 'alert-warning' ?>">
        <strong><?= $health['ok'] ? 'Video service is OK.' : 'Needs setup.' ?></strong>
        <?= e($health['message']) ?>
        <?php if (empty($settings['enabled'])): ?>
            Live classroom is currently turned off in settings.
        <?php endif; ?>
        <?php if (!empty($settings['auto_record']) && !livekit_is_cloud($cfg) && !livekit_s3_configured($cfg)): ?>
            Recording to Bunny needs MinIO/S3 on the VPS. Classes can still run.
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent fw-bold">Live now</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Class</th><th>Teacher</th><th>Students in room</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$live): ?>
                    <tr><td colspan="5" class="text-muted">No classes are live.</td></tr>
                <?php endif; ?>
                <?php foreach ($live as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e((string)$row['subject_name']) ?></strong><br>
                            <small><?= e((string)$row['class_name']) ?></small>
                        </td>
                        <td><?= e((string)$row['teacher_name']) ?></td>
                        <td><?= (int)$row['in_room'] ?></td>
                        <td><span class="badge bg-danger">Live</span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(classroom_room_url((int)$row['timetable_id'], (string)$row['public_id'])) ?>">Open</a>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="end">
                                <input type="hidden" name="lesson" value="<?= (int)$row['timetable_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">End class</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent fw-bold">Recent meetings</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>When</th><th>Class</th><th>Teacher</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td>
                            <?= e(date('d M', strtotime((string)$row['date']))) ?>
                            <?= e(date('g:i A', strtotime((string)$row['start_time']))) ?>
                        </td>
                        <td><?= e((string)$row['subject_name']) ?></td>
                        <td><?= e((string)$row['teacher_name']) ?></td>
                        <td><?= e(classroom_status_label((string)$row['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
