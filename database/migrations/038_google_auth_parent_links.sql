-- 038_google_auth_parent_links.sql
-- Google SSO for students/parents + admin-verified parent↔student link requests.
-- Column alters for users/parent_accounts are applied by ensure_ops_schema() healers
-- (safe IF NOT EXISTS style) so this file focuses on new tables.

CREATE TABLE IF NOT EXISTS parent_link_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    rejection_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plr_status (status, requested_at),
    INDEX idx_plr_parent (parent_id, status),
    INDEX idx_plr_student (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_invitations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_email VARCHAR(255) NOT NULL,
    student_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    used_by_parent_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_parent_invite_token (token_hash),
    INDEX idx_parent_invite_email (parent_email, student_id),
    INDEX idx_parent_invite_student (student_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
