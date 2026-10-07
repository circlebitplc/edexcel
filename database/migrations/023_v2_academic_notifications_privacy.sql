-- V2: Academic topic progress, exam revision plans, unified notifications, push subscriptions

CREATE TABLE IF NOT EXISTS student_topic_progress (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NULL,
    unit_id INT NULL,
    topic_key VARCHAR(120) NOT NULL,
    topic_label VARCHAR(255) NOT NULL,
    status ENUM('not_started','in_progress','completed','weak','strong','revision_required') NOT NULL DEFAULT 'not_started',
    score_percent DECIMAL(5,2) NULL,
    source VARCHAR(64) NULL,
    last_activity_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stp_student_topic (student_id, topic_key),
    KEY idx_stp_student_status (student_id, status),
    KEY idx_stp_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_revision_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    qualification_label VARCHAR(255) NULL,
    subject_label VARCHAR(255) NULL,
    exam_date DATE NULL,
    title VARCHAR(255) NOT NULL,
    plan_json JSON NOT NULL,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_by VARCHAR(32) NOT NULL DEFAULT 'student',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_student (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_center (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    audience ENUM('student','teacher','admin','parent') NOT NULL,
    user_id INT NULL,
    parent_id INT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'system',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    link_url VARCHAR(1000) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nc_audience_user (audience, user_id, is_read, created_at),
    KEY idx_nc_parent (parent_id, is_read, created_at),
    KEY idx_nc_category (category, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    audience ENUM('student','teacher','admin','parent') NOT NULL,
    user_id INT NULL,
    parent_id INT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(255) NULL,
    auth_key VARCHAR(255) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_endpoint (endpoint_hash),
    KEY idx_push_audience_user (audience, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_deletion_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_type VARCHAR(32) NOT NULL,
    requester_id INT NULL,
    email_or_phone VARCHAR(255) NULL,
    reason TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    processed_at DATETIME NULL,
    processed_by INT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ddr_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
