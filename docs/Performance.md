# Performance Guide

## Runtime Target

Use PHP 8.2 or newer. For best performance and longest support window, use the latest stable PHP 8.5.x release available for your server.

Run Composer checks after upgrading PHP:

```bash
composer check-platform-reqs
composer dump-autoload --optimize
```

Enable PHP extensions required by the app and document generation:

- `zip`
- `dom`
- `xml`
- `json`

## Apache

The root `.htaccess` enables safe defaults for:

- Static asset caching
- Gzip or Brotli compression when the Apache modules are available
- Directory listing protection
- Blocking direct access to local environment, Git, SQL, and database files

In XAMPP, enable these modules in Apache if they are not already active:

- `headers`
- `expires`
- `deflate`
- `brotli` when available

## PHP

Use `php.ini.performance.example` as a starting point for OPcache and runtime settings. OPcache is usually the biggest backend response-time win for a PHP app because scripts do not need to be parsed and compiled on every request.

Suggested local validation after editing `php.ini`:

```bash
php -v
php -m
php -i | findstr /I "opcache"
```

Restart Apache after changing PHP settings.

## Database

Keep MySQL close to the PHP runtime. With the current local-database requirement, XAMPP or a Cloudflare Tunnel to the local machine is a better match than a direct Vercel deployment.

For query performance:

- Use `EXPLAIN` on slow dashboard and report queries.
- Add indexes for frequently filtered fields such as `student_id`, `cadet_id`, `log_date`, `semester`, `td`, and role/status columns.
- Avoid test/debug endpoints in production routing.

## Frontend

For fastest first load:

- Prefer bundled/minified CSS and JS for public pages.
- Compress large gallery images before committing.
- Use WebP or optimized JPEG for photos.
- Cache static files aggressively and keep dynamic PHP pages uncached.
