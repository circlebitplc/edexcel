-- Two active student devices, replacement history, and a 14-day block on a replaced device.
-- StudentDeviceService::ensureSchema() applies the same columns on existing databases.

ALTER TABLE student_devices ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE';
ALTER TABLE student_devices ADD COLUMN blocked_until DATETIME NULL;
ALTER TABLE student_devices ADD COLUMN replaced_at DATETIME NULL;
ALTER TABLE student_devices ADD COLUMN platform VARCHAR(40) NULL;
ALTER TABLE student_devices ADD COLUMN browser VARCHAR(40) NULL;
ALTER TABLE student_devices ADD COLUMN updated_at DATETIME NULL;

UPDATE student_devices
SET status = 'REPLACED'
WHERE revoked_at IS NOT NULL
  AND (status IS NULL OR status = '' OR status = 'ACTIVE');
