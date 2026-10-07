<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();
$error=''; $success='';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_schedule'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error='Invalid security token.';
    } else {
        try {
            $id=(int)($_POST['id']??0);
            TimetableServiceFactory::services($pdo)['recurring']->delete($id);
            $success='Recurring schedule removed. Existing generated classes were kept.';
        } catch(Throwable $e) {
            $error='Unable to remove this recurring schedule.';
            error_log('Recurring schedule delete failed: '.$e->getMessage());
        }
    }
}
$sql="SELECT rs.*, t.name teacher_name, s.name subject_name, c.name class_name, r.name room_name
       FROM recurring_schedules rs
       JOIN teachers t ON rs.teacher_id=t.id
       JOIN subjects s ON rs.subject_id=s.id
       JOIN student_classes c ON rs.class_id=c.id
       JOIN rooms r ON rs.room_id=r.id
       WHERE (t.deleted_at IS NULL OR t.deleted_at IS NULL)
       ORDER BY rs.end_date >= CURDATE() DESC, rs.start_date DESC";
$schedules=$pdo->query($sql)->fetchAll();
$today=date('Y-m-d');
$active=array_values(array_filter($schedules,fn($s)=>$s['end_date'] >= $today));
$expired=count($schedules)-count($active);
$nextDates=[];
foreach($active as $s){ $last=$s['last_generated_date'] ?: $s['start_date']; $next=date('Y-m-d',strtotime($last.' +7 days')); if($next<=$s['end_date']) $nextDates[]=$next; }
$nextRun=$nextDates?min($nextDates):null;
include __DIR__ . '/../includes/header.php';
?>
<div class="ops-page">
    <div class="ops-page-header"><div><h1><i class="bi bi-arrow-repeat"></i> Recurring Schedules</h1><p>Manage weekly timetable rules that generate future lessons automatically.</p></div><div class="ops-actions"><a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Schedule</a></div></div>
    <div class="ops-stat-grid">
        <div class="ops-stat"><span class="label">Total Rules</span><span class="value"><?= count($schedules) ?></span><span class="hint">Saved recurring schedules</span></div>
        <div class="ops-stat"><span class="label">Active</span><span class="value"><?= count($active) ?></span><span class="hint">Still generating future lessons</span></div>
        <div class="ops-stat"><span class="label">Expired</span><span class="value"><?= $expired ?></span><span class="hint">Past their end date</span></div>
        <div class="ops-stat"><span class="label">Next Generation</span><span class="value" style="font-size:1.05rem"><?= $nextRun ? date('d M Y',strtotime($nextRun)) : '—' ?></span><span class="hint">Based on saved weekly rules</span></div>
    </div>
    <?php if($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="ops-card" data-live-scope><div class="ops-card-body">
        <div class="ops-toolbar"><div class="ops-search"><input type="search" id="recurringSearch" class="form-control" placeholder="Search teacher, subject, class or room..." data-live-search></div><select id="recurringFilter" class="form-select" style="max-width:170px" data-live-select data-live-key="status"><option value="all">All schedules</option><option value="active">Active</option><option value="expired">Expired</option></select></div>
        <?php if(!$schedules): ?><div class="ops-empty"><i class="bi bi-arrow-repeat"></i><strong>No recurring schedules</strong><div>Create a weekly schedule from the timetable Add Lesson screen.</div></div><?php else: ?>
            <div class="ops-recurring-grid" id="recurringGrid">
            <?php foreach($schedules as $s): $isActive=$s['end_date'] >= $today; $search=strtolower($s['teacher_name'].' '.$s['subject_name'].' '.$s['class_name'].' '.$s['room_name'].' '.$s['day_of_week']); ?>
                <article class="ops-recurring-card" data-live-item data-search="<?= htmlspecialchars($search) ?>" data-status="<?= $isActive?'active':'expired' ?>">
                    <div class="ops-recurring-top"><div><div class="ops-recurring-title"><?= htmlspecialchars($s['subject_name']) ?></div><div class="text-muted small mt-1"><?= htmlspecialchars($s['class_name']) ?></div></div><span class="ops-chip <?= $isActive?'success':'warning' ?>"><?= $isActive?'Active':'Expired' ?></span></div>
                    <div class="mt-3"><span class="ops-chip"><i class="bi bi-person"></i><?= htmlspecialchars($s['teacher_name']) ?></span> <span class="ops-chip"><i class="bi bi-door-open"></i><?= htmlspecialchars($s['room_name']) ?></span></div>
                    <div class="ops-recurring-meta">
                        <div class="ops-meta-box"><small>Weekly</small><strong><?= htmlspecialchars($s['day_of_week']) ?></strong></div>
                        <div class="ops-meta-box"><small>Time</small><strong><?= date('h:i A',strtotime($s['start_time'])) ?> – <?= date('h:i A',strtotime($s['end_time'])) ?></strong></div>
                        <div class="ops-meta-box"><small>Starts</small><strong><?= date('d M Y',strtotime($s['start_date'])) ?></strong></div>
                        <div class="ops-meta-box"><small>Ends</small><strong><?= date('d M Y',strtotime($s['end_date'])) ?></strong></div>
                    </div>
                    <div class="ops-recurring-footer"><span class="text-muted small">Last generated: <?= $s['last_generated_date'] ? date('d M Y',strtotime($s['last_generated_date'])) : 'Not yet' ?></span><form method="POST" onsubmit="return confirm('Delete this recurring schedule? Existing generated classes will remain.')"><?= csrf_field() ?><input type="hidden" name="delete_schedule" value="1"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Remove</button></form></div>
                </article>
            <?php endforeach; ?>
            </div><div id="recurringEmpty" class="ops-empty d-none" data-live-empty><i class="bi bi-search"></i><strong>No matching schedules</strong></div>
        <?php endif; ?>
    </div></div>
    <div class="alert alert-info mt-3"><i class="bi bi-info-circle"></i> Removing a recurring rule stops future generation; it does <strong>not</strong> delete lessons that were already generated.</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
