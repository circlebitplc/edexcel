-- 007_official_exam_timetable.sql
-- Official Pearson/Edexcel exam series + student exam planner.
-- Campus class mocks remain in student_exams; this is reusable official data.

CREATE TABLE IF NOT EXISTS exam_series (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    year SMALLINT NOT NULL,
    session VARCHAR(40) NOT NULL,
    qualification_type VARCHAR(20) NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Colombo',
    morning_start TIME NOT NULL DEFAULT '09:00:00',
    afternoon_start TIME NOT NULL DEFAULT '13:30:00',
    source VARCHAR(255) NULL,
    source_url VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exam_series (year, session, qualification_type),
    KEY idx_exam_series_year (year, session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_series_id INT NOT NULL,
    subject VARCHAR(150) NOT NULL,
    subject_code VARCHAR(40) NULL,
    unit_code VARCHAR(40) NOT NULL,
    paper_code VARCHAR(20) NOT NULL DEFAULT '',
    unit_title VARCHAR(255) NOT NULL,
    exam_date DATE NOT NULL,
    session VARCHAR(20) NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    duration_minutes INT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Colombo',
    source VARCHAR(255) NULL,
    notes TEXT NULL,
    extra_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exam_paper (exam_series_id, unit_code, paper_code, exam_date),
    KEY idx_exams_series_date (exam_series_id, exam_date, start_time),
    KEY idx_exams_subject (subject),
    KEY idx_exams_date (exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personal selections. Named student_exam_selections because student_exams
-- already stores campus mock / class exam slots.
CREATE TABLE IF NOT EXISTS student_exam_selections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_exam_selection (student_id, exam_id),
    KEY idx_student_exam_sel_student (student_id),
    KEY idx_student_exam_sel_exam (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
