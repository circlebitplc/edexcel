<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetablePaymentService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private float $feePerStudent,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function markPaid(int $id, ?callable $authorize=null): array
    {
        $this->pdo->beginTransaction();
        try {
            $entry=$this->repository->findForUpdate($id);
            if(!$entry) throw new RuntimeException('Lesson not found.');
            if(!empty($entry['deleted_at'])) throw new RuntimeException('This lesson has already been deleted.');
            if(($entry['payment_status']??'')==='paid') throw new RuntimeException('This lesson is already marked as paid.');
            if($authorize) $authorize($entry);
            $amount=TeacherPaymentSmsService::payableCents($entry)/100;
            $date=date('Y-m-d');
            $this->repository->markPaid($id,$date);
            if($this->audit) $this->audit->log('mark_paid',$id,$entry,[
                'payment_status'=>'paid','payment_date'=>$date,'amount'=>$amount
            ]);
            $this->pdo->commit();
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $sms = $this->smsAfterPaid($id);
        return [
            'id'=>$id,
            'teacher_id'=>(int)$entry['teacher_id'],
            'amount'=>$amount,
            'payment_date'=>$date,
            'sms'=>$sms,
        ];
    }

    public function markPaidBulk(array $ids,callable $authorize): array
    {
        $changed=0;$notifications=[];
        $this->pdo->beginTransaction();
        try {
            foreach($ids as $id) {
                $entry=$this->repository->findForUpdate((int)$id);
                if(!$entry || !empty($entry['deleted_at']) || ($entry['payment_status']??'')==='paid') continue;
                $authorize($entry);
                $date=date('Y-m-d');
                $amount=TeacherPaymentSmsService::payableCents($entry)/100;
                $this->repository->markPaid((int)$id,$date);
                if($this->audit) $this->audit->log('mark_paid',(int)$id,$entry,[
                    'payment_status'=>'paid','payment_date'=>$date,'amount'=>$amount
                ]);
                $notifications[]=[
                    'id'=>(int)$id,
                    'teacher_id'=>(int)$entry['teacher_id'],
                    'amount'=>$amount,
                ];
                $changed++;
            }
            $this->pdo->commit();
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $sms = [];
        foreach ($notifications as $notice) {
            $sms[] = $this->smsAfterPaid((int)$notice['id']);
        }
        return ['changed'=>$changed,'notifications'=>$notifications,'sms'=>$sms];
    }

    /**
     * @return array<string,mixed>
     */
    private function smsAfterPaid(int $id): array
    {
        try {
            return TeacherPaymentSmsService::notifyTimetablePaid(
                $this->pdo,
                $id,
                (int)($_SESSION['user_id'] ?? 0),
                false
            );
        } catch (\Throwable $e) {
            error_log('Teacher payment SMS failed for timetable '.$id.': '.$e->getMessage());
            return [
                'status' => 'failed',
                'sent' => false,
                'sms_notice' => 'Payment SMS could not be sent.',
                'sms_detail' => '',
                'message' => '',
            ];
        }
    }
}
