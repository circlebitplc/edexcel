<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit(); }
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND deleted_at IS NULL"); $stmt->execute([$id]); $room = $stmt->fetch();
if (!$room) { $_SESSION['error'] = 'Room not found.'; header('Location: index.php'); exit(); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) $error = 'Invalid security token.';
    else {
        $name = trim($_POST['name'] ?? ''); $capacity = (int)($_POST['capacity'] ?? 0);
        if ($name === '' || $capacity <= 0) $error = 'Room name and a valid capacity are required.';
        else {
            try { $stmt = $pdo->prepare("UPDATE rooms SET name = ?, capacity = ? WHERE id = ?"); $stmt->execute([$name, $capacity, $id]); $_SESSION['success'] = 'Room updated successfully.'; header('Location: index.php'); exit(); }
            catch (PDOException $e) { $error = 'Unable to update the room. Please check the room name and try again.'; }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="ops-page">
    <div class="ops-page-header"><div><h1><i class="bi bi-pencil-square"></i> Edit Room</h1><p>Update classroom details without changing existing timetable relationships.</p></div></div>
    <div class="ops-card ops-form-card"><div class="ops-card-body">
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST"><?= csrf_field() ?>
            <div class="ops-form-section"><h5><?= htmlspecialchars($room['name']) ?></h5>
                <div class="mb-3"><label class="form-label" for="name">Room Name <span class="text-danger">*</span></label><input class="form-control" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($room['name']) ?>"></div>
                <div class="mb-0"><label class="form-label" for="capacity">Capacity <span class="text-danger">*</span></label><input type="number" class="form-control" id="capacity" name="capacity" min="1" max="1000" required value="<?= (int)$room['capacity'] ?>"></div>
            </div>
            <div class="d-flex gap-2 flex-wrap"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Room</button><a href="index.php" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
