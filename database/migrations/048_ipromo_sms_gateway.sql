-- Migration 048: iPromo SMS Gateway support + centralized SMS log
-- Applied automatically by ensure_sms_log_schema() in config/sms_gateway.php
-- Safe to run more than once (uses IF NOT EXISTS / IGNORE).
-- Adds new settings keys for iPromo and a shared sms_logs table.

-- ─── Settings keys ────────────────────────────────────────────────────────────
-- ipromo_enabled        : '1' = iPromo SMS active, '0' = disabled (default off)
-- ipromo_api_url        : base URL from iPromo console (default https://console.ipromo.lk/api/v3/sms/send)
-- ipromo_username       : iPromo account username
-- ipromo_api_key        : iPromo API key (sensitive – never exposed to browser)
-- ipromo_sender_id      : registered sender ID shown as the sender name on device
-- (sms_provider key selects which backend is active: 'sms-gate' | 'ipromo')

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('ipromo_enabled',   '0'),
    ('ipromo_api_url',   'https://console.ipromo.lk/api/v3/sms/send'),
    ('ipromo_username',  ''),
    ('ipromo_api_key',   ''),
    ('ipromo_sender_id', ''),
    ('sms_provider',     'sms-gate');

-- ─── Centralized SMS log ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sms_logs (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient          VARCHAR(20)  NOT NULL DEFAULT '',
    message            TEXT         NOT NULL,
    provider           VARCHAR(40)  NOT NULL DEFAULT '',
    status             VARCHAR(20)  NOT NULL DEFAULT 'pending',
    provider_message_id VARCHAR(120) NULL,
    error_message      VARCHAR(500) NULL,
    sent_by            INT          NULL COMMENT 'users.id of actor or NULL for system',
    context            VARCHAR(80)  NULL COMMENT 'e.g. payment_sms, otp, classroom_join',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sms_logs_recipient  (recipient, created_at),
    KEY idx_sms_logs_status     (status, created_at),
    KEY idx_sms_logs_provider   (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reverse / rollback hints (do NOT run on production without a backup):
-- DELETE FROM settings WHERE setting_key IN ('ipromo_enabled','ipromo_api_url','ipromo_username','ipromo_api_key','ipromo_sender_id','sms_provider');
-- DROP TABLE IF EXISTS sms_logs;
