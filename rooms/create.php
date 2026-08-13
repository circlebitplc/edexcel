<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) $error = 'Invalid security token.';
    else {
        $name = trim($_POST['name'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        if ($name === '' || $capacity <= 0) $error = 'Room name and a valid capacity are required.';
        else {
            try {
                $stmt = $pdo->prepare("INSERT INTO rooms (name, capacity) VALUES (?, ?)");
                $stmt->execute([$name, $capacity]);
                $_SESSION['success'] = "Room '$name' added successfully.";
                header('Location: index.php'); exit();
            } catch (PDOException $e) { $error = 'Unable to save the room. Please check the room name and try again.'; }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="ops-page">
    <div class="ops-page-header"><div><h1><i class="bi bi-plus-circle"></i> Add Room</h1><p>Create a classroom that can be assigned to timetable entries.</p></div></div>
    <div class="ops-card ops-form-card"><div class="ops-card-body">
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="ops-form-section"><h5>Room details</h5>
                <div class="mb-3"><label class="form-label" for="name">Room Name <span class="text-danger">*</span></label><input class="form-control" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. Room 01"></div>
                <div class="mb-0"><label class="form-label" for="capacity">Capacity <span class="text-danger">*</span></label><input type="number" class="form-control" id="capacity" name="capacity" min="1" max="1000" required value="<?= htmlspecialchars($_POST['capacity'] ?? 30) ?>"><div class="form-text">Maximum number of students the room can comfortably accommodate.</div></div>
            </div>
            <div class="d-flex gap-2 flex-wrap"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Room</button><a href="index.php" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
