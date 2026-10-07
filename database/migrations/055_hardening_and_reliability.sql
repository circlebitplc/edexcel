-- Migration 055: Phone Contact Hardening, WhatsApp Group Normalization & Controlled SMS Reliability
-- Adds audit table for teacher SMS switch, canonical WhatsApp group mappings,
-- canonical_source_group column on records, and idempotency key on campaigns.

-- 1. teacher_sms_switch_audit: Track every toggle of the global emergency teacher SMS switch
CREATE TABLE IF NOT EXISTS teacher_sms_switch_audit (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id   INT NOT NULL COMMENT 'users.id of administrator who toggled the switch',
    previous_status TINYINT(1) NOT NULL COMMENT '0 = disabled, 1 = enabled',
    new_status      TINYINT(1) NOT NULL COMMENT '0 = disabled, 1 = enabled',
    reason          TEXT NULL COMMENT 'Admin supplied explanation/reason',
    ip_address      VARCHAR(45) NULL COMMENT 'Client IPv4/IPv6',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_switch_audit_admin (admin_user_id),
    KEY idx_switch_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. phone_whatsapp_group_mappings: Canonical alias mapping for WhatsApp group names
CREATE TABLE IF NOT EXISTS phone_whatsapp_group_mappings (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    raw_group_name       VARCHAR(190) NOT NULL COMMENT 'Raw WhatsApp group name entered during import',
    canonical_group_name VARCHAR(190) NOT NULL COMMENT 'Canonical unified group name',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_raw_group (raw_group_name),
    KEY idx_canonical_group (canonical_group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. phone_contact_records: add canonical_source_group column
ALTER TABLE phone_contact_records ADD COLUMN IF NOT EXISTS canonical_source_group VARCHAR(190) NULL AFTER source_group;

SET @canon_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'phone_contact_records' AND index_name = 'idx_pcr_canon_group');
SET @add_canon_idx = IF(@canon_idx = 0, 'ALTER TABLE phone_contact_records ADD KEY idx_pcr_canon_group (canonical_source_group)', 'SELECT 1');
PREPARE stmt FROM @add_canon_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. bulk_sms_campaigns: idempotency key to prevent double-submit or duplicate sends
ALTER TABLE bulk_sms_campaigns ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(100) NULL AFTER campaign_code;

SET @idem_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'bulk_sms_campaigns' AND index_name = 'idx_bsc_idempotency_key');
SET @add_idem_idx = IF(@idem_idx = 0, 'ALTER TABLE bulk_sms_campaigns ADD KEY idx_bsc_idempotency_key (idempotency_key)', 'SELECT 1');
PREPARE stmt_idem FROM @add_idem_idx;
EXECUTE stmt_idem;
DEALLOCATE PREPARE stmt_idem;
