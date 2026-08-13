<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$rooms = $pdo->query("SELECT * FROM rooms WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$total_capacity = array_sum(array_map(fn($r) => (int)$r['capacity'], $rooms));
$largest = $rooms ? max(array_map(fn($r) => (int)$r['capacity'], $rooms)) : 0;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="ops-page">
    <div class="ops-page-header">
        <div>
            <h1><i class="bi bi-door-open"></i> Rooms</h1>
            <p>Manage classrooms and their seating capacity for timetable scheduling.</p>
        </div>
        <div class="ops-actions"><a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Room</a></div>
    </div>

    <div class="ops-stat-grid">
        <div class="ops-stat"><span class="label">Rooms</span><span class="value"><?= count($rooms) ?></span><span class="hint">Active classrooms</span></div>
        <div class="ops-stat"><span class="label">Total Capacity</span><span class="value"><?= number_format($total_capacity) ?></span><span class="hint">Seats across rooms</span></div>
        <div class="ops-stat"><span class="label">Largest Room</span><span class="value"><?= $largest ?></span><span class="hint">Maximum seats</span></div>
        <div class="ops-stat"><span class="label">Average</span><span class="value"><?= $rooms ? round($total_capacity / count($rooms)) : 0 ?></span><span class="hint">Seats per room</span></div>
    </div>

    <div class="ops-card">
        <div class="ops-card-body">
            <div class="ops-toolbar">
                <div class="ops-search"><input type="search" id="roomSearch" class="form-control" placeholder="Search rooms..."></div>
                <span class="ops-chip"><i class="bi bi-building"></i> <?= count($rooms) ?> active</span>
            </div>
            <?php if (!$rooms): ?>
                <div class="ops-empty"><i class="bi bi-door-closed"></i><strong>No rooms yet</strong><div>Add your first classroom to use it in the timetable.</div></div>
            <?php else: ?>
                <div class="ops-room-grid" id="roomGrid">
                    <?php foreach ($rooms as $r): ?>
                        <article class="ops-room-card" data-room-name="<?= htmlspecialchars(strtolower($r['name'])) ?>">
                            <div class="ops-room-icon"><i class="bi bi-door-open"></i></div>
                            <div class="ops-room-name"><?= htmlspecialchars($r['name']) ?></div>
                            <div class="ops-room-capacity"><i class="bi bi-people"></i> Capacity: <strong><?= (int)$r['capacity'] ?></strong> students</div>
                            <div class="ops-room-actions">
                                <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Delete this room?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div id="roomEmpty" class="ops-empty d-none"><i class="bi bi-search"></i><strong>No matching rooms</strong><div>Try a different search term.</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.getElementById('roomSearch')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase(); let visible = 0;
    document.querySelectorAll('#roomGrid .ops-room-card').forEach(card => {
        const show = card.dataset.roomName.includes(q); card.classList.toggle('d-none', !show); if (show) visible++;
    });
    document.getElementById('roomEmpty')?.classList.toggle('d-none', visible !== 0);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
