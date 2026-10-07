-- Unified core API foundation: replay protection, internal events, webhooks.
CREATE TABLE IF NOT EXISTS api_idempotency_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idempotency_key VARCHAR(128) NOT NULL,
    user_id INT NULL,
    route VARCHAR(255) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_status SMALLINT NOT NULL,
    response_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_idempotency (idempotency_key, user_id, route),
    KEY idx_api_idempotency_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domain_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id CHAR(36) NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    aggregate_type VARCHAR(80) NULL,
    aggregate_id BIGINT NULL,
    payload_json JSON NOT NULL,
    actor_user_id INT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_domain_event_id (event_id),
    KEY idx_domain_events_name_time (event_name, occurred_at),
    KEY idx_domain_events_aggregate (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id CHAR(36) NOT NULL,
    endpoint_url VARCHAR(1000) NOT NULL,
    signature CHAR(64) NULL,
    status ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    response_code SMALLINT NULL,
    last_error VARCHAR(1000) NULL,
    next_attempt_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_event_endpoint (event_id, endpoint_url(255)),
    KEY idx_webhook_retry (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
