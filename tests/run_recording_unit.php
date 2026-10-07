<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\OnePayCallbackHandler;
use Edexcel\Services\OnePayService;
use Edexcel\Services\PaymentVerificationService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\StudentLessonFeeService;

$failed = 0;
$passed = 0;

function expect_true(bool $ok, string $label) : void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo " PASS  {$label}\n";
        return;
    }
    $failed++;
    echo " FAIL  {$label}\n";
}

expect_true(
    RecordingAccessService::decide([
        'authenticated' => true,
        'recording_exists' => true,
        'recording_status' => 'ready',
        'lesson_exists' => true,
        'enrolled' => true,
        'fee_status' => 'paid',
    ]) === RecordingAccessService::ACCESS_GRANTED,
    'absent/present ignored: paid + ready = ACCESS_GRANTED'
);

expect_true(
    RecordingAccessService::decide([
        'authenticated' => true,
        'recording_exists' => true,
        'recording_status' => 'ready',
        'lesson_exists' => true,
        'enrolled' => true,
        'fee_status' => 'unpaid',
    ]) === RecordingAccessService::PAYMENT_REQUIRED,
    'present unpaid = PAYMENT_REQUIRED'
);

expect_true(
    RecordingAccessService::decide([
        'authenticated' => true,
        'recording_exists' => true,
        'recording_status' => 'ready',
        'lesson_exists' => true,
        'enrolled' => false,
        'fee_status' => 'paid',
    ]) === RecordingAccessService::NOT_AUTHORIZED,
    'wrong student = NOT_AUTHORIZED'
);

expect_true(
    RecordingAccessService::teacherCanManageLesson(7, 7, false) === true
    && RecordingAccessService::teacherCanManageLesson(7, 9, false) === false,
    'teacher own lesson allowed, other teacher rejected'
);

expect_true(
    RecordingAccessService::teacherOwnsLibraryItem(5, 8, false) === false,
    'teacher cannot see another library'
);

expect_true(PaymentVerificationService::isIdempotentPaid('paid'), 'duplicate paid callback is idempotent');
expect_true(!PaymentVerificationService::amountsMatch(500, 1), 'tampered amount rejected');
expect_true(!PaymentVerificationService::studentMatches(10, 11), 'tampered student rejected');
expect_true(!PaymentVerificationService::lessonMatches(4, 9), 'tampered lesson rejected');
expect_true(StudentLessonFeeService::isUnlocked('paid') === true, 'paid fee unlocks recording');
expect_true(StudentLessonFeeService::isUnlocked('waived') === true, 'waived fee unlocks recording');
expect_true(StudentLessonFeeService::isUnlocked('unpaid') === false, 'unpaid fee does not unlock');
expect_true(StudentLessonFeeService::isUnlocked('pending', true) === true, 'monthly wallet cover unlocks');
expect_true(StudentLessonFeeService::teacherMayCollect('unpaid', false) === true, 'teacher can collect unpaid');
expect_true(StudentLessonFeeService::teacherMayCollect('paid', false) === false, 'teacher cannot re-collect paid');
expect_true(StudentLessonFeeService::teacherMayCollect('pending', false) === true, 'teacher can collect over a pending OnePay');
expect_true(StudentLessonFeeService::teacherMayUnpay('paid', false) === true, 'teacher can unpay a paid lesson');
expect_true(StudentLessonFeeService::teacherMayUnpay('unpaid', false) === false, 'cannot unpay an unpaid lesson');
expect_true(StudentLessonFeeService::teacherMayUnpay('paid', true) === true, 'teacher can unpay even if monthly wallet covers');
expect_true(StudentLessonFeeService::teacherMayUnpay('waived', false, 0) === false, 'zero-fee waived cannot be unpaid');
expect_true(StudentLessonFeeService::isCoveredByMonthlyLedger('absent', 'paid') === false, 'absent + monthly paid is not a recording credit');
expect_true(StudentLessonFeeService::isCoveredByMonthlyLedger('present', 'paid') === true, 'present + monthly paid covers lesson');
expect_true(OnePayCallbackHandler::isSuccessCallback(['status' => 1, 'status_message' => 'SUCCESS']), 'OnePay success callback');
expect_true(
    OnePayService::hashFor('APPID', 'LKR', 100, 'SALT') === hash('sha256', 'APPIDLKR100.00SALT')
    && OnePayService::hashFor('APPID', 'LKR', '100.00', 'SALT') === OnePayService::hashFor('APPID', 'LKR', 100, 'SALT')
    && OnePayService::hashFor('APPID', 'LKR', '100.00', 'SALT') !== OnePayService::hashFor('APPID', 'LKR', '1.00', 'SALT'),
    'OnePay hash is sha256(app_id + currency + amount + salt) with 2 decimal amount'
);
expect_true(
    StudentLessonFeeService::timetableIdFrom(['id' => 1, 'timetable_id' => 206]) === 206,
    'recording row fee lookup uses lesson 206 not recording id 1'
);
expect_true(StudentLessonFeeService::timetableIdFrom(['id' => 206]) === 206, 'timetable row fee lookup uses id');
expect_true(StudentLessonFeeService::timetableIdFrom(['timetable_id' => 206]) === 206, 'catalogue row fee lookup uses timetable_id');
expect_true(BunnyVideoService::mapStatus(3) === 'ready', 'Bunny status 3 = ready');
expect_true(BunnyVideoService::mapStatus(7) === 'processing', 'Bunny status 7 upload-finished is still encoding');
expect_true(BunnyVideoService::mapStatus(4) === 'ready', 'Bunny status 4 resolution-finished is playable');
expect_true(BunnyVideoService::mapStatus(9) === null, 'Bunny captions event does not change recording status');
expect_true(BunnyVideoService::mapStatus(5) === 'failed', 'Bunny status 5 = failed');
expect_true(BunnyVideoService::statusFromPayload(['Status' => 3]) === 3, 'webhook Status capital S is read');
expect_true(
    \Edexcel\Services\TeacherBunnyLibraryService::libraryDisplayName('Jane Perera', 12) === 'Edexcel Kandy — Jane Perera (#12)',
    'teacher Bunny library name includes teacher id'
);
expect_true(
    \Edexcel\Services\TeacherBunnyLibraryService::cdnHostnameFromLibraryPayload([
        'PullZone' => ['Hostnames' => [['Value' => 'https://vz-abc.b-cdn.net/']]],
    ]) === 'vz-abc.b-cdn.net',
    'CDN hostname parsed from Bunny pull-zone payload'
);
expect_true(
    \Edexcel\Services\TeacherBunnyLibraryService::tokenKeyFromLibraryPayload(['TokenAuthenticationKey' => 'tok'], 'api') === 'tok'
    && \Edexcel\Services\TeacherBunnyLibraryService::tokenKeyFromLibraryPayload([], 'api') === 'api',
    'embed token key falls back to library AccessKey'
);

expect_true(
    RecordingAccessService::studentMessage(RecordingAccessService::PAYMENT_REQUIRED)
    === StudentLessonFeeService::paywallMessage(),
    'unpaid copy asks the student to Pay Now'
);
expect_true(
    \Edexcel\Services\BankTransferService::isAllowedSlip('image/jpeg', 'slip.jpg')
    && \Edexcel\Services\BankTransferService::isAllowedSlip('application/pdf', 'slip.pdf')
    && !\Edexcel\Services\BankTransferService::isAllowedSlip('application/x-php', 'slip.php')
    && !\Edexcel\Services\BankTransferService::isAllowedSlip('image/gif', 'slip.gif'),
    'bank slip allows jpg/png/pdf and rejects php/gif'
);
expect_true(
    StudentLessonFeeService::isUnlocked('pending') === false,
    'pending bank slip does not unlock class'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 2 : 0);
