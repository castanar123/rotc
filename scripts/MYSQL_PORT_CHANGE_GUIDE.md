# MySQL Port Change Guide

## Overview
This guide explains how to change XAMPP MySQL port from 3306 to 3307 and how to revert back if needed.

## What Was Changed

### 1. MySQL Configuration
- **File**: `C:\xampp\mysql\bin\my.ini`
- **Change**: Added `port = 3307` under `[mysqld]` section

### 2. Database Connection Files Updated
The following files were updated to use port 3307:

- `includes/db.php`
- `rotc-system/includes/db.php`
- `rotc-qr-inventory/includes/db.php`
- `QR/db.php`
- `includes/db_connection.php`
- `investigate_cadet_profiles.php`
- `debug_birthday.php`
- `check_attendance_schema.php`
- `check_dates.php`
- `document_generation/.env`
- `document_generation/config/settings.py`
- `rotc-system/database_diagnostic.php`

## How to Revert to Port 3306

### Step 1: Stop MySQL Service
```powershell
# Stop XAMPP MySQL service
net stop mysql
```

### Step 2: Edit MySQL Configuration
1. Open `C:\xampp\mysql\bin\my.ini` in a text editor (as Administrator)
2. Find the line `port = 3307` under `[mysqld]` section
3. Change it back to `port = 3306` or remove the line entirely (3306 is default)

### Step 3: Update Database Connection Files
Replace all occurrences of:
- `localhost:3307` with `localhost` or `localhost:3306`
- Port `3307` with `3306` in configuration files

### Step 4: Restart MySQL Service
```powershell
# Start XAMPP MySQL service
net start mysql
```

## Automated Revert Script

You can use the provided PowerShell script to automate the revert process:

```powershell
# Run the change-mysql-port.ps1 script with revert option
.\scripts\change-mysql-port.ps1 -NewPort 3306 -OldPort 3307
```

## Verification

After making changes, verify the connection:

1. **Check MySQL is running on correct port**:
   ```cmd
   netstat -an | findstr :3306
   ```

2. **Test database connection**:
   - Access any PHP page that connects to the database
   - Check for connection errors in browser console or PHP error logs

3. **Verify XAMPP Control Panel**:
   - MySQL service should show as "Running" in green

## Troubleshooting

### Port Already in Use
If port 3306 is already in use:
```cmd
# Find what's using port 3306
netstat -ano | findstr :3306

# Kill the process (replace PID with actual process ID)
taskkill /PID <PID> /F
```

### Permission Issues
- Run text editor as Administrator when editing `my.ini`
- Ensure XAMPP services are stopped before making changes

### Connection Errors
- Double-check all database connection files are updated
- Verify MySQL service is running on the correct port
- Check PHP error logs for specific connection issues

## Notes

- Always backup your database before making port changes
- The change affects all applications connecting to this MySQL instance
- Port 3307 was chosen to avoid conflicts with other MySQL installations
- Some documentation and script files may still reference port 3306 for informational purposes