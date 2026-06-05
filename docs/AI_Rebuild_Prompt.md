# AI Rebuild Prompt: ROTC Management System

You are an expert full‑stack engineer. Rebuild the ROTC Cadet Management System described below from scratch. Follow the required structure, data model, logic flows, and UX. Do not include hosting/deployment details. When in doubt, prefer security, clear UX, and maintainability.

## Objectives
- Implement a role‑based ROTC management system covering: ID card generation, document generation (AER), QR attendance, registration approvals, rifle inventory, grades, reports, announcements, settings, backup management, and security monitoring.
- Reproduce exact data transforms and business rules called out below (CSV formatting, address/region normalization, print layout, etc.).
- Deliver an application with a relational database schema and client UI. Keep dependencies minimal.

 

## High‑Level Architecture
- Modules/Entry points (selected):
  - Admin Dashboard
  - Cadet Dashboard
  - Officer Dashboard
  - Security Dashboard
  - Backup Management
  - ID Card Generator
  - Document Generation UI
  - CSV Generator (server)
  - QR Attendance Home
  - Attendance Dashboard
  - Attendance Processor API (record endpoints)
  - Rifle Management
  - Generic Scanner
  - Registration Approvals
  - Advance Officer Respondents
  - Reports (Generate, View)
  - Announcements
  - Setup & Settings
  - Missing IDs
- Core services:
  - Authentication/Session service (contains login checks, redirects, role checks)
  - Data access layer
  - Security logging component
  - Backup manager service
  - Navigation/Sidebar builder

## Security & Roles
- Roles: `admin`, `commandant`, `1cl`, `2cl`, `officer`, `instructor`, `cadet`, `basic_cadet`.
- Gate pages with `check_login()` and role checks; redirect to the login page if not authorized.
- Two‑factor support (if enabled).
- Security logging component: record events: login_success/failed, admin access, data_modification, backup_completed/failed, suspicious_activity, etc. Include user_id, IP, UA, severity.
- Sessions: `user_sessions` table (session_token, expires_at, user_agent, ip_address, is_active).

## Academic Term & Enrollment (Critical)
The system is term‑driven. A “term” is the combination of `school_year` (e.g., `2025-2026`) and `semester` (e.g., `1st`, `2nd`).

Global rule:
- Every dashboard count, list, attendance record, document export, and report MUST be filtered to the currently selected term AND only include cadets who are enrolled in that same term.

Term selection (calendar/selector):
- Provide a term selector UI in dashboards (admin/officer/cadet) that lets the user choose the active `school_year` + `semester`.
- Persist the selection per-session (and optionally store a system default “current term”).
- Default selection: the system’s configured current term.

Enrollment model:
- A cadet can be enrolled in 1st semester, 2nd semester, both, or neither (history preserved).
- Enrollment is NOT inferred from “active user” alone; it is explicit per-term.

Enrollment lifecycle:
- 1st semester:
  - New cadets register via the registration wizard.
  - After admin approval, the cadet is automatically enrolled into the current term (typical case: 1st semester).
- 2nd semester:
  - Cadets who were enrolled in 1st semester must complete a “Verify Profile & Re-enroll” flow to be enrolled in 2nd semester.
  - Cadets who were NOT enrolled in 1st semester but need 2nd semester must register (same registration wizard), then admin reviews and enrolls them into the current term (2nd semester).
- Drop rule:
  - Cadets enrolled in 1st semester who do not re-enroll in 2nd semester by a configured cutoff are marked `dropped` for 2nd semester.
  - Dropped cadets must not appear in 2nd semester totals, attendance, documents, or reports.

Security for re-enrollment:
- During the 2nd semester verification/re-enrollment flow, force:
  - Password reset
  - PIN setup (extra login factor)
- Login flow then requires password + PIN (or password followed by PIN challenge).

## Database Schema (minimum viable)
Implement tables with sensible types and indexes:
- users: id, username, email, password_hash, role, status(active/inactive/pending), approval_status(pending/approved/rejected), platoon, created_at, last_login
- cadet_profiles: id, user_id(FK), student_id, first_name, middle_name, last_name, gender, course, section, platoon, province_city, region, address, contact_number, status, birthdate
- attendance_logs: id, cadet_profile_id(FK), event_name, event_date, time_in, status, created_at
- attendance_records: id, cadet_id (cadet_profiles.id), recorded_at (datetime), status
- academic_terms: id, school_year, semester, start_date, end_date, is_current, created_at
- cadet_enrollments: id, cadet_profile_id(FK), school_year, semester, enrollment_status(enrolled/pending_verification/dropped), enrolled_at, verified_at, dropped_at, source(registration/re_enroll/admin), last_reviewed_by, last_reviewed_at
- user_security: user_id(FK), pin_hash, pin_last_changed_at, must_reset_password, must_set_pin, failed_pin_attempts, pin_locked_until
- grades: id, cadet_id (users.id), semester(1|2), academic_year(YYYY-YYYY), written_work, performance_task, quarterly_exam, total_grade, subject (nullable), created_by, created_at
- quiz_scores: id, cadet_id (users.id), quiz_name, score, max_score, semester, academic_year, percentage (computed), created_at
- announcements: id, title, content, author_id, priority(low/normal/high/urgent), target_audience(all/cadets/officers/admin), expires_at, created_at
- audit_logs: id, user_id, action, ip_address, user_agent, timestamp
- security_logs: log_id, user_id, event_type, severity, description, ip_address, user_agent, created_at
- alert_notifications: id, log_id(FK), alert_type, message, created_at
- backups (or history table used by BackupManager): id, file_name, file_path, status(completed/failed/running), backup_type, description, file_size, created_at, completed_at
- missing_id_requests: id, user_id, reason, status(active/resolved), expiry_date, created_at
- advance_rotc_signups: id, full_name, course, facebook_link, created_at

## Core Feature Requirements & Logic
### 1) ID Card Generator
- Admin UI to select cadets and print cards.
- Print layout: A4 portrait, 3x3 grid (9 per page), IDs aligned top‑left. QR pages separate when QR‑only mode is selected.
- Scaling must be uniform; avoid overlapping; base card canvas resized into fixed 60mm columns with 5mm gaps.
- Provide “IDs only” vs “QR only” print toggles.

### 2) Document Generation
- UI lists AER documents: Summary, Roster, Beneficiaries, Cadet Profile, plus ASR placeholder.
- Server generator rules (critical):
  - Roster:
    - ADDRESS column is derived as `City Province` from `cp.province_city` or fallback `cp.address`.
    - Collapse multiple spaces to single space.
    - CONTACT NUMBER normalized to local 11‑digit mobile `0XXXXXXXXXX`:
      - strip non‑digits
      - drop leading `63` if present
      - if >10 digits, take last 10 then prefix `0`
      - Output to CSV as an Excel‑safe string formula: `="099…"` to preserve leading zero without showing an apostrophe.
  - Cadet Profile:
    - Header includes `CITY PROVINCE` and `RGN` columns.
    - `CITY PROVINCE` = `City Province` (single space, no comma) from `cp.province_city`.
    - Region normalized to roman/short codes where possible (e.g., `IV-A`, `NCR`).
- CSV header and row ordering must match existing AER expectations; sort by MS level, gender, then name where applicable.

### 3) QR Attendance
- In‑page QR scanner to scan cadet ID QRs.
- Decoding logic accepts:
  - Base64 permanent format: `base64(KEY_PREFIX + JSON)` -> extract `profile_id`, `student_id`, `name`.
  - Legacy AES string with key `attendance-system-permanent-key-2023` (optional compatibility).
  - Plain numeric fallback -> treat as `profile_id`.
- On success, call `/api/attendance_operations` with payload `{ action:'record_attendance', school_year, semester, event_name (daily), profile_id|cadet_id }`.
- Dashboard charts: hourly counts, recent logs; Lookup filters by name, student_id, date/time, TD, semester, status.

Processing rules:
- Require authentication and user approval before recording.
- Resolve cadet by `profile_id` (preferred) or `student_id` when provided.
- Derive `school_year` and `semester` from scan timestamp via a helper; pass through if client provided.
- Before recording, confirm the cadet has `cadet_enrollments.enrollment_status='enrolled'` for the requested `school_year` + `semester`. If not enrolled (or dropped), reject with a clear message.
- Prevent duplicates: if a record exists for the same cadet and day (and event_name where applicable), return a friendly duplicate message without creating a new record.

Persistence:
- Create a durable record in `attendance_records` with fields: `cadet_id` (cadet_profiles.id), `recorded_at` (server timestamp), `status` (e.g., present), and any contextual fields (event_name, semester, school_year) as applicable.
- Mirror to `attendance_logs` for legacy compatibility: `event_name`, `event_date`, `time_in`, `status`.
- Ensure tables exist or provide a graceful error if not.

Responses & errors:
- Success: `{ success: true, message, record: { cadet_id, recorded_at } }`.
- Errors: invalid/expired QR, cadet not found, user not approved, duplicate for day, database error — return `{ success: false, message }` with specific reasons.

Security & logging:
- Gate API with session; log attempts and outcomes in the security logging component.
- Throttle rapid repeats client-side to reduce accidental double scans.

### 4) Registration & Approvals

Registration intake (multi‑step wizard):
- Steps: Account, Personal, Physical, Files.
- Fields:
  - Account: `username`, `email`, `password`, `confirm_password`.
  - Personal: `student_number`, `first_name`, `middle_initial`, `last_name`, `gender`, `birthdate`, `place_of_birth`, `religion`, `course`, `section`, `platoon`, `contact_number`, `facebook_profile` (URL).
  - Address & Location: `region` (required), `province` (optional for NCR), `city_municipality` (required), `barangay` (required), `purok` (optional), `address` (optional).
  - Guardians: `father_name`, `father_occupation`, `mother_name`, `mother_occupation`, `guardian_name`, `guardian_contact`, `guardian_relationship`, `guardian_address`.
  - Physical: `height`, `weight`, `skin_color`, `blood_type`.
  - Files (required): `photo`, `signature`. Store as `uploads/photos/{user_id}_<original>`, `uploads/signatures/{user_id}_<original>`. Ensure directories exist and are writable; fail fast if not.

Validation rules:
- Required: `username`, `student_number`, `first_name`, `last_name`, `gender`, `email` (valid), `region`, `city_municipality`, `barangay`, `password`, `confirm_password`, `photo`, `signature`.
- Facebook URL: must be a valid URL and include `facebook.com` if provided.
- Password strength: at least 8 chars, includes uppercase, lowercase, number, and special character; must match confirmation.
- Duplicate checks: `users.email` OR `users.username` must be unique; `cadet_profiles.student_id` must be unique (exact match).

Normalization & composition:
- Compute `province_city` from location: "City, Province" if both present; otherwise whichever exists.
- If `address` is blank, compose from provided parts: `Purok X`, `Brgy. NAME`, `province_city`, `region` (use commas between parts).
- Contact numbers and location are stored as entered; CSV/document generation performs separate normalization for exports.

Persistence & status:
- Create user with role “basic-cadet”, `approval_status='pending'`, `status='inactive'`; store password as a secure hash.
- Create cadet profile with all fields plus `province_city`; store file paths for photo and signature.
- Security logging: emit `REGISTRATION_ATTEMPT` on submit, `REGISTRATION_SUCCESS` after successful commit, `REGISTRATION_FAILED` with error context on failure.
- Transactions: wrap user + profile + file operations; rollback on any failure (including upload failure) and return friendly error.
 - Preflight: verify DB connection and that `users` and `cadet_profiles` tables exist before proceeding; surface clear errors.
 - Optional: attempt to store `first_name`, `middle_name`, `last_name` on the user record; if columns are missing, continue without failing registration.

Term enrollment on approval:
- When admin approves a registration, also create or update a `cadet_enrollments` row for the system’s current term.
- Default behavior: enroll into the current term (usually 1st semester during initial enrollment period).
- If the current term is 2nd semester, allow admin to enroll a newly registered cadet directly into 2nd semester.

Database guards (self‑healing):
- Ensure `cadet_profiles.student_id` column has sufficient width for the full ID value (increase if needed).
- Ensure unique index on `cadet_profiles.student_id` covers the full column (drop/recreate to avoid prefix‑length uniqueness).

Admin approvals:
- Actions: approve single, approve selected (bulk), approve all, reject.
- Approve sets `users.approval_status='approved'`, `users.status='active'`, and `cadet_profiles.status='Active'`.
- Reject sets `users.approval_status='rejected'`, `users.status='inactive'`; cadet profile remains for audit/history (mark status accordingly).
- Display stats: counts for pending, approved, rejected; provide batch action buttons.
- Log all approval/rejection actions as DATA_MODIFICATION via the security logging component.

UX behaviors:
- Multi-step wizard with a visible progress indicator (Account → Personal → Physical → Files).
- Preserve entered values on validation errors; display field-level errors and an aggregated alert at the top.
- Password input shows live strength requirements and provides a visibility toggle.
- After successful submission, show a message that the account is pending admin approval.

Diagnostics:
- Log registration attempt metadata (non-sensitive keys and which file fields were provided) for troubleshooting.
- On errors, record diagnostic details (message, code, file, line) in server logs; do not expose sensitive internals in the UI.

Role note:
- Role string for newly registered users: 'basic-cadet' (hyphenated).

### 5) Rifle Management & Scanner
- Manage inventory; integrate rifle QR functions module and API endpoints.
- Dedicated scanner page for rifle workflows.

Data model:
- rifles: `id`, `rifle_number` (or `serial_number`), `rifle_type`, `status` (available/assigned), `qr_code_path`, optional `notes`/`condition_notes`, timestamps.
- rifle_assignments: `id`, `rifle_id`, cadet FK column varies by schema (`cadet_profile_id` preferred; legacy `cadet_id`/`borrower_id` supported), `assigned_by`, `assigned_at`, `returned_at`, `status` (active/returned).
- rifle_logs: `id`, `rifle_id`, cadet FK (same variants), `action` (created/assigned/returned), `performed_by`, `timestamp`/`created_at`, `details`.
- borrowers (legacy mapping): `id`, `temp_id` or `name`, `course`, `status` — used only when schema requires `borrower_id`.

QR generation & decoding:
- Rifle QR: JSON payload `{ type:'rifle', rifle_id, rifle_number, rifle_type, system:'rotc_rifle_management', generated_at, version:'2.0', encryption_method }` encrypted to a CryptoJS‑compatible AES‑CBC format and base64 encoded. Saved under `uploads/rifle_qrcodes/` and path stored in rifle record.
- Cadet QR (for rifle assignment): base64 of `KEY|JSON` containing `{ type:'cadet', cadet_id, student_id, name, platoon, course, system:'rotc_rifle_management' }`, saved under `uploads/cadet_qrcodes/` and optionally stored on the profile.
- Decoding: attempt CryptoJS decryption with known keys for `rifle` and `attendance` types; validate `system` and `type` fields before use.

API actions (examples):
- `check_rifle_status`: accepts `rifle_id` (id or serial/rifle_number); returns rifle row with normalized `rifle_id`.
- `assign_rifle`: requires `rifle_id` and `profile_id` or `cadet_id`. Validates rifle availability, resolves cadet, prevents duplicate active assignments, wraps in transaction: update rifles.status → 'assigned', insert rifle_assignments, log to rifle_logs; returns assignment details.
- `return_rifle`: validates active assignment for that rifle + cadet, transactionally sets rifles.status → 'available' and updates assignment to `returned` with `returned_at`, logs action.
- `get_cadet_rifle`: returns current active assignment (joins rifles + assignments) for a cadet.
- `get_recent_activities`: returns recent rifle_logs joined to rifles and cadet names (supports borrowers mapping).
- `get_rifle_statistics`, `get_current_assignments`, `resolve_cadet`: utility endpoints for dashboards and lookups.

Schema compatibility helpers:
- Determine cadet FK column dynamically (prefer `cadet_profile_id`, else `cadet_id`, else legacy `borrower_id`).
- When legacy `borrower_id` is required, create (or reuse) a deterministic borrowers mapping for each cadet profile (`temp_id = 'CADET_PROFILE_{id}'`).

Invariants & guards:
- Only available rifles can be assigned; only one active assignment per cadet.
- Use transactions for assign/return; on any failure, rollback and return a precise error.
- Log API access (`API_ACCESS`), operations (`API_OPERATION`), and modifications (`DATA_MODIFICATION`) in the security logger.

### 6) Grades
- Admin can add composite grade (WW 30%, PT 50%, QE 20%) into `total_grade`.
- Add multiple quiz scores per cadet with percentage = `score / max_score * 100`.
- Cadets can view their own grades.

### 7) Reports
- Generate different report types (attendance, grades, cadet summary, performance) over date ranges.
- Reports view shows charts (unique attendees, scans, active days), platoon breakdown and daily trends with filters.

### 8) Announcements
- Create with priority, target audience, optional expiry.
- Roles allowed to create: `admin`, `commandant`, `1cl`, `2cl`. Others view only.

### 9) Backup Management
- Manual backup creation (optional encryption flag), backup system test (DB accessible + backups dir writable), recent history with status badges and download links.

### 10) Security Dashboard
- Filter logs by event type, severity, date range; show recent alerts, active sessions, failed logins (last hour), total events.
- Severity badge and event icon mapping.

### 11) System Setup & Settings
- Setup flows for QR attendance and permissions as needed.
- Global settings page for administrators, plus user‑level settings.

### 12) Missing IDs
- Cadets can file missing ID; Admin can manage active requests. Sidebar badge shows active count where `status='active'` and `expiry_date > NOW()`.

## UI/UX Requirements
- Consistent dark UI cards, grids, and nav sidebar.
- Sidebars show badges for pending approvals and missing IDs.
- Provide responsive layouts; mobile nav button to toggle sidebar.

## Data Transforms & Invariants (Critical)
- Address formatting: Always `City Province` (single space), collapse multiple spaces.
- Region normalization: Map to roman/short codes where possible (e.g., `IV-A`, `NCR`).
- Contact numbers: Normalize to `0XXXXXXXXXX` and emit CSV as `="0…"` to keep leading zero in Excel.
- Print layout: 3x3 IDs per A4 portrait page; QR‑only pages use larger QR size (≈50mm) and consistent alignment top‑left.
- Attendance: prefer `attendance_records` for modern charts; fall back to `attendance_logs` when required. Do not double count.
- Term filtering invariant: all feature queries that list cadets (attendance, documents, grades, reports, dashboards) must filter by selected `school_year` + `semester` AND require `cadet_enrollments.enrollment_status='enrolled'`.

## Security Events (examples)
- login_success/login_failed, account_locked, password_changed, admin_access, data_modification, backup_completed/failed, suspicious_activity, data_access.
- Always store IP and user agent.

## Endpoints & Actions (non‑exhaustive)
- POST `/api/attendance_operations` `{ action:'record_attendance', school_year, semester, event_name, profile_id|cadet_id }` -> `{ success, message }`.
- POST forms for announcements create/delete, registration approvals, backup create/test, grades/quiz add.

## Acceptance Criteria
- All pages load with role‑guarding and proper redirects; no unauthenticated access.
- CSVs open in Excel preserving leading zeros; addresses/regions formatted as specified.
- Attendance dashboard: QR scan works end‑to‑end and updates stats; lookup filters function.
- Term selector: switching `school_year`/`semester` changes all counts/lists/exports; dropped/non-enrolled cadets are excluded for that term.
- 2nd semester verification: returning cadets can verify/update profile, reset password, set PIN, and become enrolled for the selected 2nd semester.
- ID print renders 9 cards per A4 page without overlaps; separate QR‑only pages scale uniformly.
- Security dashboard shows events, filters work; backups can be created and appear in history with accurate file size/time.

## Build Order (Recommended)
1) Auth/session, roles, navigation, SecurityLogger.
2) Data model and migrations for core tables.
3) Registration + Approvals.
4) Cadet/Officer/Admin dashboards.
5) QR attendance (scanner, API, records, dashboard analytics).
6) Document generation (UI + CSV rules).
7) ID card generator with print CSS.
8) Grades + Reports.
9) Announcements.
10) Backup management + Security dashboard.
11) Settings and setup flows.
12) Missing IDs + sidebar badges.

## Test Plan (Key Scenarios)
- Registration approval paths: single, selected, all.
- Attendance scan: base64 JSON, legacy AES, numeric fallback; failure messages for invalid scans.
- CSV outputs: Roster and Cadet Profile with exact column names and transforms.
- ID print preview: A4 portrait 3x3, QR‑only scaling.
- Backup: test passes/warns as appropriate; completed backups downloadable.
- Security dashboard filters by severity/event type/date.

## Non‑Goals
- No hosting/deployment steps. No third‑party auth. No external analytics.

Follow these requirements precisely to rebuild the system. Name files and implement logic as specified so the documentation (Modules and Core Features) aligns with the code structure you produce.
