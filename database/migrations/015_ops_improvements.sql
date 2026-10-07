-- 015_ops_improvements.sql
-- Unified fees, cash handover, parent login, waitlist offers, job health, gradebook publish.

CREATE TABLE IF NOT EXISTS cash_handovers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS waitlist_offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    token CHAR(32) NOT NULL,
    status ENUM('offered','accepted','expired','declined') NOT NULL DEFAULT 'offered',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_waitlist_offer_token (token),
    INDEX idx_waitlist_offer_open (class_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone VARCHAR(20) NOT NULL,
    name VARCHAR(120) NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_parent_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_students (
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (parent_id, student_id),
    INDEX idx_parent_students_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_otps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone VARCHAR(20) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_parent_otps_phone (phone, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_fee_pings (
    student_id INT NOT NULL,
    ping_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, ping_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_job_runs (
    job_name VARCHAR(80) NOT NULL,
    last_started_at DATETIME NULL,
    last_finished_at DATETIME NULL,
    last_ok TINYINT(1) NOT NULL DEFAULT 1,
    last_message VARCHAR(500) NULL,
    last_alert_at DATETIME NULL,
    PRIMARY KEY (job_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('admin_password_requires_otp', '1'),
    ('schema_version', '015')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
