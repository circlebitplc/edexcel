# Edexcel College – Production Deployment Notes

## Important
This package is prepared for upload into the website document root.
The ZIP contains the project files directly; there is no extra `final_work/` wrapper folder.

### Do NOT overwrite the live `.env`
Production secrets live **outside** the web root at `/home/edexcel.college/.env` (not in `public_html/.env`).
`config/load_env.php` prefers that path, then falls back to `public_html/.env` for local copies.
The production package intentionally excludes `.env` so credentials are not overwritten.
Keep the current server `.env` in place, or create it from `.env.example`.

For `edexcel.college`, the production values should include:

```env
APP_ENV=production
APP_URL=https://edexcel.college
```

### Google OAuth (Student, Parent, Staff, & Teacher Portals)

Add these to `/home/edexcel.college/.env` (never commit secrets):

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_CALLBACK_URL=https://edexcel.college/auth/google/callback.php
```

In Google Cloud Console:
1. Create an OAuth 2.0 Client ID (Web application).
2. Authorized redirect URI must match `GOOGLE_CALLBACK_URL` exactly (`https://edexcel.college/auth/google/callback.php`).
3. Enable the Google+ / OpenID userinfo scopes (openid, email, profile).

Portal and Login entries:
- **Staff Login**: `https://edexcel.college/login.php` (Continue with Google, `intent=staff`) with role loading and collapsible legacy password fallback (`staff_legacy_login_disabled`).
- **Staff & Teacher Migration Console**: `/admin/teacher_oauth_migration.php` (one-click "Copy Link & SMS", instant modal with ready-to-send SMS messages containing *"You will be asked to sign in with your Google account"*, WhatsApp/SMS app direct dispatch, single-use invite tokens, in-person Google OAuth linking for teachers and admin, audit logs, and legacy password login toggle).
- **Teacher Self-Linking**: `/teachers/profile.php`
- **Student & Parent Portal**: `https://edexcel.college/portal/login.php`
Google sign-in **never** auto-creates admin or teacher accounts. Unlinked Google accounts are denied access with clear instructions to contact the administrator. When an administrator saves a teacher's email in `teachers/edit.php` or `teachers/create.php`, the teacher's login account is automatically authorized for Google OAuth and links permanently on their first sign-in.

### Admin "Delete User" Tab (`admin/settings.php?tab=delete_user`)
- Added under "Browse by Task" in admin settings: task item **Delete User** with icon `bi-trash3` and description *"Find and permanently remove a user account"*.
- Single search input labeled **Email address or username** searching students, teachers, administrators, and parent accounts.
- Displays matching account details: full name, email, username, role, account status, and registration date. Supports multiple match selection.
- Regulatory compliance & data retention: Academic and financial history (attendance, course enrollments, fee ledger, exams, homework submissions, parent links, and audit history) are preserved.
- Credentials and sessions (password hash, Google OAuth link, active sessions, trusted device tokens, and pending OTPs) are permanently purged.
- Strict security blocks: Self-deletion blocked, primary administrator (User #1) deletion blocked, and final active administrator deletion blocked.
- Dual confirmation safety gate: Checkbox confirmation + re-typing exact user username or email to unlock the "Permanently Delete User" button.


### Site Visitors Analytics Tab (`admin/settings.php?tab=site_visitors`)
- Added under Admin Settings: **Site Visitors** tab with icon `bi-graph-up-arrow`.
- Backed by migration `database/migrations/041_site_visitor_analytics.sql` creating `site_visitor_sessions` and `site_visitor_pageviews`. Runtime self-healing via `SiteVisitorAnalyticsService::ensureSchema($pdo)`.
- Front-end telemetry: `assets/js/visitor-tracker.js` (< 2KB non-blocking beacon via `navigator.sendBeacon` and `fetch({keepalive: true})`). Excludes `/admin/` paths and honors `navigator.doNotTrack`. Ingestion endpoint: `ajax/track_visitor.php`.
- Features real-time active visitor pulse badge (auto-refreshed every 15s via `?action=active_count`), date preset filtering, KPI comparison deltas vs prior periods, Chart.js timeline graphs, top visited & entry/exit pages, traffic source & device distribution doughnuts, and paginated session explorer.
- Secure exports: `admin/export_visitor_analytics.php` provides CSV export (UTF-8 BOM, formula escaping) and printable Dompdf-compatible PDF reports.

### LiveKit teacher classroom (2026-09-25)
- Backed by migration `040_classroom_pdf_whiteboard.sql` creating `classroom_pdf_documents` and `classroom_pdf_download_log`. No new migration.
- Teacher desktop (`classroom/room.php`, `assets/css/classroom.css`): the brand header and Live status pill are hidden. Controls are the bottom `#ckHostDock` bar. Below 1240px and above the 860px phone layout, Present through Exit sit in More. The phone dock is unchanged.
- `assets/js/classroom-board-pdf.js`: the PDF loads once and is shown as a vertical document (`#ckPdfScroller`). Visible pages are rendered with PDF.js. The whiteboard canvas is the annotation overlay. Drawing, tool changes, and the board poll do not clear those page canvases. Host scroll is published as `pdf_scroll` about every 280ms when the position actually changes. Students apply that position. Zoom still uses the existing PDF zoom controls.
- `assets/js/classroom-board.js`: eraser width is `clamp(round(penWidth * 1.5), 4, 20)`. Pen width is unchanged. Replay does not paint a white fill over an open PDF.
- `assets/js/classroom-board-edu.js`: the left rail lists drawing tools only.
- Local restore copy: `backup/livekit-restore-20260925-1225/`. Copy those files back over `public_html` to return to this classroom build. Do not copy the backup folder into the web root.

### Student First-Time Login & Parent Details Gate Sequencing
- Google-authenticated students without parent details are presented with the mandatory Parent Details popup (`#parentPhoneGate`).
- Fixed focus trapping and input blocking: `student/dashboard.php` sequences prompts so that personal mobile onboarding (`#missingMobileModal`) and review prompts are deferred until `student_has_parent_phone()` returns `true`.
- `student/device_helpers.php` captures `focusin` events in the capture phase with `e.stopImmediatePropagation()` to neutralize document-level Bootstrap modal focus traps, autofocuses inputs, and invalidates cached lookups.
- `assets/js/parent-phone-gate.js` and `assets/css/parent-phone-gate.css` enforce name validation, inline error messaging, and pointer-event clickability.

### UI Utilities: AppDialog & LiveFilter
- `assets/js/app-dialog.js`: Accessible, async modal dialogs replacing native browser `alert()` and `confirm()` (`window.AppDialog.confirm()`, `window.AppDialog.alert()`).
- `assets/js/live-filter.js`: Real-time fast DOM search filtering for cards, tables, and roster views.

### Student Mobile Onboarding & Direct Save
- Google-authenticated students without an LK mobile number see an interactive modal (`#missingMobileModal`) on their dashboard.
- The modal saves numbers directly without requiring SMS OTP (`save_direct`).
- `student/device_helpers.php` exempts student phone actions (`phone_request`, `phone_resend`, `phone_verify`, `phone_cancel`, `dismiss_phone_modal`) so the parent-phone enforcement gate does not block student self-onboarding.
- `config/config.php` guards `BASE_URL` with `if (!defined('BASE_URL'))` for PHP 8.4+ compatibility.

Legacy hostname `kandy.edexcel.college` should **301 redirect** to `https://edexcel.college%{REQUEST_URI}` (not stay on 403). See `deploy/kandy-to-apex-redirect.md`. LiveKit remains on `live.kandy.edexcel.college` until that subdomain is renamed.
## Required server environment

- PHP 8.1 or newer (8.2/8.3 recommended)
- PDO MySQL (`pdo_mysql`) – required for the application database
- PHP sessions
- Fileinfo – recommended for teacher image uploads
- cURL – required for WhatsApp API notifications
- mbstring – recommended for WhatsApp message logging
- OpenLiteSpeed/CyberPanel or Apache/Nginx + PHP-FPM

## FTP (apex production)

Use workspace `.vscode/sftp.json` (profile `edexcel.college`).

| Field | Value |
| --- | --- |
| Host | `169.58.123.255` |
| Protocol | FTP, port `21`, passive, no TLS |
| Username | `admin_admin_root2` |
| Remote path | `/home/edexcel.college/public_html` |
| Local context | `./public_html` |
| Server home | `/home/edexcel.college` |

Local `public_html/` maps 1:1 to the remote docroot. Do not overwrite live `.env`. Prefer `tools/` if remote `cron/` returns 553.

## Upload

1. Back up the existing website and database.
2. Upload via the SFTP/FTP extension (or ZIP) into the website document root.
3. Extract/sync so `index.php`, `config/`, `teachers/`, `timetable/`, etc. are directly inside `public_html`.
4. Preserve the existing `/home/edexcel.college/.env` (do not upload a docroot `.env`).
5. Ensure the website user owns the files and directories are normally 755 / files 644.
6. Make `assets/images/teachers/` writable by PHP if teacher photo uploads are required.
7. Restart/reload OpenLiteSpeed/PHP if required.

## Database

Do not import the bundled SQL into an existing production database unless you intentionally want to replace/restore the database. The application is designed to use the existing database.

## PDF export

If Composer/Dompdf is installed, timetable PDF export uses Dompdf. If it is not installed, the endpoint now falls back to a print-ready page that can be saved as PDF from the browser instead of producing a fatal error.

## Cron (optional)

If Bunny webhooks cannot reach the site, add:

```
*/10 * * * * php /home/edexcel.college/public_html/cron/bunny_sync.php
```

If the server `cron/` folder does not allow new files, run:

```
*/10 * * * * php /home/edexcel.college/public_html/tools/bunny_sync.php
```

Timezone on the job should be Asia/Colombo. This updates metadata and may mark recordings as `scheduled_for_deletion` when retention is enabled. Actual Bunny file delete runs from `tools/ops_jobs.php` one day later.

Waitlist expiry, 09:00 fee-due parent pings, WhatsApp token alerts, and Bunny purge:

```
*/5 * * * * php /home/edexcel.college/public_html/tools/ops_jobs.php
```

Do not rely on `cron/ops_jobs.php` over FTP on this server (553). After deploy run `php bin/migrate.php` so `015_ops_improvements.sql` is applied (or wait for the first page load to heal schema when `data/schema_ok` is stale).

15-minute live/class reminders (WhatsApp + teacher portal). Safe to run every 5 minutes:

```
*/5 * * * * php /home/edexcel.college/public_html/cron/classroom_reminders.php
```

If `cron/` cannot be used:

```
*/5 * * * * php /home/edexcel.college/public_html/tools/classroom_reminders.php
*/5 * * * * php /home/edexcel.college/public_html/tools/ops_jobs.php
*/10 * * * * php /home/edexcel.college/public_html/tools/bunny_sync.php
```

Optional: point the WhatsApp outbox drain at `tools/whatsapp_outbox.php` so last-run shows on System health.

On this server FTP cannot write `public_html/cron/` (553). Use the `tools/` copies for the 15-minute job. Until that crontab line exists, reminders also start from normal portal page loads (at most every 4 minutes). If you want Online join links in the 06:00/18:00 group messages, point those crontabs at:

```
php /home/edexcel.college/public_html/tools/whatsapp_class_reminders.php
php /home/edexcel.college/public_html/tools/parent_digest.php
```

## Bunny / OnePay

Admin → Settings → Bunny.net (account API key + enable) and OnePay. Each teacher needs a unique Stream library on **Teachers → Edit**. Prefer putting secrets in `.env` (`BUNNY_ACCOUNT_API_KEY`, `ONEPAY_HASH_SALT`, `ONEPAY_APP_TOKEN`). Do not overwrite the live `.env` during deploy.

Webhook URLs:

- `https://edexcel.college/api/bunny/webhook.php`
- `https://edexcel.college/api/onepay/callback.php`
- `https://edexcel.college/api/livekit/webhook.php` (live class recordings)

LiveKit Cloud: paste the LiveKit webhook in the Cloud project (Settings → Webhooks).

Self-hosted VPS: copy `public_html/deploy/livekit/` to the VPS (see that folder’s README). Put the webhook URL in `livekit.yaml`, not the Cloud dashboard. Point Admin → Live classroom at `wss://live.kandy.edexcel.college` (or your hostname) with the **same** API key/secret as the VPS. Fill MinIO/S3 so recordings can reach Bunny.

After deploy run `php bin/migrate.php` so `008_class_recordings_onepay.sql` and `009_teacher_bunny_libraries.sql` are applied (teacher Bunny columns are also added on first page load by `ensure_recordings_schema`).

## Security

- `.env`, `.sql`, and `.log` files are blocked by the root `.htaccess` rules.
- `/deploy` is forbidden over HTTP (LiveKit Docker files are copied to the VPS, not served as a site).
- CSRF protection is enabled on the main and student login forms and management forms.
- Teacher/class/subject deletion uses POST + CSRF + soft-delete where supported.
- Rotate any credentials/tokens that were previously exposed in development/source archives.

## Main checks performed

- All PHP files pass `php -l` syntax validation.
- Root homepage loads without a PHP syntax/runtime fatal when the database is temporarily unavailable.
- Nested PHP includes use `__DIR__` paths to avoid server working-directory issues.
- The database schema supplied with the project does not contain `teachers.photo` or `student_classes.whatsapp_link`; the application no longer queries those nonexistent columns.
- Legacy `home.php` redirects to the canonical `/` homepage.
