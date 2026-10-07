-- Temporary abuse-control records. Rows expire; they are not permanent bans.
CREATE TABLE IF NOT EXISTS abuse_controls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    scope VARCHAR(64) NOT NULL,
    reason VARCHAR(160) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_abuse_ip (ip_address, expires_at),
    KEY idx_abuse_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
