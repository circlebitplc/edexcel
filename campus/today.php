<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/online_lesson.php';

require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$filterTeacher = $teacherId;
if ($isAdmin && isset($_GET['teacher_id'])) {
    $filterTeacher = (int)$_GET['teacher_id'];
}
if (!$isAdmin && $teacherId <= 0) {
    http_response_code(403);
    exit('Your teacher account is not linked to an active teacher profile. Ask an administrator to link users.teacher_id.');
}
if (!$isAdmin) {
    $profile = $pdo->prepare('SELECT 1 FROM teachers WHERE id = ? AND deleted_at IS NULL');
    $profile->execute([$teacherId]);
    if (!$profile->fetchColumn()) {
        http_response_code(403);
        exit('Your linked teacher profile is inactive. Ask an administrator to review users.teacher_id.');
    }
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$teachers = [];
if ($isAdmin) {
    $teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "
    SELECT
        tt.id,
        tt.date,
        tt.start_time,
        tt.end_time,
        tt.teacher_id,
        tt.class_id,
        tt.student_count,
        tt.payment_status,
        tt.lesson_status,
        tt.cancel_reason,
        tt.delivery_mode,
        om.status AS meeting_status,
        om.public_id,
        COALESCE(tt.unregistered_present, 0) AS unregistered_present,
        s.name AS subject_name,
        c.name AS class_name,
        r.name AS room_name,
        t.name AS teacher_name,
        st.name AS substitute_name,
        (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = tt.class_id) AS enrolled,
        (SELECT COUNT(*) FROM student_attendance sa WHERE sa.timetable_id = tt.id) AS marked,
        (SELECT COUNT(*) FROM student_attendance sa WHERE sa.timetable_id = tt.id AND sa.status = 'absent') AS absents,
        (SELECT COUNT(*) FROM student_attendance sa WHERE sa.timetable_id = tt.id AND sa.status IN ('present','late')) AS present_marked
    FROM timetable tt
    LEFT JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
    LEFT JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
    LEFT JOIN rooms r ON r.id = tt.room_id AND r.deleted_at IS NULL
    LEFT JOIN teachers t ON t.id = tt.teacher_id AND t.deleted_at IS NULL
    LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id AND st.deleted_at IS NULL
    LEFT JOIN online_meetings om ON om.timetable_id = tt.id
    WHERE tt.deleted_at IS NULL
      AND tt.date = ?
";
$params = [$date];
if ($filterTeacher > 0) {
    $sql .= " AND (tt.teacher_id = ? OR tt.substitute_teacher_id = ?)";
    $params[] = $filterTeacher;
    $params[] = $filterTeacher;
}
$sql .= " ORDER BY tt.start_time, t.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
$videoLessons = [];
if ($lessons !== []) {
    try {
        $ids = array_map(static fn (array $row): int => (int)$row['id'], $lessons);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $olStmt = $pdo->prepare("
            SELECT ol.timetable_id, ol.published,
                   (SELECT COUNT(*) FROM online_lesson_items i WHERE i.lesson_id = ol.id) AS item_count
            FROM online_lessons ol
            WHERE ol.timetable_id IN ($placeholders)
        ");
        $olStmt->execute($ids);
        foreach ($olStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $videoLessons[(int)$row['timetable_id']] = [
                'published' => (int)$row['published'] === 1,
                'item_count' => (int)$row['item_count'],
            ];
        }
    } catch (Throwable $e) {
        $videoLessons = [];
    }
}

$now = date('H:i:s');
$nextId = 0;
foreach ($lessons as $lesson) {
    $status = $lesson['lesson_status'] ?? 'scheduled';
    if ($status === 'cancelled') {
        continue;
    }
    if ($lesson['date'] > date('Y-m-d') || $lesson['end_time'] >= $now) {
        $nextId = (int)$lesson['id'];
        break;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-sun text-warning"></i> Today’s classes</h1>
            <p class="text-muted mb-0">Rooms, headcount, attendance, and payment status — no WhatsApp required.</p>
            <p class="mb-0 mt-2"><a href="<?= e(BASE_URL) ?>campus/learning_modules.php">Learning modules</a> · <a href="<?= e(BASE_URL) ?>campus/marking_queue.php">Marking queue</a></p>
        </div>
        <form class="d-flex flex-wrap gap-2" method="get">
            <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
            <?php if ($isAdmin): ?>
                <select name="teacher_id" class="form-select">
                    <option value="0">All teachers</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $filterTeacher === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-primary">Show</button>
        </form>
    </div>

    <?php if (!$lessons): ?>
        <div class="alert alert-light border">No classes on <?= e(date('D d M Y', strtotime($date))) ?>.</div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($lessons as $lesson):
            $status = $lesson['lesson_status'] ?? 'scheduled';
            $isNext = (int)$lesson['id'] === $nextId;
            $duration = 0;
            if (function_exists('lesson_duration_minutes')) {
                $duration = lesson_duration_minutes((string)$lesson['start_time'], (string)$lesson['end_time']);
            }
            $rate = function_exists('lesson_rate_per_student') ? lesson_rate_per_student($duration) : 500;
            $unregistered = (int)($lesson['unregistered_present'] ?? 0);
            $presentMarked = (int)($lesson['present_marked'] ?? 0);
            $attended = $presentMarked + $unregistered;
            $headcount = max((int)$lesson['enrolled'], (int)$lesson['student_count'], $attended);
            $amount = $headcount * $rate;
        ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 <?= $isNext ? 'border border-primary' : '' ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="text-muted small"><?= e(date('h:i A', strtotime($lesson['start_time']))) ?> – <?= e(date('h:i A', strtotime($lesson['end_time']))) ?></div>
                                <h4 class="mb-0"><?= e($lesson['subject_name']) ?></h4>
                                <div class="text-muted"><?= e($lesson['class_name']) ?></div>
                            </div>
                            <div class="text-end">
                                <?php if ($isNext): ?><span class="badge bg-primary mb-1">Now / next</span><br><?php endif; ?>
                                <?php if ($status === 'cancelled'): ?>
                                    <span class="badge bg-danger">Cancelled</span>
                                <?php elseif ($status === 'substituted'): ?>
                                    <span class="badge bg-info text-dark">Substitute</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row g-2 small mb-3">
                            <div class="col-6"><i class="bi bi-door-open"></i> <?= e($lesson['room_name']) ?></div>
                            <div class="col-6"><i class="bi bi-person"></i> <?= e($lesson['substitute_name'] ?: $lesson['teacher_name']) ?></div>
                            <div class="col-6"><i class="bi bi-people"></i> <?= (int)$lesson['enrolled'] ?> enrolled</div>
                            <div class="col-6">
                                <i class="bi bi-clipboard-check"></i>
                                <?= (int)$lesson['marked'] ?> marked
                                <?php if ((int)$lesson['absents'] > 0): ?>
                                    · <?= (int)$lesson['absents'] ?> absent
                                <?php endif; ?>
                                <?php if ($unregistered > 0): ?>
                                    · <?= $unregistered ?> unregistered
                                <?php endif; ?>
                            </div>
                            <div class="col-6"><i class="bi bi-person-check"></i> <?= $attended ?> in class</div>
                            <div class="col-6">
                                Payment:
                                <span class="badge <?= ($lesson['payment_status'] ?? '') === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= e($lesson['payment_status'] ?? 'pending') ?>
                                </span>
                            </div>
                            <div class="col-6">Est. Rs <?= number_format((float)$amount) ?></div>
                        </div>
                        <?php if ($status === 'cancelled' && !empty($lesson['cancel_reason'])): ?>
                            <p class="small text-danger mb-3"><?= e($lesson['cancel_reason']) ?></p>
                        <?php endif; ?>
                        <?php
                            $videoMeta = $videoLessons[(int)$lesson['id']] ?? null;
                            $videoLabel = 'Not built';
                            if ($videoMeta) {
                                $videoLabel = ($videoMeta['published'] ? 'Published' : 'Draft') . ' · ' . (int)$videoMeta['item_count'] . ' items';
                            }
                        ?>
                        <p class="small text-muted mb-2">Learning module · <?= e($videoLabel) ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-primary" href="<?= e(BASE_URL) ?>campus/lesson_ops.php?lesson=<?= (int)$lesson['id'] ?>">
                                <i class="bi bi-lightning-charge"></i> Lesson ops
                            </a>
                            <?php if ($videoMeta): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(campus_online_lesson_url((int)$lesson['id'])) ?>">Learning module</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(campus_lesson_analytics_url((int)$lesson['id'])) ?>">Analytics</a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(campus_online_lesson_url((int)$lesson['id'])) ?>">+ Create learning module</a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>campus/attendance.php?date=<?= e($date) ?>&lesson=<?= (int)$lesson['id'] ?>">Attendance</a>
                            <?php
                            $lessonMode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
                            if (in_array($lessonMode, ['online', 'hybrid'], true)):
                            ?>
                                <a class="btn btn-sm btn-danger" href="<?= e(classroom_room_url((int)$lesson['id'], (string)($lesson['public_id'] ?? ''))) ?>">
                                    <?= ($lesson['meeting_status'] ?? '') === 'live' ? 'Open live class' : 'Start class' ?>
                                </a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(BASE_URL) ?>campus/lesson_fees.php?date=<?= e($date) ?>&lesson=<?= (int)$lesson['id'] ?>">Fees</a>
                            <a class="btn btn-sm btn-outline-warning" href="<?= e(BASE_URL) ?>campus/lesson_ops.php?lesson=<?= (int)$lesson['id'] ?>&panel=status">Cancel / sub</a>
                            <?php if ($isAdmin): ?>
                                <a class="btn btn-sm btn-outline-success" href="<?= e(BASE_URL) ?>timetable/payments.php">Payments</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
