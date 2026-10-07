-- Advanced Examination & Assessment Intelligence.
-- Additive only. Reuses student_topic_progress, paper_attempts, exam_readiness_snapshots,
-- online_question_banks, student_exams, student_progress, and ai_recommendations.

CREATE TABLE IF NOT EXISTS assessments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    assessment_type ENUM('class_test','unit_test','mock','revision_test','topic_test','full_paper','quiz','past_paper','adaptive') NOT NULL DEFAULT 'class_test',
    status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft',
    content_origin ENUM('teacher','bank','generated_practice') NOT NULL DEFAULT 'teacher',
    qualification_label VARCHAR(120) NULL,
    subject_id INT NULL,
    class_id INT NULL,
    unit_label VARCHAR(160) NULL,
    teacher_id INT NULL,
    official_exam_id INT NULL,
    college_exam_id INT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    total_marks DECIMAL(7,2) NOT NULL DEFAULT 0,
    pass_threshold DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    question_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    attempt_limit TINYINT UNSIGNED NOT NULL DEFAULT 1,
    randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
    randomize_answers TINYINT(1) NOT NULL DEFAULT 0,
    adaptive TINYINT(1) NOT NULL DEFAULT 0,
    copy_controls TINYINT(1) NOT NULL DEFAULT 0,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    instructions TEXT NULL,
    blueprint_json JSON NULL,
    generation_prompt VARCHAR(500) NULL,
    created_by INT NULL,
    approved_by INT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assessments_status_type (status, assessment_type, start_at),
    KEY idx_assessments_class (class_id, subject_id, teacher_id),
    KEY idx_assessments_dates (start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    question_type ENUM('mcq','true_false','numeric','short','essay') NOT NULL DEFAULT 'mcq',
    topic_key VARCHAR(120) NULL,
    topic_label VARCHAR(255) NULL,
    difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
    marks DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    prompt TEXT NOT NULL,
    choices_json JSON NULL,
    correct_index INT NULL,
    accepted_answers_json JSON NULL,
    marking_guidance_json JSON NULL,
    source_bank_item_id INT NULL,
    source_label VARCHAR(120) NULL,
    source_status ENUM('practice','teacher','official_reference') NOT NULL DEFAULT 'practice',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aq_assessment (assessment_id, sort_order),
    KEY idx_aq_topic (topic_key, difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('in_progress','submitted','marking','marked','cancelled') NOT NULL DEFAULT 'in_progress',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    expires_at DATETIME NULL,
    score DECIMAL(7,2) NULL,
    max_score DECIMAL(7,2) NULL,
    percent DECIMAL(6,2) NULL,
    passed TINYINT(1) NULL,
    adaptive_path_json JSON NULL,
    last_saved_at DATETIME NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assessment_attempt (assessment_id, student_id, attempt_no),
    KEY idx_aa_student (student_id, status, started_at),
    KEY idx_aa_assessment_status (assessment_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    choice_index INT NULL,
    answer_text TEXT NULL,
    flagged TINYINT(1) NOT NULL DEFAULT 0,
    is_correct TINYINT(1) NULL,
    marks_awarded DECIMAL(6,2) NULL,
    auto_marked TINYINT(1) NOT NULL DEFAULT 0,
    teacher_comment TEXT NULL,
    marking_notes TEXT NULL,
    marked_by INT NULL,
    marked_at DATETIME NULL,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assessment_answer (attempt_id, question_id),
    KEY idx_aans_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_attempt_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    detail VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aae_attempt (attempt_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marking_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    suggested_mark DECIMAL(6,2) NULL,
    final_mark DECIMAL(6,2) NULL,
    reason TEXT NULL,
    correct_points TEXT NULL,
    missing_points TEXT NULL,
    improvement TEXT NULL,
    provider VARCHAR(40) NULL,
    status ENUM('suggested','accepted','adjusted','rejected') NOT NULL DEFAULT 'suggested',
    reviewed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_marking_reviews_attempt (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_question_analysis (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    average_percent DECIMAL(6,2) NULL,
    success_rate DECIMAL(6,2) NULL,
    difficulty_observed ENUM('easy','medium','hard') NULL,
    common_errors_json JSON NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aqa (assessment_id, question_id),
    KEY idx_aqa_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_class_analysis (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_id BIGINT UNSIGNED NOT NULL,
    class_id INT NULL,
    participant_count INT UNSIGNED NOT NULL DEFAULT 0,
    average_percent DECIMAL(6,2) NULL,
    median_percent DECIMAL(6,2) NULL,
    highest_percent DECIMAL(6,2) NULL,
    lowest_percent DECIMAL(6,2) NULL,
    distribution_json JSON NULL,
    topic_json JSON NULL,
    class_revision_topics_json JSON NULL,
    previous_average DECIMAL(6,2) NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aca (assessment_id, class_id),
    KEY idx_aca_class (class_id, calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
