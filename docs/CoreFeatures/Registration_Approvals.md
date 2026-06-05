# Registration & Approvals (Admin)

![Approvals Table](../images/approvals_table.png)

## Admin Guide
- Approve/reject single users; batch approve/reject; approve all pending.
- Activates cadet profile status on approval.

## Developer Notes
- File: `admin/registration_approvals.php`
- Actions via POST:
  - `approve_single`, `reject_single`, `approve_selected`, `reject_selected`, `approve_all`
- Effects:
  - Users: `approval_status` → `approved|rejected`, `status` → `active|inactive`
  - Cadet profiles: `status` → `Active` (on approval)
- Logging: `SecurityLogger` writes audit events for access and data modifications.
- Pending list query includes profile fields for context.

## UX Details
- Batch form with select-all helper.
- Success/Warning/Error alerts per action.
