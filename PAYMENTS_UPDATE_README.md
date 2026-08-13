# Payments + Teacher Ledger + Reports Update

## Included
- Unified finance/payment UI based on the system.css design.
- Live fee and currency read from the `settings` table with a safe fallback.
- Redesigned `timetable/payments.php`.
- New `timetable/payment_ledger.php` with daily opening/earned/paid/closing balances.
- Redesigned revenue, monthly and yearly reports.
- Payment receipt uses the live configured fee.
- Payment-related dashboard/timetable/export calculations use the live configured fee.
- Added `assets/css/payments.css`.

## Ledger calculation
Opening balance + lesson earnings - payments recorded = closing balance.
The ledger is derived from the existing timetable/payment fields; no new database table is required.

## Business rule preserved
The configured `fee_per_student` value remains the source of the lesson amount. The current database setting is Rs 500 per student per lesson. No fee was changed by this update.
