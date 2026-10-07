-- Migration 054: Phone Contact Improvements & Controlled SMS Hardening
-- Adds school to academic uniqueness, tracks WhatsApp groups, sources, timeline, SMS statuses (allowed, opted_out, blocked),
-- previous import comparison, global emergency switch, and teacher WhatsApp group scopes.

-- 1. phone_contact_records: school in uniqueness constraint + source_type
UPDATE phone_contact_records SET school = '' WHERE school IS NULL;

ALTER TABLE phone_contact_records MODIFY COLUMN school VARCHAR(190) NOT NULL DEFAULT '';

SET @old_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'phone_contact_records' AND index_name = 'uq_contact_academic');
SET @drop_sql = IF(@old_idx > 0, 'ALTER TABLE phone_contact_records DROP INDEX uq_contact_academic', 'SELECT 1');
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @new_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'phone_contact_records' AND index_name = 'uq_contact_academic_school');
SET @add_sql = IF(@new_idx = 0, 'ALTER TABLE phone_contact_records ADD UNIQUE KEY uq_contact_academic_school (contact_id, exam_year, exam_type, location, school)', 'SELECT 1');
PREPARE stmt FROM @add_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. phone_contacts: sms_status, import timestamps, source tracking, name conflict
ALTER TABLE phone_contacts ADD COLUMN IF NOT EXISTS sms_status ENUM('allowed', 'opted_out', 'blocked') NOT NULL DEFAULT 'allowed' AFTER sms_opt_out;
ALTER TABLE phone_contacts ADD COLUMN IF NOT EXISTS first_imported_at DATETIME NULL AFTER status;
ALTER TABLE phone_contacts ADD COLUMN IF NOT EXISTS last_imported_at DATETIME NULL AFTER first_imported_at;
ALTER TABLE phone_contacts ADD COLUMN IF NOT EXISTS last_source_group VARCHAR(255) NULL AFTER last_imported_at;
ALTER TABLE phone_contacts ADD COLUMN IF NOT EXISTS source_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp_group' AFTER last_source_group;
ALTER TABLE phone_contacts ADD COLUMN IF NOT EXISTS name_conflict VARCHAR(255) NULL AFTER source_type;

UPDATE phone_contacts SET sms_status = 'opted_out' WHERE sms_opt_out = 1 AND sms_status = 'allowed';

-- 3. phone_contact_records source_type
ALTER TABLE phone_contact_records ADD COLUMN IF NOT EXISTS source_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp_group' AFTER source;

-- 4. phone_import_history comparison fields
ALTER TABLE phone_import_history ADD COLUMN IF NOT EXISTS previous_contacts_count INT NOT NULL DEFAULT 0 AFTER existing_contacts;
ALTER TABLE phone_import_history ADD COLUMN IF NOT EXISTS previously_seen_count INT NOT NULL DEFAULT 0 AFTER previous_contacts_count;

-- 5. teacher_sms_permissions: allowed WhatsApp groups
ALTER TABLE teacher_sms_permissions ADD COLUMN IF NOT EXISTS allowed_whatsapp_groups TEXT NULL AFTER allowed_locations;

-- 6. System settings defaults
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('teacher_sms_global_enabled', '1'),
    ('campaign_duplicate_warning_days', '7');
