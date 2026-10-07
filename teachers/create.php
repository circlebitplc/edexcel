<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';
require_admin();

$error = '';
$success = '';

// Fetch subjects for assignment
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject_ids = $_POST['subjects'] ?? [];

    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert teacher
            $stmt = $pdo->prepare("INSERT INTO teachers (name, email) VALUES (?, ?)");
            $stmt->execute([$name, $email]);
            $teacher_id = $pdo->lastInsertId();

            // Assign subjects
            if (!empty($subject_ids)) {
                $stmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($subject_ids as $sid) {
                    $stmt->execute([$teacher_id, $sid]);
                }
            }

            // Create user account for this teacher
            $baseUsername = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
            $username = $baseUsername !== '' ? $baseUsername : 'teacher';
            $suffix = 1;
            while (true) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $check->execute([$username]);
                if ((int)$check->fetchColumn() === 0) break;
                $username = $baseUsername . $suffix++;
            }

            // Generate a unique temporary password instead of using a shared default.
            $temporaryPassword = bin2hex(random_bytes(6));
            $password_hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, teacher_id, google_email) VALUES (?, ?, 'teacher', ?, ?)");
            $stmt->execute([$username, $password_hash, $teacher_id, $email]);

            $pdo->commit();
            $_SESSION['success'] = "Teacher '$name' added successfully. Username: $username, Temporary password: $temporaryPassword";
            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Teacher creation failed: '.$e->getMessage());
            $error = 'Unable to create the teacher. Please check the submitted information.';
        }
    }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="teacher-page-header">
  <div><h1 class="teacher-page-title"><i class="bi bi-person-plus-fill"></i> Add Teacher</h1><p class="teacher-page-subtitle">Create a teacher profile and assign the subjects they teach.</p></div>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Teachers</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="teacher-form-card">
<form method="POST"><?= csrf_field() ?>
  <div class="teacher-form-section">
    <h5><i class="bi bi-person-vcard"></i> Basic Information</h5>
    <div class="row g-3">
      <div class="col-lg-6"><label for="name" class="form-label">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. Enidu Batuwanthudawe"></div>
      <div class="col-lg-6"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="teacher@example.com"></div>
    </div>
  </div>
  <div class="teacher-form-section">
    <h5><i class="bi bi-book"></i> Subjects</h5>
    <p class="text-muted small mb-3">Select every subject this teacher is assigned to. You can change these later.</p>
    <div class="subject-check-grid">
      <?php foreach ($subjects as $s): ?><div class="subject-check"><div class="form-check"><input class="form-check-input" type="checkbox" name="subjects[]" value="<?= $s['id'] ?>" id="sub_<?= $s['id'] ?>" <?= (isset($_POST['subjects']) && in_array($s['id'], $_POST['subjects'])) ? 'checked' : '' ?>><label class="form-check-label" for="sub_<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></label></div></div><?php endforeach; ?>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 justify-content-end"><a href="index.php" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Teacher</button></div>
</form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
