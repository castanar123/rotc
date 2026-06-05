# Security Model

- Session-based auth; admin-only sections guard with role checks.
- Roles: `admin`, `commandant`, `1cl`, `2cl`, `basic-cadet`/`basic_cadet`.
- Approval gating: many features require `approval_status = 'approved'` and `status = 'active'`.
- `SecurityLogger` records:
  - Unauthorized access attempts
  - Admin accesses
  - Data modification events (approvals, bulk actions)

## Input Validation
- QR input validated as numeric ID and looked up defensively (users first, then cadet_profiles).
- Document generation filters to approved/active cadets and handles null/malformed fields.

## Data Exposure
- CSV exports omit sensitive info; contain enrollment/profile data necessary for AER.

## Recommendations
- Use HTTPS in production.
- Least-privilege DB user.
- Rotate admin credentials regularly.
