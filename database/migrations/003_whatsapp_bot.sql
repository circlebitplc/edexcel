-- 003_whatsapp_bot.sql
-- Evolution API WhatsApp bot integration contacts and message logs

CREATE TABLE IF NOT EXISTS whatsapp_bot_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone VARCHAR(32) NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    profile_picture_url VARCHAR(1000) NULL,
    profile_picture_checked_at DATETIME NULL,
    verified_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_whatsapp_bot_phone (phone),
    KEY idx_whatsapp_bot_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_bot_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone VARCHAR(32) NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    message TEXT NOT NULL,
    event_name VARCHAR(80) NULL,
    message_id VARCHAR(191) NULL,
    payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_whatsapp_bot_messages_phone (phone),
    KEY idx_whatsapp_bot_messages_created (created_at),
    KEY idx_whatsapp_bot_messages_message_id (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
