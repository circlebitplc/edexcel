-- Learning module phase 3.
-- Applied on live pages by ensure_learning_module_phase3_schema() in config/online_lesson.php.
-- This file documents the same changes. It does not rename, drop, or rewrite existing lesson,
-- attempt, answer, progress, or result rows.
-- Re-running the CREATE statements is safe. Re-running a raw ALTER that adds an existing column is not.
-- To reverse a new table: DROP TABLE online_lesson_ai_drafts, online_lesson_notes, online_lesson_bookmarks,
-- lm_topics, lm_units, lm_courses, online_lesson_attempt_sessions, online_lesson_prerequisites,
-- online_lesson_versions.
-- Leave the new columns in place if any lesson already stored data in them.

CREATE TABLE IF NOT EXISTS online_lesson_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_id INT NOT NULL,
    version_no INT NOT NULL,
    snapshot_json MEDIUMTEXT NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    reason VARCHAR(40) NOT NULL DEFAULT 'saved',
    restored_from INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_olv_lesson_no (lesson_id, version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_prerequisites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_id INT NOT NULL,
    requires_lesson_id INT NOT NULL,
    min_percent TINYINT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_olpre_pair (lesson_id, requires_lesson_id),
    INDEX idx_olpre_requires (requires_lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_attempt_sessions (
    student_id INT NOT NULL,
    item_id INT NOT NULL,
    attempt_no INT NOT NULL,
    question_ids_json TEXT NULL,
    started_at DATETIME NOT NULL,
    PRIMARY KEY (student_id, item_id, attempt_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lm_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    subject_id INT NULL,
    description TEXT NULL,
    coverage_min_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lmc_subject (subject_id, archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lm_units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_lmu_course (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lm_topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    objectives TEXT NULL,
    planned_lessons INT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_lmt_unit (unit_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_bookmarks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    item_id INT NOT NULL,
    question_id INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_olbm (student_id, item_id, question_id),
    INDEX idx_olbm_student (student_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    section_id INT NOT NULL DEFAULT 0,
    item_id INT NOT NULL DEFAULT 0,
    body TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_olnote (student_id, lesson_id, section_id, item_id),
    INDEX idx_olnote_student (student_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_lesson_ai_drafts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_id INT NOT NULL,
    activity_id INT NOT NULL DEFAULT 0,
    question_id INT NOT NULL DEFAULT 0,
    user_id INT NOT NULL,
    kind VARCHAR(30) NOT NULL,
    input_json TEXT NULL,
    output_json MEDIUMTEXT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_olai_lesson (lesson_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE online_lessons ADD COLUMN publish_at DATETIME NULL;
ALTER TABLE online_lessons ADD COLUMN unpublish_at DATETIME NULL;
ALTER TABLE online_lessons ADD COLUMN topic_id INT NULL;
ALTER TABLE online_lesson_activities ADD COLUMN draw_count INT NULL;
ALTER TABLE online_lesson_activities ADD COLUMN scoring_rule VARCHAR(10) NULL;
ALTER TABLE online_lesson_activities ADD COLUMN time_limit_minutes INT NULL;
ALTER TABLE online_lesson_activities ADD COLUMN show_score TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE online_lesson_activities ADD COLUMN show_explanation TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE online_lesson_attempts ADD COLUMN question_ids_json TEXT NULL;
ALTER TABLE online_lesson_attempts ADD COLUMN version_id INT NULL;
ALTER TABLE online_lesson_attempts ADD COLUMN started_at DATETIME NULL;
ALTER TABLE online_lesson_attempts ADD COLUMN over_time TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE online_lesson_questions ADD COLUMN tags VARCHAR(255) NULL;
ALTER TABLE online_lesson_questions ADD COLUMN ai_generated TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE online_question_bank_items ADD COLUMN tags VARCHAR(255) NULL;
ALTER TABLE online_lesson_resources ADD COLUMN description TEXT NULL;
ALTER TABLE online_lesson_resources ADD COLUMN subject VARCHAR(120) NULL;
ALTER TABLE online_lesson_resources ADD COLUMN topic VARCHAR(120) NULL;
ALTER TABLE online_lesson_resources ADD COLUMN tags VARCHAR(255) NULL;
ALTER TABLE online_lesson_resources ADD COLUMN file_size INT NULL;
ALTER TABLE online_lesson_resources ADD COLUMN root_id INT NULL;
ALTER TABLE online_lesson_resources ADD COLUMN version_no INT NOT NULL DEFAULT 1;
ALTER TABLE online_lesson_resources ADD COLUMN superseded_by INT NULL;

CREATE INDEX idx_ol_topic ON online_lessons (topic_id);
CREATE INDEX idx_ol_publish_at ON online_lessons (publish_at);
CREATE INDEX idx_olr_root ON online_lesson_resources (root_id, version_no);
CREATE INDEX idx_audit_table_record ON audit_logs (table_name, record_id);
