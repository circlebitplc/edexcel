-- Channel reliability: provider message IDs for WhatsApp/SMS delivery callbacks.
-- Additive; CommunicationHubService::ensureSchema() also heals these columns.

ALTER TABLE communication_recipients
    ADD COLUMN provider_message_id VARCHAR(120) NULL AFTER phone,
    ADD COLUMN provider VARCHAR(40) NULL AFTER provider_message_id,
    ADD KEY idx_comm_recip_provider (provider_message_id);

CREATE TABLE IF NOT EXISTS communication_channel_webhooks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel VARCHAR(20) NOT NULL,
    provider_event_id VARCHAR(120) NULL,
    status VARCHAR(40) NOT NULL,
    payload_json JSON NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chan_webhook_provider (provider_event_id),
    KEY idx_chan_webhook_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
