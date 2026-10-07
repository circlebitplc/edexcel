<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;

/**
 * Per-lesson student fee for live class join and recording access.
 *
 * Monthly student_fee_ledger remains the campus wallet (present/late only, paid at the counter).
 * Live class and recordings use this table. A monthly PAID/WAIVED row covers a lesson only when
 * that student was present or late (so the lesson was included in the monthly bill).
 * Absent students are not billed on the monthly ledger, so they pay this lesson fee (or a waiver)
 * before joining live or watching.
 */
final class StudentLessonFeeService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
        ClassSessionFeeCalculator::ensureSchema($this->pdo);
    }

    public static function isCoveredByMonthlyLedger(?string $attendance, string $monthlyStatus): bool
    {
        $att = strtolower(trim((string)$attendance));
        $month = strtolower(trim($monthlyStatus));
        return in_array($att, ['present', 'late'], true)
            && in_array($month, ['paid', 'waived'], true);
    }

    public static function isUnlocked(string $status, bool $coveredByMonthly = false): bool
    {
        if ($coveredByMonthly) {
            return true;
        }
        return in_array(strtolower(trim($status)), ['paid', 'waived'], true);
    }

    public static function paywallMessage(): string
    {
        return 'Only students who have paid for the lesson can join the live class or access the class recording. If you have not made the payment yet, please click the “Pay Now” button to complete your payment and get access.';
    }

    public static function pendingPaywallMessage(): string
    {
        return 'Your payment is still being confirmed. Please wait a moment.';
    }

    public static function teacherMayCollect(string $status, bool $coveredByMonthly = false): bool
    {
        return !self::isUnlocked($status, $coveredByMonthly);
    }

    public static function teacherMayUnpay(string $status, bool $coveredByMonthly = false, float $amountDue = 1.0): bool
    {
        if ($amountDue <= 0) {
            return false;
        }
        return self::isUnlocked($status, $coveredByMonthly);
    }

    /**
     * Lesson (timetable) id from a timetable row or a class_recordings join.
     * Recording rows have id = recording id and timetable_id = lesson id.
     */
    public static function timetableIdFrom(array $row): int
    {
        $lessonId = (int)($row['timetable_id'] ?? 0);
        if ($lessonId > 0) {
            return $lessonId;
        }
        return (int)($row['id'] ?? 0);
    }

    /**
     * Teacher or admin records cash received in class. That unlocks the lesson recording.
     *
     * @param array<string,mixed> $lesson
     * @return array{marked:bool,already:bool,status:string,transaction_id:?int}
     */
    public function collectCash(int $studentId, array $lesson, int $collectedBy, ?int $recordingId = null, array $manual = []): array
    {
        $this->assertTeacherManualPayment($lesson);
        if ($studentId < 1 || self::timetableIdFrom($lesson) < 1) {
            throw new \RuntimeException('Missing student or lesson.');
        }
        if ($recordingId !== null && $recordingId < 1) {
            $recordingId = null;
        }
        if (!$this->isEnrolled($studentId, $lesson)) {
            throw new \RuntimeException('That student is not in this class.');
        }

        $resolved = $this->resolve($studentId, $lesson, $recordingId);
        if (self::isUnlocked((string)$resolved['status'], (bool)$resolved['covered_by_monthly'])) {
            $existingId = isset($resolved['row']['payment_id']) ? (int)$resolved['row']['payment_id'] : 0;
            return [
                'marked' => false,
                'already' => true,
                'status' => (string)$resolved['status'] === 'waived' ? 'waived' : 'paid',
                'transaction_id' => $existingId > 0 ? $existingId : null,
            ];
        }

        $row = $this->ensureRow($studentId, $lesson, $recordingId);
        $amount = (float)$row['amount_due'];
        if ($amount <= 0) {
            return [
                'marked' => false,
                'already' => true,
                'status' => 'waived',
                'transaction_id' => null,
            ];
        }

        $payments = new PaymentTransactionService($this->pdo);
        $txn = $payments->recordCashCollected(
            $studentId,
            $lesson,
            $amount,
            $collectedBy,
            $recordingId,
            isset($row['id']) ? (int)$row['id'] : null,
            $this,
            $manual
        );

        return [
            'marked' => true,
            'already' => false,
            'status' => 'paid',
            'transaction_id' => (int)($txn['id'] ?? 0),
        ];
    }

    /**
     * Teacher or admin reverses a lesson payment and locks the recording.
     *
     * @param array<string,mixed> $lesson
     * @return array{unmarked:bool,already:bool,status:string}
     */
    public function unpay(int $studentId, array $lesson, int $byUserId, bool $refundOnePay = false): array
    {
        $this->assertTeacherManualPayment($lesson);
        if ($studentId < 1 || self::timetableIdFrom($lesson) < 1) {
            throw new \RuntimeException('Missing student or lesson.');
        }
        if (!$this->isEnrolled($studentId, $lesson)) {
            throw new \RuntimeException('That student is not in this class.');
        }

        $timetableId = self::timetableIdFrom($lesson);
        $row = $this->ensureRow($studentId, $lesson);
        $resolved = $this->resolve($studentId, $lesson);
        $row = $resolved['row'] ?? $row;
        $due = (float)($row['amount_due'] ?? $resolved['amount_due'] ?? 0);
        if (!self::teacherMayUnpay((string)$resolved['status'], (bool)$resolved['covered_by_monthly'], $due)) {
            return [
                'unmarked' => false,
                'already' => true,
                'status' => 'unpaid',
            ];
        }
        $onepay = $this->paidOnePayTransaction($studentId, $timetableId);
        if ($onepay && !$refundOnePay) {
            throw new \RuntimeException(
                'This lesson was paid on OnePay. Ask the office to refund the card payment. Class fees cannot reverse a card charge.'
            );
        }
        $pendingRefundIpg = '';
        if ($onepay && $refundOnePay) {
            $pendingRefundIpg = trim((string)($onepay['gateway_transaction_id'] ?? ''));
            if ($pendingRefundIpg === '') {
                throw new \RuntimeException('OnePay payment has no transaction id to refund. Contact the office.');
            }
            try {
                (new OnePayService($this->pdo))->refund(
                    $pendingRefundIpg,
                    'REQUESTED_BY_CUSTOMER',
                    'Class fee unmarked by user ' . $byUserId
                );
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'The OnePay refund failed. The lesson is still marked paid. ' . $e->getMessage()
                );
            }
        }
        $monthlyCredit = (float)($row['monthly_credit'] ?? 0);
        $payments = new PaymentTransactionService($this->pdo);
        $ownTxn = !$this->pdo->inTransaction();
        if ($ownTxn) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->pdo->prepare("
                UPDATE student_lesson_fees
                SET amount_paid = 0,
                    status = 'pending',
                    payment_id = NULL,
                    paid_at = NULL,
                    waived = 0,
                    waived_by = NULL,
                    waived_at = NULL
                WHERE student_id = ? AND timetable_id = ?
            ")->execute([$studentId, $timetableId]);
            if ($this->hasMonthlyCreditColumn()) {
                $this->pdo->prepare("
                    UPDATE student_lesson_fees SET monthly_credit = 0 WHERE student_id = ? AND timetable_id = ?
                ")->execute([$studentId, $timetableId]);
            }
            $this->setForceUnpaid($studentId, $timetableId, true);
            $payments->refundLessonPayments($studentId, $timetableId, 'Unmarked on Class fees by user ' . $byUserId);
            if ($monthlyCredit > 0.009) {
                $this->debitMonthlyLedger($studentId, $timetableId, $monthlyCredit);
            }
            if ($ownTxn) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTxn && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->kickFromLiveClass($studentId, $timetableId, $byUserId);

        $this->notifyUnpaid($studentId, $lesson);

        return [
            'unmarked' => true,
            'already' => false,
            'status' => 'unpaid',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function lessonAmount(array $lesson): float
    {
        if (function_exists('campus_teacher_charge_per_student')) {
            return round(campus_teacher_charge_per_student($lesson), 2);
        }
        return round((float)($lesson['class_fee_per_student'] ?? 0), 2);
    }

    /**
     * @return array<string,mixed>
     */
    public function ensureRow(int $studentId, array $lesson, ?int $recordingId = null): array
    {
        $timetableId = self::timetableIdFrom($lesson);
        $amount = $this->lessonAmount($lesson);
        $stmt = $this->pdo->prepare('SELECT * FROM student_lesson_fees WHERE student_id = ? AND timetable_id = ? LIMIT 1');
        $stmt->execute([$studentId, $timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($recordingId && empty($row['recording_id'])) {
                $this->pdo->prepare('UPDATE student_lesson_fees SET recording_id = ? WHERE id = ?')
                    ->execute([$recordingId, $row['id']]);
                $row['recording_id'] = $recordingId;
            }
            $status = strtolower((string)($row['status'] ?? ''));
            if (!in_array($status, ['paid', 'waived'], true) && !$this->hasOpenGatewayPayment($studentId, $timetableId)) {
                $needsAmount = abs((float)$row['amount_due'] - $amount) > 0.009;
                if ($needsAmount) {
                    $this->pdo->prepare('UPDATE student_lesson_fees SET amount_due = ? WHERE id = ?')
                        ->execute([$amount, $row['id']]);
                    $row['amount_due'] = $amount;
                }
                $breakdown = ClassSessionFeeCalculator::lessonFeeColumns($lesson);
                $missingBreakdown = $breakdown !== null && !isset($row['gross_class_fee']);
                if ($breakdown !== null && ($needsAmount || $missingBreakdown || (string)($row['gross_class_fee'] ?? '') !== $breakdown['gross_class_fee'])) {
                    ClassSessionFeeCalculator::writeLessonFeeBreakdown($this->pdo, (int)$row['id'], $lesson);
                    $row = array_merge($row, $breakdown);
                }
            }
            return $row;
        }
        $status = $amount <= 0 ? 'waived' : 'pending';
        $breakdown = ClassSessionFeeCalculator::lessonFeeColumns($lesson);
        if ($breakdown !== null && ClassSessionFeeCalculator::tableHas($this->pdo, 'student_lesson_fees', 'gross_class_fee')) {
            $this->pdo->prepare("
                INSERT INTO student_lesson_fees
                    (student_id, timetable_id, recording_id, amount_due, amount_paid, currency, status, waived, waived_at,
                     gross_class_fee, institute_online_fee, transaction_handling_fee, teacher_net_amount)
                VALUES (?, ?, ?, ?, ?, 'LKR', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $studentId,
                $timetableId,
                $recordingId,
                $amount,
                0,
                $status,
                $amount <= 0 ? 1 : 0,
                $amount <= 0 ? date('Y-m-d H:i:s') : null,
                $breakdown['gross_class_fee'],
                $breakdown['institute_online_fee'],
                $breakdown['transaction_handling_fee'],
                $breakdown['teacher_net_amount'],
            ]);
        } else {
            $this->pdo->prepare("
                INSERT INTO student_lesson_fees
                    (student_id, timetable_id, recording_id, amount_due, amount_paid, currency, status, waived, waived_at)
                VALUES (?, ?, ?, ?, ?, 'LKR', ?, ?, ?)
            ")->execute([
                $studentId,
                $timetableId,
                $recordingId,
                $amount,
                0,
                $status,
                $amount <= 0 ? 1 : 0,
                $amount <= 0 ? date('Y-m-d H:i:s') : null,
            ]);
        }
        $stmt->execute([$studentId, $timetableId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function attendanceStatus(int $studentId, int $timetableId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM student_attendance WHERE student_id = ? AND timetable_id = ? LIMIT 1');
        $stmt->execute([$studentId, $timetableId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (string)$value;
    }

    public function monthlyStatus(int $studentId, int $classId, string $periodYm): string
    {
        $stmt = $this->pdo->prepare("
            SELECT status FROM student_fee_ledger
            WHERE student_id = ? AND class_id = ? AND period_ym = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $classId, $periodYm]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? '' : (string)$value;
    }

    /**
     * @return array{status:string,amount_due:float,amount_paid:float,row:?array,covered_by_monthly:bool}
     */
    public function resolve(int $studentId, array $lesson, ?int $recordingId = null): array
    {
        $row = $this->ensureRow($studentId, $lesson, $recordingId);
        $due = (float)$row['amount_due'];
        $paid = (float)$row['amount_paid'];
        $status = (string)$row['status'];
        $forcedUnpaid = $this->isForceUnpaid($row);
        if (in_array($status, ['paid', 'waived'], true) || $due <= 0) {
            return [
                'status' => $status === 'pending' && $due <= 0 ? 'waived' : $status,
                'amount_due' => $due,
                'amount_paid' => $paid,
                'row' => $row,
                'covered_by_monthly' => false,
            ];
        }

        $timetableId = self::timetableIdFrom($lesson);
        $attendance = $this->attendanceStatus($studentId, $timetableId);
        $period = date('Y-m', strtotime((string)$lesson['date']));
        $monthly = $this->monthlyStatus($studentId, (int)$lesson['class_id'], $period);
        if (!$forcedUnpaid && self::isCoveredByMonthlyLedger($attendance, $monthly)) {
            return [
                'status' => 'paid',
                'amount_due' => $due,
                'amount_paid' => $due,
                'row' => $row,
                'covered_by_monthly' => true,
            ];
        }

        $paidTxn = $this->paidLessonTransaction($studentId, $timetableId);
        if ($paidTxn) {
            try {
                $this->markPaid(
                    $studentId,
                    $timetableId,
                    (float)($paidTxn['amount'] ?? $due),
                    (int)$paidTxn['id'],
                    $recordingId
                );
                $row = $this->ensureRow($studentId, $lesson, $recordingId);
                $due = (float)$row['amount_due'];
                $paid = (float)$row['amount_paid'];
            } catch (\Throwable $e) {
                $paid = $due;
            }
            return [
                'status' => 'paid',
                'amount_due' => $due,
                'amount_paid' => $paid > 0 ? $paid : $due,
                'row' => $row,
                'covered_by_monthly' => false,
            ];
        }

        $pendingPay = $this->hasOpenGatewayPayment($studentId, $timetableId);
        if ($pendingPay) {
            return [
                'status' => 'pending',
                'amount_due' => $due,
                'amount_paid' => $paid,
                'row' => $row,
                'covered_by_monthly' => false,
            ];
        }

        return [
            'status' => $status === 'partial' ? 'partial' : 'unpaid',
            'amount_due' => $due,
            'amount_paid' => $paid,
            'row' => $row,
            'covered_by_monthly' => false,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function paidLessonTransaction(int $studentId, int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ?
              AND status = 'paid'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Staff-facing label for how the class fee was paid.
     */
    public static function gatewayLabel(?string $gateway): string
    {
        return match (strtolower(trim((string)$gateway))) {
            'onepay' => 'Online payment',
            'bank' => 'Bank',
            'cash' => 'On hand',
            'manual' => 'Teacher Manual Payment',
            'other' => 'Other',
            default => 'Paid',
        };
    }

    /**
     * Cash rows can store the method the teacher entered (cash, bank, or other).
     *
     * @param array<string,mixed> $row
     */
    public static function displayMethod(array $row): string
    {
        $gateway = strtolower(trim((string)($row['gateway'] ?? '')));
        if ($gateway === 'cash') {
            $decoded = json_decode((string)($row['gateway_response'] ?? ''), true);
            $manual = is_array($decoded) ? strtolower(trim((string)($decoded['manual_method'] ?? ''))) : '';
            if (in_array($manual, ['bank', 'other', 'manual'], true)) {
                return $manual;
            }
        }
        return $gateway;
    }

    /**
     * Teachers cannot add or reverse an online-class payment while the admin switch is off.
     * In-college classes and administrators are unchanged.
     *
     * @param array<string,mixed> $lesson
     */
    private function assertTeacherManualPayment(array $lesson): void
    {
        if (function_exists('is_admin') && is_admin()) {
            return;
        }
        if (!function_exists('teacher_manual_payment_allowed')) {
            require_once dirname(__DIR__, 2) . '/config/payment_controls.php';
        }
        if (!teacher_manual_payment_allowed(teacher_manual_payment_enabled($this->pdo), false, $lesson)) {
            throw new \RuntimeException('Manual teacher payments are currently disabled by the administrator.');
        }
    }

    /**
     * Latest paid gateway per student for one lesson.
     *
     * @return array<int,string> student_id => gateway
     */
    public function paidGatewaysForLesson(int $timetableId): array
    {
        if ($timetableId < 1) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT student_id, gateway, gateway_response
                FROM payment_transactions
                WHERE timetable_id = ? AND status = 'paid'
                ORDER BY id DESC
            ");
            $stmt->execute([$timetableId]);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $sid = (int)($row['student_id'] ?? 0);
                if ($sid < 1 || isset($out[$sid])) {
                    continue;
                }
                $out[$sid] = self::displayMethod($row);
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function paidOnePayTransaction(int $studentId, int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ?
              AND gateway = 'onepay' AND status = 'paid'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function hasOpenGatewayPayment(int $studentId, int $timetableId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT gateway, created_at FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ?
              AND status IN ('initiated','pending')
        ");
        $stmt->execute([$studentId, $timetableId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $gw = strtolower((string)($row['gateway'] ?? ''));
            if ($gw === 'bank') {
                return true;
            }
            $created = strtotime((string)($row['created_at'] ?? ''));
            if ($created !== false && $created >= time() - 7200) {
                return true;
            }
        }
        return false;
    }

    /**
     * Display statuses for a student's lessons without inserting fee rows.
     *
     * @param list<array<string,mixed>> $lessons
     * @return array<int,array{status:string,amount_due:float,amount_paid:float,covered_by_monthly:bool,needs_pay:bool}>
     */
    public function mapForStudentLessons(int $studentId, array $lessons): array
    {
        $ids = [];
        $byId = [];
        foreach ($lessons as $lesson) {
            $id = self::timetableIdFrom($lesson);
            if ($id < 1) {
                continue;
            }
            $ids[] = $id;
            $byId[$id] = $lesson;
        }
        $ids = array_values(array_unique($ids));
        if ($ids === [] || $studentId < 1) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $feeRows = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM student_lesson_fees
                WHERE student_id = ? AND timetable_id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$studentId], $ids));
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $feeRows[(int)$row['timetable_id']] = $row;
            }
        } catch (\Throwable $e) {
            $feeRows = [];
        }

        $pending = [];
        $paidLessons = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT timetable_id, gateway, created_at, status
                FROM payment_transactions
                WHERE student_id = ? AND timetable_id IN ($placeholders)
                  AND status IN ('initiated','pending','paid')
            ");
            $stmt->execute(array_merge([$studentId], $ids));
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $tid = (int)$row['timetable_id'];
                if (strtolower((string)($row['status'] ?? '')) === 'paid') {
                    $paidLessons[$tid] = true;
                    continue;
                }
                $gw = strtolower((string)($row['gateway'] ?? ''));
                $open = $gw === 'bank';
                if (!$open) {
                    $created = strtotime((string)($row['created_at'] ?? ''));
                    $open = $created !== false && $created >= time() - 7200;
                }
                if ($open) {
                    $pending[$tid] = $gw === 'bank' ? 'bank' : 'onepay';
                }
            }
        } catch (\Throwable $e) {
            $pending = [];
            $paidLessons = [];
        }

        $out = [];
        foreach ($ids as $id) {
            $lesson = $byId[$id];
            $amount = $this->lessonAmount($lesson);
            $row = $feeRows[$id] ?? null;
            $status = 'unpaid';
            $paid = 0.0;
            $covered = false;
            if ($row) {
                $status = strtolower((string)($row['status'] ?? 'pending'));
                $paid = (float)($row['amount_paid'] ?? 0);
                $amount = (float)($row['amount_due'] ?? $amount);
                $forced = $this->isForceUnpaid($row);
                if (!in_array($status, ['paid', 'waived'], true) && $amount > 0) {
                    $att = $this->attendanceStatus($studentId, $id);
                    $period = date('Y-m', strtotime((string)($lesson['date'] ?? '')));
                    $monthly = $this->monthlyStatus($studentId, (int)($lesson['class_id'] ?? 0), $period);
                    if (!$forced && self::isCoveredByMonthlyLedger($att, $monthly)) {
                        $status = 'paid';
                        $covered = true;
                    }
                }
            } elseif ($amount <= 0) {
                $status = 'waived';
            }
            if ($amount <= 0) {
                $status = 'waived';
            } elseif (in_array($status, ['paid', 'waived'], true) || $covered || isset($paidLessons[$id])) {
                $status = $covered ? 'paid' : (isset($paidLessons[$id]) ? 'paid' : $status);
                if (isset($paidLessons[$id])) {
                    $paid = max($paid, $amount);
                }
            } elseif (isset($pending[$id])) {
                $status = 'pending';
            } elseif ($status === 'partial' || $paid > 0.009) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }
            $unlocked = self::isUnlocked($status, $covered);
            $cancelled = strtolower((string)($lesson['lesson_status'] ?? 'scheduled')) === 'cancelled';
            $out[$id] = [
                'status' => $unlocked ? ($covered ? 'paid' : $status) : $status,
                'amount_due' => $amount,
                'amount_paid' => $paid,
                'covered_by_monthly' => $covered,
                'needs_pay' => !$unlocked && !$cancelled && $amount > 0,
                'pending_gateway' => $pending[$id] ?? '',
            ];
        }
        return $out;
    }

    public function markPaid(int $studentId, int $timetableId, float $amount, int $paymentId, ?int $recordingId = null): void
    {
        $now = date('Y-m-d H:i:s');
        $charged = round(max(0, $amount), 2);
        $this->pdo->prepare("
            UPDATE student_lesson_fees
            SET amount_paid = ?,
                amount_due = ?,
                status = 'paid',
                payment_id = ?,
                paid_at = ?,
                recording_id = COALESCE(recording_id, ?)
            WHERE student_id = ? AND timetable_id = ?
        ")->execute([$charged, $charged, $paymentId, $now, $recordingId, $studentId, $timetableId]);
        $this->setForceUnpaid($studentId, $timetableId, false);

        $credited = $this->creditMonthlyLedgerIfAttended($studentId, $timetableId, $amount);
        $this->storeMonthlyCredit($studentId, $timetableId, $credited);
    }

    public function waive(int $studentId, int $timetableId, int $adminUserId): void
    {
        $this->pdo->prepare("
            UPDATE student_lesson_fees
            SET status = 'waived', waived = 1, waived_by = ?, waived_at = NOW()
            WHERE student_id = ? AND timetable_id = ?
        ")->execute([$adminUserId, $studentId, $timetableId]);
    }

    /**
     * @return float Amount actually credited onto the monthly wallet
     */
    private function creditMonthlyLedgerIfAttended(int $studentId, int $timetableId, float $amount): float
    {
        $att = $this->attendanceStatus($studentId, $timetableId);
        if (!in_array(strtolower((string)$att), ['present', 'late'], true)) {
            return 0.0;
        }
        $lesson = $this->pdo->prepare('SELECT class_id, date FROM timetable WHERE id = ? LIMIT 1');
        $lesson->execute([$timetableId]);
        $row = $lesson->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }
        $period = date('Y-m', strtotime((string)$row['date']));
        $ledger = $this->pdo->prepare("
            SELECT id, amount_due, amount_paid, status
            FROM student_fee_ledger
            WHERE student_id = ? AND class_id = ? AND period_ym = ?
            LIMIT 1
        ");
        $ledger->execute([$studentId, (int)$row['class_id'], $period]);
        $fee = $ledger->fetch(PDO::FETCH_ASSOC);
        if (!$fee || in_array((string)$fee['status'], ['paid', 'waived'], true)) {
            return 0.0;
        }
        $oldPaid = (float)$fee['amount_paid'];
        $newPaid = min((float)$fee['amount_due'], $oldPaid + $amount);
        $status = $newPaid + 0.009 >= (float)$fee['amount_due'] ? 'paid' : 'partial';
        $this->pdo->prepare("
            UPDATE student_fee_ledger
            SET amount_paid = ?, status = ?, paid_at = IF(? = 'paid', NOW(), paid_at)
            WHERE id = ?
        ")->execute([$newPaid, $status, $status, $fee['id']]);
        return round(max(0, $newPaid - $oldPaid), 2);
    }

    private function debitMonthlyLedger(int $studentId, int $timetableId, float $amount): void
    {
        $lesson = $this->pdo->prepare('SELECT class_id, date FROM timetable WHERE id = ? LIMIT 1');
        $lesson->execute([$timetableId]);
        $row = $lesson->fetch(PDO::FETCH_ASSOC);
        if (!$row || $amount <= 0) {
            return;
        }
        $period = date('Y-m', strtotime((string)$row['date']));
        $ledger = $this->pdo->prepare("
            SELECT id, amount_due, amount_paid, status
            FROM student_fee_ledger
            WHERE student_id = ? AND class_id = ? AND period_ym = ?
            LIMIT 1
        ");
        $ledger->execute([$studentId, (int)$row['class_id'], $period]);
        $fee = $ledger->fetch(PDO::FETCH_ASSOC);
        if (!$fee || (string)$fee['status'] === 'waived') {
            return;
        }
        $newPaid = max(0, (float)$fee['amount_paid'] - $amount);
        $due = (float)$fee['amount_due'];
        if ($newPaid <= 0.009) {
            $status = 'due';
            $newPaid = 0.0;
        } elseif ($newPaid + 0.009 >= $due) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }
        $this->pdo->prepare("
            UPDATE student_fee_ledger
            SET amount_paid = ?, status = ?, paid_at = IF(? = 'paid', paid_at, IF(? = 'due', NULL, paid_at))
            WHERE id = ?
        ")->execute([$newPaid, $status, $status, $status, $fee['id']]);
    }

    private function isForceUnpaid(array $row): bool
    {
        return !empty($row['force_unpaid']);
    }

    private function setForceUnpaid(int $studentId, int $timetableId, bool $locked): void
    {
        if (!function_exists('campus_column_exists') || !campus_column_exists($this->pdo, 'student_lesson_fees', 'force_unpaid')) {
            return;
        }
        $this->pdo->prepare("
            UPDATE student_lesson_fees SET force_unpaid = ? WHERE student_id = ? AND timetable_id = ?
        ")->execute([$locked ? 1 : 0, $studentId, $timetableId]);
    }

    private function hasMonthlyCreditColumn(): bool
    {
        return function_exists('campus_column_exists')
            && campus_column_exists($this->pdo, 'student_lesson_fees', 'monthly_credit');
    }

    private function storeMonthlyCredit(int $studentId, int $timetableId, float $amount): void
    {
        if (!$this->hasMonthlyCreditColumn()) {
            return;
        }
        $this->pdo->prepare("
            UPDATE student_lesson_fees SET monthly_credit = ? WHERE student_id = ? AND timetable_id = ?
        ")->execute([round(max(0, $amount), 2), $studentId, $timetableId]);
    }

    /**
     * @param array<string,mixed> $lesson
     */
    private function notifyUnpaid(int $studentId, array $lesson): void
    {
        if (!function_exists('campus_portal_notify')) {
            return;
        }
        try {
            $subject = (string)($lesson['subject_name'] ?? 'class');
            $when = !empty($lesson['date']) ? date('d M Y', strtotime((string)$lesson['date'])) : '';
            campus_portal_notify(
                $this->pdo,
                [$studentId],
                'Class fee unmarked',
                'Your teacher unmarked the class fee for ' . $subject . ($when !== '' ? ' on ' . $when : '') . '. The live class and recording are locked until it is paid again.',
                'dashboard.php?tab=recordings'
            );
        } catch (\Throwable $e) {
            // Notification is optional.
        }
    }

    public function syncMonthlyCreditAfterAttendance(int $studentId, int $timetableId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, amount_paid, amount_due, monthly_credit FROM student_lesson_fees WHERE student_id = ? AND timetable_id = ? LIMIT 1'
        );
        $stmt->execute([$studentId, $timetableId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || strtolower((string)$row['status']) !== 'paid') {
            return;
        }
        if ((float)($row['monthly_credit'] ?? 0) > 0.009) {
            return;
        }
        $amount = (float)$row['amount_paid'] > 0 ? (float)$row['amount_paid'] : (float)$row['amount_due'];
        $credited = $this->creditMonthlyLedgerIfAttended($studentId, $timetableId, $amount);
        $this->storeMonthlyCredit($studentId, $timetableId, $credited);
    }

    private function kickFromLiveClass(int $studentId, int $timetableId, int $byUserId): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM online_meetings WHERE timetable_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$timetableId]);
            $meetingId = (int)$stmt->fetchColumn();
            if ($meetingId < 1) {
                return;
            }
            (new OnlineMeetingService($this->pdo))->kick($meetingId, $studentId, $byUserId);
        } catch (\Throwable $e) {
            error_log('unpay live kick: ' . $e->getMessage());
        }
    }

    public function isEnrolled(int $studentId, array $lesson): bool
    {
        $classId = (int)($lesson['class_id'] ?? 0);
        $teacherId = (int)($lesson['teacher_id'] ?? $lesson['lesson_teacher_id'] ?? 0);
        if ($studentId < 1 || $classId < 1) {
            return false;
        }
        $hasTeacher = function_exists('campus_column_exists')
            && campus_column_exists($this->pdo, 'student_enrollments', 'teacher_id');
        if ($hasTeacher) {
            $stmt = $this->pdo->prepare("
                SELECT teacher_id FROM student_enrollments
                WHERE student_id = ? AND class_id = ?
                LIMIT 1
            ");
            $stmt->execute([$studentId, $classId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            $want = (int)($row['teacher_id'] ?? 0);
            return $want < 1 || $want === $teacherId;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
        $stmt->execute([$studentId, $classId]);
        return (bool)$stmt->fetchColumn();
    }
}
