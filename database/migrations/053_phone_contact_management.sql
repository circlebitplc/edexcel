-- Migration 053: Phone Contact Management and Controlled SMS System
-- Supports WhatsApp group contact collection, academic associations, deduplication, and teacher SMS controls

CREATE TABLE IF NOT EXISTS phone_contacts (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    normalized_phone        VARCHAR(20)     NOT NULL COMMENT 'Canonical Sri Lankan format 947XXXXXXXX',
    phone                   VARCHAR(30)     NOT NULL COMMENT 'Original or formatted telephone number',
    name                    VARCHAR(150)    NULL DEFAULT NULL COMMENT 'Optional contact name',
    school                  VARCHAR(190)    NULL DEFAULT NULL COMMENT 'Optional school',
    sms_opt_out             TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = opted out from marketing/bulk SMS',
    status                  VARCHAR(20)     NOT NULL DEFAULT 'active' COMMENT 'active, inactive, archived',
    notes                   TEXT            NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_phone_contacts_norm (normalized_phone),
    KEY idx_pc_name (name),
    KEY idx_pc_school (school),
    KEY idx_pc_optout (sms_opt_out),
    KEY idx_pc_status (status),
    KEY idx_pc_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phone_contact_records (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id              BIGINT UNSIGNED NOT NULL,
    exam_year               INT             NOT NULL,
    exam_type               VARCHAR(50)     NOT NULL COMMENT 'e.g. IGCSE, IAL',
    location                VARCHAR(100)    NOT NULL COMMENT 'e.g. Kandy, Kurunegala, Online',
    school                  VARCHAR(190)    NULL DEFAULT NULL,
    source                  VARCHAR(255)    NOT NULL DEFAULT 'whatsapp_csv',
    source_group            VARCHAR(255)    NULL DEFAULT NULL COMMENT 'WhatsApp group name or CSV label',
    import_id               BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_academic (contact_id, exam_year, exam_type, location),
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
    uploaded_by             INT             NULL COMMENT 'users.id of admin/staff',
    source_group            VARCHAR(255)    NULL,
    total_rows              INT             NOT NULL DEFAULT 0,
    valid_rows              INT             NOT NULL DEFAULT 0,
    invalid_rows            INT             NOT NULL DEFAULT 0,
    new_contacts            INT             NOT NULL DEFAULT 0,
    existing_contacts       INT             NOT NULL DEFAULT 0,
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
    gateway                 VARCHAR(50)     NOT NULL DEFAULT 'ipromo' COMMENT 'Strictly restricted to ipromo',
    monthly_limit           INT             NOT NULL DEFAULT 0 COMMENT 'Monthly SMS units quota',
    can_send_sms            TINYINT(1)      NOT NULL DEFAULT 0,
    can_view_contacts       TINYINT(1)      NOT NULL DEFAULT 0,
    can_select_contacts     TINYINT(1)      NOT NULL DEFAULT 0,
    allowed_exam_years      VARCHAR(255)    NULL DEFAULT NULL,
    allowed_exam_types      VARCHAR(255)    NULL DEFAULT NULL,
    allowed_locations       VARCHAR(255)    NULL DEFAULT NULL,
    updated_by              INT             NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tsp_user (teacher_user_id),
    KEY idx_tsp_teacher (teacher_id),
    KEY idx_tsp_sms_access (sms_access)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('phone_contact_locations', 'Kandy,Kurunegala,Online');
