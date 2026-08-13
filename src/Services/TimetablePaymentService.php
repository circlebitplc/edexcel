<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TimetableRepository;
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
            $duration=\lesson_duration_minutes((string)$entry['start_time'],(string)$entry['end_time']);
            $rate=\lesson_rate_per_student($duration);
            $amount=(int)$entry['student_count']*$rate;
            $date=date('Y-m-d');
            $this->repository->markPaid($id,$date);
            if($this->audit) $this->audit->log('mark_paid',$id,$entry,[
                'payment_status'=>'paid','payment_date'=>$date,'amount'=>$amount
            ]);
            $this->pdo->commit();
            return ['id'=>$id,'teacher_id'=>(int)$entry['teacher_id'],'amount'=>$amount,'payment_date'=>$date];
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
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
                $duration=\lesson_duration_minutes(
                    (string)$entry['start_time'],
                    (string)$entry['end_time']
                );
                $rate=\lesson_rate_per_student($duration);
                $amount=(int)$entry['student_count']*$rate;
                $this->repository->markPaid((int)$id,$date);
                if($this->audit) $this->audit->log('mark_paid',(int)$id,$entry,[
                    'payment_status'=>'paid','payment_date'=>$date,'amount'=>$amount
                ]);
                $notifications[]=['teacher_id'=>(int)$entry['teacher_id'],'amount'=>$amount];
                $changed++;
            }
            $this->pdo->commit();
            return ['changed'=>$changed,'notifications'=>$notifications];
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
