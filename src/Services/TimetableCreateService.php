<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetableCreateService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private TimetableConflictService $conflicts,
        private ?RecurringScheduleService $recurring=null,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function create(array $data,callable $authorize,bool $repeat=false,string $until=''): int
    {
        TimetableInputValidator::validate($data);
        TimetableInputValidator::validateRepeat($data, $repeat, $until);
        $this->pdo->beginTransaction();
        try {
            $authorize($data);
            $conflict=$this->conflicts->message(
                (int)$data['teacher_id'],(int)$data['room_id'],
                (int)$data['class_id'],$data['date'],
                $data['start_time'],$data['end_time']
            );
            if ($conflict) throw new RuntimeException($conflict);

            $id=$this->repository->create($data);

            if ($this->audit) $this->audit->log('create',$id,null,$data);

            if ($repeat) {
                if (!$this->recurring) throw new RuntimeException('Recurring service unavailable.');
                $this->recurring->upsert([
                    'teacher_id'=>$data['teacher_id'],
                    'subject_id'=>$data['subject_id'],
                    'class_id'=>$data['class_id'],
                    'room_id'=>$data['room_id'],
                    'day_of_week'=>$data['day_of_week'],
                    'start_time'=>$data['start_time'],
                    'end_time'=>$data['end_time'],
                    'date'=>$data['date'],
                    'repeat_until'=>$until
                ]);
            }

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }


}
