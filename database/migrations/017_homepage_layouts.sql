-- Active public homepage layout (stored in existing settings table).
INSERT INTO settings (setting_key, setting_value)
VALUES ('active_homepage_layout', 'layout1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
