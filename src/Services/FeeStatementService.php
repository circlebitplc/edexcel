<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class FeeStatementService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function forStudent(int $studentId): array
    {
        $wallet = function_exists('campus_fee_summary')
            ? campus_fee_summary($this->pdo, $studentId)
            : ['due' => 0, 'paid' => 0, 'billed' => 0, 'next' => null, 'rows' => [], 'breakdown' => []];

        $lessons = $this->lessonRows($studentId);
        $receipts = $this->receipts($studentId);

        $lessonDue = 0.0;
        foreach ($lessons as $row) {
            if (!in_array($row['status'], ['unpaid', 'pending', 'partial'], true) || !empty($row['covered_by_monthly'])) {
                continue;
            }
            $att = strtolower((string)($row['attendance_status'] ?? ''));
            if (in_array($att, ['present', 'late'], true) && empty($row['upcoming'])) {
                continue;
            }
            $lessonDue += max(0, (float)$row['amount_due'] - (float)$row['amount_paid']);
        }

        return [
            'wallet' => $wallet,
            'lessons' => $lessons,
            'receipts' => $receipts,
            'due' => round((float)($wallet['due'] ?? 0) + $lessonDue, 2),
            'paid' => round(max(0, (float)($wallet['paid'] ?? 0) + $this->paidThroughGateways($receipts) - $this->monthlyCreditsAlreadyInWallet($studentId)), 2),
            'lesson_due' => round($lessonDue, 2),
            'next' => $wallet['next'] ?? null,
        ];
    }

    /**
     * @return array{cash:float,onepay:float,wallet:float,handed:float,outstanding_cash:float}
     */
    public function dayEnd(string $date): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $cash = $this->sumGatewayOnDate('cash', $date);
        $onepay = $this->sumGatewayOnDate('onepay', $date);
        $bank = $this->sumGatewayOnDate('bank', $date);
        $wallet = 0.0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount_paid), 0)
                FROM student_fee_ledger
                WHERE status IN ('paid','partial','waived')
                  AND DATE(paid_at) = ?
            ");
            $stmt->execute([$date]);
            $wallet = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }
        $handed = 0.0;
        $outstanding = 0.0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount_handed), 0)
                FROM cash_handovers
                WHERE period_date = ? AND status IN ('handed','received')
            ");
            $stmt->execute([$date]);
            $handed = (float)$stmt->fetchColumn();
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payment_transactions p
                WHERE p.gateway = 'cash' AND p.status = 'paid'
                  AND DATE(p.paid_at) = ?
                  AND (p.handover_id IS NULL OR p.handover_id = 0)
            ");
            $stmt->execute([$date]);
            $outstanding = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }

        return [
            'date' => $date,
            'cash' => round($cash, 2),
            'onepay' => round($onepay, 2),
            'bank' => round($bank, 2),
            'wallet' => round($wallet, 2),
            'handed' => round($handed, 2),
            'outstanding_cash' => round($outstanding, 2),
            'total' => round($cash + $onepay + $bank, 2),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lessonRows(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    f.*,
                    s.name AS subject_name,
                    c.name AS class_name,
                    tt.date,
                    tt.start_time,
                    tt.end_time,
                    tt.class_id,
                    p.gateway,
                    p.gateway_response,
                    p.gateway_reference,
                    p.status AS txn_status
                FROM student_lesson_fees f
                JOIN timetable tt ON tt.id = f.timetable_id AND tt.deleted_at IS NULL
                JOIN subjects s ON s.id = tt.subject_id
                JOIN student_classes c ON c.id = tt.class_id
                LEFT JOIN payment_transactions p ON p.id = f.payment_id
                WHERE f.student_id = ?
                ORDER BY tt.date DESC, tt.start_time DESC
                LIMIT 80
            ");
            $stmt->execute([$studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $fees = new StudentLessonFeeService($this->pdo);
        $out = [];
        foreach ($rows as $row) {
            $resolved = $fees->resolve($studentId, $row, isset($row['recording_id']) ? (int)$row['recording_id'] : null);
            $out[] = [
                'timetable_id' => (int)$row['timetable_id'],
                'subject_name' => (string)$row['subject_name'],
                'class_name' => (string)$row['class_name'],
                'date' => (string)$row['date'],
                'start_time' => (string)($row['start_time'] ?? ''),
                'status' => (string)$resolved['status'],
                'amount_due' => (float)$resolved['amount_due'],
                'amount_paid' => (float)$resolved['amount_paid'],
                'gateway' => StudentLessonFeeService::displayMethod($row),
                'covered_by_monthly' => !empty($resolved['covered_by_monthly']),
                'attendance_status' => $fees->attendanceStatus($studentId, (int)$row['timetable_id']),
                'upcoming' => false,
            ];
        }
        foreach ($this->upcomingUnpaid($studentId, $fees, $out) as $extra) {
            $out[] = $extra;
        }
        return $out;
    }

    /**
     * Upcoming enrolled lessons that are not yet on the class-fee table (pay before class).
     *
     * @param list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function upcomingUnpaid(int $studentId, StudentLessonFeeService $fees, array $existing): array
    {
        $seen = [];
        foreach ($existing as $row) {
            $seen[(int)$row['timetable_id']] = true;
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    tt.*,
                    tt.id AS timetable_id,
                    s.name AS subject_name,
                    c.name AS class_name,
                    COALESCE(tt.lesson_status, 'scheduled') AS lesson_status
                FROM timetable tt
                JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
                JOIN subjects s ON s.id = tt.subject_id
                JOIN student_classes c ON c.id = tt.class_id
                WHERE tt.deleted_at IS NULL
                  AND tt.date >= CURDATE()
                  AND COALESCE(tt.lesson_status, 'scheduled') <> 'cancelled'
                ORDER BY tt.date ASC, tt.start_time ASC
                LIMIT 40
            ");
            $stmt->execute([$studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        $mapped = $fees->mapForStudentLessons($studentId, $rows);
        foreach ($rows as $row) {
            $id = (int)$row['timetable_id'];
            if (isset($seen[$id]) || !$fees->isEnrolled($studentId, $row)) {
                continue;
            }
            $info = $mapped[$id] ?? null;
            if (!$info || empty($info['needs_pay'])) {
                continue;
            }
            $out[] = [
                'timetable_id' => $id,
                'subject_name' => (string)$row['subject_name'],
                'class_name' => (string)$row['class_name'],
                'date' => (string)$row['date'],
                'start_time' => (string)($row['start_time'] ?? ''),
                'status' => (string)$info['status'],
                'amount_due' => (float)$info['amount_due'],
                'amount_paid' => (float)$info['amount_paid'],
                'gateway' => (string)($info['pending_gateway'] ?? ''),
                'covered_by_monthly' => !empty($info['covered_by_monthly']),
                'attendance_status' => $fees->attendanceStatus($studentId, $id),
                'upcoming' => true,
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function receipts(int $studentId): array
    {
        try {
            return (new PaymentTransactionService($this->pdo))->studentHistory($studentId, 40);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<array<string,mixed>> $receipts
     */
    private function paidThroughGateways(array $receipts): float
    {
        $sum = 0.0;
        foreach ($receipts as $row) {
            if (strtolower((string)($row['status'] ?? '')) === 'paid') {
                $sum += (float)($row['amount'] ?? 0);
            }
        }
        return $sum;
    }

    private function monthlyCreditsAlreadyInWallet(int $studentId): float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(monthly_credit), 0)
                FROM student_lesson_fees
                WHERE student_id = ? AND monthly_credit > 0
            ");
            $stmt->execute([$studentId]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    private function sumGatewayOnDate(string $gateway, string $date): float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payment_transactions
                WHERE gateway = ? AND status = 'paid' AND DATE(paid_at) = ?
            ");
            $stmt->execute([$gateway, $date]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}
