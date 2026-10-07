# Staging environment

## Purpose
Validate migrations, payments (sandbox), WhatsApp, and cron jobs before production.

## Setup
1. Copy `.env.staging.example` to a staging-only `.env` (never reuse production secrets). Prefer keeping staging secrets outside the web root the same way production uses `/home/edexcel.college/.env`.
2. Point DNS or a subdomain (e.g. `staging.edexcel.college`) at a separate document root or host.
3. Use a **separate database** and empty/copied anonymized data.
4. Set `APP_ENV=staging` and `APP_URL=https://staging.edexcel.college` (or your host). Disable live OnePay/WhatsApp production credentials unless intentionally testing with sandbox keys.
5. Run migrations under `database/migrations/` in order (include `038_google_auth_parent_links.sql` when testing portal Google login).
6. Configure cron to hit `tools/*.php` on the staging host with longer intervals if needed.

## Checks before promoting to production
- Admin login + TOTP (if enabled)
- Student/parent portals (`/portal/login.php` Google + phone OTP)
- Bank slip upload + admin review
- Official exam seed import
- Backup run + System health green/yellow only (no unexplained red)

## Notes
- Do not enable GitHub Actions/CI from this checklist unless separately requested.
- FTP/SFTP deploy should target staging first when testing features; production uses profile `edexcel.college` in `.vscode/sftp.json`.
