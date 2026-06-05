# QR & Attendance

![QR Scan Flow](../images/qr_scan_flow.png)

## Admin Guide
- The QR scanner records attendance for approved/active users.
- Duplicate protection: prevents multiple records for the same day.

## Developer Notes
- Processor: `attendance/process_qr.php` (POST JSON)
- Request body: `{ qr_data: "<id>[:timestamp]", timestamp? }`
- Flow:
  - Parse `qr_data` as user ID, else as `cadet_profile_id` (permanent).
  - Validate approved + active user.
  - Locate cadet profile; create attendance in `attendance_logs` if not already recorded today.
  - Mirror into `attendance_records` (auto-created if absent) for analytics:
    - Compute SY/Sem via `computeSchoolYearSemester()`
    - Check duplicate by `(cadet_id, event_name, semester, date)`
- Response JSON indicates success or specific failure (not approved, inactive, not found).

## Invariants
- Event name: `Daily Attendance`.
- Semester/SY derived from timestamp month.
- Security: must be logged in (session) to call.
