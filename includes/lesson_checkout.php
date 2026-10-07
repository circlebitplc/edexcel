<?php
declare(strict_types=1);

use Edexcel\Services\BankTransferService;
use Edexcel\Services\OnePayService;
use Edexcel\Services\PaymentTransactionService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

/**
 * @return array{to:string,timetable_id:int,recording_id:int}
 */
function lesson_checkout_return(string $returnTo, int $recordingId, int $timetableId): array
{
    if (!in_array($returnTo, ['classroom', 'recording', 'fees', 'class'], true)) {
        $returnTo = $recordingId > 0 ? 'recording' : 'class';
    }
    return [
        'to' => $returnTo,
        'timetable_id' => $timetableId,
        'recording_id' => $recordingId,
    ];
}

function lesson_checkout_fail_url(string $kind, string $returnTo, int $recordingId, int $timetableId, int $studentId): string
{
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
    if ($kind === 'parent') {
        return $base . '/parent/class.php?lesson=' . max(0, $timetableId) . '&student=' . max(0, $studentId);
    }
    if ($returnTo === 'fees') {
        return $base . '/student/dashboard.php?tab=fees';
    }
    if ($returnTo === 'classroom' && $timetableId > 0) {
        return $base . '/classroom/room.php?lesson=' . $timetableId;
    }
    if ($returnTo === 'recording' && $recordingId > 0) {
        return $base . '/student/recording.php?id=' . $recordingId;
    }
    if ($timetableId > 0) {
        return $base . '/student/class.php?lesson=' . $timetableId;
    }
    return $base . '/student/dashboard.php?tab=fees';
}

function lesson_checkout_ok_url(string $kind, string $returnTo, int $recordingId, int $timetableId, int $studentId): string
{
    return lesson_checkout_fail_url($kind, $returnTo, $recordingId, $timetableId, $studentId);
}

function lesson_checkout_public_start_error(): string
{
    return 'We could not start the payment. Please try again. If the problem continues, contact the college.';
}

function lesson_checkout_log_failure(Throwable $e): void
{
    $message = trim($e->getMessage());
    $message = preg_replace('/(password|passwd|secret|token|hash|authorization|app_token)\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;
    if (preg_match('/access denied|db_pass|identified by/i', $message)) {
        $message = $e::class . ' database connection error';
    }
    error_log('Payment creation failed: ' . $message);
}

function lesson_sync_onepay(PDO $pdo, int $studentId, int $timetableId): void
{
    if ($studentId < 1 || $timetableId < 1) {
        return;
    }
    try {
        $payments = new PaymentTransactionService($pdo);
        $payments->syncOnePayForLesson($studentId, $timetableId, new StudentLessonFeeService($pdo));
    } catch (Throwable $e) {
        error_log('lesson_sync_onepay: ' . $e->getMessage());
    }
}

/**
 * @param array<string,mixed> $payerProfile
 */
function lesson_run_onepay_checkout(
    PDO $pdo,
    int $studentId,
    array $lesson,
    array $fee,
    int $recordingId,
    string $returnTo,
    array $payerProfile,
    string $payerRole = 'student',
    ?int $parentId = null
): never {
    $timetableId = (int)($lesson['timetable_id'] ?? $lesson['id'] ?? 0);
    $amount = (float)$fee['amount_due'];
    $onepay = new OnePayService($pdo);
    if (!$onepay->isEnabled()) {
        throw new RuntimeException('Card payments are not available yet. Pay by bank transfer or at the college counter.');
    }
    $name = trim((string)($payerProfile['full_name'] ?? $payerProfile['name'] ?? ''));
    $parts = preg_split('/\s+/', $name !== '' ? $name : 'Student') ?: ['Student'];
    $first = (string)array_shift($parts);
    $last = trim(implode(' ', $parts));
    $phone = (string)($payerProfile['whatsapp_number'] ?? $payerProfile['phone'] ?? '');
    $email = (string)($payerProfile['email'] ?? '');

    $payments = new PaymentTransactionService($pdo);
    $txn = $payments->initiate(
        $studentId,
        $lesson,
        $amount,
        $onepay->currency(),
        $recordingId > 0 ? $recordingId : null,
        isset($fee['row']['id']) ? (int)$fee['row']['id'] : null,
        $payerRole,
        $parentId
    );
    $existingUrl = trim((string)($txn['gateway_transaction_id'] ?? '')) !== ''
        ? $payments->storedCheckoutUrl($txn)
        : '';
    if ($existingUrl !== '') {
        header('Location: ' . $existingUrl);
        exit;
    }
    $checkout = $onepay->createCheckout([
        'reference' => (string)$txn['gateway_reference'],
        'amount' => $amount,
        'first_name' => $first,
        'last_name' => $last !== '' ? $last : 'Student',
        'phone' => $phone,
        'email' => $email,
        'additional' => (string)$txn['gateway_reference'],
    ]);
    if ($checkout['ipg_transaction_id'] !== '') {
        $payments->attachGatewayId((int)$txn['id'], $checkout['ipg_transaction_id'], $checkout['raw']);
    }
    log_audit($pdo, 'onepay_checkout', 'payment_transactions', (int)$txn['id'], null, [
        'timetable_id' => $timetableId,
        'amount' => $amount,
        'payer' => $payerRole,
    ]);
    header('Location: ' . $checkout['redirect_url']);
    exit;
}

/**
 * @param array<string,mixed> $file
 * @return array<string,mixed>
 */
function lesson_submit_bank_slip(
    PDO $pdo,
    int $studentId,
    array $lesson,
    array $fee,
    array $file,
    int $recordingId,
    ?int $parentId,
    string $note
): array {
    if (!function_exists('bank_transfer_ready') || !bank_transfer_ready($pdo)) {
        throw new RuntimeException('Bank transfer is not available yet. Pay with the card or at the college counter.');
    }
    $bank = new BankTransferService($pdo);
    return $bank->submitSlip(
        $studentId,
        $lesson,
        $file,
        (float)$fee['amount_due'],
        $recordingId > 0 ? $recordingId : null,
        isset($fee['row']['id']) ? (int)$fee['row']['id'] : null,
        $parentId,
        $note
    );
}

/**
 * @return array{lesson:?array,fee:array,recording_id:int,timetable_id:int}
 */
function lesson_checkout_resolve_fee(PDO $pdo, int $studentId, int $recordingId, int $timetableId): array
{
    $fees = new StudentLessonFeeService($pdo);
    $lesson = null;
    $fee = [
        'status' => 'unpaid',
        'amount_due' => 0.0,
        'row' => null,
        'covered_by_monthly' => false,
    ];

    if ($recordingId > 0) {
        $access = new RecordingAccessService($pdo);
        $result = $access->evaluate($studentId, $recordingId);
        if ($result['state'] === RecordingAccessService::ACCESS_GRANTED) {
            return [
                'lesson' => $result['recording'],
                'fee' => $result['fee'],
                'recording_id' => $recordingId,
                'timetable_id' => (int)($result['recording']['timetable_id'] ?? $timetableId),
                'already_paid' => true,
            ];
        }
        if ($result['state'] !== RecordingAccessService::PAYMENT_REQUIRED && $result['state'] !== RecordingAccessService::PAYMENT_PENDING) {
            throw new RuntimeException(RecordingAccessService::studentMessage($result['state']));
        }
        $lesson = $result['recording'];
        $fee = $result['fee'];
        $timetableId = (int)($lesson['timetable_id'] ?? $timetableId);
        return [
            'lesson' => $lesson,
            'fee' => $fee,
            'recording_id' => $recordingId,
            'timetable_id' => $timetableId,
            'already_paid' => false,
        ];
    }

    if ($timetableId < 1) {
        throw new RuntimeException('Choose a class to pay for.');
    }
    $recordings = new RecordingService($pdo);
    $lesson = $recordings->findLesson($timetableId);
    if (!$lesson || !empty($lesson['deleted_at']) || !$fees->isEnrolled($studentId, $lesson)) {
        throw new RuntimeException('You are not authorised to pay for this lesson.');
    }
    $existing = $recordings->activeForLesson($timetableId);
    if ($existing) {
        $recordingId = (int)$existing['id'];
    }
    $fee = $fees->resolve($studentId, $lesson, $recordingId > 0 ? $recordingId : null);
    $paid = StudentLessonFeeService::isUnlocked((string)$fee['status'], (bool)$fee['covered_by_monthly']);
    return [
        'lesson' => $lesson,
        'fee' => $fee,
        'recording_id' => $recordingId,
        'timetable_id' => $timetableId,
        'already_paid' => $paid,
    ];
}
