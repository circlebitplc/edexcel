-- 008_class_recordings_onepay.sql
-- Class recordings (timetable-lesson scoped), Bunny Stream metadata,
-- per-student lesson fees, OnePay transactions, teacher video library.

CREATE TABLE IF NOT EXISTS class_recordings (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_recording_assets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_lesson_fees (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transaction_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pte_txn (transaction_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_video_library (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_video_library_students (
    video_id INT NOT NULL,
    student_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (video_id, student_id),
    INDEX idx_tvls_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recording_access_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
