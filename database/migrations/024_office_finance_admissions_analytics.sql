-- Additive Office V2: finance, admissions, capacity, analytics, communications and imports.
-- Existing payment verification and legacy tables remain the source of truth for payments.

CREATE TABLE IF NOT EXISTS finance_adjustments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_expenses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_reconciliations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_payment_events (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_applications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_documents (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollment_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_capacity_settings (
    class_id INT NOT NULL,
    max_students INT NOT NULL DEFAULT 30,
    override_allowed TINYINT(1) NOT NULL DEFAULT 0,
    warning_percent TINYINT UNSIGNED NOT NULL DEFAULT 85,
    updated_by INT NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_availability (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_leave (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_alerts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_cards (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_card_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paper_attempts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_templates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_messages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_jobs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_reports (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performance_metrics (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('attendance_warning_threshold', '80'),
    ('attendance_critical_threshold', '70'),
    ('finance_currency', 'LKR'),
    ('report_card_ranking_enabled', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
