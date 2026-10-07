<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

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
$pdo->exec("INSERT INTO users (id, username, role, deleted_at, is_active) VALUES (7, '94771234567', 'student', NULL, 1)");

$codes = [];
$svc = new StudentDeviceService($pdo, static function (string $phone, string $otp) use (&$codes): bool {
    $codes[] = ['phone' => $phone, 'otp' => $otp];
    return true;
});

expect_true(StudentDeviceService::MAX_DEVICES === 2, 'max devices is 2');
expect_true(
    StudentDeviceService::deviceLabel('Mozilla/5.0 (Linux; Android 14) Chrome/120.0.0.0 Mobile Safari/537.36') === 'Chrome on Android',
    'labels Android Chrome'
);
expect_true(
    StudentDeviceService::deviceLabel('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1') === 'Safari on iPhone',
    'labels iPhone Safari'
);

$tokenA = bin2hex(random_bytes(32));
$_COOKIE[StudentDeviceService::COOKIE] = $tokenA;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';
$needsSms = $svc->beginLogin(7, 'password');
expect_true(($needsSms['status'] ?? '') === 'otp', 'password login on an unknown device requires SMS');
$_COOKIE[StudentDeviceService::COOKIE] = $tokenA;
$first = $svc->beginLogin(7, 'login_otp');
expect_true(($first['status'] ?? '') === 'ok' && (int)($first['device_id'] ?? 0) > 0, 'first device auto-registers after phone OTP');
$sessionA = $svc->activateSession(7, (int)$first['device_id']);
$svc->onSignedIn(7, (int)$first['device_id'], 'login_otp', true);
expect_true($svc->presenceValid(), 'phone OTP at login unlocks live class for a few hours');
expect_true($svc->sessionIsCurrent(7), 'first session is current');
expect_true($svc->verifiedCount(7) === 1, 'one registered device');

$tokenB = bin2hex(random_bytes(32));
$_COOKIE[StudentDeviceService::COOKIE] = $tokenB;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0.0.0 Mobile Safari/537.36';
$second = $svc->beginLogin(7, 'password');
expect_true(($second['status'] ?? '') === 'otp', 'new device requires SMS OTP');
expect_true($codes !== [], 'SMS OTP was sent');
$otp = (string)($codes[array_key_last($codes)]['otp'] ?? '');
$verify = $svc->verifyDeviceOtp(7, $otp);
expect_true(($verify['status'] ?? '') === 'ok', 'device OTP registers the new device');
$sessionB = $svc->activateSession(7, (int)$verify['device_id']);
expect_true($sessionA !== $sessionB, 'new login issues a new session token');
$_SESSION['student_session_token'] = $sessionA;
expect_true(!$svc->sessionIsCurrent(7), 'old device session is rejected immediately');
$_SESSION['student_session_token'] = $sessionB;
expect_true($svc->sessionIsCurrent(7), 'new device session is accepted');
expect_true($svc->verifiedCount(7) === 2, 'two registered devices');

$idA = (int)$first['device_id'];
$idB = (int)$verify['device_id'];
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_COOKIE[StudentDeviceService::COOKIE] = $tokenB;
$sameTabs = $svc->beginLogin(7, 'password');
$sameTabsAgain = $svc->beginLogin(7, 'password');
expect_true((int)($sameTabs['device_id'] ?? 0) === $idB && (int)($sameTabsAgain['device_id'] ?? 0) === $idB, 'multiple tabs stay on one device');
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
$afterIp = $svc->beginLogin(7, 'password');
expect_true((int)($afterIp['device_id'] ?? 0) === $idB, 'a network change keeps the same device');
$ipNow = $pdo->prepare('SELECT ip_address FROM student_devices WHERE id = ?');
$ipNow->execute([$idB]);
expect_true((string)$ipNow->fetchColumn() === '198.51.100.20', 'the new IP is only audit information');

$_COOKIE[StudentDeviceService::COOKIE] = $tokenA;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';
$againA = $svc->beginLogin(7, 'password');
expect_true((int)($againA['device_id'] ?? 0) === $idA, 'the same browser does not create another device');

$_COOKIE[StudentDeviceService::COOKIE] = bin2hex(random_bytes(32));
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';
$third = $svc->beginLogin(7, 'google');
expect_true(($third['status'] ?? '') === 'device_limit', 'a third device is not registered immediately');
expect_true($svc->verifiedCount(7) === 2, 'the third attempt does not consume a slot');
$replaced = $svc->replaceWithCurrent(7, $idA);
expect_true(($replaced['status'] ?? '') === 'ok', 'the student can deactivate a chosen device');
expect_true($svc->verifiedCount(7) === 2, 'the new device takes the freed slot');
$oldA = $pdo->prepare('SELECT status, blocked_until, revoked_at FROM student_devices WHERE id = ?');
$oldA->execute([$idA]);
$oldRow = $oldA->fetch(PDO::FETCH_ASSOC);
expect_true(($oldRow['status'] ?? '') === 'REPLACED' && trim((string)($oldRow['revoked_at'] ?? '')) !== '', 'the old device is replaced and revoked');
$until = strtotime((string)($oldRow['blocked_until'] ?? ''));
expect_true($until !== false && $until > time() + 13 * 86400 && $until < time() + 15 * 86400, 'the old device is blocked for 14 days');

$_COOKIE[StudentDeviceService::COOKIE] = $tokenA;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';
$blockedLogin = $svc->beginLogin(7, 'google');
expect_true(($blockedLogin['status'] ?? '') === 'device_blocked', 'the replaced device cannot register again during the block');
expect_true(str_contains((string)($blockedLogin['message'] ?? ''), 'cannot be used again until'), 'the block message includes the end time');

$_COOKIE[StudentDeviceService::COOKIE] = bin2hex(random_bytes(32));
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120.0';
$fourth = $svc->beginLogin(7, 'google');
expect_true(($fourth['status'] ?? '') === 'device_limit', 'another new device is still refused while two are active');

$_SESSION['student_session_token'] = 'stolen';
$_COOKIE[StudentDeviceService::COOKIE] = bin2hex(random_bytes(32));
$joinGate = $svc->liveClassGate(7);
expect_true(empty($joinGate['ok']), 'the live class token gate rejects an unregistered device');

$notice = $svc->replacementNotice(7, ['id' => $idA, 'label' => 'Chrome on Windows', 'last_seen_at' => date('Y-m-d H:i:s')], (int)$replaced['device_id']);
expect_true(str_contains($notice, 'Device replacement detected') && str_contains($notice, 'Old device blocked until'), 'admin replacement notice is ready');

$_COOKIE[StudentDeviceService::COOKIE] = $tokenB;
$knownAgain = $svc->beginLogin(7, 'password');
expect_true(($knownAgain['status'] ?? '') === 'ok', 'known device signs in without another OTP');
unset($_SESSION['student_presence_until']);
$svc->onSignedIn(7, (int)$knownAgain['device_id'], 'password', false);
expect_true(!$svc->presenceValid(), 'password login alone does not unlock live class');
$codesBeforeKnown = count($codes);
$presenceKnown = $svc->startPresenceOtp(7);
expect_true(($presenceKnown['status'] ?? '') === 'ok' && $svc->presenceValid(), 'known device unlocks live class without SMS');
expect_true(count($codes) === $codesBeforeKnown, 'known device does not send a presence SMS');

$tokenUnknown = bin2hex(random_bytes(32));
$_COOKIE[StudentDeviceService::COOKIE] = $tokenUnknown;
unset($_SESSION['student_presence_until']);
$presence = $svc->startPresenceOtp(7);
expect_true(($presence['status'] ?? '') === 'otp', 'unknown device still needs SMS confirmation');
$presenceOtp = (string)($codes[array_key_last($codes)]['otp'] ?? '');
$presenceOk = $svc->verifyPresenceOtp(7, $presenceOtp);
expect_true(($presenceOk['status'] ?? '') === 'ok' && $svc->presenceValid(), 'SMS confirmation unlocks live class');
$_COOKIE[StudentDeviceService::COOKIE] = $tokenB;

$_COOKIE[StudentDeviceService::COOKIE] = $tokenB;

$svc->revokeAll(7);
$_SESSION['student_session_token'] = 'stale';
$afterReset = $svc->beginLogin(7, 'existing_session');
expect_true(($afterReset['status'] ?? '') !== 'ok', 'reset devices does not silently re-bind a live PHP session');
expect_true($svc->enforceExistingSession(7) === false, 'existing session is rejected after device reset');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
