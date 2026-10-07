# System audit — 24 September 2026

This file records what was inspected, what was fixed, and what was not verified. It does not claim that every page in the college system was executed.

Later classroom work on 25 September 2026 (teacher bottom bar and the scrollable PDF document viewer) is in `CHANGELOG.md` and `SYSTEM.md` §38.6. This audit was not repeated for that work. A live two-browser class was not part of the 25 September check.

## Scope inspected

- Timetable list, export, teacher schedule, and timetable API teacher filters
- `ClassSessionFeeCalculator`, timetable add-class fee preview, teacher bank gate
- Teacher lesson payment, online teacher payout, and `TeacherPaymentSmsService`
- Whiteboard live-ink path in `classroom-board.js` and `classroom.js`
- `SYSTEM.md` against those behaviours

Not executed in a browser against a logged-in admin, a LiveKit room, a payment gateway, or the SMS handset.

## Issues

| Date | Issue | Severity | Root cause | Fix | Files | Database | Testing | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2026-09-24 | A teacher account with no `users.teacher_id` could open the timetable and see every class | HIGH / SECURITY | `timetable/index.php` treated “teacher role but teacher_id 0” as neither admin nor teacher, so the teacher filter was skipped | HTTP 403 with the same linked-profile message used by the schedule and API | `timetable/index.php` | None | Contract test asserts the profile message | Fixed in code. Not clicked in a browser |
| 2026-09-24 | A cover teacher did not see substitute lessons on the main timetable or in CSV export | HIGH | Those queries filtered `teacher_id` only. Schedule, weekly view, dashboard, and the repository already included `substitute_teacher_id` | Same OR filter on the list, class/subject dropdowns, pending count, and CSV | `timetable/index.php`, `timetable/export_csv.php` | None. Column already added by campus schema heal | `TeacherVisibilityContractTest` | Fixed in code |
| 2026-09-24 | Timetable cards showed `student count × flat fee`, which is not the teacher payment stored for online or in-college lessons | HIGH / PAYMENT | The card and page total ignored `ClassSessionFeeCalculator` | Card amount and page “Teacher pay” total use `TeacherPaymentSmsService::payableCents()` | `timetable/index.php`, `src/Services/TeacherPaymentSmsService.php` | None | Existing SMS amount tests plus syntax check | Fixed in code |
| 2026-09-24 | Teacher Payments row amount was the institute fee while the SMS used teacher net | HIGH / PAYMENT | `payments.php` summed `institute_fee` for the row and the audit | Row amount and the mark-paid audit use the teacher payable amount. Summary cards are labelled institute fee so the two figures are not presented as the same thing | `timetable/payments.php`, `src/Services/TimetablePaymentService.php` | None | Syntax check. No live mark-paid | Fixed in code |
| 2026-09-24 | “Pending” and “Paid” on the timetable did not say whether they meant the student, the institute, or the teacher | MEDIUM / UX | One status badge reused for the lesson teacher-payment flag | Badge and summary labels say “Teacher” | `timetable/index.php`, `timetable/payments.php` | None | Not browser-tested | Fixed in code |
| 2026-09-23 | Teacher paid SMS was not documented and was easy to confuse with WhatsApp `notify_payment` | DOCUMENTATION | `SYSTEM.md` stopped at the communication hub | §12.7, §12.8, and §21.9 describe the calculator, visibility rule, and SMS log | `SYSTEM.md`, this file, `CHANGELOG.md` | `teacher_payment_sms_log` is created on first SMS use | Unit tests from 23 Sep still pass | Documented |
| 2026-09-24 | Students might see whiteboard ink only after pen-up | — | Inspected, not reproduced | No code change. Freehand already publishes `{ t: 'wb-ink' }` about every 28ms while the pen is down, and `receiveInk()` paints each batch immediately. The final `{ t: 'wb' }` on pointer-up is the saved stroke | `assets/js/classroom-board.js`, `assets/js/classroom.js` | None | Code inspection only. NOT TESTED — EXTERNAL DEPENDENCY (LiveKit) | No change |

## Checks that already matched the requested rules

- Fee maths lives in `ClassSessionFeeCalculator`. `timetable/add.php` mounts `ClassSessionFee` for a live preview. Create and update call `TeacherBankAccountService::assertCanScheduleOnline()` before an online lesson is stored.
- Online payout statuses are `pending`, `processing`, `paid`, and `failed`. SMS runs only after commit, only on the transition to paid, and a second automatic send is skipped when a `sent` or `resent` log exists.
- Resend is a separate admin POST (`ajax/resend_payment_sms.php`) with CSRF. Teachers and students are rejected server-side.
- Payment amount in the SMS is not taken from the browser.

## Second-stage verification — 24 September 2026

The first-round timetable and teacher-payment fixes are still present in the local files. They were not edited again. No new application code was changed in this pass.

Confirmed still in local code:

1. An unlinked teacher account exits `timetable/index.php` with HTTP 403.
2. Timetable list, filters, pending count, and CSV include `substitute_teacher_id`.
3. Card amount, Teacher Payments row, mark-paid audit, and SMS all use `TeacherPaymentSmsService::payableCents()`.
4. Timetable labels say “Teacher pending” and “Teacher paid”. Institute summary cards are labelled separately.
5. `TimetablePaymentService::markPaid()` commits, then calls the SMS service. A row already `sent` or `resent` is not sent again.
6. `TeacherBankAccountService::assertCanScheduleOnline()` still runs on create and update.

Local version versus production at the time of the second-stage notes: a later FTP check found the PHP files already matched. See Final verification below. Opening `https://edexcel.college/classroom/room.php` on 24 September 2026 redirected to `https://edexcel.college/login.php`. There is no staff or student session in this environment, and the local MySQL server is not running. Production must not be described as updated.

| Item | Result |
| --- | --- |
| First-round fixes still present | CODE REVIEW — still in the local files. Not clicked on the site |
| Unlinked teacher blocked | PASS — automated contract test |
| Substitute filter | PASS — automated contract test |
| Teacher payable amount | PASS — automated SMS and fee tests |
| SMS after commit, no automatic duplicate | PASS — automated test with a fake sender. Not sent to a phone |
| Bank details required for a new online class | CODE REVIEW — create and update still call `assertCanScheduleOnline`. Form not submitted |
| Whiteboard live sync | NOT TESTED — LiveKit login required. Code still streams `{ t: 'wb-ink' }` during the stroke and saves `{ t: 'wb' }` on release |
| Two-browser classroom | NOT TESTED — LIVEKIT EXTERNAL DEPENDENCY. Production classroom URL redirected to staff login |
| Screen share, camera, microphone, chat, PDF, fullscreen, reconnect | NOT TESTED — same login wall |
| Student and teacher mobile classroom | NOT TESTED — no authenticated session at 320–768px |
| OnePay callback against the gateway | NOT TESTED — no gateway credentials or test payment. Handler re-checks OnePay status before marking paid. Automated tests cover hash, amount, student, currency, duplicate paid, and refunded |
| SMS handset | NOT TESTED — SMS provider not called. Fake-sender tests cover valid number, missing number, invalid number, provider failure, second Paid click, and resend |
| Full authentication and IDOR sweep | NOT TESTED as a live login matrix. Automated staff Google tests cover unlinked staff, inactive staff, student blocked from staff login, and deleted-account Google id release |
| File upload execution | NOT TESTED with a live upload. Classroom PDF upload checks `%PDF-` and `SecureUploadService` allows only `pdf` |
| Query performance | NOT TESTED — no database timings. No speculative index changes |

Role controls, from code review only, not a connected room:

| Control | Teacher token | Student token | Should exist |
| --- | --- | --- | --- |
| Camera / microphone | Yes, unless the meeting blocks them | Only if the server publish list includes them | Yes for the teacher. Student only when allowed |
| Screen share | Yes | Only after `isScreenshareAllowed` | Teacher yes. Student only when the teacher grants it |
| Whiteboard draw | Yes | Only when `studentsCanDraw` is on | Teacher yes. Student only when enabled |
| PDF upload | Host API | Denied by `classroom_pdf_access` | Teacher yes. Student no |
| PDF download | When the setting allows | Same server check, not only a hidden button | Yes only when that role is allowed |
| End class | Host | Leave only | Teacher ends. Student leaves |
| Chat | Yes | Yes | Both |

Hiding a button in `classroom.js` is not the publish gate. `api/classroom/token.php` builds the LiveKit grant from `ClassroomAccessService` and the server block/allow flags. That was not exercised with two browsers.

### Final test matrix

| Feature | Code review | Automated test | Live test | Status |
| --- | --- | --- | --- | --- |
| Authentication | Google code exchange and state check read | Staff Google OAuth tests passed (12) | Login page loads. No account signed in | NOT TESTED live |
| Authorization | Timetable 403 and substitute filter read | Visibility contract passed | Not clicked as a teacher | CODE VERIFIED |
| Timetable | First-round fixes still present | Visibility contract passed | Not opened while logged in | AUTOMATED TESTED |
| Teacher schedule | Substitute filter already in `teacher_schedule.php` | Included in the visibility contract | Not opened while logged in | AUTOMATED TESTED |
| Teacher payments | Amount and labels still use teacher payable cents | SMS amount tests passed | Not marked paid in a browser | AUTOMATED TESTED |
| Online payments | Checkout stores the server amount | Fee calculator tests passed earlier | No student checkout | NOT TESTED live |
| Payment callback | Unsigned callback is re-checked with OnePay status API | OnePay and verification tests passed (13) | Gateway not called | AUTOMATED TESTED |
| Teacher payout | Paid rows rejected. SMS after commit | SMS fake-sender tests passed | No payout recorded | AUTOMATED TESTED |
| SMS | Gateway helper reused. No bank details in the text | 10 SMS tests passed | No handset delivery | AUTOMATED TESTED |
| Bank details | Online create/update still requires a complete account | Bank masking test passed in the fee suite | Form not submitted | CODE VERIFIED |
| LiveKit | Token grant is server-side | Classroom unit file not re-run this pass | Room URL redirected to login | NOT TESTED |
| Whiteboard | Live ink during the stroke, persist on release | Not measured | Two browsers not joined | NOT TESTED |
| PDF | `%PDF-` check and pdf-only storage | Not re-run | No upload | NOT TESTED |
| Chat | Data messages in `classroom.js` | Not run | Not joined | NOT TESTED |
| Screen share | Student share only if the server allows it | Not run | Not joined | NOT TESTED |
| Mobile student | Not measured | None | Not opened at 320–768px | NOT TESTED |
| Mobile teacher | Not measured | None | Not opened | NOT TESTED |
| Admin mobile | Not measured | None | Not opened | NOT TESTED |
| File upload | PDF magic bytes and extension allow-list | Not run | No file sent | CODE VERIFIED |
| Database | No new migration | Tests used SQLite for SMS only | Production MySQL not queried | NOT TESTED |

This system is not marked production ready. A later file check, recorded below, found the PHP files already matched production.

## Not changed

- Historical fee snapshots are not recalculated.
- Whiteboard, LiveKit controls, and mobile layout were not rewritten. A full control-duplication pass was not re-run in a connected classroom on this date.
- No production database migration was applied. The SMS log table is still created by the application on first use.
- No production FTP upload was done in this audit.

## Tests

Passed locally on 24 September 2026:

- `php -l` on the PHP files edited in this audit
- `vendor/bin/phpunit tests/Unit/TeacherPaymentSmsServiceTest.php`
- `vendor/bin/phpunit tests/Unit/TeacherVisibilityContractTest.php`
- `vendor/bin/phpunit tests/Unit/ClassSessionFeeCalculatorTest.php`

NOT TESTED — EXTERNAL DEPENDENCY:

- Logged-in admin marking a real lesson paid
- SMS delivery to a handset
- LiveKit join, screen share, and live whiteboard with two browsers
- Payment gateway callback
- Mobile layout at 320–768px inside an authenticated session

## Final verification — 24 September 2026

No application code was changed. The eight local fixes were still present and were not edited.

No database migration required. `TeacherPaymentSmsService` creates `teacher_payment_sms_log` with `CREATE TABLE IF NOT EXISTS` the first time that code runs. No SQL file was added.

### Production files

FTP login succeeded. A zip of the production copies taken immediately before this upload is at `/home/edexcel.college/private_backups/production_before_teacher_payment_2026-09-24.zip`. A local copy is in `deploy_backups/2026-09-24-teacher-payment/`.

Fourteen files already on the server were byte-identical to the local fixes before they were uploaded again. `CHANGELOG.md` was absent and was uploaded. After upload, every file below was downloaded again and its SHA-256 matched the local file.

- `timetable/index.php`
- `timetable/payments.php`
- `timetable/edit.php`
- `timetable/export_csv.php`
- `src/Services/TimetablePaymentService.php`
- `src/Services/TeacherPaymentSmsService.php`
- `src/Services/TeacherPayoutService.php`
- `ajax/mark_paid.php`
- `ajax/bulk_action.php`
- `ajax/update_entry.php`
- `ajax/resend_payment_sms.php`
- `admin/online_payments.php`
- `SYSTEM.md`
- `SYSTEM_AUDIT.md`
- `CHANGELOG.md`

`php -l` passed on the twelve PHP files before upload. Unauthenticated GET requests to the timetable, teacher schedule, teacher payments, edit, admin online payments, and the four payment AJAX URLs returned HTTP 302 to `login.php`. None returned HTTP 500. `/home/edexcel.college/logs` and `/home/edexcel.college/logs/app` returned FTP 550. No `error_log` name was listed in the web root.

The editor SFTP setting `uploadOnSave` is true. That is the likely reason the PHP files were already on the server. This check did not open a logged-in timetable.

### Final status

| Area | Code review | Automated test | Live test | Status |
| --- | --- | --- | --- | --- |
| Authentication | CODE REVIEW — NOT EXECUTED for a full role matrix | AUTOMATED TEST — PASSED (staff Google, 12 tests) | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| Authorization | CODE REVIEW — NOT EXECUTED for URL and POST role changes | AUTOMATED TEST — PASSED (unlinked teacher contract) | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| Timetable | CODE REVIEW — NOT EXECUTED as a click-through | AUTOMATED TEST — PASSED (visibility contract) | HTTP 302 to login. Logged-in list not opened | NOT TESTED — NO TEST ACCOUNT |
| Teacher schedule | CODE REVIEW — NOT EXECUTED as a click-through | AUTOMATED TEST — PASSED (visibility contract) | HTTP 302 to login | NOT TESTED — NO TEST ACCOUNT |
| Teacher payments | CODE REVIEW — NOT EXECUTED as a click-through | AUTOMATED TEST — PASSED (SMS amount tests) | HTTP 302 to login. Nothing marked paid | NOT TESTED — NO TEST ACCOUNT |
| Online payment | CODE REVIEW — NOT EXECUTED | AUTOMATED TEST — PASSED (fee calculator, earlier run) | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| OnePay callback | CODE REVIEW — NOT EXECUTED against the gateway | AUTOMATED TEST — PASSED (13 callback and verification tests) | NOT TESTED — EXTERNAL DEPENDENCY | NOT TESTED — EXTERNAL DEPENDENCY |
| Teacher payout | CODE REVIEW — NOT EXECUTED as a click-through | AUTOMATED TEST — PASSED (fake-sender SMS tests) | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| SMS | CODE REVIEW — NOT EXECUTED on a handset | AUTOMATED TEST — PASSED (10 tests, fake sender) | NOT TESTED — EXTERNAL DEPENDENCY | NOT TESTED — EXTERNAL DEPENDENCY |
| Bank details | CODE REVIEW — NOT EXECUTED | Not run in this pass | NOT TESTED — NO TEST ACCOUNT | CODE REVIEW — NOT EXECUTED |
| LiveKit | CODE REVIEW — NOT EXECUTED in a room | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| Whiteboard | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| PDF | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | CODE REVIEW — NOT EXECUTED |
| Chat | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| Screen share | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| Mobile student | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| Mobile teacher | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| IDOR | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | NOT TESTED — NO TEST ACCOUNT |
| File upload | CODE REVIEW — NOT EXECUTED | Not run | NOT TESTED — NO TEST ACCOUNT | CODE REVIEW — NOT EXECUTED |
| Database | No migration file | SMS tests used SQLite only | Production database not queried | No database migration required |

LOCAL CODE STATUS: the eight fixes are present. No new defect was found, so no code was changed.

PRODUCTION DEPLOYMENT STATUS: the fifteen files above match the local bytes. Fourteen of them already matched before this upload. `CHANGELOG.md` was added. Logged-in pages were not opened.

AUTOMATED TEST STATUS: teacher SMS, teacher visibility, OnePay callback, payment verification, and staff Google tests passed in this audit. They were not re-run during the upload.

LIVE TEST STATUS: anonymous pages redirect to login and did not return HTTP 500. No admin, teacher, or student account was used.

REMAINING UNVERIFIED AREAS: logged-in timetable and payments, marking a payment paid, a real OnePay payment, a real SMS, LiveKit with two browsers, whiteboard while the pen is down, mobile classroom, IDOR requests, and file upload. The system is not production ready.

## Final live verification — 24 September 2026

No application code was changed. No payment was marked paid. No SMS was sent. No class was saved.

An existing teacher session was already signed in. Admin login, student login, an unlinked teacher, and a second teacher were not available.

| Check | Result |
| --- | --- |
| Teacher timetable | PASS — LIVE TEST. Labels show Teacher pay, Teacher pending, and Teacher paid. Cards show Teacher Pending. This week’s lessons have 0 students, so the card and page total are Rs. 0.00 |
| Teacher schedule | PASS — LIVE TEST. Weekly schedule opened for the signed-in teacher |
| Teacher dashboard, profile, online earnings | PASS — LIVE TEST. Pages opened with no PHP fatal text |
| My Payments | PASS — LIVE TEST. Title is My Payments. Row text says Teacher payment. Summary cards say Institute total, Institute paid, and Institute pending. No Paid or Resend button was visible. Shown amounts were Rs. 0.00, matching the timetable cards |
| Teacher cannot mark paid | PASS — LIVE TEST. A real security token was posted to `ajax/mark_paid.php`. The response was “Only an administrator can mark a lesson as paid.” The lesson stayed unpaid |
| Teacher cannot resend SMS | PASS — LIVE TEST. `ajax/resend_payment_sms.php` returned 403 |
| Timetable teacher id in the URL | PASS — LIVE TEST. `teacher=99999` still listed only the signed-in teacher |
| Bank details teacher id in the URL | PASS — LIVE TEST. `teacher_id=1` returned the same page as the teacher’s own bank page |
| Admin online payments | PASS — LIVE TEST. The teacher request was redirected and the page body was not returned |
| In-college fee preview, Rs 2,000, 1 student | PASS — LIVE TEST. Class fee Rs 2,000.00, institute fee Rs 500.00, teacher net Rs 1,500.00. The page says there is no transaction fee. The form was not saved |
| Online fee preview, Rs 2,000 | PASS — LIVE TEST. Institute online fee Rs 500.00, handling fee 6% Rs 120.00, teacher net Rs 1,380.00. The form was not saved |
| Add-class bank notice | PASS — LIVE TEST for the message. The page says bank details are required for an online class, and that this teacher’s bank details are already complete |
| Teacher timetable, payments, and dashboard at 320, 375, 390, 414, and 768px | PASS — LIVE TEST in an emulated browser width. No horizontal overflow. Not a physical phone |
| CSV export | PASS — LIVE TEST that the signed-in teacher received a CSV. Substitute rows were not created for this check |
| Admin login, mark paid, SMS status, resend | NOT TESTED — NO ACCOUNT |
| Student login and student pages | NOT TESTED — NO ACCOUNT |
| Unlinked teacher 403 | NOT TESTED — NO ACCOUNT |
| Substitute teacher on list, filters, pending count, and CSV | NOT TESTED — NO ACCOUNT |
| Teacher with no bank details blocked from online class | NOT TESTED — NO ACCOUNT. This teacher already has bank details |
| In-college class without bank details | NOT TESTED — NO ACCOUNT |
| Mark paid, audit row, SMS after commit, refresh without a second SMS | NOT TESTED — NO ACCOUNT |
| Handset SMS and resend | NOT TESTED — EXTERNAL DEPENDENCY |
| OnePay cancelled, failed, paid, duplicate, already paid, refund | NOT TESTED — EXTERNAL DEPENDENCY |
| LiveKit two-browser room | NOT TESTED — NO ACCOUNT |
| Whiteboard while the pen is down | NOT TESTED — NO ACCOUNT |
| Student classroom at 320–768px | NOT TESTED — NO ACCOUNT |
| File upload | NOT TESTED — NO ACCOUNT |
| Full IDOR set for payment, payout, class, session, and classroom ids | NOT TESTED — NO ACCOUNT |
| Application logs | LOGS NOT READ — SERVER PERMISSION DENIED |

Automated tests from the earlier run stay PASS — AUTOMATED TEST. They are not live tests.

DEPLOYMENT: PASS

LIVE AUTHENTICATION: NOT TESTED

LIVE AUTHORIZATION: PASS

LIVE TIMETABLE: PASS

LIVE TEACHER PAYMENTS: PASS

LIVE SMS: NOT TESTED

LIVE ONLINE PAYMENT: NOT TESTED

LIVE BANK DETAILS: NOT TESTED

LIVE LIVEKIT: NOT TESTED

LIVE WHITEBOARD: NOT TESTED

LIVE MOBILE: NOT TESTED

LIVE IDOR: PASS

LIVE FILE UPLOAD: NOT TESTED

DATABASE: NOT TESTED

No database migration required. The system is not production ready.
