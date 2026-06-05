# Module: Backup Management (admin/backup_management.php)

- Path: `admin/backup_management.php`
- Audience: Admin, Developers
- Purpose: Manual backups, system tests, history with statuses and downloads.
- Access & Roles: Admin-only.

## Key Actions
- Create manual backup (optional encryption).
- Test backup system (DB check + directory writable).
- Download completed backups.

## Data Sources
- BackupManager::getBackupHistory(), backups/ directory.

## Screenshots
- Add: backup_management_overview.png
