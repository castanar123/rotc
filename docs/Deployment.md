# Deployment Notes

## Current Runtime

This project is a PHP application designed for XAMPP:

- Apache serves the PHP pages.
- MySQL or MariaDB runs locally.
- Generated files are written to local folders such as `output/`, `uploads/`, `backups/`, and `logs/`.

## GitHub Readiness

Before pushing to GitHub, keep source code separate from machine data:

- Commit PHP, CSS, JS, SQL schema, docs, and config examples.
- Do not commit `.env`, local database config, generated QR codes, uploaded cadet files, backups, logs, SQLite files, database dumps, or dependency folders.
- Install PHP dependencies with Composer after cloning:

```bash
composer install
```

Local database credentials should be configured using one of these:

- `includes/db_config.local.php`, copied from `includes/db_config.local.php.example`
- Environment variables such as `ROTC_DB_SERVER`, `ROTC_DB_USER`, `ROTC_DB_PASS`, and `ROTC_DB_NAME`

## Vercel Reality Check

Vercel can deploy from GitHub, but this project is not a normal static or Node app. It is a PHP/XAMPP app with local filesystem writes and a local MySQL dependency.

Important constraints:

- `localhost` on Vercel means the Vercel runtime, not your XAMPP machine.
- A local MySQL database on your PC is not reachable from Vercel unless you expose it through a secure network path.
- Vercel Functions have an ephemeral/read-only runtime model, so generated files should not be treated as permanent local storage.
- PHP on Vercel depends on a community runtime path, not the default framework path.

## Practical Hosting Options

### Option A: Keep PHP and DB Local

Use XAMPP locally and expose the app with Cloudflare Tunnel. This best matches the current project because PHP and MySQL stay on the same machine.

### Option B: Vercel Frontend, Local Backend

Build a separate frontend for Vercel and keep this PHP app as a local API behind Cloudflare Tunnel. This needs API hardening, CORS rules, authentication review, and stable public tunnel URLs.

### Option C: Full Cloud Deployment

Move the database to hosted MySQL or another cloud database, replace local file writes with durable storage, then deploy the app/runtime. This is the cleanest public-hosting model, but it changes the "database is local" requirement.

## Recommended Next Step

For the current setup, push a clean GitHub repository first. Then use Cloudflare Tunnel for live access while planning whether Vercel should host a future frontend or a refactored version of the app.
