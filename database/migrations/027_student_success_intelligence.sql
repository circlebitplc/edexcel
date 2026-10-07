-- Explainable student-success and retention snapshots. Additive; operational records remain authoritative.
CREATE TABLE IF NOT EXISTS student_success_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    success_level ENUM('HEALTHY','WATCH','AT_RISK','CRITICAL') NOT NULL DEFAULT 'HEALTHY',
    success_score DECIMAL(7,2) NOT NULL DEFAULT 0,
    attendance_recent DECIMAL(6,2) NULL,
    attendance_previous DECIMAL(6,2) NULL,
    academic_recent DECIMAL(6,2) NULL,
    academic_previous DECIMAL(6,2) NULL,
    homework_percent DECIMAL(6,2) NULL,
    outstanding_amount DECIMAL(12,2) NULL,
    reasons_json JSON NOT NULL,
    retention_level ENUM('LOW','MEDIUM','HIGH') NOT NULL DEFAULT 'LOW',
    retention_reasons_json JSON NOT NULL,
    calculation_version VARCHAR(30) NOT NULL DEFAULT '1',
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_success_latest (student_id),
    KEY idx_success_level_score (success_level, success_score),
    KEY idx_success_retention (retention_level, calculated_at),
    KEY idx_success_calculated (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_success_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    success_level VARCHAR(20) NOT NULL,
    success_score DECIMAL(7,2) NOT NULL,
    reasons_json JSON NOT NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_success_history_student (student_id, calculated_at),
    KEY idx_success_history_level (success_level, calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
