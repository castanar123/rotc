# Module: Cadet Dashboard (cadet_dashboard.php)

- Path: `cadet_dashboard.php`
- Audience: Admin, Developers
- Purpose: Cadet self-service portal showing personal attendance stats, grades (if available), announcements, and quick actions.
- Access & Roles: Cadet-facing. Allows roles `cadet`, `basic_cadet` (guards in file). Others see different dashboards.
- Navigation: Shown after login for cadets. Sidebar links to My Attendance, My Grades, Announcements, My Profile.

## Key UI Actions
- View attendance totals and this-month count.
- View average grade (computed via `grades` table if present; else simulated).
- Quick links to QR check-in (for non-basic cadets), grades, announcements, and profile.

## Data Sources
- attendance_logs or attendance (fallback logic based on table existence).
- grades (if present) for average grade.
- announcements for recent items and counts.

## Security & Logging
- Gate via session; redirects if not logged in.
- Standard PHP session and DB includes.

## Screenshots
- Add to `docs/images/`:
  - cadet_dashboard_overview.png

## Developer Notes
- Handles dual attendance backends (attendance_logs vs attendance) with graceful fallbacks.
- Stores/uses `cadet_profile_id` from session or resolves via `cadet_profiles`.
