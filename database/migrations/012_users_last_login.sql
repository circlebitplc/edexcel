-- Track whether a student has signed in at least once.
-- Live schema is also applied by ensure_campus_schema() in config/campus.php.
-- Existing accounts are backfilled so they keep password login.
-- New walk-in students stay NULL until their first portal login (OTP).

ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL;

UPDATE users
SET last_login_at = COALESCE(updated_at, created_at, NOW())
WHERE last_login_at IS NULL;
