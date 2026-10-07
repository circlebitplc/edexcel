<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Services/TimetableInputValidator.php';

use Edexcel\Services\TimetableInputValidator;

$valid=[
 'teacher_id'=>1,'subject_id'=>1,'class_id'=>1,'room_id'=>1,
 'date'=>'2026-08-20','start_time'=>'10:00','end_time'=>'12:00',
 'student_count'=>10,'payment_status'=>'pending'
];

TimetableInputValidator::validate($valid);

foreach(['2026-02-30','2026-13-01','2026-00-10','not-a-date'] as $badDate) {
    $data=$valid;
    $data['date']=$badDate;
    $failed=false;
    try { TimetableInputValidator::validate($data); }
    catch(Throwable $e) { $failed=true; }
    if(!$failed) {
        fwrite(STDERR,"Invalid date accepted: {$badDate}\n");
        exit(2);
    }
}

$repo=file_get_contents(__DIR__.'/../src/Repositories/TimetableRepository.php');
foreach(['NOT EXISTS','existing.deleted_at IS NULL','LIMIT ? OFFSET ?'] as $needle) {
    if(strpos($repo,$needle)===false) {
        fwrite(STDERR,"Repository safety marker missing: {$needle}\n");
        exit(3);
    }
}

$security=file_get_contents(__DIR__.'/../config/security.php');
foreach(["'httponly' => true","'samesite' => 'Lax'"] as $needle) {
    if(strpos($security,$needle)===false) {
        fwrite(STDERR,"Session security marker missing: {$needle}\n");
        exit(4);
    }
}

echo "Step 78 regression tests passed.\n";
