# Edexcel College – Production Deployment Notes

## Important
This package is prepared for upload into the website document root.
The ZIP contains the project files directly; there is no extra `final_work/` wrapper folder.

### Do NOT overwrite the live `.env`
The production package intentionally excludes `.env` so existing database/API credentials are not overwritten.
Keep the current server `.env` in place, or create it from `.env.example`.

For `kandy.edexcel.college`, the production values should include:

```env
APP_ENV=production
APP_URL=https://kandy.edexcel.college
```

## Required server environment

- PHP 8.1 or newer (8.2/8.3 recommended)
- PDO MySQL (`pdo_mysql`) – required for the application database
- PHP sessions
- Fileinfo – recommended for teacher image uploads
- cURL – required for WhatsApp API notifications
- mbstring – recommended for WhatsApp message logging
- OpenLiteSpeed/CyberPanel or Apache/Nginx + PHP-FPM

## Upload

1. Back up the existing website and database.
2. Upload this ZIP to the website document root.
3. Extract it so `index.php`, `config/`, `teachers/`, `timetable/`, etc. are directly inside `public_html`.
4. Preserve the existing `.env`.
5. Ensure the website user owns the files and directories are normally 755 / files 644.
6. Make `assets/images/teachers/` writable by PHP if teacher photo uploads are required.
7. Restart/reload OpenLiteSpeed/PHP if required.

## Database

Do not import the bundled SQL into an existing production database unless you intentionally want to replace/restore the database. The application is designed to use the existing database.

## PDF export

If Composer/Dompdf is installed, timetable PDF export uses Dompdf. If it is not installed, the endpoint now falls back to a print-ready page that can be saved as PDF from the browser instead of producing a fatal error.

## Security

- `.env`, `.sql`, and `.log` files are blocked by the root `.htaccess` rules.
- CSRF protection is enabled on the main and student login forms and management forms.
- Teacher/class/subject deletion uses POST + CSRF + soft-delete where supported.
- Rotate any credentials/tokens that were previously exposed in development/source archives.

## Main checks performed

- All PHP files pass `php -l` syntax validation.
- Root homepage loads without a PHP syntax/runtime fatal when the database is temporarily unavailable.
- Nested PHP includes use `__DIR__` paths to avoid server working-directory issues.
- The database schema supplied with the project does not contain `teachers.photo` or `student_classes.whatsapp_link`; the application no longer queries those nonexistent columns.
- Legacy `home.php` redirects to the canonical `/` homepage.
