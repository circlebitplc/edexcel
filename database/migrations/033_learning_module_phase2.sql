-- Learning module phase 2.
-- Applied on live pages by ensure_online_lesson_schema(). This file documents the same changes.
-- It does not rename or drop existing lesson, attempt, or result tables.
-- Re-running the CREATE statements is safe. Re-running a raw ALTER that adds an existing column is not.
-- To reverse a new table: DROP TABLE online_lesson_resources, online_lesson_submissions,
-- online_lesson_criteria, online_lesson_objective_links, online_lesson_objectives.
-- Leave the new columns in place if any lesson already stored data in them.

CREATE TABLE IF NOT EXISTS online_lesson_objectives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_id INT NOT NULL,
    body VARCHAR(300) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_olobj_lesson (lesson_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_objective_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    objective_id INT NOT NULL,
    question_id INT NOT NULL DEFAULT 0,
    item_id INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_olobj_link (objective_id, question_id, item_id),
    INDEX idx_olobj_question (question_id),
    INDEX idx_olobj_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_criteria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    label VARCHAR(200) NOT NULL,
    marks DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_olc_question (question_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    item_id INT NOT NULL,
    status ENUM('submitted','marked','returned','resubmit') NOT NULL DEFAULT 'submitted',
    body_text MEDIUMTEXT NULL,
    file_key VARCHAR(180) NULL,
    file_name VARCHAR(200) NULL,
    mime VARCHAR(120) NULL,
    marks_awarded DECIMAL(6,2) NULL,
    teacher_comment TEXT NULL,
    submitted_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_olsub_student_item (student_id, item_id),
    INDEX idx_olsub_item_status (item_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    owner_user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    resource_type ENUM('video','pdf','ppt','image','document','url') NOT NULL DEFAULT 'url',
    url VARCHAR(500) NULL,
    file_key VARCHAR(180) NULL,
    file_name VARCHAR(200) NULL,
    mime VARCHAR(120) NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_olr_owner (owner_user_id, archived, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
