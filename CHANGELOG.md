# Changelog

## 2026-09-25 — teacher classroom bar and PDF document viewer

- Teacher desktop no longer shows the top brand header or the Live / timer / connection pill. The whiteboard uses that space.
- Teacher desktop controls sit in one bottom bar: Mic, Camera, Board, PDF, Share, People, Chat, Present, Speaker, Gallery, Monitor, Cameras, Record, Settings, Exit, End class, Leave. On a narrower desktop window, Present through Exit fold into More. Phones still use the existing mobile dock.
- The left drawing rail is icons only. Favorites, “Star tools to pin”, and Recent are not shown.
- The eraser stroke is about 1.5 times the pen width, clamped between 4 and 20. The pen size is unchanged.
- An uploaded PDF opens as a vertical document. Pages are rendered with the existing PDF.js pipeline when they scroll into view. Teacher marks stay on a separate transparent layer, so drawing does not reload the PDF. The page indicator follows the visible page. Teacher scroll position is sent on a throttle; students follow it. The Upload PDF button in the bottom bar is the same uploader.
- Restore copy: `backup/livekit-restore-20260925-1225/`.

## 2026-09-24 — live verification

No application code was changed. An existing teacher session was used on the live site. Admin, student, SMS, OnePay, and LiveKit sessions were not available. Details are in `SYSTEM_AUDIT.md`.

## 2026-09-24 — production file check

No application code was changed. Production copies of the timetable and teacher-payment PHP files were already identical to the local files. `CHANGELOG.md` was not on the server and was uploaded. Anonymous requests to the timetable, teacher schedule, payment, and payment AJAX URLs returned HTTP 302 to the login page. No database migration is required. Logged-in payment, SMS, LiveKit, mobile, IDOR, and file-upload checks were not run.

## 2026-09-24 — second-stage verification

No application code was changed. The first-round timetable and teacher-payment fixes were confirmed still present. Unit tests were run again for SMS, teacher visibility, OnePay callback checks, payment verification, and staff Google sign-in. A live classroom, payment gateway, SMS handset, and mobile layout were not tested: the production classroom URL redirects to staff login, and these local files have not been uploaded.

## 2026-09-24

- Teachers with no linked teacher profile can no longer open the full timetable. The page returns 403 until `users.teacher_id` is set.
- Timetable list, filters, pending count, and CSV export include lessons where the teacher is the substitute.
- Timetable cards and the Teacher Payments row show the teacher payable amount from `TeacherPaymentSmsService`. Online and in-college lessons use the stored teacher net. Other lessons use the duration-based amount.
- Teacher Payments summary cards are labelled as institute totals so they are not read as the teacher amount.
- Timetable status text says “Teacher pending” and “Teacher paid”.
- `SYSTEM.md` now documents the fee calculator, teacher visibility, and teacher-payment SMS. Details of this audit are in `SYSTEM_AUDIT.md`.

## 2026-09-23

- Marking a teacher lesson or online payout as paid sends one SMS through the existing SMS gateway after the payment is committed.
- A failed SMS does not change the paid status. Resend is a separate admin action and is logged on its own row in `teacher_payment_sms_log`.
