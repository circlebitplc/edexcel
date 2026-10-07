<?php
declare(strict_types=1);

namespace Edexcel\Repositories;

use PDO;

final class PaymentRepository
{
    public function __construct(private PDO $pdo) {}

    public function getTeacherCommission(int $teacherId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT commission_percentage FROM teacher_commission WHERE teacher_id = ? LIMIT 1
        ");
        $stmt->execute([$teacherId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : 70.0;
    }

    public function getTeacherClassStudentCount(int $teacherId, string $periodStart, string $periodEnd): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(student_count), 0)
            FROM timetable
            WHERE teacher_id = ?
              AND date BETWEEN ? AND ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$teacherId, $periodStart, $periodEnd]);
        return (int)$stmt->fetchColumn();
    }

    public function getPaymentsByTeacher(int $teacherId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payments WHERE teacher_id = ? ORDER BY period_end DESC, id DESC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordPayment(int $teacherId, float $amount, string $start, string $end, string $status = 'paid', ?string $notes = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (teacher_id, amount, period_start, period_end, payment_date, status, notes)
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?)
        ");
        $stmt->execute([$teacherId, $amount, $start, $end, $status, $notes]);
        return (int)$this->pdo->lastInsertId();
    }
}
