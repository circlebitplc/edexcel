<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class TimetableBulkService
{
    public function __construct(
        private TimetablePaymentService $payment,
        private TimetableDeleteService $delete,
        private TimetableLockService $lock
    ) {}

    public function execute(string $action,array $ids,callable $authorize): array
    {
        $ids=array_values(array_unique(array_filter(
            array_map('intval',$ids),static fn(int $id):bool=>$id>0
        )));
        if(!$ids) throw new RuntimeException('No timetable entries were selected.');
        if(count($ids)>500) throw new RuntimeException('Too many timetable entries were selected.');

        return match($action) {
            'mark_paid'=>$this->payment->markPaidBulk($ids,$authorize),
            'delete'=>['changed'=>$this->delete->deleteBulk($ids,$authorize),'notifications'=>[]],
            'lock'=>['changed'=>$this->lock->lockBulk($ids,$authorize),'notifications'=>[]],
            'unlock'=>['changed'=>$this->lock->unlockBulk($ids,$authorize),'notifications'=>[]],
            default=>throw new RuntimeException('Invalid bulk action.')
        };
    }
}
