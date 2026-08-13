<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$subjects = $pdo->query("\n    SELECT s.id, s.name,\n           COUNT(DISTINCT ts.teacher_id) AS teacher_count,\n           COUNT(DISTINCT sc.class_id) AS class_count\n    FROM subjects s\n    LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id\n    LEFT JOIN subject_classes sc ON s.id = sc.subject_id\n    GROUP BY s.id, s.name\n    ORDER BY s.name\n")->fetchAll();

$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$classCount = (int)$pdo->query("SELECT COUNT(*) FROM student_classes")->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-toolbar">
    <div>
        <h1 class="page-title"><i class="bi bi-book"></i> Manage Subjects</h1>
        <p class="page-subtitle">Keep subjects connected to teachers and class groups.</p>
    </div>
    <div class="actions">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add New Subject</a>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="management-stats management-stats-3">
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-book"></i></span><div><strong><?= count($subjects) ?></strong><small>Subjects</small></div></div>
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-people"></i></span><div><strong><?= $teacherCount ?></strong><small>Teachers</small></div></div>
    <div class="management-stat"><span class="management-stat-icon"><i class="bi bi-layers"></i></span><div><strong><?= $classCount ?></strong><small>Classes</small></div></div>
</div>

<div class="management-toolbar">
    <div class="management-search">
        <i class="bi bi-search"></i>
        <input type="search" id="subjectSearch" class="form-control" placeholder="Search subjects..." autocomplete="off">
    </div>
    <div class="management-toolbar-note"><i class="bi bi-diagram-3"></i> Subject coverage is linked through teachers and classes</div>
</div>

<div class="management-grid subject-grid" id="subjectGrid">
<?php foreach ($subjects as $s): ?>
    <article class="management-card subject-card" data-search="<?= htmlspecialchars(strtolower($s['name'])) ?>">
        <div class="management-card-head">
            <div class="management-icon"><i class="bi bi-book"></i></div>
            <div class="management-card-title-wrap"><h2><?= htmlspecialchars($s['name']) ?></h2><span class="status-pill primary">Subject</span></div>
        </div>
        <div class="management-metrics management-metrics-2">
            <div><strong><?= (int)$s['teacher_count'] ?></strong><span>Teachers</span></div>
            <div><strong><?= (int)$s['class_count'] ?></strong><span>Classes</span></div>
        </div>
        <div class="management-card-actions">
            <a href="edit.php?id=<?= (int)$s['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Subject</a>
            <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this subject from the active directory?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
    <div class="management-empty" id="subjectEmpty" <?= count($subjects) ? 'style="display:none"' : '' ?>><i class="bi bi-book"></i><strong>No subjects found</strong><span>Try another search or add a new subject.</span></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('subjectSearch');
    const cards = Array.from(document.querySelectorAll('.subject-card'));
    const empty = document.getElementById('subjectEmpty');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        let visible = 0;
        cards.forEach(card => {
            const show = !q || card.dataset.search.includes(q);
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.style.display = visible ? 'none' : 'flex';
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
