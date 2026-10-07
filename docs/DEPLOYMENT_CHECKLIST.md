# Deployment checklist

## Pre-deploy
- [ ] Migrations reviewed (`021`–`023`, `038` Google/parent links, `040` Classroom PDF Whiteboard, `041` Site Visitor Analytics) and applied on target DB
- [ ] Production secrets present at `/home/edexcel.college/.env` (never under docroot; never commit). Staging uses `.env.staging.example` as a template only
- [ ] `storage/`, `cache/`, and backup directories writable by PHP
- [ ] Cron entries point at `tools/*.php` (FTP cannot write `cron/` on this host — 553)
- [ ] SFTP profile is `.vscode/sftp.json` → `/home/edexcel.college/public_html` (profile `edexcel.college`)

## Deploy
- [ ] Upload only changed application files (exclude `vendor` unless composer changed, exclude `.vscode`, exclude `.env`)
- [ ] Confirm `composer install --no-dev` if dependencies changed
- [ ] Clear stale file cache if catalog data changed (`CacheService` / `cache/` folder)
- [ ] Prefer file backups under `/home/edexcel.college/private_backups/` (not in `public_html/`)

## Post-deploy smoke
- [ ] `/admin/command_center.php` loads with real counts
- [ ] `/admin/system_health.php` indicators reviewed
- [ ] `/admin/backup.php` config visible; optional manual backup
- [ ] `/admin/security.php` events list + TOTP UI
- [ ] `/admin/settings.php?tab=oauth` if Google portal login is enabled
- [ ] `/admin/settings.php?tab=site_visitors` renders live pulse badge, Chart.js timeline, and KPI analytics
- [ ] `/admin/settings.php?tab=delete_user` renders user search and dual-confirmation safety gate
- [ ] Staff Login: `/login.php` renders prominent "Continue with Google" (`intent=staff`) and collapsible legacy password fallback
- [ ] Teacher & Staff Migration: `/admin/teacher_oauth_migration.php` displays teacher migration table, admin linking card, and legacy login toggle switch
- [ ] Teacher Profile: `/teachers/profile.php` displays Google OAuth linking status
- [ ] Portal: `/portal/login.php` (Google + phone OTP)
- [ ] Live Classroom: teacher desktop shows the bottom control bar and no Live status pill. An uploaded PDF scrolls as a document. Drawing does not reload the PDF. Student phones still use the mobile dock.
- [ ] Student First-Time Login: Mandatory `#parentPhoneGate` modal accepts focus and input without focus trapping
- [ ] Student: `/student/progress.php`, `/student/exam_prep.php`, `/student/notifications.php`
- [ ] Parent: `/parent/dashboard.php` with child switcher
- [ ] Payment path (OnePay sandbox or known live test amount) if credentials changed

## Rollback
- Restore previous PHP files from last known good deploy
- Keep `/home/edexcel.college/.env` unchanged
- Restore DB only if a migration must be reversed (prefer forward-fix migrations)
