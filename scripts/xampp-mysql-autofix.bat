@echo off
REM XAMPP MySQL Auto-Fix Batch Wrapper
REM This script runs the PowerShell auto-fix script with administrator privileges

echo XAMPP MySQL Auto-Fix System
echo ============================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo Running with administrator privileges...
    echo.
) else (
    echo This script requires administrator privileges.
    echo Please run as administrator or the script will attempt to elevate...
    echo.
    
    REM Attempt to run as administrator
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

REM Get the directory where this batch file is located
set SCRIPT_DIR=%~dp0

REM Run the PowerShell script
echo Executing MySQL auto-fix script...
echo.
powershell -ExecutionPolicy Bypass -File "%SCRIPT_DIR%xampp-mysql-autofix.ps1"

echo.
echo Auto-fix process completed.
echo Check the log file at C:\xampp\logs\mysql-autofix.log for details.
echo.
pause