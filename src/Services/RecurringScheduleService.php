<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RecurringScheduleRepository;
use PDO;

final class RecurringScheduleService
{
    public function __construct(
        private RecurringScheduleRepository $repository,
        private ?PDO $pdo=null,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function upsert(array $data): void
    {
        $existing=$this->repository->findMatching($data);
        if ($existing) {
            $this->repository->updateEndDate(
                (int)$existing['id'],$data['repeat_until'],$data['date']
            );
            return;
        }
        $this->repository->create($data);
    }

    public function delete(int $id): void
    {
        if($id<=0) throw new \RuntimeException('Invalid recurring schedule ID.');
        $this->repository->delete($id);
        if($this->audit) {
            $this->audit->log('delete',$id,null,['deleted'=>true]);
        }
    }

    public function generateFutureEntries(int $lookAheadDays=7): int
    {
        $today=date('Y-m-d');
        $future=date('Y-m-d',strtotime("+{$lookAheadDays} days"));
        $generated=0;

        if($this->pdo) $this->pdo->beginTransaction();
        try {
            foreach($this->repository->dueSchedules($today) as $schedule) {
                $next=date(
                    'Y-m-d',
                    strtotime(($schedule['last_generated_date'] ?: $schedule['start_date']).' +7 days')
                );

                while($next <= $future && $next <= $schedule['end_date']) {
                    if(!$this->repository->entryExists($schedule,$next)) {
                        $id=$this->repository->generateEntry($schedule,$next);
                        $generated++;
                        if($this->audit) {
                            $this->audit->log(
                                'create',$id,null,
                                ['generated_from_recurring_id'=>(int)$schedule['id'],'date'=>$next]
                            );
                        }
                    }
                    $next=date('Y-m-d',strtotime($next.' +7 days'));
                }

                $lastGenerated=date('Y-m-d',strtotime($next.' -7 days'));
                if($lastGenerated > $schedule['last_generated_date']) {
                    $this->repository->updateEndDate(
                        (int)$schedule['id'],
                        $schedule['end_date'],
                        $lastGenerated
                    );
                }
            }

            if($this->pdo) $this->pdo->commit();
            return $generated;
        } catch(\Throwable $e) {
            if($this->pdo && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
