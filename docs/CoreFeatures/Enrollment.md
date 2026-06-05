# Enrollment Management

![Enrollment Management](../images/enrollment_management.png)

## Admin Guide
- Create and update enrollment tracking for cadets.
- Review signups and manage MS-level assignments.

## Developer Notes
- Entry points:
  - `enrollment_management.php`
  - `enrollment_tracking.php`
  - `advance_rotc_management.php`
- Behavior:
  - Queries approved/active users and associated `cadet_profiles`.
  - Updates profile fields (course, section, platoon) and enrollment states.
- Important invariants:
  - Operates on `users.approval_status = 'approved'` and `users.status = 'active'`.
  - Profiles expected to be `Active` when users are approved.

## Screenshots
- Pending enrollments
- Edit enrollment modal
