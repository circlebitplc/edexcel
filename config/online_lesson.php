<?php
declare(strict_types=1);

function ensure_online_lesson_schema(PDO $pdo): void
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
        "CREATE TABLE IF NOT EXISTS online_lessons (
            id INT PRIMARY KEY AUTO_INCREMENT,
            timetable_id INT NOT NULL,
            recording_id INT NULL,
            title VARCHAR(200) NOT NULL,
            intro TEXT NULL,
            published TINYINT(1) NOT NULL DEFAULT 0,
            sequential TINYINT(1) NOT NULL DEFAULT 1,
            min_watch_percent TINYINT UNSIGNED NOT NULL DEFAULT 80,
            available_after_class TINYINT(1) NOT NULL DEFAULT 0,
            close_after_days INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ol_timetable (timetable_id),
            INDEX idx_ol_recording (recording_id),
            INDEX idx_ol_published (published, timetable_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_activities (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_id INT NOT NULL,
            activity_type ENUM('mcq','essay','mixed') NOT NULL DEFAULT 'mcq',
            title VARCHAR(200) NOT NULL,
            instructions TEXT NULL,
            pass_percent TINYINT UNSIGNED NULL,
            max_attempts INT NOT NULL DEFAULT 0,
            show_correct TINYINT(1) NOT NULL DEFAULT 1,
            shuffle_choices TINYINT(1) NOT NULL DEFAULT 0,
            shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ola_lesson (lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_id INT NOT NULL,
            item_type ENUM('video','activity','page') NOT NULL DEFAULT 'video',
            title VARCHAR(200) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            video_asset_id INT NULL,
            activity_id INT NULL,
            body TEXT NULL,
            required TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_oli_lesson (lesson_id, sort_order),
            INDEX idx_oli_asset (video_asset_id),
            INDEX idx_oli_activity (activity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_questions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            activity_id INT NOT NULL,
            question_type ENUM('mcq','essay') NOT NULL DEFAULT 'mcq',
            sort_order INT NOT NULL DEFAULT 0,
            prompt TEXT NOT NULL,
            choices_json TEXT NULL,
            correct_index INT NULL,
            marks DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            explanation TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_olq_activity (activity_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_progress (
            student_id INT NOT NULL,
            lesson_id INT NOT NULL,
            current_item_id INT NULL,
            completed_count INT NOT NULL DEFAULT 0,
            completed_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, lesson_id),
            INDEX idx_olp_lesson (lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_item_state (
            student_id INT NOT NULL,
            item_id INT NOT NULL,
            status ENUM('incomplete','completed') NOT NULL DEFAULT 'incomplete',
            video_seconds INT NOT NULL DEFAULT 0,
            video_duration_seconds INT NOT NULL DEFAULT 0,
            completed_at DATETIME NULL,
            PRIMARY KEY (student_id, item_id),
            INDEX idx_olis_item (item_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            item_id INT NOT NULL,
            activity_id INT NOT NULL,
            attempt_no INT NOT NULL DEFAULT 1,
            status ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'submitted',
            score DECIMAL(8,2) NULL,
            max_score DECIMAL(8,2) NULL,
            passed TINYINT(1) NULL,
            submitted_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_olat_student_item (student_id, item_id, attempt_no),
            INDEX idx_olat_activity (activity_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id INT NOT NULL,
            choice_index INT NULL,
            essay_text MEDIUMTEXT NULL,
            is_correct TINYINT(1) NULL,
            marks_awarded DECIMAL(6,2) NULL,
            teacher_comment TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_olan_attempt_q (attempt_id, question_id),
            INDEX idx_olan_question (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_sections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            estimated_minutes INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_olsec_lesson (lesson_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_templates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            owner_user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            structure_json MEDIUMTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_olt_owner (owner_user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_question_banks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            owner_user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_oqb_owner (owner_user_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_question_bank_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            bank_id INT NOT NULL,
            question_type ENUM('mcq','essay') NOT NULL DEFAULT 'mcq',
            sort_order INT NOT NULL DEFAULT 0,
            prompt TEXT NOT NULL,
            choices_json TEXT NULL,
            correct_index INT NULL,
            marks DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            explanation TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_oqbi_bank (bank_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_objectives (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_id INT NOT NULL,
            body VARCHAR(300) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_olobj_lesson (lesson_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_objective_links (
            id INT PRIMARY KEY AUTO_INCREMENT,
            objective_id INT NOT NULL,
            question_id INT NOT NULL DEFAULT 0,
            item_id INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_olobj_link (objective_id, question_id, item_id),
            INDEX idx_olobj_question (question_id),
            INDEX idx_olobj_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_criteria (
            id INT PRIMARY KEY AUTO_INCREMENT,
            question_id INT NOT NULL,
            label VARCHAR(200) NOT NULL,
            marks DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_olc_question (question_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_submissions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_resources (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('online lesson schema: ' . $e->getMessage());
        }
    }

    $alters = [
        ['online_lessons', 'min_watch_percent', 'TINYINT UNSIGNED NOT NULL DEFAULT 80'],
        ['online_lessons', 'available_after_class', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_lessons', 'close_after_days', 'INT NOT NULL DEFAULT 0'],
        ['online_lesson_item_state', 'video_duration_seconds', 'INT NOT NULL DEFAULT 0'],
        ['online_lesson_activities', 'shuffle_choices', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_lesson_activities', 'shuffle_questions', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_lesson_items', 'link_url', 'VARCHAR(500) NULL'],
        ['online_lesson_items', 'open_new_tab', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['online_lessons', 'pass_percent', 'TINYINT UNSIGNED NULL'],
        ['online_lessons', 'plan_objectives', 'TEXT NULL'],
        ['online_lessons', 'plan_topics', 'TEXT NULL'],
        ['online_lessons', 'plan_minutes', 'INT NULL'],
        ['online_lessons', 'plan_difficulty', 'VARCHAR(40) NULL'],
        ['online_lessons', 'plan_prerequisites', 'TEXT NULL'],
        ['online_lessons', 'plan_outcomes', 'TEXT NULL'],
        ['online_lessons', 'plan_materials', 'TEXT NULL'],
        ['online_lessons', 'plan_homework', 'TEXT NULL'],
        ['online_lessons', 'plan_assessment', 'TEXT NULL'],
        ['online_lessons', 'archived', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_lesson_items', 'section_id', 'INT NULL'],
        ['online_lesson_items', 'estimated_minutes', 'INT NULL'],
        ['online_lesson_progress', 'active_seconds', 'INT NOT NULL DEFAULT 0'],
        ['online_lesson_item_state', 'active_seconds', 'INT NOT NULL DEFAULT 0'],
        ['online_lesson_item_state', 'open_count', 'INT NOT NULL DEFAULT 0'],
        ['online_lesson_item_state', 'last_seen_at', 'DATETIME NULL'],
        ['online_lesson_activities', 'due_at', 'DATETIME NULL'],
        ['online_lesson_activities', 'max_marks', 'DECIMAL(6,2) NULL'],
        ['online_lesson_activities', 'allow_text', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['online_lesson_activities', 'allow_file', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['online_lesson_questions', 'topic', 'VARCHAR(120) NULL'],
        ['online_lesson_questions', 'difficulty', 'VARCHAR(40) NULL'],
        ['online_lesson_questions', 'exam_ref', 'VARCHAR(80) NULL'],
        ['online_lesson_questions', 'expected_answer', 'TEXT NULL'],
        ['online_question_banks', 'subject', 'VARCHAR(120) NULL'],
        ['online_question_bank_items', 'topic', 'VARCHAR(120) NULL'],
        ['online_question_bank_items', 'difficulty', 'VARCHAR(40) NULL'],
        ['online_lesson_items', 'resource_id', 'INT NULL'],
    ];
    foreach ($alters as [$table, $column, $definition]) {
        try {
            if (function_exists('campus_column_exists') && campus_column_exists($pdo, $table, $column)) {
                continue;
            }
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            error_log("online lesson schema {$table}.{$column}: " . $e->getMessage());
        }
    }

    try {
        $col = $pdo->query("SHOW COLUMNS FROM online_lesson_items LIKE 'item_type'")->fetch(PDO::FETCH_ASSOC);
        $enum = strtolower((string)($col['Type'] ?? ''));
        if ($enum !== '' && !str_contains($enum, 'resource')) {
            $pdo->exec("ALTER TABLE online_lesson_items MODIFY item_type ENUM('video','activity','page','external_link','resource') NOT NULL DEFAULT 'video'");
        }
    } catch (Throwable $e) {
        error_log('online lesson schema item_type: ' . $e->getMessage());
    }

    $enums = [
        ['online_lesson_activities', 'activity_type', "ENUM('mcq','essay','mixed','short','exam','assignment','homework') NOT NULL DEFAULT 'mcq'", 'homework'],
        ['online_lesson_questions', 'question_type', "ENUM('mcq','essay','short','exam') NOT NULL DEFAULT 'mcq'", 'short'],
        ['online_question_bank_items', 'question_type', "ENUM('mcq','essay','short','exam') NOT NULL DEFAULT 'mcq'", 'short'],
    ];
    foreach ($enums as [$table, $column, $definition, $needle]) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column))->fetch(PDO::FETCH_ASSOC);
            $enum = strtolower((string)($col['Type'] ?? ''));
            if ($enum !== '' && !str_contains($enum, $needle)) {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            error_log("online lesson schema {$table}.{$column}: " . $e->getMessage());
        }
    }

    ensure_learning_module_phase3_schema($pdo);
    ensure_mcq_live_schema($pdo);
}

/**
 * Live MCQ monitor columns on the existing in-progress attempt row. Only adds columns and indexes.
 */
function ensure_mcq_live_schema(PDO $pdo): void
{
    $flag = dirname(__DIR__) . '/data/mcq_live_schema_ok';
    if (is_file($flag) && trim((string)@file_get_contents($flag)) === '047') {
        return;
    }
    if (!function_exists('campus_column_exists')) {
        return;
    }
    $failed = false;
    $alters = [
        ['lesson_id', 'INT NULL'],
        ['activity_id', 'INT NULL'],
        ['attempt_id', 'BIGINT UNSIGNED NULL'],
        ['live_only', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['current_question_id', 'INT NULL'],
        ['current_question_no', 'INT NULL'],
        ['total_questions', 'INT NULL'],
        ['selected_choice', 'INT NULL'],
        ['answers_json', 'TEXT NULL'],
        ['question_started_at', 'DATETIME NULL'],
        ['last_interaction_at', 'DATETIME NULL'],
        ['last_heartbeat_at', 'DATETIME NULL'],
        ['live_status', 'VARCHAR(12) NULL'],
        ['live_updated_at', 'DATETIME NULL'],
    ];
    foreach ($alters as [$column, $definition]) {
        try {
            if (campus_column_exists($pdo, 'online_lesson_attempt_sessions', $column)) {
                continue;
            }
            $pdo->exec("ALTER TABLE online_lesson_attempt_sessions ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            $failed = true;
            error_log("mcq live schema {$column}: " . $e->getMessage());
        }
    }
    $indexes = [
        ['idx_olas_lesson_live', '(lesson_id, live_updated_at)'],
        ['idx_olas_lesson_status', '(lesson_id, live_status, last_heartbeat_at)'],
        ['idx_olas_item_status', '(item_id, live_status)'],
        ['idx_olas_attempt', '(attempt_id)'],
        ['idx_olas_question', '(current_question_id)'],
    ];
    foreach ($indexes as [$name, $columns]) {
        try {
            $exists = $pdo->query("SHOW INDEX FROM online_lesson_attempt_sessions WHERE Key_name = " . $pdo->quote($name))->fetch(PDO::FETCH_ASSOC);
            if (!$exists) {
                $pdo->exec("CREATE INDEX `{$name}` ON online_lesson_attempt_sessions {$columns}");
            }
        } catch (Throwable $e) {
            $failed = true;
            error_log("mcq live index {$name}: " . $e->getMessage());
        }
    }
    if (!$failed) {
        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0755, true);
        }
        @file_put_contents($flag, '047');
    }
}

/**
 * Phase 3 structures. Only adds tables, columns, and indexes.
 */
function ensure_learning_module_phase3_schema(PDO $pdo): void
{
    $flag = dirname(__DIR__) . '/data/lm_phase3_schema_ok';
    if (is_file($flag) && trim((string)@file_get_contents($flag)) === '046') {
        return;
    }
    $failed = false;
    $statements = [
        "CREATE TABLE IF NOT EXISTS online_lesson_versions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_prerequisites (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_id INT NOT NULL,
            requires_lesson_id INT NOT NULL,
            min_percent TINYINT UNSIGNED NOT NULL DEFAULT 100,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_olpre_pair (lesson_id, requires_lesson_id),
            INDEX idx_olpre_requires (requires_lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_attempt_sessions (
            student_id INT NOT NULL,
            item_id INT NOT NULL,
            attempt_no INT NOT NULL,
            question_ids_json TEXT NULL,
            started_at DATETIME NOT NULL,
            PRIMARY KEY (student_id, item_id, attempt_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS lm_courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            subject_id INT NULL,
            description TEXT NULL,
            coverage_min_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT NULL,
            archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lmc_subject (subject_id, archived)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS lm_units (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_lmu_course (course_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS lm_topics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            unit_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            objectives TEXT NULL,
            planned_lessons INT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_lmt_unit (unit_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_bookmarks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            lesson_id INT NOT NULL,
            item_id INT NOT NULL,
            question_id INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_olbm (student_id, item_id, question_id),
            INDEX idx_olbm_student (student_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_notes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            lesson_id INT NOT NULL,
            section_id INT NOT NULL DEFAULT 0,
            item_id INT NOT NULL DEFAULT 0,
            body TEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_olnote (student_id, lesson_id, section_id, item_id),
            INDEX idx_olnote_student (student_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS online_lesson_ai_drafts (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            $failed = true;
            error_log('learning module phase 3 schema: ' . $e->getMessage());
        }
    }

    $alters = [
        ['online_lessons', 'publish_at', 'DATETIME NULL'],
        ['online_lessons', 'unpublish_at', 'DATETIME NULL'],
        ['online_lessons', 'topic_id', 'INT NULL'],
        ['online_lesson_activities', 'draw_count', 'INT NULL'],
        ['online_lesson_activities', 'scoring_rule', 'VARCHAR(10) NULL'],
        ['online_lesson_activities', 'time_limit_minutes', 'INT NULL'],
        ['online_lesson_activities', 'show_score', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['online_lesson_activities', 'show_explanation', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ['online_lesson_attempts', 'question_ids_json', 'TEXT NULL'],
        ['online_lesson_attempts', 'version_id', 'INT NULL'],
        ['online_lesson_attempts', 'started_at', 'DATETIME NULL'],
        ['online_lesson_attempts', 'over_time', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_lesson_questions', 'tags', 'VARCHAR(255) NULL'],
        ['online_lesson_questions', 'ai_generated', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_question_bank_items', 'tags', 'VARCHAR(255) NULL'],
        ['online_lesson_resources', 'description', 'TEXT NULL'],
        ['online_lesson_resources', 'subject', 'VARCHAR(120) NULL'],
        ['online_lesson_resources', 'topic', 'VARCHAR(120) NULL'],
        ['online_lesson_resources', 'tags', 'VARCHAR(255) NULL'],
        ['online_lesson_resources', 'file_size', 'INT NULL'],
        ['online_lesson_resources', 'root_id', 'INT NULL'],
        ['online_lesson_resources', 'version_no', 'INT NOT NULL DEFAULT 1'],
        ['online_lesson_resources', 'superseded_by', 'INT NULL'],
    ];
    foreach ($alters as [$table, $column, $definition]) {
        try {
            if (function_exists('campus_column_exists') && campus_column_exists($pdo, $table, $column)) {
                continue;
            }
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            $failed = true;
            error_log("learning module phase 3 schema {$table}.{$column}: " . $e->getMessage());
        }
    }

    $indexes = [
        ['online_lessons', 'idx_ol_topic', '(topic_id)'],
        ['online_lessons', 'idx_ol_publish_at', '(publish_at)'],
        ['online_lesson_resources', 'idx_olr_root', '(root_id, version_no)'],
        ['audit_logs', 'idx_audit_table_record', '(table_name, record_id)'],
    ];
    foreach ($indexes as [$table, $name, $columns]) {
        try {
            $exists = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($name))->fetch(PDO::FETCH_ASSOC);
            if (!$exists) {
                $pdo->exec("CREATE INDEX `{$name}` ON `{$table}` {$columns}");
            }
        } catch (Throwable $e) {
            $failed = true;
            error_log("learning module phase 3 index {$table}.{$name}: " . $e->getMessage());
        }
    }
    if (!$failed && function_exists('campus_column_exists')) {
        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0755, true);
        }
        @file_put_contents($flag, '046');
    }
}

function online_lesson_private_root(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private_uploads';
}

function online_lesson_safe_file_key(string $key): string
{
    $key = str_replace('\\', '/', trim($key));
    if ($key === '' || str_contains($key, '..') || !preg_match('#^(assignments|resources)/[0-9]+/[a-zA-Z0-9._-]+$#', $key)) {
        throw new RuntimeException('That file is not available.');
    }
    return $key;
}

function online_lesson_private_path(string $key): string
{
    $key = online_lesson_safe_file_key($key);
    $root = realpath(online_lesson_private_root());
    if ($root === false) {
        throw new RuntimeException('That file is not available.');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $file = realpath($path);
    if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('That file is not available.');
    }
    return $file;
}

function online_lesson_stream_file(string $key, string $downloadName, string $mime, bool $inline): void
{
    $path = online_lesson_private_path($key);
    $name = trim(str_replace(['"', "\r", "\n"], '', basename($downloadName)));
    if ($name === '') {
        $name = 'download';
    }
    $mime = trim($mime);
    if ($mime === '' || !preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mime)) {
        $mime = 'application/octet-stream';
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
}

function student_online_lesson_url(int $timetableId, int $itemId = 0, string $from = ''): string
{
    $url = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/student/lesson.php?lesson=' . max(0, $timetableId);
    if ($itemId > 0) {
        $url .= '&item=' . $itemId;
    }
    $from = strtolower(trim($from));
    if (in_array($from, ['timetable', 'overview', 'fees', 'recordings', 'classroom', 'class'], true)) {
        $url .= '&from=' . rawurlencode($from);
    }
    return $url;
}

function campus_online_lesson_url(int $timetableId, int $activityId = 0): string
{
    $url = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/campus/online_lesson.php?lesson=' . max(0, $timetableId);
    if ($activityId > 0) {
        $url .= '&activity=' . $activityId;
    }
    return $url;
}

/**
 * @param array<string,mixed> $input
 * @return array{class_id:int,lesson:int,kind:string,status:string,from:string,to:string,order:string}
 */
function marking_queue_filters(array $input): array
{
    $date = static fn ($v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? (string)$v : '';
    $kind = (string)($input['kind'] ?? '');
    $status = (string)($input['status'] ?? '');
    return [
        'class_id' => max(0, (int)($input['class_id'] ?? 0)),
        'lesson' => max(0, (int)($input['lesson'] ?? 0)),
        'kind' => in_array($kind, ['written', 'submission'], true) ? $kind : '',
        'status' => in_array($status, ['waiting', 'resubmit'], true) ? $status : '',
        'from' => $date($input['from'] ?? ''),
        'to' => $date($input['to'] ?? ''),
        'order' => (string)($input['order'] ?? '') === 'newest' ? 'newest' : 'oldest',
    ];
}

/**
 * @param array<string,mixed> $row marking queue row
 * @param array<string,mixed> $filters
 */
function marking_queue_item_url(array $row, array $filters): string
{
    $queue = http_build_query(array_filter($filters, static fn ($v) => $v !== '' && $v !== 0 && $v !== 'oldest'));
    $anchor = (int)($row['submission_id'] ?? 0) > 0
        ? 'mark-s' . (int)$row['submission_id']
        : 'mark-a' . (int)($row['attempt_id'] ?? 0) . '-q' . (int)($row['question_id'] ?? 0);
    return campus_online_lesson_url((int)$row['timetable_id']) . '&tab=grade&queue=1'
        . ($queue !== '' ? '&queue_filter=' . rawurlencode($queue) : '') . '#' . $anchor;
}

function campus_lesson_analytics_url(int $timetableId): string
{
    return rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/')
        . '/campus/lesson_analytics.php?lesson=' . max(0, $timetableId);
}

function student_lesson_result_url(int $timetableId): string
{
    return rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/')
        . '/student/lesson_result.php?lesson=' . max(0, $timetableId);
}

function campus_online_lesson_preview_url(int $timetableId, int $itemId = 0): string
{
    $url = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/campus/lesson_preview.php?lesson=' . max(0, $timetableId);
    if ($itemId > 0) {
        $url .= '&item=' . $itemId;
    }
    return $url;
}
