# Module: Security Dashboard (admin/security_dashboard.php)

- Path: `admin/security_dashboard.php`
- Audience: Admin, Developers
- Purpose: Monitor security events, sessions, alerts; filter by event type, severity, date range.
- Access & Roles: Admin-only.

## Data Sources
- security_logs, user_sessions, alert_notifications.

## Screenshots
- Add: security_dashboard_overview.png

## Developer Notes
- Uses `SecurityLogger` for retrieval helpers; event/severity badge mapping in UI.
