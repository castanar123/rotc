# Module: Missing IDs (Admin) (admin/missing_ids.php)

- Path: `admin/missing_ids.php`
- Audience: Admin, Developers
- Purpose: Admin panel to review/resolve missing ID filings.

## Badges & Counts
- Sidebar badge shows active missing ID requests (`includes/admin_nav.php`).

## Screenshots
- Add: admin_missing_ids.png

## Developer Notes
- Ensure table `missing_id_requests` exists; badge query: active + non-expired.
