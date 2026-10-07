<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Maps a verified Google profile onto existing student/parent accounts.
 * Never creates admin/teacher/staff roles. Never elevates roles.
 */
final class GoogleAuthAccountService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
        if (function_exists('ensure_campus_schema')) {
            ensure_campus_schema($this->pdo);
        }
    }

    /**
     * @param array{google_id:string,email:string,name:string,picture:string,intent:string,invite:?string} $profile
     * @return array{ok:bool,message:string,redirect?:string,kind?:string}
     */
    public function handle(array $profile): array
    {
        $intent = strtolower(trim((string)($profile['intent'] ?? '')));
        if ($intent === 'student') {
            return $this->handleStudent($profile);
        }
        if ($intent === 'parent') {
            return $this->handleParent($profile);
        }
        if ($intent === 'staff' || $intent === 'teacher') {
            return $this->handleStaff($profile);
        }
        if ($intent === 'link_teacher' || $intent === 'admin_link_teacher' || $intent === 'link_admin') {
            return $this->handleTeacherLink($profile);
        }
        return ['ok' => false, 'message' => 'Invalid sign-in intent.'];
    }

    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,message:string,redirect?:string,kind?:string}
     */
    private function handleStudent(array $profile): array
    {
        $googleId = (string)$profile['google_id'];
        $email = strtolower((string)$profile['email']);
        $name = trim((string)($profile['name'] ?? ''));
        $picture = trim((string)($profile['picture'] ?? ''));

        $byGoogle = $this->findUserByGoogleId($googleId);
        if ($byGoogle) {
            return $this->loginExistingStudent($byGoogle, $googleId, $email, $picture);
        }

        $byEmail = $this->findStudentByEmail($email);
        if ($byEmail) {
            $role = strtolower((string)($byEmail['role'] ?? ''));
            if ($role !== 'student') {
                $this->audit('google_login_role_conflict', (int)$byEmail['id'], [
                    'email' => $email,
                    'role' => $role,
                    'intent' => 'student',
                ]);
                return [
                    'ok' => false,
                    'message' => 'This Google email belongs to a staff account. Staff must sign in at the staff login page.',
                ];
            }
            if (!$this->canLinkGoogleToUser($byEmail, $googleId)) {
                return [
                    'ok' => false,
                    'message' => 'This account is already linked to a different Google identity.',
                ];
            }
            $this->linkGoogleToUser($byEmail, $googleId, $email, $picture);
            $fresh = $this->findUserById((int)$byEmail['id']);
            return $this->loginExistingStudent($fresh ?: $byEmail, $googleId, $email, $picture);
        }

        $staff = $this->findStaffByEmail($email);
        if ($staff) {
            $this->audit('google_login_role_conflict', (int)$staff['id'], [
                'email' => $email,
                'role' => $staff['role'] ?? '',
                'intent' => 'student',
            ]);
            return [
                'ok' => false,
                'message' => 'This Google email belongs to a staff account. Staff must sign in at the staff login page.',
            ];
        }

        $created = $this->createPendingStudent($googleId, $email, $name, $picture);
        if (!$created['ok']) {
            return $created;
        }
        $user = $created['user'];
        $this->audit('google_student_created_pending', (int)$user['id'], ['email' => $email]);
        // LK: stays pending until SMS phone verify. Outside LK: activate on Google-only login.
        return $this->establishStudentSession($user);
    }

    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,message:string,redirect?:string,kind?:string}
     */
    private function handleParent(array $profile): array
    {
        $googleId = (string)$profile['google_id'];
        $email = strtolower((string)$profile['email']);
        $name = trim((string)($profile['name'] ?? ''));
        $picture = trim((string)($profile['picture'] ?? ''));
        $inviteToken = isset($profile['invite']) ? (string)$profile['invite'] : '';

        $staff = $this->findStaffByEmail($email);
        if ($staff) {
            $this->audit('google_login_role_conflict', (int)$staff['id'], [
                'email' => $email,
                'role' => $staff['role'] ?? '',
                'intent' => 'parent',
            ]);
            return [
                'ok' => false,
                'message' => 'This Google email belongs to a staff account. Use the staff login instead.',
            ];
        }

        $studentHit = $this->findStudentByEmail($email);
        if ($studentHit && empty($studentHit['google_id'])) {
            // Same email as a student: do not auto-convert to parent; ask them to use student intent.
            // Still allow a separate parent account only if they already have a parent row for this email/google.
        }

        $parent = $this->findParentByGoogleId($googleId);
        if (!$parent) {
            $parent = $this->findParentByEmail($email);
            if ($parent && !$this->canLinkGoogleToParent($parent, $googleId)) {
                return [
                    'ok' => false,
                    'message' => 'This parent account is already linked to a different Google identity.',
                ];
            }
            if ($parent) {
                $this->linkGoogleToParent($parent, $googleId, $email, $name, $picture);
                $parent = $this->findParentById((int)$parent['id']) ?: $parent;
            }
        }

        $created = false;
        if (!$parent) {
            $parent = $this->createPendingParent($googleId, $email, $name, $picture);
            $created = true;
            $this->audit('google_parent_created', (int)$parent['id'], ['email' => $email]);
        }

        $status = strtolower((string)($parent['status'] ?? 'pending'));
        if (in_array($status, ['suspended', 'disabled'], true)) {
            $this->audit('google_login_blocked_status', (int)$parent['id'], ['status' => $status, 'kind' => 'parent']);
            return [
                'ok' => false,
                'message' => $status === 'suspended'
                    ? 'This parent account is suspended.'
                    : 'This parent account is disabled.',
            ];
        }

        $auth = new ParentAuthService($this->pdo);
        $auth->completeLogin($parent);

        $phoneSvc = new PortalPhoneLinkService($this->pdo);
        // Google-only portal: never require SMS phone linking for parents.
        unset($_SESSION['needs_phone_link'], $_SESSION['phone_link_type']);
        unset($phoneSvc);

        if ($inviteToken !== '') {
            $links = new ParentLinkService($this->pdo);
            $accepted = $links->acceptInvitation((int)$parent['id'], $inviteToken);
            if (!empty($accepted['ok'])) {
                $this->audit('google_parent_invite_accepted', (int)$parent['id'], []);
                return [
                    'ok' => true,
                    'kind' => 'parent',
                    'message' => $accepted['message'],
                    'redirect' => '/parent/home.php',
                ];
            }
            // Invitation failed: still signed in; send to verify with error
            return [
                'ok' => true,
                'kind' => 'parent',
                'message' => (string)($accepted['message'] ?? 'Invitation could not be used.'),
                'redirect' => '/parent/verify.php?error=' . rawurlencode((string)($accepted['message'] ?? 'Invalid invitation')),
            ];
        }

        $children = $auth->children((int)$parent['id']);
        if ($children === []) {
            return [
                'ok' => true,
                'kind' => $created ? 'parent_pending' : 'parent',
                'message' => 'Parent account verification required.',
                'redirect' => '/parent/verify.php',
            ];
        }

        return [
            'ok' => true,
            'kind' => 'parent',
            'message' => 'Signed in.',
            'redirect' => '/parent/home.php',
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @return array{ok:bool,message:string,redirect?:string,kind?:string}
     */
    private function loginExistingStudent(array $user, string $googleId, string $email, string $picture): array
    {
        $role = strtolower((string)($user['role'] ?? ''));
        if ($role !== 'student') {
            $this->audit('google_login_role_conflict', (int)$user['id'], [
                'role' => $role,
                'intent' => 'student',
            ]);
            return [
                'ok' => false,
                'message' => 'This Google account is linked to a non-student role and cannot use the student portal.',
            ];
        }
        $status = $this->effectiveUserStatus($user);
        if ($status === 'suspended') {
            return ['ok' => false, 'message' => 'This student account is suspended.'];
        }
        if ($status === 'disabled') {
            return ['ok' => false, 'message' => 'This student account is disabled.'];
        }
        // Pending students may continue to SMS phone linking, which activates the account.
        $this->linkGoogleToUser($user, $googleId, $email, $picture);
        return $this->establishStudentSession($user);
    }

    /**
     * @param array<string,mixed> $user
     * @return array{ok:bool,message:string,redirect?:string,kind?:string}
     */
    private function establishStudentSession(array $user): array
    {
        if (function_exists('regenerate_session')) {
            regenerate_session();
        }
        if (function_exists('clear_cross_portal_session')) {
            clear_cross_portal_session('student');
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role'] = 'student';
        $_SESSION['last_activity'] = time();
        try {
            if (!function_exists('record_student_portal_login')) {
                $helpers = dirname(__DIR__, 2) . '/student/otp_helpers.php';
                if (is_file($helpers)) {
                    require_once $helpers;
                }
            }
            if (function_exists('record_student_portal_login')) {
                record_student_portal_login($this->pdo, (int)$user['id']);
            } else {
                $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
            }
        } catch (Throwable $e) {
            // column may be missing on very old DBs
        }
        $this->audit('google_login', (int)$user['id'], [
            'kind' => 'student',
            'country' => GeoIpService::countryCode(),
        ]);
        $phoneSvc = new PortalPhoneLinkService($this->pdo);
        // Google-only portal: never require SMS phone linking.
        $phoneSvc->activateStudentWithoutPhone((int)$user['id']);
        unset($_SESSION['needs_phone_link'], $_SESSION['phone_link_type']);

        // Bind this browser as a trusted device without SMS OTP.
        try {
            if (!function_exists('student_devices')) {
                $helpers = dirname(__DIR__, 2) . '/student/device_helpers.php';
                if (is_file($helpers)) {
                    require_once $helpers;
                }
            }
            $svc = function_exists('student_devices') ? student_devices($this->pdo) : null;
            if ($svc) {
                $gate = $svc->beginLogin((int)$user['id'], 'google');
                $gateStatus = (string)($gate['status'] ?? '');
                if ($gateStatus === 'device_limit' || $gateStatus === 'device_blocked') {
                    $_SESSION['student_device_choice'] = $gateStatus;
                    $_SESSION['student_device_blocked_until'] = (string)($gate['blocked_until'] ?? '');
                    return [
                        'ok' => true,
                        'kind' => 'student',
                        'message' => (string)($gate['message'] ?? ''),
                        'redirect' => '/student/device_gate.php',
                    ];
                }
                if ($gateStatus === 'ok' && !empty($gate['device_id'])) {
                    $svc->activateSession((int)$user['id'], (int)$gate['device_id']);
                    $svc->clearPending();
                    $svc->onSignedIn((int)$user['id'], (int)$gate['device_id'], 'google', !empty($gate['new_device']));
                }
            }
        } catch (Throwable $e) {
            error_log('google student device bind: ' . $e->getMessage());
        }

        $redirect = function_exists('student_post_login_url')
            ? student_post_login_url()
            : '/student/dashboard.php';
        return [
            'ok' => true,
            'kind' => 'student',
            'message' => 'Signed in.',
            'redirect' => $redirect,
        ];
    }

    /**
     * @return array{ok:bool,message:string,user?:array<string,mixed>}
     */
    private function createPendingStudent(string $googleId, string $email, string $name, string $picture): array
    {
        $username = $this->uniqueStudentUsername($email, $googleId);
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $display = $name !== '' ? $name : (string)strtok($email, '@');
        if ($display === '') {
            $display = 'Student';
        }

        try {
            $this->pdo->beginTransaction();
            $cols = $this->userColumns();
            $data = [
                'username' => $username,
                'password_hash' => $passwordHash,
                'role' => 'student',
                'is_active' => 0,
            ];
            if (isset($cols['google_id'])) {
                $data['google_id'] = $googleId;
            }
            if (isset($cols['google_email'])) {
                $data['google_email'] = $email;
            }
            if (isset($cols['profile_image']) && $picture !== '') {
                $data['profile_image'] = mb_substr($picture, 0, 500);
            }
            if (isset($cols['account_status'])) {
                $data['account_status'] = 'pending';
            }
            if (isset($cols['student_login_count'])) {
                $data['student_login_count'] = 0;
            }
            if (isset($cols['theme_preference'])) {
                $data['theme_preference'] = 'dark';
            }
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            $this->pdo->prepare(
                'INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')'
            )->execute(array_values($data));
            $userId = (int)$this->pdo->lastInsertId();

            // Google-created students have NULL whatsapp_number until phone verified/linked.
            $profileCols = $this->studentProfileColumns();
            $profile = [
                'user_id' => $userId,
                'full_name' => mb_substr($display, 0, 150),
            ];
            if (isset($profileCols['email'])) {
                $profile['email'] = $email;
            }
            if (isset($profileCols['whatsapp_number'])) {
                $profile['whatsapp_number'] = null;
            }
            $pFields = array_keys($profile);
            $pPh = array_fill(0, count($pFields), '?');
            $this->pdo->prepare(
                'INSERT INTO student_profiles (' . implode(',', $pFields) . ') VALUES (' . implode(',', $pPh) . ')'
            )->execute(array_values($profile));

            $this->pdo->commit();
            $user = $this->findUserById($userId);
            if (!$user) {
                throw new RuntimeException('Student create failed.');
            }
            return ['ok' => true, 'message' => 'Created.', 'user' => $user];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('google create student: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not create the student account.'];
        }
    }

    /** @return array<string,true> */
    private function studentProfileColumns(): array
    {
        static $cols = null;
        if (is_array($cols)) {
            return $cols;
        }
        $cols = [];
        try {
            foreach ($this->pdo->query('SHOW COLUMNS FROM student_profiles')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[(string)$c['Field']] = true;
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        return $cols;
    }

    /**
     * @return array<string,mixed>
     */
    private function createPendingParent(string $googleId, string $email, string $name, string $picture): array
    {
        $cols = $this->parentColumns();
        $fields = ['status'];
        $values = ['pending'];
        $placeholders = ['?'];
        if (isset($cols['email'])) {
            $fields[] = 'email';
            $values[] = $email;
            $placeholders[] = '?';
        }
        if (isset($cols['google_id'])) {
            $fields[] = 'google_id';
            $values[] = $googleId;
            $placeholders[] = '?';
        }
        if (isset($cols['name'])) {
            $fields[] = 'name';
            $values[] = $name !== '' ? $name : null;
            $placeholders[] = '?';
        }
        if (isset($cols['profile_image']) && $picture !== '') {
            $fields[] = 'profile_image';
            $values[] = $picture;
            $placeholders[] = '?';
        }
        // phone left NULL for Google-only parents
        $this->pdo->prepare(
            'INSERT INTO parent_accounts (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')'
        )->execute($values);
        $id = (int)$this->pdo->lastInsertId();
        $row = $this->findParentById($id);
        if (!$row) {
            throw new RuntimeException('Parent create failed.');
        }
        return $row;
    }

    private function uniqueStudentUsername(string $email, string $googleId): string
    {
        $maxLen = 50;
        try {
            foreach ($this->pdo->query('SHOW COLUMNS FROM users LIKE \'username\'')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (preg_match('/varchar\((\d+)\)/i', (string)($c['Type'] ?? ''), $m)) {
                    $maxLen = max(20, (int)$m[1]);
                }
            }
        } catch (Throwable $e) {
        }

        $base = strtolower(preg_replace('/[^a-z0-9._+-]+/i', '', $email) ?? '');
        if ($base === '') {
            $base = 'g_' . substr($googleId, 0, 16);
        }
        $base = substr($base, 0, $maxLen);
        $candidate = $base;
        $n = 0;
        while ($this->usernameTaken($candidate)) {
            $n++;
            $suffix = '_' . $n;
            $candidate = substr($base, 0, $maxLen - strlen($suffix)) . $suffix;
            if ($n > 50) {
                $candidate = 'g_' . substr(hash('sha256', $googleId . microtime(true)), 0, min(20, $maxLen - 2));
                break;
            }
        }
        return $candidate;
    }

    private function usernameTaken(string $username): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $user
     */
    private function effectiveUserStatus(array $user): string
    {
        if (!empty($user['deleted_at'])) {
            return 'disabled';
        }
        $status = strtolower(trim((string)($user['account_status'] ?? '')));
        if (in_array($status, ['pending', 'active', 'suspended', 'disabled'], true)) {
            return $status;
        }
        return ((int)($user['is_active'] ?? 0) === 1) ? 'active' : 'suspended';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function canLinkGoogleToUser(array $user, string $googleId): bool
    {
        $existing = trim((string)($user['google_id'] ?? ''));
        return $existing === '' || hash_equals($existing, $googleId);
    }

    /**
     * @param array<string,mixed> $parent
     */
    private function canLinkGoogleToParent(array $parent, string $googleId): bool
    {
        $existing = trim((string)($parent['google_id'] ?? ''));
        return $existing === '' || hash_equals($existing, $googleId);
    }

    private function linkGoogleToUser(array $user, string $googleId, string $email, string $picture): void
    {
        $userId = (int)$user['id'];
        $cols = $this->userColumns();
        $sets = [];
        $vals = [];
        
        $currentGoogleId = trim((string)($user['google_id'] ?? ''));
        if (isset($cols['google_id']) && $currentGoogleId === '') {
            $sets[] = 'google_id = ?';
            $vals[] = $googleId;
        }
        
        $currentEmail = trim((string)($user['google_email'] ?? ''));
        if (isset($cols['google_email']) && $currentEmail === '') {
            $sets[] = 'google_email = ?';
            $vals[] = $email;
        }
        
        $currentPicture = trim((string)($user['profile_image'] ?? ''));
        if (isset($cols['profile_image']) && $picture !== '' && $picture !== $currentPicture) {
            $sets[] = 'profile_image = ?';
            $vals[] = $picture;
        }
        
        if ($sets === []) {
            return;
        }
        $vals[] = $userId;
        $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        try {
            $this->pdo->prepare("
                UPDATE student_profiles
                SET email = COALESCE(NULLIF(email, ''), ?)
                WHERE user_id = ?
            ")->execute([$email, $userId]);
        } catch (Throwable $e) {
        }
    }

    private function linkGoogleToParent(array $parent, string $googleId, string $email, string $name, string $picture): void
    {
        $parentId = (int)$parent['id'];
        $cols = $this->parentColumns();
        $sets = [];
        $vals = [];
        
        $currentGoogleId = trim((string)($parent['google_id'] ?? ''));
        if (isset($cols['google_id']) && $currentGoogleId === '') {
            $sets[] = 'google_id = ?';
            $vals[] = $googleId;
        }
        
        $currentEmail = trim((string)($parent['email'] ?? ''));
        if (isset($cols['email']) && $currentEmail === '') {
            $sets[] = 'email = ?';
            $vals[] = $email;
        }
        
        $currentName = trim((string)($parent['name'] ?? ''));
        if (isset($cols['name']) && $name !== '' && $currentName === '') {
            $sets[] = 'name = ?';
            $vals[] = $name;
        }
        
        $currentPicture = trim((string)($parent['profile_image'] ?? ''));
        if (isset($cols['profile_image']) && $picture !== '' && $picture !== $currentPicture) {
            $sets[] = 'profile_image = ?';
            $vals[] = $picture;
        }
        
        if ($sets === []) {
            return;
        }
        $vals[] = $parentId;
        $this->pdo->prepare('UPDATE parent_accounts SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    /** @return array<string,mixed>|null */
    private function findUserByGoogleId(string $googleId): ?array
    {
        if (!isset($this->userColumns()['google_id'])) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE google_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$googleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findStudentByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.deleted_at IS NULL
              AND (
                    LOWER(TRIM(COALESCE(sp.email, ''))) = ?
                 OR LOWER(TRIM(COALESCE(u.google_email, ''))) = ?
                 OR (u.role = 'student' AND LOWER(TRIM(u.username)) = ?)
              )
            ORDER BY CASE WHEN u.role = 'student' THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([$email, $email, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findStaffByEmail(string $email): ?array
    {
        // Teachers table email + users role admin/teacher
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*
                FROM users u
                LEFT JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
                WHERE u.deleted_at IS NULL
                  AND u.role IN ('admin', 'teacher')
                  AND (
                        LOWER(TRIM(COALESCE(t.email, ''))) = ?
                     OR LOWER(TRIM(COALESCE(u.google_email, ''))) = ?
                     OR LOWER(TRIM(u.username)) = ?
                  )
                LIMIT 1
            ");
            $stmt->execute([$email, $email, $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    private function findParentByGoogleId(string $googleId): ?array
    {
        if (!isset($this->parentColumns()['google_id'])) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM parent_accounts WHERE google_id = ? LIMIT 1');
        $stmt->execute([$googleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findParentByEmail(string $email): ?array
    {
        if (!isset($this->parentColumns()['email'])) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM parent_accounts WHERE LOWER(TRIM(email)) = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findParentById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parent_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,true> */
    private function userColumns(): array
    {
        static $cols = null;
        if (is_array($cols)) {
            return $cols;
        }
        $cols = [];
        try {
            foreach ($this->pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[(string)$c['Field']] = true;
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        return $cols;
    }

    /** @return array<string,true> */
    private function parentColumns(): array
    {
        static $cols = null;
        if (is_array($cols)) {
            return $cols;
        }
        $cols = [];
        try {
            foreach ($this->pdo->query('SHOW COLUMNS FROM parent_accounts')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[(string)$c['Field']] = true;
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        return $cols;
    }

    /** @param array<string,mixed> $meta */
    private function audit(string $action, ?int $recordId, array $meta): void
    {
        if (function_exists('log_audit')) {
            $prev = $_SESSION['username'] ?? null;
            if (empty($_SESSION['username'])) {
                $_SESSION['username'] = 'google_oauth';
            }
            log_audit($this->pdo, $action, 'users', $recordId, null, $meta);
            if ($prev === null) {
                unset($_SESSION['username']);
            } else {
                $_SESSION['username'] = $prev;
            }
        }
    }

    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,message:string,redirect?:string}
     */
    private function handleStaff(array $profile): array
    {
        $googleId = trim((string)($profile['google_id'] ?? ''));
        $email = strtolower(trim((string)($profile['email'] ?? '')));

        if ($googleId === '' || $email === '') {
            return ['ok' => false, 'message' => 'Google profile is missing an ID or email address.'];
        }

        // Find staff account (admin, teacher, staff) by Google ID first, or by Google email, or teacher profile email, or username
        $stmt = $this->pdo->prepare("
            SELECT u.* FROM users u
            LEFT JOIN teachers t ON t.id = u.teacher_id
            WHERE u.role != 'student' AND u.deleted_at IS NULL
              AND (
                u.google_id = ?
                OR (u.google_email = ? AND (u.google_id IS NULL OR u.google_id = ''))
                OR (u.role = 'teacher' AND LOWER(t.email) = LOWER(?) AND (u.google_id IS NULL OR u.google_id = ''))
                OR (LOWER(u.username) = LOWER(?) AND (u.google_id IS NULL OR u.google_id = ''))
              )
            ORDER BY
              CASE WHEN u.google_id IS NOT NULL AND u.google_id != '' THEN 0
                   WHEN u.google_email IS NOT NULL AND u.google_email != '' THEN 1
                   ELSE 2 END ASC
            LIMIT 1
        ");
        $stmt->execute([$googleId, $email, $email, $email]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            $this->audit('staff_google_login_unlinked', null, [
                'email' => $email,
                'google_id' => $googleId,
            ]);

            // Helpful differentiation if email belongs to student or parent
            try {
                $stmtStudent = $this->pdo->prepare("SELECT id FROM users WHERE role = 'student' AND deleted_at IS NULL AND (google_id = ? OR google_email = ?) LIMIT 1");
                $stmtStudent->execute([$googleId, $email]);
                if ($stmtStudent->fetch()) {
                    return [
                        'ok' => false,
                        'message' => "This Google account ({$email}) belongs to a student portal account. Staff members must sign in with an authorized staff Google account.",
                    ];
                }

                $stmtParent = $this->pdo->prepare("SELECT id FROM parent_accounts WHERE (google_id = ? OR email = ?) LIMIT 1");
                $stmtParent->execute([$googleId, $email]);
                if ($stmtParent->fetch()) {
                    return [
                        'ok' => false,
                        'message' => "This Google account ({$email}) belongs to a parent portal account. Staff members must sign in with an authorized staff Google account.",
                    ];
                }
            } catch (Throwable $e) {
            }

            return [
                'ok' => false,
                'message' => "No staff account is linked to this Google account ({$email}). Please contact the college administrator to link your staff account.",
            ];
        }

        // Check if account is active
        if (array_key_exists('is_active', $staff) && (int)$staff['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'This staff account is currently inactive. Please contact the administrator.'];
        }

        $accountStatus = strtolower((string)($staff['account_status'] ?? 'active'));
        if (in_array($accountStatus, ['disabled', 'suspended'], true)) {
            return ['ok' => false, 'message' => 'This staff account is currently suspended or disabled.'];
        }

        $oauthStatus = (string)($staff['teacher_oauth_status'] ?? 'not_linked');
        if ($oauthStatus === 'disabled') {
            return ['ok' => false, 'message' => 'Google sign-in for this staff account has been disabled by an administrator.'];
        }

        // Release any conflicting google_id or google_email on other accounts (such as deleted student accounts)
        $this->pdo->prepare("
            UPDATE users
            SET google_id = NULL,
                google_email = NULL
            WHERE (google_id = ? OR (google_email = ? AND (google_id IS NULL OR google_id = '')))
              AND id != ?
        ")->execute([$googleId, $email, (int)$staff['id']]);

        // If matched with missing google_id or google_email, update credentials and mark as linked
        if (empty($staff['google_id']) || empty($staff['google_email']) || $staff['google_id'] !== $googleId) {
            $now = date('Y-m-d H:i:s');
            $this->pdo->prepare("
                UPDATE users
                SET google_id = ?,
                    google_email = ?,
                    teacher_oauth_status = 'linked',
                    teacher_oauth_linked_at = COALESCE(teacher_oauth_linked_at, ?)
                WHERE id = ?
            ")->execute([$googleId, $email, $now, (int)$staff['id']]);
            $staff['google_id'] = $googleId;
            $staff['google_email'] = $email;
            $staff['teacher_oauth_status'] = 'linked';
        }

        // Log in staff member
        if (!function_exists('complete_staff_portal_login')) {
            require_once __DIR__ . '/../../student/otp_helpers.php';
        }
        if (function_exists('complete_staff_portal_login')) {
            complete_staff_portal_login($staff);
        } else {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['user_id'] = (int)$staff['id'];
            $_SESSION['role'] = (string)$staff['role'];
            $_SESSION['teacher_id'] = !empty($staff['teacher_id']) ? (int)$staff['teacher_id'] : null;
            $_SESSION['username'] = $staff['username'];
            $_SESSION['last_activity'] = time();
        }

        // Update last login
        try {
            $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([(int)$staff['id']]);
        } catch (Throwable $e) {
        }

        $this->audit('staff_google_login_success', (int)$staff['id'], [
            'email' => $email,
            'google_id' => $googleId,
            'username' => $staff['username'],
            'role' => $staff['role'],
        ]);

        return [
            'ok' => true,
            'message' => 'Signed in successfully.',
            'redirect' => '/dashboard.php',
        ];
    }

    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,message:string,redirect?:string}
     */
    private function handleTeacher(array $profile): array
    {
        return $this->handleStaff($profile);
    }

    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,message:string,redirect?:string}
     */
    private function handleTeacherLink(array $profile): array
    {
        $googleId = trim((string)($profile['google_id'] ?? ''));
        $email = strtolower(trim((string)($profile['email'] ?? '')));
        $inviteToken = trim((string)($profile['invite'] ?? ''));

        if ($googleId === '' || $email === '') {
            return ['ok' => false, 'message' => 'Google profile is missing an ID or email address.'];
        }

        // Collision check: Is this Google account already linked to ANY other user?
        $stmt = $this->pdo->prepare("SELECT id, username, role FROM users WHERE (google_id = ? OR google_email = ?) AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$googleId, $email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        // Resolve target staff user
        $targetUserId = 0;
        $linkedByAdminId = null;
        $inviteId = null;

        if ($inviteToken !== '') {
            // Invite token flow
            $migration = new TeacherMigrationService($this->pdo);
            $validation = $migration->validateInvite($inviteToken);
            if (!$validation['ok']) {
                return ['ok' => false, 'message' => $validation['message'] ?? 'Invalid or expired invitation link.'];
            }
            $inviteData = $validation['invite'] ?? [];
            $targetUserId = (int)($inviteData['user_id'] ?? 0);
            $linkedByAdminId = (int)($inviteData['created_by'] ?? 0);
            $inviteId = (int)($inviteData['id'] ?? 0);

            if (!empty($inviteData['expected_email']) && strcasecmp($email, (string)$inviteData['expected_email']) !== 0) {
                return [
                    'ok' => false,
                    'message' => "This invitation was issued for '{$inviteData['expected_email']}'. You signed in with '{$email}'. Please use the correct Google account.",
                ];
            }
        } else {
            // Session-based linking flow (staff member or admin is logged in)
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
            $sessionRole = (string)($_SESSION['role'] ?? '');

            if ($sessionUserId < 1) {
                return [
                    'ok' => false,
                    'message' => 'You must be signed in with your staff account to link your Google account.',
                ];
            }

            if ($sessionRole === 'admin'
                && !empty($_SESSION['admin_linking_staff_ready'])
                && !empty($_SESSION['admin_linking_teacher_user_id'])
            ) {
                $targetUserId = (int)$_SESSION['admin_linking_teacher_user_id'];
                $linkedByAdminId = $sessionUserId;
                unset(
                    $_SESSION['admin_linking_teacher_user_id'],
                    $_SESSION['admin_linking_staff_ready'],
                    $_SESSION['admin_linking_staff_intent']
                );
            } elseif ($sessionRole === 'teacher' || $sessionRole === 'admin') {
                $targetUserId = $sessionUserId;
                $linkedByAdminId = $sessionUserId;
            } else {
                return ['ok' => false, 'message' => 'Only authorized staff accounts can be linked.'];
            }
        }

        if ($targetUserId < 1) {
            return ['ok' => false, 'message' => 'Target staff account could not be resolved.'];
        }

        // If Google account is already linked to ANOTHER user, reject!
        if ($existingUser && (int)$existingUser['id'] !== $targetUserId) {
            return [
                'ok' => false,
                'message' => "This Google account ({$email}) is already linked to another account ({$existingUser['username']} - {$existingUser['role']}). Each Google account can only be linked to one user.",
            ];
        }

        // Check target staff account
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$targetUserId]);
        $staffUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staffUser || !in_array(($staffUser['role'] ?? ''), ['admin', 'teacher'], true)) {
            return ['ok' => false, 'message' => 'Staff account not found.'];
        }

        try {
            $this->pdo->beginTransaction();

            // Release any conflicting google_id or google_email on other accounts (such as deleted accounts)
            $this->pdo->prepare("
                UPDATE users
                SET google_id = NULL,
                    google_email = NULL
                WHERE (google_id = ? OR (google_email = ? AND (google_id IS NULL OR google_id = '')))
                  AND id != ?
            ")->execute([$googleId, $email, $targetUserId]);

            $note = "\n[" . date('Y-m-d H:i:s') . "] Google account ({$email}) linked by user #" . ($linkedByAdminId ?: $targetUserId);

            $stmt = $this->pdo->prepare("
                UPDATE users
                SET google_id = ?,
                    google_email = ?,
                    teacher_oauth_status = 'linked',
                    teacher_oauth_linked_at = NOW(),
                    teacher_oauth_linked_by = ?,
                    teacher_oauth_notes = CONCAT(COALESCE(teacher_oauth_notes, ''), ?)
                WHERE id = ?
            ");
            $stmt->execute([$googleId, $email, $linkedByAdminId ?: null, $note, $targetUserId]);

            if ($inviteId !== null && $inviteId > 0) {
                $this->pdo->prepare("UPDATE teacher_oauth_invites SET used_at = NOW() WHERE id = ?")->execute([$inviteId]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('handleTeacherLink failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to link Google account. Please try again.'];
        }

        $this->audit('staff_oauth_linked', $targetUserId, [
            'username' => $staffUser['username'],
            'google_email' => $email,
            'google_id' => $googleId,
            'role' => $staffUser['role'],
            'linked_by' => $linkedByAdminId,
            'via_invite' => $inviteId !== null,
        ]);

        // If linking was initiated by admin in an admin session for another user:
        if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin' && (int)$_SESSION['user_id'] !== $targetUserId) {
            return [
                'ok' => true,
                'message' => "Account '{$staffUser['username']}' was successfully linked to {$email}.",
                'redirect' => '/admin/teacher_oauth_migration.php?notice=linked',
            ];
        }

        // Log in the staff member
        if (!function_exists('complete_staff_portal_login')) {
            require_once __DIR__ . '/../../student/otp_helpers.php';
        }
        $staffUser['google_id'] = $googleId;
        $staffUser['google_email'] = $email;
        $staffUser['teacher_oauth_status'] = 'linked';
        complete_staff_portal_login($staffUser);

        return [
            'ok' => true,
            'message' => "Your Google account ({$email}) has been successfully linked! You can now use 'Continue with Google'.",
            'redirect' => ($staffUser['role'] === 'teacher') ? '/teachers/profile.php?linked=1' : '/dashboard.php?linked=1',
        ];
    }
}

