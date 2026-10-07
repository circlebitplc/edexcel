<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Bulk SMS Service for Edexcel College
 *
 * Handles file parsing, phone number normalization, duplicate detection (both intra-file
 * and historical fingerprinting), campaign lifecycle, safe batched delivery, retry logic,
 * and reporting.
 */
final class BulkSmsService
{
    /**
     * Standard GSM 03.38 7-bit basic character set.
     */
    private const GSM7_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    private const GSM7_EXTENDED = "^{}\\[~]|€";

    /**
     * Ensure database schema exists (self-healing for all environments).
     */
    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $driver = 'mysql';
        }

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS bulk_sms_campaigns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    campaign_code VARCHAR(32) NOT NULL UNIQUE,
                    campaign_name VARCHAR(150) NOT NULL DEFAULT '',
                    gateway VARCHAR(50) NOT NULL DEFAULT 'sms_gate_android',
                    created_by INTEGER NULL,
                    source_filename VARCHAR(255) NOT NULL DEFAULT '',
                    message TEXT NOT NULL,
                    message_hash CHAR(64) NOT NULL,
                    total_records INTEGER NOT NULL DEFAULT 0,
                    valid_records INTEGER NOT NULL DEFAULT 0,
                    invalid_records INTEGER NOT NULL DEFAULT 0,
                    duplicate_records INTEGER NOT NULL DEFAULT 0,
                    previously_sent_records INTEGER NOT NULL DEFAULT 0,
                    recipient_count INTEGER NOT NULL DEFAULT 0,
                    total_sms_units INTEGER NOT NULL DEFAULT 0,
                    sent_count INTEGER NOT NULL DEFAULT 0,
                    delivered_count INTEGER NOT NULL DEFAULT 0,
                    failed_count INTEGER NOT NULL DEFAULT 0,
                    skipped_count INTEGER NOT NULL DEFAULT 0,
                    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    started_at TEXT NULL,
                    completed_at TEXT NULL
                );
                CREATE TABLE IF NOT EXISTS bulk_sms_recipients (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    campaign_id INTEGER NOT NULL,
                    phone_number VARCHAR(20) NOT NULL,
                    name VARCHAR(150) NOT NULL DEFAULT '',
                    original_phone_number VARCHAR(50) NOT NULL DEFAULT '',
                    gateway VARCHAR(50) NULL,
                    message TEXT NOT NULL,
                    message_hash CHAR(64) NOT NULL,
                    recipient_message_hash CHAR(64) NOT NULL,
                    sms_units INTEGER NOT NULL DEFAULT 1,
                    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                    gateway_message_id VARCHAR(120) NULL,
                    gateway_response TEXT NULL,
                    error_message VARCHAR(500) NULL,
                    attempt_count INTEGER NOT NULL DEFAULT 0,
                    sent_at TEXT NULL,
                    delivered_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                );"
            );
            try {
                $pdo->exec("ALTER TABLE bulk_sms_campaigns ADD COLUMN gateway VARCHAR(50) NOT NULL DEFAULT 'sms_gate_android'");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE bulk_sms_recipients ADD COLUMN gateway VARCHAR(50) NULL");
            } catch (Throwable) {}
            $done = true;
            return;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS bulk_sms_campaigns (
                    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    campaign_code           VARCHAR(32)     NOT NULL,
                    campaign_name           VARCHAR(150)    NOT NULL DEFAULT '',
                    gateway                 VARCHAR(50)     NOT NULL DEFAULT 'sms_gate_android',
                    created_by              INT             NULL,
                    source_filename         VARCHAR(255)    NOT NULL DEFAULT '',
                    message                 TEXT            NOT NULL,
                    message_hash            CHAR(64)        NOT NULL,
                    total_records           INT             NOT NULL DEFAULT 0,
                    valid_records           INT             NOT NULL DEFAULT 0,
                    invalid_records         INT             NOT NULL DEFAULT 0,
                    duplicate_records       INT             NOT NULL DEFAULT 0,
                    previously_sent_records INT             NOT NULL DEFAULT 0,
                    recipient_count         INT             NOT NULL DEFAULT 0,
                    total_sms_units         INT             NOT NULL DEFAULT 0,
                    sent_count              INT             NOT NULL DEFAULT 0,
                    delivered_count         INT             NOT NULL DEFAULT 0,
                    failed_count            INT             NOT NULL DEFAULT 0,
                    skipped_count           INT             NOT NULL DEFAULT 0,
                    status                  VARCHAR(30)     NOT NULL DEFAULT 'DRAFT',
                    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    started_at              DATETIME        NULL,
                    completed_at            DATETIME        NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_bsc_code (campaign_code),
                    KEY idx_bsc_status (status, created_at),
                    KEY idx_bsc_created_by (created_by),
                    KEY idx_bsc_created_at (created_at),
                    KEY idx_bsc_gateway (gateway)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            try {
                $pdo->exec("ALTER TABLE bulk_sms_campaigns ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER campaign_code");
            } catch (Throwable) {}

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS bulk_sms_recipients (
                    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    campaign_id             BIGINT UNSIGNED NOT NULL,
                    phone_number            VARCHAR(20)     NOT NULL,
                    name                    VARCHAR(150)    NOT NULL DEFAULT '',
                    original_phone_number   VARCHAR(50)     NOT NULL DEFAULT '',
                    gateway                 VARCHAR(50)     NULL,
                    message                 TEXT            NOT NULL,
                    message_hash            CHAR(64)        NOT NULL,
                    recipient_message_hash  CHAR(64)        NOT NULL,
                    sms_units               INT             NOT NULL DEFAULT 1,
                    status                  VARCHAR(20)     NOT NULL DEFAULT 'PENDING',
                    gateway_message_id      VARCHAR(120)    NULL,
                    gateway_response        TEXT            NULL,
                    error_message           VARCHAR(500)    NULL,
                    attempt_count           INT             NOT NULL DEFAULT 0,
                    sent_at                 DATETIME        NULL,
                    delivered_at            DATETIME        NULL,
                    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_campaign_recipient (campaign_id, phone_number),
                    KEY idx_bsr_campaign_status (campaign_id, status),
                    KEY idx_bsr_phone (phone_number),
                    KEY idx_bsr_recip_hash (recipient_message_hash, status),
                    KEY idx_bsr_status (status),
                    KEY idx_bsr_gateway (gateway)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Self-healing columns if table already existed prior to migration
            try {
                $pdo->exec("ALTER TABLE bulk_sms_campaigns ADD COLUMN gateway VARCHAR(50) NOT NULL DEFAULT 'sms_gate_android' AFTER campaign_name");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE bulk_sms_recipients ADD COLUMN gateway VARCHAR(50) NULL AFTER original_phone_number");
            } catch (Throwable) {}

            $done = true;
        } catch (Throwable $e) {
            error_log('BulkSmsService::ensureSchema error: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique campaign code: SMS-YYYYMMDD-XXXX
     */
    public static function generateCampaignCode(PDO $pdo): string
    {
        self::ensureSchema($pdo);
        $datePrefix = date('Ymd');
        $prefix = "SMS-{$datePrefix}-";

        try {
            $stmt = $pdo->prepare("SELECT campaign_code FROM bulk_sms_campaigns WHERE campaign_code LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$prefix . '%']);
            $lastCode = $stmt->fetchColumn();

            if ($lastCode && preg_match('/SMS-\d{8}-(\d{4})/', (string)$lastCode, $matches)) {
                $seq = (int)$matches[1] + 1;
            } else {
                $seq = 1;
            }
        } catch (Throwable) {
            $seq = 1;
        }

        return sprintf("SMS-%s-%04d", $datePrefix, $seq);
    }

    /**
     * Normalizes message text for hashing and fingerprinting (trims, normalizes newlines & spaces).
     */
    public static function normalizeMessage(string $message): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $message);
        return trim((string)preg_replace('/[ \t]+/u', ' ', $text));
    }

    /**
     * Calculate SMS segments, length, and encoding according to GSM standards.
     *
     * GSM-7: 1 segment = 160 chars; multi-part = 153 chars per segment.
     * Unicode/UCS-2: 1 segment = 70 chars; multi-part = 67 chars per segment.
     *
     * @return array{encoding:string, length:int, segments:int, chars_left:int, max_per_segment:int}
     */
    public static function calculateSmsUnits(string $message): array
    {
        $rawLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
        if ($rawLength === 0) {
            return [
                'encoding'        => 'GSM-7',
                'length'          => 0,
                'segments'        => 0,
                'chars_left'      => 160,
                'max_per_segment' => 160,
            ];
        }

        // Check if any character falls outside the standard GSM-7 basic & extended tables
        $isGsm = true;
        $gsmLen = 0;
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($chars as $ch) {
            if (str_contains(self::GSM7_BASIC, $ch)) {
                $gsmLen += 1;
            } elseif (str_contains(self::GSM7_EXTENDED, $ch)) {
                $gsmLen += 2; // Extended characters count as 2 septets
            } else {
                $isGsm = false;
                break;
            }
        }

        if ($isGsm) {
            $encoding = 'GSM-7';
            $length = $gsmLen;
            if ($length <= 160) {
                $segments = 1;
                $maxPerSegment = 160;
                $charsLeft = 160 - $length;
            } else {
                $maxPerSegment = 153;
                $segments = (int)ceil($length / 153);
                $charsLeft = ($segments * 153) - $length;
            }
        } else {
            $encoding = 'Unicode';
            $length = $rawLength;
            if ($length <= 70) {
                $segments = 1;
                $maxPerSegment = 70;
                $charsLeft = 70 - $length;
            } else {
                $maxPerSegment = 67;
                $segments = (int)ceil($length / 67);
                $charsLeft = ($segments * 67) - $length;
            }
        }

        return [
            'encoding'        => $encoding,
            'length'          => $length,
            'segments'        => max(1, $segments),
            'chars_left'      => max(0, $charsLeft),
            'max_per_segment' => $maxPerSegment,
        ];
    }

    /**
     * Prevents CSV Formula Injection (CWE-1236) by prepending a quote
     * to any field starting with =, +, -, @, tab, or carriage return.
     */
    public static function sanitizeCsvCell(mixed $value): string
    {
        $str = (string)$value;
        if ($str === '') {
            return '';
        }
        $first = $str[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r") {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Parse and analyze an uploaded recipient file (CSV or TXT).
     *
     * Cross-references phone numbers against existing students/parents/teachers
     * and historical SMS send logs to detect previously contacted recipients.
     *
     * @return array<string,mixed> Detailed analysis structure
     */
    public static function analyzeFile(
        string $filePath,
        string $originalFilename,
        PDO $pdo,
        string $message = ''
    ): array {
        self::ensureSchema($pdo);

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Uploaded file cannot be read or is missing.');
        }

        $fileSize = (int)filesize($filePath);
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $rawLines = [];
        if ($ext === 'txt') {
            $content = file_get_contents($filePath) ?: '';
            $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $rawLines[] = [$line];
                }
            }
        } else {
            // CSV parsing with auto delimiter detection
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new RuntimeException('Failed to open CSV file.');
            }

            // Detect delimiter by inspecting first row
            $firstChunk = fread($handle, 4096);
            rewind($handle);
            $delimiter = ',';
            if ($firstChunk !== false) {
                $semis = substr_count($firstChunk, ';');
                $commas = substr_count($firstChunk, ',');
                $tabs = substr_count($firstChunk, "\t");
                if ($semis > $commas && $semis > $tabs) {
                    $delimiter = ';';
                } elseif ($tabs > $commas && $tabs > $semis) {
                    $delimiter = "\t";
                }
            }

            while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
                // Skip completely empty rows
                $hasVal = false;
                foreach ($row as $cell) {
                    if (trim((string)$cell) !== '') {
                        $hasVal = true;
                        break;
                    }
                }
                if ($hasVal) {
                    $rawLines[] = $row;
                }
            }
            fclose($handle);
        }

        $totalRecords = count($rawLines);
        if ($totalRecords === 0) {
            return [
                'filename'                => $originalFilename,
                'file_size'               => $fileSize,
                'total_records'           => 0,
                'valid_records'           => 0,
                'invalid_records'         => 0,
                'duplicate_records'       => 0,
                'previously_sent_records' => 0,
                'new_recipients'          => 0,
                'records'                 => [],
                'sample_records'          => [],
            ];
        }

        // Identify phone column and name column in CSV
        $phoneColIdx = 0;
        $nameColIdx = -1;
        $startIndex = 0;

        $firstRow = $rawLines[0];
        $isHeader = false;

        // Check if row 0 looks like a header
        foreach ($firstRow as $idx => $headerText) {
            $cleanHeader = strtolower(trim((string)$headerText));
            if (preg_match('/phone|mobile|whatsapp|tel|contact|number/i', $cleanHeader)) {
                $phoneColIdx = $idx;
                $isHeader = true;
            } elseif (preg_match('/name|student|fullname|first_name|recipient/i', $cleanHeader)) {
                $nameColIdx = $idx;
                $isHeader = true;
            }
        }

        if ($isHeader) {
            $startIndex = 1;
        } else {
            // Auto-detect which column has phone numbers
            $colScores = [];
            foreach (array_slice($rawLines, 0, min(10, $totalRecords)) as $sampleRow) {
                foreach ($sampleRow as $cIdx => $val) {
                    $norm = SmsService::normalizeSriLankanNumber(trim((string)$val));
                    if ($norm !== '') {
                        $colScores[$cIdx] = ($colScores[$cIdx] ?? 0) + 1;
                    }
                }
            }
            if ($colScores !== []) {
                arsort($colScores);
                $phoneColIdx = (int)array_key_first($colScores);
            }
        }

        $normalizedMsg = self::normalizeMessage($message);
        $messageHash = hash('sha256', $normalizedMsg);

        // Process rows
        $analyzedRecords = [];
        $seenInFile = [];
        $uniqueValidPhones = [];

        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;
        $previouslySentCount = 0;

        for ($i = $startIndex; $i < $totalRecords; $i++) {
            $row = $rawLines[$i];
            $rawPhone = trim((string)($row[$phoneColIdx] ?? ''));
            $rawName = ($nameColIdx >= 0 && isset($row[$nameColIdx])) ? trim((string)$row[$nameColIdx]) : '';

            $normalizedPhone = SmsService::normalizeSriLankanNumber($rawPhone);

            if ($normalizedPhone === '') {
                $invalidCount++;
                $analyzedRecords[] = [
                    'row_number'          => $i + 1,
                    'original_phone'      => $rawPhone,
                    'normalized_phone'    => '',
                    'name'                => $rawName,
                    'status'              => 'INVALID',
                    'reason'              => 'Invalid phone format (must be a valid Sri Lankan mobile, e.g. 077XXXXXXX)',
                    'is_valid'            => false,
                    'is_duplicate'        => false,
                    'is_previously_sent'  => false,
                    'match_info'          => null,
                    'previous_sent_date'  => null,
                ];
                continue;
            }

            // Check duplicate in file
            if (isset($seenInFile[$normalizedPhone])) {
                $duplicateCount++;
                $analyzedRecords[] = [
                    'row_number'          => $i + 1,
                    'original_phone'      => $rawPhone,
                    'normalized_phone'    => $normalizedPhone,
                    'name'                => $rawName,
                    'status'              => 'DUPLICATE',
                    'reason'              => 'Duplicate entry in uploaded file',
                    'is_valid'            => false,
                    'is_duplicate'        => true,
                    'is_previously_sent'  => false,
                    'match_info'          => null,
                    'previous_sent_date'  => null,
                ];
                continue;
            }

            $seenInFile[$normalizedPhone] = true;
            $uniqueValidPhones[] = $normalizedPhone;

            $analyzedRecords[] = [
                'row_number'          => $i + 1,
                'original_phone'      => $rawPhone,
                'normalized_phone'    => $normalizedPhone,
                'name'                => $rawName,
                'status'              => 'VALID',
                'reason'              => '',
                'is_valid'            => true,
                'is_duplicate'        => false,
                'is_previously_sent'  => false,
                'match_info'          => null,
                'previous_sent_date'  => null,
            ];
            $validCount++;
        }

        // Cross-reference existing contacts in Edexcel College DB
        $dbMatches = self::matchExistingContacts($pdo, $uniqueValidPhones);

        // Cross-reference historical sends for duplicate fingerprinting
        $historyMatches = self::checkHistoricalSends($pdo, $uniqueValidPhones, $normalizedMsg);

        // Apply matches to records
        foreach ($analyzedRecords as &$rec) {
            $phone = $rec['normalized_phone'];
            if ($phone === '') {
                continue;
            }

            if (isset($dbMatches[$phone])) {
                $rec['match_info'] = $dbMatches[$phone];
                if ($rec['name'] === '' && !empty($dbMatches[$phone]['name'])) {
                    $rec['name'] = $dbMatches[$phone]['name'];
                }
            }

            if (isset($historyMatches[$phone])) {
                $rec['is_previously_sent'] = true;
                $rec['previous_sent_date'] = $historyMatches[$phone]['sent_at'];
                $rec['reason'] = 'Previously sent on ' . $historyMatches[$phone]['sent_at'];
                if ($rec['status'] === 'VALID') {
                    $rec['status'] = 'PREVIOUSLY_SENT';
                    $previouslySentCount++;
                }
            }
        }
        unset($rec);

        $newRecipientsCount = max(0, $validCount - $previouslySentCount);

        return [
            'filename'                => $originalFilename,
            'file_size'               => $fileSize,
            'total_records'           => $totalRecords,
            'valid_records'           => $validCount,
            'invalid_records'         => $invalidCount,
            'duplicate_records'       => $duplicateCount,
            'previously_sent_records' => $previouslySentCount,
            'new_recipients'          => $newRecipientsCount,
            'records'                 => $analyzedRecords,
            'sample_records'          => array_slice($analyzedRecords, 0, 50),
        ];
    }

    /**
     * Batch check for prior sends of the same phone + message hash.
     *
     * @param string[] $phones
     * @return array<string,array{sent_at:string,campaign_code:string}>
     */
    private static function checkHistoricalSends(PDO $pdo, array $phones, string $normalizedMessage): array
    {
        if ($phones === [] || $normalizedMessage === '') {
            return [];
        }

        $results = [];
        $recipHashes = [];
        foreach ($phones as $p) {
            $recipHashes[hash('sha256', $p . '|' . $normalizedMessage)] = $p;
        }

        $hashChunks = array_chunk(array_keys($recipHashes), 200);

        foreach ($hashChunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $stmt = $pdo->prepare(
                    "SELECT r.recipient_message_hash, r.phone_number, r.sent_at, c.campaign_code
                     FROM bulk_sms_recipients r
                     JOIN bulk_sms_campaigns c ON c.id = r.campaign_id
                     WHERE r.recipient_message_hash IN ($placeholders)
                       AND r.status IN ('SENT', 'DELIVERED')
                     ORDER BY r.id DESC"
                );
                $stmt->execute($chunk);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $phone = (string)$row['phone_number'];
                    if (!isset($results[$phone])) {
                        $results[$phone] = [
                            'sent_at'       => (string)($row['sent_at'] ?? 'Previously'),
                            'campaign_code' => (string)($row['campaign_code'] ?? ''),
                        ];
                    }
                }
            } catch (Throwable) {
                // Table might not have prior records
            }

            // Also check sms_logs table
            try {
                $logPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
                // Lookup in sms_logs where recipient matches and status='sent'
                $phoneChunk = array_values(array_intersect_key($recipHashes, array_flip($chunk)));
                if ($phoneChunk !== []) {
                    $pHolders = implode(',', array_fill(0, count($phoneChunk), '?'));
                    $stmtLog = $pdo->prepare(
                        "SELECT recipient, created_at FROM sms_logs 
                         WHERE recipient IN ($pHolders) AND status = 'sent' AND message = ?
                         ORDER BY id DESC"
                    );
                    $params = array_merge($phoneChunk, [$normalizedMessage]);
                    $stmtLog->execute($params);
                    while ($r = $stmtLog->fetch(PDO::FETCH_ASSOC)) {
                        $p = (string)$r['recipient'];
                        if (!isset($results[$p])) {
                            $results[$p] = [
                                'sent_at'       => (string)($r['created_at'] ?? 'Previously'),
                                'campaign_code' => 'SMS-GATEWAY',
                            ];
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        return $results;
    }

    /**
     * Batch lookup phones in student_profiles, users, teachers, and parent_accounts.
     *
     * @param string[] $phones
     * @return array<string,array{role:string,name:string,identifier:string}>
     */
    private static function matchExistingContacts(PDO $pdo, array $phones): array
    {
        if ($phones === []) {
            return [];
        }

        $matches = [];
        $chunks = array_chunk($phones, 200);

        foreach ($chunks as $chunk) {
            $inClause = implode(',', array_fill(0, count($chunk), '?'));

            // 1. Check students
            try {
                $sql = "SELECT u.id, u.username, sp.full_name, sp.parent_whatsapp
                        FROM users u
                        LEFT JOIN student_profiles sp ON sp.user_id = u.id
                        WHERE u.role = 'student' AND (u.username IN ($inClause) OR sp.parent_whatsapp IN ($inClause))";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($chunk, $chunk));
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $uPhone = SmsService::normalizeSriLankanNumber((string)$row['username']);
                    $pPhone = SmsService::normalizeSriLankanNumber((string)($row['parent_whatsapp'] ?? ''));
                    $label = trim((string)($row['full_name'] ?? '')) ?: (string)$row['username'];

                    if ($uPhone !== '' && !isset($matches[$uPhone])) {
                        $matches[$uPhone] = ['role' => 'Student', 'name' => $label, 'identifier' => (string)$row['username']];
                    }
                    if ($pPhone !== '' && !isset($matches[$pPhone])) {
                        $matches[$pPhone] = ['role' => 'Parent', 'name' => $label . ' (Parent)', 'identifier' => (string)$row['username']];
                    }
                }
            } catch (Throwable) {
            }

            // 2. Check teachers
            try {
                $sql = "SELECT id, name, phone FROM teachers WHERE phone IN ($inClause) AND deleted_at IS NULL";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($chunk);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $tPhone = SmsService::normalizeSriLankanNumber((string)$row['phone']);
                    if ($tPhone !== '' && !isset($matches[$tPhone])) {
                        $matches[$tPhone] = ['role' => 'Teacher', 'name' => (string)$row['name'], 'identifier' => 'Teacher #' . $row['id']];
                    }
                }
            } catch (Throwable) {
            }
        }

        return $matches;
    }

    /**
     * Create a new campaign and populate its recipients.
     *
     * @param array<int,array<string,mixed>> $analyzedRecords
     */
    public static function createCampaign(
        PDO $pdo,
        int $adminId,
        string $filename,
        string $message,
        array $analyzedRecords,
        bool $excludePreviouslySent = true,
        string $campaignName = '',
        string $gateway = 'sms_gate_android',
        ?string $idempotencyKey = null
    ): array {
        self::ensureSchema($pdo);

        $idempotencyKey = trim((string)$idempotencyKey);
        if ($idempotencyKey !== '') {
            try {
                $stmtIdem = $pdo->prepare("SELECT * FROM bulk_sms_campaigns WHERE idempotency_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1");
                $stmtIdem->execute([$idempotencyKey]);
                $existing = $stmtIdem->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    return [
                        'campaign_id'     => (int)$existing['id'],
                        'campaign_code'   => (string)$existing['campaign_code'],
                        'gateway'         => (string)($existing['gateway'] ?? 'sms_gate_android'),
                        'gateway_label'   => SmsService::getGatewayLabel((string)($existing['gateway'] ?? 'sms_gate_android')),
                        'recipient_count' => (int)($existing['recipient_count'] ?? 0),
                        'total_sms_units' => (int)($existing['total_sms_units'] ?? 0),
                        'status'          => (string)($existing['status'] ?? 'READY'),
                        'is_idempotent'   => true,
                    ];
                }
            } catch (Throwable) {}
        }

        $normalizedGateway = SmsService::normalizeGatewayIdentifier($gateway);
        if ($normalizedGateway === '') {
            $normalizedGateway = 'sms_gate_android';
        }

        // Server-side anti-tampering validation
        if ($normalizedGateway === 'ipromo') {
            if (function_exists('ipromo_enabled') && !ipromo_enabled($pdo)) {
                throw new RuntimeException('iPromo SMS Gateway is currently disabled in system settings. Campaign creation rejected.');
            }
        }

        $normalizedMsg = self::normalizeMessage($message);
        if ($normalizedMsg === '') {
            throw new RuntimeException('Cannot create a campaign with an empty message.');
        }

        $unitsCalc = self::calculateSmsUnits($normalizedMsg);
        $smsUnitsPerMessage = $unitsCalc['segments'];
        $messageHash = hash('sha256', $normalizedMsg);

        $campaignCode = self::generateCampaignCode($pdo);
        if ($campaignName === '') {
            $campaignName = 'Bulk Campaign ' . $campaignCode;
        }

        $totalRecords = count($analyzedRecords);
        $validRecords = 0;
        $invalidRecords = 0;
        $duplicateRecords = 0;
        $prevSentRecords = 0;
        $recipientsToSend = [];

        foreach ($analyzedRecords as $rec) {
            if (!empty($rec['is_duplicate'])) {
                $duplicateRecords++;
                continue;
            }
            if (empty($rec['is_valid'])) {
                $invalidRecords++;
                continue;
            }

            $validRecords++;

            if (!empty($rec['is_previously_sent'])) {
                $prevSentRecords++;
                if ($excludePreviouslySent) {
                    $rec['status'] = 'SKIPPED';
                    $rec['error_message'] = 'Skipped: Previously sent identical message';
                    $recipientsToSend[] = $rec;
                    continue;
                }
            }

            $rec['status'] = 'PENDING';
            $rec['error_message'] = null;
            $recipientsToSend[] = $rec;
        }

        $effectiveSendCount = count(array_filter($recipientsToSend, static fn($r) => $r['status'] === 'PENDING'));
        if ($effectiveSendCount === 0 && $excludePreviouslySent && $prevSentRecords > 0) {
            throw new RuntimeException('No new recipients available. All valid numbers have already received this exact message.');
        }
        if ($effectiveSendCount === 0 && $validRecords === 0) {
            throw new RuntimeException('The file contains no valid recipients.');
        }

        $totalSmsUnits = $effectiveSendCount * $smsUnitsPerMessage;

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO bulk_sms_campaigns
                 (campaign_code, idempotency_key, campaign_name, gateway, created_by, source_filename, message, message_hash,
                  total_records, valid_records, invalid_records, duplicate_records, previously_sent_records,
                  recipient_count, total_sms_units, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'READY')"
            );
            $stmt->execute([
                $campaignCode,
                $idempotencyKey !== '' ? $idempotencyKey : null,
                $campaignName,
                $normalizedGateway,
                $adminId > 0 ? $adminId : null,
                $filename,
                $normalizedMsg,
                $messageHash,
                $totalRecords,
                $validRecords,
                $invalidRecords,
                $duplicateRecords,
                $prevSentRecords,
                $effectiveSendCount,
                $totalSmsUnits,
            ]);
            $campaignId = (int)$pdo->lastInsertId();

            // Insert recipients in batch
            $recipStmt = $pdo->prepare(
                "INSERT INTO bulk_sms_recipients
                 (campaign_id, phone_number, name, original_phone_number, gateway, message, message_hash,
                  recipient_message_hash, sms_units, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), gateway = VALUES(gateway)"
            );

            foreach ($recipientsToSend as $r) {
                $phone = $r['normalized_phone'];
                $recipHash = hash('sha256', $phone . '|' . $normalizedMsg);
                $recipStmt->execute([
                    $campaignId,
                    $phone,
                    (string)($r['name'] ?? ''),
                    (string)($r['original_phone'] ?? $phone),
                    $normalizedGateway,
                    $normalizedMsg,
                    $messageHash,
                    $recipHash,
                    $smsUnitsPerMessage,
                    $r['status'],
                    $r['error_message'],
                ]);
            }

            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            if (function_exists('log_audit')) {
                log_audit($pdo, 'create_bulk_sms_campaign', 'bulk_sms_campaigns', $campaignId, null, [
                    'campaign_code'   => $campaignCode,
                    'gateway'         => $normalizedGateway,
                    'recipient_count' => $effectiveSendCount,
                    'total_sms_units' => $totalSmsUnits,
                    'filename'        => $filename,
                ]);
            }

            return [
                'campaign_id'     => $campaignId,
                'campaign_code'   => $campaignCode,
                'gateway'         => $normalizedGateway,
                'gateway_label'   => SmsService::getGatewayLabel($normalizedGateway),
                'recipient_count' => $effectiveSendCount,
                'total_sms_units' => $totalSmsUnits,
                'status'          => 'READY',
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('createCampaign error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create bulk SMS campaign: ' . $e->getMessage());
        }
    }

    /**
     * Process a batch of pending SMS messages for a given campaign.
     *
     * Resumable: can be called repeatedly until remaining count is 0.
     * Safe: records individual results, handles rate-limits, and respects gateway enable toggle.
     */
    public static function sendBatch(PDO $pdo, int $campaignId, int $batchSize = 50, ?int $actorId = null): array
    {
        self::ensureSchema($pdo);

        // 1. Verify campaign exists
        $cStmt = $pdo->prepare("SELECT * FROM bulk_sms_campaigns WHERE id = ? LIMIT 1");
        $cStmt->execute([$campaignId]);
        $campaign = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found.', 'is_completed' => true];
        }

        // 2. Gateway identification & anti-tampering verification
        $gateway = SmsService::normalizeGatewayIdentifier((string)($campaign['gateway'] ?? 'sms_gate_android'));
        if ($gateway === '') {
            $gateway = 'sms_gate_android';
        }

        if ($gateway === 'ipromo') {
            $ipromoOn = function_exists('ipromo_enabled') && $pdo instanceof PDO ? ipromo_enabled($pdo) : false;
            if (!$ipromoOn) {
                return [
                    'success'      => false,
                    'error'        => 'iPromo SMS Gateway is currently disabled in system settings. Sending halted.',
                    'is_completed' => false,
                ];
            }
        }

        // Set status to SENDING if READY or DRAFT
        if ($campaign['status'] === 'READY' || $campaign['status'] === 'DRAFT') {
            $pdo->prepare("UPDATE bulk_sms_campaigns SET status = 'SENDING', started_at = COALESCE(started_at, NOW()) WHERE id = ?")
                ->execute([$campaignId]);
        }

        // 3. Fetch next batch of PENDING recipients
        $rStmt = $pdo->prepare(
            "SELECT id, phone_number, name, message 
             FROM bulk_sms_recipients 
             WHERE campaign_id = ? AND status = 'PENDING' 
             ORDER BY id ASC 
             LIMIT ?"
        );
        $rStmt->bindValue(1, $campaignId, PDO::PARAM_INT);
        $rStmt->bindValue(2, max(1, $batchSize), PDO::PARAM_INT);
        $rStmt->execute();
        $batch = $rStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $updateStmt = $pdo->prepare(
            "UPDATE bulk_sms_recipients 
             SET status = ?, gateway = ?, gateway_message_id = ?, error_message = ?, attempt_count = attempt_count + 1, sent_at = ?
             WHERE id = ?"
        );

        $sentInBatch = 0;
        $failedInBatch = 0;

        foreach ($batch as $recip) {
            $rId = (int)$recip['id'];
            $phone = (string)$recip['phone_number'];
            $text = (string)$recip['message'];

            // Dispatch via central SmsService with strict provider isolation and NO automatic fallback
            try {
                $sendResult = SmsService::send($phone, $text, $pdo, 'bulk_sms:' . $campaign['campaign_code'], $actorId, $gateway);
            } catch (Throwable $e) {
                $sendResult = [
                    'success'  => false,
                    'provider' => $gateway,
                    'error'    => $e->getMessage(),
                ];
            }

            if (!empty($sendResult['success'])) {
                $msgId = (string)($sendResult['message_id'] ?? '');
                $updateStmt->execute(['SENT', $gateway, $msgId ?: null, null, date('Y-m-d H:i:s'), $rId]);
                $sentInBatch++;
            } else {
                $err = (string)($sendResult['error'] ?? 'Gateway rejected message');
                $updateStmt->execute(['FAILED', $gateway, null, substr($err, 0, 500), null, $rId]);
                $failedInBatch++;
            }
        }

        // 4. Update campaign aggregates
        $pdo->prepare(
            "UPDATE bulk_sms_campaigns 
             SET sent_count = (SELECT COUNT(*) FROM bulk_sms_recipients WHERE campaign_id = ? AND status = 'SENT'),
                 failed_count = (SELECT COUNT(*) FROM bulk_sms_recipients WHERE campaign_id = ? AND status = 'FAILED'),
                 skipped_count = (SELECT COUNT(*) FROM bulk_sms_recipients WHERE campaign_id = ? AND status = 'SKIPPED')
             WHERE id = ?"
        )->execute([$campaignId, $campaignId, $campaignId, $campaignId]);

        // 5. Check remaining count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM bulk_sms_recipients WHERE campaign_id = ? AND status = 'PENDING'");
        $cntStmt->execute([$campaignId]);
        $remaining = (int)$cntStmt->fetchColumn();

        $isCompleted = ($remaining === 0);

        if ($isCompleted) {
            // Get final stats
            $statStmt = $pdo->prepare("SELECT sent_count, failed_count, recipient_count FROM bulk_sms_campaigns WHERE id = ?");
            $statStmt->execute([$campaignId]);
            $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

            $finalStatus = (!empty($stats['failed_count']) && (int)$stats['failed_count'] > 0)
                ? 'COMPLETED_WITH_ERRORS'
                : 'COMPLETED';

            $pdo->prepare("UPDATE bulk_sms_campaigns SET status = ?, completed_at = NOW() WHERE id = ?")
                ->execute([$finalStatus, $campaignId]);

            if (function_exists('log_audit')) {
                log_audit($pdo, 'complete_bulk_sms_campaign', 'bulk_sms_campaigns', $campaignId, null, [
                    'status'  => $finalStatus,
                    'gateway' => $gateway,
                    'sent'    => $stats['sent_count'] ?? 0,
                    'failed'  => $stats['failed_count'] ?? 0,
                ]);
            }
        }

        // Refresh stats for response
        $cStmt->execute([$campaignId]);
        $camp = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalRecips = (int)($camp['recipient_count'] ?? 0);
        $sentCount = (int)($camp['sent_count'] ?? 0);
        $failedCount = (int)($camp['failed_count'] ?? 0);
        $skippedCount = (int)($camp['skipped_count'] ?? 0);
        $processed = $sentCount + $failedCount;
        $pct = $totalRecips > 0 ? min(100, round(($processed / $totalRecips) * 100, 1)) : 100;

        return [
            'success'         => true,
            'campaign_id'     => $campaignId,
            'campaign_code'   => $camp['campaign_code'] ?? '',
            'gateway'         => $gateway,
            'gateway_label'   => SmsService::getGatewayLabel($gateway),
            'status'          => $camp['status'] ?? '',
            'processed'       => $processed,
            'total'           => $totalRecips,
            'sent'            => $sentCount,
            'failed'          => $failedCount,
            'skipped'         => $skippedCount,
            'remaining'       => $remaining,
            'percent'         => $pct,
            'is_completed'    => $isCompleted,
            'sent_in_batch'   => $sentInBatch,
            'failed_in_batch' => $failedInBatch,
        ];
    }

    /**
     * Reset FAILED recipients in a campaign to PENDING for re-sending.
     */
    public static function retryFailed(PDO $pdo, int $campaignId, int $actorId): array
    {
        self::ensureSchema($pdo);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE bulk_sms_recipients 
                 SET status = 'PENDING', error_message = NULL 
                 WHERE campaign_id = ? AND status = 'FAILED'"
            );
            $stmt->execute([$campaignId]);
            $resetCount = $stmt->rowCount();

            if ($resetCount > 0) {
                $pdo->prepare("UPDATE bulk_sms_campaigns SET status = 'READY' WHERE id = ?")
                    ->execute([$campaignId]);

                if (function_exists('log_audit')) {
                    log_audit($pdo, 'retry_bulk_sms_failed', 'bulk_sms_campaigns', $campaignId, null, [
                        'retried_recipients' => $resetCount,
                    ]);
                }
            }

            $pdo->commit();
            return ['success' => true, 'retried_count' => $resetCount];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retrieve campaigns for history page.
     *
     * @param array<string,mixed> $filters
     * @return array{total:int, campaigns:array<int,array<string,mixed>>}
     */
    public static function getCampaigns(PDO $pdo, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        self::ensureSchema($pdo);

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['gateway'])) {
            $normGw = SmsService::normalizeGatewayIdentifier($filters['gateway']);
            if ($normGw !== '') {
                $where[] = "c.gateway = ?";
                $params[] = $normGw;
            }
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(c.campaign_code LIKE ? OR c.campaign_name LIKE ? OR c.message LIKE ? OR c.source_filename LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['date_from'])) {
            $where[] = "c.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM bulk_sms_campaigns c $whereClause");
            $cntStmt->execute($params);
            $total = (int)$cntStmt->fetchColumn();

            $sql = "SELECT c.*, u.username AS creator_username
                    FROM bulk_sms_campaigns c
                    LEFT JOIN users u ON u.id = c.created_by
                    $whereClause
                    ORDER BY c.id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $idx = 1;
            foreach ($params as $p) {
                $stmt->bindValue($idx++, $p);
            }
            $stmt->bindValue($idx++, max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue($idx++, max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($campaigns as &$c) {
                $gw = (string)($c['gateway'] ?? 'sms_gate_android');
                $c['gateway'] = $gw;
                $c['gateway_label'] = SmsService::getGatewayLabel($gw);
            }
            unset($c);

            return ['total' => $total, 'campaigns' => $campaigns];
        } catch (Throwable $e) {
            error_log('getCampaigns error: ' . $e->getMessage());
            return ['total' => 0, 'campaigns' => []];
        }
    }

    /**
     * Get campaign details and recipient breakdown.
     */
    public static function getCampaignDetails(PDO $pdo, int $campaignId, int $recipLimit = 100, int $recipOffset = 0, string $statusFilter = ''): ?array
    {
        self::ensureSchema($pdo);

        try {
            $cStmt = $pdo->prepare(
                "SELECT c.*, u.username AS creator_username
                 FROM bulk_sms_campaigns c
                 LEFT JOIN users u ON u.id = c.created_by
                 WHERE c.id = ? LIMIT 1"
            );
            $cStmt->execute([$campaignId]);
            $campaign = $cStmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) {
                return null;
            }

            $gw = (string)($campaign['gateway'] ?? 'sms_gate_android');
            $campaign['gateway'] = $gw;
            $campaign['gateway_label'] = SmsService::getGatewayLabel($gw);

            $recipWhere = ["campaign_id = ?"];
            $recipParams = [$campaignId];

            if ($statusFilter !== '') {
                $recipWhere[] = "status = ?";
                $recipParams[] = $statusFilter;
            }

            $wClause = implode(' AND ', $recipWhere);

            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM bulk_sms_recipients WHERE $wClause");
            $cntStmt->execute($recipParams);
            $totalRecips = (int)$cntStmt->fetchColumn();

            $sql = "SELECT * FROM bulk_sms_recipients WHERE $wClause ORDER BY id ASC LIMIT ? OFFSET ?";
            $rStmt = $pdo->prepare($sql);
            $idx = 1;
            foreach ($recipParams as $p) {
                $rStmt->bindValue($idx++, $p);
            }
            $rStmt->bindValue($idx++, max(1, $recipLimit), PDO::PARAM_INT);
            $rStmt->bindValue($idx++, max(0, $recipOffset), PDO::PARAM_INT);
            $rStmt->execute();
            $recipients = $rStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'campaign'         => $campaign,
                'total_recipients' => $totalRecips,
                'recipients'       => $recipients,
            ];
        } catch (Throwable $e) {
            error_log('getCampaignDetails error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get failed recipients for a campaign with phone, name, and safe error details.
     *
     * @return array<int,array{phone_number:string,name:string,gateway:string,error_message:string}>
     */
    public static function getFailedRecipients(PDO $pdo, int $campaignId, int $limit = 100): array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare(
                "SELECT phone_number, name, gateway, error_message 
                 FROM bulk_sms_recipients 
                 WHERE campaign_id = ? AND status = 'FAILED' 
                 ORDER BY id ASC 
                 LIMIT ?"
            );
            $stmt->bindValue(1, $campaignId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Search recipient-level SMS history across all campaigns and logs.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function searchRecipientHistory(PDO $pdo, string $query, int $limit = 50): array
    {
        self::ensureSchema($pdo);
        $norm = SmsService::normalizeSriLankanNumber($query);
        $wild = '%' . trim($query) . '%';

        $results = [];

        try {
            $sql = "SELECT r.*, c.campaign_code, c.campaign_name
                    FROM bulk_sms_recipients r
                    JOIN bulk_sms_campaigns c ON c.id = r.campaign_id
                    WHERE r.phone_number = ? OR r.original_phone_number LIKE ? OR r.name LIKE ?
                    ORDER BY r.id DESC LIMIT ?";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(1, $norm ?: $query);
            $stmt->bindValue(2, $wild);
            $stmt->bindValue(3, $wild);
            $stmt->bindValue(4, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('searchRecipientHistory error: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Dashboard summary metrics: today's & this month's stats.
     *
     * @return array{today_campaigns:int, today_sent:int, today_failed:int, month_campaigns:int, month_sent:int}
     */
    public static function getDashboardSummary(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $out = [
            'today_campaigns' => 0,
            'today_sent'      => 0,
            'today_failed'    => 0,
            'month_campaigns' => 0,
            'month_sent'      => 0,
        ];

        try {
            // Today stats
            $tStmt = $pdo->prepare(
                "SELECT COUNT(*) AS total_campaigns,
                        COALESCE(SUM(sent_count), 0) AS total_sent,
                        COALESCE(SUM(failed_count), 0) AS total_failed
                 FROM bulk_sms_campaigns
                 WHERE DATE(created_at) = ?"
            );
            $tStmt->execute([$today]);
            $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
            if ($tRow) {
                $out['today_campaigns'] = (int)$tRow['total_campaigns'];
                $out['today_sent'] = (int)$tRow['total_sent'];
                $out['today_failed'] = (int)$tRow['total_failed'];
            }

            // Month stats
            $mStmt = $pdo->prepare(
                "SELECT COUNT(*) AS total_campaigns,
                        COALESCE(SUM(sent_count), 0) AS total_sent
                 FROM bulk_sms_campaigns
                 WHERE created_at >= ?"
            );
            $mStmt->execute([$monthStart . ' 00:00:00']);
            $mRow = $mStmt->fetch(PDO::FETCH_ASSOC);
            if ($mRow) {
                $out['month_campaigns'] = (int)$mRow['total_campaigns'];
                $out['month_sent'] = (int)$mRow['total_sent'];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * Streams campaign recipient results as a secure CSV download.
     */
    public static function exportCampaignCsv(PDO $pdo, int $campaignId): void
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare("SELECT campaign_code FROM bulk_sms_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        $code = (string)($stmt->fetchColumn() ?: 'campaign_' . $campaignId);

        $filename = "Bulk_SMS_Report_{$code}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            return;
        }

        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['Phone', 'Name', 'Gateway', 'Status', 'Gateway Message ID', 'SMS Units', 'Sent Time', 'Delivered Time', 'Error Details']);

        $rStmt = $pdo->prepare("SELECT * FROM bulk_sms_recipients WHERE campaign_id = ? ORDER BY id ASC");
        $rStmt->execute([$campaignId]);

        while ($r = $rStmt->fetch(PDO::FETCH_ASSOC)) {
            $gw = (string)($r['gateway'] ?? '');
            $gwLabel = $gw !== '' ? SmsService::getGatewayLabel($gw) : '';
            fputcsv($output, [
                self::sanitizeCsvCell($r['phone_number']),
                self::sanitizeCsvCell($r['name']),
                self::sanitizeCsvCell($gwLabel ?: $gw),
                self::sanitizeCsvCell($r['status']),
                self::sanitizeCsvCell($r['gateway_message_id'] ?? ''),
                self::sanitizeCsvCell((string)$r['sms_units']),
                self::sanitizeCsvCell($r['sent_at'] ?? ''),
                self::sanitizeCsvCell($r['delivered_at'] ?? ''),
                self::sanitizeCsvCell($r['error_message'] ?? ''),
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Streams validation analysis records as a secure CSV download.
     */
    public static function exportValidationCsv(array $records, string $originalFilename = 'validation'): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalFilename, PATHINFO_FILENAME));
        $filename = "Validation_Report_{$safeName}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            return;
        }

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Row', 'Original Phone', 'Normalized Phone', 'Name', 'Validation Status', 'Matched Contact', 'Reason / Last Sent']);

        foreach ($records as $r) {
            $match = '';
            if (!empty($r['match_info'])) {
                $match = $r['match_info']['role'] . ' - ' . $r['match_info']['name'];
            }
            fputcsv($output, [
                self::sanitizeCsvCell((string)($r['row_number'] ?? '')),
                self::sanitizeCsvCell((string)($r['original_phone'] ?? '')),
                self::sanitizeCsvCell((string)($r['normalized_phone'] ?? '')),
                self::sanitizeCsvCell((string)($r['name'] ?? '')),
                self::sanitizeCsvCell((string)($r['status'] ?? '')),
                self::sanitizeCsvCell($match),
                self::sanitizeCsvCell((string)($r['reason'] ?? '')),
            ]);
        }

        fclose($output);
        exit;
    }
}
