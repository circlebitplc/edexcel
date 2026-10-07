# Edexcel College V2 — Implementation Report

**Date:** 2026-09-06  
**App version setting:** `2.0.0`  
**Scope:** Production hardening + portals + ops (no GitHub Actions / automated deploy)

---

## Features completed

| Phase | Feature | Status |
| --- | --- | --- |
| 1 | Automated backup + retention + encrypt/offsite + history/verify | Done |
| 2 | Disaster recovery docs + admin backup/DR UI + RPO/RTO | Done |
| 2 | System Health Center + throttled alerts | Done |
| 3 | Migrations 021–023 + migrate.php multi-statement + schema completion | Done |
| 3 | Service-layer additions (backup/health/security/progress/etc.) | Done |
| 4 | Staging template + deployment/rollback checklists | Done |
| 5 | Expanded PHPUnit suite | Done — **95 tests, 234 assertions, 0 failures** |
| 6 | Admin TOTP 2FA + recovery codes + trusted devices + Security dashboard | Done |
| 7 | Parent Portal 2.0 dashboard | Done |
| 8–9 | Student academic progress + exam prep / revision plans | Done |
| 10 | Notification center + PWA/SW improvements | Done |
| 11 | a11y/mobile CSS pass | Done |
| 12 | CacheService + CACHE.md | Done |
| 13 | AppLogger + application_logs + error_handler hook | Done |
| 14 | docs/API.md + openapi.yaml | Done |
| 15 | SecureUploadService + DATA_RETENTION.md | Done |
| 16–17 | Command center + account recovery via existing OTP (documented) | Done |
| 18 | This audit/report | Done |

---

## Database migrations created

- `021_v2_backup_health_security.sql`
- `022_v2_schema_completion.sql` (`class_teacher_whatsapp`, `student_registration_otps`, OTP/login helpers, etc.)
- `023_v2_academic_notifications_privacy.sql`

### New tables (high level)
`system_backups`, `system_health_alerts`, `security_events`, `admin_totp_secrets`, `admin_trusted_devices`, `admin_reauth_tokens`, `application_logs`, `student_topic_progress`, `exam_revision_plans`, `notification_center`, `push_subscriptions`, `data_deletion_requests`, plus migration coverage for healer-only tables.

### New settings keys
`backup_*`, `health_alert_cooldown_minutes`, `disk_*_percent`, `admin_totp_required`, `rpo_hours`, `rto_hours`, `app_version`

---

## Key files created

**Services:** `BackupService`, `HealthAlertService`, `AppLogger`, `SecurityEventService`, `AdminTotpService`, `NotificationCenterService`, `AcademicProgressService`, `ExamPrepService`, `CacheService`, `SecureUploadService` (+ expanded `SystemHealthService`)

**Admin:** `system_health.php`, `security.php`, `command_center.php`, rewritten `backup.php`

**Portals:** `parent/dashboard.php`, `student/progress.php`, `student/exam_prep.php`, `student/notifications.php`, `notifications/index.php`, `ajax/notifications_center.php`

**Ops:** `tools/automated_backup.php`, `cron/automated_backup.php`, `storage/backups/`, `storage/logs/`

**Docs:** `DISASTER_RECOVERY.md`, `STAGING.md`, `DEPLOYMENT_CHECKLIST.md`, `CACHE.md`, `DATA_RETENTION.md`, `API.md`, `openapi.yaml`, `.env.staging.example`

**Assets:** `a11y-mobile-v2.css`, improved `service-worker.js` / `manifest.json`, `offline.html`

---

## New cron jobs

```
15 2 * * * php /home/edexcel.college/public_html/tools/automated_backup.php
```

Existing jobs unchanged; health alerts expanded via `SystemHealthService::alertIfStale()` → `HealthAlertService`.

---

## Configuration required (manual)

1. Run `php bin/migrate.php` on staging then production.
2. Add crontab line for `automated_backup.php`.
3. Optional `.env`: `BACKUP_ENCRYPTION_KEY`, `APP_KEY` (TOTP encryption seed).
4. Optional: `backup_offsite_path` + enable off-server in Admin → Backup.
5. Admins: enable TOTP under Admin → Security; optionally set `admin_totp_required`.
6. Do **not** overwrite production `.env`. Use `.env.staging.example` for staging only.

---

## Manual deployment steps

Follow `docs/DEPLOYMENT_CHECKLIST.md`. Summary:

1. Backup DB + files  
2. Upload changed files (never `.env`)  
3. `php bin/migrate.php`  
4. Verify crontab including automated backup  
5. Open System Health + Command Center  
6. Test login (staff/student/parent), payments, WhatsApp, LiveKit, recordings  

Rollback: restore previous files archive + last verified DB dump per `docs/DISASTER_RECOVERY.md`.

---

## Security improvements

- Admin authenticator 2FA + recovery codes + trusted devices  
- Security event dashboard with filters  
- Login CSRF/failed login/TOTP failures recorded  
- Backup excludes `.env` / TOTP secrets / gateway blobs  
- Backup dir `.htaccess` deny-all  
- Structured logging with secret redaction  
- SecureUploadService for MIME/extension hardening  

---

## Tests

```
vendor/bin/phpunit --testdox
→ OK (95 tests, 234 assertions)
```

Coverage includes classroom access (paid/waived/force_unpaid), recordings, OnePay duplicate/refunded callbacks, devices max=4, TOTP HOTP, health alert checks, V2 smoke, CSRF helpers.

---

## Remaining limitations

- `ensure_*` healers retained for backward compatibility (migrations now cover the gaps).  
- Web Push VAPID send path is stubbed (subscriptions table + SW click routing ready; needs VAPID keys to send).  
- Large legacy pages (`dashboard.php`, etc.) not fully rewritten — new Command Center sits alongside.  
- Topic progress depends on real `student_topic_progress` / marks data — never invented.  
- Off-server backup is filesystem copy; S3/FTP offsite can be added later without redesign.  
- No GitHub Actions (explicitly excluded).

---

## Post-deploy audit checklist

Use Phase 18 list in the upgrade brief: authN/Z, payments, timetable, classroom, recordings, WhatsApp, students, parents, security (incl. TOTP), ops, mobile. Confirm green indicators on `/admin/system_health.php`.
