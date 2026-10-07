<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\GoogleAuthAccountService;
use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\TeacherMigrationService;
use PDO;
use PHPUnit\Framework\TestCase;

if (!function_exists('regenerate_session')) {
    function regenerate_session(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }
}
if (!function_exists('clear_cross_portal_session')) {
    function clear_cross_portal_session(?string $targetPortal = null): void {}
}

final class StaffGoogleOAuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
            $this->pdo->sqliteCreateFunction('CONCAT', static fn(...$args) => implode('', $args));
        }
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                password_hash TEXT NOT NULL DEFAULT 'x',
                role TEXT NOT NULL DEFAULT 'teacher',
                email TEXT NULL,
                google_id TEXT NULL,
                google_email TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                account_status TEXT NOT NULL DEFAULT 'active',
                teacher_id INTEGER NULL,
                teacher_oauth_status TEXT NOT NULL DEFAULT 'not_linked',
                teacher_oauth_linked_at TEXT NULL,
                teacher_oauth_linked_by INTEGER NULL,
                teacher_oauth_notes TEXT NULL,
                last_login_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE teachers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NULL,
                email TEXT NULL,
                photo TEXT NULL
            );
            CREATE TABLE parent_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NULL,
                google_id TEXT NULL
            );
            CREATE TABLE settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT
            );
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT NOT NULL,
                table_name TEXT NULL,
                record_id INTEGER NULL,
                user_id INTEGER NULL,
                ip_address TEXT NULL,
                details TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE teacher_oauth_invites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expected_email TEXT NULL,
                expires_at TEXT NOT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                used_at TEXT NULL
            );
        ");

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testGoogleOAuthServiceAllowsStaffAndAdminIntents(): void
    {
        $svc = new GoogleOAuthService('client-id-123', 'client-secret-abc', 'https://edexcel.college/auth/google/callback.php');
        
        $staffUrl = $svc->begin('staff');
        $this->assertStringContainsString('accounts.google.com', $staffUrl);
        $this->assertSame('staff', $_SESSION['google_oauth_intent']);

        $adminLinkUrl = $svc->begin('link_admin');
        $this->assertStringContainsString('accounts.google.com', $adminLinkUrl);
        $this->assertSame('link_admin', $_SESSION['google_oauth_intent']);
    }

    public function testUnlinkedStaffGoogleLoginIsRejectedWithHelpfulMessage(): void
    {
        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => '1000000000001',
            'email' => 'unlinked.staff@gmail.com',
            'name' => 'Unknown Staff',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('No staff account is linked', $res['message']);
        $this->assertStringContainsString('contact the college administrator', $res['message']);
    }

    public function testLinkedStaffGoogleLoginSucceedsAndRedirectsToDashboard(): void
    {
        // Insert a teacher linked to Google
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_id, google_email, teacher_oauth_status, is_active)
            VALUES (2, 'john_teacher', 'teacher', '10998877665544', 'john.teacher@gmail.com', 'linked', 1)
        ");

        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => '10998877665544',
            'email' => 'john.teacher@gmail.com',
            'name' => 'John Teacher',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertTrue($res['ok']);
        $this->assertSame('/dashboard.php', $res['redirect']);
        $this->assertSame(2, (int)$_SESSION['user_id']);
        $this->assertSame('teacher', $_SESSION['role']);
    }

    public function testLinkedAdminGoogleLoginSucceedsAndRedirectsToDashboard(): void
    {
        // Insert an admin linked to Google
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_id, google_email, teacher_oauth_status, is_active)
            VALUES (1, 'admin', 'admin', '10111213141516', 'principal@gmail.com', 'linked', 1)
        ");

        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => '10111213141516',
            'email' => 'principal@gmail.com',
            'name' => 'College Admin',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertTrue($res['ok']);
        $this->assertSame('/dashboard.php', $res['redirect']);
        $this->assertSame(1, (int)$_SESSION['user_id']);
        $this->assertSame('admin', $_SESSION['role']);
    }

    public function testStudentCannotSignInViaStaffLogin(): void
    {
        // Insert student with matching Google ID
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_id, google_email, is_active)
            VALUES (10, 'student_sam', 'student', '9988776655', 'sam.student@gmail.com', 1)
        ");

        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => '9988776655',
            'email' => 'sam.student@gmail.com',
            'name' => 'Sam Student',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('student portal account', $res['message']);
    }

    public function testInactiveStaffAccountIsDenied(): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_id, google_email, teacher_oauth_status, is_active)
            VALUES (5, 'inactive_teacher', 'teacher', '5555555555', 'inactive@gmail.com', 'linked', 0)
        ");

        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => '5555555555',
            'email' => 'inactive@gmail.com',
            'name' => 'Inactive Teacher',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('inactive', $res['message']);
    }

    public function testStaffLegacyLoginToggleControlsStatus(): void
    {
        $migration = new TeacherMigrationService($this->pdo);

        // Initially enabled (0)
        $this->assertFalse($migration->isLegacyLoginDisabled());

        // Toggle to disabled
        $res = $migration->setLegacyLoginDisabled(true, 1, true);
        $this->assertTrue($res['ok']);
        $this->assertTrue($migration->isLegacyLoginDisabled());

        // Toggle back to enabled
        $res = $migration->setLegacyLoginDisabled(false, 1, true);
        $this->assertTrue($res['ok']);
        $this->assertFalse($migration->isLegacyLoginDisabled());
    }

    public function testTeacherForInviteRetrieval(): void
    {
        $this->pdo->exec("
            INSERT INTO teachers (id, name, phone, email)
            VALUES (101, 'Prof. Robert Taylor', '0771234567', 'robert@edexcel.lk');
            INSERT INTO users (id, username, role, teacher_id)
            VALUES (20, 'robert_t', 'teacher', 101);
        ");

        $migration = new TeacherMigrationService($this->pdo);
        $teacher = $migration->getTeacherForInvite(20);

        $this->assertNotNull($teacher);
        $this->assertSame('Prof. Robert Taylor', $teacher['teacher_name']);
        $this->assertSame('0771234567', $teacher['teacher_phone']);
        $this->assertSame('robert@edexcel.lk', $teacher['teacher_email']);
        $this->assertSame('robert_t', $teacher['username']);

        $url = $migration->resolveAppUrl();
        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('http', $url);
    }

    public function testUnlinkedTeacherWithProfileEmailAutoLinksAndLogsIn(): void
    {
        // Simulate Yashini: teacher row has email, users row has no google_id / google_email
        $this->pdo->exec("
            INSERT INTO teachers (id, name, phone, email)
            VALUES (12, 'Yashini Ekanayake', '+94729335577', 'yashiniravishani@gmail.com');
            INSERT INTO users (id, username, role, teacher_id, is_active, teacher_oauth_status)
            VALUES (13, 'yashini', 'teacher', 12, 1, 'not_linked');
        ");

        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => 'google_yashini_987654',
            'email' => 'yashiniravishani@gmail.com',
            'name' => 'Yashini Ekanayake',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertTrue($res['ok']);
        $this->assertSame('/dashboard.php', $res['redirect']);
        $this->assertSame(13, (int)$_SESSION['user_id']);
        $this->assertSame('teacher', $_SESSION['role']);
        $this->assertSame(12, (int)($_SESSION['teacher_id'] ?? 0));

        // Verify DB was automatically updated with Google ID and linked status
        $user = $this->pdo->query("SELECT * FROM users WHERE id = 13")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('google_yashini_987654', $user['google_id']);
        $this->assertSame('yashiniravishani@gmail.com', $user['google_email']);
        $this->assertSame('linked', $user['teacher_oauth_status']);
    }

    public function testStaffLoginReleasesConflictingGoogleIdFromDeletedAccount(): void
    {
        // Simulate exact bug from screenshot: deleted student user 102 holds the Google ID
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_id, google_email, is_active, deleted_at)
            VALUES (102, 'yashini_student', 'student', '109305432878139383497', 'yashiniravishani@gmail.com', 1, '2026-09-16 11:43:31');

            INSERT INTO teachers (id, name, email)
            VALUES (12, 'Yashini Ekanayake', 'yashiniravishani@gmail.com');

            INSERT INTO users (id, username, role, teacher_id, is_active, teacher_oauth_status)
            VALUES (13, 'yashini', 'teacher', 12, 1, 'not_linked');
        ");

        $service = new GoogleAuthAccountService($this->pdo);

        $profile = [
            'google_id' => '109305432878139383497',
            'email' => 'yashiniravishani@gmail.com',
            'name' => 'Yashini Ekanayake',
            'picture' => '',
            'intent' => 'staff',
            'invite' => null,
        ];

        $res = $service->handle($profile);

        $this->assertTrue($res['ok']);
        $this->assertSame('/dashboard.php', $res['redirect']);
        $this->assertSame(13, (int)$_SESSION['user_id']);

        // Check user 102 had google_id cleared
        $stmt102 = $this->pdo->prepare("SELECT google_id, google_email FROM users WHERE id = 102");
        $stmt102->execute();
        $u102 = $stmt102->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($u102['google_id']);

        // Check user 13 successfully acquired google_id
        $stmt13 = $this->pdo->prepare("SELECT google_id, google_email, teacher_oauth_status FROM users WHERE id = 13");
        $stmt13->execute();
        $u13 = $stmt13->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('109305432878139383497', $u13['google_id']);
        $this->assertSame('linked', $u13['teacher_oauth_status']);
    }

    public function testRecordSmsDispatchUpdatesUserNotesAndAuditTrail(): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, username, role, teacher_oauth_notes)
            VALUES (50, 'sarah_teacher', 'teacher', 'Existing note');
        ");

        $migration = new TeacherMigrationService($this->pdo);
        $migration->recordSmsDispatch(50, '+94771234567', 'GW_MSG_9988', 1);

        $stmt = $this->pdo->prepare("SELECT teacher_oauth_notes FROM users WHERE id = 50");
        $stmt->execute();
        $notes = (string)$stmt->fetchColumn();

        $this->assertStringContainsString('Existing note', $notes);
        $this->assertStringContainsString('+94771234567', $notes);
        $this->assertStringContainsString('GW_MSG_9988', $notes);
        $this->assertStringContainsString('Admin #1', $notes);

        $stmtAudit = $this->pdo->prepare("SELECT * FROM audit_logs WHERE action = 'teacher_oauth_sms_sent' AND record_id = 50");
        $stmtAudit->execute();
        $audit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($audit);
        $this->assertStringContainsString('GW_MSG_9988', $audit['details']);
    }

    public function testGetSmsGatewayStatusDetectsConfiguration(): void
    {
        $migration = new TeacherMigrationService($this->pdo);

        // Initially without settings
        $status = $migration->getSmsGatewayStatus();
        $this->assertIsArray($status);
        $this->assertFalse($status['ready']);

        // Set gateway settings
        $this->pdo->exec("
            INSERT INTO settings (setting_key, setting_value) VALUES
            ('sms_gateway_username', 'TEST_USER'),
            ('sms_gateway_password', 'TEST_PASS'),
            ('sms_gateway_url', 'https://api.sms-gate.app/3rdparty/v1');
        ");

        $statusConfigured = $migration->getSmsGatewayStatus();
        $this->assertTrue($statusConfigured['ready']);
        $this->assertSame('TEST_USER', $statusConfigured['username']);
        $this->assertStringContainsString('online', $statusConfigured['message']);
    }
}


