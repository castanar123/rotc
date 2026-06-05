# Module: User Management (user_management.php)

- Path: `user_management.php`
- Audience: Admin, Developers
- Purpose: Admin UI to search, view, add (via register.php), edit, and delete users; stats and role filters.
- Access & Roles: Admin-only; logs admin access via `SecurityLogger`.

## Key UI Actions
- Search users (name, ID, email).
- Filter by role and status.
- Table and grid views with action buttons (edit/view/delete).

## Data Sources
- users (core), cadet_profiles (joined for names in grid view).

## Screenshots
- Add: user_management_overview.png
