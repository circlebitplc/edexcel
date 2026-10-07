<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Services/TimetableInputValidator.php';

use Edexcel\Services\TimetableInputValidator;

$base=[
 'teacher_id'=>1,'subject_id'=>1,'class_id'=>1,'room_id'=>1,
 'date'=>'2026-08-20','start_time'=>'10:00','end_time'=>'12:00',
 'student_count'=>10,'payment_status'=>'pending'
];

TimetableInputValidator::validate($base);

$cases=[
 ['date'=>'bad','message'=>'Invalid timetable date.'],
 ['start_time'=>'12:00','end_time'=>'10:00','message'=>'Start time must be before end time.'],
 ['student_count'=>-1,'message'=>'Student count cannot be negative.'],
 ['payment_status'=>'x','message'=>'Invalid payment status.'],
];

foreach($cases as $case) {
    $data=$base;
    unset($case['message']);
    $data=array_merge($data,$case);
    $thrown=false;
    try { TimetableInputValidator::validate($data); }
    catch(Throwable $e) { $thrown=true; }
    if(!$thrown) {
        fwrite(STDERR,"Validator accepted invalid input.\n");
        exit(2);
    }
}

echo "Step 68 validation tests passed.\n";
