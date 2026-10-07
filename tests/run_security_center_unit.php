<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\SecurityEventService;
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

$ttl = SecurityEventService::tokenLifetime(7200, time() + 1800);
expect_true($ttl === 2400, 'a token ends with the class plus a short grace period');
$long = SecurityEventService::tokenLifetime(20000, null);
expect_true($long === 7200, 'a token is never longer than two hours');
$floor = SecurityEventService::tokenLifetime(30, time() + 60);
expect_true($floor === 600, 'a token is not shorter than ten minutes');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, deleted_at TEXT, is_active INT)');
$pdo->exec("INSERT INTO users (id, username, role) VALUES (7, 'Kasun Perera', 'student')");
$svc = new StudentDeviceService($pdo, static fn (): bool => true);
$security = new SecurityEventService($pdo);

$now = date('Y-m-d H:i:s');
$keyA = hash('sha256', 'device-a');
$keyB = hash('sha256', 'device-b');
$insert = $pdo->prepare("
    INSERT INTO student_devices (
        user_id, device_key, label, platform, browser, session_token, verified_at, status, last_seen_at, first_seen_at, created_at
    ) VALUES (7, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?)
");
$insert->execute([$keyA, 'Chrome on Windows', 'Windows', 'Chrome', 'token-a', $now, $now, $now, $now]);
$idA = (int)$pdo->lastInsertId();
$insert->execute([$keyB, 'Chrome on Android', 'Android', 'Chrome', 'token-b', $now, $now, $now, $now]);
$idB = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO student_active_sessions (user_id, session_token, device_id, updated_at) VALUES (7, ?, ?, ?)')
    ->execute(['token-a', $idA, $now]);
$_SESSION['student_device_id'] = $idA;
$_SESSION['student_session_token'] = 'token-a';
$cleared = $svc->logoutOtherDevices(7);
$tokenLeft = $pdo->query("SELECT session_token FROM student_devices WHERE id = $idB")->fetchColumn();
$tokenKept = $pdo->query("SELECT session_token FROM student_devices WHERE id = $idA")->fetchColumn();
$statusKept = $pdo->query("SELECT status FROM student_devices WHERE id = $idB")->fetchColumn();
expect_true($cleared === 1 && $tokenLeft === null && $tokenKept === 'token-a', 'logging out other devices keeps this device');
expect_true($statusKept === 'ACTIVE', 'logging out does not delete the other device');

$revoked = $svc->revokeActiveSession(7);
$sessions = (int)$pdo->query('SELECT COUNT(*) FROM student_active_sessions')->fetchColumn();
expect_true($revoked && $sessions === 0, 'an admin can end the live session');
expect_true((int)$pdo->query("SELECT COUNT(*) FROM student_devices WHERE status = 'ACTIVE'")->fetchColumn() === 2, 'revoking a session keeps both device registrations');

$svc->onSignedIn(7, $idA, 'google', false);
$svc->onSignedIn(7, $idA, 'google', false);
$suspicious = (int)$pdo->query("SELECT COUNT(*) FROM security_events WHERE event_code = 'SUSPICIOUS_ACTIVITY'")->fetchColumn();
expect_true($suspicious === 1, 'simultaneous device use is recorded once');

$security->record('LOGIN_FAILED', ['user_id' => 7, 'result' => 'denied', 'message' => 'Sign-in was refused']);
$mine = $security->forStudent(7, 20);
$codes = array_column($mine, 'event_code');
expect_true(!in_array('LOGIN_FAILED', $codes, true), 'a student does not see internal login-failure details');
expect_true(in_array('SUSPICIOUS_ACTIVITY', $codes, true) === false, 'a student does not see the internal suspicious-activity record');
$before = (int)$pdo->query('SELECT COUNT(*) FROM security_events')->fetchColumn();
$security->recent('', 10);
expect_true((int)$pdo->query('SELECT COUNT(*) FROM security_events')->fetchColumn() === $before, 'security history is not deleted when it is viewed');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
