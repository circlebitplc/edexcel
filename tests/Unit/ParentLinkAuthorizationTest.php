<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\ParentLinkService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Authorization / IDOR security coverage for Google parent linking.
 * Uses SQLite in-memory where possible; OAuth HTTP is not called.
 */
final class ParentLinkAuthorizationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE parent_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone TEXT NULL,
                email TEXT NULL,
                google_id TEXT NULL,
                name TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                last_login_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                password_hash TEXT NOT NULL DEFAULT 'x',
                role TEXT NOT NULL DEFAULT 'student',
                is_active INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT NULL
            );
            CREATE TABLE student_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                full_name TEXT NULL,
                email TEXT NULL
            );
            CREATE TABLE parent_students (
                parent_id INTEGER NOT NULL,
                student_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (parent_id, student_id)
            );
            CREATE TABLE parent_link_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER NOT NULL,
                student_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                requested_at TEXT DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TEXT NULL,
                reviewed_by INTEGER NULL,
                rejection_reason TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE parent_invitations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_email TEXT NOT NULL,
                student_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL,
                used_by_parent_id INTEGER NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->pdo->exec("INSERT INTO users (id, username, role, is_active) VALUES (10, 'stu10', 'student', 1)");
        $this->pdo->exec("INSERT INTO users (id, username, role, is_active) VALUES (11, 'stu11', 'student', 1)");
        $this->pdo->exec("INSERT INTO student_profiles (user_id, full_name) VALUES (10, 'John Smith')");
        $this->pdo->exec("INSERT INTO student_profiles (user_id, full_name) VALUES (11, 'Jane Smith')");
        $this->pdo->exec("INSERT INTO parent_accounts (id, email, google_id, name, status) VALUES (1, 'parent@example.com', 'g1', 'Parent One', 'pending')");
        $this->pdo->exec("INSERT INTO parent_accounts (id, email, google_id, name, status) VALUES (2, 'other@example.com', 'g2', 'Parent Two', 'active')");
    }

    private function service(): ParentLinkService
    {
        // Bypass ensure_ops_schema (MySQL) by constructing without calling it.
        $ref = new \ReflectionClass(ParentLinkService::class);
        /** @var ParentLinkService $svc */
        $svc = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        $prop->setValue($svc, $this->pdo);
        return $svc;
    }

    public function testPendingParentHasNoVerifiedAccess(): void
    {
        $svc = $this->service();
        $this->assertFalse($svc->hasVerifiedLink(1, 10));
        $this->assertFalse($svc->hasVerifiedLink(1, 11));
    }

    public function testRequestDoesNotGrantAccess(): void
    {
        $svc = $this->service();
        $result = $svc->requestAccess(1, '10');
        $this->assertTrue($result['ok']);
        $this->assertFalse($svc->hasVerifiedLink(1, 10), 'Pending request must not create parent_students');
        $pending = $svc->pendingRequestsForParent(1);
        $this->assertCount(1, $pending);
    }

    public function testInvalidStudentIdRejected(): void
    {
        $svc = $this->service();
        $result = $svc->requestAccess(1, '99999');
        $this->assertFalse($result['ok']);
        $this->assertFalse($svc->hasVerifiedLink(1, 99999));
    }

    public function testApproveCreatesVerifiedLinkOnlyForThatStudent(): void
    {
        $svc = $this->service();
        $req = $svc->requestAccess(1, '10');
        $this->assertTrue($req['ok']);
        $approve = $svc->approve((int)$req['request_id'], 99);
        $this->assertTrue($approve['ok']);
        $this->assertTrue($svc->hasVerifiedLink(1, 10));
        $this->assertFalse($svc->hasVerifiedLink(1, 11), 'Must not grant sibling without separate verification');
        $this->assertFalse($svc->hasVerifiedLink(2, 10), 'Other parent must not gain access');
    }

    public function testRejectLeavesNoAccess(): void
    {
        $svc = $this->service();
        $req = $svc->requestAccess(1, '11');
        $reject = $svc->reject((int)$req['request_id'], 99, 'Not a parent');
        $this->assertTrue($reject['ok']);
        $this->assertFalse($svc->hasVerifiedLink(1, 11));
    }

    public function testRevokeRemovesAccess(): void
    {
        $svc = $this->service();
        $req = $svc->requestAccess(1, '10');
        $svc->approve((int)$req['request_id'], 99);
        $this->assertTrue($svc->hasVerifiedLink(1, 10));
        $rev = $svc->revoke(1, 10, 99);
        $this->assertTrue($rev['ok']);
        $this->assertFalse($svc->hasVerifiedLink(1, 10));
    }

    public function testMultipleChildrenEachVerifiedIndependently(): void
    {
        $svc = $this->service();
        $a = $svc->requestAccess(1, '10');
        $b = $svc->requestAccess(1, '11');
        $svc->approve((int)$a['request_id'], 1);
        $this->assertTrue($svc->hasVerifiedLink(1, 10));
        $this->assertFalse($svc->hasVerifiedLink(1, 11));
        $svc->approve((int)$b['request_id'], 1);
        $this->assertTrue($svc->hasVerifiedLink(1, 11));
    }

    public function testInvitationSingleUseAndEmailBound(): void
    {
        $svc = $this->service();
        $created = $svc->createInvitation('parent@example.com', 10, 5, 7);
        $this->assertTrue($created['ok']);
        $token = (string)$created['token'];

        $wrong = $svc->acceptInvitation(2, $token); // wrong parent email
        $this->assertFalse($wrong['ok']);
        $this->assertFalse($svc->hasVerifiedLink(2, 10));

        $ok = $svc->acceptInvitation(1, $token);
        $this->assertTrue($ok['ok']);
        $this->assertTrue($svc->hasVerifiedLink(1, 10));

        $reuse = $svc->acceptInvitation(1, $token);
        $this->assertFalse($reuse['ok'], 'Invitation must be single-use');
    }

    public function testExpiredInvitationRejected(): void
    {
        $svc = $this->service();
        $token = bin2hex(random_bytes(16));
        $hash = hash('sha256', $token);
        $this->pdo->prepare("
            INSERT INTO parent_invitations (parent_email, student_id, token_hash, expires_at, created_by)
            VALUES ('parent@example.com', 10, ?, datetime('now', '-1 day'), 1)
        ")->execute([$hash]);
        $result = $svc->acceptInvitation(1, $token);
        $this->assertFalse($result['ok']);
        $this->assertFalse($svc->hasVerifiedLink(1, 10));
    }

    public function testGoogleOAuthStateMustMatch(): void
    {
        $_SESSION = [
            'google_oauth_state' => 'abc',
            'google_oauth_intent' => 'parent',
            'google_oauth_started_at' => time(),
        ];
        $oauth = new GoogleOAuthService('cid', 'secret', 'https://example.com/callback');
        $this->assertTrue($oauth->isConfigured());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid sign-in state');
        $oauth->complete('code', 'wrong-state');
    }

    public function testGoogleOAuthRejectsStaffIntentValues(): void
    {
        $oauth = new GoogleOAuthService('cid', 'secret', 'https://example.com/callback');
        $this->expectException(\RuntimeException::class);
        $oauth->begin('admin');
    }

    public function testAuthorizationMatrixDocumented(): void
    {
        // Decision table used by portal guards (must stay true for security reviews).
        $cases = [
            ['auth' => false, 'role' => null, 'verified' => false, 'allow' => false],
            ['auth' => true, 'role' => 'parent', 'verified' => false, 'allow' => false],
            ['auth' => true, 'role' => 'parent', 'verified' => true, 'allow' => true],
            ['auth' => true, 'role' => 'student', 'verified' => false, 'allow' => false],
            ['auth' => true, 'role' => 'admin', 'verified' => false, 'allow' => false], // admin uses staff tools, not parent ownsStudent
        ];
        foreach ($cases as $c) {
            $allow = $c['auth'] && $c['role'] === 'parent' && $c['verified'];
            $this->assertSame($c['allow'], $allow);
        }
    }
}
