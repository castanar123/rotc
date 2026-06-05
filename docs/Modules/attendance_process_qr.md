# Module: attendance/process_qr.php

## Overview
Processes QR scan submissions and records attendance for approved and active users.

## Entry Point
- POST JSON to `attendance/process_qr.php`
- Body: `{ qr_data: "<id>[:timestamp]", timestamp? }`
- Returns: JSON `{ success, message, user }`

## Functions
### computeSchoolYearSemester(string $timestamp): array
- Purpose: Derive school year and semester from a timestamp.
- Returns: `[schoolYear, semester]` where sem is `1` or `2`.

### ensureAttendanceRecordsTable(PDO $pdo): void
- Purpose: Create `attendance_records` if missing (idempotent).
- Side effects: Executes CREATE TABLE IF NOT EXISTS with indexes.

## Flow
- Parse `qr_data` → attempt users.id; fallback to cadet_profiles.id.
- Validate approved + active; fetch cadet profile and student_id.
- Prevent duplicate same-day `attendance_logs` entry.
- Mirror to `attendance_records` with computed SY/Sem; duplicate guard by `(cadet_id,event,sem,date)`.
- Log activity via `activity_logs` (if present).

## Invariants
- Event name: `Daily Attendance`.
- Requires session auth.
