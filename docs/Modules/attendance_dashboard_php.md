# Module: Attendance Dashboard (attendance/dashboard.php)

- Path: `attendance/dashboard.php`
- Audience: Admin, Instructors, Officers, Developers
- Purpose: Real-time QR scanning panel + analytics (hourly chart, recent logs) and lookup tools.

## Key UI Actions
- Start/stop in-page QR scanner (Html5QrcodeScanner).
- Refresh charts/logs and export attendance.
- Lookup records by name, student id, date/time range, TD, semester, status.

## Data Sources
- attendance_records (hourly, recent), attendance_logs (legacy), users, cadet_profiles.

## Security & Roles
- Requires login; used by admin/officer/instructor per nav.

## Screenshots
- Add: attendance_dashboard_scanner.png, attendance_dashboard_charts.png

## Developer Notes
- Uses Chart.js for charts and fetch to `api/attendance_operations.php` for recording scans.
