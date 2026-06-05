# Database Migration

Use this guide to move the local XAMPP `rotc_db` database to a hosted MySQL database that Vercel can reach.

## Current Local Database

The project currently expects:

```text
host=localhost
port=3306
database=rotc_db
user=root
password=root
```

There are also document-generation `.env` files that reference port `3307` with an empty root password. The main PHP app uses the `includes/db_config.local.php` settings above.

## Export From XAMPP

Start MySQL in XAMPP, then run:

```powershell
.\scripts\export-database.ps1
```

This creates local ignored files under:

```text
backups\migration\
```

The script creates:

- `rotc_db-schema-*.sql` for schema only
- `rotc_db-data-*.sql` for data only
- `rotc_db-full-*.sql` for schema plus data

These exports may contain real user/cadet data. Do not commit them to GitHub.

## Import To Hosted MySQL

Create a hosted MySQL database first. If you restored from an older backup, run the post-restore schema migration before creating the final dump:

```powershell
cmd /c "C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root -proot rotc_db_migration_test < db\post_restore_current_schema.sql"
```

Then import the verified full dump:

```powershell
.\scripts\import-database.ps1 `
  -SqlFile "backups\migration\rotc_db-full-YYYYMMDD-HHMMSS.sql" `
  -HostName "your-public-db-host.example.com" `
  -Port 3306 `
  -User "your_db_user" `
  -Password "your_db_password" `
  -Database "rotc_db"
```

## Vercel Environment Variables

After import, set this in Vercel:

```text
DATABASE_URL=mysql://your_db_user:your_db_password@your-public-db-host.example.com:3306/rotc_db
```

Then redeploy and test:

```text
https://your-vercel-domain.vercel.app/api/db-health.php
```

## Important Security Note

Do not expose XAMPP MySQL directly to the public internet with `root/root`. For hosted deployment, create a dedicated database user with only the permissions the app needs.
