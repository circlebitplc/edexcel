<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\UserDeletionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserDeletionServiceTest extends TestCase
{
    private PDO $pdo;
    private UserDeletionService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                password_hash TEXT NOT NULL DEFAULT 'hash123',
                role TEXT NOT NULL DEFAULT 'student',
                teacher_id INTEGER NULL,
                google_id TEXT NULL,
                google_email TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                account_status TEXT NOT NULL DEFAULT 'active',
                teacher_oauth_status TEXT NOT NULL DEFAULT 'not_linked',
                teacher_oauth_notes TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );

            CREATE TABLE student_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                full_name TEXT NOT NULL,
                email TEXT NULL,
                whatsapp_number TEXT NULL,
                parent_view_token TEXT NULL
            );

            CREATE TABLE teachers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NULL,
                email TEXT NULL,
                deleted_at TEXT NULL
            );

            CREATE TABLE parent_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NULL,
                email TEXT NULL,
                phone TEXT NULL,
                google_id TEXT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                table_name TEXT NULL,
                record_id INTEGER NULL,
                details TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE student_attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NOT NULL
            );

            CREATE TABLE student_enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NULL,
                teacher_id INTEGER NULL
            );

            CREATE TABLE student_fee_ledger (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NOT NULL
            );

            CREATE TABLE timetable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                teacher_id INTEGER NOT NULL
            );

            CREATE TABLE student_active_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL
            );

            CREATE TABLE student_devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL
            );

            CREATE TABLE teacher_oauth_invites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                used_at TEXT NULL
            );
        ");

        $this->service = new UserDeletionService($this->pdo);

        // Seed base records
        // ID 1: Primary System Admin
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_email, is_active, account_status)
            VALUES (1, 'primary_admin', 'admin', 'admin@edexcel.college', 1, 'active');
        ");
    }

    public function testSearchUsersByUsernameEmailAndName(): void
    {
        // Add a teacher and a student
        $this->pdo->exec("
            INSERT INTO teachers (id, name, email, phone) VALUES (10, 'Dr. Sarah Connor', 'sarah@edexcel.lk', '0771122334');
            INSERT INTO users (id, username, role, teacher_id, google_email) VALUES (2, 'sarah_teacher', 'teacher', 10, 'sarah.oauth@gmail.com');

            INSERT INTO users (id, username, role, google_email) VALUES (3, 'john_doe', 'student', 'john.doe@gmail.com');
            INSERT INTO student_profiles (user_id, full_name, email, whatsapp_number) VALUES (3, 'Johnathan Doe', 'john.school@edexcel.lk', '0779988776');
        ");

        // Search by username
        $byUser = $this->service->searchUsers('sarah_teacher');
        $this->assertCount(1, $byUser);
        $this->assertSame(2, $byUser[0]['user_id']);
        $this->assertSame('Teacher', $byUser[0]['role_label']);

        // Search by email
        $byEmail = $this->service->searchUsers('john.doe@gmail.com');
        $this->assertCount(1, $byEmail);
        $this->assertSame(3, $byEmail[0]['user_id']);
        $this->assertSame('Student', $byEmail[0]['role_label']);

        // Search by partial name
        $byName = $this->service->searchUsers('Johnathan');
        $this->assertCount(1, $byName);
        $this->assertSame(3, $byName[0]['user_id']);

        // Empty query returns empty
        $this->assertEmpty($this->service->searchUsers('   '));

        // Non-existent search returns empty
        $this->assertEmpty($this->service->searchUsers('nonexistent_user_9999'));
    }

    public function testMultipleMatchingUsersAreReturned(): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_email) VALUES (4, 'alex_smith', 'student', 'alex1@example.com');
            INSERT INTO student_profiles (user_id, full_name) VALUES (4, 'Alex Smith');

            INSERT INTO users (id, username, role, google_email) VALUES (5, 'alex_jones', 'student', 'alex2@example.com');
            INSERT INTO student_profiles (user_id, full_name) VALUES (5, 'Alex Jones');
        ");

        $results = $this->service->searchUsers('alex');
        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function testSelfDeletionIsBlocked(): void
    {
        // Admin user ID 2 attempting to delete user ID 2
        $this->pdo->exec("
            INSERT INTO users (id, username, role, is_active) VALUES (2, 'secondary_admin', 'admin', 1);
        ");

        $can = $this->service->canDeleteUser(2, 2);
        $this->assertFalse($can['allowed']);
        $this->assertStringContainsString('own administrator account', $can['reason']);
    }

    public function testPrimaryAdminProtectionIsEnforced(): void
    {
        // Admin user ID 2 attempting to delete primary admin (ID 1)
        $this->pdo->exec("
            INSERT INTO users (id, username, role, is_active) VALUES (2, 'secondary_admin', 'admin', 1);
        ");

        $can = $this->service->canDeleteUser(1, 2);
        $this->assertFalse($can['allowed']);
        $this->assertStringContainsString('primary system administrator account (ID #1) is protected', $can['reason']);
    }

    public function testFinalAdminDeletionIsBlocked(): void
    {
        // There is only 1 admin in the system (ID 1). Even if caller were different, deleting last admin is blocked.
        // Let's create user 3 as temporary caller
        $this->pdo->exec("
            INSERT INTO users (id, username, role, is_active) VALUES (3, 'staff_caller', 'admin', 1);
        ");
        // Now there are 2 admins (1 and 3).
        // Let's deactivate admin 1 to test when 3 becomes the sole active admin:
        $this->pdo->exec("UPDATE users SET is_active = 0 WHERE id = 1");

        // Now admin 3 is the only active admin. Trying to delete admin 3 is self-deletion,
        // but trying to delete any sole active admin is blocked:
        $can = $this->service->canDeleteUser(3, 1);
        $this->assertFalse($can['allowed']);
        $this->assertStringContainsString('final active administrator', $can['reason']);
    }

    public function testConfirmationIdentifierMismatchIsRejected(): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, username, role, google_email) VALUES (10, 'student_bob', 'student', 'bob@gmail.com');
            INSERT INTO student_profiles (user_id, full_name, email) VALUES (10, 'Bob Vance', 'bob@edexcel.lk');
        ");

        // Wrong identifier provided
        $res = $this->service->deleteUser(10, 1, 'wrong_username');
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Confirmation check failed', $res['message']);

        // Confirm account was not modified
        $check = $this->service->getUserDetails(10);
        $this->assertNotNull($check);
        $this->assertSame('student_bob', $check['username']);
        $this->assertFalse($check['is_deleted']);
    }

    public function testSuccessfulStudentDeletionAndCredentialPurging(): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, username, password_hash, role, google_id, google_email, is_active, account_status)
            VALUES (20, 'student_alice', 'secret_hash', 'student', 'google_alice_123', 'alice@gmail.com', 1, 'active');
            
            INSERT INTO student_profiles (user_id, full_name, email, parent_view_token)
            VALUES (20, 'Alice Wonderland', 'alice@edexcel.lk', 'token123');

            INSERT INTO student_active_sessions (user_id) VALUES (20);
            INSERT INTO student_devices (user_id) VALUES (20);
            INSERT INTO student_attendance (student_id) VALUES (20);
            INSERT INTO student_fee_ledger (student_id) VALUES (20);
        ");

        // Verify impact before deletion
        $impact = $this->service->getRelatedRecordsImpact(20, 'student');
        $this->assertGreaterThanOrEqual(2, $impact['total_retained_count']);

        // Delete with username confirmation
        $res = $this->service->deleteUser(20, 1, 'student_alice', 'Student graduated and requested data privacy');
        $this->assertTrue($res['ok']);
        $this->assertStringContainsString('successfully removed', $res['message']);

        // Verify user row is sanitized
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = 20");
        $stmt->execute();
        $deletedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($deletedUser['deleted_at']);
        $this->assertSame(0, (int)$deletedUser['is_active']);
        $this->assertSame('disabled', $deletedUser['account_status']);
        $this->assertNull($deletedUser['google_id']);
        $this->assertNull($deletedUser['google_email']);
        $this->assertStringStartsWith('*DELETED*', $deletedUser['password_hash']);
        $this->assertStringContainsString('_del_', $deletedUser['username']);

        // Verify active sessions and devices were purged
        $sessCount = (int)$this->pdo->query("SELECT COUNT(*) FROM student_active_sessions WHERE user_id = 20")->fetchColumn();
        $this->assertSame(0, $sessCount);

        $devCount = (int)$this->pdo->query("SELECT COUNT(*) FROM student_devices WHERE user_id = 20")->fetchColumn();
        $this->assertSame(0, $devCount);

        // Verify attendance and financial ledger records were PRESERVED
        $attCount = (int)$this->pdo->query("SELECT COUNT(*) FROM student_attendance WHERE student_id = 20")->fetchColumn();
        $this->assertSame(1, $attCount);

        $feeCount = (int)$this->pdo->query("SELECT COUNT(*) FROM student_fee_ledger WHERE student_id = 20")->fetchColumn();
        $this->assertSame(1, $feeCount);

        // Verify audit log entry was created
        $audit = $this->pdo->query("SELECT * FROM audit_logs WHERE record_id = 20 AND action = 'admin_delete_user'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($audit);
        $this->assertSame(1, (int)$audit['user_id']);
        $this->assertStringContainsString('credentials_purged_and_sessions_terminated', $audit['details']);
    }

    public function testAlreadyDeletedAccountCannotBeDeletedAgain(): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, username, role, deleted_at, is_active, account_status)
            VALUES (30, 'already_gone_del_12345', 'student', '2026-01-01 00:00:00', 0, 'disabled');
        ");

        $can = $this->service->canDeleteUser(30, 1);
        $this->assertFalse($can['allowed']);
        $this->assertStringContainsString('already deleted', $can['reason']);
    }
}

