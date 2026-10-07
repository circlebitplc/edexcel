-- 016_user_theme_preference.sql
-- Per-user appearance. Default is Dark Theme.
-- Also applied at runtime by app_theme_ensure_schema() in config/theme.php.

ALTER TABLE users
    ADD COLUMN theme_preference VARCHAR(50) NOT NULL DEFAULT 'dark';
