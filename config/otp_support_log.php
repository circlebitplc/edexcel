<?php
declare(strict_types=1);

/**
 * Short-lived plaintext OTP log for office support when SMS/WhatsApp delivery fails.
 * Codes are purged after 24 hours. Admin-only UI lives at admin/otp_support.php.
 */

function ensure_otp_support_log_table(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otp_support_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            purpose VARCHAR(40) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            otp_code CHAR(6) NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT 'unknown',
            user_id INT UNSIGNED NULL,
            sent TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_otp_support_phone_created (phone, created_at),
            KEY idx_otp_support_created (created_at),
            KEY idx_otp_support_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ready = true;
}

/**
 * @param array{purpose:string,phone:string,otp:string,channel?:string,user_id?:?int,sent?:bool,expires_at?:?string} $data
 */
function otp_support_log_record(PDO $pdo, array $data): void
{
    try {
        ensure_otp_support_log_table($pdo);
        $otp = preg_replace('/\D+/', '', (string)($data['otp'] ?? '')) ?? '';
        $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? '')) ?? '';
        $purpose = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim((string)($data['purpose'] ?? 'otp')))) ?: 'otp';
        if (!preg_match('/^\d{6}$/', $otp) || $phone === '') {
            return;
        }
        $channel = strtolower(trim((string)($data['channel'] ?? 'unknown')));
        if ($channel === '') {
            $channel = 'unknown';
        }
        $userId = isset($data['user_id']) && $data['user_id'] !== null && (int)$data['user_id'] > 0
            ? (int)$data['user_id']
            : null;
        $sent = !empty($data['sent']) ? 1 : 0;
        $expiresAt = trim((string)($data['expires_at'] ?? ''));
        if ($expiresAt === '') {
            $expiresAt = date('Y-m-d H:i:s', time() + 600);
        }

        $pdo->prepare("
            INSERT INTO otp_support_log (purpose, phone, otp_code, channel, user_id, sent, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$purpose, $phone, $otp, $channel, $userId, $sent, $expiresAt]);

        // Keep the table small; support only needs recent codes.
        $pdo->exec('DELETE FROM otp_support_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    } catch (Throwable $e) {
        error_log('otp_support_log_record failed: ' . $e->getMessage());
    }
}

function otp_support_current_channel(PDO $pdo): string
{
    if (function_exists('student_otp_channel')) {
        return student_otp_channel($pdo) === 'sms' ? 'sms' : 'whatsapp';
    }
    require_once __DIR__ . '/sms_gateway.php';
    return student_otp_channel($pdo) === 'sms' ? 'sms' : 'whatsapp';
}

/**
 * @return list<array<string,mixed>>
 */
function otp_support_log_list(PDO $pdo, string $phoneFilter = '', int $limit = 100): array
{
    ensure_otp_support_log_table($pdo);
    $limit = max(1, min(200, $limit));
    $phoneFilter = preg_replace('/\D+/', '', $phoneFilter) ?? '';

    if ($phoneFilter !== '') {
        $like = '%' . $phoneFilter . '%';
        $stmt = $pdo->prepare("
            SELECT
                l.*,
                CASE
                    WHEN l.expires_at < NOW() THEN 'expired'
                    ELSE 'active'
                END AS validity
            FROM otp_support_log l
            WHERE l.phone LIKE ?
               OR l.phone LIKE ?
            ORDER BY l.id DESC
            LIMIT {$limit}
        ");
        // Match both 9477… and local 077… style searches.
        $alt = $phoneFilter;
        if (str_starts_with($phoneFilter, '0') && strlen($phoneFilter) === 10) {
            $alt = '94' . substr($phoneFilter, 1);
        } elseif (str_starts_with($phoneFilter, '94') && strlen($phoneFilter) === 11) {
            $alt = '0' . substr($phoneFilter, 2);
        }
        $stmt->execute([$like, '%' . $alt . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $stmt = $pdo->query("
        SELECT
            l.*,
            CASE
                WHEN l.expires_at < NOW() THEN 'expired'
                ELSE 'active'
            END AS validity
        FROM otp_support_log l
        WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY l.id DESC
        LIMIT {$limit}
    ");
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

function otp_support_purpose_label(string $purpose): string
{
    return match ($purpose) {
        'student_registration' => 'Student registration',
        'student_login' => 'Student login',
        'staff_login' => 'Staff login',
        'parent_login' => 'Parent login',
        'student_device' => 'Student device',
        'student_presence' => 'Student presence',
        'student_phone_change' => 'Student phone change',
        'parent_whatsapp_change' => 'Parent WhatsApp change',
        default => ucwords(str_replace('_', ' ', $purpose)),
    };
}
