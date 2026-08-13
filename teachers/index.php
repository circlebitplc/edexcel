<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$stmt = $pdo->query("SELECT t.*, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS subjects
    FROM teachers t
    LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
    LEFT JOIN subjects s ON ts.subject_id = s.id
    WHERE t.deleted_at IS NULL
    GROUP BY t.id
    ORDER BY t.name");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalTeachers = count($teachers);
$assignedTeachers = 0;
$totalSubjectLinks = 0;
foreach ($teachers as $teacher) {
    if (!empty($teacher['subjects'])) $assignedTeachers++;
    if (!empty($teacher['subjects'])) $totalSubjectLinks += count(array_filter(array_map('trim', explode(',', $teacher['subjects']))));
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="teacher-page-header">
    <div>
        <h1 class="teacher-page-title"><i class="bi bi-people-fill"></i> Teachers</h1>
        <p class="teacher-page-subtitle">Manage teacher profiles, subjects and accounts. The directory automatically scales as your teaching team grows.</p>
    </div>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Add Teacher</a>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="teacher-stats">
    <div class="teacher-stat"><div class="teacher-stat-icon"><i class="bi bi-people"></i></div><div><strong><?= $totalTeachers ?></strong><span>Total teachers</span></div></div>
    <div class="teacher-stat"><div class="teacher-stat-icon"><i class="bi bi-book"></i></div><div><strong><?= $assignedTeachers ?></strong><span>With subjects assigned</span></div></div>
    <div class="teacher-stat"><div class="teacher-stat-icon"><i class="bi bi-diagram-3"></i></div><div><strong><?= $totalSubjectLinks ?></strong><span>Subject assignments</span></div></div>
</div>

<div class="teacher-toolbar">
    <div class="teacher-toolbar-grid">
        <div class="teacher-search">
            <i class="bi bi-search"></i>
            <input type="search" id="teacherSearch" class="form-control" placeholder="Search teacher, email or subject…" autocomplete="off">
        </div>
        <select id="subjectFilter" class="form-select">
            <option value="">All subjects</option>
            <?php
            $filterSubjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filterSubjects as $subject):
            ?>
                <option value="<?= htmlspecialchars(strtolower($subject['name'])) ?>"><?= htmlspecialchars($subject['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-secondary" id="clearTeacherFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
    </div>
</div>

<div class="teacher-grid" id="teacherGrid">
    <?php foreach ($teachers as $t):
        $name = trim($t['name']);
        $parts = preg_split('/\s+/', $name);
        $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
        $subjectNames = array_values(array_filter(array_map('trim', explode(',', $t['subjects'] ?? ''))));
        $photoFile = '';
        foreach (['png','jpg','jpeg','webp','gif'] as $ext) {
            $candidate = __DIR__ . '/../assets/images/teachers/' . $t['id'] . '.' . $ext;
            if (file_exists($candidate)) { $photoFile = $t['id'] . '.' . $ext; break; }
        }
        $photoPath = $photoFile ? __DIR__ . '/../assets/images/teachers/' . $photoFile : '';
        $photoUrl = $photoFile ? BASE_URL . 'assets/images/teachers/' . $photoFile : '';
        $hasPhoto = file_exists($photoPath);
        $searchData = strtolower($name . ' ' . ($t['email'] ?? '') . ' ' . implode(' ', $subjectNames));
    ?>
        <article class="teacher-card" data-search="<?= htmlspecialchars($searchData) ?>" data-subjects="<?= htmlspecialchars(strtolower(implode('|', $subjectNames))) ?>">
            <div class="teacher-card-top">
                <div class="teacher-avatar">
                    <?php if ($hasPhoto): ?><img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
                    <?php else: ?><span class="teacher-avatar-initials"><?= htmlspecialchars($initials ?: '?') ?></span><?php endif; ?>
                </div>
                <div class="min-w-0">
                    <h2 class="teacher-card-name"><?= htmlspecialchars($name) ?></h2>
                    <div class="teacher-card-email" title="<?= htmlspecialchars($t['email'] ?? '') ?>"><?= htmlspecialchars($t['email'] ?? 'No email') ?></div>
                </div>
            </div>
            <div class="teacher-subjects">
                <?php if ($subjectNames): foreach (array_slice($subjectNames, 0, 3) as $subject): ?>
                    <span class="teacher-subject"><?= htmlspecialchars($subject) ?></span>
                <?php endforeach; if (count($subjectNames) > 3): ?><span class="teacher-subject more">+<?= count($subjectNames)-3 ?> more</span><?php endif; ?>
                <?php else: ?><span class="teacher-subject more">No subjects assigned</span><?php endif; ?>
            </div>
            <div class="teacher-card-actions">
                <a href="teacher_profile.php?id=<?= (int)$t['id'] ?>" class="btn btn-secondary"><i class="bi bi-person-vcard"></i> View Profile</a>
                <a href="edit.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this teacher from the active directory?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
    <div class="teacher-empty" id="teacherEmpty" <?= $teachers ? 'style="display:none"' : '' ?>><i class="bi bi-person-x fs-2 d-block mb-2"></i><strong>No teachers found</strong><div class="small mt-1">Try changing your search or add a new teacher.</div></div>
</div>

<script>
(function(){
    const search = document.getElementById('teacherSearch');
    const filter = document.getElementById('subjectFilter');
    const reset = document.getElementById('clearTeacherFilters');
    const cards = [...document.querySelectorAll('.teacher-card')];
    const empty = document.getElementById('teacherEmpty');
    function apply(){
        const q = search.value.trim().toLowerCase();
        const subject = filter.value.toLowerCase();
        let visible = 0;
        cards.forEach(card => {
            const matchText = !q || card.dataset.search.includes(q);
            const matchSubject = !subject || card.dataset.subjects.split('|').includes(subject);
            const show = matchText && matchSubject;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.style.display = visible ? 'none' : '';
    }
    search.addEventListener('input', apply);
    filter.addEventListener('change', apply);
    reset.addEventListener('click', function(){ search.value=''; filter.value=''; apply(); search.focus(); });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
