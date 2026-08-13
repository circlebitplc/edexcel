<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/holidays.php';
require_admin();
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) $error = 'Invalid security token.';
    elseif (isset($_POST['add_holiday'])) {
        $date = validate_input($_POST['date'] ?? '', 'date');
        $name = validate_input($_POST['name'] ?? '', 'string');
        $description = validate_input($_POST['description'] ?? '', 'string');
        if (!$date || !$name) $error = 'Date and name are required.';
        elseif (is_holiday($pdo, $date)) $error = 'A holiday is already registered for that date.';
        else {
            try { $stmt = $pdo->prepare("INSERT INTO holidays (date, name, description) VALUES (?, ?, ?)"); $stmt->execute([$date,$name,$description]); log_audit($pdo,'create','holidays',$pdo->lastInsertId(),null,['date'=>$date,'name'=>$name]); $success='Holiday added successfully.'; }
            catch(PDOException $e) { $error='Unable to add the holiday.'; }
        }
    } elseif (isset($_POST['delete_holiday'])) {
        $id=(int)($_POST['id']??0);
        if ($id) { try { $stmt=$pdo->prepare("DELETE FROM holidays WHERE id=?"); $stmt->execute([$id]); log_audit($pdo,'delete','holidays',$id); $success='Holiday deleted.'; } catch(PDOException $e) { $error='Unable to delete the holiday.'; } }
    }
}
$holidays = get_holidays($pdo);
$today = date('Y-m-d');
$upcoming = array_values(array_filter($holidays, fn($h) => $h['date'] >= $today));
$past = array_values(array_filter($holidays, fn($h) => $h['date'] < $today));
$next = $upcoming[0] ?? null;
include __DIR__ . '/../includes/header.php';
?>
<div class="ops-page">
    <div class="ops-page-header"><div><h1><i class="bi bi-calendar2-x"></i> Holidays</h1><p>Manage dates that should be treated as non-teaching days by scheduling workflows.</p></div></div>
    <div class="ops-stat-grid">
        <div class="ops-stat"><span class="label">Total Holidays</span><span class="value"><?= count($holidays) ?></span><span class="hint">Registered dates</span></div>
        <div class="ops-stat"><span class="label">Upcoming</span><span class="value"><?= count($upcoming) ?></span><span class="hint">From today onward</span></div>
        <div class="ops-stat"><span class="label">Past</span><span class="value"><?= count($past) ?></span><span class="hint">Historical dates</span></div>
        <div class="ops-stat"><span class="label">Next Holiday</span><span class="value" style="font-size:1.05rem"><?= $next ? date('d M Y', strtotime($next['date'])) : '—' ?></span><span class="hint"><?= $next ? htmlspecialchars($next['name']) : 'None scheduled' ?></span></div>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="ops-holiday-layout">
        <div class="ops-card"><div class="ops-card-body">
            <h5 class="fw-bold mb-3">Add Holiday</h5>
            <form method="POST"><?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="date">Date <span class="text-danger">*</span></label><input type="date" class="form-control" id="date" name="date" min="<?= $today ?>" required></div>
                <div class="mb-3"><label class="form-label" for="name">Holiday Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" maxlength="150" required placeholder="e.g. Vesak Day"></div>
                <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="500" placeholder="Optional notes"></textarea></div>
                <button type="submit" name="add_holiday" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add Holiday</button>
            </form>
        </div></div>
        <div class="ops-card"><div class="ops-card-body">
            <div class="ops-toolbar"><div class="ops-search"><input type="search" id="holidaySearch" class="form-control" placeholder="Search holidays..."></div><select id="holidayFilter" class="form-select" style="max-width:170px"><option value="all">All</option><option value="upcoming">Upcoming</option><option value="past">Past</option></select></div>
            <?php if (!$holidays): ?><div class="ops-empty"><i class="bi bi-calendar-check"></i><strong>No holidays registered</strong><div>Add a holiday to keep scheduling rules accurate.</div></div><?php else: ?>
                <div id="holidayList">
                <?php foreach ($holidays as $h): $isUpcoming=$h['date'] >= $today; ?>
                    <div class="ops-holiday-item" data-search="<?= htmlspecialchars(strtolower($h['name'].' '.$h['description'])) ?>" data-period="<?= $isUpcoming?'upcoming':'past' ?>">
                        <div class="ops-date-badge"><strong><?= date('d', strtotime($h['date'])) ?></strong><span><?= date('M', strtotime($h['date'])) ?></span></div>
                        <div class="ops-holiday-content"><div class="ops-holiday-title"><?= htmlspecialchars($h['name']) ?> <?= $isUpcoming ? '<span class="ops-chip success ms-1">Upcoming</span>' : '<span class="ops-chip ms-1">Past</span>' ?></div><div class="ops-holiday-desc"><?= htmlspecialchars($h['description'] ?: date('l, Y', strtotime($h['date']))) ?></div></div>
                        <form method="POST" onsubmit="return confirm('Delete this holiday?')"><?= csrf_field() ?><input type="hidden" name="delete_holiday" value="1"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
                    </div>
                <?php endforeach; ?>
                </div><div id="holidayEmpty" class="ops-empty d-none"><i class="bi bi-search"></i><strong>No matching holidays</strong></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<script>
(function(){const search=document.getElementById('holidaySearch'), filter=document.getElementById('holidayFilter'); function run(){const q=(search?.value||'').toLowerCase().trim(), f=filter?.value||'all'; let n=0; document.querySelectorAll('#holidayList .ops-holiday-item').forEach(el=>{const show=el.dataset.search.includes(q)&&(f==='all'||el.dataset.period===f); el.classList.toggle('d-none',!show); if(show)n++;}); document.getElementById('holidayEmpty')?.classList.toggle('d-none',n!==0); } search?.addEventListener('input',run); filter?.addEventListener('change',run);})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
