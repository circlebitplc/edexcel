# Data retention

## Operational data
| Data | Suggested retention | Notes |
|------|---------------------|-------|
| Security events | 12–24 months | `security_events` |
| Audit log | Per college policy | Admin audit screens |
| Backups | Keep last N successful + monthly cold copy | See BackupService config / RPO |
| WhatsApp outbox | Failed retained for diagnosis; prune successful after 30–90 days | |
| Bank transfer slips | Keep while fee disputes possible (e.g. 24 months) | Private storage |
| Notification center | 90 days unread cleanup optional | `notification_center` |
| Parent OTPs | Short TTL already (minutes) | |
| Push subscriptions | Remove on unsubscribe / inactive endpoints | `push_subscriptions` |
| Data deletion requests | Process via `data_deletion_requests` | GDPR-style workflow |

## Private uploads
- Stored under `storage/private_uploads/` via `SecureUploadService` (not web-executable).
- Do not expose directory listing; `.htaccess` deny-all is written on first use.

## Student academic records
- Marks (`student_progress`) and attendance normally retained for the student’s enrollment lifetime unless a deletion request is approved.

## Account Deletion Policy & Retention Guardrails (`admin/settings.php?tab=delete_user`)
- **Preserved Academic & Financial Records**: When an administrator permanently removes or deactivates a user account via the Admin "Delete User" console, all historical compliance records are retained:
  - Class attendance timestamps (`student_attendance`)
  - Course enrollments and historical registrations (`student_enrollments`)
  - Fee ledger entries, payment receipts, and invoices (`student_fee_ledger`)
  - Exam marks, grading, and assessments (`student_exam_selections`)
  - Coursework and homework submissions (`student_homework_submissions`)
  - Parent/guardian connections (`parent_students`)
  - Full system security audit trails (`audit_logs`)
- **Purged Credentials & Sessions**:
  - `password_hash` overwritten with randomized deactivated marker
  - `google_id` erased and `google_email` unlinked
  - Active browser sessions terminated (`student_active_sessions`)
  - Trusted device tokens revoked (`student_devices`)
  - Pending login OTPs purged (`student_login_otps`, `student_device_otps`)
  - Username renamed with unique timestamp suffix to free original identifier
  - Account disabled (`account_status = 'disabled'`, `is_active = 0`, `deleted_at = NOW()`)
- **Security Guardrails**:
  - Administrator self-deletion is strictly blocked.
  - Primary System Administrator (ID #1) is protected from deletion.
  - Final active administrator account cannot be deleted.

## Site Visitor Telemetry & Analytics (`site_visitor_sessions`, `site_visitor_pageviews`)
| Data | Suggested retention | Notes |
|------|---------------------|-------|
| Visitor sessions | 6–12 months | `site_visitor_sessions` with anonymized IP hashes (`SHA-256(ip + salt)`); raw IPs never stored |
| Visitor pageviews | 90–180 days | `site_visitor_pageviews` (URL paths, page titles, durations); prune older granular hits to conserve database storage |
| Active visitor counter | Real-time (last 5 min) | In-memory / dynamic window calculation queried via `?action=active_count` |

- **Privacy & Security Protections:**
  - Raw client IP addresses are **never** stored in plain text; immediately hashed via salted SHA-256.
  - Sensitive query string parameters (`password`, `token`, `otp`, `secret`, `key`) are automatically stripped prior to persistence.
  - Browser `Do Not Track` (`navigator.doNotTrack === "1"`) is respected and prevents telemetry beacon dispatch.
  - Administrative back-office URLs (`/admin/`) are excluded from visitor tracking.


