<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service for managing the migration of teacher accounts from legacy usernames
 * to verified Google OAuth login emails without losing data or relationships.
 */
final class TeacherMigrationService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    /**
     * Get aggregate statistics for the teacher migration dashboard.
     *
     * @return array{total:int,linked:int,not_linked:int,requires_review:int,disabled:int,legacy_login_disabled:bool}
     */
    public function getTeacherStats(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN u.teacher_oauth_status = 'linked' THEN 1 ELSE 0 END) AS linked_count,
                SUM(CASE WHEN u.teacher_oauth_status = 'not_linked' OR u.teacher_oauth_status IS NULL OR u.teacher_oauth_status = '' THEN 1 ELSE 0 END) AS not_linked_count,
                SUM(CASE WHEN u.teacher_oauth_status = 'requires_review' THEN 1 ELSE 0 END) AS review_count,
                SUM(CASE WHEN u.teacher_oauth_status = 'disabled' THEN 1 ELSE 0 END) AS disabled_count
            FROM users u
            WHERE u.role = 'teacher' AND u.deleted_at IS NULL
        ";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'linked' => (int)($row['linked_count'] ?? 0),
            'not_linked' => (int)($row['not_linked_count'] ?? 0),
            'requires_review' => (int)($row['review_count'] ?? 0),
            'disabled' => (int)($row['disabled_count'] ?? 0),
            'legacy_login_disabled' => $this->isLegacyLoginDisabled(),
        ];
    }

    /**
     * Get the full list of teacher accounts for migration view.
     *
     * @return list<array<string,mixed>>
     */
    public function getTeachersList(?string $search = null, ?string $statusFilter = null): array
    {
        $params = [];
        $whereClauses = ["u.role = 'teacher'", "u.deleted_at IS NULL"];

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $whereClauses[] = "(u.username LIKE ? OR t.name LIKE ? OR t.email LIKE ? OR u.google_email LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            if ($statusFilter === 'not_linked') {
                $whereClauses[] = "(u.teacher_oauth_status = 'not_linked' OR u.teacher_oauth_status IS NULL OR u.teacher_oauth_status = '')";
            } else {
                $whereClauses[] = "u.teacher_oauth_status = ?";
                $params[] = $statusFilter;
            }
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.is_active,
                u.google_id,
                u.google_email,
                COALESCE(u.teacher_oauth_status, 'not_linked') AS teacher_oauth_status,
                u.teacher_oauth_linked_at,
                u.teacher_oauth_linked_by,
                u.teacher_oauth_notes,
                t.id AS teacher_id,
                COALESCE(NULLIF(TRIM(t.name), ''), u.username) AS teacher_name,
                t.email AS teacher_email,
                t.phone AS teacher_phone,
                t.photo AS teacher_photo,
                linker.username AS linked_by_username
            FROM users u
            LEFT JOIN teachers t ON t.id = u.teacher_id
            LEFT JOIN users linker ON linker.id = u.teacher_oauth_linked_by
            WHERE {$whereSql}
            ORDER BY
                CASE
                    WHEN u.teacher_oauth_status = 'requires_review' THEN 1
                    WHEN u.teacher_oauth_status = 'not_linked' OR u.teacher_oauth_status IS NULL THEN 2
                    WHEN u.teacher_oauth_status = 'linked' THEN 3
                    ELSE 4
                END,
                teacher_name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Attach active invitation info if available
        $invStmt = $this->pdo->prepare("
            SELECT token_hash, expected_email, expires_at, created_at
            FROM teacher_oauth_invites
            WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");

        foreach ($teachers as &$tch) {
            $invStmt->execute([(int)$tch['user_id']]);
            $tch['active_invite'] = $invStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        unset($tch);

        return $teachers;
    }

    /**
     * Create a single-use expiring invitation link for a teacher.
     *
     * @return array{ok:bool,message:string,token?:string,invite_url?:string,expires_at?:string}
     */
    public function createInvite(int $userId, ?string $expectedEmail, int $adminUserId, int $ttlDays = 7): array
    {
        $stmt = $this->pdo->prepare("SELECT id, username, role FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || ($user['role'] ?? '') !== 'teacher') {
            return ['ok' => false, 'message' => 'Teacher account not found.'];
        }

        $expectedEmail = strtolower(trim((string)($expectedEmail ?? '')));
        if ($expectedEmail !== '' && !filter_var($expectedEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'The expected Google email address is invalid.'];
        }

        $ttlDays = max(1, min(30, $ttlDays));
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));

        try {
            // Invalidate any prior unused invites for this user
            $this->pdo->prepare("DELETE FROM teacher_oauth_invites WHERE user_id = ? AND used_at IS NULL")->execute([$userId]);

            $this->pdo->prepare("
                INSERT INTO teacher_oauth_invites (user_id, token_hash, expected_email, expires_at, created_by)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $userId,
                $hash,
                $expectedEmail !== '' ? $expectedEmail : null,
                $expiresAt,
                $adminUserId,
            ]);
            $inviteId = (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('create teacher invite error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not create invitation token.'];
        }

        $base = rtrim($this->resolveAppUrl(), '/');
        $inviteUrl = $base . '/auth/google/link_teacher.php?invite=' . rawurlencode($token);

        $this->logAudit('teacher_invite_created', 'teacher_oauth_invites', $inviteId, [
            'user_id' => $userId,
            'username' => $user['username'],
            'expected_email' => $expectedEmail,
            'expires_at' => $expiresAt,
            'created_by' => $adminUserId,
        ]);

        return [
            'ok' => true,
            'message' => 'Invitation link generated successfully.',
            'token' => $token,
            'invite_url' => $inviteUrl,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Unlink Google OAuth from a teacher account.
     * This restores legacy username/password login without touching any other data.
     *
     * @return array{ok:bool,message:string}
     */
    public function unlinkTeacher(int $userId, int $adminUserId, string $reason = ''): array
    {
        $stmt = $this->pdo->prepare("SELECT id, username, google_id, google_email, teacher_oauth_status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['ok' => false, 'message' => 'Teacher account not found.'];
        }

        $oldGoogleEmail = (string)($user['google_email'] ?? '');
        $oldGoogleId = (string)($user['google_id'] ?? '');

        try {
            $this->pdo->beginTransaction();

            $note = "\n[" . date('Y-m-d H:i:s') . "] Google account ({$oldGoogleEmail}) unlinked by admin #{$adminUserId}";
            if (trim($reason) !== '') {
                $note .= ": " . trim($reason);
            }

            $this->pdo->prepare("
                UPDATE users
                SET google_id = NULL,
                    google_email = NULL,
                    teacher_oauth_status = 'not_linked',
                    teacher_oauth_linked_at = NULL,
                    teacher_oauth_linked_by = NULL,
                    teacher_oauth_notes = CONCAT(COALESCE(teacher_oauth_notes, ''), ?)
                WHERE id = ?
            ")->execute([$note, $userId]);

            // Clear any outstanding invites
            $this->pdo->prepare("DELETE FROM teacher_oauth_invites WHERE user_id = ? AND used_at IS NULL")->execute([$userId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('unlink teacher error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not unlink teacher account.'];
        }

        $this->logAudit('teacher_oauth_unlinked', 'users', $userId, [
            'user_id' => $userId,
            'username' => $user['username'],
            'previous_google_email' => $oldGoogleEmail,
            'previous_google_id' => $oldGoogleId,
            'reason' => $reason,
            'by' => $adminUserId,
        ]);

        return [
            'ok' => true,
            'message' => "Teacher account '{$user['username']}' unlinked successfully. Legacy username/password login is now restored.",
        ];
    }

    /**
     * Update the migration status of a teacher account.
     *
     * @return array{ok:bool,message:string}
     */
    public function setStatus(int $userId, string $status, ?string $notes, int $adminUserId): array
    {
        $status = strtolower(trim($status));
        $validStatuses = ['not_linked', 'linked', 'requires_review', 'disabled'];

        if (!in_array($status, $validStatuses, true)) {
            return ['ok' => false, 'message' => 'Invalid migration status.'];
        }

        $stmt = $this->pdo->prepare("SELECT id, username, teacher_oauth_status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['ok' => false, 'message' => 'Teacher account not found.'];
        }

        $oldStatus = (string)($user['teacher_oauth_status'] ?? 'not_linked');

        try {
            $noteAppend = '';
            if ($notes !== null && trim($notes) !== '') {
                $noteAppend = "\n[" . date('Y-m-d H:i:s') . "] Status changed from '{$oldStatus}' to '{$status}' by admin #{$adminUserId}: " . trim($notes);
            }

            $this->pdo->prepare("
                UPDATE users
                SET teacher_oauth_status = ?,
                    teacher_oauth_notes = CONCAT(COALESCE(teacher_oauth_notes, ''), ?)
                WHERE id = ?
            ")->execute([$status, $noteAppend, $userId]);
        } catch (Throwable $e) {
            error_log('set teacher status error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not update status.'];
        }

        $this->logAudit('teacher_oauth_status_change', 'users', $userId, [
            'user_id' => $userId,
            'username' => $user['username'],
            'old_status' => $oldStatus,
            'new_status' => $status,
            'notes' => $notes,
            'by' => $adminUserId,
        ]);

        return ['ok' => true, 'message' => 'Teacher migration status updated successfully.'];
    }

    /**
     * Toggle the global setting to disable or allow legacy username/password login for teachers.
     *
     * @return array{ok:bool,message:string,unlinked_count?:int}
     */
    public function setLegacyLoginDisabled(bool $disabled, int $adminUserId, bool $force = false): array
    {
        if ($disabled && !$force) {
            // Check how many teachers are still unlinked
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM users
                WHERE role = 'teacher' AND deleted_at IS NULL
                  AND (teacher_oauth_status != 'linked' OR google_id IS NULL OR google_id = '')
            ");
            $unlinkedCount = (int)$stmt->fetchColumn();

            if ($unlinkedCount > 0) {
                return [
                    'ok' => false,
                    'message' => "There are still {$unlinkedCount} teacher(s) who have not linked their Google account. Confirming will prevent them from logging in with their passwords.",
                    'unlinked_count' => $unlinkedCount,
                ];
            }
        }

        if (function_exists('ops_save_setting')) {
            ops_save_setting($this->pdo, 'teacher_legacy_login_disabled', $disabled ? '1' : '0');
            ops_save_setting($this->pdo, 'staff_legacy_login_disabled', $disabled ? '1' : '0');
        } else {
            $val = $disabled ? '1' : '0';
            try {
                $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
                $stmt->execute(['teacher_legacy_login_disabled', $val]);
                $stmt->execute(['staff_legacy_login_disabled', $val]);
            } catch (Throwable $e) {
                try {
                    $stmt = $this->pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $stmt->execute(['teacher_legacy_login_disabled', $val]);
                    $stmt->execute(['staff_legacy_login_disabled', $val]);
                } catch (Throwable $e2) {
                }
            }
        }

        $this->logAudit('staff_legacy_login_toggle', 'settings', 0, [
            'legacy_login_disabled' => $disabled ? '1' : '0',
            'by' => $adminUserId,
        ]);

        return [
            'ok' => true,
            'message' => $disabled
                ? 'Legacy password login for staff and teachers has been disabled. All staff must now sign in using Google.'
                : 'Legacy password login for staff and teachers has been re-enabled as fallback.',
        ];
    }

    public function isLegacyLoginDisabled(): bool
    {
        if (function_exists('ops_setting')) {
            return ops_setting($this->pdo, 'staff_legacy_login_disabled', ops_setting($this->pdo, 'teacher_legacy_login_disabled', '0')) === '1';
        }
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key IN ('staff_legacy_login_disabled', 'teacher_legacy_login_disabled') ORDER BY (setting_key = 'staff_legacy_login_disabled') DESC LIMIT 1");
            $stmt->execute();
            return $stmt->fetchColumn() === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Validate an invitation token and return teacher info if valid.
     *
     * @return array{ok:bool,message?:string,invite?:array<string,mixed>,teacher?:array<string,mixed>}
     */
    public function validateInvite(string $token): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) !== 64) {
            return ['ok' => false, 'message' => 'Invalid invitation link format.'];
        }

        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("
            SELECT i.*, u.username, u.teacher_id, u.teacher_oauth_status,
                   t.name AS teacher_name, t.email AS teacher_email, t.photo AS teacher_photo
            FROM teacher_oauth_invites i
            JOIN users u ON u.id = i.user_id
            LEFT JOIN teachers t ON t.id = u.teacher_id
            WHERE i.token_hash = ? AND i.used_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'message' => 'This invitation link is invalid or has already been used.'];
        }

        if (strtotime((string)$row['expires_at']) < time()) {
            return ['ok' => false, 'message' => 'This invitation link has expired. Please request a new invitation from the administrator.'];
        }

        return [
            'ok' => true,
            'invite' => [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'expected_email' => $row['expected_email'] ? (string)$row['expected_email'] : null,
                'expires_at' => (string)$row['expires_at'],
                'created_by' => (int)$row['created_by'],
            ],
            'teacher' => [
                'user_id' => (int)$row['user_id'],
                'username' => (string)$row['username'],
                'name' => (string)($row['teacher_name'] ?: $row['username']),
                'email' => (string)($row['teacher_email'] ?? ''),
                'photo' => (string)($row['teacher_photo'] ?? ''),
                'status' => (string)($row['teacher_oauth_status'] ?? 'not_linked'),
            ],
        ];
    }

    /**
     * Get recent migration audit logs for the audit tab.
     *
     * @return list<array<string,mixed>>
     */
    public function getRecentAuditLogs(int $limit = 50): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT a.*, u.username AS actor_username
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE a.action LIKE 'teacher_%' OR a.table_name = 'teacher_oauth_invites'
                ORDER BY a.id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get teacher details for invitation link / SMS generation.
     *
     * @return array{id:int,username:string,teacher_id:?int,teacher_name:string,teacher_phone:?string,teacher_email:?string}|null
     */
    public function getTeacherForInvite(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, t.id AS teacher_id,
                   COALESCE(NULLIF(TRIM(t.name), ''), u.username) AS teacher_name,
                   t.phone AS teacher_phone,
                   t.email AS teacher_email
            FROM users u
            LEFT JOIN teachers t ON t.id = u.teacher_id
            WHERE u.id = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * A Google identity may be attached only to an active teacher or administrator.
     *
     * @return array{id:int,username:string,role:string}|null
     */
    public function eligibleStaffLinkTarget(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT id, username, role
            FROM users
            WHERE id = ? AND deleted_at IS NULL AND role IN ('admin', 'teacher')
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'role' => (string)$row['role'],
        ];
    }

    public function resolveAppUrl(): string
    {
        if (function_exists('edexcel_public_app_url')) {
            $url = edexcel_public_app_url();
            if ($url !== '' && $url !== '/') {
                return $url;
            }
        }
        if (defined('APP_URL') && APP_URL !== '' && APP_URL !== '/') {
            return (string)APP_URL;
        }
        $env = (string)(getenv('APP_URL') ?: '');
        if ($env !== '' && $env !== '/') {
            return $env;
        }
        return 'https://edexcel.college';
    }

    /**
     * Check if SMS Gateway is configured and ready.
     *
     * @return array{ready:bool,mode:string,endpoint:string,username:string,device_id:string,sim:string,message:string}
     */
    public function getSmsGatewayStatus(): array
    {
        if (!function_exists('sms_gateway_config')) {
            $cfgFile = dirname(__DIR__, 2) . '/config/sms_gateway.php';
            if (is_file($cfgFile)) {
                require_once $cfgFile;
            }
        }
        if (!function_exists('sms_gateway_config')) {
            return [
                'ready' => false,
                'mode' => 'unavailable',
                'endpoint' => '',
                'username' => '',
                'device_id' => '',
                'sim' => '',
                'message' => 'SMS Gateway configuration file not found.',
            ];
        }

        $cfg = sms_gateway_config($this->pdo);
        $user = trim((string)($cfg['sms_gateway_username'] ?? ''));
        $pass = (string)($cfg['sms_gateway_password'] ?? '');
        $url = trim((string)($cfg['sms_gateway_url'] ?? ''));
        $ready = ($user !== '' && $pass !== '' && $url !== '');

        return [
            'ready' => $ready,
            'mode' => (string)($cfg['sms_gateway_mode'] ?? 'cloud'),
            'endpoint' => (string)($cfg['sms_gateway_url'] ?? ''),
            'username' => $user,
            'device_id' => (string)($cfg['sms_gateway_device_id'] ?? ''),
            'sim' => (string)($cfg['sms_gateway_sim'] ?? ''),
            'message' => $ready ? 'SMS Gateway is configured and online.' : 'SMS Gateway credentials are not fully configured in Settings.',
        ];
    }

    /**
     * Record SMS dispatch in user notes and audit trail.
     */
    public function recordSmsDispatch(int $userId, string $phone, string $msgId, int $adminUserId): void
    {
        $now = date('Y-m-d H:i:s');
        $note = "\n[{$now}] Google OAuth linking SMS dispatched to {$phone} via SMS Gateway (ID: {$msgId}) by Admin #{$adminUserId}";
        try {
            $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $sql = $isSqlite
                ? "UPDATE users SET teacher_oauth_notes = COALESCE(teacher_oauth_notes, '') || ? WHERE id = ?"
                : "UPDATE users SET teacher_oauth_notes = CONCAT(COALESCE(teacher_oauth_notes, ''), ?) WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$note, $userId]);
        } catch (Throwable $e) {
            error_log('Failed to update teacher_oauth_notes with SMS dispatch: ' . $e->getMessage());
        }

        $this->logAudit('teacher_oauth_sms_sent', 'users', $userId, [
            'phone' => $phone,
            'gateway_msg_id' => $msgId,
            'admin_user_id' => $adminUserId,
            'dispatched_at' => $now,
        ]);
    }

    public function logAudit(string $action, string $table, int $recordId, array $details): void
    {
        if (function_exists('log_audit')) {
            log_audit($this->pdo, $action, $table, $recordId, null, $details);
        } else {
            try {
                $userId = (int)($_SESSION['user_id'] ?? 0);
                $stmt = $this->pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId > 0 ? $userId : null,
                    $action,
                    $table,
                    $recordId > 0 ? $recordId : null,
                    json_encode($details, JSON_UNESCAPED_SLASHES),
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ]);
            } catch (Throwable $e) {
                error_log('audit log failed: ' . $e->getMessage());
            }
        }
    }
}

