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
$editing = null;

$rooms = $pdo->query("SELECT id, name FROM rooms WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($isAdmin) {
    $classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} elseif ($teacherId > 0) {
    $classStmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name
        FROM student_classes c
        WHERE c.deleted_at IS NULL
          AND (
            EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.class_id = c.id
                  AND tt.teacher_id = ?
                  AND tt.deleted_at IS NULL
            )
            OR EXISTS (
                SELECT 1 FROM subject_classes sc
                JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
                WHERE sc.class_id = c.id AND ts.teacher_id = ?
            )
          )
        ORDER BY c.name
    ");
    $classStmt->execute([$teacherId, $teacherId]);
    $classes = $classStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $subjectStmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.name
        FROM subjects s
        WHERE s.deleted_at IS NULL
          AND (
            EXISTS (
                SELECT 1 FROM teacher_subjects ts
                WHERE ts.subject_id = s.id AND ts.teacher_id = ?
            )
            OR EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.subject_id = s.id
                  AND tt.teacher_id = ?
                  AND tt.deleted_at IS NULL
            )
          )
        ORDER BY s.name
    ");
    $subjectStmt->execute([$teacherId, $teacherId]);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $teacherStmt = $pdo->prepare("SELECT id, name FROM teachers WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $teacherStmt->execute([$teacherId]);
    $teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $classes = [];
    $subjects = [];
    $teachers = [];
}

$allowedClassIds = array_map(static fn(array $row): int => (int)$row['id'], $classes);
$allowedSubjectIds = array_map(static fn(array $row): int => (int)$row['id'], $subjects);

function campus_exam_null_id(mixed $value): ?int
{
    $id = (int)$value;
    return $id > 0 ? $id : null;
}

function campus_exam_teacher_can_manage(array $exam, int $teacherId, array $allowedClassIds, array $allowedSubjectIds): bool
{
    if ($teacherId > 0 && (int)($exam['teacher_id'] ?? 0) === $teacherId) {
        return true;
    }
    $classId = (int)($exam['class_id'] ?? 0);
    if ($classId > 0 && in_array($classId, $allowedClassIds, true)) {
        return true;
    }
    $subjectId = (int)($exam['subject_id'] ?? 0);
    if ($subjectId > 0 && in_array($subjectId, $allowedSubjectIds, true)) {
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired. Please try again.');
        }

        if (isset($_POST['delete_exam'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) {
                throw new RuntimeException('Exam not found.');
            }
            $own = $pdo->prepare("SELECT teacher_id, class_id, subject_id FROM student_exams WHERE id = ? LIMIT 1");
            $own->execute([$id]);
            $examRow = $own->fetch(PDO::FETCH_ASSOC);
            if (!$examRow) {
                throw new RuntimeException('Exam not found.');
            }
            if (!$isAdmin && !campus_exam_teacher_can_manage($examRow, $teacherId, $allowedClassIds, $allowedSubjectIds)) {
                throw new RuntimeException('You can only remove exam slots for your own classes or subjects.');
            }
            $pdo->prepare("DELETE FROM student_exams WHERE id = ?")->execute([$id]);
            $success = 'Exam / mock slot removed.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $examType = strtolower(trim((string)($_POST['exam_type'] ?? 'exam')));
            $examDate = trim((string)($_POST['exam_date'] ?? ''));
            $examTime = trim((string)($_POST['exam_time'] ?? ''));
            $endTime = trim((string)($_POST['end_time'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $classId = campus_exam_null_id($_POST['class_id'] ?? 0);
            $subjectId = campus_exam_null_id($_POST['subject_id'] ?? 0);
            $examTeacherId = campus_exam_null_id($_POST['teacher_id'] ?? 0);
            $roomId = campus_exam_null_id($_POST['room_id'] ?? 0);
            $notify = isset($_POST['notify_students']);

            if (!in_array($examType, ['exam', 'mock'], true)) {
                $examType = 'exam';
            }
            if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
                throw new RuntimeException('Title and date are required.');
            }
            if ($examTime !== '' && !preg_match('/^\d{2}:\d{2}/', $examTime)) {
                throw new RuntimeException('Start time is not valid.');
            }
            if ($endTime !== '' && !preg_match('/^\d{2}:\d{2}/', $endTime)) {
                throw new RuntimeException('End time is not valid.');
            }
            if ($examTime !== '' && $endTime !== '' && $endTime <= $examTime) {
                throw new RuntimeException('End time must be after start time.');
            }

            if (!$isAdmin) {
                if ($classId === null || !in_array($classId, $allowedClassIds, true)) {
                    throw new RuntimeException('Choose one of your classes.');
                }
                if ($subjectId !== null && !in_array($subjectId, $allowedSubjectIds, true)) {
                    throw new RuntimeException('Choose one of your subjects.');
                }
                $examTeacherId = $teacherId > 0 ? $teacherId : $examTeacherId;
            }

            if ($examTeacherId === null && $teacherId > 0) {
                $examTeacherId = $teacherId;
            }

            if ($id > 0 && !$isAdmin) {
                $own = $pdo->prepare("SELECT teacher_id, class_id, subject_id FROM student_exams WHERE id = ? LIMIT 1");
                $own->execute([$id]);
                $examRow = $own->fetch(PDO::FETCH_ASSOC);
                if (!$examRow || !campus_exam_teacher_can_manage($examRow, $teacherId, $allowedClassIds, $allowedSubjectIds)) {
                    throw new RuntimeException('You can only edit exam slots for your own classes or subjects.');
                }
            }

            $params = [
                $title,
                $classId,
                $subjectId,
                $examDate,
                $examTime !== '' ? $examTime : null,
                $endTime !== '' ? $endTime : null,
                $examType,
                $examTeacherId,
                $roomId,
                $location !== '' ? $location : null,
                $notes !== '' ? $notes : null,
            ];

            if ($id > 0) {
                $pdo->prepare("
                    UPDATE student_exams
                    SET title = ?, class_id = ?, subject_id = ?, exam_date = ?, exam_time = ?,
                        end_time = ?, exam_type = ?, teacher_id = ?, room_id = ?, location = ?, notes = ?
                    WHERE id = ?
                ")->execute([...$params, $id]);
                $success = 'Exam / mock timetable updated.';
            } else {
                $pdo->prepare("
                    INSERT INTO student_exams
                        (title, class_id, subject_id, exam_date, exam_time, end_time, exam_type, teacher_id, room_id, location, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute($params);
                $id = (int)$pdo->lastInsertId();
                $success = 'Exam / mock slot added to the timetable.';
            }

            if ($notify) {
                $typeLabel = campus_exam_type_label($examType);
                $className = 'All classes';
                foreach ($classes as $c) {
                    if ($classId !== null && (int)$c['id'] === $classId) {
                        $className = (string)$c['name'];
                        break;
                    }
                }
                $when = campus_exam_when([
                    'exam_date' => $examDate,
                    'exam_time' => $examTime,
                    'end_time' => $endTime,
                ]);
                $venue = $location;
                if ($roomId) {
                    foreach ($rooms as $r) {
                        if ((int)$r['id'] === $roomId) {
                            $venue = (string)$r['name'];
                            break;
                        }
                    }
                }
                $wa = "📝 *{$typeLabel} timetable*\n\n*{$title}*\n{$when}\nClass: {$className}"
                    . ($venue !== '' ? "\nRoom: {$venue}" : '')
                    . "\n\nOpen Student Portal → Exams for the full mock / exam timetable.";
                $studentIds = $classId ? campus_class_student_ids($pdo, $classId) : campus_all_enrolled_student_ids($pdo);
                campus_notify_students($pdo, $studentIds, $wa, 'EXAM');
                campus_portal_notify(
                    $pdo,
                    $studentIds,
                    $typeLabel . ' scheduled: ' . $title,
                    $when . ($venue !== '' ? ' · ' . $venue : ''),
                    'dashboard.php?tab=exams'
                );
                $success .= ' Students notified.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM student_exams WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (
        $editing
        && !$isAdmin
        && !campus_exam_teacher_can_manage($editing, $teacherId, $allowedClassIds, $allowedSubjectIds)
    ) {
        $editing = null;
        $error = 'You can only edit exam slots for your own classes or subjects.';
    }
}

$filterType = strtolower(trim((string)($_GET['type'] ?? 'all')));
if (!in_array($filterType, ['all', 'exam', 'mock'], true)) {
    $filterType = 'all';
}
$filterRange = strtolower(trim((string)($_GET['range'] ?? 'upcoming')));
if (!in_array($filterRange, ['upcoming', 'past', 'all'], true)) {
    $filterRange = 'upcoming';
}

$listSql = "
    SELECT
        e.*,
        s.name AS subject_name,
        c.name AS class_name,
        t.name AS teacher_name,
        r.name AS room_name
    FROM student_exams e
    LEFT JOIN subjects s ON s.id = e.subject_id
    LEFT JOIN student_classes c ON c.id = e.class_id
    LEFT JOIN teachers t ON t.id = e.teacher_id
    LEFT JOIN rooms r ON r.id = e.room_id
    WHERE 1=1
";
$listParams = [];
$scopeSql = '';
$scopeParams = [];
if (!$isAdmin) {
    if ($teacherId < 1) {
        $scopeSql = " AND 1=0";
    } else {
        $ors = ['e.teacher_id = ?'];
        $scopeParams[] = $teacherId;
        if ($allowedClassIds) {
            $ors[] = 'e.class_id IN (' . implode(',', array_fill(0, count($allowedClassIds), '?')) . ')';
            array_push($scopeParams, ...$allowedClassIds);
        }
        if ($allowedSubjectIds) {
            $ors[] = 'e.subject_id IN (' . implode(',', array_fill(0, count($allowedSubjectIds), '?')) . ')';
            array_push($scopeParams, ...$allowedSubjectIds);
        }
        $scopeSql = ' AND (' . implode(' OR ', $ors) . ')';
    }
}
$listSql .= $scopeSql;
$listParams = $scopeParams;
if ($filterType !== 'all') {
    $listSql .= " AND e.exam_type = ?";
    $listParams[] = $filterType;
}
if ($filterRange === 'upcoming') {
    $listSql .= " AND e.exam_date >= CURDATE()";
} elseif ($filterRange === 'past') {
    $listSql .= " AND e.exam_date < CURDATE()";
}
$listSql .= " ORDER BY e.exam_date ASC, e.exam_time IS NULL, e.exam_time ASC LIMIT 200";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($listParams);
$list = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$countSql = "SELECT COUNT(*) FROM student_exams e WHERE e.exam_date >= CURDATE()" . $scopeSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($scopeParams);
$upcomingCount = (int)$countStmt->fetchColumn();

$mockSql = "SELECT COUNT(*) FROM student_exams e WHERE e.exam_type = 'mock' AND e.exam_date >= CURDATE()" . $scopeSql;
$mockStmt = $pdo->prepare($mockSql);
$mockStmt->execute($scopeParams);
$mockCount = (int)$mockStmt->fetchColumn();

$examSql = "SELECT COUNT(*) FROM student_exams e WHERE e.exam_type = 'exam' AND e.exam_date >= CURDATE()" . $scopeSql;
$examStmt = $pdo->prepare($examSql);
$examStmt->execute($scopeParams);
$examCount = (int)$examStmt->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-journal-text text-danger"></i> Exam / mock timetable</h1>
            <p class="text-muted mb-0">Publish exam and mock paper slots. Enrolled students see them in the portal and can ask WhatsApp for the schedule.</p>
        </div>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= e(BASE_URL) ?>campus/today.php">Today’s classes</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Upcoming slots</div><div class="fs-3 fw-bold"><?= $upcomingCount ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Upcoming exams</div><div class="fs-3 fw-bold"><?= $examCount ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Upcoming mocks</div><div class="fs-3 fw-bold"><?= $mockCount ?></div></div></div></div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?= $editing ? 'Edit slot' : 'Add exam or mock' ?></h5>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
                        <label class="form-label">Title</label>
                        <input class="form-control mb-3" name="title" required maxlength="200" placeholder="Unit 4 mock / May 2026 paper" value="<?= e((string)($editing['title'] ?? '')) ?>">

                        <label class="form-label">Type</label>
                        <select class="form-select mb-3" name="exam_type">
                            <?php $curType = (string)($editing['exam_type'] ?? 'exam'); ?>
                            <option value="exam" <?= $curType !== 'mock' ? 'selected' : '' ?>>Exam</option>
                            <option value="mock" <?= $curType === 'mock' ? 'selected' : '' ?>>Mock</option>
                        </select>

                        <label class="form-label">Class</label>
                        <select class="form-select mb-3" name="class_id" <?= $isAdmin ? '' : 'required' ?>>
                            <?php if ($isAdmin): ?>
                                <option value="">All classes</option>
                            <?php else: ?>
                                <option value="">Choose your class</option>
                            <?php endif; ?>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)($editing['class_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$isAdmin && !$classes): ?>
                            <div class="small text-muted mb-3">No classes are assigned to you yet.</div>
                        <?php endif; ?>

                        <label class="form-label">Subject</label>
                        <select class="form-select mb-3" name="subject_id">
                            <option value="">Optional</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= (int)($editing['subject_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input class="form-control mb-3" type="date" name="exam_date" required value="<?= e((string)($editing['exam_date'] ?? '')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start</label>
                                <input class="form-control mb-3" type="time" name="exam_time" value="<?= e(substr((string)($editing['exam_time'] ?? ''), 0, 5)) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End</label>
                                <input class="form-control mb-3" type="time" name="end_time" value="<?= e(substr((string)($editing['end_time'] ?? ''), 0, 5)) ?>">
                            </div>
                        </div>

                        <label class="form-label">Invigilator / teacher</label>
                        <?php if ($isAdmin): ?>
                        <select class="form-select mb-3" name="teacher_id">
                            <option value="">Optional</option>
                            <?php
                            $selectedTeacher = (int)($editing['teacher_id'] ?? 0);
                            foreach ($teachers as $t):
                            ?>
                                <option value="<?= (int)$t['id'] ?>" <?= $selectedTeacher === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="teacher_id" value="<?= (int)$teacherId ?>">
                        <input class="form-control mb-3" type="text" value="<?= e((string)($teachers[0]['name'] ?? 'You')) ?>" disabled>
                        <?php endif; ?>

                        <label class="form-label">Room</label>
                        <select class="form-select mb-3" name="room_id">
                            <option value="">Optional</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" <?= (int)($editing['room_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label">Location note</label>
                        <input class="form-control mb-3" name="location" maxlength="200" placeholder="Hall A / Lab 2" value="<?= e((string)($editing['location'] ?? '')) ?>">

                        <label class="form-label">Notes</label>
                        <textarea class="form-control mb-3" name="notes" rows="2" placeholder="Bring calculator, ID card…"><?= e((string)($editing['notes'] ?? '')) ?></textarea>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="notify_students" id="notify_students" <?= $editing ? '' : 'checked' ?>>
                            <label class="form-check-label" for="notify_students">Notify students on WhatsApp and in the portal</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary rounded-pill"><?= $editing ? 'Save changes' : 'Publish slot' ?></button>
                            <?php if ($editing): ?>
                                <a class="btn btn-outline-secondary rounded-pill" href="<?= e(BASE_URL) ?>campus/exams.php">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <form class="row g-2 mb-3" method="get">
                        <div class="col-sm-4">
                            <select class="form-select" name="type" onchange="this.form.submit()">
                                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All types</option>
                                <option value="exam" <?= $filterType === 'exam' ? 'selected' : '' ?>>Exams</option>
                                <option value="mock" <?= $filterType === 'mock' ? 'selected' : '' ?>>Mocks</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select class="form-select" name="range" onchange="this.form.submit()">
                                <option value="upcoming" <?= $filterRange === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="past" <?= $filterRange === 'past' ? 'selected' : '' ?>>Past</option>
                                <option value="all" <?= $filterRange === 'all' ? 'selected' : '' ?>>All dates</option>
                            </select>
                        </div>
                    </form>
                    <div class="list-group list-group-flush">
                        <?php foreach ($list as $row):
                            $isMock = strtolower((string)($row['exam_type'] ?? '')) === 'mock';
                            $venue = campus_exam_venue($row);
                        ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <span class="badge <?= $isMock ? 'bg-violet bg-secondary' : 'bg-danger' ?>"><?= e(campus_exam_type_label((string)$row['exam_type'])) ?></span>
                                        <strong class="ms-1"><?= e($row['title']) ?></strong>
                                        <div class="small text-muted mt-1">
                                            <?= e(campus_exam_when($row)) ?>
                                            · <?= e($row['class_name'] ?: 'All classes') ?>
                                            <?php if (!empty($row['subject_name'])): ?> · <?= e($row['subject_name']) ?><?php endif; ?>
                                            <?php if ($venue !== ''): ?> · <?= e($venue) ?><?php endif; ?>
                                            <?php if (!empty($row['teacher_name'])): ?> · <?= e($row['teacher_name']) ?><?php endif; ?>
                                        </div>
                                        <?php if (!empty($row['notes'])): ?>
                                            <div class="small"><?= e($row['notes']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-1 align-items-start">
                                        <a class="btn btn-sm btn-outline-success" href="<?= e(BASE_URL) ?>campus/progress.php?exam_id=<?= (int)$row['id'] ?><?= !empty($row['class_id']) ? '&class_id=' . (int)$row['class_id'] : '' ?>">Marks</a>
                                        <a class="btn btn-sm btn-outline-primary" href="?id=<?= (int)$row['id'] ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Remove this exam / mock slot?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" name="delete_exam" value="1">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$list): ?>
                            <p class="text-muted mb-0">No exam or mock slots in this filter. Add one on the left.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
