<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * A teacher's own settlement account. Account numbers are stored for payouts
 * and shown masked everywhere except an admin payout form.
 */
final class TeacherBankAccountService
{
    public const REQUIRED_MESSAGE = 'Bank account details are required before creating an online class.';

    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done || $pdo->inTransaction()) {
            return;
        }
        $done = true;
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS teacher_bank_accounts (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    teacher_id INT NOT NULL,
                    bank_name VARCHAR(120) NOT NULL,
                    account_holder_name VARCHAR(160) NOT NULL,
                    account_number VARCHAR(34) NOT NULL,
                    branch VARCHAR(120) NOT NULL,
                    branch_code VARCHAR(20) NULL,
                    account_type VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_teacher_bank_teacher (teacher_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            error_log('teacher_bank_accounts schema: ' . $e->getMessage());
        }
    }

    public static function mask(?string $accountNumber): string
    {
        $digits = preg_replace('/\D/', '', (string)$accountNumber) ?? '';
        if ($digits === '') {
            return '';
        }
        return '******' . substr($digits, -4);
    }

    /**
     * @param array<string,mixed>|null $row
     */
    public static function isCompleteRow(?array $row): bool
    {
        if ($row === null) {
            return false;
        }
        $digits = preg_replace('/\D/', '', (string)($row['account_number'] ?? '')) ?? '';
        return trim((string)($row['bank_name'] ?? '')) !== ''
            && trim((string)($row['account_holder_name'] ?? '')) !== ''
            && strlen($digits) >= 6
            && strlen($digits) <= 20
            && trim((string)($row['branch'] ?? '')) !== ''
            && in_array((string)($row['account_type'] ?? ''), ['savings', 'current'], true);
    }

    public static function findForTeacher(PDO $pdo, int $teacherId): ?array
    {
        if ($teacherId < 1) {
            return null;
        }
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare('SELECT * FROM teacher_bank_accounts WHERE teacher_id = ? LIMIT 1');
            $stmt->execute([$teacherId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isComplete(PDO $pdo, int $teacherId): bool
    {
        return self::isCompleteRow(self::findForTeacher($pdo, $teacherId));
    }

    /**
     * @param list<int> $teacherIds
     * @return array<int,bool>
     */
    public static function completionMap(PDO $pdo, array $teacherIds): array
    {
        $map = [];
        $ids = [];
        foreach ($teacherIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
                $map[$id] = false;
            }
        }
        if ($ids === []) {
            return $map;
        }
        self::ensureSchema($pdo);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare(
                'SELECT teacher_id, bank_name, account_holder_name, account_number, branch, account_type
                 FROM teacher_bank_accounts WHERE teacher_id IN (' . $placeholders . ')'
            );
            $stmt->execute(array_values($ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $map[(int)$row['teacher_id']] = self::isCompleteRow($row);
            }
        } catch (\Throwable $e) {
            return $map;
        }
        return $map;
    }

    /**
     * Masked bank details for the class form. The full account number is not included.
     *
     * @param list<int> $teacherIds
     * @return array<int,array{complete:bool,bank_name:string,holder:string,account_mask:string,branch:string,branch_code:string,account_type:string}>
     */
    public static function publicSummaries(PDO $pdo, array $teacherIds): array
    {
        $map = [];
        $ids = [];
        foreach ($teacherIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $map;
        }
        self::ensureSchema($pdo);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare(
                'SELECT teacher_id, bank_name, account_holder_name, account_number, branch, branch_code, account_type
                 FROM teacher_bank_accounts WHERE teacher_id IN (' . $placeholders . ')'
            );
            $stmt->execute(array_values($ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (!self::isCompleteRow($row)) {
                    continue;
                }
                $type = (string)($row['account_type'] ?? '');
                $map[(int)$row['teacher_id']] = [
                    'complete' => true,
                    'bank_name' => (string)$row['bank_name'],
                    'holder' => (string)$row['account_holder_name'],
                    'account_mask' => self::mask((string)$row['account_number']),
                    'branch' => (string)$row['branch'],
                    'branch_code' => (string)($row['branch_code'] ?? ''),
                    'account_type' => $type === 'current' ? 'Current' : 'Savings',
                ];
            }
        } catch (\Throwable $e) {
            return $map;
        }
        return $map;
    }

    /**
     * Creating a new online class, or switching a class to online, requires
     * the class teacher's completed bank account. Editing a class that is
     * already online does not.
     *
     * @param array<string,mixed>|null $existing
     */
    public static function assertCanScheduleOnline(PDO $pdo, int $teacherId, string $mode, ?array $existing = null): void
    {
        $mode = ClassSessionFeeCalculator::normalizeMode($mode);
        if ($mode !== 'online') {
            return;
        }
        if ($existing !== null) {
            $previous = ClassSessionFeeCalculator::normalizeMode((string)($existing['delivery_mode'] ?? 'physical'));
            if ($previous === 'online') {
                return;
            }
        }
        if (!self::isComplete($pdo, $teacherId)) {
            throw new RuntimeException(self::REQUIRED_MESSAGE);
        }
    }

    /**
     * Licensed commercial and specialised banks in Sri Lanka (CBSL, May 2026).
     * Search words cover the short names teachers usually type.
     *
     * @return list<array{name:string,aliases:list<string>}>
     */
    public static function banks(): array
    {
        return [
            ['name' => 'Amana Bank PLC', 'aliases' => ['amana']],
            ['name' => 'Bank of Ceylon', 'aliases' => ['boc']],
            ['name' => 'Bank of China Ltd.', 'aliases' => ['bank of china']],
            ['name' => 'Cargills Bank PLC', 'aliases' => ['cargills']],
            ['name' => 'Citibank, N.A.', 'aliases' => ['citi', 'citibank']],
            ['name' => 'Commercial Bank of Ceylon PLC', 'aliases' => ['combank', 'commercial bank']],
            ['name' => 'Deutsche Bank AG', 'aliases' => ['deutsche']],
            ['name' => 'DFCC Bank PLC', 'aliases' => ['dfcc']],
            ['name' => 'Habib Bank Ltd.', 'aliases' => ['hbl', 'habib']],
            ['name' => 'Hatton National Bank PLC', 'aliases' => ['hnb']],
            ['name' => 'Housing Development Finance Corporation Bank of Sri Lanka (HDFC)', 'aliases' => ['hdfc']],
            ['name' => 'Indian Bank', 'aliases' => ['indian bank']],
            ['name' => 'Indian Overseas Bank', 'aliases' => ['iob']],
            ['name' => 'MCB Bank Ltd.', 'aliases' => ['mcb']],
            ['name' => 'National Development Bank PLC', 'aliases' => ['ndb']],
            ['name' => 'National Savings Bank', 'aliases' => ['nsb']],
            ['name' => 'Nations Trust Bank PLC', 'aliases' => ['ntb']],
            ['name' => 'Pan Asia Banking Corporation PLC', 'aliases' => ['pabc', 'pan asia']],
            ['name' => "People's Bank", 'aliases' => ['peoples bank']],
            ['name' => 'Pradeshiya Sanwardhana Bank (RDB)', 'aliases' => ['rdb', 'regional development bank']],
            ['name' => 'Public Bank Berhad', 'aliases' => ['public bank']],
            ['name' => 'Sampath Bank PLC', 'aliases' => ['sampath']],
            ['name' => 'SANASA Development Bank PLC', 'aliases' => ['sdb', 'sanasa']],
            ['name' => 'Seylan Bank PLC', 'aliases' => ['seylan']],
            ['name' => 'Sri Lanka Savings Bank Ltd.', 'aliases' => ['slsb']],
            ['name' => 'Standard Chartered Bank', 'aliases' => ['scb', 'standard chartered']],
            ['name' => 'State Bank of India', 'aliases' => ['sbi']],
            ['name' => 'State Mortgage & Investment Bank', 'aliases' => ['smib']],
            ['name' => 'The Hongkong & Shanghai Banking Corporation Ltd. (HSBC)', 'aliases' => ['hsbc']],
            ['name' => 'Union Bank of Colombo PLC', 'aliases' => ['union bank']],
        ];
    }

    public static function canonicalBank(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $needle = self::searchKey($value);
        foreach (self::banks() as $bank) {
            if (self::searchKey($bank['name']) === $needle) {
                return $bank['name'];
            }
            foreach ($bank['aliases'] as $alias) {
                if ($needle === self::searchKey($alias)) {
                    return $bank['name'];
                }
            }
        }
        return null;
    }

    private static function searchKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['’', '`'], "'", $value);
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    /**
     * @param array<string,mixed> $input
     * @return array{bank_name:string,account_holder_name:string,account_number:?string,branch:string,branch_code:?string,account_type:string}
     */
    public static function validate(array $input, bool $accountRequired): array
    {
        $bank = trim((string)($input['bank_name'] ?? ''));
        $holder = trim((string)($input['account_holder_name'] ?? ''));
        $branch = trim((string)($input['branch'] ?? ''));
        $branchCode = trim((string)($input['branch_code'] ?? ''));
        $type = strtolower(trim((string)($input['account_type'] ?? '')));
        $rawNumber = trim((string)($input['account_number'] ?? ''));
        $digits = preg_replace('/\D/', '', $rawNumber) ?? '';

        $bank = self::canonicalBank($bank) ?? '';
        if ($bank === '') {
            throw new RuntimeException('Choose a bank from the list.');
        }
        if ($holder === '' || strlen($holder) > 160) {
            throw new RuntimeException('Enter the account holder name.');
        }
        if ($branch === '' || strlen($branch) > 120) {
            throw new RuntimeException('Enter the branch.');
        }
        if ($branchCode !== '' && !preg_match('/^[A-Za-z0-9\-]{1,20}$/', $branchCode)) {
            throw new RuntimeException('Branch code can only contain letters, numbers, and hyphens.');
        }
        if (!in_array($type, ['savings', 'current'], true)) {
            throw new RuntimeException('Choose savings or current as the account type.');
        }
        if ($rawNumber === '') {
            if ($accountRequired) {
                throw new RuntimeException('Enter the account number.');
            }
            $digits = null;
        } elseif (strlen($digits) < 6 || strlen($digits) > 20) {
            throw new RuntimeException('Account number must be 6 to 20 digits.');
        }

        return [
            'bank_name' => $bank,
            'account_holder_name' => $holder,
            'account_number' => $digits,
            'branch' => $branch,
            'branch_code' => $branchCode !== '' ? $branchCode : null,
            'account_type' => $type,
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function save(PDO $pdo, int $teacherId, array $input, int $actorUserId): void
    {
        if ($teacherId < 1) {
            throw new RuntimeException('Teacher account not found.');
        }
        self::ensureSchema($pdo);
        $existing = self::findForTeacher($pdo, $teacherId);
        $clean = self::validate($input, $existing === null);
        $number = $clean['account_number'] ?? (string)($existing['account_number'] ?? '');
        if ($number === '') {
            throw new RuntimeException('Enter the account number.');
        }
        if ($existing) {
            $pdo->prepare(
                'UPDATE teacher_bank_accounts
                 SET bank_name = ?, account_holder_name = ?, account_number = ?, branch = ?, branch_code = ?, account_type = ?
                 WHERE teacher_id = ?'
            )->execute([
                $clean['bank_name'],
                $clean['account_holder_name'],
                $number,
                $clean['branch'],
                $clean['branch_code'],
                $clean['account_type'],
                $teacherId,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO teacher_bank_accounts
                    (teacher_id, bank_name, account_holder_name, account_number, branch, branch_code, account_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $teacherId,
                $clean['bank_name'],
                $clean['account_holder_name'],
                $number,
                $clean['branch'],
                $clean['branch_code'],
                $clean['account_type'],
            ]);
        }
        TeacherPayoutService::audit(
            $pdo,
            $actorUserId,
            $existing ? 'bank_details_updated' : 'bank_details_added',
            'teacher_bank_account',
            (string)$teacherId,
            ['masked' => self::mask($number), 'bank_name' => $clean['bank_name']]
        );
    }
}
