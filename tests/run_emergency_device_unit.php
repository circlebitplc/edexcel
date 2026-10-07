<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\EmergencyDeviceAccessService;
use Edexcel\Services\StudentDeviceService;

$failed = 0;
$passed = 0;

function expect_true(bool $ok, string $label): void
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, deleted_at TEXT, is_active INT)');
$pdo->exec("INSERT INTO users (id, username, role, deleted_at, is_active) VALUES (7, 'Kasun Perera', 'student', NULL, 1)");
$pdo->exec('CREATE TABLE online_meetings (id INTEGER PRIMARY KEY AUTOINCREMENT, timetable_id INT, status TEXT, public_id TEXT)');

$svc = new StudentDeviceService($pdo, static function (): bool {
    return true;
});
$emergency = new EmergencyDeviceAccessService($pdo);

function meeting(PDO $pdo, int $lessonId, string $status): void
{
    $pdo->prepare('DELETE FROM online_meetings WHERE timetable_id = ?')->execute([$lessonId]);
    $pdo->prepare('INSERT INTO online_meetings (timetable_id, status, public_id) VALUES (?, ?, ?)')
        ->execute([$lessonId, $status, bin2hex(random_bytes(4))]);
}

function context(int $lessonId, int $secondsUntilEnd, int $teacherId = 5, int $substituteId = 0): array
{
    $end = time() + $secondsUntilEnd;
    return [
        'timetable_id' => $lessonId,
        'meeting_id' => $lessonId,
        'meeting_status' => 'live',
        'enrolled' => true,
        'teacher_id' => $teacherId,
        'substitute_teacher_id' => $substituteId,
        'teacher_name' => 'Nimal Silva',
        'class_label' => 'IAL Physics',
        'lesson_date' => date('Y-m-d', $end),
        'end_time' => date('H:i:s', $end),
        'student_name' => 'Kasun Perera',
    ];
}

function actor(string $role, int $userId, int $teacherId = 0): array
{
    return [
        'user_id' => $userId,
        'role' => $role,
        'is_admin' => $role === 'admin',
        'is_teacher' => $role === 'teacher',
        'teacher_id' => $teacherId,
        'name' => $role === 'admin' ? 'Admin' : ($role === 'teacher' ? 'Teacher' : 'Kasun'),
    ];
}

meeting($pdo, 10, 'live');
$tokenA = bin2hex(random_bytes(32));
$_COOKIE[StudentDeviceService::COOKIE] = $tokenA;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
unset($_SESSION['student_device_choice']);
$svc->beginLogin(7, 'login_otp');
$first = $svc->beginLogin(7, 'login_otp');
$sessionA = $svc->activateSession(7, (int)$first['device_id']);
$_SESSION['student_session_token'] = $sessionA;
expect_true($svc->verifiedCount(7) === 1, 'A setup has one device');
$gateA = $svc->liveClassGate(7, 10);
expect_true(!empty($gateA['ok']) && ($gateA['code'] ?? '') !== 'emergency', 'A one device joins the live class normally');

$tokenB = bin2hex(random_bytes(32));
$_COOKIE[StudentDeviceService::COOKIE] = $tokenB;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0.0.0 Mobile Safari/537.36';
$second = $svc->beginLogin(7, 'registration');
expect_true(($second['status'] ?? '') === 'ok', 'B second device registers');
$sessionB = $svc->activateSession(7, (int)$second['device_id']);
$_SESSION['student_session_token'] = $sessionB;
expect_true($svc->verifiedCount(7) === 2, 'B two devices stay registered');
$gateB = $svc->liveClassGate(7, 10);
expect_true(!empty($gateB['ok']) && ($gateB['code'] ?? '') !== 'emergency', 'B both registered devices can join');

$tokenC = bin2hex(random_bytes(32));
$_COOKIE[StudentDeviceService::COOKIE] = $tokenC;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/121.0';
$_SESSION['student_device_choice'] = 'device_limit';
$created = $emergency->requestAccess(7, context(10, 3600));
expect_true(($created['status'] ?? '') === 'pending' && empty($created['duplicate']), 'C third device during a live class creates one request');
expect_true($svc->verifiedCount(7) === 2, 'C the request does not register a third device');
$gateC = $svc->liveClassGate(7, 10);
expect_true(empty($gateC['ok']), 'C no LiveKit access before approval');
$again = $emergency->requestAccess(7, context(10, 3600));
expect_true(!empty($again['duplicate']) && ($again['message'] ?? '') === 'Your request is already waiting for approval.', 'C a repeat attempt waits on the same request');
$count = (int)$pdo->query('SELECT COUNT(*) FROM emergency_device_requests')->fetchColumn();
expect_true($count === 1, 'C only one pending request is stored');
$expiresIn = strtotime((string)$created['expires_at']) - time();
expect_true($expiresIn > 90 && $expiresIn <= 120, 'C the server request expires in two minutes');

$teacher = actor('teacher', 3, 5);
$admin = actor('admin', 2);
$otherTeacher = actor('teacher', 4, 9);
expect_true(count($emergency->pendingForActor($teacher)) === 1, 'D the assigned teacher receives the request');
expect_true(count($emergency->pendingForActor($otherTeacher)) === 0, 'D a teacher from another class does not receive it');
expect_true(count($emergency->pendingForActor($admin)) === 1, 'E an admin receives the request');

$studentActor = actor('student', 7, 5);
$studentActor['is_admin'] = true;
$forged = $emergency->decide((int)$created['request_id'], 'approved', $studentActor);
expect_true(empty($forged['ok']), 'M a student cannot approve the request');
$selfAdmin = actor('admin', 7);
$forgedAdmin = $emergency->decide((int)$created['request_id'], 'approved', $selfAdmin);
expect_true(empty($forgedAdmin['ok']), 'M the student cannot approve by claiming another role');
$outsider = $emergency->decide((int)$created['request_id'], 'approved', $otherTeacher);
expect_true(empty($outsider['ok']), 'N a teacher from another class cannot approve');
$still = $emergency->latestForCurrentDevice(7, 10);
expect_true(($still['status'] ?? '') === 'pending', 'M N the request stays pending after rejected decisions');

$allowed = $emergency->decide((int)$created['request_id'], 'approved', $teacher);
$deniedLate = $emergency->decide((int)$created['request_id'], 'denied', $admin);
expect_true(!empty($allowed['ok']) && ($allowed['status'] ?? '') === 'approved', 'O the first decision is stored');
expect_true(empty($deniedLate['ok']), 'O the later decision is rejected');
$gateF = $svc->liveClassGate(7, 10);
expect_true(!empty($gateF['ok']) && ($gateF['code'] ?? '') === 'emergency', 'F teacher approval lets this device into this class');
expect_true($svc->verifiedCount(7) === 2, 'F approval does not raise the device limit');
$general = $svc->liveClassGate(7, 0);
expect_true(empty($general['ok']), 'F temporary access is not a general login');
$approvedRow = $emergency->latestForCurrentDevice(7, 10);
$accessUntil = strtotime((string)($approvedRow['access_until'] ?? ''));
expect_true($accessUntil !== false && abs($accessUntil - (time() + 3600)) < 5, 'F access lasts until the class ends');

meeting($pdo, 11, 'live');
$longEnd = time() + 5 * 3600;
$long = context(11, 5 * 3600);
$longRequest = $emergency->requestAccess(7, $long);
$adminOk = $emergency->decide((int)$longRequest['request_id'], 'approved', $admin);
$gateG = $svc->liveClassGate(7, 11);
$longRow = $emergency->latestForCurrentDevice(7, 11);
$longUntil = strtotime((string)($longRow['access_until'] ?? ''));
expect_true(!empty($adminOk['ok']) && !empty($gateG['ok']), 'G an admin approval allows the same temporary access');
expect_true($longUntil !== false && abs($longUntil - (time() + EmergencyDeviceAccessService::ACCESS_CAP_SECONDS)) < 5, 'G access is capped at three hours');
expect_true($svc->verifiedCount(7) === 2, 'G the permanent limit is still 2');

meeting($pdo, 12, 'live');
$denyRequest = $emergency->requestAccess(7, context(12, 3600));
$teacherDeny = $emergency->decide((int)$denyRequest['request_id'], 'denied', $teacher);
expect_true(!empty($teacherDeny['ok']) && empty($svc->liveClassGate(7, 12)['ok']), 'H a teacher denial does not create a token');

meeting($pdo, 13, 'live');
$adminDenyRequest = $emergency->requestAccess(7, context(13, 3600));
$adminDeny = $emergency->decide((int)$adminDenyRequest['request_id'], 'denied', $admin);
expect_true(!empty($adminDeny['ok']) && empty($svc->liveClassGate(7, 13)['ok']), 'I an admin denial does not create a token');

meeting($pdo, 14, 'live');
$expireRequest = $emergency->requestAccess(7, context(14, 3600));
$pdo->prepare('UPDATE emergency_device_requests SET expires_at = ? WHERE id = ?')
    ->execute([date('Y-m-d H:i:s', time() - 5), (int)$expireRequest['request_id']]);
$late = $emergency->decide((int)$expireRequest['request_id'], 'approved', $teacher);
$expiredRow = $emergency->latestForCurrentDevice(7, 14);
expect_true(empty($late['ok']) && ($expiredRow['status'] ?? '') === 'expired', 'J an unanswered request expires and stays blocked');
expect_true(empty($svc->liveClassGate(7, 14)['ok']), 'J expiry does not create a token');

meeting($pdo, 10, 'ended');
expect_true(empty($svc->liveClassGate(7, 10)['ok']), 'K ending the class removes the temporary approval');
meeting($pdo, 15, 'live');
$endedRequest = $emergency->requestAccess(7, context(15, 3600));
meeting($pdo, 15, 'ended');
$approveLate = $emergency->decide((int)$endedRequest['request_id'], 'approved', $admin);
expect_true(empty($approveLate['ok']) && ($approveLate['status'] ?? '') !== 'approved', 'K approval after the class ends is refused');
expect_true(empty($svc->liveClassGate(7, 15)['ok']), 'K a late approval does not create a token');

meeting($pdo, 11, 'live');
expect_true(empty($svc->liveClassGate(7, 99)['ok']), 'L approval for one class does not open another class');

$history = $emergency->history('', 50);
$statuses = array_column($history, 'status');
expect_true(in_array('approved', $statuses, true) && in_array('denied', $statuses, true) && in_array('expired', $statuses, true), 'P history keeps approved, denied, and expired requests');
$approvedHistory = $emergency->history('approved', 20);
expect_true(($approvedHistory[0]['decided_by_role'] ?? '') !== '' && ($approvedHistory[0]['student_name'] ?? '') === 'Kasun Perera', 'P history shows who approved the request');
$beforeDelete = (int)$pdo->query('SELECT COUNT(*) FROM emergency_device_requests')->fetchColumn();
$emergency->history('all', 20);
expect_true((int)$pdo->query('SELECT COUNT(*) FROM emergency_device_requests')->fetchColumn() === $beforeDelete, 'P history is not deleted');

$tokenBlocked = bin2hex(random_bytes(32));
$blockedKey = StudentDeviceService::hashToken($tokenBlocked);
$now = date('Y-m-d H:i:s');
$pdo->prepare("
    INSERT INTO student_devices (
        user_id, device_key, label, verified_at, revoked_at, status, blocked_until, first_seen_at, created_at
    ) VALUES (7, ?, 'Old phone', ?, ?, 'REPLACED', ?, ?, ?)
")->execute([$blockedKey, $now, $now, date('Y-m-d H:i:s', time() + 86400), $now, $now]);
$_COOKIE[StudentDeviceService::COOKIE] = $tokenBlocked;
meeting($pdo, 16, 'live');
$blockedRequest = $emergency->requestAccess(7, context(16, 3600));
expect_true(($blockedRequest['status'] ?? '') === 'unavailable', 'a blocked device cannot open an emergency request');
$pdo->prepare("
    INSERT INTO emergency_device_requests (
        user_id, student_name, device_key, device_label, timetable_id, lesson_teacher_id,
        class_label, teacher_name, lesson_date, end_time, status, requested_at, expires_at,
        decided_at, access_until, created_at
    ) VALUES (7, 'Kasun Perera', ?, 'Old phone', 16, 5, 'IAL Physics', 'Nimal Silva', ?, ?, 'approved', ?, ?, ?, ?, ?)
")->execute([
    $blockedKey,
    date('Y-m-d'),
    date('H:i:s', time() + 3600),
    $now,
    date('Y-m-d H:i:s', time() + 120),
    date('Y-m-d H:i:s', time() + 3600),
    $now,
    $now,
]);
expect_true($emergency->allows(7, 16, $blockedKey) === false, 'a blocked device cannot use a forged approval');
expect_true(empty($svc->liveClassGate(7, 16)['ok']), 'the live class gate still blocks that device');

meeting($pdo, 17, 'live');
$_COOKIE[StudentDeviceService::COOKIE] = $tokenC;
$substituteRequest = $emergency->requestAccess(7, context(17, 3600, 5, 8));
$substitute = $emergency->decide((int)$substituteRequest['request_id'], 'approved', actor('teacher', 8, 8));
expect_true(!empty($substitute['ok']), 'the substitute for that class can approve');
expect_true(empty($emergency->decide((int)$substituteRequest['request_id'], 'denied', $otherTeacher)['ok']), 'O a second response still loses');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
