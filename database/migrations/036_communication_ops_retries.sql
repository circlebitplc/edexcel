-- Retry tracking for failed communication recipients (also healed by CommunicationHubService::ensureSchema).

ALTER TABLE communication_recipients
    ADD COLUMN retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER provider,
    ADD COLUMN last_retry_at DATETIME NULL AFTER retry_count;
