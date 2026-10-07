<?php
declare(strict_types=1);

const OPS_SCHEMA_VERSION = '043';

function ops_schema_flag_path(): string
{
    return dirname(__DIR__) . '/data/schema_ok';
}

function ops_schema_should_heal(): bool
{
    $env = strtolower((string)(defined('APP_ENV') ? APP_ENV : (getenv('APP_ENV') ?: 'production')));
    if ($env !== 'production') {
        return true;
    }
    if (PHP_SAPI === 'cli') {
        return true;
    }
    $flag = ops_schema_flag_path();
    if (!is_file($flag)) {
        return true;
    }
    $stored = trim((string)@file_get_contents($flag));
    return $stored !== OPS_SCHEMA_VERSION;
}

function ops_schema_mark_ok(): void
{
    $dir = dirname(ops_schema_flag_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents(ops_schema_flag_path(), OPS_SCHEMA_VERSION);
}

function ops_setting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}

function ops_get_setting(PDO $pdo, string $key, string $default = ''): string
{
    return ops_setting($pdo, $key, $default);
}

function ops_save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

function ops_job_start(PDO $pdo, string $job): void
{
    try {
        ensure_ops_schema($pdo);
        $pdo->prepare("
            INSERT INTO system_job_runs (job_name, last_started_at, last_ok, last_message)
            VALUES (?, NOW(), 1, NULL)
            ON DUPLICATE KEY UPDATE last_started_at = NOW()
        ")->execute([$job]);
    } catch (Throwable $e) {
        error_log('ops_job_start: ' . $e->getMessage());
    }
}

function ops_job_finish(PDO $pdo, string $job, bool $ok, string $message = ''): void
{
    try {
        ensure_ops_schema($pdo);
        $pdo->prepare("
            INSERT INTO system_job_runs (job_name, last_finished_at, last_ok, last_message)
            VALUES (?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE
                last_finished_at = NOW(),
                last_ok = VALUES(last_ok),
                last_message = VALUES(last_message)
        ")->execute([$job, $ok ? 1 : 0, mb_substr($message, 0, 500)]);
    } catch (Throwable $e) {
        error_log('ops_job_finish: ' . $e->getMessage());
    }
}

function ensure_ops_schema(PDO $pdo): void
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
        "CREATE TABLE IF NOT EXISTS cash_handovers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NULL,
            collected_by INT NOT NULL,
            period_date DATE NOT NULL,
            amount_expected DECIMAL(12,2) NOT NULL DEFAULT 0,
            amount_handed DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('open','handed','received','disputed') NOT NULL DEFAULT 'handed',
            notes VARCHAR(500) NULL,
            handed_at DATETIME NULL,
            received_by INT NULL,
            received_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_handover_date (period_date, status),
            INDEX idx_handover_teacher (teacher_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS waitlist_offers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            token CHAR(32) NOT NULL,
            status ENUM('offered','accepted','expired','declined') NOT NULL DEFAULT 'offered',
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_waitlist_offer_token (token),
            INDEX idx_waitlist_offer_open (class_id, status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS parent_accounts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL,
            name VARCHAR(120) NULL,
            last_login_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_parent_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS parent_students (
            parent_id INT NOT NULL,
            student_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (parent_id, student_id),
            INDEX idx_parent_students_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS parent_otps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_parent_otps_phone (phone, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS parent_fee_pings (
            student_id INT NOT NULL,
            ping_date DATE NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, ping_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS system_job_runs (
            job_name VARCHAR(80) NOT NULL,
            last_started_at DATETIME NULL,
            last_finished_at DATETIME NULL,
            last_ok TINYINT(1) NOT NULL DEFAULT 1,
            last_message VARCHAR(500) NULL,
            last_alert_at DATETIME NULL,
            PRIMARY KEY (job_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS student_homework_submissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            homework_id BIGINT UNSIGNED NOT NULL,
            student_id INT NOT NULL,
            note TEXT NULL,
            file_url VARCHAR(1000) NULL,
            file_name VARCHAR(255) NULL,
            status ENUM('submitted','returned','done') NOT NULL DEFAULT 'submitted',
            score DECIMAL(7,2) NULL,
            max_score DECIMAL(7,2) NULL,
            teacher_feedback TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homework_student (homework_id, student_id),
            KEY idx_hw_sub_student (student_id, submitted_at),
            KEY idx_hw_sub_homework (homework_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS finance_adjustments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            type ENUM('discount','scholarship','waiver','refund','charge') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            reason VARCHAR(500) NOT NULL,
            reference VARCHAR(120) NULL,
            approved_by INT NULL,
            status ENUM('pending','approved','rejected','applied') NOT NULL DEFAULT 'pending',
            applied_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_finance_adjustments_student (student_id, status, created_at),
            KEY idx_finance_adjustments_type (type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS finance_expenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expense_date DATE NOT NULL,
            category VARCHAR(80) NOT NULL,
            description VARCHAR(500) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_method ENUM('cash','bank','card','other') NOT NULL DEFAULT 'cash',
            receipt_path VARCHAR(1000) NULL,
            created_by INT NULL,
            approved_by INT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_finance_expenses_date (expense_date, status),
            KEY idx_finance_expenses_category (category, expense_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS finance_reconciliations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reconciliation_date DATE NOT NULL,
            payment_method ENUM('cash','onepay','bank','all') NOT NULL,
            expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            actual_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            difference_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes VARCHAR(1000) NULL,
            reconciled_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_finance_reconciliation (reconciliation_date, payment_method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_payment_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            payment_id INT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_date DATE NOT NULL,
            method VARCHAR(40) NOT NULL DEFAULT 'cash',
            reference VARCHAR(120) NULL,
            notes VARCHAR(500) NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_teacher_payment_events_teacher (teacher_id, payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS admission_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_no VARCHAR(40) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            date_of_birth DATE NULL,
            parent_name VARCHAR(255) NULL,
            parent_phone VARCHAR(50) NULL,
            selected_subjects_json JSON NULL,
            selected_classes_json JSON NULL,
            status ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
            student_id INT NULL,
            admission_notes TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admission_application_no (application_no),
            KEY idx_admission_status (status, created_at),
            KEY idx_admission_phone (phone, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS admission_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id BIGINT UNSIGNED NOT NULL,
            document_type VARCHAR(80) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            storage_path VARCHAR(1000) NOT NULL,
            mime_type VARCHAR(120) NULL,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admission_documents_application (application_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS enrollment_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            class_id INT NULL,
            subject_id INT NULL,
            action ENUM('registered','approved','enrolled','transferred','suspended','completed','withdrawn') NOT NULL,
            old_value JSON NULL,
            new_value JSON NULL,
            notes VARCHAR(1000) NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_enrollment_history_student (student_id, created_at),
            KEY idx_enrollment_history_class (class_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS class_capacity_settings (
            class_id INT NOT NULL,
            max_students INT NOT NULL DEFAULT 30,
            override_allowed TINYINT(1) NOT NULL DEFAULT 0,
            warning_percent TINYINT UNSIGNED NOT NULL DEFAULT 85,
            updated_by INT NULL,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_availability (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            weekday TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            available TINYINT(1) NOT NULL DEFAULT 1,
            notes VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_teacher_availability (teacher_id, weekday)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_leave (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason VARCHAR(500) NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_teacher_leave (teacher_id, start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS attendance_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            threshold_percent DECIMAL(5,2) NOT NULL,
            attendance_percent DECIMAL(5,2) NOT NULL,
            alert_type VARCHAR(40) NOT NULL DEFAULT 'low_attendance',
            last_notified_at DATETIME NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_attendance_alert_student_type (student_id, alert_type),
            KEY idx_attendance_alerts_open (resolved_at, attendance_percent)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS report_cards (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            term_label VARCHAR(80) NOT NULL,
            academic_year VARCHAR(20) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            overall_comment TEXT NULL,
            rank_position INT NULL,
            published_by INT NULL,
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_report_card_student_term (student_id, term_label, academic_year),
            KEY idx_report_cards_status (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS report_card_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_card_id BIGINT UNSIGNED NOT NULL,
            subject_id INT NULL,
            subject_name VARCHAR(150) NOT NULL,
            test_mark DECIMAL(6,2) NULL,
            exam_mark DECIMAL(6,2) NULL,
            homework_percent DECIMAL(6,2) NULL,
            attendance_percent DECIMAL(6,2) NULL,
            overall_percent DECIMAL(6,2) NULL,
            grade VARCHAR(20) NULL,
            teacher_comment TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_report_card_item_subject (report_card_id, subject_id),
            KEY idx_report_card_items_card (report_card_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS paper_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            official_exam_id INT NULL,
            paper_label VARCHAR(255) NOT NULL,
            topic_label VARCHAR(255) NULL,
            attempt_date DATE NOT NULL,
            score DECIMAL(7,2) NULL,
            max_score DECIMAL(7,2) NULL,
            grade VARCHAR(20) NULL,
            notes VARCHAR(1000) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_paper_attempts_student (student_id, attempt_date),
            KEY idx_paper_attempts_topic (student_id, topic_label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS communication_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            channel ENUM('whatsapp','sms','email','in_app','push') NOT NULL,
            subject VARCHAR(255) NULL,
            body TEXT NOT NULL,
            variables_json JSON NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_communication_template_name_channel (name, channel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS communication_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel ENUM('whatsapp','sms','email','in_app','push') NOT NULL,
            audience_type VARCHAR(40) NOT NULL,
            audience_id INT NULL,
            recipient VARCHAR(255) NULL,
            template_id BIGINT UNSIGNED NULL,
            subject VARCHAR(255) NULL,
            body TEXT NOT NULL,
            status ENUM('scheduled','queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            error_message VARCHAR(1000) NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_communication_messages_status (status, scheduled_at),
            KEY idx_communication_messages_recipient (recipient, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS import_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            dataset VARCHAR(40) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            storage_path VARCHAR(1000) NULL,
            status ENUM('uploaded','validated','confirmed','completed','failed') NOT NULL DEFAULT 'uploaded',
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            imported_count INT UNSIGNED NOT NULL DEFAULT 0,
            error_count INT UNSIGNED NOT NULL DEFAULT 0,
            errors_json JSON NULL,
            created_by INT NULL,
            confirmed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_import_jobs_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS scheduled_reports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_key VARCHAR(80) NOT NULL,
            frequency ENUM('daily','weekly','monthly') NOT NULL,
            recipients_json JSON NOT NULL,
            channel ENUM('whatsapp','sms','email','in_app') NOT NULL DEFAULT 'in_app',
            active TINYINT(1) NOT NULL DEFAULT 1,
            next_run_at DATETIME NULL,
            last_run_at DATETIME NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_scheduled_reports_due (active, next_run_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS performance_metrics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id VARCHAR(36) NULL,
            route VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            duration_ms DECIMAL(10,2) NOT NULL,
            memory_bytes BIGINT UNSIGNED NULL,
            status_code SMALLINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_performance_metrics_created (created_at),
            KEY idx_performance_metrics_route (route, created_at),
            KEY idx_performance_metrics_slow (duration_ms, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS parent_link_requests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            parent_id INT NOT NULL,
            student_id INT NOT NULL,
            status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            reviewed_by INT NULL,
            rejection_reason VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_plr_status (status, requested_at),
            INDEX idx_plr_parent (parent_id, status),
            INDEX idx_plr_student (student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS parent_invitations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            parent_email VARCHAR(255) NOT NULL,
            student_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            used_by_parent_id INT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_parent_invite_token (token_hash),
            INDEX idx_parent_invite_email (parent_email, student_id),
            INDEX idx_parent_invite_student (student_id, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS portal_phone_otps (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_no VARCHAR(40) NOT NULL,
            requester_type ENUM('student','parent','teacher','admin') NOT NULL,
            requester_id INT NOT NULL,
            category VARCHAR(60) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            status ENUM('open','assigned','in_progress','waiting_user','resolved','closed') NOT NULL DEFAULT 'open',
            assigned_to INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_support_ticket_no (ticket_no),
            KEY idx_support_ticket_status (status, priority, updated_at),
            KEY idx_support_ticket_requester (requester_type, requester_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS support_ticket_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            author_type ENUM('student','parent','teacher','admin') NOT NULL,
            author_id INT NOT NULL,
            message TEXT NOT NULL,
            attachment_path VARCHAR(1000) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_support_ticket_messages_ticket (ticket_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS service_request_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            request_type VARCHAR(60) NOT NULL,
            entity_type VARCHAR(60) NULL,
            entity_id INT NULL,
            lifecycle_json JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_service_request_ticket (ticket_id),
            KEY idx_service_request_type (request_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_sms_switch_audit (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_user_id   INT NOT NULL COMMENT 'users.id of administrator who toggled the switch',
            previous_status TINYINT(1) NOT NULL COMMENT '0 = disabled, 1 = enabled',
            new_status      TINYINT(1) NOT NULL COMMENT '0 = disabled, 1 = enabled',
            reason          TEXT NULL COMMENT 'Admin supplied explanation/reason',
            ip_address      VARCHAR(45) NULL COMMENT 'Client IPv4/IPv6',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_switch_audit_admin (admin_user_id),
            KEY idx_switch_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS phone_whatsapp_group_mappings (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            raw_group_name       VARCHAR(190) NOT NULL,
            canonical_group_name VARCHAR(190) NOT NULL,
            created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_raw_group (raw_group_name),
            KEY idx_canonical_group (canonical_group_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS admin_passkeys (
            id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id            INT NOT NULL COMMENT 'users.id of the administrator',
            credential_id      VARCHAR(255) NOT NULL,
            public_key         TEXT NOT NULL,
            user_handle        VARCHAR(128) NULL,
            name               VARCHAR(100) NOT NULL DEFAULT 'Passkey',
            attestation_format VARCHAR(64) NULL,
            sign_count         BIGINT UNSIGNED NOT NULL DEFAULT 0,
            transports         VARCHAR(255) NULL,
            last_used_at       DATETIME NULL,
            revoked_at         DATETIME NULL,
            created_ip         VARCHAR(45) NULL,
            user_agent         VARCHAR(255) NULL,
            created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admin_passkey_cred (credential_id),
            KEY idx_admin_passkeys_user (user_id, revoked_at),
            KEY idx_admin_passkeys_last_used (last_used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS admin_face_credentials (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id              INT NOT NULL,
            template_encrypted   MEDIUMTEXT NOT NULL,
            sample_count         INT NOT NULL DEFAULT 5,
            status               ENUM('active', 'disabled', 'revoked') NOT NULL DEFAULT 'active',
            enrolled_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_verified_at     DATETIME NULL,
            last_verification_ip VARCHAR(45) NULL,
            metadata             JSON NULL,
            created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admin_face_user (user_id),
            KEY idx_admin_face_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS authentication_audit (
            id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id               INT NULL,
            authentication_method VARCHAR(32) NOT NULL,
            success               TINYINT(1) NOT NULL,
            failure_reason        VARCHAR(255) NULL,
            ip_address            VARCHAR(45) NULL,
            user_agent            VARCHAR(255) NULL,
            session_reference     VARCHAR(64) NULL,
            metadata              JSON NULL,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_auth_audit_user (user_id, created_at),
            KEY idx_auth_audit_method (authentication_method, created_at),
            KEY idx_auth_audit_ip (ip_address, created_at),
            KEY idx_auth_audit_success (success, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS admin_biometric_challenges (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            challenge_type    ENUM('passkey_reg', 'passkey_auth', 'face_enrol', 'face_auth') NOT NULL,
            user_id           INT NOT NULL,
            challenge_token   VARCHAR(128) NOT NULL,
            challenge_payload TEXT NOT NULL,
            ip_address        VARCHAR(45) NULL,
            used_at           DATETIME NULL,
            expires_at        DATETIME NOT NULL,
            created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bio_challenge_token (challenge_token),
            KEY idx_bio_challenge_lookup (challenge_token, expires_at, used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('ops schema: ' . $e->getMessage());
        }
    }

    $alters = [
        ['payment_transactions', 'handover_id', 'INT NULL'],
        ['student_progress', 'published', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['student_materials', 'pearson_unit', 'VARCHAR(80) NULL'],
        ['student_homework', 'pearson_unit', 'VARCHAR(80) NULL'],
        ['student_profiles', 'parent_view_token_rotated_at', 'DATETIME NULL'],
        ['student_events', 'created_by', 'INT NULL'],
        ['users', 'theme_preference', "VARCHAR(50) NOT NULL DEFAULT 'dark'"],
        ['users', 'google_id', 'VARCHAR(64) NULL'],
        ['users', 'google_email', 'VARCHAR(255) NULL'],
        ['users', 'profile_image', 'VARCHAR(500) NULL'],
        ['users', 'account_status', "ENUM('pending','active','suspended','disabled') NULL"],
        ['parent_accounts', 'email', 'VARCHAR(255) NULL'],
        ['parent_accounts', 'google_id', 'VARCHAR(64) NULL'],
        ['parent_accounts', 'profile_image', 'VARCHAR(500) NULL'],
        ['parent_accounts', 'status', "ENUM('pending','active','suspended','disabled') NOT NULL DEFAULT 'active'"],
        ['parent_accounts', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ['phone_contact_records', 'canonical_source_group', 'VARCHAR(190) NULL'],
        ['bulk_sms_campaigns', 'idempotency_key', 'VARCHAR(100) NULL'],
        ['settings', 'setting_key', null],
    ];
    foreach ($alters as [$table, $column, $definition]) {
        if ($definition === null) {
            continue;
        }
        try {
            if (function_exists('campus_column_exists') && !campus_column_exists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            error_log("ops alter {$table}.{$column}: " . $e->getMessage());
        }
    }

    // Google-only parents may have NULL phone; keep unique index on phone.
    try {
        if (function_exists('campus_column_exists') && campus_column_exists($pdo, 'parent_accounts', 'phone')) {
            $pdo->exec('ALTER TABLE parent_accounts MODIFY phone VARCHAR(20) NULL');
        }
    } catch (Throwable $e) {
        error_log('ops alter parent_accounts.phone null: ' . $e->getMessage());
    }

    // Google-only students may have NULL whatsapp_number; allow multiple NULLs in unique index.
    try {
        if (function_exists('campus_column_exists') && campus_column_exists($pdo, 'student_profiles', 'whatsapp_number')) {
            $pdo->exec('ALTER TABLE student_profiles MODIFY whatsapp_number VARCHAR(32) NULL DEFAULT NULL');
            $pdo->exec("UPDATE student_profiles SET whatsapp_number = NULL WHERE whatsapp_number = ''");
        }
    } catch (Throwable $e) {
        error_log('ops alter student_profiles.whatsapp_number null: ' . $e->getMessage());
    }

    $indexHelpers = [
        ['users', 'uq_users_google_id', 'UNIQUE KEY uq_users_google_id (google_id)'],
        ['parent_accounts', 'uq_parent_google_id', 'UNIQUE KEY uq_parent_google_id (google_id)'],
        ['parent_accounts', 'uq_parent_email', 'UNIQUE KEY uq_parent_email (email)'],
    ];
    foreach ($indexHelpers as [$table, $indexName, $definition]) {
        try {
            if (!ops_index_exists($pdo, $table, $indexName)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD {$definition}");
            }
        } catch (Throwable $e) {
            error_log("ops index {$table}.{$indexName}: " . $e->getMessage());
        }
    }

    // Teacher OAuth Migration (1A)
    try {
        if (!ops_column_exists($pdo, 'users', 'teacher_oauth_status')) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `teacher_oauth_status` ENUM('not_linked', 'linked', 'requires_review', 'disabled') NOT NULL DEFAULT 'not_linked'");
        }
        if (!ops_column_exists($pdo, 'users', 'teacher_oauth_linked_at')) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `teacher_oauth_linked_at` DATETIME NULL DEFAULT NULL");
        }
        if (!ops_column_exists($pdo, 'users', 'teacher_oauth_linked_by')) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `teacher_oauth_linked_by` INT NULL DEFAULT NULL");
        }
        if (!ops_column_exists($pdo, 'users', 'teacher_oauth_notes')) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `teacher_oauth_notes` TEXT NULL DEFAULT NULL");
        }

        // Initialize teacher_oauth_status for existing teachers
        $pdo->exec("
            UPDATE users
            SET teacher_oauth_status = IF(google_id IS NOT NULL AND TRIM(google_id) != '', 'linked', 'not_linked')
            WHERE role = 'teacher' AND (teacher_oauth_status IS NULL OR teacher_oauth_status = '')
        ");

        // Teacher OAuth invites table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS teacher_oauth_invites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                expected_email VARCHAR(255) NULL,
                expires_at DATETIME NOT NULL,
                created_by INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                used_at DATETIME NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_token_hash (token_hash),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Global default setting for staff & teacher legacy login (0 = legacy allowed, 1 = disabled)
        if (ops_setting($pdo, 'teacher_legacy_login_disabled', '') === '') {
            ops_save_setting($pdo, 'teacher_legacy_login_disabled', '0');
        }
        if (ops_setting($pdo, 'staff_legacy_login_disabled', '') === '') {
            ops_save_setting($pdo, 'staff_legacy_login_disabled', '0');
        }
    } catch (Throwable $e) {
        error_log('ops teacher oauth schema error: ' . $e->getMessage());
    }
}

function ops_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function ops_index_exists(PDO $pdo, string $table, string $indexName): bool
{
    try {
        $stmt = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function ops_secret_setting_keys(): array
{
    return [
        'meta_access_token',
        'meta_app_secret',
        'meta_webhook_verify_token',
        'onepay_app_token',
        'onepay_hash_salt',
        'bunny_account_api_key',
        'bunny_api_key',
        'bunny_token_auth_key',
        'bunny_webhook_secret',
        'livekit_api_secret',
        'livekit_s3_secret',
        'evolution_api_key',
        'evolution_webhook_secret',
        'sms_gateway_password',
        'sms_gateway_token',
        'google_client_secret',
    ];
}

function ops_admin_alert(PDO $pdo, string $title, string $body): void
{
    try {
        if (!function_exists('whatsapp_sender')) {
            require_once __DIR__ . '/whatsapp_gateway.php';
        }
        if (!function_exists('evolution_setting')) {
            require_once __DIR__ . '/evolution.php';
        }
        $office = '';
        if (function_exists('campus_format_wa_phone')) {
            $office = campus_format_wa_phone(evolution_setting($pdo, 'evolution_admin_number'));
        }
        if ($office === '') {
            return;
        }
        whatsapp_sender($pdo)->sendText($office, "⚠️ *{$title}*\n\n{$body}\n\nEdexcel College");
    } catch (Throwable $e) {
        error_log('ops_admin_alert: ' . $e->getMessage());
    }
}
