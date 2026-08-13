<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetableDeleteService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function delete(int $id,callable $authorize): void
    {
        $this->pdo->beginTransaction();
        try {
            $entry=$this->repository->findForUpdate($id);
            if (!$entry) throw new RuntimeException('Entry not found.');
            if (!empty($entry['deleted_at'])) throw new RuntimeException('Entry already deleted.');
            if (!empty($entry['is_locked'])) throw new RuntimeException('This entry is locked and cannot be deleted.');
            $authorize($entry);
            $this->repository->softDelete($id);
            if ($this->audit) $this->audit->log('delete',$id,$entry,['deleted_at'=>date('Y-m-d H:i:s')]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteBulk(array $ids,callable $authorize): int
    {
        $changed=0;
        $this->pdo->beginTransaction();
        try {
            foreach($ids as $id) {
                $entry=$this->repository->findForUpdate((int)$id);
                if(!$entry || !empty($entry['deleted_at'])) continue;
                if(!empty($entry['is_locked'])) continue;
                $authorize($entry);
                $this->repository->softDelete((int)$id);
                if($this->audit) $this->audit->log('delete',(int)$id,$entry,['deleted_at'=>date('Y-m-d H:i:s')]);
                $changed++;
            }
            $this->pdo->commit();
            return $changed;
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
