<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\PaymentRepository;

/**
 * Unused by live pages. Teacher pay is stored on timetable rows
 * (timetable/payments.php). Student fees use student_fee_ledger.
 * Kept so old reports code can be wired later without dropping tables
 * payments / teacher_commission / revenue_summary.
 */
final class PaymentService
{
    public function __construct(
        private PaymentRepository $repository,
        private float $feePerStudent = 500.0
    ) {}

    public function calculateTeacherEarnings(int $teacherId, string $periodStart, string $periodEnd): array
    {
        $totalStudents = $this->repository->getTeacherClassStudentCount($teacherId, $periodStart, $periodEnd);
        $commissionPercent = $this->repository->getTeacherCommission($teacherId);

        $totalRevenue = $totalStudents * $this->feePerStudent;
        $teacherEarnings = $totalRevenue * ($commissionPercent / 100.0);
        $collegeRetained = $totalRevenue - $teacherEarnings;

        return [
            'total_students'     => $totalStudents,
            'fee_per_student'    => $this->feePerStudent,
            'total_revenue'      => $totalRevenue,
            'commission_percent' => $commissionPercent,
            'teacher_earnings'   => $teacherEarnings,
            'college_retained'   => $collegeRetained,
        ];
    }
}
