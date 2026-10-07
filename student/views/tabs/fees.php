<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/i18n.php';
$wallet = $feeWallet ?? ['due' => 0, 'paid' => 0, 'billed' => 0, 'next' => null, 'rows' => [], 'breakdown' => []];
$statement = $feeStatement ?? ['due' => $wallet['due'] ?? 0, 'paid' => $wallet['paid'] ?? 0, 'lessons' => [], 'receipts' => [], 'lesson_due' => 0];
$breakdown = $wallet['breakdown'] ?? [];
$lessons = $statement['lessons'] ?? [];
$lessonPayments = $lessonPayments ?? ($statement['receipts'] ?? []);
$walletDue = (float)($wallet['due'] ?? 0);
$lessonDue = (float)($statement['lesson_due'] ?? 0);
$totalDue = (float)($statement['due'] ?? ($walletDue + $lessonDue));

$payable = [];
foreach ($lessons as $row) {
    if (in_array((string)($row['status'] ?? ''), ['unpaid', 'pending', 'partial'], true) && empty($row['covered_by_monthly'])) {
        $payable[] = $row;
    }
}
$primaryPay = $payable[0] ?? null;
$blockedCount = count($payable);
?>
<?= eck_lang_toggle() ?>
<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-wallet2 me-2 text-primary"></i><?= student_e(eck_t('fees.title')) ?></h3>
    <p class="text-muted mb-0"><?= student_e(eck_t('fees.intro_clear')) ?></p>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary-subtle">
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-7">
                <div class="text-muted small"><?= student_e(eck_t('fees.due')) ?></div>
                <div class="display-6 fw-bold">Rs <?= number_format($totalDue, 2) ?></div>
                <p class="mb-0 mt-2 small">
                    <strong><?= student_e(eck_t('fees.wallet')) ?></strong>: Rs <?= number_format($walletDue, 2) ?>
                    <span class="text-muted">— <?= student_e(eck_t('fees.wallet_help')) ?></span><br>
                    <strong><?= student_e(eck_t('fees.lessons')) ?></strong>: Rs <?= number_format($lessonDue, 2) ?>
                    <span class="text-muted">— <?= student_e(eck_t('fees.lessons_help')) ?></span>
                </p>
                <?php if ($blockedCount > 0): ?>
                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
                        <i class="bi bi-lock"></i>
                        <?= student_e(sprintf(eck_t('fees.blocked'), $blockedCount)) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success py-2 px-3 mt-3 mb-0 small">
                        <i class="bi bi-unlock"></i> <?= student_e(eck_t('fees.unblocked')) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-5 text-lg-end">
                <?php if ($primaryPay): ?>
                    <a class="btn btn-primary btn-lg rounded-pill" href="<?= student_e(student_class_page_url((int)$primaryPay['timetable_id'], 'fees')) ?>">
                        <i class="bi bi-credit-card"></i>
                        <?= student_e(eck_t('fees.pay_next')) ?> — Rs <?= number_format((float)$primaryPay['amount_due'] - (float)($primaryPay['amount_paid'] ?? 0), 2) ?>
                    </a>
                    <div class="small text-muted mt-2">
                        <?= student_e(($primaryPay['subject_name'] ?? '') . ' · ' . ($primaryPay['class_name'] ?? '')) ?>
                        <?php if (!empty($primaryPay['date'])): ?> · <?= student_e(date('d M', strtotime((string)$primaryPay['date']))) ?><?php endif; ?>
                    </div>
                <?php elseif ($walletDue > 0): ?>
                    <div class="fw-semibold"><?= student_e(eck_t('fees.pay_counter')) ?></div>
                    <div class="small text-muted"><?= student_e(eck_t('fees.pay_counter_help')) ?></div>
                <?php else: ?>
                    <span class="badge text-bg-success fs-6"><?= student_e(eck_t('fees.already_paid')) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="text-muted small"><?= student_e(eck_t('fees.paid')) ?></div>
            <div class="fs-3 fw-bold text-success">Rs <?= number_format((float)($statement['paid'] ?? $wallet['paid'])) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="text-muted small"><?= student_e(eck_t('fees.next')) ?></div>
            <?php if (!empty($wallet['next'])): ?>
                <div class="fw-bold">Rs <?= number_format((float)$wallet['next']['amount_due'] - (float)$wallet['next']['amount_paid']) ?></div>
                <div class="small text-muted"><?= student_e($wallet['next']['description']) ?>
                    <?php if (!empty($wallet['next']['due_date'])): ?> · due <?= student_e(date('d M', strtotime($wallet['next']['due_date']))) ?><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="fw-bold"><?= student_e(eck_t('fees.none')) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="text-muted small"><?= student_e(eck_t('fees.how_title')) ?></div>
            <ol class="small mb-0 ps-3">
                <li><?= student_e(eck_t('fees.how_1')) ?></li>
                <li><?= student_e(eck_t('fees.how_2')) ?></li>
                <li><?= student_e(eck_t('fees.how_3')) ?></li>
            </ol>
        </div>
    </div>
</div>

<div class="card border shadow-sm rounded-4 mb-4">
    <div class="card-body pb-0">
        <h4 class="h6 fw-bold mb-1"><?= student_e(eck_t('fees.lessons')) ?></h4>
        <p class="text-muted small"><?= student_e(eck_t('fees.lessons_help')) ?></p>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th><?= student_e(eck_t('att.date')) ?></th><th><?= student_e(eck_t('att.class')) ?></th><th><?= student_e(eck_t('att.status')) ?></th><th></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lessons as $row): ?>
                <tr>
                    <td><?= student_e(!empty($row['date']) ? date('d M Y', strtotime((string)$row['date'])) : '') ?></td>
                    <td><?= student_e($row['subject_name'] . ' · ' . $row['class_name']) ?></td>
                    <td>
                        <?= student_e(eck_t('fees.status.' . $row['status']) !== 'fees.status.' . $row['status'] ? eck_t('fees.status.' . $row['status']) : $row['status']) ?>
                        <?php if (!empty($row['covered_by_monthly'])): ?>
                            <span class="small text-muted">(<?= student_e(eck_t('fees.gateway.wallet')) ?>)</span>
                        <?php elseif ($row['gateway'] !== ''): ?>
                            <span class="small text-muted">(<?= student_e(match ((string)$row['gateway']) {
                                'cash' => eck_t('fees.gateway.cash'),
                                'bank' => eck_t('fees.gateway.bank'),
                                'other' => 'Other',
                                'manual' => 'Teacher Manual Payment',
                                default => eck_t('fees.gateway.onepay'),
                            }) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>Rs <?= number_format((float)$row['amount_due'], 2) ?></td>
                    <td>
                        <?php if (in_array($row['status'], ['paid', 'waived'], true) || !empty($row['covered_by_monthly'])): ?>
                            <span class="badge text-bg-success"><?= student_e($row['status'] === 'waived' ? eck_t('fees.status.waived') : eck_t('fees.already_paid')) ?></span>
                        <?php elseif (in_array($row['status'], ['unpaid', 'pending', 'partial'], true)): ?>
                            <a class="btn btn-sm btn-primary" href="<?= student_e(student_class_page_url((int)$row['timetable_id'], 'fees')) ?>"><?= student_e(eck_t('fees.pay_now')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($lessons === []): ?>
                <tr><td colspan="5" class="text-muted p-4"><?= student_e(eck_t('fees.empty_lessons')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border shadow-sm rounded-4 mb-4">
    <div class="card-body pb-0">
        <h4 class="h6 fw-bold mb-1"><?= student_e(eck_t('fees.wallet')) ?></h4>
        <p class="text-muted small mb-3"><?= student_e(eck_t('fees.empty_wallet')) ?></p>
    </div>
    <?php if (empty($breakdown)): ?>
        <div class="text-muted px-4 pb-4"><?= student_e(eck_t('fees.empty_wallet')) ?></div>
    <?php else: ?>
        <?php foreach ($breakdown as $group): ?>
            <div class="border-top px-4 pt-3">
                <div class="d-flex justify-content-between align-items-baseline gap-3 mb-2">
                    <div class="fw-semibold"><?= student_e($group['class_name']) ?></div>
                    <div class="fw-bold">Rs <?= number_format((float)$group['total']) ?></div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th><?= student_e(eck_t('att.date')) ?></th>
                            <th></th>
                            <th><?= student_e(eck_t('att.status')) ?></th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['lessons'] as $lesson): ?>
                        <?php
                            $lessonDate = (string)($lesson['date'] ?? '');
                            $start = (string)($lesson['start_time'] ?? '');
                            $end = (string)($lesson['end_time'] ?? '');
                            $dateLabel = $lessonDate !== '' ? date('D, d M Y', strtotime($lessonDate)) : '—';
                            $timeLabel = ($start !== '' && $end !== '')
                                ? date('g:i A', strtotime($start)) . ' – ' . date('g:i A', strtotime($end))
                                : '—';
                        ?>
                        <tr>
                            <td><?= student_e($dateLabel) ?></td>
                            <td><?= student_e($timeLabel) ?></td>
                            <td><?= student_e((string)($lesson['attendance_status'] ?? '')) ?></td>
                            <td class="text-end">Rs <?= number_format((float)$lesson['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card border shadow-sm rounded-4">
    <div class="card-body pb-0">
        <h4 class="h6 fw-bold mb-3"><?= student_e(eck_t('fees.receipts')) ?></h4>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th><?= student_e(eck_t('att.date')) ?></th><th></th><th></th><th><?= student_e(eck_t('att.status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lessonPayments as $pay): ?>
                <tr>
                    <td><?= student_e(!empty($pay['date']) ? date('d M Y', strtotime((string)$pay['date'])) : '') ?></td>
                    <td><?= student_e((string)($pay['subject_name'] ?? '')) ?></td>
                    <td>Rs <?= number_format((float)$pay['amount'], 2) ?>
                        <?php
                        $rawGateway = strtolower((string)($pay['gateway'] ?? ''));
                        $payMethod = \Edexcel\Services\StudentLessonFeeService::displayMethod($pay);
                        $payLabel = $payMethod !== $rawGateway
                            ? \Edexcel\Services\StudentLessonFeeService::gatewayLabel($payMethod)
                            : $rawGateway;
                        ?>
                        <span class="small text-muted"><?= student_e($payLabel) ?></span>
                    </td>
                    <td><?= student_e((string)$pay['status']) ?></td>
                    <td>
                        <?php if ((string)$pay['status'] === 'paid'): ?>
                            <a href="<?= student_e(BASE_URL . 'student/payment_receipt.php?id=' . (int)$pay['id']) ?>">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($lessonPayments === []): ?>
                <tr><td colspan="5" class="text-muted p-4"><?= student_e(eck_t('fees.empty_receipts')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
