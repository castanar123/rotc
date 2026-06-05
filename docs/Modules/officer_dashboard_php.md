# Module: Officer Dashboard (officer_dashboard.php)

- Path: `officer_dashboard.php`
- Audience: Admin, Developers
- Purpose: Officer command center for platoon metrics, attendance rate, recent activities, and quick actions.
- Access & Roles: Role `officer` only (strict check). Logs unauthorized access via `SecurityLogger`.

## Key UI Actions
- View platoon cadet count and today's attendance rate.
- Recent platoon activities.
- Quick actions: QR scanner, attendance reports, grades, create announcement, generate report.

## Data Sources
- users (platoon), attendance_logs, audit_logs.

## Security & Logging
- Uses `SecurityLogger::log` for access and unauthorized attempts.

## Screenshots
- Add: officer_dashboard_overview.png

## Developer Notes
- Sidebar links to QR scanner and attendance dashboard.
