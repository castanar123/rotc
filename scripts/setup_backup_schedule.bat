@echo off
REM setup_backup_schedule.bat - Setup automated backups for ROTC Rifle Management System
REM This script creates a Windows Task Scheduler task for daily backups

echo ========================================
echo ROTC Rifle Management System
echo Backup Schedule Setup
echo ========================================
echo.

REM Get the current directory (should be the scripts folder)
set SCRIPT_DIR=%~dp0
set PROJECT_DIR=%SCRIPT_DIR%..
set PHP_SCRIPT=%SCRIPT_DIR%scheduled_backup.php

echo Project Directory: %PROJECT_DIR%
echo PHP Script: %PHP_SCRIPT%
echo.

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP is not available in the system PATH.
    echo Please ensure PHP is installed and added to your PATH environment variable.
    echo You can usually find PHP at: C:\xampp\php\php.exe
    echo.
    pause
    exit /b 1
)

echo PHP is available.
echo.

REM Check if the backup script exists
if not exist "%PHP_SCRIPT%" (
    echo ERROR: Backup script not found at: %PHP_SCRIPT%
    echo Please ensure the scheduled_backup.php file exists.
    echo.
    pause
    exit /b 1
)

echo Backup script found.
echo.

REM Test the backup script
echo Testing backup script...
php "%PHP_SCRIPT%" --test
if %errorlevel% neq 0 (
    echo WARNING: Backup script test failed. Please check the configuration.
    echo.
)

echo.
echo Setting up Windows Task Scheduler...
echo.

REM Create the scheduled task
schtasks /create /tn "ROTC Rifle Backup" /tr "php \"%PHP_SCRIPT%\"" /sc daily /st 02:00 /f

if %errorlevel% equ 0 (
    echo SUCCESS: Scheduled task created successfully!
    echo.
    echo Task Details:
    echo - Task Name: ROTC Rifle Backup
    echo - Schedule: Daily at 2:00 AM
    echo - Command: php "%PHP_SCRIPT%"
    echo.
    echo You can modify this task using Windows Task Scheduler:
    echo 1. Press Win+R, type 'taskschd.msc', and press Enter
    echo 2. Navigate to Task Scheduler Library
    echo 3. Find 'ROTC Rifle Backup' task
    echo 4. Right-click and select 'Properties' to modify settings
    echo.
) else (
    echo ERROR: Failed to create scheduled task.
    echo Please run this script as Administrator or create the task manually.
    echo.
    echo Manual setup instructions:
    echo 1. Open Task Scheduler (taskschd.msc)
    echo 2. Click 'Create Basic Task'
    echo 3. Name: ROTC Rifle Backup
    echo 4. Trigger: Daily
    echo 5. Time: 2:00 AM (or your preferred time)
    echo 6. Action: Start a program
    echo 7. Program: php
    echo 8. Arguments: "%PHP_SCRIPT%"
    echo 9. Start in: %PROJECT_DIR%
    echo.
)

REM Show current scheduled tasks related to ROTC
echo Current ROTC-related scheduled tasks:
schtasks /query /tn "ROTC*" 2>nul
if %errorlevel% neq 0 (
    echo No ROTC-related tasks found.
)

echo.
echo ========================================
echo Setup Complete
echo ========================================
echo.
echo Additional Configuration Options:
echo.
echo Environment Variables (optional):
echo - RIFLE_MAX_BACKUPS: Maximum number of backups to keep (default: 30)
echo - RIFLE_BACKUP_INTERVAL: Backup interval in hours (default: 24)
echo - RIFLE_CLEANUP_ENABLED: Enable automatic cleanup (default: true)
echo - RIFLE_BACKUP_EMAIL: Email for backup notifications (optional)
echo.
echo To set environment variables:
echo 1. Right-click 'This PC' and select 'Properties'
echo 2. Click 'Advanced system settings'
echo 3. Click 'Environment Variables'
echo 4. Add new system variables as needed
echo.
echo Log Files:
echo - Backup logs: %PROJECT_DIR%\logs\backup_scheduler.log
echo - Backup files: %PROJECT_DIR%\backups\rifle_backups\
echo.
echo To test the backup manually:
echo php "%PHP_SCRIPT%"
echo.
echo To remove the scheduled task:
echo schtasks /delete /tn "ROTC Rifle Backup" /f
echo.

pause