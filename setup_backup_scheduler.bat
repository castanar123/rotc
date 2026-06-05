@echo off
REM Setup Windows Task Scheduler for Daily ROTC Database Backup
REM Run this batch file as Administrator to create the scheduled task

echo Setting up ROTC Backup Tasks (Daily 20:30 & 22:00, Hourly, Prune 22:05)...
echo.

REM Define variables
set TASK_NAME_DAILY_2030=ROTC_Daily_Backup_2030
set TASK_NAME_DAILY_2200=ROTC_Daily_Backup_2200
set TASK_NAME_HOURLY=ROTC_Hourly_Backup
set TASK_NAME_PRUNE=ROTC_Prune_Hourly_2205
set PHP_PATH=C:\xampp\php\php.exe

REM Determine project root directory based on batch location (handles being inside backups\)
setlocal EnableExtensions
set "BASE_DIR=%~dp0"
pushd "%BASE_DIR%" >nul
if exist ".\cron\daily_backup.php" (
    set "PROJ_DIR=%CD%\"
) else (
    cd ..
    if exist ".\cron\daily_backup.php" (
        set "PROJ_DIR=%CD%\"
    ) else (
        echo ERROR: Could not locate cron\daily_backup.php relative to %BASE_DIR%
        popd
        goto :manual
    )
)
popd
echo Resolved project directory: %PROJ_DIR%

set SCRIPT_PATH_DAILY=%PROJ_DIR%cron\daily_backup.php
set SCRIPT_PATH_HOURLY=%PROJ_DIR%cron\hourly_backup.php
set SCRIPT_PATH_PRUNE=%PROJ_DIR%cron\prune_hourly.php
set LOG_PATH=%PROJ_DIR%logs\backup_scheduler.log
set LOG_PATH_DAILY_2030=%PROJ_DIR%logs\daily_2030.log
set LOG_PATH_DAILY_2200=%PROJ_DIR%logs\daily_2200.log
set LOG_PATH_HOURLY=%PROJ_DIR%logs\hourly.log
set LOG_PATH_PRUNE=%PROJ_DIR%logs\prune_2205.log

REM Create logs directory if it doesn't exist
if not exist "%PROJ_DIR%logs" mkdir "%PROJ_DIR%logs"

REM Delete existing tasks if they exist
schtasks /delete /tn "%TASK_NAME_DAILY_2030%" /f >nul 2>&1
schtasks /delete /tn "%TASK_NAME_DAILY_2200%" /f >nul 2>&1
schtasks /delete /tn "%TASK_NAME_HOURLY%" /f >nul 2>&1
schtasks /delete /tn "%TASK_NAME_PRUNE%" /f >nul 2>&1

REM Create DAILY 20:30 task
echo Creating scheduled task: %TASK_NAME_DAILY_2030%
schtasks /create /tn "%TASK_NAME_DAILY_2030%" /tr "cmd /c \"\"%PHP_PATH%\" \"%SCRIPT_PATH_DAILY%\" >> \"%LOG_PATH_DAILY_2030%\" 2>&1\"" /sc daily /st 20:30 /ru SYSTEM /rl HIGHEST /f

REM Create DAILY 22:00 task
echo Creating scheduled task: %TASK_NAME_DAILY_2200%
schtasks /create /tn "%TASK_NAME_DAILY_2200%" /tr "cmd /c \"\"%PHP_PATH%\" \"%SCRIPT_PATH_DAILY%\" >> \"%LOG_PATH_DAILY_2200%\" 2>&1\"" /sc daily /st 22:00 /ru SYSTEM /rl HIGHEST /f

REM Create the HOURLY scheduled task (every 1 hour)
echo Creating scheduled task: %TASK_NAME_HOURLY%
schtasks /create /tn "%TASK_NAME_HOURLY%" /tr "cmd /c \"\"%PHP_PATH%\" \"%SCRIPT_PATH_HOURLY%\" >> \"%LOG_PATH_HOURLY%\" 2>&1\"" /sc hourly /mo 1 /ru SYSTEM /rl HIGHEST /f

REM Create the PRUNE scheduled task (daily at 22:05)
echo Creating scheduled task: %TASK_NAME_PRUNE%
schtasks /create /tn "%TASK_NAME_PRUNE%" /tr "cmd /c \"\"%PHP_PATH%\" \"%SCRIPT_PATH_PRUNE%\" >> \"%LOG_PATH_PRUNE%\" 2>&1\"" /sc daily /st 22:05 /ru SYSTEM /rl HIGHEST /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Backup tasks created successfully!
    echo.
    echo Task Details:
    echo - Daily 20:30 Task: %TASK_NAME_DAILY_2030%
    echo   Script: %SCRIPT_PATH_DAILY%
    echo   Log: %LOG_PATH_DAILY_2030%
    echo - Daily 22:00 Task: %TASK_NAME_DAILY_2200%
    echo   Script: %SCRIPT_PATH_DAILY%
    echo   Log: %LOG_PATH_DAILY_2200%
    echo - Hourly Task: %TASK_NAME_HOURLY% (Every 1 hour)
    echo   Script: %SCRIPT_PATH_HOURLY%
    echo   Log: %LOG_PATH_HOURLY%
    echo - Prune Task (22:05): %TASK_NAME_PRUNE%
    echo   Script: %SCRIPT_PATH_PRUNE%
    echo   Log: %LOG_PATH_PRUNE%
    echo.
    echo You can view/modify this task in Windows Task Scheduler.
    echo To run the tasks manually: schtasks /run /tn "%TASK_NAME_DAILY_2030%" ^|^| schtasks /run /tn "%TASK_NAME_DAILY_2200%" ^|^| schtasks /run /tn "%TASK_NAME_HOURLY%" ^|^| schtasks /run /tn "%TASK_NAME_PRUNE%"
    echo To delete the tasks: schtasks /delete /tn "%TASK_NAME_DAILY_2030%" /f & schtasks /delete /tn "%TASK_NAME_DAILY_2200%" /f & schtasks /delete /tn "%TASK_NAME_HOURLY%" /f & schtasks /delete /tn "%TASK_NAME_PRUNE%" /f
    echo.
    
    REM Test the daily backup script
    echo Testing daily backup script...
    "%PHP_PATH%" "%SCRIPT_PATH_DAILY%"
    
    if %errorlevel% equ 0 (
        echo.
        echo SUCCESS: Backup script test completed successfully!
        echo Check the backup directory and logs for details.
    ) else (
        echo.
        echo WARNING: Daily backup script test failed. Please check the configuration.
        echo Check the log file: %LOG_PATH_DAILY%
    )
    
) else (
    echo.
    echo ERROR: Failed to create scheduled task.
    echo Please run this batch file as Administrator.
    echo.
    echo Manual setup instructions:
    echo 1. Open Windows Task Scheduler
    echo 2. Create Basic Task
    echo 3. Create these tasks:
    echo    - %TASK_NAME_DAILY_2030% (Trigger: Daily at 20:30)
    echo    - %TASK_NAME_DAILY_2200% (Trigger: Daily at 22:00)
    echo    - %TASK_NAME_HOURLY% (Trigger: Hourly, Every 1 hour)
    echo    - %TASK_NAME_PRUNE% (Trigger: Daily at 22:05)
    echo 5. Action: Start a program
    echo 6. Program: %PHP_PATH%
    echo 7a. Arguments (Daily tasks): "%SCRIPT_PATH_DAILY%"
    echo 7b. Arguments (Hourly): "%SCRIPT_PATH_HOURLY%"
    echo 7c. Arguments (Prune): "%SCRIPT_PATH_PRUNE%"
    echo 8. Run with highest privileges
)

echo.
echo Press any key to exit...
pause >nul