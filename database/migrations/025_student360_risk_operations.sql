-- Student 360 / risk / class operations phase. Additive and auditable.

CREATE TABLE IF NOT EXISTS student_risk_assessments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    risk_level ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'LOW',
    risk_score DECIMAL(7,2) NOT NULL DEFAULT 0,
    attendance_percent DECIMAL(6,2) NULL,
    academic_percent DECIMAL(6,2) NULL,
    homework_percent DECIMAL(6,2) NULL,
    outstanding_amount DECIMAL(12,2) NULL,
    reasons_json JSON NOT NULL,
    calculation_version VARCHAR(30) NOT NULL DEFAULT '1',
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME NULL,
    acknowledged_by INT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_risk_latest (student_id),
    KEY idx_student_risk_level (risk_level, calculated_at),
    KEY idx_student_risk_score (risk_score, calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_interventions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    risk_assessment_id BIGINT UNSIGNED NULL,
    intervention_type VARCHAR(60) NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    notes TEXT NULL,
    assigned_to INT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    due_date DATE NULL,
    status ENUM('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
    follow_up TEXT NULL,
    resolution TEXT NULL,
    created_by INT NULL,
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_interventions_student (student_id, status, created_at),
    KEY idx_student_interventions_due (status, due_date),
    KEY idx_student_interventions_assignee (assigned_to, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_substitutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timetable_id INT NOT NULL,
    original_teacher_id INT NOT NULL,
    substitute_teacher_id INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('assigned','completed','cancelled') NOT NULL DEFAULT 'assigned',
    assigned_by INT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_class_substitution_lesson (timetable_id, status),
    KEY idx_class_substitution_teacher (substitute_teacher_id, assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_makeup_lessons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_timetable_id INT NOT NULL,
    makeup_timetable_id INT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    teacher_id INT NOT NULL,
    room_id INT NULL,
    delivery_mode VARCHAR(30) NOT NULL DEFAULT 'physical',
    status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    reason VARCHAR(500) NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_class_makeup_date (date, status),
    KEY idx_class_makeup_original (original_timetable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS college_announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    attachment_path VARCHAR(1000) NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    target_type ENUM('everyone','students','parents','teachers','class','subject','students_specific') NOT NULL DEFAULT 'everyone',
    target_id INT NULL,
    status ENUM('draft','scheduled','published','expired') NOT NULL DEFAULT 'draft',
    publish_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_by INT NULL,
    published_by INT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_publish (status, publish_at, expires_at),
    KEY idx_announcements_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
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
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_ticket_no (ticket_no),
    KEY idx_support_ticket_status (status, priority, updated_at),
    KEY idx_support_ticket_requester (requester_type, requester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    author_type ENUM('student','parent','teacher','admin') NOT NULL,
    author_id INT NOT NULL,
    message TEXT NOT NULL,
    attachment_path VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_ticket_messages_ticket (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('risk_attendance_threshold', '75'),
    ('risk_academic_threshold', '60'),
    ('risk_homework_threshold', '60'),
    ('risk_fee_warning_amount', '5000'),
    ('risk_medium_score', '25'),
    ('risk_high_score', '50'),
    ('risk_critical_score', '75')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
