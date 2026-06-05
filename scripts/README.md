# XAMPP MySQL Auto-Fix System

A comprehensive automated solution to prevent and fix XAMPP MySQL startup issues on Windows systems.

## Overview

This system automatically detects and resolves common MySQL startup problems in XAMPP, including:
- Port conflicts (port 3306)
- Missing dependencies
- Improper privileges
- Data corruption
- Service conflicts
- Permission issues

## Files Included

### Core Scripts
- **`xampp-mysql-autofix.ps1`** - Main PowerShell script that performs all MySQL fixes
- **`xampp-mysql-autofix.bat`** - Batch wrapper for easy execution with admin privileges
- **`xampp-startup-fix.ps1`** - Startup monitoring script that runs on Windows boot
- **`setup-startup-task.ps1`** - Creates Windows Task Scheduler task for automatic startup

### Log Files (Created Automatically)
- **`C:\xampp\logs\mysql-autofix.log`** - Main fix operations log
- **`C:\xampp\logs\startup-autofix.log`** - Startup monitoring log
- **`C:\xampp\logs\task-setup.log`** - Task setup log

## Quick Start

### Option 1: Manual Fix (When MySQL is Already Down)
1. Right-click on `xampp-mysql-autofix.bat`
2. Select "Run as administrator"
3. The script will automatically detect and fix issues
4. Check the log file for details

### Option 2: Automatic Startup Protection
1. Right-click on `setup-startup-task.ps1`
2. Select "Run as administrator"
3. Follow the prompts to create the startup task
4. The system will now automatically fix MySQL issues on boot

## Detailed Usage

### Main Auto-Fix Script

```powershell
# Run with default settings
.\xampp-mysql-autofix.ps1

# Run in silent mode (no prompts)
.\xampp-mysql-autofix.ps1 -Silent

# Specify custom XAMPP path
.\xampp-mysql-autofix.ps1 -XamppPath "C:\custom\xampp"

# Custom log file location
.\xampp-mysql-autofix.ps1 -LogPath "C:\custom\logs\mysql-fix.log"
```

### Startup Monitoring Script

```powershell
# Run with default 30-second delay
.\xampp-startup-fix.ps1

# Custom delay (60 seconds)
.\xampp-startup-fix.ps1 -DelaySeconds 60

# Custom log path
.\xampp-startup-fix.ps1 -LogPath "C:\custom\startup.log"
```

## What the Auto-Fix Does

### 1. Port Conflict Resolution
- Checks if port 3306 is in use by other processes
- Identifies conflicting services (SQL Server, PostgreSQL, etc.)
- Offers to stop conflicting services
- Attempts to change MySQL port if needed

### 2. Service Management
- Stops and restarts MySQL service properly
- Handles service dependencies
- Manages Apache service if needed
- Clears service locks

### 3. Data Corruption Repair
- Backs up existing MySQL data
- Runs MySQL repair utilities
- Rebuilds corrupted indexes
- Restores from backup if needed

### 4. Permission Fixes
- Resets MySQL data directory permissions
- Fixes Windows user account permissions
- Handles MySQL user privilege issues
- Corrects file ownership problems

### 5. Configuration Repair
- Validates my.ini/my.cnf configuration
- Fixes common configuration errors
- Restores default settings if corrupted
- Updates paths and directories

## Troubleshooting

### Common Issues

**Script won't run:**
- Ensure you're running as Administrator
- Check PowerShell execution policy: `Set-ExecutionPolicy RemoteSigned`
- Verify XAMPP is installed in a standard location

**MySQL still won't start:**
- Check the detailed log files
- Manually run XAMPP Control Panel as Administrator
- Verify Windows services are not disabled
- Check for antivirus interference

**Startup task not working:**
- Verify task was created: Open Task Scheduler (taskschd.msc)
- Check task history for errors
- Ensure task is set to run with highest privileges
- Verify script paths are correct

### Log File Locations

All log files are stored in `C:\xampp\logs\` by default:

- **mysql-autofix.log** - Main operations and fixes applied
- **startup-autofix.log** - Boot-time monitoring and fixes
- **task-setup.log** - Task scheduler setup process

### Manual Verification

To verify the system is working:

1. **Check Task Scheduler:**
   ```
   taskschd.msc
   Look for "XAMPP-MySQL-AutoFix" task
   ```

2. **Test Manual Fix:**
   ```
   Stop MySQL service
   Run xampp-mysql-autofix.bat
   Verify MySQL starts
   ```

3. **Check Logs:**
   ```
   Review C:\xampp\logs\mysql-autofix.log
   Look for SUCCESS/ERROR messages
   ```

## Advanced Configuration

### Customizing the Fix Script

Edit `xampp-mysql-autofix.ps1` to modify:
- Default XAMPP installation path
- Port numbers to check
- Backup retention settings
- Repair timeout values

### Startup Delay Adjustment

Modify the startup task to change boot delay:
```powershell
# Edit the task action arguments
-DelaySeconds 60  # Wait 60 seconds instead of 30
```

### Notification Settings

The startup script can show notifications on failure. To disable:
```powershell
# Comment out the MessageBox section in xampp-startup-fix.ps1
```

## Security Considerations

- Scripts require Administrator privileges
- Startup task runs as SYSTEM account
- Log files may contain sensitive path information
- Scripts modify Windows services and registry

## Uninstallation

To remove the auto-fix system:

1. **Remove Startup Task:**
   ```powershell
   Unregister-ScheduledTask -TaskName "XAMPP-MySQL-AutoFix" -Confirm:$false
   ```

2. **Delete Script Files:**
   ```
   Delete the entire scripts folder
   ```

3. **Clean Log Files:**
   ```
   Delete C:\xampp\logs\*autofix*.log
   ```

## Support

If you encounter issues:

1. Check the log files for detailed error messages
2. Verify XAMPP installation path and permissions
3. Ensure Windows services are not disabled by policy
4. Test with antivirus temporarily disabled
5. Run scripts manually to isolate startup vs. fix issues

## Version History

- **v1.0** - Initial release with core auto-fix functionality
- Comprehensive port conflict resolution
- Data corruption repair mechanisms
- Automatic startup integration
- Full logging and monitoring

---

**Created by:** SOLO Coding  
**Last Updated:** January 2025  
**Compatibility:** Windows 10/11, XAMPP 7.x+, PowerShell 5.1+