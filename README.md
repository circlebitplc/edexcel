# Edexcel Timetable Management System

A comprehensive timetable management system for Edexcel College.

## Features
- Authentication (Admin + Teachers)
- Timetable CRUD with conflict detection
- Weekly & Google Calendar-style views
- Student count & revenue tracking (Rs 500/student)
- Payment status (pending/paid) – admin-only control
- Recurring weekly classes with auto-generation
- Teacher schedule with Monday–Sunday view
- Smart add form (Teacher → Subject → Class → Day → Time → Room)
- Bulk actions, holiday management, recurring schedule management
- Reporting & export (CSV, monthly, yearly)
- Security: CSRF, input validation, soft delete, audit log
- Code quality: Environment variables, Composer, MVC-like structure, API endpoint

## Requirements
- PHP >= 8.1
- MySQL >= 8.0
- Composer

## Installation
1. Clone the repository.
2. Run `composer install` to install dependencies.
3. Copy `.env.example` to `.env` and update database credentials.
4. Import the SQL schema from `database/` folder.
5. Configure your web server to point to the `public/` directory.

## API Endpoints
- `GET /api/timetable.php?teacher_id=1&date_from=2026-08-01&date_to=2026-08-07`

## Testing
Run `vendor/bin/phpunit` to execute tests.

## License
Proprietary – for Edexcel College use only.