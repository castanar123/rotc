# Data Model

This lists the fields referenced in code (not an exhaustive schema).

## users
- id (PK)
- username, email
- first_name, last_name
- role: `admin`, `commandant`, `1cl`, `2cl`, `basic-cadet`/`basic_cadet`
- approval_status: `pending|approved|rejected`
- status: `active|inactive`
- created_at

## cadet_profiles
- id (PK), user_id (FK users.id)
- student_id
- first_name, middle_name, last_name
- gender
- course, section, platoon
- birthdate
- blood_type, religion
- contact_number
- address (free text fallback)
- province_city (preferred source for City/Province)
- region (e.g., `IV-A`, `NCR`)
- status: `Active|active`

## attendance_logs
- id, cadet_profile_id (FK)
- created_at (used to prevent duplicates “today”)
- event_name `Daily Attendance`
- time_in, status

## attendance_records (analytics mirror, created if missing)
- id, cadet_id, cadet_name, student_id
- school_year, semester (computed from timestamp)
- event_name, recorded_at, recorded_by
- status, notes
- indexes across student_id, event, sy+sem

## other referenced tables
- missing_id_requests (active, expiry_date)
- audit_logs (general logging)
- advance_rotc_signups
