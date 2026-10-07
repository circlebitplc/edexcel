<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ParentAuthService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    /**
     * @return array{ok:bool,message:string,show_otp:bool}
     */
    public function sendOtp(string $phone): array
    {
        $phone = $this->normalize($phone);
        if (!preg_match('/^94(?:7\d{8})$/', $phone)) {
            return ['ok' => false, 'message' => 'Enter a valid Sri Lankan WhatsApp number.', 'show_otp' => false];
        }
        if (function_exists('login_is_locked')) {
            if (login_is_locked($this->pdo, 'parent:' . $phone) || login_is_locked($this->pdo, 'parent_ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
                return ['ok' => false, 'message' => 'Too many attempts. Wait 15 minutes and try again.', 'show_otp' => false];
            }
        }
        $stmt = $this->pdo->prepare('SELECT created_at FROM parent_otps WHERE phone = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$phone]);
        $last = $stmt->fetchColumn();
        if ($last && strtotime((string)$last) > time() - 60) {
            return ['ok' => true, 'message' => 'A code was already sent. Wait 60 seconds to resend.', 'show_otp' => true];
        }
        $otp = (string)random_int(100000, 999999);
        $this->pdo->prepare("
            INSERT INTO parent_otps (phone, otp_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ")->execute([$phone, password_hash($otp, PASSWORD_DEFAULT)]);
        if (function_exists('login_record_failure')) {
            login_record_failure($this->pdo, 'parent_ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        }

        $sent = false;
        if (function_exists('otp_send_via_whatsapp')) {
            $sent = otp_send_via_whatsapp(
                $phone,
                "Edexcel College parent login\n\nYour sign-in code is: *{$otp}*\n\nThis code expires in 10 minutes."
            );
        }
        require_once dirname(__DIR__, 2) . '/config/otp_support_log.php';
        otp_support_log_record($this->pdo, [
            'purpose' => 'parent_login',
            'phone' => $phone,
            'otp' => $otp,
            'channel' => 'whatsapp',
            'sent' => $sent,
        ]);
        return [
            'ok' => true,
            'show_otp' => true,
            'message' => $sent
                ? 'We sent a 6-digit code to WhatsApp.'
                : 'Your code is ready. If WhatsApp is delayed, tap resend in a minute.',
        ];
    }

    /**
     * @return array{ok:bool,message:string,parent:?array}
     */
    public function verify(string $phone, string $otp): array
    {
        $phone = $this->normalize($phone);
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['ok' => false, 'message' => 'Enter the 6-digit code.', 'parent' => null];
        }
        $stmt = $this->pdo->prepare("
            SELECT * FROM parent_otps
            WHERE phone = ? AND verified_at IS NULL AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'message' => 'That code has expired. Request a new one.', 'parent' => null];
        }
        if ((int)$row['attempts'] >= 5) {
            return ['ok' => false, 'message' => 'Too many attempts. Request a new code.', 'parent' => null];
        }
        $this->pdo->prepare('UPDATE parent_otps SET attempts = attempts + 1 WHERE id = ?')->execute([(int)$row['id']]);
        if (!password_verify($otp, (string)$row['otp_hash'])) {
            if (function_exists('login_record_failure')) {
                login_record_failure($this->pdo, 'parent:' . $phone);
            }
            return ['ok' => false, 'message' => 'That code is not correct.', 'parent' => null];
        }
        $this->pdo->prepare('UPDATE parent_otps SET verified_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
        if (function_exists('login_clear_failures')) {
            login_clear_failures($this->pdo, 'parent:' . $phone);
            login_clear_failures($this->pdo, 'parent_ip:' . (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        }
        [$parent, $created] = $this->upsertParent($phone);
        $status = strtolower((string)($parent['status'] ?? 'active'));
        if (in_array($status, ['suspended', 'disabled'], true)) {
            return [
                'ok' => false,
                'message' => $status === 'suspended'
                    ? 'This parent account is suspended.'
                    : 'This parent account is disabled.',
                'parent' => null,
            ];
        }
        $this->linkStudentsByPhone((int)$parent['id'], $phone);
        if ($created) {
            $this->notifyAdminsOfNewParent($parent);
        }
        return ['ok' => true, 'message' => 'Signed in.', 'parent' => $parent];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getAccount(int $parentId): ?array
    {
        if ($parentId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM parent_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$parentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function isPortalAllowed(int $parentId): bool
    {
        $row = $this->getAccount($parentId);
        if (!$row) {
            return false;
        }
        $status = strtolower((string)($row['status'] ?? 'active'));
        return !in_array($status, ['suspended', 'disabled'], true);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function children(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                u.id,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS student_name,
                sp.parent_name
            FROM parent_students ps
            JOIN users u ON u.id = ps.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE ps.parent_id = ?
              AND u.role = 'student'
              AND u.deleted_at IS NULL
              AND u.is_active = 1
            ORDER BY student_name
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function completeLogin(array $parent): void
    {
        if (function_exists('regenerate_session')) {
            regenerate_session();
        }
        if (function_exists('clear_cross_portal_session')) {
            clear_cross_portal_session('parent');
        }
        $_SESSION['parent_id'] = (int)$parent['id'];
        $_SESSION['parent_phone'] = (string)($parent['phone'] ?? '');
        $_SESSION['parent_email'] = (string)($parent['email'] ?? '');
        $_SESSION['parent_name'] = (string)($parent['name'] ?? '');
        $_SESSION['parent_status'] = (string)($parent['status'] ?? 'active');
        $_SESSION['last_activity'] = time();
        $this->pdo->prepare('UPDATE parent_accounts SET last_login_at = NOW() WHERE id = ?')
            ->execute([(int)$parent['id']]);
        if (function_exists('log_audit')) {
            $prev = $_SESSION['username'] ?? null;
            $_SESSION['username'] = 'parent:' . ((string)($parent['phone'] ?? $parent['email'] ?? $parent['id']));
            log_audit($this->pdo, 'parent_login', 'parent_accounts', (int)$parent['id'], null, [
                'via' => !empty($parent['google_id']) ? 'google_or_linked' : 'otp',
            ]);
            if ($prev === null) {
                unset($_SESSION['username']);
            } else {
                $_SESSION['username'] = $prev;
            }
        }
    }

    public static function logout(): void
    {
        unset(
            $_SESSION['parent_id'],
            $_SESSION['parent_phone'],
            $_SESSION['parent_email'],
            $_SESSION['parent_name'],
            $_SESSION['parent_status'],
            $_SESSION['parent_student_id']
        );
    }

    public static function isLoggedIn(): bool
    {
        return (int)($_SESSION['parent_id'] ?? 0) > 0;
    }

    public function ownsStudent(int $parentId, int $studentId): bool
    {
        if ($parentId < 1 || $studentId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM parent_students WHERE parent_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$parentId, $studentId]);
        return (bool)$stmt->fetchColumn();
    }

    /** Public wrapper used after Google SMS phone verification. */
    public function linkChildrenByVerifiedPhone(int $parentId, string $phone): void
    {
        $this->linkStudentsByPhone($parentId, $this->normalize($phone));
    }

    /**
     * @return array{0: array<string,mixed>, 1: bool}
     */
    private function upsertParent(string $phone): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parent_accounts WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [$row, false];
        }
        $this->pdo->prepare('INSERT INTO parent_accounts (phone) VALUES (?)')->execute([$phone]);
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => (int)$this->pdo->lastInsertId(), 'phone' => $phone];
        return [$row, true];
    }

    /**
     * @param array<string,mixed> $parent
     */
    private function notifyAdminsOfNewParent(array $parent): void
    {
        if (!function_exists('campus_notify_new_parent_registration')) {
            $campus = dirname(__DIR__, 2) . '/config/campus.php';
            if (is_file($campus)) {
                require_once $campus;
            }
        }
        if (!function_exists('campus_notify_new_parent_registration')) {
            return;
        }

        $kids = $this->children((int)($parent['id'] ?? 0));
        $childNames = [];
        $parentName = trim((string)($parent['name'] ?? ''));
        foreach ($kids as $kid) {
            $childNames[] = (string)($kid['student_name'] ?? '');
            if ($parentName === '' && trim((string)($kid['parent_name'] ?? '')) !== '') {
                $parentName = trim((string)$kid['parent_name']);
            }
        }

        campus_notify_new_parent_registration(
            $this->pdo,
            (int)($parent['id'] ?? 0),
            (string)($parent['phone'] ?? ''),
            $parentName,
            $childNames
        );
    }

    private function linkStudentsByPhone(int $parentId, string $phone): void
    {
        $variants = [$phone];
        if (str_starts_with($phone, '94') && strlen($phone) === 11) {
            $variants[] = '0' . substr($phone, 2);
        }
        $in = implode(',', array_fill(0, count($variants), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id, parent_name FROM student_profiles
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(parent_whatsapp,''), '+', ''), ' ', ''), '-', ''), '.', '') IN ($in)
            ");
            $stmt->execute($variants);
            $ins = $this->pdo->prepare('INSERT IGNORE INTO parent_students (parent_id, student_id) VALUES (?, ?)');
            $name = '';
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $ins->execute([$parentId, (int)$row['user_id']]);
                if ($name === '' && trim((string)($row['parent_name'] ?? '')) !== '') {
                    $name = trim((string)$row['parent_name']);
                }
            }
            if ($name !== '') {
                $this->pdo->prepare('UPDATE parent_accounts SET name = COALESCE(NULLIF(name, \'\'), ?) WHERE id = ?')
                    ->execute([$name, $parentId]);
            }
        } catch (Throwable $e) {
            error_log('parent link: ' . $e->getMessage());
        }
    }

    private function normalize(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '94' . substr($phone, 1);
        }
        return $phone;
    }
}
