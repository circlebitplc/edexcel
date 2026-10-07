-- Migration 056: Admin Biometric Authentication & Passkeys (WebAuthn / FIDO2)
-- Dedicated tables for Administrator Passkeys, Encrypted Face Credentials,
-- Interactive Biometric Challenges, and Authentication Audit Logging.

-- 1. admin_passkeys: WebAuthn / FIDO2 Passkey credentials registered by the Administrator
CREATE TABLE IF NOT EXISTS admin_passkeys (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT NOT NULL COMMENT 'users.id of the administrator',
    credential_id      VARCHAR(255) NOT NULL COMMENT 'Base64URL encoded credential ID',
    public_key         TEXT NOT NULL COMMENT 'Credential public key in PEM or CBOR format',
    user_handle        VARCHAR(128) NULL COMMENT 'WebAuthn user handle / ID',
    name               VARCHAR(100) NOT NULL DEFAULT 'Passkey' COMMENT 'Friendly authenticator name e.g. Windows Hello, iPhone',
    attestation_format VARCHAR(64) NULL COMMENT 'Attestation statement format',
    sign_count         BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Authenticator signature counter to detect cloned authenticators',
    transports         VARCHAR(255) NULL COMMENT 'Supported transports e.g. internal,usb,ble,nfc,hybrid',
    last_used_at       DATETIME NULL COMMENT 'Timestamp when passkey was last successfully verified',
    revoked_at         DATETIME NULL COMMENT 'Revocation timestamp if revoked by administrator',
    created_ip         VARCHAR(45) NULL COMMENT 'IP address during registration',
    user_agent         VARCHAR(255) NULL COMMENT 'User Agent during registration',
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_passkey_cred (credential_id),
    KEY idx_admin_passkeys_user (user_id, revoked_at),
    KEY idx_admin_passkeys_last_used (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. admin_face_credentials: Encrypted biometric templates for Admin Webcam Face Verification (1:1 verification only)
CREATE TABLE IF NOT EXISTS admin_face_credentials (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id              INT NOT NULL COMMENT 'users.id of the administrator (1:1 verification constraint)',
    template_encrypted   MEDIUMTEXT NOT NULL COMMENT 'AES-256-GCM encrypted multi-sample face descriptor vector & profile',
    sample_count         INT NOT NULL DEFAULT 5 COMMENT 'Number of pose samples captured during enrollment',
    status               ENUM('active', 'disabled', 'revoked') NOT NULL DEFAULT 'active',
    enrolled_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_verified_at     DATETIME NULL,
    last_verification_ip VARCHAR(45) NULL,
    metadata             JSON NULL COMMENT 'Non-sensitive model version and covariance bounds (NO raw photos/videos)',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_face_user (user_id),
    KEY idx_admin_face_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. authentication_audit: Comprehensive authentication audit trail
CREATE TABLE IF NOT EXISTS authentication_audit (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id               INT NULL COMMENT 'users.id if known or authenticated',
    authentication_method VARCHAR(32) NOT NULL COMMENT 'passkey, face, password, google, totp, otp',
    success               TINYINT(1) NOT NULL COMMENT '1 = successful authentication, 0 = failed attempt',
    failure_reason        VARCHAR(255) NULL COMMENT 'Reason for authentication failure',
    ip_address            VARCHAR(45) NULL,
    user_agent            VARCHAR(255) NULL,
    session_reference     VARCHAR(64) NULL COMMENT 'Masked session ID hash or trace token',
    metadata              JSON NULL COMMENT 'Contextual details e.g. passkey device name, failure stage (NO passwords or biometrics)',
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_audit_user (user_id, created_at),
    KEY idx_auth_audit_method (authentication_method, created_at),
    KEY idx_auth_audit_ip (ip_address, created_at),
    KEY idx_auth_audit_success (success, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. admin_biometric_challenges: Ephemeral server challenges for WebAuthn and Face Liveness PAD
CREATE TABLE IF NOT EXISTS admin_biometric_challenges (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    challenge_type    ENUM('passkey_reg', 'passkey_auth', 'face_enrol', 'face_auth') NOT NULL,
    user_id           INT NOT NULL,
    challenge_token   VARCHAR(128) NOT NULL,
    challenge_payload TEXT NOT NULL COMMENT 'JSON-encoded challenge string, expected gesture sequence, or options',
    ip_address        VARCHAR(45) NULL,
    used_at           DATETIME NULL,
    expires_at        DATETIME NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bio_challenge_token (challenge_token),
    KEY idx_bio_challenge_lookup (challenge_token, expires_at, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
