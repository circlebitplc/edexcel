<?php
declare(strict_types=1);

/**
 * Local checks for the admin Google link, student lookup gate, SMS secret
 * selection, and private upload path jail. Does not print secrets.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../src/Services/SecureUploadService.php';

use Edexcel\Services\SecureUploadService;
use Edexcel\Services\TeacherMigrationService;

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

$start = (string)file_get_contents(__DIR__ . '/../auth/google/start.php');
expect_true(!str_contains($start, "\$_GET['user_id']"), 'admin link start does not read a user id from the query string');
expect_true(str_contains($start, 'admin_linking_staff_ready'), 'admin link start requires the same-session ready flag');
expect_true(str_contains($start, 'eligibleStaffLinkTarget'), 'admin link start rechecks that the target is staff');

$migrationPage = (string)file_get_contents(__DIR__ . '/../admin/teacher_oauth_migration.php');
expect_true(str_contains($migrationPage, 'begin_staff_google_link'), 'admin page posts the staff link');
expect_true(!str_contains($migrationPage, 'intent=admin_link_teacher&user_id='), 'admin page does not build a GET staff-link URL');
expect_true(str_contains($migrationPage, 'verify_csrf_token'), 'admin page checks the CSRF token');

$lookup = (string)file_get_contents(__DIR__ . '/../ajax/lookup_student.php');
expect_true(str_contains($lookup, 'campus_staff_may_lookup_student'), 'student id lookup checks authorization');
expect_true(str_contains($lookup, "'found' => false"), 'unauthorized lookup uses the same not-found response');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        role TEXT NOT NULL,
        deleted_at TEXT NULL
    )
");
$pdo->exec("INSERT INTO users (id, username, role, deleted_at) VALUES
    (1, 'teacher1', 'teacher', NULL),
    (2, 'student1', 'student', NULL),
    (3, 'admin1', 'admin', NULL),
    (4, 'gone', 'teacher', '2026-01-01')
");
$migration = new TeacherMigrationService($pdo);
expect_true(($migration->eligibleStaffLinkTarget(1)['role'] ?? '') === 'teacher', 'a teacher account can be linked');
expect_true(($migration->eligibleStaffLinkTarget(3)['role'] ?? '') === 'admin', 'an administrator account can be linked');
expect_true($migration->eligibleStaffLinkTarget(2) === null, 'a student account cannot be selected as a staff link target');
expect_true($migration->eligibleStaffLinkTarget(4) === null, 'a deleted staff account cannot be linked');
expect_true($migration->eligibleStaffLinkTarget(99) === null, 'a missing account cannot be linked');

$_GET = ['secret' => 'query-value'];
$_SERVER['HTTP_X_SMS_WEBHOOK_SECRET'] = 'header-value';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer bearer-value';
$headerWins = sms_webhook_provided_secret();
expect_true($headerWins === 'header-value', 'SMS header secret is preferred over the query string');
unset($_SERVER['HTTP_X_SMS_WEBHOOK_SECRET']);
$bearerWins = sms_webhook_provided_secret();
expect_true($bearerWins === 'bearer-value', 'SMS bearer secret is used when no header is present');
unset($_SERVER['HTTP_AUTHORIZATION']);
$queryFallback = sms_webhook_provided_secret();
expect_true($queryFallback === 'query-value', 'SMS query secret remains only as a fallback');
expect_true(hash_equals('header-value', 'header-value'), 'secret comparison can use hash_equals');
$_GET = [];
$_SERVER['HTTP_X_SMS_WEBHOOK_SECRET'] = '';
$_SERVER['HTTP_AUTHORIZATION'] = '';

$uploads = new SecureUploadService();
expect_true($uploads->resolveStoredFile('../../.env') === null, 'upload resolver rejects a parent path');
expect_true($uploads->resolveStoredFile('storage/private_uploads/../../.env') === null, 'upload resolver rejects a nested parent path');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
