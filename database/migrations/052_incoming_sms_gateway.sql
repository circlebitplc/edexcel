-- Migration 052: Incoming SMS Gateway and Android Devices Schema
-- Creates sms_gateway_devices and incoming_sms tables.

CREATE TABLE IF NOT EXISTS sms_gateway_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(150) NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    phone_number VARCHAR(50) NULL,
    api_token_hash VARCHAR(255) NOT NULL,
    api_token_prefix VARCHAR(20) NOT NULL DEFAULT '',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL,
    last_sms_at DATETIME NULL,
    last_sync_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sgd_device_id (device_id),
    INDEX idx_sgd_enabled (is_enabled),
    INDEX idx_sgd_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incoming_sms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL,
    device_db_id INT NULL,
    message_id VARCHAR(150) NULL,
    sender VARCHAR(50) NOT NULL,
    raw_sender VARCHAR(100) NULL,
    recipient VARCHAR(50) NULL,
    message TEXT NOT NULL,
    sim_number INT NULL,
    received_at DATETIME NOT NULL,
    received_at_server DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    read_by INT NULL,
    matched_user_type VARCHAR(50) NULL,
    matched_user_name VARCHAR(150) NULL,
    matched_user_id INT NULL,
    fingerprint CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_incoming_fingerprint (fingerprint),
    INDEX idx_is_device (device_id),
    INDEX idx_is_sender (sender),
    INDEX idx_is_read (is_read, received_at),
    INDEX idx_is_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
