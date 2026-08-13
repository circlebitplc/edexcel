<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetableCloneService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function clone(int $id,string $date,callable $authorize): int
    {
        if($id<=0) throw new RuntimeException('Invalid lesson ID.');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) {
            throw new RuntimeException('Invalid clone date.');
        }
        $dateObject=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$dateObject || $dateObject->format('Y-m-d')!==$date) {
            throw new RuntimeException('Invalid clone date.');
        }

        $this->pdo->beginTransaction();
        try {
            $entry=$this->repository->findForUpdate($id);
            if(!$entry) throw new RuntimeException('Entry not found.');
            if(!empty($entry['deleted_at'])) throw new RuntimeException('Entry has been deleted.');
            $authorize($entry);

            if ($this->repository->conflict(
                'teacher_id',
                (int)$entry['teacher_id'],
                $date,
                (string)$entry['start_time'],
                (string)$entry['end_time'],
                0
            )) {
                throw new RuntimeException('The cloned lesson conflicts with an existing teacher lesson.');
            }
            if ($this->repository->conflict(
                'room_id',
                (int)$entry['room_id'],
                $date,
                (string)$entry['start_time'],
                (string)$entry['end_time'],
                0
            )) {
                throw new RuntimeException('The cloned lesson conflicts with an existing room booking.');
            }
            if ($this->repository->conflict(
                'class_id',
                (int)$entry['class_id'],
                $date,
                (string)$entry['start_time'],
                (string)$entry['end_time'],
                0
            )) {
                throw new RuntimeException('The cloned lesson conflicts with an existing class lesson.');
            }

            $newId=$this->repository->cloneForDate($id,$date);

            if($this->audit) {
                $this->audit->log(
                    'create',$newId,null,
                    ['cloned_from'=>$id,'date'=>$date]
                );
            }

            $this->pdo->commit();
            return $newId;
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
