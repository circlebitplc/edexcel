<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetableStudentCountService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function update(int $id,int $count,callable $authorize): array
    {
        if($id<=0) throw new RuntimeException('Invalid lesson ID.');
        if($count<0) throw new RuntimeException('Student count cannot be negative.');

        $this->pdo->beginTransaction();
        try {
            $entry=$this->repository->findForUpdate($id);
            if(!$entry) throw new RuntimeException('Entry not found.');
            if(!empty($entry['deleted_at'])) throw new RuntimeException('Entry has been deleted.');
            if(!empty($entry['is_locked']) && !is_admin()) {
                throw new RuntimeException('Entry is locked.');
            }
            $authorize($entry);

            $old=(int)($entry['student_count']??0);
            $this->repository->updateStudentCount($id,$count);

            if($this->audit) {
                $this->audit->log(
                    'update_student_count',$id,
                    ['student_count'=>$old],
                    ['student_count'=>$count]
                );
            }

            $this->pdo->commit();
            return ['id'=>$id,'student_count'=>$count];
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
