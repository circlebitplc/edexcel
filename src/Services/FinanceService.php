<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Read/report layer for finance. Existing payment services remain responsible
 * for creating and verifying transactions.
 */
final class FinanceService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function studentBalance(int $studentId): array
    {
        $statement = (new FeeStatementService($this->pdo))->forStudent($studentId);
        $adjustments = 0.0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(CASE WHEN type IN ('discount','scholarship','waiver','refund') THEN amount ELSE -amount END),0)
                FROM finance_adjustments
                WHERE student_id = ? AND status IN ('approved','applied')
            ");
            $stmt->execute([$studentId]);
            $adjustments = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {}
        return [
            'student_id' => $studentId,
            'billed' => round((float)($statement['due'] ?? 0) + (float)($statement['paid'] ?? 0), 2),
            'paid' => round((float)($statement['paid'] ?? 0), 2),
            'adjustments' => round($adjustments, 2),
            'outstanding' => round(max(0, (float)($statement['due'] ?? 0) - $adjustments), 2),
            'history' => $statement['receipts'] ?? [],
        ];
    }

    /** @return array<string,mixed> */
    public function collectionReport(string $from, string $to): array
    {
        $from = $this->validDate($from) ? $from : date('Y-m-01');
        $to = $this->validDate($to) ? $to : date('Y-m-d');
        $rows = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(paid_at) report_date,
                       COALESCE(SUM(CASE WHEN gateway='cash' THEN amount ELSE 0 END),0) cash,
                       COALESCE(SUM(CASE WHEN gateway='onepay' THEN amount ELSE 0 END),0) onepay,
                       COALESCE(SUM(CASE WHEN gateway='bank' THEN amount ELSE 0 END),0) bank,
                       COALESCE(SUM(amount),0) total, COUNT(*) transactions
                FROM payment_transactions
                WHERE status='paid' AND DATE(paid_at) BETWEEN ? AND ?
                GROUP BY DATE(paid_at) ORDER BY report_date
            ");
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}
        $totals = ['cash'=>0.0,'onepay'=>0.0,'bank'=>0.0,'total'=>0.0,'transactions'=>0];
        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) $totals[$key] += (float)($row[$key] ?? 0);
        }
        return ['from'=>$from,'to'=>$to,'rows'=>$rows,'totals'=>$totals];
    }

    /** @return list<array<string,mixed>> */
    public function teacherPayments(string $from, string $to): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.id teacher_id, t.name,
                       COALESCE(SUM(tp.amount),0) paid_amount,
                       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.teacher_id=t.id AND p.period_start<=? AND p.period_end>=?),0) scheduled_amount
                FROM teachers t
                LEFT JOIN teacher_payment_events tp ON tp.teacher_id=t.id AND tp.payment_date BETWEEN ? AND ?
                WHERE t.deleted_at IS NULL
                GROUP BY t.id, t.name ORDER BY t.name
            ");
            $stmt->execute([$to, $from, $from, $to]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function recordAdjustment(int $studentId, string $type, float $amount, string $reason, int $userId): int
    {
        if ($studentId < 1 || $amount <= 0 || !in_array($type, ['discount','scholarship','waiver','refund','charge'], true)) {
            throw new \InvalidArgumentException('Invalid financial adjustment.');
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO finance_adjustments (student_id,type,amount,reason,approved_by,status,applied_at)
            VALUES (?,?,?,?,?,'applied',NOW())
        ");
        $stmt->execute([$studentId,$type,round($amount,2),mb_substr(trim($reason),0,500),$userId]);
        $id = (int)$this->pdo->lastInsertId();
        if (function_exists('log_audit')) log_audit($this->pdo,'finance_adjustment','finance_adjustments',$id,null,['student_id'=>$studentId,'type'=>$type,'amount'=>$amount]);
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function expenses(string $from, string $to): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM finance_expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC,id DESC');
            $stmt->execute([$from,$to]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }

    private function validDate(string $date): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',$date); }
}
