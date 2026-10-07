-- 014_courso_learn.sql
-- Courso AI learns from questions, thumbs, and weak-answer follow-ups

CREATE TABLE IF NOT EXISTS courso_learned_qa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    q_hash CHAR(64) NOT NULL,
    q_norm VARCHAR(240) NOT NULL,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'study',
    ask_count INT NOT NULL DEFAULT 1,
    helpful INT NOT NULL DEFAULT 0,
    unhelpful INT NOT NULL DEFAULT 0,
    last_student_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courso_learned_hash (q_hash),
    KEY idx_courso_learned_norm (q_norm),
    KEY idx_courso_learned_score (helpful, unhelpful, ask_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
