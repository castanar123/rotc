# User Management

![User Management](../images/user_management.png)

## Admin Guide
- Create, edit, and deactivate users.
- Assign roles (admin, commandant, 1cl, 2cl, basic-cadet).

## Developer Notes
- Entry points:
  - `user_management.php`, `user_management_secure.php`
  - `add_user.php`, `edit_user.php`, `delete_user.php`, `view_user.php`
- Behavior:
  - Validates role-based permissions; admin-only operations.
  - Touches `users` and related `cadet_profiles`.
- Invariants:
  - Role and approval gates enforced on admin pages.

## Screenshots
- User list, create/edit forms
