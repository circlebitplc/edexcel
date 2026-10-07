-- 005_campus_lift.sql
-- Applied automatically by config/campus.php on first page load.

CREATE TABLE IF NOT EXISTS student_fee_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NULL,
    period_ym CHAR(7) NOT NULL,
    description VARCHAR(200) NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    due_date DATE NULL,
    status ENUM('due','partial','paid','waived') NOT NULL DEFAULT 'due',
    paid_at DATETIME NULL,
    marked_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_fee_period (student_id, class_id, period_ym)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
