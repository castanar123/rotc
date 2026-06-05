@echo off
REM Setup Windows Task Scheduler for Hourly ROTC Database Backup
REM Run this batch file as Administrator to create the scheduled task

echo Setting up ROTC Hourly Backup Task...
echo.

REM Define variables
set HOURLY_TASK_NAME=ROTC_Hourly_Backup
set DAILY_TASK_NAME=ROTC_Daily_Backup
set PHP_PATH=C:\xampp\php\php.exe
set HOURLY_SCRIPT_PATH=%~dp0backup_scheduler.php
set DAILY_SCRIPT_PATH=%~dp0cron\daily_backup.php
set LOG_PATH=%~dp0logs\backup_scheduler.log

REM Create logs directory if it doesn't exist
if not exist "%~dp0logs" mkdir "%~dp0logs"

REM Delete existing tasks if they exist
echo Removing existing backup tasks...
schtasks /delete /tn "%HOURLY_TASK_NAME%" /f >nul 2>&1
schtasks /delete /tn "%DAILY_TASK_NAME%" /f >nul 2>&1

REM Create the hourly scheduled task
echo Creating hourly backup task: %HOURLY_TASK_NAME%
schtasks /create /tn "%HOURLY_TASK_NAME%" /tr "\"%PHP_PATH%\" \"%HOURLY_SCRIPT_PATH%\" run >> \"%LOG_PATH%\" 2>&1" /sc hourly /mo 1 /ru SYSTEM /rl HIGHEST /f

if %errorlevel% equ 0 (
    echo SUCCESS: Hourly backup task created successfully!
) else (
    echo ERROR: Failed to create hourly backup task.
    goto :error_exit
)

REM Create the daily scheduled task
echo Creating daily backup task: %DAILY_TASK_NAME%
schtasks /create /tn "%DAILY_TASK_NAME%" /tr "\"%PHP_PATH%\" \"%DAILY_SCRIPT_PATH%\" >> \"%LOG_PATH%\" 2>&1" /sc daily /st 02:00 /ru SYSTEM /rl HIGHEST /f

if %errorlevel% equ 0 (
    echo SUCCESS: Daily backup task created successfully!
) else (
    echo ERROR: Failed to create daily backup task.
    goto :error_exit
)

echo.
echo ========================================
echo BACKUP SCHEDULER SETUP COMPLETE
echo ========================================
echo.
echo Task Details:
echo - Hourly Task: %HOURLY_TASK_NAME%
echo   Schedule: Every hour
echo   Script: %HOURLY_SCRIPT_PATH%
echo.
echo - Daily Task: %DAILY_TASK_NAME%
echo   Schedule: Daily at 2:00 AM
echo   Script: %DAILY_SCRIPT_PATH%
echo.
echo - Log File: %LOG_PATH%
echo.
echo Management Commands:
echo - View tasks: schtasks /query /tn "ROTC_*"
echo - Run hourly manually: schtasks /run /tn "%HOURLY_TASK_NAME%"
echo - Run daily manually: schtasks /run /tn "%DAILY_TASK_NAME%"
echo - Delete tasks: schtasks /delete /tn "ROTC_*" /f
echo.

REM Test the backup scripts
echo Testing backup scheduler...
"%PHP_PATH%" "%HOURLY_SCRIPT_PATH%" status

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Backup scheduler test completed!
    echo.
    echo The hourly backup will run automatically every hour.
    echo Check the backup directories and logs for details:
    echo - Hourly backups: %~dp0backups\hourly\
    echo - Daily backups: %~dp0backups\daily\
    echo - Logs: %LOG_PATH%
) else (
    echo.
    echo WARNING: Backup scheduler test failed.
    echo Check the log file: %LOG_PATH%
)

goto :success_exit

:error_exit
echo.
echo ERROR: Failed to create scheduled tasks.
echo Please run this batch file as Administrator.
echo.
echo Manual setup instructions:
echo 1. Open Windows Task Scheduler
echo 2. Create Basic Task for Hourly Backup
echo 3. Name: %HOURLY_TASK_NAME%
echo 4. Trigger: Hourly (every 1 hour)
echo 5. Action: Start a program
echo 6. Program: %PHP_PATH%
echo 7. Arguments: "%HOURLY_SCRIPT_PATH%" run
echo 8. Run with highest privileges
echo.
echo Repeat for Daily Backup with daily trigger at 2:00 AM
goto :exit

:success_exit
echo.
echo Backup scheduler is now active and will run automatically.
echo Monitor the system through the admin dashboard.

:exit
echo.
echo Press any key to exit...
pause >nul