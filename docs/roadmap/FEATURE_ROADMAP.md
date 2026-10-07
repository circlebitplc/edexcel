# EDEXCEL COLLEGE — COMPLETE FEATURE ROADMAP

This roadmap details each sequential phase for evolving the platform from the current codebase into an enterprise-grade institution system.

---

## Phase 0: Stability, Cleanup & System Safeguards
* **Objective:** Establish rock-solid baseline and safety nets before new feature development.
* **Scope:**
  * Implement automated database backup hook prior to migration execution.
  * Formalize rollback snapshots for `edexcel_server.exe` and `public_html/`.
  * Fix existing dark slate pause behavior in `fb-update-sender/UpdateSender.cpp` so it freezes the last frame instead of blanking out.
* **Success Criteria:** Safe baseline with zero regressions in current `room.php` and VNC monitor.

---

## Phase 1: Professional Screen-Sharing System (Primary Focus)
* **Objective:** Deliver native Windows screen sharing with freeze-frame privacy, Alt+P hotkey, area selection, and multi-window compositing.
* **Scope:**
  * **Freeze-Frame Buffer:** When paused, hold the last frame in `m_frameBuffer` and suppress incoming changes (`updCont->changedRegion.clear()`).
  * **Global Alt+P Hotkey:** Register `RegisterHotKey` in C++ companion; toggles `pauseSharing()` and `resumeSharing()`.
  * **Presentation Mode:** Minimize notifications and border distractions.
  * **Loopback Web Bridge:** Enable Web Classroom UI to trigger and reflect native sharing states.
* **Success Criteria:** Teacher presses Alt+P anywhere on Windows; students see a frozen screen while teacher opens private files.

---

## Phase 2: Unified Teaching Stage & State Machine
* **Objective:** Consolidate PDF, Whiteboard, Video, Screen Share, and Activities into one unified stage canvas.
* **Stage States:**
  1. `IDLE` (Waiting screen / Class countdown)
  2. `PDF` (Synced slide & textbook deck)
  3. `WHITEBOARD` (Collaborative STEM board)
  4. `SCREEN_SHARE` (Native Desktop / Browser feed)
  5. `VIDEO` (Synchronized synchronized video player)
  6. `QUIZ` (Live interactive exam or formative test)
  7. `POLL` (Instant 30-second comprehension check)
  8. `STUDENT_SPOTLIGHT` (Broadcasting an active student)
* **Success Criteria:** Seamless switching between teaching modes in $<300$ ms without tearing or disconnecting LiveKit audio.

---

## Phase 3: STEM Collaborative Whiteboard & Synchronized PDF
* **Objective:** Deliver advanced mathematics, physics, and chemistry diagramming on the whiteboard and synchronize PDF positions.
* **Scope:**
  * Add KaTeX mathematical formula rendering tool.
  * Add Cartesian coordinate and isometric grid backgrounds.
  * Add ruler, protractor, and geometric line/circle snapping.
  * Synchronize PDF viewport coordinates with optional "Student Free Scroll / Sync with Teacher" toggle.
* **Success Criteria:** Teachers can annotate complex calculus and coordinate geometry smoothly.

---

## Phase 4: Interactive Classroom Activity Engine
* **Objective:** Bridge the existing asynchronous `online_lessons` question bank into real-time live classroom activities.
* **Scope:**
  * Quick Poll (MCQ, True/False, Short Answer) launched directly from the teacher top bar.
  * Real-time aggregation of student answers via WebRTC DataChannel.
  * Teacher controls: "Reveal Distribution" and "Show Correct Answer".
  * Auto-commit results to `online_lesson_attempts` and `online_lesson_answers`.
* **Success Criteria:** Teacher launches a 60-second quiz; 100% of responses tally and display in real time.

---

## Phase 5: Lesson Planning & Syllabus Driver
* **Objective:** Allow teachers to pre-schedule the class timeline with automated stage orchestration.
* **Scope:**
  * Interactive lesson itinerary drawer (e.g., 10 min PDF, 15 min Board, 10 min Quiz).
  * One-click "Next Section" button that switches stage mode and auto-loads resources.
  * Curriculum progress linkage to track syllabus completion.
* **Success Criteria:** A teacher conducts an entire 90-minute session following an automated agenda without manually loading files.

---

## Phase 6: High-Integrity Assessment & Exam Mode
* **Objective:** Formal proctored examination mode with anti-cheating telemetry.
* **Scope:**
  * Enforced fullscreen mode with tab-switch / focus-lost detection.
  * Negative marking engine and randomized question permutations.
  * Real-time proctoring alerts to teacher: "Student $X$ switched tabs (Attempt 2/3)".
* **Success Criteria:** Credible institution-wide term tests run directly in the classroom.

---

## Phase 7: Composite Class Recording & Lesson Chaptering
* **Objective:** Complete automated archival with chapter markers.
* **Scope:**
  * Server-side composite egress recording (audio, video, whiteboard, stage).
  * Automatic generation of chapter markers tied to lesson plan milestones.
  * Student searchable replay index with synchronized slide timestamps.
* **Success Criteria:** Students can jump directly to "Question 4 Explanation" in the recorded video.

---

## Phase 8: Real-Time Analytics & Progress Tracking
* **Objective:** Comprehensive diagnostic reporting for students, teachers, and school administrators.
* **Scope:**
  * Individual student comprehension scores and participation metrics.
  * Class-wide weak topic detection (e.g., "68% missed Question 5 on Organic Chemistry").
  * Exportable PDF report cards and Excel ledgers.
* **Success Criteria:** Teachers receive immediate pedagogical feedback upon class completion.

---

## Phase 9: AI Pedagogical Assistant (Human-in-the-Loop)
* **Objective:** Reduce teacher administrative burden without removing human oversight.
* **Scope:**
  * AI Question Generator: Ingests PDF textbook pages to draft 10 MCQs with distractors.
  * AI Study Notes: Automatically generates post-class bulleted study guides from session transcripts.
  * AI Marking Suggestions: Pre-scores student short answers against a rubric for teacher confirmation.
* **Success Criteria:** Cuts teacher preparation and marking time by $>60\%$.

---

## Phase 10: Local LAN Classroom Mode (Zero-Internet Campus)
* **Objective:** Complete functionality in computer labs during internet outages.
* **Scope:**
  * mDNS ZeroConf auto-discovery (`edexcel.local`) on port 5800.
  * Dynamic QR code generated on teacher projector screen for instant student connection.
  * Local high-performance RFB screen streaming and offline quiz engine.
* **Success Criteria:** Full computer lab class conducted with internet cable physically disconnected.

---

## Phase 11: Enterprise Administration & Institutional Governance
* **Objective:** Multi-campus administration, timetable scheduling, and parent communication.
* **Scope:**
  * Multi-teacher co-hosting and Teaching Assistant (TA) permissions.
  * Automated WhatsApp and SMS attendance digests sent to parents at class end.
  * Comprehensive security audit logs and fee-based access control enforcement.
* **Success Criteria:** Seamless multi-branch operations for hundreds of concurrent classes.
