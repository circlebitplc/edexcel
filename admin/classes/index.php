<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_admin();

$classes = $pdo->query("SELECT c.*, l.name as level_name FROM student_classes c 
                        LEFT JOIN levels l ON c.level_id = l.id 
                        WHERE c.deleted_at IS NULL
                        ORDER BY c.name")->fetchAll();
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<h1 class="mb-4"><i class="bi bi-layers"></i> Manage Classes</h1>
<a href="<?= BASE_URL ?>classes/create.php" class="btn btn-primary mb-3"><i class="bi bi-plus-circle"></i> Add New Class</a>
<a href="../../campus/waitlist.php" class="btn btn-outline-warning mb-3"><i class="bi bi-hourglass-split"></i> Waitlist</a>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Class Name</th>
                <th>Level</th>
                <th>Description</th>
                <th>WhatsApp</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($classes) === 0): ?>
                <tr><td colspan="6" class="text-center">No classes found.</td></tr>
            <?php else: ?>
                <?php foreach ($classes as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($c['level_name']) ?></span></td>
                        <td><?= htmlspecialchars($c['description'] ?: '-') ?></td>
                        <td>
                            <?php if (!empty($c['whatsapp_link'])): ?>
                                <a href="<?= htmlspecialchars($c['whatsapp_link']) ?>" target="_blank" class="btn btn-sm btn-success" title="Join Community">
                                    <i class="bi bi-whatsapp"></i> Join
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a href="<?= BASE_URL ?>classes/edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <a href="<?= BASE_URL ?>classes/delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this class?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>