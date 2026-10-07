-- Communication platform extensions. Additive on 024/025/033.
-- Extra columns on communication_messages are healed by CommunicationHubService::ensureSchema().

CREATE TABLE IF NOT EXISTS communication_bulk_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    status ENUM('preview','confirmed','processing','completed','cancelled','failed') NOT NULL DEFAULT 'preview',
    audience_type VARCHAR(40) NOT NULL,
    audience_id INT NULL,
    channel VARCHAR(20) NOT NULL,
    estimated_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    excluded_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT NULL,
    confirmed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_bulk_status (status, created_at),
    KEY idx_bulk_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
