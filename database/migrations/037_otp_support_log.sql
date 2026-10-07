CREATE TABLE IF NOT EXISTS otp_support_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    purpose VARCHAR(40) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    otp_code CHAR(6) NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'unknown',
    user_id INT UNSIGNED NULL,
    sent TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_support_phone_created (phone, created_at),
    KEY idx_otp_support_created (created_at),
    KEY idx_otp_support_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
