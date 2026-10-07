<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Secure parent↔student linking: requests, invitations, admin approve/reject.
 * Verified access lives only in parent_students (existing table).
 * Pending Google parents never receive parent_students rows until approved.
 */
final class ParentLinkService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    /**
     * Resolve a student identifier used in the UI (numeric user id or username).
     *
     * @return array{id:int,username:string,name:string}|null
     */
    public function findStudentByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        $sql = "
            SELECT
                u.id,
                u.username,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS name
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.role = 'student'
              AND u.deleted_at IS NULL
              AND (
                    u.id = ?
                 OR u.username = ?
              )
            LIMIT 1
        ";
        $idHint = ctype_digit($identifier) ? (int)$identifier : 0;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idHint, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'name' => (string)$row['name'],
        ];
    }

    public function hasVerifiedLink(int $parentId, int $studentId): bool
    {
        if ($parentId < 1 || $studentId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM parent_students WHERE parent_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$parentId, $studentId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pendingRequestsForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS student_name,
                   u.username AS student_username
            FROM parent_link_requests r
            JOIN users u ON u.id = r.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE r.parent_id = ? AND r.status = 'pending'
            ORDER BY r.requested_at DESC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{ok:bool,message:string,request_id?:int}
     */
    public function requestAccess(int $parentId, string $studentIdentifier): array
    {
        if ($parentId < 1) {
            return ['ok' => false, 'message' => 'Not signed in.'];
        }
        $parent = $this->parentRow($parentId);
        if (!$parent) {
            return ['ok' => false, 'message' => 'Parent account not found.'];
        }
        $status = strtolower((string)($parent['status'] ?? 'active'));
        if (in_array($status, ['suspended', 'disabled'], true)) {
            return ['ok' => false, 'message' => 'This parent account cannot request student access.'];
        }

        $student = $this->findStudentByIdentifier($studentIdentifier);
        if (!$student) {
            return ['ok' => false, 'message' => 'No student matched that ID. Check the student ID and try again.'];
        }
        $studentId = $student['id'];

        if ($this->hasVerifiedLink($parentId, $studentId)) {
            return ['ok' => false, 'message' => 'You already have access to this student.'];
        }

        $open = $this->pdo->prepare("
            SELECT id FROM parent_link_requests
            WHERE parent_id = ? AND student_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $open->execute([$parentId, $studentId]);
        $existingId = (int)($open->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return [
                'ok' => true,
                'message' => 'Your access request is already awaiting approval.',
                'request_id' => $existingId,
            ];
        }

        try {
            $this->pdo->prepare("
                INSERT INTO parent_link_requests (parent_id, student_id, status, requested_at)
                VALUES (?, ?, 'pending', ?)
            ")->execute([$parentId, $studentId, date('Y-m-d H:i:s')]);
            $requestId = (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('parent link request: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not submit the request. Please try again.'];
        }

        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'parent_link_request', 'parent_link_requests', $requestId, null, [
                'parent_id' => $parentId,
                'student_id' => $studentId,
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Request submitted. An administrator must approve before you can view student information.',
            'request_id' => $requestId,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listOpenRequests(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->query("
            SELECT
                r.*,
                pa.name AS parent_name,
                pa.email AS parent_email,
                pa.phone AS parent_phone,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS student_name,
                u.username AS student_username,
                u.id AS student_user_id
            FROM parent_link_requests r
            JOIN parent_accounts pa ON pa.id = r.parent_id
            JOIN users u ON u.id = r.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE r.status = 'pending'
            ORDER BY r.requested_at ASC
            LIMIT {$limit}
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function approve(int $requestId, int $adminUserId): array
    {
        $req = $this->requestRow($requestId);
        if (!$req || ($req['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Request not found or already reviewed.'];
        }
        $parentId = (int)$req['parent_id'];
        $studentId = (int)$req['student_id'];

        try {
            $this->pdo->beginTransaction();
            $now = date('Y-m-d H:i:s');
            $this->pdo->prepare("
                UPDATE parent_link_requests
                SET status = 'approved', reviewed_at = ?, reviewed_by = ?, rejection_reason = NULL
                WHERE id = ? AND status = 'pending'
            ")->execute([$now, $adminUserId, $requestId]);
            $this->ensureVerifiedLink($parentId, $studentId);
            $this->pdo->prepare("
                UPDATE parent_accounts
                SET status = CASE
                    WHEN status IN ('suspended','disabled') THEN status
                    ELSE 'active'
                END
                WHERE id = ?
            ")->execute([$parentId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('parent link approve: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not approve the request.'];
        }

        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'parent_link_approve', 'parent_link_requests', $requestId, null, [
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'by' => $adminUserId,
            ]);
        }
        return ['ok' => true, 'message' => 'Parent access approved.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function reject(int $requestId, int $adminUserId, string $reason = ''): array
    {
        $req = $this->requestRow($requestId);
        if (!$req || ($req['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Request not found or already reviewed.'];
        }
        $reason = trim(mb_substr($reason, 0, 500));
        try {
            $this->pdo->prepare("
                UPDATE parent_link_requests
                SET status = 'rejected', reviewed_at = ?, reviewed_by = ?, rejection_reason = ?
                WHERE id = ? AND status = 'pending'
            ")->execute([
                date('Y-m-d H:i:s'),
                $adminUserId > 0 ? $adminUserId : null,
                $reason !== '' ? $reason : null,
                $requestId,
            ]);
        } catch (Throwable $e) {
            error_log('parent link reject: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not reject the request.'];
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'parent_link_reject', 'parent_link_requests', $requestId, null, [
                'parent_id' => (int)$req['parent_id'],
                'student_id' => (int)$req['student_id'],
                'by' => $adminUserId,
                'reason' => $reason,
            ]);
        }
        return ['ok' => true, 'message' => 'Request rejected.'];
    }

    /**
     * Revoke a verified parent↔student link (removes parent_students row).
     *
     * @return array{ok:bool,message:string}
     */
    public function revoke(int $parentId, int $studentId, int $adminUserId): array
    {
        if (!$this->hasVerifiedLink($parentId, $studentId)) {
            return ['ok' => false, 'message' => 'No verified relationship found.'];
        }
        $this->pdo->prepare('DELETE FROM parent_students WHERE parent_id = ? AND student_id = ?')
            ->execute([$parentId, $studentId]);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'parent_link_revoke', 'parent_students', $studentId, null, [
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'by' => $adminUserId,
            ]);
        }
        return ['ok' => true, 'message' => 'Parent access revoked.'];
    }

    /**
     * Create a single-use invitation. Returns plaintext token once.
     *
     * @return array{ok:bool,message:string,token?:string,expires_at?:string}
     */
    public function createInvitation(string $parentEmail, int $studentId, int $adminUserId, int $ttlDays = 14): array
    {
        $parentEmail = strtolower(trim($parentEmail));
        if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid parent email address.'];
        }
        $student = $this->findStudentByIdentifier((string)$studentId);
        if (!$student) {
            return ['ok' => false, 'message' => 'Student not found.'];
        }
        $ttlDays = max(1, min(60, $ttlDays));
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));
        try {
            $this->pdo->prepare("
                INSERT INTO parent_invitations (parent_email, student_id, token_hash, expires_at, created_by)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$parentEmail, $studentId, $hash, $expiresAt, $adminUserId]);
            $id = (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('parent invite create: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not create invitation.'];
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'parent_invite_create', 'parent_invitations', $id, null, [
                'parent_email' => $parentEmail,
                'student_id' => $studentId,
                'by' => $adminUserId,
            ]);
        }
        return [
            'ok' => true,
            'message' => 'Invitation created.',
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Apply a valid invitation for the logged-in parent (email must match).
     *
     * @return array{ok:bool,message:string}
     */
    public function acceptInvitation(int $parentId, string $token): array
    {
        $token = trim($token);
        if ($parentId < 1 || $token === '') {
            return ['ok' => false, 'message' => 'Invalid invitation.'];
        }
        $parent = $this->parentRow($parentId);
        if (!$parent) {
            return ['ok' => false, 'message' => 'Parent account not found.'];
        }
        $email = strtolower(trim((string)($parent['email'] ?? '')));
        if ($email === '') {
            return ['ok' => false, 'message' => 'This parent account has no email on file.'];
        }

        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare('SELECT * FROM parent_invitations WHERE token_hash = ? LIMIT 1');
        $stmt->execute([$hash]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv) {
            return ['ok' => false, 'message' => 'Invalid invitation.'];
        }
        if (!empty($inv['used_at'])) {
            return ['ok' => false, 'message' => 'This invitation has already been used.'];
        }
        if (strtotime((string)$inv['expires_at']) < time()) {
            return ['ok' => false, 'message' => 'This invitation has expired.'];
        }
        if (strtolower(trim((string)$inv['parent_email'])) !== $email) {
            return ['ok' => false, 'message' => 'Sign in with the invited Google email address.'];
        }
        $studentId = (int)$inv['student_id'];

        try {
            $this->pdo->beginTransaction();
            $now = date('Y-m-d H:i:s');
            $this->pdo->prepare("
                UPDATE parent_invitations
                SET used_at = ?, used_by_parent_id = ?
                WHERE id = ? AND used_at IS NULL
            ")->execute([$now, $parentId, (int)$inv['id']]);
            $check = $this->pdo->prepare('SELECT used_by_parent_id FROM parent_invitations WHERE id = ?');
            $check->execute([(int)$inv['id']]);
            if ((int)$check->fetchColumn() !== $parentId) {
                throw new RuntimeException('Invitation could not be claimed.');
            }
            $this->ensureVerifiedLink($parentId, $studentId);
            $this->pdo->prepare("
                UPDATE parent_accounts
                SET status = CASE WHEN status IN ('suspended','disabled') THEN status ELSE 'active' END
                WHERE id = ?
            ")->execute([$parentId]);
            $this->pdo->prepare("
                UPDATE parent_link_requests
                SET status = 'approved', reviewed_at = ?, rejection_reason = 'Accepted via invitation'
                WHERE parent_id = ? AND student_id = ? AND status = 'pending'
            ")->execute([$now, $parentId, $studentId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('parent invite accept: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not accept the invitation.'];
        }

        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'parent_invite_accept', 'parent_invitations', (int)$inv['id'], null, [
                'parent_id' => $parentId,
                'student_id' => $studentId,
            ]);
        }
        return ['ok' => true, 'message' => 'Invitation accepted. You can view the linked student.'];
    }

    /**
     * Peek invitation without consuming it (for UI messaging).
     *
     * @return array<string,mixed>|null
     */
    public function peekInvitation(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM parent_invitations WHERE token_hash = ? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parentRow(int $parentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parent_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$parentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function requestRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parent_link_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function ensureVerifiedLink(int $parentId, int $studentId): void
    {
        if ($this->hasVerifiedLink($parentId, $studentId)) {
            return;
        }
        $this->pdo->prepare('INSERT INTO parent_students (parent_id, student_id) VALUES (?, ?)')
            ->execute([$parentId, $studentId]);
    }
}
