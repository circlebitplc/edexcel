-- Applied at runtime by ClassSessionFeeCalculator::ensureSchema().
-- Existing timetable and payment rows are not recalculated.

INSERT INTO settings (setting_key, setting_value)
VALUES ('in_college_institute_fee', '500.00')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS teacher_bank_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    account_holder_name VARCHAR(160) NOT NULL,
    account_number VARCHAR(34) NOT NULL,
    branch VARCHAR(120) NOT NULL,
    branch_code VARCHAR(20) NULL,
    account_type VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teacher_bank_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_payouts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    bank_account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payout_date DATE NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'bank',
    payout_reference VARCHAR(80) NULL,
    processed_by INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_teacher_payouts_teacher (teacher_id, status, created_at),
    INDEX idx_teacher_payouts_reference (payout_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_payout_items (
    payout_id INT UNSIGNED NOT NULL,
    payment_transaction_id BIGINT UNSIGNED NOT NULL,
    teacher_net_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_transaction_id),
    INDEX idx_payout_items_payout (payout_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_payment_audit_entity (entity_type, entity_id),
    INDEX idx_payment_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
