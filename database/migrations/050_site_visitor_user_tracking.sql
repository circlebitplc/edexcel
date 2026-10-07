-- Migration 050: Add user_id and user_type to site_visitor_sessions and site_visitor_pageviews
-- Associates visitor sessions and pageviews with registered users (student, teacher, admin, parent).

SET @dbname = DATABASE();

-- 1. site_visitor_sessions: user_id
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
          AND TABLE_NAME = 'site_visitor_sessions'
          AND COLUMN_NAME = 'user_id'
    ) > 0,
    'SELECT 1',
    'ALTER TABLE site_visitor_sessions ADD COLUMN user_id INT NULL AFTER session_id, ADD INDEX idx_sv_user (user_id)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. site_visitor_sessions: user_type
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
          AND TABLE_NAME = 'site_visitor_sessions'
          AND COLUMN_NAME = 'user_type'
    ) > 0,
    'SELECT 1',
    'ALTER TABLE site_visitor_sessions ADD COLUMN user_type VARCHAR(20) NULL AFTER user_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. site_visitor_pageviews: user_id
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
          AND TABLE_NAME = 'site_visitor_pageviews'
          AND COLUMN_NAME = 'user_id'
    ) > 0,
    'SELECT 1',
    'ALTER TABLE site_visitor_pageviews ADD COLUMN user_id INT NULL AFTER visitor_id, ADD INDEX idx_sp_user (user_id)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. site_visitor_pageviews: user_type
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
          AND TABLE_NAME = 'site_visitor_pageviews'
          AND COLUMN_NAME = 'user_type'
    ) > 0,
    'SELECT 1',
    'ALTER TABLE site_visitor_pageviews ADD COLUMN user_type VARCHAR(20) NULL AFTER user_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
