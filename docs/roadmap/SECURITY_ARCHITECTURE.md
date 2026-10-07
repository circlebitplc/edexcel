# EDEXCEL COLLEGE — SECURITY & PRIVACY ARCHITECTURE

This document establishes the security perimeter, credential isolation, and content integrity safeguards for the live classroom system.

---

## 1. Classroom Access & Expiring Token Governance

1. **Short-Lived Join Tokens:**
   * Direct room URLs (`room.php?m=...`) cannot be used without an authenticated, time-bound session ticket.
   * When an admitted student clicks *"Join Class"*, the server generates an HMAC-SHA256 signed JWT containing:
     * `user_id`, `role` (`student`), `timetable_id`, `livekit_room`, `device_key`, and `exp` ($15 \text{ minutes}$).
   * Once connected to LiveKit, the token cannot be reused by another browser or device.
2. **Anti-Link Sharing & Device Limit Enforcement:**
   * Employs the existing `student_devices` and `student_active_sessions` database architecture:
     * Maximum 4 registered hardware devices per student account.
     * Strict single concurrent active session: If a student account connects from a second device, the first device is immediately disconnected with a security alert.
3. **Fee Verification at the Gateway:**
   * Students with unpaid monthly tuition are blocked by `ClassroomAccessService::PAY` and cannot generate a LiveKit token.

---

## 2. Teacher Screen Sharing Privacy & Leak Prevention

Screen sharing in an educational setting carries severe data-leak risks (teacher personal emails, WhatsApp chats, other students' grades).

1. **Native Freeze Frame Guarantee:**
   * The Alt+P global shortcut operates at the driver/framebuffer level in C++.
   * When engaged, the outgoing video pipeline ceases pulling updates from Windows DWM. Even if the teacher accidentally opens a confidential spreadsheet on their physical monitor, the students receive the static frozen bitmap.
2. **Notification & Boundary Masking:**
   * During presentation mode, the C++ companion suppresses Windows Action Center popups via Windows API `ToastNotificationManager`.
   * When "Application Window" sharing is selected, only pixels belonging to that specific `HWND` are captured. Any overlapping popup or secondary window is masked with black pixels or clipped.

---

## 3. DataChannel & API Security Controls

1. **Role-Based Message Filtering on DataChannel:**
   * LiveKit DataChannel events are stamped with the publisher's cryptographic identity.
   * Student clients are strictly forbidden from publishing `STAGE_CHANGE`, `SCREEN_FREEZE_TOGGLE`, or `POLL_START` events. If received by other clients, the payload is rejected and logged as a malicious tamper attempt.
2. **File Upload Hardening:**
   * PDF uploads in `api/classroom/pdf.php` undergo strict server-side validation:
     * File size limit capped at $30\text{ MB}$.
     * Magic byte inspection verifying `%PDF-1.x` header.
     * Content sanitization stripping executable JavaScript embedded within PDF streams.
3. **Audit Trails & Security Telemetry:**
   * All administrative actions (kicking students, muting microphones, locking room, downloading exams) are written to `meeting_events` with timestamps and IP addresses for compliance auditing.
