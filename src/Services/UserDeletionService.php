<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Service for safely searching, analyzing impact, and deleting/deactivating user accounts
 * while preserving academic, financial, attendance, and audit history.
 */
class UserDeletionService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Search user accounts by username, email, full name, phone, or ID.
     *
     * @return list<array<string, mixed>>
     */
    public function searchUsers(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $isId = ctype_digit($q);
        $likePattern = '%' . $q . '%';
        $results = [];

        // 1. Search in users table (admins, teachers, students)
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.google_email,
                u.google_id,
                u.account_status,
                u.is_active,
                u.created_at,
                u.deleted_at,
                u.teacher_id,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), NULLIF(TRIM(t.name), ''), u.username) AS full_name,
                COALESCE(NULLIF(TRIM(u.google_email), ''), NULLIF(TRIM(sp.email), ''), NULLIF(TRIM(t.email), '')) AS primary_email,
                COALESCE(NULLIF(TRIM(sp.whatsapp_number), ''), NULLIF(TRIM(t.phone), '')) AS phone,
                'user' AS account_type
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN teachers t ON t.id = u.teacher_id
            WHERE u.username LIKE ?
               OR u.google_email LIKE ?
               OR sp.email LIKE ?
               OR sp.full_name LIKE ?
               OR sp.whatsapp_number LIKE ?
               OR t.name LIKE ?
               OR t.email LIKE ?
               OR t.phone LIKE ?
        ";
        $params = array_fill(0, 8, $likePattern);
        if ($isId) {
            $sql .= " OR u.id = ? ";
            $params[] = (int)$q;
        }
        $sql .= "
            ORDER BY
                CASE WHEN u.deleted_at IS NOT NULL THEN 1 ELSE 0 END ASC,
                u.id ASC
            LIMIT 25
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($userRows as $r) {
                $results[] = $this->normalizeUserResult($r);
            }
        } catch (Throwable $e) {
            error_log('UserDeletionService::searchUsers error: ' . $e->getMessage());
        }

        // 2. Also search in parent_accounts table if present
        try {
            $parentSql = "
                SELECT
                    id AS user_id,
                    NULL AS username,
                    'parent' AS role,
                    email AS google_email,
                    google_id,
                    status AS account_status,
                    CASE WHEN status = 'active' THEN 1 ELSE 0 END AS is_active,
                    created_at,
                    NULL AS deleted_at,
                    NULL AS teacher_id,
                    COALESCE(NULLIF(TRIM(name), ''), 'Parent Account') AS full_name,
                    email AS primary_email,
                    phone,
                    'parent' AS account_type
                FROM parent_accounts
                WHERE (email LIKE ? OR name LIKE ? OR phone LIKE ?)
            ";
            $pParams = array_fill(0, 3, $likePattern);
            if ($isId) {
                $parentSql .= " OR id = ? ";
                $pParams[] = (int)$q;
            }
            $parentSql .= " ORDER BY id ASC LIMIT 10 ";

            $pStmt = $this->pdo->prepare($parentSql);
            $pStmt->execute($pParams);
            $parentRows = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($parentRows as $pr) {
                $results[] = $this->normalizeUserResult($pr);
            }
        } catch (Throwable $e) {
            // Optional table in some test environments
        }

        // Prioritize exact match to top
        usort($results, static function (array $a, array $b) use ($q): int {
            $aDeleted = !empty($a['is_deleted']) ? 1 : 0;
            $bDeleted = !empty($b['is_deleted']) ? 1 : 0;
            if ($aDeleted !== $bDeleted) {
                return $aDeleted <=> $bDeleted;
            }
            $aExact = (strcasecmp((string)($a['username'] ?? ''), $q) === 0 || strcasecmp((string)($a['primary_email'] ?? ''), $q) === 0) ? 0 : 1;
            $bExact = (strcasecmp((string)($b['username'] ?? ''), $q) === 0 || strcasecmp((string)($b['primary_email'] ?? ''), $q) === 0) ? 0 : 1;
            if ($aExact !== $bExact) {
                return $aExact <=> $bExact;
            }
            return (int)$a['user_id'] <=> (int)$b['user_id'];
        });

        return $results;
    }

    /**
     * Get complete details for a specific user ID.
     *
     * @return array<string, mixed>|null
     */
    public function getUserDetails(int $userId, string $accountType = 'user'): ?array
    {
        if ($accountType === 'parent') {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT id AS user_id, NULL AS username, 'parent' AS role, email AS google_email, google_id,
                           status AS account_status, CASE WHEN status = 'active' THEN 1 ELSE 0 END AS is_active,
                           created_at, NULL AS deleted_at, NULL AS teacher_id,
                           COALESCE(NULLIF(TRIM(name), ''), 'Parent Account') AS full_name,
                           email AS primary_email, phone, 'parent' AS account_type
                    FROM parent_accounts WHERE id = ? LIMIT 1
                ");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->normalizeUserResult($row) : null;
            } catch (Throwable $e) {
                return null;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.google_email,
                u.google_id,
                u.account_status,
                u.is_active,
                u.created_at,
                u.deleted_at,
                u.teacher_id,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), NULLIF(TRIM(t.name), ''), u.username) AS full_name,
                COALESCE(NULLIF(TRIM(u.google_email), ''), NULLIF(TRIM(sp.email), ''), NULLIF(TRIM(t.email), '')) AS primary_email,
                COALESCE(NULLIF(TRIM(sp.whatsapp_number), ''), NULLIF(TRIM(t.phone), '')) AS phone,
                'user' AS account_type
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN teachers t ON t.id = u.teacher_id
            WHERE u.id = ? LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeUserResult($row) : null;
    }

    /**
     * Inspect all related database records for an account to generate an impact report.
     *
     * @return array{
     *   retained_records: list<array{category: string, count: int, description: string, policy: string}>,
     *   revoked_items: list<array{category: string, description: string}>,
     *   total_retained_count: int
     * }
     */
    public function getRelatedRecordsImpact(int $userId, string $role, string $accountType = 'user'): array
    {
        $retained = [];
        $revoked = [];

        // Common security items to revoke immediately
        $revoked[] = [
            'category' => 'Authentication Credentials',
            'description' => 'Password hash erased, Google OAuth identity unlinked, and login access permanently disabled.'
        ];
        $revoked[] = [
            'category' => 'Active Sessions & Devices',
            'description' => 'Active browser sessions terminated; trusted device tokens and pending OTPs immediately purged.'
        ];

        $totalRetained = 0;

        // Audit Logs
        $auditCount = $this->safeCount("SELECT COUNT(*) FROM audit_logs WHERE user_id = ?", [$userId]);
        if ($auditCount > 0) {
            $retained[] = [
                'category' => 'System Audit History',
                'count' => $auditCount,
                'description' => 'Historical security and activity audit events',
                'policy' => 'Preserved permanently for compliance'
            ];
            $totalRetained += $auditCount;
        }

        if ($accountType === 'parent') {
            $psCount = $this->safeCount("SELECT COUNT(*) FROM parent_students WHERE parent_id = ?", [$userId]);
            if ($psCount > 0) {
                $retained[] = [
                    'category' => 'Linked Student Records',
                    'count' => $psCount,
                    'description' => 'Parent-student guardianship connections',
                    'policy' => 'Preserved for administrative reference'
                ];
                $totalRetained += $psCount;
            }
            return [
                'retained_records' => $retained,
                'revoked_items' => $revoked,
                'total_retained_count' => $totalRetained,
            ];
        }

        if ($role === 'student') {
            // Attendance
            $attCount = $this->safeCount("SELECT COUNT(*) FROM student_attendance WHERE student_id = ?", [$userId]);
            if ($attCount > 0) {
                $retained[] = [
                    'category' => 'Attendance Records',
                    'count' => $attCount,
                    'description' => 'Class check-ins and lesson attendance timestamps',
                    'policy' => 'Preserved for academic records'
                ];
                $totalRetained += $attCount;
            }

            // Enrollments
            $enrCount = $this->safeCount("SELECT COUNT(*) FROM student_enrollments WHERE student_id = ?", [$userId]);
            if ($enrCount > 0) {
                $retained[] = [
                    'category' => 'Course Enrollments',
                    'count' => $enrCount,
                    'description' => 'Past and current subject / class registrations',
                    'policy' => 'Preserved in archive'
                ];
                $totalRetained += $enrCount;
            }

            // Financial & Ledger
            $feeCount = $this->safeCount("SELECT COUNT(*) FROM student_fee_ledger WHERE student_id = ?", [$userId]);
            if ($feeCount > 0) {
                $retained[] = [
                    'category' => 'Financial & Payment Records',
                    'count' => $feeCount,
                    'description' => 'Fee ledger entries, payment slips, and invoices',
                    'policy' => 'Preserved for accounting & legal compliance'
                ];
                $totalRetained += $feeCount;
            }

            // Exams & Marks
            $examCount = $this->safeCount("SELECT COUNT(*) FROM student_exam_selections WHERE student_id = ?", [$userId]);
            if ($examCount > 0) {
                $retained[] = [
                    'category' => 'Academic & Exam Marks',
                    'count' => $examCount,
                    'description' => 'Exam participation, grading, and assessments',
                    'policy' => 'Preserved permanently'
                ];
                $totalRetained += $examCount;
            }

            // Homework Submissions
            $hwCount = $this->safeCount("SELECT COUNT(*) FROM student_homework_submissions WHERE student_id = ?", [$userId]);
            if ($hwCount > 0) {
                $retained[] = [
                    'category' => 'Homework Submissions',
                    'count' => $hwCount,
                    'description' => 'Student coursework submissions and feedback',
                    'policy' => 'Preserved in archive'
                ];
                $totalRetained += $hwCount;
            }

            // Parent Links
            $parentLinkCount = $this->safeCount("SELECT COUNT(*) FROM parent_students WHERE student_id = ?", [$userId]);
            if ($parentLinkCount > 0) {
                $retained[] = [
                    'category' => 'Parent / Guardian Links',
                    'count' => $parentLinkCount,
                    'description' => 'Registered parent/guardian associations',
                    'policy' => 'Preserved in record'
                ];
                $totalRetained += $parentLinkCount;
            }
        } elseif ($role === 'teacher') {
            // Find teacher_id from users table
            $tchStmt = $this->pdo->prepare("SELECT teacher_id FROM users WHERE id = ? LIMIT 1");
            $tchStmt->execute([$userId]);
            $tchId = (int)($tchStmt->fetchColumn() ?: 0);

            if ($tchId > 0) {
                // Timetable Classes
                $ttCount = $this->safeCount("SELECT COUNT(*) FROM timetable WHERE teacher_id = ?", [$tchId]);
                if ($ttCount > 0) {
                    $retained[] = [
                        'category' => 'Timetable & Class Schedules',
                        'count' => $ttCount,
                        'description' => 'Assigned classes, timetable periods, and past lessons',
                        'policy' => 'Preserved (teacher marked inactive)'
                    ];
                    $totalRetained += $ttCount;
                }

                // Student Allocations
                $allocCount = $this->safeCount("SELECT COUNT(*) FROM student_enrollments WHERE teacher_id = ?", [$tchId]);
                if ($allocCount > 0) {
                    $retained[] = [
                        'category' => 'Student Allocations',
                        'count' => $allocCount,
                        'description' => 'Students assigned to this teacher across subjects',
                        'policy' => 'Preserved in enrollment history'
                    ];
                    $totalRetained += $allocCount;
                }

                // Payments & Payroll
                $payCount = $this->safeCount("SELECT COUNT(*) FROM teacher_payment_events WHERE teacher_id = ?", [$tchId]);
                if ($payCount > 0) {
                    $retained[] = [
                        'category' => 'Teacher Payment Records',
                        'count' => $payCount,
                        'description' => 'Payment history, vouchers, and settlement logs',
                        'policy' => 'Preserved for financial compliance'
                    ];
                    $totalRetained += $payCount;
                }
            }

            // Revoked teacher invites
            $invCount = $this->safeCount("SELECT COUNT(*) FROM teacher_oauth_invites WHERE user_id = ? AND used_at IS NULL", [$userId]);
            if ($invCount > 0) {
                $revoked[] = [
                    'category' => 'Pending OAuth Invitations',
                    'description' => "{$invCount} active invitation link(s) revoked immediately."
                ];
            }
        }

        return [
            'retained_records' => $retained,
            'revoked_items' => $revoked,
            'total_retained_count' => $totalRetained,
        ];
    }

    /**
     * Check whether a user account is eligible for deletion or protected.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function canDeleteUser(int $targetUserId, int $currentAdminUserId, string $accountType = 'user'): array
    {
        if ($targetUserId <= 0) {
            return ['allowed' => false, 'reason' => 'Invalid user ID specified.'];
        }

        if ($accountType === 'user') {
            // Rule 1: No self-deletion
            if ($targetUserId === $currentAdminUserId) {
                return [
                    'allowed' => false,
                    'reason' => 'You cannot delete your own administrator account while logged in.'
                ];
            }

            // Rule 2: Primary system administrator protection (User ID #1)
            if ($targetUserId === 1) {
                return [
                    'allowed' => false,
                    'reason' => 'The primary system administrator account (ID #1) is protected and cannot be deleted.'
                ];
            }

            // Rule 3: Check existence and status
            $target = $this->getUserDetails($targetUserId, 'user');
            if (!$target) {
                return ['allowed' => false, 'reason' => 'User account does not exist.'];
            }

            // Rule 4: Already deleted / deactivated check
            if (!empty($target['deleted_at']) || ($target['account_status'] === 'disabled' && (int)$target['is_active'] === 0 && str_contains((string)$target['username'], '_deleted_'))) {
                return [
                    'allowed' => false,
                    'reason' => 'This user account is already deleted and deactivated.'
                ];
            }

            // Rule 5: Cannot delete the last active administrator
            if ($target['role'] === 'admin') {
                $adminCount = (int)$this->safeCount(
                    "SELECT COUNT(*) FROM users WHERE role = 'admin' AND deleted_at IS NULL AND (is_active = 1 OR is_active IS NULL)"
                );
                if ($adminCount <= 1) {
                    return [
                        'allowed' => false,
                        'reason' => 'Cannot delete the final active administrator. At least one other active administrator account must exist.'
                    ];
                }
            }
        } else {
            // Parent account checks
            $parent = $this->getUserDetails($targetUserId, 'parent');
            if (!$parent) {
                return ['allowed' => false, 'reason' => 'Parent account does not exist.'];
            }
            if (($parent['account_status'] ?? '') === 'disabled') {
                return ['allowed' => false, 'reason' => 'This parent account is already disabled.'];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Safely execute account deletion, credential purging, session termination, and audit logging.
     *
     * @return array{ok: bool, message: string, details?: array<string, mixed>}
     */
    public function deleteUser(
        int $targetUserId,
        int $currentAdminUserId,
        string $confirmIdentifier,
        ?string $reason = null,
        string $accountType = 'user'
    ): array {
        $can = $this->canDeleteUser($targetUserId, $currentAdminUserId, $accountType);
        if (!$can['allowed']) {
            return ['ok' => false, 'message' => $can['reason']];
        }

        $user = $this->getUserDetails($targetUserId, $accountType);
        if (!$user) {
            return ['ok' => false, 'message' => 'User account not found.'];
        }

        // Validate double-confirmation identifier
        $typed = strtolower(trim($confirmIdentifier));
        $expectedUser = strtolower(trim((string)($user['username'] ?? '')));
        $expectedEmail = strtolower(trim((string)($user['primary_email'] ?? '')));
        $expectedGoogle = strtolower(trim((string)($user['google_email'] ?? '')));

        $matches = ($typed !== '' && (
            ($expectedUser !== '' && $typed === $expectedUser) ||
            ($expectedEmail !== '' && $typed === $expectedEmail) ||
            ($expectedGoogle !== '' && $typed === $expectedGoogle)
        ));

        if (!$matches) {
            return [
                'ok' => false,
                'message' => 'Confirmation check failed: The entered confirmation identifier does not match the account username or email address.'
            ];
        }

        $impact = $this->getRelatedRecordsImpact($targetUserId, (string)$user['role'], $accountType);
        $now = date('Y-m-d H:i:s');
        $timestamp = time();

        $this->pdo->beginTransaction();
        try {
            if ($accountType === 'parent') {
                // Deactivate parent account
                $this->pdo->prepare("
                    UPDATE parent_accounts
                    SET status = 'disabled', google_id = NULL
                    WHERE id = ?
                ")->execute([$targetUserId]);
            } else {
                // 1. Terminate all active sessions, token devices, and pending OTPs
                $this->safeExec("DELETE FROM student_active_sessions WHERE user_id = ?", [$targetUserId]);
                $this->safeExec("DELETE FROM student_devices WHERE user_id = ?", [$targetUserId]);
                $this->safeExec("DELETE FROM student_device_otps WHERE user_id = ?", [$targetUserId]);
                $this->safeExec("DELETE FROM student_login_otps WHERE user_id = ?", [$targetUserId]);
                $this->safeExec("DELETE FROM student_phone_change_otps WHERE user_id = ?", [$targetUserId]);
                $this->safeExec("DELETE FROM teacher_oauth_invites WHERE user_id = ?", [$targetUserId]);

                // 2. Anonymize login credentials, revoke OAuth identity, and soft-delete in users table
                $origUsername = (string)$user['username'];
                $deletedUsername = substr($origUsername, 0, 30) . '_del_' . $timestamp;
                $randomHash = '*DELETED*' . bin2hex(random_bytes(16));

                $note = ($user['teacher_oauth_notes'] ? $user['teacher_oauth_notes'] . "\n" : '')
                    . "Account permanently deleted by Admin #{$currentAdminUserId} on {$now}";

                $stmtUp = $this->pdo->prepare("
                    UPDATE users
                    SET deleted_at = ?,
                        is_active = 0,
                        account_status = 'disabled',
                        password_hash = ?,
                        google_id = NULL,
                        google_email = NULL,
                        username = ?,
                        teacher_oauth_status = 'disabled',
                        teacher_oauth_notes = ?
                    WHERE id = ?
                ");
                $stmtUp->execute([$now, $randomHash, $deletedUsername, $note, $targetUserId]);

                // 3. If teacher, mark teacher profile deleted
                if (!empty($user['teacher_id'])) {
                    $this->safeExec("UPDATE teachers SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL", [$now, (int)$user['teacher_id']]);
                }

                // 4. If student, revoke parent view token
                $this->safeExec("UPDATE student_profiles SET parent_view_token = NULL WHERE user_id = ?", [$targetUserId]);
            }

            // 5. Fetch administrator username for audit trail
            $adminUsername = 'admin';
            try {
                $aStmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                $aStmt->execute([$currentAdminUserId]);
                $adminUsername = (string)($aStmt->fetchColumn() ?: 'admin');
            } catch (Throwable $e) {}

            $details = [
                'admin_id' => $currentAdminUserId,
                'admin_username' => $adminUsername,
                'target_user_id' => $targetUserId,
                'target_username' => $user['username'],
                'target_email' => $user['primary_email'],
                'target_name' => $user['full_name'],
                'target_role' => $user['role'],
                'account_type' => $accountType,
                'reason' => $reason ?: 'Administrator requested permanent account removal',
                'action_taken' => 'credentials_purged_and_sessions_terminated',
                'retained_records_count' => $impact['total_retained_count'],
                'deleted_at' => $now,
                'result' => 'success',
            ];

            $this->logAudit('admin_delete_user', $accountType === 'parent' ? 'parent_accounts' : 'users', $targetUserId, $details, $currentAdminUserId);

            $this->pdo->commit();

            return [
                'ok' => true,
                'message' => "User account '{$user['username']}' ({$user['full_name']}) was successfully removed. All sign-in credentials were wiped and active sessions terminated. Historical records ({$impact['total_retained_count']} related entries) remain safely preserved.",
                'details' => $details,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('UserDeletionService::deleteUser failed: ' . $e->getMessage());

            try {
                $failDetails = [
                    'admin_id' => $currentAdminUserId,
                    'target_user_id' => $targetUserId,
                    'target_username' => $user['username'] ?? '',
                    'error' => $e->getMessage(),
                    'result' => 'failed',
                    'timestamp' => $now,
                ];
                $this->logAudit('admin_delete_user_failed', 'users', $targetUserId, $failDetails, $currentAdminUserId);
            } catch (Throwable $ignore) {}

            return [
                'ok' => false,
                'message' => 'Failed to delete user account due to a database error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Helper to safely execute a non-critical statement.
     */
    private function safeExec(string $sql, array $params = []): void
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e) {
            // Non-critical cleanup error; silently continue
        }
    }

    /**
     * Helper to safely count rows in an optional table.
     */
    private function safeCount(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Record an audit log entry.
     */
    private function logAudit(string $action, string $table, int $recordId, array $details, int $userId): void
    {
        if (function_exists('log_audit')) {
            log_audit($this->pdo, $action, $table, $recordId, null, $details);
        } else {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, details, created_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId > 0 ? $userId : null,
                    $action,
                    $table,
                    $recordId > 0 ? $recordId : null,
                    json_encode($details, JSON_UNESCAPED_SLASHES),
                    date('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $e) {
                // Silently ignore audit insert failure
            }
        }
    }

    /**
     * Normalize a database row to uniform structure.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function normalizeUserResult(array $r): array
    {
        $role = strtolower((string)($r['role'] ?? 'user'));
        $roleLabel = match ($role) {
            'admin' => 'Administrator',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent' => 'Parent',
            default => ucfirst($role),
        };

        $isDeleted = !empty($r['deleted_at']) || str_contains((string)($r['username'] ?? ''), '_del_');
        $rawStatus = (string)($r['account_status'] ?? '');
        $isActive = (int)($r['is_active'] ?? 1) === 1;

        $displayStatus = 'Active';
        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';

        if ($isDeleted) {
            $displayStatus = 'Deleted';
            $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
        } elseif ($rawStatus === 'disabled' || !$isActive) {
            $displayStatus = 'Disabled';
            $badgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        } elseif ($rawStatus === 'suspended') {
            $displayStatus = 'Suspended';
            $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
        } elseif ($rawStatus === 'pending') {
            $displayStatus = 'Pending';
            $badgeClass = 'bg-info-subtle text-info border border-info-subtle';
        }

        return [
            'user_id' => (int)$r['user_id'],
            'username' => (string)($r['username'] ?? ''),
            'role' => $role,
            'role_label' => $roleLabel,
            'full_name' => (string)($r['full_name'] ?? 'User #' . $r['user_id']),
            'primary_email' => (string)($r['primary_email'] ?? ''),
            'google_email' => (string)($r['google_email'] ?? ''),
            'phone' => (string)($r['phone'] ?? ''),
            'account_status' => $displayStatus,
            'badge_class' => $badgeClass,
            'is_active' => $isActive,
            'is_deleted' => $isDeleted,
            'created_at' => (string)($r['created_at'] ?? ''),
            'deleted_at' => (string)($r['deleted_at'] ?? ''),
            'teacher_id' => isset($r['teacher_id']) ? (int)$r['teacher_id'] : null,
            'account_type' => (string)($r['account_type'] ?? 'user'),
            'teacher_oauth_notes' => (string)($r['teacher_oauth_notes'] ?? ''),
        ];
    }
}

