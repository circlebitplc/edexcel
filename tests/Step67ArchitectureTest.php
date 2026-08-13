<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$required=[
 'config/bootstrap.php',
 'config/timetable_services.php',
 'src/Repositories/TimetableRepository.php',
 'src/Repositories/RecurringScheduleRepository.php',
 'src/Services/TimetableService.php',
 'src/Services/TimetableCreateService.php',
 'src/Services/TimetableDeleteService.php',
 'src/Services/TimetableLockService.php',
 'src/Services/TimetablePaymentService.php',
 'src/Services/TimetableBulkService.php',
 'src/Services/TimetableStudentCountService.php',
 'src/Services/TimetableCloneService.php',
 'src/Services/TimetableConflictService.php',
 'src/Services/RecurringScheduleService.php',
 'src/Services/TimetableInputValidator.php',
];

foreach($required as $file) {
    if(!is_file($root.'/'.$file)) {
        fwrite(STDERR,"MISSING {$file}\n"); exit(2);
    }
}

$targets=[
 'ajax/update_entry.php','ajax/delete_entry.php','ajax/mark_paid.php',
 'ajax/toggle_lock.php','ajax/bulk_action.php','ajax/update_students.php',
 'ajax/ajax_clone_entry.php','timetable/add.php','timetable/edit.php',
 'timetable/delete.php','timetable/toggle_lock.php',
 'timetable/recurring_manage.php','api/timetable.php'
];

foreach($targets as $file) {
    $s=file_get_contents($root.'/'.$file);
    if(strpos($s,'bootstrap.php')===false && $file!=='ajax/ajax_update_students.php') {
        fwrite(STDERR,"BOOTSTRAP MISSING {$file}\n"); exit(3);
    }
    if(preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+[`]?timetable\b/i',$s)) {
        fwrite(STDERR,"DIRECT TIMETABLE MUTATION {$file}\n"); exit(4);
    }
}

$repo=file_get_contents($root.'/src/Repositories/RecurringScheduleRepository.php');
if(strpos($repo,'FOR UPDATE')===false) {
    fwrite(STDERR,"RECURRING ROW LOCK MISSING\n"); exit(5);
}

echo "Step 67 architecture tests passed.\n";
