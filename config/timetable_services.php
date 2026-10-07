<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Repositories\RecurringScheduleRepository;
use Edexcel\Repositories\TimetableRepository;
use Edexcel\Services\RecurringScheduleService;
use Edexcel\Services\TimetableAuditLogger;
use Edexcel\Services\TimetableConflictService;
use Edexcel\Services\TimetableService;
use Edexcel\Services\TimetableCreateService;
use Edexcel\Services\TimetableDeleteService;
use Edexcel\Services\TimetableLockService;
use Edexcel\Services\TimetablePaymentService;
use Edexcel\Services\TimetableStudentCountService;
use Edexcel\Services\TimetableCloneService;
use Edexcel\Services\TimetableBulkService;

final class TimetableServiceFactory
{
    public static function services(PDO $pdo): array
    {
        $repo=new TimetableRepository($pdo);
        $recRepo=new RecurringScheduleRepository($pdo);
        $conf=new TimetableConflictService($repo);
        $audit=new TimetableAuditLogger($pdo);
        $rec=new RecurringScheduleService($recRepo,$pdo,$audit);

        return [
            'repository'=>$repo,
            'conflict'=>$conf,
            'recurring'=>$rec,
            'audit'=>$audit,
            'update'=>new TimetableService($repo,$pdo,$conf,$rec,$audit),
            'create'=>new TimetableCreateService($repo,$pdo,$conf,$rec,$audit),
            'delete'=>new TimetableDeleteService($repo,$pdo,$audit),
            'lock'=>new TimetableLockService($repo,$pdo,$audit),
            'student_count'=>new TimetableStudentCountService($repo,$pdo,$audit),
            'clone'=>new TimetableCloneService($repo,$pdo,$audit),
            'payment'=>new TimetablePaymentService(
                $repo,$pdo,
                isset($GLOBALS['FEE_PER_STUDENT_LIVE'])
                    ? (float)$GLOBALS['FEE_PER_STUDENT_LIVE']
                    : 500.0,
                $audit
            )
        ];
    }

    public static function bulk(PDO $pdo): TimetableBulkService
    {
        $s=self::services($pdo);
        return new TimetableBulkService(
            $s['payment'],$s['delete'],$s['lock']
        );
    }
}
