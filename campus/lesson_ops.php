<?php
declare(strict_types=1);

/**
 * campus/lesson_ops.php
 * One-flow teacher ops for a single lesson: attendance, fees, homework,
 * recordings, cancel/substitute — without hopping across screens.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\HomeworkSubmissionService;

require_staff();
ensure_campus_schema($pdo);
ensure_ops_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$lessonId = (int)($_GET['lesson'] ?? $_POST['timetable_id'] ?? 0);
$panel = strtolower(trim((string)($_GET['panel'] ?? 'overview')));
if (!in_array($panel, ['overview', 'attendance', 'fees', 'homework', 'status'], true)) {
    $panel = 'overview';
}

$teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? '');
        $lessonId = (int)($_POST['timetable_id'] ?? $lessonId);

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
        $stmt->execute([$lessonId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            throw new RuntimeException('Lesson not found.');
        }
        if (!campus_staff_can_mark_lesson($entry, $isAdmin, $teacherId)) {
            throw new RuntimeException('You can only manage your own lessons.');
        }

        $when = date('D d M, h:i A', strtotime($entry['date'] . ' ' . $entry['start_time']));
        $students = campus_class_student_ids($pdo, (int)$entry['class_id']);

        if ($action === 'cancel') {
            $reason = trim((string)($_POST['reason'] ?? 'Class cancelled'));
            $pdo->prepare("
                UPDATE timetable
                SET lesson_status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), substitute_teacher_id = NULL
                WHERE id = ?
            ")->execute([$reason, $lessonId]);
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
                        'timetable_id' => $lessonId,
                        'date' => (string)$entry['date'],
                        'event_name' => 'timetable.cancelled',
                        'actor_id' => $userId,
                    ]
                );
            } catch (Throwable $e) {
                error_log('lesson_ops cancel hub: ' . $e->getMessage());
            }
            $success = 'Class cancelled. Students notified on WhatsApp and in the portal.';
            $panel = 'status';
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
            ")->execute([$subId, $reason !== '' ? $reason : null, $lessonId]);
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
                        'timetable_id' => $lessonId,
                        'date' => (string)$entry['date'],
                        'event_name' => 'timetable.substituted',
                        'actor_id' => $userId,
                    ]
                );
            } catch (Throwable $e) {
                error_log('lesson_ops substitute hub: ' . $e->getMessage());
            }
            $success = 'Substitute saved. Students notified on WhatsApp and in the portal.';
            $panel = 'status';
        } elseif ($action === 'restore') {
            $pdo->prepare("
                UPDATE timetable
                SET lesson_status = 'scheduled', cancel_reason = NULL, substitute_teacher_id = NULL, cancelled_at = NULL
                WHERE id = ?
            ")->execute([$lessonId]);
            $success = 'Lesson restored to the normal timetable.';
            $panel = 'status';
        } elseif ($action === 'quick_homework') {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $due = trim((string)($_POST['due_date'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Homework title is required.');
            }
            $pdo->prepare("
                INSERT INTO student_homework (class_id, subject_id, teacher_id, title, description, due_date, link)
                VALUES (?, ?, ?, ?, ?, ?, NULL)
            ")->execute([
                (int)$entry['class_id'],
                (int)$entry['subject_id'],
                $teacherId ?: null,
                $title,
                $description !== '' ? $description : null,
                $due !== '' ? $due : null,
            ]);
            $homeworkId = (int)$pdo->lastInsertId();
            $dueText = $due !== '' ? "\nDue: " . date('d M Y', strtotime($due)) : '';
            campus_notify_students(
                $pdo,
                $students,
                "📄 *New homework*\n\n*{$title}*\nClass: {$entry['class_name']}{$dueText}\n\nOpen Student Portal → Homework & notes to submit.",
                'HOMEWORK'
            );
            try {
                (new \Edexcel\Services\CommunicationEventService($pdo))->homeworkAssigned(
                    (int)$entry['class_id'],
                    $title,
                    [
                        'homework_id' => $homeworkId,
                        'actor_id' => $userId,
                        'class_name' => (string)$entry['class_name'],
                        'due' => $due,
                    ]
                );
            } catch (Throwable $e) {
                error_log('lesson_ops homework hub: ' . $e->getMessage());
            }
            $success = 'Homework posted and students notified.';
            $panel = 'homework';
        } elseif ($action === 'review_homework') {
            $svc = new HomeworkSubmissionService($pdo);
            $scoreRaw = trim((string)($_POST['score'] ?? ''));
            $maxRaw = trim((string)($_POST['max_score'] ?? ''));
            $svc->review(
                (int)($_POST['submission_id'] ?? 0),
                $userId,
                $teacherId,
                $isAdmin,
                (string)($_POST['review_status'] ?? 'done'),
                $scoreRaw !== '' ? (float)$scoreRaw : null,
                $maxRaw !== '' ? (float)$maxRaw : null,
                trim((string)($_POST['feedback'] ?? ''))
            );
            $success = 'Submission marked and student notified.';
            $panel = 'homework';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$lesson = null;
if ($lessonId > 0) {
    $stmt = $pdo->prepare("
        SELECT
            tt.*,
            s.name AS subject_name,
            c.name AS class_name,
            r.name AS room_name,
            t.name AS teacher_name,
            st.name AS substitute_name,
            om.status AS meeting_status,
            om.public_id,
            (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = tt.class_id) AS enrolled,
            (SELECT COUNT(*) FROM student_attendance sa WHERE sa.timetable_id = tt.id) AS marked,
            (SELECT COUNT(*) FROM student_attendance sa WHERE sa.timetable_id = tt.id AND sa.status IN ('present','late')) AS present_marked,
            (SELECT COUNT(*) FROM student_lesson_fees slf WHERE slf.timetable_id = tt.id AND slf.status IN ('paid','waived')) AS fees_paid
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        LEFT JOIN rooms r ON r.id = tt.room_id
        JOIN teachers t ON t.id = tt.teacher_id
        LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id
        LEFT JOIN online_meetings om ON om.timetable_id = tt.id
        WHERE tt.id = ? AND tt.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($lesson && !campus_staff_can_mark_lesson($lesson, $isAdmin, $teacherId)) {
        $error = $error !== '' ? $error : 'You can only open your own lessons.';
        $lesson = null;
    }
}

$submissions = [];
$classHomework = [];
if ($lesson) {
    try {
        $hwSvc = new HomeworkSubmissionService($pdo);
        $submissions = $hwSvc->pendingForTeacher($teacherId, $isAdmin, 20);
        $classId = (int)$lesson['class_id'];
        $hwStmt = $pdo->prepare("
            SELECT id, title, due_date, created_at
            FROM student_homework
            WHERE class_id = ?
            ORDER BY created_at DESC
            LIMIT 8
        ");
        $hwStmt->execute([$classId]);
        $classHomework = $hwStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Prefer submissions for this class
        $submissions = array_values(array_filter($submissions, static function ($row) use ($classId) {
            return (int)($row['class_id'] ?? 0) === $classId || empty($row['class_id']);
        }));
    } catch (Throwable $e) {
        $submissions = [];
    }
}

$date = $lesson ? (string)$lesson['date'] : date('Y-m-d');
$base = rtrim((string)BASE_URL, '/') . '/';

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="<?= e($base) ?>campus/today.php?date=<?= e($date) ?>">
                <i class="bi bi-arrow-left"></i> Today’s classes
            </a>
            <h1 class="h3 mb-1 mt-1"><i class="bi bi-lightning-charge text-warning"></i> Lesson ops</h1>
            <p class="text-muted mb-0">Attendance → fees → homework → cancel/sub in one place.</p>
        </div>
        <?php if ($lesson): ?>
            <div class="text-end">
                <div class="fw-bold"><?= e($lesson['subject_name']) ?></div>
                <div class="text-muted small">
                    <?= e($lesson['class_name']) ?> ·
                    <?= e(date('D d M', strtotime((string)$lesson['date']))) ?> ·
                    <?= e(date('h:i A', strtotime((string)$lesson['start_time']))) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$lesson): ?>
        <div class="alert alert-light border">Open a lesson from <a href="<?= e($base) ?>campus/today.php">Today’s classes</a>.</div>
    <?php else:
        $status = (string)($lesson['lesson_status'] ?? 'scheduled');
        $mode = function_exists('classroom_normalize_delivery_mode')
            ? classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'))
            : 'physical';
        $panels = [
            'overview' => 'Overview',
            'attendance' => 'Attendance',
            'fees' => 'Fees',
            'homework' => 'Homework',
            'status' => 'Cancel / sub',
        ];
    ?>
        <ul class="nav nav-pills flex-wrap gap-2 mb-4">
            <?php foreach ($panels as $key => $label): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $panel === $key ? 'active' : '' ?>"
                       href="?lesson=<?= $lessonId ?>&panel=<?= e($key) ?>"><?= e($label) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($panel === 'overview'): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="text-muted small">Enrolled</div>
                        <div class="fs-4 fw-bold"><?= (int)$lesson['enrolled'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="text-muted small">Attendance marked</div>
                        <div class="fs-4 fw-bold"><?= (int)$lesson['marked'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="text-muted small">Present / late</div>
                        <div class="fs-4 fw-bold"><?= (int)$lesson['present_marked'] ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="text-muted small">Fees paid / waived</div>
                        <div class="fs-4 fw-bold"><?= (int)$lesson['fees_paid'] ?></div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php if ($status === 'cancelled'): ?>
                            <span class="badge bg-danger">Cancelled</span>
                        <?php elseif ($status === 'substituted'): ?>
                            <span class="badge bg-info text-dark">Substitute: <?= e((string)$lesson['substitute_name']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Scheduled</span>
                        <?php endif; ?>
                        <span class="badge bg-light text-dark border"><?= e($mode) ?></span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-door-open"></i> <?= e((string)$lesson['room_name']) ?></span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="?lesson=<?= $lessonId ?>&panel=attendance">1. Mark attendance</a>
                        <a class="btn btn-outline-success" href="?lesson=<?= $lessonId ?>&panel=fees">2. Mark fees</a>
                        <a class="btn btn-outline-secondary" href="?lesson=<?= $lessonId ?>&panel=homework">3. Homework</a>
                        <a class="btn btn-outline-warning" href="?lesson=<?= $lessonId ?>&panel=status">4. Cancel / substitute</a>
                        <?php if (in_array($mode, ['online', 'hybrid'], true)): ?>
                            <a class="btn btn-danger" href="<?= e(classroom_room_url($lessonId, (string)($lesson['public_id'] ?? ''))) ?>">
                                <?= ($lesson['meeting_status'] ?? '') === 'live' ? 'Open live class' : 'Start class' ?>
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-outline-dark" href="<?= e($base) ?>campus/recordings.php?lesson=<?= $lessonId ?>">Recordings</a>
                    </div>
                </div>
            </div>
        <?php elseif ($panel === 'attendance'): ?>
            <div class="alert alert-light border">
                Full attendance roster opens in the dedicated screen (register walk-ins, SMS join links).
            </div>
            <a class="btn btn-primary btn-lg" href="<?= e($base) ?>campus/attendance.php?date=<?= e($date) ?>&lesson=<?= $lessonId ?>">
                Open attendance for this lesson
            </a>
            <a class="btn btn-outline-secondary btn-lg ms-2" href="?lesson=<?= $lessonId ?>&panel=fees">Next: fees →</a>
        <?php elseif ($panel === 'fees'): ?>
            <div class="alert alert-light border">
                Collect or mark class fees so students can join live and watch recordings.
            </div>
            <a class="btn btn-success btn-lg" href="<?= e($base) ?>campus/lesson_fees.php?date=<?= e($date) ?>&lesson=<?= $lessonId ?>">
                Open class fees for this lesson
            </a>
            <a class="btn btn-outline-secondary btn-lg ms-2" href="?lesson=<?= $lessonId ?>&panel=homework">Next: homework →</a>
        <?php elseif ($panel === 'homework'): ?>
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h5">Post homework for this class</h2>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="timetable_id" value="<?= $lessonId ?>">
                                <input type="hidden" name="action" value="quick_homework">
                                <label class="form-label">Title</label>
                                <input class="form-control mb-3" name="title" required placeholder="Unit 1 past paper">
                                <label class="form-label">Note</label>
                                <textarea class="form-control mb-3" name="description" rows="3"></textarea>
                                <label class="form-label">Due date</label>
                                <input class="form-control mb-3" type="date" name="due_date">
                                <button class="btn btn-primary">Post & notify</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($classHomework): ?>
                        <div class="card border-0 shadow-sm rounded-4 mt-3">
                            <div class="card-body">
                                <h3 class="h6">Recent for this class</h3>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($classHomework as $hw): ?>
                                        <li class="mb-2">
                                            <strong><?= e($hw['title']) ?></strong>
                                            <div class="small text-muted">
                                                <?php if (!empty($hw['due_date'])): ?>Due <?= e(date('d M', strtotime((string)$hw['due_date']))) ?> · <?php endif; ?>
                                                <?= e(date('d M H:i', strtotime((string)$hw['created_at']))) ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h5">Mark submissions</h2>
                            <?php if (!$submissions): ?>
                                <p class="text-muted mb-0">No pending submissions for your classes.</p>
                            <?php else: ?>
                                <?php foreach ($submissions as $sub): ?>
                                    <div class="border rounded-3 p-3 mb-3">
                                        <div class="fw-semibold"><?= e((string)$sub['student_name']) ?></div>
                                        <div class="small text-muted mb-2">
                                            <?= e((string)$sub['title']) ?>
                                            <?php if (!empty($sub['class_name'])): ?> · <?= e((string)$sub['class_name']) ?><?php endif; ?>
                                        </div>
                                        <?php if (!empty($sub['note'])): ?>
                                            <p class="small mb-2"><?= e((string)$sub['note']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($sub['file_url'])): ?>
                                            <a class="small" href="<?= e((string)$sub['file_url']) ?>" target="_blank" rel="noopener">Open file</a>
                                        <?php endif; ?>
                                        <form method="post" class="row g-2 mt-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="timetable_id" value="<?= $lessonId ?>">
                                            <input type="hidden" name="action" value="review_homework">
                                            <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">
                                            <div class="col-md-3">
                                                <select name="review_status" class="form-select form-select-sm">
                                                    <option value="done">Done</option>
                                                    <option value="returned">Return</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <input class="form-control form-control-sm" name="score" placeholder="Score">
                                            </div>
                                            <div class="col-md-2">
                                                <input class="form-control form-control-sm" name="max_score" placeholder="Max" value="100">
                                            </div>
                                            <div class="col-md-3">
                                                <input class="form-control form-control-sm" name="feedback" placeholder="Feedback">
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-sm btn-primary w-100">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= e($base) ?>campus/homework.php?view=submissions">All submissions</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: /* status */ ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h5 text-danger">Cancel class</h2>
                            <p class="text-muted small">Updates the timetable and notifies students on WhatsApp + portal bell.</p>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="timetable_id" value="<?= $lessonId ?>">
                                <input type="hidden" name="action" value="cancel">
                                <label class="form-label">Reason</label>
                                <textarea class="form-control mb-3" name="reason" rows="3" required>Class cancelled</textarea>
                                <button class="btn btn-danger" onclick="return confirm('Cancel this class and notify students?')">Cancel & notify</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h5">Substitute teacher</h2>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="timetable_id" value="<?= $lessonId ?>">
                                <input type="hidden" name="action" value="substitute">
                                <label class="form-label">Teacher</label>
                                <select class="form-select mb-3" name="substitute_teacher_id" required>
                                    <option value="">Choose…</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label">Note (optional)</label>
                                <input class="form-control mb-3" name="reason">
                                <button class="btn btn-warning">Save substitute & notify</button>
                            </form>
                            <?php if ($status !== 'scheduled'): ?>
                                <hr>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="timetable_id" value="<?= $lessonId ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button class="btn btn-outline-secondary">Restore to scheduled</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
