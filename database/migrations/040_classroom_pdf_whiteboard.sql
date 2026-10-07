-- 040_classroom_pdf_whiteboard.sql
-- PDF whiteboard for live classrooms.
-- classroom_pdf_documents: uploaded PDF files linked to a live session.
-- online_meetings.active_pdf_id / active_pdf_page track the currently
-- displayed document and page so late-joining students receive the correct state.

CREATE TABLE IF NOT EXISTS classroom_pdf_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    timetable_id INT NOT NULL,
    uploaded_by INT NOT NULL COMMENT 'user_id of the teacher who uploaded',
    teacher_id INT NOT NULL COMMENT 'teacher user_id (host check)',
    session_id VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'live session identifier for dedup',
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(512) NOT NULL COMMENT 'relative path from web root',
    mime_type VARCHAR(80) NOT NULL DEFAULT 'application/pdf',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    total_pages INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'filled in after first render',
    status ENUM('active','closed','replaced') NOT NULL DEFAULT 'active',
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cpd_meeting (meeting_id, status),
    KEY idx_cpd_uploader (uploaded_by, created_at),
    KEY idx_cpd_timetable (timetable_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classroom_pdf_download_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pdf_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cpdl_pdf (pdf_id, downloaded_at),
    KEY idx_cpdl_user (user_id, downloaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

