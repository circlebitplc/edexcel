<?php
declare(strict_types=1);

function campus_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function campus_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_campus_schema(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_fee_ledger (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id INT NOT NULL,
                class_id INT NULL,
                period_ym CHAR(7) NOT NULL,
                description VARCHAR(200) NOT NULL,
                amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
                amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
                due_date DATE NULL,
                status ENUM('due','partial','paid','waived') NOT NULL DEFAULT 'due',
                paid_at DATETIME NULL,
                marked_by INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_student_fee_period (student_id, class_id, period_ym),
                KEY idx_student_fee_student (student_id, status),
                KEY idx_student_fee_period (period_ym)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS parent_digest_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id INT NOT NULL,
                digest_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'sent',
                error TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_parent_digest (student_id, digest_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_waitlist (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id INT NOT NULL,
                class_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_student_waitlist (student_id, class_id),
                KEY idx_waitlist_class (class_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_exams (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(200) NOT NULL,
                class_id INT NULL,
                subject_id INT NULL,
                exam_date DATE NOT NULL,
                exam_time TIME NULL,
                location VARCHAR(200) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_student_exams_date (exam_date, subject_id, class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_login_otps (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                phone VARCHAR(20) NOT NULL,
                otp_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                verified_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_student_login_otps_phone (phone, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_progress (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id INT NOT NULL,
                class_id INT NULL,
                subject_id INT NULL,
                exam_id INT NULL,
                metric VARCHAR(100) NOT NULL DEFAULT 'Progress',
                score DECIMAL(7,2) NOT NULL DEFAULT 0,
                max_score DECIMAL(7,2) NOT NULL DEFAULT 100,
                recorded_at DATE NOT NULL,
                note VARCHAR(500) NULL,
                created_by INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_student_progress_student (student_id, subject_id, recorded_at),
                KEY idx_student_progress_class (class_id, recorded_at),
                KEY idx_student_progress_exam (exam_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("CREATE TABLE IF NOT EXISTS levels (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS subject_classes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            subject_id INT NOT NULL,
            class_id INT NOT NULL,
            UNIQUE KEY uq_subject_class (subject_id, class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS holidays (
            id INT PRIMARY KEY AUTO_INCREMENT,
            holiday_date DATE NOT NULL,
            name VARCHAR(150) NOT NULL,
            UNIQUE KEY uq_holiday_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_profiles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            full_name VARCHAR(150) NULL,
            email VARCHAR(150) NULL,
            whatsapp_number VARCHAR(32) NULL,
            whatsapp_verified_at DATETIME NULL,
            parent_name VARCHAR(120) NULL,
            parent_whatsapp VARCHAR(32) NULL,
            UNIQUE KEY uq_student_profiles_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_notifications (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT NULL,
            type VARCHAR(40) NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_teacher_notif (teacher_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT NULL,
            type VARCHAR(40) NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin_notif (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            username VARCHAR(100) NULL,
            action VARCHAR(80) NOT NULL,
            table_name VARCHAR(80) NULL,
            record_id INT NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS academic_years (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            starts_on DATE NULL,
            ends_on DATE NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS qualifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            code VARCHAR(40) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS subject_units (
            id INT PRIMARY KEY AUTO_INCREMENT,
            subject_id INT NOT NULL,
            code VARCHAR(40) NULL,
            name VARCHAR(150) NOT NULL,
            KEY idx_subject_units_subject (subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_documents (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            file_name VARCHAR(255) NULL,
            file_url VARCHAR(500) NOT NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_student_docs (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            event_date DATE NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            description TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_student_events_date (event_date, start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_outbox (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'general',
            attempts INT NOT NULL DEFAULT 0,
            next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_error TEXT NULL,
            sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_outbox_next (sent_at, next_attempt_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_message_log (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            channel VARCHAR(20) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            error_text VARCHAR(500) NULL,
            sent_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_staff_msg (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS class_leave_log (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            class_id INT NOT NULL,
            class_name VARCHAR(150) NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            reason VARCHAR(500) NOT NULL,
            initiated_by VARCHAR(20) NOT NULL,
            actor_user_id INT NULL,
            actor_name VARCHAR(150) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_leave_class (class_id, created_at),
            KEY idx_leave_student (student_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('Campus schema tables: ' . $e->getMessage());
    }

    $alters = [
        ['student_profiles', 'parent_name', 'VARCHAR(120) NULL'],
        ['student_profiles', 'parent_whatsapp', 'VARCHAR(32) NULL'],
        ['student_attendance', 'marked_by', 'INT NULL'],
        ['timetable', 'lesson_status', "VARCHAR(20) NOT NULL DEFAULT 'scheduled'"],
        ['timetable', 'cancel_reason', 'VARCHAR(500) NULL'],
        ['timetable', 'substitute_teacher_id', 'INT NULL'],
        ['timetable', 'cancelled_at', 'DATETIME NULL'],
        ['student_materials', 'file_name', 'VARCHAR(255) NULL'],
        ['student_classes', 'capacity', 'INT NULL'],
        ['student_exams', 'exam_type', "VARCHAR(20) NOT NULL DEFAULT 'exam'"],
        ['student_exams', 'end_time', 'TIME NULL'],
        ['student_exams', 'teacher_id', 'INT NULL'],
        ['student_exams', 'room_id', 'INT NULL'],
        ['student_progress', 'exam_id', 'INT NULL'],
        ['student_progress', 'created_by', 'INT NULL'],
        ['whatsapp_bot_contacts', 'opted_out', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['student_classes', 'level_id', 'INT NULL'],
        ['student_classes', 'academic_year_id', 'INT NULL'],
        ['student_classes', 'qualification_id', 'INT NULL'],
        ['student_enrollments', 'teacher_id', 'INT NULL'],
        ['student_profiles', 'phone_verified_via', "VARCHAR(20) NULL"],
        ['student_profiles', 'website', 'VARCHAR(255) NULL'],
        ['student_profiles', 'facebook', 'VARCHAR(255) NULL'],
        ['student_profiles', 'instagram', 'VARCHAR(255) NULL'],
        ['student_profiles', 'tiktok', 'VARCHAR(255) NULL'],
        ['student_profiles', 'youtube', 'VARCHAR(255) NULL'],
        ['student_profiles', 'linkedin', 'VARCHAR(255) NULL'],
        ['timetable', 'unregistered_present', 'INT NOT NULL DEFAULT 0'],
        ['users', 'last_login_at', 'DATETIME NULL'],
        ['users', 'student_login_count', 'INT NOT NULL DEFAULT 0'],
        ['users', 'google_review_prompt_shown_at', 'DATETIME NULL'],
        ['users', 'theme_preference', "VARCHAR(50) NOT NULL DEFAULT 'dark'"],
    ];

    try {
        $examService = new \Edexcel\Services\OfficialExamService($pdo);
        $examService->ensureSchema();
        $examService->seedIfEmpty();
    } catch (Throwable $e) {
        error_log('Official exam schema: ' . $e->getMessage());
    }

    $addedLastLogin = false;
    foreach ($alters as [$table, $column, $definition]) {
        try {
            if (!campus_column_exists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                if ($table === 'users' && $column === 'last_login_at') {
                    $addedLastLogin = true;
                }
            }
        } catch (Throwable $e) {
            error_log("Campus schema {$table}.{$column}: " . $e->getMessage());
        }
    }
    if ($addedLastLogin) {
        try {
            $pdo->exec("
                UPDATE users
                SET last_login_at = COALESCE(updated_at, created_at, NOW())
                WHERE last_login_at IS NULL
            ");
        } catch (Throwable $e) {
            error_log('Campus schema users.last_login_at backfill: ' . $e->getMessage());
        }
    }

    ensure_recordings_schema($pdo);
}

function ensure_recordings_schema(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $statements = [
        "CREATE TABLE IF NOT EXISTS class_recordings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            timetable_id INT NOT NULL,
            teacher_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NULL,
            status ENUM('draft','uploading','processing','ready','failed','deleted') NOT NULL DEFAULT 'draft',
            retention_status ENUM('active','archived','scheduled_for_deletion','deleted') NOT NULL DEFAULT 'active',
            duration_seconds INT NULL,
            thumbnail_url VARCHAR(500) NULL,
            scheduled_delete_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            INDEX idx_cr_timetable (timetable_id, status),
            INDEX idx_cr_teacher (teacher_id, status),
            INDEX idx_cr_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS class_recording_assets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            recording_id INT NOT NULL,
            bunny_video_id VARCHAR(64) NOT NULL,
            bunny_library_id VARCHAR(32) NOT NULL,
            bunny_collection_id VARCHAR(64) NULL,
            title VARCHAR(200) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            bunny_status INT NULL,
            processing_status VARCHAR(40) NULL,
            duration_seconds INT NULL,
            thumbnail_url VARCHAR(500) NULL,
            encode_progress INT NULL,
            uploaded_at DATETIME NULL,
            ready_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cra_bunny_video (bunny_video_id),
            INDEX idx_cra_recording (recording_id, sort_order),
            INDEX idx_cra_status (bunny_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS student_lesson_fees (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            timetable_id INT NOT NULL,
            recording_id INT NULL,
            amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'LKR',
            status ENUM('pending','partial','paid','waived','cancelled') NOT NULL DEFAULT 'pending',
            payment_id BIGINT UNSIGNED NULL,
            paid_at DATETIME NULL,
            waived TINYINT(1) NOT NULL DEFAULT 0,
            waived_by INT NULL,
            waived_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_slf_student_lesson (student_id, timetable_id),
            INDEX idx_slf_timetable (timetable_id, status),
            INDEX idx_slf_student (student_id, status),
            INDEX idx_slf_payment (payment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payment_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            timetable_id INT NOT NULL,
            recording_id INT NULL,
            lesson_fee_id BIGINT UNSIGNED NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'LKR',
            gateway VARCHAR(20) NOT NULL DEFAULT 'onepay',
            gateway_transaction_id VARCHAR(80) NULL,
            gateway_reference VARCHAR(80) NOT NULL,
            status ENUM('initiated','pending','paid','failed','cancelled','expired','refunded') NOT NULL DEFAULT 'initiated',
            gateway_response TEXT NULL,
            paid_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pt_reference (gateway_reference),
            UNIQUE KEY uq_pt_gateway_txn (gateway_transaction_id),
            INDEX idx_pt_student (student_id, status, created_at),
            INDEX idx_pt_timetable (timetable_id, status),
            INDEX idx_pt_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payment_transaction_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            transaction_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_pte_txn (transaction_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_video_library (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            bunny_video_id VARCHAR(64) NOT NULL,
            bunny_library_id VARCHAR(32) NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NULL,
            subject_id INT NULL,
            class_id INT NULL,
            tags VARCHAR(255) NULL,
            visibility ENUM('private','class_students','selected_students') NOT NULL DEFAULT 'private',
            status ENUM('draft','uploading','processing','ready','failed','deleted') NOT NULL DEFAULT 'draft',
            bunny_status INT NULL,
            duration_seconds INT NULL,
            thumbnail_url VARCHAR(500) NULL,
            uploaded_at DATETIME NULL,
            ready_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_tvl_bunny_video (bunny_video_id),
            INDEX idx_tvl_teacher (teacher_id, status),
            INDEX idx_tvl_class (class_id, visibility, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_video_library_students (
            video_id INT NOT NULL,
            student_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (video_id, student_id),
            INDEX idx_tvls_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS recording_access_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            recording_id INT NOT NULL,
            timetable_id INT NOT NULL,
            access_result VARCHAR(40) NOT NULL,
            payment_status VARCHAR(20) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_ral_student (student_id, created_at),
            INDEX idx_ral_recording (recording_id, created_at),
            INDEX idx_ral_result (access_result, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('Recordings schema: ' . $e->getMessage());
        }
    }

    $teacherBunnyAlters = [
        ['teachers', 'bunny_library_id', 'VARCHAR(32) NULL'],
        ['teachers', 'bunny_api_key', 'VARCHAR(255) NULL'],
        ['teachers', 'bunny_token_key', 'VARCHAR(255) NULL'],
        ['teachers', 'bunny_webhook_secret', 'VARCHAR(255) NULL'],
        ['teachers', 'bunny_cdn_hostname', 'VARCHAR(255) NULL'],
        ['teachers', 'bunny_library_name', 'VARCHAR(160) NULL'],
        ['teachers', 'bunny_pull_zone_id', 'VARCHAR(32) NULL'],
        ['teachers', 'bunny_library_created_at', 'DATETIME NULL'],
    ];
    foreach ($teacherBunnyAlters as [$table, $column, $definition]) {
        try {
            if (!campus_column_exists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            error_log("Recordings schema {$table}.{$column}: " . $e->getMessage());
        }
    }
    try {
        if (campus_column_exists($pdo, 'teachers', 'bunny_library_id') && !campus_index_exists($pdo, 'teachers', 'uq_teachers_bunny_library_id')) {
            $pdo->exec('ALTER TABLE teachers ADD UNIQUE KEY uq_teachers_bunny_library_id (bunny_library_id)');
        }
    } catch (Throwable $e) {
        error_log('Recordings schema teachers bunny library unique: ' . $e->getMessage());
    }

    try {
        if (!campus_column_exists($pdo, 'payment_transactions', 'collected_by')) {
            $pdo->exec('ALTER TABLE payment_transactions ADD COLUMN collected_by INT NULL');
        }
    } catch (Throwable $e) {
        error_log('Recordings schema payment_transactions.collected_by: ' . $e->getMessage());
    }

    try {
        if (!campus_column_exists($pdo, 'student_lesson_fees', 'monthly_credit')) {
            $pdo->exec('ALTER TABLE student_lesson_fees ADD COLUMN monthly_credit DECIMAL(10,2) NOT NULL DEFAULT 0');
        }
    } catch (Throwable $e) {
        error_log('Recordings schema student_lesson_fees.monthly_credit: ' . $e->getMessage());
    }

    try {
        if (!campus_column_exists($pdo, 'student_lesson_fees', 'force_unpaid')) {
            $pdo->exec('ALTER TABLE student_lesson_fees ADD COLUMN force_unpaid TINYINT(1) NOT NULL DEFAULT 0');
        }
    } catch (Throwable $e) {
        error_log('Recordings schema student_lesson_fees.force_unpaid: ' . $e->getMessage());
    }

    $bankAlters = [
        ['slip_path', 'VARCHAR(255) NULL'],
        ['slip_original_name', 'VARCHAR(255) NULL'],
        ['verified_by', 'INT NULL'],
        ['verified_at', 'DATETIME NULL'],
        ['payer_role', 'VARCHAR(20) NULL'],
        ['parent_id', 'INT NULL'],
        ['staff_note', 'VARCHAR(255) NULL'],
    ];
    foreach ($bankAlters as [$column, $definition]) {
        try {
            if (!campus_column_exists($pdo, 'payment_transactions', $column)) {
                $pdo->exec("ALTER TABLE payment_transactions ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            error_log("Recordings schema payment_transactions.{$column}: " . $e->getMessage());
        }
    }
}

function student_class_page_url(int $timetableId, string $from = ''): string
{
    $url = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/student/class.php?lesson=' . max(0, $timetableId);
    $from = strtolower(trim($from));
    if (in_array($from, ['timetable', 'overview', 'fees', 'recordings', 'classroom'], true)) {
        $url .= '&from=' . rawurlencode($from);
    }
    return $url;
}

function parent_class_page_url(int $timetableId, int $studentId): string
{
    return rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/')
        . '/parent/class.php?lesson=' . max(0, $timetableId)
        . '&student=' . max(0, $studentId);
}

function campus_normalize_phone(?string $phone): string
{
    if (function_exists('normalize_phone')) {
        return normalize_phone((string)$phone);
    }
    $phone = preg_replace('/\D+/', '', (string)$phone) ?? '';
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '94' . substr($phone, 1);
    }
    return $phone;
}

/**
 * @return array{via:string,whatsapp:bool,phone:bool}
 */
function student_verify_status(PDO $pdo, int $userId, string $phone = ''): array
{
    $via = '';
    $profileWa = false;
    try {
        if (campus_column_exists($pdo, 'student_profiles', 'phone_verified_via')) {
            $stmt = $pdo->prepare('SELECT phone_verified_via, whatsapp_verified_at FROM student_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $via = strtolower(trim((string)($row['phone_verified_via'] ?? '')));
            $profileWa = !empty($row['whatsapp_verified_at']);
        } else {
            $stmt = $pdo->prepare('SELECT whatsapp_verified_at FROM student_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $profileWa = !empty($stmt->fetchColumn());
        }
    } catch (Throwable $e) {
        $via = '';
        $profileWa = false;
    }

    $contactWa = false;
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if ($phone !== '') {
        try {
            $stmt = $pdo->prepare('SELECT verified_at FROM whatsapp_bot_contacts WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
            $contactWa = !empty($stmt->fetchColumn());
        } catch (Throwable $e) {
            $contactWa = false;
        }
    }

    $whatsapp = $contactWa || ($via !== 'sms' && $profileWa);
    $phoneOk = $via === 'sms' || $whatsapp || $profileWa;

    return [
        'via' => $via,
        'whatsapp' => $whatsapp,
        'phone' => $phoneOk,
    ];
}

function campus_student_contacts(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            sp.full_name,
            sp.parent_name,
            sp.parent_whatsapp
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'id' => $studentId,
        'name' => trim((string)($row['full_name'] ?? '')) ?: (string)($row['username'] ?? 'Student'),
        'student_phone' => campus_normalize_phone((string)($row['username'] ?? '')),
        'parent_name' => trim((string)($row['parent_name'] ?? '')),
        'parent_phone' => campus_normalize_phone((string)($row['parent_whatsapp'] ?? '')),
    ];
}

function campus_notify_phones(PDO $pdo, int $studentId, string $message, string $event = 'CAMPUS'): int
{
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/evolution.php';
    require_once __DIR__ . '/whatsapp_gateway.php';

    $contacts = campus_student_contacts($pdo, $studentId);
    $phones = array_values(array_unique(array_filter([
        $contacts['student_phone'],
        $contacts['parent_phone'],
    ])));

    if ($phones === []) {
        return 0;
    }

    $sent = 0;
    try {
        $api = whatsapp_sender($pdo);
        foreach ($phones as $phone) {
            try {
                $api->sendText($phone, $message);
                $stmt = $pdo->prepare("
                    INSERT INTO whatsapp_bot_messages (phone, direction, message, event_name)
                    VALUES (?, 'outbound', ?, ?)
                ");
                $stmt->execute([$phone, $message, $event]);
                $sent++;
            } catch (Throwable $e) {
                error_log('Campus WhatsApp send failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Campus WhatsApp unavailable: ' . $e->getMessage());
    }

    return $sent;
}

function campus_notify_digest(PDO $pdo, int $studentId, string $fullMessage, string $studentMessage): int
{
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/evolution.php';
    require_once __DIR__ . '/whatsapp_gateway.php';

    $contacts = campus_student_contacts($pdo, $studentId);
    $sent = 0;

    try {
        $api = whatsapp_sender($pdo);
        $targets = [];
        if ($contacts['parent_phone'] !== '') {
            $targets[] = [$contacts['parent_phone'], $fullMessage];
        }
        if (
            $contacts['student_phone'] !== ''
            && $contacts['student_phone'] !== $contacts['parent_phone']
        ) {
            $targets[] = [
                $contacts['student_phone'],
                $contacts['parent_phone'] !== '' ? $studentMessage : $fullMessage,
            ];
        }

        foreach ($targets as [$phone, $text]) {
            try {
                $api->sendText($phone, $text);
                $stmt = $pdo->prepare("
                    INSERT INTO whatsapp_bot_messages (phone, direction, message, event_name)
                    VALUES (?, 'outbound', ?, 'PARENT_DIGEST')
                ");
                $stmt->execute([$phone, $text]);
                $sent++;
            } catch (Throwable $e) {
                error_log('Campus digest send failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Campus WhatsApp unavailable: ' . $e->getMessage());
    }

    return $sent;
}

function campus_notify_students(PDO $pdo, array $studentIds, string $message, string $event = 'CAMPUS'): void
{
    $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
    foreach ($studentIds as $studentId) {
        if ($studentId > 0) {
            campus_notify_phones($pdo, $studentId, $message, $event);
        }
    }

    // Mirror important events into the student portal bell (WhatsApp stays primary).
    $portal = campus_portal_event_payload($event, $message);
    if ($portal !== null) {
        campus_portal_notify(
            $pdo,
            $studentIds,
            $portal['title'],
            $portal['message'],
            $portal['link'],
            $portal['type']
        );
    }
}

/**
 * Map campus WhatsApp events to in-app notification fields.
 *
 * @return array{title:string,message:string,link:string,type:string}|null
 */
function campus_portal_event_payload(string $event, string $message): ?array
{
    $event = strtoupper(trim($event));
    $plain = trim(preg_replace('/\*([^*]+)\*/', '$1', strip_tags($message)) ?? '');
    $plain = preg_replace("/\n{3,}/", "\n\n", $plain) ?? $plain;
    $plain = mb_substr($plain, 0, 500);

    $map = [
        'CLASS_CANCELLED' => ['title' => 'Class cancelled', 'link' => 'dashboard.php?tab=overview', 'type' => 'danger'],
        'CLASS_SUBSTITUTE' => ['title' => 'Substitute teacher', 'link' => 'dashboard.php?tab=overview', 'type' => 'warning'],
        'HOMEWORK' => ['title' => 'New homework / paper', 'link' => 'dashboard.php?tab=services', 'type' => 'info'],
        'FEE_DUE' => ['title' => 'Fee reminder', 'link' => 'dashboard.php?tab=fees', 'type' => 'payment'],
        'CLASS_REMINDER' => ['title' => 'Class reminder', 'link' => 'dashboard.php?tab=overview', 'type' => 'class'],
        'RECORDING' => ['title' => 'Recording ready', 'link' => 'dashboard.php?tab=recordings', 'type' => 'success'],
        'WAITLIST' => ['title' => 'Waitlist update', 'link' => 'dashboard.php?tab=join', 'type' => 'info'],
        'HOMEWORK_MARKED' => ['title' => 'Homework reviewed', 'link' => 'dashboard.php?tab=services', 'type' => 'success'],
    ];

    if (!isset($map[$event])) {
        return null;
    }

    return [
        'title' => $map[$event]['title'],
        'message' => $plain !== '' ? $plain : $map[$event]['title'],
        'link' => $map[$event]['link'],
        'type' => $map[$event]['type'],
    ];
}

function campus_class_student_ids(PDO $pdo, int $classId): array
{
    $stmt = $pdo->prepare("SELECT student_id FROM student_enrollments WHERE class_id = ?");
    $stmt->execute([$classId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

/**
 * @return list<array{id:int,name:string}>
 */
function campus_staff_classes(PDO $pdo, bool $isAdmin, int $teacherId): array
{
    if ($isAdmin) {
        return $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    if ($teacherId < 1) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name
        FROM student_classes c
        WHERE c.deleted_at IS NULL
          AND (
            EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.class_id = c.id
                  AND tt.teacher_id = ?
                  AND tt.deleted_at IS NULL
            )
            OR EXISTS (
                SELECT 1 FROM subject_classes sc
                JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
                WHERE sc.class_id = c.id AND ts.teacher_id = ?
            )
          )
        ORDER BY c.name
    ");
    $stmt->execute([$teacherId, $teacherId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function campus_staff_can_access_class(PDO $pdo, int $classId, bool $isAdmin, int $teacherId): bool
{
    if ($classId < 1) {
        return false;
    }
    foreach (campus_staff_classes($pdo, $isAdmin, $teacherId) as $row) {
        if ((int)$row['id'] === $classId) {
            return true;
        }
    }
    return false;
}

function campus_staff_can_access_student(PDO $pdo, int $studentId, bool $isAdmin, int $teacherId): bool
{
    if ($studentId < 1) {
        return false;
    }
    if ($isAdmin) {
        return true;
    }
    if ($teacherId < 1) {
        return false;
    }
    $classes = campus_staff_classes($pdo, false, $teacherId);
    $ids = [];
    foreach ($classes as $row) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid > 0) {
            $ids[] = $cid;
        }
    }
    if ($ids === []) {
        return false;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT 1 FROM student_enrollments WHERE student_id = ? AND class_id IN ($in) LIMIT 1");
    $stmt->execute(array_merge([$studentId], $ids));
    return (bool)$stmt->fetchColumn();
}

/**
 * Direct student-id lookup. Teachers only receive students already in their classes.
 */
function campus_staff_may_lookup_student(PDO $pdo, int $studentId, bool $isAdmin, int $teacherId): bool
{
    if ($studentId < 1) {
        return false;
    }
    if ($isAdmin) {
        return true;
    }
    return campus_staff_can_access_student($pdo, $studentId, false, $teacherId);
}

/**
 * Walk-in search is limited to a class the staff member can manage.
 */
function campus_staff_may_search_students(PDO $pdo, bool $isAdmin, int $teacherId, int $classId): bool
{
    if ($isAdmin) {
        return true;
    }
    return $classId > 0 && campus_staff_can_access_class($pdo, $classId, false, $teacherId);
}

function campus_lookup_query_is_exact(string $query): bool
{
    $query = trim($query);
    if ($query !== '' && filter_var($query, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    return function_exists('campus_lk_whatsapp') && campus_lk_whatsapp($query) !== '';
}

/**
 * @return list<array{id:int,name:string}>
 */
function campus_staff_students(PDO $pdo, bool $isAdmin, int $teacherId): array
{
    if ($isAdmin) {
        return $pdo->query("
            SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.role = 'student' AND u.deleted_at IS NULL
            ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $classes = campus_staff_classes($pdo, false, $teacherId);
    $ids = [];
    foreach ($classes as $row) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid > 0) {
            $ids[] = $cid;
        }
    }
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) AS name
        FROM student_enrollments se
        JOIN users u ON u.id = se.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE se.class_id IN ($in)
          AND u.role = 'student'
          AND u.deleted_at IS NULL
        ORDER BY name
    ");
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function campus_staff_can_mark_lesson(array $entry, bool $isAdmin, int $teacherId): bool
{
    if ($isAdmin) {
        return true;
    }
    if ($teacherId < 1) {
        return false;
    }
    if ((int)($entry['teacher_id'] ?? 0) === $teacherId) {
        return true;
    }
    $sub = (int)($entry['substitute_teacher_id'] ?? 0);
    return $sub > 0 && $sub === $teacherId;
}

function campus_lk_whatsapp(string $raw): string
{
    $phone = campus_format_wa_phone($raw);
    if (!preg_match('/^947\d{8}$/', $phone)) {
        return '';
    }
    return $phone;
}

/**
 * Search students by Google login email, name, or phone number.
 *
 * @return list<array{
 *     id: int,
 *     full_name: string,
 *     google_email: string,
 *     whatsapp: string,
 *     parent_name: string,
 *     parent_whatsapp: string,
 *     already_enrolled: bool
 * }>
 */
function campus_search_students(PDO $pdo, string $query, int $limit = 15, int $classId = 0): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $term = '%' . $query . '%';
    $digits = preg_replace('/\D+/', '', $query) ?? '';

    $phoneVariants = [];
    if (strlen($digits) >= 3) {
        $phoneVariants[] = '%' . $digits . '%';
    }
    $lkPhone = campus_lk_whatsapp($query);
    if ($lkPhone !== '') {
        $phoneVariants[] = '%' . $lkPhone . '%';
        if (strlen($lkPhone) === 11 && str_starts_with($lkPhone, '94')) {
            $phoneVariants[] = '%0' . substr($lkPhone, 2) . '%';
        }
    }
    $phoneVariants = array_values(array_unique($phoneVariants));

    $whereClauses = [
        "u.google_email LIKE ?",
        "sp.email LIKE ?",
        "sp.full_name LIKE ?",
        "u.username LIKE ?",
        "sp.whatsapp_number LIKE ?"
    ];
    $params = [$term, $term, $term, $term, $term];

    foreach ($phoneVariants as $pv) {
        $whereClauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(sp.whatsapp_number, ''), '+', ''), ' ', ''), '-', ''), '.', '') LIKE ?";
        $params[] = $pv;
        $whereClauses[] = "u.username LIKE ?";
        $params[] = $pv;
    }

    $enrolledSelect = "0 AS already_enrolled";
    $enrolledParam = [];
    if ($classId > 0) {
        $enrolledSelect = "EXISTS (SELECT 1 FROM student_enrollments se WHERE se.student_id = u.id AND se.class_id = ?) AS already_enrolled";
        $enrolledParam[] = $classId;
    }

    $whereSql = implode(' OR ', $whereClauses);
    $sql = "
        SELECT
            u.id,
            COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS full_name,
            COALESCE(NULLIF(TRIM(u.google_email), ''), NULLIF(TRIM(sp.email), ''), '') AS google_email,
            COALESCE(NULLIF(TRIM(sp.whatsapp_number), ''), u.username, '') AS whatsapp_raw,
            COALESCE(sp.parent_name, '') AS parent_name,
            COALESCE(sp.parent_whatsapp, '') AS parent_whatsapp,
            $enrolledSelect
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.deleted_at IS NULL
          AND u.role = 'student'
          AND ($whereSql)
        ORDER BY
            CASE
                WHEN LOWER(TRIM(COALESCE(u.google_email, ''))) = LOWER(?) THEN 0
                WHEN LOWER(TRIM(COALESCE(sp.full_name, ''))) = LOWER(?) THEN 1
                WHEN LOWER(TRIM(COALESCE(sp.full_name, ''))) LIKE LOWER(?) THEN 2
                ELSE 3
            END,
            u.id DESC
        LIMIT " . (int)$limit;

    $orderParams = [trim($query), trim($query), trim($query) . '%'];
    $execParams = array_merge($enrolledParam, $params, $orderParams);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($execParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $rawPhone = trim((string)$row['whatsapp_raw']);
        $cleanedPhone = '';
        $phoneDigits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if (strlen($phoneDigits) >= 9) {
            $formattedLk = campus_lk_whatsapp($rawPhone);
            if ($formattedLk !== '') {
                $cleanedPhone = '0' . substr($formattedLk, 2);
            } else {
                $cleanedPhone = $rawPhone;
            }
        }

        $results[] = [
            'id' => (int)$row['id'],
            'full_name' => trim((string)$row['full_name']),
            'google_email' => trim((string)$row['google_email']),
            'whatsapp' => $cleanedPhone,
            'parent_name' => trim((string)$row['parent_name']),
            'parent_whatsapp' => trim((string)$row['parent_whatsapp']),
            'already_enrolled' => !empty($row['already_enrolled']),
        ];
    }

    return $results;
}

/**
 * Find an existing student by Sri Lankan WhatsApp / username.
 *
 * @return array{id:int,full_name:string,google_email:string,whatsapp:string,parent_name:string,parent_whatsapp:string}|null
 */
function campus_find_student_by_whatsapp(PDO $pdo, string $whatsapp): ?array
{
    $phone = campus_lk_whatsapp($whatsapp);
    if ($phone === '') {
        return null;
    }
    $variants = [$phone];
    if (strlen($phone) === 11 && str_starts_with($phone, '94')) {
        $variants[] = '0' . substr($phone, 2);
        $variants[] = '+' . $phone;
    }
    $variants = array_values(array_unique($variants));
    $in = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            COALESCE(NULLIF(TRIM(u.google_email), ''), NULLIF(TRIM(sp.email), ''), '') AS google_email,
            COALESCE(sp.full_name, '') AS full_name,
            COALESCE(sp.whatsapp_number, u.username, '') AS whatsapp_raw,
            COALESCE(sp.parent_name, '') AS parent_name,
            COALESCE(sp.parent_whatsapp, '') AS parent_whatsapp
        FROM users u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.deleted_at IS NULL
          AND u.role = 'student'
          AND (
            u.username IN ($in)
            OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(sp.whatsapp_number, ''), '+', ''), ' ', ''), '-', ''), '.', '') IN ($in)
          )
        ORDER BY u.id
        LIMIT 1
    ");
    $stmt->execute(array_merge($variants, $variants));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'full_name' => trim((string)$row['full_name']),
        'google_email' => trim((string)$row['google_email']),
        'whatsapp' => trim((string)$row['whatsapp_raw']),
        'parent_name' => trim((string)$row['parent_name']),
        'parent_whatsapp' => trim((string)$row['parent_whatsapp']),
    ];
}

/**
 * Teacher/admin walk-in: create or find a student, enrol in the class, optional attendance.
 *
 * @return array{student_id:int,created:bool,already_enrolled:bool,name:string,phone:string,plain_password:?string}
 */
function campus_teacher_add_student(
    PDO $pdo,
    int $classId,
    string $fullName,
    string $whatsapp,
    string $parentName = '',
    string $parentWhatsapp = '',
    int $timetableId = 0,
    bool $markPresent = false,
    int $markedBy = 0,
    int $existingStudentId = 0,
    string $googleEmail = ''
): array {
    $fullName = trim($fullName);
    $phone = campus_lk_whatsapp($whatsapp);
    $parentName = trim($parentName);
    $parentPhone = campus_lk_whatsapp($parentWhatsapp);
    $googleEmail = trim($googleEmail);

    $studentId = 0;
    $created = false;
    $plainPassword = null;

    // 1. If an existing student ID is explicitly provided
    if ($existingStudentId > 0) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.role, u.deleted_at, u.username, u.google_email,
                   sp.full_name, sp.whatsapp_number, sp.parent_name, sp.parent_whatsapp
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$existingStudentId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !empty($user['deleted_at']) || (string)$user['role'] !== 'student') {
            throw new RuntimeException('Selected student was not found or the account is inactive.');
        }
        $studentId = (int)$user['id'];
        if ($phone === '') {
            $phone = campus_lk_whatsapp((string)($user['whatsapp_number'] ?? $user['username'] ?? ''));
        }
        if ($fullName === '') {
            $fullName = trim((string)($user['full_name'] ?? $user['username'] ?? ''));
        }
        if ($parentName === '') {
            $parentName = trim((string)($user['parent_name'] ?? ''));
        }
        if ($parentPhone === '') {
            $parentPhone = campus_lk_whatsapp((string)($user['parent_whatsapp'] ?? ''));
        }

        $pdo->prepare("
            INSERT INTO student_profiles (user_id, full_name, whatsapp_number, parent_name, parent_whatsapp)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name = IF(VALUES(full_name) IS NOT NULL AND VALUES(full_name) != '', VALUES(full_name), full_name),
                whatsapp_number = IF(VALUES(whatsapp_number) IS NOT NULL AND VALUES(whatsapp_number) != '', VALUES(whatsapp_number), whatsapp_number),
                parent_name = IF(VALUES(parent_name) IS NOT NULL AND VALUES(parent_name) != '', VALUES(parent_name), parent_name),
                parent_whatsapp = IF(VALUES(parent_whatsapp) IS NOT NULL AND VALUES(parent_whatsapp) != '', VALUES(parent_whatsapp), parent_whatsapp)
        ")->execute([
            $studentId,
            $fullName !== '' ? $fullName : null,
            $phone !== '' ? $phone : null,
            $parentName !== '' ? $parentName : null,
            $parentPhone !== '' ? $parentPhone : null
        ]);
    } else {
        // 2. Try to match by Google login email, profile email, or an email username
        if ($googleEmail !== '' && filter_var($googleEmail, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.role, u.deleted_at
                FROM users u
                LEFT JOIN student_profiles sp ON sp.user_id = u.id
                WHERE u.role = 'student'
                  AND u.deleted_at IS NULL
                  AND (
                        LOWER(TRIM(COALESCE(u.google_email, ''))) = LOWER(?)
                     OR LOWER(TRIM(COALESCE(sp.email, ''))) = LOWER(?)
                     OR LOWER(TRIM(u.username)) = LOWER(?)
                  )
                ORDER BY u.id
                LIMIT 1
            ");
            $stmt->execute([$googleEmail, $googleEmail, $googleEmail]);
            $matchedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($matchedUser) {
                return campus_teacher_add_student(
                    $pdo,
                    $classId,
                    $fullName,
                    $whatsapp,
                    $parentName,
                    $parentWhatsapp,
                    $timetableId,
                    $markPresent,
                    $markedBy,
                    (int)$matchedUser['id']
                );
            }
        }

        // 3. Try to match by phone / username if phone provided
        $user = null;
        if ($phone !== '') {
            $stmt = $pdo->prepare("
                SELECT id, role, deleted_at
                FROM users
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([$phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$user) {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.role, u.deleted_at
                    FROM student_profiles sp
                    JOIN users u ON u.id = sp.user_id
                    WHERE sp.whatsapp_number = ?
                    LIMIT 1
                ");
                $stmt->execute([$phone]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        if ($user) {
            if (!empty($user['deleted_at']) || (string)$user['role'] !== 'student') {
                throw new RuntimeException('That WhatsApp number is already used on another account. Ask the office to check.');
            }
            $studentId = (int)$user['id'];
            if ($fullName !== '' || $phone !== '') {
                $pdo->prepare("
                    INSERT INTO student_profiles (user_id, full_name, whatsapp_number, parent_name, parent_whatsapp)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        full_name = IF(VALUES(full_name) = '' OR VALUES(full_name) IS NULL, full_name, VALUES(full_name)),
                        whatsapp_number = IF(VALUES(whatsapp_number) IS NULL, whatsapp_number, VALUES(whatsapp_number)),
                        parent_name = IF(VALUES(parent_name) = '' OR VALUES(parent_name) IS NULL, parent_name, VALUES(parent_name)),
                        parent_whatsapp = IF(VALUES(parent_whatsapp) = '' OR VALUES(parent_whatsapp) IS NULL, parent_whatsapp, VALUES(parent_whatsapp))
                ")->execute([$studentId, $fullName, $phone, $parentName, $parentPhone]);
            }
        } else {
            if ($phone === '') {
                if ($googleEmail !== '') {
                    throw new RuntimeException('No student account uses that email. Check the Google email, or enter a WhatsApp number to register a new student.');
                }
                throw new RuntimeException('Enter a valid Sri Lankan WhatsApp number, e.g. 0771234567, or the student’s Google email.');
            }
            if (mb_strlen($fullName) < 2) {
                throw new RuntimeException('Enter the student’s full name.');
            }
            $plainPassword = bin2hex(random_bytes(6));
            $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO users (username, password_hash, role, is_active, deleted_at)
                VALUES (?, ?, 'student', 1, NULL)
            ")->execute([$phone, $hash]);
            $studentId = (int)$pdo->lastInsertId();
            $created = true;
            $pdo->prepare("
                INSERT INTO student_profiles (user_id, full_name, whatsapp_number, parent_name, parent_whatsapp)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$studentId, $fullName, $phone, $parentName !== '' ? $parentName : null, $parentPhone !== '' ? $parentPhone : null]);
        }
    }

    if ($phone !== '') {
        try {
            $pdo->prepare("
                INSERT INTO whatsapp_bot_contacts (phone, student_id, active, verified_at)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE student_id = VALUES(student_id), active = 1
            ")->execute([$phone, $studentId]);
        } catch (Throwable $e) {
            error_log('Walk-in WhatsApp contact: ' . $e->getMessage());
        }
    }

    $enrollCheck = $pdo->prepare("SELECT id FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1");
    $enrollCheck->execute([$studentId, $classId]);
    $alreadyEnrolled = (bool)$enrollCheck->fetchColumn();
    if (!$alreadyEnrolled) {
        $pdo->prepare("INSERT IGNORE INTO student_enrollments (student_id, class_id) VALUES (?, ?)")
            ->execute([$studentId, $classId]);
        campus_waitlist_leave($pdo, $studentId, $classId);
    }

    $nameStmt = $pdo->prepare("SELECT COALESCE(NULLIF(sp.full_name,''), u.username) FROM users u LEFT JOIN student_profiles sp ON sp.user_id = u.id WHERE u.id = ?");
    $nameStmt->execute([$studentId]);
    $name = trim((string)($nameStmt->fetchColumn() ?: $fullName ?: $phone));

    $classStmt = $pdo->prepare("SELECT name FROM student_classes WHERE id = ?");
    $classStmt->execute([$classId]);
    $className = (string)($classStmt->fetchColumn() ?: 'class');

    if ($markPresent && $timetableId > 0) {
        $save = $pdo->prepare("
            INSERT INTO student_attendance (student_id, timetable_id, status, marked_by)
            VALUES (?, ?, 'present', ?)
            ON DUPLICATE KEY UPDATE status = 'present', marked_by = VALUES(marked_by), marked_at = CURRENT_TIMESTAMP
        ");
        try {
            $save->execute([$studentId, $timetableId, $markedBy > 0 ? $markedBy : null]);
        } catch (Throwable $e) {
            $pdo->prepare("
                INSERT INTO student_attendance (student_id, timetable_id, status)
                VALUES (?, ?, 'present')
                ON DUPLICATE KEY UPDATE status = 'present', marked_at = CURRENT_TIMESTAMP
            ")->execute([$studentId, $timetableId]);
        }
        $dateStmt = $pdo->prepare("SELECT date FROM timetable WHERE id = ?");
        $dateStmt->execute([$timetableId]);
        $lessonDate = (string)($dateStmt->fetchColumn() ?: date('Y-m-d'));
        campus_ensure_month_fees($pdo, $studentId, date('Y-m', strtotime($lessonDate)));
    }

    if ($created) {
        campus_notify_new_student_registration($pdo, $studentId, $name, $phone, 'office');
    }

    if (!$alreadyEnrolled) {
        campus_notify_class_join($pdo, $studentId, $classId, $className, $phone, $name, []);
    }

    if ($created && $plainPassword !== null) {
        $loginUrl = function_exists('student_login_url')
            ? student_login_url()
            : (function_exists('edexcel_public_app_url') ? rtrim(edexcel_public_app_url(), '/') : 'https://edexcel.college') . '/student/login.php';
        $loginMsg =
            "🎓 *Edexcel College*\n\n" .
            "Your student account is ready.\n\n" .
            "Login: " . $loginUrl . "\n" .
            "Username: +" . $phone . "\n" .
            "Password: *" . $plainPassword . "*\n\n" .
            "Change this password after you sign in.";
        try {
            require_once __DIR__ . '/whatsapp_gateway.php';
            require_once __DIR__ . '/../vendor/autoload.php';
            whatsapp_sender($pdo)->sendText($phone, $loginMsg);
        } catch (Throwable $e) {
            error_log('Walk-in login WhatsApp failed: ' . $e->getMessage());
        }
    }

    if (function_exists('log_audit')) {
        log_audit($pdo, $created ? 'teacher_register_student' : 'teacher_enrol_student', 'users', $studentId, null, [
            'class_id' => $classId,
            'phone' => $phone,
        ]);
    }

    return [
        'student_id' => $studentId,
        'created' => $created,
        'already_enrolled' => $alreadyEnrolled,
        'name' => $name,
        'phone' => $phone,
        'plain_password' => $plainPassword,
    ];
}

/**
 * Per-student amount the teacher charges for one lesson.
 * Uses timetable class_fee_per_student. If that is 0, uses the duration rate
 * (same bands as teacher/institute lesson pay) — never a hardcoded 500.
 */
function campus_teacher_charge_per_student(array $lesson): float
{
    $explicit = (float)($lesson['class_fee_per_student'] ?? 0);
    if ($explicit > 0) {
        return $explicit;
    }
    require_once __DIR__ . '/payment.php';
    return lesson_rate_per_student(lesson_duration_minutes(
        (string)($lesson['start_time'] ?? ''),
        (string)($lesson['end_time'] ?? '')
    ));
}

/**
 * Student wallet for a class/month: teacher charge only for lessons this
 * student attended (present or late). Absent, excused, or unmarked = no fee.
 */
function campus_student_class_fee_for_period(PDO $pdo, int $studentId, int $classId, string $periodYm): float
{
    $statusSql = campus_lesson_status_sql('tt');
    $stmt = $pdo->prepare("
        SELECT tt.class_fee_per_student, tt.start_time, tt.end_time
        FROM student_attendance sa
        JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
        WHERE sa.student_id = ?
          AND tt.class_id = ?
          AND DATE_FORMAT(tt.date, '%Y-%m') = ?
          AND sa.status IN ('present', 'late')
          AND {$statusSql}
        ORDER BY tt.date, tt.start_time
    ");
    $stmt->execute([$studentId, $classId, $periodYm]);

    $total = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $lesson) {
        $total += campus_teacher_charge_per_student($lesson);
    }
    return $total;
}

function campus_ensure_month_fees(PDO $pdo, int $studentId, string $periodYm = ''): void
{
    if ($periodYm === '') {
        $periodYm = date('Y-m');
    }
    $dueDate = date('Y-m-10', strtotime($periodYm . '-01'));

    $stmt = $pdo->prepare("SELECT class_id FROM student_enrollments WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($classIds === []) {
        return;
    }

    $in = implode(',', array_fill(0, count($classIds), '?'));
    $classStmt = $pdo->prepare("
        SELECT c.id, c.name
        FROM student_classes c
        WHERE c.id IN ($in)
          AND c.deleted_at IS NULL
    ");
    $classStmt->execute($classIds);

    $insert = $pdo->prepare("
        INSERT INTO student_fee_ledger
            (student_id, class_id, period_ym, description, amount_due, due_date, status)
        VALUES
            (?, ?, ?, ?, ?, ?, 'due')
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            due_date = VALUES(due_date),
            amount_due = IF(status IN ('paid', 'waived'), amount_due, VALUES(amount_due))
    ");

    $clearUnpaid = $pdo->prepare("
        UPDATE student_fee_ledger
        SET amount_due = 0, description = ?
        WHERE student_id = ? AND class_id = ? AND period_ym = ?
          AND status NOT IN ('paid', 'waived')
    ");

    foreach ($classStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $classId = (int)$row['id'];
        $amount = campus_student_class_fee_for_period($pdo, $studentId, $classId, $periodYm);
        $description = $row['name'] . ' — ' . date('F Y', strtotime($periodYm . '-01'));
        if ($amount <= 0) {
            $clearUnpaid->execute([$description, $studentId, $classId, $periodYm]);
            continue;
        }
        $insert->execute([
            $studentId,
            $classId,
            $periodYm,
            $description,
            $amount,
            $dueDate,
        ]);
    }
}

/**
 * Amount due split by class and lesson date (attended present/late only).
 *
 * @return list<array{class_id:int,class_name:string,total:float,lessons:list<array<string,mixed>>}>
 */
function campus_student_fee_due_breakdown(PDO $pdo, int $studentId): array
{
    $statusSql = campus_lesson_status_sql('tt');
    $stmt = $pdo->prepare("
        SELECT
            tt.date,
            tt.start_time,
            tt.end_time,
            tt.class_fee_per_student,
            c.id AS class_id,
            c.name AS class_name,
            sa.status AS attendance_status
        FROM student_fee_ledger f
        JOIN student_attendance sa
            ON sa.student_id = f.student_id
        JOIN timetable tt
            ON tt.id = sa.timetable_id
           AND tt.deleted_at IS NULL
           AND tt.class_id = f.class_id
           AND DATE_FORMAT(tt.date, '%Y-%m') = f.period_ym
        LEFT JOIN student_classes c ON c.id = f.class_id
        WHERE f.student_id = ?
          AND f.status IN ('due', 'partial')
          AND (f.amount_due - f.amount_paid) > 0
          AND sa.status IN ('present', 'late')
          AND {$statusSql}
        ORDER BY c.name, tt.date, tt.start_time
    ");
    $stmt->execute([$studentId]);

    $groups = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $lesson) {
        $classId = (int)($lesson['class_id'] ?? 0);
        $key = $classId > 0 ? (string)$classId : ('x-' . ($lesson['class_name'] ?? ''));
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'class_id' => $classId,
                'class_name' => (string)($lesson['class_name'] ?? 'Class'),
                'total' => 0.0,
                'lessons' => [],
            ];
        }
        $amount = campus_teacher_charge_per_student($lesson);
        $groups[$key]['total'] += $amount;
        $groups[$key]['lessons'][] = [
            'date' => (string)($lesson['date'] ?? ''),
            'start_time' => (string)($lesson['start_time'] ?? ''),
            'end_time' => (string)($lesson['end_time'] ?? ''),
            'amount' => $amount,
            'attendance_status' => (string)($lesson['attendance_status'] ?? 'present'),
        ];
    }

    return array_values($groups);
}

function campus_fee_summary(PDO $pdo, int $studentId): array
{
    campus_ensure_month_fees($pdo, $studentId);
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount_due), 0) AS billed,
            COALESCE(SUM(amount_paid), 0) AS paid,
            COALESCE(SUM(CASE WHEN status IN ('due','partial') THEN amount_due - amount_paid ELSE 0 END), 0) AS due
        FROM student_fee_ledger
        WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $sum = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['billed' => 0, 'paid' => 0, 'due' => 0];

    $rows = $pdo->prepare("
        SELECT f.*, c.name AS class_name
        FROM student_fee_ledger f
        LEFT JOIN student_classes c ON c.id = f.class_id
        WHERE f.student_id = ?
          AND (f.amount_due > 0 OR f.amount_paid > 0)
        ORDER BY f.period_ym DESC, f.id DESC
        LIMIT 24
    ");
    $rows->execute([$studentId]);

    $next = $pdo->prepare("
        SELECT * FROM student_fee_ledger
        WHERE student_id = ? AND status IN ('due','partial')
        ORDER BY due_date IS NULL, due_date ASC
        LIMIT 1
    ");
    $next->execute([$studentId]);

    return [
        'billed' => (float)$sum['billed'],
        'paid' => (float)$sum['paid'],
        'due' => max(0, (float)$sum['due']),
        'next' => $next->fetch(PDO::FETCH_ASSOC) ?: null,
        'rows' => $rows->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'breakdown' => campus_student_fee_due_breakdown($pdo, $studentId),
    ];
}

function campus_lesson_status_sql(string $alias = 'tt'): string
{
    return "({$alias}.lesson_status IS NULL OR {$alias}.lesson_status IN ('scheduled','substituted'))";
}

function campus_class_enrolled_count(PDO $pdo, int $classId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_enrollments WHERE class_id = ?");
    $stmt->execute([$classId]);
    return (int)$stmt->fetchColumn();
}

function campus_class_is_full(PDO $pdo, int $classId, ?int $capacity = null): bool
{
    if ($capacity === null) {
        if (!campus_column_exists($pdo, 'student_classes', 'capacity')) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT capacity FROM student_classes WHERE id = ? LIMIT 1");
        $stmt->execute([$classId]);
        $capacity = $stmt->fetchColumn();
        $capacity = $capacity === false || $capacity === null || $capacity === '' ? null : (int)$capacity;
    }
    if ($capacity === null || $capacity < 1) {
        return false;
    }
    return campus_class_enrolled_count($pdo, $classId) >= $capacity;
}

function campus_waitlist_join(PDO $pdo, int $studentId, int $classId): int
{
    $pdo->prepare("
        INSERT IGNORE INTO student_waitlist (student_id, class_id)
        VALUES (?, ?)
    ")->execute([$studentId, $classId]);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM student_waitlist
        WHERE class_id = ?
          AND created_at <= (SELECT created_at FROM student_waitlist WHERE student_id = ? AND class_id = ? LIMIT 1)
    ");
    $stmt->execute([$classId, $studentId, $classId]);
    return max(1, (int)$stmt->fetchColumn());
}

function campus_waitlist_leave(PDO $pdo, int $studentId, int $classId): void
{
    $pdo->prepare("DELETE FROM student_waitlist WHERE student_id = ? AND class_id = ?")
        ->execute([$studentId, $classId]);
}

function campus_promote_next_waitlist(PDO $pdo, int $classId, ?int $preferStudentId = null): ?int
{
    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare('SELECT id FROM student_classes WHERE id = ? FOR UPDATE')->execute([$classId]);
        if (campus_class_is_full($pdo, $classId)) {
            if ($ownTxn) {
                $pdo->commit();
            }
            return null;
        }

        $studentId = 0;
        if ($preferStudentId !== null && $preferStudentId > 0) {
            $check = $pdo->prepare("SELECT student_id FROM student_waitlist WHERE class_id = ? AND student_id = ? LIMIT 1 FOR UPDATE");
            $check->execute([$classId, $preferStudentId]);
            $studentId = (int)$check->fetchColumn();
        }
        if ($studentId < 1) {
            $stmt = $pdo->prepare("
                SELECT student_id FROM student_waitlist
                WHERE class_id = ?
                ORDER BY created_at ASC, id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$classId]);
            $studentId = (int)$stmt->fetchColumn();
        }
        if ($studentId < 1) {
            if ($ownTxn) {
                $pdo->commit();
            }
            return null;
        }

        $pdo->prepare("INSERT IGNORE INTO student_enrollments (student_id, class_id) VALUES (?, ?)")
            ->execute([$studentId, $classId]);
        campus_waitlist_leave($pdo, $studentId, $classId);
        if ($ownTxn) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    try {
        $nameStmt = $pdo->prepare("SELECT name FROM student_classes WHERE id = ?");
        $nameStmt->execute([$classId]);
        $className = (string)($nameStmt->fetchColumn() ?: 'class');
        $contacts = campus_student_contacts($pdo, $studentId);
        $studentName = trim((string)($contacts['name'] ?? ''));
        $studentPhone = (string)($contacts['student_phone'] ?? '');
        campus_notify_class_join(
            $pdo,
            $studentId,
            $classId,
            $className,
            $studentPhone,
            $studentName,
            []
        );
    } catch (Throwable $e) {
        error_log('Waitlist notify: ' . $e->getMessage());
    }

    return $studentId;
}

function campus_portal_notify(
    PDO $pdo,
    array $studentIds,
    string $title,
    string $message,
    string $link = 'dashboard.php?tab=overview',
    string $type = 'info'
): void {
    $title = trim($title);
    $message = trim($message);
    $link = trim($link);
    $type = trim($type);
    $allowed = ['info', 'success', 'warning', 'danger', 'payment', 'class', 'announcement'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    if ($title === '') {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO student_notifications (student_id, title, message, type, link)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach (array_unique(array_map('intval', $studentIds)) as $studentId) {
        if ($studentId < 1) {
            continue;
        }
        try {
            // Avoid identical spam within 5 minutes.
            $dup = $pdo->prepare("
                SELECT id FROM student_notifications
                WHERE student_id = ?
                  AND title = ?
                  AND COALESCE(message, '') = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                LIMIT 1
            ");
            $dup->execute([$studentId, $title, $message]);
            if ($dup->fetchColumn()) {
                continue;
            }
            $stmt->execute([$studentId, $title, $message !== '' ? $message : null, $type, $link !== '' ? $link : null]);
        } catch (Throwable $e) {
            error_log('Portal notify: ' . $e->getMessage());
        }
    }
}

function campus_student_unread_notifications(PDO $pdo, int $studentId, int $limit = 12): array
{
    if ($studentId < 1) {
        return [];
    }
    $limit = max(1, min(40, $limit));
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, message, type, link, is_read, created_at
            FROM student_notifications
            WHERE student_id IS NULL OR student_id = ?
            ORDER BY is_read ASC, created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function campus_student_unread_notification_count(PDO $pdo, int $studentId): int
{
    if ($studentId < 1) {
        return 0;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_notifications
            WHERE (student_id IS NULL OR student_id = ?) AND is_read = 0
        ");
        $stmt->execute([$studentId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function campus_mark_student_notification_read(PDO $pdo, int $studentId, int $notificationId = 0): bool
{
    if ($studentId < 1) {
        return false;
    }
    try {
        if ($notificationId > 0) {
            $stmt = $pdo->prepare("
                UPDATE student_notifications
                SET is_read = 1
                WHERE id = ? AND (student_id = ? OR student_id IS NULL)
            ");
            $stmt->execute([$notificationId, $studentId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE student_notifications
                SET is_read = 1
                WHERE student_id = ? AND is_read = 0
            ");
            $stmt->execute([$studentId]);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function campus_exam_type_label(?string $type): string
{
    return strtolower(trim((string)$type)) === 'mock' ? 'Mock' : 'Exam';
}

function campus_exam_when(array $exam): string
{
    $date = !empty($exam['exam_date']) ? date('l, d M Y', strtotime((string)$exam['exam_date'])) : '';
    $start = !empty($exam['exam_time']) ? date('g:i A', strtotime((string)$exam['exam_time'])) : '';
    $end = !empty($exam['end_time']) ? date('g:i A', strtotime((string)$exam['end_time'])) : '';
    if ($date === '') {
        return $start !== '' ? ($end !== '' ? $start . ' – ' . $end : $start) : '';
    }
    if ($start !== '' && $end !== '') {
        return $date . ' · ' . $start . ' – ' . $end;
    }
    if ($start !== '') {
        return $date . ' · ' . $start;
    }
    return $date;
}

function campus_exam_venue(array $exam): string
{
    $room = trim((string)($exam['room_name'] ?? ''));
    if ($room !== '') {
        return $room;
    }
    return trim((string)($exam['location'] ?? ''));
}

function campus_all_enrolled_student_ids(PDO $pdo): array
{
    try {
        $ids = $pdo->query("SELECT DISTINCT student_id FROM student_enrollments")->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('intval', $ids ?: [])));
    } catch (Throwable $e) {
        return [];
    }
}

function campus_student_exams(
    PDO $pdo,
    array $classIds,
    ?string $fromDate = null,
    ?string $toDate = null,
    int $limit = 40,
    ?string $examType = null
): array {
    $sql = "
        SELECT
            e.*,
            s.name AS subject_name,
            c.name AS class_name,
            t.name AS teacher_name,
            r.name AS room_name
        FROM student_exams e
        LEFT JOIN subjects s ON s.id = e.subject_id
        LEFT JOIN student_classes c ON c.id = e.class_id
        LEFT JOIN teachers t ON t.id = e.teacher_id
        LEFT JOIN rooms r ON r.id = e.room_id
        WHERE 1=1
    ";
    $params = [];
    $classIds = array_values(array_filter(array_map('intval', $classIds)));
    if ($classIds) {
        $in = implode(',', array_fill(0, count($classIds), '?'));
        $sql .= " AND (e.class_id IS NULL OR e.class_id IN ($in))";
        $params = $classIds;
    } else {
        $sql .= " AND e.class_id IS NULL";
    }
    if ($fromDate) {
        $sql .= " AND e.exam_date >= ?";
        $params[] = $fromDate;
    }
    if ($toDate) {
        $sql .= " AND e.exam_date <= ?";
        $params[] = $toDate;
    }
    $examType = strtolower(trim((string)$examType));
    if (in_array($examType, ['exam', 'mock'], true)) {
        $sql .= " AND e.exam_type = ?";
        $params[] = $examType;
    }
    $sql .= " ORDER BY e.exam_date ASC, e.exam_time IS NULL, e.exam_time ASC LIMIT " . max(1, $limit);
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('campus_student_exams: ' . $e->getMessage());
        return [];
    }
}

function campus_format_wa_phone(string $phone): string
{
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '94' . substr($phone, 1);
    }
    return $phone;
}

function campus_display_phone(string $phone): string
{
    $digits = campus_format_wa_phone($phone);
    return $digits === '' ? '' : '+' . $digits;
}

/**
 * @return list<array{id:int,name:string,phone:string}>
 */
function campus_class_teachers(PDO $pdo, int $classId): array
{
    $sql = "
        SELECT DISTINCT t.id, t.name, t.phone
        FROM teachers t
        WHERE t.deleted_at IS NULL
          AND t.id IN (
                SELECT tt.teacher_id
                FROM timetable tt
                WHERE tt.class_id = ?
                  AND tt.deleted_at IS NULL
                  AND tt.teacher_id IS NOT NULL
                  AND tt.teacher_id > 0
                UNION
                SELECT ctw.teacher_id
                FROM class_teacher_whatsapp ctw
                WHERE ctw.class_id = ?
                  AND ctw.teacher_id IS NOT NULL
                  AND ctw.teacher_id > 0
                UNION
                SELECT ts.teacher_id
                FROM subject_classes sc
                JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
                WHERE sc.class_id = ?
                UNION
                SELECT c.teacher_id
                FROM student_classes c
                WHERE c.id = ?
                  AND c.teacher_id IS NOT NULL
                  AND c.teacher_id > 0
          )
        ORDER BY t.name
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$classId, $classId, $classId, $classId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('campus_class_teachers: ' . $e->getMessage());
        return [];
    }
}

function campus_class_subject_names(PDO $pdo, int $classId): string
{
    try {
        $stmt = $pdo->prepare("
            SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ')
            FROM subject_classes sc
            JOIN subjects s ON s.id = sc.subject_id AND s.deleted_at IS NULL
            WHERE sc.class_id = ?
        ");
        $stmt->execute([$classId]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

function campus_class_next_session(PDO $pdo, int $classId): string
{
    try {
        $stmt = $pdo->prepare("
            SELECT date, start_time, end_time
            FROM timetable
            WHERE class_id = ?
              AND deleted_at IS NULL
              AND (date > CURDATE() OR (date = CURDATE() AND start_time >= CURTIME()))
            ORDER BY date ASC, start_time ASC
            LIMIT 1
        ");
        $stmt->execute([$classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }
        $date = date('D, d M Y', strtotime((string)$row['date']));
        $start = date('g:i A', strtotime((string)$row['start_time']));
        $end = !empty($row['end_time']) ? date('g:i A', strtotime((string)$row['end_time'])) : '';
        return $end !== '' ? $date . ' · ' . $start . ' – ' . $end : $date . ' · ' . $start;
    } catch (Throwable $e) {
        return '';
    }
}

function campus_insert_teacher_notification(
    PDO $pdo,
    int $teacherId,
    string $title,
    string $message,
    string $link = 'dashboard.php'
): void {
    if ($teacherId < 1) {
        return;
    }
    try {
        $pdo->prepare("
            INSERT INTO teacher_notifications (teacher_id, title, message, type, link)
            VALUES (?, ?, ?, 'success', ?)
        ")->execute([$teacherId, $title, $message, $link]);
    } catch (Throwable $e) {
        error_log('Teacher dashboard notification: ' . $e->getMessage());
    }
}

function campus_insert_admin_notification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $link = 'admin/students.php'
): void {
    if ($userId < 1) {
        return;
    }
    try {
        $pdo->prepare("
            INSERT INTO admin_notifications (user_id, title, message, type, link)
            VALUES (?, ?, ?, 'success', ?)
        ")->execute([$userId, $title, $message, $link]);
    } catch (Throwable $e) {
        error_log('Admin dashboard notification: ' . $e->getMessage());
    }
}

function campus_admin_user_ids(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT id FROM users
            WHERE role = 'admin'
              AND deleted_at IS NULL
              AND is_active = 1
        ");
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function campus_notify_admins(
    PDO $pdo,
    string $title,
    string $message,
    string $link = 'admin/students.php'
): void {
    foreach (campus_admin_user_ids($pdo) as $adminId) {
        campus_insert_admin_notification($pdo, $adminId, $title, $message, $link);
    }
}

/**
 * Dashboard notice when a student account is created.
 *
 * @param 'portal'|'office'|'admin' $source
 */
function campus_notify_new_student_registration(
    PDO $pdo,
    int $studentId,
    string $name,
    string $phone,
    string $source = 'portal',
    string $email = ''
): void {
    $when = date('d M Y, h:i A');
    $displayPhone = campus_display_phone($phone);
    $label = trim($name) !== '' ? trim($name) : ($displayPhone !== '' ? $displayPhone : 'New student');
    if ($source === 'office') {
        $how = 'Registered at the office';
    } elseif ($source === 'admin') {
        $how = 'Created in Student Management';
    } else {
        $how = 'Registered on the student portal';
    }

    $lines = ['Student: ' . $label];
    if ($displayPhone !== '') {
        $lines[] = 'WhatsApp: ' . $displayPhone;
    }
    $email = trim($email);
    if ($email !== '') {
        $lines[] = 'Email: ' . $email;
    }
    $lines[] = $how;
    $lines[] = 'When: ' . $when;

    $link = $studentId > 0 ? 'admin/students.php?edit=' . $studentId : 'admin/students.php';
    campus_notify_admins($pdo, 'New student registered', implode("\n", $lines), $link);
}

/**
 * Dashboard notice when a parent first signs in (account created).
 *
 * @param list<string> $linkedStudents
 */
function campus_notify_new_parent_registration(
    PDO $pdo,
    int $parentId,
    string $phone,
    string $name = '',
    array $linkedStudents = []
): void {
    $when = date('d M Y, h:i A');
    $displayPhone = campus_display_phone($phone);
    $label = trim($name) !== '' ? trim($name) : ($displayPhone !== '' ? $displayPhone : 'New parent');
    $linked = [];
    foreach ($linkedStudents as $child) {
        $child = trim((string)$child);
        if ($child !== '') {
            $linked[] = $child;
        }
    }

    $lines = ['Parent: ' . $label];
    if ($displayPhone !== '') {
        $lines[] = 'WhatsApp: ' . $displayPhone;
    }
    $lines[] = 'Registered on the parent portal';
    $lines[] = $linked !== []
        ? 'Linked students: ' . implode(', ', $linked)
        : 'Linked students: none yet';
    $lines[] = 'When: ' . $when;

    campus_notify_admins($pdo, 'New parent registered', implode("\n", $lines), 'admin/students.php');
}

/**
 * Dashboard + WhatsApp notices after a student joins a class.
 *
 * @param list<string> $groupLinks
 */
function campus_notify_class_join(
    PDO $pdo,
    int $studentId,
    int $classId,
    string $className,
    string $studentPhone,
    string $studentName,
    array $groupLinks = []
): void {
    $teachers = campus_class_teachers($pdo, $classId);
    $subjects = campus_class_subject_names($pdo, $classId);
    $nextSession = campus_class_next_session($pdo, $classId);
    $when = date('d M Y, h:i A');
    $studentLabel = $studentName !== '' ? $studentName : ('+' . $studentPhone);
    $studentDisplayPhone = campus_display_phone($studentPhone);

    $teacherLines = [];
    foreach ($teachers as $teacher) {
        $name = trim((string)($teacher['name'] ?? 'Teacher'));
        $display = campus_display_phone((string)($teacher['phone'] ?? ''));
        $teacherLines[] = $display !== ''
            ? '• ' . $name . "\n  Phone: " . $display
            : '• ' . $name . "\n  Phone: not saved yet";
    }
    $teacherBlock = $teacherLines !== []
        ? implode("\n", $teacherLines)
        : "• To be assigned";

    $studentPortal =
        'You joined ' . $className . '.'
        . ($subjects !== '' ? ' Subjects: ' . $subjects . '.' : '')
        . ' Check WhatsApp for teacher contact details.';
    campus_portal_notify(
        $pdo,
        [$studentId],
        'Joined ' . $className,
        $studentPortal,
        'dashboard.php?tab=classes'
    );

    $staffMessage =
        'Student: ' . $studentLabel . "\n"
        . ($studentDisplayPhone !== '' ? 'WhatsApp: ' . $studentDisplayPhone . "\n" : '')
        . 'Class: ' . $className . "\n"
        . ($subjects !== '' ? 'Subjects: ' . $subjects . "\n" : '')
        . 'Joined: ' . $when;

    foreach ($teachers as $teacher) {
        campus_insert_teacher_notification(
            $pdo,
            (int)$teacher['id'],
            'New student joined ' . $className,
            $staffMessage,
            'dashboard.php'
        );
    }

    campus_notify_admins(
        $pdo,
        'Student joined ' . $className,
        $staffMessage,
        'admin/students.php'
    );

    $studentWa =
        "🎓 *Class joined successfully*\n\n"
        . "Class: *" . $className . "*\n";
    if ($subjects !== '') {
        $studentWa .= "Subjects: " . $subjects . "\n";
    }
    if ($nextSession !== '') {
        $studentWa .= "Next class: " . $nextSession . "\n";
    }
    $studentWa .= "\n*Your teacher(s)*\n" . $teacherBlock . "\n";
    if ($groupLinks !== []) {
        $studentWa .= "\nWhatsApp group:\n" . implode("\n", $groupLinks) . "\n";
    }
    $studentWa .= "\nEdexcel College";

    $teacherWa =
        "🔔 *New student joined your class*\n\n"
        . "Class: *" . $className . "*\n"
        . "Student: *" . $studentLabel . "*\n"
        . ($studentDisplayPhone !== '' ? "WhatsApp: " . $studentDisplayPhone . "\n" : '')
        . ($subjects !== '' ? "Subjects: " . $subjects . "\n" : '')
        . "Joined: " . $when . "\n\n"
        . "Please check your dashboard.";

    $adminWa =
        "🔔 *Student joined a class*\n\n"
        . $staffMessage;

    try {
        require_once __DIR__ . '/whatsapp_gateway.php';
        require_once __DIR__ . '/../vendor/autoload.php';
        $api = whatsapp_sender($pdo);

        if ($studentPhone !== '') {
            try {
                $api->sendText($studentPhone, $studentWa);
            } catch (Throwable $e) {
                error_log('Join WhatsApp to student failed: ' . $e->getMessage());
            }
        }

        $sentTeacherPhones = [];
        foreach ($teachers as $teacher) {
            $phone = campus_format_wa_phone((string)($teacher['phone'] ?? ''));
            if ($phone === '' || isset($sentTeacherPhones[$phone])) {
                continue;
            }
            $sentTeacherPhones[$phone] = true;
            try {
                $api->sendText($phone, $teacherWa);
            } catch (Throwable $e) {
                error_log('Join WhatsApp to teacher failed: ' . $e->getMessage());
            }
        }

        if (!function_exists('evolution_setting')) {
            require_once __DIR__ . '/evolution.php';
        }
        $office = campus_format_wa_phone(evolution_setting($pdo, 'evolution_admin_number'));
        if ($office !== '' && !isset($sentTeacherPhones[$office])) {
            try {
                $api->sendText($office, $adminWa);
            } catch (Throwable $e) {
                error_log('Join WhatsApp to admin number failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Join WhatsApp unavailable: ' . $e->getMessage());
    }
}

function campus_unenroll_from_class(
    PDO $pdo,
    int $studentId,
    int $classId,
    string $reason,
    string $initiatedBy,
    int $actorUserId = 0,
    string $actorName = ''
): void {
    $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');
    if (strlen($reason) < 5) {
        throw new RuntimeException('Please enter a short reason (at least 5 characters).');
    }
    if (strlen($reason) > 500) {
        $reason = substr($reason, 0, 500);
    }
    $initiatedBy = in_array($initiatedBy, ['student', 'teacher', 'admin'], true) ? $initiatedBy : 'student';

    $enrolled = $pdo->prepare('SELECT id FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
    $enrolled->execute([$studentId, $classId]);
    if (!$enrolled->fetchColumn()) {
        throw new RuntimeException('That student is not enrolled in this class.');
    }

    $className = 'class';
    try {
        $stmt = $pdo->prepare('SELECT name FROM student_classes WHERE id = ? LIMIT 1');
        $stmt->execute([$classId]);
        $name = $stmt->fetchColumn();
        if (is_string($name) && $name !== '') {
            $className = $name;
        }
    } catch (Throwable $e) {
    }

    $contacts = campus_student_contacts($pdo, $studentId);
    $studentName = trim((string)($contacts['name'] ?? ''));
    if ($studentName === '') {
        $studentName = 'Student #' . $studentId;
    }
    $actorName = trim($actorName);
    if ($actorName === '') {
        $actorName = $initiatedBy === 'student' ? $studentName : ucfirst($initiatedBy);
    }

    $pdo->prepare('DELETE FROM student_enrollments WHERE student_id = ? AND class_id = ?')
        ->execute([$studentId, $classId]);

    if (function_exists('classroom_disconnect_student_class')) {
        classroom_disconnect_student_class($pdo, $studentId, $classId);
    }

    try {
        $pdo->prepare("
            INSERT INTO class_leave_log
                (student_id, class_id, class_name, student_name, reason, initiated_by, actor_user_id, actor_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $studentId,
            $classId,
            $className,
            $studentName,
            $reason,
            $initiatedBy,
            $actorUserId > 0 ? $actorUserId : null,
            $actorName,
        ]);
    } catch (Throwable $e) {
        error_log('class_leave_log: ' . $e->getMessage());
    }

    $when = date('d M Y, h:i A');
    if ($initiatedBy === 'student') {
        $studentTitle = 'You left ' . $className;
        $studentBody = 'You unenrolled from ' . $className . ".\nReason: " . $reason;
        $staffTitle = $studentName . ' left ' . $className;
        $staffBody = 'Student: ' . $studentName . "\nClass: " . $className . "\nLeft: " . $when
            . "\nReason: " . $reason;
        $studentWa = "👋 *You left a class*\n\nClass: *" . $className . "*\nReason: " . $reason
            . "\n\nEdexcel College";
        $staffWa = "🔔 *Student unenrolled*\n\nClass: *" . $className . "*\nStudent: *" . $studentName
            . "*\nReason: " . $reason . "\n\nPlease check your dashboard.";
    } else {
        $studentTitle = 'Removed from ' . $className;
        $studentBody = 'You were removed from ' . $className . ' by ' . $actorName . ".\nReason: " . $reason;
        $staffTitle = $studentName . ' removed from ' . $className;
        $staffBody = 'Student: ' . $studentName . "\nClass: " . $className . "\nRemoved by: " . $actorName
            . "\nWhen: " . $when . "\nReason: " . $reason;
        $studentWa = "📛 *Removed from class*\n\nClass: *" . $className . "*\nRemoved by: " . $actorName
            . "\nReason: " . $reason . "\n\nPlease contact the college if you need help.\n\nEdexcel College";
        $staffWa = "🔔 *Student removed from class*\n\nClass: *" . $className . "*\nStudent: *" . $studentName
            . "*\nRemoved by: " . $actorName . "\nReason: " . $reason;
    }

    campus_portal_notify($pdo, [$studentId], $studentTitle, $studentBody, 'dashboard.php?tab=classes');

    $teachers = campus_class_teachers($pdo, $classId);
    foreach ($teachers as $teacher) {
        campus_insert_teacher_notification(
            $pdo,
            (int)$teacher['id'],
            $staffTitle,
            $staffBody,
            'campus/students.php?class_id=' . $classId
        );
    }
    campus_notify_admins($pdo, $staffTitle, $staffBody, 'admin/students.php');

    campus_notify_phones($pdo, $studentId, $studentWa, 'CLASS_LEAVE');

    try {
        require_once __DIR__ . '/whatsapp_gateway.php';
        require_once __DIR__ . '/../vendor/autoload.php';
        $api = whatsapp_sender($pdo);
        $sent = [];
        foreach ($teachers as $teacher) {
            $phone = campus_format_wa_phone((string)($teacher['phone'] ?? ''));
            if ($phone === '' || isset($sent[$phone])) {
                continue;
            }
            $sent[$phone] = true;
            try {
                $api->sendText($phone, $staffWa);
            } catch (Throwable $e) {
                error_log('Leave WhatsApp to teacher failed: ' . $e->getMessage());
            }
        }
        if (!function_exists('evolution_setting')) {
            require_once __DIR__ . '/evolution.php';
        }
        $office = campus_format_wa_phone(evolution_setting($pdo, 'evolution_admin_number'));
        if ($office !== '' && !isset($sent[$office])) {
            try {
                $api->sendText($office, $staffWa);
            } catch (Throwable $e) {
                error_log('Leave WhatsApp to admin number failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Leave WhatsApp unavailable: ' . $e->getMessage());
    }

    try {
        if (!class_exists(\Edexcel\Services\WaitlistOfferService::class)) {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
        }
        (new \Edexcel\Services\WaitlistOfferService($pdo))->offerNext($classId);
    } catch (Throwable $e) {
        error_log('Waitlist offer after leave: ' . $e->getMessage());
    }
}

/**
 * @return array<string,string>
 */
function campus_teacher_remove_reason_options(): array
{
    return [
        'attendance' => 'Irregular attendance / too many absences',
        'fees' => 'Unpaid class fees',
        'behaviour' => 'Behaviour / class discipline',
        'moved' => 'Moved to another class or group',
        'parent' => 'Parent requested removal',
        'completed' => 'No longer attending / course finished',
        'wrong_class' => 'Enrolled in the wrong class',
        'long_leave' => 'Long-term medical or personal leave',
    ];
}

function campus_teacher_remove_reason_from_post(array $post): string
{
    $presets = campus_teacher_remove_reason_options();
    $preset = trim((string)($post['reason_preset'] ?? ''));
    $extra = trim((string)($post['reason_extra'] ?? ''));
    if ($preset === 'other') {
        return $extra;
    }
    if (isset($presets[$preset])) {
        return $extra !== '' ? ($presets[$preset] . ' — ' . $extra) : $presets[$preset];
    }
    throw new RuntimeException('Choose a reason, or Other and type one.');
}

function campus_staff_actor_name(PDO $pdo, bool $isAdmin, int $teacherId): string
{
    if ($teacherId > 0) {
        $tn = $pdo->prepare('SELECT name FROM teachers WHERE id = ? LIMIT 1');
        $tn->execute([$teacherId]);
        return trim((string)($tn->fetchColumn() ?: 'Teacher'));
    }
    return $isAdmin ? 'Admin' : 'Teacher';
}

function campus_clear_lesson_attendance_for_student(PDO $pdo, int $studentId, int $timetableId): void
{
    if ($studentId < 1 || $timetableId < 1) {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM student_attendance WHERE student_id = ? AND timetable_id = ?')
            ->execute([$studentId, $timetableId]);
    } catch (Throwable $e) {
        error_log('Clear lesson attendance: ' . $e->getMessage());
    }
    try {
        $present = $pdo->prepare("
            SELECT COUNT(*) FROM student_attendance
            WHERE timetable_id = ? AND status IN ('present', 'late')
        ");
        $present->execute([$timetableId]);
        $registeredPresent = (int)$present->fetchColumn();
        $unregistered = 0;
        try {
            $unreg = $pdo->prepare('SELECT unregistered_present FROM timetable WHERE id = ?');
            $unreg->execute([$timetableId]);
            $unregistered = max(0, (int)$unreg->fetchColumn());
        } catch (Throwable $e) {
            $unregistered = 0;
        }
        $pdo->prepare('UPDATE timetable SET student_count = ? WHERE id = ?')
            ->execute([$registeredPresent + $unregistered, $timetableId]);
    } catch (Throwable $e) {
        error_log('Refresh lesson student_count: ' . $e->getMessage());
    }
}

/**
 * @return list<array<string,mixed>>
 */
function campus_class_leave_log(PDO $pdo, ?int $classId = null, int $limit = 20): array
{
    try {
        $limit = max(1, min(100, $limit));
        if ($classId !== null && $classId > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM class_leave_log
                WHERE class_id = ?
                ORDER BY created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$classId]);
        } else {
            $stmt = $pdo->query("
                SELECT * FROM class_leave_log
                ORDER BY created_at DESC
                LIMIT {$limit}
            ");
        }
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Unique enrolled students per teacher, across classes that teacher actually teaches
 * (timetable, class teacher_id, and enrollment teacher_id). Not subject-wide.
 *
 * @return array<int,int> teacher_id => student count
 */
function campus_teacher_enrolled_student_counts(PDO $pdo): array
{
    $parts = [];
    $parts[] = "
        SELECT tt.teacher_id AS teacher_id, se.student_id AS student_id
        FROM timetable tt
        INNER JOIN student_enrollments se ON se.class_id = tt.class_id
        INNER JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
        WHERE tt.deleted_at IS NULL
          AND tt.teacher_id IS NOT NULL AND tt.teacher_id > 0
          AND tt.class_id IS NOT NULL AND tt.class_id > 0
    ";
    if (campus_column_exists($pdo, 'student_classes', 'teacher_id')) {
        $parts[] = "
            SELECT c.teacher_id AS teacher_id, se.student_id AS student_id
            FROM student_classes c
            INNER JOIN student_enrollments se ON se.class_id = c.id
            WHERE c.deleted_at IS NULL
              AND c.teacher_id IS NOT NULL AND c.teacher_id > 0
        ";
    }
    if (campus_column_exists($pdo, 'student_enrollments', 'teacher_id')) {
        $parts[] = "
            SELECT se.teacher_id AS teacher_id, se.student_id AS student_id
            FROM student_enrollments se
            WHERE se.teacher_id IS NOT NULL AND se.teacher_id > 0
        ";
    }

    $sql = '
        SELECT teacher_id, COUNT(DISTINCT student_id) AS student_count
        FROM (' . implode(' UNION ', $parts) . ') teacher_students
        GROUP BY teacher_id
    ';

    $counts = [];
    try {
        $stmt = $pdo->query($sql);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int)($row['teacher_id'] ?? 0);
            if ($id > 0) {
                $counts[$id] = (int)($row['student_count'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('campus_teacher_enrolled_student_counts: ' . $e->getMessage());
    }
    return $counts;
}

/**
 * Teachers visible to students: profile fields plus subjects they teach.
 * Ordered by total enrolled students across the teacher's classes, then name.
 *
 * @return list<array<string,mixed>>
 */
function campus_student_teachers_directory(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT
                t.id,
                t.name,
                t.email,
                t.photo,
                tp.bio,
                tp.qualifications,
                tp.experience_years,
                tp.achievements,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
            FROM teachers t
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.id
            LEFT JOIN subjects s ON s.id = ts.subject_id AND s.deleted_at IS NULL
            WHERE t.deleted_at IS NULL
            GROUP BY t.id, t.name, t.email, t.photo
            ORDER BY t.name
        ");
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('campus_student_teachers_directory: ' . $e->getMessage());
        return [];
    }

    $studentCounts = campus_teacher_enrolled_student_counts($pdo);
    foreach ($teachers as &$teacher) {
        $teacherId = (int)$teacher['id'];
        $teacher['photo_path'] = teacherPhotoPath($teacherId, $teacher['photo'] ?? null);
        $teacher['initials'] = teacherInitials((string)($teacher['name'] ?? ''));
        $teacher['avatar_color'] = teacherAvatarColor((string)($teacher['name'] ?? ''));
        $teacher['class_count'] = count(campus_teacher_class_ids($pdo, $teacherId));
        $teacher['student_count'] = $studentCounts[$teacherId] ?? 0;
    }
    unset($teacher);

    usort($teachers, static function (array $a, array $b): int {
        $byStudents = ((int)($b['student_count'] ?? 0)) <=> ((int)($a['student_count'] ?? 0));
        if ($byStudents !== 0) {
            return $byStudents;
        }
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return $teachers;
}

/**
 * @return list<int>
 */
function campus_teacher_class_ids(PDO $pdo, int $teacherId): array
{
    if ($teacherId < 1) {
        return [];
    }
    $ids = [];
    $queries = [
        "SELECT DISTINCT class_id FROM timetable WHERE teacher_id = ? AND deleted_at IS NULL AND class_id IS NOT NULL AND class_id > 0",
        "SELECT DISTINCT sc.class_id
         FROM subject_classes sc
         JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
         WHERE ts.teacher_id = ?",
    ];
    if (campus_column_exists($pdo, 'student_classes', 'teacher_id')) {
        $queries[] = "SELECT id FROM student_classes WHERE teacher_id = ? AND deleted_at IS NULL";
    }
    foreach ($queries as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$teacherId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        } catch (Throwable $e) {
            error_log('campus_teacher_class_ids: ' . $e->getMessage());
        }
    }
    return array_values($ids);
}

/**
 * @return array{teacher:?array,classes:list<array<string,mixed>>,upcoming:list<array<string,mixed>>}
 */
function campus_student_teacher_profile(PDO $pdo, int $teacherId, int $studentId): array
{
    $empty = ['teacher' => null, 'classes' => [], 'upcoming' => []];
    if ($teacherId < 1) {
        return $empty;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                t.id, t.name, t.email, t.photo,
                tp.bio, tp.qualifications, tp.experience_years, tp.achievements,
                tp.website, tp.facebook, tp.instagram, tp.youtube,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
            FROM teachers t
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.id
            LEFT JOIN subjects s ON s.id = ts.subject_id AND s.deleted_at IS NULL
            WHERE t.id = ? AND t.deleted_at IS NULL
            GROUP BY t.id
            LIMIT 1
        ");
        $stmt->execute([$teacherId]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    t.id, t.name, t.email, t.photo,
                    tp.bio, tp.qualifications, tp.experience_years, tp.achievements,
                    GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
                FROM teachers t
                LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
                LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.id
                LEFT JOIN subjects s ON s.id = ts.subject_id AND s.deleted_at IS NULL
                WHERE t.id = ? AND t.deleted_at IS NULL
                GROUP BY t.id
                LIMIT 1
            ");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e2) {
            error_log('campus_student_teacher_profile: ' . $e2->getMessage());
            return $empty;
        }
    }

    if (!$teacher) {
        return $empty;
    }

    $teacher['photo_path'] = teacherPhotoPath((int)$teacher['id'], $teacher['photo'] ?? null);
    $teacher['initials'] = teacherInitials((string)$teacher['name']);
    $teacher['avatar_color'] = teacherAvatarColor((string)$teacher['name']);

    $classIds = campus_teacher_class_ids($pdo, $teacherId);
    $classes = [];
    if ($classIds !== []) {
        $in = implode(',', array_fill(0, count($classIds), '?'));
        try {
            $stmt = $pdo->prepare("
                SELECT
                    c.id,
                    c.name,
                    c.description,
                    GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects,
                    (SELECT COUNT(*) FROM student_enrollments se
                     WHERE se.class_id = c.id AND se.student_id = ?) AS is_enrolled
                FROM student_classes c
                LEFT JOIN subject_classes sc ON sc.class_id = c.id
                LEFT JOIN subjects s ON s.id = sc.subject_id AND s.deleted_at IS NULL
                WHERE c.deleted_at IS NULL AND c.id IN ($in)
                GROUP BY c.id, c.name, c.description
                ORDER BY c.name
            ");
            $stmt->execute(array_merge([$studentId], $classIds));
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('campus_student_teacher_profile classes: ' . $e->getMessage());
        }
    }

    $upcoming = [];
    $statusSql = campus_lesson_status_sql('tt');
    try {
        $stmt = $pdo->prepare("
            SELECT tt.date, tt.start_time, tt.end_time,
                   c.name AS class_name, s.name AS subject_name, r.name AS room_name
            FROM timetable tt
            JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
            JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
            LEFT JOIN rooms r ON r.id = tt.room_id AND r.deleted_at IS NULL
            WHERE tt.teacher_id = ?
              AND tt.deleted_at IS NULL
              AND tt.date >= CURDATE()
              AND {$statusSql}
            ORDER BY tt.date, tt.start_time
            LIMIT 16
        ");
        $stmt->execute([$teacherId]);
        $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('campus_student_teacher_profile upcoming: ' . $e->getMessage());
    }

    return ['teacher' => $teacher, 'classes' => $classes, 'upcoming' => $upcoming];
}

/**
 * OnePay “Pay Now” form for a lesson (live class or recording).
 *
 * @param array{timetable_id?:int,recording_id?:int,return?:string,button_class?:string,label?:string,action?:string,student_id?:int,disabled?:bool} $opts
 */
function student_pay_now_form(array $opts = []): void
{
    $timetableId = (int)($opts['timetable_id'] ?? 0);
    $recordingId = (int)($opts['recording_id'] ?? 0);
    $returnTo = (string)($opts['return'] ?? ($recordingId > 0 ? 'recording' : 'classroom'));
    if (!in_array($returnTo, ['classroom', 'recording', 'fees', 'class'], true)) {
        $returnTo = 'recording';
    }
    $buttonClass = (string)($opts['button_class'] ?? 'btn btn-primary btn-lg');
    $label = (string)($opts['label'] ?? 'Pay Now');
    $action = (string)($opts['action'] ?? (rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/student/pay_lesson.php'));
    $studentId = (int)($opts['student_id'] ?? 0);
    $disabled = !empty($opts['disabled']);
    if ($disabled) {
        ?>
        <button class="<?= htmlspecialchars($buttonClass) ?>" type="button" disabled><?= htmlspecialchars($label) ?></button>
        <?php
        return;
    }
    ?>
    <form method="post" action="<?= htmlspecialchars($action) ?>">
        <?= function_exists('csrf_field') ? csrf_field() : '' ?>
        <input type="hidden" name="method" value="onepay">
        <?php if ($timetableId > 0): ?>
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <?php endif; ?>
        <?php if ($recordingId > 0): ?>
            <input type="hidden" name="recording_id" value="<?= $recordingId ?>">
        <?php endif; ?>
        <?php if ($studentId > 0): ?>
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <?php endif; ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">
        <button class="<?= htmlspecialchars($buttonClass) ?>" type="submit"><?= htmlspecialchars($label) ?></button>
    </form>
    <?php
}

