<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\TimetableRepository;
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
        ClassSessionFeeCalculator::ensureSchema($this->pdo);
        $data = ClassSessionFeeCalculator::stampPayload($data, $this->pdo);
        TeacherBankAccountService::assertCanScheduleOnline(
            $this->pdo,
            (int)($data['teacher_id'] ?? 0),
            (string)($data['delivery_mode'] ?? 'physical')
        );
        $this->pdo->beginTransaction();
        try {
            $authorize($data);
            $mode = function_exists('classroom_normalize_delivery_mode')
                ? classroom_normalize_delivery_mode((string)($data['delivery_mode'] ?? 'physical'))
                : 'physical';
            $data['delivery_mode'] = $mode;
            $conflict=$this->conflicts->message(
                (int)$data['teacher_id'],(int)$data['room_id'],
                (int)$data['class_id'],$data['date'],
                $data['start_time'],$data['end_time'],0,
                $mode === 'online'
            );
            if ($conflict) throw new RuntimeException($conflict);

            $id=$this->repository->create($data);
            if (function_exists('classroom_sync_lesson_meeting')) {
                classroom_sync_lesson_meeting($this->pdo, $id);
            }

            if ($this->audit) $this->audit->log('create',$id,null,$data);

            if ($repeat) {
                if (!$this->recurring) throw new RuntimeException('Recurring service unavailable.');
                $this->recurring->upsert([
                    'teacher_id'=>$data['teacher_id'],
                    'subject_id'=>$data['subject_id'],
                    'class_id'=>$data['class_id'],
                    'room_id'=>$data['room_id'],
                    'class_fee_per_student'=>$data['class_fee_per_student'] ?? 0,
                    'day_of_week'=>$data['day_of_week'],
                    'start_time'=>$data['start_time'],
                    'end_time'=>$data['end_time'],
                    'date'=>$data['date'],
                    'repeat_until'=>$until,
                    'delivery_mode'=>$data['delivery_mode'] ?? 'physical',
                    'fee_rule'=>$data['fee_rule'] ?? null,
                    'institute_online_fee'=>$data['institute_online_fee'] ?? null,
                    'transaction_handling_fee'=>$data['transaction_handling_fee'] ?? null,
                    'teacher_net_amount'=>$data['teacher_net_amount'] ?? null,
                ]);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }


}
