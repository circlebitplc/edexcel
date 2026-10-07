-- V2: Backup history, health alerts, admin TOTP 2FA, security events, app version
-- Safe for existing installs (CREATE IF NOT EXISTS / additive columns).

CREATE TABLE IF NOT EXISTS system_backups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_type VARCHAR(32) NOT NULL DEFAULT 'full',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    storage_path VARCHAR(1000) NULL,
    filename VARCHAR(255) NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64) NULL,
    encrypted TINYINT(1) NOT NULL DEFAULT 0,
    includes_database TINYINT(1) NOT NULL DEFAULT 1,
    includes_files TINYINT(1) NOT NULL DEFAULT 0,
    retention_tier VARCHAR(16) NOT NULL DEFAULT 'daily',
    offsite_path VARCHAR(1000) NULL,
    offsite_ok TINYINT(1) NULL,
    verified_at DATETIME NULL,
    verify_ok TINYINT(1) NULL,
    error_message VARCHAR(1000) NULL,
    triggered_by INT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_system_backups_status (status),
    KEY idx_system_backups_created (created_at),
    KEY idx_system_backups_tier (retention_tier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_health_alerts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alert_key VARCHAR(120) NOT NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'warning',
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    last_status VARCHAR(16) NOT NULL DEFAULT 'open',
    last_alerted_at DATETIME NULL,
    last_resolved_at DATETIME NULL,
    alert_count INT UNSIGNED NOT NULL DEFAULT 0,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_system_health_alerts_key (alert_key),
    KEY idx_system_health_alerts_status (last_status, last_alerted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(64) NOT NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'info',
    user_id INT NULL,
    username VARCHAR(120) NULL,
    role VARCHAR(32) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(500) NULL,
    module VARCHAR(64) NULL,
    message VARCHAR(1000) NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_security_events_created (created_at),
    KEY idx_security_events_type (event_type, created_at),
    KEY idx_security_events_user (user_id, created_at),
    KEY idx_security_events_ip (ip_address, created_at),
    KEY idx_security_events_severity (severity, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_totp_secrets (
    user_id INT NOT NULL,
    secret_encrypted VARCHAR(512) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    confirmed_at DATETIME NULL,
    recovery_codes_hash TEXT NULL,
    recovery_codes_remaining INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_trusted_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_token_hash CHAR(64) NOT NULL,
    device_label VARCHAR(120) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_trusted_token (device_token_hash),
    KEY idx_admin_trusted_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_reauth_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    purpose VARCHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_reauth_token (token_hash),
    KEY idx_admin_reauth_user (user_id, purpose, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(36) NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'info',
    event VARCHAR(120) NOT NULL,
    module VARCHAR(64) NULL,
    user_id INT NULL,
    ip_address VARCHAR(64) NULL,
    message VARCHAR(2000) NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_application_logs_created (created_at),
    KEY idx_application_logs_severity (severity, created_at),
    KEY idx_application_logs_event (event, created_at),
    KEY idx_application_logs_module (module, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('backup_enabled', '1'),
    ('backup_database_enabled', '1'),
    ('backup_files_enabled', '1'),
    ('backup_location', 'storage/backups'),
    ('backup_frequency', 'daily'),
    ('backup_retention_daily', '7'),
    ('backup_retention_weekly', '4'),
    ('backup_retention_monthly', '3'),
    ('backup_offsite_enabled', '0'),
    ('backup_offsite_path', ''),
    ('backup_encryption_enabled', '0'),
    ('backup_encryption_key_id', ''),
    ('health_alert_cooldown_minutes', '60'),
    ('disk_warning_percent', '85'),
    ('disk_critical_percent', '95'),
    ('admin_totp_required', '0'),
    ('rpo_hours', '24'),
    ('rto_hours', '4'),
    ('app_version', '2.0.0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
