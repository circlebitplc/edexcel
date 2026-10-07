<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * SMS sent after a teacher payment is marked paid.
 * Uses the existing SMS gateway (sms_send). Payment status is never rolled back
 * if the gateway fails.
 */
final class TeacherPaymentSmsService
{
    public const TYPE = 'TEACHER_PAYMENT_PAID';
    public const KIND_TIMETABLE = 'timetable';
    public const KIND_PAYOUT = 'teacher_payout';

    /** @var list<string> */
    private static array $lastNotices = [];

    public static function consumeLastNotices(): array
    {
        $notices = self::$lastNotices;
        self::$lastNotices = [];
        return $notices;
    }

    public static function ensureSchema(PDO $pdo): void
    {
        if ($pdo->inTransaction() || self::tableReady($pdo)) {
            return;
        }
        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            $driver = '';
        }
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS teacher_payment_sms_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    payment_kind VARCHAR(20) NOT NULL,
                    payment_id INTEGER NOT NULL,
                    teacher_id INTEGER NOT NULL,
                    phone VARCHAR(20) NOT NULL DEFAULT \'\',
                    sms_type VARCHAR(40) NOT NULL,
                    message VARCHAR(500) NOT NULL,
                    sent_at TEXT NULL,
                    provider VARCHAR(40) NULL,
                    provider_message_id VARCHAR(120) NULL,
                    status VARCHAR(20) NOT NULL,
                    failure_reason VARCHAR(255) NULL,
                    sent_by INTEGER NULL,
                    attempt_kind VARCHAR(20) NOT NULL DEFAULT \'auto\',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );
            return;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS teacher_payment_sms_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    payment_kind VARCHAR(20) NOT NULL,
                    payment_id INT UNSIGNED NOT NULL,
                    teacher_id INT NOT NULL,
                    phone VARCHAR(20) NOT NULL DEFAULT '',
                    sms_type VARCHAR(40) NOT NULL,
                    message VARCHAR(500) NOT NULL,
                    sent_at DATETIME NULL,
                    provider VARCHAR(40) NULL,
                    provider_message_id VARCHAR(120) NULL,
                    status VARCHAR(20) NOT NULL,
                    failure_reason VARCHAR(255) NULL,
                    sent_by INT NULL,
                    attempt_kind VARCHAR(20) NOT NULL DEFAULT 'auto',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_teacher_payment_sms_payment (payment_kind, payment_id, id),
                    KEY idx_teacher_payment_sms_teacher (teacher_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('teacher payment sms schema: ' . $e->getMessage());
        }
    }

    /**
     * Amount printed in the SMS. Online and in-college lessons use the stored
     * teacher net total. Other lessons use the duration-based amount already
     * recorded for that lesson. Nothing is read from the browser.
     *
     * @param array<string,mixed> $lesson
     */
    /**
     * @param array<string,mixed> $lesson
     */
    public static function payableCents(array $lesson): int
    {
        $settlement = ClassSessionFeeCalculator::settlement($lesson);
        $useNet = !empty($settlement['uses_online_rule']) || !empty($settlement['uses_in_college_rule']);
        $raw = $useNet
            ? ($settlement['totals']['teacher_net_amount'] ?? '0')
            : ($settlement['totals']['institute_fee'] ?? '0');
        return ClassSessionFeeCalculator::toCents($raw);
    }

    public static function amountLabel(array $lesson): string
    {
        return ClassSessionFeeCalculator::formatRs(self::payableCents($lesson));
    }

    public static function greetingName(string $fullName): string
    {
        $fullName = trim((string)preg_replace('/\s+/u', ' ', $fullName));
        $first = $fullName === '' ? '' : explode(' ', $fullName)[0];
        $first = preg_replace('/[^\p{L}\p{M}\'\-]/u', '', $first) ?? '';
        if ($first === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($first, 0, 30);
        }
        return substr($first, 0, 30);
    }

    public static function buildMessage(string $teacherName, string $amountLabel, string $paidOn, string $reference): string
    {
        $amountLabel = trim($amountLabel);
        $paidOn = trim($paidOn);
        $reference = self::safeReference($reference);
        $name = self::greetingName($teacherName);
        if ($name !== '') {
            $text = 'Edexcel College: Dear ' . $name . ', your teacher payment of ' . $amountLabel
                . ' has been successfully paid on ' . $paidOn . '. Ref: ' . $reference . '. Thank you.';
        } else {
            $text = 'Edexcel College: Your teacher payment of ' . $amountLabel
                . ' has been successfully paid on ' . $paidOn . '. Ref: ' . $reference . '. Thank you.';
        }
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 480);
        }
        return substr($text, 0, 480);
    }

    public static function normalizePhone(string $raw): string
    {
        self::loadGateway();
        if (!function_exists('sms_android_address')) {
            return '';
        }
        $address = sms_android_address($raw);
        if (preg_match('/^07\d{8}$/', $address)) {
            return $address;
        }
        if (preg_match('/^\+94/', $address)) {
            return '';
        }
        if (preg_match('/^\+[1-9]\d{7,14}$/', $address)) {
            return $address;
        }
        return '';
    }

    /**
     * @param list<int> $paymentIds
     * @return array<int,array<string,mixed>>
     */
    public static function latestFor(PDO $pdo, string $kind, array $paymentIds): array
    {
        self::ensureSchema($pdo);
        $ids = [];
        foreach ($paymentIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === [] || !self::tableReady($pdo)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$kind, self::TYPE], array_values($ids));
        $sql = "SELECT l.payment_id, l.status, l.sent_at, l.failure_reason, l.created_at, c.attempts
                FROM teacher_payment_sms_log l
                INNER JOIN (
                    SELECT payment_id, MAX(id) AS id, COUNT(*) AS attempts
                    FROM teacher_payment_sms_log
                    WHERE payment_kind = ? AND sms_type = ? AND payment_id IN ($placeholders)
                    GROUP BY payment_id
                ) c ON c.id = l.id";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('teacher payment sms latest: ' . $e->getMessage());
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['payment_id']] = $row;
        }
        return $out;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'SENT',
            'resent' => 'RESENT',
            'failed' => 'FAILED',
            default => 'NOT SENT',
        };
    }

    /**
     * @param callable(string,string):array{ok:bool,provider_id?:string,error?:string}|null $sender
     * @return array<string,mixed>
     */
    public static function notifyTimetablePaid(PDO $pdo, int $timetableId, int $actorId, bool $resend = false, ?callable $sender = null): array
    {
        if ($timetableId < 1) {
            return self::result('failed', 'Payment SMS could not be sent.', false);
        }
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare(
                'SELECT t.id, t.teacher_id, t.student_count, t.payment_status, t.payment_date,
                        t.start_time, t.end_time, t.class_fee_per_student, t.delivery_mode,
                        t.fee_rule, t.institute_online_fee, t.transaction_handling_fee, t.teacher_net_amount,
                        t.deleted_at,
                        te.name AS teacher_name, te.phone AS teacher_phone
                 FROM timetable t
                 LEFT JOIN teachers te ON te.id = t.teacher_id
                 WHERE t.id = ?
                 LIMIT 1'
            );
            $stmt->execute([$timetableId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('teacher payment sms load lesson: ' . $e->getMessage());
            return self::result('failed', 'Payment SMS could not be sent.', false);
        }
        if (!$row || !empty($row['deleted_at'])) {
            return self::result('failed', 'Payment SMS could not be sent.', false);
        }
        if (strtolower((string)($row['payment_status'] ?? '')) !== 'paid') {
            return self::result('skipped', 'Payment SMS was not sent because the payment is not paid.', false);
        }
        $paidOn = self::displayDate((string)($row['payment_date'] ?? ''));
        $message = self::buildMessage(
            (string)($row['teacher_name'] ?? ''),
            self::amountLabel($row),
            $paidOn,
            'PAY-' . (int)$row['id']
        );
        return self::deliver(
            $pdo,
            self::KIND_TIMETABLE,
            (int)$row['id'],
            (int)($row['teacher_id'] ?? 0),
            (string)($row['teacher_phone'] ?? ''),
            $message,
            $actorId,
            $resend,
            $sender
        );
    }

    /**
     * @param callable(string,string):array{ok:bool,provider_id?:string,error?:string}|null $sender
     * @return array<string,mixed>
     */
    public static function notifyPayoutPaid(PDO $pdo, int $payoutId, int $actorId, bool $resend = false, ?callable $sender = null): array
    {
        if ($payoutId < 1) {
            return self::result('failed', 'Payment SMS could not be sent.', false);
        }
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare(
                'SELECT p.id, p.teacher_id, p.amount, p.payout_date, p.payout_reference, p.status,
                        te.name AS teacher_name, te.phone AS teacher_phone
                 FROM teacher_payouts p
                 LEFT JOIN teachers te ON te.id = p.teacher_id
                 WHERE p.id = ?
                 LIMIT 1'
            );
            $stmt->execute([$payoutId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('teacher payment sms load payout: ' . $e->getMessage());
            return self::result('failed', 'Payment SMS could not be sent.', false);
        }
        if (!$row) {
            return self::result('failed', 'Payment SMS could not be sent.', false);
        }
        if (strtolower((string)($row['status'] ?? '')) !== 'paid') {
            return self::result('skipped', 'Payment SMS was not sent because the payout is not paid.', false);
        }
        $reference = trim((string)($row['payout_reference'] ?? ''));
        if ($reference === '') {
            $reference = 'PAY-' . (int)$row['id'];
        }
        $message = self::buildMessage(
            (string)($row['teacher_name'] ?? ''),
            ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($row['amount'] ?? 0)),
            self::displayDate((string)($row['payout_date'] ?? '')),
            $reference
        );
        return self::deliver(
            $pdo,
            self::KIND_PAYOUT,
            (int)$row['id'],
            (int)($row['teacher_id'] ?? 0),
            (string)($row['teacher_phone'] ?? ''),
            $message,
            $actorId,
            $resend,
            $sender
        );
    }

    public static function notifyPayoutPaidQuiet(PDO $pdo, int $payoutId, int $actorId): array
    {
        try {
            $result = self::notifyPayoutPaid($pdo, $payoutId, $actorId, false);
        } catch (Throwable $e) {
            error_log('teacher payout sms: ' . $e->getMessage());
            $result = self::result('failed', 'Payment SMS could not be sent.', false);
        }
        $notice = (string)($result['sms_notice'] ?? '');
        if ($notice !== '' && ($result['status'] ?? '') !== 'skipped') {
            self::$lastNotices[] = [
                'status' => (string)($result['status'] ?? 'failed'),
                'notice' => $notice,
            ];
        }
        return $result;
    }

    /**
     * @param callable(string,string):array{ok:bool,provider_id?:string,error?:string}|null $sender
     * @return array<string,mixed>
     */
    public static function preview(PDO $pdo, string $kind, int $paymentId): array
    {
        $built = $kind === self::KIND_PAYOUT
            ? self::composePayout($pdo, $paymentId)
            : self::composeTimetable($pdo, $paymentId);
        if ($built === null) {
            return [
                'success' => false,
                'can_send' => false,
                'message' => '',
                'warning' => 'Payment SMS could not be prepared.',
            ];
        }
        $phone = self::normalizePhone($built['phone']);
        return [
            'success' => true,
            'can_send' => $phone !== '' && $built['payable'],
            'message' => $built['message'],
            'warning' => $phone === ''
                ? 'Payment SMS cannot be sent because the teacher has no valid mobile number.'
                : ($built['payable'] ? '' : 'Payment SMS cannot be sent because this payment is not paid.'),
        ];
    }

    /**
     * @param callable(string,string):array{ok:bool,provider_id?:string,error?:string}|null $sender
     * @return array<string,mixed>
     */
    private static function deliver(
        PDO $pdo,
        string $kind,
        int $paymentId,
        int $teacherId,
        string $rawPhone,
        string $message,
        int $actorId,
        bool $resend,
        ?callable $sender
    ): array {
        $release = self::acquireLock($pdo, $kind, $paymentId);
        try {
            if (!$resend && self::alreadySent($pdo, $kind, $paymentId)) {
                return self::result('already_sent', 'An SMS was already sent for this payment.', false, $message);
            }
            $phone = self::normalizePhone($rawPhone);
            $attemptKind = $resend ? 'resend' : 'auto';
            if ($teacherId < 1) {
                self::writeLog($pdo, $kind, $paymentId, 0, '', $message, 'failed', 'Teacher record was not found.', '', '', $actorId, $attemptKind, false);
                return self::result('failed', 'Payment SMS could not be sent.', false, $message);
            }
            if ($phone === '') {
                self::writeLog($pdo, $kind, $paymentId, $teacherId, '', $message, 'failed', 'Teacher has no valid mobile number.', '', '', $actorId, $attemptKind, false);
                return self::result('failed', 'Payment SMS could not be sent because the teacher has no valid mobile number.', true, $message);
            }
            $send = $sender ?? static function (string $to, string $text) use ($pdo): array {
                self::loadGateway();
                $ok = function_exists('sms_send') && sms_send($pdo, $to, $text);
                return [
                    'ok' => (bool)$ok,
                    'provider_id' => function_exists('sms_send_last_id') ? sms_send_last_id() : '',
                    'error' => function_exists('sms_send_last_error') ? sms_send_last_error() : '',
                ];
            };
            try {
                $sent = $send($phone, $message);
            } catch (Throwable $e) {
                error_log('teacher payment sms send: ' . $e->getMessage());
                $sent = ['ok' => false, 'provider_id' => '', 'error' => 'SMS provider request failed.'];
            }
            $ok = !empty($sent['ok']);
            $providerId = substr(trim((string)($sent['provider_id'] ?? '')), 0, 120);
            $error = self::safeReason((string)($sent['error'] ?? ''));
            if ($ok) {
                $status = $resend ? 'resent' : 'sent';
                self::writeLog($pdo, $kind, $paymentId, $teacherId, $phone, $message, $status, null, 'sms-gate', $providerId, $actorId, $attemptKind, true);
                $notice = $resend
                    ? 'SMS notification resent to the teacher.'
                    : 'SMS notification sent to the teacher.';
                return self::result($status, $notice, false, $message);
            }
            if ($error === '') {
                $error = 'The SMS gateway did not accept the message.';
            }
            self::writeLog($pdo, $kind, $paymentId, $teacherId, $phone, $message, 'failed', $error, 'sms-gate', $providerId, $actorId, $attemptKind, false);
            return self::result('failed', 'Payment SMS could not be sent.', false, $message, $error);
        } finally {
            $release();
        }
    }

    /**
     * @return array{phone:string,message:string,payable:bool}|null
     */
    private static function composeTimetable(PDO $pdo, int $timetableId): ?array
    {
        if ($timetableId < 1) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT t.id, t.teacher_id, t.student_count, t.payment_status, t.payment_date,
                    t.start_time, t.end_time, t.class_fee_per_student, t.delivery_mode,
                    t.fee_rule, t.institute_online_fee, t.transaction_handling_fee, t.teacher_net_amount,
                    t.deleted_at, te.name AS teacher_name, te.phone AS teacher_phone
             FROM timetable t
             LEFT JOIN teachers te ON te.id = t.teacher_id
             WHERE t.id = ? LIMIT 1'
        );
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !empty($row['deleted_at'])) {
            return null;
        }
        return [
            'phone' => (string)($row['teacher_phone'] ?? ''),
            'payable' => strtolower((string)($row['payment_status'] ?? '')) === 'paid',
            'message' => self::buildMessage(
                (string)($row['teacher_name'] ?? ''),
                self::amountLabel($row),
                self::displayDate((string)($row['payment_date'] ?? '')),
                'PAY-' . (int)$row['id']
            ),
        ];
    }

    /**
     * @return array{phone:string,message:string,payable:bool}|null
     */
    private static function composePayout(PDO $pdo, int $payoutId): ?array
    {
        if ($payoutId < 1) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT p.id, p.amount, p.payout_date, p.payout_reference, p.status,
                    te.name AS teacher_name, te.phone AS teacher_phone
             FROM teacher_payouts p
             LEFT JOIN teachers te ON te.id = p.teacher_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$payoutId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $reference = trim((string)($row['payout_reference'] ?? ''));
        if ($reference === '') {
            $reference = 'PAY-' . (int)$row['id'];
        }
        return [
            'phone' => (string)($row['teacher_phone'] ?? ''),
            'payable' => strtolower((string)($row['status'] ?? '')) === 'paid',
            'message' => self::buildMessage(
                (string)($row['teacher_name'] ?? ''),
                ClassSessionFeeCalculator::formatRs(ClassSessionFeeCalculator::toCents($row['amount'] ?? 0)),
                self::displayDate((string)($row['payout_date'] ?? '')),
                $reference
            ),
        ];
    }

    private static function alreadySent(PDO $pdo, string $kind, int $paymentId): bool
    {
        if (!self::tableReady($pdo)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM teacher_payment_sms_log
                 WHERE payment_kind = ? AND payment_id = ? AND sms_type = ?
                   AND status IN ('sent', 'resent')
                 LIMIT 1"
            );
            $stmt->execute([$kind, $paymentId, self::TYPE]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('teacher payment sms duplicate check: ' . $e->getMessage());
            return false;
        }
    }

    private static function writeLog(
        PDO $pdo,
        string $kind,
        int $paymentId,
        int $teacherId,
        string $phone,
        string $message,
        string $status,
        ?string $reason,
        string $provider,
        string $providerId,
        int $actorId,
        string $attemptKind,
        bool $markSentAt
    ): void {
        if (!self::tableReady($pdo)) {
            self::ensureSchema($pdo);
        }
        if (!self::tableReady($pdo)) {
            error_log('teacher payment sms log table is missing');
            return;
        }
        $reason = $reason !== null ? self::clip($reason, 255) : null;
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO teacher_payment_sms_log
                    (payment_kind, payment_id, teacher_id, phone, sms_type, message, sent_at, provider, provider_message_id, status, failure_reason, sent_by, attempt_kind)
                 VALUES (?, ?, ?, ?, ?, ?, ' . ($markSentAt ? 'CURRENT_TIMESTAMP' : 'NULL') . ', ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $kind,
                $paymentId,
                $teacherId,
                self::clip($phone, 20),
                self::TYPE,
                self::clip($message, 500),
                $provider !== '' ? self::clip($provider, 40) : null,
                $providerId !== '' ? $providerId : null,
                self::clip($status, 20),
                $reason,
                $actorId > 0 ? $actorId : null,
                $attemptKind === 'resend' ? 'resend' : 'auto',
            ]);
        } catch (Throwable $e) {
            error_log('teacher payment sms log insert: ' . $e->getMessage());
        }
        self::audit($pdo, $kind, $paymentId, $status, $actorId);
    }

    private static function audit(PDO $pdo, string $kind, int $paymentId, string $status, int $actorId): void
    {
        $details = [
            'sms_type' => self::TYPE,
            'status' => $status,
            'payment_kind' => $kind,
        ];
        try {
            if ($kind === self::KIND_PAYOUT && method_exists(TeacherPayoutService::class, 'audit')) {
                TeacherPayoutService::audit($pdo, $actorId, 'payout_sms', 'teacher_payout', (string)$paymentId, $details);
                return;
            }
            if (function_exists('log_audit')) {
                log_audit($pdo, 'teacher_payment_sms', $kind === self::KIND_PAYOUT ? 'teacher_payout' : 'timetable', $paymentId, null, $details);
            }
        } catch (Throwable $e) {
            error_log('teacher payment sms audit: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function result(string $status, string $notice, bool $missingPhone, string $message = '', string $detail = ''): array
    {
        return [
            'status' => $status,
            'sent' => $status === 'sent' || $status === 'resent',
            'missing_phone' => $missingPhone,
            'sms_notice' => $notice,
            'sms_detail' => $detail,
            'message' => $message,
        ];
    }

    private static function displayDate(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            $ts = strtotime(substr($value, 0, 10));
            if ($ts !== false) {
                return date('d/m/Y', $ts);
            }
        }
        return date('d/m/Y');
    }

    public static function safeReference(string $reference): string
    {
        $reference = trim($reference);
        $reference = preg_replace('/[^A-Za-z0-9\-\/]/', '', $reference) ?? '';
        if ($reference === '') {
            return 'PAY';
        }
        return function_exists('mb_substr') ? mb_substr($reference, 0, 24) : substr($reference, 0, 24);
    }

    public static function safeReason(string $reason): string
    {
        $reason = trim((string)preg_replace('/\s+/', ' ', $reason));
        if ($reason === '') {
            return '';
        }
        if (preg_match('/password|username|authorization|bearer|api[_ ]?key/i', $reason)) {
            return 'The SMS gateway did not accept the message.';
        }
        $reason = preg_replace('/\+?\d{8,15}/', '', $reason) ?? $reason;
        return self::clip(trim($reason), 180);
    }

    private static function clip(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }

    private static function tableReady(PDO $pdo): bool
    {
        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return false;
        }
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'teacher_payment_sms_log'");
                return $stmt !== false && (bool)$stmt->fetchColumn();
            }
            $stmt = $pdo->query("SHOW TABLES LIKE 'teacher_payment_sms_log'");
            return $stmt !== false && (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function loadGateway(): void
    {
        if (!function_exists('sms_android_address')) {
            $file = dirname(__DIR__, 2) . '/config/sms_gateway.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * @return callable():void
     */
    private static function acquireLock(PDO $pdo, string $kind, int $paymentId): callable
    {
        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            $driver = '';
        }
        if ($driver !== 'mysql' || $pdo->inTransaction()) {
            return static function (): void {
            };
        }
        $name = 'tpsms_' . preg_replace('/[^a-z_]/', '', $kind) . '_' . $paymentId;
        try {
            $stmt = $pdo->prepare('SELECT GET_LOCK(?, 8)');
            $stmt->execute([$name]);
            $got = (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            $got = false;
        }
        if (!$got) {
            return static function (): void {
            };
        }
        return static function () use ($pdo, $name): void {
            try {
                $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$name]);
            } catch (Throwable $e) {
            }
        };
    }
}
