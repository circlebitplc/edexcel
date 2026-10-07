<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/i18n.php';
$monthRows = $attendanceMonth ?? [];
$summary = $attendanceSummary ?? ['present' => 0, 'absent' => 0, 'late' => 0, 'percent' => null];
?>
<?= eck_lang_toggle() ?>
<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-check2-circle me-2 text-primary"></i><?= student_e(eck_t('att.title')) ?></h3>
    <p class="text-muted mb-0"><?= student_e(eck_t('att.intro')) ?></p>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted"><?= student_e(eck_t('att.present')) ?></div><strong><?= (int)$summary['present'] ?></strong></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted"><?= student_e(eck_t('att.absent')) ?></div><strong><?= (int)$summary['absent'] ?></strong></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted"><?= student_e(eck_t('att.late')) ?></div><strong><?= (int)$summary['late'] ?></strong></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 p-3"><div class="small text-muted"><?= student_e(eck_t('att.month')) ?></div><strong><?= $summary['percent'] !== null ? (int)$summary['percent'] . '%' : '—' ?></strong></div></div>
</div>
<div class="card border shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th><?= student_e(eck_t('att.date')) ?></th><th><?= student_e(eck_t('att.class')) ?></th><th><?= student_e(eck_t('att.status')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($monthRows as $row): ?>
                <tr>
                    <td><?= student_e(date('D d M, h:i A', strtotime($row['date'] . ' ' . $row['start_time']))) ?></td>
                    <td><?= student_e($row['subject_name'] . ' · ' . $row['class_name']) ?></td>
                    <td>
                        <span class="badge <?= $row['status'] === 'absent' ? 'bg-danger' : ($row['status'] === 'late' ? 'bg-warning text-dark' : ($row['status'] === 'excused' ? 'bg-secondary' : 'bg-success')) ?>">
                            <?= student_e(eck_t('att.' . $row['status']) !== 'att.' . $row['status'] ? eck_t('att.' . $row['status']) : $row['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$monthRows): ?>
                <tr><td colspan="3" class="text-muted p-4"><?= student_e(eck_t('att.empty')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
