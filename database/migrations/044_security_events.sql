-- Central security events. Rows are kept. This does not replace audit_logs.
CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_code VARCHAR(64) NOT NULL,
    user_id INT NULL,
    actor_user_id INT NULL,
    device_id INT NULL,
    timetable_id INT NULL,
    teacher_id INT NULL,
    result VARCHAR(32) NULL,
    message VARCHAR(255) NULL,
    reference_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_security_events_user (user_id, created_at),
    KEY idx_security_events_code (event_code, created_at),
    KEY idx_security_events_class (timetable_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
