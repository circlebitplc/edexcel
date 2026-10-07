# HTTP API overview

Base URL: site origin (e.g. `https://edexcel.college`).

Most endpoints are session-authenticated PHP pages or `ajax/*.php` JSON helpers. CSRF tokens are required on state-changing POSTs (`csrf_token` field or JSON body).

## Auth
- Staff: session after `/login.php` with "Continue with Google" OAuth (`intent=staff`) and verified role loading; optional collapsible legacy password fallback.
- Student & Parent: unified portal login at `/portal/login.php` using Google OAuth (with automatic profile picture sync) or WhatsApp/SMS OTP.
- Multi-child Parents: sessions managed by `ParentAuthService` after `/portal/login.php`, with dynamic view tokens (`?t=`).

## Notable JSON endpoints
| Path | Method | Role | Purpose |
|------|--------|------|---------|
| `/ajax/track_visitor.php` | POST/GET | public / rate-limited | Visitor telemetry beacon (`pageview`, `heartbeat`) and live active visitor count (`?action=active_count`) |
| `/ajax/lookup_student.php` | GET | staff | Campus student search by query string, direct student ID, or phone |
| `/admin/export_visitor_analytics.php` | GET | admin | Export visitor analytics as CSV (formula sanitized) or Dompdf-compatible printable PDF |
| `/ajax/notifications_center.php` | POST | any logged-in | Mark notification center read |
| `/ajax/student_notifications.php` | POST | student | Legacy student notifications read |
| `/ajax/student_session_ping.php` | POST/GET | student | Session keepalive |
| `/ajax/online_lesson_watch.php` | POST | student | Lesson progress |
| `/ajax/bunny_upload.php` | POST | staff | Bunny upload handshake |
| `/student/settings.php` | POST | student | Save profile / phone directly (`phone_action=save_direct&ajax=1`) or verify via OTP; dismiss modal |

## Live classroom client

HTTP upload and download stay on `/api/classroom/pdf.php`. Page position between browsers is a LiveKit data message, `{ t: 'pdf_scroll', doc_id, ratio, page, zoom }`, not a new HTTP route. The PDF file is not sent through LiveKit. See `SYSTEM.md` §38.6.

## OpenAPI
Machine-readable stub: [`openapi.yaml`](./openapi.yaml). Expand paths as new public APIs are added.

## Errors
JSON helpers typically return `{ "ok": false, "error": "..." }` with HTTP 4xx.
