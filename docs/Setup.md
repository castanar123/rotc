# Setup & Deployment

- OS: Windows (XAMPP)
- Web root: `c:\xampp\htdocs\generate qr`
- Server URL: `http://localhost/generate%20qr/`

## Prerequisites
- XAMPP with PHP and MySQL/MariaDB
- Create a database; configure credentials with `includes/db_config.local.php` or `ROTC_DB_*` environment variables
- Ensure `output/` directory is writable for document exports

## Database
- Import the schema and seed data as provided by your environment (users, cadet_profiles, etc.).
- Tables referenced by the system include: `users`, `cadet_profiles`, `attendance_logs`, and `attendance_records` (auto-created if missing).
- Copy `includes/db_config.local.php.example` to `includes/db_config.local.php` for machine-specific local credentials. Do not commit the local file.

## Running locally
1) Start Apache and MySQL in XAMPP.
2) Visit `http://localhost/generate%20qr/`.
3) Log in with an admin account to access admin features (dashboard, approvals, ID generator, documents).

## Printing IDs (Admin)
- Use Chrome print dialog.
- Settings: A4, Portrait, Scale 100%, Margins None/Minimum, Background graphics On, Headers/Footers Off.

## Output files
- Generated documents are saved under `output/` and returned by API as a path/URL.

## GitHub and Vercel
- Keep local data, logs, backups, uploads, `.env`, and dependency folders out of Git.
- See `docs/Deployment.md` for the supported hosting paths and Vercel limitations when the database remains local.

## Screenshots
- Store screenshots under `docs/images/` and link them in feature docs.
