# ROTC Management System Documentation

Audience: Admin users and Developers

This documentation explains how the system works end-to-end, with practical how-tos for admins and implementation details for developers. Screenshots should be stored under `docs/images/` and referenced from the pages below.

- Admin-focused sections explain what a feature does and how to operate it.
- Developer-focused sections explain how it’s implemented, where the code lives, and important invariants.

## Navigation
- Setup & Deployment: `docs/Setup.md`
- Architecture overview: `docs/Architecture.md`
- Data Model: `docs/DataModel.md`
- Core Features:
  - ID Card Generator: `docs/CoreFeatures/IDCardGenerator.md`
  - Document Generation (AER): `docs/CoreFeatures/DocumentGeneration.md`
  - QR & Attendance: `docs/CoreFeatures/QR_Attendance.md`
  - Registration & Approvals: `docs/CoreFeatures/Registration_Approvals.md`
  - Enrollment Management: `docs/CoreFeatures/Enrollment.md`
  - User Management: `docs/CoreFeatures/UserManagement.md`
  - Profile Management: `docs/CoreFeatures/ProfileManagement.md`
  - Rifle Management: `docs/CoreFeatures/RifleManagement.md`
  - Grades: `docs/CoreFeatures/Grades.md`
  - Reports: `docs/CoreFeatures/Reports.md`
  - Settings: `docs/CoreFeatures/Settings.md`
  - Two-Factor (2FA): `docs/CoreFeatures/TwoFactor.md`
  - Announcements: `docs/CoreFeatures/Announcements.md`
- Security model: `docs/Security.md`
- Deployment notes: `docs/Deployment.md`
- Performance guide: `docs/Performance.md`
- Modules (per-file references): `docs/Modules/Index.md`
- Changelog: `docs/Changelog.md`

## Admin & User Pages
- Dashboard
  - Admin: `docs/Modules/admin_dashboard.md`
  - Officer: `docs/Modules/officer_dashboard_php.md`
  - Cadet: `docs/Modules/cadet_dashboard_php.md`
- Document Generation: `docs/Modules/document_generation_php.md`, `docs/Modules/generate_document.md`
- QR Attendance: `docs/Modules/qr_home_php.md`, `docs/Modules/attendance_process_qr.md`
- Attendance Dashboard: `docs/Modules/attendance_dashboard_php.md`
- Rifle Management: `docs/Modules/rifle_management_php.md`
- QR Scanner: `docs/Modules/rifle_scanner_php.md`, `docs/Modules/scanner_php.md`
- User Management: `docs/Modules/user_management_php.md`, `docs/Modules/user_management_secure_php.md`
- Missing IDs: `docs/Modules/admin_missing_ids_php.md`, `docs/Modules/file_missing_id_php.md`
- ID Card Generator: `docs/Modules/admin_id_card.md`, `docs/Modules/generate_id_card_php.md`
- Registration Approvals: `docs/Modules/admin_registration_approvals.md`
- Advance Officer Respondents: `docs/Modules/advance_rotc_management_php.md`
- Reports: `docs/Modules/reports_generate_report_php.md`, `docs/Modules/reports_view_report_php.md`
- Announcements: `docs/Modules/announcements_php.md`
- Grades: `docs/Modules/grades_manage_grades_php.md`, `docs/Modules/grades_view_grades_php.md`
- Security Dashboard: `docs/Modules/admin_security_dashboard_php.md`
- Backup Management: `docs/Modules/admin_backup_management_php.md`
- System Setup: `docs/Modules/qr_setup_php.md`
- Settings: `docs/Modules/settings_php.md`, `docs/Modules/user_settings_php.md`

## Screenshots
Place PNG/JPG images in `docs/images/` and reference them like:

```markdown
![ID 3x3 A4 Print Preview](../images/idcard_print_preview.png)
```

Recommended screenshots:
- ID Card Generator: selection UI, 3x3 print preview (IDs and QR pages).
- Registration Approvals: pending list, batch actions.
- Attendance: QR scan flow/results.
- Document Generation: success toast and sample CSV opened.

## Conventions
- CSV dates use `d-M-y` when derived (e.g., `05-Jan-25`).
- Regions: normalized to roman/short codes (e.g., `IV-A`, `NCR`).
- Address formatting: `City Province` (single space, no comma).
- Contact numbers in Roster: normalized to `0XXXXXXXXXX` and emitted as `="099…"` for Excel.
