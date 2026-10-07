# Edexcel College Timetable / Campus Portal

Timetable, fees, portals, WhatsApp, recordings, and live classroom for [edexcel.college](https://edexcel.college).

## Features
- Staff auth: Prominent "Continue with Google" button on `/login.php` with verified role loading, audit logging, and temporary collapsible legacy password fallback
- Teacher & Staff Google OAuth Migration: Admin console at `/admin/teacher_oauth_migration.php` with one-time invite generation, in-person Google OAuth linking for teachers and admins, audit trail, and legacy password login toggle
- Teacher self-service Google OAuth linking on `/teachers/profile.php`
- Student & parent portal (Google OAuth + phone OTP)
- Parent/guardian information management & multi-child linking (`parent_accounts` & `parent_students`)
- Automatic Google profile picture synchronization for students and parents
- Timetable CRUD with conflict detection
- Weekly & Google Calendar-style views
- Student count & revenue tracking (default Rs 500/student)
- Payment status (pending/paid) – admin-only control; OnePay / bank slips
- Recurring weekly classes with auto-generation
- Teacher schedule with Monday–Sunday view
- Bunny recordings + LiveKit classroom. Teacher controls are on the bottom bar. Uploaded PDFs open as a scrollable document with a separate annotation layer.
- Site Visitors Analytics: privacy-first telemetry, live pulse monitor, and CSV/PDF export
- Admin User Deletion Console: safe credential purge with compliance record retention
- Security: CSRF, input validation, soft delete, audit log, IP anonymization
- Code quality: Environment variables, Composer, service layer, API endpoints, AppDialog & LiveFilter UI modules

## Requirements
- PHP >= 8.1
- MySQL >= 8.0
- Composer

## Installation
1. Clone the repository (workspace root contains `public_html/`).
2. Point the web server document root at `public_html/`.
3. Run `composer install` inside `public_html/`.
4. Create env file:
   - Local: `public_html/.env`
   - Production: `/home/edexcel.college/.env` (preferred; outside web root)
5. Set at least `DB_*`, `APP_URL`, `APP_ENV`.
6. Run `php bin/migrate.php`.
7. See `DEPLOYMENT_NOTES.md` for FTP, cron, and production checklist.

## Portal & Staff Google OAuth
```env
GOOGLE_OAUTH_ENABLED=1
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_CALLBACK_URL=https://edexcel.college/auth/google/callback.php
```
- **Staff Login**: `/login.php` provides "Continue with Google" (`intent=staff`) and redirects directly to `/dashboard.php`.
- **Teacher & Staff Migration**: `/admin/teacher_oauth_migration.php` allows admins to link teachers and administrator accounts directly to Google identities.
- **Student & Parent Login**: `/portal/login.php` (`intent=student`, `intent=parent`). Parent link requests at `/admin/parent_requests.php`.

**Recent Changes:**
- **Site Visitors Analytics Dashboard**: Built a privacy-first web telemetry suite inside Admin Settings (`/admin/settings.php?tab=site_visitors`). Captures non-blocking pageview and heartbeat beacons (`assets/js/visitor-tracker.js`, `ajax/track_visitor.php`) with SHA-256 IP hashing, sensitive query parameter stripping, traffic source and device categorization, live 15-second active visitor polling, interactive Chart.js timelines, and executive CSV & PDF reports (`admin/export_visitor_analytics.php`). Backed by migration `041_site_visitor_analytics.sql`.
- **First-Time Student Parent Details Gate Sequencing**: Resolved modal focus trapping and input blocking on `#parentPhoneGate` during first-time student sign-in. Sequenced personal mobile number prompts (`#missingMobileModal`) to appear only *after* parent details are registered, intercepted capture-phase focus events to prevent background focus trapping, autofocuses inputs, and cleared cached parent phone lookups.
- **LiveKit teacher classroom (2026-09-25)**: Teacher desktop controls are the bottom bar in `classroom/room.php` (Mic, Camera, Board, PDF, Share, People, Chat, then view and tool buttons, End class, Leave). The PDF in `assets/js/classroom-board-pdf.js` is a vertical document viewer. Pages render lazily with PDF.js. Annotations stay on a transparent overlay and are not redrawn into the PDF. Scroll position syncs with a throttled `pdf_scroll` data message. Educator tools remain in `assets/js/classroom-board-edu.js`. PDF storage is still migration `040_classroom_pdf_whiteboard.sql`.
- **Admin User Deletion Console**: Added `/admin/settings.php?tab=delete_user` enabling administrators to permanently purge credentials and active sessions for departed accounts while preserving historical academic records (attendance, enrollments, fee ledgers, exam marks, audit logs) for compliance. Protected with dual-confirmation safeguards and administrator self-deletion guards.
- **Accessible Modal Dialogs (`AppDialog`) & Dynamic Filtering (`LiveFilter`)**: Replaced blocking native browser alerts with promise-based Bootstrap modal dialogs (`assets/js/app-dialog.js`) and introduced declarative client-side search filtering (`assets/js/live-filter.js`) for rosters and management tables.
- **Staff Google OAuth Login**: Replaced the primary username/password form on `/login.php` with a prominent "Continue with Google" button. Verified server-side ID token, checks verified Google email, matches existing staff accounts (`role != 'student'`), populates staff session (`complete_staff_portal_login()`), and redirects to `/dashboard.php`. Includes loading spinner state and helpful admin contact guidance for unlinked accounts.
- **Teacher & Admin OAuth Migration Console**: Built `/admin/teacher_oauth_migration.php` with one-time invite token generation (`teacher_oauth_invites`), in-person Google OAuth linking for teachers and admins, audit logging, and an administrator switch (`staff_legacy_login_disabled`) to disable legacy password login once migration is complete.
- **Teacher Self-Linking**: Teachers can link their Google account from `/teachers/profile.php`.
- **Profile Picture Sync**: Google OAuth automatically synchronizes the latest profile picture (`users.profile_image` / `parent_accounts.profile_image`) upon every login.
- **Parent/Guardian Linking**: Parents can link multiple student accounts after OAuth login. The relationships are managed via `parent_students` and `parent_accounts`.
- **Student Mobile Number Onboarding**: Google-authenticated students without a registered Sri Lankan mobile number are prompted with an interactive modal to add their mobile number. Supports immediate direct saving (`save_direct`) without requiring OTP, backed by post-commit database verification and parent-gate exemption (`student_parent_phone_request_exempt()`).

## API Endpoints
- `GET /api/timetable.php?teacher_id=1&date_from=2026-08-01&date_to=2026-08-07`
- `POST /ajax/track_visitor.php` (visitor pageview & heartbeat telemetry beacon)
- `GET /ajax/track_visitor.php?action=active_count` (real-time active visitor count)
- `GET /ajax/lookup_student.php?q=...` (campus student lookup)
- `GET /admin/export_visitor_analytics.php?format=csv&period=30d` (visitor analytics report export)

## Testing
Run `vendor/bin/phpunit` from `public_html/` to execute tests.

## Docs
- `SYSTEM.md` — full system documentation
- `DEPLOYMENT_NOTES.md` — production upload / cron / FTP
- `docs/DEPLOYMENT_CHECKLIST.md` — smoke checklist

## License
Proprietary – for Edexcel College use only.

## Evolution API WhatsApp Bot

The project includes a WhatsApp bot using Evolution API (optional; Meta Cloud API is the primary production path).

### Files
- `api/whatsapp/webhook.php` — secure incoming-message webhook
- `src/Services/EvolutionApiService.php` — Evolution API client
- `src/Services/WhatsAppBotService.php` — timetable/admissions bot logic
- `admin/whatsapp_bot.php` — admin configuration and student number linking
- `database/whatsapp_bot.sql` — bot contact/message tables

### Install
1. Run `database/whatsapp_bot.sql` on the existing database (or rely on migrations / healers where applicable).
2. Run `composer dump-autoload` after deployment.
3. Configure `EVOLUTION_API_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE` and `EVOLUTION_WEBHOOK_SECRET` in `.env`.
4. Make the webhook publicly reachable over HTTPS at `/api/whatsapp/webhook.php`.
5. Open the admin WhatsApp Bot page and configure the Evolution webhook.
6. Link student WhatsApp numbers to student accounts.

The bot ignores WhatsApp group messages and messages sent by the institute account itself. It supports menu commands for today's classes, tomorrow's classes, enrolled classes, teachers, weekly timetable and admissions.
