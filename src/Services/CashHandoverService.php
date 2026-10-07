<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class CashHandoverService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function outstandingForUser(int $userId, int $teacherId, bool $isAdmin): array
    {
        $sql = "
            SELECT
                DATE(p.paid_at) AS period_date,
                COALESCE(p.collected_by, 0) AS collected_by,
                tt.teacher_id,
                t.name AS teacher_name,
                COUNT(*) AS n,
                COALESCE(SUM(p.amount), 0) AS amount
            FROM payment_transactions p
            JOIN timetable tt ON tt.id = p.timetable_id
            LEFT JOIN teachers t ON t.id = tt.teacher_id
            WHERE p.gateway = 'cash'
              AND p.status = 'paid'
              AND (p.handover_id IS NULL OR p.handover_id = 0)
        ";
        $params = [];
        if (!$isAdmin) {
            $sql .= " AND (p.collected_by = ? OR tt.teacher_id = ?)";
            $params[] = $userId;
            $params[] = $teacherId;
        }
        $sql .= " GROUP BY DATE(p.paid_at), COALESCE(p.collected_by, 0), tt.teacher_id, t.name
                  ORDER BY period_date DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->query("
            SELECT h.*, t.name AS teacher_name, u.username AS collected_name, r.username AS received_name
            FROM cash_handovers h
            LEFT JOIN teachers t ON t.id = h.teacher_id
            LEFT JOIN users u ON u.id = h.collected_by
            LEFT JOIN users r ON r.id = h.received_by
            ORDER BY h.id DESC
            LIMIT {$limit}
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function handOver(
        int $userId,
        int $teacherId,
        string $date,
        float $amount,
        string $notes = '',
        bool $isAdmin = false,
        int $collectedBy = 0
    ): int {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Choose a date.');
        }
        $collectedBy = $collectedBy > 0 ? $collectedBy : $userId;
        unset($isAdmin);
        $this->pdo->beginTransaction();
        try {
            $ids = $this->openTransactionIds($collectedBy, $teacherId, $date, false, true);
            if ($ids === []) {
                throw new RuntimeException('No unmatched cash for that day.');
            }
            $sum = $this->sumIds($ids);
            $handed = round($amount > 0 ? $amount : $sum, 2);
            if (!PaymentVerificationService::amountsMatch($sum, $handed)) {
                throw new RuntimeException(
                    'Handed amount must match the cash attached to this bag (Rs ' . number_format($sum, 2) . ').'
                );
            }
            $this->pdo->prepare("
                INSERT INTO cash_handovers
                    (teacher_id, collected_by, period_date, amount_expected, amount_handed, status, notes, handed_at)
                VALUES (?, ?, ?, ?, ?, 'handed', ?, NOW())
            ")->execute([
                $teacherId > 0 ? $teacherId : null,
                $collectedBy,
                $date,
                $sum,
                $handed,
                $notes !== '' ? mb_substr($notes, 0, 500) : null,
            ]);
            $handoverId = (int)$this->pdo->lastInsertId();
            $upd = $this->pdo->prepare('UPDATE payment_transactions SET handover_id = ? WHERE id = ?');
            foreach ($ids as $id) {
                $upd->execute([$handoverId, $id]);
            }
            $this->pdo->commit();
            return $handoverId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function receive(int $handoverId, int $adminUserId): void
    {
        $this->pdo->prepare("
            UPDATE cash_handovers
            SET status = 'received', received_by = ?, received_at = NOW()
            WHERE id = ? AND status = 'handed'
        ")->execute([$adminUserId, $handoverId]);
        if ($this->pdo->rowCount() < 1) {
            throw new RuntimeException('That handover is not waiting for the office.');
        }
    }

    /**
     * @return list<int>
     */
    private function openTransactionIds(
        int $userId,
        int $teacherId,
        string $date,
        bool $isAdmin,
        bool $forUpdate = false
    ): array {
        $sql = "
            SELECT p.id
            FROM payment_transactions p
            JOIN timetable tt ON tt.id = p.timetable_id
            WHERE p.gateway = 'cash' AND p.status = 'paid'
              AND DATE(p.paid_at) = ?
              AND (p.handover_id IS NULL OR p.handover_id = 0)
              AND COALESCE(p.collected_by, 0) = ?
              AND tt.teacher_id = ?
        ";
        $params = [$date, $userId, $teacherId];
        unset($isAdmin);
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<int> $ids
     */
    private function sumIds(array $ids): float
    {
        if ($ids === []) {
            return 0.0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE id IN ($in)");
        $stmt->execute($ids);
        return round((float)$stmt->fetchColumn(), 2);
    }
}
