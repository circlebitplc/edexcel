<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * After Google sign-in, require a verified Sri Lankan mobile number via SMS OTP
 * when the visitor IP is in Sri Lanka. Outside LK, Google-only login is enough.
 */
final class PortalPhoneLinkService
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
        if (!function_exists('valid_lk_phone')) {
            $helpers = dirname(__DIR__, 2) . '/student/otp_helpers.php';
            if (is_file($helpers)) {
                require_once $helpers;
            }
        }
        if (!function_exists('sms_send')) {
            require_once dirname(__DIR__, 2) . '/config/sms_gateway.php';
        }
    }

    /**
     * SMS phone linking after Google sign-in is disabled.
     * Students and parents use Google-only portal login (no phone/SMS OTP).
     */
    public function smsPhoneLinkRequired(): bool
    {
        return false;
    }

    public function studentNeedsPhone(int $userId): bool
    {
        if (!$this->smsPhoneLinkRequired()) {
            return false;
        }
        if ($userId < 1) {
            return true;
        }
        $phone = $this->studentBoundPhone($userId);
        return $phone === '' || !valid_lk_phone($phone);
    }

    public function parentNeedsPhone(int $parentId): bool
    {
        if (!$this->smsPhoneLinkRequired()) {
            return false;
        }
        if ($parentId < 1) {
            return true;
        }
        $phone = $this->parentBoundPhone($parentId);
        return $phone === '' || !valid_lk_phone($phone);
    }

    /**
     * Activate a pending Google student when SMS phone linking is skipped (non-LK).
     */
    public function activateStudentWithoutPhone(int $userId): void
    {
        if ($userId < 1) {
            return;
        }
        try {
            $this->pdo->prepare("
                UPDATE users
                SET is_active = 1,
                    account_status = CASE
                        WHEN account_status IN ('suspended', 'disabled') THEN account_status
                        ELSE 'active'
                    END
                WHERE id = ? AND role = 'student'
            ")->execute([$userId]);
        } catch (\Throwable $e) {
            try {
                $this->pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ? AND role = \'student\'')->execute([$userId]);
            } catch (\Throwable $e2) {
                // ignore
            }
        }
        unset($_SESSION['needs_phone_link'], $_SESSION['phone_link_type']);
    }

    public function studentBoundPhone(int $userId): string
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.username, sp.whatsapp_number, sp.phone_verified_via, sp.whatsapp_verified_at
                FROM users u
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.id = ? AND u.role = 'student' AND u.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return '';
            }
            $candidates = [
                $this->normalize((string)($row['whatsapp_number'] ?? '')),
                $this->normalize((string)($row['username'] ?? '')),
            ];
            $via = strtolower(trim((string)($row['phone_verified_via'] ?? '')));
            $waVerified = !empty($row['whatsapp_verified_at']);
            foreach ($candidates as $phone) {
                if ($phone !== '' && valid_lk_phone($phone)) {
                    // Prefer verified; username that is itself a LK phone counts as bound.
                    if ($via === 'sms' || $via === 'whatsapp' || $waVerified || $phone === $this->normalize((string)$row['username'])) {
                        return $phone;
                    }
                }
            }
            // Unverified profile number still "has" a number field but we require SMS verify for Google.
            return '';
        } catch (Throwable $e) {
            return '';
        }
    }

    public function parentBoundPhone(int $parentId): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT phone FROM parent_accounts WHERE id = ? LIMIT 1');
            $stmt->execute([$parentId]);
            $phone = $this->normalize((string)($stmt->fetchColumn() ?: ''));
            return ($phone !== '' && valid_lk_phone($phone)) ? $phone : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @return array{ok:bool,message:string,show_otp?:bool}
     */
    public function sendStudentSms(int $userId, string $phone): array
    {
        return $this->sendOtp('student', $userId, $phone, function (string $normalized) use ($userId): ?string {
            if (function_exists('student_phone_taken_by_other') && student_phone_taken_by_other($this->pdo, $normalized, $userId)) {
                return 'That mobile number is already registered to another student account.';
            }
            return null;
        });
    }

    /**
     * @return array{ok:bool,message:string,show_otp?:bool}
     */
    public function sendParentSms(int $parentId, string $phone): array
    {
        return $this->sendOtp('parent', $parentId, $phone, function (string $normalized) use ($parentId): ?string {
            $stmt = $this->pdo->prepare('SELECT id FROM parent_accounts WHERE phone = ? AND id <> ? LIMIT 1');
            $stmt->execute([$normalized, $parentId]);
            if ($stmt->fetchColumn()) {
                return 'That mobile number is already linked to another parent account.';
            }
            return null;
        });
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function verifyStudentSms(int $userId, string $phone, string $otp): array
    {
        $check = $this->verifyOtp('student', $userId, $phone, $otp);
        if (empty($check['ok'])) {
            return $check;
        }
        $normalized = (string)$check['phone'];
        try {
            $this->bindStudent($userId, $normalized);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'google_phone_linked', 'users', $userId, null, [
                'phone' => $normalized,
                'via' => 'sms',
            ]);
        }
        return ['ok' => true, 'message' => 'Mobile number verified and saved.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function verifyParentSms(int $parentId, string $phone, string $otp): array
    {
        $check = $this->verifyOtp('parent', $parentId, $phone, $otp);
        if (empty($check['ok'])) {
            return $check;
        }
        $normalized = (string)$check['phone'];
        try {
            $this->bindParent($parentId, $normalized);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'google_phone_linked', 'parent_accounts', $parentId, null, [
                'phone' => $normalized,
                'via' => 'sms',
            ]);
        }
        return ['ok' => true, 'message' => 'Mobile number verified and saved.'];
    }

    /**
     * @param callable(string):(?string) $conflictCheck
     * @return array{ok:bool,message:string,show_otp?:bool}
     */
    private function sendOtp(string $type, int $subjectId, string $phone, callable $conflictCheck): array
    {
        $normalized = $this->normalize($phone);
        if (!valid_lk_phone($normalized)) {
            return ['ok' => false, 'message' => 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.', 'show_otp' => false];
        }
        $conflict = $conflictCheck($normalized);
        if ($conflict !== null) {
            return ['ok' => false, 'message' => $conflict, 'show_otp' => false];
        }

        $stmt = $this->pdo->prepare("
            SELECT created_at FROM portal_phone_otps
            WHERE subject_type = ? AND subject_id = ? AND phone = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$type, $subjectId, $normalized]);
        $last = $stmt->fetchColumn();
        if ($last && strtotime((string)$last) > time() - 60) {
            return [
                'ok' => true,
                'show_otp' => true,
                'message' => 'A code was already sent by SMS. Wait 60 seconds to resend.',
            ];
        }

        $otp = (string)random_int(100000, 999999);
        $this->pdo->prepare("
            INSERT INTO portal_phone_otps (subject_type, subject_id, phone, otp_hash, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $type,
            $subjectId,
            $normalized,
            password_hash($otp, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s', time() + 600),
        ]);

        $message = "Edexcel College code: {$otp}\nValid 10 minutes. Do not share.";
        $sent = sms_send($this->pdo, $normalized, $message);
        if (function_exists('otp_support_log_record')) {
            otp_support_log_record($this->pdo, [
                'purpose' => 'google_phone_link_' . $type,
                'phone' => $normalized,
                'otp' => $otp,
                'channel' => 'sms',
                'user_id' => $type === 'student' ? $subjectId : null,
                'sent' => $sent,
            ]);
        }
        if (!$sent) {
            $err = function_exists('sms_send_last_error') ? trim(sms_send_last_error()) : '';
            return [
                'ok' => false,
                'show_otp' => false,
                'message' => $err !== ''
                    ? ('Could not send SMS: ' . $err)
                    : 'Could not send the SMS code. Check SMS gateway settings and try again.',
            ];
        }

        return [
            'ok' => true,
            'show_otp' => true,
            'message' => 'We sent a 6-digit code by SMS to your number.',
        ];
    }

    /**
     * @return array{ok:bool,message:string,phone?:string}
     */
    private function verifyOtp(string $type, int $subjectId, string $phone, string $otp): array
    {
        $normalized = $this->normalize($phone);
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        if (!valid_lk_phone($normalized)) {
            return ['ok' => false, 'message' => 'Enter a valid Sri Lankan mobile number.'];
        }
        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['ok' => false, 'message' => 'Enter the 6-digit SMS code.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM portal_phone_otps
            WHERE subject_type = ? AND subject_id = ? AND phone = ?
              AND verified_at IS NULL AND expires_at > ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$type, $subjectId, $normalized, date('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'message' => 'That code has expired. Request a new SMS code.'];
        }
        if ((int)$row['attempts'] >= 5) {
            return ['ok' => false, 'message' => 'Too many attempts. Request a new SMS code.'];
        }
        $this->pdo->prepare('UPDATE portal_phone_otps SET attempts = attempts + 1 WHERE id = ?')
            ->execute([(int)$row['id']]);
        if (!password_verify($otp, (string)$row['otp_hash'])) {
            return ['ok' => false, 'message' => 'Incorrect code. Check the SMS and try again.'];
        }
        $this->pdo->prepare('UPDATE portal_phone_otps SET verified_at = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), (int)$row['id']]);

        return ['ok' => true, 'message' => 'Verified.', 'phone' => $normalized];
    }

    private function bindStudent(int $userId, string $phone): void
    {
        if (function_exists('student_phone_taken_by_other') && student_phone_taken_by_other($this->pdo, $phone, $userId)) {
            throw new RuntimeException('That mobile number is already registered to another student account.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE users SET username = ? WHERE id = ? AND role = 'student' AND deleted_at IS NULL")
                ->execute([$phone, $userId]);

            // Activate after SMS-verified phone for Google-created pending students.
            try {
                $this->pdo->prepare("
                    UPDATE users
                    SET is_active = 1,
                        account_status = CASE
                            WHEN account_status IN ('suspended','disabled') THEN account_status
                            ELSE 'active'
                        END
                    WHERE id = ? AND role = 'student'
                ")->execute([$userId]);
            } catch (Throwable $e) {
                $this->pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ? AND role = \'student\'')->execute([$userId]);
            }

            $exists = $this->pdo->prepare('SELECT user_id FROM student_profiles WHERE user_id = ? LIMIT 1');
            $exists->execute([$userId]);
            if ($exists->fetchColumn()) {
                try {
                    $this->pdo->prepare("
                        UPDATE student_profiles
                        SET whatsapp_number = ?, phone_verified_via = 'sms', whatsapp_verified_at = NULL
                        WHERE user_id = ?
                    ")->execute([$phone, $userId]);
                } catch (Throwable $e) {
                    $this->pdo->prepare('UPDATE student_profiles SET whatsapp_number = ? WHERE user_id = ?')
                        ->execute([$phone, $userId]);
                }
            } else {
                try {
                    $this->pdo->prepare("
                        INSERT INTO student_profiles (user_id, full_name, whatsapp_number, phone_verified_via)
                        VALUES (?, '', ?, 'sms')
                    ")->execute([$userId, $phone]);
                } catch (Throwable $e) {
                    $this->pdo->prepare('INSERT INTO student_profiles (user_id, full_name, whatsapp_number) VALUES (?, \'\', ?)')
                        ->execute([$userId, $phone]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof \PDOException && (string)$e->getCode() === '23000') {
                throw new RuntimeException('That mobile number is already registered to another account.');
            }
            throw $e;
        }

        $_SESSION['username'] = $phone;
        unset($_SESSION['needs_phone_link'], $_SESSION['phone_link_type']);
    }

    private function bindParent(int $parentId, string $phone): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM parent_accounts WHERE phone = ? AND id <> ? LIMIT 1');
        $stmt->execute([$phone, $parentId]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('That mobile number is already linked to another parent account.');
        }

        $this->pdo->prepare('UPDATE parent_accounts SET phone = ?, status = CASE WHEN status IN (\'suspended\',\'disabled\') THEN status ELSE \'active\' END WHERE id = ?')
            ->execute([$phone, $parentId]);
        $_SESSION['parent_phone'] = $phone;
        unset($_SESSION['needs_phone_link'], $_SESSION['phone_link_type']);

        // Match existing OTP parent behaviour: link children by parent_whatsapp.
        try {
            (new ParentAuthService($this->pdo))->linkChildrenByVerifiedPhone($parentId, $phone);
        } catch (Throwable $e) {
            error_log('parent phone link children: ' . $e->getMessage());
        }
    }

    private function normalize(string $phone): string
    {
        if (function_exists('student_normalize_lk_phone')) {
            return student_normalize_lk_phone($phone);
        }
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '94' . substr($phone, 1);
        }
        return $phone;
    }

    private function ensureSchema(): void
    {
        if ($this->pdo->inTransaction()) {
            return;
        }
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS portal_phone_otps (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    subject_type ENUM('student','parent') NOT NULL,
                    subject_id INT NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    otp_hash VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    verified_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_portal_phone_otps_subject (subject_type, subject_id, phone, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('portal_phone_otps: ' . $e->getMessage());
        }
    }
}
