<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';

$classes = campus_staff_classes($pdo, $isAdmin, $teacherId);
$allowedClassIds = array_map(static fn(array $row): int => (int)$row['id'], $classes);

$classId = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);

if ($classId > 0 && !in_array($classId, $allowedClassIds, true)) {
    $classId = 0;
}

$examPrefill = null;
if ($examId > 0) {
    $examStmt = $pdo->prepare("SELECT * FROM student_exams WHERE id = ? LIMIT 1");
    $examStmt->execute([$examId]);
    $examPrefill = $examStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($examPrefill) {
        $examClassId = (int)($examPrefill['class_id'] ?? 0);
        if ($examClassId > 0 && in_array($examClassId, $allowedClassIds, true) && $classId < 1) {
            $classId = $examClassId;
        }
        if ($examClassId > 0 && !in_array($examClassId, $allowedClassIds, true) && !$isAdmin) {
            $examPrefill = null;
            $examId = 0;
            $error = 'You can only enter marks for exams in your classes.';
        }
    } else {
        $examId = 0;
    }
}

function campus_progress_find_existing(
    PDO $pdo,
    int $studentId,
    int $classId,
    ?int $subjectId,
    ?int $examId,
    string $metric,
    string $recordedAt
): ?int {
    $sql = "
        SELECT id FROM student_progress
        WHERE student_id = ?
          AND class_id <=> ?
          AND subject_id <=> ?
          AND metric = ?
          AND recorded_at = ?
    ";
    $params = [$studentId, $classId > 0 ? $classId : null, $subjectId, $metric, $recordedAt];
    if (campus_column_exists($pdo, 'student_progress', 'exam_id')) {
        $sql .= " AND exam_id <=> ?";
        $params[] = $examId;
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }

        if (isset($_POST['delete_mark'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) {
                throw new RuntimeException('Result not found.');
            }
            $rowStmt = $pdo->prepare("SELECT class_id FROM student_progress WHERE id = ? LIMIT 1");
            $rowStmt->execute([$id]);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Result not found.');
            }
            $rowClass = (int)($row['class_id'] ?? 0);
            if (!$isAdmin && ($rowClass < 1 || !in_array($rowClass, $allowedClassIds, true))) {
                throw new RuntimeException('You can only remove results for your classes.');
            }
            $pdo->prepare("DELETE FROM student_progress WHERE id = ?")->execute([$id]);
            $success = 'Result removed.';
            $editId = 0;
        } elseif (isset($_POST['save_one'])) {
            $id = (int)($_POST['id'] ?? 0);
            $score = trim((string)($_POST['score'] ?? ''));
            $maxScore = trim((string)($_POST['max_score'] ?? '100'));
            $note = trim((string)($_POST['note'] ?? ''));
            $metric = trim((string)($_POST['metric'] ?? ''));
            $recordedAt = trim((string)($_POST['recorded_at'] ?? ''));
            if ($id < 1) {
                throw new RuntimeException('Result not found.');
            }
            if ($metric === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordedAt)) {
                throw new RuntimeException('Assessment name and date are required.');
            }
            if ($score === '' || !is_numeric($score) || !is_numeric($maxScore) || (float)$maxScore <= 0) {
                throw new RuntimeException('Enter a valid score and maximum.');
            }
            if ((float)$score < 0 || (float)$score > (float)$maxScore) {
                throw new RuntimeException('Score must be between 0 and the maximum.');
            }
            $own = $pdo->prepare("SELECT class_id FROM student_progress WHERE id = ? LIMIT 1");
            $own->execute([$id]);
            $row = $own->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Result not found.');
            }
            $rowClass = (int)($row['class_id'] ?? 0);
            if (!$isAdmin && ($rowClass < 1 || !in_array($rowClass, $allowedClassIds, true))) {
                throw new RuntimeException('You can only edit results for your classes.');
            }
            $pdo->prepare("
                UPDATE student_progress
                SET metric = ?, score = ?, max_score = ?, recorded_at = ?, note = ?
                WHERE id = ?
            ")->execute([
                substr($metric, 0, 100),
                round((float)$score, 2),
                round((float)$maxScore, 2),
                $recordedAt,
                $note !== '' ? substr($note, 0, 500) : null,
                $id,
            ]);
            $success = 'Result updated.';
            $editId = 0;
        } else {
            $classId = (int)($_POST['class_id'] ?? 0);
            if ($classId < 1 || !in_array($classId, $allowedClassIds, true)) {
                throw new RuntimeException('Choose one of your classes.');
            }
            $metric = trim((string)($_POST['metric'] ?? ''));
            $recordedAt = trim((string)($_POST['recorded_at'] ?? ''));
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $examId = (int)($_POST['exam_id'] ?? 0);
            $maxScore = trim((string)($_POST['max_score'] ?? '100'));
            $notify = isset($_POST['notify_students']);
            $publish = isset($_POST['publish_results']);
            $scores = $_POST['score'] ?? [];
            $notes = $_POST['note'] ?? [];

            if ($metric === '') {
                throw new RuntimeException('Name this assessment (for example Unit 4 mock).');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordedAt)) {
                throw new RuntimeException('Date is required.');
            }
            if (!is_numeric($maxScore) || (float)$maxScore <= 0) {
                throw new RuntimeException('Maximum mark must be greater than 0.');
            }
            if (!is_array($scores)) {
                throw new RuntimeException('No student scores submitted.');
            }

            $subjectId = $subjectId > 0 ? $subjectId : null;
            $examId = $examId > 0 ? $examId : null;
            $maxVal = round((float)$maxScore, 2);
            $metric = substr($metric, 0, 100);

            $enrolled = campus_class_student_ids($pdo, $classId);
            $saved = 0;
            $notifiedIds = [];
            $className = '';
            foreach ($classes as $c) {
                if ((int)$c['id'] === $classId) {
                    $className = (string)$c['name'];
                    break;
                }
            }

            $insert = $pdo->prepare("
                INSERT INTO student_progress
                    (student_id, class_id, subject_id, exam_id, metric, score, max_score, recorded_at, note, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $update = $pdo->prepare("
                UPDATE student_progress
                SET score = ?, max_score = ?, note = ?, exam_id = ?, created_by = ?
                WHERE id = ?
            ");

            $hasExamId = campus_column_exists($pdo, 'student_progress', 'exam_id');
            $hasCreatedBy = campus_column_exists($pdo, 'student_progress', 'created_by');
            $hasPublished = campus_column_exists($pdo, 'student_progress', 'published');
            $publishedVal = $publish ? 1 : 0;

            foreach ($scores as $studentId => $rawScore) {
                $studentId = (int)$studentId;
                if ($studentId < 1 || !in_array($studentId, $enrolled, true)) {
                    continue;
                }
                $rawScore = trim((string)$rawScore);
                if ($rawScore === '') {
                    continue;
                }
                if (!is_numeric($rawScore)) {
                    throw new RuntimeException('One of the scores is not a number.');
                }
                $scoreVal = round((float)$rawScore, 2);
                if ($scoreVal < 0 || $scoreVal > $maxVal) {
                    throw new RuntimeException('Scores must be between 0 and ' . $maxVal . '.');
                }
                $note = is_array($notes) ? trim((string)($notes[$studentId] ?? '')) : '';
                $note = $note !== '' ? substr($note, 0, 500) : null;
                $existing = campus_progress_find_existing(
                    $pdo,
                    $studentId,
                    $classId,
                    $subjectId,
                    $hasExamId ? $examId : null,
                    $metric,
                    $recordedAt
                );
                if ($existing) {
                    if ($hasExamId && $hasCreatedBy && $hasPublished) {
                        $pdo->prepare("
                            UPDATE student_progress
                            SET score = ?, max_score = ?, note = ?, exam_id = ?, created_by = ?, published = ?
                            WHERE id = ?
                        ")->execute([$scoreVal, $maxVal, $note, $examId, $userId, $publishedVal, $existing]);
                    } elseif ($hasExamId && $hasCreatedBy) {
                        $update->execute([$scoreVal, $maxVal, $note, $examId, $userId, $existing]);
                    } else {
                        $pdo->prepare("UPDATE student_progress SET score = ?, max_score = ?, note = ? WHERE id = ?")
                            ->execute([$scoreVal, $maxVal, $note, $existing]);
                    }
                } else {
                    if ($hasExamId && $hasCreatedBy && $hasPublished) {
                        $pdo->prepare("
                            INSERT INTO student_progress
                                (student_id, class_id, subject_id, exam_id, metric, score, max_score, recorded_at, note, created_by, published)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ")->execute([
                            $studentId, $classId, $subjectId, $examId, $metric,
                            $scoreVal, $maxVal, $recordedAt, $note, $userId, $publishedVal,
                        ]);
                    } elseif ($hasExamId && $hasCreatedBy) {
                        $insert->execute([
                            $studentId, $classId, $subjectId, $examId, $metric,
                            $scoreVal, $maxVal, $recordedAt, $note, $userId,
                        ]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO student_progress
                                (student_id, class_id, subject_id, metric, score, max_score, recorded_at, note)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ")->execute([
                            $studentId, $classId, $subjectId, $metric, $scoreVal, $maxVal, $recordedAt, $note,
                        ]);
                    }
                }
                $saved++;
                $notifiedIds[] = $studentId;
            }

            if ($saved < 1) {
                throw new RuntimeException('Enter at least one score. Leave a row blank to skip that student.');
            }

            $success = $saved . ' result' . ($saved === 1 ? '' : 's') . ($publish
                ? ' published. Students can see them under Homework & notes → My Progress.'
                : ' saved as a draft. Tick Publish when the class should see them.');

            if ($notify && $publish && $notifiedIds) {
                $subjectName = '';
                if ($subjectId) {
                    $sn = $pdo->prepare("SELECT name FROM subjects WHERE id = ? LIMIT 1");
                    $sn->execute([$subjectId]);
                    $subjectName = (string)$sn->fetchColumn();
                }
                foreach ($notifiedIds as $sid) {
                    $contacts = campus_student_contacts($pdo, $sid);
                    $raw = $scores[$sid] ?? $scores[(string)$sid] ?? '';
                    $shown = is_numeric((string)$raw) ? round((float)$raw, 2) : '';
                    $wa = "📈 *Result recorded*\n\n"
                        . ($contacts['name'] !== '' ? $contacts['name'] . "\n" : '')
                        . '*' . $metric . "*\n"
                        . $shown . ' / ' . $maxVal
                        . ($subjectName !== '' ? "\n" . $subjectName : '')
                        . ($className !== '' ? "\n" . $className : '')
                        . "\n\nOpen Student Portal → Homework & notes to see all results.";
                    campus_notify_phones($pdo, $sid, $wa, 'PROGRESS');
                }
                campus_portal_notify(
                    $pdo,
                    $notifiedIds,
                    'Result: ' . $metric,
                    $metric . ' · out of ' . $maxVal,
                    'dashboard.php?tab=services'
                );
                $success .= ' Students notified.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$subjects = [];
$roster = [];
$existingByStudent = [];
$className = '';
$defaultMetric = (string)($_POST['metric'] ?? '');
$defaultDate = (string)($_POST['recorded_at'] ?? date('Y-m-d'));
$defaultMax = (string)($_POST['max_score'] ?? '100');
$defaultSubject = (int)($_POST['subject_id'] ?? 0);

if ($examPrefill && $defaultMetric === '') {
    $defaultMetric = (string)$examPrefill['title'];
}
if ($examPrefill && empty($_POST['recorded_at']) && !empty($examPrefill['exam_date'])) {
    $defaultDate = (string)$examPrefill['exam_date'];
}
if ($examPrefill && $defaultSubject < 1) {
    $defaultSubject = (int)($examPrefill['subject_id'] ?? 0);
}

if ($classId > 0) {
    foreach ($classes as $c) {
        if ((int)$c['id'] === $classId) {
            $className = (string)$c['name'];
            break;
        }
    }

    $subjectSql = "
        SELECT DISTINCT s.id, s.name
        FROM subjects s
        WHERE s.deleted_at IS NULL
          AND (
            EXISTS (SELECT 1 FROM subject_classes sc WHERE sc.subject_id = s.id AND sc.class_id = ?)
            OR EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.subject_id = s.id AND tt.class_id = ? AND tt.deleted_at IS NULL
            )
          )
        ORDER BY s.name
    ";
    $subjectStmt = $pdo->prepare($subjectSql);
    $subjectStmt->execute([$classId, $classId]);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$subjects && $teacherId > 0 && !$isAdmin) {
        $subjectStmt = $pdo->prepare("
            SELECT DISTINCT s.id, s.name
            FROM subjects s
            JOIN teacher_subjects ts ON ts.subject_id = s.id
            WHERE s.deleted_at IS NULL AND ts.teacher_id = ?
            ORDER BY s.name
        ");
        $subjectStmt->execute([$teacherId]);
        $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    if (!$subjects && $isAdmin) {
        $subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $rosterStmt = $pdo->prepare("
        SELECT u.id, COALESCE(NULLIF(sp.full_name, ''), u.username) AS name
        FROM student_enrollments se
        JOIN users u ON u.id = se.student_id AND u.deleted_at IS NULL
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE se.class_id = ?
        ORDER BY name
    ");
    $rosterStmt->execute([$classId]);
    $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $lookupMetric = $defaultMetric !== '' ? $defaultMetric : null;
    if ($lookupMetric && $defaultDate !== '') {
        $exSql = "
            SELECT student_id, score, note
            FROM student_progress
            WHERE class_id = ? AND metric = ? AND recorded_at = ?
        ";
        $exParams = [$classId, $lookupMetric, $defaultDate];
        if ($examId > 0 && campus_column_exists($pdo, 'student_progress', 'exam_id')) {
            $exSql .= " AND exam_id <=> ?";
            $exParams[] = $examId;
        }
        $exStmt = $pdo->prepare($exSql);
        $exStmt->execute($exParams);
        foreach ($exStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $existingByStudent[(int)$row['student_id']] = $row;
        }
    }
}

$editing = null;
if ($editId > 0) {
    $editStmt = $pdo->prepare("
        SELECT p.*, COALESCE(NULLIF(sp.full_name, ''), u.username) AS student_name,
               c.name AS class_name, s.name AS subject_name
        FROM student_progress p
        JOIN users u ON u.id = p.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        LEFT JOIN student_classes c ON c.id = p.class_id
        LEFT JOIN subjects s ON s.id = p.subject_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $editStmt->execute([$editId]);
    $editing = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editing) {
        $rowClass = (int)($editing['class_id'] ?? 0);
        if (!$isAdmin && ($rowClass < 1 || !in_array($rowClass, $allowedClassIds, true))) {
            $editing = null;
            $error = $error !== '' ? $error : 'You can only edit results for your classes.';
        } elseif ($classId < 1) {
            $classId = $rowClass;
        }
    }
}

$recent = [];
$recentSql = "
    SELECT p.id, p.class_id, p.metric, p.score, p.max_score, p.recorded_at, p.note,
           COALESCE(NULLIF(sp.full_name, ''), u.username) AS student_name,
           c.name AS class_name, s.name AS subject_name
    FROM student_progress p
    JOIN users u ON u.id = p.student_id
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN student_classes c ON c.id = p.class_id
    LEFT JOIN subjects s ON s.id = p.subject_id
    WHERE 1=1
";
$recentParams = [];
if ($classId > 0) {
    $recentSql .= " AND p.class_id = ?";
    $recentParams[] = $classId;
} elseif (!$isAdmin) {
    if ($allowedClassIds === []) {
        $recentSql .= " AND 1=0";
    } else {
        $recentSql .= " AND p.class_id IN (" . implode(',', array_fill(0, count($allowedClassIds), '?')) . ")";
        array_push($recentParams, ...$allowedClassIds);
    }
}
$recentSql .= " ORDER BY p.recorded_at DESC, p.id DESC LIMIT 80";
$recentStmt = $pdo->prepare($recentSql);
$recentStmt->execute($recentParams);
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow text-success"></i> Marks</h1>
            <p class="text-muted mb-0">Enter mock, exam, or test scores. Students see them in Academic Hub and can ask WhatsApp for progress.</p>
        </div>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= e(BASE_URL) ?>campus/exams.php">Exam timetable</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h5 class="mb-3">Class</h5>
                    <?php if (!$classes): ?>
                        <p class="text-muted mb-0">No classes are assigned to you yet.</p>
                    <?php else: ?>
                        <div class="class-picker">
                            <?php foreach ($classes as $c): ?>
                                <a class="class-picker-item <?= (int)$c['id'] === $classId ? 'is-active' : '' ?>"
                                   href="?class_id=<?= (int)$c['id'] ?><?= $examId > 0 ? '&exam_id=' . $examId : '' ?>">
                                    <?= e((string)$c['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($editing): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Edit result · <?= e((string)$editing['student_name']) ?></h5>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$editing['class_id'] ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Assessment</label>
                                    <input class="form-control" name="metric" required maxlength="100" value="<?= e((string)$editing['metric']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date</label>
                                    <input class="form-control" type="date" name="recorded_at" required value="<?= e((string)$editing['recorded_at']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Score</label>
                                    <div class="input-group">
                                        <input class="form-control" name="score" type="number" step="0.01" min="0" required value="<?= e((string)$editing['score']) ?>">
                                        <span class="input-group-text">/</span>
                                        <input class="form-control" name="max_score" type="number" step="0.01" min="0.01" required value="<?= e((string)$editing['max_score']) ?>">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Note</label>
                                    <input class="form-control" name="note" maxlength="500" value="<?= e((string)($editing['note'] ?? '')) ?>">
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-primary rounded-pill" name="save_one" value="1">Save</button>
                                <a class="btn btn-outline-secondary rounded-pill" href="?class_id=<?= (int)$classId ?>">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($classId < 1): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <p class="text-muted mb-0">Select a class to enter marks for the enrolled students.</p>
                    </div>
                </div>
            <?php elseif (!$roster): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <p class="text-muted mb-0">No enrolled students in <?= e($className) ?>.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <h5 class="mb-1"><?= e($className) ?></h5>
                        <p class="small text-muted">Leave a score blank to skip that student. Saving the same assessment and date again updates the existing result.</p>
                        <?php if ($examPrefill): ?>
                            <div class="alert alert-light border mb-3">Entering marks for <strong><?= e((string)$examPrefill['title']) ?></strong> (<?= e(campus_exam_when($examPrefill)) ?>).</div>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="class_id" value="<?= $classId ?>">
                            <input type="hidden" name="exam_id" value="<?= $examId ?>">
                            <div class="row g-3 mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Assessment name</label>
                                    <input class="form-control" name="metric" required maxlength="100" placeholder="Unit 4 mock / May paper" value="<?= e($defaultMetric) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date</label>
                                    <input class="form-control" type="date" name="recorded_at" required value="<?= e($defaultDate) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Out of</label>
                                    <input class="form-control" type="number" name="max_score" step="0.01" min="0.01" required value="<?= e($defaultMax) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Subject</label>
                                    <select class="form-select" name="subject_id">
                                        <option value="">Optional</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>" <?= $defaultSubject === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th style="width:8rem">Score</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roster as $st):
                                            $sid = (int)$st['id'];
                                            $prev = $existingByStudent[$sid] ?? null;
                                            $posted = $_POST['score'][$sid] ?? $_POST['score'][(string)$sid] ?? null;
                                            $scoreVal = $posted !== null ? (string)$posted : (string)($prev['score'] ?? '');
                                            $noteVal = (string)($_POST['note'][$sid] ?? $prev['note'] ?? '');
                                        ?>
                                            <tr>
                                                <td><?= e((string)$st['name']) ?></td>
                                                <td>
                                                    <input class="form-control" type="number" step="0.01" min="0" name="score[<?= $sid ?>]" value="<?= e($scoreVal) ?>" inputmode="decimal">
                                                </td>
                                                <td>
                                                    <input class="form-control" name="note[<?= $sid ?>]" maxlength="500" value="<?= e($noteVal) ?>" placeholder="Optional">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="publish_results" id="publish_results" checked>
                                <label class="form-check-label" for="publish_results">Publish to students (Academic Hub)</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="notify_students" id="notify_students">
                                <label class="form-check-label" for="notify_students">Notify these students on WhatsApp and in the portal</label>
                            </div>
                            <button class="btn btn-primary rounded-pill">Save marks</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-body">
            <h5 class="mb-3"><?= $classId > 0 ? 'Recent results in this class' : 'Recent results' ?></h5>
            <?php if (!$recent): ?>
                <p class="text-muted mb-0">No marks recorded yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Assessment</th>
                                <th>Score</th>
                                <th>Class / subject</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $row): ?>
                                <tr>
                                    <td><?= e(date('d M Y', strtotime((string)$row['recorded_at']))) ?></td>
                                    <td><?= e((string)$row['student_name']) ?></td>
                                    <td><?= e((string)$row['metric']) ?></td>
                                    <td><?= e((string)$row['score']) ?> / <?= e((string)$row['max_score']) ?></td>
                                    <td class="small text-muted">
                                        <?= e((string)($row['class_name'] ?? '')) ?>
                                        <?php if (!empty($row['subject_name'])): ?> · <?= e((string)$row['subject_name']) ?><?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="?class_id=<?= (int)($row['class_id'] ?? $classId) ?>&edit=<?= (int)$row['id'] ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this result?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="class_id" value="<?= $classId ?>">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" name="delete_mark" value="1">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
