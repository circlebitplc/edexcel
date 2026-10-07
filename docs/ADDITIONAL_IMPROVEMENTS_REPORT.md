# Additional improvements implementation report

## Implemented

- Additive finance reporting over existing verified `payment_transactions`:
  daily/multi-day collection by cash, OnePay and bank; student balance/history
  service; teacher payment comparison; audited discounts, scholarships, waivers
  and refunds; expense/reconciliation schema.
- Admission application and review workflow with duplicate phone protection,
  approval/rejection, student account creation, selected-class enrollment and
  enrollment history.
- Class capacity settings, occupancy/available-seat reporting, hard
  over-enrollment protection and audited capacity changes.
- Teacher workload analytics using existing timetable, enrollment and
  commission summaries.
- Attendance analytics by student, subject, class and month, including
  consecutive absence streaks and configurable at-risk thresholds.
- Published-only student and parent report-card views, with additive report
  card storage for term/subject marks, homework, attendance, grades and
  comments.
- Existing homework submission/review flow is preserved; the new migration
  supports the broader report-card and analytics layer.
- Unified communication queue/history for supported channels, scheduled
  report configuration and safe cron processing through existing notification
  infrastructure.
- Role-aware global search: teacher results are scoped to their lessons and
  financial results remain admin-only.
- Controlled CSV preview/import center with all-or-nothing transactions and
  audit logging.
- Branded error page with JSON responses for API clients.
- Request performance metrics stored in `performance_metrics`.
- Staging-only Playwright smoke-test scaffold with production hostname guard.

## Files created

`database/migrations/024_office_finance_admissions_analytics.sql`

Services:

`FinanceService.php`, `AdmissionService.php`, `CapacityService.php`,
`AttendanceAnalyticsService.php`, `ReportCardService.php`,
`GlobalSearchService.php`, `CommunicationService.php`,
`PerformanceMonitor.php`, `ImportExportService.php`

Pages/jobs:

`admin/finance.php`, `admin/admissions.php`, `admin/capacity.php`,
`admin/communications.php`, `admin/data_center.php`,
`admin/scheduled_reports.php`, `admin/search.php`,
`admin/teacher_analytics.php`, `admin/attendance_analytics.php`,
`admissions/apply.php`, `student/report_card.php`, `parent/report_card.php`,
`error_page.php`, `tools/communication_queue.php`,
`cron/communication_queue.php`, `tools/scheduled_reports.php`,
`cron/scheduled_reports.php`

Tests/docs:

`tests/e2e/README.md`, `tests/e2e/playwright.config.js`,
`tests/e2e/portal.spec.js`, and this report.

## Files modified

- `.htaccess` — branded error mappings
- `config/bootstrap.php` — non-blocking request performance recording
- `includes/app_menu.php` — new authorized admin/student links
- `storage/.htaccess` — private import/storage protection

## Migration additions

Migration `024` adds finance adjustments/expenses/reconciliations, teacher
payment events, admission applications/documents, enrollment history, class
capacity, teacher availability/leave, attendance alerts, report cards,
paper attempts, communication templates/messages, import jobs, scheduled
reports and performance metrics.

## Cron additions

- `communication_queue.php`
- `scheduled_reports.php`

Install these on staging first. Existing OnePay/payment verification code was
not changed.

## Verification

- PHP syntax checks passed for all new/modified PHP files.
- IDE lint checks passed.
- Existing PHPUnit suite: **95 tests, 234 assertions, 0 failures**.
- FTP upload: **34 files OK, 0 failed**.

## Required deployment steps

1. On staging, run `php bin/migrate.php`.
2. Configure staging-only test accounts and run the Playwright suite with
   `E2E_ALLOW_MUTATIONS=1`.
3. Add the two new cron jobs using the `tools/` copies.
4. Review admin permissions and test admission approval, capacity enforcement,
   finance adjustments and report publication.
5. Run the migration on production during the normal maintenance window.
6. Do not overwrite production `.env`.

## Known limitations

- Existing fee/payment creation and verification remain the source of truth;
  finance adjustments are additive and do not rewrite historical transactions.
- The report-card service reads existing marks and publishes only explicit
  report-card records; it does not invent missing exam/homework data.
- Scheduled reports currently create authorized in-app report notices; full
  email/SMS/PDF delivery should be connected only after the corresponding
  provider and export policy are configured.
- The browser suite is a safe scaffold for staging and currently covers
  admission-page, staff-login and student-report-card smoke flows. It does not
  run against production or mutate live data.
- Existing teacher/parent/student permission helpers remain authoritative;
  no new finance role was introduced because the current role enum is
  admin/teacher/student.
