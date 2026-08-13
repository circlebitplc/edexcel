<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $error = 'Subject name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
                $stmt->execute([$name]);
                $_SESSION['success'] = "Subject '$name' added successfully.";
                header('Location: index.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Unable to save the subject. Please check the subject name and try again.';
            }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-toolbar"><div><h1 class="page-title"><i class="bi bi-plus-circle"></i> Add New Subject</h1><p class="page-subtitle">Create a subject that can be assigned to teachers and classes.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="form-page-card">
    <?= csrf_field() ?>
    <section class="form-section">
        <h2 class="form-section-title"><i class="bi bi-book"></i> Subject details</h2>
        <label for="name" class="form-label">Subject Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. IGCSE Computer Science">
        <div class="form-help">Use a clear subject name because it will appear throughout the timetable, teacher profiles and class management.</div>
    </section>
    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Subject</button><a href="index.php" class="btn btn-secondary">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
