-- 020_student_today_homework.sql
-- Homework submissions + teacher review for student Today / lesson ops

CREATE TABLE IF NOT EXISTS student_homework_submissions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
