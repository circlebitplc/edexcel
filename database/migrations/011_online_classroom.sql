-- 011_online_classroom.sql
-- Live classroom tables. timetable.delivery_mode and recurring_schedules.delivery_mode
-- are added by ensure_classroom_schema() so ALTER is safe if they already exist.

CREATE TABLE IF NOT EXISTS online_meetings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timetable_id INT NOT NULL,
    public_id CHAR(16) NOT NULL,
    livekit_room VARCHAR(80) NOT NULL,
    status ENUM('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled',
    host_user_id INT NULL,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    waiting_room TINYINT(1) NOT NULL DEFAULT 0,
    students_can_publish TINYINT(1) NOT NULL DEFAULT 1,
    students_can_share TINYINT(1) NOT NULL DEFAULT 0,
    whiteboard_open TINYINT(1) NOT NULL DEFAULT 0,
    students_can_draw TINYINT(1) NOT NULL DEFAULT 0,
    recording_requested TINYINT(1) NOT NULL DEFAULT 0,
    class_recording_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_om_timetable (timetable_id),
    UNIQUE KEY uq_om_public (public_id),
    UNIQUE KEY uq_om_room (livekit_room),
    KEY idx_om_status (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_participants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    livekit_identity VARCHAR(80) NOT NULL,
    joined_at DATETIME NOT NULL,
    left_at DATETIME NULL,
    last_seen_at DATETIME NOT NULL,
    duration_seconds INT NOT NULL DEFAULT 0,
    reconnect_count INT NOT NULL DEFAULT 0,
    hand_raised TINYINT(1) NOT NULL DEFAULT 0,
    kicked TINYINT(1) NOT NULL DEFAULT 0,
    camera_blocked TINYINT(1) NOT NULL DEFAULT 0,
    mic_blocked TINYINT(1) NOT NULL DEFAULT 0,
    screenshare_allowed TINYINT(1) NOT NULL DEFAULT 0,
    waiting_status ENUM('none','waiting','admitted','denied') NOT NULL DEFAULT 'none',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mp_meeting_user (meeting_id, user_id),
    KEY idx_mp_meeting (meeting_id, left_at),
    KEY idx_mp_user (user_id, joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    user_id INT NULL,
    event_type VARCHAR(40) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_me_meeting (meeting_id, created_at),
    KEY idx_me_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    body VARCHAR(2000) NOT NULL,
    is_announcement TINYINT(1) NOT NULL DEFAULT 0,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    recipient_user_id INT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mc_meeting (meeting_id, created_at),
    KEY idx_mc_private (meeting_id, is_private, recipient_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whiteboard_strokes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    stroke_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wb_meeting (meeting_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
