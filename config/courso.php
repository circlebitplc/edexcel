<?php
declare(strict_types=1);

function ensure_courso_schema(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $statements = [
        "CREATE TABLE IF NOT EXISTS courso_learner_profiles (
            student_id INT NOT NULL,
            goals TEXT NULL,
            interests VARCHAR(500) NULL,
            ai_style VARCHAR(20) NOT NULL DEFAULT 'coach',
            difficulty VARCHAR(20) NOT NULL DEFAULT 'adaptive',
            notify_study TINYINT(1) NOT NULL DEFAULT 1,
            notify_streak TINYINT(1) NOT NULL DEFAULT 1,
            notify_community TINYINT(1) NOT NULL DEFAULT 1,
            study_days VARCHAR(40) NOT NULL DEFAULT '1,2,3,4,5',
            study_hour TINYINT NOT NULL DEFAULT 19,
            density VARCHAR(20) NOT NULL DEFAULT 'comfortable',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_memory (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            fact_key VARCHAR(80) NOT NULL,
            fact_value TEXT NOT NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'chat',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_courso_memory (student_id, fact_key),
            KEY idx_courso_memory_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_chat_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            role VARCHAR(16) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_courso_chat_student (student_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_activity (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            kind VARCHAR(40) NOT NULL,
            ref_type VARCHAR(40) NULL,
            ref_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_courso_activity_student (student_id, created_at),
            KEY idx_courso_activity_kind (student_id, kind, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_quizzes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            subject_id INT NULL,
            class_id INT NULL,
            topic VARCHAR(200) NOT NULL DEFAULT 'Practice',
            difficulty VARCHAR(20) NOT NULL DEFAULT 'core',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            score DECIMAL(7,2) NULL,
            max_score DECIMAL(7,2) NOT NULL DEFAULT 5,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_courso_quizzes_student (student_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_quiz_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            sort_order TINYINT NOT NULL DEFAULT 0,
            prompt TEXT NOT NULL,
            choices TEXT NOT NULL,
            correct_index TINYINT NOT NULL DEFAULT 0,
            explanation TEXT NULL,
            example_text TEXT NULL,
            student_choice TINYINT NULL,
            feedback TEXT NULL,
            is_correct TINYINT(1) NULL,
            PRIMARY KEY (id),
            KEY idx_courso_quiz_items_quiz (quiz_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            body VARCHAR(800) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_courso_posts_class (class_id, created_at),
            KEY idx_courso_posts_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_nudge_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            nudge_date DATE NOT NULL,
            kind VARCHAR(40) NOT NULL DEFAULT 'study',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_courso_nudge (student_id, nudge_date, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_learned_qa (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS courso_public_rl (
            ip_hash CHAR(64) NOT NULL,
            window_start INT NOT NULL,
            hits INT NOT NULL DEFAULT 0,
            PRIMARY KEY (ip_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('Courso schema: ' . $e->getMessage());
        }
    }

    if (function_exists('campus_column_exists')) {
        try {
            if (!campus_column_exists($pdo, 'courso_chat_messages', 'rating')) {
                $pdo->exec("ALTER TABLE courso_chat_messages ADD COLUMN rating VARCHAR(16) NULL");
            }
        } catch (Throwable $e) {
            error_log('Courso schema rating: ' . $e->getMessage());
        }
    }
}
