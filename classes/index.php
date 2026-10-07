<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$sql = "
    SELECT c.id, c.name, c.description, l.name AS level_name,
           COUNT(DISTINCT sc.subject_id) AS subject_count,
           COUNT(DISTINCT ts.teacher_id) AS teacher_count,
           COUNT(DISTINCT e.id) AS student_count
    FROM student_classes c
    LEFT JOIN levels l ON c.level_id = l.id
    LEFT JOIN subject_classes sc ON c.id = sc.class_id
    LEFT JOIN teacher_subjects ts ON sc.subject_id = ts.subject_id
    LEFT JOIN student_enrollments e ON c.id = e.class_id
    WHERE c.deleted_at IS NULL
";
$params = [];
if (!$isAdmin) {
    if ($teacherId < 1) {
        $sql .= " AND 1=0";
    } else {
        $sql .= "
            AND (
                EXISTS (
                    SELECT 1 FROM timetable tt
                    WHERE tt.class_id = c.id AND tt.teacher_id = ? AND tt.deleted_at IS NULL
                )
                OR EXISTS (
                    SELECT 1 FROM subject_classes sc2
                    JOIN teacher_subjects ts2 ON ts2.subject_id = sc2.subject_id
                    WHERE sc2.class_id = c.id AND ts2.teacher_id = ?
                )
            )
        ";
        $params[] = $teacherId;
        $params[] = $teacherId;
    }
}
$sql .= " GROUP BY c.id, c.name, c.description, l.name ORDER BY c.name";
$classesStmt = $pdo->prepare($sql);
$classesStmt->execute($params);
$classes = $classesStmt->fetchAll();

$levelCount = (int)$pdo->query("SELECT COUNT(*) FROM levels")->fetchColumn();
$subjectCount = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-toolbar">
    <div>
        <h1 class="page-title"><i class="bi bi-layers"></i> <?= $isAdmin ? 'Manage Classes' : 'My Classes' ?></h1>
        <p class="page-subtitle"><?= $isAdmin ? 'Manage class groups, levels, subjects and teaching coverage.' : 'Classes you teach. Add a new class for your students.' ?></p>
    </div>
    <div class="actions">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add New Class</a>
        <a href="../campus/waitlist.php" class="btn btn-outline-warning"><i class="bi bi-hourglass-split"></i> Waitlist</a>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="management-stats">
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-layers"></i></span><div><strong><?= count($classes) ?></strong><small>Classes</small></div></div>
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-mortarboard"></i></span><div><strong><?= $levelCount ?></strong><small>Levels</small></div></div>
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-book"></i></span><div><strong><?= $subjectCount ?></strong><small>Subjects</small></div></div>
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-people"></i></span><div><strong><?= $teacherCount ?></strong><small>Teachers</small></div></div>
</div>

<div data-live-scope>
<div class="management-toolbar">
    <div class="management-search">
        <i class="bi bi-search"></i>
        <input type="search" id="classSearch" class="form-control" placeholder="Search classes, levels or descriptions..." autocomplete="off" data-live-search>
    </div>
    <div class="management-toolbar-note"><i class="bi bi-info-circle"></i> <span data-live-count><?= count($classes) ?></span> matching</div>
</div>

<div class="management-grid" id="classGrid">
<?php foreach ($classes as $c): ?>
    <article class="management-card class-card" data-live-item data-search="<?= htmlspecialchars(strtolower($c['name'].' '.$c['level_name'].' '.$c['description'])) ?>">
        <div class="management-card-head">
            <div class="management-icon"><i class="bi bi-layers"></i></div>
            <div class="management-card-title-wrap">
                <h2><?= htmlspecialchars($c['name']) ?></h2>
                <span class="status-pill info"><?= htmlspecialchars($c['level_name'] ?: 'No level') ?></span>
            </div>
        </div>
        <p class="management-description"><?= htmlspecialchars($c['description'] ?: 'No description has been added for this class.') ?></p>
        <div class="management-metrics">
            <div><strong><?= (int)$c['subject_count'] ?></strong><span>Subjects</span></div>
            <div><strong><?= (int)$c['teacher_count'] ?></strong><span>Teachers</span></div>
            <div><strong><?= (int)$c['student_count'] ?></strong><span>Students</span></div>
        </div>
        <div class="management-card-actions">
            <a href="edit.php?id=<?= (int)$c['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Manage</a>
            <?php if ($isAdmin): ?>
            <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this class from the active directory?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
    <div class="management-empty" id="classEmpty" data-live-empty <?= count($classes) ? 'style="display:none"' : '' ?>><i class="bi bi-layers"></i><strong>No classes found</strong><span>Try another search or add a new class.</span></div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
