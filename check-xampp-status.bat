@echo off
echo XAMPP Status Checker
echo ===================
echo.

REM Check XAMPP status
powershell -ExecutionPolicy Bypass -File "%~dp0xampp-auto-startup-solution.ps1" -Action status

echo.
echo Press any key to exit...
pause > nul