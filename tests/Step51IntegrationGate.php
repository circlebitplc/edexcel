<?php
declare(strict_types=1);

$root=dirname(__DIR__);

$required=[
 'config/timetable_services.php',
 'src/Repositories/TimetableRepository.php',
 'src/Repositories/RecurringScheduleRepository.php',
 'src/Services/TimetableService.php',
 'src/Services/TimetableCreateService.php',
 'src/Services/TimetableConflictService.php',
 'src/Services/TimetableDeleteService.php',
 'src/Services/TimetableLockService.php',
 'src/Services/TimetablePaymentService.php',
 'src/Services/TimetableBulkService.php',
 'src/Services/TimetableAuditLogger.php',
 'src/Services/RecurringScheduleService.php',
 'ajax/update_entry.php',
 'ajax/bulk_action.php',
 'ajax/mark_paid.php',
 'ajax/toggle_lock.php',
 'ajax/delete_entry.php',
];

foreach($required as $f) {
    if(!is_file($root.'/'.$f)) {
        fwrite(STDERR,"MISSING: {$f}\n");
        exit(2);
    }
}

foreach([
 'ajax/update_entry.php',
 'ajax/bulk_action.php',
 'ajax/mark_paid.php',
 'ajax/toggle_lock.php',
 'ajax/delete_entry.php'
] as $f) {
    $s=file_get_contents($root.'/'.$f);
    if(preg_match('/\bUPDATE\s+[`]?timetable\b/i',$s) ||
       preg_match('/\bDELETE\s+FROM\s+[`]?timetable\b/i',$s) ||
       preg_match('/\bINSERT\s+INTO\s+[`]?timetable\b/i',$s)) {
        fwrite(STDERR,"DIRECT SQL REMAINS: {$f}\n");
        exit(3);
    }
    if(
        strpos($s,'timetable_services.php')===false &&
        strpos($s,'bootstrap.php')===false
    ) {
        fwrite(STDERR,"SERVICE BOOTSTRAP NOT WIRED: {$f}\n");
        exit(4);
    }
}

$repo=file_get_contents($root.'/src/Repositories/TimetableRepository.php');
foreach(['findForUpdate','create','update','softDelete','setLockState','markPaid','conflict'] as $m) {
    if(strpos($repo,"function {$m}(")===false) {
        fwrite(STDERR,"Repository method missing: {$m}\n");
        exit(5);
    }
}

echo "Step 51 integration gate passed.\n";
