<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Teacher settlement for online class payments.
 * A student payment does not mark the teacher as paid.
 */
final class TeacherPayoutService
{
    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done || $pdo->inTransaction()) {
            return;
        }
        $done = true;
        $statements = [
            "CREATE TABLE IF NOT EXISTS teacher_payouts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                teacher_id INT NOT NULL,
                bank_account_id INT UNSIGNED NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payout_date DATE NULL,
                payment_method VARCHAR(40) NOT NULL DEFAULT 'bank',
                payout_reference VARCHAR(80) NULL,
                processed_by INT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_teacher_payouts_teacher (teacher_id, status, created_at),
                INDEX idx_teacher_payouts_reference (payout_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS teacher_payout_items (
                payout_id INT UNSIGNED NOT NULL,
                payment_transaction_id BIGINT UNSIGNED NOT NULL,
                teacher_net_amount DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (payment_transaction_id),
                INDEX idx_payout_items_payout (payout_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS payment_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id INT NULL,
                action VARCHAR(60) NOT NULL,
                entity_type VARCHAR(40) NOT NULL,
                entity_id VARCHAR(40) NOT NULL,
                details TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_payment_audit_entity (entity_type, entity_id),
                INDEX idx_payment_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($statements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\Throwable $e) {
                error_log('teacher payout schema: ' . $e->getMessage());
            }
        }
        try {
            $pdo->exec('ALTER TABLE payment_transactions ADD INDEX idx_pt_payout (payout_status, teacher_id, created_at)');
        } catch (\Throwable $e) {
            // Index already exists, or this host rejects a second identical index.
        }
    }

    /**
     * @param array<string,mixed> $details
     */
    public static function audit(PDO $pdo, int $actorUserId, string $action, string $entityType, string $entityId, array $details = []): void
    {
        self::ensureSchema($pdo);
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        try {
            $pdo->prepare(
                'INSERT INTO payment_audit_log (actor_user_id, action, entity_type, entity_id, details)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $actorUserId > 0 ? $actorUserId : null,
                substr($action, 0, 60),
                substr($entityType, 0, 40),
                substr($entityId, 0, 40),
                is_string($json) ? $json : null,
            ]);
        } catch (\Throwable $e) {
            error_log('payment audit: ' . $e->getMessage());
        }
    }

    /**
     * Student payment status and teacher payout status stay separate.
     */
    public static function syncPayment(PDO $pdo, int $transactionId, string $paymentStatus): void
    {
        if ($transactionId < 1 || !ClassSessionFeeCalculator::tableHas($pdo, 'payment_transactions', 'payout_status')) {
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT pt.id, pt.status, pt.payout_status, pt.teacher_net_amount, pt.delivery_mode,
                    t.fee_rule, t.delivery_mode AS lesson_mode
             FROM payment_transactions pt
             LEFT JOIN timetable t ON t.id = pt.timetable_id
             WHERE pt.id = ? LIMIT 1'
        );
        $stmt->execute([$transactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $rule = (string)($row['fee_rule'] ?? '');
        $mode = ClassSessionFeeCalculator::normalizeMode((string)($row['lesson_mode'] ?? $row['delivery_mode'] ?? ''));
        if ($rule !== ClassSessionFeeCalculator::ONLINE_RULE || $mode !== 'online') {
            return;
        }
        $current = strtolower((string)($row['payout_status'] ?? ''));
        $paymentStatus = strtolower($paymentStatus);
        if ($paymentStatus === 'paid') {
            if ($current === '' || $current === 'pending') {
                $pdo->prepare(
                    "UPDATE payment_transactions SET payout_status = 'pending' WHERE id = ? AND (payout_status IS NULL OR payout_status = '' OR payout_status = 'pending')"
                )->execute([$transactionId]);
            }
            return;
        }
        if (in_array($paymentStatus, ['failed', 'cancelled', 'refunded'], true) && in_array($current, ['', 'pending', 'processing'], true)) {
            $pdo->prepare(
                "UPDATE payment_transactions SET payout_status = 'failed' WHERE id = ? AND (payout_status IS NULL OR payout_status IN ('', 'pending', 'processing'))"
            )->execute([$transactionId]);
            self::audit($pdo, 0, 'payout_blocked', 'payment_transaction', (string)$transactionId, [
                'payment_status' => $paymentStatus,
            ]);
        }
    }

    /**
     * @param list<int> $transactionIds
     */
    public static function record(PDO $pdo, array $transactionIds, string $status, int $actorUserId, string $reference, string $payoutDate, string $method, string $notes): int
    {
        self::ensureSchema($pdo);
        $status = strtolower(trim($status));
        if (!in_array($status, ['processing', 'paid', 'failed'], true)) {
            throw new RuntimeException('Choose a payout status of processing, paid, or failed.');
        }
        $ids = [];
        foreach ($transactionIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            throw new RuntimeException('Select at least one paid online class payment.');
        }
        if ($status === 'paid' && trim($reference) === '') {
            throw new RuntimeException('Enter the payout reference.');
        }
        if ($status === 'paid' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payoutDate)) {
            throw new RuntimeException('Enter the payout date.');
        }
        $method = trim($method) !== '' ? substr(trim($method), 0, 40) : 'bank';
        $reference = trim($reference) !== '' ? substr(trim($reference), 0, 80) : null;
        $notes = trim($notes) !== '' ? substr(trim($notes), 0, 500) : null;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT pt.id,
                    COALESCE(pt.teacher_id, t.teacher_id) AS teacher_id,
                    pt.status, pt.payout_status, pt.payout_id,
                    COALESCE(pt.teacher_net_amount, t.teacher_net_amount) AS teacher_net_amount,
                    t.fee_rule
             FROM payment_transactions pt
             INNER JOIN timetable t ON t.id = pt.timetable_id
             WHERE pt.id IN ($placeholders)"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('One of the selected payments could not be found.');
        }
        $teacherId = 0;
        $cents = 0;
        $existingPayoutId = 0;
        foreach ($rows as $row) {
            if ((string)$row['fee_rule'] !== ClassSessionFeeCalculator::ONLINE_RULE) {
                throw new RuntimeException('Teacher payouts are recorded for online classes only.');
            }
            if (strtolower((string)$row['status']) !== 'paid') {
                throw new RuntimeException('Only a paid student payment can be settled to the teacher.');
            }
            $payoutStatus = strtolower((string)($row['payout_status'] ?? ''));
            if ($payoutStatus === 'paid') {
                throw new RuntimeException('One of these payments is already settled.');
            }
            $rowPayoutId = (int)($row['payout_id'] ?? 0);
            if ($rowPayoutId > 0) {
                if ($existingPayoutId === 0) {
                    $existingPayoutId = $rowPayoutId;
                } elseif ($existingPayoutId !== $rowPayoutId) {
                    throw new RuntimeException('These payments belong to different payouts.');
                }
            } elseif ($existingPayoutId > 0) {
                throw new RuntimeException('Do not mix a new payment with one that is already on a payout.');
            }
            $rowTeacher = (int)$row['teacher_id'];
            if ($teacherId === 0) {
                $teacherId = $rowTeacher;
            } elseif ($teacherId !== $rowTeacher) {
                throw new RuntimeException('Select payments for one teacher at a time.');
            }
            $cents += ClassSessionFeeCalculator::toCents($row['teacher_net_amount'] ?? 0);
        }
        if ($teacherId < 1) {
            throw new RuntimeException('These payments are not linked to a teacher.');
        }
        $bank = TeacherBankAccountService::findForTeacher($pdo, $teacherId);
        if (!TeacherBankAccountService::isCompleteRow($bank)) {
            throw new RuntimeException('This teacher still needs completed bank details before a payout can be recorded.');
        }

        $pdo->beginTransaction();
        try {
            if ($existingPayoutId > 0) {
                $pdo->prepare(
                    'UPDATE teacher_payouts
                     SET amount = ?, payout_date = ?, payment_method = ?, payout_reference = ?, processed_by = ?, status = ?, notes = ?
                     WHERE id = ? AND teacher_id = ?'
                )->execute([
                    ClassSessionFeeCalculator::formatCents($cents),
                    $status === 'paid' ? $payoutDate : null,
                    $method,
                    $reference,
                    $actorUserId > 0 ? $actorUserId : null,
                    $status,
                    $notes,
                    $existingPayoutId,
                    $teacherId,
                ]);
                $payoutId = $existingPayoutId;
            } else {
                $pdo->prepare(
                    'INSERT INTO teacher_payouts
                        (teacher_id, bank_account_id, amount, payout_date, payment_method, payout_reference, processed_by, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $teacherId,
                    (int)$bank['id'],
                    ClassSessionFeeCalculator::formatCents($cents),
                    $status === 'paid' ? $payoutDate : null,
                    $method,
                    $reference,
                    $actorUserId > 0 ? $actorUserId : null,
                    $status,
                    $notes,
                ]);
                $payoutId = (int)$pdo->lastInsertId();
            }
            $item = $pdo->prepare(
                'INSERT INTO teacher_payout_items (payout_id, payment_transaction_id, teacher_net_amount) VALUES (?, ?, ?)'
            );
            $mark = $pdo->prepare(
                'UPDATE payment_transactions SET payout_status = ?, payout_id = ? WHERE id = ? AND status = \'paid\''
            );
            foreach ($rows as $row) {
                if ($existingPayoutId < 1) {
                    $item->execute([
                        $payoutId,
                        (int)$row['id'],
                        ClassSessionFeeCalculator::formatCents(ClassSessionFeeCalculator::toCents($row['teacher_net_amount'] ?? 0)),
                    ]);
                }
                $mark->execute([$status, $payoutId, (int)$row['id']]);
            }
            self::audit($pdo, $actorUserId, 'payout_' . $status, 'teacher_payout', (string)$payoutId, [
                'teacher_id' => $teacherId,
                'bank_account_id' => (int)$bank['id'],
                'amount' => ClassSessionFeeCalculator::formatCents($cents),
                'reference' => $reference,
                'payments' => array_values($ids),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        if ($status === 'paid') {
            TeacherPaymentSmsService::notifyPayoutPaidQuiet($pdo, $payoutId, $actorUserId);
        }
        return $payoutId;
    }

    /**
     * Copy the stored online-class snapshot onto paid student payments.
     * This does not recalculate historical fees.
     */
    public static function attachOpenOnlinePayments(PDO $pdo): void
    {
        if (!ClassSessionFeeCalculator::tableHas($pdo, 'payment_transactions', 'payout_status')
            || !ClassSessionFeeCalculator::tableHas($pdo, 'timetable', 'fee_rule')) {
            return;
        }
        try {
            $pdo->exec(
                "UPDATE payment_transactions pt
                 INNER JOIN timetable t ON t.id = pt.timetable_id
                 SET pt.teacher_id = COALESCE(pt.teacher_id, t.teacher_id),
                     pt.delivery_mode = COALESCE(NULLIF(pt.delivery_mode, ''), t.delivery_mode),
                     pt.gross_class_fee = COALESCE(pt.gross_class_fee, t.class_fee_per_student),
                     pt.institute_online_fee = COALESCE(pt.institute_online_fee, t.institute_online_fee),
                     pt.transaction_handling_fee = COALESCE(pt.transaction_handling_fee, t.transaction_handling_fee),
                     pt.teacher_net_amount = COALESCE(pt.teacher_net_amount, t.teacher_net_amount),
                     pt.payout_status = IF(pt.status = 'paid' AND (pt.payout_status IS NULL OR pt.payout_status = ''), 'pending', pt.payout_status)
                 WHERE t.fee_rule = 'online_v1'
                   AND t.teacher_net_amount IS NOT NULL
                   AND pt.status = 'paid'
                   AND (pt.payout_status IS NULL OR pt.payout_status = '' OR pt.teacher_net_amount IS NULL OR pt.teacher_id IS NULL)"
            );
        } catch (\Throwable $e) {
            error_log('attach online teacher payouts: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function outstandingRows(PDO $pdo, int $teacherId): array
    {
        if ($teacherId < 1) {
            return [];
        }
        self::attachOpenOnlinePayments($pdo);
        $stmt = $pdo->prepare(
            "SELECT pt.id,
                    COALESCE(pt.teacher_id, t.teacher_id) AS teacher_id,
                    pt.payout_status,
                    pt.payout_id,
                    COALESCE(pt.teacher_net_amount, t.teacher_net_amount) AS teacher_net_amount
             FROM payment_transactions pt
             INNER JOIN timetable t ON t.id = pt.timetable_id
             WHERE t.fee_rule = 'online_v1'
               AND pt.status = 'paid'
               AND COALESCE(pt.teacher_id, t.teacher_id) = ?
               AND (pt.payout_status IS NULL OR pt.payout_status IN ('', 'pending', 'processing'))"
        );
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    public static function outstandingCents(array $rows): int
    {
        $cents = 0;
        foreach ($rows as $row) {
            $cents += ClassSessionFeeCalculator::toCents($row['teacher_net_amount'] ?? 0);
        }
        return $cents;
    }

    public static function requestOutstanding(PDO $pdo, int $teacherId, int $actorUserId): int
    {
        $rows = self::outstandingRows($pdo, $teacherId);
        $ids = [];
        $processing = 0;
        foreach ($rows as $row) {
            $status = strtolower((string)($row['payout_status'] ?? ''));
            if ($status === 'processing') {
                $processing++;
                continue;
            }
            $ids[] = (int)$row['id'];
        }
        if ($ids === []) {
            if ($processing > 0) {
                throw new RuntimeException('Your payment request is already with the office.');
            }
            throw new RuntimeException('There is no online class amount waiting to be paid to you.');
        }
        return self::record($pdo, $ids, 'processing', $actorUserId, '', '', 'bank', 'Requested by the teacher');
    }

    public static function payOutstanding(PDO $pdo, int $teacherId, int $actorUserId, string $reference, string $payoutDate, string $method, string $notes): int
    {
        self::ensureSchema($pdo);
        $reference = trim($reference);
        $payoutDate = trim($payoutDate);
        if ($reference === '') {
            throw new RuntimeException('Enter the payout reference.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payoutDate)) {
            throw new RuntimeException('Enter the payout date.');
        }
        $rows = self::outstandingRows($pdo, $teacherId);
        if ($rows === []) {
            throw new RuntimeException('This teacher has no online class amount waiting to be paid.');
        }
        $cents = self::outstandingCents($rows);
        if ($cents <= 0) {
            throw new RuntimeException('The teacher net amount waiting to be paid is not above zero.');
        }
        $bank = TeacherBankAccountService::findForTeacher($pdo, $teacherId);
        if (!TeacherBankAccountService::isCompleteRow($bank)) {
            throw new RuntimeException('This teacher still needs completed bank details before a payout can be recorded.');
        }
        $method = trim($method) !== '' ? substr(trim($method), 0, 40) : 'bank';
        $notes = trim($notes) !== '' ? substr(trim($notes), 0, 500) : null;
        $reference = substr($reference, 0, 80);

        $groups = [];
        foreach ($rows as $row) {
            $groups[(int)($row['payout_id'] ?? 0)][] = $row;
        }

        $pdo->beginTransaction();
        $paidIds = [];
        try {
            $firstId = 0;
            foreach ($groups as $payoutId => $group) {
                $groupCents = self::outstandingCents($group);
                if ($payoutId > 0) {
                    $pdo->prepare(
                        'UPDATE teacher_payouts
                         SET amount = ?, payout_date = ?, payment_method = ?, payout_reference = ?, processed_by = ?, status = ?, notes = ?
                         WHERE id = ? AND teacher_id = ?'
                    )->execute([
                        ClassSessionFeeCalculator::formatCents($groupCents),
                        $payoutDate,
                        $method,
                        $reference,
                        $actorUserId > 0 ? $actorUserId : null,
                        'paid',
                        $notes,
                        $payoutId,
                        $teacherId,
                    ]);
                    $savedId = $payoutId;
                } else {
                    $pdo->prepare(
                        'INSERT INTO teacher_payouts
                            (teacher_id, bank_account_id, amount, payout_date, payment_method, payout_reference, processed_by, status, notes)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $teacherId,
                        (int)$bank['id'],
                        ClassSessionFeeCalculator::formatCents($groupCents),
                        $payoutDate,
                        $method,
                        $reference,
                        $actorUserId > 0 ? $actorUserId : null,
                        'paid',
                        $notes,
                    ]);
                    $savedId = (int)$pdo->lastInsertId();
                    $item = $pdo->prepare(
                        'INSERT INTO teacher_payout_items (payout_id, payment_transaction_id, teacher_net_amount) VALUES (?, ?, ?)'
                    );
                    foreach ($group as $row) {
                        $item->execute([
                            $savedId,
                            (int)$row['id'],
                            ClassSessionFeeCalculator::formatCents(ClassSessionFeeCalculator::toCents($row['teacher_net_amount'] ?? 0)),
                        ]);
                    }
                }
                $mark = $pdo->prepare(
                    "UPDATE payment_transactions
                     SET payout_status = 'paid', payout_id = ?, teacher_id = COALESCE(teacher_id, ?)
                     WHERE id = ? AND status = 'paid'"
                );
                foreach ($group as $row) {
                    $mark->execute([$savedId, $teacherId, (int)$row['id']]);
                }
                self::audit($pdo, $actorUserId, 'payout_paid', 'teacher_payout', (string)$savedId, [
                    'teacher_id' => $teacherId,
                    'bank_account_id' => (int)$bank['id'],
                    'amount' => ClassSessionFeeCalculator::formatCents($groupCents),
                    'reference' => $reference,
                ]);
                if ($firstId === 0) {
                    $firstId = $savedId;
                }
                $paidIds[] = $savedId;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        foreach ($paidIds as $paidId) {
            TeacherPaymentSmsService::notifyPayoutPaidQuiet($pdo, (int)$paidId, $actorUserId);
        }
        return $firstId;
    }
}
