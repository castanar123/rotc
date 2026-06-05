# Module: User Management (Secure) (user_management_secure.php)

- Path: `user_management_secure.php`
- Audience: Admin, Developers
- Purpose: Hardened user management with input validation and audit logging.
- Access & Roles: Admin-only; denies and logs non-admin access.

## Key Operations (AJAX)
- delete_user, update_user_role, toggle_user_status, search_users.

## Security & Logging
- Uses `secure_db` wrapper and `InputValidator`.
- `auditLog` entries for each operation.

## Screenshots
- Add: user_management_secure.png
