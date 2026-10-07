<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Single fee breakdown for a class session.
 *
 * The student pays the class fee the teacher entered. Institute and handling
 * amounts are taken from that fee. They are not added on top of it.
 *
 * Online (delivery_mode = online), fee_rule online_v1:
 *   institute fee is a flat amount (setting online_institute_fee, default Rs 500)
 *   transaction & handling fee is a percentage of the class fee (default 6%)
 *   teacher net = class fee - institute fee - handling fee
 *
 * In college (delivery_mode = physical), fee_rule in_college_v1, new classes only:
 *   institute fee is a flat amount (setting in_college_institute_fee, default Rs 500)
 *   handling fee is 0
 *   teacher net = class fee - institute fee
 *
 * Hybrid classes, and in-college rows saved before in_college_v1, keep the
 * duration-based institute fee. Those rows are never rewritten.
 */
final class ClassSessionFeeCalculator
{
    public const ONLINE_RULE = 'online_v1';
    public const IN_COLLEGE_RULE = 'in_college_v1';
    public const PHYSICAL_RULE = 'physical_duration';

    public const SETTING_INSTITUTE_FEE = 'online_institute_fee';
    public const SETTING_IN_COLLEGE_FEE = 'in_college_institute_fee';
    public const SETTING_HANDLING_RATE = 'online_transaction_handling_rate';

    /**
     * @return array{institute_fee_cents:int,in_college_fee_cents:int,rate_bps:int}
     */
    public static function defaultConfig(): array
    {
        return [
            'institute_fee_cents' => 50000,
            'in_college_fee_cents' => 50000,
            'rate_bps' => 600,
        ];
    }

    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done || $pdo->inTransaction()) {
            return;
        }
        $done = true;

        $alters = [
            ['timetable', 'fee_rule', 'VARCHAR(32) NULL'],
            ['timetable', 'institute_online_fee', 'DECIMAL(10,2) NULL'],
            ['timetable', 'transaction_handling_fee', 'DECIMAL(10,2) NULL'],
            ['timetable', 'teacher_net_amount', 'DECIMAL(10,2) NULL'],
            ['recurring_schedules', 'fee_rule', 'VARCHAR(32) NULL'],
            ['recurring_schedules', 'institute_online_fee', 'DECIMAL(10,2) NULL'],
            ['recurring_schedules', 'transaction_handling_fee', 'DECIMAL(10,2) NULL'],
            ['recurring_schedules', 'teacher_net_amount', 'DECIMAL(10,2) NULL'],
            ['payment_transactions', 'gross_class_fee', 'DECIMAL(10,2) NULL'],
            ['payment_transactions', 'institute_online_fee', 'DECIMAL(10,2) NULL'],
            ['payment_transactions', 'transaction_handling_fee', 'DECIMAL(10,2) NULL'],
            ['payment_transactions', 'teacher_net_amount', 'DECIMAL(10,2) NULL'],
            ['payment_transactions', 'delivery_mode', 'VARCHAR(20) NULL'],
            ['payment_transactions', 'teacher_id', 'INT NULL'],
            ['payment_transactions', 'payout_status', 'VARCHAR(20) NULL'],
            ['payment_transactions', 'payout_id', 'INT UNSIGNED NULL'],
            ['student_lesson_fees', 'gross_class_fee', 'DECIMAL(10,2) NULL'],
            ['student_lesson_fees', 'institute_online_fee', 'DECIMAL(10,2) NULL'],
            ['student_lesson_fees', 'transaction_handling_fee', 'DECIMAL(10,2) NULL'],
            ['student_lesson_fees', 'teacher_net_amount', 'DECIMAL(10,2) NULL'],
        ];

        foreach ($alters as [$table, $column, $definition]) {
            self::addColumn($pdo, $table, $column, $definition);
        }

        try {
            $pdo->exec(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES ('" . self::SETTING_INSTITUTE_FEE . "', '500.00')
                 ON DUPLICATE KEY UPDATE setting_key = setting_key"
            );
            $pdo->exec(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES ('" . self::SETTING_HANDLING_RATE . "', '0.0600')
                 ON DUPLICATE KEY UPDATE setting_key = setting_key"
            );
            $pdo->exec(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES ('" . self::SETTING_IN_COLLEGE_FEE . "', '500.00')
                 ON DUPLICATE KEY UPDATE setting_key = setting_key"
            );
        } catch (\Throwable $e) {
            // Older installs may not have settings yet. Defaults still apply in code.
        }

        TeacherBankAccountService::ensureSchema($pdo);
        TeacherPayoutService::ensureSchema($pdo);
    }

    /**
     * @return array{institute_fee_cents:int,in_college_fee_cents:int,rate_bps:int}
     */
    public static function config(?PDO $pdo = null): array
    {
        $config = self::defaultConfig();
        if (!$pdo instanceof PDO) {
            return $config;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT setting_key, setting_value FROM settings WHERE setting_key IN (?, ?, ?)'
            );
            $stmt->execute([
                self::SETTING_INSTITUTE_FEE,
                self::SETTING_IN_COLLEGE_FEE,
                self::SETTING_HANDLING_RATE,
            ]);
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [] as $key => $value) {
                if ($key === self::SETTING_INSTITUTE_FEE || $key === self::SETTING_IN_COLLEGE_FEE) {
                    $cents = self::toCents($value);
                    if ($cents >= 0) {
                        $config[$key === self::SETTING_IN_COLLEGE_FEE ? 'in_college_fee_cents' : 'institute_fee_cents'] = $cents;
                    }
                }
                if ($key === self::SETTING_HANDLING_RATE) {
                    $config['rate_bps'] = self::rateToBps((string)$value);
                }
            }
        } catch (\Throwable $e) {
            return self::defaultConfig();
        }
        return $config;
    }

    /**
     * Browser-facing numbers. The same integer-cent rules are used in
     * assets/js/class-session-fee.js. Do not copy this formula elsewhere.
     *
     * @param array{institute_fee_cents?:int,in_college_fee_cents?:int,rate_bps?:int}|null $config
     * @return array{
     *   valid:bool,
     *   gross_class_fee:string,
     *   institute_online_fee:string,
     *   transaction_handling_fee:string,
     *   teacher_net_amount:string,
     *   total_student_payable:string,
     *   institute_fee:string,
     *   class_type:string,
     *   fee_rule:string,
     *   transaction_handling_rate:string
     * }
     */
    public static function quote(mixed $gross, string $deliveryMode, ?array $config = null): array
    {
        $config = $config ?? self::defaultConfig();
        $mode = self::normalizeMode($deliveryMode);
        $parsed = self::parseGross($gross);
        $instituteOnline = 0;
        $handling = 0;
        $teacherNet = 0;
        $instituteFee = 0;
        $rule = self::PHYSICAL_RULE;

        if ($parsed['valid'] && $mode === 'online') {
            $rule = self::ONLINE_RULE;
            $instituteOnline = max(0, (int)($config['institute_fee_cents'] ?? 0));
            $handling = self::applyRate($parsed['cents'], (int)($config['rate_bps'] ?? 0));
            $teacherNet = $parsed['cents'] - $instituteOnline - $handling;
            $instituteFee = $instituteOnline;
        } elseif ($parsed['valid'] && $mode === 'physical') {
            $rule = self::IN_COLLEGE_RULE;
            $instituteOnline = max(0, (int)($config['in_college_fee_cents'] ?? 0));
            $handling = 0;
            $teacherNet = $parsed['cents'] - $instituteOnline;
            $instituteFee = $instituteOnline;
        }

        $grossCents = $parsed['valid'] ? $parsed['cents'] : 0;

        return [
            'valid' => $parsed['valid'],
            'gross_class_fee' => self::formatCents($grossCents),
            'institute_online_fee' => self::formatCents($instituteOnline),
            'transaction_handling_fee' => self::formatCents($handling),
            'teacher_net_amount' => self::formatCents($teacherNet),
            'total_student_payable' => self::formatCents($grossCents),
            'institute_fee' => self::formatCents($instituteFee),
            'class_type' => $mode,
            'fee_rule' => $rule,
            'transaction_handling_rate' => self::bpsToRate((int)($config['rate_bps'] ?? 0)),
            'student_payable_amount' => self::formatCents($grossCents),
            'currency' => 'LKR',
        ];
    }

    /**
     * Replace any browser-supplied fee components with the server calculation.
     * An unchanged online class keeps the snapshot stored when it was saved.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    public static function stampPayload(array $data, ?PDO $pdo = null, ?array $existing = null): array
    {
        unset(
            $data['institute_online_fee'],
            $data['transaction_handling_fee'],
            $data['teacher_net_amount'],
            $data['fee_rule'],
            $data['total_student_payable'],
            $data['gross_class_fee']
        );

        $mode = self::normalizeMode((string)($data['delivery_mode'] ?? 'physical'));
        $data['delivery_mode'] = $mode;
        $quote = self::quote($data['class_fee_per_student'] ?? 0, $mode, self::config($pdo));
        if (!$quote['valid']) {
            throw new RuntimeException('Class fee per student is not a valid amount.');
        }

        $data['class_fee_per_student'] = $quote['gross_class_fee'];

        if ($mode === 'online' && self::keepStoredSnapshot($existing, $quote['gross_class_fee'], self::ONLINE_RULE)) {
            return self::copyStoredSnapshot($data, $existing, self::ONLINE_RULE);
        }

        if ($mode === 'online') {
            $data['fee_rule'] = self::ONLINE_RULE;
            $data['institute_online_fee'] = $quote['institute_online_fee'];
            $data['transaction_handling_fee'] = $quote['transaction_handling_fee'];
            $data['teacher_net_amount'] = $quote['teacher_net_amount'];
            return $data;
        }

        if ($mode === 'physical' && self::keepStoredSnapshot($existing, $quote['gross_class_fee'], self::IN_COLLEGE_RULE)) {
            return self::copyStoredSnapshot($data, $existing, self::IN_COLLEGE_RULE);
        }

        if (self::preserveLegacyPhysical($existing, $mode)) {
            $previousRule = (string)($existing['fee_rule'] ?? '');
            $data['fee_rule'] = $previousRule !== '' ? $previousRule : null;
            $data['institute_online_fee'] = self::storedMoney($existing, 'institute_online_fee');
            $data['transaction_handling_fee'] = self::storedMoney($existing, 'transaction_handling_fee');
            $data['teacher_net_amount'] = self::storedMoney($existing, 'teacher_net_amount');
            return $data;
        }

        if ($mode === 'physical') {
            $data['fee_rule'] = self::IN_COLLEGE_RULE;
            $data['institute_online_fee'] = $quote['institute_online_fee'];
            $data['transaction_handling_fee'] = '0.00';
            $data['teacher_net_amount'] = $quote['teacher_net_amount'];
            return $data;
        }

        $data['fee_rule'] = self::PHYSICAL_RULE;
        $data['institute_online_fee'] = '0.00';
        $data['transaction_handling_fee'] = '0.00';
        $data['teacher_net_amount'] = null;
        return $data;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    private static function copyStoredSnapshot(array $data, array $existing, string $rule): array
    {
        $data['fee_rule'] = $rule;
        $data['institute_online_fee'] = self::formatCents(self::toCents($existing['institute_online_fee'] ?? 0));
        $data['transaction_handling_fee'] = self::formatCents(self::toCents($existing['transaction_handling_fee'] ?? 0));
        $data['teacher_net_amount'] = self::formatCents(self::toCents($existing['teacher_net_amount'] ?? 0));
        return $data;
    }

    /**
     * @param array<string,mixed> $existing
     */
    private static function storedMoney(array $existing, string $key): ?string
    {
        if (!array_key_exists($key, $existing) || $existing[$key] === null || $existing[$key] === '') {
            return null;
        }
        return self::formatCents(self::toCents($existing[$key]));
    }

    /**
     * @param array<string,mixed>|null $existing
     */
    private static function keepStoredSnapshot(?array $existing, string $gross, string $rule): bool
    {
        if ($existing === null) {
            return false;
        }
        if ((string)($existing['fee_rule'] ?? '') !== $rule) {
            return false;
        }
        if (!array_key_exists('institute_online_fee', $existing) || $existing['institute_online_fee'] === null) {
            return false;
        }
        $previousMode = self::normalizeMode((string)($existing['delivery_mode'] ?? 'physical'));
        $expected = $rule === self::ONLINE_RULE ? 'online' : 'physical';
        if ($previousMode !== $expected) {
            return false;
        }
        return self::toCents($existing['class_fee_per_student'] ?? 0) === self::toCents($gross);
    }

    /**
     * An in-college lesson saved before the flat institute fee keeps that
     * older duration settlement when it is edited.
     *
     * @param array<string,mixed>|null $existing
     */
    private static function preserveLegacyPhysical(?array $existing, string $mode): bool
    {
        if ($existing === null || $mode !== 'physical') {
            return false;
        }
        if (self::normalizeMode((string)($existing['delivery_mode'] ?? 'physical')) !== 'physical') {
            return false;
        }
        $rule = (string)($existing['fee_rule'] ?? '');
        return $rule !== self::IN_COLLEGE_RULE && $rule !== self::ONLINE_RULE;
    }

    /**
     * Lesson totals for dashboards and teacher settlement.
     * Online snapshots are read from the row. Everything else uses the
     * existing duration institute fee and does not add the online charges.
     *
     * @param array<string,mixed> $lesson
     * @param (callable(array<string,mixed>):float)|null $durationRate
     * @return array<string,mixed>
     */
    public static function settlement(array $lesson, ?callable $durationRate = null): array
    {
        $students = max(0, (int)($lesson['student_count'] ?? 0));
        $mode = self::normalizeMode((string)($lesson['delivery_mode'] ?? 'physical'));
        $grossCents = self::toCents($lesson['class_fee_per_student'] ?? 0);
        $ruleName = (string)($lesson['fee_rule'] ?? '');
        $online = $ruleName === self::ONLINE_RULE
            && $lesson['institute_online_fee'] !== null
            && $lesson['institute_online_fee'] !== '';
        $inCollege = $ruleName === self::IN_COLLEGE_RULE
            && $lesson['institute_online_fee'] !== null
            && $lesson['institute_online_fee'] !== '';

        if ($online || $inCollege) {
            $institutePer = self::toCents($lesson['institute_online_fee'] ?? 0);
            $handlingPer = $online ? self::toCents($lesson['transaction_handling_fee'] ?? 0) : 0;
            $storedNet = $lesson['teacher_net_amount'] ?? null;
            $netPer = ($storedNet === null || $storedNet === '')
                ? ($grossCents - $institutePer - $handlingPer)
                : self::toCents($storedNet);
            $rule = $online ? self::ONLINE_RULE : self::IN_COLLEGE_RULE;
            $classType = $online ? 'online' : 'physical';
        } else {
            $rate = $durationRate
                ? (float)$durationRate($lesson)
                : self::durationInstituteRate($lesson);
            $institutePer = self::toCents($rate);
            $handlingPer = 0;
            $netPer = $grossCents - $institutePer;
            $rule = (string)($lesson['fee_rule'] ?? '') === self::PHYSICAL_RULE
                ? self::PHYSICAL_RULE
                : 'legacy_duration';
            $classType = $mode === 'online' ? 'online' : $mode;
        }

        $per = [
            'gross_class_fee' => self::formatCents($grossCents),
            'institute_online_fee' => self::formatCents(($online || $inCollege) ? $institutePer : 0),
            'transaction_handling_fee' => self::formatCents($handlingPer),
            'teacher_net_amount' => self::formatCents($netPer),
            'total_student_payable' => self::formatCents($grossCents),
            'institute_fee' => self::formatCents($institutePer),
        ];

        $totals = [];
        foreach ($per as $key => $amount) {
            $totals[$key] = self::formatCents(self::toCents($amount) * $students);
        }

        return [
            'class_type' => $classType,
            'fee_rule' => $rule,
            'uses_online_rule' => $online,
            'uses_in_college_rule' => $inCollege,
            'students' => $students,
            'per_student' => $per,
            'totals' => $totals,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{fee_rule:?string,institute_online_fee:?string,transaction_handling_fee:?string,teacher_net_amount:?string}
     */
    public static function snapshotFromPayload(array $data): array
    {
        $net = $data['teacher_net_amount'] ?? null;
        return [
            'fee_rule' => isset($data['fee_rule']) ? (string)$data['fee_rule'] : null,
            'institute_online_fee' => isset($data['institute_online_fee']) && $data['institute_online_fee'] !== null
                ? self::formatCents(self::toCents($data['institute_online_fee']))
                : null,
            'transaction_handling_fee' => isset($data['transaction_handling_fee']) && $data['transaction_handling_fee'] !== null
                ? self::formatCents(self::toCents($data['transaction_handling_fee']))
                : null,
            'teacher_net_amount' => $net === null || $net === ''
                ? null
                : self::formatCents(self::toCents($net)),
        ];
    }

    /**
     * @param array<string,mixed> $lesson
     * @return array<string,string>|null
     */
    public static function lessonFeeColumns(array $lesson): ?array
    {
        $ruleName = (string)($lesson['fee_rule'] ?? '');
        if ($ruleName !== self::ONLINE_RULE && $ruleName !== self::IN_COLLEGE_RULE) {
            return null;
        }
        if (!array_key_exists('institute_online_fee', $lesson) || $lesson['institute_online_fee'] === null) {
            return null;
        }
        return [
            'gross_class_fee' => self::formatCents(self::toCents($lesson['class_fee_per_student'] ?? 0)),
            'institute_online_fee' => self::formatCents(self::toCents($lesson['institute_online_fee'])),
            'transaction_handling_fee' => self::formatCents(self::toCents($lesson['transaction_handling_fee'] ?? 0)),
            'teacher_net_amount' => self::formatCents(self::toCents($lesson['teacher_net_amount'] ?? 0)),
        ];
    }

    public static function writeLessonFeeBreakdown(PDO $pdo, int $lessonFeeId, array $lesson): void
    {
        if ($lessonFeeId < 1 || !self::tableHas($pdo, 'student_lesson_fees', 'gross_class_fee')) {
            return;
        }
        $cols = self::lessonFeeColumns($lesson);
        if ($cols === null) {
            return;
        }
        $pdo->prepare(
            'UPDATE student_lesson_fees
             SET gross_class_fee = ?, institute_online_fee = ?, transaction_handling_fee = ?, teacher_net_amount = ?
             WHERE id = ? AND status NOT IN (\'paid\', \'waived\')'
        )->execute([
            $cols['gross_class_fee'],
            $cols['institute_online_fee'],
            $cols['transaction_handling_fee'],
            $cols['teacher_net_amount'],
            $lessonFeeId,
        ]);
    }

    public static function writePaymentBreakdown(PDO $pdo, int $transactionId, int $timetableId): void
    {
        if ($transactionId < 1 || $timetableId < 1 || !self::tableHas($pdo, 'payment_transactions', 'gross_class_fee')) {
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT teacher_id, delivery_mode, class_fee_per_student, fee_rule,
                    institute_online_fee, transaction_handling_fee, teacher_net_amount
             FROM timetable WHERE id = ? LIMIT 1'
        );
        try {
            $stmt->execute([$timetableId]);
        } catch (\Throwable $e) {
            return;
        }
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lesson) {
            return;
        }
        $online = self::lessonFeeColumns($lesson);
        $gross = self::formatCents(self::toCents($lesson['class_fee_per_student'] ?? 0));
        $pdo->prepare(
            'UPDATE payment_transactions
             SET gross_class_fee = ?,
                 institute_online_fee = ?,
                 transaction_handling_fee = ?,
                 teacher_net_amount = ?,
                 delivery_mode = ?,
                 teacher_id = ?
             WHERE id = ? AND gross_class_fee IS NULL'
        )->execute([
            $online['gross_class_fee'] ?? $gross,
            $online['institute_online_fee'] ?? null,
            $online['transaction_handling_fee'] ?? null,
            $online['teacher_net_amount'] ?? null,
            self::normalizeMode((string)($lesson['delivery_mode'] ?? 'physical')),
            (int)($lesson['teacher_id'] ?? 0) > 0 ? (int)$lesson['teacher_id'] : null,
            $transactionId,
        ]);
    }

    public static function instituteAmountSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if (!function_exists('lesson_amount_sql')) {
            require_once dirname(__DIR__, 2) . '/config/payment.php';
        }
        $duration = lesson_amount_sql(
            'CAST(COALESCE(' . $prefix . 'student_count, 0) AS DECIMAL(10,2))',
            $prefix . 'start_time',
            $prefix . 'end_time'
        );
        return '(CASE WHEN ' . $prefix . "fee_rule IN ('online_v1', 'in_college_v1') THEN CAST(COALESCE("
            . $prefix . 'student_count, 0) AS DECIMAL(12,2)) * COALESCE('
            . $prefix . 'institute_online_fee, 0) ELSE ' . $duration . ' END)';
    }

    public static function tableHas(PDO $pdo, string $table, string $column): bool
    {
        $cache = &self::cacheRef();
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
        $key = spl_object_id($pdo) . ':' . $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if ($table === '' || $column === '') {
            return $cache[$key] = false;
        }
        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = '';
        }
        if ($driver === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $names = array_map(static fn (array $row): string => (string)($row['name'] ?? ''), $rows);
            return $cache[$key] = in_array($column, $names, true);
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return $cache[$key] = (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return $cache[$key] = false;
        }
    }

    public static function moneyString(mixed $amount): string
    {
        return self::formatCents(self::toCents($amount));
    }

    public static function formatRs(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $whole = number_format(intdiv($cents, 100), 0, '.', ',');
        $frac = str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
        return ($negative ? '-Rs. ' : 'Rs. ') . $whole . '.' . $frac;
    }

    public static function toCents(mixed $value): int
    {
        if (is_float($value)) {
            if (!is_finite($value)) {
                return 0;
            }
            $value = sprintf('%.6F', $value);
        } elseif (is_int($value)) {
            $value = (string)$value;
        } else {
            $value = trim((string)$value);
        }
        $value = str_replace(',', '', $value);
        if ($value === '' || !preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return 0;
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $frac] = array_pad(explode('.', $value, 2), 2, '0');
        $frac = substr(str_pad($frac, 3, '0'), 0, 3);
        $cents = ((int)$whole) * 100 + (int)substr($frac, 0, 2);
        if ((int)substr($frac, 2, 1) >= 5) {
            $cents++;
        }
        return $negative ? -$cents : $cents;
    }

    public static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        return ($negative ? '-' : '')
            . intdiv($cents, 100)
            . '.'
            . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function applyRate(int $cents, int $bps): int
    {
        if ($cents <= 0 || $bps <= 0) {
            return 0;
        }
        $bps = min(10000, $bps);
        $numerator = $cents * $bps;
        $quotient = intdiv($numerator, 10000);
        $remainder = $numerator % 10000;
        if ($remainder * 2 >= 10000) {
            $quotient++;
        }
        return $quotient;
    }

    public static function rateToBps(string $rate): int
    {
        $rate = trim($rate);
        if (!preg_match('/^\d+(\.\d+)?$/', $rate)) {
            return self::defaultConfig()['rate_bps'];
        }
        [$whole, $frac] = array_pad(explode('.', $rate, 2), 2, '0');
        $frac = substr(str_pad($frac, 5, '0'), 0, 5);
        $bps = ((int)$whole) * 10000 + (int)substr($frac, 0, 4);
        if ((int)substr($frac, 4, 1) >= 5) {
            $bps++;
        }
        return max(0, min(10000, $bps));
    }

    public static function bpsToRate(int $bps): string
    {
        $bps = max(0, min(10000, $bps));
        return intdiv($bps, 10000)
            . '.'
            . str_pad((string)($bps % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function normalizeMode(string $mode): string
    {
        if (function_exists('classroom_normalize_delivery_mode')) {
            return classroom_normalize_delivery_mode($mode);
        }
        $mode = strtolower(trim($mode));
        return in_array($mode, ['physical', 'online', 'hybrid'], true) ? $mode : 'physical';
    }

    /**
     * @return array{valid:bool,cents:int}
     */
    private static function parseGross(mixed $gross): array
    {
        if (is_float($gross)) {
            if (!is_finite($gross) || $gross < 0) {
                return ['valid' => false, 'cents' => 0];
            }
            return ['valid' => true, 'cents' => self::toCents($gross)];
        }
        if (is_int($gross)) {
            if ($gross < 0) {
                return ['valid' => false, 'cents' => 0];
            }
            return ['valid' => true, 'cents' => self::toCents($gross)];
        }
        $raw = trim(str_replace(',', '', (string)$gross));
        if ($raw === '') {
            return ['valid' => true, 'cents' => 0];
        }
        if ($raw[0] === '-' || !preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return ['valid' => false, 'cents' => 0];
        }
        return ['valid' => true, 'cents' => self::toCents($raw)];
    }

    /**
     * @param array<string,mixed> $lesson
     */
    private static function durationInstituteRate(array $lesson): float
    {
        if (!function_exists('lesson_rate_per_student') || !function_exists('lesson_duration_minutes')) {
            $file = dirname(__DIR__, 2) . '/config/payment.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (!function_exists('lesson_rate_per_student') || !function_exists('lesson_duration_minutes')) {
            return 0.0;
        }
        $minutes = lesson_duration_minutes(
            (string)($lesson['start_time'] ?? ''),
            (string)($lesson['end_time'] ?? '')
        );
        return (float)lesson_rate_per_student((int)$minutes);
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (self::tableHas($pdo, $table, $column)) {
            return;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
        if ($table === '' || $column === '') {
            return;
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            if (!str_contains($message, 'duplicate') && !str_contains($message, 'already exists')) {
                error_log('Class session fee schema ' . $table . '.' . $column . ': ' . $e->getMessage());
            }
        }
        $key = spl_object_id($pdo) . ':' . $table . '.' . $column;
        $cache = &self::cacheRef();
        unset($cache[$key]);
    }

    /**
     * @return array<string,bool>
     */
    private static function &cacheRef(): array
    {
        static $cache = [];
        return $cache;
    }
}
