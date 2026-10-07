<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$error = '';
$success = '';

$teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $id = (int)($_POST['timetable_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $stmt = $pdo->prepare("
            SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name, r.name AS room_name
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            LEFT JOIN rooms r ON r.id = tt.room_id
            WHERE tt.id = ? AND tt.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            throw new RuntimeException('Lesson not found.');
        }
        if (!$isAdmin && (int)$entry['teacher_id'] !== $teacherId) {
            throw new RuntimeException('You can only update your own lessons.');
        }

        $when = date('D d M, h:i A', strtotime($entry['date'] . ' ' . $entry['start_time']));
        $students = campus_class_student_ids($pdo, (int)$entry['class_id']);

        if ($action === 'cancel') {
            $reason = trim((string)($_POST['reason'] ?? 'Class cancelled'));
            $pdo->prepare("
                UPDATE timetable
                SET lesson_status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), substitute_teacher_id = NULL
                WHERE id = ?
            ")->execute([$reason, $id]);
            campus_notify_students(
                $pdo,
                $students,
                "🚫 *Class cancelled*\n\n*{$entry['subject_name']}* ({$entry['class_name']})\n{$when}\nRoom: {$entry['room_name']}\n\n{$reason}",
                'CLASS_CANCELLED'
            );
            try {
                (new \Edexcel\Services\CommunicationEventService($pdo))->timetableChanged(
                    (int)$entry['class_id'],
                    "Class cancelled: {$entry['subject_name']} ({$entry['class_name']}) on {$when}. {$reason}",
                    [
                        'timetable_id' => $id,
                        'date' => (string)$entry['date'],
                        'event_name' => 'timetable.cancelled',
                        'actor_id' => (int)($_SESSION['user_id'] ?? 0),
                    ]
                );
            } catch (Throwable $e) {
                error_log('class cancel communication hub: ' . $e->getMessage());
            }
            $success = 'Class cancelled. Students notified on WhatsApp and in the portal bell.';
        } elseif ($action === 'substitute') {
            $subId = (int)($_POST['substitute_teacher_id'] ?? 0);
            $subName = '';
            foreach ($teachers as $t) {
                if ((int)$t['id'] === $subId) {
                    $subName = (string)$t['name'];
                    break;
                }
            }
            if ($subName === '') {
                throw new RuntimeException('Choose a substitute teacher.');
            }
            $reason = trim((string)($_POST['reason'] ?? ''));
            $pdo->prepare("
                UPDATE timetable
                SET lesson_status = 'substituted', substitute_teacher_id = ?, cancel_reason = ?, cancelled_at = NULL
                WHERE id = ?
            ")->execute([$subId, $reason !== '' ? $reason : null, $id]);
            campus_notify_students(
                $pdo,
                $students,
                "✅ *Substitute teacher*\n\n*{$entry['subject_name']}* ({$entry['class_name']})\n{$when}\nRoom: {$entry['room_name']}\nTeacher: *{$subName}* (covering for {$entry['teacher_name']})",
                'CLASS_SUBSTITUTE'
            );
            try {
                (new \Edexcel\Services\CommunicationEventService($pdo))->timetableChanged(
                    (int)$entry['class_id'],
                    "Substitute teacher: {$subName} for {$entry['subject_name']} ({$entry['class_name']}) on {$when}.",
                    [
                        'timetable_id' => $id,
                        'date' => (string)$entry['date'],
                        'event_name' => 'timetable.substituted',
                        'actor_id' => (int)($_SESSION['user_id'] ?? 0),
                    ]
                );
            } catch (Throwable $e) {
                error_log('class substitute communication hub: ' . $e->getMessage());
            }
            $success = 'Substitute saved. Students notified on WhatsApp and in the portal bell.';
        } elseif ($action === 'restore') {
            $pdo->prepare("
                UPDATE timetable
                SET lesson_status = 'scheduled', cancel_reason = NULL, substitute_teacher_id = NULL, cancelled_at = NULL
                WHERE id = ?
            ")->execute([$id]);
            $success = 'Lesson restored to the normal timetable.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$from = date('Y-m-d');
$to = date('Y-m-d', strtotime('+14 days'));
$sql = "
    SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name, r.name AS room_name,
           st.name AS substitute_name
    FROM timetable tt
    JOIN subjects s ON s.id = tt.subject_id
    JOIN student_classes c ON c.id = tt.class_id
    JOIN teachers t ON t.id = tt.teacher_id
    LEFT JOIN rooms r ON r.id = tt.room_id
    LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id
    WHERE tt.deleted_at IS NULL
      AND tt.date BETWEEN ? AND ?
";
$params = [$from, $to];
if (!$isAdmin && $teacherId > 0) {
    $sql .= " AND tt.teacher_id = ?";
    $params[] = $teacherId;
}
$sql .= " ORDER BY tt.date, tt.start_time";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-1"><i class="bi bi-exclamation-octagon text-warning"></i> Cancel / substitute</h1>
    <p class="text-muted">One action updates the timetable, student portal bell, and WhatsApp. From Today you can also use <strong>Lesson ops</strong>.</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>When</th><th>Class</th><th>Teacher</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lessons as $row): ?>
                    <tr>
                        <td>
                            <?= e(date('D d M', strtotime($row['date']))) ?><br>
                            <small><?= e(date('h:i A', strtotime($row['start_time']))) ?></small>
                        </td>
                        <td>
                            <strong><?= e($row['subject_name']) ?></strong><br>
                            <small><?= e($row['class_name']) ?> · <?= e($row['room_name']) ?></small>
                        </td>
                        <td>
                            <?= e($row['teacher_name']) ?>
                            <?php if (!empty($row['substitute_name'])): ?>
                                <div class="small text-success">Sub: <?= e($row['substitute_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $st = $row['lesson_status'] ?? 'scheduled'; ?>
                            <span class="badge <?= $st === 'cancelled' ? 'bg-danger' : ($st === 'substituted' ? 'bg-info text-dark' : 'bg-secondary') ?>">
                                <?= e($st) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="timetable_id" value="<?= (int)$row['id'] ?>">
                                <input class="form-control form-control-sm" name="reason" placeholder="Reason" style="min-width:140px">
                                <select class="form-select form-select-sm" name="substitute_teacher_id" style="min-width:140px">
                                    <option value="">Substitute…</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="cancel">Cancel</button>
                                <button class="btn btn-sm btn-outline-primary" name="action" value="substitute">Substitute</button>
                                <?php if (($row['lesson_status'] ?? '') !== 'scheduled'): ?>
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="restore">Restore</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
