<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TimetableRepository;
use PDO;
use RuntimeException;

final class TimetableLockService
{
    public function __construct(
        private TimetableRepository $repository,
        private PDO $pdo,
        private ?TimetableAuditLogger $audit=null
    ) {}

    public function toggle(int $id,callable $authorize): array
    {
        $this->pdo->beginTransaction();
        try {
            $entry=$this->repository->findForUpdate($id);
            if (!$entry) throw new RuntimeException('Lesson not found.');
            if (!empty($entry['deleted_at'])) throw new RuntimeException('This lesson has already been deleted.');
            $authorize($entry);
            $new=(int)$entry['is_locked']===1?0:1;
            $this->repository->setLockState($id,$new);
            if ($this->audit) $this->audit->log($new?'lock':'unlock',$id,$entry,['is_locked'=>$new]);
            $this->pdo->commit();
            return ['id'=>$id,'is_locked'=>$new,'status'=>$new?'locked':'unlocked'];
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function lockBulk(array $ids,callable $authorize): int { return $this->bulk($ids,1,$authorize); }
    public function unlockBulk(array $ids,callable $authorize): int { return $this->bulk($ids,0,$authorize); }

    private function bulk(array $ids,int $state,callable $authorize): int
    {
        $changed=0;
        $this->pdo->beginTransaction();
        try {
            foreach($ids as $id) {
                $entry=$this->repository->findForUpdate((int)$id);
                if(!$entry || !empty($entry['deleted_at'])) continue;
                $authorize($entry);
                if((int)$entry['is_locked']===$state) continue;
                $this->repository->setLockState((int)$id,$state);
                if($this->audit) $this->audit->log($state?'lock':'unlock',(int)$id,$entry,['is_locked'=>$state]);
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
