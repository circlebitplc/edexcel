<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$id = $_GET['id'] ?? 0;
if (!$id) { header('Location: index.php'); exit(); }
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$id]);
$subject = $stmt->fetch();
if (!$subject) { $_SESSION['error'] = 'Subject not found.'; header('Location: index.php'); exit(); }

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
            $stmt = $pdo->prepare("UPDATE subjects SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $_SESSION['success'] = "Subject updated successfully.";
            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-toolbar"><div><h1 class="page-title"><i class="bi bi-pencil-square"></i> Edit Subject</h1><p class="page-subtitle">Update the subject name used across the system.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="form-page-card">
    <?= csrf_field() ?>
    <section class="form-section">
        <h2 class="form-section-title"><i class="bi bi-book"></i> Subject details</h2>
        <label for="name" class="form-label">Subject Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($subject['name']) ?>">
    </section>
    <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Subject</button><a href="index.php" class="btn btn-secondary">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>