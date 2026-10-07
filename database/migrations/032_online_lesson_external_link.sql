-- External link items on an existing video lesson.
-- ensure_online_lesson_schema() applies the same change on the next lesson page.
-- Existing video, page, and question rows are left as they are.

ALTER TABLE online_lesson_items
    MODIFY item_type ENUM('video','activity','page','external_link') NOT NULL DEFAULT 'video';

ALTER TABLE online_lesson_items
    ADD COLUMN link_url VARCHAR(500) NULL,
    ADD COLUMN open_new_tab TINYINT(1) NOT NULL DEFAULT 1;
