CREATE TABLE IF NOT EXISTS ai_recommendations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope_type ENUM('student','teacher','class') NOT NULL,
    scope_id INT NOT NULL,
    recommendation_type VARCHAR(60) NOT NULL,
    facts_json JSON NOT NULL,
    recommendation_text TEXT NOT NULL,
    provider VARCHAR(40) NULL,
    model VARCHAR(120) NULL,
    prompt_version VARCHAR(30) NOT NULL DEFAULT '1',
    status ENUM('generated','reviewed','accepted','dismissed','expired') NOT NULL DEFAULT 'generated',
    created_by INT NULL,
    reviewed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ai_recommendation_scope (scope_type,scope_id,created_at),
    KEY idx_ai_recommendation_status (status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_readiness_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    official_exam_id INT NULL,
    readiness_score DECIMAL(6,2) NULL,
    syllabus_coverage DECIMAL(6,2) NULL,
    topic_mastery DECIMAL(6,2) NULL,
    past_paper_performance DECIMAL(6,2) NULL,
    recent_improvement DECIMAL(6,2) NULL,
    revision_consistency DECIMAL(6,2) NULL,
    components_json JSON NOT NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_readiness (student_id,official_exam_id),
    KEY idx_exam_readiness_score (readiness_score,calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
