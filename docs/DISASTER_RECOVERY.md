# Disaster Recovery — Edexcel College

Default objectives (configurable in Admin → Backup):

| Metric | Setting key | Default |
| --- | --- | --- |
| RPO | `rpo_hours` | 24 hours |
| RTO | `rto_hours` | 4 hours |

Backups: `BackupService` → `storage/backups/` (HTTP denied). Prefer keeping zip archives under `/home/edexcel.college/private_backups/` (outside docroot). Cron: `tools/automated_backup.php`. UI: `/admin/backup.php`.

**Never** put `.env` into downloadable backups. **Never** expose backup archives over HTTP. **Never** overwrite production `/home/edexcel.college/.env` during restore.

---

## Scenario playbooks

### 1. Database corruption
1. Enable maintenance / stop cron writes if possible.
2. Identify latest **verified** success row in Admin → Backup.
3. On the server: decrypt if needed (`BACKUP_ENCRYPTION_KEY`), extract `database.sql`.
4. Restore via `mysql` CLI into a new schema or after taking a forensic dump of the broken DB.
5. Point app DB credentials only after validation queries succeed.
6. Run `php bin/migrate.php`, then `/admin/system_health.php`.

### 2. Server failure / full host loss
1. Provision replacement PHP/MySQL host.
2. Deploy application files from git or last files archive (exclude vendor rebuild via `composer install --no-dev` if needed).
3. Recreate `/home/edexcel.college/.env` from your password manager (not staging secrets; not under `public_html/`).
4. Restore DB + `files/` + `uploads/` from backup.
5. Reinstall crontab (`tools/*` paths under `/home/edexcel.college/public_html/`).
6. Health checklist: staff login, `/portal/login.php`, OnePay callback URL, WhatsApp webhook, LiveKit, Bunny.

### 3. Disk failure
1. Attach replacement volume or new host.
2. Restore from off-server copy if local `storage/backups` is lost.
3. Verify disk free space on System Health before reopening payments.

### 4. Bad deployment
1. Do **not** run browser restore in production.
2. Re-upload previous known-good PHP/assets from last files backup or a local restore folder such as `backup/livekit-restore-20260925-1225/` (classroom UI and PDF document viewer). Do not copy that folder into the web root.
3. Keep current `/home/edexcel.college/.env`.
4. Re-run migrations only forward (`php bin/migrate.php`).
5. Smoke-test timetable + payments + classroom + portal Google login if enabled.

### 5. Accidental data deletion
1. Stop further destructive admin actions.
2. Restore affected tables from the most recent dump into a temporary database.
3. Selective `INSERT … SELECT` of missing rows after review.
4. Audit `audit_logs` / `security_events` for who/when.

### 6. Payment inconsistencies
1. Export `payment_transactions` + `payment_transaction_events` for the window.
2. Compare with OnePay merchant portal / bank slip queue.
3. Prefer manual reconciliation; do not auto-refund from restore.
4. Use existing PaymentVerificationService flows for stuck `initiated/pending`.

### 7. WhatsApp outage
1. System Health will show WhatsApp red/yellow; alerts are throttled.
2. OTP fallback: SMS channel where configured.
3. Outbox retries via `tools/whatsapp_outbox.php` after Meta/Evolution recovers.
4. Paste a new Cloud API token on Connect WhatsApp if auth failed.

### 8. Bunny webhook outage
1. `tools/bunny_sync.php` recovers processing status.
2. Stuck recordings appear on System Health; re-sync rather than deleting assets.
3. Confirm webhook secret still matches Bunny dashboard.

### 9. LiveKit outage
1. Online classes degrade; physical/hybrid lessons continue.
2. After LiveKit returns, verify `LIVEKIT_URL` / keys in `.env` and a test room join.
3. Classroom reminders resume via cron.

---

## Safeguards
- Production browser SQL restore is **disabled**.
- Manual restore requires typed confirmation `RESTORE-YYYY-MM-DD` on non-production only.
- Changing backup settings while admin TOTP is enabled expects re-authentication from Admin → Security.
- Retention: 7 daily / 4 weekly / 3 monthly (configurable); policy-required backups are not purged early.
