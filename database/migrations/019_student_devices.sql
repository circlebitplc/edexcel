-- Student device registry: max 4 registered devices, one live session at a time.
-- Live schema is also applied by StudentDeviceService::ensureSchema().

CREATE TABLE IF NOT EXISTS student_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_key CHAR(64) NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    user_agent VARCHAR(512) NULL,
    ip_address VARCHAR(45) NULL,
    session_token CHAR(64) NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    last_login_at DATETIME NULL,
    verified_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_devices_user_key (user_id, device_key),
    KEY idx_student_devices_user_status (user_id, revoked_at, verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_device_otps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_key CHAR(64) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    replace_device_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_device_otps_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_active_sessions (
    user_id INT NOT NULL,
    session_token CHAR(64) NOT NULL,
    device_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
