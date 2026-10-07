<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class PaymentTransactionService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
        ClassSessionFeeCalculator::ensureSchema($this->pdo);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE gateway_reference = ? LIMIT 1');
        $stmt->execute([$reference]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByGatewayId(string $gatewayId): ?array
    {
        if ($gatewayId === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE gateway_transaction_id = ? LIMIT 1');
        $stmt->execute([$gatewayId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function studentHistory(int $studentId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, s.name AS subject_name, c.name AS class_name, tt.date, tt.start_time
            FROM payment_transactions p
            JOIN timetable tt ON tt.id = p.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            WHERE p.student_id = ?
            ORDER BY p.id DESC
            LIMIT " . max(1, min(100, $limit))
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create a new initiated transaction. Amount and student come from the server, never the browser.
     *
     * @return array<string,mixed>
     */
    public function initiate(
        int $studentId,
        array $lesson,
        float $amount,
        string $currency,
        ?int $recordingId,
        ?int $lessonFeeId,
        string $payerRole = 'student',
        ?int $parentId = null
    ): array {
        $timetableId = StudentLessonFeeService::timetableIdFrom($lesson);
        if ($this->hasPaidTransaction($studentId, $timetableId)) {
            throw new RuntimeException('This lesson is already paid.');
        }
        if ($this->hasPendingBank($studentId, $timetableId)) {
            throw new RuntimeException('A bank slip is already with the office. Wait for confirmation, or ask them to reject it before paying by card.');
        }
        $payerRole = $payerRole === 'parent' ? 'parent' : 'student';
        $ownTxn = !$this->pdo->inTransaction();
        if ($ownTxn) {
            $this->pdo->beginTransaction();
        }
        try {
            $existing = $this->reusableOnePayCheckout($studentId, $timetableId, $amount, $currency);
            if ($existing) {
                $this->expireOtherOnePay($studentId, $timetableId, (int)$existing['id']);
                if ($ownTxn) {
                    $this->pdo->commit();
                }
                return $existing;
            }
            $this->expireStale($studentId, $timetableId, 'onepay');
            $reference = $this->newReference();
            $id = $this->insertCheckout(
                $studentId,
                $timetableId,
                $recordingId,
                $lessonFeeId,
                $amount,
                $currency,
                'onepay',
                $reference,
                'initiated',
                $payerRole,
                $parentId
            );
            $this->addEvent($id, 'initiated', 'Checkout created');
            $row = $this->findById($id);
            if (!$row) {
                error_log('Payment creation failed: payment row missing after insert id ' . $id);
                throw new RuntimeException('Could not create payment.');
            }
            if ($ownTxn) {
                $this->pdo->commit();
            }
            return $row;
        } catch (Throwable $e) {
            if ($ownTxn && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * HTTPS checkout URL stored from the gateway response, if this row already has one.
     *
     * @param array<string,mixed> $txn
     */
    public function storedCheckoutUrl(array $txn): string
    {
        $raw = json_decode((string)($txn['gateway_response'] ?? ''), true);
        if (!is_array($raw)) {
            return '';
        }
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $gateway = is_array($data['gateway'] ?? null) ? $data['gateway'] : (is_array($raw['gateway'] ?? null) ? $raw['gateway'] : []);
        $candidates = [
            $gateway['redirect_url'] ?? null,
            $data['redirect_url'] ?? null,
            $raw['redirect_url'] ?? null,
        ];
        foreach ($candidates as $url) {
            $url = trim((string)$url);
            if (str_starts_with($url, 'https://')) {
                return $url;
            }
        }
        return '';
    }

    /**
     * A recent unpaid OnePay row for this lesson. Rows that never reached the gateway
     * are retried. Rows that already have a checkout URL are reused as-is.
     *
     * @return array<string,mixed>|null
     */
    private function reusableOnePayCheckout(int $studentId, int $timetableId, float $amount, string $currency): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND gateway = 'onepay'
              AND status IN ('initiated','pending')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$studentId, $timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (strtoupper((string)($row['currency'] ?? '')) !== strtoupper(trim($currency))) {
            return null;
        }
        if (abs((float)$row['amount'] - $amount) > 0.009) {
            return null;
        }
        $gatewayId = trim((string)($row['gateway_transaction_id'] ?? ''));
        if ($gatewayId === '') {
            return $row;
        }
        return $this->storedCheckoutUrl($row) !== '' ? $row : null;
    }

    private function expireOtherOnePay(int $studentId, int $timetableId, int $keepId): void
    {
        if ($keepId < 1) {
            return;
        }
        $stmt = $this->pdo->prepare("
            SELECT id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND gateway = 'onepay'
              AND status IN ('initiated','pending') AND id <> ?
        ");
        $stmt->execute([$studentId, $timetableId, $keepId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $oldId) {
            $oldId = (int)$oldId;
            if ($oldId < 1) {
                continue;
            }
            $this->pdo->prepare("
                UPDATE payment_transactions SET status = 'expired'
                WHERE id = ? AND status IN ('initiated','pending')
            ")->execute([$oldId]);
            $this->addEvent($oldId, 'expired', 'Replaced by the open checkout');
        }
    }

    public function attachGatewayId(int $id, string $gatewayId, array $rawResponse): void
    {
        $safe = $this->safeResponse($rawResponse);
        try {
            $this->pdo->prepare("
                UPDATE payment_transactions
                SET gateway_transaction_id = ?, status = 'pending', gateway_response = ?
                WHERE id = ? AND status IN ('initiated','pending')
            ")->execute([$gatewayId !== '' ? $gatewayId : null, $safe, $id]);
        } catch (Throwable $e) {
            throw new RuntimeException('Could not store the payment reference.');
        }
        $this->addEvent($id, 'pending', 'Sent to OnePay');
    }

    /**
     * Apply a verified OnePay status. Idempotent for already-paid rows.
     *
     * @param array<string,mixed> $txn
     * @param array{paid:bool,amount:?float,currency:?string,paid_on:?string,ipg_transaction_id:?string,raw:array} $verified
     * @return array<string,mixed>
     */
    public function applyVerifiedStatus(array $txn, array $verified, StudentLessonFeeService $fees): array
    {
        $id = (int)$txn['id'];
        $current = (string)$txn['status'];
        if (PaymentVerificationService::isIdempotentPaid($current)) {
            return $this->findById($id) ?? $txn;
        }
        if (strtolower($current) === 'refunded') {
            return $this->findById($id) ?? $txn;
        }

        if (!empty($verified['paid'])) {
            if (!PaymentVerificationService::canMarkPaid($current)) {
                return $this->findById($id) ?? $txn;
            }
            if ($this->hasPaidTransaction((int)$txn['student_id'], (int)$txn['timetable_id'], $id)) {
                $this->pdo->prepare("
                    UPDATE payment_transactions
                    SET status = 'cancelled'
                    WHERE id = ? AND status IN ('initiated','pending','failed','expired','cancelled')
                ")->execute([$id]);
                $this->addEvent($id, 'cancelled', 'Lesson already paid another way');
                $dupIpg = trim((string)($verified['ipg_transaction_id'] ?? $txn['gateway_transaction_id'] ?? ''));
                if ($dupIpg !== '') {
                    try {
                        (new OnePayService($this->pdo))->refund(
                            $dupIpg,
                            'DUPLICATE',
                            'Lesson already paid'
                        );
                        $this->addEvent($id, 'refunded', 'Automatic refund of duplicate OnePay charge');
                    } catch (Throwable $e) {
                        error_log('OnePay duplicate refund failed for transaction ' . $id);
                    }
                }
                return $this->findById($id) ?? $txn;
            }
            $verifiedAmount = $verified['amount'] ?? null;
            if (!is_numeric($verifiedAmount)) {
                // Status API sometimes omits amount or nests it; this IPG row is ours.
                $verifiedAmount = (float)$txn['amount'];
            }
            if (!PaymentVerificationService::amountsMatch((float)$txn['amount'], (float)$verifiedAmount)) {
                $this->fail($id, 'Amount mismatch');
                throw new RuntimeException('Payment amount did not match the lesson fee.');
            }
            $verifiedCurrency = (string)($verified['currency'] ?? $txn['currency'] ?? '');
            if ($verifiedCurrency !== '' && !PaymentVerificationService::currenciesMatch((string)$txn['currency'], $verifiedCurrency)) {
                $this->fail($id, 'Currency mismatch');
                throw new RuntimeException('Payment currency did not match.');
            }
            $ipg = (string)($verified['ipg_transaction_id'] ?? $txn['gateway_transaction_id'] ?? '');
            $this->pdo->prepare("
                UPDATE payment_transactions
                SET status = 'paid',
                    paid_at = NOW(),
                    gateway_transaction_id = COALESCE(NULLIF(?, ''), gateway_transaction_id),
                    gateway_response = ?
                WHERE id = ? AND status IN ('initiated','pending','failed','expired','cancelled')
            ")->execute([$ipg, $this->safeResponse($verified['raw'] ?? []), $id]);
            if ($this->pdo->rowCount() < 1) {
                return $this->findById($id) ?? $txn;
            }
            $this->addEvent($id, 'paid', 'Verified with OnePay status API');
            $fees->markPaid(
                (int)$txn['student_id'],
                (int)$txn['timetable_id'],
                (float)$txn['amount'],
                $id,
                isset($txn['recording_id']) ? (int)$txn['recording_id'] : null
            );
            $this->closeCompetingCheckouts((int)$txn['student_id'], (int)$txn['timetable_id'], $id);
            $this->notifyPaid($txn);
            return $this->findById($id) ?? $txn;
        }

        if (PaymentVerificationService::canMarkPaid($current) && $current !== 'failed') {
            $this->pdo->prepare("
                UPDATE payment_transactions
                SET status = 'pending', gateway_response = ?
                WHERE id = ? AND status IN ('initiated','pending')
            ")->execute([$this->safeResponse($verified['raw'] ?? []), $id]);
        }
        return $this->findById($id) ?? $txn;
    }

    public function markCancelled(int $id, string $note = 'Cancelled'): void
    {
        $this->pdo->prepare("
            UPDATE payment_transactions SET status = 'cancelled' WHERE id = ? AND status IN ('initiated','pending')
        ")->execute([$id]);
        $this->addEvent($id, 'cancelled', $note);
    }

    public function fail(int $id, string $note): void
    {
        $this->pdo->prepare("
            UPDATE payment_transactions SET status = 'failed' WHERE id = ? AND status IN ('initiated','pending')
        ")->execute([$id]);
        $this->addEvent($id, 'failed', $note);
    }

    /**
     * Teacher/admin cash in class. Marks the lesson fee paid so the student can watch the recording.
     *
     * @param array<string,mixed> $lesson
     * @return array<string,mixed>
     */
    public function recordCashCollected(
        int $studentId,
        array $lesson,
        float $amount,
        int $collectedBy,
        ?int $recordingId,
        ?int $lessonFeeId,
        StudentLessonFeeService $fees,
        array $manual = []
    ): array {
        $timetableId = StudentLessonFeeService::timetableIdFrom($lesson);
        if ($studentId < 1 || $timetableId < 1) {
            throw new RuntimeException('Missing student or lesson.');
        }
        if ($recordingId !== null && $recordingId < 1) {
            $recordingId = null;
        }
        $manualMethod = strtolower(trim((string)($manual['method'] ?? 'cash')));
        if (!in_array($manualMethod, ['cash', 'bank', 'other', 'manual'], true)) {
            $manualMethod = 'cash';
        }
        $manualNote = trim(strip_tags((string)($manual['note'] ?? '')));
        if (strlen($manualNote) > 180) {
            $manualNote = substr($manualNote, 0, 180);
        }
        $paidAt = date('Y-m-d H:i:s');
        $paymentDate = trim((string)($manual['payment_date'] ?? ''));
        if ($paymentDate !== '') {
            if (!function_exists('manual_payment_date')) {
                require_once dirname(__DIR__, 2) . '/config/payment_controls.php';
            }
            $paymentDate = manual_payment_date($paymentDate);
            $paidAt = $paymentDate === date('Y-m-d')
                ? date('Y-m-d H:i:s')
                : $paymentDate . ' 12:00:00';
        }
        $reference = 'ECKC' . strtoupper(bin2hex(random_bytes(8)));
        $note = json_encode([
            'method' => 'cash',
            'manual_method' => $manualMethod,
            'manual_note' => $manualNote,
            'payment_date' => $paymentDate !== '' ? $paymentDate : substr($paidAt, 0, 10),
            'collected_by' => $collectedBy,
            'source' => 'teacher_class_fees',
            'class_id' => (int)($lesson['class_id'] ?? 0),
            'timetable_id' => $timetableId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hasCollectedBy = function_exists('campus_column_exists')
            && campus_column_exists($this->pdo, 'payment_transactions', 'collected_by');
        $ownTxn = !$this->pdo->inTransaction();
        if ($ownTxn) {
            $this->pdo->beginTransaction();
        }
        $id = 0;
        try {
            $this->expireStale($studentId, $timetableId, 'onepay');
            $this->cancelPendingBank($studentId, $timetableId, 'Paid in cash in class');
            $feeLock = $this->pdo->prepare(
                'SELECT id, status FROM student_lesson_fees WHERE student_id = ? AND timetable_id = ? LIMIT 1 FOR UPDATE'
            );
            $feeLock->execute([$studentId, $timetableId]);
            $feeRow = $feeLock->fetch(PDO::FETCH_ASSOC);
            if ($feeRow && in_array((string)$feeRow['status'], ['paid', 'waived'], true)) {
                $existingPaid = $this->pdo->prepare("
                    SELECT * FROM payment_transactions
                    WHERE student_id = ? AND timetable_id = ? AND gateway = 'cash' AND status = 'paid'
                    ORDER BY id DESC LIMIT 1
                ");
                $existingPaid->execute([$studentId, $timetableId]);
                $existing = $existingPaid->fetch(PDO::FETCH_ASSOC);
                if ($ownTxn) {
                    $this->pdo->commit();
                }
                if ($existing) {
                    return $existing;
                }
                throw new RuntimeException('This lesson is already marked paid.');
            }
            $dup = $this->pdo->prepare("
                SELECT * FROM payment_transactions
                WHERE student_id = ? AND timetable_id = ? AND gateway = 'cash' AND status = 'paid'
                ORDER BY id DESC LIMIT 1
                FOR UPDATE
            ");
            $dup->execute([$studentId, $timetableId]);
            $alreadyCash = $dup->fetch(PDO::FETCH_ASSOC);
            if ($alreadyCash) {
                if ($ownTxn) {
                    $this->pdo->commit();
                }
                return $alreadyCash;
            }
            if ($hasCollectedBy) {
                $this->pdo->prepare("
                    INSERT INTO payment_transactions
                        (student_id, timetable_id, recording_id, lesson_fee_id, amount, currency,
                         gateway, gateway_reference, status, paid_at, collected_by, gateway_response)
                    VALUES (?, ?, ?, ?, ?, 'LKR', 'cash', ?, 'paid', ?, ?, ?)
                ")->execute([
                    $studentId,
                    $timetableId,
                    $recordingId,
                    $lessonFeeId,
                    PaymentVerificationService::formatAmount($amount),
                    $reference,
                    $paidAt,
                    $collectedBy > 0 ? $collectedBy : null,
                    $note,
                ]);
            } else {
                $this->pdo->prepare("
                    INSERT INTO payment_transactions
                        (student_id, timetable_id, recording_id, lesson_fee_id, amount, currency,
                         gateway, gateway_reference, status, paid_at, gateway_response)
                    VALUES (?, ?, ?, ?, ?, 'LKR', 'cash', ?, 'paid', ?, ?)
                ")->execute([
                    $studentId,
                    $timetableId,
                    $recordingId,
                    $lessonFeeId,
                    PaymentVerificationService::formatAmount($amount),
                    $reference,
                    $paidAt,
                    $note,
                ]);
            }
            $id = (int)$this->pdo->lastInsertId();
            ClassSessionFeeCalculator::writePaymentBreakdown($this->pdo, $id, $timetableId);
            $this->addEvent($id, 'paid', 'Payment recorded in class (' . $manualMethod . ')');
            $fees->markPaid($studentId, $timetableId, $amount, $id, $recordingId);
            if ($ownTxn) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTxn && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $txn = $this->findById($id);
        if (!$txn) {
            throw new RuntimeException('Could not record the cash payment.');
        }
        $this->notifyPaid($txn);
        $this->notifyCashWhatsApp($studentId, $lesson, $amount);
        return $txn;
    }

    public function refundLessonPayments(int $studentId, int $timetableId, string $note = 'Unmarked'): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND status = 'paid'
        ");
        $stmt->execute([$studentId, $timetableId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $oldId) {
            $this->pdo->prepare("
                UPDATE payment_transactions SET status = 'refunded' WHERE id = ? AND status = 'paid'
            ")->execute([(int)$oldId]);
            $this->addEvent((int)$oldId, 'refunded', $note);
        }
    }

    /**
     * @param array<string,mixed> $lesson
     */
    private function notifyCashWhatsApp(int $studentId, array $lesson, float $amount): void
    {
        if (!function_exists('campus_notify_phones')) {
            return;
        }
        try {
            $contacts = function_exists('campus_student_contacts')
                ? campus_student_contacts($this->pdo, $studentId)
                : ['name' => 'Student'];
            $subject = (string)($lesson['subject_name'] ?? 'class');
            $when = !empty($lesson['date']) ? date('d M Y', strtotime((string)$lesson['date'])) : '';
            $amountStr = number_format($amount, 2);
            $line = $subject . ($when !== '' ? ' on ' . $when : '');
            campus_notify_phones(
                $this->pdo,
                $studentId,
                "💳 *Payment received*\n\nStudent: *{$contacts['name']}*\n{$line}\nAmount: *Rs {$amountStr}*\nPaid to the teacher.\nYou can join the live class and watch this class recording.\n\nThank you.",
                'LESSON_FEE_CASH'
            );
        } catch (Throwable $e) {
            // Receipt is optional.
        }
    }

    /**
     * OnePay checkouts only. Pending bank slips stay until staff verify or reject them.
     */
    public function expireStale(int $studentId, int $timetableId, string $gateway = 'onepay'): void
    {
        $gateway = strtolower(trim($gateway));
        if ($gateway === '') {
            $gateway = 'onepay';
        }
        $stmt = $this->pdo->prepare("
            SELECT id, gateway_transaction_id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND gateway = ?
              AND status IN ('initiated','pending')
        ");
        $stmt->execute([$studentId, $timetableId, $gateway]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $oldId = (int)($row['id'] ?? 0);
            if ($oldId < 1) {
                continue;
            }
            $this->pdo->prepare("UPDATE payment_transactions SET status = 'expired' WHERE id = ?")->execute([$oldId]);
            $this->addEvent($oldId, 'expired', 'Replaced by a new checkout');
        }
    }

    public function cancelPendingBank(int $studentId, int $timetableId, string $note = 'Cancelled'): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND gateway = 'bank' AND status = 'pending'
        ");
        $stmt->execute([$studentId, $timetableId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $oldId) {
            $this->pdo->prepare("UPDATE payment_transactions SET status = 'cancelled' WHERE id = ? AND status = 'pending'")
                ->execute([(int)$oldId]);
            $this->addEvent((int)$oldId, 'cancelled', $note);
        }
    }

    /**
     * @param array<string,mixed> $lesson
     * @return array<string,mixed>
     */
    public function recordBankSlip(
        int $studentId,
        array $lesson,
        float $amount,
        string $slipPath,
        string $originalName,
        ?int $recordingId,
        ?int $lessonFeeId,
        ?int $parentId,
        string $note = ''
    ): array {
        $timetableId = StudentLessonFeeService::timetableIdFrom($lesson);
        $this->expireStale($studentId, $timetableId, 'onepay');
        $this->cancelPendingBank($studentId, $timetableId, 'Replaced by a new bank slip');
        $reference = 'ECKB' . strtoupper(bin2hex(random_bytes(8)));
        $id = $this->insertCheckout(
            $studentId,
            $timetableId,
            $recordingId,
            $lessonFeeId,
            $amount,
            'LKR',
            'bank',
            $reference,
            'pending',
            $parentId !== null && $parentId > 0 ? 'parent' : 'student',
            $parentId,
            $slipPath,
            $originalName,
            $note
        );
        $this->addEvent($id, 'pending', 'Bank slip uploaded');
        $row = $this->findById($id);
        if (!$row) {
            throw new RuntimeException('Could not save the bank slip.');
        }
        return $row;
    }

    public function markBankVerified(int $id, int $staffUserId): void
    {
        $this->pdo->prepare("
            UPDATE payment_transactions
            SET status = 'paid',
                paid_at = NOW(),
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ? AND gateway = 'bank' AND status IN ('initiated','pending')
        ")->execute([$staffUserId > 0 ? $staffUserId : null, $id]);
        if ($this->pdo->rowCount() < 1) {
            $fresh = $this->findById($id);
            if (!$fresh || strtolower((string)$fresh['status']) !== 'paid') {
                throw new RuntimeException('Could not confirm that slip.');
            }
            return;
        }
        $this->addEvent($id, 'paid', 'Bank slip confirmed by staff');
        $fresh = $this->findById($id);
        if ($fresh) {
            $this->closeCompetingCheckouts((int)$fresh['student_id'], (int)$fresh['timetable_id'], $id);
        }
    }

    public function hasPaidTransaction(int $studentId, int $timetableId, int $exceptId = 0): bool
    {
        if ($studentId < 1 || $timetableId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            SELECT id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND status = 'paid' AND id <> ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId, $exceptId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function hasPendingBank(int $studentId, int $timetableId): bool
    {
        if ($studentId < 1 || $timetableId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            SELECT id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND gateway = 'bank' AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function closeCompetingCheckouts(int $studentId, int $timetableId, int $keepId): void
    {
        $this->cancelPendingBank($studentId, $timetableId, 'Paid another way');
        $stmt = $this->pdo->prepare("
            SELECT id FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND id <> ?
              AND status IN ('initiated','pending')
        ");
        $stmt->execute([$studentId, $timetableId, $keepId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $oldId) {
            $oldId = (int)$oldId;
            if ($oldId < 1) {
                continue;
            }
            $this->pdo->prepare("
                UPDATE payment_transactions SET status = 'cancelled'
                WHERE id = ? AND status IN ('initiated','pending')
            ")->execute([$oldId]);
            $this->addEvent($oldId, 'cancelled', 'Paid another way');
        }
    }

    public function rejectBankSlip(int $id, int $staffUserId, string $reason): void
    {
        $note = mb_substr(trim($reason), 0, 240);
        $this->pdo->prepare("
            UPDATE payment_transactions
            SET status = 'failed',
                verified_by = ?,
                verified_at = NOW(),
                staff_note = ?
            WHERE id = ? AND gateway = 'bank' AND status IN ('initiated','pending')
        ")->execute([$staffUserId > 0 ? $staffUserId : null, $note, $id]);
        $this->addEvent($id, 'failed', $note !== '' ? $note : 'Bank slip rejected');
    }

    /**
     * @param array<string,mixed> $txn
     */
    public function notifyBankVerified(array $txn): void
    {
        $this->notifyPaid($txn);
    }

    /**
     * @param mixed $raw
     */
    private function insertCheckout(
        int $studentId,
        int $timetableId,
        ?int $recordingId,
        ?int $lessonFeeId,
        float $amount,
        string $currency,
        string $gateway,
        string $reference,
        string $status,
        string $payerRole = 'student',
        ?int $parentId = null,
        ?string $slipPath = null,
        ?string $originalName = null,
        string $note = ''
    ): int {
        $hasPayer = function_exists('campus_column_exists')
            && campus_column_exists($this->pdo, 'payment_transactions', 'payer_role');
        $hasSlip = function_exists('campus_column_exists')
            && campus_column_exists($this->pdo, 'payment_transactions', 'slip_path');
        $meta = $note !== '' ? json_encode(['note' => mb_substr($note, 0, 240)], JSON_UNESCAPED_UNICODE) : null;

        if ($hasSlip && $hasPayer) {
            $this->pdo->prepare("
                INSERT INTO payment_transactions
                    (student_id, timetable_id, recording_id, lesson_fee_id, amount, currency,
                     gateway, gateway_reference, status, payer_role, parent_id,
                     slip_path, slip_original_name, gateway_response)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $studentId,
                $timetableId,
                $recordingId,
                $lessonFeeId,
                PaymentVerificationService::formatAmount($amount),
                $currency,
                $gateway,
                $reference,
                $status,
                $payerRole,
                $parentId !== null && $parentId > 0 ? $parentId : null,
                $slipPath,
                $originalName,
                $meta,
            ]);
            $insertedId = (int)$this->pdo->lastInsertId();
            ClassSessionFeeCalculator::writePaymentBreakdown($this->pdo, $insertedId, $timetableId);
            return $insertedId;
        }

        $this->pdo->prepare("
            INSERT INTO payment_transactions
                (student_id, timetable_id, recording_id, lesson_fee_id, amount, currency,
                 gateway, gateway_reference, status, gateway_response)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $studentId,
            $timetableId,
            $recordingId,
            $lessonFeeId,
            PaymentVerificationService::formatAmount($amount),
            $currency,
            $gateway,
            $reference,
            $status,
            $meta,
        ]);
        $insertedId = (int)$this->pdo->lastInsertId();
        ClassSessionFeeCalculator::writePaymentBreakdown($this->pdo, $insertedId, $timetableId);
        if ($hasSlip && $slipPath) {
            $this->pdo->prepare('UPDATE payment_transactions SET slip_path = ?, slip_original_name = ? WHERE id = ?')
                ->execute([$slipPath, $originalName, $insertedId]);
        }
        return $insertedId;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestForLesson(int $studentId, int $timetableId, string $gateway = 'onepay'): ?array
    {
        if ($studentId < 1 || $timetableId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ? AND gateway = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId, $gateway]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Ask OnePay whether an existing checkout for this lesson was paid, then unlock the class.
     */
    public function syncOnePayForLesson(int $studentId, int $timetableId, StudentLessonFeeService $fees): void
    {
        if ($studentId < 1 || $timetableId < 1) {
            return;
        }
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ?
              AND gateway = 'onepay'
              AND status IN ('initiated','pending','failed','expired','cancelled')
              AND gateway_transaction_id IS NOT NULL AND gateway_transaction_id != ''
            ORDER BY id DESC
            LIMIT 8
        ");
        $stmt->execute([$studentId, $timetableId]);
        $onepay = new OnePayService($this->pdo);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $txn) {
            try {
                $verified = $onepay->transactionStatus((string)$txn['gateway_transaction_id']);
                $updated = $this->applyVerifiedStatus($txn, $verified, $fees);
                if (strtolower((string)($updated['status'] ?? '')) === 'paid') {
                    return;
                }
            } catch (Throwable $e) {
                error_log('OnePay lesson sync failed for transaction ' . (int)($txn['id'] ?? 0));
            }
        }
    }

    public function addEvent(int $transactionId, string $status, ?string $note = null): void
    {
        try {
            $this->pdo->prepare('INSERT INTO payment_transaction_events (transaction_id, status, note) VALUES (?, ?, ?)')
                ->execute([$transactionId, $status, $note]);
            TeacherPayoutService::syncPayment($this->pdo, $transactionId, $status);
        } catch (Throwable $e) {
            // History must not block payment.
        }
    }

    private function newReference(): string
    {
        return 'ECKR' . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * @param mixed $raw
     */
    private function safeResponse($raw): string
    {
        if (!is_array($raw)) {
            return '';
        }
        unset($raw['hash'], $raw['app_token'], $raw['Authorization']);
        $json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? mb_substr($json, 0, 65000) : '';
    }

    /**
     * @param array<string,mixed> $txn
     */
    private function notifyPaid(array $txn): void
    {
        try {
            $lesson = $this->pdo->prepare("
                SELECT
                    s.name AS subject_name,
                    c.name AS class_name,
                    tt.date,
                    tt.teacher_id,
                    tt.substitute_teacher_id,
                    COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username, 'Student') AS student_name
                FROM timetable tt
                JOIN subjects s ON s.id = tt.subject_id
                LEFT JOIN student_classes c ON c.id = tt.class_id
                LEFT JOIN users u ON u.id = ?
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE tt.id = ?
            ");
            $lesson->execute([(int)$txn['student_id'], (int)$txn['timetable_id']]);
            $row = $lesson->fetch(PDO::FETCH_ASSOC) ?: [];
            $subject = (string)($row['subject_name'] ?? 'class');
            $className = trim((string)($row['class_name'] ?? ''));
            $studentName = trim((string)($row['student_name'] ?? 'Student'));
            $rawDate = (string)($row['date'] ?? '');
            $date = $rawDate !== '' ? date('d M Y', strtotime($rawDate)) : '';
            $amount = number_format((float)($txn['amount'] ?? 0), 2);
            $reference = trim((string)($txn['gateway_reference'] ?? $txn['gateway_transaction_id'] ?? ''));
            $gateway = strtolower((string)($txn['gateway'] ?? ''));
            $cash = $gateway === 'cash';
            $bank = $gateway === 'bank';
            $onePay = $gateway === 'onepay' || $gateway === '';
            $methodLabel = StudentLessonFeeService::gatewayLabel($onePay ? 'onepay' : $gateway);
            $title = $cash ? 'Class fee received' : 'Payment successful';
            if ($cash) {
                $body = 'Your teacher recorded your class fee for ' . $subject . ($date !== '' ? ' on ' . $date : '') . '. You can join the live class and watch the recording.';
            } elseif ($bank) {
                $body = 'The office confirmed your bank transfer for ' . $subject . ($date !== '' ? ' on ' . $date : '') . '. You can join the live class and access the class recording.';
            } else {
                $body = 'Your payment for ' . $subject . ($date !== '' ? ' on ' . $date : '') . ' was successful. You can join the live class and access the class recording.';
            }
            if (function_exists('campus_portal_notify')) {
                campus_portal_notify(
                    $this->pdo,
                    [(int)$txn['student_id']],
                    $title,
                    $body,
                    'class.php?lesson=' . (int)($txn['timetable_id'] ?? 0)
                );
            }

            // Staff dashboard: OnePay + bank confirmations (cash is marked by the teacher already).
            if ($onePay || $bank) {
                $staffLines = [
                    'Student: ' . ($studentName !== '' ? $studentName : 'Student'),
                    'Subject: ' . $subject,
                ];
                if ($className !== '') {
                    $staffLines[] = 'Class: ' . $className;
                }
                if ($date !== '') {
                    $staffLines[] = 'Lesson: ' . $date;
                }
                $staffLines[] = 'Amount: Rs ' . $amount;
                $staffLines[] = 'Method: ' . $methodLabel;
                if ($reference !== '') {
                    $staffLines[] = 'Ref: ' . $reference;
                }
                $staffMessage = implode("\n", $staffLines);
                $staffTitle = 'Student paid · ' . $methodLabel;
                $feesLink = 'campus/lesson_fees.php';
                if ($rawDate !== '') {
                    $feesLink .= '?date=' . rawurlencode($rawDate)
                        . '&lesson=' . (int)($txn['timetable_id'] ?? 0);
                } elseif ((int)($txn['timetable_id'] ?? 0) > 0) {
                    $feesLink .= '?lesson=' . (int)$txn['timetable_id'];
                }

                $teacherIds = [];
                $primaryTeacher = (int)($row['teacher_id'] ?? 0);
                $substituteTeacher = (int)($row['substitute_teacher_id'] ?? 0);
                if ($primaryTeacher > 0) {
                    $teacherIds[$primaryTeacher] = true;
                }
                if ($substituteTeacher > 0) {
                    $teacherIds[$substituteTeacher] = true;
                }
                if (function_exists('campus_insert_teacher_notification')) {
                    foreach (array_keys($teacherIds) as $teacherId) {
                        campus_insert_teacher_notification(
                            $this->pdo,
                            (int)$teacherId,
                            $staffTitle,
                            $staffMessage,
                            $feesLink
                        );
                    }
                }
                if (function_exists('campus_notify_admins')) {
                    campus_notify_admins(
                        $this->pdo,
                        $staffTitle,
                        $staffMessage,
                        $feesLink
                    );
                }
            }

            try {
                (new CommunicationEventService($this->pdo))->paymentReceived(
                    (int)$txn['student_id'],
                    (float)($txn['amount'] ?? 0),
                    [
                        'payment_id' => (int)($txn['id'] ?? 0),
                        'reference' => (string)($txn['gateway_reference'] ?? $txn['gateway_transaction_id'] ?? ''),
                        'subject' => $subject,
                    ]
                );
            } catch (Throwable $e) {
            }
        } catch (Throwable $e) {
            // Notification is optional.
        }
        try {
            $gateway = strtolower((string)($txn['gateway'] ?? 'onepay'));
            (new AdmissionLifecycleService($this->pdo))->syncFromExistingPayment(
                (int)($txn['student_id'] ?? 0),
                (int)($txn['id'] ?? 0),
                (float)($txn['amount'] ?? 0),
                $gateway,
                (string)($txn['gateway_reference'] ?? $txn['gateway_transaction_id'] ?? '')
            );
        } catch (Throwable $e) {
            // Admission status sync must never block a verified payment.
        }
    }
}
