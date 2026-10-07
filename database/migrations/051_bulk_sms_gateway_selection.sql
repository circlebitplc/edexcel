-- Migration 051: Bulk SMS Gateway Selection Schema
-- Adds gateway selection tracking to bulk_sms_campaigns and bulk_sms_recipients.

ALTER TABLE bulk_sms_campaigns
    ADD COLUMN gateway VARCHAR(50) NOT NULL DEFAULT 'sms_gate_android' AFTER campaign_name,
    ADD INDEX idx_bsc_gateway (gateway);

ALTER TABLE bulk_sms_recipients
    ADD COLUMN gateway VARCHAR(50) NULL AFTER status;
