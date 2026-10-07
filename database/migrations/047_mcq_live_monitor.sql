-- Live MCQ student monitor.
-- Applied on live pages by ensure_mcq_live_schema() in config/online_lesson.php.
-- This file documents the same changes. It reuses online_lesson_attempt_sessions (the existing
-- in-progress attempt row) and does not create a second attempt table or touch attempts, answers,
-- progress, or result rows.
-- Re-running a raw ALTER that adds an existing column is not safe; the PHP function checks first.
-- To reverse: drop the indexes, then the columns. Existing pool/timer data in the table is unaffected.

ALTER TABLE online_lesson_attempt_sessions ADD COLUMN lesson_id INT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN activity_id INT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN attempt_id BIGINT UNSIGNED NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN live_only TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN current_question_id INT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN current_question_no INT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN total_questions INT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN selected_choice INT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN answers_json TEXT NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN question_started_at DATETIME NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN last_interaction_at DATETIME NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN last_heartbeat_at DATETIME NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN live_status VARCHAR(12) NULL;
ALTER TABLE online_lesson_attempt_sessions ADD COLUMN live_updated_at DATETIME NULL;

CREATE INDEX idx_olas_lesson_live ON online_lesson_attempt_sessions (lesson_id, live_updated_at);
CREATE INDEX idx_olas_lesson_status ON online_lesson_attempt_sessions (lesson_id, live_status, last_heartbeat_at);
CREATE INDEX idx_olas_item_status ON online_lesson_attempt_sessions (item_id, live_status);
CREATE INDEX idx_olas_attempt ON online_lesson_attempt_sessions (attempt_id);
CREATE INDEX idx_olas_question ON online_lesson_attempt_sessions (current_question_id);

-- Reverse:
-- DROP INDEX idx_olas_lesson_live ON online_lesson_attempt_sessions;
-- DROP INDEX idx_olas_lesson_status ON online_lesson_attempt_sessions;
-- DROP INDEX idx_olas_item_status ON online_lesson_attempt_sessions;
-- DROP INDEX idx_olas_attempt ON online_lesson_attempt_sessions;
-- DROP INDEX idx_olas_question ON online_lesson_attempt_sessions;
-- DELETE FROM online_lesson_attempt_sessions WHERE live_only = 1;
-- ALTER TABLE online_lesson_attempt_sessions DROP COLUMN lesson_id, DROP COLUMN activity_id, DROP COLUMN attempt_id,
--   DROP COLUMN live_only, DROP COLUMN current_question_id, DROP COLUMN current_question_no, DROP COLUMN total_questions,
--   DROP COLUMN selected_choice, DROP COLUMN answers_json, DROP COLUMN question_started_at, DROP COLUMN last_interaction_at,
--   DROP COLUMN last_heartbeat_at, DROP COLUMN live_status, DROP COLUMN live_updated_at;
