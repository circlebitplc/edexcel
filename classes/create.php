<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$levels = $pdo->query("SELECT * FROM levels ORDER BY name")->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $level_id = $_POST['level_id'] ?? 0;
        $description = trim($_POST['description'] ?? '');
        if (empty($name) || !$level_id) {
            $error = 'Name and Level are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO student_classes (name, level_id, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $level_id, $description]);
                $_SESSION['success'] = "Class '$name' added successfully.";
                header('Location: index.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Unable to save the class. Please check the class details and try again.';
            }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-toolbar"><div><h1 class="page-title"><i class="bi bi-plus-circle"></i> Add New Class</h1><p class="page-subtitle">Create a class group and assign its academic level.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="form-page-card">
    <?= csrf_field() ?>
    <section class="form-section">
        <h2 class="form-section-title"><i class="bi bi-layers"></i> Class details</h2>
        <div class="row g-3">
            <div class="col-md-7"><label for="name" class="form-label">Class Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. IGCSE ICT 2027"></div>
            <div class="col-md-5"><label for="level_id" class="form-label">Level <span class="text-danger">*</span></label><select class="form-select" id="level_id" name="level_id" required><option value="">Select Level</option><?php foreach ($levels as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (isset($_POST['level_id']) && $_POST['level_id'] == $l['id']) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4" placeholder="Optional notes about this class..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
        </div>
    </section>
    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Class</button><a href="index.php" class="btn btn-secondary">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
