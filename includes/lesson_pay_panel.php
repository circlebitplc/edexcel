<?php
declare(strict_types=1);
/**
 * Pay panel for a lesson (OnePay + bank slip). Expects:
 * $timetableId, $recordingId, $returnTo, $payAction, $amount, $feeStatus,
 * $onepayEnabled, $bankCfg, $openSlip, $studentId (0 for student session)
 */
$timetableId = (int)($timetableId ?? 0);
$recordingId = (int)($recordingId ?? 0);
$returnTo = (string)($returnTo ?? 'class');
$payAction = (string)($payAction ?? (rtrim((string)BASE_URL, '/') . '/student/pay_lesson.php'));
$amount = (float)($amount ?? 0);
$feeStatus = strtolower((string)($feeStatus ?? 'unpaid'));
$onepayEnabled = !empty($onepayEnabled);
$bankCfg = is_array($bankCfg ?? null) ? $bankCfg : [];
$openSlip = is_array($openSlip ?? null) ? $openSlip : null;
$studentId = (int)($studentId ?? 0);
$payMode = function_exists('classroom_normalize_delivery_mode')
    ? classroom_normalize_delivery_mode((string)($payDeliveryMode ?? ($mode ?? 'physical')))
    : strtolower((string)($payDeliveryMode ?? ($mode ?? 'physical')));
$isOnlinePay = $payMode === 'online';
$alreadyPaid = in_array($feeStatus, ['paid', 'waived'], true);
$paidLabel = $feeStatus === 'waived' ? 'Fee waived' : 'Already paid';
$bankEnabled = !empty($bankCfg['enabled']);
if (!function_exists('teacher_manual_payment_enabled')) {
    require_once __DIR__ . '/../config/payment_controls.php';
}
$teacherCanMark = !isset($pdo) || !$pdo instanceof PDO || teacher_manual_payment_allowed(
    teacher_manual_payment_enabled($pdo),
    false,
    ['delivery_mode' => $payMode]
);
$showCard = $onepayEnabled && ($alreadyPaid || !$openSlip);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<?php if ($alreadyPaid): ?>
    <div class="lesson-pay-paid" role="status">
        <div class="lesson-pay-paid-icon" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></div>
        <div>
            <p class="mb-1 fw-bold"><?= $feeStatus === 'waived' ? 'This class fee is waived' : 'You have already paid for this lesson' ?></p>
            <p class="mb-0 text-muted">Pay Now is turned off. The live class and this lesson’s recording are unlocked.</p>
        </div>
    </div>
<?php elseif ($feeStatus === 'pending' && $openSlip): ?>
    <div class="lesson-pay-pending">
        <p class="mb-1 fw-semibold">Your bank slip is with the office</p>
        <p class="text-muted mb-0">The live class and recording stay locked until they confirm the transfer.<?= $bankEnabled ? ' You can upload a clearer copy below if needed.' : ' Bank slip upload is turned off. The office still has this slip.' ?></p>
    </div>
<?php elseif ($feeStatus === 'pending'): ?>
    <div class="alert alert-warning">Your card payment is still being confirmed. Refresh in a moment<?= $bankEnabled ? ', or pay by bank transfer below' : '' ?>.</div>
<?php endif; ?>

<?php if ($alreadyPaid || $amount > 0): ?>
    <div class="lesson-pay-amount mb-3">
        <div class="text-muted small"><?= $isOnlinePay ? 'Online class fee' : 'Class fee' ?></div>
        <div class="fs-3 fw-bold">Rs <?= number_format($amount, 2) ?></div>
        <?php if ($isOnlinePay && !$alreadyPaid): ?>
            <p class="text-muted small mb-2">Total payment Rs <?= number_format($amount, 2) ?>. This is the class fee sent to the payment gateway. Institute and handling fees are settled from this amount and are not added again.</p>
        <?php endif; ?>
        <?php if ($alreadyPaid): ?>
            <p class="text-success small mb-0 fw-semibold"><?= $h($paidLabel) ?></p>
        <?php else: ?>
            <p class="text-muted small mb-0">Pay before class starts — online or in college. The same payment unlocks the live class and this lesson’s recording.</p>
        <?php endif; ?>
    </div>

    <div class="lesson-pay-methods">
        <?php if ($showCard): ?>
            <section class="lesson-pay-card<?= $alreadyPaid ? ' is-paid' : ' is-secure' ?>" id="pay-card">
                <?php if ($alreadyPaid): ?>
                    <div class="lesson-pay-secure-badge is-paid">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <?= $h($paidLabel) ?>
                    </div>
                <?php else: ?>
                    <div class="lesson-pay-secure-badge">
                        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                        Secure payment
                    </div>
                <?php endif; ?>
                <h2 class="h6 fw-bold"><i class="bi bi-credit-card me-1"></i> Pay with card</h2>
                <?php if ($alreadyPaid): ?>
                    <p class="small text-muted mb-2">This lesson is already paid. You do not need to pay again.</p>
                    <?php student_pay_now_form([
                        'timetable_id' => $timetableId,
                        'recording_id' => $recordingId,
                        'return' => $returnTo,
                        'action' => $payAction,
                        'student_id' => $studentId,
                        'button_class' => 'btn btn-success',
                        'label' => $paidLabel,
                        'disabled' => true,
                    ]); ?>
                <?php else: ?>
                    <p class="small text-muted mb-2">Visa, Mastercard, and other cards. You start the payment on this college site over an encrypted connection.</p>
                    <?php student_pay_now_form([
                        'timetable_id' => $timetableId,
                        'recording_id' => $recordingId,
                        'return' => $returnTo,
                        'action' => $payAction,
                        'student_id' => $studentId,
                        'button_class' => 'btn btn-primary',
                        'label' => 'Pay Now',
                    ]); ?>
                    <ul class="lesson-pay-secure-list">
                        <li><i class="bi bi-lock-fill" aria-hidden="true"></i> HTTPS encrypted on edexcel.college</li>
                        <li><i class="bi bi-shield-check" aria-hidden="true"></i> Card details are not stored by the college</li>
                    </ul>
                    <p class="lesson-pay-policies">By paying you agree to the
                        <a href="<?= $h(rtrim((string)BASE_URL, '/') . '/terms.php') ?>">Terms and Conditions</a>,
                        <a href="<?= $h(rtrim((string)BASE_URL, '/') . '/privacy.php') ?>">Privacy Policy</a>,
                        and <a href="<?= $h(rtrim((string)BASE_URL, '/') . '/refund.php') ?>">Refund Policy</a>.
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($bankEnabled && !$alreadyPaid): ?>
            <section class="lesson-pay-card" id="pay-bank">
                <h2 class="h6 fw-bold"><i class="bi bi-bank me-1"></i> Bank transfer</h2>
                <?php if (($bankCfg['bank_name'] ?? '') !== '' || ($bankCfg['account_number'] ?? '') !== ''): ?>
                    <dl class="lesson-bank-details">
                        <?php if (($bankCfg['bank_name'] ?? '') !== ''): ?>
                            <dt>Bank</dt><dd><?= $h($bankCfg['bank_name']) ?></dd>
                        <?php endif; ?>
                        <?php if (($bankCfg['account_name'] ?? '') !== ''): ?>
                            <dt>Account name</dt><dd><?= $h($bankCfg['account_name']) ?></dd>
                        <?php endif; ?>
                        <?php if (($bankCfg['account_number'] ?? '') !== ''): ?>
                            <dt>Account number</dt><dd class="font-monospace"><?= $h($bankCfg['account_number']) ?></dd>
                        <?php endif; ?>
                        <?php if (($bankCfg['branch'] ?? '') !== ''): ?>
                            <dt>Branch</dt><dd><?= $h($bankCfg['branch']) ?></dd>
                        <?php endif; ?>
                    </dl>
                <?php else: ?>
                    <p class="small text-muted">Transfer the class fee to the college bank account, then upload the slip. Ask the office if you need the account details.</p>
                <?php endif; ?>
                <?php if (($bankCfg['instructions'] ?? '') !== ''): ?>
                    <p class="small"><?= nl2br($h($bankCfg['instructions'])) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= $h($payAction) ?>" enctype="multipart/form-data" class="lesson-slip-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="method" value="bank">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <?php if ($recordingId > 0): ?>
                        <input type="hidden" name="recording_id" value="<?= $recordingId ?>">
                    <?php endif; ?>
                    <?php if ($studentId > 0): ?>
                        <input type="hidden" name="student_id" value="<?= $studentId ?>">
                    <?php endif; ?>
                    <input type="hidden" name="return" value="<?= $h($returnTo) ?>">
                    <div class="mb-2">
                        <label class="form-label small" for="slip-<?= $timetableId ?>">Bank slip (JPG, PNG, or PDF)</label>
                        <input class="form-control" type="file" id="slip-<?= $timetableId ?>" name="slip" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="slip-note-<?= $timetableId ?>">Reference / note (optional)</label>
                        <input class="form-control" type="text" id="slip-note-<?= $timetableId ?>" name="slip_note" maxlength="180" placeholder="Your name or transfer reference">
                    </div>
                    <button class="btn btn-outline-primary" type="submit">
                        <?= $openSlip ? 'Upload a new slip' : 'Upload slip for verification' ?>
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if (!$alreadyPaid && !$onepayEnabled && !$bankEnabled): ?>
            <p class="mb-0"><?= $teacherCanMark ? 'Online payment is not available yet. Pay at the college counter or ask the teacher to mark the class fee.' : 'Online payment is not available yet. Pay at the college counter.' ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
