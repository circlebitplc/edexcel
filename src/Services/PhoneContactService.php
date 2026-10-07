<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Phone Contact Management Service
 *
 * Handles WhatsApp contact imports, canonical phone normalization,
 * academic record associations, exact duplicate detection, server-side filtering,
 * recipient deduplication for SMS, and CSV export.
 */
final class PhoneContactService
{
    /**
     * Ensure database schema exists (self-healing).
     */
    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS phone_contacts (
                    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    normalized_phone    VARCHAR(20)     NOT NULL,
                    phone               VARCHAR(30)     NOT NULL,
                    name                VARCHAR(150)    NULL DEFAULT NULL,
                    school              VARCHAR(190)    NULL DEFAULT NULL,
                    sms_opt_out         TINYINT(1)      NOT NULL DEFAULT 0,
                    sms_status          ENUM('allowed', 'opted_out', 'blocked') NOT NULL DEFAULT 'allowed',
                    status              VARCHAR(20)     NOT NULL DEFAULT 'active',
                    first_imported_at   DATETIME        NULL,
                    last_imported_at    DATETIME        NULL,
                    last_source_group   VARCHAR(255)    NULL,
                    source_type         VARCHAR(50)     NOT NULL DEFAULT 'whatsapp_group',
                    name_conflict       VARCHAR(255)    NULL,
                    notes               TEXT            NULL,
                    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_phone_contacts_norm (normalized_phone),
                    KEY idx_pc_name (name),
                    KEY idx_pc_school (school),
                    KEY idx_pc_optout (sms_opt_out),
                    KEY idx_pc_sms_status (sms_status),
                    KEY idx_pc_status (status),
                    KEY idx_pc_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS phone_contact_records (
                    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    contact_id          BIGINT UNSIGNED NOT NULL,
                    exam_year           INT             NOT NULL,
                    exam_type           VARCHAR(50)     NOT NULL,
                    location            VARCHAR(100)    NOT NULL,
                    school              VARCHAR(190)    NOT NULL DEFAULT '',
                    source              VARCHAR(255)    NOT NULL DEFAULT 'whatsapp_csv',
                    source_type         VARCHAR(50)     NOT NULL DEFAULT 'whatsapp_group',
                    source_group        VARCHAR(255)    NULL DEFAULT NULL,
                    import_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_contact_academic_school (contact_id, exam_year, exam_type, location, school),
                    KEY idx_pcr_year (exam_year),
                    KEY idx_pcr_type (exam_type),
                    KEY idx_pcr_location (location),
                    KEY idx_pcr_school (school),
                    KEY idx_pcr_source_group (source_group),
                    KEY idx_pcr_contact_id (contact_id),
                    KEY idx_pcr_import_id (import_id),
                    CONSTRAINT fk_pcr_contact FOREIGN KEY (contact_id) REFERENCES phone_contacts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS phone_import_history (
                    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    filename                VARCHAR(255)    NOT NULL,
                    uploaded_by             INT             NULL,
                    source_group            VARCHAR(255)    NULL,
                    total_rows              INT             NOT NULL DEFAULT 0,
                    valid_rows              INT             NOT NULL DEFAULT 0,
                    invalid_rows            INT             NOT NULL DEFAULT 0,
                    new_contacts            INT             NOT NULL DEFAULT 0,
                    existing_contacts       INT             NOT NULL DEFAULT 0,
                    previous_contacts_count INT             NOT NULL DEFAULT 0,
                    previously_seen_count   INT             NOT NULL DEFAULT 0,
                    new_records             INT             NOT NULL DEFAULT 0,
                    duplicate_records       INT             NOT NULL DEFAULT 0,
                    error_csv_path          VARCHAR(255)    NULL,
                    error_summary           TEXT            NULL,
                    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_pih_created_at (created_at),
                    KEY idx_pih_uploaded_by (uploaded_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS teacher_sms_permissions (
                    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                    teacher_user_id         INT             NOT NULL,
                    teacher_id              INT             NULL,
                    sms_access              TINYINT(1)      NOT NULL DEFAULT 0,
                    gateway                 VARCHAR(50)     NOT NULL DEFAULT 'ipromo',
                    monthly_limit           INT             NOT NULL DEFAULT 0,
                    can_send_sms            TINYINT(1)      NOT NULL DEFAULT 0,
                    can_view_contacts       TINYINT(1)      NOT NULL DEFAULT 0,
                    can_select_contacts     TINYINT(1)      NOT NULL DEFAULT 0,
                    allowed_exam_years      VARCHAR(255)    NULL DEFAULT NULL,
                    allowed_exam_types      VARCHAR(255)    NULL DEFAULT NULL,
                    allowed_locations       VARCHAR(255)    NULL DEFAULT NULL,
                    allowed_whatsapp_groups TEXT            NULL DEFAULT NULL,
                    updated_by              INT             NULL,
                    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_tsp_user (teacher_user_id),
                    KEY idx_tsp_teacher (teacher_id),
                    KEY idx_tsp_sms_access (sms_access)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );

            // Self-healing schema alterations for existing databases
            try {
                $pdo->exec("UPDATE phone_contact_records SET school = '' WHERE school IS NULL");
                $pdo->exec("ALTER TABLE phone_contact_records MODIFY COLUMN school VARCHAR(190) NOT NULL DEFAULT ''");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE phone_contact_records DROP INDEX uq_contact_academic");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE phone_contact_records ADD UNIQUE KEY uq_contact_academic_school (contact_id, exam_year, exam_type, location, school)");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE phone_contact_records ADD COLUMN source_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp_group' AFTER source");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD COLUMN sms_status ENUM('allowed', 'opted_out', 'blocked') NOT NULL DEFAULT 'allowed' AFTER sms_opt_out");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD KEY idx_pc_sms_status (sms_status)");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD COLUMN first_imported_at DATETIME NULL AFTER status");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD COLUMN last_imported_at DATETIME NULL AFTER first_imported_at");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD COLUMN last_source_group VARCHAR(255) NULL AFTER last_imported_at");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD COLUMN source_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp_group' AFTER last_source_group");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_contacts ADD COLUMN name_conflict VARCHAR(255) NULL AFTER source_type");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE phone_import_history ADD COLUMN previous_contacts_count INT NOT NULL DEFAULT 0 AFTER existing_contacts");
            } catch (Throwable) {}
            try {
                $pdo->exec("ALTER TABLE phone_import_history ADD COLUMN previously_seen_count INT NOT NULL DEFAULT 0 AFTER previous_contacts_count");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE teacher_sms_permissions ADD COLUMN allowed_whatsapp_groups TEXT NULL AFTER allowed_locations");
            } catch (Throwable) {}

            try {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS teacher_sms_switch_audit (
                        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        admin_user_id   INT NOT NULL,
                        previous_status TINYINT(1) NOT NULL,
                        new_status      TINYINT(1) NOT NULL,
                        reason          TEXT NULL,
                        ip_address      VARCHAR(45) NULL,
                        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_switch_audit_admin (admin_user_id),
                        KEY idx_switch_audit_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            } catch (Throwable) {}

            try {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS phone_whatsapp_group_mappings (
                        id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        raw_group_name       VARCHAR(190) NOT NULL,
                        canonical_group_name VARCHAR(190) NOT NULL,
                        created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uq_raw_group (raw_group_name),
                        KEY idx_canonical_group (canonical_group_name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE phone_contact_records ADD COLUMN canonical_source_group VARCHAR(190) NULL AFTER source_group");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE bulk_sms_campaigns ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER campaign_code");
            } catch (Throwable) {}

            try {
                $pdo->exec("ALTER TABLE student_profiles ADD KEY idx_sp_whatsapp (whatsapp_number)");
            } catch (Throwable) {}

            // Default locations setting if not set
            $check = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'phone_contact_locations' LIMIT 1");
            $check->execute();
            if ($check->fetchColumn() === false) {
                $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('phone_contact_locations', 'Kandy,Kurunegala,Online')");
                $ins->execute();
            }

            // Global emergency switch setting
            $checkGlobal = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'teacher_sms_global_enabled' LIMIT 1");
            $checkGlobal->execute();
            if ($checkGlobal->fetchColumn() === false) {
                $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('teacher_sms_global_enabled', '1')");
            }

            // Duplicate campaign warning days setting
            $checkWarn = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'campaign_duplicate_warning_days' LIMIT 1");
            $checkWarn->execute();
            if ($checkWarn->fetchColumn() === false) {
                $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('campaign_duplicate_warning_days', '7')");
            }

            $done = true;
        } catch (Throwable $e) {
            error_log('PhoneContactService::ensureSchema error: ' . $e->getMessage());
        }
    }

    /**
     * Get allowed locations list from settings.
     *
     * @return string[]
     */
    public static function getAllowedLocations(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'phone_contact_locations' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false && trim((string)$val) !== '') {
                $list = array_map('trim', explode(',', (string)$val));
                $clean = array_values(array_filter($list, static fn($v) => $v !== ''));
                if ($clean !== []) {
                    return $clean;
                }
            }
        } catch (Throwable) {
        }
        return ['Kandy', 'Kurunegala', 'Online'];
    }

    /**
     * Save configurable allowed locations.
     *
     * @param string[] $locations
     */
    public static function saveAllowedLocations(PDO $pdo, array $locations): void
    {
        self::ensureSchema($pdo);
        $clean = [];
        foreach ($locations as $loc) {
            $loc = trim($loc);
            if ($loc !== '' && !in_array($loc, $clean, true)) {
                $clean[] = $loc;
            }
        }
        if ($clean === []) {
            $clean = ['Kandy', 'Kurunegala', 'Online'];
        }
        $val = implode(',', $clean);

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('phone_contact_locations', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$val, $val]);
    }

    /**
     * Check if a location is allowed (case-insensitive) and return canonical casing.
     * If invalid, returns null.
     */
    public static function matchAllowedLocation(string $loc, array $allowedLocations): ?string
    {
        $trimmed = trim($loc);
        if ($trimmed === '') {
            return null;
        }
        foreach ($allowedLocations as $allowed) {
            if (strcasecmp($trimmed, $allowed) === 0) {
                return $allowed;
            }
        }
        return null;
    }

    /**
     * Normalize a Sri Lankan mobile number into canonical 11-digit format: 947XXXXXXXX.
     * Returns empty string if invalid.
     */
    public static function normalizePhone(string $raw): string
    {
        return SmsService::normalizeSriLankanNumber($raw);
    }

    /**
     * Format a normalized number (947XXXXXXXX) into standard display (07XXXXXXXX).
     */
    public static function formatPhoneDisplay(string $normalized): string
    {
        if (preg_match('/^947\d{8}$/', $normalized)) {
            return '0' . substr($normalized, 2);
        }
        return $normalized;
    }

    /**
     * Sanitize string against CSV formula injection.
     */
    public static function sanitizeCsvField(mixed $value): string
    {
        $str = (string)($value ?? '');
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Parse and analyze a CSV file before importing.
     *
     * @return array<string,mixed>
     */
    public static function analyzeCsvFile(string $filePath, string $originalFilename, PDO $pdo, ?string $sourceGroup = null): array
    {
        self::ensureSchema($pdo);

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Cannot read uploaded CSV file.');
        }

        $sourceGroup = trim((string)$sourceGroup);
        if ($sourceGroup === '') {
            // Infer from filename, e.g. 2026_IGCSE_Kandy.csv -> 2026 IGCSE Kandy
            $base = pathinfo($originalFilename, PATHINFO_FILENAME);
            $sourceGroup = trim((string)preg_replace('/[_\-]+/', ' ', $base));
        }

        $allowedLocations = self::getAllowedLocations($pdo);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Failed to open uploaded CSV file.');
        }

        // Read first chunk to detect BOM and delimiter
        $sample = fread($handle, 4096);
        rewind($handle);

        // Strip UTF-8 BOM if present
        $bom = pack('H*', 'EFBBBF');
        $hasBom = str_starts_with($sample, $bom);
        if ($hasBom) {
            fseek($handle, 3);
        }

        // Detect delimiter
        $delimiters = [',', ';', "\t"];
        $delimiter = ',';
        $maxCount = 0;
        foreach ($delimiters as $d) {
            $count = substr_count(substr($sample, 0, 1000), $d);
            if ($count > $maxCount) {
                $maxCount = $count;
                $delimiter = $d;
            }
        }

        // Read header row
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || $header === null) {
            fclose($handle);
            throw new RuntimeException('Uploaded CSV file appears to be empty.');
        }

        // Normalize header columns
        $colMap = [];
        foreach ($header as $idx => $colName) {
            $normalizedCol = strtolower(trim((string)$colName));
            $normalizedCol = preg_replace('/[^a-z0-9_]/', '', $normalizedCol) ?? $normalizedCol;

            if (in_array($normalizedCol, ['phone_number', 'phone', 'mobile', 'contact_number', 'contact', 'telephone'], true)) {
                $colMap['phone'] = $idx;
            } elseif (in_array($normalizedCol, ['exam_year', 'year', 'examyear'], true)) {
                $colMap['exam_year'] = $idx;
            } elseif (in_array($normalizedCol, ['exam_type', 'exam', 'examtype', 'type'], true)) {
                $colMap['exam_type'] = $idx;
            } elseif (in_array($normalizedCol, ['location', 'city', 'branch', 'center', 'centre'], true)) {
                $colMap['location'] = $idx;
            } elseif (in_array($normalizedCol, ['name', 'full_name', 'student_name', 'contact_name'], true)) {
                $colMap['name'] = $idx;
            } elseif (in_array($normalizedCol, ['school', 'school_name', 'college'], true)) {
                $colMap['school'] = $idx;
            }
        }

        // Validate required headers
        $missingHeaders = [];
        if (!isset($colMap['phone'])) {
            $missingHeaders[] = 'phone_number';
        }
        if (!isset($colMap['exam_year'])) {
            $missingHeaders[] = 'exam_year';
        }
        if (!isset($colMap['exam_type'])) {
            $missingHeaders[] = 'exam_type';
        }
        if (!isset($colMap['location'])) {
            $missingHeaders[] = 'location';
        }

        if ($missingHeaders !== []) {
            fclose($handle);
            throw new RuntimeException('CSV header is missing required columns: ' . implode(', ', $missingHeaders) . '. Found columns: ' . implode(', ', $header));
        }

        $totalRows = 0;
        $validRows = 0;
        $invalidRows = 0;
        $namesAvailable = 0;
        $namesMissing = 0;
        $schoolsAvailable = 0;
        $schoolsMissing = 0;

        $invalidBreakdown = [
            'invalid_phone'   => 0,
            'invalid_location'=> 0,
            'invalid_year'    => 0,
            'invalid_type'    => 0,
            'missing_fields'  => 0,
        ];

        $validRecords = [];
        $invalidRowsList = [];
        $samplePreview = [];

        $lineNumber = 1; // 1 is header

        // Track intra-file uniqueness for academic records
        // Key: phone_norm . '|' . exam_year . '|' . exam_type . '|' . location
        $fileAcademicKeys = [];
        $fileUniquePhones = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            if ($row === [null] || empty(array_filter($row, static fn($v) => trim((string)$v) !== ''))) {
                continue; // Skip blank lines
            }
            $totalRows++;

            $rawPhone = trim((string)($row[$colMap['phone']] ?? ''));
            $rawYear  = trim((string)($row[$colMap['exam_year']] ?? ''));
            $rawType  = trim((string)($row[$colMap['exam_type']] ?? ''));
            $rawLoc   = trim((string)($row[$colMap['location']] ?? ''));
            $rawName  = isset($colMap['name']) ? trim((string)($row[$colMap['name']] ?? '')) : '';
            $rawSchool = isset($colMap['school']) ? trim((string)($row[$colMap['school']] ?? '')) : '';

            $rowErrors = [];

            // 1. Phone validation
            if ($rawPhone === '') {
                $rowErrors[] = 'Missing phone number';
                $invalidBreakdown['missing_fields']++;
            } else {
                $normPhone = self::normalizePhone($rawPhone);
                if ($normPhone === '') {
                    $rowErrors[] = "Invalid Phone Number: '{$rawPhone}' (must be a valid Sri Lankan mobile, e.g. 077XXXXXXX)";
                    $invalidBreakdown['invalid_phone']++;
                }
            }

            // 2. Exam year validation
            if ($rawYear === '') {
                $rowErrors[] = 'Missing exam year';
                $invalidBreakdown['missing_fields']++;
            } elseif (!preg_match('/^(20\d{2})$/', $rawYear)) {
                $rowErrors[] = "Invalid Exam Year: '{$rawYear}' (expected 4-digit year, e.g. 2026)";
                $invalidBreakdown['invalid_year']++;
            }

            // 3. Exam type validation
            if ($rawType === '') {
                $rowErrors[] = 'Missing exam type';
                $invalidBreakdown['missing_fields']++;
            } else {
                // Normalize e.g. "igcse" -> "IGCSE"
                $rawType = strtoupper($rawType);
            }

            // 4. Location validation against allowed list
            if ($rawLoc === '') {
                $rowErrors[] = 'Missing location';
                $invalidBreakdown['missing_fields']++;
            } else {
                $matchedLoc = self::matchAllowedLocation($rawLoc, $allowedLocations);
                if ($matchedLoc === null) {
                    $rowErrors[] = "Invalid Location: '{$rawLoc}' (Allowed: " . implode(', ', $allowedLocations) . ')';
                    $invalidBreakdown['invalid_location']++;
                } else {
                    $rawLoc = $matchedLoc;
                }
            }

            // Row validity check
            if ($rowErrors !== []) {
                $invalidRows++;
                $invalidRowsList[] = [
                    'line'         => $lineNumber,
                    'raw_phone'    => $rawPhone,
                    'raw_year'     => $rawYear,
                    'raw_type'     => $rawType,
                    'raw_location' => $rawLoc,
                    'raw_name'     => $rawName,
                    'raw_school'   => $rawSchool,
                    'errors'       => implode('; ', $rowErrors),
                ];
                continue;
            }

            // Valid row
            $validRows++;

            if ($rawName !== '') {
                $namesAvailable++;
            } else {
                $namesMissing++;
            }

            if ($rawSchool !== '') {
                $schoolsAvailable++;
            } else {
                $schoolsMissing++;
            }

            $normPhone = self::normalizePhone($rawPhone);
            $yearInt = (int)$rawYear;
            $typeStr = $rawType;
            $locStr = $rawLoc;
            $schoolStr = trim((string)$rawSchool);

            $academicKey = "{$normPhone}|{$yearInt}|{$typeStr}|{$locStr}|{$schoolStr}";
            $isIntraFileDuplicate = isset($fileAcademicKeys[$academicKey]);
            $fileAcademicKeys[$academicKey] = true;
            $fileUniquePhones[$normPhone] = true;

            $record = [
                'line'                    => $lineNumber,
                'phone'                   => $rawPhone,
                'normalized_phone'        => $normPhone,
                'exam_year'               => $yearInt,
                'exam_type'               => $typeStr,
                'location'                => $locStr,
                'name'                    => $rawName !== '' ? $rawName : null,
                'school'                  => $rawSchool !== '' ? $rawSchool : null,
                'source_group'            => $sourceGroup !== '' ? $sourceGroup : null,
                'is_intra_file_duplicate' => $isIntraFileDuplicate,
            ];

            $validRecords[] = $record;

            if (count($samplePreview) < 10) {
                $samplePreview[] = [
                    'line'      => $lineNumber,
                    'phone'     => $normPhone,
                    'display'   => self::formatPhoneDisplay($normPhone),
                    'year'      => $yearInt,
                    'type'      => $typeStr,
                    'location'  => $locStr,
                    'name'      => $rawName ?: 'Unknown',
                    'school'    => $rawSchool ?: 'Not Available',
                ];
            }
        }
        fclose($handle);

        // Pre-check DB state for accurate preview numbers
        $distinctPhones = array_keys($fileUniquePhones);
        $existingPhoneMap = [];
        $existingAcademicMap = [];

        if ($distinctPhones !== []) {
            $chunks = array_chunk($distinctPhones, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                // Find existing contacts
                $stmt = $pdo->prepare("SELECT id, normalized_phone FROM phone_contacts WHERE normalized_phone IN ($placeholders)");
                $stmt->execute($chunk);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingPhoneMap[(string)$r['normalized_phone']] = (int)$r['id'];
                }
            }

            // Find existing academic records for existing contacts (including school)
            if ($existingPhoneMap !== []) {
                $contactIds = array_values($existingPhoneMap);
                $idChunks = array_chunk($contactIds, 500);
                foreach ($idChunks as $idChunk) {
                    $placeholders = implode(',', array_fill(0, count($idChunk), '?'));
                    $stmt = $pdo->prepare("SELECT contact_id, exam_year, exam_type, location, school FROM phone_contact_records WHERE contact_id IN ($placeholders)");
                    $stmt->execute($idChunk);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $sch = trim((string)($r['school'] ?? ''));
                        $k = "{$r['contact_id']}|{$r['exam_year']}|{$r['exam_type']}|{$r['location']}|{$sch}";
                        $existingAcademicMap[$k] = true;
                    }
                }
            }
        }

        // Compute preview stats
        $newPhoneCount = 0;
        $existingPhoneCount = 0;
        $seenPhonesInFile = [];

        $newRecordsCount = 0;
        $duplicateRecordsCount = 0;
        $seenRecordsInFile = [];

        foreach ($validRecords as $rec) {
            $p = $rec['normalized_phone'];
            if (!isset($seenPhonesInFile[$p])) {
                $seenPhonesInFile[$p] = true;
                if (isset($existingPhoneMap[$p])) {
                    $existingPhoneCount++;
                } else {
                    $newPhoneCount++;
                }
            }

            $schVal = trim((string)($rec['school'] ?? ''));
            $recKey = "{$p}|{$rec['exam_year']}|{$rec['exam_type']}|{$rec['location']}|{$schVal}";
            if (isset($seenRecordsInFile[$recKey])) {
                // Exact duplicate within the file
                $duplicateRecordsCount++;
            } else {
                $seenRecordsInFile[$recKey] = true;
                // Check if already in DB
                if (isset($existingPhoneMap[$p])) {
                    $cId = $existingPhoneMap[$p];
                    $dbKey = "{$cId}|{$rec['exam_year']}|{$rec['exam_type']}|{$rec['location']}|{$schVal}";
                    if (isset($existingAcademicMap[$dbKey])) {
                        $duplicateRecordsCount++;
                    } else {
                        $newRecordsCount++;
                    }
                } else {
                    $newRecordsCount++;
                }
            }
        }

        // Previous import comparison metrics
        $previousContactsCount = 0;
        if ($sourceGroup !== '') {
            try {
                $stmtPrev = $pdo->prepare("SELECT COUNT(DISTINCT contact_id) FROM phone_contact_records WHERE source_group = ?");
                $stmtPrev->execute([$sourceGroup]);
                $previousContactsCount = (int)$stmtPrev->fetchColumn();
            } catch (Throwable) {}
        }
        $previouslySeenCount = $existingPhoneCount;

        return [
            'filename'                => $originalFilename,
            'source_group'            => $sourceGroup,
            'total_rows'              => $totalRows,
            'valid_rows'              => $validRows,
            'invalid_rows'            => $invalidRows,
            'new_phone_contacts'      => $newPhoneCount,
            'existing_phone_contacts' => $existingPhoneCount,
            'previous_contacts_count' => $previousContactsCount,
            'previously_seen_count'   => $previouslySeenCount,
            'new_academic_records'    => $newRecordsCount,
            'duplicate_records'       => $duplicateRecordsCount,
            'names_available'         => $namesAvailable,
            'names_missing'           => $namesMissing,
            'schools_available'       => $schoolsAvailable,
            'schools_missing'         => $schoolsMissing,
            'invalid_breakdown'       => $invalidBreakdown,
            'sample_preview'          => $samplePreview,
            'valid_records'           => $validRecords,
            'invalid_rows_list'       => $invalidRowsList,
        ];
    }

    /**
     * Execute the import using previously analyzed records.
     *
     * @param array<int,array<string,mixed>> $validRecords
     * @param array<int,array<string,mixed>> $invalidRowsList
     * @return array<string,mixed>
     */
    public static function executeImport(
        PDO $pdo,
        int $uploadedBy,
        string $filename,
        string $sourceGroup,
        array $validRecords,
        array $invalidRowsList
    ): array {
        self::ensureSchema($pdo);

        $pdo->beginTransaction();

        try {
            // Count previous contacts for this source group if specified
            $previousContactsCount = 0;
            if ($sourceGroup !== '') {
                try {
                    $stmtPrev = $pdo->prepare("SELECT COUNT(DISTINCT contact_id) FROM phone_contact_records WHERE source_group = ?");
                    $stmtPrev->execute([$sourceGroup]);
                    $previousContactsCount = (int)$stmtPrev->fetchColumn();
                } catch (Throwable) {}
            }

            // 1. Create import history record
            $stmtHist = $pdo->prepare(
                "INSERT INTO phone_import_history 
                (filename, uploaded_by, source_group, total_rows, valid_rows, invalid_rows, new_contacts, existing_contacts, previous_contacts_count, previously_seen_count, new_records, duplicate_records, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, 0, 0, 0, NOW())"
            );
            $totalRows = count($validRecords) + count($invalidRowsList);
            $stmtHist->execute([
                $filename,
                $uploadedBy > 0 ? $uploadedBy : null,
                $sourceGroup !== '' ? $sourceGroup : null,
                $totalRows,
                count($validRecords),
                count($invalidRowsList),
                $previousContactsCount,
            ]);
            $importId = (int)$pdo->lastInsertId();

            // 2. Prepare statements
            $stmtFindContact = $pdo->prepare("SELECT id, name, school, name_conflict FROM phone_contacts WHERE normalized_phone = ? LIMIT 1");
            $stmtInsertContact = $pdo->prepare(
                "INSERT INTO phone_contacts (normalized_phone, phone, name, school, sms_opt_out, sms_status, status, first_imported_at, last_imported_at, last_source_group, source_type, created_at)
                 VALUES (?, ?, ?, ?, 0, 'allowed', 'active', NOW(), NOW(), ?, 'whatsapp_group', NOW())"
            );
            $stmtUpdateContact = $pdo->prepare(
                "UPDATE phone_contacts 
                 SET name = COALESCE(?, name),
                     school = COALESCE(?, school),
                     name_conflict = COALESCE(?, name_conflict),
                     last_imported_at = NOW(),
                     last_source_group = ?,
                     first_imported_at = COALESCE(first_imported_at, created_at, NOW()),
                     updated_at = NOW()
                 WHERE id = ?"
            );

            $stmtCheckRecord = $pdo->prepare(
                "SELECT id FROM phone_contact_records WHERE contact_id = ? AND exam_year = ? AND exam_type = ? AND location = ? AND school = ? LIMIT 1"
            );
            $stmtInsertRecord = $pdo->prepare(
                "INSERT INTO phone_contact_records 
                (contact_id, exam_year, exam_type, location, school, source, source_type, source_group, canonical_source_group, import_id, created_at)
                VALUES (?, ?, ?, ?, ?, 'whatsapp_csv', 'whatsapp_group', ?, ?, ?, NOW())"
            );

            $newContacts = 0;
            $existingContacts = 0;
            $newRecords = 0;
            $duplicateRecords = 0;

            $contactCache = []; // normalized_phone => contact_id
            $academicCache = []; // "contact_id|year|type|loc|school" => true

            foreach ($validRecords as $rec) {
                $normPhone = (string)$rec['normalized_phone'];
                $contactId = 0;

                if (isset($contactCache[$normPhone])) {
                    $contactId = $contactCache[$normPhone];
                    $incomingName = trim((string)($rec['name'] ?? ''));
                    $incomingSchool = trim((string)($rec['school'] ?? ''));
                    if ($incomingName !== '' || $incomingSchool !== '') {
                        $stmtFindContact->execute([$normPhone]);
                        $existing = $stmtFindContact->fetch(PDO::FETCH_ASSOC);
                        if ($existing) {
                            $existingName = trim((string)($existing['name'] ?? ''));
                            $nameConflict = null;
                            $nameToUpdate = null;
                            if ($incomingName !== '') {
                                if ($existingName === '') {
                                    $nameToUpdate = $incomingName;
                                } elseif (strcasecmp($existingName, $incomingName) !== 0) {
                                    $nameConflict = "Conflict: Existing '{$existingName}' vs Imported '{$incomingName}' (" . date('Y-m-d') . ")";
                                }
                            }
                            $existingSchool = trim((string)($existing['school'] ?? ''));
                            $schoolToUpdate = null;
                            if ($incomingSchool !== '' && $existingSchool === '') {
                                $schoolToUpdate = $incomingSchool;
                            }
                            if ($nameToUpdate !== null || $schoolToUpdate !== null || $nameConflict !== null) {
                                $stmtUpdateContact->execute([
                                    $nameToUpdate,
                                    $schoolToUpdate,
                                    $nameConflict,
                                    $sourceGroup !== '' ? $sourceGroup : null,
                                    $contactId,
                                ]);
                            }
                        }
                    }
                } else {
                    $stmtFindContact->execute([$normPhone]);
                    $existing = $stmtFindContact->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $contactId = (int)$existing['id'];
                        $existingContacts++;

                        $existingName = trim((string)($existing['name'] ?? ''));
                        $incomingName = trim((string)($rec['name'] ?? ''));
                        $nameConflict = null;
                        $nameToUpdate = null;

                        if ($incomingName !== '') {
                            if ($existingName === '') {
                                $nameToUpdate = $incomingName;
                            } elseif (strcasecmp($existingName, $incomingName) !== 0) {
                                // Conflict: keep existing name, log conflict
                                $nameConflict = "Conflict: Existing '{$existingName}' vs Imported '{$incomingName}' (" . date('Y-m-d') . ")";
                            }
                        }

                        $existingSchool = trim((string)($existing['school'] ?? ''));
                        $incomingSchool = trim((string)($rec['school'] ?? ''));
                        $schoolToUpdate = null;
                        if ($incomingSchool !== '' && $existingSchool === '') {
                            $schoolToUpdate = $incomingSchool;
                        }

                        $stmtUpdateContact->execute([
                            $nameToUpdate,
                            $schoolToUpdate,
                            $nameConflict,
                            $sourceGroup !== '' ? $sourceGroup : null,
                            $contactId,
                        ]);
                    } else {
                        // Create brand new contact
                        $stmtInsertContact->execute([
                            $normPhone,
                            (string)$rec['phone'],
                            !empty($rec['name']) ? (string)$rec['name'] : null,
                            !empty($rec['school']) ? (string)$rec['school'] : null,
                            $sourceGroup !== '' ? $sourceGroup : null,
                        ]);
                        $contactId = (int)$pdo->lastInsertId();
                        $newContacts++;
                    }
                    $contactCache[$normPhone] = $contactId;
                }

                // Academic Record Insertion / Deduplication
                $year = (int)$rec['exam_year'];
                $type = (string)$rec['exam_type'];
                $loc = (string)$rec['location'];
                $school = trim((string)($rec['school'] ?? ''));
                $recGroup = !empty($rec['source_group']) ? (string)$rec['source_group'] : ($sourceGroup !== '' ? $sourceGroup : null);
                $canonGroup = $recGroup !== null ? self::resolveCanonicalGroup($pdo, $recGroup) : null;

                $cacheKey = "{$contactId}|{$year}|{$type}|{$loc}|{$school}";

                if (isset($academicCache[$cacheKey])) {
                    // Exact duplicate in this import run
                    $duplicateRecords++;
                    continue;
                }

                $stmtCheckRecord->execute([$contactId, $year, $type, $loc, $school]);
                if ($stmtCheckRecord->fetchColumn()) {
                    // Exact duplicate already in DB
                    $academicCache[$cacheKey] = true;
                    $duplicateRecords++;
                    continue;
                }

                // Insert new academic record
                $stmtInsertRecord->execute([
                    $contactId,
                    $year,
                    $type,
                    $loc,
                    $school,
                    $recGroup,
                    $canonGroup,
                    $importId,
                ]);
                $newRecords++;
                $academicCache[$cacheKey] = true;
            }

            // 3. Write error CSV if invalid rows exist
            $errorCsvPath = null;
            if ($invalidRowsList !== []) {
                $storageDir = dirname(__DIR__, 2) . '/storage/imports';
                if (!is_dir($storageDir)) {
                    @mkdir($storageDir, 0755, true);
                }
                $htPath = $storageDir . '/.htaccess';
                if (!file_exists($htPath)) {
                    @file_put_contents($htPath, "Require all denied\nDeny from all\n");
                }
                $safeToken = bin2hex(random_bytes(16));
                $safeName = 'errors_import_' . $importId . '_' . $safeToken . '.csv';
                $fullPath = $storageDir . '/' . $safeName;

                $fp = fopen($fullPath, 'w');
                if ($fp !== false) {
                    // Header
                    fputcsv($fp, ['Line', 'Raw Phone', 'Exam Year', 'Exam Type', 'Location', 'Name', 'School', 'Error Reason']);
                    foreach ($invalidRowsList as $errRow) {
                        fputcsv($fp, [
                            $errRow['line'] ?? '',
                            self::sanitizeCsvField($errRow['raw_phone'] ?? ''),
                            self::sanitizeCsvField($errRow['raw_year'] ?? ''),
                            self::sanitizeCsvField($errRow['raw_type'] ?? ''),
                            self::sanitizeCsvField($errRow['raw_location'] ?? ''),
                            self::sanitizeCsvField($errRow['raw_name'] ?? ''),
                            self::sanitizeCsvField($errRow['raw_school'] ?? ''),
                            $errRow['errors'] ?? '',
                        ]);
                    }
                    fclose($fp);
                    $errorCsvPath = 'storage/imports/' . $safeName;
                }
            }

            // 4. Update import history with actual final tallies
            $errorSummary = [
                'invalid_rows_count' => count($invalidRowsList),
            ];
            $stmtUpdateHist = $pdo->prepare(
                "UPDATE phone_import_history 
                 SET new_contacts = ?, existing_contacts = ?, previously_seen_count = ?, new_records = ?, duplicate_records = ?, error_csv_path = ?, error_summary = ?
                 WHERE id = ?"
            );
            $stmtUpdateHist->execute([
                $newContacts,
                $existingContacts,
                $existingContacts,
                $newRecords,
                $duplicateRecords,
                $errorCsvPath,
                json_encode($errorSummary, JSON_UNESCAPED_UNICODE),
                $importId,
            ]);

            $pdo->commit();

            return [
                'success'                 => true,
                'import_id'               => $importId,
                'filename'                => $filename,
                'source_group'            => $sourceGroup,
                'rows_processed'          => $totalRows,
                'valid_rows'              => count($validRecords),
                'new_contacts'            => $newContacts,
                'existing_contacts'       => $existingContacts,
                'previous_contacts_count' => $previousContactsCount,
                'previously_seen_count'   => $existingContacts,
                'new_records'             => $newRecords,
                'duplicate_records'       => $duplicateRecords,
                'invalid_rows'            => count($invalidRowsList),
                'error_csv_path'          => $errorCsvPath,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PhoneContactService::executeImport error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Query contacts with combined filtering, pagination, and total counters.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public static function getFilteredContacts(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        self::ensureSchema($pdo);

        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        // Overall search
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = "(pc.name LIKE ? OR pc.normalized_phone LIKE ? OR pc.phone LIKE ? OR pc.school LIKE ? OR pc.notes LIKE ? OR pcr.school LIKE ? OR pcr.source_group LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Exam Year
        $year = trim((string)($filters['exam_year'] ?? ''));
        if ($year !== '' && $year !== 'all') {
            $where[] = "pcr.exam_year = ?";
            $params[] = (int)$year;
        }

        // Exam Type
        $type = trim((string)($filters['exam_type'] ?? ''));
        if ($type !== '' && $type !== 'all') {
            $where[] = "pcr.exam_type = ?";
            $params[] = strtoupper($type);
        }

        // Location
        $loc = trim((string)($filters['location'] ?? ''));
        if ($loc !== '' && $loc !== 'all') {
            $where[] = "pcr.location = ?";
            $params[] = $loc;
        }

        // School
        $school = trim((string)($filters['school'] ?? ''));
        if ($school !== '' && $school !== 'all') {
            $where[] = "(pc.school LIKE ? OR pcr.school LIKE ?)";
            $params[] = '%' . $school . '%';
            $params[] = '%' . $school . '%';
        }

        // WhatsApp Group / Source
        $group = trim((string)($filters['source_group'] ?? ''));
        if ($group !== '' && $group !== 'all') {
            $where[] = "(pcr.source_group LIKE ? OR pcr.canonical_source_group LIKE ?)";
            $params[] = '%' . $group . '%';
            $params[] = '%' . $group . '%';
        }

        // SMS Opt-Out
        if (isset($filters['sms_opt_out']) && $filters['sms_opt_out'] !== '' && $filters['sms_opt_out'] !== 'all') {
            $where[] = "pc.sms_opt_out = ?";
            $params[] = (int)$filters['sms_opt_out'];
        }

        // SMS Status (allowed, opted_out, blocked)
        $smsStatus = trim((string)($filters['sms_status'] ?? ''));
        if ($smsStatus !== '' && $smsStatus !== 'all') {
            $where[] = "pc.sms_status = ?";
            $params[] = $smsStatus;
        }

        // Contact lifecycle status (active, archived)
        // Default normal contact list shows active contacts only (archived contacts excluded)
        $statusFilter = strtolower(trim((string)($filters['status'] ?? '')));
        if ($statusFilter === 'archived') {
            $where[] = "pc.status = 'archived'";
        } elseif ($statusFilter === 'all') {
            // Show all contacts including archived
        } else {
            $where[] = "pc.status != 'archived'";
        }

        $whereClause = implode(' AND ', $where);

        // 1. Get total contacts in entire database
        $totalAll = (int)$pdo->query("SELECT COUNT(*) FROM phone_contacts")->fetchColumn();

        // 2. Count distinct matching contacts
        $countSql = "SELECT COUNT(DISTINCT pc.id) 
                     FROM phone_contacts pc 
                     LEFT JOIN phone_contact_records pcr ON pcr.contact_id = pc.id 
                     WHERE $whereClause";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalFiltered = (int)$stmtCount->fetchColumn();

        // 3. Fetch paginated distinct contact IDs
        $idSql = "SELECT pc.id 
                  FROM phone_contacts pc 
                  LEFT JOIN phone_contact_records pcr ON pcr.contact_id = pc.id 
                  WHERE $whereClause 
                  GROUP BY pc.id 
                  ORDER BY pc.id DESC 
                  LIMIT $perPage OFFSET $offset";
        $stmtIds = $pdo->prepare($idSql);
        $stmtIds->execute($params);
        $contactIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $contacts = [];
        if ($contactIds !== []) {
            $inPlaceholders = implode(',', array_fill(0, count($contactIds), '?'));

            // Fetch full contact records
            $cSql = "SELECT id, normalized_phone, phone, name, school, sms_opt_out, sms_status, status, 
                            first_imported_at, last_imported_at, last_source_group, source_type, name_conflict, notes, created_at, updated_at
                     FROM phone_contacts 
                     WHERE id IN ($inPlaceholders) 
                     ORDER BY id DESC";
            $stmtC = $pdo->prepare($cSql);
            $stmtC->execute($contactIds);
            $contactRows = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Fetch attached academic records
            $rSql = "SELECT id, contact_id, exam_year, exam_type, location, school, source, source_type, source_group, created_at 
                     FROM phone_contact_records 
                     WHERE contact_id IN ($inPlaceholders) 
                     ORDER BY exam_year DESC, exam_type ASC";
            $stmtR = $pdo->prepare($rSql);
            $stmtR->execute($contactIds);
            $recordsByContact = [];
            while ($r = $stmtR->fetch(PDO::FETCH_ASSOC)) {
                $cId = (int)$r['contact_id'];
                $recordsByContact[$cId][] = $r;
            }

            foreach ($contactRows as $c) {
                $cId = (int)$c['id'];
                $c['academic_records'] = $recordsByContact[$cId] ?? [];

                // Aggregated display strings
                $years = [];
                $types = [];
                $locations = [];
                $groups = [];
                foreach ($c['academic_records'] as $rec) {
                    $years[] = $rec['exam_year'];
                    $types[] = $rec['exam_type'];
                    $locations[] = $rec['location'];
                    if (!empty($rec['source_group'])) {
                        $groups[] = $rec['source_group'];
                    }
                }
                $c['display_phone'] = self::formatPhoneDisplay((string)$c['normalized_phone']);
                $c['summary_years'] = implode(', ', array_unique($years));
                $c['summary_types'] = implode(', ', array_unique($types));
                $c['summary_locations'] = implode(', ', array_unique($locations));
                $c['summary_groups'] = implode(', ', array_unique($groups));

                $contacts[] = $c;
            }
        }

        return [
            'contacts'       => $contacts,
            'total_all'      => $totalAll,
            'total_filtered' => $totalFiltered,
            'page'           => $page,
            'per_page'       => $perPage,
            'total_pages'    => $totalFiltered > 0 ? (int)ceil($totalFiltered / $perPage) : 1,
        ];
    }

    /**
     * Get unique recipient phone numbers for SMS sending.
     * Guaranteed deduplication: exactly 1 entry per normalized phone number.
     * Strictly excludes blocked contacts and opted-out contacts.
     *
     * @param array<string,mixed> $filters
     * @param int[] $selectedContactIds
     * @return array<string,array{id:int,normalized_phone:string,display_phone:string,name:string,school:string}>
     */
    public static function getUniqueRecipients(
        PDO $pdo,
        array $filters = [],
        array $selectedContactIds = [],
        bool $excludeOptOut = true
    ): array {
        self::ensureSchema($pdo);

        $where = ['1=1'];
        $params = [];

        // STRICT SERVER-SIDE SECURITY: Blocked contacts are NEVER messaged
        $where[] = "pc.sms_status != 'blocked'";

        // Soft-delete: Archived contacts are NEVER messaged
        $where[] = "pc.status != 'archived'";

        if ($excludeOptOut) {
            $where[] = "(pc.sms_opt_out = 0 AND pc.sms_status != 'opted_out')";
        }

        if ($selectedContactIds !== []) {
            $cleanIds = array_values(array_filter(array_map('intval', $selectedContactIds), static fn($id) => $id > 0));
            if ($cleanIds !== []) {
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                $where[] = "pc.id IN ($placeholders)";
                $params = array_merge($params, $cleanIds);
            }
        } else {
            // Apply standard filters
            $search = trim((string)($filters['search'] ?? ''));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[] = "(pc.name LIKE ? OR pc.normalized_phone LIKE ? OR pc.phone LIKE ? OR pc.school LIKE ? OR pcr.school LIKE ? OR pcr.source_group LIKE ?)";
                $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
            }
            $year = trim((string)($filters['exam_year'] ?? ''));
            if ($year !== '' && $year !== 'all') {
                $where[] = "pcr.exam_year = ?";
                $params[] = (int)$year;
            }
            $type = trim((string)($filters['exam_type'] ?? ''));
            if ($type !== '' && $type !== 'all') {
                $where[] = "pcr.exam_type = ?";
                $params[] = strtoupper($type);
            }
            $loc = trim((string)($filters['location'] ?? ''));
            if ($loc !== '' && $loc !== 'all') {
                $where[] = "pcr.location = ?";
                $params[] = $loc;
            }
            $group = trim((string)($filters['source_group'] ?? ''));
            if ($group !== '' && $group !== 'all') {
                $where[] = "(pcr.source_group LIKE ? OR pcr.canonical_source_group LIKE ?)";
                $params[] = '%' . $group . '%';
                $params[] = '%' . $group . '%';
            }
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT DISTINCT pc.id, pc.normalized_phone, pc.phone, pc.name, pc.school
                FROM phone_contacts pc
                LEFT JOIN phone_contact_records pcr ON pcr.contact_id = pc.id
                WHERE $whereClause
                ORDER BY pc.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $unique = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $norm = (string)$row['normalized_phone'];
            if (!isset($unique[$norm])) {
                $unique[$norm] = [
                    'id'               => (int)$row['id'],
                    'normalized_phone' => $norm,
                    'display_phone'    => self::formatPhoneDisplay($norm),
                    'name'             => trim((string)($row['name'] ?? '')) ?: 'Student',
                    'school'           => trim((string)($row['school'] ?? '')),
                ];
            }
        }

        return $unique;
    }

    /**
     * Get detailed exclusion breakdown before sending SMS.
     *
     * @return array{total_selected:int, eligible:int, blocked:int, opted_out:int, archived:int}
     */
    public static function getRecipientExclusionBreakdown(PDO $pdo, array $filters = [], array $selectedContactIds = []): array
    {
        self::ensureSchema($pdo);

        $where = ['1=1'];
        $params = [];

        if ($selectedContactIds !== []) {
            $cleanIds = array_values(array_filter(array_map('intval', $selectedContactIds), static fn($id) => $id > 0));
            if ($cleanIds !== []) {
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                $where[] = "pc.id IN ($placeholders)";
                $params = array_merge($params, $cleanIds);
            }
        } else {
            $search = trim((string)($filters['search'] ?? ''));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[] = "(pc.name LIKE ? OR pc.normalized_phone LIKE ? OR pc.phone LIKE ? OR pc.school LIKE ? OR pcr.school LIKE ? OR pcr.source_group LIKE ?)";
                $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
            }
            $year = trim((string)($filters['exam_year'] ?? ''));
            if ($year !== '' && $year !== 'all') {
                $where[] = "pcr.exam_year = ?";
                $params[] = (int)$year;
            }
            $type = trim((string)($filters['exam_type'] ?? ''));
            if ($type !== '' && $type !== 'all') {
                $where[] = "pcr.exam_type = ?";
                $params[] = strtoupper($type);
            }
            $loc = trim((string)($filters['location'] ?? ''));
            if ($loc !== '' && $loc !== 'all') {
                $where[] = "pcr.location = ?";
                $params[] = $loc;
            }
            $group = trim((string)($filters['source_group'] ?? ''));
            if ($group !== '' && $group !== 'all') {
                $where[] = "(pcr.source_group LIKE ? OR pcr.canonical_source_group LIKE ?)";
                $params[] = '%' . $group . '%';
                $params[] = '%' . $group . '%';
            }
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT DISTINCT pc.id, pc.normalized_phone, pc.sms_opt_out, pc.sms_status, pc.status
                FROM phone_contacts pc
                LEFT JOIN phone_contact_records pcr ON pcr.contact_id = pc.id
                WHERE $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $total = 0;
        $seen = [];
        $blocked = 0;
        $optedOut = 0;
        $archived = 0;
        $eligible = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $norm = (string)$row['normalized_phone'];
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $total++;

            $isArchived = ($row['status'] ?? 'active') === 'archived';
            $isBlocked = ($row['sms_status'] ?? 'allowed') === 'blocked';
            $isOptOut = !empty($row['sms_opt_out']) || ($row['sms_status'] ?? 'allowed') === 'opted_out';

            if ($isArchived) {
                $archived++;
            } elseif ($isBlocked) {
                $blocked++;
            } elseif ($isOptOut) {
                $optedOut++;
            } else {
                $eligible++;
            }
        }

        return [
            'total_selected' => $total,
            'eligible'       => $eligible,
            'blocked'        => $blocked,
            'opted_out'      => $optedOut,
            'archived'       => $archived,
        ];
    }

    /**
     * Check if recipients have already received an SMS campaign within recent days.
     *
     * @param string[] $phoneNumbers
     * @return array{duplicate_count:int, days:int, sample_recipients:array}
     */
    public static function checkRecentCampaignDuplicates(PDO $pdo, array $phoneNumbers, int $days = 7): array
    {
        self::ensureSchema($pdo);
        $days = max(1, $days);

        if ($phoneNumbers === []) {
            return [
                'has_duplicates'    => false,
                'duplicate_count'   => 0,
                'days'              => $days,
                'sample_recipients' => [],
            ];
        }

        $duplicates = [];
        $chunks = array_chunk($phoneNumbers, 500);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            try {
                $sql = "SELECT bsr.phone_number, bsc.campaign_name, bsc.campaign_code, COALESCE(bsr.sent_at, bsr.created_at) as sent_time
                        FROM bulk_sms_recipients bsr
                        JOIN bulk_sms_campaigns bsc ON bsc.id = bsr.campaign_id
                        WHERE bsr.phone_number IN ($placeholders)
                          AND bsr.status IN ('SENT', 'DELIVERED', 'SENDING', 'PENDING')
                          AND COALESCE(bsr.sent_at, bsr.created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        ORDER BY sent_time DESC";
                $params = array_merge($chunk, [$days]);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $p = (string)$r['phone_number'];
                    if (!isset($duplicates[$p])) {
                        $duplicates[$p] = [
                            'phone'         => $p,
                            'campaign_name' => $r['campaign_name'] ?: $r['campaign_code'],
                            'sent_time'     => $r['sent_time'],
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        return [
            'has_duplicates'    => count($duplicates) > 0,
            'duplicate_count'   => count($duplicates),
            'days'              => $days,
            'sample_recipients' => array_slice(array_values($duplicates), 0, 10),
        ];
    }

    /**
     * Get statistics dashboard aggregated by WhatsApp group.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getWhatsAppGroupStatistics(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $sql = "SELECT 
                    pcr.source_group,
                    COUNT(DISTINCT pcr.contact_id) as total_contacts,
                    COUNT(pcr.id) as total_records,
                    MIN(pcr.created_at) as first_imported_at,
                    MAX(pcr.created_at) as last_imported_at,
                    GROUP_CONCAT(DISTINCT pcr.location ORDER BY pcr.location SEPARATOR ', ') as locations,
                    GROUP_CONCAT(DISTINCT pcr.exam_year ORDER BY pcr.exam_year DESC SEPARATOR ', ') as exam_years,
                    GROUP_CONCAT(DISTINCT pcr.exam_type ORDER BY pcr.exam_type SEPARATOR ', ') as exam_types
                FROM phone_contact_records pcr
                WHERE pcr.source_group IS NOT NULL AND pcr.source_group != ''
                GROUP BY pcr.source_group
                ORDER BY last_imported_at DESC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Build an interactive chronological timeline for a contact.
     *
     * @return array<int,array{event_type:string,title:string,description:string,timestamp:string,badge_class:string}>
     */
    public static function getContactTimeline(PDO $pdo, int $contactId): array
    {
        self::ensureSchema($pdo);

        $details = self::getContactDetails($pdo, $contactId);
        if (!$details) {
            return [];
        }

        $events = [];

        // 1. Initial registration
        $events[] = [
            'event_type'  => 'contact_created',
            'title'       => 'Contact Added',
            'description' => 'Contact registered in database' . (!empty($details['first_imported_at']) ? " (First imported: {$details['first_imported_at']})" : ''),
            'timestamp'   => $details['first_imported_at'] ?: $details['created_at'],
            'badge_class' => 'bg-info',
        ];

        // 2. Academic record associations
        foreach ($details['academic_records'] as $rec) {
            $schoolText = !empty($rec['school']) ? " (School: {$rec['school']})" : '';
            $groupText = !empty($rec['source_group']) ? " from WhatsApp group: {$rec['source_group']}" : '';
            $events[] = [
                'event_type'  => 'academic_record',
                'title'       => "Academic Record: {$rec['exam_year']} {$rec['exam_type']}",
                'description' => "Location: {$rec['location']}{$schoolText}{$groupText}",
                'timestamp'   => $rec['created_at'],
                'badge_class' => 'bg-primary',
            ];
        }

        // 3. Name conflict notice if present
        if (!empty($details['name_conflict'])) {
            $events[] = [
                'event_type'  => 'name_conflict',
                'title'       => 'Name Conflict Detected',
                'description' => (string)$details['name_conflict'],
                'timestamp'   => $details['updated_at'],
                'badge_class' => 'bg-warning text-dark',
            ];
        }

        // 4. SMS History
        foreach ($details['sms_history'] as $sms) {
            $status = strtoupper((string)($sms['status'] ?? ''));
            $badge = in_array($status, ['SENT', 'DELIVERED'], true) ? 'bg-success' : 'bg-secondary';
            $events[] = [
                'event_type'  => 'sms_sent',
                'title'       => 'SMS Message: ' . ($sms['reference'] ?: 'Direct'),
                'description' => "Status: {$status} | Gateway: {$sms['gateway']} | Message: " . mb_substr((string)$sms['message'], 0, 80) . '...',
                'timestamp'   => $sms['date'],
                'badge_class' => $badge,
            ];
        }

        // 5. Archived status
        if (($details['status'] ?? '') === 'archived') {
            $events[] = [
                'event_type'  => 'contact_archived',
                'title'       => 'Contact Archived',
                'description' => 'Contact is archived / soft-deleted and excluded from SMS messaging.',
                'timestamp'   => $details['updated_at'] ?: $details['created_at'],
                'badge_class' => 'bg-danger',
            ];
        }

        // 6. Student name update note if present
        if (!empty($details['notes']) && stripos((string)$details['notes'], 'Student name updated') !== false) {
            $events[] = [
                'event_type'  => 'student_name_updated',
                'title'       => 'Student Name Updated from Records',
                'description' => (string)$details['notes'],
                'timestamp'   => $details['updated_at'] ?: $details['created_at'],
                'badge_class' => 'bg-success',
            ];
        }

        // Sort timeline descending by timestamp
        usort($events, static fn($a, $b) => strcmp((string)$b['timestamp'], (string)$a['timestamp']));

        return $events;
    }

    /**
     * Send an admin test SMS with confirmation and test audit context.
     * Guaranteed to use SmsService::send with context 'admin_test' and never affect teacher quotas.
     *
     * @return array{success:bool, provider:string, message_id?:string, error?:string, response?:array<string,mixed>, skipped?:bool}
     */
    public static function sendAdminTestSms(PDO $pdo, string $phoneNumber, string $message, string $gateway, int $adminUserId): array
    {
        self::ensureSchema($pdo);

        $normPhone = self::normalizePhone($phoneNumber);
        if ($normPhone === '') {
            throw new RuntimeException("Invalid phone number format: '{$phoneNumber}'. Must be valid Sri Lankan mobile.");
        }

        if (trim($message) === '') {
            throw new RuntimeException('SMS message cannot be empty.');
        }

        $gateway = SmsService::normalizeGatewayIdentifier($gateway);
        if ($gateway === '') {
            $gateway = 'ipromo';
        }

        return SmsService::send($normPhone, $message, $pdo, 'admin_test', $adminUserId, $gateway);
    }

    /**
     * Soft delete / archive a contact. Excludes contact from normal SMS selection.
     */
    public static function archiveContact(PDO $pdo, int $contactId, int $adminUserId, ?string $reason = null): bool
    {
        self::ensureSchema($pdo);
        if ($contactId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE phone_contacts SET status = 'archived', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$contactId]);
    }

    /**
     * Restore an archived contact back to active status.
     */
    public static function restoreContact(PDO $pdo, int $contactId, int $adminUserId): bool
    {
        self::ensureSchema($pdo);
        if ($contactId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE phone_contacts SET status = 'active', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$contactId]);
    }

    /**
     * Permanently delete a single phone contact and its associated academic records.
     */
    public static function deleteContact(PDO $pdo, int $contactId, int $adminUserId): bool
    {
        self::ensureSchema($pdo);
        if ($contactId <= 0) {
            return false;
        }

        $pdo->beginTransaction();
        try {
            $stmtRec = $pdo->prepare("DELETE FROM phone_contact_records WHERE contact_id = ?");
            $stmtRec->execute([$contactId]);

            $stmt = $pdo->prepare("DELETE FROM phone_contacts WHERE id = ?");
            $res = $stmt->execute([$contactId]);

            $pdo->commit();
            return $res && $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Failed to delete contact: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete contacts in bulk, including all associated academic records.
     * Supports both explicit ID list and "all filtered" mode.
     *
     * @param int[] $contactIds
     * @param array<string, mixed> $filters
     * @return array{success:bool, deleted_count:int}
     */
    public static function deleteContactsBulk(PDO $pdo, array $contactIds, array $filters = [], bool $allFiltered = false, int $adminUserId = 0): array
    {
        self::ensureSchema($pdo);

        if ($allFiltered) {
            $filtered = self::getFilteredContacts($pdo, $filters, 1, 1000000);
            $contacts = $filtered['contacts'] ?? [];
            $contactIds = array_column($contacts, 'id');
        }

        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $contactIds), static fn($id) => $id > 0)));
        if ($cleanIds === []) {
            return ['success' => true, 'deleted_count' => 0];
        }

        $pdo->beginTransaction();
        $deletedCount = 0;
        try {
            $chunks = array_chunk($cleanIds, 500);
            foreach ($chunks as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));

                $stmtRec = $pdo->prepare("DELETE FROM phone_contact_records WHERE contact_id IN ($ph)");
                $stmtRec->execute($chunk);

                $stmt = $pdo->prepare("DELETE FROM phone_contacts WHERE id IN ($ph)");
                $stmt->execute($chunk);
                $deletedCount += $stmt->rowCount();
            }

            $pdo->commit();
            return ['success' => true, 'deleted_count' => $deletedCount];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Failed to bulk delete contacts: ' . $e->getMessage());
        }
    }

    /**
     * Resolve canonical group name from raw group name via phone_whatsapp_group_mappings.
     */
    public static function resolveCanonicalGroup(PDO $pdo, string $rawGroup): string
    {
        $rawGroup = trim($rawGroup);
        if ($rawGroup === '') {
            return '';
        }

        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->prepare("SELECT canonical_group_name FROM phone_whatsapp_group_mappings WHERE raw_group_name = ? LIMIT 1");
            $stmt->execute([$rawGroup]);
            $canon = $stmt->fetchColumn();
            if ($canon !== false && trim((string)$canon) !== '') {
                return trim((string)$canon);
            }
        } catch (Throwable) {}

        // Default: collapse consecutive whitespace
        return (string)preg_replace('/\s+/', ' ', $rawGroup);
    }

    /**
     * Set or update a canonical mapping for a raw WhatsApp group name.
     */
    public static function setGroupMapping(PDO $pdo, string $rawGroup, string $canonicalGroup): bool
    {
        self::ensureSchema($pdo);
        $rawGroup = trim($rawGroup);
        $canonicalGroup = trim($canonicalGroup);
        if ($rawGroup === '' || $canonicalGroup === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO phone_whatsapp_group_mappings (raw_group_name, canonical_group_name, created_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE canonical_group_name = VALUES(canonical_group_name)"
        );
        return $stmt->execute([$rawGroup, $canonicalGroup]);
    }

    /**
     * Get all raw to canonical WhatsApp group mappings.
     *
     * @return array<int,array{raw_group_name:string, canonical_group_name:string, created_at:string}>
     */
    public static function getAllGroupMappings(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        try {
            $stmt = $pdo->query("SELECT raw_group_name, canonical_group_name, created_at FROM phone_whatsapp_group_mappings ORDER BY raw_group_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get detailed contact data with all academic records and SMS history.
     */
    public static function getContactDetails(PDO $pdo, int $contactId): ?array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare("SELECT * FROM phone_contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            return null;
        }

        // Academic records
        $stmtR = $pdo->prepare("SELECT * FROM phone_contact_records WHERE contact_id = ? ORDER BY exam_year DESC, exam_type ASC, id DESC");
        $stmtR->execute([$contactId]);
        $contact['academic_records'] = $stmtR->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // SMS history for this normalized phone
        $norm = (string)$contact['normalized_phone'];
        $smsHistory = [];

        try {
            // Check bulk_sms_recipients
            $stmtBulk = $pdo->prepare(
                "SELECT bsr.id, bsr.campaign_id, bsr.message, bsr.status, bsr.gateway, bsr.sent_at, bsr.created_at, bsc.campaign_code, bsc.campaign_name
                 FROM bulk_sms_recipients bsr
                 LEFT JOIN bulk_sms_campaigns bsc ON bsc.id = bsr.campaign_id
                 WHERE bsr.phone_number = ?
                 ORDER BY bsr.id DESC
                 LIMIT 50"
            );
            $stmtBulk->execute([$norm]);
            while ($r = $stmtBulk->fetch(PDO::FETCH_ASSOC)) {
                $smsHistory[] = [
                    'type'         => 'bulk_campaign',
                    'reference'    => $r['campaign_code'] ?: ('Campaign #' . $r['campaign_id']),
                    'message'      => $r['message'],
                    'gateway'      => $r['gateway'] ?: 'iPromo',
                    'status'       => $r['status'],
                    'date'         => $r['sent_at'] ?: $r['created_at'],
                ];
            }
        } catch (Throwable) {
        }

        try {
            // Check central sms_logs
            $stmtLogs = $pdo->prepare(
                "SELECT id, recipient, message, provider, status, context, created_at 
                 FROM sms_logs 
                 WHERE recipient = ? OR recipient = ?
                 ORDER BY id DESC 
                 LIMIT 50"
            );
            $stmtLogs->execute([$norm, (string)$contact['phone']]);
            while ($r = $stmtLogs->fetch(PDO::FETCH_ASSOC)) {
                $smsHistory[] = [
                    'type'         => 'direct_log',
                    'reference'    => $r['context'] ?: 'Direct SMS',
                    'message'      => $r['message'],
                    'gateway'      => $r['provider'],
                    'status'       => $r['status'],
                    'date'         => $r['created_at'],
                ];
            }
        } catch (Throwable) {
        }

        // Sort SMS history by date descending
        usort($smsHistory, static fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));

        $contact['sms_history'] = $smsHistory;
        $contact['display_phone'] = self::formatPhoneDisplay($norm);

        return $contact;
    }

    /**
     * Update main contact details.
     */
    public static function updateContact(PDO $pdo, int $contactId, array $data): array
    {
        self::ensureSchema($pdo);

        $name = isset($data['name']) ? trim((string)$data['name']) : null;
        $school = isset($data['school']) ? trim((string)$data['school']) : null;
        $rawPhone = trim((string)($data['phone'] ?? ''));
        $optOut = !empty($data['sms_opt_out']) ? 1 : 0;
        $smsStatus = in_array(($data['sms_status'] ?? ''), ['allowed', 'opted_out', 'blocked'], true) 
            ? (string)$data['sms_status'] 
            : ($optOut ? 'opted_out' : 'allowed');
        if ($smsStatus === 'opted_out') {
            $optOut = 1;
        } elseif ($smsStatus === 'allowed' && empty($data['sms_opt_out'])) {
            $optOut = 0;
        }
        $status = in_array(($data['status'] ?? ''), ['active', 'inactive', 'archived'], true) ? (string)$data['status'] : 'active';
        $notes = isset($data['notes']) ? trim((string)$data['notes']) : null;

        if ($rawPhone === '') {
            throw new RuntimeException('Phone number cannot be empty.');
        }

        $normPhone = self::normalizePhone($rawPhone);
        if ($normPhone === '') {
            throw new RuntimeException('Invalid Sri Lankan mobile number format.');
        }

        // Check uniqueness of normalized phone against other contacts
        $chk = $pdo->prepare("SELECT id FROM phone_contacts WHERE normalized_phone = ? AND id != ? LIMIT 1");
        $chk->execute([$normPhone, $contactId]);
        if ($chk->fetchColumn()) {
            throw new RuntimeException('Another contact already exists with phone number ' . $normPhone . '.');
        }

        $stmt = $pdo->prepare(
            "UPDATE phone_contacts 
             SET normalized_phone = ?, phone = ?, name = ?, school = ?, sms_opt_out = ?, sms_status = ?, status = ?, notes = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $normPhone,
            $rawPhone,
            $name !== '' ? $name : null,
            $school !== '' ? $school : null,
            $optOut,
            $smsStatus,
            $status,
            $notes !== '' ? $notes : null,
            $contactId,
        ]);

        return ['success' => true];
    }

    /**
     * Add academic record to contact.
     */
    public static function addAcademicRecord(PDO $pdo, int $contactId, array $data): array
    {
        self::ensureSchema($pdo);

        $year = (int)($data['exam_year'] ?? 0);
        $type = strtoupper(trim((string)($data['exam_type'] ?? '')));
        $loc = trim((string)($data['location'] ?? ''));
        $school = trim((string)($data['school'] ?? ''));
        $sourceGroup = trim((string)($data['source_group'] ?? ''));

        if ($year < 2020 || $year > 2035) {
            throw new RuntimeException('Invalid exam year (expected 2020-2035).');
        }
        if ($type === '') {
            throw new RuntimeException('Exam type cannot be empty.');
        }
        $allowedLocations = self::getAllowedLocations($pdo);
        $matchedLoc = self::matchAllowedLocation($loc, $allowedLocations);
        if ($matchedLoc === null) {
            throw new RuntimeException("Invalid location. Allowed: " . implode(', ', $allowedLocations));
        }

        // Exact duplicate check (including school)
        $chk = $pdo->prepare("SELECT id FROM phone_contact_records WHERE contact_id = ? AND exam_year = ? AND exam_type = ? AND location = ? AND school = ? LIMIT 1");
        $chk->execute([$contactId, $year, $type, $matchedLoc, $school]);
        if ($chk->fetchColumn()) {
            $schoolDesc = $school !== '' ? " for school '{$school}'" : '';
            throw new RuntimeException("An academic record for {$year} {$type} in {$matchedLoc}{$schoolDesc} already exists for this contact.");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO phone_contact_records (contact_id, exam_year, exam_type, location, school, source, source_type, source_group, created_at)
             VALUES (?, ?, ?, ?, ?, 'manual_admin', 'manual', ?, NOW())"
        );
        $stmt->execute([
            $contactId,
            $year,
            $type,
            $matchedLoc,
            $school,
            $sourceGroup !== '' ? $sourceGroup : null,
        ]);

        return ['success' => true, 'id' => (int)$pdo->lastInsertId()];
    }

    /**
     * Update existing academic record.
     */
    public static function updateAcademicRecord(PDO $pdo, int $recordId, array $data): bool
    {
        self::ensureSchema($pdo);

        $stmtCur = $pdo->prepare("SELECT contact_id FROM phone_contact_records WHERE id = ? LIMIT 1");
        $stmtCur->execute([$recordId]);
        $contactId = (int)$stmtCur->fetchColumn();
        if ($contactId < 1) {
            throw new RuntimeException('Academic record not found.');
        }

        $year = (int)($data['exam_year'] ?? 0);
        $type = strtoupper(trim((string)($data['exam_type'] ?? '')));
        $loc = trim((string)($data['location'] ?? ''));
        $school = trim((string)($data['school'] ?? ''));
        $sourceGroup = trim((string)($data['source_group'] ?? ''));

        if ($year < 2020 || $year > 2035) {
            throw new RuntimeException('Invalid exam year (expected 2020-2035).');
        }
        if ($type === '') {
            throw new RuntimeException('Exam type cannot be empty.');
        }
        $allowedLocations = self::getAllowedLocations($pdo);
        $matchedLoc = self::matchAllowedLocation($loc, $allowedLocations);
        if ($matchedLoc === null) {
            throw new RuntimeException("Invalid location. Allowed: " . implode(', ', $allowedLocations));
        }

        // Duplicate check (including school)
        $chk = $pdo->prepare("SELECT id FROM phone_contact_records WHERE contact_id = ? AND exam_year = ? AND exam_type = ? AND location = ? AND school = ? AND id != ? LIMIT 1");
        $chk->execute([$contactId, $year, $type, $matchedLoc, $school, $recordId]);
        if ($chk->fetchColumn()) {
            throw new RuntimeException("Another academic record for {$year} {$type} in {$matchedLoc} with the same school already exists.");
        }

        $stmt = $pdo->prepare(
            "UPDATE phone_contact_records 
             SET exam_year = ?, exam_type = ?, location = ?, school = ?, source_group = ?, updated_at = NOW() 
             WHERE id = ?"
        );
        return $stmt->execute([
            $year,
            $type,
            $matchedLoc,
            $school,
            $sourceGroup !== '' ? $sourceGroup : null,
            $recordId,
        ]);
    }

    /**
     * Delete academic record.
     */
    public static function deleteAcademicRecord(PDO $pdo, int $recordId): bool
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare("DELETE FROM phone_contact_records WHERE id = ?");
        return $stmt->execute([$recordId]);
    }

    /**
     * Export contacts as CSV with formula injection protection.
     */
    public static function exportContactsCsv(PDO $pdo, array $filters = [], array $selectedIds = []): string
    {
        self::ensureSchema($pdo);

        $result = self::getFilteredContacts($pdo, $filters, 1, 100000);
        $contacts = $result['contacts'] ?? [];

        if ($selectedIds !== []) {
            $idSet = array_flip(array_map('intval', $selectedIds));
            $contacts = array_values(array_filter($contacts, static fn($c) => isset($idSet[(int)$c['id']])));
        }

        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }

        // CSV Header
        fputcsv($out, [
            'Contact ID',
            'Name',
            'Phone (Normalized)',
            'Phone (Display)',
            'School',
            'Exam Years',
            'Exam Types',
            'Locations',
            'WhatsApp Groups / Sources',
            'SMS Opt-Out',
            'Status',
            'Created Date',
        ]);

        foreach ($contacts as $c) {
            fputcsv($out, [
                $c['id'],
                self::sanitizeCsvField($c['name'] ?: 'Unknown'),
                self::sanitizeCsvField($c['normalized_phone']),
                self::sanitizeCsvField($c['display_phone']),
                self::sanitizeCsvField($c['school'] ?: 'Not Available'),
                self::sanitizeCsvField($c['summary_years']),
                self::sanitizeCsvField($c['summary_types']),
                self::sanitizeCsvField($c['summary_locations']),
                self::sanitizeCsvField($c['summary_groups']),
                !empty($c['sms_opt_out']) ? 'Yes' : 'No',
                ucfirst((string)$c['status']),
                $c['created_at'],
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }

    /**
     * Get distinct filter options currently present in database.
     *
     * @return array{years:int[], types:string[], locations:string[], groups:string[]}
     */
    public static function getDistinctFilterOptions(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        $years = [];
        $types = [];
        $locations = self::getAllowedLocations($pdo);
        $groups = [];

        try {
            $stmtY = $pdo->query("SELECT DISTINCT exam_year FROM phone_contact_records ORDER BY exam_year DESC");
            $years = array_map('intval', $stmtY->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($years === []) {
                $years = [2024, 2025, 2026, 2027, 2028];
            }

            $stmtT = $pdo->query("SELECT DISTINCT exam_type FROM phone_contact_records WHERE exam_type != '' ORDER BY exam_type ASC");
            $types = array_values(array_filter($stmtT->fetchAll(PDO::FETCH_COLUMN) ?: []));
            if ($types === []) {
                $types = ['IGCSE', 'IAL'];
            }

            $stmtG = $pdo->query("SELECT DISTINCT source_group FROM phone_contact_records WHERE source_group IS NOT NULL AND source_group != '' ORDER BY source_group ASC LIMIT 100");
            $groups = array_values(array_filter($stmtG->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (Throwable) {
        }

        return [
            'years'     => $years,
            'types'     => $types,
            'locations' => $locations,
            'groups'    => $groups,
        ];
    }

    /**
     * Match normalized phone numbers against active students in the database.
     * Checks student_profiles (whatsapp_number, parent_whatsapp) and users (username).
     *
     * @param string[] $normalizedPhones List of canonical normalized Sri Lankan numbers (947XXXXXXXX)
     * @return array<string, array<int, array{user_id:int, student_name:string, matched_field:string, matched_value:string, parent_name:string}>>
     */
    public static function matchStudentsByPhones(PDO $pdo, array $normalizedPhones): array
    {
        $uniqueNorms = array_values(array_unique(array_filter(array_map('trim', $normalizedPhones))));
        if ($uniqueNorms === []) {
            return [];
        }

        $termToNorm = [];
        $allTerms = [];

        foreach ($uniqueNorms as $norm) {
            if (strlen($norm) !== 11 || !str_starts_with($norm, '947')) {
                continue;
            }
            $local = '0' . substr($norm, 2); // 07XXXXXXXX
            $plus = '+' . $norm;             // +947XXXXXXXX
            $short = substr($norm, 2);        // 7XXXXXXXX

            $variants = [$norm, $local, $plus, $short];
            $prefix3 = substr($local, 0, 3);
            $rest7 = substr($local, 3);
            $variants[] = $prefix3 . ' ' . $rest7;
            $variants[] = $prefix3 . '-' . $rest7;
            if (strlen($rest7) === 7) {
                $variants[] = $prefix3 . ' ' . substr($rest7, 0, 3) . ' ' . substr($rest7, 3);
                $variants[] = $prefix3 . ' ' . substr($rest7, 0, 4) . ' ' . substr($rest7, 4);
                $variants[] = $prefix3 . '-' . substr($rest7, 0, 3) . '-' . substr($rest7, 3);
            }

            foreach ($variants as $v) {
                $trimmedV = trim($v);
                $termToNorm[$trimmedV] = $norm;
                $allTerms[$trimmedV] = true;
            }
        }

        $allTermsList = array_keys($allTerms);
        if ($allTermsList === []) {
            return [];
        }

        $matchesByNorm = [];
        foreach ($uniqueNorms as $norm) {
            $matchesByNorm[$norm] = [];
        }

        // Batch query in chunks of 300 terms
        $chunks = array_chunk($allTermsList, 300);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT u.id AS user_id, u.username, sp.full_name, sp.whatsapp_number, sp.parent_name, sp.parent_whatsapp
                    FROM users u
                    JOIN student_profiles sp ON sp.user_id = u.id
                    WHERE u.role = 'student'
                      AND u.deleted_at IS NULL
                      AND (
                          sp.whatsapp_number IN ($placeholders)
                          OR u.username IN ($placeholders)
                          OR sp.parent_whatsapp IN ($placeholders)
                      )";
            $params = array_merge($chunk, $chunk, $chunk);
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userId = (int)$row['user_id'];
                    $rawName = trim((string)($row['full_name'] ?? ''));
                    if ($rawName === '') {
                        $rawName = trim((string)($row['username'] ?? ''));
                    }
                    if ($rawName === '') {
                        $rawName = 'Student #' . $userId;
                    }

                    $candidateFields = [
                        'whatsapp_number' => trim((string)($row['whatsapp_number'] ?? '')),
                        'username'        => trim((string)($row['username'] ?? '')),
                        'parent_whatsapp' => trim((string)($row['parent_whatsapp'] ?? '')),
                    ];

                    foreach ($candidateFields as $fieldName => $fieldVal) {
                        if ($fieldVal === '') {
                            continue;
                        }

                        $norm = $termToNorm[$fieldVal] ?? null;
                        if ($norm === null) {
                            $candidateNorm = self::normalizePhone($fieldVal);
                            if (isset($matchesByNorm[$candidateNorm])) {
                                $norm = $candidateNorm;
                            }
                        }

                        if ($norm !== null && isset($matchesByNorm[$norm])) {
                            $alreadyHas = false;
                            foreach ($matchesByNorm[$norm] as $existingMatch) {
                                if ($existingMatch['user_id'] === $userId) {
                                    $alreadyHas = true;
                                    break;
                                }
                            }
                            if (!$alreadyHas) {
                                $matchesByNorm[$norm][] = [
                                    'user_id'       => $userId,
                                    'student_name'  => $rawName,
                                    'matched_field' => $fieldName,
                                    'matched_value' => $fieldVal,
                                    'parent_name'   => trim((string)($row['parent_name'] ?? '')),
                                ];
                            }
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        return $matchesByNorm;
    }

    /**
     * Analyze student matching against CSV records preview.
     * Evaluates priority logic, flags conflicts and multiple student matches, and determines
     * which names can be enriched or updated.
     *
     * @param array<int, array<string, mixed>> $validRecords
     * @return array{
     *     summary: array{
     *         total_preview_records: int,
     *         matched_students_count: int,
     *         new_matches_found: int,
     *         already_matching: int,
     *         conflicts_found: int,
     *         multiple_students: int,
     *         not_found: int,
     *         existing_contacts_to_update: int
     *     },
     *     sample_matches: array<int, array<string, mixed>>,
     *     proposed_existing_updates: array<int, array{contact_id:int, normalized_phone:string, display_phone:string, old_name:string, new_name:string, reason:string}>
     * }
     */
    public static function analyzeStudentMatchingForPreview(PDO $pdo, array $validRecords): array
    {
        self::ensureSchema($pdo);

        if ($validRecords === []) {
            return [
                'summary' => [
                    'total_preview_records'       => 0,
                    'matched_students_count'      => 0,
                    'new_matches_found'           => 0,
                    'already_matching'            => 0,
                    'conflicts_found'             => 0,
                    'multiple_students'           => 0,
                    'not_found'                   => 0,
                    'existing_contacts_to_update' => 0,
                ],
                'sample_matches'            => [],
                'proposed_existing_updates' => [],
            ];
        }

        $allNorms = [];
        foreach ($validRecords as $r) {
            $norm = (string)($r['normalized_phone'] ?? '');
            if ($norm !== '') {
                $allNorms[] = $norm;
            }
        }
        $uniqueNorms = array_values(array_unique($allNorms));

        // 1. Fetch matching student profiles
        $studentMatches = self::matchStudentsByPhones($pdo, $uniqueNorms);

        // 2. Query existing phone_contacts in database
        $existingContactsMap = [];
        $chunks = array_chunk($uniqueNorms, 500);
        foreach ($chunks as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("SELECT id, normalized_phone, phone, name, school, sms_status, status FROM phone_contacts WHERE normalized_phone IN ($ph)");
            $stmt->execute($chunk);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingContactsMap[(string)$row['normalized_phone']] = $row;
            }
        }

        $newMatchesFound = 0;
        $alreadyMatching = 0;
        $conflictsFound = 0;
        $multipleStudents = 0;
        $notFound = 0;
        $proposedExistingUpdates = [];
        $seenExistingUpdateIds = [];

        $sampleMatches = [];
        $sampleLimit = 50;

        foreach ($validRecords as $idx => $rec) {
            $norm = (string)($rec['normalized_phone'] ?? '');
            $csvName = trim((string)($rec['name'] ?? ''));
            $matches = $studentMatches[$norm] ?? [];
            $matchCount = count($matches);

            $matchStatus = 'Student Not Found';
            $matchedStudentName = null;
            $suggestedName = null;
            $badgeClass = 'bg-secondary';
            $conflictDetails = null;

            if ($matchCount === 0) {
                $matchStatus = 'Student Not Found';
                $badgeClass = 'bg-secondary';
                $notFound++;
            } elseif ($matchCount === 1) {
                $st = $matches[0];
                $matchedStudentName = $st['student_name'];

                if ($csvName === '') {
                    $matchStatus = 'Student Found';
                    $suggestedName = $matchedStudentName;
                    $badgeClass = 'bg-success';
                    $newMatchesFound++;
                } else {
                    if (strcasecmp($csvName, $matchedStudentName) === 0) {
                        $matchStatus = 'Already Matching';
                        $suggestedName = $matchedStudentName;
                        $badgeClass = 'bg-info text-dark';
                        $alreadyMatching++;
                    } else {
                        $matchStatus = 'Name Conflict';
                        $suggestedName = null;
                        $badgeClass = 'bg-warning text-dark';
                        $conflictDetails = "CSV: '{$csvName}' vs Student: '{$matchedStudentName}'";
                        $conflictsFound++;
                    }
                }
            } else {
                $allNames = array_column($matches, 'student_name');
                $matchStatus = 'Multiple Students Found';
                $matchedStudentName = implode(', ', $allNames);
                $suggestedName = null;
                $badgeClass = 'bg-danger';
                $conflictDetails = 'Shared phone by: ' . implode(', ', $allNames);
                $multipleStudents++;
            }

            // Check if existing contact in database can be updated
            if (isset($existingContactsMap[$norm]) && $matchCount === 1) {
                $ex = $existingContactsMap[$norm];
                $exId = (int)$ex['id'];
                $exName = trim((string)($ex['name'] ?? ''));
                $targetStudentName = $matches[0]['student_name'];

                if ($exName === '' && !isset($seenExistingUpdateIds[$exId])) {
                    $proposedExistingUpdates[] = [
                        'contact_id'       => $exId,
                        'normalized_phone' => $norm,
                        'display_phone'    => self::formatPhoneDisplay($norm),
                        'old_name'         => '',
                        'new_name'         => $targetStudentName,
                        'reason'           => 'Existing contact without name matched with active student record',
                    ];
                    $seenExistingUpdateIds[$exId] = true;
                }
            }

            if (count($sampleMatches) < $sampleLimit) {
                $sampleMatches[] = [
                    'line'             => (int)($rec['line'] ?? ($idx + 2)),
                    'phone'            => (string)($rec['phone'] ?? $norm),
                    'display'          => self::formatPhoneDisplay($norm),
                    'normalized_phone' => $norm,
                    'year'             => $rec['exam_year'] ?? '',
                    'type'             => $rec['exam_type'] ?? '',
                    'location'         => $rec['location'] ?? '',
                    'csv_name'         => $csvName,
                    'school'           => $rec['school'] ?? '',
                    'matched_student'  => $matchedStudentName,
                    'match_status'     => $matchStatus,
                    'badge_class'      => $badgeClass,
                    'suggested_name'   => $suggestedName,
                    'conflict_details' => $conflictDetails,
                ];
            }
        }

        $matchedTotal = $newMatchesFound + $alreadyMatching;

        return [
            'summary' => [
                'total_preview_records'       => count($validRecords),
                'matched_students_count'      => $matchedTotal,
                'new_matches_found'           => $newMatchesFound,
                'already_matching'            => $alreadyMatching,
                'conflicts_found'             => $conflictsFound,
                'multiple_students'           => $multipleStudents,
                'not_found'                   => $notFound,
                'existing_contacts_to_update' => count($proposedExistingUpdates),
            ],
            'sample_matches'            => $sampleMatches,
            'proposed_existing_updates' => $proposedExistingUpdates,
        ];
    }

    /**
     * Apply student name updates to existing phone_contacts records.
     * Guaranteed to only update name and audit log note.
     * Never changes academic records, exam years, exam types, schools, locations, or SMS statuses.
     *
     * @param array<int, array{contact_id:int, new_name:string}> $updates
     * @return array{success:bool, updated_count:int, errors:array<string>}
     */
    public static function applyStudentNameUpdates(PDO $pdo, int $adminUserId, array $updates): array
    {
        self::ensureSchema($pdo);

        if ($updates === []) {
            return ['success' => true, 'updated_count' => 0, 'errors' => []];
        }

        $pdo->beginTransaction();
        $updatedCount = 0;
        $errors = [];

        try {
            $selectStmt = $pdo->prepare("SELECT id, name, notes, sms_status, status FROM phone_contacts WHERE id = ? FOR UPDATE");
            $updateStmt = $pdo->prepare("UPDATE phone_contacts SET name = ?, notes = ?, updated_at = NOW() WHERE id = ?");

            foreach ($updates as $u) {
                $contactId = (int)($u['contact_id'] ?? 0);
                $newName = trim((string)($u['new_name'] ?? ''));

                if ($contactId <= 0 || $newName === '') {
                    continue;
                }

                $selectStmt->execute([$contactId]);
                $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $errors[] = "Contact #{$contactId} not found.";
                    continue;
                }

                $existingNotes = trim((string)($row['notes'] ?? ''));
                $auditNote = '[' . date('Y-m-d H:i:s') . "] Student name updated from system records: '{$newName}' by Admin #{$adminUserId}";
                $newNotes = $existingNotes !== '' ? ($existingNotes . "\n" . $auditNote) : $auditNote;

                $updateStmt->execute([$newName, $newNotes, $contactId]);
                $updatedCount++;
            }

            $pdo->commit();
            return [
                'success'       => true,
                'updated_count' => $updatedCount,
                'errors'        => $errors,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Failed to apply student name updates: ' . $e->getMessage());
        }
    }
}
