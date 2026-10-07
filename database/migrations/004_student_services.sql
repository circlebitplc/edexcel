-- 004_student_services.sql
-- Student enrollments, learning materials, homework, exams, events, progress, and notifications

CREATE TABLE IF NOT EXISTS student_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_enrollment (student_id, class_id),
    INDEX idx_se_student (student_id),
    INDEX idx_se_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_notifications_student (student_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    timetable_id INT NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    note VARCHAR(500) NULL,
    marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_attendance (student_id, timetable_id),
    KEY idx_student_attendance_student (student_id, marked_at),
    KEY idx_student_attendance_timetable (timetable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_materials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id INT NULL,
    subject_id INT NULL,
    teacher_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    file_url VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_materials_class (class_id, created_at),
    KEY idx_student_materials_subject (subject_id, created_at),
    KEY idx_student_materials_teacher (teacher_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_homework (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id INT NULL,
    subject_id INT NULL,
    teacher_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    link VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_homework_class (class_id, due_date),
    KEY idx_student_homework_subject (subject_id, due_date),
    KEY idx_student_homework_teacher (teacher_id, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_exams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    class_id INT NULL,
    subject_id INT NULL,
    exam_date DATE NOT NULL,
    exam_time TIME NULL,
    location VARCHAR(200) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_exams_date (exam_date, subject_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_events_date (event_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_progress (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NULL,
    subject_id INT NULL,
    metric VARCHAR(100) NOT NULL DEFAULT 'Progress',
    score DECIMAL(7,2) NOT NULL DEFAULT 0,
    max_score DECIMAL(7,2) NOT NULL DEFAULT 100,
    recorded_at DATE NOT NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_progress_student (student_id, subject_id, recorded_at),
    KEY idx_student_progress_class (class_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
