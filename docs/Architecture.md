# Architecture Overview

- PHP application with server-rendered pages and JSON endpoints.
- MySQL/MariaDB database for users, cadet profiles, attendance, logs.
- Client-side JS for QR, previews, and UI interactions.
- Libraries:
  - QR: chillerlan/php-qrcode, qrcodejs
  - CSV generation: native string building + file write to `output/`
  - TCPDF present for PDFs but primary AER outputs are CSV

## Key Entry Points
- Admin Dashboard: `admin_dashboard.php`
- Registration Approvals: `admin/registration_approvals.php`
- ID Card Generator: `admin/id_card.php`
- Document Generation API: `generate_document.php` (POST JSON)
- Attendance Scan Processor: `attendance/process_qr.php` (POST JSON)

## Directory Highlights
- `admin/` admin pages
- `attendance/` QR processing
- `docs/` documentation
- `output/` generated files (CSV)
- `includes/` shared modules (db, session, nav, SecurityLogger)
- `QR/` front-end QR tools

## Cross-Cutting
- Security: role checks in PHP, `SecurityLogger` events
- Styling: dashboard CSS and print CSS inside pages (not a separate build step)
- Logging: PHP error_log for debug traces
