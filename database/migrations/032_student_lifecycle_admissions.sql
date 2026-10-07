-- Student lifecycle & admissions intelligence. Additive; reuses admission_applications,
-- admission_documents, enrollment_history, student_enrollments, waitlist, payments.

CREATE TABLE IF NOT EXISTS admission_leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    whatsapp VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    programme_label VARCHAR(160) NULL,
    subject_id INT NULL,
    qualification_label VARCHAR(120) NULL,
    location_pref VARCHAR(120) NULL,
    delivery_pref ENUM('onsite','online','either') NOT NULL DEFAULT 'either',
    academic_year VARCHAR(40) NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'other',
    notes VARCHAR(1000) NULL,
    assigned_to INT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'NEW',
    application_id BIGINT UNSIGNED NULL,
    student_id INT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leads_status (status, created_at),
    KEY idx_leads_assigned (assigned_to, status),
    KEY idx_leads_phone (phone),
    KEY idx_leads_source (source, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_followups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NULL,
    application_id BIGINT UNSIGNED NULL,
    channel ENUM('phone','whatsapp','sms','note','appointment','other') NOT NULL DEFAULT 'note',
    reason VARCHAR(255) NOT NULL,
    last_contact_at DATETIME NULL,
    due_at DATETIME NOT NULL,
    next_action VARCHAR(255) NULL,
    assigned_to INT NULL,
    status ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
    notes VARCHAR(1000) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_followups_due (status, due_at, assigned_to),
    KEY idx_followups_lead (lead_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_offers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    offer_no VARCHAR(40) NOT NULL,
    programme_label VARCHAR(160) NULL,
    subject_label VARCHAR(160) NULL,
    class_id INT NULL,
    teacher_label VARCHAR(160) NULL,
    location_label VARCHAR(160) NULL,
    schedule_text VARCHAR(500) NULL,
    fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_instructions TEXT NULL,
    terms_text TEXT NULL,
    status ENUM('issued','accepted','declined','expired') NOT NULL DEFAULT 'issued',
    issued_by INT NULL,
    accepted_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_offer_no (offer_no),
    KEY idx_offers_app (application_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    payment_id INT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    method VARCHAR(40) NOT NULL DEFAULT 'cash',
    reference VARCHAR(120) NULL,
    status ENUM('pending','verified','failed','duplicate') NOT NULL DEFAULT 'pending',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admission_payment_ref (application_id, method, reference),
    KEY idx_admission_payments_app (application_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_agreements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NULL,
    student_id INT NULL,
    document_key VARCHAR(80) NOT NULL,
    version VARCHAR(40) NOT NULL,
    accepted TINYINT(1) NOT NULL DEFAULT 0,
    accepted_by VARCHAR(120) NULL,
    accepted_at DATETIME NULL,
    ip_address VARCHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_agreements_app (application_id, document_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_onboarding (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    checklist_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_onboarding_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_lifecycle_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NULL,
    application_id BIGINT UNSIGNED NULL,
    student_id INT NULL,
    event_type VARCHAR(80) NOT NULL,
    detail VARCHAR(500) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lifecycle_app (application_id, created_at),
    KEY idx_lifecycle_student (student_id, created_at),
    KEY idx_lifecycle_lead (lead_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_permissions (
    user_id INT NOT NULL,
    permission VARCHAR(80) NOT NULL,
    granted_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission),
    KEY idx_staff_perm (permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_analytics_cache (
    cache_key VARCHAR(80) NOT NULL,
    payload_json JSON NOT NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra application columns are added by AdmissionLifecycleService::ensureSchema()
-- so this migration remains re-runnable on MySQL 8.
