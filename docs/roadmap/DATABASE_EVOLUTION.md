# EDEXCEL COLLEGE — DATABASE EVOLUTION SPECIFICATION

This document details the database schema strategy, reusing existing production tables wherever possible and introducing targeted new tables with strict idempotency and index optimization.

---

## 1. Inventory of Reused Existing Tables

The Edexcel College database already possesses rich schema structures. **We will NOT duplicate these**:

1. **Classroom Sessions & Real-Time Sync:**
   * `online_meetings`: Reused as master meeting state. Add columns: `stage_mode`, `stage_payload_json`, `is_paused`.
   * `meeting_participants`: Reused for student presence, role, hand raised, mic/camera block status.
   * `meeting_events`: Reused for chronological event telemetry (join, leave, tab switch, quiz start).
   * `meeting_chat_messages`: Reused for classroom chat and announcements.
   * `whiteboard_strokes`: Reused for persistent drawing stroke records.
2. **Asynchronous Lesson Builder & Question Banks:**
   * `online_lessons`: Reused as container for curriculum-linked lesson content.
   * `online_lesson_items`: Reused for sequenced steps (video, activity, text page, link).
   * `online_lesson_activities`: Reused for MCQs, essays, and mixed tests.
   * `online_lesson_questions`: Reused for prompts, choices JSON, correct index, and marks.
   * `online_lesson_attempts` & `online_lesson_answers`: Reused for student grading ledger.
3. **PDF Whiteboard & Security:**
   * `classroom_pdf_documents`: Reused for uploaded slide decks and past papers.
   * `classroom_pdf_download_log`: Reused for download auditing.
   * `student_devices` & `student_active_sessions`: Reused for anti-link-sharing and single concurrent session enforcement.

---

## 2. Alterations to Existing Tables

```sql
-- Migration: 041_classroom_stage_evolution.sql

-- 1. Extend online_meetings with dynamic teaching stage and freeze state
ALTER TABLE online_meetings
    ADD COLUMN IF NOT EXISTS stage_mode ENUM('idle','pdf','whiteboard','screen_share','video','quiz','poll','spotlight') NOT NULL DEFAULT 'idle' AFTER whiteboard_open,
    ADD COLUMN IF NOT EXISTS stage_payload_json JSON NULL AFTER stage_mode,
    ADD COLUMN IF NOT EXISTS is_screen_paused TINYINT(1) NOT NULL DEFAULT 0 AFTER stage_payload_json,
    ADD COLUMN IF NOT EXISTS current_milestone_index INT NOT NULL DEFAULT 0 AFTER is_screen_paused;

-- 2. Extend meeting_participants with connection quality and focus telemetry
ALTER TABLE meeting_participants
    ADD COLUMN IF NOT EXISTS blur_count INT NOT NULL DEFAULT 0 AFTER reconnect_count,
    ADD COLUMN IF NOT EXISTS last_ping_rtt_ms INT NOT NULL DEFAULT 0 AFTER blur_count,
    ADD COLUMN IF NOT EXISTS packet_loss_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER last_ping_rtt_ms;
```

---

## 3. Targeted New Database Tables

### A. Real-Time Classroom Quick Polls (`classroom_quick_polls`)
For instant formative checks (30–60 second questions launched during live lectures).

```sql
CREATE TABLE IF NOT EXISTS classroom_quick_polls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    teacher_user_id INT NOT NULL,
    poll_type ENUM('mcq','boolean','short_text') NOT NULL DEFAULT 'mcq',
    question_text VARCHAR(500) NOT NULL,
    options_json JSON NULL,
    correct_option_index INT NULL,
    time_limit_seconds INT NOT NULL DEFAULT 60,
    status ENUM('active','closed','revealed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cqp_meeting (meeting_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classroom_poll_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    poll_id BIGINT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    selected_option INT NULL,
    text_response VARCHAR(255) NULL,
    response_time_seconds DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_correct TINYINT(1) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cpr_poll_student (poll_id, student_user_id),
    KEY idx_cpr_poll (poll_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### B. Lesson Plan Milestones (`lesson_plan_milestones`)
Allows teachers to construct the interactive class itinerary beforehand.

```sql
CREATE TABLE IF NOT EXISTS lesson_plan_milestones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timetable_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    duration_minutes INT NOT NULL DEFAULT 10,
    title VARCHAR(160) NOT NULL,
    target_stage_mode ENUM('pdf','whiteboard','screen_share','video','quiz','poll') NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    teacher_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lpm_timetable (timetable_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### C. Class Recording Chapters (`classroom_recording_chapters`)
Stores indexed navigation markers for post-class student review.

```sql
CREATE TABLE IF NOT EXISTS classroom_recording_chapters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    meeting_id BIGINT UNSIGNED NOT NULL,
    timestamp_seconds INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    stage_mode VARCHAR(40) NOT NULL,
    resource_page INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crc_meeting (meeting_id, timestamp_seconds)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### D. Curriculum Nodes & Syllabus Coverage (`curriculum_nodes`, `curriculum_coverage`)
Tracks official Edexcel IGCSE/A-Level curriculum completion.

```sql
CREATE TABLE IF NOT EXISTS curriculum_nodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id INT NOT NULL,
    parent_id INT UNSIGNED NULL,
    node_type ENUM('unit','topic','subtopic') NOT NULL DEFAULT 'topic',
    code VARCHAR(40) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_cn_subject (subject_id, parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum_coverage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    node_id INT UNSIGNED NOT NULL,
    timetable_id INT NOT NULL,
    teacher_user_id INT NOT NULL,
    covered_at DATETIME NOT NULL,
    notes TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cc_node_class (node_id, timetable_id),
    KEY idx_cc_timetable (timetable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
