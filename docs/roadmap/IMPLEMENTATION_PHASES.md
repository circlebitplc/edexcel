# EDEXCEL COLLEGE — DETAILED IMPLEMENTATION PHASES

This document breaks down each implementation phase into actionable technical units, specifying files, database changes, APIs, LiveKit adaptations, dependencies, testing, and rollback plans.

---

## Phase 0: Stability & Architecture Cleanup

* **Features:**
  * Create pre-migration backup and restore script.
  * Audit and clean up stale temporary files.
  * Create executable backup of `edexcel_server.exe`.
* **Files to Modify:**
  * `deploy/backup_restore_helper.php`
* **New Files:**
  * `tools/create_restore_point.ps1`
* **Database Changes:** None.
* **APIs:** None.
* **LiveKit Changes:** None.
* **C++ Changes:** None.
* **Dependencies:** None.
* **Testing Requirements:** Run `tests/Step69DeploymentHealthCheck.php` to establish a passing baseline.
* **Rollback Strategy:** Revert file changes via Git.

---

## Phase 1: Professional Screen-Sharing System (Primary Focus)

* **Features:**
  * Native freeze frame (students see frozen last frame while teacher works privately).
  * System-wide global hotkey `Alt + P` (Live <-> Paused).
  * Area selection overlay integration with web controls.
  * Single window and multi-window selective sharing.
  * Top bar status indicators: `● LIVE` (Green) and `⏸️ FROZEN` (Amber).
* **Files to Modify:**
  * `fb-update-sender/UpdateSender.cpp` (Freeze frame retention logic).
  * `monitor-lib/MonitorServer.h` & `MonitorServer.cpp` (Hotkey hooks & state reporting).
  * `http-server-lib/HttpRequestHandler.cpp` (Expose `/monitor/share/*` commands).
  * `assets/js/classroom.js` (Web UI controls and companion loopback polling).
  * `classroom/room.php` (Add pause/resume UI pill and hotkey listener).
* **New Files:**
  * None (reuse and enhance existing C++ files).
* **Database Changes:**
  * `ALTER TABLE online_meetings ADD COLUMN IF NOT EXISTS is_screen_paused TINYINT(1) NOT NULL DEFAULT 0;`
* **APIs:**
  * `GET /monitor/share/status` (Returns pause state, active mode, rect/hwnd).
  * `POST /monitor/share/pause` (Triggers freeze frame).
  * `POST /monitor/share/resume` (Restores live streaming).
  * `POST /monitor/share/select_area` (Launches drag overlay).
* **LiveKit Changes:**
  * Publish `SCREEN_FREEZE_TOGGLE` event across DataChannel so remote students see a static canvas frame or paused watermark if consuming via WebRTC.
* **C++ Changes:**
  * Register `RegisterHotKey(NULL, ID_HOTKEY_SCREEN_PAUSE, MOD_ALT, 'P')`.
  * In `UpdateSender.cpp`, prevent `m_frameBuffer.setColor(20,24,32)` when paused; keep the last frame intact.
* **Dependencies:** Win32 API, GDI+.
* **Testing Requirements:**
  * Verify Alt+P toggles pause state when teacher is inside Excel, WhatsApp, or Chrome.
  * Confirm student screen does not flicker or turn dark, but remains frozen on the last frame.
* **Rollback Strategy:** Replace `edexcel_server.exe` with `edexcel_server_backup.exe`.

---

## Phase 2: Unified Teaching Stage

* **Features:**
  * Central stage container switching between PDF, Whiteboard, Screen Share, Video, Quiz, and Poll.
  * Stage state machine (`IDLE`, `PDF`, `WHITEBOARD`, `SCREEN_SHARE`, `VIDEO`, `QUIZ`, `POLL`, `STUDENT_SPOTLIGHT`).
  * Instant switching without interrupting LiveKit audio.
* **Files to Modify:**
  * `classroom/room.php` (Refactor main layout into `#ckStageMaster`).
  * `assets/js/classroom.js` (Implement `StageController` state machine).
  * `assets/css/classroom.css` (Stage layout and responsive styling).
* **New Files:**
  * `assets/js/classroom-stage.js` (Dedicated stage state machine module).
* **Database Changes:**
  * `ALTER TABLE online_meetings ADD COLUMN IF NOT EXISTS stage_mode VARCHAR(32) NOT NULL DEFAULT 'idle';`
  * `ALTER TABLE online_meetings ADD COLUMN IF NOT EXISTS stage_payload_json JSON NULL;`
* **APIs:**
  * `POST /api/classroom/control.php?action=set_stage`
* **LiveKit Changes:**
  * Broadcast `STAGE_CHANGE` via DataChannel to all participants.
* **C++ Changes:** None.
* **Dependencies:** Phase 1.
* **Testing Requirements:** Cycle all 8 stage modes; verify zero memory leaks or track drops.
* **Rollback Strategy:** Revert `classroom/room.php` and `assets/js/classroom.js`.

---

## Phase 3: STEM Whiteboard & Synchronized PDF Engine

* **Features:**
  * KaTeX mathematical formula insertion on whiteboard.
  * Coordinate grid and isometric dot grid backgrounds.
  * Geometric snapping tools (ruler, protractor, circles, lines).
  * Laser pointer with decay trail.
  * Synchronized PDF page turns and zoom with "Sync with Teacher" student pill.
* **Files to Modify:**
  * `assets/js/classroom-board.js` (Add math tool and grid canvas renderer).
  * `assets/js/classroom-board-pdf.js` (Add uncoupled scrolling and sync pill).
  * `classroom/room.php` (Load KaTeX CSS and JS).
* **New Files:**
  * `assets/js/classroom-board-math.js` (KaTeX equation modal and stamp tool).
* **Database Changes:**
  * `ALTER TABLE whiteboard_strokes ADD COLUMN IF NOT EXISTS page_number INT NOT NULL DEFAULT 1;`
* **APIs:**
  * `POST /api/classroom/pdf.php?action=sync_position`
* **LiveKit Changes:**
  * Transmit laser pointer coordinates and KaTeX stamp deltas via DataChannel.
* **C++ Changes:** None.
* **Dependencies:** KaTeX library (CDN).
* **Testing Requirements:** Draw equations on PDF slides; verify exact coordinate alignment on student viewports.
* **Rollback Strategy:** Revert JS modules.

---

## Phase 4: Interactive Classroom Activity Engine

* **Features:**
  * 1-Click Quick Poll (MCQ, True/False, Short Answer) launched from top bar.
  * 30-to-60 second countdown timer.
  * Real-time aggregation of student responses.
  * "Reveal Results" and "Show Solution" controls.
  * Auto-committing results into student database.
* **Files to Modify:**
  * `classroom/room.php` (Add Quick Poll modal and student answer sheet).
  * `assets/js/classroom.js` (Poll event listeners and live bar chart renderer).
* **New Files:**
  * `api/classroom/poll.php` (Poll creation and response endpoints).
  * `assets/js/classroom-poll.js` (Interactive poll component).
* **Database Changes:**
  * Create `classroom_quick_polls` and `classroom_poll_responses` tables.
* **APIs:**
  * `POST /api/classroom/poll.php?action=create`
  * `POST /api/classroom/poll.php?action=submit`
  * `GET /api/classroom/poll.php?action=results`
* **LiveKit Changes:**
  * Broadcast `POLL_START`, `POLL_TICK`, and `POLL_REVEAL` via DataChannel.
* **C++ Changes:** None.
* **Dependencies:** Phase 2.
* **Testing Requirements:** Simulate 50 simultaneous responses; verify accurate tallying within 1 second.
* **Rollback Strategy:** Drop `classroom_quick_polls` tables.

---

## Phase 5: Lesson Planning & Syllabus Driver

* **Features:**
  * Side-drawer lesson plan agenda (e.g., 00:00-00:10 Intro, 00:10-00:30 PDF, etc.).
  * 1-Click "Next Activity" button that sets stage, opens resource, and starts timers.
  * Linking class progress to official Edexcel syllabus topics.
* **Files to Modify:**
  * `classroom/room.php` (Inject lesson plan drawer).
  * `campus/today.php` (Add lesson plan builder button).
* **New Files:**
  * `assets/js/classroom-agenda.js`
  * `api/classroom/agenda.php`
* **Database Changes:**
  * Create `lesson_plan_milestones` table.
* **APIs:**
  * `GET /api/classroom/agenda.php?action=load`
  * `POST /api/classroom/agenda.php?action=advance`
* **LiveKit Changes:** None.
* **C++ Changes:** None.
* **Dependencies:** Phase 2 and 4.
* **Testing Requirements:** Step through full 5-milestone lesson; confirm automated stage transitions.
* **Rollback Strategy:** Revert UI changes.

---

## Phase 6: Assessment & Examination System

* **Features:**
  * Timed exam mode with question bank randomization.
  * Fullscreen enforcement and blur/focus-lost detection.
  * Negative marking support.
  * Real-time proctoring telemetry for teachers.
* **Files to Modify:**
  * `campus/online_lesson.php` (Add exam configuration flags).
  * `student/lesson.php` (Add proctored lockdown UI).
* **New Files:**
  * `assets/js/classroom-proctor.js`
* **Database Changes:**
  * `ALTER TABLE online_lesson_activities ADD COLUMN IF NOT EXISTS is_proctored TINYINT(1) NOT NULL DEFAULT 0;`
  * `ALTER TABLE meeting_participants ADD COLUMN IF NOT EXISTS blur_count INT NOT NULL DEFAULT 0;`
* **APIs:**
  * `POST /api/classroom/control.php?action=log_blur`
* **LiveKit Changes:** None.
* **C++ Changes:** None.
* **Dependencies:** Phase 4.
* **Testing Requirements:** Trigger tab blur; verify immediate increment in teacher proctoring column.
* **Rollback Strategy:** Revert proctoring JS script.

---

## Phase 7: Class Recording & Lesson Archival

* **Features:**
  * Synchronized composite recording (Audio, Video, Whiteboard, Stage).
  * Automatic generation of chapter markers tied to lesson plan milestones.
  * Student chapter-based video replay player.
* **Files to Modify:**
  * `classroom/room.php` (Trigger chapter marker on stage transition).
  * `student/recording.php` (Render interactive chapter timeline).
* **New Files:**
  * `api/classroom/recording_chapters.php`
* **Database Changes:**
  * Create `classroom_recording_chapters` table.
* **APIs:**
  * `POST /api/classroom/recording_chapters.php?action=add_marker`
  * `GET /api/classroom/recording_chapters.php?action=get_markers`
* **LiveKit Changes:** Egress worker triggers chapter events.
* **C++ Changes:** Optional local MP4 fallback capture via FFmpeg.
* **Dependencies:** Phase 2, Phase 5.
* **Testing Requirements:** Verify MP4 playback skips to exact chapter timestamp upon click.
* **Rollback Strategy:** Drop `classroom_recording_chapters` table.

---

## Phase 8: Real-Time Analytics & Progress Tracking

* **Features:**
  * Post-class comprehension scoring and participation breakdown.
  * Automatic class-wide weak-topic diagnostic report.
  * Automated student attendance ledger export.
* **Files to Modify:**
  * `campus/lesson_analytics.php`
  * `student/progress.php`
* **New Files:**
  * `campus/classroom_diagnostics.php`
* **Database Changes:** None (aggregates existing tables).
* **APIs:**
  * `GET /api/classroom/analytics.php`
* **LiveKit Changes:** None.
* **C++ Changes:** None.
* **Dependencies:** Phase 4, Phase 6.
* **Testing Requirements:** Verify calculations for class median, pass rate, and topic discrimination.
* **Rollback Strategy:** Revert analytics PHP pages.

---

## Phase 9: AI Pedagogical Assistant (Human-in-the-Loop)

* **Features:**
  * AI Question Generator: Ingests PDF text to generate 10 MCQs with distractors and explanations.
  * AI Study Notes: Summarizes session transcript into bullet points for student revision.
  * AI Assisted Marking: Pre-populates suggested rubric scores for essay answers.
* **Files to Modify:**
  * `campus/online_lesson.php` (Add "Generate Questions with AI" button).
  * `src/Services/OnlineLessonService.php`
* **New Files:**
  * `src/Services/AiPedagogyService.php`
  * `api/classroom/ai_assist.php`
* **Database Changes:**
  * Create `ai_prompts_cache` table.
* **APIs:**
  * `POST /api/classroom/ai_assist.php?action=generate_mcq`
  * `POST /api/classroom/ai_assist.php?action=summarize_lesson`
* **LiveKit Changes:** None.
* **C++ Changes:** None.
* **Dependencies:** LLM Provider API Key.
* **Testing Requirements:** Verify questions parse cleanly into `online_lesson_questions` table.
* **Rollback Strategy:** Disable AI button in UI.

---

## Phase 10: Local LAN Classroom Mode (Zero-Internet Campus)

* **Features:**
  * Offline computer lab operation using `edexcel_server.exe` on port 5800/5900.
  * mDNS auto-discovery (`edexcel.local`) and dynamic QR code generation.
  * Local high-speed RFB screen streaming and offline quiz engine.
* **Files to Modify:**
  * `monitor-lib/TeacherDashboard.h` (Enhance local dashboard with local PDF and quiz runner).
  * `http-server-lib/HttpRequestHandler.cpp` (Serve local static classroom app).
* **New Files:**
  * None.
* **Database Changes:** SQLite local cache on teacher PC.
* **APIs:** Local HTTP endpoints on port 5800.
* **LiveKit Changes:** None (bypassed entirely in LAN mode).
* **C++ Changes:** Implement mDNS responder using Win32 sockets.
* **Dependencies:** Phase 1.
* **Testing Requirements:** Disconnect WAN router uplink; verify students can join via `http://teacher-ip:5800/` and view screen.
* **Rollback Strategy:** Revert `HttpRequestHandler.cpp`.

---

## Phase 11: Enterprise Administration & Institutional Governance

* **Features:**
  * Teaching Assistant (TA) co-host permissions.
  * Automated WhatsApp/SMS attendance notifications to parents.
  * Centralized syllabus coverage tracking across all classes.
* **Files to Modify:**
  * `admin/timetable.php`
  * `parent/progress.php`
* **New Files:**
  * `admin/curriculum_coverage.php`
* **Database Changes:**
  * Create `curriculum_nodes` and `curriculum_coverage` tables.
* **APIs:**
  * `POST /api/admin/curriculum.php`
* **LiveKit Changes:** Support co-host permission tokens.
* **C++ Changes:** None.
* **Dependencies:** All previous phases.
* **Testing Requirements:** Verify parent receives WhatsApp alert within 60 seconds of class completion.
* **Rollback Strategy:** Revert admin dashboard PHP files.
