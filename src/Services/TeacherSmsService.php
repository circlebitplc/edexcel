<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Teacher SMS Permissions and Quota Enforcement Service
 *
 * Enforces server-side permissions, hard monthly SMS quota limits,
 * group-level access controls, and strict iPromo gateway lockdown for teachers.
 */
final class TeacherSmsService
{
    /**
     * Ensure database schema exists (self-healing).
     */
    public static function ensureSchema(PDO $pdo): void
    {
        PhoneContactService::ensureSchema($pdo);
    }

    /**
     * Get SMS permissions for a specific teacher user.
     *
     * @return array<string,mixed>|null
     */
    public static function getTeacherPermissions(PDO $pdo, int $teacherUserId): ?array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT tsp.*, u.username, u.teacher_id, t.name as teacher_name, t.phone as teacher_phone, t.email as teacher_email
             FROM teacher_sms_permissions tsp
             JOIN users u ON u.id = tsp.teacher_user_id
             LEFT JOIN teachers t ON t.id = u.teacher_id
             WHERE tsp.teacher_user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$teacherUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Format helper fields
        $row['allowed_years_array'] = !empty($row['allowed_exam_years']) ? array_map('intval', explode(',', $row['allowed_exam_years'])) : [];
        $row['allowed_types_array'] = !empty($row['allowed_exam_types']) ? array_map('trim', explode(',', $row['allowed_exam_types'])) : [];
        $row['allowed_locations_array'] = !empty($row['allowed_locations']) ? array_map('trim', explode(',', $row['allowed_locations'])) : [];
        $row['allowed_groups_array'] = !empty($row['allowed_whatsapp_groups']) ? array_values(array_filter(array_map('trim', explode(',', $row['allowed_whatsapp_groups'])))) : [];

        return $row;
    }

    /**
     * Get or create default permission row for a teacher user.
     *
     * @return array<string,mixed>
     */
    public static function getOrCreateTeacherPermissions(PDO $pdo, int $teacherUserId): array
    {
        self::ensureSchema($pdo);

        $perm = self::getTeacherPermissions($pdo, $teacherUserId);
        if ($perm !== null) {
            return $perm;
        }

        // Fetch teacher_id from users
        $stmtU = $pdo->prepare("SELECT teacher_id FROM users WHERE id = ? LIMIT 1");
        $stmtU->execute([$teacherUserId]);
        $teacherId = $stmtU->fetchColumn();

        $stmtIns = $pdo->prepare(
            "INSERT INTO teacher_sms_permissions 
            (teacher_user_id, teacher_id, sms_access, gateway, monthly_limit, can_send_sms, can_view_contacts, can_select_contacts, created_at)
            VALUES (?, ?, 0, 'ipromo', 0, 0, 0, 0, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()"
        );
        $stmtIns->execute([$teacherUserId, $teacherId !== false && $teacherId !== null ? (int)$teacherId : null]);

        return self::getTeacherPermissions($pdo, $teacherUserId) ?? [
            'teacher_user_id'         => $teacherUserId,
            'sms_access'              => 0,
            'gateway'                 => 'ipromo',
            'monthly_limit'           => 0,
            'can_send_sms'            => 0,
            'can_view_contacts'       => 0,
            'can_select_contacts'     => 0,
            'allowed_years_array'     => [],
            'allowed_types_array'     => [],
            'allowed_locations_array' => [],
            'allowed_groups_array'    => [],
        ];
    }

    /**
     * Get all teachers with their current SMS permissions and monthly usage.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getAllTeachersWithPermissions(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $currentMonth = date('Y-m');

        // Query users with role 'teacher' or having a teacher_id, plus teachers table
        $sql = "SELECT u.id as user_id, u.username, u.teacher_id, u.is_active,
                       t.name as teacher_name, t.phone as teacher_phone, t.email as teacher_email,
                       tsp.id as perm_id, tsp.sms_access, tsp.gateway, tsp.monthly_limit, 
                       tsp.can_send_sms, tsp.can_view_contacts, tsp.can_select_contacts,
                       tsp.allowed_exam_years, tsp.allowed_exam_types, tsp.allowed_locations, tsp.allowed_whatsapp_groups,
                       tsp.updated_at as perm_updated_at
                FROM users u
                LEFT JOIN teachers t ON t.id = u.teacher_id
                LEFT JOIN teacher_sms_permissions tsp ON tsp.teacher_user_id = u.id
                WHERE u.role = 'teacher' OR u.teacher_id IS NOT NULL
                ORDER BY COALESCE(t.name, u.username) ASC";

        $stmt = $pdo->query($sql);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $results = [];
        foreach ($teachers as $t) {
            $uId = (int)$t['user_id'];
            $usage = self::getTeacherMonthlyUsage($pdo, $uId, $currentMonth);

            $t['sms_access'] = (int)($t['sms_access'] ?? 0);
            $t['gateway'] = 'ipromo'; // Always enforced
            $t['monthly_limit'] = (int)($t['monthly_limit'] ?? 0);
            $t['can_send_sms'] = (int)($t['can_send_sms'] ?? 0);
            $t['can_view_contacts'] = (int)($t['can_view_contacts'] ?? 0);
            $t['can_select_contacts'] = (int)($t['can_select_contacts'] ?? 0);
            $t['used_this_month'] = $usage['used_units'];
            $t['remaining_this_month'] = $usage['remaining_units'];
            $t['is_limit_exceeded'] = $usage['is_exceeded'];

            $t['allowed_years_array'] = !empty($t['allowed_exam_years']) ? array_map('intval', explode(',', $t['allowed_exam_years'])) : [];
            $t['allowed_types_array'] = !empty($t['allowed_exam_types']) ? array_map('trim', explode(',', $t['allowed_exam_types'])) : [];
            $t['allowed_locations_array'] = !empty($t['allowed_locations']) ? array_map('trim', explode(',', $t['allowed_locations'])) : [];
            $t['allowed_groups_array'] = !empty($t['allowed_whatsapp_groups']) ? array_values(array_filter(array_map('trim', explode(',', $t['allowed_whatsapp_groups'])))) : [];

            $results[] = $t;
        }

        return $results;
    }

    /**
     * Save teacher permissions and quota settings.
     *
     * @param array<string,mixed> $data
     */
    public static function saveTeacherPermissions(PDO $pdo, int $teacherUserId, array $data, int $adminId): bool
    {
        self::ensureSchema($pdo);

        $smsAccess = !empty($data['sms_access']) ? 1 : 0;
        $monthlyLimit = max(0, (int)($data['monthly_limit'] ?? 0));
        $canSend = !empty($data['can_send_sms']) ? 1 : 0;
        $canView = !empty($data['can_view_contacts']) ? 1 : 0;
        $canSelect = !empty($data['can_select_contacts']) ? 1 : 0;

        // Clean arrays into comma-separated strings
        $years = [];
        if (!empty($data['allowed_exam_years'])) {
            $rawYears = is_array($data['allowed_exam_years']) ? $data['allowed_exam_years'] : explode(',', (string)$data['allowed_exam_years']);
            foreach ($rawYears as $y) {
                $yInt = (int)trim((string)$y);
                if ($yInt >= 2020 && $yInt <= 2035) {
                    $years[] = $yInt;
                }
            }
            sort($years);
        }
        $allowedYearsStr = $years !== [] ? implode(',', array_unique($years)) : null;

        $types = [];
        if (!empty($data['allowed_exam_types'])) {
            $rawTypes = is_array($data['allowed_exam_types']) ? $data['allowed_exam_types'] : explode(',', (string)$data['allowed_exam_types']);
            foreach ($rawTypes as $tp) {
                $tTrim = strtoupper(trim((string)$tp));
                if ($tTrim !== '') {
                    $types[] = $tTrim;
                }
            }
            sort($types);
        }
        $allowedTypesStr = $types !== [] ? implode(',', array_unique($types)) : null;

        $locations = [];
        if (!empty($data['allowed_locations'])) {
            $rawLocs = is_array($data['allowed_locations']) ? $data['allowed_locations'] : explode(',', (string)$data['allowed_locations']);
            $allowedList = PhoneContactService::getAllowedLocations($pdo);
            foreach ($rawLocs as $l) {
                $matched = PhoneContactService::matchAllowedLocation((string)$l, $allowedList);
                if ($matched !== null) {
                    $locations[] = $matched;
                }
            }
            sort($locations);
        }
        $allowedLocationsStr = $locations !== [] ? implode(',', array_unique($locations)) : null;

        $groups = [];
        if (!empty($data['allowed_whatsapp_groups'])) {
            $rawGroups = is_array($data['allowed_whatsapp_groups']) ? $data['allowed_whatsapp_groups'] : explode(',', (string)$data['allowed_whatsapp_groups']);
            foreach ($rawGroups as $g) {
                $gTrim = trim((string)$g);
                if ($gTrim !== '') {
                    $groups[] = $gTrim;
                }
            }
            sort($groups);
        }
        $allowedGroupsStr = $groups !== [] ? implode(',', array_unique($groups)) : null;

        // Fetch teacher_id from users
        $stmtU = $pdo->prepare("SELECT teacher_id FROM users WHERE id = ? LIMIT 1");
        $stmtU->execute([$teacherUserId]);
        $teacherId = $stmtU->fetchColumn();

        // Gateway is ALWAYS locked to 'ipromo'
        $stmt = $pdo->prepare(
            "INSERT INTO teacher_sms_permissions 
            (teacher_user_id, teacher_id, sms_access, gateway, monthly_limit, can_send_sms, can_view_contacts, can_select_contacts, allowed_exam_years, allowed_exam_types, allowed_locations, allowed_whatsapp_groups, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, 'ipromo', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                sms_access = VALUES(sms_access),
                gateway = 'ipromo',
                monthly_limit = VALUES(monthly_limit),
                can_send_sms = VALUES(can_send_sms),
                can_view_contacts = VALUES(can_view_contacts),
                can_select_contacts = VALUES(can_select_contacts),
                allowed_exam_years = VALUES(allowed_exam_years),
                allowed_exam_types = VALUES(allowed_exam_types),
                allowed_locations = VALUES(allowed_locations),
                allowed_whatsapp_groups = VALUES(allowed_whatsapp_groups),
                updated_by = VALUES(updated_by),
                updated_at = NOW()"
        );

        return $stmt->execute([
            $teacherUserId,
            $teacherId !== false && $teacherId !== null ? (int)$teacherId : null,
            $smsAccess,
            $monthlyLimit,
            $canSend,
            $canView,
            $canSelect,
            $allowedYearsStr,
            $allowedTypesStr,
            $allowedLocationsStr,
            $allowedGroupsStr,
            $adminId > 0 ? $adminId : null,
        ]);
    }

    /**
     * Check if teacher SMS broadcasts are enabled globally.
     */
    public static function isTeacherSmsGloballyEnabled(PDO $pdo): bool
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'teacher_sms_global_enabled' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val === false || (string)$val === '1';
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Toggle the global emergency switch for teacher SMS broadcasts.
     */
    public static function setTeacherSmsGloballyEnabled(
        PDO $pdo,
        bool $enabled,
        int $adminUserId,
        ?string $reason = null,
        ?string $ipAddress = null
    ): bool {
        self::ensureSchema($pdo);
        $prevEnabled = self::isTeacherSmsGloballyEnabled($pdo);
        $val = $enabled ? '1' : '0';
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('teacher_sms_global_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $res = $stmt->execute([$val, $val]);

        // Audit log entry
        try {
            if ($ipAddress === null) {
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            }
            $stmtAudit = $pdo->prepare(
                "INSERT INTO teacher_sms_switch_audit (admin_user_id, previous_status, new_status, reason, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmtAudit->execute([
                $adminUserId,
                $prevEnabled ? 1 : 0,
                $enabled ? 1 : 0,
                $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                $ipAddress,
            ]);
        } catch (Throwable $e) {
            error_log('Failed to log teacher_sms_switch_audit: ' . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get recent emergency switch audit logs.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getSwitchAuditLogs(PDO $pdo, int $limit = 50): array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare(
                "SELECT a.*, u.username as admin_username
                 FROM teacher_sms_switch_audit a
                 LEFT JOIN users u ON u.id = a.admin_user_id
                 ORDER BY a.id DESC LIMIT ?"
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Compute actual SMS units consumed by a teacher in a given month.
     *
     * @return array{monthly_limit:int, used_units:int, remaining_units:int, is_exceeded:bool, month:string}
     */
    public static function getTeacherMonthlyUsage(PDO $pdo, int $teacherUserId, ?string $yearMonth = null): array
    {
        self::ensureSchema($pdo);

        if ($yearMonth === null || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $yearMonth = date('Y-m');
        }

        // Fetch monthly limit from permissions table
        $stmtLimit = $pdo->prepare("SELECT monthly_limit FROM teacher_sms_permissions WHERE teacher_user_id = ? LIMIT 1");
        $stmtLimit->execute([$teacherUserId]);
        $limitVal = $stmtLimit->fetchColumn();
        $monthlyLimit = $limitVal !== false ? (int)$limitVal : 0;

        // Sum SMS units from bulk_sms_recipients for campaigns created by this teacher
        $usedUnits = 0;
        try {
            $sqlBulk = "SELECT COALESCE(SUM(bsr.sms_units), 0)
                        FROM bulk_sms_recipients bsr
                        JOIN bulk_sms_campaigns bsc ON bsc.id = bsr.campaign_id
                        WHERE bsc.created_by = ?
                          AND DATE_FORMAT(bsc.created_at, '%Y-%m') = ?
                          AND bsr.status IN ('SENT', 'DELIVERED', 'PENDING', 'SENDING')";
            $stmtBulk = $pdo->prepare($sqlBulk);
            $stmtBulk->execute([$teacherUserId, $yearMonth]);
            $usedUnits = (int)$stmtBulk->fetchColumn();
        } catch (Throwable) {
        }

        // Also add direct sms_logs sent by this teacher user if any (excluding bulk context to prevent double count)
        try {
            $sqlLogs = "SELECT COUNT(*)
                        FROM sms_logs
                        WHERE sent_by = ?
                          AND DATE_FORMAT(created_at, '%Y-%m') = ?
                          AND (context IS NULL OR context NOT LIKE 'bulk_%')
                          AND status IN ('sent', 'pending')";
            $stmtLogs = $pdo->prepare($sqlLogs);
            $stmtLogs->execute([$teacherUserId, $yearMonth]);
            $usedUnits += (int)$stmtLogs->fetchColumn();
        } catch (Throwable) {
        }

        $remainingUnits = max(0, $monthlyLimit - $usedUnits);
        $isExceeded = $monthlyLimit > 0 && $usedUnits >= $monthlyLimit;

        return [
            'monthly_limit'   => $monthlyLimit,
            'used_units'      => $usedUnits,
            'remaining_units' => $remainingUnits,
            'is_exceeded'     => $isExceeded,
            'month'           => $yearMonth,
        ];
    }

    /**
     * Validate whether a teacher is authorized to send a specific campaign.
     * Enforces global emergency switch, permissions, IPROMO-only lockdown, and hard monthly quota limit.
     *
     * @return array{allowed:bool, error?:string, remaining_units?:int, required_units?:int}
     */
    public static function validateTeacherCanSend(PDO $pdo, int $teacherUserId, int $requiredUnits, string $requestedGateway): array
    {
        self::ensureSchema($pdo);

        // 0. Teacher account active check
        $stmtUser = $pdo->prepare("SELECT id, is_active, role, teacher_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$teacherUserId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$userRow || empty($userRow['is_active'])) {
            return [
                'allowed' => false,
                'error'   => 'Teacher user account is inactive or not found.',
            ];
        }

        // 1. Global emergency switch check
        if (!self::isTeacherSmsGloballyEnabled($pdo)) {
            return [
                'allowed' => false,
                'error'   => 'Global Emergency Broadcast Switch is currently disabled. Teacher SMS sending is temporarily blocked by administrator.',
            ];
        }

        $perm = self::getTeacherPermissions($pdo, $teacherUserId);
        if (!$perm || empty($perm['sms_access']) || empty($perm['can_send_sms'])) {
            return [
                'allowed' => false,
                'error'   => 'You do not have permission to send SMS broadcasts. Contact administrator.',
            ];
        }

        // 2. Gateway enforcement: MUST strictly be 'ipromo'
        $normGateway = SmsService::normalizeGatewayIdentifier($requestedGateway);
        if ($normGateway !== 'ipromo') {
            return [
                'allowed' => false,
                'error'   => 'Security violation: Teachers are restricted to the iPromo SMS Gateway only.',
            ];
        }

        // 3. Monthly quota hard limit check
        $usage = self::getTeacherMonthlyUsage($pdo, $teacherUserId);
        $remaining = $usage['remaining_units'];
        $limit = $usage['monthly_limit'];

        if ($limit <= 0) {
            return [
                'allowed' => false,
                'error'   => 'Your monthly SMS quota is 0. Contact administrator to assign an SMS quota.',
            ];
        }

        if ($requiredUnits > $remaining) {
            return [
                'allowed'         => false,
                'error'           => "Monthly SMS limit exceeded. Remaining: {$remaining}, Required: {$requiredUnits}.",
                'remaining_units' => $remaining,
                'required_units'  => $requiredUnits,
            ];
        }

        // 4. iPromo configuration check
        if (!function_exists('ipromo_enabled') || !ipromo_enabled($pdo) || !function_exists('ipromo_configured') || !ipromo_configured($pdo)) {
            return [
                'allowed' => false,
                'error'   => 'iPromo SMS Gateway is currently inactive or not configured in system settings. Please contact the administrator.',
            ];
        }

        return [
            'allowed'         => true,
            'remaining_units' => $remaining,
            'required_units'  => $requiredUnits,
        ];
    }

    /**
     * Restrict contact filters to a teacher's allowed scopes.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public static function enforceTeacherFilters(PDO $pdo, int $teacherUserId, array $filters): array
    {
        $perm = self::getTeacherPermissions($pdo, $teacherUserId);
        if (!$perm) {
            throw new RuntimeException('Teacher permissions not found.');
        }

        // 1. Exam Years
        $allowedYears = $perm['allowed_years_array'];
        if ($allowedYears !== []) {
            $reqYear = (int)($filters['exam_year'] ?? 0);
            if ($reqYear > 0 && !in_array($reqYear, $allowedYears, true)) {
                throw new RuntimeException("Access denied: You are not authorized to message contacts in exam year {$reqYear}.");
            }
            if ($reqYear <= 0) {
                $filters['allowed_years_subset'] = $allowedYears;
            }
        }

        // 2. Exam Types
        $allowedTypes = $perm['allowed_types_array'];
        if ($allowedTypes !== []) {
            $reqType = strtoupper(trim((string)($filters['exam_type'] ?? '')));
            if ($reqType !== '' && $reqType !== 'ALL' && !in_array($reqType, $allowedTypes, true)) {
                throw new RuntimeException("Access denied: You are not authorized to message contacts for exam type {$reqType}.");
            }
            if ($reqType === '' || $reqType === 'ALL') {
                $filters['allowed_types_subset'] = $allowedTypes;
            }
        }

        // 3. Locations
        $allowedLocs = $perm['allowed_locations_array'];
        if ($allowedLocs !== []) {
            $reqLoc = trim((string)($filters['location'] ?? ''));
            if ($reqLoc !== '' && $reqLoc !== 'all') {
                $found = false;
                foreach ($allowedLocs as $al) {
                    if (strcasecmp($reqLoc, $al) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new RuntimeException("Access denied: You are not authorized to message contacts in {$reqLoc}.");
                }
            } else {
                $filters['allowed_locations_subset'] = $allowedLocs;
            }
        }

        // 4. WhatsApp Groups
        $allowedGroups = $perm['allowed_groups_array'] ?? [];
        if ($allowedGroups !== []) {
            $reqGroup = trim((string)($filters['source_group'] ?? ''));
            if ($reqGroup !== '' && $reqGroup !== 'all') {
                $found = false;
                foreach ($allowedGroups as $ag) {
                    if (strcasecmp($reqGroup, $ag) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new RuntimeException("Access denied: You are not authorized to message contacts in WhatsApp group '{$reqGroup}'.");
                }
            } else {
                $filters['allowed_groups_subset'] = $allowedGroups;
            }
        }

        return $filters;
    }
}
