-- 009_teacher_bunny_libraries.sql
-- Each teacher has a unique Bunny Stream library ID.
-- Columns and unique index are applied by ensure_recordings_schema()
-- (safe if they already exist). This file records the change for migrate.php.

SELECT 1 AS teacher_bunny_libraries;
