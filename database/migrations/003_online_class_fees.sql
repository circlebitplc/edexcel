-- Online class fee snapshot.
-- Existing rows stay NULL and keep the duration-based institute fee.
-- New and edited classes store the breakdown that applied when they were saved.

ALTER TABLE timetable
    ADD COLUMN fee_rule VARCHAR(32) NULL,
    ADD COLUMN institute_online_fee DECIMAL(10,2) NULL,
    ADD COLUMN transaction_handling_fee DECIMAL(10,2) NULL,
    ADD COLUMN teacher_net_amount DECIMAL(10,2) NULL;

ALTER TABLE recurring_schedules
    ADD COLUMN fee_rule VARCHAR(32) NULL,
    ADD COLUMN institute_online_fee DECIMAL(10,2) NULL,
    ADD COLUMN transaction_handling_fee DECIMAL(10,2) NULL,
    ADD COLUMN teacher_net_amount DECIMAL(10,2) NULL;

ALTER TABLE payment_transactions
    ADD COLUMN gross_class_fee DECIMAL(10,2) NULL,
    ADD COLUMN institute_online_fee DECIMAL(10,2) NULL,
    ADD COLUMN transaction_handling_fee DECIMAL(10,2) NULL,
    ADD COLUMN teacher_net_amount DECIMAL(10,2) NULL,
    ADD COLUMN delivery_mode VARCHAR(20) NULL,
    ADD COLUMN teacher_id INT NULL;

ALTER TABLE student_lesson_fees
    ADD COLUMN gross_class_fee DECIMAL(10,2) NULL,
    ADD COLUMN institute_online_fee DECIMAL(10,2) NULL,
    ADD COLUMN transaction_handling_fee DECIMAL(10,2) NULL,
    ADD COLUMN teacher_net_amount DECIMAL(10,2) NULL;

INSERT INTO settings (setting_key, setting_value)
VALUES ('online_institute_fee', '500.00')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO settings (setting_key, setting_value)
VALUES ('online_transaction_handling_rate', '0.0600')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
