# XAMPP MySQL Auto-Fix Installation Guide

## Quick Installation (Recommended)

### Step 1: Enable Auto-Fix on Startup
1. Right-click on `setup-startup-task.ps1`
2. Select **"Run as administrator"**
3. Follow the prompts to create the startup task
4. Choose **"Yes"** when asked to test the task

✅ **Done!** Your system will now automatically fix MySQL issues on boot.

### Step 2: Test Manual Fix (Optional)
1. Right-click on `xampp-mysql-autofix.bat`
2. Select **"Run as administrator"**
3. The script will check and fix any current MySQL issues

## What You Get

🔧 **Automatic Fixes For:**
- Port 3306 conflicts
- MySQL service startup failures
- Data corruption issues
- Permission problems
- Missing dependencies
- Service conflicts

📊 **Monitoring & Logging:**
- Detailed logs in `C:\xampp\logs\`
- Real-time startup monitoring
- Automatic error detection

🚀 **Startup Protection:**
- Runs automatically on Windows boot
- 30-second delay for system stability
- Silent operation (no interruptions)

## Files Created

```
scripts/
├── xampp-mysql-autofix.ps1     # Main fix script
├── xampp-mysql-autofix.bat     # Easy-run wrapper
├── xampp-startup-fix.ps1       # Startup monitor
├── setup-startup-task.ps1      # Installation script
├── README.md                   # Full documentation
└── INSTALL.md                  # This guide

C:\xampp\logs/
├── mysql-autofix.log           # Fix operations log
├── startup-autofix.log         # Startup monitoring log
└── task-setup.log              # Installation log
```

## Troubleshooting

### "Access Denied" or "Execution Policy" Errors
1. Right-click PowerShell and select **"Run as administrator"**
2. Run: `Set-ExecutionPolicy RemoteSigned`
3. Type **"Y"** to confirm

### MySQL Still Won't Start
1. Check logs in `C:\xampp\logs\mysql-autofix.log`
2. Run `xampp-mysql-autofix.bat` as administrator
3. Restart your computer to test startup protection

### Task Not Created
1. Ensure you ran `setup-startup-task.ps1` as administrator
2. Check Task Scheduler: Press `Win+R`, type `taskschd.msc`
3. Look for "XAMPP-MySQL-AutoFix" task

## Manual Commands

```powershell
# Fix MySQL issues now
.\xampp-mysql-autofix.ps1

# Silent fix (no prompts)
.\xampp-mysql-autofix.ps1 -Silent

# Test startup monitoring
.\xampp-startup-fix.ps1

# Remove startup task
Unregister-ScheduledTask -TaskName "XAMPP-MySQL-AutoFix"
```

## Verification

To verify everything is working:

1. **Check Task Scheduler:**
   - Press `Win+R`, type `taskschd.msc`
   - Find "XAMPP-MySQL-AutoFix" task
   - Status should be "Ready"

2. **Test Manual Fix:**
   - Stop MySQL in XAMPP Control Panel
   - Run `xampp-mysql-autofix.bat`
   - MySQL should start automatically

3. **Check Logs:**
   - Open `C:\xampp\logs\mysql-autofix.log`
   - Look for recent entries and "SUCCESS" messages

## Support

If you need help:
1. Check the detailed `README.md` file
2. Review log files for error messages
3. Ensure XAMPP is installed in standard location
4. Verify Windows services are not disabled

---

**Installation Complete!** 🎉

Your XAMPP MySQL will now automatically fix startup issues.