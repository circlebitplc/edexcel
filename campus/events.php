<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_staff();
ensure_campus_schema($pdo);
if (function_exists('ensure_ops_schema')) {
    ensure_ops_schema($pdo);
}

$isAdmin = is_admin();
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$editing = null;
$hasCreatedBy = campus_column_exists($pdo, 'student_events', 'created_by');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        if (isset($_POST['delete_event'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($isAdmin || !$hasCreatedBy) {
                $pdo->prepare("DELETE FROM student_events WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("DELETE FROM student_events WHERE id = ? AND created_by = ?")->execute([$id, $userId]);
                if ($pdo->rowCount() < 1) {
                    throw new RuntimeException('You can only remove events you published.');
                }
            }
            $success = 'Event removed.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $date = trim((string)($_POST['event_date'] ?? ''));
            $start = trim((string)($_POST['start_time'] ?? ''));
            $end = trim((string)($_POST['end_time'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Title and date are required.');
            }
            $params = [$title, $date, $start !== '' ? $start : null, $end !== '' ? $end : null, $desc !== '' ? $desc : null];
            if ($id > 0) {
                if ($isAdmin || !$hasCreatedBy) {
                    $pdo->prepare("UPDATE student_events SET title=?, event_date=?, start_time=?, end_time=?, description=? WHERE id=?")->execute([...$params, $id]);
                } else {
                    $pdo->prepare("UPDATE student_events SET title=?, event_date=?, start_time=?, end_time=?, description=? WHERE id=? AND created_by=?")->execute([...$params, $id, $userId]);
                    if ($pdo->rowCount() < 1) {
                        throw new RuntimeException('You can only edit events you published.');
                    }
                }
                $success = 'Event updated.';
            } elseif ($hasCreatedBy) {
                $pdo->prepare("INSERT INTO student_events (title, event_date, start_time, end_time, description, created_by) VALUES (?,?,?,?,?,?)")->execute([...$params, $userId]);
                $success = 'Event published.';
            } else {
                $pdo->prepare("INSERT INTO student_events (title, event_date, start_time, end_time, description) VALUES (?,?,?,?,?)")->execute($params);
                $success = 'Event published.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAdmin || !$hasCreatedBy) {
        $s = $pdo->prepare("SELECT * FROM student_events WHERE id = ?");
        $s->execute([$editId]);
    } else {
        $s = $pdo->prepare("SELECT * FROM student_events WHERE id = ? AND created_by = ?");
        $s->execute([$editId, $userId]);
    }
    $editing = $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($isAdmin || !$hasCreatedBy) {
    $list = $pdo->query("SELECT * FROM student_events ORDER BY event_date DESC, start_time DESC LIMIT 80")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $s = $pdo->prepare("SELECT * FROM student_events WHERE created_by = ? ORDER BY event_date DESC, start_time DESC LIMIT 80");
    $s->execute([$userId]);
    $list = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-3"><i class="bi bi-calendar-event"></i> College events</h1>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <h5><?= $editing ? 'Edit event' : 'Add event' ?></h5>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
                    <label class="form-label">Title</label>
                    <input class="form-control mb-2" name="title" required value="<?= e((string)($editing['title'] ?? '')) ?>">
                    <label class="form-label">Date</label>
                    <input class="form-control mb-2" type="date" name="event_date" required value="<?= e((string)($editing['event_date'] ?? '')) ?>">
                    <div class="row g-2">
                        <div class="col"><label class="form-label">Start</label><input class="form-control mb-2" type="time" name="start_time" value="<?= e(substr((string)($editing['start_time'] ?? ''),0,5)) ?>"></div>
                        <div class="col"><label class="form-label">End</label><input class="form-control mb-2" type="time" name="end_time" value="<?= e(substr((string)($editing['end_time'] ?? ''),0,5)) ?>"></div>
                    </div>
                    <label class="form-label">Notes</label>
                    <textarea class="form-control mb-3" name="description" rows="3"><?= e((string)($editing['description'] ?? '')) ?></textarea>
                    <button class="btn btn-primary rounded-pill"><?= $editing ? 'Save' : 'Publish' ?></button>
                    <?php if ($editing): ?><a class="btn btn-outline-secondary rounded-pill" href="events.php">Cancel</a><?php endif; ?>
                </form>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <?php foreach ($list as $row): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div>
                            <strong><?= e((string)$row['title']) ?></strong>
                            <div class="small text-muted"><?= e(date('d M Y', strtotime((string)$row['event_date']))) ?></div>
                        </div>
                        <div class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="?id=<?= (int)$row['id'] ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Remove this event?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete_event" value="1">Delete</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$list): ?><p class="text-muted mb-0">No events yet.</p><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
