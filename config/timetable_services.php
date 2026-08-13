<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/payment.php';
require_once __DIR__ . '/../src/Services/TimetableAuditLogger.php';
require_once __DIR__ . '/../src/Services/TimetableInputValidator.php';
require_once __DIR__ . '/../src/Repositories/TimetableRepository.php';
require_once __DIR__ . '/../src/Repositories/RecurringScheduleRepository.php';
require_once __DIR__ . '/../src/Services/RecurringScheduleService.php';
require_once __DIR__ . '/../src/Services/TimetableConflictService.php';
require_once __DIR__ . '/../src/Services/TimetableService.php';
require_once __DIR__ . '/../src/Services/TimetableCreateService.php';
require_once __DIR__ . '/../src/Services/TimetableDeleteService.php';
require_once __DIR__ . '/../src/Services/TimetableLockService.php';
require_once __DIR__ . '/../src/Services/TimetablePaymentService.php';
require_once __DIR__ . '/../src/Services/TimetableStudentCountService.php';
require_once __DIR__ . '/../src/Services/TimetableCloneService.php';
require_once __DIR__ . '/../src/Services/TimetableBulkService.php';

use App\Repositories\RecurringScheduleRepository;
use App\Repositories\TimetableRepository;
use App\Services\RecurringScheduleService;
use App\Services\TimetableAuditLogger;
use App\Services\TimetableConflictService;
use App\Services\TimetableService;
use App\Services\TimetableCreateService;
use App\Services\TimetableDeleteService;
use App\Services\TimetableLockService;
use App\Services\TimetablePaymentService;
use App\Services\TimetableStudentCountService;
use App\Services\TimetableCloneService;
use App\Services\TimetableBulkService;

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
