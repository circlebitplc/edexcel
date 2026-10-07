<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetableService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private TimetableConflictService $conflicts,
        private ?RecurringScheduleService $recurring=null,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function update(
        int $id,array $data,callable $authorize,
        bool $repeat=false,string $repeatUntil=''
    ): int {
        if ($id<=0) throw new RuntimeException('Invalid lesson ID.');
        TimetableInputValidator::validate($data);
        TimetableInputValidator::validateRepeat($data, $repeat, $repeatUntil);

        $updatedRecurringFeeRows = 0;

        ClassSessionFeeCalculator::ensureSchema($this->pdo);
        $this->pdo->beginTransaction();
        try {
            $entry=$this->repository->findForUpdate($id);
            if (!$entry) throw new RuntimeException('Lesson not found.');
            if (!empty($entry['deleted_at'])) throw new RuntimeException('Lesson not found.');
            if (!empty($entry['is_locked'])) {
                throw new RuntimeException('This lesson is locked and cannot be edited.');
            }
            $authorize($entry);
            $data = ClassSessionFeeCalculator::stampPayload($data, $this->pdo, $entry);
            TeacherBankAccountService::assertCanScheduleOnline(
                $this->pdo,
                (int)($data['teacher_id'] ?? 0),
                (string)($data['delivery_mode'] ?? 'physical'),
                $entry
            );

            $mode = function_exists('classroom_normalize_delivery_mode')
                ? classroom_normalize_delivery_mode((string)($data['delivery_mode'] ?? 'physical'))
                : 'physical';
            $data['delivery_mode'] = $mode;
            $conflict=$this->conflicts->message(
                (int)$data['teacher_id'],(int)$data['room_id'],
                (int)$data['class_id'],$data['date'],
                $data['start_time'],$data['end_time'],$id,
                $mode === 'online'
            );
            if ($conflict) throw new RuntimeException($conflict);

            $this->repository->update($id,$data);
            if (function_exists('classroom_sync_lesson_meeting')) {
                classroom_sync_lesson_meeting($this->pdo, $id);
            }

            if ($this->audit) {
                $this->audit->log(
                    'update',$id,$entry,$data
                );
            }

            if ($repeat) {
                if (!$this->recurring) {
                    throw new RuntimeException('Recurring service unavailable.');
                }
                $day=date('l',strtotime($data['date']));
                $this->recurring->upsert([
                    'teacher_id'=>$data['teacher_id'],
                    'subject_id'=>$data['subject_id'],
                    'class_id'=>$data['class_id'],
                    'room_id'=>$data['room_id'],
                    'class_fee_per_student'=>$data['class_fee_per_student'] ?? 0,
                    'day_of_week'=>$day,
                    'start_time'=>$data['start_time'],
                    'end_time'=>$data['end_time'],
                    'date'=>$data['date'],
                    'repeat_until'=>$repeatUntil,
                    'delivery_mode'=>$data['delivery_mode'] ?? 'physical',
                    'fee_rule'=>$data['fee_rule'] ?? null,
                    'institute_online_fee'=>$data['institute_online_fee'] ?? null,
                    'transaction_handling_fee'=>$data['transaction_handling_fee'] ?? null,
                    'teacher_net_amount'=>$data['teacher_net_amount'] ?? null,
                ]);

                // Match already-generated weekly copies of this lesson
                // using the pattern from before this edit.
                $updatedRecurringFeeRows = $this->repository->updateRecurringClassFees(
                    $entry,
                    $id,
                    (float)($data['class_fee_per_student'] ?? 0),
                    (string)$data['date'],
                    $repeatUntil,
                    ClassSessionFeeCalculator::snapshotFromPayload($data)
                );
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $updatedRecurringFeeRows;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }


}
