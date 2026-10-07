<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';
require_admin();

ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$id = $_GET['id'] ?? 0;
if (!$id) { header('Location: index.php'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM student_classes WHERE id = ?");
$stmt->execute([$id]);
$class = $stmt->fetch();
if (!$class) { $_SESSION['error'] = 'Class not found.'; header('Location: index.php'); exit(); }

if (!$isAdmin) {
    $access = $pdo->prepare("
        SELECT 1
        FROM student_classes c
        WHERE c.id = ?
          AND c.deleted_at IS NULL
          AND (
            EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.class_id = c.id AND tt.teacher_id = ? AND tt.deleted_at IS NULL
            )
            OR EXISTS (
                SELECT 1 FROM subject_classes sc
                JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
                WHERE sc.class_id = c.id AND ts.teacher_id = ?
            )
          )
        LIMIT 1
    ");
    $access->execute([(int)$id, $teacherId, $teacherId]);
    if (!$access->fetchColumn()) {
        $_SESSION['error'] = 'You can only manage classes assigned to you.';
        header('Location: index.php');
        exit();
    }
}

$levels = $pdo->query("SELECT * FROM levels ORDER BY name")->fetchAll();
$assigned = $pdo->prepare("SELECT subject_id FROM subject_classes WHERE class_id = ?");
$assigned->execute([$id]);
$assigned_ids = $assigned->fetchAll(PDO::FETCH_COLUMN);
$subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
if (!$isAdmin && $teacherId > 0) {
    $mine = $pdo->prepare("
        SELECT s.id, s.name
        FROM subjects s
        INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
        WHERE ts.teacher_id = ? AND s.deleted_at IS NULL
        ORDER BY s.name
    ");
    $mine->execute([$teacherId]);
    $teacherSubjectRows = $mine->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($teacherSubjectRows) {
        $keep = [];
        foreach ($subjects as $s) {
            if (in_array((int)$s['id'], array_map('intval', $assigned_ids), true)) {
                $keep[(int)$s['id']] = $s;
            }
        }
        foreach ($teacherSubjectRows as $s) {
            $keep[(int)$s['id']] = $s;
        }
        $subjects = array_values($keep);
    }
}

$teacherStmt = $pdo->prepare("\n    SELECT DISTINCT t.id, t.name\n    FROM teachers t\n    JOIN teacher_subjects ts ON t.id = ts.teacher_id\n    JOIN subject_classes sc ON ts.subject_id = sc.subject_id\n    WHERE sc.class_id = ?\n    ORDER BY t.name\n");
$teacherStmt->execute([$id]);
$teachers = $teacherStmt->fetchAll();

$links = [];
if (!empty($teachers)) {
    $placeholders = implode(',', array_fill(0, count($teachers), '?'));
    $linkStmt = $pdo->prepare("SELECT teacher_id, whatsapp_link FROM class_teacher_whatsapp WHERE class_id = ? AND teacher_id IN ($placeholders)");
    $params = array_merge([$id], array_column($teachers, 'id'));
    $linkStmt->execute($params);
    while ($row = $linkStmt->fetch()) $links[$row['teacher_id']] = $row['whatsapp_link'];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $level_id = $_POST['level_id'] ?? 0;
        $description = trim($_POST['description'] ?? '');
        $capacityRaw = trim((string)($_POST['capacity'] ?? ''));
        $capacity = $capacityRaw === '' ? null : max(1, (int)$capacityRaw);
        $subject_ids = $_POST['subjects'] ?? [];
        $whatsapp_links = $_POST['whatsapp_links'] ?? [];
        if (!$isAdmin) {
            $mineIds = [];
            foreach ($subjects as $s) {
                $mineIds[] = (int)$s['id'];
            }
            $posted = array_map('intval', (array)$subject_ids);
            $keepOthers = [];
            foreach ($assigned_ids as $sid) {
                $sid = (int)$sid;
                if ($sid > 0 && !in_array($sid, $mineIds, true)) {
                    $keepOthers[] = $sid;
                }
            }
            $subject_ids = array_values(array_unique(array_merge($keepOthers, array_intersect($posted, $mineIds))));
            if ($teacherId > 0) {
                $whatsapp_links = [
                    $teacherId => (string)($whatsapp_links[$teacherId] ?? ''),
                ];
            }
        }

        if (empty($name) || !$level_id) {
            $error = 'Name and Level are required.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE student_classes SET name = ?, level_id = ?, description = ?, capacity = ? WHERE id = ?");
                $stmt->execute([$name, $level_id, $description, $capacity, $id]);
                $pdo->prepare("DELETE FROM subject_classes WHERE class_id = ?")->execute([$id]);
                if (!empty($subject_ids)) {
                    $insert = $pdo->prepare("INSERT INTO subject_classes (subject_id, class_id) VALUES (?, ?)");
                    foreach ($subject_ids as $sid) $insert->execute([(int)$sid, $id]);
                }
                if (!$isAdmin && $teacherId > 0) {
                    $insertTs = $pdo->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                    foreach ($subject_ids as $sid) {
                        $insertTs->execute([$teacherId, (int)$sid]);
                    }
                }
                if ($isAdmin) {
                    $pdo->prepare("DELETE FROM class_teacher_whatsapp WHERE class_id = ?")->execute([$id]);
                    $insertLink = $pdo->prepare("INSERT INTO class_teacher_whatsapp (class_id, teacher_id, whatsapp_link) VALUES (?, ?, ?)");
                    foreach ($whatsapp_links as $teacher_id => $link) {
                        $link = trim($link);
                        if ($link !== '') $insertLink->execute([$id, (int)$teacher_id, $link]);
                    }
                } elseif ($teacherId > 0) {
                    $pdo->prepare("DELETE FROM class_teacher_whatsapp WHERE class_id = ? AND teacher_id = ?")->execute([$id, $teacherId]);
                    $link = trim((string)($whatsapp_links[$teacherId] ?? ''));
                    if ($link !== '') {
                        $pdo->prepare("INSERT INTO class_teacher_whatsapp (class_id, teacher_id, whatsapp_link) VALUES (?, ?, ?)")
                            ->execute([$id, $teacherId, $link]);
                    }
                }
                $pdo->commit();
                log_audit($pdo, 'update', 'student_classes', $id, $class, ['name' => $name, 'level_id' => $level_id, 'description' => $description, 'whatsapp_links' => $whatsapp_links]);
                $_SESSION['success'] = 'Class updated successfully.';
                header('Location: index.php');
                exit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Unable to update the class. Please check the selected subjects and links.';
            }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-toolbar"><div><h1 class="page-title"><i class="bi bi-pencil-square"></i> Edit Class</h1><p class="page-subtitle">Update class details, subjects and teacher WhatsApp links.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="form-page-card">
    <?= csrf_field() ?>
    <section class="form-section">
        <h2 class="form-section-title"><i class="bi bi-layers"></i> Class details</h2>
        <div class="row g-3">
            <div class="col-md-7"><label for="name" class="form-label">Class Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($class['name']) ?>"></div>
            <div class="col-md-5"><label for="level_id" class="form-label">Level <span class="text-danger">*</span></label><select class="form-select" id="level_id" name="level_id" required><option value="">Select Level</option><?php foreach ($levels as $l): ?><option value="<?= (int)$l['id'] ?>" <?= ($class['level_id'] == $l['id']) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label for="capacity" class="form-label">Seat limit</label><input type="number" min="1" class="form-control" id="capacity" name="capacity" value="<?= htmlspecialchars((string)($class['capacity'] ?? '')) ?>" placeholder="Unlimited"><div class="form-help">Leave empty for no limit. Full classes use a waitlist.</div></div>
            <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($class['description']) ?></textarea></div>
        </div>
    </section>

    <section class="form-section">
        <h2 class="form-section-title"><i class="bi bi-book"></i> Subjects offered</h2>
        <div class="choice-grid">
            <?php foreach ($subjects as $s): ?>
            <label class="choice-card form-check mb-0"><input class="form-check-input" type="checkbox" name="subjects[]" value="<?= (int)$s['id'] ?>" <?= in_array($s['id'], $assigned_ids) ? 'checked' : '' ?>><span class="form-check-label ms-1"><?= htmlspecialchars($s['name']) ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="form-help">Select every subject that this class group offers.</div>
    </section>

    <section class="form-section">
        <h2 class="form-section-title"><i class="bi bi-whatsapp"></i> Teacher WhatsApp links</h2>
        <?php if (empty($teachers)): ?>
            <div class="management-empty" style="min-height:130px"><i class="bi bi-people"></i><strong>No teachers available yet</strong><span>Assign subjects to teachers first; matching teachers will appear here.</span></div>
        <?php else: ?>
            <div class="row g-3">
            <?php foreach ($teachers as $teacher):
                if (!$isAdmin && (int)$teacher['id'] !== $teacherId) {
                    continue;
                }
            ?>
                <div class="col-lg-6"><div class="choice-card"><label for="wa_<?= (int)$teacher['id'] ?>" class="form-label fw-bold"><i class="bi bi-person me-1"></i><?= htmlspecialchars($teacher['name']) ?></label><input type="url" class="form-control" id="wa_<?= (int)$teacher['id'] ?>" name="whatsapp_links[<?= (int)$teacher['id'] ?>]" value="<?= htmlspecialchars($links[$teacher['id']] ?? '') ?>" placeholder="https://chat.whatsapp.com/..."><div class="form-help">Optional WhatsApp Community invite link.</div></div></div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Class</button><a href="index.php" class="btn btn-secondary">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
